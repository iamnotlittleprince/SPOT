<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated extends \Illuminate\Auth\Middleware\RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->is('api/*')) {
            foreach ($guards ?: [null] as $guard) {
                if (Auth::guard($guard)->check()) {
                    return response()->json([
                        'message' => 'Já existe uma conta conectada neste navegador. Saia da conta atual ou abra o link em uma janela privativa para continuar.',
                    ], 409);
                }
            }
        }

        return parent::handle($request, $next, ...$guards);
    }
}
