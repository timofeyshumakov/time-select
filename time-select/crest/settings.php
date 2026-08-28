<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/server/lib/Env.php';
Env::load(dirname(__DIR__, 2) . '/server/.env');

define('C_REST_CLIENT_ID', Env::get('C_REST_CLIENT_ID', ''));
define('C_REST_CLIENT_SECRET', Env::get('C_REST_CLIENT_SECRET', ''));
define('C_REST_BLOCK_LOG', true);
