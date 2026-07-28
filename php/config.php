<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
define('DATA_DIR', PROJECT_ROOT . '/data');
define('LOG_DIR', PROJECT_ROOT . '/logs');
define('LOG_FILE', LOG_DIR . '/php-error.log');

/** Load a small, dependency-free .env file without overwriting server variables. */
function loadEnvFile(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function envValue(string $key, string $default = ''): string
{
    $value = getenv($key);

    return $value === false ? $default : trim((string) $value);
}

loadEnvFile(PROJECT_ROOT . '/.env');

$appName = preg_replace('/[\x00-\x1F\x7F]/u', '', envValue('APP_NAME', 'Чисто Так')) ?? 'Чисто Так';
if (trim($appName) === '') {
    $appName = 'Чисто Так';
}
define('APP_NAME', function_exists('mb_substr') ? mb_substr(trim($appName), 0, 120, 'UTF-8') : substr(trim($appName), 0, 120));
define('TELEGRAM_BOT_TOKEN', envValue('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_ADMIN_CHAT_ID', envValue('TELEGRAM_ADMIN_CHAT_ID'));

$timezone = envValue('APP_TIMEZONE', 'Europe/Kyiv');
if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    $timezone = 'Europe/Kyiv';
}

define('APP_TIMEZONE', $timezone);
date_default_timezone_set(APP_TIMEZONE);
