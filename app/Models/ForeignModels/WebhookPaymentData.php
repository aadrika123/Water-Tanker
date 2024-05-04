<?php

namespace App\Models\ForeignModels;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookPaymentData extends ParamModel
{
    use HasFactory;

    public function getNotesDetails($request)
    {
        $details = WebhookPaymentData::select(
            'payment_transaction_id AS transactionNo',
            'created_at AS dateOfTransaction',
            'payment_method AS paymentMethod',
            'payment_amount AS amount',
            'payment_status AS paymentStatus'
        )
            ->where('user_id', $request)
            ->get();
        if (!empty($details['0'])) {
            return $details;
        }
        return ("no data!");
    }


    /**
     * |------------------------------- Get ApplicationId -------------------------------------|
     * | @param payId : paymentId
     * | @var userDetails
     * | @return data
        | Serial No :
     */
    public function getApplicationId($transId)
    {
        $userDetails = WebhookPaymentData::where('payment_transaction_id', $transId)
            ->select(
                'payment_notes AS userDetails'
            )
            ->get();

        if ($userDetails->isEmpty())
            throw new Exception("Application Not Found");
        $data = collect($userDetails)->first()->userDetails;
        return $data;
    }

    public function getApplicationId1($paymentId)
    {
        $userDetails = WebhookPaymentData::where('payment_id', $paymentId)
            ->select(
                'payment_notes AS userDetails'
            )
            ->get();
        $data = collect($userDetails)->first()->userDetails;
        return $data;
    }


    /**
     * |----------------------------------- Save Webhook data / Razorpay ----------------------------------|
     * | @param request
     * | @param captured
     * | @param actulaAmount
     * | @param status
     * | @param notes
     * | @param firstKey
     * | @param contains
     * | @param actualTransactionNo
     * | @param webhookEntity
     * | @var webhookData : Object for the model 
        | Serial No : 
     */
    public function saveWebhookData($request, $captured, $actulaAmount, $status, $notes, $firstKey, $contains, $actualTransactionNo, $webhookEntity)
    {
        $webhookData = new WebhookPaymentData();
        $webhookData->entity                       = $request->entity;
        $webhookData->account_id                   = $request->account_id;
        $webhookData->event                        = $request->event;
        $webhookData->webhook_created_at           = $request->created_at;
        $webhookData->payment_captured             = $captured;
        $webhookData->payment_amount               = $actulaAmount;
        $webhookData->payment_status               = $status;                                                      //<---------------- here (STATUS)
        $webhookData->payment_notes                = $notes;                                                       //<-----here (NOTES)
        $webhookData->payment_acquirer_data_type   = $firstKey;                                                    //<------------here (FIRSTKEY)
        $webhookData->contains                     = $contains;                                                    //<---------- this(CONTAINS)
        $webhookData->payment_id                   = $webhookEntity['id'];
        $webhookData->payment_entity               = $webhookEntity['entity'];
        $webhookData->payment_currency             = $webhookEntity['currency'];
        $webhookData->payment_order_id             = $webhookEntity['order_id'];
        $webhookData->payment_invoice_id           = $webhookEntity['invoice_id'];
        $webhookData->payment_international        = $webhookEntity['international'];
        $webhookData->payment_method               = $webhookEntity['method'];
        $webhookData->payment_amount_refunded      = $webhookEntity['amount_refunded'];
        $webhookData->payment_refund_status        = $webhookEntity['refund_status'];
        $webhookData->payment_description          = $webhookEntity['description'];
        $webhookData->payment_card_id              = $webhookEntity['card_id'];
        $webhookData->payment_bank                 = $webhookEntity['bank'];
        $webhookData->payment_wallet               = $webhookEntity['wallet'];
        $webhookData->payment_vpa                  = $webhookEntity['vpa'];
        $webhookData->payment_email                = $webhookEntity['email'];
        $webhookData->payment_contact              = $webhookEntity['contact'];
        $webhookData->payment_fee                  = $webhookEntity['fee'];
        $webhookData->payment_tax                  = $webhookEntity['tax'];
        $webhookData->payment_error_code           = $webhookEntity['error_code'];
        $webhookData->payment_error_description    = $webhookEntity['error_description'];
        $webhookData->payment_error_source         = $webhookEntity['error_source'] ?? null;
        $webhookData->payment_error_step           = $webhookEntity['error_step'] ?? null;
        $webhookData->payment_error_reason         = $webhookEntity['error_reason'] ?? null;
        $webhookData->payment_acquirer_data_value  = $webhookEntity['acquirer_data'][$firstKey];
        $webhookData->payment_created_at           = $webhookEntity['created_at'];

        # user details
        $webhookData->user_id                      = $webhookEntity['notes']['userId'];
        $webhookData->department_id                = $webhookEntity['notes']['departmentId'];   // moduleId
        $webhookData->workflow_id                  = $webhookEntity['notes']['workflowId'];
        $webhookData->ulb_id                       = $webhookEntity['notes']['ulbId'];

        # transaction id generation and saving
        $webhookData->payment_transaction_id = $actualTransactionNo;
        $webhookData->save();

        return $webhookData;
    }


