<?php
require_once 'db_connect.php';

$user_id = $_GET['user_id'];
$admin_id = $_GET['admin_id'];

$query = "UPDATE messages 
          SET is_read = 1 
          WHERE sender_id = ? AND receiver_id = ? AND sender_type = 'user'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $admin_id);
$stmt->execute();

echo json_encode(['success' => true]);
?>
