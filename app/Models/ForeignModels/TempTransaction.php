<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TempTransaction extends ParamModel
{
    use HasFactory;

    public function tempTransaction($req)
    {
        $mTempTransaction = new TempTransaction();
        return $mTempTransaction->create($req)->id;
    }

    public function transactionDtl($date, $ulbId)
    {
        return TempTransaction::select('temp_transactions.*', 'users.name', 'ulb_ward_masters.id as ward_id')
            ->join('users', 'users.id', 'temp_transactions.user_id')
            ->leftjoin("ulb_ward_masters", DB::raw("CAST (ulb_ward_masters.ward_name AS VARCHAR)"), "temp_transactions.ward_no")
            ->where('tran_date', $date)
            ->where('temp_transactions.status', 1)
            ->where('temp_transactions.ulb_id', $ulbId)
            ->orderByDesc('temp_transactions.id');
    }

    public function transactionList($date, $userId, $ulbId)
    {
        return TempTransaction::select(
            'temp_transactions.id',
            'transaction_no as tran_no',
            'payment_mode',
            'cheque_dd_no',
            'bank_name',
            'amount',
            'module_id',
            'ward_no as ward_name',
            'application_no',
            DB::raw("TO_CHAR(tran_date, 'DD-MM-YYYY') as tran_date"),
            'name as user_name',
            'users.id as user_id'
        )
            ->join('users', 'users.id', 'temp_transactions.user_id')
            ->where('payment_mode', '!=', 'ONLINE')
            ->where('tran_date', $date)
            ->where('temp_transactions.status', 1)
            ->where('user_id', $userId)
            ->where('temp_transactions.ulb_id', $ulbId)
            ->get();
    }



    
}
