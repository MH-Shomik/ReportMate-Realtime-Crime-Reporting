<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$page_title = "Login | CrimeAlert";

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Attempt login
        if (login_user($username, $password)) {
            // Set longer session if "Remember me" is checked
            if ($remember) {
                $_SESSION['remember'] = true;
                session_set_cookie_params(86400 * 30); // 30 days
                session_regenerate_id(true);
            }
            // --> ADD THIS LINE <--
            $_SESSION['show_report_crime_popup'] = true;
            
            $_SESSION['success_message'] = 'Welcome back, ' . htmlspecialchars($_SESSION['username']) . '!';
            redirect('dashboard.php');
        } else {
            $error = 'Invalid username or password';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto my-10 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-indigo-600 py-4 px-6">
        <h2 class="text-2xl font-bold text-white">Welcome Back</h2>
        <p class="text-indigo-200">Sign in to your CrimeAlert account</p>
    </div>
    
    <div class="p-6">
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
                <input type="text" id="username" name="username" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <button type="button" onclick="togglePasswordVisibility()" 
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-indigo-600">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Remember me
                    </label>
                </div>
                
                <div>
                    <a href="forgot-password.php" class="text-sm text-indigo-600 hover:underline">Forgot password?</a>
                </div>
            </div>
            
            <button type="submit" 
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Sign In
            </button>
        </form>
        
        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">Or continue with</span>
                </div>
            </div>
            
            <div class="mt-4 grid grid-cols-2 gap-3">
                <button type="button" 
                    class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fab fa-google text-red-500 mr-2"></i> Google
                </button>
                
                <button type="button" 
                    class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fab fa-facebook text-blue-600 mr-2"></i> Facebook
                </button>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <p class="text-sm text-gray-600">
                Don't have an account? 
                <a href="register.php" class="text-indigo-600 font-medium hover:underline">Sign up</a>
            </p>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordField = document.getElementById('password');
    const eyeIcon = document.querySelector('#password + button i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>