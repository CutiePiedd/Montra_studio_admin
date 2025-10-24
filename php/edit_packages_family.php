<?php
session_start();
require_once '../api/db_connect.php';

// Ensure only admin can access
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}
$adminName = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'];

// Fetch admin details
$query = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if (!$admin) {
        $admin = [
            'name' => 'Unknown Admin',
            'email' => 'N/A',
            'address' => 'N/A',
            'contact_number' => 'N/A'
        ];
    }

    $stmt->close();
} else {
    die("Query failed: " . $conn->error);
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
$packageLabels = [];
$packageCounts = [];

$packageSql = "SELECT package_name, COUNT(*) AS total FROM bookings GROUP BY package_name ORDER BY total DESC";
$result = $conn->query($packageSql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $packageLabels[] = $row['package_name'];
        $packageCounts[] = intval($row['total']);
    }
}

// Convert to JSON for Chart.js
$packageLabelsJson = json_encode($packageLabels);
$packageCountsJson = json_encode($packageCounts);

// Fetch 5 upcoming bookings approved by admin
$recentBookingsSql = "
    SELECT b.id, u.first_name, u.last_name, b.package_name, b.preferred_date, b.status
    FROM bookings AS b
    JOIN users AS u ON b.user_id = u.id
    WHERE b.status = 'approved' 
      AND b.preferred_date >= CURDATE() - INTERVAL 7 DAY
    ORDER BY b.preferred_date DESC
    LIMIT 5
";

$recentBookingsResult = $conn->query($recentBookingsSql);
$recentBookings = [];
if ($recentBookingsResult) {
    while ($row = $recentBookingsResult->fetch_assoc()) {
        $recentBookings[] = $row;
    }
}



$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($notifQuery);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifs = $stmt->get_result();
$notifCount = $notifs->num_rows;

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Family Package | Montra Studio</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
/* Ensure sidebar and navbar don't block modals or dropdowns */
/* Layering fixes: make navbar visible but allow dropdowns/modals to overlay */
.custom-navbar { z-index: 1030; position: fixed; top: 0; left: 0; right: 0; }
.sidebar { z-index: 1020; }

/* Ensure Bootstrap dropdown and modal layers win */
.dropdown-menu { z-index: 2000 !important; }
.modal-backdrop { z-index: 2050 !important; }
.modal { z-index: 2060 !important; }

/* If any element uses pointer-events:none accidentally, restore pointer events for navbar */
.custom-navbar, .custom-navbar * { pointer-events: auto; }


  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <h4 class="fw-bold mb-4 ps-2 text-dark">Montra Studio</h4>
    <ul class="nav nav-pills flex-column mb-auto">
      <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
      <li><a href="user_management.php" class="nav-link">User Management</a></li>
      <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
      <li><a href="packages.php" class="nav-link active">Packages</a></li>
    </ul>
    <div class="mt-auto">
      <hr class="text-secondary">
      <form action="logout.php" method="POST">
        <button class="btn btn-outline-blue w-100" type="submit">Logout</button>
      </form>
    </div>
  </aside>
  <!-- Main Content -->
   <main class="main-content">
     <!-- TOP NAVBAR -->
   <nav class="navbar navbar-dark fixed-top shadow-sm custom-navbar">
  <div class="container-fluid d-flex justify-content-between align-items-center">
    
    <div class="navbar-left">
      <span class="navbar-title">Montra Studio</span>
    </div>

    <!-- Right side: Notifications + Profile -->
    <div class="navbar-right d-flex align-items-center gap-3">

      <!-- Notification icon -->
      <div class="notification-dropdown position-relative">
        <i class="fas fa-bell fs-5 text-light"></i>
        <?php if ($notifCount > 0): ?>
          <span class="notif-count position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?php echo $notifCount; ?>
          </span>
        <?php endif; ?>

        <div class="dropdown-content">
          <?php if ($notifCount === 0): ?>
            <p class="px-3 py-2 mb-0">No new notifications</p>
          <?php else: ?>
            <?php while ($n = $notifs->fetch_assoc()): ?>
              <div class="notif-item px-3 py-2 border-bottom">
                <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
                <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>
<a href="view_all_notifications.php" class="view-all">View all notifications</a>
</div>

      </div>
      <!-- Profile dropdown -->
<div class="dropdown">
  <button id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false" class="profile-btn">
    <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" 
         alt="Admin" width="35" height="35">
  </button>

  <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="adminDropdown">
    <li class="dropdown-header text-center">
      <strong><?php echo htmlspecialchars($admin['name']); ?></strong><br>
      <small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small>
    </li>
    <li><hr class="dropdown-divider"></li>
    <li class="px-3">
      <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($admin['address'] ?? 'N/A'); ?></p>
      <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($admin['contact_number'] ?? 'N/A'); ?></p>
    </li>
    <li><hr class="dropdown-divider"></li>
    <li>
      <button class="dropdown-item text-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
        Change Password
      </button>
    </li>
  </ul>
</div>
 

    </div>
  </div>
</nav>
    <!-- PAGE CONTENT -->
 
    
           <br/><br/><h2 class="fw-semibold mb-4">Edit “Family” Package</h2>

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
   
    </main>
 <!-- Change Password Modal -->
   <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="changePasswordLabel">Change Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="changePasswordForm" method="POST" action="change_password.php">
            <div class="mb-3">
              <label for="currentPassword" class="form-label">Current Password</label>
              <input type="password" class="form-control" id="currentPassword" name="current_password" required>
            </div>
            <div class="mb-3">
              <label for="newPassword" class="form-label">New Password</label>
              <input type="password" class="form-control" id="newPassword" name="new_password" required>
            </div>
            <div class="mb-3">
              <label for="confirmPassword" class="form-label">Confirm New Password</label>
              <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
  document.getElementById("changePasswordForm").addEventListener("submit", function(event) {
    const newPass = document.getElementById("newPassword").value;
    const confirmPass = document.getElementById("confirmPassword").value;
    if (newPass !== confirmPass) {
      alert("New passwords do not match!");
      event.preventDefault();
    }
  });
  </script>
<script>
  // Handle notification dropdown toggle
  document.addEventListener("DOMContentLoaded", () => {
    const notifIcon = document.querySelector(".notification-dropdown i");
    const notifDropdown = document.querySelector(".notification-dropdown");

    notifIcon.addEventListener("click", (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle("show");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!notifDropdown.contains(e.target)) {
        notifDropdown.classList.remove("show");
      }
    });
  });
</script>

</html>
