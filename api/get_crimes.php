<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    // Base query
    $sql = "SELECT id, title, description, crime_type, created_at, latitude, longitude 
            FROM crime_reports 
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL";

    $params = [];
    
    // Filter by crime type
    if (!empty($_GET['crime_type'])) {
        $sql .= " AND crime_type = ?";
        $params[] = $_GET['crime_type'];
    }

    // Filter by date range
    if (!empty($_GET['date_range'])) {
        $date_range = $_GET['date_range'];
        $interval = '';
        switch ($date_range) {
            case '24h':
                $interval = '1 DAY';
                break;
            case '7d':
                $interval = '7 DAY';
                break;
            case '30d':
                $interval = '30 DAY';
                break;
        }
        
        if ($interval) {
            $sql .= " AND created_at >= NOW() - INTERVAL " . $interval;
        }
    }

    // Order by most recent
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $crimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($crimes);

} catch (PDOException $e) {
    // Log the error and return an empty array or an error message
    error_log("API Error getting crimes: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Could not retrieve crime data.']);
}
?>
