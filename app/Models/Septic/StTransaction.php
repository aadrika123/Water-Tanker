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
        return $this->hasOne(StChequeDtl::class, "tran_id", "id")->orderBy("id", "DESC")->first();
    }

    public function transactionDtl($date)
    {
        return self::select("st_transactions.*", "users.name")
            ->join("users", "users.id", "st_transactions.emp_dtl_id")
            ->where("st_transactions.tran_date", Carbon::parse($date)->format("Y-m-d"))
            // ->whereIn("st_transactions.status",[1,2]);
            ->where("st_transactions.status", 1);
    }

    public function transactionList($date, $userId, $ulbId)
    {
        return self::select("st_transactions.*", "users.name", "app.booking_no")
            ->join("users", "users.id", "st_transactions.emp_dtl_id")
            ->join(DB::raw("(
            (select id,booking_no from st_bookings)
            union(select id,booking_no from st_cancelled_bookings)
            )app"), "app.id", "st_transactions.booking_id")
            ->where("st_transactions.tran_date", Carbon::parse($date)->format("Y-m-d"))
            ->where("st_transactions.emp_dtl_id", $userId)
            ->where("st_transactions.ulb_id", $ulbId)
            ->whereIn("st_transactions.status", [1, 2])->get();
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
            ->join('st_bookings', 'st_bookings.id', '=', 't.booking_id')
            ->where('t.tran_no', $tranNo)
            ->where('t.is_verified', false)
            ->where('t.status', 1)
            ->where('st_bookings.is_vehicle_sent', [0, 1])
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
        return self::select("st_transactions.tran_no", "st_transactions.tran_type", "st_transactions.tran_date", "st_transactions.payment_mode", "stank_transaction_deactivate_dtls.deactive_date", "stank_transaction_deactivate_dtls.reason", "st_transactions.paid_amount", "st_bookings.booking_no", "users.name as deactivated_by")
            ->join('stank_transaction_deactivate_dtls', 'stank_transaction_deactivate_dtls.tran_id', '=', 'st_transactions.id')
            ->join('st_bookings', 'st_bookings.id', '=', 'st_transactions.booking_id')
            ->join('users', 'users.id', '=', 'stank_transaction_deactivate_dtls.deactivated_by')
            ->where("st_transactions.status", 0);
        //->get();
    }


    public function Tran($fromDate, $toDate)
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
                'st_bookings.id as booking_id',
                'st_bookings.applicant_name',
                DB::raw("CASE WHEN t.tran_type = 'Water Tanker Booking' THEN 11 ELSE 16 END AS module_id")
            )
            ->join('st_bookings', 'st_bookings.id', '=', 't.booking_id')
            ->where('t.tran_date', '>=', $fromDate)
            ->where('t.tran_date', '<=', $toDate)
            ->where("t.status", 1);
    }

    public function dailyCollection($fromDate, $toDate, $wardNo = null, $paymentMode = null, $applicationMode = null,$perPage,$ulbId)
    {
        $query = DB::table('st_transactions as t')
            ->select(
                't.tran_no as transaction_no',
                't.paid_amount',
                't.payment_mode',
                DB::raw("TO_CHAR(t.tran_date, 'DD-MM-YYYY') as tran_date"),
                //'t.tran_date',
                't.tran_type as module_name',
                't.status',
                'st_bookings.booking_no',
                'ulb_ward_masters.ward_name AS ward_id',
                'st_bookings.applicant_name',
                'st_bookings.user_type',
                'users.name as collected_by'
            )
            ->join('st_bookings', 'st_bookings.id', '=', 't.booking_id')
            ->JOIN("ulb_ward_masters", "ulb_ward_masters.id",  '=',"st_bookings.ward_id")
            ->leftjoin('users', 'users.id', '=', 'st_bookings.user_id')
            ->where('t.tran_date', '>=', $fromDate)
            ->where('t.tran_date', '<=', $toDate)
            ->where('t.ulb_id', $ulbId)
            ->where("t.status", 1);

        if ($wardNo) {
            $query->where('st_bookings.ward_id', $wardNo);
        }
        if ($paymentMode) {
            $query->where('t.payment_mode', $paymentMode);
        }
        if ($applicationMode) {
            if ($applicationMode == 'JSK') {
                $query->where('st_bookings.user_type', 'JSK');
            } else {
                $query->where('st_bookings.user_type', 'Citizen');
            }
        }
        $summaryQuery = clone $query;
        $transactions = $query->paginate($perPage);
        $collectAmount = $summaryQuery->sum('t.paid_amount');
        $totalTransactions = $summaryQuery->count();
        $cashSummaryQuery = clone $summaryQuery;
        $cashSummaryQuery->where('t.payment_mode', 'CASH');
        $cashAmount = $cashSummaryQuery->sum('t.paid_amount');
        $cashCount = $cashSummaryQuery->count();

        $onlineSummaryQuery = clone $summaryQuery;
        $onlineSummaryQuery->where('t.payment_mode', 'ONLINE');
        $onlineAmount = $onlineSummaryQuery->sum('t.paid_amount');
        $onlineCount = $onlineSummaryQuery->count();

        // JSK cash collection
        $jskCashCollection = clone $summaryQuery;
        $jskCashCollection->where('t.payment_mode', 'CASH')->whereNotNull('t.emp_dtl_id');
        $jskCashAmount = $jskCashCollection->sum('t.paid_amount');
        $jskCashCount = $jskCashCollection->count();

        // JSK online collection
        $jskOnlineCollection = clone $summaryQuery;
        $jskOnlineCollection->where('t.payment_mode', 'ONLINE')->whereNotNull('t.emp_dtl_id');
        $jskOnlineAmount = $jskOnlineCollection->sum('t.paid_amount');
        $jskOnlineCount = $jskOnlineCollection->count();

        // Citizen cash collection
        $citizenCashCollection = clone $summaryQuery;
        $citizenCashCollection->where('t.payment_mode', 'CASH')->whereNotNull('t.citizen_id');
        $citizenCashAmount = $citizenCashCollection->sum('t.paid_amount');
        $citizenCashCount = $citizenCashCollection->count();

        // Citizen online collection
        $citizenOnlineCollection = clone $summaryQuery;
        $citizenOnlineCollection->where('t.payment_mode', 'ONLINE')->whereNotNull('t.citizen_id');
        $citizenOnlineAmount = $citizenOnlineCollection->sum('t.paid_amount');
        $citizenOnlineCount = $citizenOnlineCollection->count();
        $totaljskCount =  $jskCashCount +$jskOnlineCount;
        $totalCitizenCount =  $citizenCashCount +$citizenOnlineCount;
    
        return [
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'data' => $transactions->items(),
            'total' => $transactions->total(),
            'collectAmount' => $collectAmount,
            'totalTransactions' => $totalTransactions,
            'cashCollection' => $cashAmount,
            'cashTranCount' => $cashCount,
            'onlineCollection' => $onlineAmount,
            'onlineTranCount' => $onlineCount,
            'jskCashCollectionAmount' => $jskCashAmount,
            'jskCashCollectionCount' => $jskCashCount,
            'jskOnlineCollectionAmount' => $jskOnlineAmount,
            'jskOnlineCollectionCount' => $jskOnlineCount,
            'citizenCashCollectionAmount' => $citizenCashAmount,
            'citizenCashCollectionCount' => $citizenCashCount,
            'citizenOnlineCollectionAmount' => $citizenOnlineAmount,
            'citizenOnlineCollectionCount' => $citizenOnlineCount,
            'totalJskCount' =>$totaljskCount,
            'totalCitizenCount' =>$totalCitizenCount
        ];
    }
}
