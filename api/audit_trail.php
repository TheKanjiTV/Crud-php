<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth('Admin');

require_once '../config/database.php';

try {
	$stmt = $pdo->prepare(
		"SELECT
			audit_trail.id,
			audit_trail.user_id,
			admin_user.username AS admin_username,
			audit_trail.changed_user_id,
			target_user.username AS changed_username,
			audit_trail.old_role,
			audit_trail.new_role,
			audit_trail.ip_address,
			audit_trail.action,
			audit_trail.timestamp
		 FROM audit_trail
		 LEFT JOIN users AS admin_user ON audit_trail.user_id = admin_user.id
		 LEFT JOIN users AS target_user ON audit_trail.changed_user_id = target_user.id
		 ORDER BY audit_trail.timestamp DESC"
	);
	$stmt->execute();
	$logs = $stmt->fetchAll();
	echo json_encode($logs);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error while reading audit trail.']);
}
