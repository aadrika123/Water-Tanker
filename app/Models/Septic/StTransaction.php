<?php

namespace App\Models\Septic;

use App\Models\WtChequeDtl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class StTransaction extends Model
{
    use HasFactory;

    public function getChequeDtls()
    {
        return $this->hasOne(StChequeDtl::class,"tran_id","id")->orderBy("id","DESC")->first();
    }

    public function transactionDtl($date)
    {
        return self::select("st_transactions.*","users.name")
        ->join("users","users.id","st_transactions.emp_dtl_id")
        ->where("st_transactions.tran_date",Carbon::parse($date)->format("Y-m-d"))
        // ->whereIn("st_transactions.status",[1,2]);
        ->where("st_transactions.status",1);
    }

    public function transactionList($date, $userId, $ulbId)
    {
        return self::select("st_transactions.*","users.name","app.booking_no")
        ->join("users","users.id","st_transactions.emp_dtl_id")
        ->join(DB::raw("(
            (select id,booking_no from st_bookings)
            union(select id,booking_no from st_cancelled_bookings)
            )app"),"app.id","st_transactions.booking_id")
        ->where("st_transactions.tran_date",Carbon::parse($date)->format("Y-m-d"))
        ->where("st_transactions.emp_dtl_id",$userId)
        ->where("st_transactions.ulb_id",$ulbId)
        ->whereIn("st_transactions.status",[1,2])->get();
    }

    public function getTransByTranNo($tranNo)
    {
        return DB::table('st_transactions as t')
            ->select(
                't.id as transaction_id',
                't.tran_no as transaction_no',
                't.paid_amount',
                't.payment_mode',
                't.tran_date',
                't.tran_type as module_name',
                't.status',
                'st_bookings.booking_no',
                DB::raw("CASE WHEN t.tran_type = 'Water Tanker Booking' THEN 11 ELSE 16 END AS module_id")
            )
            ->join('st_bookings','st_bookings.id','=','t.booking_id')
            ->where('t.tran_no', $tranNo)
            ->where('t.is_verified', false)
            ->where('t.status', 1)
            ->get();
    }

    public function deactivateTransaction($transactionId)
    {

        StTransaction::where('id', $transactionId)
                        ->update(
                            [
                                'status' => 0,
                            ]
                        );
    }

    public function getDeactivatedTran()
    {
       return self::select("st_transactions.tran_no","st_transactions.tran_type","st_transactions.tran_date","st_transactions.payment_mode","stank_transaction_deactivate_dtls.deactive_date","stank_transaction_deactivate_dtls.reason","st_transactions.paid_amount","st_bookings.booking_no","users.name as deactivated_by")
        ->join('stank_transaction_deactivate_dtls','stank_transaction_deactivate_dtls.tran_id','=','st_transactions.id')
        ->join('st_bookings','st_bookings.id','=','st_transactions.booking_id')
        ->join('users','users.id','=','stank_transaction_deactivate_dtls.deactivated_by')
        ->where("st_transactions.status",0);
        //->get();
    }
}
