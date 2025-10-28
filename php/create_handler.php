<?php
require_once '../api/db_connect.php';

$query = $_GET['q'] ?? '';
$results = [];

if ($query) {
    // Search across users, bookings, and packages
    $sql = "
        SELECT 'User' AS type, CONCAT(first_name, ' ', last_name) AS name
        FROM users
        WHERE first_name LIKE ? OR last_name LIKE ?
        UNION
        SELECT 'Booking' AS type, package_name AS name
        FROM bookings
        WHERE package_name LIKE ?
        UNION
        SELECT 'Package' AS type, package_name AS name
        FROM packages
        WHERE package_name LIKE ?
        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);
    $like = '%' . $query . '%';
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($results);
