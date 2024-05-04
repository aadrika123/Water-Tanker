<?php

namespace App\Repository\Payment\Concrete;

use App\Http\IdGenerator\PrefixIdGenerator;
use App\Models\ForeignModels\ApiMaster;
use App\Models\ForeignModels\CardDetail;
use App\Models\ForeignModels\DepartmentMaster;
use App\Models\ForeignModels\PaymentGatewayDetail;
use App\Models\ForeignModels\PaymentGatewayMaster;
use App\Models\ForeignModels\PaymentReconciliation;
use App\Models\ForeignModels\PaymentReject;
use App\Models\ForeignModels\PaymentRequest;
use App\Models\ForeignModels\PaymentSuccess;
use App\Models\ForeignModels\WebhookPaymentData;
use Illuminate\Http\Request;
use App\Repository\Payment\Interfaces\iPayment;

use App\Traits\Payment\Razorpay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * |--------------------------------------------------------------------------------------------------------|
 * | Created On-14-11-2022 
 * | Created By- Sam kerketta
 * | Payment Regarding Crud Operations
 * |--------------------------------------------------------------------------------------------------------|
 */


class PaymentRepository implements iPayment
{
    # traits
    use Razorpay;
    protected $_safRepo;
    
    /**
     * | Get Department By Ulb
     * | @param req request from the frontend
     * | @var mReadDepartment collecting data from the table DepartmentMaster
     * | 
     * | Rating : 2
     * | Time :
     */
    public function getDepartmentByulb(Request $req)
    {
        try {
            $mReadDepartment = DepartmentMaster::select(
                'department_masters.id',
                'department_masters.department_name AS departmentName'
            )
                ->join('ulb_department_maps', 'ulb_department_maps.department_id', '=', 'department_masters.id')
                ->where('ulb_department_maps.ulb_id', $req->ulbId)
                ->get();

            if (!empty($mReadDepartment['0'])) {
                return responseMsgs(true, "Data according to ulbid", $mReadDepartment);
            }
            return responseMsgs(false, "Data not exist", "");
        } catch (Exception $error) {
            return responseMsgs(false, "Error", $error->getMessage());
        }
    }


    /**
     * | Get Payment gateway details by provided requests
     * | @param req request from the frontend
     * | @param error collecting the operation error
     * | @var mReadPg collecting data from the table PaymentGatewayMaster
     * | 
     * | Rating : 
     * | Time :
     */
    public function getPaymentgatewayByrequests(Request $req)
    {
        try {
            $mReadPg = PaymentGatewayMaster::select(
                'payment_gateway_masters.id',
                'payment_gateway_masters.pg_full_name AS paymentGatewayName'
            )
                ->join('department_pg_maps', 'department_pg_maps.pg_id', '=', 'payment_gateway_masters.id')
                ->join('ulb_department_maps', 'ulb_department_maps.department_id', '=', 'department_pg_maps.department_id')
                ->where('ulb_department_maps.department_id', $req->departmentId)
                ->where('ulb_department_maps.ulb_id', $req->ulbId)
                ->get();

            if (!empty($mReadPg['0'])) {
                return responseMsgs(true, "Data of PaymentGateway", $mReadPg);
            }
            return responseMsgs(false, "Data not found", "");
        } catch (Exception $error) {
            return responseMsgs(false, "error", $error);
        }
    }


    /**
     * | Get Payment gateway details by required gateway
     * | @param req request from the frontend
     * | @param error collecting the operation error
     * | @var mReadRazorpay collecting data from the table RazorpayPgMaster
     * | 
     * | Rating :
     * | Time :
     */
    public function getPgDetails(Request $req)
    {
        try {
            $mReadRazorpay = PaymentGatewayDetail::select(
                'payment_gateway_details.pg_name AS paymentGatewayName',
                'payment_gateway_details.pg_details AS details'
            )
                ->join('payment_gateway_masters', 'payment_gateway_masters.id', '=', 'payment_gateway_details.id')
                ->join('department_pg_maps', 'department_pg_maps.pg_id', '=', 'payment_gateway_masters.id')
                ->join('ulb_department_maps', 'ulb_department_maps.department_id', '=', 'department_pg_maps.department_id')

                ->where('ulb_department_maps.department_id', $req->departmentId)
                ->where('ulb_department_maps.ulb_id', $req->ulbId)
                ->where('payment_gateway_masters.id', $req->paymentGatewayId)
                ->get();
            if (!empty($mReadRazorpay['0'])) {
                return responseMsgs(true, "Razorpay Data!", $mReadRazorpay);
            }

            return responseMsgs(false, "Data Not found", "");
        } catch (Exception $error) {
            return responseMsgs(false, "error", $error->getMessage());
        }
    }


