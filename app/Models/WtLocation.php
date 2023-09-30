<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtLocation extends Model
{
    use HasFactory;
    protected $guarded=[]; 

    /**
     * | Make MetaRequest For Store location Details in Database
     */
    public function metaReqs($req){
        return [
            'ulb_id'=>$req->ulbId,
            'location'=>$req->location,
            'is_in_ulb'=>$req->isInUlb,
            'date'=>Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store Location in Database
     */
    public function storelocation($req){
        $metaReqs=$this->metaReqs($req);
        return self::create($metaReqs);
    }

    /**
     * | Get List Of All Location
     */
    public function listLocation($ulbId){
        return self::select('id','ulb_id','location','date','is_in_ulb',
                    DB::raw("case when is_in_ulb = 1 then 'Within ULB' else 'Outside ULB' end as OutOrInside"))
                    ->where('status','1')
                    ->where('ulb_id',$ulbId)
                    ->orderby('id','desc')
                    ->get();
    }

    /**
     * | List Location For Septic Tanker
     */
    public function listLocationforSepticTank($ulbId,$isInUlb){
        return self::select('id','ulb_id','location','date','is_in_ulb')
                    ->where('status','1')
                    ->where('ulb_id',$ulbId)
                    ->where('is_in_ulb',$isInUlb)
                    ->orderby('id','desc')
                    ->get();
    }

    /**
     * | Get Location Details By Id
     */
    public function getLocationDetailsById($id){
        return self::select('id','ulb_id','location','date','is_in_ulb')->where(['status'=>'1','id'=>$id])->first();
    }
}
