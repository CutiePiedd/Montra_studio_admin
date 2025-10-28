<?php
session_start();
require_once '../api/db_connect.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

// Search users and bookings
$sql = "
    SELECT 'User' AS type, CONCAT(u.first_name, ' ', u.last_name) AS label, 'User' AS sub, 'user_management.php' AS link
    FROM users AS u
    WHERE u.first_name LIKE ? OR u.last_name LIKE ?
    UNION
    SELECT 'Booking' AS type, b.package_name AS label, CONCAT('Date: ', b.preferred_date) AS sub, 'admin_bookings.php' AS link
    FROM bookings AS b
    WHERE b.package_name LIKE ?
    LIMIT 10
";

$searchTerm = "%$query%";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
