<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuildingType extends Model
{
    use HasFactory;
    protected $guarded =[];

    /**
     * | Get List All BUilding Type for Septic Tank
     */
    public function getAllBuildingType(){
        return self::select('id','building_type')->where('status','1')->get();
    }
}
