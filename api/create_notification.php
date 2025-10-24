<?php
require_once 'db_connect.php';
session_start();

if (!isset($_POST['booking_id'])) {
    die("Booking ID not provided.");
}

$booking_id = intval($_POST['booking_id']);

// Fetch booking details from bookings table
$sql = "SELECT user_id, package_name, preferred_date 
        FROM bookings 
        WHERE id = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No booking found with that ID.");
}

$booking = $result->fetch_assoc();
$stmt->close();

// Fetch user's full name from users table
$user_id = $booking['user_id'];
$userSql = "SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1";
$userStmt = $conn->prepare($userSql);
if (!$userStmt) {
    die("Prepare failed (user query): " . $conn->error);
}
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

$userFullName = $user ? $user['first_name'] . ' ' . $user['last_name'] : "Unknown User";

$packageName = $booking['package_name'];
$bookingDate = date('F j, Y', strtotime($booking['preferred_date']));
$message = "📸 $userFullName booked the $packageName package for $bookingDate.";

// Insert into notifications table
$admin_id = $_SESSION['admin_id'] ?? 1; // fallback for testing
$notif = $conn->prepare("INSERT INTO notifications (admin_id, message, booking_id) VALUES (?, ?, ?)");
if (!$notif) {
    die("Prepare failed: " . $conn->error);
}
$notif->bind_param("isi", $admin_id, $message, $booking_id);

if ($notif->execute()) {
    echo "✅ Notification added: $message";
} else {
    echo "❌ Failed to insert notification: " . $notif->error;
}
$notif->close();
$conn->close();
?>
