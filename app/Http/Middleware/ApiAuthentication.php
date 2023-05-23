<?php

namespace App\Http\Middleware;

use App\Models\ActiveCitizen;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ApiAuthentication
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
        $token = $request->bearerToken();
        $citizen = ActiveCitizen::where('remember_token', $token)->first();
        if ($citizen) {
            auth()->login($citizen);
            return $next($request);
        }

        $user = User::where('remember_token', $token)->first();
        if ($user) {
            auth()->login($user);
            return $next($request);
        }

        abort(response()->json(['error' => 'Unauthenticated.'], 401));
    }
}
