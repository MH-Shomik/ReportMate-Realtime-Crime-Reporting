<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login();

$page_title = "Delete Report | CrimeAlert";
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Fetch the report to confirm ownership before showing the confirmation
try {
    $stmt = $pdo->prepare("SELECT id, title FROM crime_reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        $_SESSION['error_message'] = "Report not found or you don't have permission to delete it.";
        redirect('dashboard.php');
    }
} catch (PDOException $e) {
    error_log("Error fetching report for delete confirmation: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred. Please try again.";
    redirect('dashboard.php');
}


// Handle the deletion on POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token.");
        }
        if (isset($_POST['confirm_delete'])) {
            // Use a transaction for safety
            $pdo->beginTransaction();

            // 1. Delete associated comments
            $stmt_comments = $pdo->prepare("DELETE FROM comments WHERE report_id = ?");
            $stmt_comments->execute([$report_id]);

            // 2. Delete associated images (and files)
            $stmt_images = $pdo->prepare("SELECT image_path FROM crime_images WHERE crime_id = ?");
            $stmt_images->execute([$report_id]);
            $images = $stmt_images->fetchAll(PDO::FETCH_COLUMN);

            foreach ($images as $image_path) {
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            $stmt_delete_images = $pdo->prepare("DELETE FROM crime_images WHERE crime_id = ?");
            $stmt_delete_images->execute([$report_id]);

            // 3. Delete the report itself
            $stmt_report = $pdo->prepare("DELETE FROM crime_reports WHERE id = ? AND user_id = ?");
            $stmt_report->execute([$report_id, $user_id]);

            $pdo->commit();

            $_SESSION['success_message'] = "The report and all associated data have been permanently deleted.";
            redirect('dashboard.php');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error deleting report: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to delete the report. Please try again.";
        redirect("view-report.php?id=" . $report_id);
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow-md mt-10 text-center">
    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
        <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
    </div>
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Confirm Deletion</h2>

    <p class="text-gray-600 mb-6">
        Are you sure you want to permanently delete the report titled:
        <strong class="block mt-2">"<?= htmlspecialchars($report['title']) ?>"</strong>?
        <br><br>
        This action cannot be undone. All associated comments and images will also be deleted.
    </p>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <div class="flex justify-center space-x-4">
            <a href="view-report.php?id=<?= $report_id ?>" class="bg-gray-200 text-gray-800 px-8 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                Cancel
            </a>
            <button type="submit" name="confirm_delete" 
                class="bg-red-600 text-white px-8 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                Yes, Delete It
            </button>
        </div>
    </form>
</div>


<?php
require_once 'includes/footer.php';
?>
