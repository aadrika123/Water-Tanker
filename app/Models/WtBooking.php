<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtBooking extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'citizen_id' => $req->citizenId,
            'agency_id' => $req->agencyId,
            'booking_date' => $req->bookingDate,
            'delivery_date' => $req->deliveryDate,
            'delivery_time' => $req->deliveryTime,
            'mobile' => $req->mobile,
            'email' => $req->email,
            'address' => $req->address,
            'ward_id' => $req->wardId,
            'capacity_id' => $req->capacityId,
            'quantity' => $req->quantity,
        ];
    }

    /**
     * | Store Booking Request in Model
     */
    public function storeBooking($req)
    {
        $metaRequest = $this->metaReqs($req);
        return WtBooking::create($metaRequest);
    }


    /**
     * | Get Booking List from Model
     */
    public function getBookingList()
    {
        return $list = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            // ->join('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name')
            ->orderBy('wb.ulb_id');
    }

    /**
     * | Get Aggigning List of Tanker,Driver, Hydration Center
     */
    public function assignList()
    {
        return DB::table('wt_bookings as wb')
                    ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
                    ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
                    ->join('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
                    ->join('wt_driver_vehicle_maps as dvm', 'wb.vdm_id', '=', 'dvm.id')
                    ->join('wt_resources as wr', 'wr.id', '=', 'dvm.vehicle_id')
                    ->join('wt_drivers as wd', 'wd.id', '=', 'dvm.driver_id')
                    ->select('wb.*', 'wc.capacity', 'wa.agency_name','whc.name as hydration_center_name','wr.vehicle_name','wr.vehicle_no','wd.driver_name','wd.driver_mobile')
                    ->where('assign_date','!=',NULL);
    }
}
