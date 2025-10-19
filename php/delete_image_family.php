<?php
session_start();
require_once '../api/db_connect.php';

// Only allow admins
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}

if (isset($_GET['package_id']) && isset($_GET['filename'])) {
  $id = intval($_GET['package_id']);
  $filename = basename($_GET['filename']); // sanitize input

  // Fetch the package
  $result = mysqli_query($conn, "SELECT images FROM packages_family WHERE id=$id");
  $package = mysqli_fetch_assoc($result);

  if ($package) {
    $images = explode(',', $package['images']);
    $images = array_filter($images, fn($img) => trim($img) !== $filename); // remove the one we’re deleting
    $new_images = implode(',', $images);

    // Update DB
    $update = "UPDATE packages_family SET images='$new_images' WHERE id=$id";
    mysqli_query($conn, $update);

    // Delete file from server
    $file_path = "../../montra_website/uploads/$filename";
    if (file_exists($file_path)) {
      unlink($file_path);
    }

    // Redirect back to edit page
    header("Location: edit_packages_family.php?deleted=1");
    exit();
  }
}

header("Location: edit_packages_family.php?error=1");
exit();
?>
