<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class WtBooking extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'citizen_id' => $req->citizenId,
            'agency_id' => $req->agencyId,
            'booking_date' => Carbon::now()->format('Y-m-d'),
            'delivery_date' => $req->deliveryDate,
            'delivery_time' => $req->deliveryTime,
            'mobile' => $req->mobile,
            'email' => $req->email,
            'address' => $req->address,
            'ward_id' => $req->wardId,
            'capacity_id' => $req->capacityId,
            'quantity' => 1,
            'hydration_center_id' => $req->hydrationCenter,
            'applicant_name' => $req->applicantName,
            'booking_no' => $req->bookingNo,
            'payment_amount' => $req->paymentAmount,
            'location_id' => $req->locationId,
            'booking_latitude' => $req->latitude ?? null,
            'booking_longitude' => $req->longitude ?? null,
            'user_id' => $req->userId,
            'user_type' => $req->userType,
        ];
    }

    /**
     * | Store Booking Request in Model
     */
    public function storeBooking($req)
    {
        $metaRequest = $this->metaReqs($req);
        $res = WtBooking::create($metaRequest);
        $returnData['applicationId'] = $res->id;
        $returnData['bookingNo'] = $req->bookingNo;
        return $returnData;
    }


    /**
     * | Get Booking List from Model
     */
    public function getBookingList()
    {
        return DB::table('wt_bookings as wb')
            ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_drivers as dr', 'wb.driver_id', '=', 'dr.id')
            ->leftjoin('wt_resources as res', 'wb.vehicle_id', '=', 'res.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name', "dr.driver_name", 'wb.driver_delivery_update_date_time as cancelledDate', "res.vehicle_no", "wt_locations.location")
            ->orderBy('wb.ulb_id');
    }

    /**
     * | Get Aggigning List of Tanker,Driver, Hydration Center
     */
    public function assignList()
    {
        return DB::table('wt_bookings as wb')
            ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->join('wt_resources as wr', 'wr.id', '=', 'wb.vehicle_id')
            ->join('wt_drivers as wd', 'wd.id', '=', 'wb.driver_id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            // ->join('wt_driver_vehicle_maps as dvm', 'wb.vdm_id', '=', 'dvm.id')
            ->leftJoin(Db::raw("(select distinct application_id from wt_reassign_bookings)wtr"), "wtr.application_id", "wb.id")
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name', 'wr.vehicle_name', 'wr.vehicle_no', 'wd.driver_name', 'wd.driver_mobile', "wtr.application_id", "wt_locations.location")
            ->where('assign_date', '!=', NULL)
            ->whereNull('wtr.application_id');
    }


    /**
     * | Get Booking Details By Id
     */
    public function getBookingDetailById($id)
    {
        return $list = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name')
            ->where('wb.id', $id)
            ->first();
    }

    /**
     * | Get Payment Details By Application Id Before Payments
     */
    public function getPaymentDetailsById($id)
    {
        return $list = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->select('wb.booking_no', 'wb.applicant_name', 'wb.payment_amount', 'wb.id as applicationId', 'wb.mobile', 'wb.email', 'wc.capacity')
            ->where('wb.id', $id)
            ->first();
    }

    /**
     * | Get Today booking list
     */
    public function todayBookings($agencyId)
    {
        $todayDate = Carbon::now()->format('Y-m-d');
        return self::select('*')->where('delivery_date', $todayDate)
            ->where('agency_id', $agencyId);
    }


    /**
     * | Get Payment Reciept Details By Payment Id After Payments
     */
    public function getRecieptDetails($payId)
    {
        $details = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'whc.name as hydration_center_name')
            ->where('wb.id', $payId)
            // ->where('wb.payment_id', $payId)
            ->first();

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Water Tanker Booking";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }


    /**
     * | Get Payment Details By Payment Id After Payments
     */
    public function getPaymentDetails($payId)
    {
        $details = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->join('wt_transactions', 'wt_transactions.booking_id', '=', 'wb.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wt_transactions.id as tran_id', 'wc.capacity', 'whc.name as hydration_center_name')
            ->where('wb.payment_id', $payId)
            ->first();

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Water Tanker";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }

    /**
     * add function by sandeep bara
     */

    public function getReassignedBookingOrm()
    {
        return $this->hasMany(WtReassignBooking::class, "application_id", "id");
    }
    public function getLastReassignedBooking()
    {
        return $this->getReassignedBookingOrm()->orderBy("id", "DESC")->first();
    }

    public function getDeliveredDriver()
    {
        return $this->belongsTo(WtDriver::class, "delivered_by_driver_id", "id")->first();
    }

    public function getAssignedVehicle()
    {
        return $this->hasOne(WtResource::class, "id", "vehicle_id")->first();
    }
    public function getAssignedDriver()
    {
        return $this->hasOne(WtDriver::class, "id", "driver_id")->first();
    }

    public function getAllTrans()
    {
        return $this->hasMany(WtTransaction::class, "booking_id", "id")->whereIn("status", [1, 2])->orderBy("tran_date", "ASC")->orderBy("id", "ASC")->get();
    }


    //==================end=======================//
    public function getBookedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $waterCapacity, $perPage, $ulbId)
    {
        $query = DB::table('wt_bookings as wb')
            ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->select('wb.id', 'wb.booking_no', 'wb.applicant_name', 'wb.booking_date', 'wb.delivery_date', 'wc.capacity', 'wa.agency_name', "wt_locations.location", 'wb.address', 'wb.ward_id', 'wc.capacity')
            ->where('wb.is_vehicle_sent', '<=', '1')
            ->where('wb.assign_date', NULL)
            ->where('wb.payment_status', '=', '1')
            ->where('wb.delivery_date', '>=', Carbon::now()->format('Y-m-d'))
            ->whereBetween('wb.booking_date', [$fromDate, $toDate])
            ->where('wb.ulb_id',$ulbId )
            ->orderByDesc('wb.id');
        if ($wardNo) {
            $query->where('wb.ward_id', $wardNo);
        }

        if ($applicationMode) {
            $query->where('wb.user_type', $applicationMode);
        }
        if ($waterCapacity) {
            $query->where('wc.capacity', $waterCapacity);
        }
        $booking = $query->paginate($perPage);
        $totalbooking = $booking->total();
        $totalJSKBookings = $query->clone()->where('wb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('wb.user_type', 'Citizen')->count();
        $totalCapacity = $query->clone()->where('wc.capacity', $waterCapacity)->count();

        return [
            'current_page' => $booking->currentPage(),
            'last_page' => $booking->lastPage(),
            'data' => $booking->items(),
            'total' => $totalbooking,
            'totalJSKBookings' => $totalJSKBookings,
            'totalCitizenBookings' => $totalCitizenBookings,
            'bookedCapacityCount' => $totalCapacity
        ];
    }

    public function assignedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $waterCapacity, $driverName, $perPage, $ulbId)
    {
        $query = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->join('wt_resources as wr', 'wr.id', '=', 'wb.vehicle_id')
            ->join('wt_drivers as wd', 'wd.id', '=', 'wb.driver_id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->leftJoin(Db::raw("(select distinct application_id from wt_reassign_bookings)wtr"), "wtr.application_id", "wb.id")
            ->select('wb.id', 'wb.ward_id', 'wb.booking_no', 'wb.applicant_name', 'wb.booking_date', 'wb.delivery_date', 'wc.capacity', 'wa.agency_name', 'wr.vehicle_name', 'wr.vehicle_no', 'wd.driver_name', 'wd.driver_mobile', "wtr.application_id")
            ->where('assign_date', '!=', NULL)
            ->whereBetween('wb.booking_date', [$fromDate, $toDate])
            ->whereNull('wtr.application_id')
            ->where('delivery_track_status', '0')
            ->where('wb.ulb_id',$ulbId )
            ->where('delivery_date', '>=', Carbon::now()->format('Y-m-d'));
        if ($wardNo) {
            $query->where('wb.ward_id', $wardNo);
        }
        if ($driverName) {
            $query->where('dr.driver_name', $driverName);
        }
        if ($applicationMode) {
            $query->where('wb.user_type', $applicationMode);
        }
        if ($waterCapacity) {
            $query->where('wc.capacity', $waterCapacity);
        }
        $booking = $query->paginate($perPage);
        $totalbooking = $booking->total();
        return [
            'current_page' => $booking->currentPage(),
            'last_page' => $booking->lastPage(),
            'data' => $booking->items(),
            'total' => $totalbooking
        ];
    }

    public function getDeliveredList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $waterCapacity, $driverName, $perPage, $ulbId)
    {
        $query = DB::table('wt_bookings as wb')
            ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_drivers as dr', 'wb.driver_id', '=', 'dr.id')
            ->leftjoin('wt_resources as res', 'wb.vehicle_id', '=', 'res.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.id', 'wb.ward_id', 'wb.booking_no', 'wb.applicant_name', 'wb.booking_date', 'wb.delivery_date', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name', "dr.driver_name", "res.vehicle_no", "wt_locations.location")
            ->where('wb.is_vehicle_sent', 2)
            ->whereBetween('wb.booking_date', [$fromDate, $toDate])
            ->where('wb.ulb_id',$ulbId )
            ->orderByDesc('wb.id');
        if ($wardNo) {
            $query->where('wb.ward_id', $wardNo);
        }
        if ($driverName) {
            $query->where('dr.driver_name', $driverName);
        }
        if ($applicationMode) {
            $query->where('wb.user_type', $applicationMode);
        }
        if ($waterCapacity) {
            $query->where('wc.capacity', $waterCapacity);
        }
        $booking = $query->paginate($perPage);
        $totalbooking = $booking->total();
        return [
            'current_page' => $booking->currentPage(),
            'last_page' => $booking->lastPage(),
            'data' => $booking->items(),
            'total' => $totalbooking
        ];
    }

    public function getCancelBookingListByDriver($fromDate, $toDate, $wardNo = null, $perPage, $ulbId)
    {
        $query = DB::table('wt_bookings as wb')
            ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_drivers as dr', 'wb.driver_id', '=', 'dr.id')
            ->leftjoin('wt_resources as res', 'wb.vehicle_id', '=', 'res.id')
            ->select('wb.id', 'wb.booking_no', 'wb.applicant_name', 'wb.booking_date', 'wb.delivery_date', 'wc.capacity', 'wa.agency_name', "dr.driver_name", "res.vehicle_no", "wt_locations.location", 'wb.address', 'wb.ward_id', 'wc.capacity')
            ->where("delivery_track_status", 1)
            ->where("is_vehicle_sent", "<", 2)
            //->where("wb.ulb_id", 2)
            ->whereBetween(DB::raw("CAST(wb.driver_delivery_update_date_time as date)"), [$fromDate, $toDate])
            ->where('wb.ulb_id',$ulbId )
            ->orderByDesc('wb.id');
        if ($wardNo) {
            $query->where('wb.ward_id', $wardNo);
        }
        $booking = $query->paginate($perPage);
        $totalbooking = $booking->total();
        return [
            'current_page' => $booking->currentPage(),
            'last_page' => $booking->lastPage(),
            'data' => $booking->items(),
            'total' => $totalbooking
        ];
    }

    public function allBooking(Request $request)
    {
        $cancle = new WtCancellation();
        $perPage = $request->per_page ?: 10;
        $page = $request->page ?: 1;
        $user = Auth()->user();
        $ulbId = $user->ulb_id ?? null;
        $bookedApplication = $this->getBookedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->waterCapacity, $perPage, $ulbId);
        $assignedApplication = $this->assignedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->waterCapacity, $request->driverName, $perPage, $ulbId);
        $deliveredApplication = $this->getDeliveredList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->waterCapacity, $request->driverName, $perPage,$ulbId);
        $cancleByAgency = $cancle->getCancelBookingListByAgency($request->fromDate, $request->toDate, $request->wardNo, $perPage,$ulbId);
        $cancleByCitizen = $cancle->getCancelBookingListByCitizen($request->fromDate, $request->toDate, $request->wardNo, $perPage,$ulbId);
        $cancleByDriver = $this->getCancelBookingListByDriver($request->fromDate, $request->toDate, $request->wardNo, $perPage,$ulbId);

        $totalbooking = ($bookedApplication["total"] ?? 0) + ($assignedApplication["total"] ?? 0)
            + ($deliveredApplication["total"] ?? 0) + ($cancleByAgency["total"] ?? 0) + ($cancleByCitizen["total"] ?? 0)
            + ($cancleByDriver["total"] ?? 0);
        //dd($totalbooking);
        $data = collect($bookedApplication["data"] ?? [])
            ->merge(collect($assignedApplication["data"] ?? []))
            ->merge(collect($deliveredApplication["data"] ?? []))
            ->merge(collect($cancleByAgency["data"] ?? []))
            ->merge(collect($cancleByCitizen["data"] ?? []))
            ->merge(collect($cancleByDriver["data"] ?? []));
        $currentPageData = $data->forPage($page, $perPage);
        $paginator = new LengthAwarePaginator(
            $currentPageData,
            $data->count(),
            $perPage,
            $page
        );

        // return [
        //     'current_page' => $paginator->currentPage(),
        //     'last_page' => $paginator->lastPage(),
        //     'data' => $paginator->items(),
        //     'total' => $paginator->total()
        // ];
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'data' => $paginator->items(),
            //'total' => $paginator->total(),
            'total_bookings' => $totalbooking,
            'summary' => [
                'booked_total' => $bookedApplication["total"] ?? 0,
                'assigned_total' => $assignedApplication["total"] ?? 0,
                'delivered_total' => $deliveredApplication["total"] ?? 0,
                'cancel_by_agency_total' => $cancleByAgency["total"] ?? 0,
                'cancel_by_citizen_total' => $cancleByCitizen["total"] ?? 0,
                'cancel_by_driver_total' => $cancleByDriver["total"] ?? 0,
            ]
        ];
    }

    public function getPendingList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $perPage,$ulbId)
    {
        $dataQuery = WtBooking::select("wt_bookings.booking_no", "wt_bookings.booking_date", "wt_bookings.applicant_name", "wt_resources.vehicle_name", "wt_resources.vehicle_no", "wt_resources.resource_type", "wt_drivers.driver_name")
            ->join("wt_drivers", "wt_drivers.id", "wt_bookings.driver_id")
            ->join("wt_resources", "wt_resources.id", "wt_bookings.vehicle_id")
            ->where("wt_bookings.status", 1)
            ->whereNotNull('assign_date')
            ->where('is_vehicle_sent', '!=', 2)
            ->where('delivery_track_status', 0)
            ->where('wt_bookings.ulb_id', $ulbId)
            ->whereBetween('assign_date', [$fromDate, $toDate]);

        $reassignQuery = WtBooking::select("wt_bookings.booking_no",  "wt_bookings.booking_date", "wt_bookings.applicant_name", "wt_resources.vehicle_name", "wt_resources.vehicle_no", "wt_resources.resource_type", "wt_drivers.driver_name")
            ->join("wt_reassign_bookings", "wt_reassign_bookings.application_id", "wt_bookings.id")
            ->join("wt_drivers", "wt_drivers.id", "wt_reassign_bookings.driver_id")
            ->join("wt_resources", "wt_resources.id", "wt_reassign_bookings.vehicle_id")
            ->where("wt_bookings.status", 1)
            ->whereNotNull('assign_date')
            ->where('is_vehicle_sent', '!=', 2)
            ->where('wt_reassign_bookings.delivery_track_status', 0)
            ->where('wt_bookings.ulb_id', $ulbId)
            ->whereBetween('re_assign_date', [$fromDate, $toDate]);

        if ($wardNo) {
            $dataQuery->where('wt_bookings.ward_id', $wardNo);
            $reassignQuery->where('wt_bookings.ward_id', $wardNo);
        }
        if ($applicationMode) {
            $dataQuery->where('wt_bookings.user_type', $applicationMode);
            $reassignQuery->where('wt_bookings.user_type', $applicationMode);
        }
        $data = $dataQuery->union($reassignQuery)->paginate($perPage);

        return [
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items(),
            'total' => $data->total()
        ];
    }

    public function getPendingAgencyList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $perPage,$ulbId)
    {
        $dataQuery = DB::table('wt_bookings as wb')
            ->leftJoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftJoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->select(
                'wb.id',
                'wb.booking_no',
                'wb.applicant_name',
                'wb.booking_date',
                'wb.delivery_date',
                'wc.capacity',
                'wa.agency_name',
                'wt_locations.location',
                'wb.address',
                'wb.ward_id'
            )
            ->where('wb.is_vehicle_sent', '<=', '1')
            ->whereNull('wb.assign_date')
            ->where('wb.payment_status', '=', '1')
            ->where('wb.delivery_date', '>=', Carbon::now()->format('Y-m-d'))
            ->whereBetween('wb.booking_date', [$fromDate, $toDate])
            ->where('wb.ulb_id', $ulbId)
            ->orderByDesc('wb.id');

        $cancelledQuery = DB::table('wt_bookings as wb')
            ->leftJoin('wt_locations', 'wt_locations.id', '=', 'wb.location_id')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftJoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftJoin('wt_drivers as dr', 'wb.driver_id', '=', 'dr.id')
            ->leftJoin('wt_resources as res', 'wb.vehicle_id', '=', 'res.id')
            ->select(
                'wb.id',
                'wb.booking_no',
                'wb.applicant_name',
                'wb.booking_date',
                'wb.delivery_date',
                'wc.capacity',
                'wa.agency_name',
                'wt_locations.location',
                'wb.address',
                'wb.ward_id'
            )
            ->where('delivery_track_status', 1)
            ->where('is_vehicle_sent', '<', 2)
            ->whereBetween(DB::raw("CAST(wb.driver_delivery_update_date_time as date)"), [$fromDate, $toDate])
            ->where('wb.ulb_id', $ulbId)
            ->orderByDesc('wb.id');


        if ($wardNo) {
            $dataQuery->where('wb.ward_id', $wardNo);
            $cancelledQuery->where('wb.ward_id', $wardNo);
        }
        if ($applicationMode) {
            $dataQuery->where('wb.user_type', $applicationMode);
            $cancelledQuery->where('wb.user_type', $applicationMode);
        }
        $data = $dataQuery->union($cancelledQuery)->paginate($perPage);
        return [
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items(),
            'total' => $data->total()
        ];
    }
}
