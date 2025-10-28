<?php
require_once 'db_connect.php';

$user_id = $_GET['user_id'];
$admin_id = $_GET['admin_id'];

$query = "SELECT * FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
          OR (sender_id = ? AND receiver_id = ?)
          ORDER BY sent_at ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
  $messages[] = $row;
}

header('Content-Type: application/json');
echo json_encode($messages);
?>
