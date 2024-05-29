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
        return $this->hasOne(WtChequeDtl::class,"tran_id","id")->orderBy("id","DESC")->first();
    }

    public function getBooking()
    {
        return $this->belongsTo(WtBooking::class,"booking_id","id")->first();
    }
    public function transactionDtl($date)
    {
        return self::select("wt_transactions.*","users.name")
        ->join("users","users.id","wt_transactions.emp_dtl_id")
        ->where("wt_transactions.tran_date",$date)
        ->where("wt_transactions.status",1);
        //->whereIn("wt_transactions.status",[1,2]);
    }

    public function transactionList($date, $userId, $ulbId)
    {
        return self::select("wt_transactions.*","users.name","app.booking_no")
        ->join("users","users.id","wt_transactions.emp_dtl_id")
        ->join(DB::raw("(
            (select id,booking_no from wt_bookings)
            union(select id,booking_no from wt_cancellations)
            )app"),"app.id","wt_transactions.booking_id")
        ->where("wt_transactions.tran_date",Carbon::parse($date)->format("Y-m-d"))
        ->where("wt_transactions.emp_dtl_id",$userId)
        ->where("wt_transactions.ulb_id",$ulbId)
        ->whereIn("wt_transactions.status",[1,2])->get();
    }
}
