<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login();

$page_title = "View Report | CrimeAlert";
$report = null;
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';
$success_message = '';

if ($report_id > 0) {
    try {
        // Fetch report details
        $stmt = $pdo->prepare("SELECT cr.*, u.username FROM crime_reports cr JOIN users u ON cr.user_id = u.id WHERE cr.id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            $error_message = "Report not found.";
        }

    } catch (PDOException $e) {
        error_log("Error fetching report: " . $e->getMessage());
        $error_message = "An error occurred while fetching the report.";
    }
} else {
    $error_message = "Invalid report ID.";
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid or expired form submission. Please try again.';
    } elseif ($report) {
        $comment_text = sanitize($_POST['comment_text']);
        $user_id = $_SESSION['user_id'];

        if (empty($comment_text)) {
            $error_message = "Comment cannot be empty.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO comments (report_id, user_id, comment_text) VALUES (?, ?, ?)");
                $stmt->execute([$report_id, $user_id, $comment_text]);
                $success_message = "Your comment has been added.";
                $_POST['comment_text'] = '';
            } catch (PDOException $e) {
                error_log("Error adding comment: " . $e->getMessage());
                $error_message = "Failed to add comment. Please try again.";
            }
        }
    }
}

// Fetch comments for the report
$comments = [];
if ($report) {
    try {
        $stmt_comments = $pdo->prepare("
            SELECT c.*, u.username
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.report_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt_comments->execute([$report_id]);
        $comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching comments: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <?php flash('success_message'); ?>
    <?php if (isset($_SESSION['error_message'])) {
        echo '<div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg"><i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    } ?>
    <?php if ($error_message): ?>
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($report): ?>
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($report['title']) ?></h1>
                    <span class="inline-block bg-indigo-100 text-indigo-800 text-sm px-3 py-1 rounded-full font-semibold">
                        <?= ucfirst(htmlspecialchars($report['crime_type'])) ?>
                    </span>
                    <span class="ml-2 inline-block text-sm font-medium px-2.5 py-1 rounded-full
                        <?php echo $report['status'] === 'resolved' ? 'bg-green-100 text-green-800' : ($report['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($report['status']))) ?>
                    </span>
                </div>
                <div class="text-left sm:text-right mt-4 sm:mt-0">
                    <p class="text-sm text-gray-500">Reported by:
                        <span class="font-medium text-gray-700">
                            <?= $report['is_anonymous'] ? 'Anonymous' : htmlspecialchars($report['username']) ?>
                        </span>
                    </p>
                    <p class="text-sm text-gray-500">On: <?= date('M j, Y g:i a', strtotime($report['created_at'])) ?></p>
                </div>
            </div>
            
            <!-- NEW: Action Buttons for Report Owner -->
            <?php if (!$report['is_anonymous'] && $report['user_id'] === $_SESSION['user_id']): ?>
            <div class="border-t border-b border-gray-200 my-6 py-4 flex items-center justify-start space-x-3">
                 <h3 class="text-lg font-semibold text-gray-700 mr-4">Manage Report</h3>
                <a href="edit-report.php?id=<?= $report['id'] ?>" class="bg-blue-500 text-white px-5 py-2 rounded-lg hover:bg-blue-600 transition text-sm font-semibold">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <a href="delete-report.php?id=<?= $report['id'] ?>" class="bg-red-500 text-white px-5 py-2 rounded-lg hover:bg-red-600 transition text-sm font-semibold">
                    <i class="fas fa-trash-alt mr-2"></i>Delete
                </a>
            </div>
            <?php endif; ?>


            <h2 class="text-xl font-semibold text-gray-800 mb-2 mt-6">Description</h2>
            <p class="text-gray-700 leading-relaxed mb-6">
                <?= nl2br(htmlspecialchars($report['description'])) ?>
            </p>

            <?php if (!empty($report['latitude']) && !empty($report['longitude'])): ?>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Location</h2>
                <div id="reportMap" class="h-64 w-full rounded-lg bg-gray-200 mb-6"></div>
            <?php endif; ?>

            <?php
            // Fetch images if available
            $report_images = [];
            try {
                $stmt_img = $pdo->prepare("SELECT image_path FROM crime_images WHERE crime_id = ?");
                $stmt_img->execute([$report_id]);
                $report_images = $stmt_img->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                error_log("Error fetching report images: " . $e->getMessage());
            }

            if (!empty($report_images)): ?>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Attached Images</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-6">
                    <?php foreach ($report_images as $image_path): ?>
                        <div class="rounded-lg overflow-hidden shadow-sm">
                            <img src="<?= htmlspecialchars($image_path) ?>" alt="Crime image" class="w-full h-32 object-cover">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Community Comments (<?= count($comments) ?>)</h2>

            <div class="space-y-4 mb-6 max-h-96 overflow-y-auto pr-2">
                <?php if (empty($comments)): ?>
                    <p class="text-gray-500">No comments yet. Be the first to comment!</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="border-b border-gray-200 pb-4 last:border-b-0">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($comment['username']) ?></span>
                                <span class="text-sm text-gray-500"><?= time_ago($comment['created_at']) ?></span>
                            </div>
                            <p class="text-gray-700 leading-snug"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-6 border-t border-gray-200 pt-6">
                <h3 class="text-lg font-bold text-gray-800 mb-3">Add Your Comment</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_comment">
                    <textarea name="comment_text" rows="4" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 mb-3"
                        placeholder="Type your comment here..."></textarea>
                    <button type="submit"
                        class="bg-indigo-600 text-white py-2 px-5 rounded-lg hover:bg-indigo-700 transition">
                        Post Comment
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <p class="text-center text-red-500 text-lg"><?= htmlspecialchars($error_message) ?></p>
        <div class="text-center mt-6">
            <a href="dashboard.php" class="inline-block bg-gray-200 text-gray-800 px-6 py-3 rounded-lg hover:bg-gray-300 transition">
                Go to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($report && !empty($report['latitude']) && !empty($report['longitude'])): ?>
<script>
    function initReportMap() {
        const mapElement = document.getElementById('reportMap');
        if (!mapElement) return;

        const reportLocation = { lat: parseFloat(<?= $report['latitude'] ?>), lng: parseFloat(<?= $report['longitude'] ?>) };
        const map = new google.maps.Map(mapElement, {
            center: reportLocation,
            zoom: 14,
            mapTypeControl: false,
            streetViewControl: false,
            gestureHandling: 'cooperative'
        });

        new google.maps.Marker({
            position: reportLocation,
            map: map,
            title: "Incident Location"
        });
    }
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M&callback=initReportMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
</script>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
