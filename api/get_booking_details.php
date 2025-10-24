<?php
require_once '../api/db_connect.php';

$booking_id = $_GET['booking_id'] ?? 0;
$booking_id = intval($booking_id);

$response = ['success' => false];

if ($booking_id > 0) {
    $sql = "SELECT b.package_name, b.preferred_date, b.preferred_time, 
                   b.contact_person, b.email, b.phone, b.special_request, 
                   b.addons, b.total_price, u.first_name, u.last_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
        $response = array_merge($response, $row);
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
