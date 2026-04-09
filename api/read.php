<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth();

require_once '../config/database.php';

try {
	$stmt = $pdo->prepare(
		"SELECT id, productCode, productName, price, deleted_at
		 FROM products
		 WHERE deleted_at IS NULL
		 ORDER BY id DESC"
	);
	$stmt->execute();
	$products = $stmt->fetchAll();
	echo json_encode($products);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error while reading products.']);
}
