<?php

namespace App\Http\IdGenerator;

use App\Models\ForeignModels\UlbMaster;
// use App\Models\IdGenerationParam ;
use App\Models\ForeignModels\IdGenerationParam;
use App\Models\Septic\StBooking;

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
    // public function generate(): string
    // {
    //     $paramId = $this->paramId;
    //     $mIdGenerationParams = new IdGenerationParam();
    //     $mUlbMaster = new UlbMaster();
    //     $ulbDtls = $mUlbMaster::findOrFail($this->ulbId);

    //     $ulbDistrictCode = $ulbDtls->district_code;
    //     $ulbCategory = $ulbDtls->category;
    //     $code = $ulbDtls->code;

    //     $params = $mIdGenerationParams->getParams($paramId);
    //     $prefixString = $params->string_val;
    //     $stringVal = $ulbDistrictCode . $ulbCategory . $code;

    //     $stringSplit = collect(str_split($stringVal));
    //     $flag = ($stringSplit->sum()) % 9;
    //     $intVal = $params->int_val;
    //     // Case for the Increamental
    //     if ($this->incrementStatus == true) {
    //         $id = $stringVal . str_pad($intVal, 7, "0", STR_PAD_LEFT);
    //         $intVal += 1;
    //         $params->int_val = $intVal;
    //         $params->save();
    //     }

    //     // Case for not Increamental
    //     if ($this->incrementStatus == false) {
    //         $id = $stringVal  . str_pad($intVal, 7, "0", STR_PAD_LEFT);
    //     }

    //     return $prefixString . '-' . $id . $flag;
    // }

    public function generate(): string
    {
        $paramId = $this->paramId;
        $mIdGenerationParams = new IdGenerationParam();
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
        $unique = false;

        // Loop until a unique booking_no is generated
        while (!$unique) {
            $id = $stringVal . str_pad($intVal, 7, "0", STR_PAD_LEFT);
            $bookingNo = $prefixString . '-' . $id . $flag;

            // Check if the generated bookingNo already exists
            if (!$this->checkBookingNoExists($bookingNo)) {
                $unique = true;
            } else {
                $intVal += 1; // Increment the counter if duplicate is found
            }
        }

        // Save the updated int_val
        if ($this->incrementStatus == true) {
            $params->int_val = $intVal + 1; // Increment the value for the next generation
            $params->save();
        }

        return $bookingNo;
    }


    public function checkBookingNoExists($bookingNo)
    {
        return StBooking::where('booking_no', $bookingNo)->exists();
    }
}
