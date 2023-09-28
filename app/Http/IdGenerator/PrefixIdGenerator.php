<?php

namespace App\MicroServices\IdGenerator;

use App\Models\IdGenerationParam as ModelsIdGenerationParam;
use App\Models\Masters\IdGenerationParam;
use App\Models\UlbMaster;

/**
 * | Created On-04/09/2023 
 * | Created By-Bikash Kumar
 * | Created for- Id Generation Service 
 */

class PrefixIdGenerator implements iIdGenerator
{
    protected $prefix;
    protected $paramId;
    protected $ulbId;
    protected $incrementStatus;

    public function __construct(int $paramId, int $ulbId)
    {
        $this->paramId          = $paramId;
        $this->ulbId            = $ulbId;
        $this->incrementStatus  = true;
    }

    /**
     * | Id Generation Business Logic 
     */
    public function generate(): string
    {
        $paramId = $this->paramId;
        $mIdGenerationParams = new ModelsIdGenerationParam();
        $mUlbMaster = new UlbMaster();
        $ulbDtls = $mUlbMaster::findOrFail($this->ulbId);

        $ulbDistrictCode = $ulbDtls->district_code;
        $ulbCategory = $ulbDtls->category;
        $code = $ulbDtls->code;

        $params = $mIdGenerationParams->getParams($paramId);
        $prefixString = $params->string_val;
        $stringVal = $ulbDistrictCode . $ulbCategory . $code;

        $stringSplit = collect(str_split($stringVal));
        $flag = ($stringSplit->sum()) % 9;
        $intVal = $params->int_val;
        // Case for the Increamental
        if ($this->incrementStatus == true) {
            $id = $stringVal . str_pad($intVal, 7, "0", STR_PAD_LEFT);
            $intVal += 1;
            $params->int_val = $intVal;
            $params->save();
        }

        // Case for not Increamental
        if ($this->incrementStatus == false) {
            $id = $stringVal  . str_pad($intVal, 7, "0", STR_PAD_LEFT);
        }

        return $prefixString . '-' . $id . $flag;
    }
}
