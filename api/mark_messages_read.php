<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;

if (!$user_id || !$admin_id) {
    echo json_encode(['success' => false, 'error' => 'missing_params']);
    exit;
}

$query = "UPDATE messages 
          SET is_read = 1 
          WHERE sender_id = ? AND receiver_id = ? AND sender_type = 'user' AND is_read = 0";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param("ii", $user_id, $admin_id);
$stmt->execute();
$updated = $stmt->affected_rows;
$stmt->close();

echo json_encode(['success' => true, 'updated' => $updated]);
?>