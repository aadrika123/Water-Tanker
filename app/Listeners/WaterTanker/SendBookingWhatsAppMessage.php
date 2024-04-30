<?php

namespace App\Listeners\WaterTanker;

use App\Events\Watertanker\EventWaterTankerBooked;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class SendBookingWhatsAppMessage
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EventWaterTankerBooked $event): void
    {
        $booking = $event->booking;
        try{
            $whatsapp2=(Whatsapp_Send( $booking->mobile,"trn_2_var",
                ["content_type"=>"text",
                    [
                        Str::title($booking->applicant_name),$booking->booking_no,$booking->booking_no
                    ]
                ]));
        }
        catch(Exception $e)
        {

        }
        
    }
}
