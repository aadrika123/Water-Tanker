<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtDriverVehicleMap extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Make Meta Request For Storing Driver and Vehicle Mapping
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'driver_id' => $req->driverId,
            'vehicle_id' => $req->vehicleId,
            'is_ulb_vehicle' => $req->isUlbVehicle,
            'date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store Driver and Vehicle Mapping
     */
    public function storeMappingDriverVehicle($req)
    {
        $metaReqs = $this->metaReqs($req);
        return self::create($metaReqs);
    }

    /**
     * | Get Mapping List of Driver and Vehicle
     */
    public function getMapDriverVehicle()
    {
        return $list = DB::table('wt_driver_vehicle_maps as wdvm')
            ->join('wt_resources as wr', 'wdvm.vehicle_id', '=', 'wr.id')
            ->leftjoin('wt_agencies as wa', 'wr.agency_id', '=', 'wa.id')
            ->join('wt_drivers as wd', 'wdvm.driver_id', '=', 'wd.id')
            ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
            ->select('wdvm.*', 'wd.driver_name','wd.driver_license_no','wd.driver_address','wd.driver_mobile', 'wr.vehicle_name','wr.vehicle_no','wc.capacity','wa.agency_name')
            ->get();
    }
}
