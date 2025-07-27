<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$page_title = "Register | CrimeAlert";

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Get latitude and longitude from the form
    $latitude = isset($_POST['latitude']) && !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;


    // Validate inputs
    if (empty($username) || empty($email) || empty($phone) || empty($password) || empty($latitude) || empty($longitude)) {
        $error = 'All fields and location are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (user_exists($username, $email)) {
        $error = 'Username or email already exists';
    } else {
        // Pass location data to the registration function
        $result = register_user($username, $email, $phone, $password, $latitude, $longitude);
        
        if ($result['success']) {
            // Auto-login after registration
            if (login_user($username, $password)) {
                $_SESSION['success_message'] = 'Registration successful! Welcome to CrimeAlert.';
                redirect('dashboard.php');
            } else {
                $error = 'Registration successful but login failed. Please try logging in.';
            }
        } else {
            $error = 'Registration failed: ' . $result['error'];
        }
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto my-10 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-indigo-600 py-4 px-6">
        <h2 class="text-2xl font-bold text-white">Create Account</h2>
        <p class="text-indigo-200">Join our community safety network</p>
    </div>
    
    <div class="p-6">
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div id="location-status" class="mb-4 p-3 bg-blue-100 border border-blue-400 text-blue-700 rounded hidden"></div>
        
        <form method="POST" class="space-y-4">
            
            <input type="hidden" name="latitude" id="latitudeInput">
            <input type="hidden" name="longitude" id="longitudeInput">

            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" id="username" name="username" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="tel" id="phone" name="phone" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required minlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            </div>

            <div>
                <button type="button" id="getLocationBtn" class="w-full bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-map-marker-alt mr-2"></i>Use My Location (Required)
                </button>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" id="terms" name="terms" required
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="terms" class="ml-2 block text-sm text-gray-700">
                    I agree to the <a href="#" class="text-indigo-600 hover:underline">Terms and Conditions</a>
                </label>
            </div>
            
            <button type="submit" id="registerBtn" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:bg-indigo-300 disabled:cursor-not-allowed" disabled>
                Register
            </button>
        </form>
        
        <div class="mt-4 text-center">
            <p class="text-sm text-gray-600">
                Already have an account? 
                <a href="login.php" class="text-indigo-600 font-medium hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const latInput = document.getElementById('latitudeInput');
    const lonInput = document.getElementById('longitudeInput');
    const statusDiv = document.getElementById('location-status');
    const getLocationBtn = document.getElementById('getLocationBtn');
    const registerBtn = document.getElementById('registerBtn');

    getLocationBtn.addEventListener('click', () => {
        if (navigator.geolocation) {
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Fetching your location...';
            statusDiv.className = 'mb-4 p-3 bg-blue-100 border border-blue-400 text-blue-700 rounded'; // Reset and show

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    latInput.value = lat.toFixed(8);
                    lonInput.value = lon.toFixed(8);

                    // Update status message to success
                    statusDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Location captured successfully!';
                    statusDiv.className = 'mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded';
                    
                    // Enable the register button and disable the location button
                    registerBtn.disabled = false;
                    getLocationBtn.disabled = true;
                    getLocationBtn.classList.add('disabled:bg-gray-400', 'disabled:cursor-not-allowed');

                },
                (error) => {
                    console.error(`Geolocation error: ${error.message}`);
                    // Update status message to error
                    statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Could not get location. Please allow location access to register.';
                    statusDiv.className = 'mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
                    
                    // Keep the register button disabled
                    registerBtn.disabled = true;
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            console.error("Geolocation is not supported by this browser.");
            statusDiv.innerHTML = 'Location services are not supported by your browser.';
            statusDiv.className = 'mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded';
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
