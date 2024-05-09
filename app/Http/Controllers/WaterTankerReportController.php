<?php

namespace App\Http\Controllers;

use App\Models\WtBooking;
use App\Models\WtTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WaterTankerReportController extends Controller
{
    /**
     * =========water tanker reports===============
     *          created by : sandeep bara
     *          date       : 2024-05-08
     */

    public function __construct()
    {
        
    }

    public function collationReports(Request $request)
    {
        $user = Auth()->user();
        $userJoin = "LEFTJOIN";
        $userId = $paymentMode = null;
        $ulbId = $user->ulb_id;
        $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
        if ($request->userJoin) {
            $userJoin = $request->userJoin;
        }
        if ($request->fromDate)
        {
            $fromDate = $request->fromDate;
        }
        if ($request->uptoDate)
        {
            $uptoDate = $request->uptoDate;
        }
        if ($request->userId)
        {
            $userId = $request->userId;
        }
        if ($request->ulbId)
        {
            $ulbId = $request->ulbId;
        }
        if ($request->paymentMode)
        {
            $paymentMode = $request->paymentMode;
        }
        try{
            $data = WtTransaction::select("wt_transactions.id","wt_transactions.booking_id",
                            "wt_transactions.payment_mode",
                            "wt_transactions.paid_amount",
                            "wt_transactions.tran_no",
                            "wt_cheque_dtls.cheque_no",
                            "wt_cheque_dtls.cheque_date",
                            "wt_cheque_dtls.bank_name",
                            "wt_cheque_dtls.branch_name",
                            "users.name"
                        )
                    ->leftJoin("wt_cheque_dtls","wt_cheque_dtls.tran_id","wt_transactions.id")
                    ->$userJoin("users","users.id","wt_transactions.emp_dtl_id")
                    ->whereIn("wt_transactions.status",[1,2])
                    ->whereBetween("wt_transactions.tran_date",[$fromDate,$uptoDate]);
            if($userId)
            {
                $data->where("wt_transactions.emp_dtl_id",$userId);
            }
            if($ulbId)
            {
                $data->where("wt_transactions.ulb_id",$ulbId);
            }
            if($paymentMode)
            {
                $data->where(DB::raw("UPPER(wt_transactions.ulb_id)"),DB::raw("UPPER('$paymentMode')"));
            }
            $perPage = $request->perPge ? $request->perPge : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        }
        catch(Exception $e)
        {
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function userWiseCollection(Request $request)
    {
        try{
            $user = Auth()->user();
            $request->merge(["userJoin"=>"JOIN","userId"=>$user->id]);
            return $this->collationReports($request);
        }
        catch(Exception $e)
        {
            return responseMsgs(false,$e->getMessage(),"");
        }
    }


    public function dashBoard(Request $request)
    {
        try{
            $user = Auth()->user();
            $userJoin = "LEFTJOIN";
            $userId = $paymentMode = null;
            $ulbId = $user->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->userJoin) {
                $userJoin = $request->userJoin;
            }
            if ($request->fromDate)
            {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate)
            {
                $uptoDate = $request->uptoDate;
            }
            if ($request->userId)
            {
                $userId = $request->userId;
            }
            if ($request->ulbId)
            {
                $ulbId = $request->ulbId;
            }
            if ($request->paymentMode)
            {
                $paymentMode = $request->paymentMode;
            }
            $tran = WtTransaction::select(DB::raw("count(wt_transactions.id) as total_tran,
                        count(distinct(wt_transactions.booking_id))as total_booking,
                        count(distinct(wt_transactions.emp_dtl_id))as total_users,
                        sum(wt_transactions.paid_amount)as total_amount"))
                ->$userJoin("users","users.id","wt_transactions.emp_dtl_id")
                ->whereIn("wt_transactions.status",[1,2]);
            $pendingPaymentApp = WtBooking::select(DB::raw("count(wt_bookings.id) as total_booking,
                                count(distinct(wt_bookings.payment_amount))as pending_amount,
                                count(distinct(wt_bookings.user_id))as total_users"))
                        ->$userJoin("users","users.id","wt_transactions.emp_dtl_id")
                        ->where("wt_bookings.status",1)
                        ->where("wt_bookings.payment_status",0);
            $applyApp = WtBooking::select(DB::raw("count(wt_bookings.id) as total_booking,
                            count(distinct(wt_bookings.payment_amount))as pending_amount,
                            count(distinct(wt_bookings.user_id))as total_users"))
                        ->$userJoin("users","users.id","wt_transactions.emp_dtl_id")
                        ->where("wt_bookings.status",1);
            if($fromDate && $uptoDate)
            {
                $tran->whereBetween("wt_transactions.tran_date",[$fromDate,$uptoDate]);
                $pendingPaymentApp->whereBetween("wt_bookings.booking_date",[$fromDate,$uptoDate]);
                $applyApp->whereBetween("wt_bookings.booking_date",[$fromDate,$uptoDate]);
            }
            if($userId)
            {
                $tran->where("wt_transactions.emp_dtl_id",$userId);
                $pendingPaymentApp->where("wt_bookings.user_id",$userId);
                $applyApp->where("wt_bookings.user_id",$userId);
            }
            if($ulbId)
            {
                $tran->where("wt_transactions.ulb_id",$ulbId);
                $pendingPaymentApp->where("wt_bookings.ulb_id",$ulbId);
                $applyApp->where("wt_bookings.ulb_id",$ulbId);
            }
            if($paymentMode)
            {
                $tran->where(DB::raw("UPPER(wt_transactions.ulb_id)"),DB::raw("UPPER('$paymentMode')"));
            }
            
            $tran = $tran->first();
            $pendingPaymentApp = $pendingPaymentApp->first();
            $applyApp = $applyApp->first();
            $data = [
                "tran"=>$tran,
                "paymentPending"=>$pendingPaymentApp,
                "apply"=>$applyApp
            ];
            return responseMsgs(true,"Dashboard data",$data);
        }
        catch(Exception $e)
        {
            return responseMsgs(false,$e->getMessage(),"");
        }
    }

    public function userWishDashBoard(Request $request)
    {
        try{
            $user = Auth()->user();
            $request->merge(["userJoin"=>"JOIN","userId"=>$user->id]);
            return $this->dashBoard($request);
        }
        catch(Exception $e)
        {
            return responseMsgs(false,$e->getMessage(),"");
        }
    }
}
