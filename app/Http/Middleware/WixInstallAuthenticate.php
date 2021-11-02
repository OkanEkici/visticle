<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;

class WixInstallAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        return Auth::guard('wix')->onceBasic('user_id') ?: $next($request);
    }

}
