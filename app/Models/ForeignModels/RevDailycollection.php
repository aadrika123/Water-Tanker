<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

class RevDailycollection extends ParamModel
{

    /**
     * |
     */
    public function store($req)
    {
        $req = $req->toarray();
        $revDailyCollections =  RevDailycollection::create($req);
        return $revDailyCollections->id;
    }

    /**
     * | collection Dtl
     */
    public function collectionDetails($ulbId)
    {
        return RevDailycollection::select(
            'rev_dailycollections.id',
            'ulb_name',
            'u.name as tc_name',
            'u.mobile as tc_mobile',
            'uu.name as verifier_name',
            'uu.mobile as verifier_mobile',
            'tran_no',
            'deposit_amount as amount',
            'module_id',
            DB::raw("TO_CHAR(demand_date, 'DD-MM-YYYY') as tran_date"),
            DB::raw("TO_CHAR(deposit_date, 'DD-MM-YYYY') as verification_date"),
            'deposit_mode as payment_mode',
            'cheq_dd_no as cheque_dd_no',
            'bank_name',
            'application_no'
        )
            ->join('rev_dailycollectiondetails as rdc', 'rdc.collection_id', 'rev_dailycollections.id')
            ->join('users as u', 'u.id', 'rev_dailycollections.tc_id')
            ->join('users as uu', 'uu.id', 'rev_dailycollections.user_id')
            ->join('ulb_masters', 'ulb_masters.id', 'rev_dailycollections.ulb_id')
            ->where('rev_dailycollections.status', 1)
            ->where('rev_dailycollections.ulb_id', $ulbId);
    }
}
