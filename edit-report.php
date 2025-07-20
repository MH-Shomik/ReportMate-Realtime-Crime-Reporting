<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login();

$page_title = "Edit Report | CrimeAlert";
$error = '';
$success = '';
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Fetch the existing report
try {
    $stmt = $pdo->prepare("SELECT * FROM crime_reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        $_SESSION['error_message'] = "Report not found or you don't have permission to edit it.";
        redirect('dashboard.php');
    }
} catch (PDOException $e) {
    error_log("Error fetching report for edit: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred. Please try again.";
    redirect('dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token.");
        }

        // Get form data
        $crime_type = sanitize($_POST['crime_type']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $status = sanitize($_POST['status']); // Added status field

        if (empty($title) || empty($description) || empty($crime_type) || empty($status)) {
            throw new Exception("All required fields must be filled.");
        }

        // Update the report
        $stmt_update = $pdo->prepare(
            "UPDATE crime_reports 
             SET title = ?, description = ?, crime_type = ?, status = ? 
             WHERE id = ? AND user_id = ?"
        );
        $stmt_update->execute([$title, $description, $crime_type, $status, $report_id, $user_id]);

        $_SESSION['success_message'] = "Report updated successfully!";
        redirect("view-report.php?id=" . $report_id);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}


require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md mt-8">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Edit Your Report</h2>
    
    <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Title -->
        <div class="mb-6">
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
            <input type="text" id="title" name="title" maxlength="100" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                value="<?= htmlspecialchars($report['title']) ?>">
        </div>

        <!-- Crime Type -->
        <div class="mb-6">
            <label for="crime_type" class="block text-sm font-medium text-gray-700 mb-2">Crime Type *</label>
            <select id="crime_type" name="crime_type" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="theft" <?= $report['crime_type'] == 'theft' ? 'selected' : '' ?>>Theft</option>
                <option value="assault" <?= $report['crime_type'] == 'assault' ? 'selected' : '' ?>>Assault</option>
                <option value="burglary" <?= $report['crime_type'] == 'burglary' ? 'selected' : '' ?>>Burglary</option>
                <option value="vandalism" <?= $report['crime_type'] == 'vandalism' ? 'selected' : '' ?>>Vandalism</option>
                <option value="other" <?= $report['crime_type'] == 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>

        <!-- Status -->
        <div class="mb-6">
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
            <select id="status" name="status" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="pending" <?= $report['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="under_investigation" <?= $report['status'] == 'under_investigation' ? 'selected' : '' ?>>Under Investigation</option>
                <option value="resolved" <?= $report['status'] == 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </div>

        <!-- Description -->
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
            <textarea name="description" id="description" rows="6" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"
                ><?= htmlspecialchars($report['description']) ?></textarea>
        </div>

        <div class="flex items-center justify-end space-x-4">
            <a href="view-report.php?id=<?= $report_id ?>" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 transition">
                Cancel
            </a>
            <button type="submit" 
                class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
