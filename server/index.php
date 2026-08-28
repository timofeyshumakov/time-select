<?php

class PrettyErrorHandler
{
    private static $instance = null;
    private $showTrace = true;
    private $logDir = 'logs';
    private $logFile = 'errors.log';
    private $logErrors = true;

    public static function register($logErrors = true, $logDir = 'logs')
    {
        if (self::$instance === null) {
            self::$instance = new self($logErrors, $logDir);
        }

        set_error_handler([self::$instance, 'handleError']);
        set_exception_handler([self::$instance, 'handleException']);
        register_shutdown_function([self::$instance, 'handleShutdown']);

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', $logErrors ? 1 : 0);
        
        if ($logErrors) {
            ini_set('error_log', self::$instance->getLogPath());
        }
    }

    private function __construct($logErrors = true, $logDir = 'logs')
    {
        $this->logErrors = $logErrors;
        $this->logDir = $logDir;
        $this->createLogDirectory();
    }

    private function createLogDirectory()
    {
        if ($this->logErrors && !is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    private function getLogPath()
    {
        return $this->logDir . '/' . $this->logFile;
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {
        $errorData = [
            'type' => $this->errorTypeToString($errno),
            'code' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];

        $this->logError($errorData);
        $this->render($errorData);
        return true;
    }

    public function handleException($exception)
    {
        $errorData = [
            'type' => get_class($exception),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];

        $this->logError($errorData);
        $this->render($errorData);
    }

    public function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $errorData = [
                'type' => $this->errorTypeToString($error['type']),
                'code' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ];

            $this->logError($errorData);
            $this->render($errorData);
        }
    }

    private function logError($errorData)
    {
        if (!$this->logErrors) {
            return;
        }

        // Логирование в JSON файл
        $logEntry = json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
        file_put_contents($this->getLogPath(), $logEntry, FILE_APPEND | LOCK_EX);

        // Логирование в отдельные файлы по типам
        $typeLogFile = $this->logDir . '/' . strtolower(str_replace('\\', '_', $errorData['type'])) . '.log';
        file_put_contents($typeLogFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Логирование в системный лог PHP
        error_log(sprintf(
            "[%s] %s: %s in %s:%d",
            $errorData['timestamp'],
            $errorData['type'],
            $errorData['message'],
            $errorData['file'],
            $errorData['line']
        ));
    }

    // Публичный метод для ручного логирования
    public static function log($message, $type = 'INFO', $context = [])
    {
        if (self::$instance === null) {
            self::register();
        }

        $logData = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];

        $logEntry = json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
        file_put_contents(self::$instance->logDir . '/app.log', $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function render($error)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Для AJAX/API запросов возвращаем JSON
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit(1);
        }

        // HTML вывод для обычных запросов
        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="UTF-8"><title>Error</title>';
        echo '<style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .error-container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
            .error-header { background: #f44336; color: white; padding: 20px; }
            .error-header h1 { font-size: 24px; margin-bottom: 10px; }
            .error-type { background: rgba(0,0,0,0.2); display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 14px; font-family: monospace; }
            .error-content { padding: 20px; }
            .error-message { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            .error-message strong { color: #c62828; }
            .error-location { background: #f5f5f5; padding: 10px; font-family: monospace; margin-bottom: 20px; border-radius: 4px; }
            .error-trace { background: #263238; color: #a6c1ff; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 12px; }
            .error-trace h3 { color: #ffa726; margin-bottom: 10px; font-size: 16px; }
            .trace-line { padding: 5px 0; border-bottom: 1px solid #37474f; }
            .trace-file { color: #81c784; }
            .trace-function { color: #64b5f6; }
            .error-code { background: #fafafa; padding: 15px; border-radius: 4px; margin-top: 20px; overflow-x: auto; }
            pre { margin: 0; font-family: monospace; font-size: 12px; }
            .log-info { background: #e3f2fd; padding: 10px; margin-top: 20px; border-radius: 4px; font-size: 12px; color: #1565c0; }
        </style>';
        echo '</head><body>';

        echo '<div class="error-container">';
        echo '<div class="error-header">';
        echo '<h1>Произошла ошибка</h1>';
        echo '<div class="error-type">' . htmlspecialchars($error['type']) . '</div>';
        echo '</div>';

        echo '<div class="error-content">';
        echo '<div class="error-message">';
        echo '<strong>Сообщение:</strong><br>';
        echo htmlspecialchars($error['message']);
        echo '</div>';

        echo '<div class="error-location">';
        echo '<strong>Файл:</strong> ' . htmlspecialchars($error['file']) . '<br>';
        echo '<strong>Строка:</strong> ' . $error['line'];
        echo '</div>';

        if ($this->logErrors) {
            echo '<div class="log-info">';
            echo '📝 Ошибка залогирована: ' . htmlspecialchars($this->getLogPath());
            echo '</div>';
        }

        if (file_exists($error['file'])) {
            $lines = file($error['file']);
            $start = max(0, $error['line'] - 5);
            $end = min(count($lines), $error['line'] + 5);

            echo '<div class="error-code">';
            echo '<strong>Код:</strong><br><pre>';
            for ($i = $start; $i < $end; $i++) {
                $lineNum = $i + 1;
                $isErrorLine = ($lineNum == $error['line']);
                echo ($isErrorLine ? '-> ' : '  ') . str_pad($lineNum, 4, ' ', STR_PAD_LEFT) . ' | ';
                echo htmlspecialchars(rtrim($lines[$i]));
                echo "\n";
            }
            echo '</pre></div>';
        }

        if ($this->showTrace && !empty($error['trace'])) {
            echo '<div class="error-trace">';
            echo '<h3>Stack trace:</h3>';
            foreach ($error['trace'] as $i => $trace) {
                echo '<div class="trace-line">';
                echo '#' . $i . ' ';
                if (isset($trace['file'])) {
                    echo '<span class="trace-file">' . htmlspecialchars(basename($trace['file'])) . ':' . $trace['line'] . '</span> -> ';
                }
                if (isset($trace['class'])) {
                    echo '<span class="trace-function">' . htmlspecialchars($trace['class'] . $trace['type'] . $trace['function']) . '</span>';
                } elseif (isset($trace['function'])) {
                    echo '<span class="trace-function">' . htmlspecialchars($trace['function']) . '</span>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '</div></div>';
        echo '</body></html>';

        exit(1);
    }

    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ||
               !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }

    private function errorTypeToString($type)
    {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $types[$type] ?? 'UNKNOWN';
    }
}

// Класс для ротации логов
class LogRotator
{
    private $logDir;
    private $maxSize; // в байтах
    private $maxFiles;

    public function __construct($logDir = 'logs', $maxSize = 10485760, $maxFiles = 5) // 10MB по умолчанию
    {
        $this->logDir = $logDir;
        $this->maxSize = $maxSize;
        $this->maxFiles = $maxFiles;
    }

    public function rotate()
    {
        if (!is_dir($this->logDir)) {
            return;
        }

        $files = glob($this->logDir . '/*.log');
        foreach ($files as $file) {
            if (filesize($file) > $this->maxSize) {
                $this->rotateFile($file);
            }
        }
    }

    private function rotateFile($file)
    {
        $info = pathinfo($file);
        $basename = $info['filename'];
        
        // Удаляем старые файлы
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->logDir . '/' . $basename . '.' . $i . '.log';
            if (file_exists($oldFile)) {
                if ($i == $this->maxFiles - 1) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $this->logDir . '/' . $basename . '.' . ($i + 1) . '.log');
                }
            }
        }
        
        // Переименовываем текущий файл
        rename($file, $this->logDir . '/' . $basename . '.1.log');
    }
}

// Регистрация обработчика с логированием
PrettyErrorHandler::register(true, 'logs');
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
require_once('bitrix24/Bitrix24Handler.php');
$logMessage = json_encode($_REQUEST, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
file_put_contents('log.json', $logMessage, FILE_APPEND);

class RequestRouter {

    private $allowedDomains = [
        'https://azbykamed.bitrix24.ru',
        'https://renovoapp.webtm.ru'
    ];

    private $bitrixHandler;
    private $renovatioHandler;
    
    public function __construct() {
        $this->bitrixHandler = new Bitrix24Handler();
        $this->renovatioHandler = new RenovatioHandler();
    }
    
    public function handleRequest() {
        $request = $_REQUEST;
        $bxId = $request["bx_id"] ?? 0;
        $doctorId = $request["doctor_id"] ?? 0;
        $clinicId = $request["clinic_id"] ?? 0;
        $action = $request["action"] ?? '';
        $event = $request["event"] ?? '';
        $serviceId = $request["service_id"] ?? 0;

        $data = null;
        if (isset($request['data']) && is_array($request['data']) && isset($request['data'][0])) {
            $data = $request['data'][0];
        } elseif (isset($request['data']) && is_string($request['data'])) {
            // Если data пришла как JSON строка
            $decoded = json_decode($request['data'], true);
            if (is_array($decoded) && isset($decoded[0])) {
                $data = $decoded[0];
            }
        }
        
        $timeStart = $request["time_start"] ?? '';
        $timeEnd = $request["time_end"] ?? '';
        $services = '';
        if (is_array($data) && isset($data["services"]) && is_array($data["services"]) && isset($data["services"][0]["title"])) {
            $services = $data["services"][0]["title"];
        }
        // Маршрутизация
        if ($action === 'torenova' && $bxId > 0 && $serviceId > 0) {
            return $this->renovatioHandler->createAppointment($bxId, $serviceId);
        } elseif($action === 'get_calendar' && $doctorId > 0) {
            header('Content-Type: application/json');
            echo $this->renovatioHandler->getCalendar($doctorId, $clinicId, $timeStart, $timeEnd);
        } elseif($event === 'update_appointment') {
            return $this->bitrixHandler->updateDeal($data["id"], $data["status"], $data["status_id"], $services, $data["patient_id"], $data["sum_value"]);
        } elseif ($action === 'get_services' && $doctorId > 0 && $clinicId > 0) {
            header('Content-Type: application/json');
            echo $this->renovatioHandler->getServices($doctorId, $clinicId);
        }
    }

    public function isAllowedDomain() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Если нет заголовков, разрешаем прямой доступ
        if (empty($referer) && empty($origin)) {
            return true;
        }


        // Проверяем Referer
        if (!empty($referer)) {
            foreach ($this->allowedDomains as $domain) {
                if (strpos($referer, $domain) === 0) {
                    return true;
                }
            }
        }
        
        // Проверяем Origin
        if (!empty($origin)) {
            foreach ($this->allowedDomains as $domain) {
                if (strpos($origin, $domain) === 0) {
                    return true;
                }
            }
        }
        
        return true; //false
    }

    private function getInputData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?: [];
        }
        
        return $_POST;
    }
    
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    private function logRequest($method, $path, $data) {
        $log = date('Y-m-d H:i:s') . " - $method $path - " . json_encode($data) . PHP_EOL;
        file_put_contents('logs/requests.log', $log, FILE_APPEND | LOCK_EX);
    }
}

// Запуск роутера
$router = new RequestRouter();
//if($router->isAllowedDomain()){
    $router->handleRequest();
//}
?>