    /**
     * | Get Payment details by readind the webhook table
     * | @var mReadPayment : collect webhook payment details
     * | @return mCollection
     * | 
     * | Rating :
     * | Time :
        | Working
     */
    public function getWebhookDetails()
    {
        try {
            $mReadPayment =  WebhookPaymentData::select(
                'payment_transaction_id AS transactionNo',
                'payment_order_id AS orderId',
                'payment_id AS paymentId',
                'payment_amount AS amount',
                'payment_status AS status',
                'created_at AS date',
            )
                ->orderByDesc('id')
                ->get();

            $mCollection = collect($mReadPayment)->map(function ($value, $key) {
                $decode = WebhookPaymentData::select('payment_notes AS userDetails')
                    ->where('payment_id', $value['paymentId'])
                    ->where('payment_order_id', $value['orderId'])
                    ->where('payment_status', $value['status'])
                    ->get();
                $details = json_decode(collect($decode)->first()->userDetails);
                $value['userDetails'] = (object)$details;
                return $value;
            });
            return responseMsgs(true, "Data fetched!", $mCollection);
        } catch (Exception $error) {
            return responseMsgs(false, "Error listed below!", $error->getMessage());
        }
    }


    /**
     * | verifiying the payment success and the signature key
     * | @param requet request from the frontend
     * | @param error collecting the operation error
     * | @var mAttributes
     * | @var mVerification
        | Use for payment verificaton
        | $mVerification = $this->paymentVerify($request, $mAttributes); (Calling the trait function)
     */
    public function verifyPaymentStatus(Request $request)
    {

        # test code 
        $attributes     = null;
        $success        = false;
        $refRazorpayId  = Config::get('razorpay.RAZORPAY_ID');
        $refRazorpayKey = Config::get('razorpay.RAZORPAY_KEY');

        $refPaymentId = $request->razorpayPaymentId;
        $api = new Api($refRazorpayKey, $refRazorpayId);
        $paymentId = $_POST['razorpayPaymentId'];
        $payment = $api->payment->fetch($paymentId);
        if ($payment->status === 'captured') {
            return  $payment;
        } else {
            return $payment;
        }

        return false;


        # variable and model declaration 
        $attributes     = null;
        $success        = false;
        $refRazorpayId  = Config::get('razorpay.RAZORPAY_ID');
        $refRazorpayKey = Config::get('razorpay.RAZORPAY_KEY');

        # verify the existence of the razerpay Id
        try {
            $api = new Api($refRazorpayId, $refRazorpayKey);
            $attributes = [
                'razorpay_order_id'     => $request->razorpayOrderId,
                'razorpay_payment_id'   => $request->razorpayPaymentId,
                'razorpay_signature'    => $request->razorpaySignature
            ];
            $api->utility->verifyPaymentSignature($attributes);
            $success = true;
        } catch (SignatureVerificationError $exception) {
            $success = false;
            $messsage = $exception->getMessage();
        }

        try {
            if ($success === true) {
                # Check the webhook transaction data
                $messsage = "Payment Successfully done!";
                return responseMsgs(true, $messsage, [], "", "01", "", "POST", $request->deviceId);
            } else {
                # Update database with error data
                return responseMsgs(false, $messsage, [], "", "01", "", "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "04", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | ----------------------------------- payment Gateway ENDS -------------------------------
     * | collecting the data provided by the webhook in database
     * | @param requet request from the frontend
     * | @param error collecting the operation error
     * | @var mAttributes
     * | @var mVerification
     * |
     * | Rating :
     * | Time :
        | Working
     */
    public function gettingWebhookDetails(Request $request)
    {
        try {
            # creating json of webhook data
            $paymentId = $request->payload['payment']['entity']['id'];
            Storage::disk('public')->put($paymentId . '.json', json_encode($request->all()));

            if (!empty($request)) {
                $mWebhookDetails = $this->collectWebhookDetails($request);
                return $mWebhookDetails;
            }
            return responseMsgs(false, "WEBHOOK DATA NOT ACCUIRED!", "");
        } catch (Exception $error) {
            return responseMsgs(false, "OPERATIONAL ERROR!", $error->getMessage());
        }
    }

    /**
     * | -------------------------------- integration of the webhook ------------------------------- |
     * | @param request
     * | 
     * | @return 
     * | Operation : this function url is hited by the webhook and the detail of the payment is collected in request 
     *               thie the storage -> generating pdf -> generating json ->save -> hitting url for watsapp message.
     * | Rating : 4
     * | this -> naming
     * | here -> variable
        | Serial No : 03
        | Flag : department Id will be replaced / switch case / the checking of the payment is success (keys:amount,orderid,departmentid,status) / razorpay verification 
     */
    public function collectWebhookDetails($request)
    {
        try {
            # Variable Defining Section
            $webhookEntity  = $request->payload['payment']['entity'];
            $contains       = json_encode($request->contains);
            $notes          = json_encode($webhookEntity['notes']);
            $depatmentId    = $webhookEntity['notes']['departmentId'];  // ModuleId
            $status         = $webhookEntity['status'];
            $captured       = $webhookEntity['captured'];
            $aCard          = $webhookEntity['card_id'];
            $amount         = $webhookEntity['amount'];
            $arrayInAquirer = $webhookEntity['acquirer_data'];

            $actulaAmount = $amount / 100;
            $firstKey = array_key_first($arrayInAquirer);
            $actualTransactionNo = $this->generatingTransactionId($webhookEntity['notes']['ulbId']);

            # Save card details 
            // if (!is_null($aCard)) {
            //     $webhookCardDetails = $webhookEntity['card'];
            //     $objcard = new CardDetail();
            //     $objcard->saveCardDetails($webhookCardDetails);
            // }

            # Data to be stored in webhook table
            $webhookData = new WebhookPaymentData();
            $refWebhookDetails = $webhookData->getWebhookRecord($request, $captured, $webhookEntity, $status)->first();
            if (is_null($refWebhookDetails)) {
                $webhookData = $webhookData->saveWebhookData($request, $captured, $actulaAmount, $status, $notes, $firstKey, $contains, $actualTransactionNo, $webhookEntity);
            }
            # data transfer to the respective module's database 
            $transfer = [
                'paymentMode'   => $webhookData->payment_method,
                'id'            => $webhookEntity['notes']['applicationId'],
                'amount'        => $actulaAmount,
                'workflowId'    => $webhookData->workflow_id,
                'transactionNo' => $actualTransactionNo,
                'userId'        => $webhookData->user_id,
                'ulbId'         => $webhookData->ulb_id,
                'departmentId'  => $webhookData->department_id,         //ModuleId
                'orderId'       => $webhookData->payment_order_id,
                'paymentId'     => $webhookData->payment_id,
                'tranDate'      => $request->created_at,
                'gatewayType'   => 1,                                   // Razorpay Id
            ];

            # conditionaly upadting the request data
            if ($status == 'captured' && $captured == 1) {
                PaymentRequest::where('razorpay_order_id', $webhookEntity['order_id'])
                    ->update(['payment_status' => 1]);

                # calling function for the modules                  
                switch ($depatmentId) {
                    // case ('1'):
                    //     $refpropertyType = $webhookEntity['notes']['workflowId'];
                    //     if ($refpropertyType == 0) {
                    //         $objHoldingTaxController = new HoldingTaxController($this->_safRepo);
                    //         $transfer = new ReqPayment($transfer);
                    //         $objHoldingTaxController->paymentHolding($transfer);
                    //     } else {                                            //<------------------ (SAF PAYMENT)
                    //         $obj = new ActiveSafController($this->_safRepo);
                    //         $transfer = new ReqPayment($transfer);
                    //         $obj->paymentSaf($transfer);
                    //     }
                    //     break;
                    // case ('2'):                                             //<------------------ (Water)
                    //     $objWater = new WaterNewConnection();
                    //     $data = $objWater->razorPayResponse($transfer);
                    //     $data;
                    //     break;
                    // case ('3'):                                             //<------------------ (TRADE)
                    //     $objTrade = new TradeCitizen();
                    //     $objTrade->razorPayResponse($transfer);
                    //     break;
                    // case ('5'):                                             //<------------------ (Advertisment) 
                    //     $adv = 76;                                                          // Static
                    //     $mApiMaster = new ApiMaster();
                    //     $api = $mApiMaster->getApiEndpoint($adv);
                    //     Http::withHeaders([])
                    //         ->post("$api->end_point", $transfer);
                    //     break;
                    // case ('9'):
                    //     $pet = 80;
                    //     $mApiMaster = new ApiMaster();
                    //     $petApi = $mApiMaster->getApiEndpoint($pet);
                    //     $petRequest = Http::withHeaders([])
                    //         ->post("$petApi->end_point", $transfer);
                    //     $petRequest;
                    //     break;
                    case ('11'):
                        $waterTanker = 77;
                        $mApiMaster = new ApiMaster();
                        $petApi = $mApiMaster->getApiEndpoint($waterTanker);
                        Http::withHeaders([])
                            ->post("$petApi->end_point", $transfer);
                        break;
                    // case ('12'):
                    //     $waterTanker = 78;
                    //     $mApiMaster = new ApiMaster();
                    //     $petApi = $mApiMaster->getApiEndpoint($waterTanker);
                    //     Http::withHeaders([])
                    //         ->post("$petApi->end_point", $transfer);
                    //     break;
                    // case ('10'):                                            //<-------------------(Marriage)
                    //     $marriage = 29;
                    //     $mApiMaster = new ApiMaster();
                    //     $marriageApi = $mApiMaster->getApiEndpoint($marriage);
                    //     $details = Http::withHeaders([])
                    //         ->post("$marriageApi->end_point", $transfer);
                    //     $details;
                    //     break;
                }
            }
            return responseMsgs(true, "Webhook Data Collected!", $request->event);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getLine());
        }
    }

    /**
     * | ------------------------ generating Application ID ------------------------------- |
     * | @param request
     * | @var id
     * | @return idDetails
     * | Operation : this function generate a random and unique transactionID
     * | Rating : 1
        | Serial No : 03.1
     */
    public function generatingTransactionId($ulbId)
    {
        $tranParamId = Config::get("constants.PARAM_IDS");
        $idGeneration = new PrefixIdGenerator($tranParamId['TRN'], $ulbId);
        $transactionNo = $idGeneration->generate();
        $transactionNo = str_replace('/', '-', $transactionNo);
        return $transactionNo;
    }

    /**
     * | ------------- geting details of the transaction according to the orderId, paymentId and payment status --------------|
     * | @param requet request from the frontend
     * | @param error collecting the operation error
     * | @var mReadTransactions
     * | @var mCollection
     * |
     * | Rating :
     * | Time:
        | Working
     */
    public function getTransactionNoDetails(Request $request)
    {
        try {
            $objWebhookData = new WebhookPaymentData();
            $mReadTransactions = $objWebhookData->webhookByTransaction($request)
                ->get();

            $mCollection = collect($mReadTransactions)->map(function ($value, $key) {
                $decode = WebhookPaymentData::select('payment_notes AS userDetails')
                    ->where('payment_id', $value['paymentId'])
                    ->where('payment_order_id', $value['orderId'])
                    ->where('payment_status', $value['status'])
                    ->get();
                $details = json_decode(collect($decode)->first()->userDetails);
                $value['userDetails'] = $details;
                return $value;
            });
            if (empty(collect($mCollection)->first())) {
                return responseMsgs(false, "data not found!", "");
            }
            return responseMsgs(true, "Data fetched!", remove_null(collect($mCollection)->first()), "", "02", "618.ms", "POST", $request->deviceId);
        } catch (Exception $error) {
            return responseMsgs(false, "Error listed below!", $error->getMessage());
        }
    }


    /**
     * | --------------------------- Payment Reconciliation (/1.6) ------------------------------- |
     * | @param request
     * | @param error
     * | @var reconciliation
     * | Operation :  Payment Reconciliation / viewing all data
     * | 
     * | Rating :
     * | Time : 
        | Working
     */
    // public function getReconcillationDetails()
    // {
    //     try {
    //         $reconciliation = new PaymentReconciliation();
    //         $reconciliation = $reconciliation->allReconciliationDetails()
    //             ->get();
    //         return responseMsgs(true, "Payment Reconciliation data!", $reconciliation);
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }



    /**
     * | -------------------- Payment Reconciliation details acoording to request details (1)------------------------------- |
     * | @param request
     * | @param error
     * | @var reconciliationTypeWise
     * | @var reconciliationModeWise
     * | @var reconciliationDateWise
     * | @var reconciliationOnlyTranWise
     * | @var reconciliationWithAll
     * | Operation :  Payment Reconciliation / searching for the specific data
     * |
     * | Rating : 
     * | Time :
        | Working
     */
    // public function searchReconciliationDetails($request)
    // {
    //     if (empty($request->fromDate) && empty($request->toDate) && null == ($request->chequeDdNo)) {
    //         return $this->getReconcillationDetails();
    //     }

    //     switch ($request) {
    //         case (null == ($request->chequeDdNo) && !null == ($request->verificationType) && null == ($request->paymentMode)): {
    //                 $reconciliationTypeWise = $this->reconciliationTypeWise($request);
    //                 return $reconciliationTypeWise;
    //             }
    //             break;
    //         case (null == ($request->chequeDdNo) && null == ($request->verificationType) && !null == ($request->paymentMode)): {
    //                 $reconciliationModeWise = $this->reconciliationModeWise($request);
    //                 return $reconciliationModeWise;
    //             }
    //             break;
    //         case (null == ($request->chequeDdNo) && null == ($request->verificationType) && null == ($request->paymentMode)): {
    //                 $reconciliationDateWise = $this->reconciliationDateWise($request);
    //                 return $reconciliationDateWise;
    //             }
    //             break;
    //         case (!null == ($request->chequeDdNo) && null == ($request->verificationType) && null == ($request->paymentMode)): {
    //                 $reconciliationOnlyTranWise = $this->reconciliationOnlyTranWise($request);
    //                 return $reconciliationOnlyTranWise;
    //             }
    //             break;
    //         case (!null == ($request->chequeDdNo) && !null == ($request->verificationType) && !null == ($request->paymentMode) && !null == ($request->fromDate)): {
    //                 $reconciliationWithAll = $this->reconciliationWithAll($request);
    //                 return $reconciliationWithAll;
    //             }
    //             break;
    //         case (null == ($request->chequeDdNo) && !null == ($request->verificationType) && !null == ($request->paymentMode)): {
    //                 $reconciliationModeType = $this->reconciliationModeType($request);
    //                 return $reconciliationModeType;
    //             }
    //             break;
    //         default:
    //             return ("Some Error try again !");
    //     }
    // }


    /**
     * | --------------------------- UPDATING Payment Reconciliation details ------------------------------- |
     * | @param request
     * | @param error
     * | @var reconciliation
     * | Operation :  Payment Reconciliation / updating the data of the payment Recou..
     * | 
     * | Rating :
     * | Time :
        | Flag move to model
     */
    // public function updateReconciliationDetails($request)
    // {
    //     try {
    //         PaymentReconciliation::where('transaction_no', $request->transactionNo)
    //             ->update([
    //                 'status' => $request->status,
    //                 'date' => $request->date,
    //                 'remark' => $request->reason,
    //                 'cancellation_charges' => $request->cancellationCharges
    //             ]);
    //         return responseMsgs(true, "Data Saved!", "");
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }

    #____________________________________(Search Reconciliation - START)___________________________________________#

    /**
     * |--------- reconciliationDateWise 1.1----------
     * |@param request
     * |@var reconciliationDetails
     */
    // public function reconciliationDateWise($request)
    // {
    //     $reconciliationDetails = PaymentReconciliation::select(
    //         'ulb_id AS ulbId',
    //         'department_id AS dpartmentId',
    //         'transaction_no AS transactionNo',
    //         'payment_mode AS paymentMode',
    //         'date AS transactionDate',
    //         'status',
    //         'cheque_no AS chequeNo',
    //         'cheque_date AS chequeDate',
    //         'branch_name AS branchName',
    //         'bank_name AS bankName',
    //         'transaction_amount AS amount',
    //         'clearance_date AS clearanceDate'
    //     )
    //         ->whereBetween('date', [$request->fromDate, $request->toDate])
    //         ->get();

    //     if (!empty(collect($reconciliationDetails)->first())) {
    //         return responseMsgs(true, "Data Acording to request!", $reconciliationDetails);
    //     }
    //     return responseMsgs(false, "data not found!", "");
    // }

    /**
     * |--------- reconciliationModeWise 1.2----------
     * |@param request
     * |@var reconciliationDetails
     */
    // public function reconciliationModeWise($request)
    // {
    //     try {
    //         $reconciliationDetails = PaymentReconciliation::select(
    //             'ulb_id AS ulbId',
    //             'department_id AS dpartmentId',
    //             'transaction_no AS transactionNo',
    //             'payment_mode AS paymentMode',
    //             'date AS transactionDate',
    //             'status',
    //             'cheque_no AS chequeNo',
    //             'cheque_date AS chequeDate',
    //             'branch_name AS branchName',
    //             'bank_name AS bankName',
    //             'transaction_amount AS amount',
    //             'clearance_date AS clearanceDate'
    //         )
    //             ->whereBetween('date', [$request->fromDate, $request->toDate])
    //             ->where('payment_mode', $request->paymentMode)
    //             ->get();

    //         if (!empty(collect($reconciliationDetails)->first())) {
    //             return responseMsgs(true, "Data Acording to request!", $reconciliationDetails);
    //         }
    //         return responseMsgs(false, "data not found!", "");
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }

