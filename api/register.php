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
$passwordRaw = (string)($data['password'] ?? '');
$role = 'User';

if ($username === '') {
	http_response_code(422);
	echo json_encode(['message' => 'Username is required.']);
	exit;
}

if (strlen($passwordRaw) < 6) {
	http_response_code(422);
	echo json_encode(['message' => 'Password must be at least 6 characters.']);
	exit;
}

try {
	$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
	$stmt->execute(['username' => $username]);
	if ($stmt->fetch()) {
		http_response_code(409);
		echo json_encode(['message' => 'Username already exists.']);
		exit;
	}

	$passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
	$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
	$stmt->execute(['username' => $username, 'password' => $passwordHash, 'role' => $role]);

	http_response_code(201);
	echo json_encode(['message' => 'User registered successfully']);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error during registration.']);
}
