<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login();

$page_title = "Report Crime | CrimeAlert";

// Initialize variables
$error = '';
$success = '';
$latitude = '';
$longitude = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token");
        }

        // Get form data
        $crime_type = sanitize($_POST['crime_type']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $emergency_level = intval($_POST['emergency_level']);
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);

        // Validate inputs
        if (empty($title) || empty($description) || empty($crime_type)) {
            throw new Exception("All required fields must be filled");
        }

        if (strlen($title) > 100) {
            throw new Exception("Title must be less than 100 characters");
        }

        // Handle file uploads
        $uploaded_images = [];
        if (!empty($_FILES['crime_images']['name'][0])) {
            $upload_dir = 'uploads/crime_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['crime_images']['tmp_name'] as $key => $tmp_name) {
                $file_name = sanitize($_FILES['crime_images']['name'][$key]);
                $file_size = $_FILES['crime_images']['size'][$key];
                $file_tmp = $_FILES['crime_images']['tmp_name'][$key];
                $file_type = $_FILES['crime_images']['type'][$key];
                
                // Validate image
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Invalid file type for image: $file_name");
                }

                if ($file_size > 5 * 1024 * 1024) { // 5MB
                    throw new Exception("Image too large: $file_name (max 5MB)");
                }

                $new_filename = uniqid('crime_image_', true) . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_images[] = $destination;
                } else {
                    throw new Exception("Failed to upload image: $file_name");
                }
            }
        }

        // Insert report into database
        $stmt = $pdo->prepare("INSERT INTO crime_reports 
            (user_id, title, description, crime_type, emergency_level, is_anonymous, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $_SESSION['user_id'],
            $title,
            $description,
            $crime_type,
            $emergency_level,
            $is_anonymous,
            $latitude,
            $longitude
        ]);

        $report_id = $pdo->lastInsertId();

        // Insert images
        foreach ($uploaded_images as $image_path) {
            $stmt = $pdo->prepare("INSERT INTO crime_images (crime_id, image_path) VALUES (?, ?)");
            $stmt->execute([$report_id, $image_path]);
        }

        // --- BEGIN: Notify Nearby Users ---
        
        // Define the radius (e.g., 10 kilometers) for notifications
        $notification_radius_km = 10;
        
        // Get the ID of the user who is reporting the crime
        $reporter_id = $_SESSION['user_id'];
        
        // Fetch users within the defined radius, excluding the person who made the report
        $nearby_users = get_nearby_users($pdo, $latitude, $longitude, $notification_radius_km, $reporter_id);
        
        // Prepare the crime data for the email
        $crime_data = [
            'title' => $title,
            'description' => $description,
            'crime_type' => $crime_type
        ];
        
        // Loop through the nearby users and send them an email notification
        foreach ($nearby_users as $user) {
            send_crime_notification_email($user['email'], $user['username'], $crime_data);
        }
        
        // --- END: Notify Nearby Users ---

        // +++ ADD THIS NEW BLOCK OF CODE +++
        // --- BEGIN: Notify Users in Alert Zones ---
        $zone_users = get_users_in_alert_zones($pdo, $latitude, $longitude, $reporter_id);
        // --- END: Notify Users in Alert Zones ---


        // --- Combine and Send Notifications ---
        $all_notifiable_users = array_merge($nearby_users, $zone_users);

        // Remove duplicates to ensure a user doesn't get two emails for the same report
        $unique_users = [];
        foreach ($all_notifiable_users as $user) {
            $unique_users[$user['email']] = $user;
        }

        // Loop through the unique users and send them an email notification
        foreach ($unique_users as $user) {
            send_crime_notification_email($user['email'], $user['username'], $crime_data); //
        }
        // +++ END OF NEW CODE +++


        // The success message should be updated to reflect this
        $success = "Crime report submitted successfully! Nearby users and those with matching alert zones have been notified.";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

}



