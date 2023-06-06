<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WtHydrationCenter extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function metaReqs($req){
        return [
            'name'=>$req->name,
            'ulb_id'=>$req->ulbId,
            'ward_id'=>$req->wardId,
            'water_capacity' => $req->waterCapacity,
            'address'=>$req->address
        ];
    }

    /**
     * | Store Hydration Center in model
     */
    public function storeHydrationCenter($req)
    {
        $metaReqs=$this->metaReqs($req);
        return WtHydrationCenter::create($metaReqs);
    }

    /**
     * | Get Hydration Center list
     */
    public function getHydrationCenterList($req){
       return Self::all();
    }
}
