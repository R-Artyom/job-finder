<?php

return [
    // Разрешение отправки email уведомлений об ошибках
    'emailNotifications' => env('ENABLE_EMAIL_NOTIFICATIONS', false),

    // Разрешение отправки Telegram уведомлений об ошибках
    'telegramNotifications' => env('ENABLE_TELEGRAM_NOTIFICATIONS', false),
];
