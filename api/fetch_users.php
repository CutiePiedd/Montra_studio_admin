<?php
include('db_connect.php');

$query = "SELECT id, first_name, last_name, email, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($query);

$users = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode($users);
$conn->close();
?>
