<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/**
 * | Routers for Water Tanker
 * | Created On - 23 May 2023 
 * | Created By- Bikash Kumar
 * | Status - Open
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::group(['middleware' => ['auth.citizen', 'json.response', 'expireBearerToken']], function () {

});
Route::controller(SelfAdvetController::class)->group(function () {
    Route::post('advert/self/add-new', 'addNew');       // 01 ( Save Application )
    Route::post('advert/self/get-application-details-for-renew', 'applicationDetailsForRenew');       // 02 ( Renew Application )
    Route::post('advert/self/renewal-selfAdvt', 'renewalSelfAdvt');       // 03 ( Renew Application )
    Route::post('advert/self/list-self-advt-category', 'listSelfAdvtCategory');       // 04 ( Save Application )
    Route::post('advert/self/list-inbox', 'listInbox');      // 05 ( Application Inbox Lists )
    Route::post('advert/self/list-outbox', 'listOutbox');    // 06 ( Application Outbox Lists )
    Route::post('advert/self/get-details-by-id', 'getDetailsById');  // 07 ( Get Application Details By Application ID )
    Route::post('advert/self/list-applied-applications', 'listAppliedApplications');     // 08 ( Get Applied Applications List By CityZen )
    Route::post('advert/self/escalate-application', 'escalateApplication');  // 09 ( Escalate or De-escalate Application )
    Route::post('advert/self/list-escalated', 'listEscalated');  // 10 ( Special Inbox Applications )
    Route::post('advert/self/forward-next-level', 'forwordNextLevel');  // 11 ( Forward or Backward Application )
    Route::post('advert/self/comment-application', 'commentApplication');  // 12 ( Independent Comment )
    Route::post('advert/self/get-license-by-id', 'getLicenseById');  // 13 ( Get License By User ID )
    Route::post('advert/self/get-license-by-holding-no', 'getLicenseByHoldingNo');  // 14 ( Get License By Holding No )
    // Route::post('advert/self/get-license-details-by-license-no', 'getLicenseDetailso');  // 12 ( Get License Details By Licence No )
    Route::post('advert/self/view-advert-document', 'viewAdvertDocument');  // 15 ( Get Uploaded Document By Advertisement ID )
    Route::post('advert/self/view-active-document', 'viewActiveDocument');  // 16 ( Get Uploaded Document By Advertisement ID )
    Route::post('advert/self/get-details-by-license-no', 'getDetailsByLicenseNo');  // 17 ( Get Uploaded Document By Advertisement ID )
    Route::post('advert/self/view-documents-on-workflow', 'viewDocumentsOnWorkflow');  // 18 ( View Uploaded Document By Advertisement ID )
    // Route::post('advert/self/workflow-upload-document', 'workflowUploadDocument');  // 16 ( Workflow Upload Document )
    Route::post('advert/self/approved-or-reject', 'approvalOrRejection');          // 19 ( Approve or Reject )
    Route::post('advert/self/list-approved', 'listApproved');          // 20 ( Approved list for Citizen)
    Route::post('advert/self/list-rejected', 'listRejected');          // 21 ( Rejected list for Citizen)
    Route::post('advert/self/get-jsk-applications', 'getJSKApplications');          // 22 ( Get Applied Applications List By JSK )
    Route::post('advert/self/list-jsk-approved-application', 'listJskApprovedApplication');          // 23 ( Approved list for JSK)
    Route::post('advert/self/list-jsk-rejected-application', 'listJskRejectedApplication');          // 24 ( Rejected list for JSK)    
    Route::post('advert/self/generate-payment-order-id', 'generatePaymentOrderId');          // 25 ( Generate Payment Order ID)
    Route::post('advert/self/get-application-details-for-payment', 'applicationDetailsForPayment');          // 26 ( Application Details For Payments )
    // Route::post('advert/self/get-payment-details', 'getPaymentDetails');          // 19 ( Payments Details )
    Route::post('advert/self/payment-by-cash', 'paymentByCash');          // 27 ( Payment via Cash )
    Route::post('advert/self/entry-cheque-dd', 'entryChequeDd');          // 28 ( Entry Cheque or DD For Payments )
    Route::post('advert/self/clear-or-bounce-cheque', 'clearOrBounceCheque');          // 29 ( Clear Cheque or DD )
    Route::post('advert/self/verify-or-reject-doc', 'verifyOrRejectDoc');          // 30 ( Verify or Reject Document )
    Route::post('advert/self/back-to-citizen', 'backToCitizen');          // 31 ( Application Back to Citizen )
    Route::post('advert/self/list-btc-inbox', 'listBtcInbox');          // 32 ( list Back to citizen )
    // Route::post('advert/self/check-full-upload', 'checkFullUpload');          // 19 ( Application Details For Payments )
    Route::post('advert/self/reupload-document', 'reuploadDocument');          // 33 ( Reupload Rejected Document )
    Route::post('advert/self/search-by-name-or-mobile', 'searchByNameorMobile');          //34 ( Search application by name and mobile no )
    Route::post('advert/self/get-application-between-date', 'getApplicationBetweenDate');          //35 ( Get Application Between two date )
    Route::post('advert/self/get-application-financial-year-wise', 'getApplicationFinancialYearWise');          //36 ( Get Application Financial Year Wise )
    Route::post('advert/self/get-application-display-wise', 'getApplicationDisplayWise');          //37 ( Get Application Financial Year Wise )
    Route::post('advert/self/payment-collection', 'paymentCollection');          //38 ( Get Application Financial Year Wise )
});

