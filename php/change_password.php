<?php
session_start();
require_once '../api/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access");
}

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        echo "<script>alert('New passwords do not match'); window.history.back();</script>";
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if (!password_verify($current_password, $admin['password'])) {
        echo "<script>alert('Current password is incorrect'); window.history.back();</script>";
        exit;
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $admin_id);
    $update_stmt->execute();

    echo "<script>alert('Password successfully updated!'); window.location.href='dashboard.php';</script>";
}
?>
