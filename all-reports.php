<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = "All Crime Reports | CrimeAlert";
$all_reports = [];
$error_message = '';

// Pagination settings
$limit = 10; // Number of reports per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filters
$filter_type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';


try {
    // Build the WHERE clause for filters and search
    $where_clauses = ["1=1"]; // Start with a true condition
    $params = [];

    if (!empty($filter_type)) {
        $where_clauses[] = "cr.crime_type = ?";
        $params[] = $filter_type;
    }
    if (!empty($filter_status)) {
        $where_clauses[] = "cr.status = ?";
        $params[] = $filter_status;
    }
    if (!empty($search_query)) {
        // Use LIKE for searching in title or description
        $where_clauses[] = "(cr.title LIKE ? OR cr.description LIKE ?)";
        $params[] = '%' . $search_query . '%';
        $params[] = '%' . $search_query . '%';
    }

    $where_sql = implode(" AND ", $where_clauses);

    // Get total number of reports for pagination
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM crime_reports cr WHERE " . $where_sql);
    $stmt_count->execute($params);
    $total_reports = $stmt_count->fetchColumn();
    $total_pages = ceil($total_reports / $limit);

    // Fetch all crime reports with user data
    $stmt_reports = $pdo->prepare("
        SELECT 
            cr.id, 
            cr.title, 
            cr.description, 
            cr.crime_type, 
            cr.status, 
            cr.created_at,
            cr.is_anonymous,
            u.username
        FROM 
            crime_reports cr
        JOIN 
            users u ON cr.user_id = u.id
        WHERE 
            " . $where_sql . "
        ORDER BY 
            cr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    // Add limit and offset params
    $params[] = $limit;
    $params[] = $offset;

    $stmt_reports->execute($params);
    $all_reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct crime types for filter dropdown
    $stmt_types = $pdo->query("SELECT DISTINCT crime_type FROM crime_reports ORDER BY crime_type");
    $crime_types = $stmt_types->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Error fetching all reports: " . $e->getMessage());
    $error_message = "An error occurred while fetching crime reports. Please try again later.";
    $total_reports = 0; // Reset for pagination logic
    $total_pages = 0;
}


require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="bg-white p-4 rounded-xl shadow-md mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">All Reported Incidents</h1>
        <p class="text-gray-600 mb-6">Browse all crime reports submitted across the community.</p>

        <!-- Filters and Search Form -->
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 items-end">
            <div>
                <label for="typeFilter" class="block text-sm font-medium text-gray-700">Crime Type</label>
                <select id="typeFilter" name="type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">All Types</option>
                    <?php foreach ($crime_types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= ($filter_type === $type) ? 'selected' : '' ?>>
                            <?= ucfirst(htmlspecialchars($type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="statusFilter" class="block text-sm font-medium text-gray-700">Status</label>
                <select id="statusFilter" name="status" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($filter_status === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="resolved" <?= ($filter_status === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                    <option value="under_investigation" <?= ($filter_status === 'under_investigation') ? 'selected' : '' ?>>Under Investigation</option>
                    <!-- Add other statuses as needed in your system -->
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700">Search by Title/Description</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search reports..." class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div class="md:col-span-4 text-right">
                <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                    Apply Filters
                </button>
                <a href="all-reports.php" class="ml-2 bg-gray-200 text-gray-800 px-5 py-2 rounded-lg hover:bg-gray-300 transition">
                    Reset Filters
                </a>
            </div>
        </form>
    </div>

    <?php if ($error_message): ?>
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($all_reports)): ?>
        <div class="bg-white p-6 rounded-xl shadow-md text-center text-gray-500 text-lg">
            No reports found matching your criteria.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($all_reports as $report): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col h-full">
                    <div class="p-6 flex-grow">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-semibold text-gray-900 leading-tight">
                                <?= htmlspecialchars($report['title']) ?>
                            </h3>
                            <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full whitespace-nowrap">
                                <?= ucfirst(htmlspecialchars($report['crime_type'])) ?>
                            </span>
                        </div>
                        <p class="text-gray-600 text-sm mb-3 line-clamp-3">
                            <?= htmlspecialchars($report['description']) ?>
                        </p>
                        <div class="text-sm text-gray-500">
                            Reported by: <span class="font-medium">
                                <?= $report['is_anonymous'] ? 'Anonymous' : htmlspecialchars($report['username']) ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">
                            On: <?= date('M j, Y g:i a', strtotime($report['created_at'])) ?>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex justify-between items-center border-t border-gray-100">
                        <span class="text-sm font-medium px-2.5 py-1 rounded-full
                            <?php echo $report['status'] === 'resolved' ? 'bg-green-100 text-green-800' : ($report['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?= ucfirst(htmlspecialchars($report['status'])) ?>
                        </span>
                        <a href="view-report.php?id=<?= $report['id'] ?>" class="text-indigo-600 hover:underline font-semibold text-sm">
                            View Details &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center space-x-2 mt-8">
                <?php
                    $queryString = '';
                    if (!empty($filter_type)) $queryString .= '&type=' . urlencode($filter_type);
                    if (!empty($filter_status)) $queryString .= '&status=' . urlencode($filter_status);
                    if (!empty($search_query)) $queryString .= '&search=' . urlencode($search_query);
                ?>
                <a href="?page=<?= max(1, $page - 1) ?><?= $queryString ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 <?= ($page <= 1) ? 'opacity-50 cursor-not-allowed' : '' ?>">Previous</a>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?><?= $queryString ?>" class="px-4 py-2 rounded-lg 
                        <?= ($i === $page) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <a href="?page=<?= min($total_pages, $page + 1) ?><?= $queryString ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 <?= ($page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : '' ?>">Next</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
