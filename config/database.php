<?php
$host = 'localhost';
$db   = 'db_estore';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function table_exists(PDO $pdo, string $schema, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = :schema AND table_name = :table"
    );
    $stmt->execute([
        'schema' => $schema,
        'table' => $table,
    ]);

    return ((int)$stmt->fetchColumn()) > 0;
}

function column_exists(PDO $pdo, string $schema, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = :schema
           AND table_name = :table
           AND column_name = :column"
    );
    $stmt->execute([
        'schema' => $schema,
        'table' => $table,
        'column' => $column,
    ]);

    return ((int)$stmt->fetchColumn()) > 0;
}

function normalize_roles(PDO $pdo): void {
    $pdo->exec(
        "UPDATE users
         SET role = CASE
             WHEN LOWER(role) = 'admin' THEN 'Admin'
             WHEN LOWER(role) = 'user' THEN 'User'
             WHEN LOWER(role) = 'guest' THEN 'Guest'
             ELSE 'Guest'
         END"
    );
}

function ensure_users_role_enum(PDO $pdo): void {
    normalize_roles($pdo);

    $pdo->exec(
        "ALTER TABLE users
         MODIFY role ENUM('Admin','User','Guest') NOT NULL DEFAULT 'Guest'"
    );
}

function ensure_default_admin(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = :role LIMIT 1");
    $stmt->execute(['role' => 'Admin']);
    if ($stmt->fetch()) {
        return;
    }

    $existingAdmin = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE username = :username
         ORDER BY id ASC
         LIMIT 1"
    );
    $existingAdmin->execute(['username' => 'admin']);
    $row = $existingAdmin->fetch();

    if ($row) {
        $update = $pdo->prepare(
            "UPDATE users
             SET password = :password,
                 role = :role
             WHERE id = :id"
        );
        $update->execute([
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'Admin',
            'id' => (int)$row['id'],
        ]);
        return;
    }

    $insert = $pdo->prepare(
        "INSERT INTO users (username, password, role)
         VALUES (:username, :password, :role)"
    );
    $insert->execute([
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'Admin',
    ]);
}

function ensure_audit_role_columns(PDO $pdo, string $schema): void {
    if (!table_exists($pdo, $schema, 'audit_trail')) {
        return;
    }

    if (!column_exists($pdo, $schema, 'audit_trail', 'changed_user_id')) {
        $pdo->exec("ALTER TABLE audit_trail ADD COLUMN changed_user_id INT(11) DEFAULT NULL AFTER user_id");
    }

    if (!column_exists($pdo, $schema, 'audit_trail', 'old_role')) {
        $pdo->exec("ALTER TABLE audit_trail ADD COLUMN old_role ENUM('Admin','User','Guest') DEFAULT NULL AFTER changed_user_id");
    }

    if (!column_exists($pdo, $schema, 'audit_trail', 'new_role')) {
        $pdo->exec("ALTER TABLE audit_trail ADD COLUMN new_role ENUM('Admin','User','Guest') DEFAULT NULL AFTER old_role");
    }

    if (!column_exists($pdo, $schema, 'audit_trail', 'ip_address')) {
        $pdo->exec("ALTER TABLE audit_trail ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER new_role");
    }

    // Backward-compatible columns used by current API endpoints.
    if (!column_exists($pdo, $schema, 'audit_trail', 'affected_table')) {
        $pdo->exec("ALTER TABLE audit_trail ADD COLUMN affected_table VARCHAR(255) DEFAULT NULL AFTER action");
    }

    if (!column_exists($pdo, $schema, 'audit_trail', 'details')) {
        $pdo->exec("ALTER TABLE audit_trail ADD COLUMN details TEXT DEFAULT NULL AFTER affected_table");
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    if (table_exists($pdo, $db, 'users')) {
        ensure_users_role_enum($pdo);
        ensure_default_admin($pdo);
    }

    ensure_audit_role_columns($pdo, $db);

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
