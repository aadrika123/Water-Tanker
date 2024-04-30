<?php

namespace App\Observers\WaterTanker;

use App\Events\WaterTanker\EventWaterTanker;
use App\Models\WtBooking;

class WtBookingObserver
{
    /**
     * Handle the WtBooking "created" event.
     */
    public function created(WtBooking $wtBooking): void
    {
        if($wtBooking->getTable()==(new WtBooking())->getTable())
            event(new EventWaterTanker($wtBooking,"created"));        
    }

    /**
     * Handle the WtBooking "updated" event.
     */
    public function updated(WtBooking $wtBooking): void
    {
        if ($wtBooking->isDirty('driver_id')) 
        {
            event(new EventWaterTanker($wtBooking,"updated"));
        }

        if ($wtBooking->isDirty('is_vehicle_sent')) 
        {
            event(new EventWaterTanker($wtBooking,"updated"));
        }
        if ($wtBooking->isDirty('payment_status')) 
        {
            event(new EventWaterTanker($wtBooking,"updated"));
        }
    }

    public function updating(WtBooking $wtBooking): void
    {
        // if ($wtBooking->isDirty('driver_id')) 
        // {
        //     event(new EventWaterTanker($wtBooking,"updating"));
        // }
    }

    /**
     * Handle the WtBooking "deleted" event.
     */
    public function deleted(WtBooking $wtBooking): void
    {
        event(new EventWaterTanker($wtBooking,"deleted"));
    }

    /**
     * Handle the WtBooking "restored" event.
     */
    public function restored(WtBooking $wtBooking): void
    {
        //
    }

    /**
     * Handle the WtBooking "force deleted" event.
     */
    public function forceDeleted(WtBooking $wtBooking): void
    {
        //
    }
}
