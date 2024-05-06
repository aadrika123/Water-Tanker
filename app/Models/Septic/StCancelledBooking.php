<?php

namespace App\Models\Septic;

use App\Models\StDriver;
use App\Models\StReassignBooking;
use App\Models\StResource;
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

    public function getReassignedBookingOrm()
    {
        return $this->hasMany(StReassignBooking::class,"application_id","id");
    }
    public function getLastReassignedBooking()
    {
        return $this->getReassignedBookingOrm()->orderBy("id","DESC")->first();
    }

    public function getDeliveredDriver()
    {
        return $this->belongsTo(StDriver::class,"delivered_by_driver_id","id")->first();
    }

    public function getAssignedVehicle()
    {
        return $this->hasOne(StResource::class,"id","vehicle_id")->first();
    }
    public function getAssignedDriver()
    {
        return $this->hasOne(StDriver::class,"id","driver_id")->first();
    }
    public function getAllTrans()
    {
        return $this->hasMany(StTransaction::class,"booking_id","id")->whereIn("status",[1,2])->orderBy("tran_date","ASC")->orderBy("id","ASC")->get();
    }
}
