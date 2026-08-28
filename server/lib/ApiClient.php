<?php

declare(strict_types=1);

class ApiClient
{
    public static function makeCurlRequest($url, $postData = [], $options = []): array
    {
        $ch = curl_init();

        $defaultOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ];

        if (!empty($postData)) {
            $postDataString = http_build_query($postData);
            $defaultOptions[CURLOPT_POST] = true;
            $defaultOptions[CURLOPT_POSTFIELDS] = $postDataString;
            $defaultOptions[CURLOPT_HTTPHEADER] = [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postDataString),
            ];
        }

        curl_setopt_array($ch, $options + $defaultOptions);

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
