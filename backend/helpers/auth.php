<?php
const JWT_SECRET_FALLBACK = 'change-this-local-development-secret';

function jwt_secret(): string
{
    $secret = (string)(getenv('JWT_SECRET') ?: '');
    if ((getenv('APP_ENV') ?: 'development') === 'production' && strlen($secret) < 32) {
        throw new RuntimeException('JWT_SECRET must contain at least 32 characters in production.');
    }
    return $secret !== '' ? $secret : JWT_SECRET_FALLBACK;
}

function base64url_encode_data(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode_data(string $data): string|false
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function token_for(array $user): string
{
    $ttl = max(900, min(86400, (int)(getenv('JWT_TTL_SECONDS') ?: 28800)));
    $header = base64url_encode_data(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64url_encode_data(json_encode([
        'sub' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => time(),
        'exp' => time() + $ttl,
        'jti' => bin2hex(random_bytes(16)),
    ]));
    $secret = jwt_secret();
    $signature = base64url_encode_data(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
    return "{$header}.{$payload}.{$signature}";
}

function current_user(): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (!preg_match('/Bearer\s+(.+)/', $header, $matches)) {
        return null;
    }
    $parts = explode('.', $matches[1]);
    if (count($parts) !== 3) {
        return null;
    }
    [$head, $payload, $sig] = $parts;
    $headerData = json_decode(base64url_decode_data($head), true);
    if (!is_array($headerData) || ($headerData['typ'] ?? '') !== 'JWT' || ($headerData['alg'] ?? '') !== 'HS256') {
        return null;
    }
    $secret = jwt_secret();
    $expected = base64url_encode_data(hash_hmac('sha256', "{$head}.{$payload}", $secret, true));
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $data = json_decode(base64url_decode_data($payload), true);
    if (!$data || !isset($data['sub'], $data['exp'], $data['jti']) || !is_numeric($data['sub']) || !is_numeric($data['exp']) || !preg_match('/^[a-f0-9]{32}$/', (string)$data['jti']) || (int)$data['exp'] < time()) {
        return null;
    }
    $stmt = db()->prepare('SELECT jti FROM revoked_tokens WHERE jti = ? AND expires_at > NOW()');
    $stmt->execute([$data['jti']]);
    if ($stmt->fetch()) return null;
    return $data;
}

function revoke_token(array $token): void
{
    if (empty($token['jti']) || empty($token['exp'])) return;
    $expiresAt = date('Y-m-d H:i:s', (int)$token['exp']);
    $stmt = db()->prepare('INSERT IGNORE INTO revoked_tokens (jti, expires_at) VALUES (?, ?)');
    $stmt->execute([$token['jti'], $expiresAt]);
}

function login_attempt_key(string $email): string
{
    return hash('sha256', strtolower(trim($email)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function enforce_login_throttle(string $email): void
{
    $key = login_attempt_key($email);
    $stmt = db()->prepare('SELECT blocked_until FROM login_attempts WHERE attempt_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row && !empty($row['blocked_until']) && strtotime($row['blocked_until']) > time()) {
        header('Retry-After: ' . max(1, strtotime($row['blocked_until']) - time()));
        json_response(false, 'Too many login attempts. Try again later.', null, [], 429);
    }
}

function record_failed_login(string $email): void
{
    $key = login_attempt_key($email);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM login_attempts WHERE attempt_key = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $windowExpired = !$row || strtotime($row['window_started_at']) <= time() - 900;
        $failedCount = $windowExpired ? 1 : (int)$row['failed_count'] + 1;
        $windowStarted = $windowExpired ? date('Y-m-d H:i:s') : $row['window_started_at'];
        $blockedUntil = $failedCount >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        $stmt = $pdo->prepare('INSERT INTO login_attempts (attempt_key, failed_count, window_started_at, blocked_until) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE failed_count=VALUES(failed_count), window_started_at=VALUES(window_started_at), blocked_until=VALUES(blocked_until), updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([$key, $failedCount, $windowStarted, $blockedUntil]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function clear_login_attempts(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
    $stmt->execute([login_attempt_key($email)]);
    if (random_int(1, 100) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE updated_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        db()->exec('DELETE FROM revoked_tokens WHERE expires_at <= NOW()');
    }
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        json_response(false, 'Authentication required', null, [], 401);
    }
    $stmt = db()->prepare('SELECT id, name, email, role, status FROM users WHERE id = ? AND status = "Active"');
    $stmt->execute([(int)$user['sub']]);
    $fresh = $stmt->fetch();
    if (!$fresh) json_response(false, 'Session is no longer active', null, [], 401);
    $user['id'] = (int)$fresh['id'];
    $user['name'] = $fresh['name'];
    $user['email'] = $fresh['email'];
    $user['role'] = $fresh['role'];
    return $user;
}

function require_write(array $user): void
{
    if (($user['role'] ?? '') === 'Viewer') {
        json_response(false, 'Viewer role cannot modify data', null, [], 403);
    }
}

function require_admin(array $user): void
{
    if (($user['role'] ?? '') !== 'Admin') {
        json_response(false, 'Admin role required', null, [], 403);
    }
}
