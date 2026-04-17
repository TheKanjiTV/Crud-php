<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$roleRaw = (string)($_SESSION['role'] ?? 'Guest');
$roleNormalized = ucfirst(strtolower($roleRaw));
if (!in_array($roleNormalized, ['Admin', 'User', 'Guest'], true)) {
    $roleNormalized = 'Guest';
}
$_SESSION['role'] = $roleNormalized;

$isAdmin = ($roleNormalized === 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100" data-role="<?php echo htmlspecialchars($roleNormalized, ENT_QUOTES, 'UTF-8'); ?>">
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-2xl font-bold">Dashboard</h1>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if ($isAdmin): ?>
                        <a href="admin_users.php" class="mr-4 bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Manage Users</a>
                    <?php endif; ?>
                    <span class="mr-4">Welcome, <span class="font-bold"><?php echo htmlspecialchars($roleNormalized, ENT_QUOTES, 'UTF-8'); ?></span>!</span>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-8">
        <div class="flex justify-end mb-4">
            <button id="create-btn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Create Product</button>
        </div>

        <div id="app" class="bg-white shadow-md rounded my-6">
            <!-- Product management will be rendered here -->
        </div>

        <div id="form-container" style="display: none;" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <h2 id="form-title" class="text-2xl mb-4">Create Product</h2>
                <form id="product-form">
                    <input type="hidden" id="product-id" name="id">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="productName">Product Name:</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" type="text" id="productName" name="productName">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="price">Price:</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" type="text" id="price" name="price">
                    </div>
                    <div class="flex items-center justify-between">
                        <input class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" value="Submit">
                        <button id="cancel-btn" type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="js/app.js?v=20260410"></script>
</body>
</html>