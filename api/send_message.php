<?php
require_once 'db_connect.php';

$sender_id   = $_POST['sender_id'];
$receiver_id = $_POST['receiver_id'];
$sender_type = $_POST['sender_type'];
$message     = $_POST['message'];

if (empty($sender_id) || empty($receiver_id) || empty($sender_type) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Insert the chat message
$sql = "INSERT INTO messages (sender_id, receiver_id, sender_type, message, sent_at, is_read) VALUES (?, ?, ?, ?, NOW(), 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $sender_id, $receiver_id, $sender_type, $message);

if ($stmt->execute()) {
    // Create a notification depending on who sent the message

    if ($sender_type === 'admin') {
        // Notify user
        $notif_msg = "Admin sent you a new message.";
        $notif = $conn->prepare("INSERT INTO notifications_user (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        $notif->bind_param("is", $receiver_id, $notif_msg);
        $notif->execute();
    }

    if ($sender_type === 'user') {
        // Notify admin
        $notif_msg = "You have a new message from a user.";
        $notif = $conn->prepare("INSERT INTO notifications_admin (admin_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        $notif->bind_param("is", $receiver_id, $notif_msg);
        $notif->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
?>
