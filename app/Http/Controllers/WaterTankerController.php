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
use PhpParser\Node\Stmt\Return_;

class WaterTankerController extends Controller
{
    protected $_base_url;

    protected $_ulbs;

    public function __construct()
    {
        $this->_base_url = Config::get('constants.BASE_URL');
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
            'ulbId' => 'required|digits_between:1,200|integer',
            'agencyName' => 'required|string|max:255',
            'ownerName' => 'required|string|max:255',
            'agencyAddress' => 'required|string',
            // 'agencyWardId' => 'required|integer',
            'agencyMobile' => 'required|digits:10',
            'agencyEmail' => 'required|string|email',
            'dispatchCapacity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtAgency = new WtAgency();
            DB::beginTransaction();
            $res = $mWtAgency->storeAgency($req);                                       // Store Agency Request
            DB::commit();
            return responseMsgs(true, "Agency Added Successfully !!!", '', "050101", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            // return $e->getMessage();
            return responseMsgs(false, $e->getMessage(), "", "050101", "1.0", "", 'POST', $req->deviceId ?? "");
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
            // Variable initialization
            // return $req;
            $mWtAgency = new WtAgency();
            $list = $mWtAgency->getAllAgency();
            // $bearerToken = $req->token;
            // $bearerToken = (collect(($req->headers->all())['authorization'] ?? "")->first());
            // $contentType = (collect(($req->headers->all())['content-type'] ?? "")->first());
            // $bearerToken = "34|gDhTZJ1PYRx1B8Xl3mMSTTZJ97ztz8UZV2NpzqGp";

            $ulb = $this->_ulbs;
            $ulbId = "";
            $wards = collect([]);
            // $f_list = $list->map(function ($val) use ($ulb, $bearerToken, $contentType, $ulbId, $wards) {
            $f_list = $list->map(function ($val) use ($ulb) {
                $val["ulb_name"] = (collect($ulb)->where("id", $val["ulb_id"]))->value("ulb_name");
                $val['date'] = Carbon::createFromFormat('Y-m-d', $val['date'])->format('d/m/Y');
                // if ($ulbId != $val["ulb_id"]) {
                //     $wardRespons = Http::withHeaders(
                //         [
                //             "Authorization" => "Bearer $bearerToken",
                //             "contentType" => "$contentType",
                //         ]
                //     )
                //         ->post(
                //             $this->_base_url . "api/workflow/getWardByUlb",
                //             ["ulbId" => $val["ulb_id"]]
                //         );
                //     $response = collect(json_decode($wardRespons->getBody()->getContents()));
                //     $wards = collect($response["data"] ?? []);
                // }
                // $val["ward_no"] = $wards->where("id", $val["agency_ward_id"])->value("ward_name");
                $val["ward_no"] = 2;
                return $val;
            });
            return responseMsgs(true, "Agency List !!!",  $f_list, "050102", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050102", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'capacity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtCapacity = new WtCapacity();
            DB::beginTransaction();
            $res = $mWtCapacity->storeCapacity($req);                                       // Store Capacity Request
            DB::commit();
            return responseMsgs(true, "Capacity Added Successfully !!!", '', "050103", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            // return $e->getMessage();
            return responseMsgs(false, $e->getMessage(), "", "050103", "1.0", "", 'POST', $req->deviceId ?? "");
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
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Capacity List !!!", $f_list, "050104", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050104", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'capacityId' => 'required|integer|digits_between:1,99',
            'ulbId' => 'required|integer|digits_between:1,200',
            'rate' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtUlbCapacityRate = new WtUlbCapacityRate();
            DB::beginTransaction();
            $res = $mWtUlbCapacityRate->storeCapacityRate($req);                                       // Store Capacity Rate Request
            DB::commit();
            return responseMsgs(true, "Capacity Rate Added Successfully !!!",  '', "050105", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050105", "1.0", "", 'POST', $req->deviceId ?? "");
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
            $list = $mWtUlbCapacityRate->getUlbCapacityRateList();
            $f_list = $list->map(function ($val) {
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Capacity Rate List !!!", $f_list, "0501056", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050106", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            // 'wardId' => 'required|integer|digits_between:1,200',
            'waterCapacity' => 'required|numeric',
            'address' => 'required|string',

        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtHydrationCenter = new WtHydrationCenter();
            DB::beginTransaction();
            $res = $mWtHydrationCenter->storeHydrationCenter($req);                                       // Store Capacity Rate Request
            DB::commit();
            return responseMsgs(true, "Hydration Center Added Successfully !!!",  '', "050107", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050107", "1.0", "", 'POST', $req->deviceId ?? "");
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
            // Variable initialization
            $mWtHydrationCenter = new WtHydrationCenter();
            $list = $mWtHydrationCenter->getHydrationCenterList($req);
            $ulb = $this->_ulbs;
            $ulbId = "";
            $bearerToken = (collect(($req->headers->all())['authorization'] ?? "")->first());
            $contentType = (collect(($req->headers->all())['content-type'] ?? "")->first());
            $f_list = $list->map(function ($val) use ($ulb, $ulbId, $bearerToken, $contentType) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::createFromFormat('Y-m-d H:i:s', $val->created_at)->format('d/m/Y');
                // if ($ulbId != $val["ulb_id"]) {
                //     $wardRespons = Http::withHeaders(
                //         [
                //             "Authorization" => "Bearer $bearerToken",
                //             "contentType" => "$contentType",
                //         ]
                //     )
                //         ->post(
                //             $this->_base_url . "api/workflow/getWardByUlb",
                //             ["ulbId" => $val["ulb_id"]]
                //         );
                //     $response = collect(json_decode($wardRespons->getBody()->getContents()));
                //     $wards = collect($response["data"] ?? []);
                // }
                // $val["ward_no"] = $wards->where("id", $val["agency_ward_id"])->value("ward_name");
                return $val;
            });
            return responseMsgs(true, "Hydration Center List !!!", $f_list, "050108", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050108", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            'agencyId' => 'required|integer',
            'driverName' => 'required|string|max:200',
            'driverAadharNo' => 'required|string|max:16',
            'driverMobile' => 'required|digits:10',
            'driverAddress' => 'required|string',
            'driverFather' => 'required|string|max:200',
            'driverDob' => 'required|date',
            'driverLicenseNo' => 'required|string|max:50',

        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtDriver = new WtDriver();
            DB::beginTransaction();
            $res = $mWtDriver->storeDriverInfo($req);                                       // Store Driver Information in Model 
            DB::commit();
            return responseMsgs(true, "Driver Added Successfully !!!",  '', "050109", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050109", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Driver List
     * | Function - 10
     * | API - 10
     */
    public function listDriver(Request $req)
    {
        try {
            // Variable initialization
            $mWtDriver = new WtDriver();
            $list = $mWtDriver->getDriverList($req);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->driver_dob = Carbon::createFromFormat('Y-m-d', $val->driver_dob)->format('d/m/Y');
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Hydration Center List !!!", $f_list, "050110", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050110", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            'agencyId' => 'nullable|integer',
            'vehicleName' => 'required|string|max:200',
            'vehicleNo' => 'required|string|max:16',
            'capacityId' => 'required|integer|digits_between:1,150',
            'resourceType' => 'required|string|max:200',
            'isUlbResource' => 'required|boolean',

        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtResource = new WtResource();
            DB::beginTransaction();
            $res = $mWtResource->storeResourceInfo($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Resoure Added Successfully !!!",  '', "050111", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050111", "1.0", "", 'POST', $req->deviceId ?? "");
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
            // Variable initialization
            $mWtResource = new WtResource();
            $list = $mWtResource->getResourceList($req);
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d/m/Y');
                return $val;
            });
            return responseMsgs(true, "Resource List !!!", $f_list, "050112", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050112", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtHydrationDispatchLog = new WtHydrationDispatchLog();
            DB::beginTransaction();
            $res = $mWtHydrationDispatchLog->storeHydrationDispatchLog($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Dispatch Log Added Successfully !!!",  '', "050113", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050113", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return responseMsgs(true, "Resource List !!!", $f_list, "050114", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050114", "1.0", "", 'POST', $req->deviceId ?? "");
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
            // Variable initialization
            $mWtBooking = new WtBooking();
            $mCalculations = new Calculations();
            $bookingStatus = $mCalculations->checkBookingStatus($req->deliveryDate, $req->agencyId, $req->capacityId);       // Check booking is available or not on selected agency on delivery date
            if ($bookingStatus == false)
                throw new Exception('Your Delivery Date Slot are Not Available. Please Try To Other Date or Agency !!!');
            DB::beginTransaction();
            $res = $mWtBooking->storeBooking($req);                                                                     // Store Booking Informations
            DB::commit();
            return responseMsgs(true, "Booking Added Successfully !!!",  '', "050115", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050115", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Get Booking list
     * | Function - 16
     * | API - 16
     */
    public function listAgencyBooking(Request $req)
    {
        try {
            // Variable initialization
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingList($req)->where('agency_id', '!=', NULL)->get();

            $bearerToken = $req->token;
            $contentType = (collect(($req->headers->all())['content-type'] ?? "")->first());

            $ulb = $this->_ulbs;
            $ulbId = "";
            $f_list = $list->map(function ($val) use ($ulb, $bearerToken, $contentType, $ulbId) {
                // $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d/m/Y');
                $val->delivery_date = Carbon::createFromFormat('Y-m-d', $val->delivery_date)->format('d/m/Y');
                // if ($ulbId != $val->ulb_id) {
                //   $wardRespons = Http::withHeaders(
                //         [
                //             "Authorization" => "Bearer $bearerToken",
                //         ]
                //     )
                //         ->post(
                //             $this->_base_url . "api/workflow/getWardByUlb",
                //             ["ulbId" => $val->ulb_id]
                //         );
                //     $response = collect(json_decode($wardRespons->getBody()->getContents()));
                //     $wards = collect($response["data"] ?? []);
                // }
                // $val->ward_no = $wards->where("id", $val->ward_id)->value("ward_name");
                $val->ward_no = 2;
                return $val;
            });
            return responseMsgs(true, "Agency Booking List !!!", $f_list, "050116", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050116", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            'driverId' => 'required|integer',
            'vehicleId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtResource = WtResource::find($req->vehicleId);
            $isUlbVehicle = ['isUlbVehicle' => $mWtResource->is_ulb_resource];
            $req->request->add($isUlbVehicle);
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            DB::beginTransaction();
            $res = $mWtDriverVehicleMap->storeMappingDriverVehicle($req);                                       // Store Resource Information in Model 
            DB::commit();
            return responseMsgs(true, "Mapping Added Successfully !!!",  '', "050117", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "050117", "1.0", "", 'POST', $req->deviceId ?? "");
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
            // Variable initialization
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            $list = $mWtDriverVehicleMap->getMapDriverVehicle();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->assign_date = Carbon::createFromFormat('Y-m-d', $val->date)->format('d/m/Y');
                $val->is_ulb_vehicle = $val->is_ulb_vehicle == 1 ? 'Yes' : 'No';
                return $val;
            });
            return responseMsgs(true, "Mapping List !!!", $f_list, "050118", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050118", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Cancel Booking
     * | Function - 19
     * | API - 19
     */
    public function cancelBooking(Request $req)
    {
        // return $req;
        $cancelById = $req->auth['id'];
        $cancelledBy = $req->auth['user_type'];
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'remarks' => 'required|string',
            'cancelDetails' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        $req->request->add(['cancelledById' => $cancelById, 'cancelledBy' => $cancelledBy]);
        try {
            // Variable initialization
            $mWtBooking = WtBooking::find($req->applicationId);
            if (Carbon::now()->format('Y-m-d') >= $mWtBooking->delivery_date)
                throw new Exception('Today Booking is Not Cancelled !!!');
            $cancelledBooking = $mWtBooking->replicate();                                   // Replicate Data fromm Booking to Cancel table
            $cancelledBooking->cancel_date = Carbon::now()->format('Y-m-d');
            $cancelledBooking->remarks = $req->remarks;
            $cancelledBooking->cancel_details = $req->cancelDetails;
            $cancelledBooking->cancelled_by = $req->cancelledBy;
            $cancelledBooking->cancelled_by_id = $req->cancelledById;
            $cancelledBooking->id =  $mWtBooking->id;
            $cancelledBooking->setTable('wt_cancellations');
            $cancelledBooking->save();                                                       // Save in Cancel Booking Table
            $mWtBooking->delete();                                                           // Delete Data From Booking Table
            return responseMsgs(true, "Booking Cancelled Successfully !!!",  '', "050119", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050119", "1.0", "", 'POST', $req->deviceId ?? "");
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
            $list = $mWtCancellation->getCancelBookingList($req)->where('refund_status', '0');    // 0 - Booking Cancel Success

            $bearerToken = (collect(($req->headers->all())['authorization'] ?? "")->first());
            $contentType = (collect(($req->headers->all())['content-type'] ?? "")->first());

            $ulb = $this->_ulbs;
            $ulbId = "";
            $f_list = $list->map(function ($val) use ($ulb, $bearerToken, $contentType, $ulbId) {
                // $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                // if ($ulbId != $val->ulb_id) {
                //     $wardRespons = Http::withHeaders(
                //         [
                //             "Authorization" => "Bearer $bearerToken",
                //             "contentType" => "$contentType",
                //         ]
                //     )
                //         ->post(
                //             $this->_base_url . "api/workflow/getWardByUlb",
                //             ["ulbId" => $val->ulb_id]
                //         );
                //     $response = collect(json_decode($wardRespons->getBody()->getContents()));
                //     $wards = collect($response["data"] ?? []);
                // }
                // $val->ward_no = $wards->where("id", $val->ward_id)->value("ward_name");
                $val->ward_no = 2;
                return $val;
            })->values();
            return responseMsgs(true, "Booking List !!!", $f_list, "050120", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050120", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtCancellation = WtCancellation::find($req->applicationId);
            $mWtCancellation->refund_status = '1';
            $mWtCancellation->refund_remarks = $req->refundRemarks;
            $mWtCancellation->refund_details = $req->refundDetails;
            $mWtCancellation->refund_date = Carbon::now()->format('Y-m-d');
            $mWtCancellation->save();                                                       // Update Cancellation Table for Refund
            return responseMsgs(true, "Refund Successfully !!!",  '', "050121", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050121", "1.0", "", 'POST', $req->deviceId ?? "");
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
            $list = $mWtCancellation->getCancelBookingList($req)->where('refund_status', '1');    // 1 - Booking Refund Success

            $bearerToken = (collect(($req->headers->all())['authorization'] ?? "")->first());
            $contentType = (collect(($req->headers->all())['content-type'] ?? "")->first());

            $ulb = $this->_ulbs;
            $ulbId = "";
            $f_list = $list->map(function ($val) use ($ulb, $bearerToken, $contentType, $ulbId) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                if ($ulbId != $val->ulb_id) {
                    $wardRespons = Http::withHeaders(
                        [
                            "Authorization" => "Bearer $bearerToken",
                            "contentType" => "$contentType",
                        ]
                    )
                        ->post(
                            $this->_base_url . "api/workflow/getWardByUlb",
                            ["ulbId" => $val->ulb_id]
                        );
                    $response = collect(json_decode($wardRespons->getBody()->getContents()));
                    $wards = collect($response["data"] ?? []);
                }
                $val->ward_no = $wards->where("id", $val->ward_id)->value("ward_name");
                return $val;
            });
            return responseMsgs(true, "Booking List !!!", $f_list, "050122", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050122", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Get Booking list
     * | Function - 23
     * | API - 23
     */
    public function listUlbBooking(Request $req)
    {
        try {
            // Variable initialization
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->getBookingList($req)->where('agency_id', '=', NULL)->get();

            $bearerToken = (collect(($req->headers->all())['authorization'] ?? "")->first());
            $contentType = (collect(($req->headers->all())['content-type'] ?? "")->first());

            $ulb = $this->_ulbs;
            $ulbId = "";
            $f_list = $list->map(function ($val) use ($ulb, $bearerToken, $contentType, $ulbId) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                // if ($ulbId != $val->ulb_id) {
                //     $wardRespons = Http::withHeaders(
                //         [
                //             "Authorization" => "Bearer $bearerToken",
                //             "contentType" => "$contentType",
                //         ]
                //     )
                //         ->post(
                //             $this->_base_url . "api/workflow/getWardByUlb",
                //             ["ulbId" => $val->ulb_id]
                //         );
                //     $response = collect(json_decode($wardRespons->getBody()->getContents()));
                //     $wards = collect($response["data"] ?? []);
                // }
                // $val->ward_no = $wards->where("id", $val->ward_id)->value("ward_name");
                $val->ward_no = 2;
                return $val;
            });
            return responseMsgs(true, "ULB Booking List !!!", $f_list, "050123", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050123", "1.0", "", 'POST', $req->deviceId ?? "");
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
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
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
            $mWtAgency->save();
            return responseMsgs(true, "Agency Updated Successfully !!!", '', "050123", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050124", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            // 'wardId' => 'required|integer|digits_between:1,200',
            'waterCapacity' => 'required|numeric',
            'address' => 'required|string',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            $mWtHydrationCenter = WtHydrationCenter::find($req->hydrationCenterId);
            if (!$mWtHydrationCenter)
                throw new Exception("No Data Found !!!");
            $mWtHydrationCenter->name = $req->name;
            $mWtHydrationCenter->ulb_id = $req->ulbId;
            $mWtHydrationCenter->water_capacity = $req->waterCapacity;
            $mWtHydrationCenter->address = $req->address;
            $mWtHydrationCenter->save();
            return responseMsgs(true, "Hydration Center Details Updated Successfully !!!", '', "050125", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050125", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            'agencyId' => 'nullable|integer',
            'vehicleName' => 'required|string|max:200',
            'vehicleNo' => 'required|string|max:16',
            'capacityId' => 'required|integer|digits_between:1,150',
            'resourceType' => 'required|string|max:200',
            'isUlbResource' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
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
            $mWtResource->save();
            return responseMsgs(true, "Resource Details Updated Successfully !!!", '', "050126", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050126", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'capacity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            $mWtCapacity = WtCapacity::find($req->capacityId);
            if (!$mWtCapacity)
                throw new Exception("No Data Found !!!");
            $mWtCapacity->capacity = $req->capacity;
            $mWtCapacity->save();
            return responseMsgs(true, "Capacity Details Updated Successfully !!!", '', "050127", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050127", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer',
            'capacityId' => 'required|integer',
            'capacityRateId' => 'required|integer',
            'rate' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            $mWtUlbCapacityRate = WtUlbCapacityRate::find($req->capacityRateId);
            if (!$mWtUlbCapacityRate)
                throw new Exception("No Data Found !!!");
            $mWtUlbCapacityRate->ulb_id = $req->ulbId;
            $mWtUlbCapacityRate->capacity_id = $req->capacityId;
            $mWtUlbCapacityRate->rate = $req->rate;
            $mWtUlbCapacityRate->save();
            return responseMsgs(true, "Capacity Rate Updated Successfully !!!", '', "050128", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050128", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Update Details for Driver
     * | Function - 29
     * | API - 29
     */
    public function editDriver(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'required|integer|digits_between:1,200',
            'agencyId' => 'required|integer',
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            $mWtDriver = WtDriver::find($req->driverId);
            if (!$mWtDriver)
                throw new Exception("No Data Found !!!");
            $mWtDriver->ulb_id = $req->ulbId;
            $mWtDriver->agency_id = $req->agencyId;
            $mWtDriver->driver_name = $req->driverName;
            $mWtDriver->driver_aadhar_no = $req->driverAadharNo;
            $mWtDriver->driver_mobile = $req->driverMobile;
            $mWtDriver->driver_address = $req->driverAddress;
            $mWtDriver->driver_father = $req->driverFather;
            $mWtDriver->driver_dob = $req->driverDob;
            $mWtDriver->driver_license_no = $req->driverLicenseNo;
            $mWtDriver->save();
            return responseMsgs(true, "Driver Details Updated Successfully !!!", '', "050129", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050129", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtAgency = new WtAgency();
            $list = $mWtAgency->getAgencyById($req->agencyId);
            return responseMsgs(true, "Data Fetched !!!",  $list, "050129", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050129", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtHydrationCenter = new WtHydrationCenter();
            $list = $mWtHydrationCenter->getHydrationCenterDetailsByID($req->hydrationCenterId);
            return responseMsgs(true, "Data Fetched !!!", $list, "050130", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050130", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtResource = new WtResource();
            $list = $mWtResource->getResourceById($req->resourceId);
            return responseMsgs(true, "Data Fetched !!!", $list, "050112", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050112", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtCapacity = new WtCapacity();
            $list = $mWtCapacity->getCapacityById($req->capacityId);
            return responseMsgs(true, "Data Fetched !!!", $list, "050133", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050133", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtUlbCapacityRate = new WtUlbCapacityRate();
            $list = $mWtUlbCapacityRate->getCapacityRateDetailsById($req->capacityRateId);
            return responseMsgs(true, "Data Fetched !!!", $list, "050134", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050134", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtDriver = new WtDriver();
            $list = $mWtDriver->getDriverDetailsById($req->driverId);
            return responseMsgs(true, "Data Fetched !!!", $list, "050135", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050135", "1.0", "", 'POST', $req->deviceId ?? "");
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
            $list = $mWtDriverVehicleMap->getMapDriverVehicle();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->driver_vehicle = $val->vehicle_no . "( " . $val->driver_name . " )";
                return $val;
            });
            return responseMsgs(true, "Driver Vehicle Mapping List !!!", $f_list, "050136", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050136", "1.0", "", 'POST', $req->deviceId ?? "");
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
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWtDriverVehicleMap = new WtDriverVehicleMap();
            $list = $mWtDriverVehicleMap->getDriverVehicleMapById($req->mapId);
            return responseMsgs(true, "Data Fetched !!!", $list, "050137", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050137", "1.0", "", 'POST', $req->deviceId ?? "");
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
            'ulbId' => 'required|integer|digits_between:1,200',
            'driverId' => 'required|integer',
            'vehicleId' => 'required|integer',
            'mapId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            $mWtDriverVehicleMap = WtDriverVehicleMap::find($req->mapId);
            if (!$mWtDriverVehicleMap)
                throw new Exception("No Data Found !!!");
            $mWtDriverVehicleMap->ulb_id = $req->ulbId;
            $mWtDriverVehicleMap->driver_id = $req->driverId;
            $mWtDriverVehicleMap->vehicle_id = $req->vehicleId;
            $mWtDriverVehicleMap->save();
            return responseMsgs(true, "Map Driver & Vehicle Details Updated Successfully !!!", '', "050138", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050138", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }

    /**
     * | Booking Assign for Delevery (Tanker , Driver & Hydration Center )
     * | Function - 39
     * | API - 39
     */
    public function bookingAssignment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'vdmId' => 'required|integer',
            'hydrationCenterId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            $mWtBooking = WtBooking::find($req->applicationId);
            if (!$mWtBooking)
                throw new Exception("No Data Found !!!");
            $mWtBooking->vdm_id = $req->vdmId;
            $mWtBooking->hydration_center_id = $req->hydrationCenterId;
            $mWtBooking->assign_date = Carbon::now()->format('Y-m-d');
            $mWtBooking->save();
            return responseMsgs(true, "Booking Assignent Successfully !!!", '', "050139", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050139", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }


    /**
     * | Agency Booking Assign for Delevery (Tanker , Driver & Hydration Center )
     * | Function - 40
     * | API - 40
     */
    public function listAssignAgency(Request $req)
    {
        try {
            $mWtBooking = new WtBooking();
            $list = $mWtBooking->assignList()->get();
            // $list=$list->where('agency_id','!=',NULL)->get();
            // $list=$list->where('agency_id',$agencyId)->get();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d/m/Y');
                $val->delivery_date = Carbon::createFromFormat('Y-m-d', $val->delivery_date)->format('d/m/Y');
                $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
                return $val;
            });
            return responseMsgs(true, "Assign List !!!", $f_list, "050140", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050140", "1.0", "", 'POST', $req->deviceId ?? "");
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
            // $list=$list->where('hydration_center_id',$hydrationCenterId)->get();
            $ulb = $this->_ulbs;
            $f_list = $list->map(function ($val) use ($ulb) {
                $val->ulb_name = (collect($ulb)->where("id", $val->ulb_id))->value("ulb_name");
                $val->booking_date = Carbon::createFromFormat('Y-m-d', $val->booking_date)->format('d/m/Y');
                $val->delivery_date = Carbon::createFromFormat('Y-m-d', $val->delivery_date)->format('d/m/Y');
                $val->driver_vehicle = $val->vehicle_no . " ( " . $val->driver_name . " )";
                return $val;
            });
            return responseMsgs(true, "Assign List !!!", $f_list, "050141", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050141", "1.0", "", 'POST', $req->deviceId ?? "");
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

    /**
     * | Get Master Data of Water Tanker
     * | Function - unknown
     * | API - unknown
     */
    public function masterData(Request $req)
    {
        $redis = Redis::connection();
        try {
            // Variable initialization
            $data1 = json_decode(Redis::get('wt_masters'));                     // Get Value from Redis Cache Memory
            if (1) {                                                      // If Cache Memory is not available
                $data1 = array();

                $magency = new WtAgency();
                $adencyList = $magency->getAllAgencyForMasterData();
                $data1['agency'] = $adencyList;

                $mWtCapacity = new WtCapacity();
                $listCapacity = $mWtCapacity->getCapacityList();
                $data1['capacity'] = $listCapacity;

                $mWtDriver = new WtDriver();
                $listDriver = $mWtDriver->getDriverListForMasterData();
                $data1['driver'] = $listDriver;

                $mWtHydrationCenter = new WtHydrationCenter();
                $hydrationCenter = $mWtHydrationCenter->getHydrationCeenterForMasterData();
                $data1['hydrationCenter'] = $hydrationCenter;

                $mWtUlbCapacityRate = new WtUlbCapacityRate();
                $capacityRate = $mWtUlbCapacityRate->getCapacityRateForMasterData();
                $data1['capacityRate'] = $capacityRate;


                $mWtResource = new WtResource();
                $capacityRate = $mWtResource->getVehicleForMasterData();
                $data1['vehicle'] = $capacityRate;

                $redis->set('wt_masters', json_encode($data1));                 // Set Key on Water Tanker masters
            }
            return responseMsgs(true, "Data Fetched !!!", $data1, "050112", "1.0", responseTime(), 'POST', $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "050112", "1.0", "", 'POST', $req->deviceId ?? "");
        }
    }
}
