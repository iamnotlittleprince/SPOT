<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['guest' => \App\Http\Middleware\RedirectIfAuthenticated::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if ($status < 400) return $response;
            [$title, $description] = \App\Support\ErrorPage::message($status);
            if ($request->is('api/*') || $request->expectsJson()) {
                // Never expose traces, SQL or credentials in server failure responses.
                if ($status >= 500) $response->setContent(json_encode(['message' => $description]));
                if ($status >= 500) $response->headers->set('Content-Type', 'application/json');
                return $response;
            }
            $response->setContent(view('errors.spot', compact('status', 'title', 'description'))->render());
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            $response->headers->set('Cache-Control', 'no-store');
            return $response;
        });
    })->create();
