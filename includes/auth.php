<?php
// includes/auth.php

require_once 'db.php';
require_once 'functions.php';

/**
 * Register a new user
 */
function register_user($username, $email, $phone, $password, $latitude, $longitude) {
    global $pdo;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, phone, password, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)"
    );
    
    try {
        $stmt->execute([$username, $email, $phone, $hashed_password, $latitude, $longitude]);
        return ['success' => true];
    } catch (PDOException $e) {
        // In a real app, you would log the detailed error, not expose it to the user.
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verify user account
 */
function verify_user($email, $verification_code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET verified = 1, verification_code = NULL WHERE email = ? AND verification_code = ?");
        $stmt->execute([$email, $verification_code]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Verification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Login user
 */
function login_user($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID
 */
function get_user_by_id($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, phone, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to get user: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if username or email exists
 */
function user_exists($username, $email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("User check failed: " . $e->getMessage());
        return false;
    }
}
?>