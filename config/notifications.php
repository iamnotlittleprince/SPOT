<?php

return [
    // A criação de notificações internas não depende da ativação de e-mail externo.
    'email_enabled' => (bool) env('SPOT_ACTIVITY_EMAIL_ENABLED', false),
    'reminder_lookback_days' => 30,
];
