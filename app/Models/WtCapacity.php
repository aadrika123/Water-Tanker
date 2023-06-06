<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WtCapacity extends Model
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
        ];
    }

    /**
     * | Store Capacity Request in Model
     */
    public function storeCapacity($req)
    {
        $metareqs = $this->metaReqs($req);
        return WtCapacity::create($metareqs);
    }

    /**
     * | Get Capacity List
     */
    public function getCapacityList()
    {
        return WtCapacity::select('id','capacity')->get();
    }
}
