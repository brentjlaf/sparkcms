<?php
function respond_json(array $data, int $status = 200): void
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}
