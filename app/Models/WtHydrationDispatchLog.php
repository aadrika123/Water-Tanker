<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtHydrationDispatchLog extends Model
{
    use HasFactory;
    protected $guarded=[];

    /**
     * | Make Store Request For Hydration Dispatch Logs
     */
    public function metaReqs($req){
        return [
            'ulb_id'=>$req->ulbId,
            'agency_id' => $req->agencyId,
            'vehicle_id' => $req->vehicleId,
            'hydration_center_id' => $req->hydrationCenterId,
            'dispatch_date' => $req->daipatchDate,
            'capacity_id' => $req->capacityId,
        ];
    }

    /**
     * | Store Hydration Center Dispatch Log
     */
    public function storeHydrationDispatchLog($req){
        $metaReqs=$this->metaReqs($req);
        return Self::create($metaReqs);
    }

    /**
     * | Get Hydration Center Dispatch Logs
     */
    public function getHydrationCenerDispatchLogList(){
        return $list = DB::table('wt_hydration_dispatch_logs as hdl')
                        ->join('wt_capacities as wc', 'hdl.capacity_id', '=', 'wc.id')
                        ->leftjoin('wt_agencies as wa', 'hdl.agency_id', '=', 'wa.id')
                        ->join('wt_hydration_centers as whc', 'hdl.hydration_center_id', '=', 'whc.id')
                        ->select('hdl.*', 'wc.capacity','wa.agency_name','whc.name as hydration_center_name')
                        ->get();
    }
}