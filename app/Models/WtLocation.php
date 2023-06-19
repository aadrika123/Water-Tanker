<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function listLocation(){
        return self::select('id','ulb_id','location','date')->where('status','1')->orderby('id','desc')->get();
    }

    /**
     * | Get Location Details By Id
     */
    public function getLocationDetailsById($id){
        return self::select('id','ulb_id','location','date')->where(['status'=>'1','id'=>$id])->first();
    }

    /**
     * | Get Locations list For Master Data
     */
    public function getLocationForMasterData(){

    }
}
