<?php

namespace App\Models\Septic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StCancelledBooking extends Model
{
    use HasFactory;

    /**
     * |        
     */
    public function getCancelledBookingList()
    {
        // return $list=StCancelledBooking::select('*');
        return DB::table('st_cancelled_bookings as stcb')
            ->select('stcb.*', 'wtl.location')
            ->join('wt_locations as wtl', 'wtl.id', '=', 'stcb.location_id');
    }
}
