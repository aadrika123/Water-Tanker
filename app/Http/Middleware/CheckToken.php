<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * | Author Name - Bikash Kumar
 * | Date - 03 Jun 2023
 * | Status - Closed(03 Jun 2023)
 */

class CheckToken
{
    private $_user;
    private $_currentTime;
    private $_token;
    private $_lastActivity;
    private $_key;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    // public function handle(Request $request, Closure $next)
    // {
    //     if (!$request->auth && !$request->token) {       
    //         $this->unauthenticate();                                                    
    //     }
    //     return $next($request);
    // }

    // /**
    //  * | Unauthenticate
    //  */
    // public function unauthenticate()
    // {
    //     abort(response()->json(
    //         [
    //             'status' => true,
    //             'authenticated' => false
    //         ]
    //     ));
    // }

    public function handle(Request $request, Closure $next)
    {
        //     $apiKey = Config::get('constants.API_KEY');
        //     // Returns boolean
        //     if ($request->headers->has('API-KEY') == false) {
        //         return response()->json([
        //             'status' => false,
        //             'message' => 'No Authorization Key',
        //         ], 400);
        //     };
        //     // Returns header value with default as fallback
        //     $val = $request->header('API-KEY', 'default_value');
        //     if ($val === $apiKey) {
        //         $this->validateApiKey($request);
        //         return $next($request);
        //     } else {
        //         return response()->json([
        //             'status' => false,
        //             'message' => 'Invalid API Key',
        //         ], 400);
        //     }
        // }

        // // Api Token Validity
        // public function validateApiKey($request)
        // {
        //     $apiToken = $request->apiToken;
        //     if (isset($apiToken)) {
        //         $mPersonalAccessToken = new PersonalAccessToken();
        //         $tokenValidity = $mPersonalAccessToken->findToken($apiToken);
        //         if (collect($tokenValidity)->isEmpty())
        //             return responseMsgs(false, "Api Token Is Invalid", []);
        //     }
        if (!Auth()->user() && $request->auth) {
            if (!is_array($request->auth)) {
                $request->merge(["auth" => json_decode($request->auth, true)]);
            }
            if (!is_array($request->currentAccessToken)) {
                $request->merge(["currentAccessToken" => json_decode($request->currentAccessToken, true)]);
            }
            switch ($request->currentAccessToken["tokenable_type"]) {
                case "App\\Models\\Auth\\User":
                    Auth::login(new \App\Models\User($request->auth));
                    break;
                default:
                    Auth::login(new \App\Models\ActiveCitizen($request->auth));
                    break;
            }
            collect($request->auth)->map(function ($val, $key) {
                Auth()->user()->$key = $val;
            });
        }
        return $next($request);
    }
}
