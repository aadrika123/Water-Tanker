<?php

namespace App\Listeners\SepticTanker;

use App\Events\SepticTanker\EventSepticTanker;
use App\Models\ForeignModels\UlbMaster;
use App\Models\StDriver;
use App\Models\StResource;
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
    private $ulbDtls;
    private $drives;
    private $vehicle;
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EventSepticTanker $event): void
    {
        $this->booking = $booking = $event->booking;
        $this->eventName = $eventName = $event->eventName;
        $this->ulbDtls = UlbMaster::find($this->booking->ulb_id);
        $this->drives = StDriver::find($this->booking->driver_id);
        $this->vehicle = StResource::find($this->booking->vehicle_id);
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
        // return (
        //     Whatsapp_Send( $this->booking->mobile,"trn_2_var",
        //             ["content_type"=>"text",
        //                 [
        //                     Str::title($this->booking->applicant_name."-- Record Create"),$this->booking->booking_no,$this->booking->booking_no
        //                 ]
        //             ])
        //     );
    }

    private function updated()
    {
        if($this->booking->isDirty('driver_id') && $this->booking->driver_id)
        {
            return (Whatsapp_Send( $this->booking->mobile,"wt_driver_assignment",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name),Str::title($this->drives->driver_name),$this->drives->driver_mobile,"Septic Cleaning",$this->booking->booking_no
                        ]
                    ]));
        } 
        
        
        if($this->booking->isDirty('payment_status') && in_array($this->booking->payment_status,[1,2]))
        {
            return (Whatsapp_Send( $this->booking->mobile,"wt_booking_initiated",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name),$this->booking->payment_amount,"Septic Cleaning",$this->booking->booking_no,($this->ulbDtls->toll_free_no)
                        ]
                    ]));
        }
        if($this->booking->isDirty('is_vehicle_sent') && $this->booking->is_vehicle_sent==2)
        {
            return (Whatsapp_Send( $this->booking->mobile,"wt_successful_delivery",
                    ["content_type"=>"text",
                        [
                            Str::title($this->booking->applicant_name),$this->booking->booking_no,"Trip",$this->ulbDtls->current_website
                        ]
                    ]));
        }
    }

    private function updating()
    {
        
        
    }


    private function deleted()
    {
        // return (Whatsapp_Send( $this->booking->mobile,"trn_2_var",
        //         ["content_type"=>"text",
        //             [
        //                 Str::title($this->booking->applicant_name."-- Deleted"),$this->booking->booking_no,$this->booking->booking_no
        //             ]
        //         ]));
        
    }
}
