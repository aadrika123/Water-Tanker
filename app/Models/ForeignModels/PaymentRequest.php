<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends ParamModel
{
    use HasFactory;

    /**
     * |--------------------- Save the details of the Payment requests -------------------------------------|
     * | @param request
     * | @param userId
     * | @param UlbId
     * | @param orderId
     * | @var transaction 
        | (Working)
     */
    public function saveRazorpayRequest($userId, $ulbId, $orderId, $request)
    {
        $transaction = new PaymentRequest();
        $transaction->user_id = $userId;
        $transaction->workflow_id = $request->workflowId;
        $transaction->ulb_id = $ulbId;
        $transaction->application_id = $request->id;
        $transaction->department_id = $request->departmentId;                       //<--------here(CHECK)
        $transaction->razorpay_order_id = $orderId;
        $transaction->amount = $request->amount;
        $transaction->currency = 'INR';
        $transaction->save();
    }
}
