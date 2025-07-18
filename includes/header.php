<?php
// includes/header.php

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; ?><?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/custom.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-indigo-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-2xl font-bold">
                        <i class="fas fa-shield-alt mr-2"></i><?php echo APP_NAME; ?>
                    </a>
                    <?php if (is_logged_in()): ?>
                        <a href="dashboard.php" class="hover:bg-indigo-600 px-3 py-2 rounded">Dashboard</a>
                        <a href="report.php" class="hover:bg-indigo-600 px-3 py-2 rounded">Report Crime</a>
                        <a href="map.php" class="hover:bg-indigo-600 px-3 py-2 rounded">Crime Map</a>
                        <a href="all-reports.php" class="hover:bg-indigo-600 px-3 py-2 rounded">View Reports</a>
                        <a href="members.php" class="hover:bg-indigo-600 px-3 py-2 rounded">Members and Chat</a>
                        <a href="my-zones.php" class="hover:bg-indigo-600 px-3 py-2 rounded">My Zones</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (is_logged_in()): ?>
                        <span class="hidden sm:inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="profile.php" class="hover:bg-indigo-600 px-3 py-2 rounded">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-3 py-2 rounded">
                            <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">Logout</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="hover:bg-indigo-600 px-3 py-2 rounded">Login</a>
                        <a href="register.php" class="bg-indigo-800 hover:bg-indigo-900 px-3 py-2 rounded">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-6">
        <?php flash(); ?>