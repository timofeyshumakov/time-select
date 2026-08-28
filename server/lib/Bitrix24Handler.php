<?php

declare(strict_types=1);

class Bitrix24Handler
{
    private BatchRequestExecutor $batchExecutor;
    private ServiceNormalizer $serviceNormalizer;
    private DealStageMapper $stageMapper;
    private DateManager $dateManager;
    private PatientStatsPage $statsPage;

    public function __construct()
    {
        $this->batchExecutor = new BatchRequestExecutor();
        $this->serviceNormalizer = new ServiceNormalizer();
        $this->stageMapper = new DealStageMapper();
        $this->dateManager = new DateManager();
        $this->statsPage = new PatientStatsPage();
    }

    private function findContactByRenovatioPatientId(int $patientId): ?array
    {
        try {
            $result = CRest::call('crm.contact.list', [
                'filter' => [
                    'UF_CRM_1729239754' => $patientId,
                ],
                'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'UF_CRM_1776436700', 'UF_CRM_1776436729'],
            ]);

            if (!empty($result['result'])) {
                return $result['result'][0];
            }
        } catch (Exception $e) {
            error_log('Error finding contact: ' . $e->getMessage());
        }

        return null;
    }

    public function updateDeal($renovoId, $status, $statusId, $services, $patientId, $amount = null): void
    {
        $stage = $this->stageMapper->mapFromRenovatioStatus((string)$status, $statusId !== null ? (string)$statusId : null);
        if ($stage === '') {
            return;
        }

        $dealList = CRest::call('crm.deal.list', [
            'SELECT' => ['ID', 'CONTACT_ID'],
            'FILTER' => ['UF_CRM_1729241550' => $renovoId],
        ]);

        if (!empty($dealList['error'])) {
            $this->appendLog([
                'action' => 'updateDeal_deal_list_error',
                'renovo_id' => $renovoId,
                'stage' => $stage,
                'error' => $dealList['error'],
            ]);
            return;
        }

        if (empty($dealList['result']) || !isset($dealList['result'][0])) {
            $this->appendLog([
                'action' => 'updateDeal_deal_not_found',
                'renovo_id' => $renovoId,
                'stage' => $stage,
                'hint' => 'Нет сделки с UF_CRM_1729241550 = renovo_id',
            ]);
            return;
        }

        $deal = $dealList['result'][0];
        if (empty($deal['ID'])) {
            $this->appendLog(['action' => 'updateDeal_invalid_deal', 'deal' => $deal]);
            return;
        }

        $updateFields = ['STAGE_ID' => $stage];
        $normalizedServices = [];

        if ($stage === 'C1:WON') {
            $normalizedServices = $this->serviceNormalizer->normalize($services, $amount);
            $opportunity = $this->serviceNormalizer->total($normalizedServices);
            if ($opportunity <= 0.0 && $amount !== null && $amount !== '') {
                $opportunity = (float)$amount;
            }
            $updateFields['OPPORTUNITY'] = $opportunity;
            $updateFields['UF_CRM_1771593682'] = $this->serviceNormalizer->formatForDealField($normalizedServices);
            $updateFields['UF_CRM_1770898429704'] = 'https://app.rnova.org/patients/default/detail/id/' . $patientId;

            try {
                if (empty($deal['CONTACT_ID'])) {
                    $this->appendLog([
                        'action' => 'contact_visit_stats_skipped',
                        'reason' => 'deal_has_no_contact_id',
                        'deal_id' => $deal['ID'] ?? null,
                    ]);
                } else {
                    $renovatioHandler = new RenovatioHandler();
                    $fullStats = $renovatioHandler->getPatientFullStats((int)$patientId, true);
                    $statistics = $fullStats['statistics'] ?? [];
                    $totalPaid = (float)($statistics['total_paid'] ?? 0);
                    $completedVisits = (int)($statistics['completed'] ?? 0);
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

                    $this->appendLog([
                        'action' => 'contact_visit_stats_updated',
                        'contact_id' => $deal['CONTACT_ID'],
                        'stats' => [
                            'total_paid' => $totalPaid,
                            'visit_count' => $completedVisits,
                        ],
                        'result' => $contactUpdate,
                    ]);
                }
            } catch (Throwable $e) {
                $this->appendLog([
                    'action' => 'contact_visit_stats_error',
                    'contact_id' => $deal['CONTACT_ID'] ?? null,
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                ]);
            }
        }

        $result = CRest::call('crm.deal.update', [
            'ID' => $deal['ID'],
            'FIELDS' => $updateFields,
        ]);

        if ($stage === 'C1:WON' && $normalizedServices !== [] && empty($result['error'])) {
            $rowsResult = $this->syncDealProductRows((int)$deal['ID'], $normalizedServices);
            $this->appendLog([
                'action' => 'deal_product_rows_synced',
                'deal_id' => (int)$deal['ID'],
                'result' => $rowsResult,
            ]);
        }
    }

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

