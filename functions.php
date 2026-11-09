<?php
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid form token. Please go back and try again.');
    }
}

function client_ip(): string {
    // Do not trust X-Forwarded-For unless you operate a trusted reverse proxy.
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function approximate_location(string $ip): string {
    if (!ENABLE_IP_LOCATION || $ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'Unavailable';
    }
    // Private/local addresses cannot be geolocated reliably.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return 'Local/private network';
    }

    $url = 'https://ipwho.is/' . rawurlencode($ip);
    $context = stream_context_create(['http' => [
        'timeout' => 3,
        'header' => "User-Agent: PHP-Login-Monitor/1.0\r\n"
    ]]);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) return 'Unavailable';

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['success'])) return 'Unavailable';

    $parts = array_filter([
        $data['city'] ?? '',
        $data['region'] ?? '',
        $data['country'] ?? ''
    ]);
    return $parts ? implode(', ', $parts) : 'Unavailable';
}

function log_login_attempt(PDO $pdo, ?int $userId, string $username, bool $success): void {
    $ip = client_ip();
    $location = approximate_location($ip);
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (user_id, username_entered, ip_address, location_text, success, attempted_at)
         VALUES (:uid, :username, :ip, :location, :success, NOW())'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':username' => $username,
        ':ip' => $ip,
        ':location' => $location,
        ':success' => $success ? 1 : 0
    ]);
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied. Admin account required.');
    }
}

function flash(string $message, string $type = 'info'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function show_flash(): void {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert ' . e($f['type']) . '">' . e($f['message']) . '</div>';
        unset($_SESSION['flash']);
    }
}
