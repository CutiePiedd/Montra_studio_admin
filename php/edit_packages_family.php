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
$result = mysqli_query($conn, "SELECT * FROM packages_family WHERE id=$id");
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

  $update = "UPDATE packages_family 
           SET name='$name', description='$description', price='$price', includes='$includes', 
               images='$images', main_image='$main_image'
           WHERE id=$id";

  if (mysqli_query($conn, $update)) {
    header("Location: edit_packages_family.php?success=1");
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
  <title>Edit Family Package | Montra Studio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .form-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
      padding: 30px;
    }
    .image-preview {
      border-radius: 12px;
      overflow: hidden;
      margin-bottom: 15px;
    }
    .image-preview img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }
    .gallery-img {
      position: relative;
      display: inline-block;
      margin: 10px;
    }
    .gallery-img img {
      width: 120px;
      height: 100px;
      object-fit: cover;
      border-radius: 10px;
    }
    .delete-btn {
      position: absolute;
      top: 4px;
      right: 4px;
      background: rgba(255, 0, 0, 0.8);
      color: white;
      border: none;
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 12px;
      cursor: pointer;
    }
  </style>
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
        <h2 class="fw-semibold mb-4">Edit “Family” Package</h2>

        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success">Package updated successfully!</div>
        <?php endif; ?>

        <div class="form-card">
          <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">Package Name</label>
              <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($package['name']) ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="5" required><?= htmlspecialchars($package['description']) ?></textarea>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Price</label>
                <input type="number" class="form-control" name="price" step="0.01" value="<?= $package['price'] ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Includes</label>
                <textarea class="form-control" name="includes" rows="3" required><?= htmlspecialchars($package['includes']) ?></textarea>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Main Image</label>
              <div class="image-preview mb-2">
                <img src="http://localhost/montra_website/uploads/<?= htmlspecialchars($package['main_image']) ?>" alt="Main Image">
              </div>
              <input type="file" class="form-control" name="main_image">
            </div>

            <div class="mb-4">
              <label class="form-label">Add More Images</label>
              <input type="file" class="form-control mb-3" name="images[]" multiple>
              <div>
                <?php 
                $imgs = explode(",", $package['images']);
                foreach ($imgs as $img):
                  $img = trim($img);
                  if ($img): ?>
                    <div class="gallery-img">
                      <img src="http://localhost/montra_website/uploads/<?= htmlspecialchars($img) ?>" alt="Package Image">
                      <a href="delete_image_family.php?package_id=<?= $package['id'] ?>&filename=<?= urlencode($img) ?>" 
                         onclick="return confirm('Are you sure you want to delete this image?');" 
                         class="delete-btn">✕</a>
                    </div>
                  <?php endif;
                endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn btn-primary px-4 py-2">Save Changes</button>
          </form>
        </div>
      </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
