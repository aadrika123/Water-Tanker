<?php

namespace App\Http\Controllers;

use App\Models\WtBooking;
use App\Models\WtCancellation;
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
        if ($request->fromDate) {
            $fromDate = $request->fromDate;
        }
        if ($request->uptoDate) {
            $uptoDate = $request->uptoDate;
        }
        if ($request->userId) {
            $userId = $request->userId;
        }
        if ($request->ulbId) {
            $ulbId = $request->ulbId;
        }
        if ($request->paymentMode) {
            $paymentMode = $request->paymentMode;
        }
        try {
            $data = WtTransaction::select(
                "wt_transactions.id",
                "wt_transactions.booking_id",
                "wt_transactions.payment_mode",
                "wt_transactions.paid_amount",
                "wt_transactions.tran_no",
                "wt_cheque_dtls.cheque_no",
                "wt_cheque_dtls.cheque_date",
                "wt_cheque_dtls.bank_name",
                "wt_cheque_dtls.branch_name",
                "users.name",
                "bookings.booking_no",
                "bookings.applicant_name",
                "bookings.mobile",
                "bookings.booking_date",
            )
                ->join(DB::raw(
                    "(
                            (
                                SELECT wt_transactions.id as tran_id,
                                    wt_bookings.id,wt_bookings.booking_no,wt_bookings.applicant_name,wt_bookings.mobile,wt_bookings.booking_date 
                                FROM wt_bookings
                                JOIN wt_transactions on wt_transactions.booking_id = wt_bookings.id
                                WHERE wt_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                            )
                            UNION(
                                SELECT wt_transactions.id as tran_id,
                                    wt_bookings.id,wt_bookings.booking_no,wt_bookings.applicant_name,wt_bookings.mobile,wt_bookings.booking_date 
                                FROM wt_cancellations AS wt_bookings
                                JOIN wt_transactions on wt_transactions.booking_id = wt_bookings.id
                                WHERE wt_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                            )
                        )bookings"
                ), "bookings.tran_id", "wt_transactions.id")
                ->leftJoin("wt_cheque_dtls", "wt_cheque_dtls.tran_id", "wt_transactions.id")
                ->$userJoin("users", "users.id", "wt_transactions.emp_dtl_id")
                ->whereIn("wt_transactions.status", [1, 2])
                ->whereBetween("wt_transactions.tran_date", [$fromDate, $uptoDate]);
            if ($userId) {
                $data->where("wt_transactions.emp_dtl_id", $userId);
            }
            if ($ulbId) {
                $data->where("wt_transactions.ulb_id", $ulbId);
            }
            if ($paymentMode) {
                $data->where(DB::raw("UPPER(wt_transactions.ulb_id)"), DB::raw("UPPER('$paymentMode')"));
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
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    public function userWiseCollection(Request $request)
    {
        try {
            $user = Auth()->user();
            $request->merge(["userJoin" => "JOIN", "userId" => $user->id]);
            return $this->collationReports($request);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    public function dashBoard(Request $request)
    {
        try {
            $user = Auth()->user();
            $userJoin = "LEFTJOIN";
            $userId = $paymentMode = null;
            $ulbId = $user->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->userJoin) {
                $userJoin = $request->userJoin;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            $tran = WtTransaction::select(DB::raw("count(wt_transactions.id) as total_tran,
                        count(distinct(wt_transactions.booking_id))as total_booking,
                        count(distinct(wt_transactions.emp_dtl_id))as total_users,
                        case when sum(wt_transactions.paid_amount) is null  then 0 else sum(wt_transactions.paid_amount) end as total_amount"))
                ->$userJoin("users", "users.id", "wt_transactions.emp_dtl_id")
                ->whereIn("wt_transactions.status", [1, 2]);
            $pendingPaymentApp = WtBooking::select(DB::raw("count(wt_bookings.id) as total_booking,
                                count(distinct(wt_bookings.payment_amount))as pending_amount,
                                count(distinct(wt_bookings.user_id))as total_users"))
                ->$userJoin("users", "users.id", "wt_bookings.user_id")
                ->where("wt_bookings.status", 1)
                ->where("wt_bookings.payment_status", 0);
            $applyApp = WtBooking::select(DB::raw("count(wt_bookings.id) as total_booking,
                            count(distinct(wt_bookings.payment_amount))as pending_amount,
                            count(distinct(wt_bookings.user_id))as total_users"))
                ->$userJoin("users", "users.id", "wt_bookings.user_id")
                ->where("wt_bookings.status", 1);
            if ($fromDate && $uptoDate) {
                $tran->whereBetween("wt_transactions.tran_date", [$fromDate, $uptoDate]);
                $pendingPaymentApp->whereBetween("wt_bookings.booking_date", [$fromDate, $uptoDate]);
                $applyApp->whereBetween("wt_bookings.booking_date", [$fromDate, $uptoDate]);
            }
            if ($userId) {
                $tran->where("wt_transactions.emp_dtl_id", $userId);
                $pendingPaymentApp->where("wt_bookings.user_id", $userId);
                $applyApp->where("wt_bookings.user_id", $userId);
            }
            if ($ulbId) {
                $tran->where("wt_transactions.ulb_id", $ulbId);
                $pendingPaymentApp->where("wt_bookings.ulb_id", $ulbId);
                $applyApp->where("wt_bookings.ulb_id", $ulbId);
            }
            if ($paymentMode) {
                $tran->where(DB::raw("UPPER(wt_transactions.ulb_id)"), DB::raw("UPPER('$paymentMode')"));
            }

            $tran = $tran->first();
            $pendingPaymentApp = $pendingPaymentApp->first();
            $applyApp = $applyApp->first();
            $data = [
                "tran" => $tran,
                "paymentPending" => $pendingPaymentApp,
                "apply" => $applyApp
            ];
            return responseMsgs(true, "Dashboard data", remove_null($data));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    public function userWishDashBoard(Request $request)
    {
        try {
            $user = Auth()->user();
            $request->merge(["userJoin" => "JOIN", "userId" => $user->id]);
            return $this->dashBoard($request);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    public function cancleBookingList(Request $request)
    {
        try {
            $user = Auth()->user();
            $ulbId = $user->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->toDate) {
                $uptoDate = $request->toDate;
            }
            $cancleBookingList = WtCancellation::select(DB::raw("booking_no,wt_locations.location,applicant_name,mobile,address,booking_date,cancel_date,cancelled_by,remarks as reason,delivery_latitude as lat,delivery_longitude as long,CONCAT(wt_drivers.driver_name, ' - ', wt_resources.vehicle_no) as driver_vehcile","ward_id"))
                ->leftjoin('wt_locations', 'wt_locations.id', '=', 'wt_cancellations.location_id')
                ->leftjoin("wt_drivers", "wt_drivers.id", "wt_cancellations.driver_id")
                ->leftjoin("wt_resources", "wt_resources.id", "wt_cancellations.vehicle_id")
                ->where("wt_cancellations.ulb_id", $ulbId);
            //->get();
            if ($fromDate && $uptoDate) {
                $cancleBookingList->whereBetween("wt_cancellations.booking_date", [$fromDate, $uptoDate]);
            }
            $perPage = $request->perPge ? $request->perPge : 10;
            $paginator = $cancleBookingList->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsgs(true, "water tank cancle booking list", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }
}
