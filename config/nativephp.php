<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identificação do Aplicativo Desktop
    |--------------------------------------------------------------------------
    |
    | Estas configurações definem como o app aparece no sistema operacional.
    | O app_id deve seguir o padrão de domínio reverso (ex: com.empresa.app).
    |
    */

    'app_id'      => env('NATIVEPHP_APP_ID', 'br.com.computecnica.spot'),
    'app_version' => env('NATIVEPHP_APP_VERSION', '1.0.0'),
    'app_author'  => env('NATIVEPHP_APP_AUTHOR', 'Computécnica'),

    /*
    |--------------------------------------------------------------------------
    | Janela Principal
    |--------------------------------------------------------------------------
    */

    'window' => [
        'width'     => 1280,
        'height'    => 800,
        'min_width' => 960,
        'min_height'=> 600,
        'title'     => 'Spot — Computécnica',
        'frame'     => true,        // true = barra de título nativa do SO
        'resizable' => true,
        'center'    => true,
        'fullscreen'=> false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Splash / Ícone
    |--------------------------------------------------------------------------
    */

    'icon' => public_path('images/spot-icon.png'),

    /*
    |--------------------------------------------------------------------------
    | Auto-update (Electron Updater)
    |--------------------------------------------------------------------------
    */

   'updater' => [
    'enabled' => false,
    'default' => 'github',        // precisa existir mesmo desabilitado
    'providers' => [
        'github' => [
            'driver' => 'github',
            'repo'   => '',
            'token'  => '',
        ],
        's3' => [
            'driver' => 's3',
            'bucket' => '',
            'region' => '',
            'key'    => '',
            'secret' => '',
        ],
    ],
],

    /*
    |--------------------------------------------------------------------------
    | Sistema de arquivos local do desktop
    |--------------------------------------------------------------------------
    */

    'storage_path' => env('NATIVEPHP_STORAGE_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Deep links (protocolo customizado)
    |--------------------------------------------------------------------------
    */

    'deeplink_scheme' => env('NATIVEPHP_DEEPLINK_SCHEME', 'spot'),

    /*
    |--------------------------------------------------------------------------
    | Workers de fila
    |--------------------------------------------------------------------------
    |
    | O SPOT ainda nao usa fila em segundo plano. Manter isso vazio evita que o
    | NativePHP tente iniciar um processo PHP extra ao abrir a tela de login.
    |
    */

    'queue_workers' => [],

];
