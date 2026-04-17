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

function normalize_role($role): string {
    $normalized = strtolower(trim((string)$role));
    if ($normalized === 'admin') {
        return 'Admin';
    }
    if ($normalized === 'user') {
        return 'User';
    }
    return 'Guest';
}

function check_auth($role = null) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Unauthorized']);
        exit();
    }

    $_SESSION['role'] = normalize_role($_SESSION['role'] ?? 'Guest');

    if ($role) {
        $roleRank = [
            'Guest' => 0,
            'User'  => 1,
            'Admin' => 2,
        ];

        $currentRole = $_SESSION['role'] ?? 'Guest';
        $requiredRole = normalize_role($role);
        $currentRank = $roleRank[$currentRole] ?? -1;
        $requiredRank = $roleRank[$requiredRole] ?? PHP_INT_MAX;

        if ($currentRank < $requiredRank) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden']);
            exit();
        }
    }
}
