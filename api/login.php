<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$raw = (string)file_get_contents('php://input');
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

// 1) Try JSON
$data = json_decode($raw, true);

// 2) Some clients accidentally wrap JSON in single quotes: '{"a":1}'
if (!is_array($data)) {
    $trimmed = trim($raw);
    if ($trimmed !== '' && $trimmed[0] === "'" && substr($trimmed, -1) === "'") {
        $unquoted = substr($trimmed, 1, -1);
        $data = json_decode($unquoted, true);
    }
}

// 3) Some clients double-encode JSON as a JSON string
if (!is_array($data)) {
    $maybeJsonString = json_decode($raw, false);
    if (is_string($maybeJsonString)) {
        $data = json_decode($maybeJsonString, true);
    }
}

// 4) Fallback to normal form posts
if (!is_array($data) || $data === []) {
    if (!empty($_POST)) {
        $data = $_POST;
    } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false || stripos($contentType, 'multipart/form-data') !== false || str_contains($raw, '=')) {
        $formData = [];
        parse_str($raw, $formData);
        if (!empty($formData)) {
            $data = $formData;
        }
    }
}

if (!is_array($data) || $data === []) {
    http_response_code(400);
    echo json_encode([
        'message' => 'Invalid request payload.',
        'debug' => [
            'contentType' => $contentType,
            'rawLength' => strlen($raw),
            'jsonError' => json_last_error_msg(),
        ],
    ]);
    exit;
}

$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['message' => 'Username and password are required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    echo json_encode(['message' => 'Login successful']);
} else {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid credentials']);
}
