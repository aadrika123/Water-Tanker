<?php

namespace App\Traits\Payment;
use App\Models\ForeignModels\PaymentRequest;
use App\Models\ForeignModels\PaymentReject;
use App\Models\ForeignModels\PaymentSuccess;
use Exception;
use Razorpay\Api\Api;
use Illuminate\Support\Str;
use Razorpay\Api\Errors\SignatureVerificationError;
use App\Models\ForeignModels\WfWorkflow;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for Razorpay
 * Created By-Sam kerktta
 * Created On-15-11-2022 
 * --------------------------------------------------------------------------------------
 */

trait Razorpay
{
    /**
     * | ----------------- payment generating order id / Saving in database ------------------------------- |
     * | @var mReciptId
     * | @var mUserID
     * | @var mUlbID
     * | @var mApi
     * | @var mOrder
     * | @var mReturndata
     * | @var transaction
     * | @var error
     * | @param request
     * | Operation : generating the order id according to request data, using the razorpay API 
     * | Rating : 3
        | Serial No : 01
        | Department Id will be replaced by module Id /76
     */

    public function saveGenerateOrderid($request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'id'            => 'required|integer',
                'amount'        => 'required|',
                'workflowId'    => 'required|',
                'ulbId'         => 'nullable'
            ]
        );
        if ($validated->fails())
            return validationErrorV2($validated);

        try {
            $mWfWorkflow    = new WfWorkflow();
            $wfReq = new Request([
                'id' => $request->workflowId
            ]);

            $userId             = auth()->user()->id ?? $request->ghostUserId;
            $workflowDetails    = $mWfWorkflow->listbyId($wfReq);
            $ulbId              = $workflowDetails->ulb_id ?? $request->ulbId;                                           // ulbId
            $refRazorpayId      = Config::get('razorpay.RAZORPAY_ID');
            $refRazorpayKey     = Config::get('razorpay.RAZORPAY_KEY');
            $mReciptId          = Str::random(15);                                                      // Static recipt ID

            if (!$ulbId) {
                throw new Exception("Ulb details not found!");
            }
            $mApi = new Api($refRazorpayId, $refRazorpayKey);
            $mOrder = $mApi->order->create(array(
                'receipt'           => $mReciptId,
                'amount'            => $request->all()['amount'] * 100,
                'currency'          => 'INR',                                                       // Static
                'payment_capture'   => 1                                                            // Static
            ));

            $Returndata = [
                'orderId'       => $mOrder['id'],
                'amount'        => $request->all()['amount'],
                'currency'      => 'INR',                                                           // Static
                'userId'        => $userId,
                'ulbId'         => $ulbId,
                'workflowId'    => $request->workflowId,
                'applicationId' => $request->id,
                'departmentId'  => $request->departmentId,
                'propType'      => $request->propType
            ];

            $saveRequestObj = new PaymentRequest();
            $saveRequestObj->saveRazorpayRequest($userId, $ulbId, $Returndata['orderId'], $request);

            // return responseMsgs(true, "OrderId Generated!", $Returndata, "", "04", responseTime(), "POST", $request->deviceId);
            return (array)$Returndata;
        } catch (Exception $error) {
            return responseMsgs(false, "Error Listed Below!", $error->getMessage());
        }
    }

    /**
     * | ----------------- verification of the signature ------------------------------- |
     * | @var reciptId
     * | @var api
     * | @var success
     * | @var successData
     * | @var rejectData
     * | @var error
     * | @param request
     * | @param attributes
     * |
     * | @constants : refRazorpayId
     * | @constants : refRazorpayKey
     * |
     * | Operation : generating the order id according the request data using the razorpay API 
     * | Rating : 4
        | Serial No : 02
        | (Working)
     */
    function paymentVerify($request, $attributes)
    {
        try {
            $success = false;
            $refRazorpayId = Config::get('razorpay.RAZORPAY_ID');
            $refRazorpayKey = Config::get('razorpay.RAZORPAY_KEY');

            # verify the existence of the razerpay Id
            if (!is_null($request->razorpayPaymentId) && !empty($request->razorpaySignature)) {
                $api = new Api($refRazorpayId, $refRazorpayKey);
                try {
                    $attributes = [
                        'razorpay_order_id' => $request->razorpayOrderId,
                        'razorpay_payment_id' => $request->razorpayPaymentId,
                        'razorpay_signature' => $request->razorpaySignature
                    ];
                    $api->utility->verifyPaymentSignature($attributes);
                    $success = true;
                } catch (SignatureVerificationError $exception) {
                    $success = false;
                    $error = $exception->getMessage();
                }
            }
            if ($success === true) {
                # Update database with success data
                try {
                    $successData = new PaymentSuccess();
                    $successData->saveSuccessDetails($request);
                    return responseMsgs(true, "Payment Clear to Procede!", "");
                } catch (Exception $exception) {
                    return responseMsgs(false, "Error listed below!", $exception->getMessage());
                }
            }
            # Update database with error data
            $rejectData = new PaymentReject();
            $rejectData->saveRejectedData($request);
            return responseMsgs(true, "There is Some error!", $error);
        } catch (Exception $exception) {
            return responseMsgs(false, "Exception occured of whole function", $exception->getMessage());
        }
    }
}
