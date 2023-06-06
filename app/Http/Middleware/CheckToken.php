<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

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
    public function handle(Request $request, Closure $next)
    {
        if (!$request->auth && !$request->token) {       
            $this->unauthenticate();                                                    
        }
        return $next($request);
    }

    /**
     * | Unauthenticate
     */
    public function unauthenticate()
    {
        abort(response()->json(
            [
                'status' => true,
                'authenticated' => false
            ]
        ));
    }
}
