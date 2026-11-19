<?php
header('Content-Type: text/plain; charset=utf-8');
$body = file_get_contents('php://input');
file_put_contents(__DIR__ . '/_debug_result.txt', date('c') . "\n" . $body);
echo 'ok';
