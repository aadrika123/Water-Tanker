<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UlbMaster extends ParamModel
{
    use HasFactory;

    public function getAllUlb()
    {
        $ulb = UlbMaster::orderBy('ulb_name')
            ->where('active_status', true)
            ->get();
        return responseMsgs(true, "", remove_null($ulb));
    }
}