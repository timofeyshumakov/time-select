<?php

declare(strict_types=1);

/**
 * Нормализация услуг из вебхука Renovatio для товарных строк Bitrix24.
 */
class ServiceNormalizer
{
    /**
     * @param array|string|null $services
     * @param float|string|null $amount
     * @return array<int, array{name:string,price:float,qty:float,xml_id:?string}>
     */
    public function normalize($services, $amount): array
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

    /**
     * @param array<int, array{name:string,price:float,qty:float}> $normalized
     */
    public function formatForDealField(array $normalized): string
    {
        if ($normalized === []) {
            return '';
        }

        $parts = [];
        foreach ($normalized as $line) {
            $parts[] = $line['name'] . ': ' . ($line['price'] * $line['qty']);
        }

        return implode("\n", $parts);
    }

    public function total(array $normalized): float
    {
        $total = 0.0;
        foreach ($normalized as $line) {
            $total += $line['price'] * $line['qty'];
        }
        return $total;
    }

    private function isAssocServiceRow(array $arr): bool
    {
        return isset($arr['title']) || isset($arr['name']) || !array_is_list($arr);
    }
}
