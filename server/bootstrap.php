<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Env.php';

Env::load(__DIR__ . '/.env');

$crestPath = file_exists(__DIR__ . '/crest/crest.php')
    ? __DIR__ . '/crest/crest.php'
    : null;

if ($crestPath !== null) {
    require_once $crestPath;
}

$libFiles = [
    'DateManager.php',
    'ApiClient.php',
    'BatchExecutionException.php',
    'BatchRequestExecutor.php',
    'PatientStatsCache.php',
    'ServiceNormalizer.php',
    'DealStageMapper.php',
    'FreeSlotCalculator.php',
    'PatientStatsPage.php',
    'Bitrix24Handler.php',
    'RenovatioHandler.php',
];

foreach ($libFiles as $file) {
    require_once __DIR__ . '/lib/' . $file;
}
