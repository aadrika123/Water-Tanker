<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WtUlbCapacityRate extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Make Store Request for Store capacity rate
     */
    public function metaReqs($req)
    {
        return [
            'capacity_id' => $req->capacityId,
            'ulb_id' => $req->ulbId,
            'rate' => $req->rate,
            'date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store capacity Rate in model
     */
    public function storeCapacityRate($req)
    {
        $metareqs = $this->metaReqs($req);
        return WtUlbCapacityRate::create($metareqs);
    }


    /**
     * | Get Capacity Rate list ULB wise
     */
    public function getUlbCapacityRateList()
    {
        return WtUlbCapacityRate::select('wt_ulb_capacity_rates.id','wt_ulb_capacity_rates.date', 'wt_ulb_capacity_rates.ulb_id', 'wt_ulb_capacity_rates.rate', 'wc.capacity')
            ->join('wt_capacities as wc', 'wc.id', '=', 'wt_ulb_capacity_rates.capacity_id')
            ->get();
    }

    /**
     * | Get Capacity Rate Details for Master Datas
     */
    public function getCapacityRateForMasterData($ulbId)
    {
        return self::select('id', 'rate')->where('ulb_id',$ulbId)->where("status",1)->get();
    }

    /**
     * | Get capacity Rate Details By Id
     */
    public function getCapacityRateDetailsById($id)
    {
        return WtUlbCapacityRate::select('wt_ulb_capacity_rates.id', 'wt_ulb_capacity_rates.ulb_id','wt_ulb_capacity_rates.capacity_id', 'wt_ulb_capacity_rates.rate', 'wc.capacity')
            ->join('wt_capacities as wc', 'wc.id', '=', 'wt_ulb_capacity_rates.capacity_id')
            ->where('wt_ulb_capacity_rates.id', '=', $id)
            ->first();
    }
}
