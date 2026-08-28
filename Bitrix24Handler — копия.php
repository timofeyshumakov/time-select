<?php
$crestPath = file_exists(__DIR__ . '/../crest/crest.php')
    ? __DIR__ . '/../crest/crest.php'
    : __DIR__ . '/crest/crest.php';
require_once($crestPath);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
class DateManager
{
    /**
     * Формат даты в Bitrix24
     */
    private const BITRIX_FORMAT = 'Y-m-d H:i';
    private const ISO_FORMAT = 'Y-m-d\TH:i:sP';
    /**
     * Формат даты в Renovatio
     */
    private const RENOVATIO_FORMAT = 'd.m.Y H:i';
    
    /**
     * Часовой пояс по умолчанию (Москва)
     */
    private const DEFAULT_TIMEZONE = 'Europe/Moscow';

    private DateTimeZone $timezone;

    public function __construct(?string $timezone = null)
    {
        $this->timezone = new DateTimeZone($timezone ?? self::DEFAULT_TIMEZONE);
    }

    /**
     * Преобразует дату из формата Bitrix24 в формат Renovatio
     * 
     * @param string $bitrixDate Дата в формате Bitrix24 (2025-11-06T12:12:00+03:00)
     * @return string Дата в формате Renovatio (06.11.2025 12:12)
     * @throws Exception
     */
    public function bitrixToRenovatio(string $bitrixDate): string
    {
        try {
            // Пробуем парсить ISO формат
            $date = DateTime::createFromFormat(self::ISO_FORMAT, $bitrixDate);
            if ($date) {
                // В Renovatio время должно быть в часовом поясе клиники (Москва).
                // Нормализуем ISO-дату (с TZ пользователя) к Москве, чтобы не получать +2 часа для GMT+5.
                $date->setTimezone($this->timezone);
                return $date->format(self::RENOVATIO_FORMAT);
            }
            
            // Если не получилось, пробуем стандартный Bitrix формат
            $date = DateTime::createFromFormat(self::BITRIX_FORMAT, $bitrixDate, $this->timezone);
            
            if (!$date) {
                throw new Exception("Неверный формат даты Bitrix24: {$bitrixDate}");
            }

            return $date->format(self::RENOVATIO_FORMAT);
        } catch (Exception $e) {
            throw new Exception("Ошибка преобразования даты из Bitrix24: " . $e->getMessage());
        }
    }

    /**
     * Преобразует дату из формата Renovatio в формат Bitrix24
     * 
     * @param string $renovatioDate Дата в формате Renovatio (06.11.2025 12:12)
     * @return string Дата в формате Bitrix24 (2025-11-06T12:12:00+03:00)
     * @throws Exception
     */
    public function renovatioToBitrix(string $renovatioDate): string
    {
        try {
            $date = DateTime::createFromFormat(self::RENOVATIO_FORMAT, $renovatioDate, $this->timezone);
            
            if (!$date) {
                throw new Exception("Неверный формат даты Renovatio: {$renovatioDate}");
            }
            
            // Возвращаем в ISO формате с часовым поясом
            return $date->format(self::ISO_FORMAT);
        } catch (Exception $e) {
            throw new Exception("Ошибка преобразования даты в Bitrix24: " . $e->getMessage());
        }
    }

    /**
     * Преобразует массив дат из Bitrix24 в Renovatio
     * 
     * @param array $dates Массив дат в формате Bitrix24
     * @return array Массив дат в формате Renovatio
     */
    public function convertArrayBitrixToRenovatio(array $dates): array
    {
        $result = [];
        
        foreach ($dates as $key => $date) {
            try {
                $result[$key] = $this->bitrixToRenovatio($date);
            } catch (Exception $e) {
                $result[$key] = null;
                // Можно добавить логирование ошибки здесь
                error_log("Date conversion error for key {$key}: " . $e->getMessage());
            }
        }
        
        return $result;
    }

    /**
     * Преобразует массив дат из Renovatio в Bitrix24
     * 
     * @param array $dates Массив дат в формате Renovatio
     * @return array Массив дат в формате Bitrix24
     */
    public function convertArrayRenovatioToBitrix(array $dates): array
    {
        $result = [];
        
        foreach ($dates as $key => $date) {
            try {
                $result[$key] = $this->renovatioToBitrix($date);
            } catch (Exception $e) {
                $result[$key] = null;
                // Можно добавить логирование ошибки здесь
                error_log("Date conversion error for key {$key}: " . $e->getMessage());
            }
        }
        
        return $result;
    }

    /**
     * Валидирует дату в формате Bitrix24
     */
    public function isValidBitrixDate(string $date): bool
    {
        $d = DateTime::createFromFormat(self::BITRIX_FORMAT, $date);
        return $d && $d->format(self::BITRIX_FORMAT) === $date;
    }

    /**
     * Валидирует дату в формате Renovatio
     */
    public function isValidRenovatioDate(string $date): bool
    {
        $d = DateTime::createFromFormat(self::RENOVATIO_FORMAT, $date);
        return $d && $d->format(self::RENOVATIO_FORMAT) === $date;
    }

    /**
     * Получает текущую дату в формате Bitrix24
     */
    public function getCurrentBitrixDate(): string
    {
        $date = new DateTime('now', $this->timezone);
        return $date->format(self::BITRIX_FORMAT);
    }

    /**
     * Получает текущую дату в формате Renovatio
     */
    public function getCurrentRenovatioDate(): string
    {
        $date = new DateTime('now', $this->timezone);
        return $date->format(self::RENOVATIO_FORMAT);
    }

    /**
     * Устанавливает часовой пояс
     */
    public function setTimezone(string $timezone): void
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * Получает текущий часовой пояс
     */
    public function getTimezone(): string
    {
        return $this->timezone->getName();
    }
}

class BatchRequestExecutor
{
    private int $batchSize;
    private int $delayMicroseconds;
    
    public function __construct(int $batchSize = 50, int $delayMicroseconds = 100000)
    {
        $this->batchSize = $batchSize;
        $this->delayMicroseconds = $delayMicroseconds;
    }
    
    public function execute(array $requests): array
    {
        // Если только один элемент - выполняем обычный запрос
        if (count($requests) === 1) {
            return $this->executeSingleRequest(reset($requests));
        }
        
        // Если несколько элементов - выполняем batch
        $results = [];
        $batches = array_chunk($requests, $this->batchSize, true);
        
        foreach ($batches as $batchIndex => $batchRequests) {
            $batchCommands = $this->prepareBatchCommands($batchRequests);
            $batchResult = $this->executeBatch($batchCommands);
            $results = array_merge($results, $this->processBatchResult($batchResult));
            
            $this->applyDelay($batchIndex, count($batches));
        }
        
        return $results;
    }
    
    /**
     * Выполняет одиночный запрос
     */
    private function executeSingleRequest(array $request): array
    {
        $result = CRest::call($request['method'], $request['params']);
        
        return [$this->createSingleResult($result)];
    }
    
    /**
     * Создает результат для одиночного запроса
     */
    private function createSingleResult(array $result): array
    {
        if (isset($result['error'])) {
            return [
                'success' => false,
                'error' => $result['error_description'] ?? 'Unknown error'
            ];
        }
        
        return [
            'success' => true,
            'result' => $result['result'] ?? null
        ];
    }
    
    private function prepareBatchCommands(array $batchRequests): array
    {
        $batchCommands = [];
        
        foreach ($batchRequests as $key => $request) {
            $batchCommands[$key] = [
                'method' => $request['method'],
                'params' => $request['params']
            ];
        }
        
        return $batchCommands;
    }
    
    private function executeBatch(array $batchCommands): array
    {
        $batchResult = CRest::callBatch($batchCommands);
        
        if (isset($batchResult['error'])) {
            throw new BatchExecutionException(
                "Batch error: " . ($batchResult['error_description'] ?? 'Unknown batch error')
            );
        }
        
        return $batchResult;
    }
    
