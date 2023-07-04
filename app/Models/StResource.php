<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StResource extends Model
{
    use HasFactory;

    protected $guarded = [];


    /**
     * | Make meta request for store resource information
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'vehicle_name' => $req->vehicleName,
            'vehicle_no' => $req->vehicleNo,
            'capacity_id' => $req->capacityId,
            'resource_type' => $req->resourceType,
            'date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store Resource Information in Model
     */
    public function storeResourceInfo($req)
    {
        $metaReqs = $this->metaReqs($req);
        return self::create($metaReqs);
    }

    /**
     * | Get Resource List
     */
    public function getResourceList($ulbId)
    {
        return DB::table('st_resources as wr')
            ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
            ->select('wr.*', 'wc.capacity')
            ->where('wr.ulb_id', $ulbId)
            ->orderBy('wr.id', 'desc')
            ->get();
    }

    /**
     * | Get Resource Details By Id
     */
    public function getResourceById($id)
    {
        return DB::table('st_resources as wr')
            ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
            ->select('wr.*', 'wc.capacity')
            ->where('wr.id', $id)
            ->first();
    }

    /**
     * | Get vehicle list for Master Data
     */
    public function getVehicleForMasterData($ulbId)
    {
        return DB::table('wt_resources as wr')
            ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wr.agency_id', '=', 'wa.id')
            ->select('wr.*', 'wc.capacity', 'wa.agency_name')
            ->where('wr.ulb_id', $ulbId)
            ->get();
    }

    /**
     * | Get vehicle list for Assign Booking
     */
    public function getResourceListForAssign($ulbId)
    {
        return DB::table('st_resources as wr')
            ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
            ->select('wr.id', 'wr.ulb_id', 'vehicle_name', 'vehicle_no', 'wc.capacity')
            ->where('wr.ulb_id', $ulbId)
            ->orderBy('wr.id', 'desc')
            ->get();
    }
}
