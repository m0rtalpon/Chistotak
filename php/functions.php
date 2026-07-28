<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function jsonResponse(bool $status, string $message, int $httpCode = 200, array $extra = []): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        array_merge(['status' => $status, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function ensureRuntimeDirectory(string $path): ?string
{
    if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
        return 'Не вдалося створити ' . basename($path) . '/.';
    }

    if (!is_writable($path)) {
        return 'Немає прав на запис у ' . basename($path) . '/.';
    }

    $protectionFile = $path . '/.htaccess';
    if (!is_file($protectionFile) && @file_put_contents($protectionFile, "Require all denied\n") === false) {
        return 'Не вдалося захистити ' . basename($path) . '/ від доступу з браузера.';
    }

    return null;
}

function initializeApplication(): void
{
    error_reporting(E_ALL);
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    @ini_set('log_errors', '1');

    if (ensureRuntimeDirectory(LOG_DIR) === null) {
        @ini_set('error_log', LOG_FILE);
    }

    ensureRuntimeDirectory(DATA_DIR);
}

function applicationIssues(): array
{
    $issues = [];
    $hasPhpCurl = function_exists('curl_init');
    $hasOpenSsl = extension_loaded('openssl');
    $hasLocalCurlFallback = isLocalCliCurlAvailable();

    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        $issues[] = 'Потрібен PHP 8.0 або новіший.';
    }

    if (!$hasPhpCurl && !$hasLocalCurlFallback) {
        $issues[] = 'На сервері не ввімкнено розширення PHP cURL.';
    }

    if (!$hasOpenSsl && !$hasLocalCurlFallback) {
        $issues[] = 'На сервері не ввімкнено розширення OpenSSL.';
    }

    foreach ([LOG_DIR, DATA_DIR] as $directory) {
        if ($issue = ensureRuntimeDirectory($directory)) {
            $issues[] = $issue;
        }
    }

    return array_values(array_unique($issues));
}

function applicationDiagnostics(): array
{
    $issues = applicationIssues();

    return [
        'ready' => $issues === [],
        'issues' => $issues,
        'php_version' => PHP_VERSION,
        'php_supported' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'curl' => function_exists('curl_init'),
        'openssl' => extension_loaded('openssl'),
        'local_curl_fallback' => isLocalCliCurlAvailable(),
        'logs_writable' => is_dir(LOG_DIR) && is_writable(LOG_DIR),
        'data_writable' => is_dir(DATA_DIR) && is_writable(DATA_DIR),
        'env_file_present' => is_file(PROJECT_ROOT . '/.env'),
        'telegram_token_configured' => isTelegramTokenConfigured(),
        'telegram_chat_id_configured' => TELEGRAM_ADMIN_CHAT_ID !== '',
        'timezone' => APP_TIMEZONE,
    ];
}

function ensureApplicationReady(): void
{
    $issues = applicationIssues();

    if ($issues !== []) {
        writeLog(['event' => 'environment_error', 'issues' => $issues]);
        jsonResponse(false, 'Сервер не готовий до відправлення заявок. ' . implode(' ', $issues), 503);
    }
}

