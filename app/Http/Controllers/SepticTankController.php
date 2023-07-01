<?php

namespace App\Http\Controllers;

use App\BLL\Calculations;
use App\Http\Requests\SepticTank\StoreRequest;
use App\Models\Septic\StBooking;
use App\Models\Septic\StCancelledBooking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SepticTankController extends Controller
{
    /**
     * | Add Booking 
     * | Function - 01
     * | API - 01
     */

    protected $_paramId;
    protected $_base_url;
    protected $_ulbs;
    public function __construct()
    {
        $this->_base_url = Config::get('constants.BASE_URL');
        $this->_paramId = Config::get('constants.PARAM_ST_ID');
        $this->_ulbs = $this->ulbList();
    }

    /**
     * | Add Septic Tank Booking
     * | Function - 01
     * | API - 01
     */
    public function addBooking(StoreRequest $req)
    {
        try {
            // Variable initialization
            $mStBooking = new StBooking();
            $mCalculations = new Calculations();
            $req->merge(['citizenId' => $req->auth['id']]);

            $generatedId = $mCalculations->generateId($this->_paramId, $req->ulbId);          // Generate Booking No
            $bookingNo = ['bookingNo' => $generatedId];
            $req->merge($bookingNo);

            DB::beginTransaction();
            $res = $mStBooking->storeBooking($req);                                                                     // Store Booking Informations
            DB::commit();
            return responseMsgs(true, "Booking Added Successfully !!!",  $res, "110101", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110101", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Septic Tank Booking
     * | Function - 02
     * | API - 02
     */
    public function listBooking(Request $req)
    {
        try {
            // Variable initialization
            if ($req->auth['user_type'] != "UlbUser")
                throw new Exception("Unothorished  Access !!!");
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->orderByDesc('id')
                ->get();

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d/m/Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d/m/Y');
                $val->vehicle_no = $val->vehicle_no === NULL ? "Not Assign" : $val->vehicle_no;
                $val->driver_name = $val->driver_name === NULL ? "Not Assign" : $val->driver_name;
                $val->driver_mobile = $val->driver_mobile === NULL ? "Not Assign" : $val->driver_mobile;
                return $val;
            });
            return responseMsgs(true, "Septic Tank Booking List !!!", $f_list, "110102", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110102", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Get Applied Application 
     * | Function - 03
     * | API - 03
     */
    public function getappliedApplication(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'Citizen')
                throw new Exception('Anothorished Access !!!');
            // Variable initialization
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where('citizen_id', $req->auth['id'])
                ->orderByDesc('id')
                ->get();

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d/m/Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Assign Successfully !!!", $f_list, "110103", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110103", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }



    /**
     * | Booking Assignment with vehicle and Driver
     * | Function - 04
     * | API - 04
     */
    public function assignmentBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'vehicleNo' => 'required|string|max:15',
            'driverName' => 'required|string',
            'driverMobile' => 'required|digits:10',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('Anothorished Access !!!');
            // Variable initialization
            $mStBooking = StBooking::find($req->applicationId);
            if (!$mStBooking)
                throw new Exception('Application Not Found !!!');
            if ($mStBooking->assign_status == '1')
                throw new Exception('Application Already Assigned !!!');
            $mStBooking->vehicle_no = $req->vehicleNo;
            $mStBooking->driver_name = $req->driverName;
            $mStBooking->driver_mobile = $req->driverMobile;
            $mStBooking->assign_date = Carbon::now()->format('Y-m-d');
            $mStBooking->assign_status = '1';
            $mStBooking->save();
            return responseMsgs(true, "Assignment Booking Successfully !!!", "", "110104", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110104", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancel
     * | Function - 05
     * | API - 05
     */
    public function cancelBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'remarks' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStBooking = StBooking::find($req->applicationId);
            if (!$mStBooking)
                throw new Exception('Application Not Found !!!');
            // if ($mStBooking->assign_status == '1')
            //     throw new Exception('Application Already Assigned !!!');
            $cancelledBooking = $mStBooking->replicate();                                   // Replicate Data fromm Booking to Cancel table
            $cancelledBooking->cancel_by = $req->auth['user_name'];
            $cancelledBooking->remarks = $req->remarks;
            $cancelledBooking->cancel_date = Carbon::now()->format('Y-m-d');
            $cancelledBooking->id =  $mStBooking->id;
            $cancelledBooking->setTable('st_cancelled_bookings');
            $cancelledBooking->save();
            $mStBooking->delete();
            return responseMsgs(true, "Booking Cancelled Successfully !!!", "", "110105", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110105", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancel
     * | Function - 06
     * | API - 06
     */
    public function listCancelBooking(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'Citizen' && $req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mStCancelledBooking = new StCancelledBooking();
            $list = $mStCancelledBooking->getCancelledBookingList()
                // ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->orderByDesc('id')
                ->get();
            if ($req->auth['user_type'] == 'Citizen')
                $list = $list->where('citizen_id', $req->auth['id']);
            if ($req->auth['user_type'] == 'UlbUser')
                $list = $list->where('ulb_id', $req->auth['ulb_id']);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d/m/Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d/m/Y');
                $val->cancel_date = Carbon::createFromFormat('Y-m-d', $val->cancel_date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Cancelled Booking List !!!", $f_list->values(), "110106", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110106", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancel
     * | Function - 07
     * | API - 07
     */
    public function getApplicationDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStBooking = new StBooking();
            $ulb = $this->_ulbs;
            $list = $mStBooking->getApplicationDetailsById($req->applicationId);
            if (!$list)
                throw new Exception("No Application Found !!!");
            $list->ulb_name = (collect($ulb)->where("id", $list->ulb_id))->value("ulb_name");
            $list->booking_date = Carbon::createFromFormat('Y-m-d', $list->booking_date)->format('d/m/Y');
            $list->cleaning_date = Carbon::createFromFormat('Y-m-d', $list->cleaning_date)->format('d/m/Y');

            return responseMsgs(true, "Details Featch Successfully!!!", $list, "110107", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110107", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Ulb list from juidco database from GuzzleHttp
     * | Function - unknown
     * | API - unknown
     */
    public function ulbList()
    {
        $redis = Redis::connection();
        try {
            // Variable initialization
            $data1 = json_decode(Redis::get('ulb_masters'));      // Get Value from Redis Cache Memory
            if (!$data1) {                                                   // If Cache Memory is not available
                $data1 = array();

                $client = new \GuzzleHttp\Client();
                $request = $client->get($this->_base_url . 'api/get-all-ulb'); // Url of your choosing
                $data1 = $request->getBody()->getContents();
                $data1 = json_decode($data1, true)['data'];
                $data1 = collect($data1);
                $redis->set('ulb_masters', json_encode($data1));      // Set Key on ULB masters
            }
            return $data1;
        } catch (Exception $e) {
        }
    }
}
