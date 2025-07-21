<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login(); //

$page_title = "My Alert Zones | CrimeAlert";
$user_id = $_SESSION['user_id']; //

// Fetch user's current zones
try {
    $stmt = $pdo->prepare("SELECT * FROM alert_zones WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $zones = $stmt->fetchAll();
} catch (PDOException $e) {
    $zones = [];
    $error_message = "Could not load your saved zones.";
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">My Safety Alert Zones</h1>
    <p class="text-gray-600 mb-6">Create custom zones to receive email notifications for incidents reported in specific areas.</p>

    <?php flash('zone_feedback'); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Create a New Zone</h2>
            <form action="api/zone-handler.php" method="POST" id="zone-form">
                <input type="hidden" name="action" value="add_zone">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"> <input type="hidden" name="latitude" id="latitudeInput" required>
                <input type="hidden" name="longitude" id="longitudeInput" required>

                <div class="mb-4">
                    <label for="zone_name" class="block text-sm font-medium text-gray-700">Zone Name</label>
                    <input type="text" name="zone_name" id="zone_name" required placeholder="e.g., Home, Office, School"
                           class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="mb-4">
                    <label for="radius_km" class="block text-sm font-medium text-gray-700">Radius</label>
                    <div class="flex items-center space-x-4">
                        <input type="range" name="radius_km" id="radius_km" min="1" max="20" value="5" step="0.5" class="w-full">
                        <span class="font-bold text-indigo-600"><span id="radius-display">5</span> km</span>
                    </div>
                </div>

                <div id="zoneMap" class="h-80 w-full rounded-lg bg-gray-200 mb-4"></div>
                <p class="text-sm text-gray-500 mb-4">Click on the map to set the center of your zone.</p>

                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition">
                    Save Zone
                </button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Saved Zones</h2>
            <div class="space-y-4">
                <?php if (empty($zones)): ?>
                    <p class="text-gray-500">You have no saved zones.</p>
                <?php else: ?>
                    <?php foreach ($zones as $zone): ?>
                        <div class="border rounded-lg p-4 flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($zone['zone_name']) ?></p>
                                <p class="text-sm text-gray-500">Radius: <?= htmlspecialchars($zone['radius_km']) ?> km</p>
                            </div>
                            <form action="actions/zone_handler.php" method="POST">
                                <input type="hidden" name="action" value="delete_zone">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700" title="Delete Zone">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let map, marker, circle;
const latInput = document.getElementById('latitudeInput');
const lonInput = document.getElementById('longitudeInput');
const radiusSlider = document.getElementById('radius_km');
const radiusDisplay = document.getElementById('radius-display');

window.initZoneMap = function() {
    const initialPosition = { lat: 23.8103, lng: 90.4125 }; // Default to Dhaka

    map = new google.maps.Map(document.getElementById('zoneMap'), {
        center: initialPosition,
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false,
    });

    map.addListener('click', (e) => {
        updateMarkerAndCircle(e.latLng.toJSON());
    });

    radiusSlider.addEventListener('input', () => {
        const newRadius = parseFloat(radiusSlider.value);
        radiusDisplay.textContent = newRadius;
        if (circle) {
            circle.setRadius(newRadius * 1000); // Radius in meters
        }
    });
};

function updateMarkerAndCircle(position) {
    // Update hidden form inputs
    latInput.value = position.lat.toFixed(8);
    lonInput.value = position.lng.toFixed(8);

    if (marker) {
        marker.setPosition(position);
    } else {
        marker = new google.maps.Marker({
            position: position,
            map: map,
            draggable: true,
        });
        marker.addListener('dragend', (e) => {
            updateMarkerAndCircle(e.latLng.toJSON());
        });
    }

    const radius = parseFloat(radiusSlider.value) * 1000; // Convert km to meters
    if (circle) {
        circle.setCenter(position);
        circle.setRadius(radius);
    } else {
        circle = new google.maps.Circle({
            strokeColor: '#FF0000',
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: '#FF0000',
            fillOpacity: 0.25,
            map: map,
            center: position,
            radius: radius,
        });
    }
    map.panTo(position);
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M&callback=initZoneMap" async defer></script>

<?php
require_once 'includes/footer.php';
?>
