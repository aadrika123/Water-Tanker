<?php

namespace App\BLL;

use App\Models\Septic\StUlbCapacityRate;
use App\Models\StRate;
use App\Models\WtAgency;
use App\Models\WtBooking;
use App\Models\WtCapacity;
use App\Models\WtHydrationCenter;
use App\Models\WtLocationHydrationMap;
use App\Models\WtUlbCapacityRate;
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
    protected $_idGeneraionUrl;
    public function __construct()
    {
        $this->_baseUrl = Config::get('constants.BASE_URL');
        $this->_idGeneraionUrl = Config::get('constants.ID_GENERATE_URL');
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

    /**
     * | Find Hydration Center at the time of booking rankwise
     */
    public function findHydrationCenter($deliveryDate, $capacityId, $locationId)
    {
        $mWtLocationHydrationMap = new WtLocationHydrationMap();
        $locationMapList = $mWtLocationHydrationMap->getLocationMapList($locationId);
        $hc_id = 0;
        if (!empty($locationMapList)) {
            foreach ($locationMapList as $lml) {
                $waterCapacity = $this->getWaterCapacity($lml['hydration_center_id']);
                // echo $waterCapacity; die;
                $totalBookingOnDeliveryDate = $this->getTotalBooking($lml['id'], $deliveryDate);
                $currentBooking = WtCapacity::where('id', $capacityId)->first()->capacity;                     // find current book capacity
                $totalBooking = $totalBookingOnDeliveryDate + $currentBooking;                                                // Add total book and current book for find agency capacity
                if ($waterCapacity >= $totalBooking) {
                    $hc_id = $lml['hydration_center_id'];
                    break;
                }
            }
        }
        if ($hc_id > 0)
            return $hc_id;
        else
            return false;
    }

    /**
     * | Get Water Capacity by Hydration Center Id
     */
    public function getWaterCapacity($hydrationCenterId)
    {
        return WtHydrationCenter::select('water_capacity')->where('id', $hydrationCenterId)->first()->water_capacity;
    }

    /**
     * | Get Total booking of Hydration Center On prticular Delivery Date
     */
    public function getTotalBooking($hydrationCenterId, $deliveryDate)
    {
        $list = WtBooking::select('hydration_center_id', 'delivery_date', 'capacity_id', 'quantity')             // get booking list of delivery date
            ->where(['hydration_center_id' => $hydrationCenterId, 'delivery_date' => $deliveryDate])
            ->get();
        $booked = collect();

        $multiplied = $list->map(function ($item) use ($booked) {                                      // Map book list for calculate total booked capacity
            $capacity = WtCapacity::where('id', $item->capacity_id)->first()->capacity;
            $booked->push($capacity * $item->quantity);                                                // Push on collection capacity multiplied by quantity
        });
        $totalBooked = $booked->sum();
        if ($totalBooked > 0)
            return $totalBooked;
        else
            return 0;
    }

    /**
     * | Id Generate function
     */
    public function generateId($paramId, $ulbId)
    {
        // Generate Application No
        $reqData = [
            "paramId" => $paramId,
            'ulbId' => $ulbId
        ];
        $refResponse = Http::post($this->_idGeneraionUrl . 'api/id-generator', $reqData);
        $idGenerateData = json_decode($refResponse);
        return $idGenerateData->data;
    }

    /**
     * | Get Payment Amount of application
     */
    public function getAmount($ulb, $capacityId)
    {
        return WtUlbCapacityRate::select('rate')->where('ulb_id', $ulb)->where("status",1)->where('capacity_id', $capacityId)->first()->rate;
    }

    /**
     * | Get Septic Tank Amount regaurding ulbId, CapacityId, isResidential or Commercial
     */
    public function getSepticTankAmount($ulbId, $ulbArea, $buildingType)
    {
        // $mStUlbCapacityRate=new StUlbCapacityRate();
        // $septicAmount=$mStUlbCapacityRate->getAmount($ulbId, $capacityId, $isResidential);
        $mStRate=new StRate();
        $septicAmount=$mStRate->getAmount($ulbId, $ulbArea, $buildingType);
        return $septicAmount;
    }

    /**
     * | Get Agency Id FOr ULB
     */
    public function getAgency($ulbId){
        return WtAgency::select('id')->where('ulb_id',$ulbId)->first()->id;
    }
}
