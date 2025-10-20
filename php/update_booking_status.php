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
    // check intersection
    foreach ($occupiedSlots as $slot) {
        if (in_array($slot, $occupied)) {
            $conflict = true;
            break 2;
        }
    }
}
$stmt2->close();

if ($conflict) {
    // can't approve due to overlap
    header("Location: admin_bookings.php?error=conflict");
    exit();
}

// no conflict: approve
$stmt3 = $conn->prepare("UPDATE bookings SET status='approved' WHERE id = ?");
$stmt3->bind_param("i", $booking_id);
$stmt3->execute();
$stmt3->close();
//===========================
// ✅ Create notification automatically
// ===========================
require_once '../api/db_connect.php';

// Fetch booking info directly
$bookingQuery = "SELECT contact_person, package_name, preferred_date FROM bookings WHERE id = ? LIMIT 1";
$bookingStmt = $conn->prepare($bookingQuery);
$bookingStmt->bind_param("i", $booking_id);
$bookingStmt->execute();
$result = $bookingStmt->get_result();
$booking = $result->fetch_assoc();
$bookingStmt->close();

if ($booking) {
    $userFullName = $booking['contact_person'];
    $packageName = $booking['package_name'];
    $bookingDate = date('F j, Y', strtotime($booking['preferred_date']));
    $message = "📸 $userFullName booked the $packageName package for $bookingDate.";

    $admin_id = $_SESSION['admin_id'];
    $stmtNotif = $conn->prepare("INSERT INTO notifications (admin_id, message) VALUES (?, ?)");
    $stmtNotif->bind_param("is", $admin_id, $message);
    $stmtNotif->execute();
    $stmtNotif->close();
}

header("Location: admin_bookings.php?msg=approved");
exit();
?>
