<?php

namespace App\Models\Septic;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StUlbCapacityRate extends Model
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
            'is_residential' => $req->isResidential,
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
        return StUlbCapacityRate::create($metareqs);
    }


    /**
     * | Get Capacity Rate list ULB wise
     */
    public function getUlbCapacityRateList()
    {
        return StUlbCapacityRate::select(
            'st_ulb_capacity_rates.id',
            'st_ulb_capacity_rates.date',
            'st_ulb_capacity_rates.ulb_id',
            'st_ulb_capacity_rates.rate',
            'st_ulb_capacity_rates.is_residential',
            DB::raw('(CASE 
                    WHEN st_ulb_capacity_rates.is_residential = 1 THEN "Residential" 
                    ELSE "Commercial" 
                    END) AS building_type'),
            'sc.capacity'
        )
            ->join('st_capacities as sc', 'sc.id', '=', 'st_ulb_capacity_rates.capacity_id')
            ->get();
    }

    /**
     * | Get Capacity Rate Details for Master Datas
     */
    public function getCapacityRateForMasterData($ulbId)
    {
        return self::select('id', 'rate')->where('ulb_id', $ulbId)->get();
    }

    /**
     * | Get capacity Rate Details By Id
     */
    public function getCapacityRateDetailsById($id)
    {
        return StUlbCapacityRate::select(
            'st_ulb_capacity_rates.id',
            'st_ulb_capacity_rates.ulb_id',
            'st_ulb_capacity_rates.capacity_id',
            'st_ulb_capacity_rates.rate',
            'sc.capacity',
            'st_ulb_capacity_rates.is_residential',
            DB::raw('(CASE 
                    WHEN st_ulb_capacity_rates.is_residential = 1 THEN "Residential" 
                    ELSE "Commercial" 
                    END) AS building_type'),
        )
            ->join('st_capacities as sc', 'sc.id', '=', 'st_ulb_capacity_rates.capacity_id')
            ->where('st_ulb_capacity_rates.id', '=', $id)
            ->first();
    }

    /**
     * | Get Capacity List Fro Booking
     */
    public function getCapacityListForBooking($ulbId, $isResidential)
    {
        $capacityids = StUlbCapacityRate::select('capacity_id')->where(['ulb_id' => $ulbId, 'is_residential' => $isResidential])->get();
        $cap = collect();
        $capacity = $capacityids->map(function ($capacity, $key) use ($cap) {
            $cap = StCapacity::select('id', 'capacity')->where('id', $capacity['capacity_id'])->first();
            return $cap;
        });
        return $capacity;
    }

    /**
     * | Get Septic Tank amount
     */
    public function getAmount($ulbId, $capacityId, $isResidential)
    {
        return self::select('rate')->where('ulb_id', $ulbId)->where('is_residential', $isResidential)->where('capacity_id', $capacityId)->first()->rate;
    }
}
