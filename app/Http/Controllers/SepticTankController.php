<?php

namespace App\Http\Controllers;

use App\BLL\Calculations;
use App\Http\Controllers\Payment\RazorpayPaymentController;
use App\Http\Requests\PaymentCounterReq;
use App\Http\Requests\SepticTank\StoreRequest;
use App\MicroServices\DocUpload;
use App\Models\BuildingType;
use App\Models\ForeignModels\TempTransaction;
use App\Models\ForeignModels\UlbMaster;
use App\Models\ForeignModels\WfRole;
use App\Models\ForeignModels\WfRoleusermap;
use App\Models\Septic\StBooking;
use App\Models\Septic\StCancelledBooking;
use App\Models\Septic\StCapacity;
use App\Models\Septic\StChequeDtl;
use App\Models\Septic\StTransaction;
use App\Models\Septic\StUlbCapacityRate;
use App\Models\StDriver;
use App\Models\StReassignBooking;
use App\Models\StResource;
use App\Models\User;
use App\Models\WtLocation;
use App\Repository\Payment\Concrete\PaymentRepository;
use App\Repository\Payment\Interfaces\iPayment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\App;

class SepticTankController extends Controller
{
    protected $_paramId;
    protected $_base_url;
    protected $_ulbs;
    protected $_ulbLogoUrl;
    protected $_propertyModuleUrl;
    public function __construct()
    {
        $this->_base_url = Config::get('constants.BASE_URL');
        $this->_paramId = Config::get('constants.PARAM_ST_ID');
        $this->_ulbLogoUrl = Config::get('constants.ULB_LOGO_URL');
        $this->_propertyModuleUrl = Config::get("constants.ID_GENERATE_URL");
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
            $user = Auth()->user();
            // Variable initialization
            $mStBooking = new StBooking();
            $mCalculations = new Calculations();

            $generatedId = $mCalculations->generateId($this->_paramId, $req->ulbId);          // Generate Booking No
            $bookingNo = ['bookingNo' => $generatedId];
            $req->merge($bookingNo);

            // $payAmt = $mCalculations->getSepticTankAmount($req->ulbId, $req->capacityId,$req->isResidential);
            $payAmt = $mCalculations->getSepticTankAmount($req->ulbId, $req->ulbArea, $req->buildingType);
            $paymentAmount = ['paymentAmount' => round($payAmt)];
            $req->merge($paymentAmount);

            DB::beginTransaction();
            $res = $mStBooking->storeBooking($req);                                                                     // Store Booking Informations
            DB::commit();
            #_Whatsaap Message
            // if (strlen($req->mobile) == 10) {

            //     $whatsapp2 = (Whatsapp_Send(
            //         $req->mobile,
            //         "wt_booking_initiated",
            //         [
            //             "content_type" => "text",
            //             [
            //                 $req->applicantName ?? "",
            //                 $req->paymentAmount,
            //                 "septic tanker",
            //                 $req->bookingNo,
            //                 "87787878787 "
            //             ]
            //         ]
            //     ));
            // }
            return responseMsgs(true, "Booking Added Successfully !!!",  $res, "110201", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110201", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    public function stAgencyDashboard(Request $req)
    {
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception("Unauthorized Access !!!");
            // Variable initialization           
            $ulbId = Auth()->user()->ulb_id;
            $ulbDtl = UlbMaster::find($ulbId);
            $mWtBooking = new StBooking();
            $todayBookings = $mWtBooking->todayBookings($ulbId)->get();

            $mWtCancellation = new StCancelledBooking();
            $todayCancelBookings = $mWtCancellation->todayCancelledBooking($ulbId);

            $retData['todayTotalCleaning'] = $todayBookings->count('id');
            $retData['todayOutForCleaning'] = $todayBookings->where('is_vehicle_sent', 1)->count('id');
            $retData['todayCleaned'] = $todayBookings->where('is_vehicle_sent', 2)->count('id');
            $retData['todayTotalCancelCleaning'] = $todayCancelBookings->count();
            $retData['agencyName'] =   $ulbDtl->ulb_name;
            // $retData['waterCapacity'] =  $agencyDetails->dispatch_capacity;
            return responseMsgs(true, "Data Fetched Successfully !!!", $retData, "110158", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110158", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Septic Tank Booking
     * | Function - 02
     * | API - 02
     */
    public function listBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d|before:' . date('Y-m-d'),
            'toDate' => $req->fromDate != NULL ? 'required|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d') : 'nullable|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d'),
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }

        try {
            // Variable initialization
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception("Unothorished  Access !!!");
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where('assign_date', NULL)
                ->where('payment_status', 1)
                ->orderByDesc('id')
                ->get();
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            if ($req->fromDate != NULL)
                $list = $list->whereBetween('booking_date', [$req->fromDate, $req->toDate])->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
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
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception("Unothorished  Access !!!");
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where('assign_status', '1')
                ->where('delivery_track_status', '0')
                ->whereNull('str.application_id')
                ->orderByDesc('id')
                ->get();
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
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
     * | Get List of Applied Application 
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
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
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
        // $validator = Validator::make($req->all(), [
        //     'applicationId' => 'required|integer',
        //     'vehicleId' => 'required|integer',
        //     'driverId' => 'required|integer',
        // ]);

        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'vehicleId' => 'required|integer',
            'driverId' => 'required|integer',
        ], [
            'applicationId.required' => 'The application ID is required.',
            'applicationId.integer' => 'The application ID must be an integer.',
            'vehicleId.required' => 'The vehicle ID is required. Please select a vehicle.',
            'vehicleId.integer' => 'The vehicle ID must be an integer.',
            'driverId.required' => 'The driver ID is required. Please select a driver.',
            'driverId.integer' => 'The driver ID must be an integer.',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
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
            $driver = StDriver::find($req->driverId);
            if (!$driver) {
                throw new Exception("Driver Not Found !!!");
            }

            $driverName = $driver->driver_name;
            $driverContact = $driver->driver_mobile;
            #_Whatsaap Message
            if (strlen($mStBooking->mobile) == 10) {

                $whatsapp2 = (Whatsapp_Send(
                    $mStBooking->mobile,
                    "wt_driver_assignment",
                    [
                        "content_type" => "text",
                        [
                            $mStBooking->applicant_name, $driverName,
                            $driverContact,
                            "septic tank",
                            $mStBooking->booking_no,
                            "1800123123 "
                        ]
                    ]
                ));
            }
            return responseMsgs(true, "Assignment Booking Successfully !!!", "", "110205", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110205", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Cancellation 
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
            return validationErrorV2($validator);
        }
        try {
            $user = $req->auth;
            $req->merge(['cancelledById' => $user["id"], 'cancelledBy' => $user['user_type']]);
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
            $cancelledBooking->cancelled_by = $req->cancelledBy;
            $cancelledBooking->cancelled_by_id = $req->cancelledById;
            $cancelledBooking->setTable('st_cancelled_bookings');
            $cancelledBooking->save();
            $mStBooking->delete();
            return responseMsgs(true, "Booking Cancelled Successfully !!!", "", "110206", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110206", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List of Cancelled Booking
     * | Function - 07
     * | API - 07
     */
    public function listCancelBooking(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'Citizen' && in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mStCancelledBooking = new StCancelledBooking();
            $list = $mStCancelledBooking->getCancelledBookingList()
                // ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->orderByDesc('id')
                ->get();
            if ($req->auth['user_type'] == 'Citizen')
                $list = $list->where('citizen_id', $req->auth['id']);
            if (in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                $list = $list->where('ulb_id', $req->auth['ulb_id']);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                $val->cancel_date = Carbon::parse($val->cancel_date)->format('d-m-Y');
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
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mStBooking = new StBooking();
            $ulb = $this->_ulbs;
            $list = $mStBooking->getApplicationDetailsById($req->applicationId);
            if (!$list)
                throw new Exception("No Application Found !!!");
            $list->ulb_name = (collect($ulb)->where("id", $list->ulb_id))->value("ulb_name");
            $list->booking_date = Carbon::parse($list->booking_date)->format('d-m-Y');
            $list->cleaning_date = Carbon::parse($list->cleaning_date)->format('d-m-Y');

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
        $user = new User();
        $validator = Validator::make($req->all(), [
            'driverName' => 'required|string|max:200',
            'driverAadharNo' => 'required|string|max:16',
            'driverMobile' => 'required|digits:10',
            'driverAddress' => 'required|string',
            'driverFather' => 'required|string|max:200',
            'driverDob' => 'required|date_format:Y-m-d|before:' . Carbon::now()->subYears(18)->format('Y-m-d'),
            'driverLicenseNo' => 'required|string|max:50',
            'driverEmail' => "required|string|email|unique:" . $user->getConnectionName() . "." . $user->getTable() . ",email",


        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
            return validationErrorV2($validator);
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');

            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization

            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $reqs = [
                "name" =>  $req->driverName,
                "email" => $req->driverEmail,
                "password" => $req->password ? $req->password : ("Basic" . '@' . "12345"),
                "mobile" => $req->driverMobile,
                "address"   => $req->driverAddress,
                "ulb" => $req->ulbId,
                "userType" =>  "Septic-Driver",
            ];

            $roleModle = new WfRole();
            $dRoleRequest = new Request([
                "wfRoleId" => $roleModle->getSepticTankDriverRoleId(),
                "createdBy" => $req->auth['id'],
            ]);

            $mStDriver = new StDriver();
            $waterTankerController = new WaterTankerController();
            DB::beginTransaction();
            DB::connection("pgsql_master")->beginTransaction();
            $userId = $waterTankerController->store($reqs);                                                // Create User in User Table for own Dashboard and Login
            $req->merge(['UId' => $userId]);
            $dRoleRequest->merge([
                "userId" => $userId,
            ]);
            $insertRole = (new WfRoleusermap())->addRoleUser($dRoleRequest);
            $res = $mStDriver->storeDriverInfo($req);                                       // Store Driver Information in Model 
            DB::commit();
            DB::connection("pgsql_master")->commit();
            return responseMsgs(true, "Driver Added Successfully !!!",  '', "110209", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection("pgsql_master")->rollBack();
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
            if (in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                $list = $list->where('ulb_id', $req->auth['ulb_id']);
            $ulb = $this->_ulbs;
            $users = User::where("ulb_id", $req->auth["ulb_id"])->get();
            $f_list = $list->map(function ($val) use ($ulb, $users) {
                $user = $users->where("id", $val->u_id)->first();
                $val->email = $user ? $user->email : "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->driver_dob = Carbon::parse($val->driver_dob)->format('d-m-Y');
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
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
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mStDriver = new StDriver();
            $list = $mStDriver->getDriverDetailsById($req->driverId);
            if (!$list)
                throw new Exception("No Records Found !!!");
            $user  = User::where("id", $list->u_id)->first();
            $list->email = $user ? $user->email : "";
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
        $mUser = new User();
        $mWtDriver = new StDriver();
        $WtDriver = $mWtDriver->find($req->driverId);
        $validator = Validator::make($req->all(), [
            'driverId' => "required|integer|exists:" . $mWtDriver->getConnectionName() . "." . $mWtDriver->getTable() . ",id",
            'driverName' => 'required|string|max:200',
            'driverAadharNo' => 'required|string|max:16',
            'driverMobile' => 'required|digits:10',
            'driverAddress' => 'required|string',
            'driverFather' => 'required|string|max:200',
            'driverDob' => 'required|date',
            'driverLicenseNo' => 'required|string|max:50',
            "status"    => "nullable|integer|in:1,0",
            'driverEmail' => "required|email|unique:" . $mUser->getConnectionName() . "." . $mUser->getTable() . ",email" . ($WtDriver && $WtDriver->u_id ? ("," . $WtDriver->u_id) : "")
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        try {
            $mStDriver = StDriver::find($req->driverId);
            if (!$mStDriver)
                throw new Exception("No Data Found !!!");
            $user = User::find($mStDriver->u_id);
            $mStDriver->ulb_id = $req->ulbId;
            $mStDriver->driver_name = $req->driverName;
            $mStDriver->driver_aadhar_no = $req->driverAadharNo;
            $mStDriver->driver_mobile = $req->driverMobile;
            $mStDriver->driver_address = $req->driverAddress;
            $mStDriver->driver_father = $req->driverFather;
            $mStDriver->driver_dob = $req->driverDob;
            $mStDriver->driver_license_no = $req->driverLicenseNo;
            if (isset($req->status)) {
                $mStDriver->status = $req->status;
            }
            if (!$user) {

                $waterTankerController = new WaterTankerController();
                $reqs = [
                    "name" =>  $req->driverName,
                    "email" => $req->driverEmail,
                    "password" => $req->password ? $req->password : ("Basic" . '@' . "12345"),
                    "mobile" => $req->driverMobile,
                    "address"   => $req->driverAddress,
                    "ulb" => $req->ulbId,
                    "userType" =>  "Septic-Driver",
                ];
                $userId = $waterTankerController->store($reqs);                                                // Create User in User Table for own Dashboard and Login                
                $mStDriver->u_id = $userId;
            }
            if ($user) {
                isset($req->driverEmail) ? $user->email = $req->driverEmail : "";
                $user->name = $req->driverName;
                $user->mobile = $req->driverMobile;
                $user->address = $req->driverAddress;
                $user->suspended = !(bool)$WtDriver->status;
            }
            $mStDriver->save();
            $user ? $user->update() : "";
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
            return validationErrorV2($validator);
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
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
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mStResource = new StResource();
            $list = $mStResource->getResourceList($req->auth['ulb_id']);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
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
            return validationErrorV2($validator);
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
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
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
            if (isset($req->status)) {
                $mWtResource->status = $req->status;
            }
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
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
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
            return validationErrorV2($validator);
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Initialize Variable
            $mStBooking = StBooking::find($req->applicationId);
            if (!$mStBooking)
                throw new Exception("No Data Found !!!");
            $mStBooking->assign_status = '2';           // After Cleaning the Tank Assign Status is 2
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
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception("Unothorished  Access !!!");
            $mStBooking = new StBooking();
            $list = $mStBooking->getBookingList()
                ->where('assign_status', '2')
                ->orderByDesc('id')
                ->get();
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            if ($req->fromDate != NULL)
                $list = $list->whereBetween('driver_delivery_update_date_time', [$req->fromDate, $req->toDate])->values();
            $ulb = $this->_ulbs;
            $DocUpload = new DocUpload();
            $f_list = $list->map(function ($val) use ($ulb, $DocUpload) {
                $uploadDoc = ($DocUpload->getSingleDocUrl($val));
                $val->doc_path = $uploadDoc["doc_path"] ?? "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
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
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mStBooking = StBooking::find($req->applicationId);
            $reqData = [
                "id" => $mStBooking->id,
                'amount' => $mStBooking->payment_amount,
                'citizenId' => $mStBooking->citizen_id,
                'workflowId' => "0",
                'ulbId' => $mStBooking->ulb_id,
                'departmentId' => Config::get('constants.SEPTIC_TANKER_MODULE_ID'),
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
            $data->data->citizenId = $mStBooking->citizen_id;

            $mStBooking->order_id =  $data->data->orderId;
            $mStBooking->save();
            return responseMsgs(true, "Payment OrderId Generated Successfully !!!", $data->data, "110220", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110220", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    public function generatePaymentOrderIdV2(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mStBooking = StBooking::find($req->applicationId);
            $reqData = [
                "id" => $mStBooking->id,
                'amount' => $mStBooking->payment_amount,
                'workflowId' => "0",
                'ulbId' => $mStBooking->ulb_id,
                'departmentId' => Config::get('constants.SEPTIC_TANKER_MODULE_ID'),
                'auth' => $req->auth,
            ];
            $RazorpayPaymentController = App::makeWith(RazorpayPaymentController::class);
            $refResponse = $RazorpayPaymentController->generateOrderid(new Request($reqData));

            $data = $refResponse->original;

            if (!$data)
                throw new Exception("Payment Order Id Not Generate");
            if ($data["status"] == false) {
                return responseMsgs(false, collect($data->message)->first()[0] ?? $data->message, json_decode($refResponse), "110220", "1.0", "", 'POST', $req->deviceId ?? "");
            }

            $data["data"]["name"] = $mStBooking->applicant_name;
            $data["data"]["email"] = $mStBooking->email;
            $data["data"]["contact"] = $mStBooking->mobile;
            $data["data"]["type"] = "Septic Tanker";

            $mStBooking->order_id =  $data["data"]["orderId"];
            $mStBooking->save();
            return responseMsgs(true, "Payment OrderId Generated Successfully !!!", $data["data"], "110220", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "", "110220", "1.0", "", 'POST', $req->deviceId ?? "");
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
            if ($req->auth['user_type'] != 'Citizen' && !in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mStBooking = new StBooking();
            DB::enableQueryLog();
            $list = $mStBooking->getBookingList()
                // ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where('citizen_id', $req->auth['id'])
                ->orderByDesc('id')
                ->get();

            $ulb = $this->_ulbs;
            $list->map(function ($val) use ($ulb) {
                $val->tranId = StTransaction::select("id")->where("booking_id", $val->id)->whereIn("status", [1, 2])->first()->id ?? "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                return $val;
            });
            //$f_list['listApplied'] = $list->where("is_vehicle_sent", "<>", 2)->values();
            $f_list['listApplied'] = $list->where("is_vehicle_sent", "<>", 2)
                //->where("delivery_track_status", "=", 0)
                ->values();
            //$f_list['listDelivered'] = $list->where("is_vehicle_sent", 2)->values();
            $f_list['listCleaned'] = $list->where("delivery_track_status", 2)->values();

            $mStCancelledBooking = new StCancelledBooking();
            $list = $mStCancelledBooking->getCancelledBookingList()
                // ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
                ->orderByDesc('id')
                ->get();
            if ($req->auth['user_type'] == 'Citizen')
                $list = $list->where('citizen_id', $req->auth['id']);
            if (in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                $list = $list->where('ulb_id', $req->auth['ulb_id']);

            $ulb = $this->_ulbs;
            $f_list['listCancelled'] = $list->map(function ($val) use ($ulb) {
                $val->tranId = StTransaction::select("id")->where("booking_id", $val->id)->whereIn("status", [1, 2])->first()->id ?? "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                $val->cancel_date = Carbon::parse($val->cancel_date)->format('d-m-Y');
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
            return validationErrorV2($validator);
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
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
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
            return validationErrorV2($validator);
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
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtCapacity = StCapacity::find($req->capacityId);
            if (!$mWtCapacity)
                throw new Exception("No Data Found !!!");
            $mWtCapacity->capacity = $req->capacity;
            if (isset($req->status)) {
                $mWtCapacity->status = $req->status;
            }
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
            return validationErrorV2($validator);
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
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
                return $val;
            })->values();
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
            return validationErrorV2($validator);
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
            "status" => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mWtUlbCapacityRate = StUlbCapacityRate::find($req->capacityRateId);
            if (!$mWtUlbCapacityRate)
                throw new Exception("No Data Found !!!");
            $mWtUlbCapacityRate->ulb_id = $req->ulbId;
            $mWtUlbCapacityRate->capacity_id = $req->capacityId;
            $mWtUlbCapacityRate->rate = $req->rate;
            if (isset($req->status)) {
                $mWtUlbCapacityRate->status = $req->status;
            }
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
            return validationErrorV2($validator);
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
            return validationErrorV2($validator);
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
     * | Get Master Data of Water Tanker
     * | Function - 67
     * | API - 67
     */
    public function masterData(Request $req)
    {
        // $redis = Redis::connection();
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency", "JSK"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            // $data1 = json_decode(Redis::get('wt_masters'));                     // Get Value from Redis Cache Memory
            if (1) {                                                      // If Cache Memory is not available
                $data1 = array();
                $mWtCapacity = new StCapacity();
                $mWtDriver = new StDriver();


                $listCapacity = $mWtCapacity->getCapacityList();
                $data1['capacity'] = $listCapacity;

                $users = User::where("ulb_id", $req->auth["ulb_id"])->get();

                $listDriver = $mWtDriver->getDriverListForMasterData($req->auth['ulb_id'])->map(function ($val) use ($users) {
                    $user = $users->where("id", $val->u_id)->first();
                    $val->email = $user ? $user->email : "";
                    return $val;
                });
                $data1['driver'] = $listDriver;
                if (in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                    $data1['driver'] = $listDriver->where('agency_id', NULL)->values();

                $mWtUlbCapacityRate = new StUlbCapacityRate();
                $capacityRate = $mWtUlbCapacityRate->getCapacityRateForMasterData($req->auth['ulb_id']);
                $data1['capacityRate'] = $capacityRate;

                $mWtResource = new StResource();
                $resource = $mWtResource->getVehicleForMasterData($req->auth['ulb_id']);
                if (in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                    $data1['vehicle'] = $resource->where('agency_id', NULL)->values();

                $ulb = $this->_ulbs;
            }
            return responseMsgs(true, "Data Fetched !!!", $data1, "110167", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110167", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return validationErrorV2($validator);
        }
        try {
            $mWtLocation = new WtLocation();
            $list = $mWtLocation->listLocationforSepticTank($req->ulbId, $req->isInUlb);
            // $list = collect($list)->where('inside_ulb',$req->insideUlb);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Location List !!!", $f_list, "110234", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110234", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }
    /**
     * | Get Payment Details By Payment Id
     * | Function - 35
     * | API - 35
     */
    public function getRecieptDetailsByPaymentId(Request $req)
    {
        try {
            // Variable initialization
            $ulb = $this->ulbList();
            $mStBooking = new StBooking();
            $mTransaction = StTransaction::whereIn("status", [1, 2])->find($req->tranId);
            if (!$mTransaction) {
                throw new Exception("Payment Details Not Found !!!");
            }
            $appData = $mStBooking->getRecieptDetails($mTransaction->booking_id);
            if (!$appData)
                throw new Exception("Booking Details Not Found !!!");
            if ($appData->payment_status == 0) {
                throw new Exception("Payment not Done");
            }
            $chequeDtls = $mTransaction->getChequeDtls();
            $mTransaction->cheque_no = $chequeDtls->cheque_no ?? "";
            $mTransaction->cheque_date = $chequeDtls->cheque_date ?? "";
            $mTransaction->bank_name = $chequeDtls->bank_name ?? "";
            $mTransaction->branch_name = $chequeDtls->branch_name ?? "";
            $appData->payment_details = json_decode(json_encode($mTransaction->toArray()));
            $appData->ulb_name = (collect($ulb)->where("id", $appData->ulb_id))->value("ulb_name");
            $appData->ulb_address = (collect($ulb)->where("id", $appData->ulb_id))->value("address");
            $appData->inWords = getIndianCurrency($mTransaction->paid_amount) . "Only /-";
            $appData->ulbLogo = $this->_ulbLogoUrl . (collect($ulb)->where("id", $appData->ulb_id))->value("logo");
            $appData->tollFreeNo = (collect($ulb)->where("id", $appData->ulb_id))->value("toll_free_no");
            $appData->website = (collect($ulb)->where("id", $appData->ulb_id))->value("parent_website");
            $appData->paymentAgainst = "Septic Tanker";
            return responseMsgs(true, "Payment Details Fetched Successfully !!!", $appData, '110233', 01, responseTime(), 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110233', 01, "", 'POST', $req->deviceId);
        }
    }

    /**
     * | Get Feedback From Citizen
     * | Function - 36
     * | API - 36
     */
    public function getFeedback(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'remarks' => 'required|string',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $applicationDetails = StBooking::find($req->applicationId);
            if (!$applicationDetails)
                throw new Exception("Application Not Found !!!");
            $applicationDetails->feedback = $req->remarks;
            $applicationDetails->feedback_date = Carbon::now();
            $applicationDetails->save();
            return responseMsgs(true, "Feedback Submitted Successfully !!!", '', "110236", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110236", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Feedback From Citizen
     * | Function - 37
     * | API - 37
     */
    public function checkFeedback(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $applicationDetails = StBooking::find($req->applicationId);
            if (!$applicationDetails)
                throw new Exception("Application Not Found !!!");
            if ($applicationDetails->feedback_date === NULL)
                throw new Exception("No Any Feedback Against Booking !!!");
            $commentDetails = array();
            $commentDetails['comment'] = $applicationDetails->feedback;
            $commentDetails['comment_date'] = Carbon::parse($applicationDetails->feedback_date)->format('d-m-Y');
            return responseMsgs(true, "Feedback !!!", $commentDetails, "110237", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110237", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * function added by sandeep bara
     */
    public function driverDeliveryList(Request $res)
    {
        try {
            $key = $res->key;
            $formDate = $uptoDate = null;
            if ($res->fromDate) {
                $formDate = $res->fromDate;
            }
            if ($res->uptoDate) {
                $uptoDate = $res->uptoDate;
            }
            $user = $res->auth;
            $data = StBooking::select("st_bookings.*", "st_resources.vehicle_name", "st_resources.vehicle_no", "st_resources.resource_type", "wtl.location")
                ->join("st_drivers", "st_drivers.id", "st_bookings.driver_id")
                ->join("st_resources", "st_resources.id", "st_bookings.vehicle_id")
                ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'st_bookings.location_id')
                ->where("st_drivers.u_id", $user["id"])
                // ->where("st_bookings.status",1)
                ->where("st_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->where('assign_status', 1)
                ->where('delivery_track_status', 0);

            // $reassign = StBooking::select("st_bookings.*","st_resources.vehicle_name","st_resources.vehicle_no","st_resources.resource_type")
            //             ->join("st_reassign_bookings","st_reassign_bookings.application_id","st_bookings.id")
            //             ->join("st_drivers","st_drivers.id","st_reassign_bookings.driver_id")
            //             ->join("st_resources","st_resources.id","st_reassign_bookings.vehicle_id")
            //             ->where("st_drivers.u_id",$user["id"])
            //             ->where("st_bookings.status",1)
            //             ->where("st_bookings.ulb_id",$user["ulb_id"])
            //             ->where('assign_date', '!=', NULL)
            //             ->where('assign_status', 1)
            //             ->where('st_reassign_bookings.delivery_track_status', 0 );

            if ($key) {
                $data = $data->where(function ($where) use ($key) {
                    $where->orWhere("st_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("st_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("st_bookings.mobile", "LIKE", "%$key%");
                });
                // $reassign = $reassign->where(function($where) use($key){
                //     $where->orWhere("st_bookings.booking_no","LIKE","%$key%")
                //     ->orWhere("st_bookings.applicant_name","LIKE","%$key%")
                //     ->orWhere("st_bookings.mobile","LIKE","%$key%");
                // });
            }
            if ($formDate && $uptoDate) {
                $data = $data->whereBetween("assign_date", [$formDate, $uptoDate]);
                // $reassign = $reassign->whereBetween("re_assign_date",[$formDate,$uptoDate]);
            }

            // $data = $data->union($reassign);

            $data = $data->orderBy("cleaning_date", "ASC")
                // ->orderBy("delivery_time","ASC")
                ->get();
            return responseMsgs(true, "Booking list",  $data, "110115", "1.0", responseTime(), 'POST', $res->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $res->deviceId ?? "");
        }
    }

    public function updatedListDeliveryByDriver(Request $request)
    {
        try {
            $user = $request->auth;
            $key = $request->key;
            $formDate = $uptoDate =  null;
            if (!$key) {
                $formDate = $uptoDate = Carbon::now()->format("Y-m-d");
            }
            if ($request->fromDate && $request->uptoDate) {
                $formDate = $request->fromDate;
                $uptoDate = $request->uptoData;
            }
            $data = StBooking::select(
                "st_bookings.*",
                "st_resources.vehicle_name",
                "st_resources.vehicle_no",
                "st_resources.resource_type",
                "st_bookings.delivery_track_status",
                "st_bookings.delivery_comments",
                "st_bookings.delivery_latitude",
                "st_bookings.delivery_longitude",
                "st_bookings.driver_delivery_update_date_time",
                "assign_date AS assign_date",
                "st_bookings.driver_delivery_update_date_time AS update_date_time",
                "wtl.location"
            )
                ->join("st_drivers", "st_drivers.id", "st_bookings.driver_id")
                ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'st_bookings.location_id')
                ->join("st_resources", "st_resources.id", "st_bookings.vehicle_id")
                ->where("st_drivers.u_id", $user["id"])
                ->where("st_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->whereIn('delivery_track_status', [1, 2]);

            $reassign = StBooking::select(
                "st_bookings.*",
                "st_resources.vehicle_name",
                "st_resources.vehicle_no",
                "st_resources.resource_type",
                "st_reassign_bookings.delivery_track_status",
                "st_reassign_bookings.delivery_comments",
                "st_reassign_bookings.delivery_latitude",
                "st_reassign_bookings.delivery_longitude",
                "st_reassign_bookings.driver_delivery_update_date_time",
                "re_assign_date AS assign_date",
                "st_reassign_bookings.driver_delivery_update_date_time AS update_date_time",
                "wtl.location"
            )
                ->join("st_reassign_bookings", "st_reassign_bookings.application_id", "st_bookings.id")
                ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'st_bookings.location_id')
                ->join("st_drivers", "st_drivers.id", "st_reassign_bookings.driver_id")
                ->join("st_resources", "st_resources.id", "st_reassign_bookings.vehicle_id")
                ->where("st_drivers.u_id", $user["id"])
                ->where("st_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->whereIn('st_reassign_bookings.delivery_track_status', [1, 2]);

            if ($key) {
                $data = $data->where(function ($where) use ($key) {
                    $where->orWhere("st_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("st_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("st_bookings.mobile", "LIKE", "%$key%");
                });
                $reassign = $reassign->where(function ($where) use ($key) {
                    $where->orWhere("st_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("st_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("st_bookings.mobile", "LIKE", "%$key%");
                });
            }
            if ($formDate && $uptoDate) {
                $data = $data->whereBetween(DB::raw("cast(st_bookings.driver_delivery_update_date_time as date)"), [$formDate, $uptoDate]);
                $reassign = $reassign->whereBetween(DB::raw("cast(st_reassign_bookings.driver_delivery_update_date_time as date)"), [$formDate, $uptoDate]);
            }

            $data = $data->union($reassign);
            $data = $data->orderBy("update_date_time", "DESC");
            $perPage = $request->perPage ? $request->perPage : 10;
            DB::enableQueryLog();
            $data = $data->paginate($perPage);
            $f_list = [
                "currentPage" => $data->currentPage(),
                "lastPage" => $data->lastPage(),
                "total" => $data->total(),
                "data" => collect($data->items())->map(function ($val) {
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                    $val->assign_date = Carbon::parse($val->assign_date)->format('d-m-Y');
                    return $val;
                }),
            ];
            return responseMsgs(true, "Booking Delivered/Canceled list",  $f_list, "110115", "1.0", responseTime(), 'POST', $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $request->deviceId ?? "");
        }
    }

    public function driverCanceledList(Request $request)
    {
        try {
            $user = $request->auth;
            $key = $request->key;
            $formDate = $uptoDate =  null;
            if (!$key) {
                $formDate = $uptoDate = Carbon::now()->format("Y-m-d");
            }
            if ($request->fromDate && $request->toDate) {
                $formDate = $request->fromDate;
                $uptoDate = $request->toDate;
            }
            // $ulbId = $user["ulb_id"];
            $mWtBooking = new StBooking();
            $data = $mWtBooking->getBookingList()
                // ->leftJoin(
                //     DB::raw("(SELECT DISTINCT application_id FROM st_reassign_bookings WHERE delivery_track_status !=0 )reassign"),
                //     function($join){
                //         $join->on("reassign.application_id","stb.id");
                //     }
                //     )
                ->where("delivery_track_status", 1)
                ->where("assign_status", "<", 2);
            //->where("stb.ulb_id", $ulbId);
            // ->whereNull("reassign.application_id");
            if ($formDate && $uptoDate) {
                $data->whereBetween(DB::raw("CAST(stb.driver_delivery_update_date_time as date)"), [$formDate, $uptoDate]);
            }
            $DocUpload = new DocUpload();
            $perPage = $request->perPage ? $request->perPage : 10;
            $list = $data->paginate($perPage);
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) use ($DocUpload) {
                    $uploadDoc = ($DocUpload->getSingleDocUrl($val));
                    $val->doc_path = $uploadDoc["doc_path"] ?? "";
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                    $val->assign_date =  $val->assign_date;
                    $val->assign_date  = Carbon::parse($val->assign_date)->format('d-m-Y');
                    $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
                    return $val;
                }),
            ];
            return responseMsgs(true, "Driver Cancel Booking List !!!", $f_list, "110152", "1.0", responseTime(), 'POST', $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $request->deviceId ?? "");
        }
    }

    public function updateDeliveryTrackStatus(Request $request)
    {
        $rules = [
            "applicationId" => 'required|digits_between:1,9223372036854775807',
            "status" => 'required|in:1,2',
            "comments" => 'required|string|min:10',
            "latitude" => 'required',
            "longitude" => 'required',
            "document" => 'required|mimes:png,jpg,jpeg,gif',
        ];
        $validated = Validator::make($request->all(), $rules);
        if ($validated->fails()) {
            return validationErrorV2($validated);
        }
        try {
            $user = $request->auth;
            if (!$user || $user["user_type"] != "Septic-Driver") {
                throw new Exception("You are not authorized for this");
            }
            $driver = StDriver::where("u_id", $user["id"])->first();
            $ModelWtBooking =   new StBooking();
            $booking = $ModelWtBooking->find($request->applicationId);
            if (!$booking) {
                throw new Exception("booking not fund");
            }
            $reBooking = null;
            #$booking->getLastReassignedBooking();
            $updateData = $reBooking ? $reBooking : $booking;
            $isReassigned = $reBooking ? true : false;
            if ($updateData->driver_id != $driver->id) {
                throw new Exception("You have not this booking");
            }
            $document = new DocUpload();
            $document = $document->severalDoc($request);
            $document = $document->original["data"];
            $sms = "Canceled";
            if ($request->status == 2) {
                $sms = "Successfully";
            }
            $updateData->delivery_track_status = $request->status;
            $updateData->delivery_latitude = $request->latitude;
            $updateData->delivery_longitude = $request->longitude;
            $updateData->delivery_comments = $request->comments;
            $updateData->driver_delivery_update_date_time = Carbon::now();
            $updateData->unique_id = $document["document"]["data"]["uniqueId"];
            $updateData->reference_no = $document["document"]["data"]["ReferenceNo"];

            if ($updateData->delivery_track_status == 2) {
                $booking->is_vehicle_sent = $updateData->delivery_track_status;
                $booking->assign_status = $updateData->delivery_track_status;
                $booking->delivered_by_driver_id = $driver->id;
                $booking->driver_delivery_date_time = Carbon::now();
            }

            DB::beginTransaction();
            $updateData->update();
            $booking->update();

            DB::commit();
            //  #_Whatsaap Message
            // if (strlen($booking->mobile) == 10) {

            //     $whatsapp2 = (Whatsapp_Send(
            //         $booking->mobile,
            //         "wt_successful_delivery",
            //         [
            //             "content_type" => "text",
            //             [
            //                 $booking->applicant_name ?? "",
            //                 $booking->booking_no,
            //                 "delivered/trip",
            //                 "jharkhandegovernance.com",
            //                 "1800123123 "
            //             ]
            //         ]
            //     ));
            // }
            return responseMsgs(true, $sms, "", "110115", "1.0", "", 'POST', $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $request->deviceId ?? "");
        }
    }

    /**
     * | Re-Assign Booking
     * | Function - 51
     * | API - 51
     */
    public function reassignBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'vehicleId' => 'required|integer',
            'driverId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtBooking = StBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("No Data Found !!!");

            $mWtBookingForReplicate = StBooking::select(
                'id',
                'vehicle_id',
                'driver_id',
                "assign_date as re_assign_date",
                "delivery_track_status",
                "delivery_comments",
                "delivery_latitude",
                "delivery_longitude",
                "unique_id",
                "reference_no",
                "driver_delivery_update_date_time"
            )
                ->where('id', $req->applicationId)->first();
            $mWtBooking->vehicle_id = $req->vehicleId;
            $mWtBooking->driver_id = $req->driverId;

            $mWtBooking->delivery_track_status = 0;
            $mWtBooking->delivery_comments = null;
            $mWtBooking->delivery_latitude = null;
            $mWtBooking->delivery_longitude = null;

            $mWtBooking->unique_id = null;
            $mWtBooking->reference_no = null;
            $mWtBooking->driver_delivery_update_date_time = null;

            $mWtBooking->assign_date = Carbon::now()->format('Y-m-d');


            // Re-Assign booking on Re-assign Table
            $reassign = $mWtBookingForReplicate->replicate();
            $reassign->setTable('st_reassign_bookings');
            $reassign->application_id =  $mWtBookingForReplicate->id;
            // $reassign->re_assign_date =  Carbon::now()->format('Y-m-d');
            DB::beginTransaction();
            $mWtBooking->save();
            $reassign->save();
            DB::commit();
            return responseMsgs(true, "Booking Assignent Successfully !!!", '', "110151", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110151", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List of Re-Assign Booking
     * | Function - 52
     * | API - 52
     */
    public function listReassignBooking(Request $req)
    {
        try {
            $ulbId = $req->auth["ulb_id"];
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($req->fromDate) {
                $fromDate = $req->fromDate;
            }
            if ($req->uptoDate) {
                $uptoDate = $req->uptoDate;
            }
            $mWtReassignBooking = new StReassignBooking();
            $list = $mWtReassignBooking->listReassignBookingOrm();
            $list = $list->where("wb.ulb_id", $ulbId)
                ->whereBetween("wb.assign_date", [$fromDate, $uptoDate])
                ->where("wb.delivery_track_status", "<>", 2)
                ->orderBy("wb.assign_date", "DESC");

            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) {
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                    $val->re_assign_date = Carbon::parse($val->assign_date)->format('d-m-Y');
                    $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
                    return $val;
                }),
            ];

            return responseMsgs(true, "Re-Assign Booking List !!!", $f_list, "110152", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110152", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Location Details By Id
     * | Function - 50
     * | API - 50
     */
    public function getBookingDetailById(Request $req)
    {

        $validator = Validator::make($req->all(), [
            'applicationId' => [
                "required",
                "integer",
                function ($attribute, $value, $fail) {
                    $existsInTable1 = StBooking::where("id", $value)
                        ->exists();
                    if (!$existsInTable1) {
                        $existsInTable1 = StCancelledBooking::where("id", $value)
                            ->exists();
                    }
                    if (!$existsInTable1) {
                        $fail('The ' . $attribute . ' is invalid.');
                    }
                },
            ],
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtBooking = new StBooking();
            $data = $mWtBooking->find($req->applicationId);
            if (!$data) {
                $mWtBooking = new StCancelledBooking();
                $data = $mWtBooking->find($req->applicationId);
            }
            $tranDtls = $data->getAllTrans()->map(function ($val) {
                $chequeDtls = $val->getChequeDtls();
                $val->tran_date = Carbon::parse($val->tran_date)->format("d-m-Y");
                $val->cheque_no = $chequeDtls->cheque_no ?? "";
                $val->cheque_date = $chequeDtls->cheque_date ?? "";
                $val->bank_name = $chequeDtls->bank_name ?? "";
                $val->branch_name = $chequeDtls->branch_name ?? "";
                return $val;
            });
            $appStatus = $this->getAppStatus($req->applicationId);
            $reassign = $data->getLastReassignedBooking();
            $data->booking_status = $appStatus;
            $data->tran_dtls = $tranDtls;
            $data->payment_details = json_decode($data->payment_details);
            $data->booking_date = Carbon::parse($data->booking_date)->format('d-m-Y');
            $data->cleaning_date = Carbon::parse($data->cleaning_date)->format('d-m-Y');
            $data->assign_date = Carbon::parse($reassign ? $reassign->re_assign_date : $data->assign_date)->format('d-m-Y');

            $driver = $reassign ? $reassign->getAssignedDriver() : $data->getAssignedDriver();
            $vehicle = $reassign ? $reassign->getAssignedVehicle() : $data->getAssignedVehicle();

            $data->driver_name = $driver ? $driver->driver_name : "";
            $data->driver_mobile = $driver ? $driver->driver_mobile : "";
            $data->vehicle_no = $vehicle ? $vehicle->vehicle_no : "";
            return responseMsgs(true, "Booking Details!!!", $data, "110150", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110150", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    public function sentVehicle(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtBooking = StBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("Application Not Found !!!");
            // if ($mWtBooking->delivery_date > Carbon::now()->format('Y-m-d'))
            //     throw new Exception("This Booking is Not Delivery Date Today !!!");
            $mWtBooking->is_vehicle_sent = '1';                                                           // 1 - for Vehicle sent
            $mWtBooking->save();
            return responseMsgs(true, "Vehicle Sent Updation Successfully !!!", '', "110156", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110156", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    public function offlinePayment(PaymentCounterReq $req)
    {
        try {

            $user = Auth()->user();
            $userType = $user->user_type;
            $mergData = [
                'departmentId' => Config::get('constants.SEPTIC_TANKER_MODULE_ID'),
                "moduleId" => Config::get('constants.SEPTIC_TANKER_MODULE_ID'),
                "gatewayType"   => "",
                "id" => $req->applicationId,
                "orderId" => "",
                "paymentId" => "",
                "paymentModes" => $req->paymentModes,
                "tranDate" => Carbon::parse(),
                "ulbId" => $user->ulb_id,
                "userId" => $user->id,
                "workflowId" => 0,
                "chequeNo" => $req->chequeNo,
                "bankName" => $req->bankName,
                "chequeDate" => $req->chequeDate,
            ];
            if ($req->isNotWebHook && $user->getTable() != "users") {
                throw new Exception("Citizen Not Allowed");
            }
            if ($req->isNotWebHook && $userType != "JSK") {
                throw new Exception("Only jsk allow");
            }
            $booking = StBooking::find($req->applicationId);
            if ($booking->ulb_id != $user->ulb_id) {
                throw new Exception("this application related to another ulb");
            }
            if (in_array($booking->payment_status, [1, 2])) {
                throw new Exception("Payment Already Done");
            }
            $mTransaction = new StTransaction();
            $idGenrater = new PaymentRepository();
            $tranNo = $idGenrater->generatingTransactionId($booking->ulb_id);
            $mergData["transactionNo"] = $tranNo;
            $mergData["amount"] = $booking->payment_amount;
            $mergData["applicationNo"] = $booking->booking_no;
            $mergData["paidAmount"] = $booking->payment_amount;
            $mergData["empDtlId"] = $user->id;
            $mergData["ulbId"] = $booking->ulb_id;
            $req->merge($mergData);

            $booking->payment_date = Carbon::now();
            $booking->payment_mode = $req->paymentMode;
            $booking->payment_status = in_array($req->paymentMode, ["CASH"], "ONLINE") ? 1 : 2;
            $booking->payment_id = "";
            $booking->payment_details = json_encode($mergData);
            $booking->payment_by_user_id = $user->id;

            $mTransaction->booking_id = $booking->id;
            $mTransaction->ward_id = $booking->ward_id ?? 0;
            $mTransaction->ulb_id = $booking->ulb_id;
            $mTransaction->tran_type = "Septic Tanker Booking";
            $mTransaction->tran_no = $tranNo;
            $mTransaction->tran_date = $booking->payment_date;
            $mTransaction->payment_mode = $booking->payment_mode;
            $mTransaction->paid_amount = $booking->payment_amount;
            $mTransaction->emp_dtl_id = $user->id;
            $mTransaction->emp_user_type = $userType;
            // if (in_array($req->paymentMode, ["CASH"], "ONLINE")) {
            //     $mTransaction->status = 2;
            // }
            if (Str::upper($mTransaction->payment_mode) != 'CASH') {
                $mTransaction->status = 2;
            }


            DB::beginTransaction();
            DB::connection("pgsql_master")->beginTransaction();

            $booking->update();
            $mTransaction->save();
            $req->merge(["tranDate" => Carbon::now()->format("Y-m-d"), "tranId" => $mTransaction->id]);
            $this->postTempTransaction($req);

            DB::commit();
            DB::connection("pgsql_master")->commit();
            $msg = "Payment Accepted Successfully !!!";
            $tranId  = $mTransaction->id;
            $url = "https://aadrikainfomedia.com/citizen/water-tanker-receipt/" . $tranId;
            #_Whatsaap Message
            // if (strlen($booking->mobile) == 10) {

            //     $whatsapp2 = (Whatsapp_Send(
            //         $booking->mobile,
            //         "all_module_payment_receipt",
            //         [
            //             "content_type" => "text",
            //             [
            //                 $booking->applicant_name ?? "",
            //                 $mTransaction->paid_amount,
            //                 "Booking No",
            //                 $booking->booking_no,
            //                 $url
            //             ]
            //         ]
            //     ));
            // }
            return responseMsgs(true, $msg, ["tranId" => $mTransaction->id, "TranNo" => $mTransaction->tran_no], '110169', 01, "", 'POST', $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection("pgsql_master")->rollBack();
            return responseMsgs(false, $e->getMessage(), "", '110169', 01, "", 'POST', $req->deviceId);
        }
    }

    public function postTempTransaction(Request $req)
    {
        $tranReqs = [
            'transaction_id' => $req->tranId,
            'application_id' => $req->applicationId,
            'module_id' => $req->moduleId,
            'workflow_id' => $req->workflowId,
            'transaction_no' => $req->transactionNo,
            'application_no' => $req->applicationNo,
            'amount' => $req->paidAmount,
            'payment_mode' => $req->paymentMode,
            'tran_date' => $req->tranDate,
            'user_id' => $req->empDtlId,
            'ulb_id' => $req->ulbId,
            'cheque_dd_no' => $req->chequeNo,
            'bank_name' => $req->bankName,
            "ward_no" => null,
        ];

        if (!in_array($req->paymentMode, ['CASH', "ONLINE"])) {
            $tradeChq = new StChequeDtl();
            $tradeChq->tran_id = $req->tranId;
            $tradeChq->booking_id = $req->applicationId;
            $tradeChq->cheque_no      = $req->chequeNo;
            $tradeChq->cheque_date    = $req->chequeDate;
            $tradeChq->bank_name      = $req->bankName;
            $tradeChq->branch_name    = $req->branchName;
            $tradeChq->emp_dtl_id     =  $req->empDtlId;
            $tradeChq->save();
        }
        if ($req->payment_mode != 'ONLINE') {
            $mTempTransaction = new TempTransaction();
            $mTempTransaction->tempTransaction($tranReqs);
        }
    }

    public function searchApp(Request $req)
    {
        try {
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            $key = $req->key;
            $fromDate = $uptoDate = null;
            if ($req->fromDate) {
                $fromDate = $req->fromDate;
            }
            if ($req->uptoDate) {
                $uptoDate = $req->uptoDate;
            }
            $list = StBooking::select("*");
            if ($key) {
                $list = $list->where(function ($where) use ($key) {
                    $where->orWhere("booking_no", "ILIKE", "%$key%")
                        ->orWhere("applicant_name", "ILIKE", "%$key%")
                        ->orWhere("mobile", "ILIKE", "%$key%")
                        ->orWhere("holding_no", "ILIKE", "%$key%");
                });
            }
            if ($ulbId) {
                $list = $list->where("ulb_id", $ulbId);
            }
            if ($fromDate && $uptoDate) {
                $list = $list->whereBetween("booking_date", [$fromDate, $uptoDate]);
            }
            $list = $list->orderBy("id", "DESC");
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) {
                    $val->payment_details = json_decode($val->payment_details);
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
                    $val->assign_date = Carbon::parse($val->assign_date)->format('d-m-Y');
                    return $val;
                }),
            ];
            return responseMsgs(true, "Booking list",  $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
        }
    }

    public function getAppStatus($appId)
    {
        $booking = StBooking::find($appId);
        if (!$booking) {
            $booking = StCancelledBooking::find($appId);
        }
        $driver  = $booking->getAssignedDriver();
        $vehicle  = $booking->getAssignedVehicle();
        $status = "";
        if ($booking->getTable() == (new StCancelledBooking())->getTable()) {
            $status = "Booking canceled on " . Carbon::parse($booking->cancel_date)->format("d-m-Y") . " by " . $booking->cancelled_by;
        } elseif ($booking->payment_status == 0) {
            $status = "Payment Pending of amount " . $booking->payment_amount;
        } elseif ($booking->delivery_track_status == 2) {
            $status = "Septic Tanker Cleaned On " . Carbon::parse($booking->driver_delivery_update_date_time)->format("d-m-Y h:i:s A");
        } elseif ($booking->driver_id && $booking->vehicle_id) {
            $status = "Driver (" . $driver->driver_name . ") And Vehicle (" . $vehicle->vehicle_no . ") assigned";
        } elseif (!$booking->driver_id && !$booking->vehicle_id) {
            $status = "Driver And Vehicle not assigned";
        } elseif (!$booking->driver_id && $booking->vehicle_id) {
            $status = "Driver is not assigned But Vehicle assigned ";
        } elseif ($booking->driver_id && !$booking->vehicle_id) {
            $status = "Driver is assigned But Vehicle not assigned ";
        } elseif ($booking->is_vehicle_sent = 1) {
            $status = "Driver is going for cleaning";
        }
        return $status;
    }



    public function listCollection(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d',
            'toDate' => 'nullable|date_format:Y-m-d|after_or_equal:fromDate',
            'paymentMode'  => 'nullable'
        ]);
        if ($validator->fails()) {
            return  $validator->errors();
        }
        try {
            $perPage = $req->perPage ? $req->perPage : 10;

            $paymentMode = null;
            if (!isset($req->fromDate))
                $fromDate = Carbon::now()->format('Y-m-d');
            else
                $fromDate = $req->fromDate;
            if (!isset($req->toDate))
                $toDate = Carbon::now()->format('Y-m-d');
            else
                $toDate = $req->toDate;

            if ($req->paymentMode) {
                $paymentMode = $req->paymentMode;
            }
            $mWtankPayment = new StTransaction();
            $data = $mWtankPayment->Tran($fromDate, $toDate,);
            if ($req->paymentMode != 0)
                $data = $data->where('t.payment_mode', $paymentMode);

            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                'collectAmount' => $paginator->sum('paid_amount')
            ];
            return responseMsgs(true, "SepticTanker Collection List Fetch Succefully !!!", $list, "055017", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    public function ReportDataSepticTanker(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fromDate' => 'required|date_format:Y-m-d',
            'toDate' => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'paymentMode'  => 'nullable',
            'reportType' => 'required',
            'wardNo' => 'nullable',
            'applicationMode' => 'nullable',
            'driverName' => 'nullable',
            'applicationStatus' => 'nullable'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'validation error',
                'errors'  => $validator->errors()
            ], 200);
        }
        try {
            $tran = new StTransaction();
            $response = [];
            $fromDate = $request->fromDate ?: Carbon::now()->format('Y-m-d');
            $toDate = $request->toDate ?: Carbon::now()->format('Y-m-d');
            $perPage = $request->perPage ?: 10;
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            if ($request->reportType == 'dailyCollection') {
                $response = $tran->dailyCollection($fromDate, $toDate, $request->wardNo, $request->paymentMode, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($response) {
                //return response()->json(['status' => true, 'data' => $response, 'msg' => ''], 200);
                return responseMsgs(true, "SepticTanker Collection List Fetch Succefully !!!", $response, "055017", "1.0", responseTime(), "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    public function applicationReportDataWaterTanker(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fromDate' => 'required|date_format:Y-m-d',
            'toDate' => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'paymentMode'  => 'nullable',
            'reportType' => 'required',
            'wardNo' => 'nullable',
            'applicationMode' => 'nullable',
            'driverName' => 'nullable',
            'applicationStatus' => 'nullable'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'validation error',
                'errors'  => $validator->errors()
            ], 200);
        }
        try {
            $booked = new StBooking();
            $cancle = new StCancelledBooking();
            $response = [];
            $fromDate = $request->fromDate ?: Carbon::now()->format('Y-m-d');
            $toDate = $request->toDate ?: Carbon::now()->format('Y-m-d');
            $perPage = $request->perPage ?: 10;
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'bookedApplication') {
                $response = $booked->getBookedList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'assignedApplication') {
                $response = $booked->getAssignedList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $request->driverName, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'cleanedApplication') {
                $response = $booked->getCleanedList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $request->driverName, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'cancleByAgency') {
                $response = $cancle->getCancelBookingListByAgency($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }

            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'cancleByCitizen') {
                $response = $cancle->getCancelBookingListByCitizen($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'cancleByDriver') {
                $response = $booked->getCancelBookingListByDriver($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'All') {
                $response = $booked->allBooking($request);
                $response['user_name'] = $user->name;
            }
            if ($response) {
                return responseMsgs(true, "SepticTanker Application List Fetch Succefully !!!", $response, "055017", "1.0", responseTime(), "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    public function pendingReportDataSepticTanker(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fromDate' => 'required|date_format:Y-m-d',
            'toDate' => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'reportType' => 'required',
            'wardNo' => 'nullable',
            'applicationMode' => 'nullable',
            'waterCapacity' => 'nullable',
            'driverName' => 'nullable',
            'applicationStatus' => 'nullable'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'validation error',
                'errors'  => $validator->errors()
            ], 200);
        }
        try {
            $booked = new StBooking();
            $response = [];
            $fromDate = $request->fromDate ?: Carbon::now()->format('Y-m-d');
            $toDate = $request->toDate ?: Carbon::now()->format('Y-m-d');
            $perPage = $request->perPage ?: 10;
            $page = $request->page ?: 1;
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            if ($request->reportType == 'pendingReport' && $request->applicationStatus == 'pendingAtDriver') {
                $response = $booked->getPendingList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }

            if ($request->reportType == 'pendingReport' && $request->applicationStatus == 'pendingAtAgency') {
                $response = $booked->getPendingAgencyList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'pendingReport' && $request->applicationStatus == 'All') {
                $response = $booked->allPending($request);
                $response['user_name'] = $user->name;
                //$response = response()->json($response);
            }
            if ($response) {
                return responseMsgs(true, "SepticTanker Pending List Fetch Succefully !!!", $response, "055017", "1.0", responseTime(), "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }
}
