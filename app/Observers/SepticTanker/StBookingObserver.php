<?php

namespace App\Observers\SepticTanker;

use App\Events\SepticTanker\EventSepticTanker;
use App\Models\Septic\StBooking;

class StBookingObserver
{
    /**
     * Handle the StBooking "created" event.
     */
    public function created(StBooking $stBooking): void
    {
        if($stBooking->getTable()==(new StBooking())->getTable())
            event(new EventSepticTanker($stBooking,"created"));
    }

    /**
     * Handle the StBooking "updated" event.
     */
    public function updated(StBooking $stBooking): void
    {
        if ($stBooking->isDirty('driver_id')) 
        {
            event(new EventSepticTanker($stBooking,"updated"));
        }

        if ($stBooking->isDirty('is_vehicle_sent')) 
        {
            event(new EventSepticTanker($stBooking,"updated"));
        }
        if ($stBooking->isDirty('payment_status')) 
        {
            event(new EventSepticTanker($stBooking,"updated"));
        }
    }

    public function updating(StBooking $stBooking): void
    {
        // if ($stBooking->isDirty('driver_id')) 
        // {
        //     event(new EventSepticTanker($stBooking,"updating"));
        // }
    }

    /**
     * Handle the StBooking "deleted" event.
     */
    public function deleted(StBooking $stBooking): void
    {
        event(new EventSepticTanker($stBooking,"deleted"));
    }

    /**
     * Handle the StBooking "restored" event.
     */
    public function restored(StBooking $stBooking): void
    {
        //
    }

    /**
     * Handle the StBooking "force deleted" event.
     */
    public function forceDeleted(StBooking $stBooking): void
    {
        //
    }
}
