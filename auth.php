<?php
// auth.php
require_once __DIR__ . '/auth_config.php';

function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login(string $redirect = 'acesso-feedback.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function attempt_login(string $user, string $pass): bool {
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        return true;
    }
    return false;
}

function do_logout(string $redirect = 'acesso-feedback.php'): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: ' . $redirect);
    exit;
}
