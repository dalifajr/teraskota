<?php

return [
    'name' => env('PLATFORM_NAME', 'KasirLoka'),
    'tagline' => env('PLATFORM_TAGLINE', 'Aplikasi Kasir POS & Keuangan UMKM Anti-Offline'),
    'trial_days' => (int) env('PLATFORM_TRIAL_DAYS', 14),
    'grace_days' => (int) env('PLATFORM_GRACE_DAYS', 3),
    'support_whatsapp' => env('PLATFORM_SUPPORT_WHATSAPP', ''),
    'support_email' => env('PLATFORM_SUPPORT_EMAIL', 'support@kasirloka.id'),
];
