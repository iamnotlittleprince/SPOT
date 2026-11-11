<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountNotArchived
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->archived_at) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
