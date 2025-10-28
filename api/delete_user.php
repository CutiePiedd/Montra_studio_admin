<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

echo "<pre>";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

if (!isset($_POST['id'])) {
    die("Missing user ID");
}

$id = intval($_POST['id']);
echo "Deleting user ID: $id\n";

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "User deleted successfully.";
} else {
    die("Execute failed: " . $stmt->error);
}

$stmt->close();
$conn->close();

echo "\nDone.";
?>
