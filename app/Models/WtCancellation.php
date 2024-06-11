<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtCancellation extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Get Cancelled List
     */
    public function getCancelBookingList()
    {
        return $list = DB::table('wt_cancellations as wtc')
            ->join('wt_capacities as wc', 'wtc.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wtc.agency_id', '=', 'wa.id')
            ->select('wtc.*', 'wc.capacity', 'wa.agency_name')
            ->orderBy('wtc.ulb_id')
            ->get();
    }

    public function getBookingDetailById($id)
    {
        return $list = DB::table('wt_cancellations as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name')
            ->where('wb.id', $id)
            ->first();
    }

    /**
     * | Get Today Cancelled Booking List
     */
    public function todayCancelledBooking($agencyId)
    {
        return self::select('*')->where('delivery_date', Carbon::now()->format('Y-m-d'))->where('agency_id', $agencyId);
    }

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

    public function getCancelBookingListByAgency($fromDate, $toDate, $wardNo = null, $perPage, $ulbId)
    {
        $query = DB::table('wt_cancellations as wtc')
            ->join('wt_capacities as wc', 'wtc.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wtc.agency_id', '=', 'wa.id')
            ->select('wtc.booking_no', 'wtc.applicant_name', 'wc.capacity', 'wtc.booking_date', 'wtc.cancel_date', 'wa.agency_name', 'wtc.ward_id', 'wtc.user_type as applied_by', DB::raw("'cancleByAgency' as application_type"))
            ->whereBetween('wtc.cancel_date', [$fromDate, $toDate])
            ->where('wtc.cancelled_by', 'Water-Agency')
            ->where('wtc.ulb_id', $ulbId);

        if ($wardNo) {
            $query->where('wtc.ward_id', $wardNo);
        }
        // $cancle = $query->paginate($perPage);
        // $totalcancle = $cancle->total();
        // return [
        //     'current_page' => $cancle->currentPage(),
        //     'last_page' => $cancle->lastPage(),
        //     'data' => $cancle->items(),
        //     'total' => $totalcancle
        // ];
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking
        ];
    }

    public function getCancelBookingListByCitizen($fromDate, $toDate, $wardNo = null, $perPage, $ulbId)
    {
        $query = DB::table('wt_cancellations as wtc')
            ->join('wt_capacities as wc', 'wtc.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wtc.agency_id', '=', 'wa.id')
            ->select('wtc.booking_no', 'wtc.applicant_name', 'wc.capacity', 'wtc.booking_date', 'wtc.cancel_date', 'wa.agency_name', 'wtc.ward_id', 'wtc.user_type as applied_by', DB::raw("'cancleByCitizen' as application_type"))
            ->whereBetween('wtc.cancel_date', [$fromDate, $toDate])
            ->where('wtc.cancelled_by', 'Citizen')
            ->where('wtc.ulb_id', $ulbId);

        if ($wardNo) {
            $query->where('wtc.ward_id', $wardNo);
        }
        // $cancle = $query->paginate($perPage);
        // $totalcancle = $cancle->total();
        // return [
        //     'current_page' => $cancle->currentPage(),
        //     'last_page' => $cancle->lastPage(),
        //     'data' => $cancle->items(),
        //     'total' => $totalcancle
        // ];
        if ($perPage) {
            $booking = $query->paginate($perPage);
        } else {
            $booking = $query->get();
        }

        $totalbooking = $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->total() : $booking->count();
        return [
            'current_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->currentPage() : 1,
            'last_page' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->lastPage() : 1,
            'data' => $booking instanceof \Illuminate\Pagination\LengthAwarePaginator ? $booking->items() : $booking,
            'total' => $totalbooking
        ];
    }
}
