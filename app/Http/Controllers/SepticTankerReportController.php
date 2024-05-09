<?php

namespace App\Http\Controllers;

use App\Models\Septic\StBooking;
use App\Models\Septic\StTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SepticTankerReportController extends Controller
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
             $data = StTransaction::select("st_transactions.id","st_transactions.booking_id",
                             "st_transactions.payment_mode",
                             "st_transactions.paid_amount",
                             "st_transactions.tran_no",
                             "st_cheque_dtls.cheque_no",
                             "st_cheque_dtls.cheque_date",
                             "st_cheque_dtls.bank_name",
                             "st_cheque_dtls.branch_name",
                             "users.name"
                         )
                     ->leftJoin("st_cheque_dtls","st_cheque_dtls.tran_id","st_transactions.id")
                     ->$userJoin("users","users.id","st_transactions.emp_dtl_id")
                     ->whereIn("st_transactions.status",[1,2])
                     ->whereBetween("st_transactions.tran_date",[$fromDate,$uptoDate]);
             if($userId)
             {
                 $data->where("st_transactions.emp_dtl_id",$userId);
             }
             if($ulbId)
             {
                 $data->where("st_transactions.ulb_id",$ulbId);
             }
             if($paymentMode)
             {
                 $data->where(DB::raw("UPPER(st_transactions.ulb_id)"),DB::raw("UPPER('$paymentMode')"));
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
             $tran = StTransaction::select(DB::raw("count(st_transactions.id) as total_tran,
                         count(distinct(st_transactions.booking_id))as total_booking,
                         count(distinct(st_transactions.emp_dtl_id))as total_users,
                         case when sum(st_transactions.paid_amount) is null  then 0 else sum(st_transactions.paid_amount) end as total_amount"))
                 ->$userJoin("users","users.id","st_transactions.emp_dtl_id")
                 ->whereIn("st_transactions.status",[1,2]);
             $pendingPaymentApp = StBooking::select(DB::raw("count(st_bookings.id) as total_booking,
                                 count(distinct(st_bookings.payment_amount))as pending_amount,
                                 count(distinct(st_bookings.user_id))as total_users"))
                         ->$userJoin("users","users.id","st_bookings.user_id")
                         ->where("st_bookings.status",1)
                         ->where("st_bookings.payment_status",0);
             $applyApp = StBooking::select(DB::raw("count(st_bookings.id) as total_booking,
                             count(distinct(st_bookings.payment_amount))as pending_amount,
                             count(distinct(st_bookings.user_id))as total_users"))
                         ->$userJoin("users","users.id","st_bookings.user_id")
                         ->where("st_bookings.status",1);
             if($fromDate && $uptoDate)
             {
                 $tran->whereBetween("st_transactions.tran_date",[$fromDate,$uptoDate]);
                 $pendingPaymentApp->whereBetween("st_bookings.booking_date",[$fromDate,$uptoDate]);
                 $applyApp->whereBetween("st_bookings.booking_date",[$fromDate,$uptoDate]);
             }
             if($userId)
             {
                 $tran->where("st_transactions.emp_dtl_id",$userId);
                 $pendingPaymentApp->where("st_bookings.user_id",$userId);
                 $applyApp->where("st_bookings.user_id",$userId);
             }
             if($ulbId)
             {
                 $tran->where("st_transactions.ulb_id",$ulbId);
                 $pendingPaymentApp->where("st_bookings.ulb_id",$ulbId);
                 $applyApp->where("st_bookings.ulb_id",$ulbId);
             }
             if($paymentMode)
             {
                 $tran->where(DB::raw("UPPER(st_transactions.ulb_id)"),DB::raw("UPPER('$paymentMode')"));
             }
             
             $tran = $tran->first();
             $pendingPaymentApp = $pendingPaymentApp->first();
             $applyApp = $applyApp->first();
             $data = [
                 "tran"=>$tran,
                 "paymentPending"=>$pendingPaymentApp,
                 "apply"=>$applyApp
             ];
             return responseMsgs(true,"Dashboard data",remove_null($data));
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