    /**
     * |--------- reconciliationTypeWise 1.3----------
     * |@param request
     * |@var reconciliationDetails
     */
    // public function reconciliationTypeWise($request)
    // {
    //     try {
    //         $reconciliationDetails = PaymentReconciliation::select(
    //             'ulb_id AS ulbId',
    //             'department_id AS dpartmentId',
    //             'transaction_no AS transactionNo',
    //             'payment_mode AS paymentMode',
    //             'date AS transactionDate',
    //             'status',
    //             'cheque_no AS chequeNo',
    //             'cheque_date AS chequeDate',
    //             'branch_name AS branchName',
    //             'bank_name AS bankName',
    //             'transaction_amount AS amount',
    //             'clearance_date AS clearanceDate'
    //         )
    //             ->whereBetween('date', [$request->fromDate, $request->toDate])
    //             ->where('status', $request->verificationType)
    //             ->get();

    //         if (!empty(collect($reconciliationDetails)->first())) {
    //             return responseMsgs(true, "Data Acording to request!", $reconciliationDetails);
    //         }
    //         return responseMsgs(false, "data not found!", "");
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }

    /**
     * |--------- reconciliationOnlyTranWise 1.4-------
     * |@param request
     * |@var reconciliationDetails
     */
    // public function reconciliationOnlyTranWise($request)
    // {
    //     try {
    //         $reconciliationDetails = PaymentReconciliation::select(
    //             'ulb_id AS ulbId',
    //             'department_id AS dpartmentId',
    //             'transaction_no AS transactionNo',
    //             'payment_mode AS paymentMode',
    //             'date AS transactionDate',
    //             'status',
    //             'cheque_no AS chequeNo',
    //             'cheque_date AS chequeDate',
    //             'branch_name AS branchName',
    //             'bank_name AS bankName',
    //             'transaction_amount AS amount',
    //             'clearance_date AS clearanceDate'
    //         )
    //             ->where('cheque_no', $request->chequeDdNo)
    //             ->get();

    //         if (!empty(collect($reconciliationDetails)->first())) {
    //             return responseMsgs(true, "Data Acording to request!", $reconciliationDetails);
    //         }
    //         return responseMsgs(false, "data not found!", "");
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }

    /**
     * |--------- reconciliationOnlyTranWise 1.5--------
     * |@param request
     * |@var reconciliationDetails
     */
    // public function reconciliationWithAll($request)
    // {
    //     try {
    //         $reconciliationDetails = PaymentReconciliation::select(
    //             'ulb_id AS ulbId',
    //             'department_id AS dpartmentId',
    //             'transaction_no AS transactionNo',
    //             'payment_mode AS paymentMode',
    //             'date AS transactionDate',
    //             'status',
    //             'cheque_no AS chequeNo',
    //             'cheque_date AS chequeDate',
    //             'branch_name AS branchName',
    //             'bank_name AS bankName',
    //             'transaction_amount AS amount',
    //             'clearance_date AS clearanceDate'
    //         )
    //             ->whereBetween('date', [$request->fromDate, $request->toDate])
    //             ->where('payment_mode', $request->paymentMode)
    //             ->where('status', $request->verificationType)
    //             ->where('cheque_no', $request->chequeDdNo)
    //             ->get();

    //         if (!empty(collect($reconciliationDetails)->first())) {
    //             return responseMsgs(true, "Data Acording to request!", $reconciliationDetails);
    //         }
    //         return responseMsgs(false, "data not found!", "");
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }

    /**
     * |--------- reconciliationDateWise 1.1----------
     * |@param request
     * |@var reconciliationDetails
     */
    // public function reconciliationModeType($request)
    // {
    //     try {
    //         $reconciliationDetails = PaymentReconciliation::select(
    //             'ulb_id AS ulbId',
    //             'department_id AS dpartmentId',
    //             'transaction_no AS transactionNo',
    //             'payment_mode AS paymentMode',
    //             'date AS transactionDate',
    //             'status',
    //             'cheque_no AS chequeNo',
    //             'cheque_date AS chequeDate',
    //             'branch_name AS branchName',
    //             'bank_name AS bankName',
    //             'transaction_amount AS amount',
    //             'clearance_date AS clearanceDate'
    //         )
    //             ->whereBetween('date', [$request->fromDate, $request->toDate])
    //             ->where('payment_mode', $request->paymentMode)
    //             ->where('status', $request->verificationType)
    //             ->get();

    //         if (!empty(collect($reconciliationDetails)->first())) {
    //             return responseMsgs(true, "Data Acording to request!", $reconciliationDetails);
    //         }
    //         return responseMsgs(false, "data not found!", "");
    //     } catch (Exception $error) {
    //         return responseMsgs(false, "ERROR!", $error->getMessage());
    //     }
    // }

    #________________________________________(END)_________________________________________#

    /**
     * |--------- all the transaction details regardless of module ----------|
     * |@var object webhookModel
     * |@var transaction
     * |@var userId
     */
    public function allModuleTransaction()
    {
        try {
            $userId = auth()->user()->id;
            $transaction = WebhookPaymentData::select(
                'webhook_payment_data.payment_transaction_id AS transactionNo',
                'webhook_payment_data.created_at AS dateOfTransaction',
                'webhook_payment_data.payment_method AS paymentMethod',
                'webhook_payment_data.payment_amount AS amount',
                'webhook_payment_data.payment_status AS paymentStatus',
                'department_masters.department_name AS modueName'
            )
                ->join('department_masters', 'department_masters.id', '=', 'webhook_payment_data.department_id')
                ->where('user_id', $userId)
                ->get();
            if (!empty(collect($transaction)->first())) {
                return responseMsgs(true, "All transaction for the respective id", $transaction);
            }
            return responseMsgs(false, "No Data!", "");
        } catch (Exception $error) {
            return responseMsgs(false, "", $error->getMessage());
        }
    }

    /**
        | function to be used for the payment reconcilation
     */
    public function searchTransaction($request)
    {
        // $typeWise = new Model();
        // switch ($request) {
        //     case (null == ($request->chequeDdNo) && !null == ($request->verificationType) && null == ($request->paymentMode)): {
        //             $reconciliationTypeWise = $typeWise->transactionTypeWise($request);
        //             return $reconciliationTypeWise;
        //         }
        //         break;
        //     case (null == ($request->chequeDdNo) && null == ($request->verificationType) && !null == ($request->paymentMode)): {
        //             $reconciliationModeWise = $typeWise->transactionModeWise($request);
        //             return $reconciliationModeWise;
        //         }
        //         break;
        //     case (null == ($request->chequeDdNo) && null == ($request->verificationType) && null == ($request->paymentMode)): {
        //             $reconciliationDateWise = $typeWise->transactionDateWise($request);
        //             return $reconciliationDateWise;
        //         }
        //         break;
        //     case (!null == ($request->chequeDdNo) && null == ($request->verificationType) && null == ($request->paymentMode)): {
        //             $reconciliationOnlyTranWise = $typeWise->transactionOnlyTranWise($request);
        //             return $reconciliationOnlyTranWise;
        //         }
        //         break;
        //     case (!null == ($request->chequeDdNo) && !null == ($request->verificationType) && !null == ($request->paymentMode)): {
        //             $reconciliationWithAll = $typeWise->transactionWithAll($request);
        //             return $reconciliationWithAll;
        //         }
        //         break;
        //     case (null == ($request->chequeDdNo) && !null == ($request->verificationType) && !null == ($request->paymentMode)): {
        //             $reconciliationModeType = $typeWise->transactionModeType($request);
        //             return $reconciliationModeType;
        //         }
        //         break;
        //     default:
        //         return ("Some Error try again !");
        // }
    }
}


# Conversion of epoch time in human readable time
// $epoch = 1673593047;
// $dt = new DateTime("@$epoch");  
// return  $dt->format('Y-m-d H:i:s');