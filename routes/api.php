<?php

use App\Http\Controllers\CashVerificationController;
use App\Http\Controllers\SepticTankController;
use App\Http\Controllers\SepticTankerReportController;
use App\Http\Controllers\WaterTankerController;
use App\Http\Controllers\WaterTankerReportController;
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
 * | Routes for Water Tanker/Septic Tanker
 * | Created On - 23 May 2023 
 * | Created By - Bikash Kumar
 * | Module - 11 (Water Tanker/Septic Tank)
 * | Status - Closed By Bikash Kumar ( 29 Sep 2023 )
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['auth.citizen', 'json.response', 'expireBearerToken']], function () {
});

Route::post('water-tanker/payment-success-or-failure', [WaterTankerController::class, 'paymentSuccessOrFailure']);            // 68 ( Update Payment Status After Update )
Route::get('water-tanker/get-water-tanker-reciept/{tranId}', [WaterTankerController::class,'getWaterTankerReciept']);          // 69 ( Get Payment Reciept For Water Tanker )  
Route::get('septic-tanker/get-septic-tanker-reciept/{tranId}', [SepticTankController::class, 'getRecieptDetailsByPaymentId']); // 35 ( Get Payment Reciept For Septic Tanker )  

Route::group(['middleware' => ['checkToken']], function () {

    /**
     * | Controller - 01
     * | Created By - Bikash Kumar
     * | Status - Closed By Bikash Kumar ( 29 Sep 2023 )
     * | Module Id - 11 (Water Tanker)
     */
    Route::group(['middleware' => ["auth_maker"]], function () {
        Route::controller(WaterTankerController::class)->group(function () {
            Route::post('water-tanker/add-agency', 'addAgency');                                                                    // 01 ( Add Agency )
            Route::post('water-tanker/list-agency', 'listAgency');                                                                  // 02 ( List Agency )
            Route::post('water-tanker/add-capacity', 'addCapacity');                                                                // 03 ( Add Capacity )
            Route::post('water-tanker/list-capacity', 'listCapacity');                                                              // 04 ( List Capacity )
            Route::post('water-tanker/add-capacity-rate', 'addCapacityRate');                                                       // 05 ( Add Capacity Rate )
            Route::post('water-tanker/list-capacity-rate', 'listCapacityRate');                                                     // 06 ( List Capacity Rate )
            Route::post('water-tanker/add-hydration-center', 'addHydrationCenter');                                                 // 07 ( Add Hydration Center )
            Route::post('water-tanker/list-hydration-center', 'listHydrationCenter');                                               // 08 ( List Hydration Center )
            Route::post('water-tanker/add-driver', 'addDriver');                                                                    // 09 ( Add Driver )
            Route::post('water-tanker/list-driver', 'listDriver');                                                                  // 10 ( List Driver )
            Route::post('water-tanker/add-resource', 'addResource');                                                                // 11 ( Add Resource )
            Route::post('water-tanker/list-resource', 'listResource');                                                              // 12 ( List Resource )
            Route::post('water-tanker/add-hydration-center-dispatch-log', 'addHydrationCenerDispatchLog');                          // 13 ( Add Hydration Center Dispatch Logs )
            Route::post('water-tanker/list-hydration-center-dispatch-log', 'listHydrationCenerDispatchLog');                        // 14 ( List Hydration Center Dispatch Logs )
            Route::post('water-tanker/add-booking', 'addBooking');                                                                  // 15 ( Add Bookings )
            Route::post('water-tanker/list-agency-booking', 'listAgencyBooking');                                                   // 16 ( list Agency Bookings )  
            Route::post('water-tanker/map-driver-vehicle', 'mapDriverVehicle');                                                     // 17 ( Map Driver Vehicle Maps )  
            Route::post('water-tanker/list-map-driver-vehicle', 'listMapDriverVehicle');                                            // 18 ( List Map Driver Vehicle Maps )  
            Route::post('water-tanker/cancel-booking', 'cancelBooking');                                                            // 19 ( Cancel Booking )  
            Route::post('water-tanker/list-cancel-booking', 'listCancelBooking');                                                   // 20 ( List Cancel Booking )  
            Route::post('water-tanker/refund-booking', 'refundBooking');                                                            // 21 ( Refund Booking )  
            Route::post('water-tanker/list-refund-booking', 'listRefundBooking');                                                   // 22 ( List Refund Booking )  
            Route::post('water-tanker/list-ulb-booking', 'listUlbBooking');                                                         // 23 ( List ULB Booking )  
            Route::post('water-tanker/edit-agency', 'editAgency');                                                                  // 24 ( Edit Agency )  
            Route::post('water-tanker/edit-hydration-center', 'editHydrationCenter');                                               // 25 ( Edit Hydration Center )  
            Route::post('water-tanker/edit-resource', 'editResource');                                                              // 26 ( Edit Resource ) 
            Route::post('water-tanker/edit-capacity', 'editCapacity');                                                              // 27 ( Edit Resource ) 
            Route::post('water-tanker/edit-capacity-rate', 'editCapacityRate');                                                     // 28 ( Edit Capacity Rate ) 
            Route::post('water-tanker/edit-driver', 'editDriver');                                                                  // 29 ( Edit Driver )
            Route::post('water-tanker/get-agency-details-by-id', 'getAgencyDetailsById');                                           // 30 ( Get Agency Details By Id )
            Route::post('water-tanker/get-hydration-center-details-by-id', 'getHydrationCenterDetailsById');                        // 31 ( Get Agency Details By Id )
            Route::post('water-tanker/get-resource-details-by-id', 'getResourceDetailsById');                                       // 32 ( Get Agency Details By Id )
            Route::post('water-tanker/get-capacity-details-by-id', 'getCapacityDetailsById');                                       // 33 ( Get Capacity Details By Id )
            Route::post('water-tanker/get-capacity-rate-details-by-id', 'getCapacityRateDetailsById');                              // 34 ( Get Capacity Rate Details By Id ) 
            Route::post('water-tanker/get-driver-details-by-id', 'getdriverDetailsById');                                           // 35 ( Get Driver Details By Id )  
            Route::post('water-tanker/list-driver-vehicle-for-assign', 'listDriverVehicleForAssign');                               // 36 ( Get Driver Details By Id ) 
            Route::post('water-tanker/get-driver-vehicle-map-by-id', 'getDriverVehicleMapById');                                    // 37 ( Get Map Driver & Vehicle Details By Id ) 
            Route::post('water-tanker/edit-driver-vehicle-map', 'editDriverVehicleMap');                                            // 38 ( Update Map Driver & Vehicle Details By Id ) 
            Route::post('water-tanker/booking-assignment', 'bookingAssignment');                                                    // 39 ( Assignment Booking for delivery in Hydartion Center, Vehicle Driver Map &  ) 
            Route::post('water-tanker/list-assign-agency', 'listAssignAgency');                                                     // 40 ( Agency Assign List ) 
            Route::post('water-tanker/list-assign-hydration-center', 'listAssignHydrationCenter');                                  // 41 ( Hydration Center Assign List ) 
            Route::post('water-tanker/add-location', 'addLocation');                                                                // 42 ( Add Location in Master ) 
            Route::post('water-tanker/list-location', 'listLocation');                                                              // 43 ( List Locations ) 
            Route::post('water-tanker/get-location-details-by-id', 'getLocationDetailsById');                                       // 44 ( Get Location Details By Id ) 
            Route::post('water-tanker/edit-location', 'editLocation');                                                              // 45 ( Edit Location ) 
            Route::post('water-tanker/add-location-hydration-map', 'addLocationHydrationMap');                                      // 46 ( Add Location Hydration Map in Master ) 
            Route::post('water-tanker/list-location-hydration-map', 'listLocationHydrationMap');                                    // 47 ( List Location Hydration Map ) 
            Route::post('water-tanker/get-location-hydration-map-details-by-id', 'getLocationHydrationMapDetailsById');             // 48 ( Get Location Hydration Map Details By Id ) 
            Route::post('water-tanker/edit-location-hydration-map', 'editLocationHydrationMap');                                    // 49 ( Edit Location Hydration Map ) 
            Route::post('water-tanker/get-booking-details-by-id', 'getBookingDetailById');                                          // 50 ( Get Booking Details By Id ) 
            Route::post('water-tanker/re-assign-booking', 'reassignBooking');                                                       // 51 ( Re-Assign Booking ) 
            Route::post('water-tanker/list-re-assign-booking', 'listReassignBooking');                                              // 52 ( List Re-Assign Booking ) 
            Route::post('water-tanker/list-ulb-wise-agency', 'listUlbWiseAgency');                                                  // 53 ( List Ulb Wise Agency ) 
            Route::post('water-tanker/get-payment-details-by-id', 'getPaymentDetailsById');                                         // 54 ( Get Payment Details By Id ) 
            Route::post('water-tanker/get-applied-application', 'getappliedApplication');                                           // 55 ( Get Applied Application ) 
            Route::post('water-tanker/sent-vehicle', 'sentVehicle');                                                                // 56 ( Sent Vehicle For Fill Water ) 
            Route::post('water-tanker/delivered-water', 'deliveredWater');                                                          // 57 ( Delivered Water ) 
            Route::post('water-tanker/wt-agency-dashboard', 'wtAgencyDashboard');                                                   // 58 ( Agency Dashboard ) 
            Route::post('water-tanker/list-delivered-booking', 'listDeliveredBooking');                                             // 59 ( List Delivered Booking ) 
            // Route::post('water-tanker/generate-qr', 'generateQRCode');                                                           // 59 ( Qr code generator ) 
            Route::post('water-tanker/list-ulb-wise-location', 'listUlbWiseLocation');                                              // 60 ( List Ulb Wise Location ) 
            Route::post('water-tanker/ulb-dashboard', 'ulbDashboard');                                                              // 61 ( Ulb Dashboard ) 
            Route::post('water-tanker/generate-payment-order-id', 'generatePaymentOrderId');                                        // 62 ( Generate Payment Order Id For Water Tanker Payment ) 
            Route::post('water-tanker/get-payment-details-by-pay-id', 'getPaymentDetailsByPaymentId');                              // 63 ( Get Payment Details By Payment Id )  
            Route::post('water-tanker/list-applied-and-cancelled-application', 'listAppliedAndCancelledApplication');               // 64 ( List Applied and cancelled applications ) 
            Route::post('water-tanker/re-assign-hydration-center', 'reAssignHydrationCenter');                                      // 65 ( Re-Assign Hydration Center ) 
            Route::post('water-tanker/list-ulb', 'ulbList');                                                                        // 66 ( Ulb List )
            Route::post('water-tanker/master-data', 'masterData');                                                                  // 67 ( Water Tanker Master Data )
            Route::post('water-tanker/water-tank-feedback', 'getFeedback');                                                         // 68 ( Get Feedback )  
            Route::post('water-tanker/check-feedback', 'checkFeedback');                                                            // 69 ( Check Feedback )  
            Route::post('water-tanker/booking-by-ulb', 'bookingByUlb');                                                             // 70 ( Water Tanker Book By ULB )  

            Route::post("water-tanker/driver-to-delivery-list","driverDeliveryList");
            Route::post("water-tanker/driver-update-delivery","updateDeliveryTrackStatus");
            Route::post("water-tanker/driver-cancel-list","driverCanceledList");
            Route::post("water-tanker/driver-updated-list","updatedListDeliveryByDriver");
            Route::post('water-tanker/vehicle-driver-master-ulb-wise', 'vehicleDriverMasterUlbWise');
            Route::post('water-tanker/offline-payment', 'offlinePayment');
            Route::post('water-tanker/search-booking', 'searchApp');
            //writen by prity pandey
            Route::post('water-tanker/collection-report', 'listCollection');
            Route::post('water-tanker/daily-collection-report', 'ReportDataWaterTanker');
            Route::post('water-tanker/application-report', 'applicationReportDataWaterTanker');
            Route::post('water-tanker/pending-report', 'pendingReportDataWaterTanker');
            Route::post('water-tanker/freeBooking', 'freeBookingWaterTanker');                  // Free Tanker Booking Functionality
            Route::post('water-tanker/freeBookingDocument', 'uploadBookingDocument');           // Free Tanker Booking Document Upload Functionality
            Route::post('water-tanker/verifyRejectDocByVerifier', 'verifyRejectDocByVerifier'); // Free Tanker Booking Document Verification by Verifier
            Route::post('water-tanker/freeSearchBooking', 'freeSearchBooking');
            Route::post('water-tanker/addFreeBooking', 'addFreeBooking');
            Route::post('water-tanker/freeSearchBookingVerifier', 'freeSearchBookingVerifier');
            Route::post('water-tanker/btcInbox', 'btcInbox');
        });

        Route::controller(WaterTankerReportController::class)->group(function () {
               
            Route::post('water-tanker/report/collection-user-wise', 'userWiseCollection');   
            Route::post('water-tanker/report/dashboard', 'dashBoard');  
            Route::post('water-tanker/report/dashboard-user-wise', 'userWishDashBoard'); 
            
            //written by Prity Pandey
            Route::post('water-tanker/cancle-booking', 'cancleBookingList');
        });

        /**
         * | Controller - 02
         * | Created By - Bikash Kumar
         * | Status - Closed By Bikash Kumar ( 29 Sep 2023 )
         * | Module Id - 11 (Septic Tanker)
         */
        Route::controller(SepticTankController::class)->group(function () {
            Route::post('septic-tanker/add-booking', 'addBooking');   
            /**
             * make by prity pandey
             */
            Route::post('septic-tanker/wt-agency-dashboard', 'stAgencyDashboard');                                                              // 01 ( Add Booking )
            Route::post('septic-tanker/list-booking', 'listBooking');                                                               // 02 ( List Booking )
            Route::post('septic-tanker/list-assigned-booking', 'listAssignedBooking');                                              // 03 ( List Booking )
            Route::post('septic-tanker/get-applied-application', 'getAppliedApplication');                                          // 04 ( Get Applied Application )
            Route::post('septic-tanker/assignment-booking', 'assignmentBooking');                                                   // 05 ( Driver & Vehicle Assign on Booking )
            Route::post('septic-tanker/cancel-booking', 'cancelBooking');                                                           // 06 ( Cancel Booking )
            Route::post('septic-tanker/list-cancel-booking', 'listCancelBooking');                                                  // 07 ( List Cancel Booking )
            Route::post('septic-tanker/get-application-details-by-id', 'getApplicationDetailsById');                                // 08 ( Get Application Details By Id )
            Route::post('septic-tanker/add-driver', 'addDriver');                                                                   // 09 ( Add Driver )
            Route::post('septic-tanker/list-driver', 'listDriver');                                                                 // 10 ( List Driver )
            Route::post('septic-tanker/get-driver-detail-by-id', 'getDriverDetailById');                                            // 11 ( Get Driver Details By ID )
            Route::post('septic-tanker/edit-driver', 'editDriver');                                                                 // 12 ( Update Driver Details)
            Route::post('septic-tanker/add-resource', 'addResource');                                                               // 13 ( Add Resource )
            Route::post('septic-tanker/list-resource', 'listResource');                                                             // 14 ( list Resource )
            Route::post('septic-tanker/get-resource-details-by-id', 'getResourceDetailsById');                                      // 15 ( Get Resource Details By Id )
            Route::post('septic-tanker/edit-resource', 'editResource');                                                             // 16 ( Edit Resource )
            Route::post('septic-tanker/vehicle-driver-master-ulb-wise', 'vehicleDriverMasterUlbWise');                              // 17 ( Vehicle Driver Master ULB Wise )
            Route::post('septic-tanker/septic-tank-cleaned', 'septicTankCleaned');                                                  // 18 ( Septic Tank Cleaned )
            Route::post('septic-tanker/septic-tank-cleaned', 'septicTankCleaned');                                                  // 18 ( Septic Tank Cleaned )
            Route::post('septic-tanker/list-cleaned-booking', 'listCleanedBooking');                                                // 19 ( List Cleaned Booking)
            Route::post('septic-tanker/generate-payment-order-id', 'generatePaymentOrderId');                                       // 20 ( Generate Payment Order ID ) 
            // Route::post('septic-tanker/generate-payment-order-id', 'generatePaymentOrderIdV2');
            Route::post('septic-tanker/list-applied-and-cancelled-application', 'listAppliedAndCancelledApplication');              // 21 ( List Applied and cancelled applications ) 
            Route::post('septic-tanker/add-capacity', 'addCapacity');                                                               // 22 ( Add Capacity ) 
            Route::post('septic-tanker/list-capacity', 'listCapacity');                                                             // 23 ( List Capacity )
            Route::post('septic-tanker/get-capacity-details-by-id', 'getCapacityDetailsById');                                      // 24 ( Get Capacity Details By Id )
            Route::post('septic-tanker/edit-capacity', 'editCapacity');                                                             // 25 ( Edit Capacity )
            Route::post('septic-tanker/add-capacity-rate', 'addCapacityRate');                                                      // 26 ( Add Capacity Rate )
            Route::post('septic-tanker/list-capacity-rate', 'listCapacityRate');                                                    // 27 ( List Capacity Rate )
            Route::post('septic-tanker/get-capacity-rate-details-by-id', 'getCapacityRateDetailsById');                             // 28 ( Get Capacity Rate Details By Id ) 
            Route::post('septic-tanker/edit-capacity-rate', 'editCapacityRate');                                                    // 29 ( Edit Capacity Rate ) 
            Route::post('septic-tanker/get-capacity-list-for-booking', 'getCapacityListForBooking');                                // 30 ( Get Capacity List For Booking ) 
            Route::post('septic-tanker/get-payment-details-by-pay-id', 'getPaymentDetailsByPaymentId');                             // 31 ( Get Payment Details By Payment Id )  
            Route::post('septic-tanker/list-buliding-type', 'listBuildingType');                                                    // 32 ( Get List of All Type of Building )  
            Route::post('septic-tanker/list-ulb-wise-location', 'listUlbwiseLocation');                                             // 33 ( List ULB Wise Location )  
            Route::post('septic-tanker/septic-tank-feedback', 'getFeedback');                                                       // 34 ( Get Feedback )   
            Route::post('septic-tanker/check-feedback', 'checkFeedback');                                                           // 35 ( Check Feedback )  
            Route::post('septic-tanker/master-data', 'masterData');

            Route::post('septic-tanker/re-assign-booking', 'reassignBooking');                                                       // 51 ( Re-Assign Booking ) 
            Route::post('septic-tanker/list-re-assign-booking', 'listReassignBooking');

            Route::post('septic-tanker/get-booking-details-by-id', 'getBookingDetailById'); 
            Route::post("septic-tanker/driver-to-delivery-list","driverDeliveryList");
            Route::post("septic-tanker/driver-update-delivery","updateDeliveryTrackStatus");
            Route::post("septic-tanker/driver-cancel-list","driverCanceledList");
            Route::post("septic-tanker/driver-updated-list","updatedListDeliveryByDriver");
            Route::post('septic-tanker/sent-vehicle', 'sentVehicle'); 
            Route::post('septic-tanker/offline-payment', 'offlinePayment');
            Route::post('septic-tanker/search-booking', 'searchApp');
            //writen by prity pandey
            Route::post('septic-tanker/collection-report', 'listCollection');
            Route::post('septic-tanker/daily-collection-report', 'ReportDataSepticTanker');
            Route::post('septic-tanker/application-report', 'applicationReportDataWaterTanker');
            Route::post('septic-tanker/pending-report', 'pendingReportDataSepticTanker');
            
        });

         //written by Prity Pandey
         Route::controller(CashVerificationController::class)->group(function () {
            Route::post('water-tanker/list-cash-verification', 'cashVerificationList');
            Route::post('water-tanker/cash-verification-dtl', 'cashVerificationDtl');
            Route::post('water-tanker/verify-cash', 'verifyCash');
            Route::post('water-tanker/search-transaction-no', 'searchTransactionNo');
            Route::post('water-tanker/deactivate-transaction', 'deactivateTransaction');
            Route::post('water-tanker/deactivate-transaction-list', 'deactivatedTransactionList');
         });

        Route::controller(SepticTankerReportController::class)->group(function () {
            Route::post('septic-tanker/report/collection', 'collationReports');  
            Route::post('septic-tanker/report/collection-user-wise', 'userWiseCollection');   
            Route::post('septic-tanker/report/dashboard', 'dashBoard');  
            Route::post('septic-tanker/report/dashboard-user-wise', 'userWishDashBoard'); 
            //written by prity pandey
            Route::post('septic-tanker/cancle-booking', 'cancleBookingList');
        });
    });
});