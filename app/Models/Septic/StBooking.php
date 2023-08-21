<?php

namespace App\Models\Septic;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StBooking extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Store meta Request in database 
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'citizen_id' => $req->citizenId,
            'applicant_name' => $req->applicantName,
            'mobile' => $req->mobile,
            'email' => $req->email,
            'address' => $req->address,
            'booking_date' => Carbon::now()->format('Y-m-d'),
            'cleaning_date' => $req->cleaningDate,
            'ward_id' => $req->wardId,
            'capacity_id' => $req->capacityId,
            'distance' => $req->distance,
            'road_width' => $req->roadWidth,
            'booking_no' => $req->bookingNo,
            'payment_amount' => $req->paymentAmount,
            'holding_no' => $req->holdingNo,
            'location_id' => $req->locationId,
        ];
    }

    /**
     * | Store Booking Request in Model
     */
    public function storeBooking($req)
    {
        $metaRequest = $this->metaReqs($req);
        $res = StBooking::create($metaRequest);
        $returnData['applicationId'] = $res->id;
        $returnData['bookingNo'] = $req->bookingNo;
        return $returnData;
    }

    /**
     * | Get Booking List
     */
    public function getBookingList()
    {
        return DB::table('st_bookings as stb')
                    ->leftjoin('st_drivers as sd','sd.id','=','stb.driver_id')
                    ->leftjoin('st_resources as sr','sr.id','=','stb.vehicle_id')
                    ->leftjoin('st_capacities as sc','sc.id','=','stb.capacity_id')
                    ->select('stb.*','wtl.location','sd.driver_name','sr.vehicle_no','sc.capacity')
                    ->join('wt_locations as wtl','wtl.id','=','stb.location_id');
    }

    /**
     * | Get Application Details By Id
     */
    public function getApplicationDetailsById($id){
        return StBooking::select('*')->where('id',$id)->first();
    }

    /**
     * | Get Payment Details By Payment Id After Payments
     */
    public function getPaymentDetails($payId)
    {
        $details = DB::table('st_bookings as sb')
            ->join('st_capacities as sc', 'sb.capacity_id', '=', 'sc.id')
            ->select('sb.*', 'sc.capacity')
            ->where('sb.payment_id', $payId)
            ->first();

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Septic Tanker";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }
}
