<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$currentRole = ucfirst(strtolower((string)($_SESSION['role'] ?? 'Guest')));
if (!in_array($currentRole, ['Admin', 'User', 'Guest'], true)) {
    $currentRole = 'Guest';
}
$_SESSION['role'] = $currentRole;

if ($currentRole !== 'Admin') {
    header('Location: index.php');
    exit();
}

require_once '../config/database.php';

$roles = ['Admin', 'User', 'Guest'];
$message = '';
$error = '';

function is_allowed_transition(string $oldRole, string $newRole): bool {
    if ($oldRole === $newRole) {
        return true;
    }

    $allowed = [
        'User' => ['Guest', 'Admin'],
        'Guest' => ['User'],
        'Admin' => ['User'],
    ];

    return in_array($newRole, $allowed[$oldRole] ?? [], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newRole = trim((string)($_POST['role'] ?? ''));

    if ($targetUserId === false || $targetUserId === null || !in_array($newRole, $roles, true)) {
        $error = 'Invalid role update request.';
    } else {
        try {
            $selectUser = $pdo->prepare(
                "SELECT id, username, role
                 FROM users
                 WHERE id = :id
                 LIMIT 1"
            );
            $selectUser->execute(['id' => $targetUserId]);
            $targetUser = $selectUser->fetch();

            if (!$targetUser) {
                $error = 'User not found.';
            } else {
                $oldRole = in_array((string)$targetUser['role'], $roles, true) ? (string)$targetUser['role'] : 'Guest';

                if (!is_allowed_transition($oldRole, $newRole)) {
                    $error = 'Role transition not allowed.';
                } elseif ($oldRole === $newRole) {
                    $message = 'Role is already set to ' . $newRole . '.';
                } else {
                    $updateRole = $pdo->prepare(
                        "UPDATE users
                         SET role = :role
                         WHERE id = :id"
                    );
                    $updateRole->execute([
                        'role' => $newRole,
                        'id' => $targetUserId,
                    ]);

                    $insertAudit = $pdo->prepare(
                        "INSERT INTO audit_trail (user_id, action, affected_table, details, changed_user_id, old_role, new_role)
                         VALUES (:user_id, :action, :affected_table, :details, :changed_user_id, :old_role, :new_role)"
                    );
                    $insertAudit->execute([
                        'user_id' => (int)$_SESSION['user_id'],
                        'action' => 'UPDATE',
                        'affected_table' => 'users',
                        'details' => sprintf(
                            'Updated role of %s from %s to %s',
                            (string)$targetUser['username'],
                            $oldRole,
                            $newRole
                        ),
                        'changed_user_id' => $targetUserId,
                        'old_role' => $oldRole,
                        'new_role' => $newRole,
                    ]);

                    if ((int)$targetUserId === (int)$_SESSION['user_id']) {
                        $_SESSION['role'] = $newRole;
                    }

                    $message = 'Role updated successfully.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error while updating role.';
        }
    }
}

$users = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, username, role
         FROM users
         ORDER BY id ASC"
    );
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error while loading users.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <h1 class="text-2xl font-bold">Admin User Management</h1>
                <div class="flex items-center gap-3">
                    <a href="index.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back to Dashboard</a>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <?php if ($message !== ''): ?>
            <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-green-700"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-red-700"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded overflow-hidden">
            <table class="min-w-full table-auto">
                <thead class="bg-gray-800 text-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Username</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <?php $userRole = in_array((string)$user['role'], $roles, true) ? (string)$user['role'] : 'Guest'; ?>
                        <tr class="bg-white">
                            <td class="px-4 py-3"><?php echo (int)$user['id']; ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-4 py-3">
                                <form method="post" class="flex items-center gap-2">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                    <select name="role" class="border rounded px-3 py-2">
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $userRole === $role ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>
                            <td class="px-4 py-3">
                                    <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Update Role</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