    public function createCrmItems(array $items, int $entityTypeId): array
    {
        $requests = $this->prepareCrmItemRequests($items, $entityTypeId);
        return $this->batchExecutor->execute($requests);
    }

    public function createClinic($data)
    {
        $result = $this->createCrmItems([$data], 1044);
        return reset($result);
    }

    public function createClinics(array $clinicsData): array
    {
        return $this->createCrmItems($clinicsData, 1044);
    }

    public function createDoctors(array $doctorsData): array
    {
        return $this->createCrmItems($doctorsData, 1040);
    }

    private function prepareCrmItemRequests(array $items, int $entityTypeId): array
    {
        $requests = [];
        foreach ($items as $index => $fields) {
            $requestFields = match ($entityTypeId) {
                1044 => $this->prepareClinicFields($fields),
                1040 => $this->prepareDoctorFields($fields),
                default => $fields,
            };

            $requests["crm_item_add_{$index}"] = [
                'method' => 'crm.item.add',
                'params' => [
                    'entityTypeId' => $entityTypeId,
                    'fields' => $requestFields,
                ],
            ];
        }

        return $requests;
    }

    private function prepareClinicFields(array $data): array
    {
        $fields = [
            'title' => $data['title'] ?? '',
            'ufCrm9_Renovatioid' => $data['id'] ?? '',
        ];

        return array_filter($fields, static fn($value) => $value !== null && $value !== '');
    }

    private function prepareDoctorFields(array $data): array
    {
        $fields = [
            'title' => $data['name'] ?? '',
            'ufCrm7Renovatioid' => $data['id'] ?? '',
            'ufCrm7Profession' => $this->prepareProfessionField($data['profession_titles'] ?? ''),
            'ufCrm7Clinics' => $this->prepareClinicsField($data['clinic'] ?? []),
        ];

        if (isset($data['birth_date'])) {
            $fields['ufCrm7Birthdate'] = $this->dateManager->formatDateForBitrix($data['birth_date']);
        }
        if (isset($data['phone'])) {
            $fields['ufCrm7Phone'] = $data['phone'];
        }
        if (isset($data['email'])) {
            $fields['ufCrm7Email'] = $data['email'];
        }
        if (isset($data['gender'])) {
            $fields['ufCrm7Gender'] = $data['gender'] == 1 ? 'Мужской' : 'Женский';
        }

        return array_filter($fields, static fn($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function prepareProfessionField($professionTitles): string
    {
        if (is_array($professionTitles)) {
            return implode(', ', array_filter($professionTitles));
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
            $result = CRest::call('crm.item.list', [
                'entityTypeId' => 1044,
                'filter' => ['ufCrm9Renovatioid' => $renovatioId],
                'select' => ['id'],
            ]);

            if (isset($result['result']['items'][0]['id'])) {
                return $result['result']['items'][0]['id'];
            }
        } catch (Exception $e) {
            error_log('Error finding clinic: ' . $e->getMessage());
        }

        return null;
    }

    public function formatDateForBitrix(string $date): string
    {
        return $this->dateManager->formatDateForBitrix($date);
    }

    public function showPatientStatsPage(): void
    {
        $this->statsPage->show(function (int $patientId, bool $forceRefresh): array {
            $renovatio = new RenovatioHandler();
            if ($forceRefresh) {
                $renovatio->cache->clear($patientId);
            }

            return [
                'stats' => $renovatio->getPatientFullStats($patientId),
                'patientInfo' => $renovatio->getPatientInfo($patientId),
                'bitrixContact' => $this->findContactByRenovatioPatientId($patientId),
            ];
        });
    }

    private function appendLog(array $payload): void
    {
        $logMessage = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        file_put_contents('log.json', $logMessage, FILE_APPEND);
    }
}
