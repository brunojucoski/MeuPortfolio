<?php

$port = filter_var(getenv('PORT') ?: 8080, FILTER_VALIDATE_INT);
$host = parse_url(getenv('APP_URL') ?: 'http://localhost', PHP_URL_HOST);
$context = stream_context_create(['http' => [
    'timeout' => 3, 'header' => 'Host: '.$host."\r\nConnection: close\r\n",
]]);
$body = @file_get_contents('http://127.0.0.1:'.$port.'/health', false, $context);
exit($body === '{"status":"ok"}' ? 0 : 1);
