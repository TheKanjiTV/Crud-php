<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth('admin');

require_once '../config/database.php';

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
	$data = [];
	parse_str($raw, $data);
}

$idRaw = $data['id'] ?? null;
$id = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($id === false) {
	http_response_code(422);
	echo json_encode(['message' => 'Valid product id is required.']);
	exit;
}

try {
	$stmt = $pdo->prepare(
		"UPDATE products
		 SET deleted_at = NOW()
		 WHERE id = :id AND deleted_at IS NULL"
	);
	$stmt->execute(['id' => $id]);

	if ($stmt->rowCount() === 0) {
		http_response_code(404);
		echo json_encode(['message' => 'Product not found or already deleted.']);
		exit;
	}

	$audit = $pdo->prepare(
		"INSERT INTO audit_trail (user_id, action)
		 VALUES (:user_id, :action)"
	);
	$audit->execute([
		'user_id' => $_SESSION['user_id'],
		'action' => 'Soft deleted product with ID ' . $id,
	]);

	echo json_encode(['message' => 'Product soft deleted successfully']);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error while deleting product.']);
}
