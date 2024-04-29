<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfRole extends ParamModel
{
    public Function getDriverRoleId()
    {
        $data= self::where(DB::raw("upper(role_name)"),"=","DRIVER")->where("is_suspended",false)->orderBy("id","DESC")->first();
        return ($data? $data->id:0);
    }
    public Function getSepticTankDriverRoleId()
    {
        $data= self::where(DB::raw("upper(role_name)"),"=","SEPTIC TANKER DRIVER")->where("is_suspended",false)->orderBy("id","DESC")->first();
        return ($data? $data->id:0);
    }
}
