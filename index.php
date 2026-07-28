<?php

declare(strict_types=1);

require_once __DIR__ . '/php/config.php';

$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    exit('Не вдалося завантажити головну сторінку.');
}

$siteName = htmlspecialchars(APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = str_replace('<title>Чисто Так</title>', '<title>' . $siteName . '</title>', $html);

header('Content-Type: text/html; charset=utf-8');
echo $html;
