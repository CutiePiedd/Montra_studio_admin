<?php
session_start();
require_once '../api/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$admin_id = $_SESSION['admin_id'];

$query = "SELECT id, message, status, created_at 
          FROM notifications 
          WHERE admin_id = ? 
          ORDER BY created_at DESC 
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode($notifications);