    /**
     * |----------------------------------- Get Webhook Details By transactionId --------------------------------|
     * | @param req : transaction Id
     * 
     */
    public function webhookByTransaction($req)
    {
        return WebhookPaymentData::select(
            'payment_order_id AS orderId',
            'payment_amount AS amount',
            'payment_status AS status',
            'payment_bank AS bank',
            'payment_contact AS contact',
            'payment_method AS method',
            'payment_id AS paymentId',
            'payment_transaction_id AS transactionNo',
            'payment_acquirer_data_value AS paymentAcquirerDataValue',
            'payment_acquirer_data_type AS paymentAcquirerDataType',
            'payment_error_reason AS paymentErrorReason',
            'payment_error_source AS paymentErrorSource',
            'payment_error_description AS paymentErrorDescription',
            'payment_error_code AS paymentErrorCode',
            'payment_email AS emails',
            'payment_vpa AS  paymentVpa',
            'payment_wallet AS paymentWallet',
            'payment_card_id AS paymentCardId'
        )
            ->where('payment_transaction_id', $req->transactionNo)
            ->orderByDesc('id');
    }


    /**
     * | Get payment Details by PaymentId
     */
    public function getPaymentDetailsByPId($transId)
    {
        return WebhookPaymentData::where('payment_transaction_id', $transId)
            ->get();
    }


    /**
     * |Get Transaction Id by application Id
     */
    public function getTransactionDetails($depId, $user)
    {
        $ref = WebhookPaymentData::select('id', 'payment_transaction_id', 'payment_notes')
            ->where('user_id', $user->id)
            ->where('department_id', $depId)
            ->orderByDesc('id')
            ->get();
        return collect($ref)->map(function ($value) {
            $notes = json_decode($value['payment_notes']);
            $applicationId = $notes->applicationId;
            return [
                'applicationId' => $applicationId,
                'payment_transaction_id' => $value['payment_transaction_id']
            ];
        });
    }

    /**
     * | Fetch the Transaction No by Order id and Payment id
     */
    public function getTranByOrderPayId($req)
    {
        return WebhookPaymentData::where('payment_id', $req->paymentId)
            ->select(
                'payment_amount',
                'payment_currency',
                'payment_method',
                'payment_bank',
                'payment_email',
                'payment_contact',
                'payment_transaction_id as transaction_no'
            )
            ->where('payment_order_id', $req->orderId)
            ->orderByDesc('id')
            ->first();
    }


    /**
     * | Get details according to given data to check the record in webhook table
     */
    public function getWebhookRecord($request, $captured, $webhookEntity, $status)
    {
        return WebhookPaymentData::where("account_id", $request->account_id)
            ->where("payment_order_id", $webhookEntity['order_id'])
            ->where("payment_id", $webhookEntity['id'])
            ->where("payment_status", $status)
            ->where("payment_captured", $captured);
    }
}
