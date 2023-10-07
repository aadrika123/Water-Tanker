<?php

namespace App\Http\Controllers;

use App\BLL\Calculations;
use App\Http\Requests\SepticTank\StoreRequest;
use App\Models\BuildingType;
use App\Models\Septic\StBooking;
use App\Models\Septic\StCancelledBooking;
use App\Models\Septic\StCapacity;
use App\Models\Septic\StUlbCapacityRate;
use App\Models\StDriver;
use App\Models\StResource;
use App\Models\WtLocation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class SepticTankController extends Controller
{
    protected $_paramId;
    protected $_base_url;
    protected $_ulbs;
    protected $_ulbLogoUrl;
    public function __construct()
    {
        $this->_base_url = Config::get('constants.BASE_URL');
        $this->_paramId = Config::get('constants.PARAM_ST_ID');
        $this->_ulbLogoUrl = Config::get('constants.ULB_LOGO_URL');
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

            // $payAmt = $mCalculations->getSepticTankAmount($req->ulbId, $req->capacityId,$req->isResidential);
            $payAmt = $mCalculations->getSepticTankAmount($req->ulbId, $req->ulbArea, $req->buildingType);
            $paymentAmount = ['paymentAmount' => $payAmt];
            $req->merge($paymentAmount);

            DB::beginTransaction();
            $res = $mStBooking->storeBooking($req);                                                                     // Store Booking Informations
            DB::commit();
            return responseMsgs(true, "Booking Added Successfully !!!",  $res, "110201", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110201", "1.0", "", 'POST', $req->deviceId ?? "");
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
                ->where('assign_date', NULL)
                ->orderByDesc('id')
                ->get();
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                $val->vehicle_no = $val->vehicle_id === NULL ? "Not Assign" : $val->vehicle_no;
                $val->driver_name = $val->driver_name === NULL ? "Not Assign" : $val->driver_name;
                $val->driver_mobile = $val->driver_mobile === NULL ? "Not Assign" : $val->driver_mobile;
                $val->cleaning_status = $val->assign_status == '2' ? "Cleaned" : 'Pending';
                return $val;
            });
            return responseMsgs(true, "Septic Tank Booking List !!!", $f_list, "110202", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110202", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Septic Tank Assign Booking
     * | Function - 03
     * | API - 03
     */
    public function listAssignedBooking(Request $req)
    {
        try {
            // Variable initialization
            if ($req->auth['user_type'] != "UlbUser")
                throw new Exception("Unothorished  Access !!!");
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where('assign_status', '1')
                ->orderByDesc('id')
                ->get();
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                $val->vehicle_no = $val->vehicle_id === NULL ? "Not Assign" : $val->vehicle_no;
                $val->driver_name = $val->driver_name === NULL ? "Not Assign" : $val->driver_name;
                $val->driver_mobile = $val->driver_mobile === NULL ? "Not Assign" : $val->driver_mobile;
                $val->cleaning_status = $val->assign_status == '2' ? "Cleaned" : 'Pending';
                return $val;
            });
            return responseMsgs(true, "Septic Tank Assigned Booking List !!!", $f_list, "110203", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110203", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Applied Application 
     * | Function - 04
     * | API - 04
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
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Assign Successfully !!!", $f_list, "110204", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110204", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Assignment with vehicle and Driver
     * | Function - 05
     * | API - 05
     */
    public function assignmentBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'vehicleId' => 'required|integer',
            'driverId' => 'required|integer',
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
            $mStBooking->vehicle_id = $req->vehicleId;
            $mStBooking->driver_id = $req->driverId;
            $mStBooking->assign_date = Carbon::now()->format('Y-m-d');
            $mStBooking->assign_status = '1';
            $mStBooking->save();
            return responseMsgs(true, "Assignment Booking Successfully !!!", "", "110205", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110205", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancel
     * | Function - 06
     * | API - 06
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
            return responseMsgs(true, "Booking Cancelled Successfully !!!", "", "110206", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110206", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancel
     * | Function - 07
     * | API - 07
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
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                $val->cancel_date = Carbon::createFromFormat('Y-m-d', $val->cancel_date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Cancelled Booking List !!!", $f_list->values(), "110207", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110207", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancel
     * | Function - 08
     * | API - 08
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
            $list->booking_date = Carbon::createFromFormat('Y-m-d', $list->booking_date)->format('d-m-Y');
            $list->cleaning_date = Carbon::createFromFormat('Y-m-d', $list->cleaning_date)->format('d-m-Y');

            return responseMsgs(true, "Details Featch Successfully!!!", $list, "110208", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110208", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Driver
     * | Function - 09
     * | API - 09
     */
    public function addDriver(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'driverName' => 'required|string|max:200',
            'driverAadharNo' => 'required|string|max:16',
            'driverMobile' => 'required|digits:10',
            'driverAddress' => 'required|string',
            'driverFather' => 'required|string|max:200',
            'driverDob' => 'required|date_format:Y-m-d|before:' . Carbon::now()->subYears(18)->format('Y-m-d'),
            'driverLicenseNo' => 'required|string|max:50',

        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');

            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization
            $mStDriver = new StDriver();
            DB::beginTransaction();
            $res = $mStDriver->storeDriverInfo($req);                                       // Store Driver Information in Model 
            DB::commit();
            return responseMsgs(true, "Driver Added Successfully !!!",  '', "110209", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110209", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Driver List
     * | Function - 10
     * | API - 10
     */
    public function listDriver(Request $req)
    {
        if ($req->auth['user_type'] == 'Citizen')
            throw new Exception("Unothorized Access !!!");
        try {
            // Variable initialization
            $mStDriver = new StDriver();
            $list = $mStDriver->getDriverList();
            if ($req->auth['user_type'] == 'UlbUser')
                $list = $list->where('ulb_id', $req->auth['ulb_id']);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->driver_dob = Carbon::createFromFormat('Y-m-d', $val->driver_dob)->format('d-m-Y');
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Driver List !!!", $f_list->values(), "110210", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110210", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 11
     * | API - 11
     */
    public function getDriverDetailById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'driverId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStDriver = new StDriver();
            $list = $mStDriver->getDriverDetailsById($req->driverId);
            if (!$list)
                throw new Exception("No Records Found !!!");
            return responseMsgs(true, "Data Fetched !!!", $list, "110211", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110211", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details for Driver
     * | Function - 12
     * | API - 12
     */
    public function editDriver(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'driverId' => 'required|integer',
            'driverName' => 'required|string|max:200',
            'driverAadharNo' => 'required|string|max:16',
            'driverMobile' => 'required|digits:10',
            'driverAddress' => 'required|string',
            'driverFather' => 'required|string|max:200',
            'driverDob' => 'required|date',
            'driverLicenseNo' => 'required|string|max:50',

        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        try {
            $mStDriver = StDriver::find($req->driverId);
            if (!$mStDriver)
                throw new Exception("No Data Found !!!");
            $mStDriver->ulb_id = $req->ulbId;
            $mStDriver->driver_name = $req->driverName;
            $mStDriver->driver_aadhar_no = $req->driverAadharNo;
            $mStDriver->driver_mobile = $req->driverMobile;
            $mStDriver->driver_address = $req->driverAddress;
            $mStDriver->driver_father = $req->driverFather;
            $mStDriver->driver_dob = $req->driverDob;
            $mStDriver->driver_license_no = $req->driverLicenseNo;
            $mStDriver->save();
            return responseMsgs(true, "Driver Details Updated Successfully !!!", '', "110212", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110212", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Agency and ULB Resources
     * | Function - 13
     * | API - 13
     */
    public function addResource(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'vehicleName' => 'required|string|max:200',
            'vehicleNo' => 'required|string|max:16',
            'capacityId' => 'required|integer|digits_between:1,150',
            'resourceType' => 'required|string|max:200',

        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');

            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization
            $mStResource = new StResource();
            DB::beginTransaction();
            $res = $mStResource->storeResourceInfo($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Resoure Added Successfully !!!",  '', "110213", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110213", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Agency and ULB Resources
     * | Function - 14
     * | API - 14
     */
    public function listResource(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mStResource = new StResource();
            $list = $mStResource->getResourceList($req->auth['ulb_id']);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Resource List !!!", $f_list->values(), "110214", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110214", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 15
     * | API - 15
     */
    public function getResourceDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'resourceId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStResource = new StResource();
            $list = $mStResource->getResourceById($req->resourceId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110215", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110215", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Resource
     * | Function - 16
     * | API - 16
     */
    public function editResource(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'resourceId' => 'required|integer',
            'vehicleName' => 'required|string|max:200',
            'vehicleNo' => 'required|string|max:16',
            'capacityId' => 'required|integer|digits_between:1,150',
            'resourceType' => 'required|string|max:200',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        try {
            $mWtResource = StResource::find($req->resourceId);
            if (!$mWtResource)
                throw new Exception("No Data Found !!!");
            $mWtResource->ulb_id = $req->ulbId;
            $mWtResource->vehicle_name = $req->vehicleName;
            $mWtResource->vehicle_no = $req->vehicleNo;
            $mWtResource->capacity_id = $req->capacityId;
            $mWtResource->resource_type = $req->resourceType;
            $mWtResource->save();
            return responseMsgs(true, "Resource Details Updated Successfully !!!", '', "110216", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110216", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Resource
     * | Function - 17
     * | API - 17
     */
    public function vehicleDriverMasterUlbWise(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');
            // Initialize Variable
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mStResource = new StResource();
            $resource = $mStResource->getResourceListForAssign($req->auth['ulb_id']);
            $mStDriver = new StDriver();
            $driver = $mStDriver->getDriverListForAssign()->where('ulb_id', $req->auth['ulb_id']);
            $f_list['listResource'] = $resource->values();
            $f_list['listDriver'] = $driver->values();
            return responseMsgs(true, "Data Fetched Successfully !!!", $f_list, "110217", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110217", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Resource
     * | Function - 18
     * | API - 18
     */
    public function septicTankCleaned(Request $req)
    {

        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');
            // Initialize Variable
            $mStBooking = StBooking::find($req->applicationId);
            if (!$mStBooking)
                throw new Exception("No Data Found !!!");
            $mStBooking->assign_status = '2';
            $mStBooking->save();
            return responseMsgs(true, "Septic Tank Cleaned Successfully !!!", '', "110218", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110218", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Septic Tank Cleaned Booking
     * | Function - 19
     * | API - 19
     */
    public function listCleanedBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d|before:' . date('Y-m-d'),
            'toDate' => $req->fromDate != NULL ? 'required|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d') : 'nullable|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d'),
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            if ($req->auth['user_type'] != "UlbUser")
                throw new Exception("Unothorished  Access !!!");
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('assign_status', '2')
                ->orderByDesc('id')
                ->get();
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            if ($req->fromDate != NULL)
                $list = $list->whereBetween('cleaning_date', [$req->fromDate, $req->toDate])->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                $val->vehicle_no = $val->vehicle_id === NULL ? "Not Assign" : $val->vehicle_no;
                $val->driver_name = $val->driver_name === NULL ? "Not Assign" : $val->driver_name;
                $val->driver_mobile = $val->driver_mobile === NULL ? "Not Assign" : $val->driver_mobile;
                $val->cleaning_status = $val->assign_status == '2' ? "Cleaned" : 'Pending';
                return $val;
            });
            return responseMsgs(true, "Septic Tank Cleaned Booking List !!!", $f_list, "110219", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110219", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Generate Payment Order ID
     * | Function - 20
     * | API - 20
     */
    public function generatePaymentOrderId(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStBooking = StBooking::find($req->applicationId);
            $reqData = [
                "id" => $mStBooking->id,
                'amount' => $mStBooking->payment_amount,
                'workflowId' => "0",
                'ulbId' => $mStBooking->ulb_id,
                'departmentId' => Config::get('constants.WATER_TANKER_MODULE_ID'),
                'auth' => $req->auth,
            ];
            $paymentUrl = Config::get('constants.PAYMENT_URL');
            $refResponse = Http::withHeaders([
                "api-key" => "eff41ef6-d430-4887-aa55-9fcf46c72c99"
            ])
                ->withToken($req->bearerToken())
                ->post($paymentUrl . 'api/payment/generate-orderid', $reqData);

            $data = json_decode($refResponse);

            if (!$data)
                throw new Exception("Payment Order Id Not Generate");
            if ($data->status == false) {
                return responseMsgs(false, collect($data->message)->first()[0] ?? $data->message, json_decode($refResponse), "110220", "1.0", "", 'POST', $req->deviceId ?? "");
            }

            $data->name = $mStBooking->applicant_name;
            $data->email = $mStBooking->email;
            $data->contact = $mStBooking->mobile;
            $data->type = "Septic Tanker";

            $mStBooking->order_id =  $data->data->orderId;
            $mStBooking->save();
            return responseMsgs(true, "Payment OrderId Generated Successfully !!!", $data->data, "110220", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110220", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Applied And Cancelled Application
     * | Function - 21
     * | API - 21
     */
    public function listAppliedAndCancelledApplication(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'Citizen' && $req->auth['user_type'] != 'UlbUser')
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where('citizen_id', $req->auth['id'])
                ->orderByDesc('id')
                ->get();

            $ulb = $this->_ulbs;
            $f_list['listApplied'] = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                return $val;
            })->values();

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
            $f_list['listCancelled'] = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::createFromFormat('Y-m-d', $val->cleaning_date)->format('d-m-Y');
                $val->cancel_date = Carbon::createFromFormat('Y-m-d', $val->cancel_date)->format('d-m-Y');
                return $val;
            })->values();
            return responseMsgs(true, "Data Fetch Successfully !!!", $f_list, "110221", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110221", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Capacity
     * | Function - 22
     * | API - 22
     */
    public function addCapacity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacity' => 'required|integer|unique:st_capacities',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStCapacity = new StCapacity();
            DB::beginTransaction();
            $res = $mStCapacity->storeCapacity($req);                                       // Store Capacity Request
            DB::commit();
            return responseMsgs(true, "Capacity Added Successfully !!!", '', "110222", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            // return $e->getMessage();
            return responseMsgs(false, $e->getMessage(), "", "110222", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Capacity List
     * | Function - 23
     * | API - 23
     */
    public function listCapacity(Request $req)
    {
        try {
            // Variable initialization
            $mStCapacity = new StCapacity();
            $list = $mStCapacity->getCapacityList();
            $f_list = $list->map(function ($val) {
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Capacity List !!!", $f_list, "110223", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110223", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 24
     * | API - 24
     */
    public function getCapacityDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mStCapacity = new StCapacity();
            $list = $mStCapacity->getCapacityById($req->capacityId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110224", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110224", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Capacity
     * | Function - 25
     * | API - 25
     */
    public function editCapacity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityId' => 'required|integer',
            'capacity' => 'required|numeric|unique:st_capacities',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            $mWtCapacity = StCapacity::find($req->capacityId);
            if (!$mWtCapacity)
                throw new Exception("No Data Found !!!");
            $mWtCapacity->capacity = $req->capacity;
            $mWtCapacity->save();
            return responseMsgs(true, "Capacity Details Updated Successfully !!!", '', "110225", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110225", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Capacity Rate ULB wise
     * | Function - 26
     * | API - 26
     */
    public function addCapacityRate(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityId' => 'required|integer|digits_between:1,999999',
            'isResidential' => 'required|boolean',
            'rate' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization
            $mStUlbCapacityRate = new StUlbCapacityRate();
            DB::beginTransaction();
            $res = $mStUlbCapacityRate->storeCapacityRate($req);                                       // Store Capacity Rate Request
            DB::commit();
            return responseMsgs(true, "Capacity Rate Added Successfully !!!",  '', "110226", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110226", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Capacity Rate list
     * | Function - 27
     * | API - 27
     */
    public function listCapacityRate(Request $req)
    {
        try {
            // Variable initialization
            $mStUlbCapacityRate = new StUlbCapacityRate();
            $list = $mStUlbCapacityRate->getUlbCapacityRateList()->where('ulb_id', $req->auth['ulb_id']);
            $f_list = $list->map(function ($val) {
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Capacity Rate List !!!", $f_list, "110227", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110227", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 28
     * | API - 28
     */
    public function getCapacityRateDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityRateId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mWtUlbCapacityRate = new StUlbCapacityRate();
            $list = $mWtUlbCapacityRate->getCapacityRateDetailsById($req->capacityRateId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110228", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110228", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Capacity Rates
     * | Function - 29
     * | API - 29
     */
    public function editCapacityRate(Request $req)
    {
        $validator = Validator::make($req->all(), [
            // 'ulbId' => 'required|integer',
            'capacityId' => 'required|integer',
            'capacityRateId' => 'required|integer',
            'rate' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mWtUlbCapacityRate = StUlbCapacityRate::find($req->capacityRateId);
            if (!$mWtUlbCapacityRate)
                throw new Exception("No Data Found !!!");
            $mWtUlbCapacityRate->ulb_id = $req->ulbId;
            $mWtUlbCapacityRate->capacity_id = $req->capacityId;
            $mWtUlbCapacityRate->rate = $req->rate;
            $mWtUlbCapacityRate->save();
            return responseMsgs(true, "Capacity Rate Updated Successfully !!!", '', "110229", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110229", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Capacity list For Booking
     * | Function - 30
     * | API - 30
     */
    public function getCapacityListForBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'required|integer',
            'isResidential' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $mWtUlbCapacityRate = new StUlbCapacityRate();
            $list = $mWtUlbCapacityRate->getCapacityListForBooking($req->ulbId, $req->isResidential);
            return responseMsgs(true, "Data Fetched !!!", $list, "110230", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110230", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Payment Details By Payment Id
     * | Function - 31
     * | API - 31
     */
    public function getPaymentDetailsByPaymentId(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'paymentId' => 'required|string',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            // Variable initialization
            $ulb = $this->ulbList();
            $mStBooking = new StBooking();
           $payDetails = $mStBooking->getPaymentDetails($req->paymentId);
            // $payDetails['payment_details'] = json_decode($payDetails->payment_details);
            if (!$payDetails)
                throw new Exception("Payment Details Not Found !!!");
            $payDetails->ulb_name = (collect($ulb)->where("id", $payDetails->ulb_id))->value("ulb_name");
            $payDetails->inWords = getIndianCurrency($payDetails->payment_amount) . "Only /-";
            $payDetails->ulbLogo = $this->_ulbLogoUrl . (collect($ulb)->where("id", $payDetails->ulb_id))->value("logo");
            $payDetails->tollFreeNo = (collect($ulb)->where("id", $payDetails->ulb_id))->value("toll_free_no");
            $payDetails->website = (collect($ulb)->where("id", $payDetails->ulb_id))->value("parent_website");
            $payDetails->paymentAgainst = "Water Tanker";
            return responseMsgs(true, "Payment Details Fetched Successfully !!!", $payDetails, '110231', 01, responseTime(), 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110231', 01, "", 'POST', $req->deviceId);
        }
    }


       /**
     * | Get Payment Details By Payment Id
     * | Function - 31
     * | API - 31
     */
    public function getRecieptDetailsByPaymentId($tranId,Request $req)
    {
        try {
            // Variable initialization
            $ulb = $this->ulbList();
            $mStBooking = new StBooking();
            $payDetails = $mStBooking->getRecieptDetails($tranId);
            // $payDetails['payment_details'] = json_decode($payDetails->payment_details);
            if (!$payDetails)
                throw new Exception("Payment Details Not Found !!!");
            $payDetails->ulb_name = (collect($ulb)->where("id", $payDetails->ulb_id))->value("ulb_name");
            $payDetails->inWords = getIndianCurrency($payDetails->payment_amount) . "Only /-";
            $payDetails->ulbLogo = $this->_ulbLogoUrl . (collect($ulb)->where("id", $payDetails->ulb_id))->value("logo");
            $payDetails->tollFreeNo = (collect($ulb)->where("id", $payDetails->ulb_id))->value("toll_free_no");
            $payDetails->website = (collect($ulb)->where("id", $payDetails->ulb_id))->value("parent_website");
            $payDetails->paymentAgainst = "Water Tanker";
            return responseMsgs(true, "Payment Details Fetched Successfully !!!", $payDetails, '110231', 01, responseTime(), 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110231', 01, "", 'POST', $req->deviceId);
        }
    }


    /**
     * | Get List Nuilding Type
     * | Function - 32
     * | API - 32
     */
    public function listBuildingType(Request $req)
    {
        try {
            $mBuildingType = new BuildingType();
            $list = $mBuildingType->getAllBuildingType();
            return responseMsgs(true, "Data Fetch Successfully !!!", $list, '110232', 01, responseTime(), 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110232', 01, "", 'POST', $req->deviceId);
        }
    }

    /**
     * | Get Ulb list from juidco database from GuzzleHttp  ===================================================
     * | Function - 33
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

    /**
     * | Get ULB Wise Location List
     * | Function - 34
     * | API - 34
     */
    public function listUlbWiseLocation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'required|integer',
            'isInUlb' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()->first()];
        }
        try {
            $mWtLocation = new WtLocation();
            $list = $mWtLocation->listLocationforSepticTank($req->ulbId,$req->isInUlb);
            // $list = collect($list)->where('inside_ulb',$req->insideUlb);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Location List !!!", $f_list, "110234", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110234", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }
}
