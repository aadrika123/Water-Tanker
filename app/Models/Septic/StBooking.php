<?php

namespace App\Models\Septic;

use App\Models\StDriver;
use App\Models\StReassignBooking;
use App\Models\StResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StBooking extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Store meta Request in database 
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'is_ulb' => $req->ulbArea,
            'building_type_id' => $req->buildingType,
            'citizen_id' => $req->citizenId,
            'applicant_name' => $req->applicantName,
            'mobile' => $req->mobile,
            'email' => $req->email,
            'address' => $req->address,
            'booking_date' => Carbon::now()->format('Y-m-d'),
            'cleaning_date' => $req->cleaningDate,
            'ward_id' => $req->wardId,
            'capacity_id' => $req->capacityId,
            'distance' => $req->distance,
            'road_width' => $req->roadWidth,
            'booking_no' => $req->bookingNo,
            'payment_amount' => $req->paymentAmount,
            'holding_no' => $req->holdingNo,
            'location_id' => $req->locationId,
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
        $res = StBooking::create($metaRequest);
        $returnData['applicationId'] = $res->id;
        $returnData['bookingNo'] = $req->bookingNo;
        return $returnData;
    }

    /**
     * | Get Booking List
     */
    public function getBookingList()
    {
        return DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->select('stb.*', 'wtl.location', 'sd.driver_name', 'sr.vehicle_no')
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id');
    }

    /**
     * | Get Application Details By Id
     */
    public function getApplicationDetailsById($id)
    {
        return StBooking::select('*')->where('id', $id)->first();
    }

    /**
     * | Get Payment Details By Payment Id After Payments
     */
    public function getPaymentDetails($payId)
    {
        $details = DB::table('st_bookings as sb')
            ->join('st_transactions', 'st_transactions.booking_id', '=', 'sb.id')
            ->select('*', 'st_transactions.id as tran_id')
            ->where('sb.payment_id', $payId)
            ->first();

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Septic Tanker";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }


    /**
     * | Get Payment Reciept Details By Payment Id After Payments
     */
    public function getRecieptDetails($payId)
    {
        $details = DB::table('st_bookings as sb')->select('*')
            ->where('sb.id', $payId)
            ->first();
        if(!$details){
            $details = DB::table('st_cancelled_bookings as sb')->select('*')
            ->where('sb.id', $payId)
            ->first();
        }

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Septic Tanker Booking";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }

    public function todayBookings($ulb_id)
    {
        $todayDate = Carbon::now()->format('Y-m-d');
        return self::select('*')->where('booking_date', $todayDate)
            ->where('ulb_id', $ulb_id);
    }


    public function getReassignedBookingOrm()
    {
        return $this->hasMany(StReassignBooking::class, "application_id", "id");
    }
    public function getLastReassignedBooking()
    {
        return $this->getReassignedBookingOrm()->orderBy("id", "DESC")->first();
    }

    public function getDeliveredDriver()
    {
        return $this->belongsTo(StDriver::class, "delivered_by_driver_id", "id")->first();
    }

    public function getAssignedVehicle()
    {
        return $this->hasOne(StResource::class, "id", "vehicle_id")->first();
    }
    public function getAssignedDriver()
    {
        return $this->hasOne(StDriver::class, "id", "driver_id")->first();
    }

    public function getAllTrans()
    {
        return $this->hasMany(StTransaction::class, "booking_id", "id")->whereIn("status", [1, 2])->orderBy("tran_date", "ASC")->orderBy("id", "ASC")->get();
    }


    public function getBookedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $perPage, $ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id", DB::raw("'booked' as application_type"))
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select(
                'stb.booking_no',
                'stb.applicant_name',
                'stb.address',
                //'stb.ward_id',
                // 'stb.booking_date',
                // 'stb.cleaning_date',
                'ulb_ward_masters.ward_name AS ward_id',
                DB::raw("TO_CHAR(stb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'wtl.location',
                'stb.user_type as applied_by'
            )
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stb.ward_id")
            ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
            ->where('assign_date', NULL)
            ->where('payment_status', 1)
            ->whereBetween('stb.booking_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');
        if ($wardNo) {
            $query->where('ulb_ward_masters.ward_name', $wardNo);
        }

        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
        }
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $query->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stb.user_type', 'Citizen')->count();

        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'booked_total' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
            // 'totalJSKBookings' => $totalJSKBookings,
            // 'totalCitizenBookings' => $totalCitizenBookings
        ];
    }

    public function getAssignedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $driverName, $perPage, $ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select(
                'stb.booking_no',
                'stb.applicant_name',
                'stb.address',
                //'stb.ward_id',
                // 'stb.booking_date',
                // 'stb.cleaning_date',
                'ulb_ward_masters.ward_name AS ward_id',
                DB::raw("TO_CHAR(stb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'wtl.location',
                'sd.driver_name',
                'sr.vehicle_no',
                DB::raw("'assigned' as application_type"),
                'stb.user_type as applied_by'
            )
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stb.ward_id")

            ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
            ->where('assign_status', '1')
            ->where('delivery_track_status', '0')
            ->whereNull('str.application_id')
            ->whereBetween('stb.booking_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');
        if ($wardNo) {
            $query->where('ulb_ward_masters.ward_name', $wardNo);
        }

        if ($driverName) {
            $query->where('sd.driver_name', $driverName);
        }

        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
        }

        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $query->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stb.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'assigned_total' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
        ];
    }

    public function getCleanedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $driverName, $perPage, $ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select(
                'stb.booking_no',
                'stb.applicant_name',
                'stb.address',
               // 'stb.ward_id',
                // 'stb.booking_date',
                // 'stb.cleaning_date',
                'ulb_ward_masters.ward_name AS ward_id',
                DB::raw("TO_CHAR(stb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'wtl.location',
                'sd.driver_name',
                'sr.vehicle_no',
                DB::raw("'cleaned' as application_type"),
                'stb.user_type as applied_by'
            )
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stb.ward_id")
            ->where('stb.assign_status', '2')
            //->where('stb.ulb_id', '2')
            ->whereBetween('stb.booking_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');
        if ($wardNo) {
            $query->where('ulb_ward_masters.ward_name', $wardNo);
        }
        if ($driverName) {
            $query->where('sd.driver_name', $driverName);
        }
        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
        }
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $query->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stb.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'delivered_total' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
        ];
    }

    public function getCancelBookingListByDriver($fromDate, $toDate, $wardNo = null, $applicationMode, $perPage, $ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select(
                'stb.booking_no',
                'stb.applicant_name',
                'stb.address',
                'stb.delivery_comments',
                //'stb.ward_id',
                // 'stb.booking_date',
                // 'stb.cleaning_date',
                'ulb_ward_masters.ward_name AS ward_id',
                DB::raw("TO_CHAR(stb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'wtl.location',
                DB::raw("'cancleByDriver' as application_type"),
                'stb.user_type as applied_by'
            )
            ->leftJoin("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stb.ward_id")
            ->where("delivery_track_status", 1)
            ->where("assign_status", "<", 2)
            ->whereBetween(DB::raw("CAST(stb.driver_delivery_update_date_time as date)"), [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            //->where("stb.ulb_id", 2)
            ->orderByDesc('stb.id');


        if ($wardNo) {
            $query->where('stb.ward_id', $wardNo);
        }
        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
        }
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $query->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stb.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'cancel_by_driver_total' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
        ];
    }

    public function allBooking(Request $request)
    {
        $cancle = new StCancelledBooking();
        $perPage = $request->perPage ?: 10;
        $page = $request->page ?: 1;
        $user = Auth()->user();
        $ulbId = $user->ulb_id ?? null;
        $bookedApplication = $this->getBookedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, null, $ulbId);
        //dd($perPage);
        $assignedApplication = $this->getAssignedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->driverName, null, $ulbId);
        $deliveredApplication = $this->getCleanedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->driverName, null, $ulbId);
        $cancleByAgency = $cancle->getCancelBookingListByAgency($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, null, $ulbId);
        $cancleByCitizen = $cancle->getCancelBookingListByCitizen($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, null, $ulbId);
        $cancleByDriver = $this->getCancelBookingListByDriver($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, null, $ulbId);

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
        $currentPageData = $data->forPage($page, $perPage)->values();
        $appliedByJSKCount = $data->where('applied_by', 'JSK')->count();
        $appliedByCitizenCount = $data->where('applied_by', 'Citizen')->count();
        $paginator = new LengthAwarePaginator(
            $currentPageData,
            $data->count(),
            $perPage,
            $page
        );

        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'data' => $paginator->items(),
            'total_bookings' => $totalbooking,
            'summary' => [
                'booked_total' => $bookedApplication["total"] ?? 0,
                'assigned_total' => $assignedApplication["total"] ?? 0,
                'delivered_total' => $deliveredApplication["total"] ?? 0,
                'cancel_by_agency_total' => $cancleByAgency["total"] ?? 0,
                'cancel_by_citizen_total' => $cancleByCitizen["total"] ?? 0,
                'cancel_by_driver_total' => $cancleByDriver["total"] ?? 0,
                'applied_by_jsk' => $appliedByJSKCount,
                'applied_by_citizen' => $appliedByCitizenCount
            ]
        ];
    }

    public function getPendingList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $perPage, $ulbId)
    {
        $dataQuery = StBooking::select(
            "st_bookings.booking_no",
            DB::raw("TO_CHAR(st_bookings.booking_date, 'DD-MM-YYYY') as booking_date"),
            // "st_bookings.booking_date",
            "st_bookings.applicant_name",
            "st_resources.vehicle_name",
            "st_resources.vehicle_no",
            "st_resources.resource_type",
            "wtl.location",
            'ulb_ward_masters.ward_name AS ward_id',
            "st_drivers.driver_name",
            DB::raw("'pendingAtDriver' as application_type"),
            'st_bookings.user_type as applied_by'
        )
        
            ->join("st_drivers", "st_drivers.id", "st_bookings.driver_id")
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"st_bookings.ward_id")

            ->join("st_resources", "st_resources.id", "st_bookings.vehicle_id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'st_bookings.location_id')
            ->where('assign_date', '!=', NULL)
            ->where('assign_status', 1)
            ->where('delivery_track_status', 0)
            ->where('st_bookings.ulb_id', $ulbId)
            ->whereBetween('assign_date', [$fromDate, $toDate]);

        if ($wardNo) {
            $dataQuery->where('ulb_ward_masters.ward_name', $wardNo);
        }
        if ($applicationMode) {
            $dataQuery->where('st_bookings.user_type', $applicationMode);
        }
        $data = $dataQuery;

        if ($perPage) {
            $booking = $data->paginate($perPage);
        } else {
            $booking = $data->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $dataQuery->clone()->where('st_bookings.user_type', 'JSK')->count();
        $totalCitizenBookings = $dataQuery->clone()->where('st_bookings.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'driver_pending' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
        ];
    }

    public function getPendingAgencyList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $perPage, $ulbId)
    {
        $dataQuery = DB::table('st_bookings as stb')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->select(
                'stb.booking_no',
                'stb.applicant_name',
                'stb.address',
                'ulb_ward_masters.ward_name AS ward_id',
                // 'stb.booking_date',
                // 'stb.cleaning_date',
                DB::raw("TO_CHAR(stb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'wtl.location',
                'sr.vehicle_name',
                "sr.vehicle_no",
                "sr.resource_type",
                "sd.driver_name",
                DB::raw("'pendingAtAgency' as application_type"),
                'stb.user_type as applied_by'
            )
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stb.ward_id")
            ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
            ->where('assign_date', NULL)
            ->where('payment_status', 1)
            ->whereBetween('stb.booking_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');

        $cancelledQuery = DB::table('st_bookings as stb')
        ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stb.ward_id")
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select(
                'stb.booking_no',
                'stb.applicant_name',
                'stb.address',
                'ulb_ward_masters.ward_name AS ward_id',
                // 'stb.booking_date',
                // 'stb.cleaning_date',
                DB::raw("TO_CHAR(stb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'wtl.location',
                'sr.vehicle_name',
                "sr.vehicle_no",
                "sr.resource_type",
                "sd.driver_name",
                DB::raw("'pendingAtAgency' as application_type"),
                'stb.user_type as applied_by'
            )
            ->where("delivery_track_status", 1)
            ->where("assign_status", "<", 2)
            ->whereBetween(DB::raw("CAST(stb.driver_delivery_update_date_time as date)"), [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');

        if ($wardNo) {
            $dataQuery->where('ulb_ward_masters.ward_name', $wardNo);
            $cancelledQuery->where('ulb_ward_masters.ward_name', $wardNo);
        }
        if ($applicationMode) {
            $dataQuery->where('stb.user_type', $applicationMode);
            $cancelledQuery->where('stb.user_type', $applicationMode);
        }
        $data = $dataQuery->union($cancelledQuery);

        if ($perPage) {
            $booking = $data->paginate($perPage);
        } else {
            $booking = $data->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $dataQuery->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings = $dataQuery->clone()->where('stb.user_type', 'Citizen')->count();
        $totalJSKBookings1 = $cancelledQuery->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings1 = $cancelledQuery->clone()->where('stb.user_type', 'Citizen')->count();
        $totaljsk = $totalJSKBookings + $totalJSKBookings1;
        $totalCitizen = $totalCitizenBookings + $totalCitizenBookings1;
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'agency_pending' => $totalbooking,
                'applied_by_jsk' => $totaljsk,
                'applied_by_citizen' => $totalCitizen
            ]
        ];
    }

    public function allPending(Request $request)
    {
        $perPage = $request->perPage ?: 10;
        $page = $request->page ?: 1;
        $user = Auth()->user();
        $ulbId = $user->ulb_id ?? null;
        $bookedApplication = $this->getPendingList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, null, $ulbId);
        //dd($perPage);
        $assignedApplication = $this->getPendingAgencyList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, null, $ulbId);
        $data = collect($bookedApplication['data'])->merge($assignedApplication['data']);
        $appliedByJSKCount = $data->where('applied_by', 'JSK')->count();
        $appliedByCitizenCount = $data->where('applied_by', 'Citizen')->count();
        $totalBooking = count($data);
        $currentPageData = $data->forPage($page, $perPage)->values();

        return [
            'current_page' => $page,
            'last_page' => ceil($totalBooking / $perPage),
            'data' => $currentPageData,
            'total_bookings' => $totalBooking,
            'summary' => [
                'driver_pending' => $bookedApplication['total'] ?? 0,
                'agency_pending' => $assignedApplication['total'] ?? 0,
                'applied_by_jsk' => $appliedByJSKCount,
                'applied_by_citizen' => $appliedByCitizenCount
            ]
        ];
    }
}
