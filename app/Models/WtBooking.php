<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtBooking extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'citizen_id' => $req->citizenId,
            'agency_id' => $req->agencyId,
            'booking_date' => Carbon::now()->format('Y-m-d'),
            'delivery_date' => $req->deliveryDate,
            'delivery_time' => $req->deliveryTime,
            'mobile' => $req->mobile,
            'email' => $req->email,
            'address' => $req->address,
            'ward_id' => $req->wardId,
            'capacity_id' => $req->capacityId,
            'quantity' => 1,
            'hydration_center_id' => $req->hydrationCenter,
            'applicant_name' => $req->applicantName,
            'booking_no' => $req->bookingNo,
            'payment_amount' => $req->paymentAmount,
            'booking_latitude' => $req->latitude??null,
            'booking_longitude' => $req->longitude??null,
            'user_id' => $req->userId,
            'user_type' => $req->userType,
        ];
    }

    /**
     * | Store Booking Request in Model
     */
    public function storeBooking($req)
    {
        $metaRequest = $this->metaReqs($req);
        $res = WtBooking::create($metaRequest);
        $returnData['applicationId'] = $res->id;
        $returnData['bookingNo'] = $req->bookingNo;
        return $returnData;
    }


    /**
     * | Get Booking List from Model
     */
    public function getBookingList()
    {
        return DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_drivers as dr', 'wb.driver_id', '=', 'dr.id')
            ->leftjoin('wt_resources as res', 'wb.vehicle_id', '=', 'res.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name',"dr.driver_name","res.vehicle_no")
            ->orderBy('wb.ulb_id');
    }

    /**
     * | Get Aggigning List of Tanker,Driver, Hydration Center
     */
    public function assignList()
    {
        return DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->join('wt_resources as wr', 'wr.id', '=', 'wb.vehicle_id')
            ->join('wt_drivers as wd', 'wd.id', '=', 'wb.driver_id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            // ->join('wt_driver_vehicle_maps as dvm', 'wb.vdm_id', '=', 'dvm.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name', 'wr.vehicle_name', 'wr.vehicle_no', 'wd.driver_name', 'wd.driver_mobile')
            ->where('assign_date', '!=', NULL);
    }


    /**
     * | Get Booking Details By Id
     */
    public function getBookingDetailById($id)
    {
        return $list = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'wa.agency_name', 'whc.name as hydration_center_name')
            ->where('wb.id', $id)
            ->first();
    }

    /**
     * | Get Payment Details By Application Id Before Payments
     */
    public function getPaymentDetailsById($id)
    {
        return $list = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->select('wb.booking_no', 'wb.applicant_name', 'wb.payment_amount', 'wb.id as applicationId', 'wb.mobile', 'wb.email', 'wc.capacity')
            ->where('wb.id', $id)
            ->first();
    }

    /**
     * | Get Today booking list
     */
    public function todayBookings($agencyId)
    {
        $todayDate = Carbon::now()->format('Y-m-d');
        return self::select('*')->where('delivery_date', $todayDate)
        ->where('agency_id', $agencyId);
    }


    /**
     * | Get Payment Reciept Details By Payment Id After Payments
     */
    public function getRecieptDetails($payId)
    {
       $details = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'whc.name as hydration_center_name')
            ->where('wb.id', $payId)
            // ->where('wb.payment_id', $payId)
            ->first();

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Water Tanker Booking";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }

    
    /**
     * | Get Payment Details By Payment Id After Payments
     */
    public function getPaymentDetails($payId)
    {
        $details = DB::table('wt_bookings as wb')
            ->join('wt_capacities as wc', 'wb.capacity_id', '=', 'wc.id')
            ->leftjoin('wt_agencies as wa', 'wb.agency_id', '=', 'wa.id')
            ->leftjoin('wt_hydration_centers as whc', 'wb.hydration_center_id', '=', 'whc.id')
            ->select('wb.*', 'wc.capacity', 'whc.name as hydration_center_name')
            ->where('wb.payment_id', $payId)
            ->first();

        $details->payment_details = json_decode($details->payment_details);
        $details->towards = "Water Tanker";
        $details->payment_date = Carbon::createFromFormat('Y-m-d', $details->payment_date)->format('d-m-Y');
        $details->booking_date = Carbon::createFromFormat('Y-m-d',  $details->booking_date)->format('d-m-Y');
        return $details;
    }

    /**
     * add function by sandeep bara
     */

    public function getReassignedBookingOrm()
    {
        return $this->hasMany(WtReassignBooking::class,"application_id","id");
    }
    public function getLastReassignedBooking()
    {
        return $this->getReassignedBookingOrm()->orderBy("id","DESC")->first();
    }

    public function getDeliveredDriver()
    {
        return $this->belongsTo(WtDriver::class,"delivered_by_driver_id","id")->first();
    }

    public function getAssignedVehicle()
    {
        return $this->hasOne(WtResource::class,"id","vehicle_id")->first();
    }
    public function getAssignedDriver()
    {
        return $this->hasOne(WtDriver::class,"id","driver_id")->first();
    }

    public function getAllTrans()
    {
        return $this->hasMany(WtTransaction::class,"booking_id","id")->whereIn("status",[1,2])->orderBy("tran_date","ASC")->orderBy("id","ASC")->get();
    }

}
