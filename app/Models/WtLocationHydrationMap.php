<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WtLocationHydrationMap extends Model
{
    use HasFactory;

    protected $guarded = [];


    /**
     * | Make MetaRequest For Store location & Hydaration Map Details in Database
     */
    public function metaReqs($req)
    {
        return [
            'ulb_id' => $req->ulbId,
            'location_id' => $req->locationId,
            'hydration_center_id' => $req->hydrationCenterId,
            'distance' => $req->distance,
            'rank' => $req->rank,
            'date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * | Store Location Hydration Map in Database
     */
    public function storeLocationHydrationMap($req)
    {
        $metaReqs = $this->metaReqs($req);
        return self::create($metaReqs);
    }

    /**
     * | Store Location Hydration Map in Database
     */
    public function listLocationHydrationMap()
    {
        return DB::table('wt_location_hydration_maps as wlhm')
            ->join('wt_hydration_centers as whc', 'whc.id', '=', 'wlhm.hydration_center_id')
            ->join('wt_locations as wl', 'wl.id', '=', 'wlhm.location_id')
            ->select('wlhm.*', 'whc.name as hydration_center_name', 'wl.location')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * | Get Location Hydration Map Details By Id
     */
    public function getLocationHydrationMapDetailsById($id)
    {
        return DB::table('wt_location_hydration_maps as wlhm')
            ->join('wt_hydration_centers as whc', 'whc.id', '=', 'wlhm.hydration_center_id')
            ->join('wt_locations as wl', 'wl.id', '=', 'wlhm.location_id')
            ->select('wlhm.*', 'whc.name as hydration_center_name', 'wl.location')
            ->where('wlhm.id', '=', $id)
            ->first();
    }

    /**
     * | Get Hydration Center of selected location rank wise
     */
    public function getLocationMapList($location)
    {
        $list = WtLocationHydrationMap::select('*')->where('location_id', $location)->orderBy('rank', 'asc')->get();
        return $list;
    }

    public function listLocation($ulbId)
    {
        $list = DB::table('wt_location_hydration_maps as wlhm')
            ->join('wt_locations as wl', 'wl.id', '=', 'wlhm.location_id')
            ->where('wlhm.ulb_id', $ulbId)
            ->select('wlhm.location_id as id', 'wl.location')
            ->distinct()
            ->get();
        return $list;
    }
}
