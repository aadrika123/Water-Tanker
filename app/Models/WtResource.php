<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtResource extends Model
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
            'agency_id' => $req->agencyId,
            'vehicle_name' => $req->vehicleName,
            'vehicle_no' => $req->vehicleNo,
            'capacity_id' => $req->capacityId,
            'resource_type' => $req->resourceType,
            'is_ulb_resource' => $req->isUlbResource,
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
    public function getResourceList()
    {
        return $list = DB::table('wt_resources as wr')
                        ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
                        ->leftjoin('wt_agencies as wa', 'wr.agency_id', '=', 'wa.id')
                        ->select('wr.*', 'wc.capacity','wa.agency_name')
                        ->orderBy('wr.id','desc')
                        ->get();
    }

    /**
     * | Get Resource Details By Id
     */
    public function getResourceById($id){
        return $list = DB::table('wt_resources as wr')
                        ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
                        ->leftjoin('wt_agencies as wa', 'wr.agency_id', '=', 'wa.id')
                        ->select('wr.*', 'wc.capacity','wa.agency_name')
                        ->where('wr.id', $id)
                        ->first();
    }

    /**
     * | Get vehicle list for Master Data
     */
    public function getVehicleForMasterData(){
        return $list = DB::table('wt_resources as wr')
        ->join('wt_capacities as wc', 'wr.capacity_id', '=', 'wc.id')
        ->leftjoin('wt_agencies as wa', 'wr.agency_id', '=', 'wa.id')
        ->select('wr.*', 'wc.capacity','wa.agency_name')
        ->get();
    }
}
