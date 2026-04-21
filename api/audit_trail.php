<?php
header('Content-Type: application/json');

require_once 'session.php';
check_auth('Admin'); // Only admins can view the audit trail

require_once '../config/database.php';

try {
    $hasAffectedTable = column_exists($pdo, $db, 'audit_trail', 'affected_table');
    $hasDetails = column_exists($pdo, $db, 'audit_trail', 'details');

    $affectedTableExpr = $hasAffectedTable ? 'a.affected_table' : 'NULL';
    $detailsExpr = $hasDetails ? 'a.details' : 'NULL';

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(u.role, 'Unknown') AS user_role,
            COALESCE(u.username, '[Deleted User]') AS username,
            a.action,
            {$affectedTableExpr} AS affected_table,
            {$detailsExpr} AS details,
            a.timestamp AS date_time
        FROM audit_trail a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.timestamp DESC, a.id DESC"
    );
    $stmt->execute();
    $audit_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($audit_data);

} catch (PDOException $e) {
    http_response_code(500);
    // For debugging, you might want to log the error.
    // error_log($e->getMessage());
    echo json_encode(['message' => 'Database error while fetching audit trail.']);
}
