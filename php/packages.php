<?php
session_start();
require_once '../api/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];

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
$admin_id = $_SESSION['admin_id'];
$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($notifQuery);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifs = $stmt->get_result();
$notifCount = $notifs->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Manage Packages</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      transition: transform 0.2s, box-shadow 0.2s;
      cursor: pointer;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

.booking-modal {
  display: none;
  position: fixed;
  z-index: 1040; /* lower than bootstrap modal backdrop (1050+) but above page */
  left: 0; top: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  justify-content: center;
  align-items: center;
  animation: fadeIn 0.3s ease;
}

.booking-modal .booking-modal-inner {
  background: #fff;
  padding: 25px;
  border-radius: 15px;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  animation: slideUp 0.3s ease;
}

.close-btn {
  float: right;
  font-size: 1.5rem;
  color: #555;
  cursor: pointer;
}

.close-btn:hover {
  color: #000;
}

@keyframes fadeIn {
  from { opacity: 0; } to { opacity: 1; }
}

@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
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
  
    <!-- Sidebar -->
        <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
  
      <ul class="nav nav-pills flex-column mb-auto">
        <br/><br/><br/>
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
        <li><a href="packages.php" class="nav-link active">Packages</a></li>
      </ul>
      <div class="mt-auto">
        <hr class="text-secondary">
        <form action="logout.php" method="POST">
          <button class= "btn btn-outline-blue w-100" type="submit">Logout</button>
        </form>
      </div>
    </aside>

    <!-- Main content -->
     <main class="main-content flex-grow-1">
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
     

      <br/> <br/> <br/> <br/> 
      <h2 class="fw-semibold mb-4">Manage Packages</h2>

      <div class="row cards-row">
    <div class="card" onclick="window.location.href='edit_packages_maincharacter.php'">
        <img src="../images/solo1.jpg" alt="Main Character Package">
        <div class="card-body">
            <h5 class="card-title">Main Character Package</h5>
            <p class="card-text">Edit details and images for Main Character.</p>
        </div>
    </div>

    <div class="card" onclick="window.location.href='edit_packages_couple.php'">
        <img src="../images/couple1.jpg" alt="Couple Package">
        <div class="card-body">
            <h5 class="card-title">Couple Package</h5>
            <p class="card-text">Edit details and images for Couple Package.</p>
        </div>
    </div>

    <div class="card" onclick="window.location.href='edit_packages_family.php'">
        <img src="../images/family1.jpg" alt="Family Package">
        <div class="card-body">
            <h5 class="card-title">Family Package</h5>
            <p class="card-text">Edit details and images for Family Package.</p>
        </div>
    </div>

    <div class="card" onclick="window.location.href='edit_packages_squad.php'">
        <img src="../images/tropa1.jpg" alt="Squad Package">
        <div class="card-body">
            <h5 class="card-title">Squad Goals Package</h5>
            <p class="card-text">Edit details and images for Solo Package.</p>
        </div>
    </div>
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
document.addEventListener('DOMContentLoaded', () => {
  const notifIcon = document.querySelector('.notification-dropdown i');
  const notifDropdown = document.querySelector('.notification-dropdown');

  notifIcon.addEventListener('click', () => {
    notifDropdown.classList.toggle('show');
  });

  // Close dropdown if clicking outside
  document.addEventListener('click', (e) => {
    if (!notifDropdown.contains(e.target)) {
      notifDropdown.classList.remove('show');
    }
  });
});
</script>
 
</body>
</html>