require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Report a Crime Incident</h2>
    
    <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="reportForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Crime Type -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Crime Type *</label>
            <select name="crime_type" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Select Crime Type</option>
                <option value="theft">Theft</option>
                <option value="assault">Assault</option>
                <option value="burglary">Burglary</option>
                <option value="vandalism">Vandalism</option>
                <option value="other">Other</option>
            </select>
        </div>

        <!-- Title -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
            <input type="text" name="title" maxlength="100" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Brief incident title">
            <div class="text-sm text-gray-500 mt-1" id="titleCounter">0/100 characters</div>
        </div>

        <!-- Description -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
            <textarea name="description" id="description" rows="6" required
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Detailed description of the incident"></textarea>
            <div class="text-sm text-gray-500 mt-1" id="descCounter">0 words</div>
        </div>

        <!-- Location Picker -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Incident Location *</label>
            <div class="h-64 w-full mb-4 border rounded-lg" id="map"></div>
            <div class="grid grid-cols-2 gap-4">
                <input type="number" step="any" name="latitude" required
                    class="w-full px-4 py-2 border rounded-lg"
                    placeholder="Latitude" id="latitudeInput">
                <input type="number" step="any" name="longitude" required
                    class="w-full px-4 py-2 border rounded-lg"
                    placeholder="Longitude" id="longitudeInput">
            </div>
            <button type="button" onclick="getCurrentLocation()"
                class="mt-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                <i class="fas fa-location-arrow mr-2"></i>Use Current Location
            </button>
        </div>

        <!-- Image Upload -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Images (Max 5)</label>
            <div class="flex items-center justify-center w-full">
                <label class="flex flex-col w-full border-2 border-dashed rounded-lg h-32 hover:border-gray-400">
                    <div class="flex flex-col items-center justify-center pt-7">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl"></i>
                        <p class="text-sm text-gray-500 mt-2">Click to select images</p>
                    </div>
                    <input type="file" name="crime_images[]" multiple accept="image/*" 
                        class="opacity-0" id="imageUpload">
                </label>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-4" id="imagePreviews"></div>
        </div>

        <!-- Emergency Level -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Level</label>
            <div class="flex items-center space-x-4">
                <input type="range" name="emergency_level" min="1" max="5" value="3"
                    class="w-full range-slider" id="emergencySlider">
                <span class="text-lg font-bold" id="emergencyValue">3</span>
            </div>
            <div class="flex justify-between text-sm text-gray-500 mt-1">
                <span>Low</span>
                <span>High</span>
            </div>
        </div>

        <!-- Anonymous Reporting -->
        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_anonymous" class="h-4 w-4 text-indigo-600">
                <span class="ml-2 text-sm text-gray-700">Report anonymously</span>
            </label>
        </div>

        <button type="submit" 
            class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg hover:bg-indigo-700 transition">
            Submit Report
        </button>
    </form>
</div>

<script>
// Initialize Map
let map;
let marker;

function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: {lat: -34.397, lng: 150.644},
        zoom: 8
    });

    // Add click listener
    map.addListener('click', (e) => {
        updateMarker(e.latLng);
    });
}

function updateMarker(latLng) {
    if (marker) marker.setMap(null);
    
    marker = new google.maps.Marker({
        position: latLng,
        map: map,
        draggable: true
    });

    updateCoordinates(latLng.lat(), latLng.lng());
}

function updateCoordinates(lat, lng) {
    document.getElementById('latitudeInput').value = lat.toFixed(6);
    document.getElementById('longitudeInput').value = lng.toFixed(6);
}

function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                // Create a Google Maps LatLng object
                const pos = new google.maps.LatLng(lat, lng);
                
                // Center the map on the current location
                map.setCenter(pos);
                map.setZoom(15); // Zoom in closer for better precision
                
                // Update marker and coordinates
                updateMarker(pos);
            },
            error => {
                // Handle geolocation errors
                console.error('Error getting location:', error);
                alert('Unable to get your current location. Please check your browser settings and ensure location access is enabled.');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    } else {
        alert('Geolocation is not supported by this browser.');
    }
}

// Image Preview Handling
document.getElementById('imageUpload').addEventListener('change', function(e) {
    const previewContainer = document.getElementById('imagePreviews');
    previewContainer.innerHTML = '';
    
    Array.from(e.target.files).slice(0, 5).forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'relative';
            div.innerHTML = `
                <img src="${e.target.result}" class="h-24 w-full object-cover rounded">
                <button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs">
                    ×
                </button>
            `;
            div.querySelector('button').addEventListener('click', () => {
                div.remove();
            });
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});

// Real-time Validation
document.querySelector('input[name="title"]').addEventListener('input', function(e) {
    document.getElementById('titleCounter').textContent = `${e.target.value.length}/100 characters`;
});

document.querySelector('textarea[name="description"]').addEventListener('input', function(e) {
    const wordCount = e.target.value.trim().split(/\s+/).length;
    document.getElementById('descCounter').textContent = `${wordCount} words`;
});

// Emergency Level Display
document.getElementById('emergencySlider').addEventListener('input', function(e) {
    document.getElementById('emergencyValue').textContent = e.target.value;
});

// Load Google Maps
function loadGoogleMaps() {
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M&callback=initMap`;
    script.async = true;
    document.head.appendChild(script);
}

window.onload = loadGoogleMaps;
</script>

<script>
// This part seems to have an error in the original file, as #description is a textarea.
// If you intend to use a rich text editor, you should replace the textarea with a div.
// For now, I'm commenting it out to prevent potential errors.
/*
const quill = new Quill('#description', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['clean']
        ]
    }
});
*/
</script>

<?php
require_once 'includes/footer.php';
?>
