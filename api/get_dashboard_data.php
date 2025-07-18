<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For time_ago function

// This API endpoint provides data for the dashboard components

if (isset($_GET['feed'])) {
    try {
        $stmt = $pdo->query(
            "SELECT title, created_at 
             FROM crime_reports 
             ORDER BY created_at DESC 
             LIMIT 7"
        );
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [];
        foreach ($activities as $activity) {
            $response[] = [
                'title' => htmlspecialchars($activity['title']),
                // You will need to create this 'time_ago' function
                'time_ago' => time_ago($activity['created_at']) 
            ];
        }

        echo json_encode($response);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not fetch activity feed.']);
        error_log("API Feed Error: " . $e->getMessage());
    }
    exit;
}

// You can add more blocks here for other data, e.g., if (isset($_GET['stats'])) { ... }

// Default response if no valid parameter is provided
http_response_code(400);
echo json_encode(['error' => 'Invalid request to dashboard API.']);

?>
