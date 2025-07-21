<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = "My Profile | CrimeAlert";
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// --- Fetch Current User Data ---
try {
    $stmt = $pdo->prepare("SELECT username, email, phone, latitude, longitude, notify_email_comment, notify_email_status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // This should not happen for a logged-in user, but as a safeguard
        throw new Exception("User not found.");
    }
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    // Redirect to dashboard with an error if user data cannot be fetched
    $_SESSION['error_message'] = "Could not load your profile. Please try again.";
    redirect('dashboard.php');
}


// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid or expired form submission. Please try again.';
    } else {
        // Determine which form was submitted
        $action = $_POST['action'] ?? '';

        try {
            // --- Action: Update Profile Details ---
            if ($action === 'update_profile') {
                $username = sanitize($_POST['username']);
                $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                $phone = sanitize($_POST['phone']);

                // Validation
                if (empty($username) || empty($email) || empty($phone)) {
                    throw new Exception("Username, email, and phone cannot be empty.");
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format.");
                }

                // Check if username or email is taken by another user
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt_check->execute([$username, $email, $user_id]);
                if ($stmt_check->fetch()) {
                    throw new Exception("Username or email is already in use by another account.");
                }

                // Update database
                $stmt_update = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?");
                $stmt_update->execute([$username, $email, $phone, $user_id]);
                
                // Update session username
                $_SESSION['username'] = $username;
                $user['username'] = $username;
                $user['email'] = $email;
                $user['phone'] = $phone;

                $success_message = 'Your profile details have been updated successfully!';
            }

            // --- Action: Change Password ---
            if ($action === 'change_password') {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception("All password fields are required.");
                }
                
                // Fetch current password hash to verify
                $stmt_pass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt_pass->execute([$user_id]);
                $current_hash = $stmt_pass->fetchColumn();

                if (!password_verify($current_password, $current_hash)) {
                    throw new Exception("Your current password is not correct.");
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long.");
                }

                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_pass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update_pass->execute([$new_hash, $user_id]);

                $success_message = 'Your password has been changed successfully!';
            }

            // --- Action: Update Location ---
            if ($action === 'update_location') {
                $latitude = filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
                $longitude = filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);

                if ($latitude === false || $longitude === false) {
                    throw new Exception("Invalid location coordinates provided.");
                }
                
                $stmt_loc = $pdo->prepare("UPDATE users SET latitude = ?, longitude = ? WHERE id = ?");
                $stmt_loc->execute([$latitude, $longitude, $user_id]);

                $user['latitude'] = $latitude;
                $user['longitude'] = $longitude;
                $success_message = 'Your default location has been updated!';
            }

            // --- Action: Update Notifications ---
            if ($action === 'update_notifications') {
                $notify_comment = isset($_POST['notify_comment']) ? 1 : 0;
                $notify_status = isset($_POST['notify_status']) ? 1 : 0;

                $stmt_notif = $pdo->prepare("UPDATE users SET notify_email_comment = ?, notify_email_status = ? WHERE id = ?");
                $stmt_notif->execute([$notify_comment, $notify_status, $user_id]);

                $user['notify_email_comment'] = $notify_comment;
                $user['notify_email_status'] = $notify_status;
                $success_message = 'Notification preferences saved!';
            }

        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}


require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Your Profile</h1>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Profile & Password -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Profile Details Form -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Personal Information</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition">Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Change Password</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8" class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-gray-700 text-white px-5 py-2 rounded-lg hover:bg-gray-800 transition">Update Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Location & Notifications -->
        <div class="space-y-8">
            <!-- Location Form -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Your Default Location</h2>
                <p class="text-sm text-gray-600 mb-4">Set your home address to get more relevant alerts. Click the map to set your location.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_location">
                    <input type="hidden" name="latitude" id="latitudeInput" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                    <input type="hidden" name="longitude" id="longitudeInput" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
                    
                    <div id="locationMap" class="h-64 w-full rounded-lg bg-gray-200 mb-4"></div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition">Save Location</button>
                </form>
            </div>

            <!-- Notification Settings Form -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Email Notifications</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_notifications">
                    <label class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition">
                        <input type="checkbox" name="notify_comment" class="h-5 w-5 text-indigo-600 rounded" <?= $user['notify_email_comment'] ? 'checked' : '' ?>>
                        <span class="ml-3 text-sm text-gray-700">Notify me on new comments on my reports</span>
                    </label>
                    <label class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition">
                        <input type="checkbox" name="notify_status" class="h-5 w-5 text-indigo-600 rounded" <?= $user['notify_email_status'] ? 'checked' : '' ?>>
                        <span class="ml-3 text-sm text-gray-700">Notify me when my report status changes</span>
                    </label>
                    <div class="text-right pt-2">
                        <button type="submit" class="bg-teal-600 text-white px-5 py-2 rounded-lg hover:bg-teal-700 transition">Save Preferences</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Google Maps Script -->
<script>
let map;
let marker;
const latInput = document.getElementById('latitudeInput');
const lonInput = document.getElementById('longitudeInput');

window.initProfileMap = function() {
    // Use saved coordinates if available, otherwise default to a central location
    const initialLat = parseFloat(latInput.value) || 23.8103;
    const initialLng = parseFloat(lonInput.value) || 90.4125;
    const initialPosition = { lat: initialLat, lng: initialLng };

    map = new google.maps.Map(document.getElementById('locationMap'), {
        center: initialPosition,
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
    });

    // Place an initial marker if location is already set
    if (latInput.value && lonInput.value) {
        placeMarker(initialPosition);
    }
    
    // Add a click listener to the map
    map.addListener('click', (e) => {
        placeMarker(e.latLng.toJSON());
    });
};

function placeMarker(position) {
    if (marker) {
        marker.setMap(null); // Remove existing marker
    }
    
    marker = new google.maps.Marker({
        position: position,
        map: map,
        draggable: true,
        title: "Your default location"
    });

    updateCoordinates(position);

    // Update coordinates if marker is dragged
    marker.addListener('dragend', (e) => {
        updateCoordinates(e.latLng.toJSON());
    });
}

function updateCoordinates(position) {
    latInput.value = position.lat.toFixed(8);
    lonInput.value = position.lng.toFixed(8);
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M&callback=initProfileMap" async defer></script>


<?php
require_once 'includes/footer.php';
?>
