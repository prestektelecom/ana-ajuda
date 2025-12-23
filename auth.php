<?php
// auth.php
// Autenticação do painel via SQLite (data/feedback.db) usando password_hash/password_verify.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

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
    $user = trim($user);

    if ($user === '' || $pass === '') {
        return false;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT username, password_hash, is_active FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }
    if ((int)$row['is_active'] !== 1) {
        return false;
    }
    if (!password_verify($pass, (string)$row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['admin_user'] = (string)$row['username'];
    return true;
}

function do_logout(string $redirect = 'acesso-feedback.php'): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . $redirect);
    exit;
}