function writeLog(string|array $entry): void
{
    if (!is_dir(LOG_DIR) || !is_writable(LOG_DIR)) {
        return;
    }

    $line = is_array($entry)
        ? (json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Unable to encode log entry')
        : $entry;

    @file_put_contents(LOG_FILE, '[' . date('c') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function cleanField(mixed $value, int $maxLength = 500): string
{
    $text = trim((string) $value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? '';
    $text = trim($text);

    return function_exists('mb_substr')
        ? mb_substr($text, 0, $maxLength, 'UTF-8')
        : substr($text, 0, $maxLength);
}

function isTelegramTokenConfigured(): bool
{
    return TELEGRAM_BOT_TOKEN !== ''
        && TELEGRAM_BOT_TOKEN !== 'PASTE_TELEGRAM_BOT_TOKEN_HERE';
}

function applicationBasePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(dirname(dirname($scriptName)), '/.');

    return $basePath === '' ? '/' : $basePath . '/';
}

function currentSiteUrl(): ?string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(?:localhost|(?:[a-z0-9-]+\.)*[a-z0-9-]+|\[[0-9a-f:]+\])(?::\d{1,5})?$/i', $host)) {
        return null;
    }

    $https = (string) ($_SERVER['HTTPS'] ?? '');
    $scheme = ($https !== '' && strtolower($https) !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        ? 'https'
        : 'http';

    return $scheme . '://' . $host . applicationBasePath();
}

function localCurlCommand(): ?string
{
    if (PHP_SAPI !== 'cli-server' || !function_exists('proc_open')) {
        return null;
    }

    return PHP_OS_FAMILY === 'Windows' ? 'curl.exe' : 'curl';
}

function isLocalCliCurlAvailable(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $command = localCurlCommand();
    if ($command === null) {
        return $available = false;
    }

    $process = @proc_open([$command, '--version'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        return $available = false;
    }

    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return $available = proc_close($process) === 0;
}

function runHttpPostWithLocalCurl(string $url, string $payload): ?array
{
    $command = localCurlCommand();
    if ($command === null) {
        return null;
    }

    $process = @proc_open([
        $command,
        '--silent',
        '--show-error',
        '--max-time',
        '20',
        '--connect-timeout',
        '10',
        '--request',
        'POST',
        '--header',
        'Content-Type: application/json',
        '--data-binary',
        '@-',
        '--write-out',
        "\nHTTP_STATUS:%{http_code}",
        $url,
    ], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        return null;
    }

    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = trim((string) stream_get_contents($pipes[2]));
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $body = (string) $output;
    $httpCode = 0;
    if (preg_match('/\nHTTP_STATUS:(\d{3})\s*$/', $body, $matches)) {
        $httpCode = (int) $matches[1];
        $body = preg_replace('/\nHTTP_STATUS:\d{3}\s*$/', '', $body) ?? $body;
    }

    return [
        'body' => $body,
        'http_code' => $httpCode,
        'error' => $exitCode === 0 ? null : ($error !== '' ? $error : 'Local curl exited with code ' . $exitCode),
    ];
}

function telegramApiRequest(string $method, array $data = []): array
{
    if (!isTelegramTokenConfigured()) {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'Telegram bot token is not configured.'];
    }

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'Could not encode Telegram request.'];
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/' . $method;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => APP_NAME . ' form sender',
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    } else {
        $localResult = runHttpPostWithLocalCurl($url, $payload);
        if ($localResult === null) {
            return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'PHP cURL is not available.'];
        }

        $body = $localResult['body'];
        $error = (string) ($localResult['error'] ?? '');
        $httpCode = $localResult['http_code'];
    }

    $decoded = is_string($body) ? json_decode($body, true) : null;
    $isOk = $body !== false
        && $httpCode >= 200
        && $httpCode < 300
        && is_array($decoded)
        && ($decoded['ok'] ?? false) === true;

    $telegramError = $error !== '' ? $error : (is_array($decoded) ? ($decoded['description'] ?? null) : 'Invalid Telegram response.');
    writeLog([
        'event' => 'telegram_api',
        'method' => $method,
        'http_code' => $httpCode,
        'ok' => $isOk,
        'error' => $isOk ? null : $telegramError,
    ]);

    return ['ok' => $isOk, 'http_code' => $httpCode, 'response' => $decoded, 'error' => $isOk ? null : $telegramError];
}

function sendTelegramMessage(string $text): array
{
    if (TELEGRAM_ADMIN_CHAT_ID === '') {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'Telegram admin chat id is not configured.'];
    }

    return telegramApiRequest('sendMessage', [
        'chat_id' => TELEGRAM_ADMIN_CHAT_ID,
        'text' => $text,
        'disable_web_page_preview' => true,
    ]);
}

initializeApplication();
