<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$page_title = "CrimeAlert - Real-Time Crime Reporting";
$recent_crimes = [];

// If user is logged in, fetch recent crimes
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, description, crime_type, created_at 
                              FROM crime_reports 
                              ORDER BY created_at DESC 
                              LIMIT 5");
        $stmt->execute();
        $recent_crimes = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching crimes: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<div class="bg-indigo-700 text-white py-12">
    <div class="container mx-auto px-4 text-center">
        <h1 class="text-4xl font-bold mb-4">Report Crimes in Real-Time</h1>
        <p class="text-xl mb-8">Help keep your community safe by reporting crimes as they happen</p>
        
        <?php if (!is_logged_in()): ?>
            <div class="flex justify-center space-x-4">
                <a href="register.php" class="bg-white text-indigo-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                    Get Started
                </a>
                <a href="login.php" class="bg-indigo-800 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-900 transition">
                    Login
                </a>
            </div>
        <?php else: ?>
            <a href="report.php" class="inline-block bg-white text-indigo-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                Report a Crime
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container mx-auto px-4 py-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="text-indigo-600 text-3xl mb-4">
                <i class="fas fa-bell"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Real-Time Alerts</h3>
            <p class="text-gray-600">Receive instant notifications about crimes happening in your area.</p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="text-indigo-600 text-3xl mb-4">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Interactive Map</h3>
            <p class="text-gray-600">View reported crimes on our live map to stay informed about your neighborhood.</p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="text-indigo-600 text-3xl mb-4">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Community Safety</h3>
            <p class="text-gray-600">Join forces with your community to create a safer environment for everyone.</p>
        </div>
    </div>
</div>

<?php if (!empty($recent_crimes)): ?>
<div class="container mx-auto px-4 py-8">
    <h2 class="text-2xl font-bold mb-6">Recently Reported Crimes</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($recent_crimes as $crime): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($crime['title']); ?></h3>
                    <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full">
                        <?php echo ucfirst($crime['crime_type']); ?>
                    </span>
                </div>
                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars(substr($crime['description'], 0, 100)); ?>...</p>
                <div class="text-sm text-gray-500">
                    <?php echo date('M j, Y g:i a', strtotime($crime['created_at'])); ?>
                </div>
                <a href="view-report.php?id=<?php echo $crime['id']; ?>" class="inline-block mt-4 text-indigo-600 hover:underline">
                    View Details
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>