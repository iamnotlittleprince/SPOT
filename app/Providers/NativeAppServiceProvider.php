<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Throwable;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        retry(5, function () {
            $window = Window::open()
                ->url(route('login'))
                ->title('SPOT')
                ->width(config('nativephp.window.width', 1280))
                ->height(config('nativephp.window.height', 800))
                ->minWidth(config('nativephp.window.min_width', 960))
                ->minHeight(config('nativephp.window.min_height', 600));

            unset($window);
        }, 500, function (Throwable $exception) {
            report($exception);

            return true;
        });
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
