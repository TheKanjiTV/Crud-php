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

$idRaw = $data['id'] ?? null;
$productNameRaw = (string)($data['productName'] ?? '');
$priceRaw = (string)($data['price'] ?? '');

$id = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$productName = trim(preg_replace('/\s+/', ' ', strip_tags($productNameRaw)));
$price = trim($priceRaw);

if ($id === false) {
	http_response_code(422);
	echo json_encode(['message' => 'Valid product id is required.']);
	exit;
}

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
	$exists = $pdo->prepare(
		"SELECT id
		 FROM products
		 WHERE id = :id AND is_deleted = 0"
	);
	$exists->execute(['id' => $id]);
	if (!$exists->fetch()) {
		http_response_code(404);
		echo json_encode(['message' => 'Product not found.']);
		exit;
	}

	$stmt = $pdo->prepare(
		"UPDATE products
		 SET productName = :productName,
			 price = :price
		 WHERE id = :id AND is_deleted = 0"
	);
	$stmt->execute([
		'id' => $id,
		'productName' => $productName,
		'price' => $price,
	]);

	$audit = $pdo->prepare(
		"INSERT INTO audit_trail (user_id, action)
		 VALUES (:user_id, :action)"
	);
	$audit->execute([
		'user_id' => $_SESSION['user_id'],
		'action' => 'Updated product with ID ' . $id,
	]);

	echo json_encode(['message' => 'Product updated successfully']);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['message' => 'Database error while updating product.']);
}
