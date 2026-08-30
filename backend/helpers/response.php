<?php
function json_response(bool $success, string $message, mixed $data = null, array $errors = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $maximumBytes = 1024 * 1024;
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maximumBytes) {
        json_response(false, 'Request body is too large', null, ['body' => 'Maximum request size is 1 MB.'], 413);
    }
    $raw = file_get_contents('php://input');
    if (strlen($raw) > $maximumBytes) {
        json_response(false, 'Request body is too large', null, ['body' => 'Maximum request size is 1 MB.'], 413);
    }
    $data = json_decode($raw ?: '{}', true);
    if ($raw !== '' && json_last_error() !== JSON_ERROR_NONE) {
        json_response(false, 'Invalid JSON body', null, ['body' => 'Send a valid JSON object.'], 400);
    }
    return is_array($data) ? $data : [];
}
