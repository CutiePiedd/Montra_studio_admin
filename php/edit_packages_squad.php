<?php
session_start();
require_once '../api/db_connect.php';

// Ensure only admin can access
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}

// Fetch current package data
$id = 1; // assuming this is the Couple package
$result = mysqli_query($conn, "SELECT * FROM packages_squad WHERE id=$id");
$package = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $description = mysqli_real_escape_string($conn, $_POST['description']);
  $price = $_POST['price'];
  $includes = mysqli_real_escape_string($conn, $_POST['includes']);

  // Handle main image upload
  $main_image = $package['main_image']; // keep existing if none uploaded
  if (!empty($_FILES['main_image']['name'])) {
    $target_dir = "../../montra_website/uploads/";
    $filename = time() . "_" . basename($_FILES["main_image"]["name"]);
    move_uploaded_file($_FILES["main_image"]["tmp_name"], $target_dir . $filename);
    $main_image = $filename;
  }

  // Handle new image uploads (optional)
  $images = $package['images'];
  if (!empty($_FILES['images']['name'][0])) {
    $uploaded = [];
    $target_dir = "../../montra_website/uploads/";

    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
      $filename = time() . "_" . basename($_FILES["images"]["name"][$key]);
      move_uploaded_file($tmp_name, $target_dir . $filename);
      $uploaded[] = $filename;
    }
    $existing = !empty($package['images']) ? explode(",", $package['images']) : [];
    $images = implode(",", array_merge($existing, $uploaded));
  }

  $update = "UPDATE packages_couple 
           SET name='$name', description='$description', price='$price', includes='$includes', 
               images='$images', main_image='$main_image'
           WHERE id=$id";

  if (mysqli_query($conn, $update)) {
    header("Location: edit_packages_couple.php?success=1");
    exit();
  } else {
    echo "Error: " . mysqli_error($conn);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Squad Package | Montra Studio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
<div class="d-flex">
  <!-- Sidebar -->
  <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <h4 class="fw-bold text-white mb-4 ps-2">Montra Studio</h4>
    <ul class="nav nav-pills flex-column mb-auto">
      <li><a href="dashboard.php" class="nav-link text-white">Dashboard</a></li>
      <li><a href="user_management.php" class="nav-link text-white">User Management</a></li>
      <li><a href="admin_bookings.php" class="nav-link text-white">Bookings</a></li>
      <li><a href="packages.php" class="nav-link active text-white">Packages</a></li>
      <li><a href="#" class="nav-link text-white">Support</a></li>
    </ul>
    <div class="mt-auto">
      <hr class="text-secondary">
      <form action="logout.php" method="POST">
        <button class="btn btn-outline-light w-100" type="submit">Logout</button>
      </form>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content p-5 flex-grow-1 bg-light">
    <div class="container">
      <h2 class="fw-semibold text-dark mb-4">Edit Couple Package</h2>

      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-danger">Image deleted successfully.</div>
      <?php endif; ?>
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Package updated successfully!</div>
      <?php endif; ?>

      <div class="card shadow-sm border-0 p-4">
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-medium">Package Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($package['name']) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">Description</label>
            <textarea name="description" rows="4" class="form-control" required><?= htmlspecialchars($package['description']) ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">Price</label>
            <input type="number" name="price" step="0.01" class="form-control" value="<?= $package['price'] ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">Includes (comma-separated)</label>
            <textarea name="includes" rows="3" class="form-control" required><?= htmlspecialchars($package['includes']) ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">Upload Main Image (optional)</label>
            <input type="file" name="main_image" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">Upload New Images (optional)</label>
            <input type="file" name="images[]" class="form-control" multiple>
          </div>

          <div class="text-end">
            <button type="submit" class="btn btn-primary px-4">💾 Save Changes</button>
          </div>
        </form>
      </div>

      <div class="mt-5">
        <h4 class="fw-semibold">Current Images</h4>
        <div class="d-flex flex-wrap mt-3">
          <?php 
          $imgs = explode(",", $package['images']);
          foreach ($imgs as $img):
            $img = trim($img);
            if ($img):
          ?>
          <div class="text-center me-3 mb-3">
            <img src="http://localhost/montra_website/uploads/<?= htmlspecialchars($img) ?>" 
                 alt="Package Image" width="150" height="110" 
                 style="object-fit:cover; border-radius:10px; border:1px solid #ddd; display:block; margin-bottom:8px;">
            <a href="delete_image_couple.php?package_id=<?= $package['id'] ?>&filename=<?= urlencode($img) ?>"
               onclick="return confirm('Are you sure you want to delete this image?');"
               class="btn btn-sm btn-danger px-3">Delete</a>
          </div>
          <?php endif; endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
