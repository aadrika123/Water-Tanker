<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WtAgencyResource extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Make meta request for store resource information
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id'=>$req->ulbId,
            'agency_id'=>$req->agencyId,
            'vehicle_name'=>$req->vehicleName,
            'vehicle_no'=>$req->vehicleNo,
            'capacity_id'=>$req->capacityId,
            'resource_type'=>$req->resourceType,
            'is_ulb_resource'=>$req->isUlbResource,
        ];
    }

    /**
     * | Store Resource Information in Model
     */
    public function storeResourceInfo($req)
    {
        $metaReqs = $this->metaReqs($req);
        return self::create($metaReqs);
    }
}
