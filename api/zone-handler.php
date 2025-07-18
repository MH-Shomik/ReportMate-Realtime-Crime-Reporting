<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../my-zones.php');
}

if (!validate_csrf_token($_POST['csrf_token'])) {
    die('Invalid CSRF token.');
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'add_zone') {
        $zone_name = sanitize($_POST['zone_name']);
        $latitude = filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);
        $radius_km = filter_var($_POST['radius_km'], FILTER_VALIDATE_FLOAT);

        if (empty($zone_name) || $latitude === false || $longitude === false || $radius_km === false) {
            throw new Exception("All fields are required and must be valid.");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO alert_zones (user_id, zone_name, latitude, longitude, radius_km) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $zone_name, $latitude, $longitude, $radius_km]);
        flash('zone_feedback', 'Safety zone created successfully!', 'p-3 bg-green-100 border border-green-400 text-green-700 rounded mb-4');

    } elseif ($action === 'delete_zone') {
        $zone_id = filter_var($_POST['zone_id'], FILTER_VALIDATE_INT);
        if ($zone_id === false) {
            throw new Exception("Invalid zone ID.");
        }

        // Ensure the user owns the zone before deleting
        $stmt = $pdo->prepare("DELETE FROM alert_zones WHERE id = ? AND user_id = ?");
        $stmt->execute([$zone_id, $user_id]);

        if ($stmt->rowCount() > 0) {
            flash('zone_feedback', 'Safety zone deleted.', 'p-3 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded mb-4');
        } else {
            throw new Exception("Could not delete zone or permission denied.");
        }
    }
} catch (Exception $e) {
    flash('zone_feedback', 'An error occurred: ' . $e->getMessage(), 'p-3 bg-red-100 border border-red-400 text-red-700 rounded mb-4');
}

redirect('../my-zones.php');