<?php

namespace App\Http\Controllers;

use App\Http\IdGenerator\PrefixIdGenerator;
use App\Models\ForeignModels\RevDailycollection;
use App\Models\ForeignModels\RevDailycollectiondetail;
use App\Models\ForeignModels\TempTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\WtTransaction;
use App\Models\Septic\StTransaction;
use App\Models\WtBooking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CashVerificationController extends Controller
{
    // public function cashVerificationList(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'date' => 'required|date',
    //         'userId' => 'nullable|int'
    //     ]);
    //     if ($validator->fails())
    //         return validationErrorV2($validator);
    //     try {
    //         $ulbId = authUser($request)->ulb_id;
    //         $userId = $request->id;
    //         $date = date('Y-m-d', strtotime($request->date));
    //         $waterTankerTranType = Config::get('constants.WATER_TANKER_TRAN_TYPE');
    //         $septicTankerTranType = Config::get('constants.SEPTIC_TANKER_TRAN_TYPE');
    //         $mTempTransactionWtank = new WtTransaction();
    //         $mTempTransactionStank = new StTransaction();
    //         $waterTankerData = $mTempTransactionWtank->transactionDtl($date)
    //             ->where('wt_transactions.ulb_id', $ulbId);
    //         $septicTankerData = $mTempTransactionStank->transactionDtl($date)
    //             ->where('st_transactions.ulb_id', $ulbId);
    //         if (isset($userId)) {
    //             $waterTankerData = $waterTankerData->where('emp_dtl_id', $userId);
    //             $septicTankerData = $septicTankerData->where('emp_dtl_id', $userId);
    //         }

    //         $waterTankerData = $waterTankerData->get();
    //         $septicTankerData = $septicTankerData->get();

    //         $data = $waterTankerData->merge($septicTankerData);

    //         $collection = $data->groupBy("emp_dtl_id");

    //         $data = $collection->map(function ($val) use ($date) {
    //             $total = $val->sum('paid_amount');
    //             $wtank = $val->where("tran_type", 'Water Tanker Booking')->sum('paid_amount');
    //             $stank = $val->where("tran_type", 'Septic Tanker Booking')->sum('paid_amount');
    //             return [
    //                 // "id" => $val[0]['emp_dtl_id'],
    //                 "user_id" => $val[0]['emp_dtl_id'],
    //                 "user_name" => $val[0]['name'],
    //                 "waterTanker" => $wtank,
    //                 "septicTanker" => $stank,
    //                 "total" => $total,
    //                 "date" => Carbon::parse($date)->format('d-m-Y'),
    //                 // "verified_amount" => 0,
    //             ];
    //         });

    //         $data = array_values($data->toArray());

    //         return responseMsgs(true, "List cash Verification", $data, "012201", "1.0", "", "POST", $request->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "012201", "1.0", "", "POST", $request->deviceId ?? "");
    //     }
    // }



    // public function cashVerificationDtl(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         "date" => "required|date",
    //         "userId" => "required|numeric",
    //     ]);
    //     if ($validator->fails())
    //         return validationErrorV2($validator);
    //     try {
    //         $userId =  $request->userId;
    //         $ulbId =  authUser($request)->ulb_id;
    //         $date = date('Y-m-d', strtotime($request->date));
    //         $waterTankerModuleId = Config::get('constants.WATER_TANKER_MODULE_ID');
    //         $septicTankerModuleId = Config::get('constants.SEPTIC_TANKER_MODULE_ID');
    //         $mTempTransactionWtank = new WtTransaction();
    //         $mTempTransactionStank = new StTransaction();

    //         $details = $mTempTransactionWtank->transactionList($date, $userId, $ulbId);
    //         $details = ($details)->merge($mTempTransactionStank->transactionList($date, $userId, $ulbId));
    //         if ($details->isEmpty())
    //             throw new Exception("No Application Found for this id");

    //         $data['wtank'] = collect($details)->where("tran_type", 'Water Tanker Booking')->values();
    //         $data['stank'] = collect($details)->where("tran_type", 'Septic Tanker Booking')->values();
    //         $data['Cash'] = collect($details)->where('payment_mode', '=', 'CASH')->sum('paid_amount');
    //         $data['date'] = Carbon::parse($date)->format('d-m-Y');
    //         $data['is_verified'] = false;

    //         return responseMsgs(true, "cash Collection", remove_null($data), "012203", "1.0", "", "POST", $request->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "012203", "1.0", "", "POST", $request->deviceId ?? "");
    //     }
    // }

    public function cashVerificationList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'userId' => 'nullable|int'
        ]);
        if ($validator->fails())
            return validationErrorV2($validator);
        try {
            $ulbId =  Auth()->user()->ulb_id;
            $userId =  $request->userId;
            $date = date('Y-m-d', strtotime($request->date));
            $waterTankerModuleId = Config::get('constants.WATER_TANKER_MODULE_ID');
            $septicTankerModuleId = Config::get('constants.SEPTIC_TANKER_MODULE_ID');
            $mTempTransaction =  new TempTransaction();
            $zoneId = $request->zone;
            $wardId = $request->wardId;

            $data = $mTempTransaction->transactionDtl($date, $ulbId);
            if ($userId) {
                $data = $data->where('user_id', $userId);
            }
            if ($zoneId) {
                $data = $data->where('ulb_ward_masters.zone', $zoneId);
            }
            if ($wardId) {
                $data = $data->where('ulb_ward_masters.id', $wardId);
            }
            $data = $data->get();

            $collection = collect($data->groupBy("user_id")->all());

            $data = $collection->map(function ($val) use ($date, $waterTankerModuleId, $septicTankerModuleId) {
                $total =  $val->sum('amount');
                $wtank = $val->where("module_id", $waterTankerModuleId)->sum('amount');
                $stank = $val->where("module_id", $septicTankerModuleId)->sum('amount');
                return [
                    "id" => $val[0]['user_id'],
                    "user_name" => $val[0]['name'],
                    "wtank" => $wtank,
                    "stank" => $stank,
                    "total" => $total,
                    "date" => Carbon::parse($date)->format('d-m-Y'),
                    // "verified_amount" => 0,
                ];
            });

            $data = (array_values(objtoarray($data)));
            return responseMsgs(true, "List cash Verification", $data, "010201", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }



    public function cashVerificationDtl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "date" => "required|date",
            "userId" => "required|numeric",
        ]);
        if ($validator->fails())
            return validationErrorV2($validator);
        try {
            $userId =  $request->userId;
            $ulbId =  authUser($request)->ulb_id;
            $date = date('Y-m-d', strtotime($request->date));
            $waterTankerModuleId = Config::get('constants.WATER_TANKER_MODULE_ID');
            $septicTankerModuleId = Config::get('constants.SEPTIC_TANKER_MODULE_ID');
            $mTempTransaction = new TempTransaction();

            $details = $mTempTransaction->transactionList($date, $userId, $ulbId);
            if ($details->isEmpty())
                throw new Exception("No Application Found for this id");

            $data['wtank'] = collect($details)->where("module_id", $waterTankerModuleId)->values();
            $data['stank'] = collect($details)->where("module_id", $septicTankerModuleId)->values();
            $data['Cash'] = collect($details)->where('payment_mode', '=', 'CASH')->sum('amount');
            $data['date'] = Carbon::parse($date)->format('d-m-Y');
            $data['is_verified'] = false;

            return responseMsgs(true, "cash Collection", remove_null($data), "012203", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "012203", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    public function verifyCash(Request $request)
    {

        try {
            $user = Auth()->user();
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $wtank =  $request->wtank;
            $stank    =  $request->stank;
            $mRevDailycollection = new RevDailycollection();
            $cashParamId = Config::get('constants.PARAM_IDS.CASH_VERIFICATION_PARAM_ID');

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            $idGeneration = new PrefixIdGenerator($cashParamId, $ulbId);
            $tranNo = $idGeneration->generate();

            if ($wtank) {
                $tempTranDtl = TempTransaction::find($wtank[0]['id']);
                $tranDate = $tempTranDtl['tran_date'];
                $tcId = $tempTranDtl['user_id'];
                $mReqs = new Request([
                    "tran_no" => $tranNo,
                    "user_id" => $userId,
                    "demand_date" => $tranDate,
                    "deposit_date" => Carbon::now(),
                    "ulb_id" => $ulbId,
                    "tc_id" => $tcId,
                ]);
                $collectionId =  $mRevDailycollection->store($mReqs);

                foreach ($wtank as $item) {
                    $tempDtl = TempTransaction::find($item['id']);
                    $tranId =  $tempDtl->transaction_id;

                    WtTransaction::where('id', $tranId)
                        ->update(
                            [
                                'is_verified' => 1,
                                'verify_date' => Carbon::now(),
                                'verify_by' => $userId,
                                'verify_user_type' => $user->user_type,
                            ]
                        );
                    $this->dailyCollectionDtl($tempDtl, $collectionId);
                    if (!$tempDtl)
                        throw new Exception("No Transaction Found for this id");

                    $logTrans = $tempDtl->replicate();
                    $logTrans->setTable('log_temp_transactions');
                    $logTrans->id = $tempDtl->id;
                    $logTrans->save();
                    $tempDtl->delete();
                }
            }

            if ($stank) {
                $tempTranDtl = TempTransaction::find($stank[0]['id']);
                $tranDate = $tempTranDtl['tran_date'];
                $tcId = $tempTranDtl['user_id'];
                $mReqs = new Request([
                    "tran_no" => $tranNo,
                    "user_id" => $userId,
                    "demand_date" => $tranDate,
                    "deposit_date" => Carbon::now(),
                    "ulb_id" => $ulbId,
                    "tc_id" => $tcId,
                ]);
                $collectionId =  $mRevDailycollection->store($mReqs);

                foreach ($stank as $item) {

                    $tempDtl = TempTransaction::find($item['id']);
                    $tranId =  $tempDtl->transaction_id;

                    StTransaction::where('id', $tranId)
                        ->update(
                            [
                                'is_verified' => 1,
                                'verify_date' => Carbon::now(),
                                'verify_by' => $userId,
                                'verify_user_type' => $user->user_type,
                            ]
                        );
                    $this->dailyCollectionDtl($tempDtl, $collectionId);
                    if (!$tempDtl)
                        throw new Exception("No Transaction Found for this id");

                    $logTrans = $tempDtl->replicate();
                    $logTrans->setTable('log_temp_transactions');
                    $logTrans->id = $tempDtl->id;
                    $logTrans->save();
                    $tempDtl->delete();
                }
            }
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Cash Verified", '', "010201", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    public function dailyCollectionDtl($tranDtl, $collectionId)
    {
        $RevDailycollectiondetail = new RevDailycollectiondetail();
        $mReqs = new Request([
            "collection_id" => $collectionId,
            "module_id" => $tranDtl['module_id'],
            "demand" => $tranDtl['amount'],
            "deposit_amount" => $tranDtl['amount'],
            "cheq_dd_no" => $tranDtl['cheque_dd_no'],
            "bank_name" => $tranDtl['bank_name'],
            "deposit_mode" => strtoupper($tranDtl['payment_mode']),
            "application_no" => $tranDtl['application_no'],
            "transaction_id" => $tranDtl['id']
        ]);
        $RevDailycollectiondetail->store($mReqs);
    }
}
