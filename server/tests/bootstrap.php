<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Env.php';
Env::load(dirname(__DIR__) . '/.env');

require_once dirname(__DIR__) . '/lib/DateManager.php';
require_once dirname(__DIR__) . '/lib/ServiceNormalizer.php';
require_once dirname(__DIR__) . '/lib/DealStageMapper.php';
require_once dirname(__DIR__) . '/lib/FreeSlotCalculator.php';
require_once dirname(__DIR__) . '/lib/PatientStatsCache.php';
