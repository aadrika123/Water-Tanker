<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\ForeignModels\WebhookPaymentData;
use App\Repository\Payment\Concrete\PaymentRepository;
use App\Repository\Payment\Interfaces\iPayment;
use App\Traits\Payment\Razorpay;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RazorpayPaymentController extends Controller
{
    //  Construct Function
    # traits
    use Razorpay;
    private $Prepository;
    public function __construct()
    {
        $this->Prepository = new PaymentRepository();
    }

    //get department by ulbid
    public function getDepartmentByulb(Request $req)
    {
        #   validation
        $validateUser = Validator::make(
            $req->all(),
            [
                'ulbId'   => 'required|integer',
            ]
        );

        if ($validateUser->fails()) {
            return responseMsgs(false, 'validation error', $validateUser->errors(), 401);
        }
        return $this->Prepository->getDepartmentByulb($req);
    }

    //get PaymentGateway by request
    public function getPaymentgatewayByrequests(Request $req)
    {
        #   validation
        $validateUser = Validator::make(
            $req->all(),
            [
                'departmentId'   => 'required|integer',
                'ulbId'   => 'required|integer',
            ]
        );

        if ($validateUser->fails()) {
            return responseMsgs(false, 'validation error', $validateUser->errors(), 401);
        }
        return $this->Prepository->getPaymentgatewayByrequests($req);
    }

    //get specific PaymentGateway Details according request
    public function getPgDetails(Request $req)
    {
        # validation
        $validateUser = Validator::make(
            $req->all(),
            [
                'departmentId'   => 'required|integer',
                'ulbId'   => 'required|integer',
                'paymentGatewayId'   => 'required|integer',
            ]
        );

        if ($validateUser->fails()) {
            return responseMsgs(false, 'validation error', $validateUser->errors(), 401);
        }
        return $this->Prepository->getPgDetails($req);
    }

    //get finla payment details of the webhook
    public function getWebhookDetails()
    {
        return $this->Prepository->getWebhookDetails();
    }


    /**
     * | Verify the payment status 
     * | Use to check the actual paymetn from the server 
        | Testing
        | This
     */
    public function verifyPaymentStatus(Request $req)
    {
        $req->validate([
            'razorpayOrderId' => 'required',
            'razorpayPaymentId' => 'required',
        ]);
        try {
            return responseMsgs(true, "payment On process!", [], "", "01", "", "POST", $req->deviceId);
            return $this->Prepository->verifyPaymentStatus($req);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", "", "POST", $req->deviceId);
        }
    }

    //verify the payment status
    /**
        | This
     */
    public function gettingWebhookDetails(Request $req)
    {
        return $this->Prepository->gettingWebhookDetails($req);
    }

    //get the details of webhook according to transactionNo
    public function getTransactionNoDetails(Request $req)
    {
        # validation 
        $validated = Validator::make(
            $req->all(),
            [
                'transactionNo' => 'required|integer',
            ]
        );
        if ($validated->fails()) {
            return responseMsgs(false, "validation error", $validated->errors(), 401);
        }
        return $this->Prepository->getTransactionNoDetails($req);
    }

    // saveGenerateOrderid
    /**
        | This
     */
    public function generateOrderid(Request $req)
    {
        // return $req;
        try {
            $Returndata =  $this->saveGenerateOrderid($req); 
            return responseMsgs(true, "OrderId Generated!", $Returndata, "", "04", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    /**
        |-------------------------------------- Payment Reconcillation -----------------------------------------| 
     */

    //get all the details of Payment Reconciliation 
    // public function getReconcillationDetails()
    // {
    //     try {
    //         return $this->Prepository->getReconcillationDetails();
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "");
    //     }
    // }

    // serch the specific details according to the request
    // public function searchReconciliationDetails(Request $request)
    // {
    //     return $this->Prepository->searchReconciliationDetails($request);
    // }

    // // serch the specific details according to the request
    // public function updateReconciliationDetails(Request $request)
    // {
    //     # validation 
    //     $validated = Validator::make(
    //         $request->all(),
    //         [
    //             'transactionNo' => 'required',
    //             'status' => 'required',
    //             'date' => 'required|date'
    //         ]
    //     );
    //     if ($validated->fails()) {
    //         return responseMsgs(false, "validation error", $validated->errors(), 401);
    //     }
    //     return $this->Prepository->updateReconciliationDetails($request);
    // }

    // get all details of the payments of all modules
    public function allModuleTransaction()
    {
        return $this->Prepository->allModuleTransaction();
    }

    // Serch the tranasaction details
    /**
     | Flag / Route
     */
    // public function searchTransaction(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'fromDate' => 'required',
    //             'toDate' => 'required',
    //         ]);
    //         return $this->Prepository->searchTransaction($request);
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "");
    //     }
    // }

    /**
     * | Get Transaction dtls by orderid and paymentid
     */
    public function getTranByOrderId(Request $req)
    {
        $req->validate([
            'orderId' => 'required',
            'paymentId' => 'required'
        ]);
        try {
            $mWebhook = new WebhookPaymentData();
            $webhookData = $mWebhook = $mWebhook->getTranByOrderPayId($req);
            return responseMsgs(true, "Transaction No", remove_null($webhookData), "15", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
