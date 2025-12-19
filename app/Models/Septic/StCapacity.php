<?php

namespace App\Models\Septic;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StCapacity extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Make Store Request for Store capacity
     */
    public function metaReqs($req)
    {
        return [
            'capacity' => $req->capacity,
            'date' => Carbon::now()->format('Y-m-d'),
            'ulb_id' => auth()->user()->ulb_id,
            'created_by' => auth()->user()->id,
        ];
    }

    /**
     * | Store Capacity Request in Model
     */
    public function storeCapacity($req)
    {
        $metareqs = $this->metaReqs($req);
        return StCapacity::create($metareqs);
    }

    /**
     * | Get Capacity List
     */
    public function getCapacityList()
    {
        return StCapacity::select('id','capacity','date')->get();
    }

    /**
     * | Get Capacity Details By Id
     */
    public function getCapacityById($id){
        return StCapacity::select('id','capacity')->where('id',$id)->first();
    }
}
