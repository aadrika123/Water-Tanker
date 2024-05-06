<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtCancellation extends Model
{
    use HasFactory;


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
    public function todayCancelledBooking($agencyId){
        return self::select('*')->where('delivery_date',Carbon::now()->format('Y-m-d'))->where('agency_id',$agencyId);  
    }

    public function getReassignedBookingOrm()
    {
        return $this->hasMany(WtReassignBooking::class,"application_id","id");
    }
    public function getLastReassignedBooking()
    {
        return $this->getReassignedBookingOrm()->orderBy("id","DESC")->first();
    }

    public function getDeliveredDriver()
    {
        return $this->belongsTo(WtDriver::class,"delivered_by_driver_id","id")->first();
    }

    public function getAssignedVehicle()
    {
        return $this->hasOne(WtResource::class,"id","vehicle_id")->first();
    }
    public function getAssignedDriver()
    {
        return $this->hasOne(WtDriver::class,"id","driver_id")->first();
    }
}
