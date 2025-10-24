<?php
require_once '../api/db_connect.php';

$sql = "
    SELECT 
        b.id, 
        u.first_name, 
        u.last_name, 
        b.package_name, 
        b.preferred_date, 
        b.preferred_time, 
        b.status
    FROM bookings AS b
    JOIN users AS u ON b.user_id = u.id
    WHERE 
        b.status = 'approved'
        AND (
            b.preferred_date > CURDATE()
            OR (b.preferred_date = CURDATE() AND b.preferred_time > CURTIME())
        )
    ORDER BY b.preferred_date ASC, b.preferred_time ASC
    LIMIT 5
";

$result = $conn->query($sql);
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

header('Content-Type: application/json');
echo json_encode($bookings);
