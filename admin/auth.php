<?php

function brewery_admin_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
}

function brewery_admin_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    ini_set('session.use_strict_mode', '1');
    session_name('brewery_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => brewery_admin_is_https(),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

function brewery_admin_password_hash(): string
{
    $hash = getenv('BREWERY_ADMIN_PASSWORD_HASH');
    if (($hash === false || trim($hash) === '') && isset($_SERVER['BREWERY_ADMIN_PASSWORD_HASH'])) {
        $hash = (string)$_SERVER['BREWERY_ADMIN_PASSWORD_HASH'];
    }

    return trim((string)$hash);
}

function brewery_admin_password_is_configured(): bool
{
    $hash = brewery_admin_password_hash();
    if ($hash === '') {
        return false;
    }

    $details = password_get_info($hash);
    return isset($details['algoName']) && $details['algoName'] !== 'unknown';
}

function brewery_admin_verify_password(string $password): bool
{
    return brewery_admin_password_is_configured()
        && password_verify($password, brewery_admin_password_hash());
}

function brewery_admin_is_authenticated(): bool
{
    if (empty($_SESSION['brewery_admin']) || !brewery_admin_password_is_configured()) {
        return false;
    }

    $sessionFingerprint = (string)($_SESSION['brewery_admin_password_fingerprint'] ?? '');
    $currentFingerprint = hash('sha256', brewery_admin_password_hash());

    return $sessionFingerprint !== '' && hash_equals($currentFingerprint, $sessionFingerprint);
}

function brewery_admin_require_login(): void
{
    brewery_admin_start_session();
    if (!brewery_admin_is_authenticated()) {
        header('Location: index.php');
        exit;
    }
}

function brewery_admin_log_out(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict'
        ]);
    }

    session_destroy();
}
