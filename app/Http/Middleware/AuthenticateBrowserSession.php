<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/** Keep Sanctum's password-change protection without starting a second session. */
class AuthenticateBrowserSession extends AuthenticateSession
{
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend($request)
            ? parent::handle($request, $next)
            : $next($request);
    }
}
