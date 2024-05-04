<?php

namespace App\Repository\Payment\Interfaces;

use Illuminate\Http\Request;

/**
 * | Created On-14
 * | Created By-sam kerketta
 * | Interface for the Eloquent Repostory for PaymentRepository
 */

interface iPayment
{
    # payment Gateway (RAZORPAY/Property)
    public function getDepartmentByulb(Request $req);                               //01
    public function getPaymentgatewayByrequests(Request $req);                      //02
    public function getPgDetails(Request $req);                                     //03
    public function getWebhookDetails();                                            //04
    public function verifyPaymentStatus(Request $request);                          // 05
    public function gettingWebhookDetails(Request $request);                        // 06
    public function getTransactionNoDetails(Request $request);                      // 07

    # Payment Reconciliation
    // public function getReconcillationDetails();                                     // 08
    // public function searchReconciliationDetails($request);                          // 09
    // public function updateReconciliationDetails($request);                          // 10
    // public function searchTransaction($request);                                    // 11  Flag

    # Common Payments details
    public function allModuleTransaction();                                         // 11
}
