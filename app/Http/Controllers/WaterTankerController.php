<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\WtAgency;
use App\Models\WtAgencyResource;
use App\Models\WtBooking;
use App\Models\WtCancellation;
use App\Models\WtCapacity;
use App\Models\WtDriver;
use App\Models\WtDriverVehicleMap;
use App\Models\WtHydrationCenter;
use App\Models\WtHydrationDispatchLog;
use App\Models\WtResource;
use Illuminate\Support\Str;
use App\Models\WtUlbCapacityRate;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\BLL\Calculations;
use App\Http\Requests\PaymentCounter;
use App\Http\Requests\PaymentCounterReq;
use App\MicroServices\DocUpload;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\ForeignModels\TempTransaction;
use App\Models\ForeignModels\UlbMaster;
use App\Models\ForeignModels\WfRole;
use App\Models\ForeignModels\WfRoleusermap;
use App\Models\Septic\StBooking;
use App\Models\Septic\StTransaction;
use App\Models\UlbWaterTankerBooking;
use App\Models\User;
use App\Models\WtChequeDtl;
use App\Models\WtLocation;
use App\Models\WtLocationHydrationMap;
use App\Models\WtReassignBooking;
use App\Models\WtTransaction;
use App\Repository\Payment\Concrete\PaymentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Nette\Utils\Random;
use PhpParser\Node\Stmt\Return_;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class WaterTankerController extends Controller
{
    protected $_base_url;
    protected $_paramId;
    protected $_ulbs;
    protected $_ulbLogoUrl;             // ULB Logo URL

    public function __construct()
    {
        $this->_base_url = Config::get('constants.BASE_URL');
        $this->_paramId = Config::get('constants.PARAM_ID');
        $this->_ulbLogoUrl = Config::get('constants.ULB_LOGO_URL');
        $this->_ulbs = $this->ulbList();
    }
    /**
     * | Add Agency 
     * | Function - 01
     * | API - 01
     */
    public function addAgency(Request $req)
    {
        $validator = Validator::make($req->all(), [
            // 'ulbId' => 'required|digits_between:1,200|integer',
            'agencyName' => 'required|string|max:255',
            'ownerName' => 'required|string|max:255',
            'agencyAddress' => 'required|string',
            'password' =>  [
                'required',
                'min:6',
                'max:255',
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:/[@$!%*#?&]/'  // must contain a special character
            ],
            'agencyMobile' => 'required|digits:10',
            'agencyEmail' => 'required|string|email',
            'dispatchCapacity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception("You Are Unauthorized For Add Agency !!!");
            // Variable initialization
            $reqs = [
                "name" =>  $req->agencyName,
                "email" => $req->agencyEmail,
                "password" => $req->password,
                "mobile" => $req->agencyMobile,
                "ulb" => $req->ulbId,
                "userType" =>  "Water-Agency",
            ];
            // return $reqs;
            $mWtAgency = new WtAgency();
            DB::beginTransaction();
            $userId = $this->store($reqs);                                                // Create User in User Table for own Dashboard and Login
            $req->merge(['UId' => $userId]);
            $res = $mWtAgency->storeAgency($req);                                       // Store Agency Request
            DB::commit();
            return responseMsgs(true, "Agency Added Successfully !!!", '', "110101", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            // return $e->getMessage();
            return responseMsgs(false, $e->getMessage(), "", "110101", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    public function addAgencyNotInLocal(Request $reqs)
    {
        try {
            $users =  $reqs->auth;
            $ulb = UlbMaster::find($users["ulb_id"]);
            $reqs->merge([
                'agencyName' => ($ulb->ulb_name ?? "test") . " Water Agency",
                'ownerName' => $users["name"],
                'agencyAddress' => $users["address"],
                'agencyMobile' => $users["mobile"],
                'agencyEmail' => $users["email"],
                'dispatchCapacity' => 0,
                "UId" => $users["id"],
                "ulbId" => $users["ulb_id"],
            ]);
            $mWtAgency = new WtAgency();
            DB::beginTransaction();
            $res = $mWtAgency->storeAgency($reqs);                                       // Store Agency Request
            DB::commit();
            return responseMsgs(true, "Agency Added Successfully !!!", '', "110101", "1.0", responseTime(), 'POST', $reqs->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110101", "1.0", "", 'POST', $reqs->deviceId ?? "");
        }
    }

    /**
     * | Get Agency List 
     * | Function - 02
     * | API - 02
     */
    public function listAgency(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception('You Are Not Aauthorized !!!');
            // Variable initialization
            $mWtAgency = new WtAgency();
            $list = $mWtAgency->getAllAgency()->where('ulb_id', $req->auth['ulb_id']);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val["ulb_name"] = (collect($ulb)->where("id", $val["ulb_id"]))->value("ulb_name");
                $val['date'] = Carbon::parse($val['date'])->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Agency List !!!",  $f_list, "110102", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110102", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Capacity
     * | Function - 03
     * | API - 03
     */
    public function addCapacity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacity' => [
                'required',
                'integer',
                Rule::unique('wt_capacities')->where(function ($q) use ($req) {
                    return $q->where('ulb_id', $req->auth['ulb_id']);
                }),
            ],
        ]);

        if ($validator->fails()) {
            return validationErrorV2($validator);
        }

        try {
            $mWtCapacity = new WtCapacity();
            DB::beginTransaction();
            $res = $mWtCapacity->storeCapacity($req);
            DB::commit();

            return responseMsgs(true, "Capacity Added Successfully !!!", '', "110103", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110103", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Capacity List
     * | Function - 04
     * | API - 04
     */
    public function listCapacity(Request $req)
    {
        try {
            // Variable initialization
            $mWtCapacity = new WtCapacity();
            $list = $mWtCapacity->getCapacityList();
            $f_list = $list->map(function ($val) {
                $val->date = Carbon::parse($val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Capacity List !!!", $f_list, "110104", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110104", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Capacity Rate ULB wise
     * | Function - 05
     * | API - 05
     */
    public function addCapacityRate(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityId' => 'required|integer|digits_between:1,999999',
            // 'ulbId' => 'required|integer|digits_between:1,200',
            'rate' => 'required|integer|gt:0',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization
            $mWtUlbCapacityRate = new WtUlbCapacityRate();
            DB::beginTransaction();
            $res = $mWtUlbCapacityRate->storeCapacityRate($req);                                       // Store Capacity Rate Request
            DB::commit();
            return responseMsgs(true, "Capacity Rate Added Successfully !!!",  '', "110105", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110105", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Capacity Rate list
     * | Function - 06
     * | API - 06
     */
    public function listCapacityRate(Request $req)
    {
        try {
            // Variable initialization
            $mWtUlbCapacityRate = new WtUlbCapacityRate();
            $list = $mWtUlbCapacityRate->getUlbCapacityRateList()->where('ulb_id', $req->auth['ulb_id']);
            $f_list = $list->map(function ($val) {
                $val->date = Carbon::parse($val->date)->format('d/m/Y');
                return $val;
            })->values()->all();
            //$arrayList = $f_list->toArray();
            return responseMsgs(true, "Capacity Rate List !!!", $f_list, "110106", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110106", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Add Hydration Center
     * | Function - 07
     * | API - 07
     */
    public function addHydrationCenter(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'name' => 'required|string',
            'email' => 'required|email|',
            'mobile' => 'required|digits:10',
            'password' =>  [
                'required',
                'min:6',
                'max:255',
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:/[@$!%*#?&]/'  // must contain a special character
            ],
            'waterCapacity' => 'required|numeric',
            'address' => 'required|string',

        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        try {
            // Variable initialization
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception("You Are Unauthorized For Add Hydration Center !!!");
            $reqs = [
                "name" =>  $req->name,
                "email" => $req->email,
                "password" => $req->password,
                "mobile" => $req->mobile,
                "ulb" => $req->ulbId,
                "userType" =>  "Water-Hydration-Center",
            ];
            $mWtHydrationCenter = new WtHydrationCenter();
            DB::beginTransaction();
            $userId = $this->store($reqs);                                                // Create User in User Table for own Dashboard and Login
            $req->merge(['UId' => $userId]);
            $res = $mWtHydrationCenter->storeHydrationCenter($req);                                       // Store Capacity Rate Request
            DB::commit();
            return responseMsgs(true, "Hydration Center Added Successfully !!!",  '', "110107", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110107", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Hydration Center
     * | Function - 08
     * | API - 08
     */
    public function listHydrationCenter(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception("You Are Unauthorized !!!");
            // Variable initialization
            $mWtHydrationCenter = new WtHydrationCenter();
            $list = $mWtHydrationCenter->getHydrationCenterList($req);
            $list = $list->where('ulb_id', $req->auth['ulb_id'])->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::createFromFormat('Y-m-d H:i:s', $val->created_at)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Hydration Center List !!!", $f_list, "110108", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110108", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Driver
     * | Function - 09
     * | API - 09
     */
    // public function addDriver(Request $req)
    // {
    //     $user = new User();
    //     $validator = Validator::make($req->all(), [
    //         'driverName' => 'required|string|max:200',
    //         'driverAadharNo' => 'required|string|max:16',
    //         'driverMobile' => 'required|digits:10',
    //         'driverAddress' => 'required|string',
    //         'driverFather' => 'required|string|max:200',
    //         'driverDob' => 'required|date_format:Y-m-d|before:' . Carbon::now()->subYears(18)->format('Y-m-d'),
    //         'driverLicenseNo' => 'required|string|max:50',
    //         'driverEmail' => "required|string|email|unique:" . $user->getConnectionName() . "." . $user->getTable() . ",email",

    //     ]);
    //     if ($validator->fails()) {
    //         return ['status' => false, 'message' => "validation Error", "errors" => $validator->errors()];
    //     }
    //     try {
    //         if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
    //             throw new Exception('Unauthorized Access !!!');

    //         if ($req->auth['user_type'] == 'Water-Agency')
    //             $req->merge(['agencyId' => DB::table('wt_agencies')->select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id]);

    //         $req->merge(['ulbId' => $req->auth['ulb_id']]);
    //         $reqs = [
    //             "name" =>  $req->driverName,
    //             "email" => $req->driverEmail,
    //             "password" => $req->password ? $req->password : ("Basic" . '@' . "12345"),
    //             "mobile" => $req->driverMobile,
    //             "address"   => $req->driverAddress,
    //             "ulb" => $req->ulbId,
    //             "userType" =>  "Driver",
    //         ];

    //         $roleModle = new WfRole();
    //         $dRoleRequest = new Request([
    //             "wfRoleId" => $roleModle->getDriverRoleId(),
    //             "createdBy" => $req->auth['id'],
    //         ]);
    //         // Variable initialization
    //         $mWtDriver = new WtDriver();
    //         DB::beginTransaction();
    //         DB::connection("pgsql_master")->beginTransaction();
    //         $userId = $this->store($reqs);                                                // Create User in User Table for own Dashboard and Login
    //         $req->merge(['UId' => $userId]);
    //         $dRoleRequest->merge([
    //             "userId" => $userId,
    //         ]);
    //         $insertRole = (new WfRoleusermap())->addRoleUser($dRoleRequest);
    //         $res = $mWtDriver->storeDriverInfo($req);                                       // Store Driver Information in Model 
    //         DB::commit();
    //         DB::connection("pgsql_master")->commit();
    //         return responseMsgs(true, "Driver Added Successfully !!!",  '', "110109", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         DB::connection("pgsql_master")->rollBack();
    //         return responseMsgs(false, $e->getMessage(), "", "110109", "1.0", "", 'POST', $req->deviceId ?? "");
    //     }
    // }


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
            return ['status' => false, 'message' => "validation Error", "errors" => $validator->errors()];
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');

            if ($req->auth['user_type'] == 'Water-Agency')
                $req->merge(['agencyId' => DB::table('wt_agencies')->select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id]);

            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $reqs = [
                "name" =>  $req->driverName,
                "email" => $req->driverEmail,
                "password" => $req->password ? $req->password : ("Basic" . '@' . "12345"),
                "mobile" => $req->driverMobile,
                "address"   => $req->driverAddress,
                "ulb" => $req->ulbId,
                "userType" =>  "Driver",
            ];

            $roleModle = new WfRole();
            $dRoleRequest = new Request([
                "wfRoleId" => $roleModle->getDriverRoleId(),
                "createdBy" => $req->auth['id'],
            ]);
            // Variable initialization
            $mWtDriver = new WtDriver();
            DB::beginTransaction();
            DB::connection("pgsql_master")->beginTransaction();
            $userId = $this->store($reqs);                                                // Create User in User Table for own Dashboard and Login
            $req->merge(['UId' => $userId]);
            $dRoleRequest->merge([
                "userId" => $userId,
            ]);
            $insertRole = (new WfRoleusermap())->addRoleUser($dRoleRequest);
            $res = $mWtDriver->storeDriverInfo($req);                                       // Store Driver Information in Model 
            DB::commit();
            DB::connection("pgsql_master")->commit();
            return responseMsgs(true, "Driver Added Successfully !!!",  '', "110109", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection("pgsql_master")->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110109", "1.0", "", 'POST', $req->deviceId ?? "");
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
            $ulbId = $req->auth['ulb_id'];
            $mWtDriver = new WtDriver();
            $list = $mWtDriver->getDriverList($ulbId);
            if ($req->auth['user_type'] == 'UlbUser')
                $list = $list->where('ulb_id', $req->auth['ulb_id']);
            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id',  DB::table('wt_agencies')->select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id);
            $ulb = $this->_ulbs;
            $users = User::where("ulb_id", $req->auth["ulb_id"])->get();
            $f_list = $list->map(function ($val) use ($ulb, $users) {
                $user = $users->where("id", $val->u_id)->first();
                $val->email = $user ? $user->email : "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->driver_dob = Carbon::parse($val->driver_dob)->format('d/m/Y');
                $val->date = Carbon::parse($val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Driver List !!!", $f_list->values(), "110110", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110110", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Add Agency and ULB Resources
     * | Function - 11
     * | API - 11
     */
    public function addResource(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'vehicleName' => 'required|string|max:200',
            'vehicleNo' => 'required|string|max:16',
            'capacityId' => 'required|integer|digits_between:1,150',
            'resourceType' => 'required|string|max:200',
            'isUlbResource' => 'required|boolean',

        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');

            if ($req->auth['user_type'] == 'Water-Agency')
                $req->merge(['agencyId' => DB::table('wt_agencies')->select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id]);

            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization
            $mWtResource = new WtResource();
            DB::beginTransaction();
            $res = $mWtResource->storeResourceInfo($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Resoure Added Successfully !!!",  '', "110111", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110111", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Agency and ULB Resources
     * | Function - 12
     * | API - 12
     */
    public function listResource(Request $req)
    {
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mWtResource = new WtResource();
            $list = $mWtResource->getResourceList($req->auth['ulb_id']);
            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id', WtAgency::select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Resource List !!!", $f_list->values(), "110112", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110112", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Hydration Center Disatch Logs
     * | Function - 13
     * | API - 13
     */
    public function addHydrationCenerDispatchLog(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'required|integer|digits_between:1,200',
            'agencyId' => 'nullable|integer',
            'vehicleId' => 'required|integer',
            'hydrationCenterId' => 'required|integer',
            'daipatchDate' => 'required|date',
            'capacityId' => 'required|integer|digits_between:1,150',

        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtHydrationDispatchLog = new WtHydrationDispatchLog();
            DB::beginTransaction();
            $res = $mWtHydrationDispatchLog->storeHydrationDispatchLog($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Dispatch Log Added Successfully !!!",  '', "110113", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110113", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Hydration Center Disatch Logs
     * | Function - 14
     * | API - 14
     */
    public function listHydrationCenerDispatchLog(Request $req)
    {
        try {
            // Variable initialization
            $mWtHydrationDispatchLog = new WtHydrationDispatchLog();
            $list = $mWtHydrationDispatchLog->getHydrationCenerDispatchLogList($req);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                return $val;
            });
            return responseMsgs(true, "Resource List !!!", $f_list, "110114", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110114", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Add Hydration Center Disatch Logs
     * | Function - 15
     * | API - 15
     */
    public function addBooking(StoreBookingRequest $req)
    {
        try {
            $user = Auth()->user();
            // Variable initialization
            $mWtBooking = new WtBooking();
            $mCalculations = new Calculations();
            // $bookingStatus = $mCalculations->checkBookingStatus($req->deliveryDate, $req->agencyId, $req->capacityId);       // Check booking is available or not on selected agency on delivery date
            // if ($bookingStatus == false)
            //     throw new Exception('Your Delivery Date Slot are Not Available. Please Try To Other Date or Agency !!!');

            //commented by prity pandey
            // $hydrationCenterId = $mCalculations->findHydrationCenter($req->deliveryDate,  $req->capacityId, $req->locationId);
            // if (!$hydrationCenterId)
            //     throw new Exception('Your Delivery Date Slot are Not Available. Please Try To Other Date !!!');
            // $hydrationCenter = ['hydrationCenter' => $hydrationCenterId];
            // $req->merge($hydrationCenter);

            $generatedId = $mCalculations->generateId($this->_paramId, $req->ulbId);          // Generate Booking No
            $bookingNo = ['bookingNo' => $generatedId];
            $req->merge($bookingNo);

            $payAmt = $mCalculations->getAmount($req->ulbId, $req->capacityId);
            $paymentAmount = ['paymentAmount' => round($payAmt)];
            $req->merge($paymentAmount);

            $agencyId = $mCalculations->getAgency($req->ulbId);
            $agency = ['agencyId' => $agencyId];
            $req->merge($agency);

            DB::beginTransaction();
            $res = $mWtBooking->storeBooking($req);                                                                     // Store Booking Informations
            DB::commit();
            // #_Whatsaap Message
            // if (strlen($req->mobile) == 10) {

            //     $whatsapp2 = (Whatsapp_Send(
            //         $req->mobile,
            //         "wt_booking_initiated",
            //         [
            //             "content_type" => "text",
            //             [
            //                 $req->applicantName ?? "",
            //                 $req->paymentAmount,
            //                 "water tanker",
            //                 $req->bookingNo,
            //                 "87787878787 "
            //             ]
            //         ]
            //     ));
            // }

            return responseMsgs(true, "Booking Added Successfully !!!",  $res, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [$e->getFile(), $e->getLine(), $e->getCode()], "110115", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Booking list
     * | Function - 16
     * | API - 16
     */
    // public function listAgencyBooking(Request $req)
    // {
    //     // WtAgency::select('id')->where('u_id', $req->auth['id'])->first()->id;
    //     // $validator = Validator::make($req->all(), [
    //     //     'date' => 'nullable|date|date_format:Y-m-d',
    //     // ]);
    //     // if ($validator->fails()) {
    //     //     return validationErrorV2($validator);
    //     // }
    //     $validator = Validator::make($req->all(), [
    //         'fromDate' => 'nullable|date_format:Y-m-d|before:' . date('Y-m-d'),
    //         'toDate' => $req->fromDate != NULL ? 'required|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d') : 'nullable|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d'),
    //     ]);
    //     if ($validator->fails()) {
    //         return validationErrorV2($validator);
    //     }

    //     try {
    //         // Variable initialization
    //         if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
    //             throw new Exception("Unauthorized  Access !!!");
    //         $test = WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first();
    //         if (!$test) {
    //             $this->addAgencyNotInLocal($req);
    //         }
    //         $mWtBooking = new WtBooking();
    //         $list = $mWtBooking->getBookingList()
    //             ->where('wb.agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id)
    //             ->where('wb.is_vehicle_sent', '<=', '1')
    //             ->where('wb.assign_date', NULL)
    //             ->where('wb.payment_status', '!=', '0')                                 // modified previously was '=' 1
    //             ->where('wb.delivery_date', '>=', Carbon::now()->format('Y-m-d'))
    //             // added this filter to showing booking which are either tanker free with document uploaded or tanker not free
    //             ->where(function($query) {                                              
    //                 $query->where(function($q) {
    //                     $q->where('wb.is_tanker_free', true)
    //                       ->where('wb.is_document_uploaded', true);
    //                 })->orWhere('wb.is_tanker_free', false);
    //             })
    //             ->orderByDesc('id');
    //         if ($req->fromDate != NULL) {
    //             $list = $list->whereBetween('booking_date', [$req->fromDate, $req->toDate]);
    //         }
    //         // if ($req->date != NULL)
    //         //     $list = $list->where('delivery_date', $req->date)->values();

    //         $ulb = $this->_ulbs;
    //         $perPage = $req->perPage ? $req->perPage : 10;
    //         $list = $list->paginate($perPage);
    //         $f_list = [
    //             "currentPage" => $list->currentPage(),
    //             "lastPage" => $list->lastPage(),
    //             "total" => $list->total(),
    //             "data" => collect($list->items())->map(function ($val) use ($ulb) {
    //                 $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
    //                 $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
    //                 $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
    //                 $val->delivery_status = $val->is_vehicle_sent == '0' ? "Waiting For Delivery" : ($val->is_vehicle_sent == '1' ? "Out For Delivery" : "Delivered");
    //                 return $val;
    //             }),
    //         ];
    //         return responseMsgs(true, "Water Tanker Booking List !!!", $f_list, "110116", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "110116", "1.0", "", 'POST', $req->deviceId ?? "");
    //     }
    // }

    /* 
     * | Modified Function - listAgencyBooking
     * | Added Feature - Filter by bookingType (paid/free)
    */
    public function listAgencyBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d|before:' . date('Y-m-d'),
            'toDate' => $req->fromDate != NULL 
                ? 'required|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d') 
                : 'nullable|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d'),
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }

        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception("Unauthorized  Access !!!");

            $test = WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first();
            if (!$test) {
                $this->addAgencyNotInLocal($req);
            }

            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingList()
                ->where('wb.agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id)
                ->where('wb.is_vehicle_sent', '<=', '1')
                ->where('wb.assign_date', NULL)
                ->where('wb.payment_status', '!=', '0')   // existing condition
                ->where('wb.delivery_date', '>=', Carbon::now()->format('Y-m-d'))
                ->where(function($query) {
                    $query->where(function($q) {
                        $q->where('wb.is_tanker_free', true)
                        ->where('wb.is_document_uploaded', true);
                    })->orWhere('wb.is_tanker_free', false);
                })
                ->orderByDesc('id');

            // ⭐ NEW FILTER: bookingType → paid (1) / free (2)
            if ($req->bookingType) {
                $typeMap = [
                    "paid" => 1,
                    "free" => 2,
                ];

                if (isset($typeMap[$req->bookingType])) {
                    $list = $list->where("wb.payment_status", $typeMap[$req->bookingType]);
                }
            }

            if ($req->fromDate != NULL) {
                $list = $list->whereBetween('booking_date', [$req->fromDate, $req->toDate]);
            }

            $ulb = $this->_ulbs;
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);

            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) use ($ulb) {
                    $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                    $val->delivery_status = $val->is_vehicle_sent == '0' 
                        ? "Waiting For Delivery" 
                        : ($val->is_vehicle_sent == '1' ? "Out For Delivery" : "Delivered");
                    return $val;
                }),
            ];

            return responseMsgs(true, "Water Tanker Booking List !!!", $f_list, "110116", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110116", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Mapping Driver to vehicle (Assign Driver to Vehicle)
     * | Function - 17
     * | API - 17
     */
    public function mapDriverVehicle(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'driverId' => 'required|integer',
            'vehicleId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            if ($req->auth['user_type'] == 'Water-Agency')
                $req->merge(['agencyId' => WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id]);

            $mWtResource = WtResource::find($req->vehicleId);
            $isUlbVehicle = ['isUlbVehicle' => $mWtResource->is_ulb_resource];
            $req->request->add($isUlbVehicle);
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            DB::beginTransaction();
            $res = $mWtDriverVehicleMap->storeMappingDriverVehicle($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Mapping Added Successfully !!!",  '', "110117", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110117", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Mapping Driver to vehicle (Assign Driver to Vehicle)
     * | Function - 18
     * | API - 18
     */
    public function listMapDriverVehicle(Request $req)
    {
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            $list = $mWtDriverVehicleMap->getMapDriverVehicle($req->auth['ulb_id']);
            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
                $val->is_ulb_vehicle = $val->is_ulb_vehicle == 1 ? 'Yes' : 'No';
                return $val;
            });
            return responseMsgs(true, "Mapping List !!!", $f_list->values(), "110118", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110118", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Cancel Booking
     * | Function - 19
     * | API - 19
     */
    public function cancelBooking(Request $req)
    {
        $cancelledBy = $req->auth['user_type'];
        if ($cancelledBy == 'Citizen')
            $cancelById = $req->auth['id'];
        if ($cancelledBy == 'Water-Agency' || $cancelledBy == 'UlbUser')
            $cancelById = WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id;
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer|exists:' . (new WtBooking())->getTable() . ",id",
            'remarks' => 'required|string',
            'cancelDetails' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->request->add(['cancelledById' => $cancelById, 'cancelledBy' => $cancelledBy]);
        try {
            // Variable initialization
            $mWtBooking = WtBooking::find($req->applicationId);
            // if ($mWtBooking->is_vehicle_sent > 0)
            //     throw new Exception('This Booking is Not Cancel, Because Tanker is Going to Re-fill !!!');
            if ($mWtBooking->is_vehicle_sent == 2)
                throw new Exception('This Booking is delivered. You can not cancel it !!!');
            $cancelledBooking = $mWtBooking->replicate();                                   // Replicate Data fromm Booking to Cancel table
            $cancelledBooking->cancel_date = Carbon::now()->format('Y-m-d');
            $cancelledBooking->remarks = $req->remarks;
            $cancelledBooking->cancel_details = $req->cancelDetails;
            $cancelledBooking->cancelled_by = $req->cancelledBy;
            $cancelledBooking->cancelled_by_id = $req->cancelledById;
            $cancelledBooking->id =  $mWtBooking->id;
            // if ($cancelledBy == 'Citizen' || $cancelledBy == 'UlbUser')
            //     $cancelledBooking->refund_amount =  $mWtBooking->payment_amount;
            if ($cancelledBy == 'Citizen' || $cancelledBy == 'UlbUser' || $cancelledBy == 'Water-Agency')
                $cancelledBooking->refund_amount =  $mWtBooking->payment_amount;
            else
                $cancelledBooking->refund_amount = 0;

            $cancelledBooking->setTable('wt_cancellations');
            $cancelledBooking->save();                                                       // Save in Cancel Booking Table
            $mWtBooking->delete();                                                           // Delete Data From Booking Table
            return responseMsgs(true, "Booking Cancelled Successfully !!!",  '', "110119", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110119", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Cancelled Booking List
     * | Function - 20
     * | API - 20
     */
    public function listCancelBooking(Request $req)
    {
        try {
            // Variable initialization
            $mWtCancellation = new WtCancellation();
            $list = $mWtCancellation->getCancelBookingList();    // 0 - Booking Cancel Success
            if ($req->auth['user_type'] == 'Citizen')
                $list = $list->where('citizen_id', $req->auth['id']);                        // Get Citizen Cancel Application List
            if ($req->auth['user_type'] == 'UlbUser')
                $list = $list->where('ulb_id', $req->auth['id']);                            // Get ULB Cancel Application List
            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id', WtAgency::select('id')->wehere('ulb_id', $req->auth['ulb_id'])->first()->id);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cancel_date = Carbon::parse($val->cancel_date)->format('d-m-Y');
                return $val;
            })->values();
            return responseMsgs(true, "Booking List !!!", $f_list, "110120", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110120", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Refund Booking 
     * | Function - 21
     * | API - 21
     */
    public function refundBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'refundRemarks' => 'required|string',
            'refundDetails' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtCancellation = WtCancellation::find($req->applicationId);
            $mWtCancellation->refund_status = '1';
            $mWtCancellation->refund_remarks = $req->refundRemarks;
            $mWtCancellation->refund_details = $req->refundDetails;
            $mWtCancellation->refund_date = Carbon::now()->format('Y-m-d');
            $mWtCancellation->save();                                                       // Update Cancellation Table for Refund
            return responseMsgs(true, "Refund Successfully !!!",  '', "110121", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110121", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Refund Booking List
     * | Function - 22
     * | API - 22
     */
    public function listRefundBooking(Request $req)
    {
        try {
            // Variable initialization
            $mWtCancellation = new WtCancellation();
            $list = $mWtCancellation->getCancelBookingList()->where('refund_status', '1');    // 1 - Booking Refund Success
            if ($req->auth['user_type'] == 'Citizen')
                $list = $list->where('citizen_id', $req->auth['id']);                        // Get Citizen Refund Application List
            if ($req->auth['user_type'] == 'UlbUser')
                $list = $list->where('ulb_id', $req->auth['id']);                            // Get ULB Refund Application List
            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id', WtAgency::select('id')->wehere('ulb_id', $req->auth['ulb_id'])->first()->id);

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                return $val;
            });
            return responseMsgs(true, "Refund Booking List !!!", $f_list, "110122", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110122", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Booking list
     * | Function - 23
     * | API - 23
     */
    public function listUlbBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'date' => 'nullable|date|date_format:Y-m-d',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            if ($req->auth['user_type'] != 'UlbUser')
                throw new Exception("Unauthorized Access !!!");
            // Variable initialization
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingList()->where('agency_id', '=', NULL)->where('ulb_id', $req->auth['ulb_id'])->get();
            if ($req->date != NULL)
                $list = $list->where('delivery_date', $req->date)->values();

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                return $val;
            });
            return responseMsgs(true, "ULB Booking List !!!", $f_list, "110123", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110123", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Booking list
     * | Function - 24
     * | API - 24
     */
    public function editAgency(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'agencyId' => 'required|integer',
            'agencyName' => 'required|string',
            'agencyAddress' => 'required|string',
            'agencyMobile' => 'required|digits:10',
            'agencyEmail' => 'required|email',
            'ownerName' => 'required|string|max:255',
            'dispatchCapacity' => 'required|numeric',
            'ulbId' => 'required|integer',
            "status" => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtAgency = WtAgency::find($req->agencyId);
            if (!$mWtAgency)
                throw new Exception("No Data Found !!!");
            $mWtAgency->agency_name = $req->agencyName;
            $mWtAgency->agency_address = $req->agencyAddress;
            $mWtAgency->agency_mobile = $req->agencyMobile;
            $mWtAgency->agency_email = $req->agencyEmail;
            $mWtAgency->owner_name = $req->ownerName;
            $mWtAgency->dispatch_capacity = $req->dispatchCapacity;
            $mWtAgency->ulb_id = $req->ulbId;
            if (isset($req->status)) {
                $mWtAgency->status = $req->status;
            }
            $mWtAgency->save();
            return responseMsgs(true, "Agency Updated Successfully !!!", '', "110124", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110124", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Booking list
     * | Function - 25
     * | API - 25
     */
    public function editHydrationCenter(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'hydrationCenterId' => 'required|integer',
            'name' => 'required|string',
            'waterCapacity' => 'required|numeric',
            'address' => 'required|string',
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mWtHydrationCenter = WtHydrationCenter::find($req->hydrationCenterId);
            if (!$mWtHydrationCenter)
                throw new Exception("No Data Found !!!");
            $mWtHydrationCenter->name = $req->name;
            $mWtHydrationCenter->ulb_id = $req->ulbId;
            $mWtHydrationCenter->water_capacity = $req->waterCapacity;
            $mWtHydrationCenter->address = $req->address;
            if (isset($req->status)) {
                $mWtHydrationCenter->status = $req->status;
            }
            $mWtHydrationCenter->save();
            return responseMsgs(true, "Hydration Center Details Updated Successfully !!!", '', "110125", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110125", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Resource
     * | Function - 26
     * | API - 26
     */
    public function editResource(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'resourceId' => 'required|integer',
            'vehicleName' => 'required|string|max:200',
            'vehicleNo' => 'required|string|max:16',
            'capacityId' => 'required|integer|digits_between:1,150',
            'resourceType' => 'required|string|max:200',
            'isUlbResource' => 'required|boolean',
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        if ($req->auth['user_type'] == 'Water-Agency')
            $req->merge(['agencyId' => DB::table('wt_agencies')->select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id]);
        try {
            $mWtResource = WtResource::find($req->resourceId);
            if (!$mWtResource)
                throw new Exception("No Data Found !!!");
            $mWtResource->ulb_id = $req->ulbId;
            $mWtResource->agency_id = $req->agencyId;
            $mWtResource->vehicle_name = $req->vehicleName;
            $mWtResource->vehicle_no = $req->vehicleNo;
            $mWtResource->capacity_id = $req->capacityId;
            $mWtResource->resource_type = $req->resourceType;
            $mWtResource->is_ulb_resource = $req->isUlbResource;
            if (isset($req->status)) {
                $mWtResource->status = $req->status;
            }
            $mWtResource->save();
            return responseMsgs(true, "Resource Details Updated Successfully !!!", '', "110126", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110126", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Capacity
     * | Function - 27
     * | API - 27
     */
    public function editCapacity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityId' => 'required|integer',
            'capacity' => 'required|numeric|unique:wt_capacities',
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtCapacity = WtCapacity::find($req->capacityId);
            if (!$mWtCapacity)
                throw new Exception("No Data Found !!!");
            $mWtCapacity->capacity = $req->capacity;
            if (isset($req->status)) {
                $mWtCapacity->status = $req->status;
            }
            $mWtCapacity->save();
            return responseMsgs(true, "Capacity Details Updated Successfully !!!", '', "110127", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110127", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details of Capacity Rates
     * | Function - 28
     * | API - 28
     */
    public function editCapacityRate(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'capacityId' => 'required|integer',
            'capacityRateId' => 'required|integer',
            'rate' => 'required|integer|gt:0',
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mWtUlbCapacityRate = WtUlbCapacityRate::find($req->capacityRateId);
            if (!$mWtUlbCapacityRate)
                throw new Exception("No Data Found !!!");
            $mWtUlbCapacityRate->ulb_id = $req->ulbId;
            $mWtUlbCapacityRate->capacity_id = $req->capacityId;
            $mWtUlbCapacityRate->rate = $req->rate;
            if (isset($req->status)) {
                $mWtUlbCapacityRate->status = $req->status;
            }
            $mWtUlbCapacityRate->save();
            return responseMsgs(true, "Capacity Rate Updated Successfully !!!", '', "110128", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110128", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details for Driver
     * | Function - 29
     * | API - 29
     */
    public function editDriver(Request $req)
    {
        $mUser = new User();
        $mWtDriver = new WtDriver();
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
            "status" => "nullable|integer|in:1,0",
            'driverEmail' => "required|email|unique:" . $mUser->getConnectionName() . "." . $mUser->getTable() . ",email" . ($WtDriver && $WtDriver->u_id ? ("," . $WtDriver->u_id) : ""),

        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        if ($req->auth['user_type'] == 'Water-Agency')
            $req->merge(['agencyId' => DB::table('wt_agencies')->select('*')->where('ulb_id', $req->auth['ulb_id'])->first()->id]);
        try {
            if (!$WtDriver)
                throw new Exception("No Data Found !!!");
            $user = User::find($WtDriver->u_id);
            $WtDriver->ulb_id = $req->ulbId;
            $WtDriver->agency_id = $req->agencyId;
            $WtDriver->driver_name = $req->driverName;
            $WtDriver->driver_aadhar_no = $req->driverAadharNo;
            $WtDriver->driver_mobile = $req->driverMobile;
            $WtDriver->driver_address = $req->driverAddress;
            $WtDriver->driver_father = $req->driverFather;
            $WtDriver->driver_dob = $req->driverDob;
            $WtDriver->driver_license_no = $req->driverLicenseNo;
            if (isset($req->status)) {
                $WtDriver->status = $req->status;
            }
            if (!$user) {
                $reqs = [
                    "name" =>  $req->driverName,
                    "email" => $req->driverEmail,
                    "password" => $req->password ? $req->password : ("Basic" . '@' . "12345"),
                    "mobile" => $req->driverMobile,
                    "address"   => $req->driverAddress,
                    "ulb" => $req->ulbId,
                    "userType" =>  "Driver",
                ];
                $userId = $this->store($reqs);                                                // Create User in User Table for own Dashboard and Login                
                $WtDriver->u_id = $userId;
                $user = User::find($WtDriver->u_id);
            }
            if ($user) {
                isset($req->driverEmail) ? $user->email = $req->driverEmail : "";
                $user->name = $req->driverName;
                $user->mobile = $req->driverMobile;
                $user->address = $req->driverAddress;
                $user->suspended = !(bool)$WtDriver->status;
            }
            $WtDriver->save();
            $user ? $user->update() : "";
            return responseMsgs(true, "Driver Details Updated Successfully !!!", '', "110129", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110129", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Agency Details By Id
     * | Function - 30
     * | API - 30
     */

    public function getAgencyDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'agencyId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtAgency = new WtAgency();
            $list = $mWtAgency->getAgencyById($req->agencyId);
            return responseMsgs(true, "Data Fetched !!!",  $list, "110130", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110130", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Hydration Center Details By Id
     * | Function - 31
     * | API - 31
     */
    public function getHydrationCenterDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'hydrationCenterId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtHydrationCenter = new WtHydrationCenter();
            $list = $mWtHydrationCenter->getHydrationCenterDetailsByID($req->hydrationCenterId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110131", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110131", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 32
     * | API - 32
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
            $mWtResource = new WtResource();
            $list = $mWtResource->getResourceById($req->resourceId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110132", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110132", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 33
     * | API - 33
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
            $mWtCapacity = new WtCapacity();
            $list = $mWtCapacity->getCapacityById($req->capacityId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110133", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110133", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 34
     * | API - 34
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
            $mWtUlbCapacityRate = new WtUlbCapacityRate();
            $list = $mWtUlbCapacityRate->getCapacityRateDetailsById($req->capacityRateId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110134", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110134", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Resource Details By Id
     * | Function - 35
     * | API - 35
     */
    public function getDriverDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'driverId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtDriver = new WtDriver();
            $list = $mWtDriver->getDriverDetailsById($req->driverId);
            $user  = User::where("id", $list->u_id)->first();
            $list ? ($list->email = $user ? $user->email : "") : "";
            return responseMsgs(true, "Data Fetched !!!", $list, "110135", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110135", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Driver Vehicle Map List
     * | Function - 36
     * | API - 36
     */
    public function listDriverVehicleForAssign(Request $req)
    {
        try {
            // Variable initialization
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            $list = $mWtDriverVehicleMap->getMapDriverVehicle($req->auth['ulb_id']);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->driver_vehicle = $val->vehicle_no . "( " . $val->driver_name . " )";
                return $val;
            });
            return responseMsgs(true, "Driver Vehicle Mapping List !!!", $f_list, "110136", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110136", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Driver Vehicle Map Details By Id
     * | Function - 37
     * | API - 37
     */
    public function getDriverVehicleMapById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'mapId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            $list = $mWtDriverVehicleMap->getDriverVehicleMapById($req->mapId);
            return responseMsgs(true, "Data Fetched !!!", $list, "110137", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110137", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Driver Vehicle Map Details By Id
     * | Function - 38
     * | API - 38
     * 
     */
    public function editDriverVehicleMap(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'driverId' => 'required|integer',
            'vehicleId' => 'required|integer',
            'mapId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        try {
            $mWtDriverVehicleMap = WtDriverVehicleMap::find($req->mapId);
            if (!$mWtDriverVehicleMap)
                throw new Exception("No Data Found !!!");
            $mWtDriverVehicleMap->ulb_id = $req->ulbId;
            $mWtDriverVehicleMap->driver_id = $req->driverId;
            $mWtDriverVehicleMap->vehicle_id = $req->vehicleId;
            $mWtDriverVehicleMap->save();
            return responseMsgs(true, "Map Driver & Vehicle Details Updated Successfully !!!", '', "110138", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110138", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Assign for Delevery (Tanker , Driver & Hydration Center )
     * | Function - 39
     * | API - 39
     */
    public function bookingAssignment(Request $req)
    {
        $ulbId = $req->auth["ulb_id"] ?? null;
        $mWtResource = new WtResource();
        $mWtBooking = new WtBooking();
        $mWtDriver = new WtDriver();
        $validator = Validator::make($req->all(), [
            'applicationId' => "required|integer",
            // 'vdmId' => 'required|integer',
            'vehicleId' => "required|integer",
            'driverId' => "required|integer",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtBooking = WtBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("No Data Found !!!");
            // $mWtDriverVehicleMap = WtDriverVehicleMap::find($req->vdmId);
            // if (!$mWtDriverVehicleMap)
            //     throw new Exception("Driver Vehicle Map Not Found !!!");
            // $mWtBooking->vdm_id = $req->vdmId;
            // $mWtBooking->vehicle_id = $mWtDriverVehicleMap->vehicle_id;
            // $mWtBooking->driver_id = $mWtDriverVehicleMap->driver_id;
            $mWtBooking->vehicle_id = $req->vehicleId;
            $mWtBooking->driver_id = $req->driverId;
            $mWtBooking->assign_date = Carbon::now()->format('Y-m-d');
            $mWtBooking->update();
            $driver = WtDriver::find($req->driverId);
            if (!$driver) {
                throw new Exception("Driver Not Found !!!");
            }

            $driverName = $driver->driver_name;
            $driverContact = $driver->driver_mobile;
            #_Whatsaap Message
            // if (strlen($mWtBooking->mobile) == 10) {

            //     $whatsapp2 = (Whatsapp_Send(
            //         $mWtBooking->mobile,
            //         "wt_driver_assignment",
            //         [
            //             "content_type" => "text",
            //             [
            //                 $mWtBooking->applicant_name, $driverName,
            //                 $driverContact,
            //                 "water supply",
            //                 $mWtBooking->booking_no,
            //                 "1800123123 "
            //             ]
            //         ]
            //     ));
            // }

            return responseMsgs(true, "Booking Assignment Successfully !!!", '', "110139", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110139", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Agency Booking Assign for Delevery (Tanker , Driver & Hydration Center )
     * | Function - 40
     * | API - 40
     */
    public function listAssignAgency(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d|before:' . date('Y-m-d'),
            'toDate' => $req->fromDate != NULL ? 'required|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d') : 'nullable|date_format:Y-m-d|after:' . $req->fromDate . '|before_or_equal:' . date('Y-m-d'),
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $ulbId = $req->auth["ulb_id"];
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->assignList()
                ->where('delivery_track_status', '0')
                ->where('delivery_date', '>=', Carbon::now()->format('Y-m-d'));

            if ($req->fromDate != NULL) {
                $list = $list->whereBetween('booking_date', [$req->fromDate, $req->toDate]);
            }
            $ulb = collect($this->_ulbs);
            $list = ($list)->where("wb.ulb_id", $ulbId);

            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) use ($ulb) {
                    $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                    $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
                    return $val;
                }),
            ];
            // $f_list = $list->map(function ($val) use ($ulb) {
            //     $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
            //     $val->booking_date = Carbon::parse( $val->booking_date)->format('d-m-Y');
            //     $val->delivery_date = Carbon::parse( $val->delivery_date)->format('d-m-Y');
            //     $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
            //     return $val;
            // });
            return responseMsgs(true, "Assign List !!!", $f_list, "110140", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110140", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Hydration Center Booking Assign for Delevery (Tanker , Driver & Hydration Center )
     * | Function - 41
     * | API - 41
     */
    public function listAssignHydrationCenter(Request $req)
    {
        try {
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->assignList()->get();
            $ulb = $this->_ulbs;
            $ulbId = $req->auth["ulb_id"];
            $list = collect($list)->where("ulb_id", $ulbId);
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d/m/Y');
                $val->delivery_date = Carbon::parse($val->delivery_date)->format('d/m/Y');
                $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
                return $val;
            });
            return responseMsgs(true, "Assign List !!!", $f_list, "110141", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110141", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Add Location Matser
     * | Function - 42
     * | API - 42
     */
    public function addLocation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'location' => 'required|string|max:255|unique:wt_locations',
            'isInUlb' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            // Variable initialization
            $mWtLocation = new WtLocation();
            DB::beginTransaction();
            $res = $mWtLocation->storelocation($req);                                       // Store Location in Model 
            DB::commit();
            return responseMsgs(true, "Location Added Successfully !!!",  '', "110142", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110142", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Location Matser
     * | Function - 43
     * | API - 43
     */
    public function listLocation(Request $req)
    {
        try {
            $mWtLocation = new WtLocation();
            $list = $mWtLocation->listLocation($req->auth['ulb_id']);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Location List !!!", $f_list, "110143", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110143", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Location Details By Id
     * | Function - 44
     * | API - 44
     */
    public function getLocationDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'locationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtLocation = new WtLocation();
            $list = $mWtLocation->getLocationDetailsById($req->locationId);
            return responseMsgs(true, "Location List !!!", $list, "110144", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110144", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Location Details By Id
     * | Function - 45
     * | API - 45
     */
    public function editLocation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'locationId' => 'required|integer',
            'isInUlb' => 'required|integer',
            'location' => 'required|string',
            "status"    => "nullable|integer|in:1,0",
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        $req->merge(['ulbId' => $req->auth['ulb_id']]);
        try {
            $mWtLocation = WtLocation::find($req->locationId);
            if (!$mWtLocation)
                throw new Exception("No Data Found !!!");
            $mWtLocation->ulb_id = $req->ulbId;
            $mWtLocation->location = $req->location;
            $mWtLocation->is_in_ulb = $req->isInUlb;
            if (isset($req->status)) {
                $mWtLocation->status = $req->status;
            }
            $mWtLocation->save();
            return responseMsgs(true, "Update Location Successfully !!!", '', "110145", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110145", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Add Location Matser
     * | Function - 46
     * | API - 46
     */
    public function addLocationHydrationMap(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'locationId' => 'required|integer',
            'hydrationCenterId' => 'required|integer',
            'distance' => 'required|numeric',
            'rank' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mWtLocationHydrationMap = new WtLocationHydrationMap();
            $check = $mWtLocationHydrationMap->isLocationHydrationMapOrNot($req->locationId, $req->hydrationCenterId);
            if ($check > 0)
                throw new Exception("Selected Location & Hydration are Already Mapped !!!");
            DB::beginTransaction();
            $res = $mWtLocationHydrationMap->storeLocationHydrationMap($req);                                       // Store Location in Model 
            DB::commit();
            return responseMsgs(true, "Location Hydration Map Successfully !!!",  '', "110146", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110146", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | List Location Matser
     * | Function - 47
     * | API - 47
     */
    public function listLocationHydrationMap(Request $req)
    {
        try {
            $mWtLocationHydrationMap = new WtLocationHydrationMap();
            $list = $mWtLocationHydrationMap->listLocationHydrationMap()->where('ulb_id', $req->auth['ulb_id']);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Location Hydration Map List !!!", $f_list, "110147", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110147", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Location Details By Id
     * | Function - 48
     * | API - 48
     */
    public function getLocationHydrationMapDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'locationHydrationMapId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtLocationHydrationMap = new WtLocationHydrationMap();
            $list = $mWtLocationHydrationMap->getLocationHydrationMapDetailsById($req->locationHydrationMapId);
            return responseMsgs(true, "Location Hydration Map Details !!!", $list, "110148", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110148", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Location Details By Id
     * | Function - 49
     * | API - 49
     */
    public function editLocationHydrationMap(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'locationHydrationMapId' => 'required|integer',
            'locationId' => 'required|integer',
            'hydrationCenterId' => 'required|integer',
            'distance' => 'required|numeric',
            'rank' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable Initialization
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mtLocationHydrationMap = WtLocationHydrationMap::find($req->locationHydrationMapId);
            if (!$mtLocationHydrationMap)
                throw new Exception("No Data Found !!!");
            $mtLocationHydrationMap->ulb_id = $req->ulbId;
            $mtLocationHydrationMap->location_id = $req->locationId;
            $mtLocationHydrationMap->hydration_center_id = $req->hydrationCenterId;
            $mtLocationHydrationMap->distance = $req->distance;
            $mtLocationHydrationMap->rank = $req->rank;
            $mtLocationHydrationMap->save();
            return responseMsgs(true, "Update Location Hydration Center Successfully !!!", '', "110149", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110149", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Location Details By Id
     * | Function - 50
     * | API - 50
     */
    // public function getBookingDetailById(Request $req)
    // {

    //     $validator = Validator::make($req->all(), [
    //         'applicationId' => [
    //             "required",
    //             function ($attribute, $value, $fail) {
    //                 $existsInTable1 = WtBooking::where("id", $value)
    //                     ->exists();
    //                 if (!$existsInTable1) {
    //                     $existsInTable1 = WtCancellation::where("id", $value)
    //                         ->exists();
    //                 }
    //                 if (!$existsInTable1) {
    //                     $fail('The ' . $attribute . ' is invalid.');
    //                 }
    //             },
    //         ],
    //     ]);
    //     if ($validator->fails()) {
    //         return validationErrorV2($validator);
    //     }
    //     try {
    //         $mWtBooking = new WtBooking();
    //         $data = $mWtBooking->find($req->applicationId);
    //         if (!$data) {
    //             $mWtBooking = new WtCancellation();
    //             $data = WtCancellation::find($req->applicationId);
    //         }
    //         $tranDtls = $data->getAllTrans()->map(function ($val) {
    //             $chequeDtls = $val->getChequeDtls();
    //             $val->tran_date = Carbon::parse($val->tran_date)->format("d-m-Y");
    //             $val->cheque_no = $chequeDtls->cheque_no ?? "";
    //             $val->cheque_date = $chequeDtls->cheque_date ?? "";
    //             $val->bank_name = $chequeDtls->bank_name ?? "";
    //             $val->branch_name = $chequeDtls->branch_name ?? "";
    //             return $val;
    //         });
    //         $wardDtl = DB::table("ulb_ward_masters")->find($data->ward_id);
    //         $appStatus = $this->getAppStatus($req->applicationId);
    //         $list = $mWtBooking->getBookingDetailById($req->applicationId);
    //         $reassign = $data->getLastReassignedBooking();
    //         $list->booking_status = $appStatus;
    //         $list->tran_dtls = $tranDtls;
    //         $list->ward_no =  $wardDtl ?  $wardDtl->ward_name ?? "" : "";

    //         $list->payment_details = json_decode($list->payment_details);
    //         $list->booking_date = Carbon::parse($list->booking_date)->format('d-m-Y');
    //         $list->delivery_date = Carbon::parse($list->delivery_date)->format('d-m-Y');
    //         $list->assign_date = Carbon::parse($reassign ? $reassign->re_assign_date : $list->assign_date)->format('d-m-Y');

    //         $driver = $reassign ? $reassign->getAssignedDriver() : $data->getAssignedDriver();
    //         $vehicle = $reassign ? $reassign->getAssignedVehicle() : $data->getAssignedVehicle();

    //         $list->driver_name = $driver ? $driver->driver_name : "";
    //         $list->driver_mobile = $driver ? $driver->driver_mobile : "";
    //         $list->vehicle_no = $vehicle ? $vehicle->vehicle_no : "";
    //         return responseMsgs(true, "Booking Details!!!", $list, "110150", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "110150", "1.0", "", 'POST', $req->deviceId ?? "");
    //     }
    // }

    public function getBookingDetailById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => [
                "required",
                function ($attribute, $value, $fail) {
                    $exists = WtBooking::where("id", $value)->exists()
                        || WtCancellation::where("id", $value)->exists();

                    if (!$exists) {
                        $fail('The ' . $attribute . ' is invalid.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return validationErrorV2($validator);
        }

        try {
            $mWtBooking = new WtBooking();
            $data = $mWtBooking->find($req->applicationId);

            if (!$data) {
                $mWtBooking = new WtCancellation();
                $data = WtCancellation::find($req->applicationId);
            }

            /*
            |--------------------------------------------------------------------------
            | Transaction Details
            |--------------------------------------------------------------------------
            */
            $tranDtls = $data->getAllTrans()->map(function ($val) {
                $chequeDtls = $val->getChequeDtls();
                $val->tran_date = Carbon::parse($val->tran_date)->format("d-m-Y");
                $val->cheque_no = $chequeDtls->cheque_no ?? "";
                $val->cheque_date = $chequeDtls->cheque_date ?? "";
                $val->bank_name = $chequeDtls->bank_name ?? "";
                $val->branch_name = $chequeDtls->branch_name ?? "";
                return $val;
            });

            $wardDtl  = DB::table("ulb_ward_masters")->find($data->ward_id);
            $appStatus = $this->getAppStatus($req->applicationId);
            $list = $mWtBooking->getBookingDetailById($req->applicationId);
            $reassign = $data->getLastReassignedBooking();

            /*
            |--------------------------------------------------------------------------
            | Booking Meta
            |--------------------------------------------------------------------------
            */
            $list->booking_status = $appStatus;
            $list->tran_dtls = $tranDtls;
            $list->ward_no = $wardDtl ? $wardDtl->ward_name ?? "" : "";

            $list->payment_details = json_decode($list->payment_details);
            $list->booking_date = Carbon::parse($list->booking_date)->format('d-m-Y');
            $list->delivery_date = Carbon::parse($list->delivery_date)->format('d-m-Y');
            $list->assign_date = Carbon::parse(
                $reassign ? $reassign->re_assign_date : $list->assign_date
            )->format('d-m-Y');

            /*
            |--------------------------------------------------------------------------
            | Driver & Vehicle
            |--------------------------------------------------------------------------
            */
            $driver  = $reassign ? $reassign->getAssignedDriver() : $data->getAssignedDriver();
            $vehicle = $reassign ? $reassign->getAssignedVehicle() : $data->getAssignedVehicle();

            $list->driver_name   = $driver ? $driver->driver_name : "";
            $list->driver_mobile = $driver ? $driver->driver_mobile : "";
            $list->vehicle_no    = $vehicle ? $vehicle->vehicle_no : "";

            /*
            |--------------------------------------------------------------------------
            | REASON HISTORY (NEW ADDITION)
            |--------------------------------------------------------------------------
            */
            $reasons = [];

            // Current comment (from wt_bookings)
            if (!empty($data->verify_comment)) {
                $reasons[] = [
                    "verified_at"     => $data->verified_at
                        ? Carbon::parse($data->verified_at)->format('d-m-Y H:i:s')
                        : null,
                    "current_comment" => $data->verify_comment
                ];
            }

            // Previous comments (from log table)
            $logReasons = DB::table('log_wt_bookings')
                ->select('reason', 'logged_at')
                ->where('booking_id', $data->id)
                ->whereNotNull('reason')
                ->orderBy('logged_at', 'DESC')
                ->get();

            foreach ($logReasons as $log) {
                $reasons[] = [
                    "verified_at"      => Carbon::parse($log->logged_at)->format('d-m-Y H:i:s'),
                    "previous_comment" => $log->reason
                ];
            }

            $list->remarks = $reasons;

            return responseMsgs(true, "Booking Details!!!", $list, "110150", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110150", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Re-Assign Booking
     * | Function - 51
     * | API - 51
     */
    public function reassignBooking(Request $req)
    {

        $ulbId = $req->auth["ulb_id"] ?? null;
        $mWtResource = new WtResource();
        $mWtBooking = new WtBooking();
        $mWtDriver = new WtDriver();
        DB::enableQueryLog();
        $validator = Validator::make($req->all(), [
            'applicationId' => "required|integer|exists:" . $mWtBooking->getConnectionName() . "." . $mWtBooking->getTable() . ",id,status,1,ulb_id," . $ulbId,
            // 'vdmId' => 'required|integer',
            'vehicleId' => "required|integer|exists:" . $mWtResource->getConnectionName() . "." . $mWtResource->getTable() . ",id,status,1,ulb_id," . $ulbId,
            'driverId' => "required|integer|exists:" . $mWtDriver->getConnectionName() . "." . $mWtDriver->getTable() . ",id,status,1,ulb_id," . $ulbId,
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtBooking = WtBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("No Data Found !!!");
            // $mWtDriverVehicleMap = WtDriverVehicleMap::find($req->vdmId);
            // if (!$mWtDriverVehicleMap)
            //     throw new Exception("Driver Vehicle Map Not Found !!!");
            $mWtBookingForReplicate = WtBooking::select(
                'id',
                'vdm_id',
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
            // $mWtBooking->vdm_id = $req->vdmId;
            // $mWtBooking->vehicle_id = $mWtDriverVehicleMap->vehicle_id;
            // $mWtBooking->driver_id = $mWtDriverVehicleMap->driver_id;
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
            $reassign->setTable('wt_reassign_bookings');
            $reassign->application_id =  $mWtBookingForReplicate->id;
            // $reassign->re_assign_date =  Carbon::now()->format('Y-m-d');
            DB::beginTransaction();
            $mWtBooking->update();
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
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($req->fromDate) {
                $fromDate = $req->fromDate;
            }
            if ($req->toDate) {
                $uptoDate = $req->toDate;
            }
            $ulbId = $req->auth["ulb_id"];
            $mWtReassignBooking = new WtReassignBooking();
            $list = $mWtReassignBooking->listReassignBookingOrm();
            $list = $list->where("wb.ulb_id", $ulbId)
                ->whereBetween("wrb.re_assign_date", [$fromDate, $uptoDate])
                ->where("wb.delivery_track_status", 0)
                ->where("wb.is_vehicle_sent", 1)
                ->orderBy("wrb.re_assign_date", "DESC");
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) {
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
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
     * | Get Agency List Ulb Wise
     * | Function - 53
     * | API - 53
     */
    public function listUlbWiseAgency(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            // $mWtAgency = new WtAgency();
            // $list = $mWtAgency->getAllAgency()->where('ulb_id', $req->ulbId);
            $mWtLocationHydrationMap = new WtLocationHydrationMap();
            $list1 = $mWtLocationHydrationMap->listLocation($req->ulbId);

            $mWtCapacity = new WtCapacity();
            $listCapacity = $mWtCapacity->getCapacityList();

            // $ulb = $this->_ulbs;
            // $f_list['listAgency'] = $list->map(function ($val) use ($ulb) {
            //     $val["ulb_name"] = (collect($ulb)->where("id", $val["ulb_id"]))->value("ulb_name");
            //     $val['date'] = Carbon::parse( $val['date'])->format('d-m-Y');
            //     return $val;
            // });
            $f_list['listLocation'] = $list1;
            $f_list['listCapacity'] = $listCapacity;
            return responseMsgs(true, "Agency List !!!",  $f_list, "110153", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110153", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Payment Details By Id
     * | Function - 54
     * | API - 54
     */
    public function getPaymentDetailsById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtBooking = new WtBooking();
            $data = '';
            if ($req->applicationId)
                $data = $mWtBooking->getPaymentDetailsById($req->applicationId);

            if (!$data)
                throw new Exception("Application Not Found");

            return responseMsgs(true, "Booking Details for payment !!!",  $data, "110154", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110154", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Get Applied Application 
     * | Function - 55
     * | API - 55
     */
    public function getappliedApplication(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'Citizen')
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingList()
                ->where('citizen_id', $req->auth['id'])
                ->orderByDesc('id')
                ->get();

            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Agency Booking List !!!", $f_list, "110155", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110155", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Applied Application 
     * | Function - 56
     * | API - 56
     */
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
            $mWtBooking = WtBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("Application Not Found !!!");
            if (!$mWtBooking->vehicle_id || !$mWtBooking->driver_id)
                throw new Exception("First Assign Driver & Vehicle !!!");
            // if ($mWtBooking->delivery_date > Carbon::now()->format('Y-m-d'))
            //     throw new Exception("This Booking is Not Delivery Date Today !!!");
            $mWtBooking->is_vehicle_sent = '1';                                                           // 1 - for Vehicle sent
            $mWtBooking->save();
            return responseMsgs(true, "Vehicle Sent Updation Successfully !!!", '', "110156", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110156", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Applied Application 
     * | Function - 57
     * | API - 57
     */
    public function deliveredWater(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtBooking = WtBooking::find($req->applicationId);
            $mWtBooking->is_vehicle_sent = '2';                                                           // 2 - for Booking Delivered
            $mWtBooking->save();
            return responseMsgs(true, "Water Delivered Successfully !!!", '', "110157", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110157", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Applied Application 
     * | Function - 58
     * | API - 58
     */
    // public function bookingForAgency(Request $req)
    // {
    //     $validator = Validator::make($req->all(), [
    //         'applicationId' => 'required|integer',
    //     ]);
    //     if ($validator->fails()) {
    //         return ['status' => false, 'message' => $validator->errors()];
    //     }
    //     try {
    //         // Variable initialization
    //         return responseMsgs(true, "Vehicle Sent Updation Successfully !!!", '', "110155", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "110155", "1.0", "", 'POST', $req->deviceId ?? "");
    //     }
    // }

    /**
     * | Agency Dashboard
     * | Function - 58
     * | API - 58
     */
    public function wtAgencyDashboard(Request $req)
    {
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception("Unauthorized Access !!!");
            // Variable initialization
            $agencyDetails = WtAgency::select('id', 'agency_name', 'dispatch_capacity')->where('ulb_id', $req->auth['ulb_id'])->first();

            $mWtBooking = new WtBooking();
            $todayBookings = $mWtBooking->todayBookings($agencyDetails->id)->get();

            $mWtBooking = new WtReassignBooking();
            $todayReassign = $mWtBooking->todayreassign($agencyDetails->id)->get();

            $mWtCancellation = new WtCancellation();
            $todayCancelBookings = $mWtCancellation->todayCancelledBooking($agencyDetails->id);

            $retData['todayTotalBooking'] = $todayBookings->count('id') + $todayCancelBookings->count('id');
            $retData['todayOutForDelivery'] = $todayBookings->whereBetween('is_vehicle_sent', [0, 1])
                ->whereNotNull('driver_id')->count('id') + $todayReassign->count();
            $retData['todayDelivered'] = $todayBookings->where('is_vehicle_sent', 2)->count('id');
            $retData['todayTotalCancelBooking'] = $todayCancelBookings->count();
            $retData['agencyName'] =  $agencyDetails->agency_name;
            $retData['waterCapacity'] =  $agencyDetails->dispatch_capacity;
            return responseMsgs(true, "Data Fetched Successfully !!!", $retData, "110158", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110158", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Delivered Application
     * | Function - 59
     * | API - 59
     */
    public function listDeliveredBooking(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d',
            'toDate' => 'nullable|date_format:Y-m-d|after_or_equal:fromDate'
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization  
            if (!isset($req->fromDate))
                $fromDate = Carbon::now()->format('Y-m-d');
            else
                $fromDate = $req->fromDate;
            if (!isset($req->toDate))
                $toDate = Carbon::now()->format('Y-m-d');
            else
                $toDate = $req->toDate;
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingListDelivered()
                ->where(['wb.ulb_id' => $req->auth['ulb_id'], 'is_vehicle_sent' => '2'])
                // ->where()
                ->whereBetween('wb.booking_date', [$fromDate, $toDate])
                ->orderByDesc('id')
                ->get();

            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id);
            $DocUpload = new DocUpload();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb, $DocUpload) {
                $driver = WtDriver::find($val->delivered_by_driver_id);
                $val->delivered_by = (($driver->driver_name ?? "") . " (" . ($driver->driver_license_no ?? "") . ")");
                $val->delivered_date_time = $val->driver_delivery_date_time ?  Carbon::parse($val->driver_delivery_date_time)->format('h:i:s A d-m-Y') : "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                $val->delivery_status = $val->is_vehicle_sent == '0' ? "Waiting For Delivery" : ($val->is_vehicle_sent == '1' ? "Out For Delivery" : "Delivered");
                $uploadDoc = ($DocUpload->getSingleDocUrl($val));
                $val->doc_path = $uploadDoc["doc_path"] ?? "";
                return $val;
            });
            return responseMsgs(true, "Water Tanker Booking List !!!", $f_list, "110159", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110159", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get ULB Wise Location List
     * | Function - 60
     * | API - 60
     */
    public function listUlbWiseLocation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            $mWtLocation = new WtLocation();
            $list = collect($mWtLocation->listLocation($req->ulbId))->where('is_in_ulb', '1')->values();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::parse($val->date)->format('d-m-Y');
                return $val;
            });
            return responseMsgs(true, "Location List !!!", $f_list, "110160", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110160", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get ULB Dashboard Data
     * | Function - 61
     * | API - 61
     */
    public function  ulbDashboard(Request $req)
    {
        if ($req->auth['user_type'] != 'UlbUser')
            throw new Exception('Unauthorized Access !!!');
        try {
            $data['noOfAgency'] = WtAgency::select('*')->where('ulb_id', $req->auth['ulb_id'])->count();
            $data['noOfLocation'] = WtLocation::select('*')->where('ulb_id', $req->auth['ulb_id'])->count();
            $data['noOfHydrationCenter'] = WtHydrationCenter::select('*')->where('ulb_id', $req->auth['ulb_id'])->count();
            $data['noOfUlbDriver'] = WtDriver::select('*')->where('ulb_id', $req->auth['ulb_id'])->where('agency_id', NULL)->count();
            $data['noOfDriver'] = WtDriver::select('*')->where('ulb_id', $req->auth['ulb_id'])->count();
            $data['noOfUlbVehicle'] = WtResource::select('*')->where('ulb_id', $req->auth['ulb_id'])->where('agency_id', NULL)->count();
            $data['noOfVehicle'] = WtResource::select('*')->where('ulb_id', $req->auth['ulb_id'])->count();
            $data['noOfCapacity'] = WtCapacity::select('*')->count();
            $data['userName'] = $req->auth['user_name'];
            $data['userEmail'] = $req->auth['email'];
            return responseMsgs(true, "Ulb Dashboard Data !!!", $data, "110161", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110161", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Generate Payment Order ID
     * | Function - 62
     * | API - 62
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
            $mWtBooking = WtBooking::find($req->applicationId);
            $reqData = [
                "id" => $mWtBooking->id,
                'amount' => $mWtBooking->payment_amount,
                'citizenId' => $mWtBooking->citizen_id,
                'workflowId' => "0",
                'ulbId' => $mWtBooking->ulb_id,
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
                return responseMsgs(false, collect($data->message)->first()[0] ?? $data->message, json_decode($refResponse), "110162", "1.0", "", 'POST', $req->deviceId ?? "");
            }

            $data->name = $mWtBooking->applicant_name;
            $data->email = $mWtBooking->email;
            $data->contact = $mWtBooking->mobile;
            $data->type = "Water Tanker";
            $data->data->citizenId = $mWtBooking->citizen_id;
            $mWtBooking->order_id =  $data->data->orderId;
            $mWtBooking->save();
            return responseMsgs(true, "Payment OrderId Generated Successfully !!!", $data->data, "110162", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110162", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Payment Details By Payment Id
     * | Function - 63
     * | API - 63
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
            $mWtBooking = new WtBooking();
            $payDetails = $mWtBooking->getPaymentDetails($req->paymentId);
            // $payDetails['payment_details'] = json_decode($payDetails->payment_details);
            if (!$payDetails)
                throw new Exception("Payment Details Not Found !!!");
            $payDetails->ulb_name = (collect($ulb)->where("id", $payDetails->ulb_id))->value("ulb_name");
            $payDetails->toll_free_no = (collect($ulb)->where("id", $payDetails->ulb_id))->value("toll_free_no");
            $payDetails->website = (collect($ulb)->where("id", $payDetails->ulb_id))->value("current_website");
            $payDetails->inWords = getIndianCurrency($payDetails->payment_amount) . "Only /-";
            $payDetails->ulbLogo = $this->_ulbLogoUrl . (collect($ulb)->where("id", $payDetails->ulb_id))->value("logo");
            $payDetails->paymentAgainst = "Water Tanker";
            $payDetails->delivery_date = Carbon::parse($payDetails->delivery_date)->format('d-m-Y');
            return responseMsgs(true, "Payment Details Fetched Successfully !!!", $payDetails, '110164', 01, responseTime(), 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110163', 01, "", 'POST', $req->deviceId);
        }
    }

    /**
     * | Get list of Applied and Cancelled Application
     * | Function - 64
     * | API - 64
     */
    public function listAppliedAndCancelledApplication(Request $req)
    {
        try {
            if ($req->auth['user_type'] != 'Citizen')
                throw new Exception('Unauthorized Access !!!');
            // Variable initialization
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingListCitizen()
                ->where('citizen_id', $req->auth['id'])
                ->orderByDesc('id')
                ->get();

            $ulb = $this->_ulbs;
            $list->map(function ($val) use ($ulb) {
                $val->tranId = WtTransaction::select("id")->where("booking_id", $val->id)->whereIn("status", [1, 2])->first()->id ?? "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                return $val;
            });
            $f_list['listApplied'] =  $list->where("is_vehicle_sent", "<>", 2)->values();
            $f_list['listDelivered'] = $list->where("is_vehicle_sent", 2)->values();


            $mWtCancellation = new WtCancellation();
            $list = $mWtCancellation->getCancelBookingList()->where('refund_status', '0');    // 0 - Booking Cancel Success
            if ($req->auth['user_type'] == 'Citizen')
                $list = $list->where('citizen_id', $req->auth['id']);                        // Get Citizen Cancel Application List
            if ($req->auth['user_type'] == 'UlbUser')
                $list = $list->where('ulb_id', $req->auth['id']);                            // Get ULB Cancel Application List
            if ($req->auth['user_type'] == 'Water-Agency')
                $list = $list->where('agency_id', WtAgency::select('id')->wehere('ulb_id', $req->auth['ulb_id'])->first()->id);

            $ulb = $this->_ulbs;
            $f_list['listCancelled'] = $list->map(function ($val) use ($ulb) {
                $val->tranId = WtTransaction::select("id")->where("booking_id", $val->id)->whereIn("status", [1, 2])->first()->id ?? "";
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                $val->cancel_date = Carbon::parse($val->cancel_date)->format('d-m-Y');
                return $val;
            })->values();
            return responseMsgs(true, "Agency Booking List !!!", $f_list, "110164", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110164", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get ULB Dashboard Data
     * | Function - 65
     * | API - 65
     */
    public function reAssignHydrationCenter(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'hydrationCenterId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationErrorV2($validator);
        }
        try {
            // Variable initialization
            $mWtBooking = WtBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("Application Not Found !!!");
            if ($mWtBooking->is_vehicle_sent == 1)
                throw new Exception("This Booking is Not Re-Assign, Because Vehicle Sent Successfully !!!");
            $mWtBooking->hydration_center_id = $req->hydrationCenterId;
            $mWtBooking->save();
            return responseMsgs(true, "Re-Assign Hydration Center Successfully !!!", '', "110165", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110165", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Get Ulb list from juidco database from GuzzleHttp
     * | Function - 66
     * | API - 66
     */
    public function ulbList()
    {
        $redis = Redis::connection();
        try {
            // Variable initialization
            $data1 = null; #json_decode(Redis::get('ulb_masters'));      // Get Value from Redis Cache Memory
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
            $ulb = (new UlbMaster())->getAllUlb();
            $data1 = $ulb->original['data'];
            $data1 = collect($data1);
            $redis->set('ulb_masters', json_encode($data1));
            return $data1;
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

                $magency = new WtAgency();
                $mWtCapacity = new WtCapacity();
                $mWtDriver = new WtDriver();
                $mWtHydrationCenter = new WtHydrationCenter();

                $adencyList = $magency->getAllAgencyForMasterData($req->auth['ulb_id']);
                $data1['agency'] = $adencyList;

                $listCapacity = $mWtCapacity->getCapacityList();
                $data1['capacity'] = $listCapacity;
                $users = User::where("ulb_id", $req->auth["ulb_id"])->get();

                $listDriver = $mWtDriver->getDriverListForMasterData($req->auth['ulb_id'])->map(function ($val) use ($users) {
                    $user = $users->where("id", $val->u_id)->first();
                    $val->email = $user ? $user->email : "";
                    return $val;
                });
                $data1['driver'] = $listDriver;
                if ($req->auth['user_type'] == 'UlbUser')
                    $data1['driver'] = $listDriver->where('agency_id', NULL)->values();
                if ($req->auth['user_type'] == 'Water-Agency')
                    $data1['driver'] = $listDriver->where('agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id ?? 0)->values();

                $hydrationCenter = $mWtHydrationCenter->getHydrationCeenterForMasterData($req->auth['ulb_id']);
                $data1['hydrationCenter'] = $hydrationCenter;

                $mWtUlbCapacityRate = new WtUlbCapacityRate();
                $capacityRate = $mWtUlbCapacityRate->getCapacityRateForMasterData($req->auth['ulb_id']);
                $data1['capacityRate'] = $capacityRate;

                $mWtResource = new WtResource();
                $resource = $mWtResource->getVehicleForMasterData($req->auth['ulb_id']);
                // $data1['vehicle'] = $resource;
                if ($req->auth['user_type'] == 'UlbUser')
                    $data1['vehicle'] = $resource->where('agency_id', NULL)->values();
                if ($req->auth['user_type'] == 'Water-Agency')
                    $data1['vehicle'] = $resource->where('agency_id', WtAgency::select('id')
                        //->where('u_id', $req->auth['id'])->first()->id ?? 0)->values();
                        ->where('ulb_id', $req->auth['ulb_id'])->first()->id ?? 0)->values();

                $mWtLocation = new WtLocation();
                $location = collect($mWtLocation->listLocation($req->auth['ulb_id']))->where('is_in_ulb', '1')->values();
                $data1['location'] = $location;

                $mWtDriverVehicleMap = new WtDriverVehicleMap();
                $list = $mWtDriverVehicleMap->getMapDriverVehicle($req->auth['ulb_id']);
                if ($req->auth['user_type'] == 'Water-Agency')
                    $list = $list->where('agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id ?? 0)->values();

                $ulb = $this->_ulbs;
                $f_list = $list->map(function ($val) use ($ulb) {
                    $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                    $val->driver_vehicle = $val->vehicle_no . "( " . $val->driver_name . " )";
                    return $val;
                });
                $data1['driverVehicleMap'] = $f_list;

                // $redis->set('wt_masters', json_encode($data1));                 // Set Key on Water Tanker masters
            }
            return responseMsgs(true, "Data Fetched !!!", $data1, "110167", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [$e->getFile(), $e->getLine()], "110167", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Payment Success or Failure of Water Tanker
     * | Function - 68
     * | API - 68
     */
    public function paymentSuccessOrFailure(Request $req)
    {
        try {
            $refUser                = authUser($req);
            $refUserId          = $req->citizenId;
            if ($req->orderId != NULL && $req->paymentId != NULL) {
                // Variable initialization
                $msg = '';
                $tranId = "";
                $response = "";
                $req->merge(["isNotWebHook" => true, "paymentModes" => $req->paymentMode, "paymentMode" => "ONLINE"]);
                DB::beginTransaction();
                $wtCount = DB::table('wt_bookings')->where('id', $req->id)->where('order_id', $req->orderId)->count();
                if ($wtCount > 0) {
                    $mWtBooking = WtBooking::find($req->id);
                    $mWtBooking->payment_date = Carbon::now();
                    $mWtBooking->payment_mode = "ONLINE";
                    $mWtBooking->payment_status = 1;
                    $mWtBooking->payment_id = $req->paymentId;
                    $mWtBooking->payment_details = $req->all();
                    $mWtBooking->save();
                    $wtTransaction = new WtTransaction();

                    $wtTransaction->ulb_id = $mWtBooking->ulb_id;
                    $wtTransaction->citizen_id = $req->citizenId;
                    $wtTransaction->tran_type = "Water Tanker Booking";
                    $wtTransaction->ward_id = $mWtBooking->ward_id;
                    $wtTransaction->verify_date = Carbon::now();;
                    $wtTransaction->is_verified = 'true';
                    $wtTransaction->tran_no = $req->transactionNo;
                    $wtTransaction->booking_id = $req->id;
                    $wtTransaction->paid_amount = $req->amount;
                    $wtTransaction->payment_mode = "ONLINE";
                    $wtTransaction->tran_date = Carbon::now();
                    $wtTransaction->save();
                    $mobile = $mWtBooking->mobile;
                    $applicantName = $mWtBooking->applicant_name;
                    $amount = $wtTransaction->paid_amount;
                    $module = "water tanker";
                    $bookingNo = $mWtBooking->booking_no;
                    $tranId  = $wtTransaction->id;
                    $url = "https://aadrikainfomedia.com/citizen/water-tanker-receipt/" . $tranId;
                    // $response = $this->offlinePayment($req);
                    $msg = "Payment Accepted Successfully !!!";
                }
                $stCount = DB::table('st_bookings')->where('id', $req->id)->where('order_id', $req->orderId)->count();
                if ($stCount > 0) {
                    $mStBooking = StBooking::find($req->id);
                    $mStBooking->payment_date = Carbon::now();
                    $mStBooking->payment_mode = "ONLINE";
                    $mStBooking->payment_status = 1;
                    $mStBooking->payment_id = $req->paymentId;
                    $mStBooking->payment_details = $req->all();
                    $mStBooking->save();
                    $stTransaction = new StTransaction();
                    $stTransaction->citizen_id = $req->citizenId;
                    $stTransaction->ulb_id = $mStBooking->ulb_id;
                    $stTransaction->tran_type = "Septic Tanker Booking";
                    $stTransaction->ward_id = $mStBooking->ward_id;
                    $stTransaction->verify_date = Carbon::now();;
                    $stTransaction->is_verified = 'true';
                    $stTransaction->tran_no = $req->transactionNo;
                    $stTransaction->booking_id = $req->id;
                    $stTransaction->paid_amount = $req->amount;
                    $stTransaction->payment_mode = "ONLINE";
                    $stTransaction->tran_date = Carbon::now();
                    $stTransaction->save();
                    $mobile = $mStBooking->mobile;
                    $applicantName = $mStBooking->applicant_name;
                    $amount = $stTransaction->paid_amount;
                    $module = "septic tank";
                    $bookingNo = $mStBooking->booking_no;
                    $tranId  = $stTransaction->id;
                    $url = "https://aadrikainfomedia.com/citizen/septic-tank-receipt/" . $tranId;
                    // $response = (new SepticTankController())->offlinePayment($req);
                    $msg = "Payment Accepted Successfully !!!";
                }
                DB::commit();
                // $data = $response->original["data"];
                #_Whatsaap Message
                // if (strlen($mobile) == 10) {
                //     $whatsapp2 = (Whatsapp_Send(
                //         $mobile,
                //         "all_module_payment_receipt",
                //         [
                //             "content_type" => "text",
                //             [
                //                 $applicantName ?? "Citizen",
                //                 $amount,
                //                 "Booking No.",
                //                 $bookingNo,
                //                 $url
                //             ]
                //         ]
                //     ));
                // }
                return responseMsgs(true, $msg, "", '110168', 01, responseTime(), 'POST', $req->deviceId);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", '110168', 01, "", 'POST', $req->deviceId);
        }
    }

    public function offlinePayment(PaymentCounterReq $req)
    {
        try {

            $user = Auth()->user();
            $userType = $user->user_type;
            $mergData = [
                'departmentId' => Config::get('constants.WATER_TANKER_MODULE_ID'),
                "moduleId" => Config::get('constants.WATER_TANKER_MODULE_ID'),
                "gatewayType"   => "",
                "id" => $req->applicationId,
                "orderId" => $req->paymentId,
                "paymentId" => $req->paymentId,
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
            $booking = WtBooking::find($req->applicationId);
            if ($booking->ulb_id != $user->ulb_id) {
                throw new Exception("this application related to another ulb");
            }
            if (in_array($booking->payment_status, [1, 2])) {
                throw new Exception("Payment Already Done");
            }
            $mTransaction = new WtTransaction();
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
            $booking->payment_status = $req->paymentMode == "CASH" ? 1 : 2;
            $booking->payment_id = "";
            $booking->payment_details = json_encode($mergData);
            $booking->payment_by_user_id = $user->id;

            $mTransaction->booking_id = $booking->id;
            $mTransaction->ward_id = $booking->ward_id;
            $mTransaction->ulb_id = $booking->ulb_id;
            $mTransaction->tran_type = "Water Tanker Booking";
            $mTransaction->tran_no = $tranNo;
            $mTransaction->tran_date = $booking->payment_date;
            $mTransaction->payment_mode = $booking->payment_mode;
            $mTransaction->paid_amount = $booking->payment_amount;
            $mTransaction->emp_dtl_id = $user->id;
            $mTransaction->emp_user_type = $userType;
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
            $tradeChq = new WtChequeDtl();
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
    /**
     * | Get Payment Reciept
     * | Function - 69
     * | API - 69
     */
    public function getWaterTankerReciept(Request $req)
    {
        try {
            // Variable initialization
            $ulb = $this->ulbList();
            $mWtBooking = new WtBooking();
            $mTransaction = WtTransaction::whereIn("status", [1, 2])->find($req->tranId);
            if (!$mTransaction) {
                throw new Exception("Payment Details Not Found !!!");
            }
            $appData = $mWtBooking->getRecieptDetails($mTransaction->booking_id);
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
            $appData->toll_free_no = (collect($ulb)->where("id", $appData->ulb_id))->value("toll_free_no");
            $appData->website = (collect($ulb)->where("id", $appData->ulb_id))->value("current_website");
            $appData->inWords = getIndianCurrency($mTransaction->paid_amount) . "Only /-";
            $appData->ulbLogo = $this->_ulbLogoUrl . (collect($ulb)->where("id", $appData->ulb_id))->value("logo");
            $appData->paymentAgainst = "Water Tanker";
            $appData->delivery_date = Carbon::parse($appData->delivery_date)->format('d-m-Y');
            return responseMsgs(true, "Payment Details Fetched Successfully !!!", $appData, '110169', 01, responseTime(), 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110169', 01, "", 'POST', $req->deviceId);
        }
    }

    /**
     * | Get Feedback From Citizen
     * | Function - 70
     * | API - 70
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
            $applicationDetails = WtBooking::find($req->applicationId);
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
     * | Function - 71
     * | API - 71
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
            $applicationDetails = WtBooking::find($req->applicationId);
            if (!$applicationDetails)
                throw new Exception("Application Not Found !!!");
            if ($applicationDetails->feedback_date === NULL)
                throw new Exception("No Any Feedback Against Booking !!!");
            $commentDetails = array();
            $commentDetails['comment'] = $applicationDetails->feedback;
            $commentDetails['comment_date'] = Carbon::parse($applicationDetails->feedback_date)->format('d-m-Y');
            return responseMsgs(true, "Feedback !!!", $commentDetails, "110271", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110271", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Water Tanker Book By ULB
     */
    public function bookingByUlb(UlbWaterTankerBooking $req)
    {
        try {
            // Variable initialization
            $mUlbWaterTankerBooking = new UlbWaterTankerBooking();
            $req->merge(['ulbId' => $req->auth['id']]);

            DB::beginTransaction();
            $res = $mUlbWaterTankerBooking->storeBooking($req);                                                                     // Store Booking Informations
            DB::commit();
            return responseMsgs(true, "Booking Added Successfully !!!",  $res, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * function added by sandeep bara
     */

    public function vehicleDriverMasterUlbWise(Request $req)
    {
        try {
            if (!in_array($req->auth['user_type'], ["UlbUser", "Water-Agency"]))
                throw new Exception('Unauthorized Access !!!');
            // Initialize Variable
            $req->merge(['ulbId' => $req->auth['ulb_id']]);
            $mWtResource = new WtResource();
            $resource = $mWtResource->getVehicleForMasterData($req->auth['ulb_id']);
            // $data1['vehicle'] = $resource;
            if ($req->auth['user_type'] == 'UlbUser')
                $resource = $resource->where('agency_id', NULL)->values();
            if ($req->auth['user_type'] == 'Water-Agency')
                $resource = $resource->where('agency_id', WtAgency::select('id')
                    //->where('u_id', $req->auth['id'])->first()->id ?? 0)->values();
                    ->where('ulb_id', $req->auth['ulb_id'])->first()->id ?? 0)->values();

            $users = User::where("ulb_id", $req->auth["ulb_id"])->get();
            $mWtDriver = new WtDriver();
            $driver = $mWtDriver->getDriverListForMasterData($req->auth['ulb_id'])->map(function ($val) use ($users) {
                $user = $users->where("id", $val->u_id)->first();
                $val->email = $user ? $user->email : "";
                return $val;
            });
            if ($req->auth['user_type'] == 'UlbUser')
                $driver = $driver->where('agency_id', NULL)->values();
            if ($req->auth['user_type'] == 'Water-Agency')
                $driver = $driver->where('agency_id', WtAgency::select('id')->where('ulb_id', $req->auth['ulb_id'])->first()->id ?? 0)->values();;
            $f_list['listResource'] = $resource->values();
            $f_list['listDriver'] = $driver->values();
            return responseMsgs(true, "Data Fetched Successfully !!!", $f_list, "110217", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110217", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }
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
            $data = WtBooking::select("wt_bookings.*", "wt_resources.vehicle_name", "wt_resources.vehicle_no", "wt_resources.resource_type", "wt_locations.location")
                ->join("wt_drivers", "wt_drivers.id", "wt_bookings.driver_id")
                ->join("wt_resources", "wt_resources.id", "wt_bookings.vehicle_id")
                ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wt_bookings.location_id')
                ->where("wt_drivers.u_id", $user["id"])
                ->where("wt_bookings.status", 1)
                ->where("wt_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->where('is_vehicle_sent', '!=', 2)
                ->where('delivery_track_status', 0);

            $reassign = WtBooking::select("wt_bookings.*", "wt_resources.vehicle_name", "wt_resources.vehicle_no", "wt_resources.resource_type", "wt_locations.location")
                ->join("wt_reassign_bookings", "wt_reassign_bookings.application_id", "wt_bookings.id")
                ->join("wt_drivers", "wt_drivers.id", "wt_reassign_bookings.driver_id")
                ->join("wt_resources", "wt_resources.id", "wt_reassign_bookings.vehicle_id")
                ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wt_bookings.location_id')
                ->where("wt_drivers.u_id", $user["id"])
                ->where("wt_bookings.status", 1)
                ->where("wt_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->where('is_vehicle_sent', '!=', 2)
                ->where('wt_reassign_bookings.delivery_track_status', 0);

            if ($key) {
                $data = $data->where(function ($where) use ($key) {
                    $where->orWhere("wt_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.mobile", "LIKE", "%$key%");
                });
                $reassign = $reassign->where(function ($where) use ($key) {
                    $where->orWhere("wt_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.mobile", "LIKE", "%$key%");
                });
            }
            if ($formDate && $uptoDate) {
                $data = $data->whereBetween("assign_date", [$formDate, $uptoDate]);
                $reassign = $reassign->whereBetween("re_assign_date", [$formDate, $uptoDate]);
            }

            $data = $data;
            $data = $data->orderBy("delivery_date", "ASC")
                ->orderBy("delivery_time", "ASC")
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
            $data = WtBooking::select(
                "wt_bookings.*",
                "wt_resources.vehicle_name",
                "wt_resources.vehicle_no",
                "wt_resources.resource_type",
                "wt_bookings.delivery_track_status",
                "wt_bookings.delivery_comments",
                "wt_bookings.delivery_latitude",
                "wt_bookings.delivery_longitude",
                "wt_bookings.driver_delivery_update_date_time",
                "assign_date AS assign_date",
                "wt_bookings.driver_delivery_update_date_time AS update_date_time",
                "wt_locations.location"
            )
                ->join("wt_drivers", "wt_drivers.id", "wt_bookings.driver_id")
                ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wt_bookings.location_id')
                ->join("wt_resources", "wt_resources.id", "wt_bookings.vehicle_id")
                ->where("wt_drivers.u_id", $user["id"])
                ->where("wt_bookings.status", 1)
                ->where("wt_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->whereIn('delivery_track_status', [1, 2]);

            $reassign = WtBooking::select(
                "wt_bookings.*",
                "wt_resources.vehicle_name",
                "wt_resources.vehicle_no",
                "wt_resources.resource_type",
                "wt_reassign_bookings.delivery_track_status",
                "wt_reassign_bookings.delivery_comments",
                "wt_reassign_bookings.delivery_latitude",
                "wt_reassign_bookings.delivery_longitude",
                "wt_reassign_bookings.driver_delivery_update_date_time",
                "re_assign_date AS assign_date",
                "wt_reassign_bookings.driver_delivery_update_date_time AS update_date_time",
                "wt_locations.location"
            )
                ->join("wt_reassign_bookings", "wt_reassign_bookings.application_id", "wt_bookings.id")
                ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wt_bookings.location_id')
                ->join("wt_drivers", "wt_drivers.id", "wt_reassign_bookings.driver_id")
                ->join("wt_resources", "wt_resources.id", "wt_reassign_bookings.vehicle_id")
                ->where("wt_drivers.u_id", $user["id"])
                ->where("wt_bookings.status", 1)
                ->where("wt_bookings.ulb_id", $user["ulb_id"])
                ->where('assign_date', '!=', NULL)
                ->whereIn('wt_reassign_bookings.delivery_track_status', [1, 2]);

            if ($key) {
                $data = $data->where(function ($where) use ($key) {
                    $where->orWhere("wt_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.mobile", "LIKE", "%$key%");
                });
                $reassign = $reassign->where(function ($where) use ($key) {
                    $where->orWhere("wt_bookings.booking_no", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.applicant_name", "LIKE", "%$key%")
                        ->orWhere("wt_bookings.mobile", "LIKE", "%$key%");
                });
            }
            if ($formDate && $uptoDate) {
                $data = $data->whereBetween(DB::raw("cast(wt_bookings.driver_delivery_update_date_time as date)"), [$formDate, $uptoDate]);
                $reassign = $reassign->whereBetween(DB::raw("cast(wt_reassign_bookings.driver_delivery_update_date_time as date)"), [$formDate, $uptoDate]);
            }

            $data = $data->union($reassign);
            $data = $data->orderBy("update_date_time", "DESC");
            $perPage = $request->perPage ? $request->perPage : 10;
            $data = $data->paginate($perPage);
            $f_list = [
                "currentPage" => $data->currentPage(),
                "lastPage" => $data->lastPage(),
                "total" => $data->total(),
                "data" => collect($data->items())->map(function ($val) {
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
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
            $ulbId = $user["ulb_id"];
            $mWtBooking = new WtBooking();
            $mWtReassignBooking = new WtReassignBooking();
            $data = $mWtBooking->getBookingListForCancle()
                // ->leftJoin(
                //     DB::raw("(SELECT DISTINCT application_id FROM wt_reassign_bookings WHERE delivery_track_status !=0 )reassign"),
                //     function($join){
                //         $join->on("reassign.application_id","wb.id");
                //     }
                //     )
                ->where("wb.delivery_track_status", 1)
                ->where("wb.is_vehicle_sent", "<", 2)
                ->where("wb.ulb_id", $ulbId);
            // ->whereNull("reassign.application_id");
            if ($formDate && $uptoDate) {
                $data->whereBetween(DB::raw("CAST(wb.driver_delivery_update_date_time as date)"), [$formDate, $uptoDate]);
            }
            $DocUpload = new DocUpload();
            $perPage = $request->perPage ? $request->perPage : 10;
            $list = $data->paginate($perPage);
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) use ($mWtReassignBooking, $DocUpload) {
                    // $reassign = $mWtReassignBooking->select("wt_reassign_bookings.*", "dr.driver_name", "res.vehicle_no")
                    //     ->leftjoin('wt_drivers as dr', 'wt_reassign_bookings.driver_id', '=', 'dr.id')
                    //     ->leftjoin('wt_resources as res', 'wt_reassign_bookings.vehicle_id', '=', 'res.id')
                    //     ->where("wt_reassign_bookings.application_id", $val->id)
                    //     ->orderBy("wt_reassign_bookings.id", "DESC")
                    //     ->first();
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                    //$val->assign_date = $reassign ? $reassign->re_assign_date : $val->assign_date;
                    $val->assign_date  = Carbon::parse($val->assign_date)->format('d-m-Y');
                    $val->cancelledDate  = Carbon::parse($val->cancelledDate)->format('d-m-Y');
                    $uploadDoc = ($DocUpload->getSingleDocUrl($val));
                    $val->doc_path = $uploadDoc["doc_path"] ?? "";
                    // $val->driver_vehicle = $reassign ? $reassign->vehicle_no . " ( " . $reassign->driver_name . " )" : $val->vehicle_no . " ( " . $val->driver_name . " )";
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
            if (!$user || $user["user_type"] != "Driver") {
                throw new Exception("You are not authorized for this");
            }
            $driver = WtDriver::where("u_id", $user["id"])->first();
            $ModelWtBooking =   new WtBooking();
            $booking = $ModelWtBooking->find($request->applicationId);
            if (!$booking) {
                throw new Exception("booking not fund");
            }
            $reBooking = $booking->getLastReassignedBooking();
            $updateData = $booking;
            $isReassigned = $reBooking ? true : false;
            if ($updateData->driver_id != $driver->id) {
                throw new Exception("You have not this booking");
            }
            $document = new DocUpload();
            $document = $document->severalDoc($request);
            $document = $document->original["data"];
            $sms = "Delivery Canceled";
            if ($request->status == 2) {
                $sms = "Delivered Successfully";
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
                $booking->delivered_by_driver_id = $driver->id;
                $booking->driver_delivery_date_time = Carbon::now();
            }

            DB::beginTransaction();
            $updateData->update();
            $booking->update();

            DB::commit();
            #_Whatsaap Message
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
            //                 "87787878787 "
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

    // public function searchApp(Request $req)
    // {
    //     try {
    //         $user = Auth()->user();
    //         $ulbId = $user->ulb_id ?? null;
    //         $key = $req->key;
    //         $fromDate = $uptoDate = null;
    //         if ($req->fromDate) {
    //             $fromDate = $req->fromDate;
    //         }
    //         if ($req->uptoDate) {
    //             $uptoDate = $req->uptoDate;
    //         }
    //         //$list = WtBooking::select("*");

    //         $bookings = WtBooking::select('applicant_name','booking_date','booking_no','delivery_date','delivery_time','payment_status','id');


    //         $cancellations = WtCancellation::select('applicant_name','booking_date','booking_no','delivery_date','delivery_time','payment_status','id');

    //         $list = $bookings->union($cancellations);

    //         if ($key) {
    //             $list = $list->where(function ($where) use ($key) {
    //                 $where->orWhere("booking_no", "ILIKE", "%$key%")
    //                     ->orWhere("applicant_name", "ILIKE", "%$key%")
    //                     ->orWhere("mobile", "ILIKE", "%$key%");
    //                 // ->orWhere("holding_no","ILIKE","%$key%");                        
    //             });
    //         }
    //         if ($ulbId) {
    //             $list = $list->where("ulb_id", $ulbId);
    //         }
    //         if ($fromDate && $uptoDate) {
    //             $list = $list->whereBetween("booking_date", [$fromDate, $uptoDate]);
    //         }
    //         $list = $list->orderBy("id", "DESC");
    //         $perPage = $req->perPage ? $req->perPage : 10;
    //         $list = $list->paginate($perPage);
    //         $f_list = [
    //             "currentPage" => $list->currentPage(),
    //             "lastPage" => $list->lastPage(),
    //             "total" => $list->total(),
    //             "data" => collect($list->items())->map(function ($val) {
    //                 $val->payment_details = json_decode($val->payment_details);
    //                 $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
    //                 //$val->cleaning_date = Carbon::parse($val->cleaning_date)->format('d-m-Y');
    //                 //$val->assign_date = Carbon::parse($val->assign_date)->format('d-m-Y');
    //                 return $val;
    //             }),
    //         ];
    //         return responseMsgs(true, "Booking list",  $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
    //     }
    // }


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

            // Apply filters individually to each query before union
            $bookings = WtBooking::select('applicant_name', 'booking_date', 'booking_no', 'delivery_date', 'delivery_time', 'payment_status', 'feedback', 'id');
            $cancellations = WtCancellation::select('applicant_name', 'booking_date', 'booking_no', 'delivery_date', 'delivery_time', 'payment_status', 'feedback', 'id');

            if ($key) {
                $bookings = $bookings->where(function ($where) use ($key) {
                    $where->orWhere("booking_no", "ILIKE", "%$key%")
                        ->orWhere("applicant_name", "ILIKE", "%$key%")
                        ->orWhere("mobile", "ILIKE", "%$key%");
                });

                $cancellations = $cancellations->where(function ($where) use ($key) {
                    $where->orWhere("booking_no", "ILIKE", "%$key%")
                        ->orWhere("applicant_name", "ILIKE", "%$key%")
                        ->orWhere("mobile", "ILIKE", "%$key%");
                });
            }

            if ($ulbId) {
                $bookings = $bookings->where("ulb_id", $ulbId);
                $cancellations = $cancellations->where("ulb_id", $ulbId);
            }

            if ($fromDate && $uptoDate) {
                $bookings = $bookings->whereBetween("booking_date", [$fromDate, $uptoDate]);
                $cancellations = $cancellations->whereBetween("booking_date", [$fromDate, $uptoDate]);
            }

            // Combine the queries using union
            $list = $bookings->union($cancellations)->orderBy("id", "DESC");

            // Paginate the combined result
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);

            // Format the response
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) {
                    $val->payment_details = json_decode($val->payment_details);
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    return $val;
                }),
            ];

            return responseMsgs(true, "Booking list",  $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
        }
    }

    // Revised searchApp function with payment status filter : currently using this api for search
    // public function searchAppNew(Request $req)
    // {
    //     try {
    //         $user = Auth()->user();
    //         $ulbId = $user->ulb_id ?? null;
    //         $key = $req->key;
    //         $fromDate = $uptoDate = null;

    //         if ($req->fromDate) {
    //             $fromDate = $req->fromDate;
    //         }
    //         if ($req->uptoDate) {
    //             $uptoDate = $req->uptoDate;
    //         }

    //         // NEW payment filter
    //         $paymentStatus = $req->paymentStatus; // paid/unpaid/free
    //         $statusMap = ['paid' => 1, 'unpaid' => 0, 'free' => 2];

    //         // Base queries
    //         $bookings = WtBooking::select(
    //             'applicant_name', 'booking_date', 'booking_no',
    //             'delivery_date', 'delivery_time', 'payment_status',
    //             'feedback', 'payment_details', 'id'
    //         );

    //         $cancellations = WtCancellation::select(
    //             'applicant_name', 'booking_date', 'booking_no',
    //             'delivery_date', 'delivery_time', 'payment_status',
    //             'feedback', 'payment_details', 'id'
    //         );

    //         // Search filter
    //         if ($key) {
    //             $bookings->where(function ($q) use ($key) {
    //                 $q->orWhere("booking_no", "ILIKE", "%$key%")
    //                     ->orWhere("applicant_name", "ILIKE", "%$key%")
    //                     ->orWhere("mobile", "ILIKE", "%$key%");
    //             });

    //             $cancellations->where(function ($q) use ($key) {
    //                 $q->orWhere("booking_no", "ILIKE", "%$key%")
    //                     ->orWhere("applicant_name", "ILIKE", "%$key%")
    //                     ->orWhere("mobile", "ILIKE", "%$key%");
    //             });
    //         }

    //         // ULB filter
    //         if ($ulbId) {
    //             $bookings->where("ulb_id", $ulbId);
    //             $cancellations->where("ulb_id", $ulbId);
    //         }

    //         // Date filter
    //         if ($fromDate && $uptoDate) {
    //             $bookings->whereBetween("booking_date", [$fromDate, $uptoDate]);
    //             $cancellations->whereBetween("booking_date", [$fromDate, $uptoDate]);
    //         }

    //         // Apply payment filter to BOTH tables
    //         if ($paymentStatus && isset($statusMap[$paymentStatus])) {
    //             $status = $statusMap[$paymentStatus];
    //             $bookings->where("payment_status", $status);
    //             $cancellations->where("payment_status", $status);
    //         }

    //         // Build union query
    //         $list = $bookings->union($cancellations);

    //         // Order + paginate
    //         $perPage = $req->perPage ?? 10;

    //         // Laravel requires wrapping union query for pagination
    //         $final = DB::query()
    //             ->fromSub($list, 't')
    //             ->orderBy("id", "DESC")
    //             ->paginate($perPage);

    //         // Format output
    //         $f_list = [
    //             "currentPage" => $final->currentPage(),
    //             "lastPage" => $final->lastPage(),
    //             "total" => $final->total(),
    //             "data" => collect($final->items())->map(function ($val) {
    //                 $val->payment_details = json_decode($val->payment_details);
    //                 $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
    //                 return $val;
    //             }),
    //         ];

    //         return responseMsgs(true, "Booking list", $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
    //     }
    // }

    // public function searchAppNew(Request $req)
    // {
    //     try {
    //         $user  = Auth()->user();
    //         $ulbId = $user->ulb_id ?? null;

    //         $key      = $req->key;
    //         $fromDate = $req->fromDate ?? null;
    //         $uptoDate = $req->uptoDate ?? null;

    //         /* ---------------- Common Filters ---------------- */

    //         $paymentStatus = $req->paymentStatus; // paid | unpaid | free
    //         $statusMap = ['paid' => 1, 'unpaid' => 0, 'free' => 2];

    //         /* ---------------- Booking-only Filters ---------------- */

    //         $bookingType     = $req->bookingType;       // free | paid
    //         $applicationType = $req->applicationType;   // forwarded | backwarded | new
    //         $userType        = $req->userType;           // Citizen | JSK | Employee

    //         // If any booking-only filter exists → DO NOT UNION cancellations
    //         $hasBookingOnlyFilter =
    //             $bookingType ||
    //             $userType ||
    //             in_array($applicationType, ['forwarded', 'backwarded', 'new']);

    //         /* ---------------- Base Booking Query ---------------- */

    //         $bookings = WtBooking::select('id','applicant_name','booking_date','booking_no','delivery_date','delivery_time','payment_status',
    //             'payment_details', 'feedback','parked_status','current_role','status'
    //         );

    //         /* ---------------- Base Cancellation Query ---------------- */

    //         $cancellations = WtCancellation::select(
    //             'id',
    //             'applicant_name',
    //             'booking_date',
    //             'booking_no',
    //             'delivery_date',
    //             'delivery_time',
    //             'payment_status',
    //             'payment_details',
    //             'feedback',
    //             DB::raw('NULL as parked_status'),
    //             DB::raw('NULL as current_role'),
    //             DB::raw('NULL as status')
    //         );

    //         /* ---------------- Search ---------------- */

    //         if ($key) {
    //             $bookings->where(function ($q) use ($key) {
    //                 $q->where('booking_no', 'ILIKE', "%$key%")
    //                 ->orWhere('applicant_name', 'ILIKE', "%$key%")
    //                 ->orWhere('mobile', 'ILIKE', "%$key%");
    //             });

    //             $cancellations->where(function ($q) use ($key) {
    //                 $q->where('booking_no', 'ILIKE', "%$key%")
    //                 ->orWhere('applicant_name', 'ILIKE', "%$key%")
    //                 ->orWhere('mobile', 'ILIKE', "%$key%");
    //             });
    //         }

    //         /* ---------------- ULB ---------------- */

    //         if ($ulbId) {
    //             $bookings->where('ulb_id', $ulbId);
    //             $cancellations->where('ulb_id', $ulbId);
    //         }

    //         /* ---------------- Date ---------------- */

    //         if ($fromDate && $uptoDate) {
    //             $bookings->whereBetween('booking_date', [$fromDate, $uptoDate]);
    //             $cancellations->whereBetween('booking_date', [$fromDate, $uptoDate]);
    //         }

    //         /* ---------------- Payment Status ---------------- */

    //         if ($paymentStatus && isset($statusMap[$paymentStatus])) {
    //             $status = $statusMap[$paymentStatus];
    //             $bookings->where('payment_status', $status);
    //             $cancellations->where('payment_status', $status);
    //         }

    //         /* ---------------- BookingType (FIXED LOGIC) ---------------- */

    //         // FREE = no payment required
    //         if ($bookingType === 'free') {
    //             $bookings->where('is_tanker_free', true);

    //         // PAID = payment required AND payment completed
    //         } elseif ($bookingType === 'paid') {
    //             $bookings
    //                 ->where('is_tanker_free', false)
    //                 ->where('payment_status', 1);
    //         }

    //         /* ---------------- userType ---------------- */

    //         if ($userType) {
    //             $bookings->where('user_type', $userType);
    //         }

    //         /* ---------------- applicationType (STRICT FILTERS) ---------------- */

    //         if ($applicationType === 'backwarded') {
    //             $bookings->where('parked_status', true);

    //         } elseif ($applicationType === 'forwarded') {
    //             $bookings
    //                 ->where('current_role', 35)
    //                 ->where(function ($q) {
    //                     $q->whereNull('parked_status')
    //                     ->orWhere('parked_status', false);
    //                 });

    //         } elseif ($applicationType === 'new') {
    //             $bookings->where('status', 1);
    //         }

    //         /* ---------------- Final Source ---------------- */

    //         $list = $hasBookingOnlyFilter
    //             ? $bookings
    //             : $bookings->union($cancellations);

    //         /* ---------------- Pagination ---------------- */

    //         $final = DB::query()
    //             ->fromSub($list, 't')
    //             ->orderBy('id', 'DESC')
    //             ->paginate($req->perPage ?? 10);

    //         /* ---------------- Response Mapping ---------------- */

    //         $response = [
    //             'currentPage' => $final->currentPage(),
    //             'lastPage'    => $final->lastPage(),
    //             'total'       => $final->total(),
    //             'data'        => collect($final->items())->map(function ($item) {

    //                 // applicationType (RESPONSE ONLY, priority-based)
    //                 if ($item->parked_status === true) {
    //                     $item->applicationType = 'backwarded';
    //                 } elseif ($item->current_role == 35) {
    //                     $item->applicationType = 'forwarded';
    //                 } elseif ($item->status == 1) {
    //                     $item->applicationType = 'New Application';
    //                 } else {
    //                     $item->applicationType = 'In Process';
    //                 }

    //                 $item->payment_details = json_decode($item->payment_details);
    //                 $item->booking_date = Carbon::parse($item->booking_date)->format('d-m-Y');

    //                 unset($item->parked_status, $item->current_role, $item->status);

    //                 return $item;
    //             }),
    //         ];

    //         return responseMsgs(
    //             true,
    //             'Booking list',
    //             $response,
    //             '110115',
    //             '1.0',
    //             responseTime(),
    //             'POST',
    //             $req->deviceId ?? ''
    //         );

    //     } catch (\Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), '', 'POST', $req->deviceId ?? '');
    //     }
    // }

    public function searchAppNew(Request $req)
    {
        try {
            $user  = Auth()->user();
            $ulbId = $user->ulb_id ?? null;

            $key      = $req->key;
            $fromDate = $req->fromDate;
            $uptoDate = $req->uptoDate;

            $bookingType     = $req->bookingType;       // paid | free
            $applicationType = $req->applicationType;   // forwarded | backwarded | new | unpaid
            $userType        = $req->userType;           // Citizen | JSK | Verifier (Employee in DB)

            /* ---------------- Booking-only decision ---------------- */

            $hasBookingOnlyFilter =
                $bookingType ||
                in_array($userType, ['Citizen', 'JSK', 'Verifier', 'Employee']) ||
                in_array($applicationType, ['forwarded', 'backwarded', 'new', 'unpaid']);

            /* ---------------- Base Booking Query ---------------- */

            $bookings = WtBooking::select(
                'id',
                'applicant_name',
                'booking_date',
                'booking_no',
                'delivery_date',
                'delivery_time',
                'payment_status',
                'payment_details',
                'feedback',
                'parked_status',
                'current_role',
                'status',
                'user_type'
            );

            /* ---------------- Base Cancellation Query ---------------- */

            $cancellations = WtCancellation::select(
                'id',
                'applicant_name',
                'booking_date',
                'booking_no',
                'delivery_date',
                'delivery_time',
                'payment_status',
                'payment_details',
                'feedback',
                DB::raw('NULL::boolean as parked_status'),
                DB::raw('NULL::integer as current_role'),
                DB::raw('NULL::integer as status'),
                'user_type'
            );

            /* ---------------- Search ---------------- */

            if ($key) {
                $bookings->where(function ($q) use ($key) {
                    $q->where('booking_no', 'ILIKE', "%$key%")
                    ->orWhere('applicant_name', 'ILIKE', "%$key%")
                    ->orWhere('mobile', 'ILIKE', "%$key%");
                });

                $cancellations->where(function ($q) use ($key) {
                    $q->where('booking_no', 'ILIKE', "%$key%")
                    ->orWhere('applicant_name', 'ILIKE', "%$key%")
                    ->orWhere('mobile', 'ILIKE', "%$key%");
                });
            }

            /* ---------------- ULB ---------------- */

            if ($ulbId) {
                $bookings->where('ulb_id', $ulbId);
                $cancellations->where('ulb_id', $ulbId);
            }

            /* ---------------- Date ---------------- */

            if ($fromDate && $uptoDate) {
                $bookings->whereBetween('booking_date', [$fromDate, $uptoDate]);
                $cancellations->whereBetween('booking_date', [$fromDate, $uptoDate]);
            }

            /* ---------------- Booking Type ---------------- */

            if ($bookingType === 'free') {
                $bookings->where('is_tanker_free', true);

            } elseif ($bookingType === 'paid') {
                $bookings->where('is_tanker_free', false);
            }

            /* ---------------- User Type (Verifier ⇄ Employee) ---------------- */

            if ($userType === 'Verifier' || $userType === 'Employee') {
                $bookings->where('user_type', 'Employee');
                $cancellations->where('user_type', 'Employee');

            } elseif ($userType) {
                $bookings->where('user_type', $userType);
                $cancellations->where('user_type', $userType);
            }

            /* ---------------- Application Type ---------------- */

            if ($applicationType === 'backwarded') {

                $bookings->where('parked_status', true);

            } elseif ($applicationType === 'forwarded') {

                $bookings
                    ->where('current_role', 35)
                    ->where(function ($q) {
                        $q->whereNull('parked_status')
                        ->orWhere('parked_status', false);
                    });

            } elseif ($applicationType === 'new') {

                $bookings->where('status', 1);

            } elseif ($applicationType === 'unpaid') {

                // 🔥 NEW FILTER
                $bookings->where('payment_status', 0);
            }

            /* ---------------- Final Source ---------------- */

            $list = $hasBookingOnlyFilter
                ? $bookings
                : $bookings->unionAll($cancellations);

            /* ---------------- Pagination ---------------- */

            $final = DB::query()
                ->fromSub($list, 't')
                ->orderBy('id', 'DESC')
                ->paginate($req->perPage ?? 10);

            /* ---------------- Response ---------------- */

            $response = [
                'currentPage' => $final->currentPage(),
                'lastPage'    => $final->lastPage(),
                'total'       => $final->total(),
                'data'        => collect($final->items())->map(function ($item) use ($applicationType) {

                    // Normalize user_type
                    if ($item->user_type === 'Employee') {
                        $item->user_type = 'Verifier';
                    }

                    // Application type (response)
                    if (!empty($applicationType)) {
                        $item->applicationType = $applicationType;
                    } else {
                        if ($item->parked_status === true) {
                            $item->applicationType = 'backwarded';
                        } elseif ($item->current_role == 35) {
                            $item->applicationType = 'forwarded';
                        } elseif ($item->status == 1) {
                            $item->applicationType = 'new';
                        } elseif ($item->payment_status == 0) {
                            $item->applicationType = 'unpaid';
                        } else {
                            $item->applicationType = 'in_process';
                        }
                    }

                    $item->payment_details = json_decode($item->payment_details);
                    $item->booking_date = Carbon::parse($item->booking_date)->format('d-m-Y');

                    return $item;
                }),
            ];

            return responseMsgs(
                true,
                'Booking list',
                $response,
                '110115',
                '1.0',
                responseTime(),
                'POST',
                $req->deviceId ?? ''
            );

        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), '', 'POST', $req->deviceId ?? '');
        }
    }



    /**
     * | Get Application Status 
     */
    // public function getAppStatus($appId)
    // {
    //     $booking = WtBooking::find($appId);
    //     if (!$booking) {
    //         $booking = WtCancellation::find($appId);
    //     }
    //     $driver  = $booking->getAssignedDriver();
    //     $vehicle  = $booking->getAssignedVehicle();
    //     $status = "";
    //     if ($booking->getTable() == (new WtCancellation())->getTable()) {
    //         $status = "Booking canceled on " . Carbon::parse($booking->cancel_date)->format("d-m-Y") . " by " . $booking->cancelled_by;
    //     } elseif ($booking->payment_status == 0) {
    //         $status = "Payment Pending of amount " . $booking->payment_amount;
    //     } elseif ($booking->delivery_track_status == 2) {
    //         $status = "Water Tanker Delivered On " . Carbon::parse($booking->driver_delivery_update_date_time)->format("d-m-Y h:i:s A");
    //     } elseif ($booking->delivery_track_status == 1) {
    //         $status = "Water Tanker Delivery trip Cancelled By Driver Due To " . Str::title($booking->delivery_comments);
    //     } elseif ($booking->driver_id && $booking->vehicle_id) {
    //         $status = "Driver (" . $driver->driver_name . ") And Vehicle (" . $vehicle->vehicle_no . ") assigned";
    //     } elseif (!$booking->driver_id && !$booking->vehicle_id) {
    //         $status = "Driver And Vehicle not assigned";
    //     } elseif (!$booking->driver_id && $booking->vehicle_id) {
    //         $status = "Driver is not assigned But Vehicle assigned ";
    //     } elseif ($booking->driver_id && !$booking->vehicle_id) {
    //         $status = "Driver is assigned But Vehicle not assigned ";
    //     } elseif ($booking->is_vehicle_sent = 1) {
    //         $status = "Driver is going for delivery";
    //     }
    //     return $status;
    // }

    public function getAppStatus($appId)
    {
        $booking = WtBooking::find($appId);
        if (!$booking) {
            $booking = WtCancellation::find($appId);
        }

        if (!$booking) {
            return "";
        }

        $driver  = $booking->getAssignedDriver();
        $vehicle = $booking->getAssignedVehicle();
        $status  = "";

        // Booking Cancelled (highest priority)
        if ($booking->getTable() == (new WtCancellation())->getTable()) {
            $status = "Booking canceled on " .
                Carbon::parse($booking->cancel_date)->format("d-m-Y") .
                " by " . $booking->cancelled_by;
        // Application Pending at Verifier (NEW)
        } elseif ($booking->status == 1 && $booking->current_role == 79) {
            $status = "Application is Pending at Verifier";
        // Backwarded by Verifier
        } elseif ($booking->parked_status === true && $booking->current_role == 79) {
            $status = "Backwarded by Verifier";
        //Payment Pending
        } elseif ($booking->payment_status == 0) {
            $status = "Payment Pending of amount " . $booking->payment_amount;
        //Delivered
        } elseif ($booking->delivery_track_status == 2) {
            $status = "Water Tanker Delivered On " .
                Carbon::parse($booking->driver_delivery_update_date_time)
                    ->format("d-m-Y h:i:s A");
        // Delivery cancelled by driver
        } elseif ($booking->delivery_track_status == 1) {
            $status = "Water Tanker Delivery trip Cancelled By Driver Due To " .
                Str::title($booking->delivery_comments);
        // Driver & Vehicle assigned
        } elseif ($booking->driver_id && $booking->vehicle_id) {
            $status = "Driver (" . ($driver->driver_name ?? '') .
                ") And Vehicle (" . ($vehicle->vehicle_no ?? '') . ") assigned";
        // Neither assigned
        } elseif (!$booking->driver_id && !$booking->vehicle_id) {
            $status = "Driver And Vehicle not assigned";
        // Only vehicle assigned
        } elseif (!$booking->driver_id && $booking->vehicle_id) {
            $status = "Driver is not assigned But Vehicle assigned";
        // Only driver assigned
        } elseif ($booking->driver_id && !$booking->vehicle_id) {
            $status = "Driver is assigned But Vehicle not assigned";
        // Vehicle sent for delivery
        } elseif ($booking->is_vehicle_sent == 1) {
            $status = "Driver is going for delivery";
        }

        return $status;
    }



    /**======================================   Support Function ====================================================================== */
    /**
     * | Generate QR - Code
     * | Function - 70 
     */
    public function generateQRCode()
    {
        $name = "Bikash Kumar";
        $email = "bikash@gmail.com";
        $contact = "8271522513";

        $data = [
            'name' => $name,
            'email' => $email,
            'contact' => $contact,
        ];

        $qrCode = QrCode::size(300)->generate(json_encode($data));

        return response($qrCode)->header('Content-Type', 'image/png');
    }

    /**
     * | Store user Details For Login in Users Table
     * | Function - 71
     */
    public function store($req)
    {
        try {
            // Validation---@source-App\Http\Requests\AuthUserRequest
            $user = new User;
            $this->saving($user, $req);                     // Storing data using Auth trait
            $user->password = Hash::make(($req['password']) ?? ("Basic" . '@' . "12345"));
            $user->save();
            return  $user->id;
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    /**
     * | Saving User Credentials 
     * | Function - 72 
     */
    // public function saving($user, $request)
    // {
    //     $user->name = $request['name'];
    //     $user->mobile = $request['mobile'];
    //     $user->email = $request['email'];
    //     if ($request['ulb']) {
    //         $user->ulb_id = $request['ulb'];
    //     }
    //     if ($request['userType']) {
    //         $user->user_type = $request['userType'];
    //     }

    //     $token = Str::random(80);                       //Generating Random Token for Initial
    //     $user->remember_token = $token;
    // }
    public function saving($user, $request)
    {
        $user->name = $request['name'];
        $user->mobile = $request['mobile'];
        $user->email = $request['email'];
        // $user->ulb_id = $request->ulb;
        if ($request['ulb']) {
            $user->ulb_id = $request['ulb'];
        }
        if ($request['userType']) {
            $user->user_type = $request['userType'];
        }

        $token = Str::random(80);                       //Generating Random Token for Initial
        $user->remember_token = $token;
    }

    /**
     * | Image Document Upload
     * | @param refImageName format Image Name like SAF-geotagging-id (Pass Your Ref Image Name Here)
     * | @param requested image (pass your request image here)
     * | @param relativePath Image Relative Path (pass your relative path of the image to be save here)
     * | @return imageName imagename to save (Final Image Name with time and extension)
     */
    public function upload($refImageName, $image, $relativePath)
    {
        $extention = $image->getClientOriginalExtension();
        $imageName = time() . '-' . $refImageName . '.' . $extention;
        $image->move($relativePath, $imageName);

        return $imageName;
    }

    /** ================================================XXXXXXXXXXXXXXXXXXXXXXXXXXX===================================================================== */



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
            $mWtankPayment = new WtTransaction();
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
            return responseMsgs(true, "WaterTanker Collection List Fetch Succefully !!!", $list, "055017", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    public function ReportDataWaterTanker(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fromDate' => 'required|date_format:Y-m-d',
            'toDate' => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'paymentMode'  => 'nullable',
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
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            $tran = new WtTransaction();
            $response = [];
            $fromDate = $request->fromDate ?: Carbon::now()->format('Y-m-d');
            $toDate = $request->toDate ?: Carbon::now()->format('Y-m-d');
            $perPage = $request->perPage ?: 10;

            if ($request->reportType == 'dailyCollection') {
                $response = $tran->dailyCollection($fromDate, $toDate, $request->wardNo, $request->paymentMode, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($response) {
                return responseMsgs(true, "WaterTanker Collection List Fetch Succefully !!!", $response, "055017", "1.0", responseTime(), "POST", $request->deviceId);
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
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            $booked = new WtBooking();
            $cancle = new WtCancellation();
            $response = [];
            $fromDate = $request->fromDate ?: Carbon::now()->format('Y-m-d');
            $toDate = $request->toDate ?: Carbon::now()->format('Y-m-d');
            $perPage = $request->perPage ?: 10;
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'bookedApplication') {
                $response = $booked->getBookedList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $request->waterCapacity, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }

            if ($request->reportType == 'applicationReport' && $request->applicationStatus ==  'assignedApplication') {
                $response = $booked->assignedList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $request->waterCapacity, $request->driverName, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus ==  'deliveredApplication') {
                $response = $booked->getDeliveredList($fromDate, $toDate, $request->wardNo, $request->applicationMode, $request->waterCapacity, $request->driverName, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }

            if ($request->reportType == 'applicationReport' && $request->applicationStatus ==  'cancleByAgency') {
                $response = $cancle->getCancelBookingListByAgency($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }

            if ($request->reportType == 'applicationReport' && $request->applicationStatus ==  'cancleByCitizen') {
                $response = $cancle->getCancelBookingListByCitizen($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus ==  'cancleByDriver') {
                $response = $booked->getCancelBookingListByDriver($fromDate, $toDate, $request->wardNo, $request->applicationMode, $perPage, $ulbId);
                $response['user_name'] = $user->name;
            }
            if ($request->reportType == 'applicationReport' && $request->applicationStatus == 'All') {
                $response = $booked->allBooking($request);
                //$response = response()->json($response);
                $response['user_name'] = $user->name;
            }
            if ($response) {
                return responseMsgs(true, "WaterTanker Collection List Fetch Succefully !!!", $response, "055017", "1.0", responseTime(), "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    public function pendingReportDataWaterTanker(Request $request)
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
            $booked = new WtBooking();
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
                return responseMsgs(true, "WaterTanker Pending List Fetch Succefully !!!", $response, "055017", "1.0", responseTime(), "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }


    /**
     * Apply free booking for water tanker
     * ---------------------------------------
     * This API:
     * - Validates booking exists by booking_no
     * - Sets payment_status to 2 (free)
     * - Updates is_tanker_free to true
     * - Returns "Booking not found!" if booking doesn't exist
     */
    public function freeBookingWaterTanker(Request $request)
    {
        try {
            $user = auth()->user();
            $ulbId = $user->ulb_id ?? null;

            $request->validate([
                'bookingId' => 'required'
            ]);

            // Find booking by booking_no (NOT id)
            $booking = WtBooking::where('booking_no', $request->bookingId)
                ->where('ulb_id', $ulbId)
                ->first();

            if (!$booking) {
                return responseMsgs(false, "Booking not found!", null, "110115", "1.0", "", 'POST', $request->deviceId ?? "");
            }

            // Update booking for Free Apply
            $booking->payment_status = 2;     // Set payment status to 2 (free)
            $booking->payment_amount = 00.00; // Set payment amount to 0 for free booking
            $booking->is_tanker_free = true;
            $booking->save();

            return responseMsgs(true, "Free application applied successfully!", $booking, "110152", "1.0", responseTime(), 'POST', $request->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $request->deviceId ?? "");
        }
    }

    public function addFreeBooking(StoreBookingRequest $req)
    {
        try {
            $user = Auth()->user();
            $mWtBooking = new WtBooking();
            $mCalculations = new Calculations();

            $generatedId = $mCalculations->generateId($this->_paramId, $req->ulbId);
            $req->merge(['bookingNo' => $generatedId]);

            $agencyId = $mCalculations->getAgency($req->ulbId);
            $req->merge(['agencyId' => $agencyId]);

            DB::beginTransaction();
            $res = $mWtBooking->freeBooking($req);
            DB::commit();

            return responseMsgs(true, "Booking Added Successfully !!!", $res, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [$e->getFile(), $e->getLine(), $e->getCode()], "110115", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    public function editFreeBooking(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|integer',
            'deliveryDate'  => 'nullable|date',
            'deliveryTime'  => 'nullable',
            'address'       => 'nullable|string',
            'mobile'        => 'nullable|digits:10',
            'email'         => 'nullable|email',
            'locationId'    => 'nullable|integer',
            'capacityId'    => 'nullable|integer',
            'wardId'        => 'nullable|integer',
            'reason'        => 'nullable|string',
            'applicantName' => 'nullable|string'
        ]);

        try {
            $user = Auth()->user();
            $ulbId = $user->ulb_id;

            // Fetch booking
            $booking = WtBooking::where('id', $req->applicationId)
                ->where('ulb_id', $ulbId)
                ->where('payment_status', 2) // Only free bookings
                ->first();

            if (!$booking) {
                return responseMsgs(false, "Free booking not found!", null, "110118", "1.0", "", 'POST', $req->deviceId ?? "");
            }

            DB::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | 1. INSERT OLD DATA INTO LOG TABLE
            |--------------------------------------------------------------------------
            */
            $logData = $booking->toArray(); // all original fields
            $logData['booking_id']  = $booking->id;
            $logData['action_type'] = 'EDIT';
            $logData['logged_by']   = $user->id;
            $logData['logged_at']   = now();

            unset($logData['id']);  // remove original primary key

            DB::table('log_wt_bookings')->insert($logData);

            /*
            |--------------------------------------------------------------------------
            | 2. UPDATE BOOKING WITH NEW DATA
            |--------------------------------------------------------------------------
            */
            $updateData = array_filter([
                'delivery_date'   => $req->deliveryDate,
                'delivery_time'   => $req->deliveryTime,
                'address'         => $req->address,
                'mobile'          => $req->mobile,
                'email'           => $req->email,
                'applicant_name'  => $req->applicantName,
                'location_id'     => $req->locationId,
                'capacity_id'     => $req->capacityId,
                'ward_id'         => $req->wardId,
                'reason'          => $req->reason,
            ], fn($v) => !is_null($v)); // allow 0 values

            $booking->update($updateData);

            DB::commit();

            return responseMsgs(true, "Booking Updated Successfully!", $booking, "110118", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110118", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * Upload document for water tanker booking
     * This API:
     * - Validates booking exists by booking_no
     * - Uploads document to DMS
     * - Updates is_document_uploaded status to true
     * - Returns DMS response
     */
    public function uploadBookingDocument(Request $request)
    {
        try {
            $request->validate([
                'bookingId' => 'required',
                'document' => 'nullable|file|max:2048'
            ]);

            $user = auth()->user();
            $ulbId = $user->ulb_id ?? null;

            // Find booking by booking_no
            $booking = WtBooking::where('booking_no', $request->bookingId)
                ->where('ulb_id', $ulbId)
                ->first();

            if (!$booking) {
                return responseMsgs(false, "Application Not Found!", null, "110116", "1.0", "", 'POST', $request->deviceId ?? "");
            }

            // Upload document to DMS
            $document = new DocUpload();
            $documentData = $document->severalDoc($request);
            
            if (!$documentData || $documentData->original['status'] == false) {
                return responseMsgs(false, "Document upload failed!", null, "110116", "1.0", "", 'POST', $request->deviceId ?? "");
            }

            $referenceNo = $documentData->original['data']['document']['data']['ReferenceNo'] ?? null;
            
            // Get full DMS URL using reference number
            $fullPath = $document->getDocPathByReferenceNo($referenceNo);

            // Store DMS full URL and update flag
            $booking->documents = $fullPath;
            $booking->is_document_uploaded = true;
            
            // Check if user id is 4245
            if ($user->id == 4245) {
                $booking->doc_uploaded_by_verifier = true;
                $booking->current_role = 35;
            } else {
                $booking->current_role = 79;                // Update current role to Verifier
            }
            
            $booking->save();

            return responseMsgs(true, "Document uploaded successfully!", ['documents' => $fullPath, 'referenceNo' => $referenceNo], "110153", "1.0", responseTime(), 'POST', $request->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110116", "1.0", "", 'POST', $request->deviceId ?? "");
        }
    }

    /**
     * Verify document by Verifier
     * This API:
     * - Validates application exists
     * - Checks if document is uploaded
     * - Checks if payment_status is 2 (free)
     * - Checks if is_tanker_free is true
     * - Stores admin comment
     */
    // public function verifyRejectDocByVerifier(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'applicationId' => 'required|integer',
    //             'verifyStatus'  => 'required|in:BTC,Verified',
    //             'comment'       => 'required|string'
    //         ]);

    //         $user = auth()->user();
    //         $ulbId = $user->ulb_id ?? null;

    //         $booking = WtBooking::where('id', $request->applicationId)
    //             ->where('ulb_id', $ulbId)
    //             ->first();

    //         if (!$booking) {
    //             return responseMsgs(false, "Application Not Found!", null, "110154", "1.0", "", 'POST', $request->deviceId ?? "");
    //         }

    //         if ($booking->payment_status != 2) {
    //             return responseMsgs(false, "Payment status must be 2 (free)!", null, "110154", "1.0", "", 'POST', $request->deviceId ?? "");
    //         }

    //         if (!$booking->is_tanker_free) {
    //             return responseMsgs(false, "This is not a free tanker booking!", null, "110154", "1.0", "", 'POST', $request->deviceId ?? "");
    //         }

    //         // -------------------------
    //         // VERIFIED FLOW
    //         // -------------------------
    //         if ($request->verifyStatus == 'Verified') {

    //             // ⭐ NEW CHANGE: Mark document as verified
    //             $booking->is_document_verified = true;

    //             $booking->current_role = 35;
    //             $booking->parked_status = false;

    //         } else {

    //             // -------------------------
    //             // BTC REJECTED FLOW
    //             // -------------------------

    //             // When rejected, document is NOT verified
    //             $booking->is_document_verified = false;

    //             // SET PARKED STATUS = TRUE
    //             $booking->parked_status = true;
    //         }

    //         $booking->verify_comment = $request->comment;
    //         $booking->verified_by = $user->id;
    //         $booking->verified_at = Carbon::now();
    //         $booking->save();

    //         $message = $request->verifyStatus == 'Verified'
    //             ? "Document verified successfully!"
    //             : "Document sent back to citizen!";

    //         return responseMsgs(true, $message, $booking, "110154", "1.0", responseTime(), 'POST', $request->deviceId ?? "");

    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "110154", "1.0", "", 'POST', $request->deviceId ?? "");
    //     }
    // }

    public function verifyRejectDocByVerifier(Request $request)
    {
        try {
            $request->validate([
                'applicationId' => 'required|integer',
                'verifyStatus'  => 'required|in:BTC,Verified',
                'comment'       => 'required|string'
            ]);

            $user  = auth()->user();
            $ulbId = $user->ulb_id ?? null;

            // Fetch booking
            $booking = WtBooking::where('id', $request->applicationId)
                ->where('ulb_id', $ulbId)
                ->where('payment_status', 2) // only free bookings
                ->first();

            if (!$booking) {
                return responseMsgs(false, "Application Not Found!", null, "110154", "1.0", "", 'POST', $request->deviceId ?? "");
            }

            if (!$booking->is_tanker_free) {
                return responseMsgs(false, "This is not a free tanker booking!", null, "110154", "1.0", "", 'POST', $request->deviceId ?? "");
            }

            DB::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | 1. INSERT OLD DATA INTO LOG TABLE
            |--------------------------------------------------------------------------
            */
            $logData = $booking->toArray();   // snapshot of old data
            $logData['booking_id']  = $booking->id;
            $logData['action_type'] = $request->verifyStatus === 'Verified' ? 'VERIFY' : 'REJECT';
            $logData['logged_by']   = $user->id;
            $logData['logged_at']   = now();
            $logData['reason']      = $request->comment;

            unset($logData['id']); // remove primary key

            DB::table('log_wt_bookings')->insert($logData);

            /*
            |--------------------------------------------------------------------------
            | 2. UPDATE CURRENT BOOKING
            |--------------------------------------------------------------------------
            */
            if ($request->verifyStatus === 'Verified') {

                $booking->is_document_verified = true;
                $booking->current_role = 35;
                $booking->parked_status = false;

            } else {

                $booking->is_document_verified = false;
                $booking->parked_status = true;
            }

            $booking->verify_comment = $request->comment;
            $booking->verified_by    = $user->id;
            $booking->verified_at    = now();

            $booking->save();

            DB::commit();

            $message = $request->verifyStatus === 'Verified'
                ? "Document verified successfully!"
                : "Document sent back to citizen!";

            return responseMsgs(true, $message, $booking, "110154", "1.0", responseTime(), 'POST', $request->deviceId ?? "");

        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "110154", "1.0", "", 'POST', $request->deviceId ?? "");
        }
    }


    // get only free apply booking list
    // public function freeSearchBooking(Request $req)
    // {
    //     try {
    //         $user = Auth()->user();
    //         $ulbId = $user->ulb_id ?? null;
    //         $key = $req->key;
    //         $userType = $req->userType;
    //         $fromDate = $uptoDate = null;

    //         if ($req->fromDate) {
    //             $fromDate = $req->fromDate;
    //         }
    //         if ($req->uptoDate) {
    //             $uptoDate = $req->uptoDate;
    //         }

    //         $list = WtBooking::select('id','applicant_name','booking_date','booking_no','delivery_date','delivery_time','payment_status','feedback','user_type','is_document_uploaded'
    //             )
    //             ->where('payment_status', 2)
    //             // ->where('is_document_uploaded', true)
    //             ->where('user_id', '!=', 4245);

    //         // user_type filter
    //         if ($userType) {
    //             $list = $list->where('user_type', $userType);  // Expects "Citizen" or "JSK"
    //         }

    //         // Search Filter
    //         if ($key) {
    //             $list = $list->where(function ($where) use ($key) {
    //                 $where->orWhere("booking_no", "ILIKE", "%$key%")
    //                     ->orWhere("applicant_name", "ILIKE", "%$key%")
    //                     ->orWhere("mobile", "ILIKE", "%$key%");
    //             });
    //         }

    //         // ULB Filter
    //         if ($ulbId) {
    //             $list = $list->where("ulb_id", $ulbId);
    //         }

    //         // Date Filter
    //         if ($fromDate && $uptoDate) {
    //             $list = $list->whereBetween("delivery_date", [$fromDate, $uptoDate]);
    //         }

    //         // Sorting
    //         $list = $list->orderBy("id", "DESC");

    //         // Pagination
    //         $perPage = $req->perPage ? $req->perPage : 10;
    //         $list = $list->paginate($perPage);

    //         // Formatting
    //         $f_list = [
    //             "currentPage" => $list->currentPage(),
    //             "lastPage" => $list->lastPage(),
    //             "total" => $list->total(),
    //             "data" => collect($list->items())->map(function ($val) {
    //                 $val->payment_details = json_decode($val->payment_details);
    //                 $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
    //                 return $val;
    //             }),
    //         ];

    //         return responseMsgs(true, "Booking list", $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
    //     }
    // }

    public function freeSearchBooking(Request $req)
    {
        try {
            $user  = Auth()->user();
            $ulbId = $user->ulb_id ?? null;

            $key      = $req->key;
            $userType = $req->userType;

            // NEW FILTERS
            $bookingType     = $req->bookingType;       // free | paid
            $applicationType = $req->applicationType;   // forwarded | backwarded

            $fromDate = $req->fromDate ?? null;
            $uptoDate = $req->uptoDate ?? null;

            /* ---------------- Base Query ---------------- */

            $list = WtBooking::select(
                    'id',
                    'applicant_name',
                    'booking_date',
                    'booking_no',
                    'delivery_date',
                    'delivery_time',
                    'payment_status',
                    'feedback',
                    'user_type',
                    'is_document_uploaded',
                    'payment_details'
                )
                ->where('payment_status', 2)   // FREE bookings
                ->where('user_id', '!=', 4245);

            /* ---------------- bookingType Filter ---------------- */
            if ($bookingType === 'free') {
                $list->where('is_tanker_free', true);
            } elseif ($bookingType === 'paid') {
                $list->where('is_tanker_free', false);
            }

            /* ---------------- applicationType Filter ---------------- */
            if ($applicationType === 'forwarded') {
                $list->where('current_role', 35);
            } elseif ($applicationType === 'backwarded') {
                $list->where('parked_status', true);
            }

            /* ---------------- userType Filter ---------------- */
            if ($userType) {
                $list->where('user_type', $userType); // JSK | Employee | Citizen
            }

            /* ---------------- Search Filter ---------------- */
            if ($key) {
                $list->where(function ($q) use ($key) {
                    $q->where('booking_no', 'ILIKE', "%$key%")
                    ->orWhere('applicant_name', 'ILIKE', "%$key%")
                    ->orWhere('mobile', 'ILIKE', "%$key%");
                });
            }

            /* ---------------- ULB Filter ---------------- */
            if ($ulbId) {
                $list->where('ulb_id', $ulbId);
            }

            /* ---------------- Date Filter ---------------- */
            if ($fromDate && $uptoDate) {
                $list->whereBetween('delivery_date', [$fromDate, $uptoDate]);
            }

            /* ---------------- Sorting ---------------- */
            $list->orderBy('id', 'DESC');

            /* ---------------- Pagination ---------------- */
            $perPage = $req->perPage ?? 10;
            $list = $list->paginate($perPage);

            /* ---------------- Response Format ---------------- */
            $f_list = [
                'currentPage' => $list->currentPage(),
                'lastPage'    => $list->lastPage(),
                'total'       => $list->total(),
                'data'        => collect($list->items())->map(function ($val) {
                    $val->payment_details = json_decode($val->payment_details);
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    return $val;
                }),
            ];

            return responseMsgs(
                true,
                'Booking list',
                $f_list,
                '110115',
                '1.0',
                responseTime(),
                'POST',
                $req->deviceId ?? ''
            );

        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), '', 'POST', $req->deviceId ?? '');
        }
    }

    // get only verifier apply free booking list
    public function freeSearchBookingVerifier(Request $req)
    {
        try {
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;
            $key = $req->key;

            $list = WtBooking::select('id','applicant_name', 'booking_date', 'booking_no', 'delivery_date', 'delivery_time', 'payment_status', 'feedback', 'user_type', 'is_document_uploaded', 'doc_uploaded_by_verifier')
                ->where('payment_status', 2)
                ->where('is_document_uploaded', true)
                ->where('doc_uploaded_by_verifier', true)
                ->where('user_id', '=', 4245);

            if ($key) {
                $list = $list->where(function ($where) use ($key) {
                    $where->orWhere("booking_no", "ILIKE", "%$key%")
                        ->orWhere("applicant_name", "ILIKE", "%$key%")
                        ->orWhere("mobile", "ILIKE", "%$key%");
                });
            }

            if ($ulbId) {
                $list = $list->where("ulb_id", $ulbId);
            }

            if ($req->fromDate && $req->uptoDate) {
                $list = $list->whereBetween("booking_date", [$req->fromDate, $req->uptoDate]);
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
                    return $val;
                }),
            ];

            return responseMsgs(true, "Booking list",  $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
        }
    }

    // Back to BTC Inbox List is parked_status is true
    public function btcInbox(Request $req)
    {
        try {
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? null;

            $key = $req->key;
            $bookingType = $req->bookingType; 

            $fromDate = $uptoDate = null;

            if ($req->fromDate) {
                $fromDate = $req->fromDate;
            }
            if ($req->uptoDate) {
                $uptoDate = $req->uptoDate;
            }

            // Only parked applications
            $list = WtBooking::select('id','applicant_name','booking_date','booking_no','delivery_date','delivery_time','payment_status','feedback','user_type','is_document_uploaded','parked_status'
                )
                ->where('parked_status', 1);

            //  NEW — Booking Type Filter
            if ($bookingType) {
                if ($bookingType === "paid") {
                    $list = $list->where("payment_status", 1);
                } elseif ($bookingType === "free") {
                    $list = $list->where("payment_status", 2);
                } elseif ($bookingType === "unpaid") {
                    $list = $list->where("payment_status", 0);
                }
            }

            //  Search filter
            if ($key) {
                $list = $list->where(function ($where) use ($key) {
                    $where->orWhere("booking_no", "ILIKE", "%$key%")
                        ->orWhere("applicant_name", "ILIKE", "%$key%")
                        ->orWhere("mobile", "ILIKE", "%$key%");
                });
            }

            //  ULB filter
            if ($ulbId) {
                $list = $list->where("ulb_id", $ulbId);
            }

            //  Date filter
            if ($fromDate && $uptoDate) {
                $list = $list->whereBetween("booking_date", [$fromDate, $uptoDate]);
            }

            // Sorting
            $list = $list->orderBy("id", "DESC");

            // Pagination
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);

            // Formatting
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) {
                    $val->payment_details = json_decode($val->payment_details);
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    return $val;
                }),
            ];

            return responseMsgs(true, "BTC Inbox List", $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "POST", $req->deviceId ?? "");
        }
    }

    // Approved Booking List where current_role = 35
    public function outboxList(Request $req)
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

            // Fetch only applications where current_role = 35
            $list = WtBooking::select('id','applicant_name','booking_date','booking_no','delivery_date','delivery_time','payment_status','feedback','user_type','is_document_uploaded','current_role'
                )
                ->where('current_role', 35);   

            // Search filter
            if ($key) {
                $list = $list->where(function ($where) use ($key) {
                    $where->orWhere("booking_no", "ILIKE", "%$key%")
                        ->orWhere("applicant_name", "ILIKE", "%$key%")
                        ->orWhere("mobile", "ILIKE", "%$key%");
                });
            }

            // ULB filter
            if ($ulbId) {
                $list = $list->where("ulb_id", $ulbId);
            }

            // Date filter
            if ($fromDate && $uptoDate) {
                $list = $list->whereBetween("delivery_date", [$fromDate, $uptoDate]);
            }

            // Sort latest first
            $list = $list->orderBy("id", "DESC");

            // Pagination
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);

            // Formatting output
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage" => $list->lastPage(),
                "total" => $list->total(),
                "data" => collect($list->items())->map(function ($val) {
                    $val->payment_details = json_decode($val->payment_details);
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    return $val;
                }),
            ];

            return responseMsgs(true, "Approved Booking List", $f_list, "110115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110115", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    // Forward Rejected Application
    public function forwardRejectedApplication(Request $req)
    {
        try {
            $req->validate([
                'applicationId' => 'required|integer'
            ]);

            $user = auth()->user();
            $ulbId = $user->ulb_id ?? null;

            // Fetch the booking
            $booking = WtBooking::where('id', $req->applicationId)
                ->where('ulb_id', $ulbId)
                ->first();

            if (!$booking) {
                return responseMsgs(false, "Application not found!", null, "110160", "1.0", "", 'POST', $req->deviceId ?? "");
            }

            // Check if parked
            if (!$booking->parked_status) {
                return responseMsgs(false, "Application is not in parked state!", null, "110160", "1.0", "", 'POST', $req->deviceId ?? "");
            }

            // Update parked status → FALSE
            $booking->parked_status = false;
            $booking->save();

            return responseMsgs(true, "Application forwarded successfully!", $booking, "110160", "1.0", responseTime(), 'POST', $req->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "110160", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    // Approved Application List with Vehicle & Driver assigned
    public function getApprovedApplicationList(Request $req)
    {
        try {
            $user  = auth()->user();
            $ulbId = $user->ulb_id ?? null;
            $mWtBooking = new WtBooking();

            if (!$ulbId) {
                return responseMsgs(false, "ULB not found!", [], "110170", "1.0", "", "GET", $req->deviceId ?? "");
            }

            // Base query
            $list = $mWtBooking->getApprovedApp($ulbId);

            // Sort latest first
            $list = $list->orderBy('id', 'DESC');

            // Pagination
            $perPage = $req->perPage ? $req->perPage : 10;
            $list = $list->paginate($perPage);

            // Format response (same structure as outboxList)
            $f_list = [
                "currentPage" => $list->currentPage(),
                "lastPage"    => $list->lastPage(),
                "total"       => $list->total(),
                "data"        => collect($list->items())->map(function ($val) {
                    $val->booking_date = Carbon::parse($val->booking_date)->format('d-m-Y');
                    if ($val->delivery_date) {
                        $val->delivery_date = Carbon::parse($val->delivery_date)->format('d-m-Y');
                    }
                    return $val;
                }),
            ];

            return responseMsgs(true, "Approved application list fetched successfully!", $f_list, "110170", "1.0", responseTime(), "GET", $req->deviceId ?? "");

        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "110170", "1.0", "", "GET", $req->deviceId ?? "");
        }
    }


}