    private function processBatchResult(array $batchResult): array
    {
        $results = [];
        
        if (isset($batchResult['result']) && is_array($batchResult['result'])) {
            foreach ($batchResult['result'] as $key => $result) {
                $results[$key] = $this->createResult($result);
            }
        }
        
        return $results;
    }
    
    private function createResult(array $result): array
    {
        if (isset($result['error'])) {
            return [
                'success' => false,
                'error' => $result['error_description'] ?? 'Unknown error'
            ];
        }
        
        return [
            'success' => true,
            'result' => $result['result'] ?? null
        ];
    }
    
    private function applyDelay(int $currentBatchIndex, int $totalBatches): void
    {
        if ($totalBatches > 1 && $currentBatchIndex < $totalBatches - 1) {
            usleep($this->delayMicroseconds);
        }
    }
    
    // Геттеры для конфигурации
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }
    
    public function getDelayMicroseconds(): int
    {
        return $this->delayMicroseconds;
    }
}

class BatchExecutionException extends Exception {}

class ApiClient {
    /**
     * Универсальная функция для выполнения CURL запросов
     * 
     * @param string $url URL для запроса
     * @param array $postData Данные для POST запроса
     * @param array $options Дополнительные опции для CURL
     * @return array
     * @throws Exception
     */
    public static function makeCurlRequest($url, $postData = [], $options = []) {
        $ch = curl_init();
        
        // Базовые настройки CURL
        $defaultOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ];
        
        // Если есть данные для POST запроса
        if (!empty($postData)) {
            $postDataString = http_build_query($postData);
            $defaultOptions[CURLOPT_POST] = true;
            $defaultOptions[CURLOPT_POSTFIELDS] = $postDataString;
            $defaultOptions[CURLOPT_HTTPHEADER] = [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postDataString)
            ];
        }
        
        // Объединяем с пользовательскими опциями
        $curlOptions = $options + $defaultOptions;
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200) {
            throw new Exception('API Error: HTTP ' . $httpCode . ' - ' . ($result['error'] ?? 'Unknown error'));
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        
        return $result;
    }
}
class PatientStatsCache {
    private string $cacheDir;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/patient_stats/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get(int $patientId): ?array {
        $file = $this->getCacheFile($patientId);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (isset($data['cached_at']) && (time() - $data['cached_at']) < 3600) {
            return $data;
        }
        
        return null;
    }
    
