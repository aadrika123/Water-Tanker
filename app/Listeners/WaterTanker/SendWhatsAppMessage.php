<?php

namespace App\Listeners\WaterTanker;

use App\Events\Watertanker\EventWaterTanker;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class SendWhatsAppMessage
{
    /**
     * Create the event listener.
     */
    private $booking;
    private $eventName;
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EventWaterTanker $event): void
    {
        $this->booking = $booking = $event->booking;
        $this->eventName = $eventName = $event->eventName;
        try{
            switch($eventName){
                case "created" : $whatsapp=$this->created();
                                break;

                case "updated" : $whatsapp=$this->updated();
                            break;
                case "updating" : $whatsapp=$this->updating();
                            break;
                case "deleted" : $whatsapp=$this->deleted();
                            break;
            }
            
        }
        catch(Exception $e)
        {
            $eerrpr = $e->getMessage();
        }
        
    }

    private function created()
    {
        return (
            Whatsapp_Send( $this->booking->mobile,"trn_2_var",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name."-- Record Create"),$this->booking->booking_no,$this->booking->booking_no
                        ]
                    ])
            );
    }

    private function updated()
    {
        if($this->booking->isDirty('driver_id'))
        {
            return (Whatsapp_Send( $this->booking->mobile,"trn_2_var",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name."-- Driver Asing"),$this->booking->booking_no,$this->booking->booking_no
                        ]
                    ]));
        } 

        if($this->booking->isDirty('is_vehicle_sent') && $this->booking->is_vehicle_sent==2)
        {
            return (Whatsapp_Send( $this->booking->mobile,"trn_2_var",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name."-- success"),$this->booking->booking_no,$this->booking->booking_no
                        ]
                    ]));
        }
        
        if($this->booking->isDirty('payment_status') && $this->booking->payment_status==1)
        {
            return (Whatsapp_Send( $this->booking->mobile,"trn_2_var",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name."-- payment_status"),$this->booking->booking_no,$this->booking->booking_no
                        ]
                    ]));
        }
    }

    private function updating()
    {
        
        
    }


    private function deleted()
    {
        return (Whatsapp_Send( $this->booking->mobile,"trn_2_var",
                ["content_type"=>"text",
                    [
                        Str::title($this->booking->applicant_name."-- Deleted"),$this->booking->booking_no,$this->booking->booking_no
                    ]
                ]));
        
    }




}
