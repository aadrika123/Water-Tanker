<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class WtTransaction extends Model
{
    use HasFactory;

    public function getChequeDtls()
    {
        return $this->hasOne(WtChequeDtl::class, "tran_id", "id")->orderBy("id", "DESC")->first();
    }

    public function getBooking()
    {
        return $this->belongsTo(WtBooking::class, "booking_id", "id")->first();
    }
    public function transactionDtl($date)
    {
        return self::select("wt_transactions.*", "users.name")
            ->join("users", "users.id", "wt_transactions.emp_dtl_id")
            ->where("wt_transactions.tran_date", $date)
            ->where("wt_transactions.status", 1);
        //->whereIn("wt_transactions.status",[1,2]);
    }

    public function transactionList($date, $userId, $ulbId)
    {
        return self::select("wt_transactions.*", "users.name", "app.booking_no")
            ->join("users", "users.id", "wt_transactions.emp_dtl_id")
            ->join(DB::raw("(
            (select id,booking_no from wt_bookings)
            union(select id,booking_no from wt_cancellations)
            )app"), "app.id", "wt_transactions.booking_id")
            ->where("wt_transactions.tran_date", Carbon::parse($date)->format("Y-m-d"))
            ->where("wt_transactions.emp_dtl_id", $userId)
            ->where("wt_transactions.ulb_id", $ulbId)
            ->whereIn("wt_transactions.status", [1, 2])->get();
    }

    public function getTransByTranNo($tranNo)
    {
        return DB::table('wt_transactions as t')
            ->select(
                't.id as transaction_id',
                't.tran_no as transaction_no',
                't.paid_amount',
                't.payment_mode',
                't.tran_date',
                't.tran_type as module_name',
                't.status',
                'wt_bookings.booking_no',
                DB::raw("CASE WHEN t.tran_type = 'Water Tanker Booking' THEN 11 ELSE 16 END AS module_id")
            )
            ->join('wt_bookings', 'wt_bookings.id', '=', 't.booking_id')
            ->where('t.tran_no', $tranNo)
            ->where('t.is_verified', false)
            ->where('t.status', 1)
            ->where('wt_bookings.is_vehicle_sent',[0,1])
            ->get();
    }

    public function deactivateTransaction($transactionId)
    {

        WtTransaction::where('id', $transactionId)
            ->update(
                [
                    'status' => 0,
                ]
            );
    }

    public function getDeactivatedTran()
    {
        return self::select("wt_transactions.tran_no", "wt_transactions.tran_type", "wt_transactions.tran_date", "wt_transactions.payment_mode", "wtank_transaction_deactivate_dtls.deactive_date", "wtank_transaction_deactivate_dtls.reason", "wt_transactions.paid_amount", "wt_bookings.booking_no", "users.name as deactivated_by")
            ->join('wtank_transaction_deactivate_dtls', 'wtank_transaction_deactivate_dtls.tran_id', '=', 'wt_transactions.id')
            ->join('wt_bookings', 'wt_bookings.id', '=', 'wt_transactions.booking_id')
            ->join('users', 'users.id', '=', 'wtank_transaction_deactivate_dtls.deactivated_by')
            ->where("wt_transactions.status", 0);
        //->get();
    }

    public function Tran($fromDate, $toDate)
    {
        return DB::table('wt_transactions as t')
            ->select(
                't.id as transaction_id',
                't.tran_no as transaction_no',
                't.paid_amount',
                't.payment_mode',
                't.tran_date',
                't.tran_type as module_name',
                't.status',
                'wt_bookings.booking_no',
                'wt_bookings.id as booking_id',
                DB::raw("CASE WHEN t.tran_type = 'Water Tanker Booking' THEN 11 ELSE 16 END AS module_id")
            )
            ->join('wt_bookings', 'wt_bookings.id', '=', 't.booking_id')
            ->where('t.tran_date', '>=', $fromDate)
            ->where('t.tran_date', '<=', $toDate)
            ->where("t.status", 1);
    }
}
