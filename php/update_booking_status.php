<?php
session_start();
require_once '../api/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_bookings.php");
    exit();
}

$booking_id = intval($_POST['booking_id']);
$action = $_POST['action'] ?? '';

if (!in_array($action, ['approve','reject'])) {
    header("Location: admin_bookings.php?error=invalid_action");
    exit();
}

if ($action === 'reject') {
    $stmt = $conn->prepare("UPDATE bookings SET status='rejected' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    // 📨 Create user notification for rejection
    $stmtUserNotif = $conn->prepare("
        SELECT user_id FROM bookings WHERE id = ? LIMIT 1
    ");
    $stmtUserNotif->bind_param("i", $booking_id);
    $stmtUserNotif->execute();
    $resUser = $stmtUserNotif->get_result();

    if ($resUser->num_rows === 1) {
        $userRow = $resUser->fetch_assoc();
        $user_id = $userRow['user_id'];
        $notifMsg = "❌ Your booking request has been rejected.";
        $stmtInsertNotif = $conn->prepare("INSERT INTO notifications_user (user_id, message) VALUES (?, ?)");
        $stmtInsertNotif->bind_param("is", $user_id, $notifMsg);
        $stmtInsertNotif->execute();
        $stmtInsertNotif->close();
    }
    $stmtUserNotif->close();

    header("Location: admin_bookings.php?msg=rejected");
    exit();
}

// For approve: first fetch the booking details
$stmt = $conn->prepare("SELECT preferred_date, preferred_time, addons FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    $stmt->close();
    header("Location: admin_bookings.php?error=notfound");
    exit();
}
$row = $res->fetch_assoc();
$stmt->close();

$date = $row['preferred_date'];
$time = substr($row['preferred_time'], 0, 5); // 'HH:MM'
$addons = $row['addons'] ?? '';

// build list of time slots this booking will occupy
function slotToMinutes($hms) {
    $parts = explode(':', substr($hms,0,5));
    return intval($parts[0]) * 60 + intval($parts[1]);
}
$startMin = slotToMinutes($time);
$occupiedSlots = [$startMin];
if (strpos($addons, 'extended_time') !== false) {
    $occupiedSlots[] = $startMin + 30;
}

// Now fetch all APPROVED bookings on same date and see if any occupied slot intersects
$sql = "SELECT preferred_time, addons FROM bookings WHERE preferred_date = ? AND status = 'approved'";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $date);
$stmt2->execute();
$res2 = $stmt2->get_result();

$conflict = false;
while ($r = $res2->fetch_assoc()) {
    $t = substr($r['preferred_time'],0,5);
    $sMin = slotToMinutes($t);
    $occupied = [$sMin];
    if (strpos($r['addons'] ?? '', 'extended_time') !== false) {
        $occupied[] = $sMin + 30;
    }
    foreach ($occupiedSlots as $slot) {
        if (in_array($slot, $occupied)) {
            $conflict = true;
            break 2;
        }
    }
}
$stmt2->close();

if ($conflict) {
    header("Location: admin_bookings.php?error=conflict");
    exit();
}

// no conflict: approve
$stmt3 = $conn->prepare("UPDATE bookings SET status='approved' WHERE id = ?");
$stmt3->bind_param("i", $booking_id);
$stmt3->execute();
$stmt3->close();

// ✅ Create admin notification automatically
require_once '../api/db_connect.php';

// Fetch booking info and corresponding user name
$bookingQuery = "
    SELECT b.package_name, b.preferred_date, u.first_name, u.last_name, u.id AS user_id
    FROM bookings AS b
    JOIN users AS u ON b.user_id = u.id
    WHERE b.id = ? 
    LIMIT 1
";
$bookingStmt = $conn->prepare($bookingQuery);
$bookingStmt->bind_param("i", $booking_id);
$bookingStmt->execute();
$result = $bookingStmt->get_result();
$booking = $result->fetch_assoc();
$bookingStmt->close();

if ($booking) {
    $userFullName = $booking['first_name'] . ' ' . $booking['last_name'];
    $packageName = $booking['package_name'];
    $bookingDate = date('F j, Y', strtotime($booking['preferred_date']));
    $message = "📸 $userFullName booked the $packageName package for $bookingDate.";

    $admin_id = $_SESSION['admin_id'];
    $stmtNotif = $conn->prepare("INSERT INTO notifications (admin_id, message, booking_id) VALUES (?, ?, ?)");
    $stmtNotif->bind_param("isi", $admin_id, $message, $booking_id);
    $stmtNotif->execute();
    $stmtNotif->close();

     $user_id = $booking['user_id'];
    $messageUser = "✅ Your booking for the $packageName package on $bookingDate at $bookingTime has been approved.";
    $stmtUserNotif = $conn->prepare("INSERT INTO notifications_user (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
    $stmtUserNotif->bind_param("is", $user_id, $messageUser);
    $stmtUserNotif->execute();
    $stmtUserNotif->close();
}

header("Location: admin_bookings.php?msg=approved");
exit();
?>
