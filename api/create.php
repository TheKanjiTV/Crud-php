<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth('user');

require_once '../config/database.php';

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
	$data = [];
	parse_str($raw, $data);
}

$productNameRaw = (string)($data['productName'] ?? '');
$priceRaw = (string)($data['price'] ?? '');

$productName = trim(preg_replace('/\s+/', ' ', strip_tags($productNameRaw)));
$price = trim($priceRaw);

if ($productName === '') {
	http_response_code(422);
	echo json_encode(['message' => 'Product name is required.']);
	exit;
}

$nameLength = function_exists('mb_strlen') ? mb_strlen($productName) : strlen($productName);
if ($nameLength > 255) {
	http_response_code(422);
	echo json_encode(['message' => 'Product name is too long (max 255).']);
	exit;
}

if ($price === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $price)) {
	http_response_code(422);
	echo json_encode(['message' => 'Price must be a valid number (up to 2 decimals).']);
	exit;
}

try {
	$productCode = 'P' . random_int(100000, 999999);

	$stmt = $pdo->prepare(
		"INSERT INTO products (productCode, productName, price)
		 VALUES (:productCode, :productName, :price)"
	);
	$stmt->execute([
		'productCode' => $productCode,
		'productName' => $productName,
		'price' => $price,
	]);

	$productId = (int)$pdo->lastInsertId();

	$audit = $pdo->prepare(
		"INSERT INTO audit_trail (user_id, action)
		 VALUES (:user_id, :action)"
	);
	$audit->execute([
		'user_id' => $_SESSION['user_id'],
		'action' => 'Created product with ID ' . $productId,
	]);

	http_response_code(201);
	echo json_encode(['message' => 'Product created successfully', 'id' => $productId]);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error while creating product.']);
}
