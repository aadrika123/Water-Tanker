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

class StCancelledBooking extends Model
{
    use HasFactory;

    /**
     * | Get List of Cancelled Septic tank     
     */
    public function getCancelledBookingList()
    {
        return DB::table('st_cancelled_bookings as stcb')
            ->select('stcb.*', 'wtl.location')
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id');
    }

    public function todayCancelledBooking($ulb_id)
    {
        return self::select('*')->where('cancel_date', Carbon::now()->format('Y-m-d'))->where('ulb_id', $ulb_id);
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

    public function getCancelBookingListByAgency($fromDate, $toDate, $wardNo = null, $applicationMode, $perPage, $ulbId)
    {
        $query =  DB::table('st_cancelled_bookings as stcb')
            ->select(
                'stcb.booking_no',
                'stcb.applicant_name',
                'stcb.delivery_comments',
                // 'stcb.booking_date',
                // 'stcb.cleaning_date',
                DB::raw("TO_CHAR(stcb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stcb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'stcb.cancel_date',
                //'stcb.ward_id',
                'wtl.location',
                'ulb_ward_masters.ward_name AS ward_id',
                DB::raw("'cancleByAgency' as application_type"),
                'stcb.user_type as applied_by'
            )
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stcb.ward_id")
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id')
            ->whereBetween('stcb.cancel_date', [$fromDate, $toDate])
            ->where('stcb.ulb_id', $ulbId)
            ->where('stcb.cancelled_by', 'UlbUser');

        if ($wardNo) {
            $query->where('ulb_ward_masters.ward_name', $wardNo);
        }
        if ($applicationMode) {
            $query->where('stcb.user_type', $applicationMode);
        }
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $query->clone()->where('stcb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stcb.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'cancel_by_agency_total' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
        ];
    }


    public function getCancelBookingListByCitizen($fromDate, $toDate, $wardNo = null, $applicationMode, $perPage, $ulbId)
    {
        $query =  DB::table('st_cancelled_bookings as stcb')
            ->select(
                'stcb.booking_no',
                'stcb.applicant_name',
                'stcb.delivery_comments',
                // 'stcb.booking_date',
                // 'stcb.cleaning_date',
                DB::raw("TO_CHAR(stcb.booking_date, 'DD-MM-YYYY') as booking_date"),
                DB::raw("TO_CHAR(stcb.cleaning_date, 'DD-MM-YYYY') as cleaning_date"),
                'stcb.cancel_date',
                //'stcb.ward_id',
                'ulb_ward_masters.ward_name AS ward_id',
                'wtl.location',
                DB::raw("'cancleByCitizen' as application_type"),
                'stcb.user_type as applied_by'
            )
            ->leftJOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"stcb.ward_id")
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id')
            ->whereBetween('stcb.cancel_date', [$fromDate, $toDate])
            ->where('stcb.ulb_id', $ulbId)
            ->where('stcb.cancelled_by', 'Citizen');

        if ($wardNo) {
            $query->where('ulb_ward_masters.ward_name', $wardNo);
        }
        if ($applicationMode) {
            $query->where('stcb.user_type', $applicationMode);
        }
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        $totalJSKBookings = $query->clone()->where('stcb.user_type', 'JSK')->count();
        $totalCitizenBookings = $query->clone()->where('stcb.user_type', 'Citizen')->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking,
            'summary' => [
                'cancel_by_citizen_total' => $totalbooking,
                'applied_by_jsk' => $totalJSKBookings,
                'applied_by_citizen' => $totalCitizenBookings
            ]
        ];
    }
}
