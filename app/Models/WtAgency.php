<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtAgency extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Make metaRequest for store Agency Data 
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'agency_name' => $req->agencyName,
            'owner_name' => $req->ownerName,
            'agency_address' => $req->agencyAddress,
            'agency_ward_id' => $req->agencyWardId,
            'agency_mobile' => $req->agencyMobile,
            'agency_email' => $req->agencyEmail,
            'dispatch_capacity' => $req->dispatchCapacity,
            'date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store Agency Request in model
     */
    public function storeAgency($req)
    {
        $metaReqs = $this->metaReqs($req);
        return WtAgency::create($metaReqs);
    }

    /**
     * | Get Agency List
     */
    public function getAllAgency()
    {
        return self::select("*")
            // ->orderBy('ulb_id')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * | Get Agency Details By Id
     */
    public function getAgencyById($id)
    {
        return self::select("*")
            ->where('id', $id)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getAllAgencyForMasterData(){
        return self::select("id","agency_name")
            // ->orderBy('ulb_id')
            ->orderBy('id', 'desc')
            ->get();
    }
}
