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

// Fetch all notifications
$query = "
  SELECT 
    n.id,
    n.message,
    n.booking_id,
    n.created_at,
    n.is_read,
    b.package_name,
    b.preferred_date,
    b.preferred_time,
    b.contact_person,
    b.email,
    b.phone,
    b.special_request,
    b.addons,
    b.total_price,
    u.first_name,
    u.last_name
  FROM notifications n
  LEFT JOIN bookings b ON n.booking_id = b.id
  LEFT JOIN users u ON b.user_id = u.id
  WHERE n.admin_id = ?
  ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifications = $stmt->get_result();

// Fetch latest notifications for dropdown
$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt2 = $conn->prepare($notifQuery);
$stmt2->bind_param("i", $admin_id);
$stmt2->execute();
$notifDropdown = $stmt2->get_result();
$notifCount = $notifDropdown->num_rows;

// Fetch admin info
$queryAdmin = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
$stmt3 = $conn->prepare($queryAdmin);
$stmt3->bind_param("i", $admin_id);
$stmt3->execute();
$admin = $stmt3->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Notifications | Montra Studio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    body { background: #f8f9fa; font-family: 'Inter', sans-serif; }

    .profile-btn {
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
    }

    .profile-btn:focus,
    .profile-btn:active {
      outline: none;
      box-shadow: none;
    }

    .notification-item {
      background: #fff;
      border-radius: 10px;
      margin: 10px 30px;
      padding: 20px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.1);
      transition: 0.2s ease;
    }
    .notification-item.unread { background-color: #f0f7ff; }
    .notification-item:hover { transform: translateY(-2px); }

    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.45);
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    .modal-box {
      background: #fff;
      border-radius: 12px;
      padding: 30px;
      width: 450px;
      max-width: 90%;
      box-shadow: 0 5px 25px rgba(0,0,0,0.2);
      animation: fadeIn 0.2s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.97); }
      to { opacity: 1; transform: scale(1); }
    }

    .btn-logout {
      background: #dc3545;
      color: #fff;
    }
    .btn-logout:hover {
      background: #bb2d3b;
    }
  </style>
</head>
<body>

<div class="d-flex">
  <!-- Sidebar -->
   <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
  
      <ul class="nav nav-pills flex-column mb-auto">
        <br/><br/><br/>
        <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
        <li><a href="packages.php" class="nav-link">Packages</a></li>
      </ul>
      <div class="mt-auto">
        <hr class="text-secondary">
        <form action="logout.php" method="POST">
          <button class= "btn btn-outline-blue w-100" type="submit">Logout</button>
        </form>
      </div>
    </aside>

  <!-- Main -->
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
            <?php while ($n = $notifDropdown->fetch_assoc()): ?>
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

    <!-- Notifications content -->
    <div class="content-wrapper p-5" style="margin-top: 70px;">
      <h1 class="mb-4">All Notifications</h1>

      <?php while ($row = $notifications->fetch_assoc()):
        $booker_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
      ?>
        <div class="notification-item <?php echo !$row['is_read'] ? 'unread' : ''; ?>"
             data-booker="<?php echo htmlspecialchars($booker_name); ?>"
             data-package="<?php echo htmlspecialchars($row['package_name'] ?? ''); ?>"
             data-date="<?php echo htmlspecialchars($row['preferred_date'] ?? ''); ?>"
             data-time="<?php echo htmlspecialchars($row['preferred_time'] ?? ''); ?>"
             data-contact="<?php echo htmlspecialchars($row['contact_person'] ?? ''); ?>"
             data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>"
             data-phone="<?php echo htmlspecialchars($row['phone'] ?? ''); ?>"
             data-request="<?php echo htmlspecialchars($row['special_request'] ?? ''); ?>"
             data-addons="<?php echo htmlspecialchars($row['addons'] ?? ''); ?>"
             data-price="<?php echo htmlspecialchars($row['total_price'] ?? ''); ?>">
          <p class="notification-message"><?php echo htmlspecialchars($row['message']); ?></p>
          <small class="notification-date"><?php echo date('M d, Y • h:i A', strtotime($row['created_at'])); ?></small>
        </div>
      <?php endwhile; ?>
    </div>
  </main>
</div>

<!-- Booking Details Modal -->
<div id="bookingsModal" class="modal-overlay" onclick="this.style.display='none'">
  <div class="modal-box" onclick="event.stopPropagation()">
    <span class="close-btn" onclick="document.getElementById('bookingsModal').style.display='none'">&times;</span>
    <h3 class="mb-3">Booking Details</h3>
    <div class="detail-item"><strong>Booked by:</strong> <span id="modal-booker"></span></div>
    <div class="detail-item"><strong>Package:</strong> <span id="modal-package"></span></div>
    <div class="detail-item"><strong>Preferred Date:</strong> <span id="modal-date"></span></div>
    <div class="detail-item"><strong>Preferred Time:</strong> <span id="modal-time"></span></div>
    <div class="detail-item"><strong>Contact Person:</strong> <span id="modal-contact"></span></div>
    <div class="detail-item"><strong>Email:</strong> <span id="modal-email"></span></div>
    <div class="detail-item"><strong>Phone:</strong> <span id="modal-phone"></span></div>
    <div class="detail-item"><strong>Special Request:</strong> <span id="modal-request"></span></div>
    <div class="detail-item"><strong>Add-ons:</strong> <span id="modal-addons"></span></div>
    <div class="detail-item total"><strong>Total Price:</strong> ₱<span id="modal-price"></span></div>
  </div>
</div>

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
document.querySelectorAll('.notification-item').forEach(item => {
  item.addEventListener('click', function() {
    const modal = document.getElementById("bookingsModal");
    modal.style.display = "flex";

    document.getElementById("modal-booker").innerText = this.dataset.booker || "—";
    document.getElementById("modal-package").innerText = this.dataset.package || "—";
    document.getElementById("modal-date").innerText = this.dataset.date || "—";
    document.getElementById("modal-time").innerText = this.dataset.time || "—";
    document.getElementById("modal-contact").innerText = this.dataset.contact || "—";
    document.getElementById("modal-email").innerText = this.dataset.email || "—";
    document.getElementById("modal-phone").innerText = this.dataset.phone || "—";
    document.getElementById("modal-request").innerText = this.dataset.request || "—";
    document.getElementById("modal-addons").innerText = this.dataset.addons || "—";
    document.getElementById("modal-price").innerText = this.dataset.price || "—";
  });
});
</script>
</body>
</html>
