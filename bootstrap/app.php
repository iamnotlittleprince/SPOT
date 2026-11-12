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
        $middleware->prepend(\App\Http\Middleware\SetRequestLocale::class);
        $middleware->statefulApi();
        // Keep significant spaces between formatted text nodes and inside code blocks.
        $middleware->trimStrings(except: ['body_format.*']);
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
                if ($exception instanceof \Illuminate\Validation\ValidationException) {
                    $payload = json_decode($response->getContent(), true);
                    $payload['message'] = __('errors.validation');
                    $response->setContent(json_encode($payload, JSON_UNESCAPED_UNICODE));
                }
                // Replace framework defaults while retaining actionable domain messages.
                if ($status < 500 && ! ($exception instanceof \Illuminate\Validation\ValidationException)) {
                    $payload = json_decode($response->getContent(), true);
                    $message = $payload['message'] ?? '';
                    $defaults = ['', 'Unauthenticated.', 'This action is unauthorized.', 'Forbidden', 'Not Found', 'Page Expired', 'CSRF token mismatch.', 'Too Many Attempts.', 'Server Error'];
                    if (is_array($payload) && (in_array($message, $defaults, true) || str_starts_with($message, 'No query results for model') || str_starts_with($message, 'The route ') || str_starts_with($message, 'The POST method is not supported') || str_starts_with($message, 'The GET method is not supported'))) {
                        $payload['message'] = \App\Support\ErrorPage::localizedDescription($status);
                        $response->setContent(json_encode($payload, JSON_UNESCAPED_UNICODE));
                    }
                }
                // Never expose traces, SQL or credentials in server failure responses.
                if ($status >= 500) $response->setContent(json_encode(['message' => \App\Support\ErrorPage::localizedDescription($status)]));
                if ($status >= 500) $response->headers->set('Content-Type', 'application/json');
                return $response;
            }
            $response->setContent(view('errors.spot', compact('status', 'title', 'description'))->render());
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            $response->headers->set('Cache-Control', 'no-store');
            return $response;
        });
    })->create();
