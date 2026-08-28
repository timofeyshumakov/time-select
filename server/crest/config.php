<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Env.php';
Env::load(dirname(__DIR__) . '/.env');

define('BOT_TOKEN', Env::get('BOT_TOKEN', ''));
define('WEBHOOK_URL', Env::get('WEBHOOK_URL', ''));
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_NAME', Env::get('DB_NAME', ''));
define('DB_USER', Env::get('DB_USER', ''));
define('DB_PASS', Env::get('DB_PASS', ''));
