<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySessionIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = $request->header('X-Spot-User');
        if ($expected !== null && (string) $request->user()->getAuthIdentifier() !== $expected) {
            return response()->json([
                'code' => 'session_changed',
                'message' => __('errors.session_changed'),
            ], 409);
        }

        return $next($request);
    }
}
