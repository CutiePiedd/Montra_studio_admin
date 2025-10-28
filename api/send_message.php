<?php
require_once 'db_connect.php';

$sender_id = $_POST['sender_id'];
$receiver_id = $_POST['receiver_id'];
$sender_type = $_POST['sender_type'];
$message = $_POST['message'];

if (empty($sender_id) || empty($receiver_id) || empty($sender_type) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$sql = "INSERT INTO messages (sender_id, receiver_id, sender_type, message) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $sender_id, $receiver_id, $sender_type, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
?>
