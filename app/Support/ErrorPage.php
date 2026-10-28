<?php

namespace App\Support;

final class ErrorPage
{
    public static function message(int $status): array
    {
        $messages = json_decode(file_get_contents(resource_path('errors/messages.json')), true, flags: JSON_THROW_ON_ERROR);
        return $messages[(string) $status] ?? $messages[$status >= 500 ? '5xx' : '4xx'];
    }
}
