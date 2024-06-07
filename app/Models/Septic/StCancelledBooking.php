<?php

namespace App\Models\Septic;

use App\Models\StDriver;
use App\Models\StReassignBooking;
use App\Models\StResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        return self::select('*')->where('cleaning_date', Carbon::now()->format('Y-m-d'))->where('ulb_id', $ulb_id);
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

    public function getCancelBookingListByAgency($fromDate, $toDate, $wardNo = null)
    {
        $query =  DB::table('st_cancelled_bookings as stcb')
            ->select('stcb.booking_no', 'stcb.applicant_name', 'stcb.booking_date', 'stcb.cleaning_date', 'stcb.cancel_date', 'stcb.ward_id', 'wtl.location')
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id')
            ->whereBetween('stcb.cancel_date', [$fromDate, $toDate])
            ->where('stcb.cancelled_by', 'UlbUser');

        if ($wardNo) {
            $query->where('stcb.ward_id', $wardNo);
        }
        $cancle = $query->paginate(1000);
        $totalcancle = $cancle->total();
        return [
            'current_page' => $cancle->currentPage(),
            'last_page' => $cancle->lastPage(),
            'data' => $cancle->items(),
            'total' => $totalcancle
        ];;
    }


    public function getCancelBookingListByCitizen($fromDate, $toDate, $wardNo = null)
    {
        $query =  DB::table('st_cancelled_bookings as stcb')
            ->select('stcb.booking_no', 'stcb.applicant_name', 'stcb.booking_date', 'stcb.cleaning_date', 'stcb.cancel_date', 'stcb.ward_id', 'wtl.location')
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id')
            ->whereBetween('stcb.cancel_date', [$fromDate, $toDate])
            ->where('stcb.cancelled_by', 'Citizen');

        if ($wardNo) {
            $query->where('stcb.ward_id', $wardNo);
        }
        $cancle = $query->paginate(1000);
        $totalcancle = $cancle->total();
        return [
            'current_page' => $cancle->currentPage(),
            'last_page' => $cancle->lastPage(),
            'data' => $cancle->items(),
            'total' => $totalcancle
        ];;
    }

    public function totalcancle($fromDate, $toDate, $wardNo = null)
    {
        $query =  DB::table('st_cancelled_bookings as stcb')
            ->select('stcb.booking_no','stcb.applicant_name','stcb.address','stcb.booking_date','stcb.cleaning_date','wtl.location'
            )
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id')
            ->whereBetween('stcb.cancel_date', [$fromDate, $toDate])
            ->where('stcb.cancelled_by', 'Citizen');

        if ($wardNo) {
            $query->where('wtc.ward_id', $wardNo);
        }
        return $query;
    }
}
