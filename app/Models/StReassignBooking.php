<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StReassignBooking extends Model
{
    use HasFactory;

    /**
     * | Get Re-assign Booking List
     */
    public function listReassignBooking()
    {
        return DB::table('st_reassign_bookings as wrb')
            ->join('st_bookings as wb', 'wb.id', '=', 'wrb.application_id')
            ->join('st_resources as wr', 'wr.id', '=', 'wrb.vehicle_id')
            ->join('st_drivers as wd', 'wd.id', '=', 'wrb.driver_id')
            ->join('st_capacities as wc', 'wc.id', '=', 'wb.capacity_id')
            ->select(
                'wrb.re_assign_date',
                'wb.applicant_name',
                'wb.booking_date',
                'wb.cleaning_date',
                'wb.address',
                'wb.id',
                'wb.ulb_id',
                'wb.mobile as applicant_mobile',
                'wd.driver_name',
                'wd.driver_mobile',
                'wr.vehicle_name',
                'wr.vehicle_no',
                'wc.capacity',
                "wrb.delivery_track_status",
                "wrb.delivery_comments"
            )
            ->get();
    }

    public function listReassignBookingOrm()
    {
        return DB::table('st_bookings as wb')
            ->join(DB::raw(
                "
                (
                    SELECT * FROM st_reassign_bookings
                    WHERE id in (select max(id) from st_reassign_bookings group by  application_id)
                )wrb"
            ), 'wb.id', '=', 'wrb.application_id')
            ->leftjoin('wt_locations as wtl', 'wtl.id', '=', 'wb.location_id')
            ->join('st_resources as wr', 'wr.id', '=', 'wrb.vehicle_id')
            ->join('st_drivers as wd', 'wd.id', '=', 'wrb.driver_id')
            ->leftJoin('st_capacities as wc', 'wc.id', '=', 'wb.capacity_id')
            ->select(
                'wb.assign_date',
                'wb.applicant_name',
                'wb.booking_date',
                'wb.cleaning_date',
                'wb.address',
                'wb.id',
                'wb.ulb_id',
                'wb.mobile as applicant_mobile',
                'wd.driver_name',
                'wd.driver_mobile',
                'wr.vehicle_name',
                'wr.vehicle_no',
                'wc.capacity',
                "wrb.delivery_track_status",
                "wrb.delivery_comments",
                "wb.booking_no",
                'wtl.location',
                'wb.ward_id'
            );
    }

    public function getAssignedVehicle()
    {
        return $this->hasOne(StResource::class, "id", "vehicle_id")->first();
    }
    public function getAssignedDriver()
    {
        return $this->hasOne(StDriver::class, "id", "driver_id")->first();
    }
}
