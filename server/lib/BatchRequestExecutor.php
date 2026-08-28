<?php

declare(strict_types=1);

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
        if (count($requests) === 1) {
            return $this->executeSingleRequest(reset($requests));
        }

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

    private function executeSingleRequest(array $request): array
    {
        $result = CRest::call($request['method'], $request['params']);
        return [$this->createSingleResult($result)];
    }

    private function createSingleResult(array $result): array
    {
        if (isset($result['error'])) {
            return [
                'success' => false,
                'error' => $result['error_description'] ?? 'Unknown error',
            ];
        }

        return [
            'success' => true,
            'result' => $result['result'] ?? null,
        ];
    }

    private function prepareBatchCommands(array $batchRequests): array
    {
        $batchCommands = [];
        foreach ($batchRequests as $key => $request) {
            $batchCommands[$key] = [
                'method' => $request['method'],
                'params' => $request['params'],
            ];
        }
        return $batchCommands;
    }

    private function executeBatch(array $batchCommands): array
    {
        $batchResult = CRest::callBatch($batchCommands);

        if (isset($batchResult['error'])) {
            throw new BatchExecutionException(
                'Batch error: ' . ($batchResult['error_description'] ?? 'Unknown batch error')
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
                'error' => $result['error_description'] ?? 'Unknown error',
            ];
        }

        return [
            'success' => true,
            'result' => $result['result'] ?? null,
        ];
    }

    private function applyDelay(int $currentBatchIndex, int $totalBatches): void
    {
        if ($totalBatches > 1 && $currentBatchIndex < $totalBatches - 1) {
            usleep($this->delayMicroseconds);
        }
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function getDelayMicroseconds(): int
    {
        return $this->delayMicroseconds;
    }
}