    public function set(int $patientId, array $data): void {
        $file = $this->getCacheFile($patientId);
        $data['cached_at'] = time();
        $data['cached_date'] = date('Y-m-d H:i:s');
        
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    private function getCacheFile(int $patientId): string {
        return $this->cacheDir . 'patient_' . $patientId . '_stats.json';
    }
    
    public function clear(int $patientId): void {
        $file = $this->getCacheFile($patientId);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

class Bitrix24Handler {
    private BatchRequestExecutor $batchExecutor;
    
    public function __construct()
    {
        $this->batchExecutor = new BatchRequestExecutor();
    }

    /**
     * Находит контакт в Битрикс24 по ID пациента в Renovatio
     */
    private function findContactByRenovatioPatientId(int $patientId): ?array {
        try {
            $result = CRest::call('crm.contact.list', [
                'filter' => [
                    'UF_CRM_1729239754' => $patientId
                ],
                'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'UF_CRM_1776436700', 'UF_CRM_1776436729']
            ]);
            
            if (!empty($result['result'])) {
                return $result['result'][0];
            }
        } catch (Exception $e) {
            error_log("Error finding contact: " . $e->getMessage());
        }
        
        return null;
    }

    public function updateDeal($renovoId, $status, $statusId, $services, $patientId, $amount = null){
    if ($status === 'refused') {
        $stage = 'C1:4'; // Другое
    } elseif ($status === 'completed') {
        $stage = 'C1:WON'; // C1:WON
    } elseif ($status === 'upcoming' && $statusId === '3'){
        $stage = 'C1:UC_Q3I0Z1'; // Клиент пришел
    } else {
        $stage = '';
    }


    if ($stage) {
        $dealList = CRest::call(
            'crm.deal.list',
            [
                'SELECT' => [
                    'ID',
                    'CONTACT_ID',
                ],
                'FILTER' => [
                    'UF_CRM_1729241550' => $renovoId,
                ],
            ]
        );

        if (!empty($dealList['error'])) {
            $logMessage = json_encode([
                'action' => 'updateDeal_deal_list_error',
                'renovo_id' => $renovoId,
                'stage' => $stage,
                'error' => $dealList['error'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
            file_put_contents('log.json', $logMessage, FILE_APPEND);
            return;
        }

        if (empty($dealList['result']) || !isset($dealList['result'][0])) {
            $logMessage = json_encode([
                'action' => 'updateDeal_deal_not_found',
                'renovo_id' => $renovoId,
                'stage' => $stage,
                'hint' => 'Нет сделки с UF_CRM_1729241550 = renovo_id',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
            file_put_contents('log.json', $logMessage, FILE_APPEND);
            return;
        }

        $deal = $dealList['result'][0];

        if (empty($deal['ID'])) {
            $logMessage = json_encode([
                'action' => 'updateDeal_invalid_deal',
                'deal' => $deal,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
            file_put_contents('log.json', $logMessage, FILE_APPEND);
            return;
        }

        // Подготавливаем поля для обновления
        $updateFields = [
            'STAGE_ID' => $stage,
        ];

        $normalizedServices = [];
        if ($stage === 'C1:WON') {
            
            $normalizedServices = $this->normalizeServicesInput($services, $amount);
            $totalFromServices = 0.0;
            foreach ($normalizedServices as $line) {
                $totalFromServices += $line['price'] * $line['qty'];
            }
            $opportunity = $totalFromServices;
            if ($opportunity <= 0.0 && $amount !== null && $amount !== '') {
                $opportunity = (float)$amount;
            }
            $updateFields['OPPORTUNITY'] = $opportunity;
            $updateFields['UF_CRM_1771593682'] = $this->formatServicesForDealField($normalizedServices);
            $updateFields['UF_CRM_1770898429704'] = 'https://app.rnova.org/patients/default/detail/id/' . $patientId;
            try {
                if (empty($deal['CONTACT_ID'])) {
                    $logMessage = json_encode([
                        'action' => 'contact_visit_stats_skipped',
                        'reason' => 'deal_has_no_contact_id',
                        'deal_id' => $deal['ID'] ?? null,
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
                    file_put_contents('log.json', $logMessage, FILE_APPEND);
                } else {
                    $renovatioHandler = new RenovatioHandler();
                    // Принудительный пересчёт из API Renovatio без использования кеша
                    $fullStats = $renovatioHandler->getPatientFullStats((int)$patientId, true);
                    $statistics = $fullStats['statistics'] ?? [];
                    // Те же показатели, что на странице статистики: «Общая сумма» и «Завершенных»
                    $totalPaid = (float)($statistics['total_paid'] ?? 0);
                    $completedVisits = (int)($statistics['completed'] ?? 0);
                    $stats = [
                        'total_paid' => $totalPaid,
                        'visit_count' => $completedVisits,
                    ];
                    $patientLink = 'https://app.rnova.org/patients/default/detail/id/' . $patientId;
                    $contactUpdate = CRest::call('crm.contact.update', [
                        'ID' => $deal['CONTACT_ID'],
                        'FIELDS' => [
                            'UF_CRM_1729239754' => $patientId,
                            'UF_CRM_1773225131' => $patientLink,
                            'UF_CRM_1776436700' => $totalPaid,
                            'UF_CRM_1776436729' => $completedVisits,
                        ],
                    ]);

                    $logMessage = json_encode([
                        'action' => 'contact_visit_stats_updated',
                        'contact_id' => $deal['CONTACT_ID'],
                        'stats' => $stats,
                        'statistics_snapshot' => [
                            'total_paid' => $totalPaid,
                            'completed' => $completedVisits,
                        ],
                        'result' => $contactUpdate,
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
                    file_put_contents('log.json', $logMessage, FILE_APPEND);
                }

            } catch (Throwable $e) {
                $logMessage = json_encode([
                    'action' => 'contact_visit_stats_error',
                    'contact_id' => $deal['CONTACT_ID'] ?? null,
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
                file_put_contents('log.json', $logMessage, FILE_APPEND);
            }

        }

        $result = CRest::call(
            'crm.deal.update',
            [
                'ID' => $deal["ID"],
                'FIELDS' => $updateFields,
            ]
        );

        if ($stage === 'C1:WON' && $normalizedServices !== [] && empty($result['error'])) {
            $rowsResult = $this->syncDealProductRows((int)$deal['ID'], $normalizedServices);
            $logMessage = json_encode([
                'action' => 'deal_product_rows_synced',
                'deal_id' => (int)$deal['ID'],
                'result' => $rowsResult,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
            file_put_contents('log.json', $logMessage, FILE_APPEND);
        }
    }
    }

    /**
     * Приводит услуги из вебхука к единому виду для товарных строк и суммы.
     *
     * @param array|string|null $services Массив услуг, JSON-строка или одно название (legacy)
     * @param float|string|null $amount   Сумма визита (fallback для цены и OPPORTUNITY)
     * @return array<int, array{name:string,price:float,qty:float,xml_id:?string}>
     */
    private function normalizeServicesInput($services, $amount): array
    {
        if (is_string($services) && $services !== '') {
            $decoded = json_decode($services, true);
            if (is_array($decoded)) {
                $services = $decoded;
            } else {
                return [[
                    'name' => trim($services),
                    'price' => (float)($amount ?? 0),
                    'qty' => 1.0,
                    'xml_id' => null,
                ]];
            }
        }
        if (!is_array($services) || $services === []) {
            return [];
        }
        if ($this->isAssocServiceRow($services)) {
            $services = [$services];
        }
        $out = [];
        foreach ($services as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string)($row['title'] ?? $row['name'] ?? $row['service_name'] ?? $row['service_title'] ?? ''));
            if ($name === '') {
                continue;
            }
            $price = null;
            foreach (['price', 'cost', 'amount', 'sum', 'sum_value', 'service_price'] as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $price = (float)$row[$key];
                    break;
                }
            }
            $qty = 1.0;
            foreach (['quantity', 'qty', 'count'] as $key) {
                if (isset($row[$key]) && is_numeric($row[$key]) && (float)$row[$key] > 0) {
                    $qty = (float)$row[$key];
                    break;
                }
            }
            $xmlId = null;
            if (isset($row['id']) && $row['id'] !== '' && $row['id'] !== null) {
                $xmlId = 'rnova_svc_' . preg_replace('/\W/', '_', (string)$row['id']);
            } elseif (isset($row['service_id']) && $row['service_id'] !== '' && $row['service_id'] !== null) {
                $xmlId = 'rnova_svc_' . preg_replace('/\W/', '_', (string)$row['service_id']);
            }
            $out[] = [
                'name' => $name,
                'price' => $price ?? 0.0,
                'qty' => $qty,
                'xml_id' => $xmlId,
            ];
        }
        return $out;
    }

    private function isAssocServiceRow(array $arr): bool
    {
        return isset($arr['title']) || isset($arr['name']) || !array_is_list($arr);
    }

    /**
     * Текст для пользовательского поля сделки со списком услуг и сумм по строкам.
     *
     * @param array<int, array{name:string,price:float,qty:float}> $normalized
     */
    private function formatServicesForDealField(array $normalized): string
    {
        if ($normalized === []) {
            return '';
        }
        $parts = [];
        foreach ($normalized as $line) {
            $lineSum = $line['price'] * $line['qty'];
            $parts[] = $line['name'] . ': ' . $lineSum;
        }
        return implode("\n", $parts);
    }

    /**
     * Ищет товар в каталоге CRM по XML_ID или по названию; при отсутствии создаёт.
     */
    private function findOrCreateCrmProduct(string $name, float $price, ?string $xmlId): int
    {
        $currency = 'RUB';
        if ($xmlId) {
            $byXml = CRest::call('crm.product.list', [
                'filter' => ['XML_ID' => $xmlId],
                'select' => ['ID'],
            ]);
            if (!empty($byXml['result']) && is_array($byXml['result'])) {
                $id = (int)($byXml['result'][0]['ID'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        }
        $byName = CRest::call('crm.product.list', [
            'filter' => ['NAME' => $name],
            'select' => ['ID'],
        ]);
        if (!empty($byName['result']) && is_array($byName['result'])) {
            foreach ($byName['result'] as $p) {
                $id = (int)($p['ID'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        }
        $fields = [
            'NAME' => $name,
            'PRICE' => $price,
            'CURRENCY_ID' => $currency,
        ];
        if ($xmlId) {
            $fields['XML_ID'] = $xmlId;
        }
        $add = CRest::call('crm.product.add', ['fields' => $fields]);
        if (!empty($add['error'])) {
            error_log('crm.product.add: ' . ($add['error_description'] ?? $add['error']));
            return 0;
        }
        return (int)($add['result'] ?? 0);
    }

    /**
     * Записывает услуги в сделку как товарные строки (каталог или произвольная позиция).
     *
     * @param array<int, array{name:string,price:float,qty:float,xml_id:?string}> $normalized
     * @return array{success:bool,total:float,error?:string}
     */
    private function syncDealProductRows(int $dealId, array $normalized): array
    {
        if ($dealId <= 0 || $normalized === []) {
            return ['success' => true, 'total' => 0.0];
        }
        $rows = [];
        $total = 0.0;
        $sort = 10;
        foreach ($normalized as $line) {
            $productId = $this->findOrCreateCrmProduct($line['name'], $line['price'], $line['xml_id']);
            $row = [
                'PRICE' => $line['price'],
                'QUANTITY' => $line['qty'],
                'DISCOUNT_TYPE_ID' => 2,
                'DISCOUNT_RATE' => 0,
                'MEASURE_CODE' => 796,
                'MEASURE_NAME' => 'шт',
                'SORT' => $sort,
            ];
            $sort += 10;
            if ($productId > 0) {
                $row['PRODUCT_ID'] = $productId;
            } else {
                $row['PRODUCT_NAME'] = $line['name'];
            }
            $rows[] = $row;
            $total += $line['price'] * $line['qty'];
        }
        $set = CRest::call('crm.deal.productrows.set', [
            'id' => $dealId,
            'rows' => $rows,
        ]);
        if (!empty($set['error'])) {
            $err = (string)($set['error_description'] ?? $set['error'] ?? 'unknown');
            error_log('crm.deal.productrows.set: ' . $err);
            return ['success' => false, 'total' => $total, 'error' => $err];
        }
        return ['success' => true, 'total' => $total];
    }

    /**
     * Создает элементы в CRM - автоматически выбирает batch или обычный запрос
     */
    public function createCrmItems(array $items, int $entityTypeId): array
    {
        $requests = $this->prepareCrmItemRequests($items, $entityTypeId);
        return $this->batchExecutor->execute($requests);
    }

    /**
     * Создает одну клинику (удобный метод для обратной совместимости)
     */
    public function createClinic($data) {
        $result = $this->createCrmItems([$data], 1044);
        return reset($result); // Возвращаем первый результат
    }

    /**
     * Создает несколько клиник
     */
    public function createClinics(array $clinicsData): array
    {
        return $this->createCrmItems($clinicsData, 1044);
    }

    private function prepareCrmItemRequests(array $items, int $entityTypeId): array{
        $requests = [];
        
        foreach ($items as $index => $fields) {
            $requestFields = match($entityTypeId) {
                1044 => $this->prepareClinicFields($fields), // Клиники
                1040 => $this->prepareDoctorFields($fields), // Врачи
                default => $fields
            };
            
            $requests["crm_item_add_{$index}"] = [
                'method' => 'crm.item.add',
                'params' => [
                    'entityTypeId' => $entityTypeId,
                    'fields' => $requestFields
                ]
            ];
        }
        
        return $requests;
    }

    /**
     * Подготавливает поля клиники для Bitrix24
     */
    private function prepareClinicFields(array $data): array
    {
        $fields = [
            'title' => $data["title"] ?? "",
            'ufCrm9_Renovatioid' => $data["id"] ?? "",
        ];

        // Убираем пустые поля
        return array_filter($fields, function($value) {
            return $value !== null && $value !== '';
        });
    }

    private function logResult($result) {
        $logMessage = date('Y-m-d H:i:s') . " - Bitrix24 API Response: " . json_encode($result) . PHP_EOL;
        file_put_contents('bitrix24_log.txt', $logMessage, FILE_APPEND);
    }

// Добавьте этот метод в класс Bitrix24Handler
public function createDoctors(array $doctorsData): array
{
    return $this->createCrmItems($doctorsData, 1040); // 1040 - ID смарт-процесса врачей
}

private function prepareDoctorFields(array $data): array
{
    $fields = [
        'title' => $data["name"] ?? "",
        'ufCrm7Renovatioid' => $data["id"] ?? "",
        'ufCrm7Profession' => $this->prepareProfessionField($data["profession_titles"] ?? ""),
        'ufCrm7Clinics' => $this->prepareClinicsField($data["clinic"] ?? []),
    ];

    if (isset($data["birth_date"])) {
        $fields['ufCrm7Birthdate'] = $dateManager->formatDateForBitrix($data["birth_date"]);
    }
    
    if (isset($data["phone"])) {
        $fields['ufCrm7Phone'] = $data["phone"];
    }
    
    if (isset($data["email"])) {
        $fields['ufCrm7Email'] = $data["email"];
    }
    
    if (isset($data["gender"])) {
        $fields['ufCrm7Gender'] = $data["gender"] == 1 ? 'Мужской' : 'Женский';
    }

    // Убираем пустые поля
    return array_filter($fields, function($value) {
        return $value !== null && $value !== '' && $value !== [];
    });
}

private function prepareProfessionField($professionTitles): string
{
    if (is_array($professionTitles)) {
        return implode(", ", array_filter($professionTitles));
    }
    return (string)$professionTitles;
}

private function prepareClinicsField(array $clinicIds): array
{
    $bitrixClinicIds = [];
    
    foreach ($clinicIds as $renovatioClinicId) {
        $bxClinicId = $this->findClinicByRenovatioId($renovatioClinicId);
        if ($bxClinicId) {
            $bitrixClinicIds[] = $bxClinicId;
        }
    }
    
    return $bitrixClinicIds;
}

private function findClinicByRenovatioId($renovatioId): ?int
{
    try {
        $result = CRest::call(
            'crm.item.list',
            [
                'entityTypeId' => 1044, // ID смарт-процесса клиник
                'filter' => [
                    'ufCrm9Renovatioid' => $renovatioId
                ],
                'select' => ['id']
            ]
        );

        if (isset($result['result']['items'][0]['id'])) {
            return $result['result']['items'][0]['id'];
        }
    } catch (Exception $e) {
        error_log("Error finding clinic: " . $e->getMessage());
    }
    
    return null;
}

    public function formatDateForBitrix(string $date): string
    {
        try {
            // Пробуем парсить в формате ISO 8601
            $dateObj = DateTime::createFromFormat(self::ISO_FORMAT, $date);
            
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }
            
            // Пробуем парсить в формате d.m.Y
            $dateObj = DateTime::createFromFormat('d.m.Y', $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }
            
            // Пробуем парсить в формате Bitrix
            $dateObj = DateTime::createFromFormat(self::BITRIX_FORMAT, $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }
            
        } catch (Exception $e) {
            error_log("Date format error for '{$date}': " . $e->getMessage());
        }
        
        return $date;
    }











/**
     * Отображает страницу со статистикой пациента
     */
    public function showPatientStatsPage(): void {
        $patientId = $_GET['patient_id'] ?? null;
        $forceRefresh = isset($_GET['refresh']);
        
        if (!$patientId) {
            $this->renderPatientIdForm();
            return;
        }
        
        try {
            $renovatio = new RenovatioHandler();
            
            if ($forceRefresh) {
                $renovatio->cache->clear((int)$patientId);
            }
            
            $stats = $renovatio->getPatientFullStats((int)$patientId);
            $patientInfo = $renovatio->getPatientInfo((int)$patientId);
            $bitrixContact = $this->findContactByRenovatioPatientId((int)$patientId);
            
            $this->renderPatientStatsPage($patientId, $stats, $patientInfo, $bitrixContact);
            
        } catch (Exception $e) {
            $this->renderErrorPage($e);
        }
    }
    
    private function renderPatientIdForm(): void {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Статистика пациента Renovatio</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #f8f9fa; }
                .container { max-width: 600px; margin-top: 100px; }
                .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">📊 Статистика пациента Renovatio</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">ID пациента в Renovatio:</label>
                                <input type="number" 
                                       class="form-control form-control-lg" 
                                       id="patient_id" 
                                       name="patient_id" 
                                       required 
                                       placeholder="Например: 12345"
                                       autofocus>
                                <div class="form-text">Введите ID пациента из системы Renovatio</div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Показать статистику</button>
                        </form>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    private function renderPatientStatsPage(int $patientId, array $stats, ?array $patientInfo, ?array $bitrixContact): void {
        $statistics = $stats['statistics'];
        $monthlyBreakdown = $stats['monthly_breakdown'];
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Пациент #<?= htmlspecialchars($patientId) ?> - Статистика</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                body { background-color: #f8f9fa; }
                .container { max-width: 1400px; margin-top: 20px; }
                .stat-card { 
                    background: white; 
                    border-radius: 10px; 
                    padding: 20px; 
                    margin-bottom: 20px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .stat-value { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #0d6efd;
                }
                .stat-label { 
                    color: #6c757d; 
                    font-size: 14px; 
                    margin-bottom: 5px;
                }
                .status-badge {
                    padding: 5px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                .status-completed { background: #d4edda; color: #155724; }
                .status-upcoming { background: #fff3cd; color: #856404; }
                .status-cancelled { background: #f8d7da; color: #721c24; }
                .table-hover tbody tr:hover {
                    background-color: #f5f5f5;
                }
                .chart-container {
                    position: relative;
                    height: 300px;
                    margin-bottom: 30px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Заголовок -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        📊 Статистика пациента #<?= htmlspecialchars($patientId) ?>
                        <?php if ($patientInfo): ?>
                            <small class="text-muted fs-5">
                                <?= htmlspecialchars($patientInfo['last_name'] ?? '') ?> 
                                <?= htmlspecialchars($patientInfo['first_name'] ?? '') ?>
                            </small>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <a href="?patient_id=<?= $patientId ?>&refresh=1" class="btn btn-warning me-2">
                            🔄 Обновить данные
                        </a>
                        <a href="?" class="btn btn-secondary">← Назад</a>
                    </div>
                </div>
                
                <!-- Информация о генерации -->
                <div class="alert alert-info">
                    <strong>📅 Данные сгенерированы:</strong> <?= htmlspecialchars($stats['generated_at']) ?><br>
                    <strong>📆 Период данных:</strong> <?= htmlspecialchars($stats['data_period']['from']) ?> - <?= htmlspecialchars($stats['data_period']['to']) ?><br>
                    <strong>📂 Обработано месяцев:</strong> <?= $stats['months_processed'] ?><br>
                    <strong>👤 Контакт в Битрикс:</strong> 
                    <?php if ($bitrixContact): ?>
                        ID: <?= $bitrixContact['ID'] ?> - 
                        Текущее кол-во визитов (завершённых): <?= (int)($bitrixContact['UF_CRM_1776436729'] ?? 0) ?>,
                        Текущая сумма: <?= number_format((float)($bitrixContact['UF_CRM_1776436700'] ?? 0), 2, '.', ' ') ?> ₽
                    <?php else: ?>
                        <span class="text-warning">Не найден</span>
                    <?php endif; ?>
                </div>
                
                <!-- Основные показатели -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Всего визитов</div>
                            <div class="stat-value"><?= $statistics['total_visits'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Завершенных</div>
                            <div class="stat-value text-success"><?= $statistics['completed'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Общая сумма</div>
                            <div class="stat-value text-primary"><?= number_format($statistics['total_paid'], 0, '.', ' ') ?> ₽</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Средний чек</div>
                            <div class="stat-value text-info"><?= number_format($statistics['average_check'], 0, '.', ' ') ?> ₽</div>
                        </div>
                    </div>
                </div>
                
                <!-- График по месяцам -->
                <div class="stat-card">
                    <h4>📈 Динамика по месяцам</h4>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                
                <!-- Детальная таблица по месяцам -->
                <div class="stat-card">
                    <h4>📋 Разбивка по месяцам</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Месяц</th>
                                    <th>Всего визитов</th>
                                    <th>Завершено</th>
                                    <th>Отменено</th>
                                    <th>Предстоящие</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyBreakdown as $month): ?>
                                <tr>
                                    <td><?= htmlspecialchars($month['month']) ?></td>
                                    <td><?= $month['total_visits'] ?></td>
                                    <td class="text-success"><?= $month['completed'] ?></td>
                                    <td class="text-danger"><?= $month['cancelled'] ?></td>
                                    <td class="text-warning"><?= $month['upcoming'] ?></td>
                                    <td><?= number_format($month['total_sum'], 0, '.', ' ') ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Топ услуг -->
                <?php if (!empty($statistics['top_services'])): ?>
                <div class="stat-card">
                    <h4>🔝 Топ услуг</h4>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Услуга</th>
                                    <th>Количество</th>
                                    <th>Общая сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statistics['top_services'] as $serviceName => $data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($serviceName) ?></td>
                                    <td><?= $data['count'] ?></td>
                                    <td><?= number_format($data['total'], 0, '.', ' ') ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Список всех визитов -->
                <div class="stat-card">
                    <h4>📝 Все визиты</h4>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>id</th>
                                    <th>Дата</th>
                                    <th>Клиника</th>
                                    <th>Врач</th>
                                    <th>Статус</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['visits'] as $visit): ?>
                                <tr>
                                    <td><?= htmlspecialchars($visit['id'] ?? 'Н/Д') ?></td>
                                    <td><?= htmlspecialchars($visit['time_start'] ?? 'Н/Д') ?></td>
                                    <td><?= htmlspecialchars($visit['clinic_title'] ?? 'Н/Д') ?></td>
                                    <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Н/Д') ?></td>
                                    <td>
                                        <?php
                                        $status = $visit['status'] ?? 'unknown';
                                        $statusClass = match($status) {
                                            'completed' => 'status-completed',
                                            'upcoming' => 'status-upcoming',
                                            'refused', 'cancelled' => 'status-cancelled',
                                            default => ''
                                        };
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($visit['sum_value'] ?? 0, 0, '.', ' ') ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($stats['errors'])): ?>
                <div class="alert alert-warning">
                    <h5>⚠️ Ошибки при загрузке:</h5>
                    <ul>
                        <?php foreach ($stats['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error['period']) ?>: <?= htmlspecialchars($error['error']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <script>
                // График по месяцам
                const ctx = document.getElementById('monthlyChart').getContext('2d');
                const monthlyData = <?= json_encode($monthlyBreakdown) ?>;
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(m => m.month),
                        datasets: [{
                            label: 'Сумма (₽)',
                            data: monthlyData.map(m => m.sum_value),
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            yAxisID: 'y',
                            tension: 0.1
                        }, {
                            label: 'Количество визитов',
                            data: monthlyData.map(m => m.completed),
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Сумма (₽)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Количество визитов'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        }
                    }
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    private function renderErrorPage(Exception $e): void {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Ошибка</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-danger">
                    <h4>❌ Произошла ошибка</h4>
                    <p><?= htmlspecialchars($e->getMessage()) ?></p>
                    <hr>
                    <pre class="small"><?= htmlspecialchars($e->getTraceAsString()) ?></pre>
                </div>
                <a href="?" class="btn btn-primary">← Назад</a>
            </div>
        </body>
        </html>
        <?php
    }
}
class RenovatioHandler {
    private $apiKey;
    private $baseUrl = 'https://app.rnova.org/api/public/';
    private PatientStatsCache $cache;

    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?? $_ENV['RENOVATIO_API_KEY'] ?? 'fb95bb02394e26d9e79e6955115eb202';
        $this->cache = new PatientStatsCache();
    }
    
    /**
     * Получает информацию о пациенте
     */
    public function getPatientInfo(int $patientId): ?array {
        $url = $this->baseUrl . 'getPatients?api_key=' . $this->apiKey;
        
        $postData = [
            'patient_id' => $patientId
        ];
        
        try {
            $response = ApiClient::makeCurlRequest($url, $postData);
            
            if (isset($response['data']) && !empty($response['data'])) {
                return $response['data'][0] ?? null;
            }
        } catch (Exception $e) {
            error_log("Error getting patient info: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function getAppointmentsForPeriod(string $dateFrom, string $dateTo): array {
        $url = $this->baseUrl . 'v2/getAppointments?api_key=' . $this->apiKey;
        
        $postData = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        $response = ApiClient::makeCurlRequest($url, $postData);
        
        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Ошибка получения визитов: ' . ($response['error_description'] ?? 'Неизвестная ошибка'));
        }
        
        return $response['data'] ?? [];
    }
    
    public function getPatientFullStats(int $patientId, bool $forceRefresh = false): array {
        if (!$forceRefresh) {
            $cached = $this->cache->get($patientId);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $startDate = $this->getPatientFirstVisitDate($patientId) ?? date('Y-m-d', strtotime('-4 years'));
        $endDate = date('Y-m-d');
        
        $allAppointments = [];
        $currentDate = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        
        $monthsProcessed = 0;
        $errors = [];
        
        while ($currentDate <= $endDateTime) {
            $monthStart = $currentDate->format('d.m.Y');
            $monthEnd = (clone $currentDate)->modify('last day of this month')->format('d.m.Y');
            
            try {
                $monthAppointments = $this->getAppointmentsForPeriod($monthStart, $monthEnd);
                
                $patientMonthAppointments = array_filter($monthAppointments, function($app) use ($patientId) {
                    return isset($app['patient_id']) && $app['patient_id'] == $patientId;
                });
                
                $allAppointments = array_merge($allAppointments, $patientMonthAppointments);
                $monthsProcessed++;
                
                error_log(sprintf(
                    "Loaded appointments for patient %d: %s - %s (%d visits)",
                    $patientId,
                    $monthStart,
                    $monthEnd,
                    count($patientMonthAppointments)
                ));
                
            } catch (Exception $e) {
                $errors[] = [
                    'period' => $monthStart . ' - ' . $monthEnd,
                    'error' => $e->getMessage()
                ];
                error_log("Error loading month {$monthStart}-{$monthEnd}: " . $e->getMessage());
            }
            
            $currentDate->modify('first day of next month');
            usleep(100000);
        }
        
        usort($allAppointments, function($a, $b) {
            $dateA = DateTime::createFromFormat('d.m.Y H:i', $a['time_start'] ?? '01.01.2000 00:00');
            $dateB = DateTime::createFromFormat('d.m.Y H:i', $b['time_start'] ?? '01.01.2000 00:00');
            
            if (!$dateA || !$dateB) {
                return 0;
            }
            
            return $dateB->getTimestamp() - $dateA->getTimestamp();
        });
        
        $stats = $this->calculateVisitStats($allAppointments);
        
        $result = [
            'patient_id' => $patientId,
            'generated_at' => date('Y-m-d H:i:s'),
            'data_period' => [
                'from' => $startDate,
                'to' => $endDate
            ],
            'months_processed' => $monthsProcessed,
            'total_visits_loaded' => count($allAppointments),
            'statistics' => $stats,
            'visits' => $allAppointments,
            'monthly_breakdown' => $this->calculateMonthlyBreakdown($allAppointments),
            'errors' => $errors
        ];
        
        $this->cache->set($patientId, $result);
        $this->saveDetailedLog($patientId, $result);
        
        return $result;
    }
    
    private function calculateVisitStats(array $appointments): array {
        $totalPaid = 0.0;
        $completedCount = 0;
        $cancelledCount = 0;
        $upcomingCount = 0;
        $otherCount = 0;
        
        $servicesStats = [];
        $clinicsStats = [];
        $doctorsStats = [];
        
        foreach ($appointments as $appointment) {
            $status = $appointment['status'] ?? 'unknown';
            $sum = $this->getAppointmentSum($appointment);
            
            switch ($status) {
                case 'completed':
                    $completedCount++;
                    $totalPaid += $sum;
                    
                    if (isset($appointment['services'])) {
                        foreach ($appointment['services'] as $service) {
                            $serviceName = $service['title'] ?? 'Неизвестная услуга';
                            if (!isset($servicesStats[$serviceName])) {
                                $servicesStats[$serviceName] = ['count' => 0, 'total' => 0];
                            }
                            $servicesStats[$serviceName]['count']++;
                            $servicesStats[$serviceName]['total'] += (float)($service['price'] ?? 0);
                        }
                    }
                    
                    $clinicTitle = $appointment['clinic_title'] ?? 'Неизвестная клиника';
                    if (!isset($clinicsStats[$clinicTitle])) {
                        $clinicsStats[$clinicTitle] = ['count' => 0, 'total' => 0];
                    }
                    $clinicsStats[$clinicTitle]['count']++;
                    $clinicsStats[$clinicTitle]['total'] += $sum;
                    
                    $doctorName = $appointment['doctor_name'] ?? 'Неизвестный врач';
                    if (!isset($doctorsStats[$doctorName])) {
                        $doctorsStats[$doctorName] = ['count' => 0, 'total' => 0];
                    }
                    $doctorsStats[$doctorName]['count']++;
                    $doctorsStats[$doctorName]['total'] += $sum;
                    
                    break;
                case 'refused':
                case 'cancelled':
                    $cancelledCount++;
                    break;
                case 'upcoming':
                    $upcomingCount++;
                    break;
                default:
                    $otherCount++;
            }
        }
        
        return [
            'completed' => $completedCount,
            'cancelled' => $cancelledCount,
            'upcoming' => $upcomingCount,
            'other' => $otherCount,
            'total_visits' => count($appointments),
            'total_paid' => $totalPaid,
            'average_check' => $completedCount > 0 ? round($totalPaid / $completedCount, 2) : 0,
            'top_services' => array_slice($servicesStats, 0, 10),
            'top_clinics' => array_slice($clinicsStats, 0, 5),
            'top_doctors' => array_slice($doctorsStats, 0, 5)
        ];
    }
    
    /**
 * Получает сумму визита из разных форматов данных
 * 
 * @param array $appointment Данные визита
 * @return float Сумма визита
 */
private function getAppointmentSum(array $appointment): float {
    // Пробуем получить сумму из разных возможных ключей
    $possibleKeys = ['sum_value', 'sum', 'amount', 'total', 'price', 'cost'];
    
    foreach ($possibleKeys as $key) {
        if (isset($appointment[$key]) && $appointment[$key] !== null && $appointment[$key] !== '') {
            // Пробуем преобразовать в float, если это строка с запятой
            $value = $appointment[$key];
            if (is_string($value)) {
                // Заменяем запятую на точку для корректного преобразования
                $value = str_replace(',', '.', $value);
            }
            $floatValue = (float)$value;
            if ($floatValue > 0) {
                return $floatValue;
            }
        }
    }
        
        // Если сумма не найдена в основных ключах, пробуем посчитать из услуг
        if (isset($appointment['services']) && is_array($appointment['services'])) {
            $servicesSum = 0.0;
            foreach ($appointment['services'] as $service) {
                $servicePrice = 0;
                if (isset($service['price'])) {
                    $priceValue = is_string($service['price']) ? str_replace(',', '.', $service['price']) : $service['price'];
                    $servicePrice = (float)$priceValue;
                } elseif (isset($service['cost'])) {
                    $priceValue = is_string($service['cost']) ? str_replace(',', '.', $service['cost']) : $service['cost'];
                    $servicePrice = (float)$priceValue;
                }
                $servicesSum += $servicePrice;
            }
            if ($servicesSum > 0) {
                return $servicesSum;
            }
        }
        
        return 0.0;
    }

    private function calculateMonthlyBreakdown(array $appointments): array {
        $monthly = [];
        
        foreach ($appointments as $appointment) {
            if (empty($appointment['time_start'])) {
                continue;
            }
            
            $date = DateTime::createFromFormat('d.m.Y H:i', $appointment['time_start']);
            if (!$date) {
                continue;
            }
            
            $monthKey = $date->format('Y-m');
            
            if (!isset($monthly[$monthKey])) {
                $monthly[$monthKey] = [
                    'month' => $date->format('F Y'),
                    'total_visits' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                    'upcoming' => 0,
                    'total_sum' => 0
                ];
            }
            
            $monthly[$monthKey]['total_visits']++;
            
            $status = $appointment['status'] ?? 'unknown';
            if ($status === 'completed') {
                $monthly[$monthKey]['completed']++;
                $monthly[$monthKey]['total_sum'] += (float)($appointment['sum_value'] ?? 0);
            } elseif (in_array($status, ['refused', 'cancelled'])) {
                $monthly[$monthKey]['cancelled']++;
            } elseif ($status === 'upcoming') {
                $monthly[$monthKey]['upcoming']++;
            }
        }
        
        ksort($monthly);
        
        return array_values($monthly);
    }
    
    private function getPatientFirstVisitDate(int $patientId): ?string {
        try {
            $patientInfo = $this->getPatientInfo($patientId);
            
            if ($patientInfo && isset($patientInfo['created_at'])) {
                return date('Y-m-d', strtotime($patientInfo['created_at']));
            }
        } catch (Exception $e) {
            error_log("Error getting patient first visit date: " . $e->getMessage());
        }
        
        return date('Y-m-d', strtotime('-4 years'));
    }
    
    private function saveDetailedLog(int $patientId, array $data): void {
        $logDir = __DIR__ . '/../logs/patient_stats/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $filename = sprintf('%s/patient_%d_%s.json', 
            $logDir, 
            $patientId, 
            date('Y-m-d_His')
        );
        
        file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    public function getPatientVisitStats(int $patientId, bool $forceRefresh = false): array {
        $stats = $this->getPatientFullStats($patientId, $forceRefresh);

        return [
            'total_paid' => $stats['statistics']['total_paid'] ?? 0,
            'visit_count' => $stats['statistics']['completed'] ?? 0,
        ];
    }
    
    public function getClinics() {
        $url = $this->baseUrl . 'getClinics?api_key=' . $this->apiKey;
        $result = ApiClient::makeCurlRequest($url);
        return $result;
    }

    public function getAppointments($dateFrom, $dateTo) {
        $url = $this->baseUrl . 'v2/getAppointments?api_key=' . $this->apiKey;
        
        $postData = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        $result = ApiClient::makeCurlRequest($url, $postData);
        return $result;
    }

    public function getCalendar($doctorId, $clinicId, $timeStart, $timeEnd) {
        $dateManager = new DateManager();
        header('Content-Type: application/json');
        $url = $this->baseUrl . 'getSchedule?api_key=' . $this->apiKey;
        $renovatioTimeStart = $dateManager->bitrixToRenovatio($timeStart);
        $renovatioTimeEnd = $dateManager->bitrixToRenovatio($timeEnd);

        $postData = [
            'user_id' => $doctorId,
            'clinic_id' => $clinicId,
            'step' => 10,
            'time_start' => $renovatioTimeStart,
            "time_end" => $renovatioTimeEnd,
        ];

        $freeSlots = ApiClient::makeCurlRequest($url, $postData);

        $url = $this->baseUrl . 'getSchedulePeriods?api_key=' . $this->apiKey;
        $postData = [
            'user_id' => $doctorId,
            'clinic_id' => $clinicId,
            "time_start" => $renovatioTimeStart,
            "time_end" => $renovatioTimeEnd,
        ];

        $schedule = ApiClient::makeCurlRequest($url, $postData);

        $appointments = $this->getAppointments($renovatioTimeStart, $renovatioTimeEnd);
        $scheduleData = $schedule['data'] ?? [];
        $appointmentsData = $appointments['data'] ?? [];
        $canCalculateSlots = $this->isListArray($scheduleData) && $this->isListArray($appointmentsData);

        $calculatedFreeSlots = $canCalculateSlots
            ? $this->buildFreeSlotsFromSchedulePeriods(
                (string)$doctorId,
                (string)$clinicId,
                $scheduleData,
                $appointmentsData,
                10
            )
            : [];

        $freeSlotsData = !empty($calculatedFreeSlots)
            ? $calculatedFreeSlots
            : ($freeSlots['data'] ?? []);

        return json_encode([
            'freeSlots' => $freeSlotsData,
            'schedule' => $scheduleData
        ]);
    }

    private function buildFreeSlotsFromSchedulePeriods(string $doctorId, string $clinicId, array $schedulePeriods, array $appointments, int $stepMinutes = 10): array
    {
        $workingPeriods = array_values(array_filter($schedulePeriods, function ($period) use ($doctorId, $clinicId) {
            if (!is_array($period)) {
                return false;
            }

            return (string)($period['user_id'] ?? '') === $doctorId
                && (string)($period['clinic_id'] ?? '') === $clinicId
                && (int)($period['type'] ?? 0) === 1;
        }));

        if (empty($workingPeriods)) {
            return [];
        }

        $busyRanges = [];

        foreach ($schedulePeriods as $period) {
            if (!is_array($period)) {
                continue;
            }

            if ((string)($period['user_id'] ?? '') !== $doctorId || (string)($period['clinic_id'] ?? '') !== $clinicId) {
                continue;
            }

            if ((int)($period['type'] ?? 0) === 1) {
                continue;
            }

            $start = $this->parseRenovatioDateTime($period['time_start'] ?? null);
            $end = $this->parseRenovatioDateTime($period['time_end'] ?? null);
            if ($start && $end && $start < $end) {
                $busyRanges[] = ['start' => $start, 'end' => $end];
            }
        }

        foreach ($appointments as $appointment) {
            if (!is_array($appointment)) {
                continue;
            }

            if ((string)($appointment['doctor_id'] ?? '') !== $doctorId || (string)($appointment['clinic_id'] ?? '') !== $clinicId) {
                continue;
            }

            if ($this->isAppointmentCancelled($appointment)) {
                continue;
            }

            $start = $this->parseRenovatioDateTime($appointment['time_start'] ?? null);
            $end = $this->parseRenovatioDateTime($appointment['time_end'] ?? null);
            if ($start && $end && $start < $end) {
                $busyRanges[] = ['start' => $start, 'end' => $end];
            }
        }

        $result = [$doctorId => []];
        $seenSlots = [];

        foreach ($workingPeriods as $period) {
            $workStart = $this->parseRenovatioDateTime($period['time_start'] ?? null);
            $workEnd = $this->parseRenovatioDateTime($period['time_end'] ?? null);
            if (!$workStart || !$workEnd || $workStart >= $workEnd) {
                continue;
            }

            $current = clone $workStart;
            while ($current < $workEnd) {
                $slotEnd = (clone $current)->modify("+{$stepMinutes} minutes");
                if ($slotEnd > $workEnd) {
                    break;
                }

                if (!$this->rangeOverlapsBusyRanges($current, $slotEnd, $busyRanges)) {
                    $slotKey = $current->format('Y-m-d H:i:s') . '_' . $slotEnd->format('Y-m-d H:i:s');
                    if (!isset($seenSlots[$slotKey])) {
                        $result[$doctorId][] = $this->formatFreeSlot($period, $current, $slotEnd);
                        $seenSlots[$slotKey] = true;
                    }
                }

                $current = $slotEnd;
            }
        }

        usort($result[$doctorId], function ($a, $b) {
            return strcmp($a['time_start'], $b['time_start']);
        });

        return $result;
    }

    private function isListArray($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return empty($value) || array_keys($value) === range(0, count($value) - 1);
    }

    private function parseRenovatioDateTime(?string $value): ?DateTime
    {
        if (!$value) {
            return null;
        }

        foreach (['d.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date;
            }
        }

        return null;
    }

    private function isAppointmentCancelled(array $appointment): bool
    {
        $status = mb_strtolower((string)($appointment['status'] ?? ''), 'UTF-8');
        $cancelledStatuses = ['refused', 'cancelled', 'canceled'];

        return in_array($status, $cancelledStatuses, true)
            || (int)($appointment['status_id'] ?? 0) === 5;
    }

    private function rangeOverlapsBusyRanges(DateTime $start, DateTime $end, array $busyRanges): bool
    {
        foreach ($busyRanges as $range) {
            if ($start < $range['end'] && $end > $range['start']) {
                return true;
            }
        }

        return false;
    }

    private function formatFreeSlot(array $period, DateTime $start, DateTime $end): array
    {
        $date = $start->format('d.m.Y');

        return [
            'schedule_id' => $period['id'] ?? $period['schedule_id'] ?? null,
            'user_id' => $period['user_id'] ?? null,
            'clinic_id' => $period['clinic_id'] ?? null,
            'date' => $date,
            'time_start' => $start->format('Y-m-d H:i:s'),
            'time_end' => $end->format('Y-m-d H:i:s'),
            'time' => $start->format('H:i') . ' - ' . $end->format('H:i'),
            'time_start_short' => $start->format('H:i'),
            'time_end_short' => $end->format('H:i'),
            'category_id' => $period['category_id'] ?? null,
            'room' => $period['room'] ?? null,
            'is_busy' => false,
            'is_past' => false,
            '_date' => $start->format('Y-m-d'),
            'beautyDate' => $date,
            'extra' => [10],
            '_extra' => 10,
        ];
    }

    public function getServices($doctorId, $clinicId) {
        header('Content-Type: application/json');
        $url = $this->baseUrl . 'getServices?api_key=' . $this->apiKey;

        $postData = [
            'user_id' => $doctorId,
            'clinic_id' => $clinicId
        ];

        $services = ApiClient::makeCurlRequest($url, $postData);
        return json_encode([
            'services' => $services['data']
        ]);
    }
    
    public function getUsers(){
        $url = $this->baseUrl . 'getUsers?api_key=' . $this->apiKey;
        $result = ApiClient::makeCurlRequest($url);
        return $result;
    }

    private function normalizeServiceNameForMatch(?string $name): string
    {
        $normalized = mb_strtolower(trim((string)$name), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $normalized) ?? '';
    }

    private function extractRenovatioServiceIdFromXmlId(?string $xmlId): ?string
    {
        if (!$xmlId) {
            return null;
        }

        if (preg_match('/^rnova_svc_(.+)$/', (string)$xmlId, $matches)) {
            return $matches[1] !== '' ? $matches[1] : null;
        }

        return null;
    }

    private function getCrmProductXmlId(int $productId): ?string
    {
        if ($productId <= 0) {
            return null;
        }

        $product = CRest::call('crm.product.get', ['id' => $productId]);
        if (!empty($product['error'])) {
            error_log('crm.product.get: ' . ($product['error_description'] ?? $product['error']));
            return null;
        }

        $xmlId = $product['result']['XML_ID'] ?? null;
        return $xmlId !== null && $xmlId !== '' ? (string)$xmlId : null;
    }

    private function getDealProductRows(int $dealId): array
    {
        if ($dealId <= 0) {
            return [];
        }

        $rows = CRest::call('crm.deal.productrows.get', ['id' => $dealId]);
        if (!empty($rows['error'])) {
            error_log('crm.deal.productrows.get: ' . ($rows['error_description'] ?? $rows['error']));
            return [];
        }

        return is_array($rows['result'] ?? null) ? $rows['result'] : [];
    }

    private function getRenovatioServiceMapByName($doctorId, $clinicId): array
    {
        $url = $this->baseUrl . 'getServices?api_key=' . $this->apiKey;
        $response = ApiClient::makeCurlRequest($url, [
            'user_id' => $doctorId,
            'clinic_id' => $clinicId,
        ]);

        $services = is_array($response['data'] ?? null) ? $response['data'] : [];
        $map = [];

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $serviceId = $service['service_id'] ?? $service['id'] ?? null;
            $title = $service['title'] ?? $service['name'] ?? $service['sub_code'] ?? null;
            $key = $this->normalizeServiceNameForMatch($title);

            if ($serviceId !== null && $serviceId !== '' && $key !== '') {
                $map[$key] = (string)$serviceId;
            }
        }

        return $map;
    }

    private function getAppointmentServicesFromDealProducts(int $dealId, $doctorId, $clinicId, $fallbackServiceId): array
    {
        $productRows = $this->getDealProductRows($dealId);
        if ($productRows === []) {
            return [['service_id' => $fallbackServiceId]];
        }

        $serviceMapByName = null;
        $services = [];
        $seen = [];
        $skipped = [];

        foreach ($productRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $serviceId = null;
            foreach (['XML_ID', 'PRODUCT_XML_ID'] as $xmlKey) {
                $serviceId = $this->extractRenovatioServiceIdFromXmlId($row[$xmlKey] ?? null);
                if ($serviceId) {
                    break;
                }
            }

            if (!$serviceId) {
                $productId = (int)($row['PRODUCT_ID'] ?? 0);
                $serviceId = $this->extractRenovatioServiceIdFromXmlId($this->getCrmProductXmlId($productId));
            }

            if (!$serviceId) {
                if ($serviceMapByName === null) {
                    $serviceMapByName = $this->getRenovatioServiceMapByName($doctorId, $clinicId);
                }

                $nameKey = $this->normalizeServiceNameForMatch($row['PRODUCT_NAME'] ?? null);
                $serviceId = $nameKey !== '' ? ($serviceMapByName[$nameKey] ?? null) : null;
            }

            if ($serviceId) {
                if (!isset($seen[$serviceId])) {
                    $services[] = ['service_id' => $serviceId];
                    $seen[$serviceId] = true;
                }
            } else {
                $skipped[] = [
                    'PRODUCT_ID' => $row['PRODUCT_ID'] ?? null,
                    'PRODUCT_NAME' => $row['PRODUCT_NAME'] ?? null,
                ];
            }
        }

        $logMessage = json_encode([
            'action' => 'appointment_services_from_deal_products',
            'deal_id' => $dealId,
            'product_rows_count' => count($productRows),
            'services' => $services,
            'skipped_rows' => $skipped,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        file_put_contents('log.json', $logMessage, FILE_APPEND);

        return $services !== [] ? $services : [['service_id' => $fallbackServiceId]];
    }

    public function createAppointment($bxId, $serviceId) {
        $dateManager = new DateManager();

        $dealResult = CRest::call('crm.deal.get', ['ID' => $bxId]);

        if (isset($dealResult['error'])) {
            throw new Exception("Ошибка получения сделки: " . $dealResult['error_description']);
        }
        
        if (empty($dealResult['result'])) {
            throw new Exception("Сделка с ID {$bxId} не найдена");
        }
        
        $deal = $dealResult['result'];

        $doctor = CRest::call(
            'crm.item.get',
            [
                'entityTypeId' => 1040,
                'id' => $deal["UF_CRM_1761998673"]
            ]
        )['result']['item'];

        $clinic = CRest::call(
            'crm.item.get',
            [
                'entityTypeId' => 1044,
                'id' => $deal["UF_CRM_1762175501"]
            ]
        )['result']['item'];

        $contact = CRest::call(
            'crm.contact.get',
            [
                'id' => $deal["CONTACT_ID"]
            ]
        )['result'];

        $appointmentServices = $this->getAppointmentServicesFromDealProducts(
            (int)$bxId,
            $doctor['ufCrm7Renovatioid'],
            $clinic['ufCrm9Renovatioid'],
            $serviceId
        );

        $appointmentData = [
            "first_name" => $contact["NAME"],
            "last_name" => $contact["LAST_NAME"],
            "third_name" => $contact["SECOND_NAME"],
            "birth_date" => $contact["BIRTHDATE"],
            "doctor_id" => $doctor['ufCrm7Renovatioid'],
            "time_start" => $dateManager->bitrixToRenovatio($deal["UF_CRM_1726973347808"]),
            "time_end" => $dateManager->bitrixToRenovatio($deal["UF_CRM_1762178514"]),
            "clinic_id" => $clinic["ufCrm9Renovatioid"],
            "services" => $appointmentServices
        ];

        header('Content-Type: application/json');
        $url = $this->baseUrl . 'createAppointment?api_key=' . $this->apiKey;
        $result = ApiClient::makeCurlRequest($url, $appointmentData);

        CRest::call(
            'crm.deal.update',
            [
                'ID' => $bxId,
                'FIELDS' => [
                    'UF_CRM_1729241550' => $result["data"],
                    'STAGE_ID' => 'C1:EXECUTING',
                ],
            ]
        );

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Пример использования для создания клиник
try {
    $renovatio = new RenovatioHandler('fb95bb02394e26d9e79e6955115eb202');
    $bx = new Bitrix24Handler();
    /*
    // Получение врачей из Renovatio
    $response = $renovatio->getUsers();
    
    if (isset($response['data']) && is_array($response['data'])) {
        $doctors = $response['data'];
        
        if (!empty($doctors)) {
            echo "Найдено врачей: " . count($doctors) . "\n";
            
            // Фильтруем только врачей (убираем администраторов и других пользователей)
            $filteredDoctors = array_filter($doctors, function($doctor) {
                return in_array('doctor', $doctor['role_names'] ?? []);
            });
            
            echo "Врачей после фильтрации: " . count($filteredDoctors) . "\n";
            
            if (!empty($filteredDoctors)) {
                // Автоматически будет выбран batch или обычный запрос в зависимости от количества
                $results = $bx->createDoctors($filteredDoctors);
                
                // Вывод результатов
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($results as $key => $result) {
                    if ($result['success']) {
                        $doctorId = $result['result']['item']['id'] ?? 'unknown';
                        $doctorName = $filteredDoctors[$key]['name'] ?? 'Неизвестный врач';
                        echo "✓ Успешно создан врач ID: {$doctorId} - {$doctorName}\n";
                        $successCount++;
                    } else {
                        $doctorName = $filteredDoctors[$key]['name'] ?? 'Неизвестный врач';
                        echo "✗ Ошибка при создании врача {$doctorName}: {$result['error']}\n";
                        $errorCount++;
                    }
                }
                
                echo "\nИтог: успешно - {$successCount}, с ошибками - {$errorCount}\n";
            } else {
                echo "Нет врачей для импорта\n";
            }
        } else {
            echo "Нет данных о врачах для импорта\n";
        }
    } else {
        echo "Неверный формат ответа от Renovatio API\n";
        print_r($response);
    }*/
    /*
    if (isset($response['data']) && is_array($response['data'])) {
        $clinics = $response['data'];

        if (!empty($clinics)) {
            echo "Найдено клиник: " . count($clinics) . "\n";
            
            // Автоматически будет выбран batch или обычный запрос в зависимости от количества
            $results = $bx->createClinics($clinics);
            
            // Вывод результатов
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($results as $key => $result) {
                if ($result['success']) {
                    $clinicId = $result['result']['item']['id'] ?? 'unknown';
                    echo "✓ Успешно создана клиника ID: {$clinicId}\n";
                    $successCount++;
                } else {
                    echo "✗ Ошибка при создании клиники: {$result['error']}\n";
                    $errorCount++;
                }
            }
            
            echo "\nИтог: успешно - {$successCount}, с ошибками - {$errorCount}\n";
        } else {
            echo "Нет данных о клиниках для импорта\n";
        }
    } else {
        echo "Неверный формат ответа от Renovatio API\n";
        print_r($response);
    }*/
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>