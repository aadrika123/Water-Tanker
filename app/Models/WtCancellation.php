<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtCancellation extends Model
{
    use HasFactory;


    /**
     * | Get Cancelled List
     */
    public function getCancelBookingList()
    {
        return $list = DB::table('wt_cancellations as wtc')
            ->join('wt_capacities as wc', 'wtc.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wtc.agency_id', '=', 'wa.id')
            ->select('wtc.*', 'wc.capacity', 'wa.agency_name')
            ->orderBy('wtc.ulb_id')
            ->get();
    }
}
