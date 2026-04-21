<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth('Admin');

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
	$pdo->beginTransaction();

	// Get product name for audit log
	$nameStmt = $pdo->prepare("SELECT productName FROM products WHERE id = :id");
	$nameStmt->execute(['id' => $id]);
	$product = $nameStmt->fetch(PDO::FETCH_ASSOC);
	$productName = $product ? $product['productName'] : 'N/A';

	$stmt = $pdo->prepare(
		"UPDATE products
		 SET is_deleted = 1, deleted_at = NOW()
		 WHERE id = :id AND is_deleted = 0"
	);
	$stmt->execute(['id' => $id]);

	if ($stmt->rowCount() === 0) {
		$pdo->rollBack();
		http_response_code(404);
		echo json_encode(['message' => 'Product not found or already deleted.']);
		exit;
	}

	$audit = $pdo->prepare(
		"INSERT INTO audit_trail (user_id, action, affected_table, details)
		 VALUES (:user_id, :action, :affected_table, :details)"
	);
	$audit->execute([
		'user_id' => $_SESSION['user_id'],
		'action' => 'DELETE',
		'affected_table' => 'products',
		'details' => "Soft-deleted product ID #{$id} ({$productName})",
	]);

	$pdo->commit();

	echo json_encode(['message' => 'Product soft deleted successfully']);
} catch (PDOException $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	http_response_code(500);
	echo json_encode(['message' => 'Database error while deleting product.', 'error' => $e->getMessage()]);
}
