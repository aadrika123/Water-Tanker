<?php

use App\Http\Controllers\WaterTankerController;
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

Route::group(['middleware' => ['checkToken']], function () {
    Route::controller(WaterTankerController::class)->group(function () {
        Route::post('water-tanker/list-ulb', 'ulbList');                                    // 01 ( Ulb List )
        Route::post('water-tanker/add-agency', 'addAgency');                                // 01 ( Add Agency )
        Route::post('water-tanker/list-agency', 'listAgency');                              // 02 ( List Agency )
        Route::post('water-tanker/add-capacity', 'addCapacity');                            // 03 ( Add Capacity )
        Route::post('water-tanker/list-capacity', 'listCapacity');                          // 04 ( List Capacity )
        Route::post('water-tanker/add-capacity-rate', 'addCapacityRate');                   // 05 ( Add Capacity Rate )
        Route::post('water-tanker/list-capacity-rate', 'listCapacityRate');                 // 06 ( List Capacity Rate )
        Route::post('water-tanker/add-hydration-center', 'addHydrationCenter');             // 07 ( Add Hydration Center )
        Route::post('water-tanker/list-hydration-center', 'listHydrationCenter');           // 08 ( List Hydration Center )
        Route::post('water-tanker/add-driver', 'addDriver');                                // 09 ( Add Driver )
        Route::post('water-tanker/list-driver', 'listDriver');                              // 10 ( List Driver )
        Route::post('water-tanker/add-resouce', 'addResource');                             // 11 ( Add Resource )
        Route::post('water-tanker/list-resouce', 'listResource');                           // 12 ( List Resource )
        Route::post('water-tanker/add-hydration-center-dispatch-log', 'addHydrationCenerDispatchLog'); // 13 ( Add Hydration Center Dispatch Logs )
        Route::post('water-tanker/list-hydration-center-dispatch-log', 'listHydrationCenerDispatchLog'); // 14 ( List Hydration Center Dispatch Logs )
        Route::post('water-tanker/add-booking', 'addBooking');                             // 15 ( Add Bookings )
        Route::post('water-tanker/list-agency-booking', 'listAgencyBooking');              // 16 ( list Agency Bookings )  
        Route::post('water-tanker/map-driver-vehicle', 'mapDriverVehicle');                // 17 ( Map Driver Vehicle Maps )  
        Route::post('water-tanker/list-map-driver-vehicle', 'listMapDriverVehicle');       // 18 ( List Map Driver Vehicle Maps )  
        Route::post('water-tanker/cancel-booking', 'cancelBooking');                       // 19 ( Cancel Booking )  
        Route::post('water-tanker/list-cancel-booking', 'listCancelBooking');              // 20 ( List Cancel Booking )  
        Route::post('water-tanker/refund-booking', 'refundBooking');                       // 21 ( Refund Booking )  
        Route::post('water-tanker/list-refund-booking', 'listRefundBooking');              // 22 ( List Refund Booking )  
        Route::post('water-tanker/list-ulb-booking', 'listUlbBooking');                    // 23 ( List ULB Booking )  
    });
});
