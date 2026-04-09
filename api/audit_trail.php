<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth('admin');

require_once '../config/database.php';

try {
	$stmt = $pdo->query(
		"SELECT audit_trail.*, users.username
		 FROM audit_trail
		 JOIN users ON audit_trail.user_id = users.id
		 ORDER BY timestamp DESC"
	);
	$logs = $stmt->fetchAll();
	echo json_encode($logs);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error while reading audit trail.']);
}
