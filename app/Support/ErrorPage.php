<?php

namespace App\Support;

final class ErrorPage
{
    public static function localizedDescription(int $status): string
    {
        $key = 'errors.http.'.($status >= 500 && ! in_array($status, [500, 502, 503, 504], true) ? '5xx' : $status);
        return __($key) === $key ? self::message($status)[1] : __($key);
    }

    public static function message(int $status): array
    {
        $messages = json_decode(file_get_contents(resource_path('errors/messages.json')), true, flags: JSON_THROW_ON_ERROR);
        return $messages[(string) $status] ?? $messages[$status >= 500 ? '5xx' : '4xx'];
    }
}
