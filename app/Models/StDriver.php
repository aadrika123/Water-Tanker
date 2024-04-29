<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StDriver extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Make Metarequest of driver Information for store data 
     */
    public function metaReqst($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            "u_id"=>$req->UId,
            'driver_name' => $req->driverName,
            'driver_aadhar_no' => $req->driverAadharNo,
            'driver_mobile' => $req->driverMobile,
            'driver_address' => $req->driverAddress,
            'driver_father' => $req->driverFather,
            'driver_dob' => $req->driverDob,
            'driver_license_no' => $req->driverLicenseNo,
            'date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store Driver information in Database
     */
    public function storeDriverInfo($req)
    {
        $metaReqst = $this->metaReqst($req);
        return self::create($metaReqst);
    }

    /**
     * | Get Driver List
     */
    public function getDriverList()
    {
        return DB::table('st_drivers')
            ->select('*')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * | Get Driver Detaails By Id
     */
    public function getDriverDetailsById($id)
    {
        return DB::table('st_drivers')
            ->select('*')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * | Get Driver List For assign Booking
     */
    public function getDriverListForAssign(){
        return DB::table('st_drivers as sd')
        ->select('sd.id','sd.ulb_id','sd.driver_name','sd.driver_mobile')
        ->orderBy('id', 'desc')
        ->get();
    }

    public function getDriverListForMasterData($ulbId)
    {
        return self::select('id', 'driver_name')->where('ulb_id',$ulbId)
            ->get();
    }
}
