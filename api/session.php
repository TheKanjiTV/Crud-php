<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    // SameSite support is reliable in PHP >= 7.3
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function check_auth($role = null) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Unauthorized']);
        exit();
    }

    if ($role) {
        $roleRank = [
            'guest' => 0,
            'user'  => 1,
            'admin' => 2,
        ];

        $currentRole = $_SESSION['role'] ?? 'guest';
        $currentRank = $roleRank[$currentRole] ?? -1;
        $requiredRank = $roleRank[$role] ?? PHP_INT_MAX;

        if ($currentRank < $requiredRank) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden']);
            exit();
        }
    }
}
