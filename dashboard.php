<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login
require_login();

$page_title = "Dashboard | CrimeAlert";
$user_id = $_SESSION['user_id'];

// --- Fetch all necessary data in one go ---

try {
    // 1. Get user-specific stats
    $stmt_stats = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM crime_reports WHERE user_id = ?) AS total_reports,
        (SELECT COUNT(*) FROM crime_reports WHERE user_id = ? AND status = 'resolved') AS resolved_reports,
        (SELECT COUNT(*) FROM crime_reports WHERE user_id = ? AND status = 'pending') AS pending_reports
    ");
    $stmt_stats->execute([$user_id, $user_id, $user_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // 2. Get user's 5 most recent reports
    $stmt_my_reports = $pdo->prepare(
        "SELECT id, title, crime_type, status, created_at 
         FROM crime_reports 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 5"
    );
    $stmt_my_reports->execute([$user_id]);
    $my_recent_reports = $stmt_my_reports->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get data for the crime distribution chart
    $stmt_chart = $pdo->query(
        "SELECT crime_type, COUNT(*) as count 
         FROM crime_reports 
         GROUP BY crime_type 
         ORDER BY count DESC"
    );
    $chart_data_raw = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

    // Format data for Chart.js
    $chart_labels = [];
    $chart_values = [];
    foreach ($chart_data_raw as $data) {
        $chart_labels[] = ucfirst($data['crime_type']);
        $chart_values[] = $data['count'];
    }
    $crime_chart_data = json_encode(['labels' => $chart_labels, 'values' => $chart_values]);

} catch (PDOException $e) {
    error_log("Dashboard data fetching error: " . $e->getMessage());
    // Set default empty values on error
    $stats = ['total_reports' => 0, 'resolved_reports' => 0, 'pending_reports' => 0];
    $my_recent_reports = [];
    $crime_chart_data = json_encode(['labels' => [], 'values' => []]);
}


require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Dashboard Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </h1>
            <p class="text-gray-500 mt-1">Here’s what’s happening in your community today.</p>
        </div>
        <a href="report.php" class="mt-4 sm:mt-0 w-full sm:w-auto bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
            <i class="fas fa-plus-circle mr-2"></i> Report an Incident
        </a>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Total Reports Submitted</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_reports'] ?? 0; ?></p>
            </div>
            <div class="bg-indigo-100 text-indigo-600 rounded-full h-12 w-12 flex items-center justify-center">
                <i class="fas fa-file-alt text-xl"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Reports Resolved</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats['resolved_reports'] ?? 0; ?></p>
            </div>
            <div class="bg-green-100 text-green-600 rounded-full h-12 w-12 flex items-center justify-center">
                <i class="fas fa-check-circle text-xl"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Pending Review</p>
                <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['pending_reports'] ?? 0; ?></p>
            </div>
            <div class="bg-yellow-100 text-yellow-600 rounded-full h-12 w-12 flex items-center justify-center">
                <i class="fas fa-hourglass-half text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Mini Map -->
            <!-- Mini Map -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Recent Incidents Map</h2>
                    <div>
                        <a href="map.php" class="text-sm font-medium text-indigo-600 hover:underline mr-4">View Full Map &rarr;</a>
                        <a href="all-reports.php" class="text-sm font-medium text-indigo-600 hover:underline">View All Reports &rarr;</a>
                    </div>
                </div>
                <div id="miniMap" class="h-96 w-full rounded-lg bg-gray-200">
                    <div class="flex items-center justify-center h-full text-gray-500">Loading map...</div>
                </div>
            </div>

            <!-- My Recent Reports -->
            <div class="bg-white rounded-xl shadow-md p-6">
                 <h2 class="text-xl font-bold text-gray-800 mb-4">Your Recent Reports</h2>
                 <div class="divide-y divide-gray-200">
                    <?php if (empty($my_recent_reports)): ?>
                        <p class="text-gray-500 py-4">You haven't submitted any reports yet.</p>
                    <?php else: ?>
                        <?php foreach ($my_recent_reports as $report): ?>
                            <div class="py-3 flex justify-between items-center">
                                <div>
                                    <a href="view-report.php?id=<?php echo $report['id']; ?>" class="font-semibold text-gray-700 hover:text-indigo-600"><?php echo htmlspecialchars($report['title']); ?></a>
                                    <p class="text-sm text-gray-500">
                                        <?php echo ucfirst(htmlspecialchars($report['crime_type'])); ?> - Reported <?php echo date('M j, Y', strtotime($report['created_at'])); ?>
                                    </p>
                                </div>
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full 
                                    <?php echo $report['status'] === 'resolved' ? 'bg-green-100 text-green-800' : ($report['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($report['status'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                 </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-8">
            <!-- Community Activity Feed -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Community Activity</h2>
                <div id="activityFeed" class="space-y-4 max-h-72 overflow-y-auto">
                     <div class="text-center text-gray-500 py-4">Loading activity...</div>
                </div>
            </div>

            <!-- Crime Distribution Chart -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Crime Distribution</h2>
                <div class="h-64">
                    <canvas id="crimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Make initMap globally available for the Google Maps callback
window.initMap = function() {
    const mapElement = document.getElementById('miniMap');
    if (!mapElement) return;

    const map = new google.maps.Map(mapElement, {
        center: { lat: 23.8103, lng: 90.4125 }, // Default center
        zoom: 11,
        mapTypeControl: false,
        streetViewControl: false,
        styles: [ /* Optional: Add modern map styles here */ ]
    });

    // Fetch and display markers for recent crimes
    fetchAndDisplayMarkers(map);
};

async function fetchAndDisplayMarkers(map) {
    try {
        const response = await fetch('api/get_crimes.php?limit=10'); // Get latest 10 reports
        const crimes = await response.json();

        if (crimes.length === 0) return;

        const bounds = new google.maps.LatLngBounds();

        crimes.forEach(crime => {
            if (crime.latitude && crime.longitude) {
                const position = { lat: parseFloat(crime.latitude), lng: parseFloat(crime.longitude) };
                new google.maps.Marker({
                    position: position,
                    map: map,
                    title: crime.title
                });
                bounds.extend(position);
            }
        });

        map.fitBounds(bounds); // Adjust map to show all markers
    } catch (error) {
        console.error('Error loading map markers:', error);
    }
}


// Function to load the recent activity feed
async function loadActivityFeed() {
    const feedContainer = document.getElementById('activityFeed');
    if (!feedContainer) return;

    try {
        const response = await fetch('api/get_dashboard_data.php?feed=true');
        const activities = await response.json();

        if (activities.length === 0) {
            feedContainer.innerHTML = '<p class="text-center text-gray-500 py-4">No recent community activity.</p>';
            return;
        }

        feedContainer.innerHTML = activities.map(activity => `
            <div class="flex items-start space-x-3">
                <div class="bg-gray-200 rounded-full h-8 w-8 flex-shrink-0 flex items-center justify-center">
                    <i class="fas fa-bullhorn text-gray-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-800">
                        <span class="font-semibold">${activity.title}</span> was reported in your area.
                    </p>
                    <p class="text-xs text-gray-500">${activity.time_ago}</p>
                </div>
            </div>
        `).join('');

    } catch(error) {
        console.error('Error loading activity feed:', error);
        feedContainer.innerHTML = '<p class="text-center text-red-500 py-4">Could not load activity.</p>';
    }
}

// Function to initialize the crime chart
function initCrimeChart() {
    const chartElement = document.getElementById('crimeChart');
    if (!chartElement) return;

    const chartData = <?php echo $crime_chart_data; ?>;
    
    if (chartData.labels.length === 0) {
        chartElement.parentElement.innerHTML = '<p class="text-center text-gray-500 h-full flex items-center justify-center">No crime data available to display.</p>';
        return;
    }

    const ctx = chartElement.getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.values,
                backgroundColor: [
                    '#4f46e5', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                ],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                    }
                }
            }
        }
    });
}


