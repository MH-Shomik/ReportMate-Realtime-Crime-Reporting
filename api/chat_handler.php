<?php
// Set content type to JSON
header('Content-Type: application/json');

// Bootstrap the application
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure the user is logged in before proceeding
if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required.']);
    http_response_code(401);
    exit();
}

$action = $_GET['action'] ?? '';
$current_user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_messages':
            get_messages($pdo, $current_user_id);
            break;

        case 'send_message':
            send_message($pdo, $current_user_id);
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Fetches the conversation between the current user and another user.
 */
function get_messages($pdo, $current_user_id) {
    $other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($other_user_id === 0) {
        throw new Exception('User ID is required.');
    }

    // SQL to fetch messages between the two users
    $stmt = $pdo->prepare("
        SELECT id, sender_id, receiver_id, message_text, created_at 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as read
    $stmt_mark_read = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt_mark_read->execute([$other_user_id, $current_user_id]);

    echo json_encode($messages);
}

/**
 * Inserts a new message into the database.
 */
function send_message($pdo, $current_user_id) {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

    if ($receiver_id === 0 || empty($message_text)) {
        throw new Exception('Receiver ID and message text cannot be empty.');
    }
    
    // You might want to add a check here to ensure the receiver_id exists and is not the current user

    $stmt = $pdo->prepare(
        "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)"
    );
    $stmt->execute([$current_user_id, $receiver_id, $message_text]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    } else {
        throw new Exception('Failed to send message.');
    }
}