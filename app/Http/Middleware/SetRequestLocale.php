<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class SetRequestLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Use the interface choice, without accepting arbitrary translation paths.
        $locale = ['pt' => 'pt_BR', 'pt-BR' => 'pt_BR', 'en' => 'en', 'es' => 'es'][$request->header('X-Spot-Locale', '')] ?? config('app.locale');
        app()->setLocale($locale);

        return $next($request);
    }
}
