<?php
return [
    'bitrix24' => [
        'webhook_url' => getenv('BITRIX24_WEBHOOK_URL') ?: '',
        'webhook_secret' => getenv('BITRIX24_WEBHOOK_SECRET') ?: '',
    ],
    'renovatio' => [
        'api_url' => getenv('RENOVATIO_API_BASE_URL') ?: 'https://app.rnova.org/api/public/',
        'api_key' => getenv('RENOVATIO_API_KEY') ?: '',
    ],
    'logging' => [
        'enabled' => true,
        'file' => 'logs/integration.log',
    ],
];
