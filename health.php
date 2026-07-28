<?php

declare(strict_types=1);

require_once __DIR__ . '/php/functions.php';

$diagnostics = applicationDiagnostics();
http_response_code($diagnostics['ready'] ? 200 : 503);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function healthRow(string $label, bool $status, string $detail = ''): string
{
    $mark = $status ? '✅' : '❌';
    $text = $detail === '' ? '' : ' — ' . htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<li>' . $mark . ' ' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $text . '</li>';
}
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Перевірка сайту</title>
</head>
<body>
    <h1>Стан сайту</h1>
    <ul>
        <?= healthRow('PHP 8.0+', $diagnostics['php_supported'], $diagnostics['php_version']) ?>
        <?= healthRow('PHP cURL', $diagnostics['curl']) ?>
        <?= healthRow('OpenSSL', $diagnostics['openssl']) ?>
        <?= healthRow('Локальний curl.exe fallback', $diagnostics['local_curl_fallback']) ?>
        <?= healthRow('Запис у logs/', $diagnostics['logs_writable']) ?>
        <?= healthRow('Запис у data/', $diagnostics['data_writable']) ?>
        <?= healthRow('Файл .env', $diagnostics['env_file_present']) ?>
        <?= healthRow('Токен Telegram налаштовано', $diagnostics['telegram_token_configured']) ?>
        <?= healthRow('Chat ID Telegram налаштовано', $diagnostics['telegram_chat_id_configured']) ?>
        <?= healthRow('Часовий пояс', true, $diagnostics['timezone']) ?>
    </ul>
    <?php if ($diagnostics['issues'] !== []): ?>
        <p><?= htmlspecialchars(implode(' ', $diagnostics['issues']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php endif; ?>
</body>
</html>