// Load all dynamic components when the page is ready
document.addEventListener('DOMContentLoaded', () => {
    initCrimeChart();
    loadActivityFeed();
});
</script>

<!-- Google Maps API Script -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M&callback=initMap" async defer></script>

<?php
// Check if the one-time session flag is set
if (isset($_SESSION['show_report_crime_popup']) && $_SESSION['show_report_crime_popup'] === true):
?>

<div id="report-now-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl p-6 sm:p-8 text-center max-w-md w-full transform transition-transform duration-300 scale-95">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
            <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
        </div>

        <h3 class="text-2xl font-bold text-gray-800 mb-2">Need to Report an Incident?</h3>

        <p class="text-gray-600 mb-6">
            Your safety is our priority. If you have witnessed or been a victim of a crime, please report it now. Your report can help keep the community safe.
        </p>

        <div class="flex flex-col sm:flex-row gap-3">
            <a href="report.php" class="w-full bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                <i class="fas fa-bullhorn mr-2"></i> Report Now
            </a>
            <button id="close-modal-btn" class="w-full bg-gray-200 text-gray-800 px-6 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Maybe Later
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('report-now-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    const dialog = modal.querySelector('.transform');

    // Function to close the modal
    function closeModal() {
        modal.classList.add('opacity-0');
        dialog.classList.remove('scale-100');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Wait for animations to finish
    }

    // Show the modal with a slight delay for effect
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        dialog.classList.add('scale-100');
    }, 100);

    // Event listeners to close the modal
    closeBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
});
</script>

<?php
// Unset the session variable so the pop-up does not appear again on page refresh
unset($_SESSION['show_report_crime_popup']);
endif;
?>


<?php
require_once 'includes/footer.php';
?>
