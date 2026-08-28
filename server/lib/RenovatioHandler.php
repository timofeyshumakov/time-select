<?php

declare(strict_types=1);

class RenovatioHandler
{
    private $apiKey;
    private string $baseUrl;
    public PatientStatsCache $cache;
    private FreeSlotCalculator $slotCalculator;

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?? Env::get('RENOVATIO_API_KEY');
        if (!$this->apiKey) {
            throw new RuntimeException('RENOVATIO_API_KEY is not set');
        }
        $this->baseUrl = rtrim(Env::get('RENOVATIO_API_BASE_URL', 'https://app.rnova.org/api/public/'), '/') . '/';
        $this->cache = new PatientStatsCache();
        $this->slotCalculator = new FreeSlotCalculator();
    }

    /**
     * Получает информацию о пациенте
     */
    public function getPatientInfo(int $patientId): ?array
    {
        $url = $this->baseUrl . 'getPatients?api_key=' . $this->apiKey;

        $postData = [
            'patient_id' => $patientId,
        ];

        try {
            $response = ApiClient::makeCurlRequest($url, $postData);

            if (isset($response['data']) && !empty($response['data'])) {
                return $response['data'][0] ?? null;
            }
        } catch (Exception $e) {
            error_log('Error getting patient info: ' . $e->getMessage());
        }

        return null;
    }

    private function getAppointmentsForPeriod(string $dateFrom, string $dateTo): array
    {
        $url = $this->baseUrl . 'v2/getAppointments?api_key=' . $this->apiKey;

        $postData = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $response = ApiClient::makeCurlRequest($url, $postData);

        if (isset($response['error']) && $response['error'] != 0) {
            throw new Exception('Ошибка получения визитов: ' . ($response['error_description'] ?? 'Неизвестная ошибка'));
        }

        return $response['data'] ?? [];
    }

    public function getPatientFullStats(int $patientId, bool $forceRefresh = false): array
    {
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

                $patientMonthAppointments = array_filter($monthAppointments, function ($app) use ($patientId) {
                    return isset($app['patient_id']) && $app['patient_id'] == $patientId;
                });

                $allAppointments = array_merge($allAppointments, $patientMonthAppointments);
                $monthsProcessed++;

                error_log(sprintf(
                    'Loaded appointments for patient %d: %s - %s (%d visits)',
                    $patientId,
                    $monthStart,
                    $monthEnd,
                    count($patientMonthAppointments)
                ));
            } catch (Exception $e) {
                $errors[] = [
                    'period' => $monthStart . ' - ' . $monthEnd,
                    'error' => $e->getMessage(),
                ];
                error_log("Error loading month {$monthStart}-{$monthEnd}: " . $e->getMessage());
            }

            $currentDate->modify('first day of next month');
            usleep(100000);
        }

        usort($allAppointments, function ($a, $b) {
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
                'to' => $endDate,
            ],
            'months_processed' => $monthsProcessed,
            'total_visits_loaded' => count($allAppointments),
            'statistics' => $stats,
            'visits' => $allAppointments,
            'monthly_breakdown' => $this->calculateMonthlyBreakdown($allAppointments),
            'errors' => $errors,
        ];

        $this->cache->set($patientId, $result);
        $this->saveDetailedLog($patientId, $result);

        return $result;
    }

    private function calculateVisitStats(array $appointments): array
    {
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
            'top_doctors' => array_slice($doctorsStats, 0, 5),
        ];
    }

    /**
     * Получает сумму визита из разных форматов данных
     */
    private function getAppointmentSum(array $appointment): float
    {
        $possibleKeys = ['sum_value', 'sum', 'amount', 'total', 'price', 'cost'];

        foreach ($possibleKeys as $key) {
            if (isset($appointment[$key]) && $appointment[$key] !== null && $appointment[$key] !== '') {
                $value = $appointment[$key];
                if (is_string($value)) {
                    $value = str_replace(',', '.', $value);
                }
                $floatValue = (float)$value;
                if ($floatValue > 0) {
                    return $floatValue;
                }
            }
        }

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

    private function calculateMonthlyBreakdown(array $appointments): array
    {
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
                    'total_sum' => 0,
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

    private function getPatientFirstVisitDate(int $patientId): ?string
    {
        try {
            $patientInfo = $this->getPatientInfo($patientId);

            if ($patientInfo && isset($patientInfo['created_at'])) {
                return date('Y-m-d', strtotime($patientInfo['created_at']));
            }
        } catch (Exception $e) {
            error_log('Error getting patient first visit date: ' . $e->getMessage());
        }

        return date('Y-m-d', strtotime('-4 years'));
    }

    private function saveDetailedLog(int $patientId, array $data): void
    {
        $logDir = __DIR__ . '/../logs/patient_stats/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $filename = sprintf(
            '%s/patient_%d_%s.json',
            $logDir,
            $patientId,
            date('Y-m-d_His')
        );

        file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function getPatientVisitStats(int $patientId, bool $forceRefresh = false): array
    {
        $stats = $this->getPatientFullStats($patientId, $forceRefresh);

        return [
            'total_paid' => $stats['statistics']['total_paid'] ?? 0,
            'visit_count' => $stats['statistics']['completed'] ?? 0,
        ];
    }

    public function getClinics()
    {
        $url = $this->baseUrl . 'getClinics?api_key=' . $this->apiKey;
        $result = ApiClient::makeCurlRequest($url);

        return $result;
    }

    public function getAppointments($dateFrom, $dateTo)
    {
        $url = $this->baseUrl . 'v2/getAppointments?api_key=' . $this->apiKey;

        $postData = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $result = ApiClient::makeCurlRequest($url, $postData);

        return $result;
    }

    public function getCalendar($doctorId, $clinicId, $timeStart, $timeEnd)
    {
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
            'time_end' => $renovatioTimeEnd,
        ];

        $freeSlots = ApiClient::makeCurlRequest($url, $postData);

        $url = $this->baseUrl . 'getSchedulePeriods?api_key=' . $this->apiKey;
        $postData = [
            'user_id' => $doctorId,
            'clinic_id' => $clinicId,
            'time_start' => $renovatioTimeStart,
            'time_end' => $renovatioTimeEnd,
        ];

        $schedule = ApiClient::makeCurlRequest($url, $postData);

        $appointments = $this->getAppointments($renovatioTimeStart, $renovatioTimeEnd);
        $scheduleData = $schedule['data'] ?? [];
        $appointmentsData = $appointments['data'] ?? [];
        $canCalculateSlots = $this->slotCalculator->isListArray($scheduleData)
            && $this->slotCalculator->isListArray($appointmentsData);

        $calculatedFreeSlots = $canCalculateSlots
            ? $this->slotCalculator->build(
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
            'schedule' => $scheduleData,
        ]);
    }

    public function getServices($doctorId, $clinicId)
    {
        header('Content-Type: application/json');
        $url = $this->baseUrl . 'getServices?api_key=' . $this->apiKey;

        $postData = [
            'user_id' => $doctorId,
            'clinic_id' => $clinicId,
        ];

        $services = ApiClient::makeCurlRequest($url, $postData);

        return json_encode([
            'services' => $services['data'],
        ]);
    }

    public function getUsers()
    {
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

    public function createAppointment($bxId, $serviceId)
    {
        $dateManager = new DateManager();

        $dealResult = CRest::call('crm.deal.get', ['ID' => $bxId]);

        if (isset($dealResult['error'])) {
            throw new Exception('Ошибка получения сделки: ' . $dealResult['error_description']);
        }

        if (empty($dealResult['result'])) {
            throw new Exception("Сделка с ID {$bxId} не найдена");
        }

        $deal = $dealResult['result'];

        $doctor = CRest::call(
            'crm.item.get',
            [
                'entityTypeId' => 1040,
                'id' => $deal['UF_CRM_1761998673'],
            ]
        )['result']['item'];

        $clinic = CRest::call(
            'crm.item.get',
            [
                'entityTypeId' => 1044,
                'id' => $deal['UF_CRM_1762175501'],
            ]
        )['result']['item'];

        $contact = CRest::call(
            'crm.contact.get',
            [
                'id' => $deal['CONTACT_ID'],
            ]
        )['result'];

        $appointmentServices = $this->getAppointmentServicesFromDealProducts(
            (int)$bxId,
            $doctor['ufCrm7Renovatioid'],
            $clinic['ufCrm9Renovatioid'],
            $serviceId
        );

        $appointmentData = [
            'first_name' => $contact['NAME'],
            'last_name' => $contact['LAST_NAME'],
            'third_name' => $contact['SECOND_NAME'],
            'birth_date' => $contact['BIRTHDATE'],
            'doctor_id' => $doctor['ufCrm7Renovatioid'],
            'time_start' => $dateManager->bitrixToRenovatio($deal['UF_CRM_1726973347808']),
            'time_end' => $dateManager->bitrixToRenovatio($deal['UF_CRM_1762178514']),
            'clinic_id' => $clinic['ufCrm9Renovatioid'],
            'services' => $appointmentServices,
        ];

        header('Content-Type: application/json');
        $url = $this->baseUrl . 'createAppointment?api_key=' . $this->apiKey;
        $result = ApiClient::makeCurlRequest($url, $appointmentData);

        CRest::call(
            'crm.deal.update',
            [
                'ID' => $bxId,
                'FIELDS' => [
                    'UF_CRM_1729241550' => $result['data'],
                    'STAGE_ID' => 'C1:EXECUTING',
                ],
            ]
        );

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
