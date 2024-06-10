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

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Septic Tanker Booking";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }

    public function todayBookings($ulb_id)
    {
        $todayDate = Carbon::now()->format('Y-m-d');
        return self::select('*')->where('cleaning_date', $todayDate)
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


    public function getBookedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $perPage,$ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select('stb.booking_no', 'stb.applicant_name', 'stb.address', 'stb.booking_date', 'stb.cleaning_date', 'wtl.location')
            ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
            ->where('assign_date', NULL)
            ->where('payment_status', 1)
            ->whereBetween('stb.booking_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');
        if ($wardNo) {
            $query->where('stb.ward_id', $wardNo);
        }

        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
        }
        $booking = $query->paginate($perPage);
        $totalbooking = $booking->total();
        $totalJSKBookings = $query->clone()->where('stb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stb.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking->currentPage(),
            'last_page' => $booking->lastPage(),
            'data' => $booking->items(),
            'total' => $totalbooking,
            'totalJSKBookings' => $totalJSKBookings,
            'totalCitizenBookings' => $totalCitizenBookings
        ];
    }

    public function getAssignedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $driverName,$perPage,$ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select('stb.booking_no', 'stb.applicant_name', 'stb.address', 'stb.booking_date', 'stb.cleaning_date', 'wtl.location', 'sd.driver_name', 'sr.vehicle_no')
            ->where('cleaning_date', '>=', Carbon::now()->format('Y-m-d'))
            ->where('assign_status', '1')
            ->where('delivery_track_status', '0')
            ->whereNull('str.application_id')
            ->whereBetween('stb.booking_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');
        if ($wardNo) {
            $query->where('stb.ward_id', $wardNo);
        }

        if ($driverName) {
            $query->where('sd.driver_name', $driverName);
        }

        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
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

    public function getCleanedList($fromDate, $toDate, $wardNo = null, $applicationMode = null, $driverName,$perPage,$ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select('stb.booking_no', 'stb.applicant_name', 'stb.address', 'stb.booking_date', 'stb.cleaning_date', 'wtl.location', 'sd.driver_name', 'sr.vehicle_no')
            ->where('stb.assign_status', '2')
            //->where('stb.ulb_id', '2')
            ->whereBetween('stb.cleaning_date', [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            ->orderByDesc('stb.id');
        if ($wardNo) {
            $query->where('stb.ward_id', $wardNo);
        }
        if ($driverName) {
            $query->where('sd.driver_name', $driverName);
        }
        if ($applicationMode) {
            $query->where('stb.user_type', $applicationMode);
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

    public function getCancelBookingListByDriver($fromDate, $toDate, $wardNo = null,$perPage,$ulbId)
    {
        $query =  DB::table('st_bookings as stb')
            ->leftjoin('st_drivers as sd', 'sd.id', '=', 'stb.driver_id')
            ->leftjoin('st_resources as sr', 'sr.id', '=', 'stb.vehicle_id')
            ->leftJoin(Db::raw("(select distinct application_id from st_reassign_bookings)str"), "str.application_id", "stb.id")
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'stb.location_id')
            ->select('stb.booking_no', 'stb.applicant_name', 'stb.address', 'stb.booking_date', 'stb.cleaning_date', 'wtl.location')
            ->where("delivery_track_status", 1)
            ->where("assign_status", "<", 2)
            ->whereBetween(DB::raw("CAST(stb.driver_delivery_update_date_time as date)"), [$fromDate, $toDate])
            ->where('stb.ulb_id', $ulbId)
            //->where("stb.ulb_id", 2)
            ->orderByDesc('stb.id');


        if ($wardNo) {
            $query->where('stb.ward_id', $wardNo);
        }
        $cancle = $query->paginate($perPage);
        $totalcancle = $cancle->total();
        return [
            'current_page' => $cancle->currentPage(),
            'last_page' => $cancle->lastPage(),
            'data' => $cancle->items(),
            'total' => $totalcancle
        ];
    }

    public function allBooking(Request $request)
    {
        $cancle = new StCancelledBooking();
        $perPage = $request->per_page ?: 10;
        $page = $request->page ?: 1;
        $user = Auth()->user();
        $ulbId = $user->ulb_id ?? null;
        $bookedApplication = $this->getBookedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $perPage,$ulbId);
        //dd($perPage);
        $assignedApplication = $this->getAssignedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->driverName, $perPage,$ulbId);
        $deliveredApplication = $this->getCleanedList($request->fromDate, $request->toDate, $request->wardNo, $request->applicationMode, $request->driverName, $perPage,$ulbId);
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
            // dd($data);
        $currentPageData = $data->forPage($page, $perPage);
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
            'total' => $paginator->total()
        ];
    }
}
