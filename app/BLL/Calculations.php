<?php

namespace App\BLL;

use App\Models\WtAgency;
use App\Models\WtBooking;
use App\Models\WtCapacity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * | Calculate of Booking Water Tanker
 * | Created By- Bikash Kumar
 * | Created On - 31 May 2023 
 * | Status - Open
 */


class Calculations
{
    protected $_baseUrl;
    public function __construct()
    {
        $this->_baseUrl = Config::get('constants.BASE_URL');
    }

    /**
     * | Check booking is available or not in Agency
     */
    public function checkBookingStatus($deliveryDate, $agencyId, $capacityId)
    {
        $agencyCapacity = WtAgency::find($agencyId)->first('dispatch_capacity')->dispatch_capacity;    // Find Agency Capacity
        $list = WtBooking::select('agency_id', 'delivery_date', 'capacity_id', 'quantity')             // get booking list of delivery date
            ->where(['agency_id' => $agencyId, 'delivery_date' => $deliveryDate])
            ->get();
        $booked = collect();
        $multiplied = $list->map(function ($item) use ($booked) {                                      // Map book list for calculate total booked capacity
            $capacity = WtCapacity::where('id', $item->capacity_id)->first()->capacity;
            $booked->push($capacity * $item->quantity);                                                // Push on collection capacity multiplied by quantity
        });
        $totalBooked = $booked->sum();                                                                 // Add total booked capacity 
        $currentBooking = WtCapacity::where('id', $capacityId)->first()->capacity;                     // find current book capacity
        $totalBooking = $totalBooked + $currentBooking;                                                // Add total book and current book for find agency capacity
        if ($agencyCapacity >= $totalBooking)
            return true;
        else
            return false;
    }
}
