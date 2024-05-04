<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthMaker
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if(!Auth()->user() && $request->auth)
        {
            if(!is_array($request->auth))
            {
                $request->merge(["auth"=>json_decode($request->auth,true)]);
            }
            if(!is_array($request->currentAccessToken))
            {
                $request->merge(["currentAccessToken"=>json_decode($request->currentAccessToken,true)]);
            }
            switch($request->currentAccessToken["tokenable_type"])
            {
                case "App\\Models\\Auth\\User": Auth::login(new \App\Models\User($request->auth));
                                                break;
                default                       : Auth::login(new \App\Models\ActiveCitizen($request->auth));
                                                break;
            }
            collect($request->auth)->map(function ($val, $key) {
                Auth()->user()->$key = $val;
            });
        }
        return $next($request);
    }
}
