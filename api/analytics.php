<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth();

require_once '../config/database.php';

function queryValue(PDO $pdo, string $sql, array $params = []): mixed {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function queryRows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function buildRecentMonthSeries(array $rows, int $months = 6): array {
    $map = [];
    foreach ($rows as $row) {
        $label = (string)($row['month_label'] ?? '');
        if ($label !== '') {
            $map[$label] = (float)($row['revenue'] ?? 0);
        }
    }

    $labels = [];
    $values = [];
    $cursor = new DateTime('first day of this month');
    $cursor->modify('-' . ($months - 1) . ' months');
    for ($i = 0; $i < $months; $i++) {
        $key = $cursor->format('Y-m');
        $labels[] = $key;
        $values[] = isset($map[$key]) ? (float)$map[$key] : 0.0;
        $cursor->modify('+1 month');
    }

    return [
        'labels' => $labels,
        'values' => $values,
    ];
}

try {
    $analyticsPdo = $pdo;
    $warnings = [];

    // Prefer creative_zone_db for analytics while keeping existing CRUD DB untouched.
    if (($db ?? '') !== 'creative_zone_db') {
        try {
            $creativeDsn = "mysql:host={$host};dbname=creative_zone_db;charset={$charset}";
            $analyticsPdo = new PDO($creativeDsn, $user, $pass, $options);
            $warnings[] = 'Analytics is connected to creative_zone_db.';
        } catch (PDOException $e) {
            $warnings[] = 'Could not connect to creative_zone_db; using current database instead.';
        }
    }

    $dbName = (string)queryValue($analyticsPdo, 'SELECT DATABASE()');

    $summary = [
        'users' => (int)queryValue($analyticsPdo, 'SELECT COUNT(*) FROM users'),
        'products' => (int)queryValue($analyticsPdo, 'SELECT COUNT(*) FROM products'),
        'orders' => (int)queryValue($analyticsPdo, 'SELECT COUNT(*) FROM orders'),
        'totalRevenue' => (float)queryValue(
            $analyticsPdo,
            "SELECT COALESCE((SELECT SUM(amount) FROM payments), (SELECT SUM(total) FROM orders), 0)"
        ),
    ];

    $monthlyRows = queryRows(
        $analyticsPdo,
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_label,
                ROUND(SUM(amount), 2) AS revenue
         FROM payments
         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
         ORDER BY month_label ASC"
    );

    if (count($monthlyRows) === 0) {
        $monthlyRows = queryRows(
            $analyticsPdo,
            "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month_label,
                    ROUND(SUM(total), 2) AS revenue
             FROM orders
             WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(order_date, '%Y-%m')
             ORDER BY month_label ASC"
        );
    }

    $monthlyRevenue = buildRecentMonthSeries($monthlyRows, 6);

    $statusRows = queryRows(
        $analyticsPdo,
        "SELECT status, COUNT(*) AS total
         FROM orders
         GROUP BY status
         ORDER BY total DESC"
    );
    $orderStatus = [
        'labels' => array_map(static fn($r) => (string)($r['status'] ?: 'unknown'), $statusRows),
        'values' => array_map(static fn($r) => (int)$r['total'], $statusRows),
    ];

    $categoryRows = queryRows(
        $analyticsPdo,
        "SELECT c.name AS category_name,
                ROUND(SUM(oi.quantity * oi.price), 2) AS sales
         FROM order_items oi
         INNER JOIN products p ON p.product_id = oi.product_id
         INNER JOIN categories c ON c.category_id = p.category_id
         GROUP BY c.category_id, c.name
         ORDER BY sales DESC
         LIMIT 5"
    );
    $topCategories = [
        'labels' => array_column($categoryRows, 'category_name'),
        'values' => array_map(static fn($r) => (float)$r['sales'], $categoryRows),
    ];

    $paymentRows = queryRows(
        $analyticsPdo,
        "SELECT method, COUNT(*) AS total
         FROM payments
         GROUP BY method
         ORDER BY total DESC"
    );
    $paymentMethods = [
        'labels' => array_map(static fn($r) => (string)($r['method'] ?: 'unknown'), $paymentRows),
        'values' => array_map(static fn($r) => (int)$r['total'], $paymentRows),
    ];

    echo json_encode([
        'database' => $dbName,
        'warnings' => $warnings,
        'summary' => $summary,
        'charts' => [
            'monthlyRevenue' => $monthlyRevenue,
            'orderStatus' => $orderStatus,
            'topCategories' => $topCategories,
            'paymentMethods' => $paymentMethods,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Database error while preparing analytics data.',
    ]);
}
