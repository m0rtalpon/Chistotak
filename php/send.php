<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Невірний метод запиту. Відправте форму методом POST.', 405);
}

ensureApplicationReady();

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16 * 1024) {
    jsonResponse(false, 'Заявка завелика. Скоротіть коментар.', 413);
}

function requestData(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $rawBody = file_get_contents('php://input') ?: '';
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    parse_str($rawBody, $parsed);
    return is_array($parsed) ? $parsed : [];
}

$services = [
    'kvartiry' => 'Прибирання квартир',
    'budynky' => 'Прибирання будинків',
    'ofisi' => 'Прибирання офісів',
    'vikna' => 'Миття вікон',
    'khimchistka' => 'Хімчистка',
];

$data = requestData();
if (cleanField($data['website'] ?? '', 200) !== '') {
    jsonResponse(true, 'Заявку успішно надіслано.');
}

$name = cleanField($data['name'] ?? '', 80);
$phone = cleanField($data['phone'] ?? '', 40);
$serviceKey = cleanField($data['service'] ?? '', 60);
$comment = cleanField($data['comment'] ?? '', 700);

if ($name === '' || $phone === '' || $serviceKey === '') {
    jsonResponse(false, "Заповніть ім'я, телефон та послугу.", 422);
}

$digitsOnly = preg_replace('/\D+/', '', $phone) ?? '';
if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
    jsonResponse(false, 'Перевірте номер телефону. Він має містити від 7 до 15 цифр.', 422);
}

if (!isset($services[$serviceKey])) {
    jsonResponse(false, 'Виберіть коректну послугу.', 422);
}

$messageLines = [
    '🧹 Нова заявка — ' . APP_NAME,
    '',
    "👤 Ім'я:",
    $name,
    '',
    '📞 Телефон:',
    $phone,
    '',
    '🏠 Послуга:',
    $services[$serviceKey],
];

if ($comment !== '') {
    $messageLines[] = '';
    $messageLines[] = '📝 Коментар:';
    $messageLines[] = $comment;
}

$messageLines[] = '';
$messageLines[] = '🕒 Дата:';
$messageLines[] = date('d.m.Y H:i');

$result = sendTelegramMessage(implode("\n", $messageLines));
if (!$result['ok']) {
    writeLog(['event' => 'order_not_sent', 'reason' => $result['error'], 'http_code' => $result['http_code']]);
    jsonResponse(false, 'Заявку тимчасово не вдалося надіслати. Спробуйте ще раз пізніше.', 502);
}

writeLog(['event' => 'order_sent', 'service' => $services[$serviceKey]]);
jsonResponse(true, "Заявку успішно надіслано. Ми зв'яжемося з вами найближчим часом.");
