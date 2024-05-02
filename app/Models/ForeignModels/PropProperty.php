<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropProperty extends Model
{
    use HasFactory;

    public function propertyDetailsfortradebyHoldingNo(string $holdingNo, int $ulb_id): array
    {   
        $property = self::select("*")
            ->leftjoin(
                DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name ,property_id
                                        FROM Prop_OwnerS 
                                        WHERE status = 1
                                        GROUP BY property_id
                                        ) owners
                                        "),
                function ($join) {
                    $join->on("owners.property_id", "=", "prop_properties.id");
                }
            )
            ->where("status", 1)
            ->where("new_holding_no", "<>", "")
            ->where("new_holding_no", "ILIKE", $holdingNo)
            ->where("ulb_id", $ulb_id)
            ->first();
        if(!$property)
        {
            $property = self::select("*")
            ->leftjoin(
                DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name ,property_id
                                        FROM Prop_OwnerS 
                                        WHERE status = 1
                                        GROUP BY property_id
                                        ) owners
                                        "),
                function ($join) {
                    $join->on("owners.property_id", "=", "prop_properties.id");
                }
            )
            ->where("status", 1)
            // ->where("new_holding_no", "<>", "")
            ->where("holding_no", "ILIKE", $holdingNo)
            ->where("ulb_id", $ulb_id)
            ->orderBy("id",'DESC')
            ->first();
        }
        if ($property) {
            return ["status" => true, 'property' => objToArray($property)];
        }
        return ["status" => false, 'property' => ''];
    }
}
