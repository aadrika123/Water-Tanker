<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StRate extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Get Septic Tank amount for Booking
     */
    public function getAmount($ulbId, $ulbArea, $buildingType)
    {
        // return self::select('rate')->where('ulb_id', $ulbId)->where('is_residential', $isResidential)->where('capacity_id', $capacityId)->first()->rate;
        return $rate = DB::table('st_rates')
            ->select(DB::raw("case when $ulbArea = 1 then rate_within_ulb
                          when $ulbArea = 0 then rate_outside_ulb
                    else 0 end as rate"))
            ->where('ulb_id', $ulbId)
            ->where('building_type_id', $buildingType)
            ->first()->rate;
    }
}
