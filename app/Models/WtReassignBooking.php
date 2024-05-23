<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtReassignBooking extends Model
{
    use HasFactory;


    /**
     * | Get Re-assign Booking List
     */
    public function listReassignBooking(){
        return DB::table('wt_reassign_bookings as wrb')
                ->join('wt_bookings as wb','wb.id','=','wrb.application_id')
                ->join('wt_resources as wr','wr.id','=','wrb.vehicle_id')
                ->join('wt_drivers as wd','wd.id','=','wrb.driver_id')
                ->join('wt_capacities as wc','wc.id','=','wb.capacity_id')
                ->join('wt_hydration_centers as hc','hc.id','=','wb.hydration_center_id')
                ->select('wrb.re_assign_date','wb.applicant_name','wb.booking_date','wb.delivery_date','wb.address','wb.id','wb.ulb_id','wb.mobile as applicant_mobile','wd.driver_name','wd.driver_mobile','wr.vehicle_name','wr.vehicle_no','wc.capacity','hc.name as hydration_center_name'
                    ,"wrb.delivery_track_status","wrb.delivery_comments")
                ->get();
    }

    public function listReassignBookingOrm(){
        return DB::table('wt_bookings as wb')
                ->join(DB::raw("
                    (
                        SELECT * FROM wt_reassign_bookings
                        WHERE id in (select max(id) from wt_reassign_bookings group by  application_id)
                    )wrb"
                ),'wb.id','=','wrb.application_id')
                ->join('wt_resources as wr','wr.id','=','wrb.vehicle_id')
                ->join('wt_drivers as wd','wd.id','=','wrb.driver_id')
                ->join('wt_capacities as wc','wc.id','=','wb.capacity_id')
                ->leftjoin('wt_hydration_centers as hc','hc.id','=','wb.hydration_center_id')
                ->select('wb.assign_date','wb.applicant_name','wb.booking_date','wb.delivery_date','wb.address','wb.id','wb.ulb_id',"wb.booking_no",
                    'wb.mobile as applicant_mobile','wd.driver_name','wd.driver_mobile','wr.vehicle_name','wr.vehicle_no','wc.capacity',
                    'hc.name as hydration_center_name'
                    ,"wrb.delivery_track_status","wrb.delivery_comments");
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
