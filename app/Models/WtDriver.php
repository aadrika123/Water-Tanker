<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtDriver extends Model
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
            'agency_id' => $req->agencyId,
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
        return DB::table('wt_drivers as wd')
            ->leftjoin('wt_agencies as wa', 'wd.agency_id', '=', 'wa.id')
            ->select('wd.*', 'wa.agency_name')
            ->orderBy('id','desc')
            ->get();
    }

    /**
     * | Get Driver List For Master Data
     */
    public function getDriverListForMasterData($ulbId)
    {
        return WtDriver::select('id', 'driver_name','agency_id')->where('ulb_id',$ulbId)
            ->get();
    }

    /**
     * | Get Driver Detaails By Id
     */
    public function getDriverDetailsById($id)
    {
        return DB::table('wt_drivers as wd')
            ->leftjoin('wt_agencies as wa', 'wd.agency_id', '=', 'wa.id')
            ->select('wd.*', 'wa.agency_name')
            ->where('wd.id', '=', $id)
            ->first();
    }
}
