<?php

namespace App\Observers\WaterTanker;

use App\Events\WaterTanker\EventWaterTankerBooked;
use App\Models\WtBooking;

class WtBookingObserver
{
    /**
     * Handle the WtBooking "created" event.
     */
    public function created(WtBooking $wtBooking): void
    {
        event(new EventWaterTankerBooked($wtBooking));
    }

    /**
     * Handle the WtBooking "updated" event.
     */
    public function updated(WtBooking $wtBooking): void
    {
        //
    }

    /**
     * Handle the WtBooking "deleted" event.
     */
    public function deleted(WtBooking $wtBooking): void
    {
        //
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
