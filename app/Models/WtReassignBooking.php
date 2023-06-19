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
                ->select('wrb.re_assign_date','wb.applicant_name','wb.booking_date','wb.delivery_date','wb.address','wb.id','wb.mobile as applicant_mobile','wd.driver_name','wd.driver_mobile','wr.vehicle_name','wr.vehicle_no','wc.capacity')
                ->get();
    }
}
