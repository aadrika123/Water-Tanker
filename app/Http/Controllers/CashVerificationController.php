<?php

namespace App\Http\Controllers;

use App\Http\IdGenerator\PrefixIdGenerator;
use App\MicroServices\DocUpload;
use App\Models\ForeignModels\RevDailycollection;
use App\Models\ForeignModels\RevDailycollectiondetail;
use App\Models\ForeignModels\TempTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\WtTransaction;
use App\Models\Septic\StTransaction;
use App\Models\StankTransactionDeactivateDtl;
use App\Models\WtankTransactionDeactivateDtl;
use App\Models\WtBooking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CashVerificationController extends Controller
{
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
            $data['totalAmount'] =  $details->sum('amount');
            $data['numberOfTransaction'] =  $details->count();
            $data['collectorName'] =  collect($details)[0]->user_name;

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

    public function searchTransactionNo(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "transactionNo" => "required",
            "tranType" => "required|In:Watertanker,Septictanker"
        ]);

        if ($validator->fails())
            return validationErrorV2($validator);
        try {

            if ($req->tranType == "Watertanker") {
                $mWtTransaction = new WtTransaction();
                $transactionDtl = $mWtTransaction->getTransByTranNo($req->transactionNo);
            }
            if ($req->tranType == "Septictanker") {
                $mWtTransaction = new StTransaction();
                $transactionDtl = $mWtTransaction->getTransByTranNo($req->transactionNo);
            }
            return responseMsgs(true, "Transaction No is", $transactionDtl, "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    public function deactivateTransaction(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "TranId" => "required",                         // Transaction ID
            "moduleId" => "required",
            "remarks" => "required|string",
            "document" => 'required|mimes:png,jpg,jpeg,gif'
        ]);
        if ($validator->fails())
            return validationErrorV2($validator);

        try {
            $waterTankerModuleId = Config::get('constants.WATER_TANKER_MODULE_ID');
            $septicTankerModuleId = Config::get('constants.SEPTIC_TANKER_MODULE_ID');
            $transactionId = $req->TranId;
            $moduleId = $req->moduleId;
            $document = new DocUpload();
            $document = $document->severalDoc($req);
            $document = $document->original["data"];
            $refImageName = $req->id . "_" . $req->moduleId . "_" . (Carbon::now()->format("Y-m-d"));
            $user = Auth()->user();
            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();

            $imageName = "";
            $deactivationArr = [
                "tran_id" => $req->TranId,
                "deactivated_by" => $user->id,
                "reason" => $req->remarks,
                "file_path" => $imageName,
                "unique_id" => $document["document"]["data"]["uniqueId"],
                "reference_no" => $document["document"]["data"]["ReferenceNo"],
                "deactive_date" => $req->deactiveDate ?? Carbon::now()->format("Y-m-d"),
            ];
            if ($req->moduleId == $waterTankerModuleId) {
                $mWtTransaction = new WtTransaction();
                $mWtTransaction->deactivateTransaction($transactionId);
                $wtankTranDeativetion = new WtankTransactionDeactivateDtl();
                $wtankTranDeativetion->create($deactivationArr);
                TempTransaction::where('transaction_id', $transactionId)
                    ->where('module_id', $moduleId)
                    ->update(['status' => 0]);
            }
            if ($req->moduleId == $septicTankerModuleId) {
                $mStTransaction = new StTransaction();
                $mStTransaction->deactivateTransaction($transactionId);
                $stankTranDeativetion = new StankTransactionDeactivateDtl();
                $stankTranDeativetion->create($deactivationArr);
                TempTransaction::where('transaction_id', $transactionId)
                    ->where('module_id', $moduleId)
                    ->update(['status' => 0]);
            }
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Transaction Deactivated", "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    public function deactivatedTransactionList(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date',
            'uptoDate' => 'nullable|date',
            'paymentMode' => 'nullable|string',
            'transactionNo' => 'nullable|string'
        ]);
        if ($validator->fails())
            return validationErrorV2($validator);

        try {
            $fromDate = $req->fromDate ?? Carbon::now()->format("Y-m-d");
            $uptoDate = $req->uptoDate ?? Carbon::now()->format("Y-m-d");
            $paymentMode = $req->paymentMode ?? null;
            $transactionNo = $req->transactionNo ?? null;

            // Get deactivated transactions for water tankers
            $mWtTransaction = new WtTransaction();
            $transactionDeactivationDtlWtank = $mWtTransaction->getDeactivatedTran()
                ->whereBetween('wt_transactions.tran_date', [$fromDate, $uptoDate]);

            if ($paymentMode) {
                $transactionDeactivationDtlWtank->where('wt_transactions.payment_mode', $paymentMode);
            }
            if ($transactionNo) {
                $transactionDeactivationDtlWtank->where('wt_transactions.tran_no', $transactionNo);
            }
            $mStTransaction = new StTransaction();
            $transactionDeactivationDtlStank = $mStTransaction->getDeactivatedTran()
                ->whereBetween('st_transactions.tran_date', [$fromDate, $uptoDate]);

            if ($paymentMode) {
                $transactionDeactivationDtlStank->where('st_transactions.payment_mode', $paymentMode);
            }
            if ($transactionNo) {
                $transactionDeactivationDtlStank->where('st_transactions.tran_no', $transactionNo);
            }
            $query = $transactionDeactivationDtlWtank->union($transactionDeactivationDtlStank);
            $perPage = $req->perPge ?? 10;
            $page = $req->input('page', 1);
            $data = $query->paginate($perPage, ['*'], 'page', $page);

            $list = [
                "current_page" => $data->currentPage(),
                "last_page" => $data->lastPage(),
                "data" => $data->items(),
                "total" => $data->total(),
            ];
            return responseMsgs(true, "Deactivated Transaction List", $list, "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
