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
$query = "SELECT id, message, created_at, is_read FROM notifications WHERE admin_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifications = $stmt->get_result();

// Fetch 10 latest notifications for dropdown
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

    /* Navbar notification dropdown */
    .notification-dropdown {
      position: relative;
      cursor: pointer;
    }
    .notification-dropdown .dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      top: 40px;
      background: #fff;
      min-width: 280px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
      border-radius: 6px;
      z-index: 100;
    }
    .notification-dropdown.show .dropdown-content {
      display: block;
    }
    .notif-item {
      padding: 10px 15px;
      border-bottom: 1px solid #eee;
    }
    .notif-item:last-child {
      border-bottom: none;
    }
    .view-all {
      display: block;
      text-align: center;
      padding: 8px;
      background: #212529;
      color: #fff;
      text-decoration: none;
      border-radius: 0 0 6px 6px;
    }
    .view-all:hover { background: #343a40; }

    /* Full-width notification style */
    .notification-header {
      background-color: #212529;
      color: #fff;
      padding: 15px 30px;
      margin-bottom: 20px;
    }

    .notification-item {
      background: #fff;
      border-radius: 10px;
      margin: 10px 30px;
      padding: 20px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.1);
      transition: transform 0.1s ease-in-out, box-shadow 0.1s ease-in-out;
    }

    .notification-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }

    .notification-item.unread {
      background-color: #f0f7ff;
    }

    .notification-message {
      font-size: 1rem;
      color: #333;
      margin-bottom: 5px;
    }

    .notification-date {
      font-size: 0.85rem;
      color: #6c757d;
    }
  </style>
</head>
<body>

<div class="d-flex">
  <!-- Sidebar -->
  <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <ul class="nav nav-pills flex-column mb-auto mt-5">
            <br/>
      <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
      <li><a href="user_management.php" class="nav-link">User Management</a></li>
      <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
      <li><a href="packages.php" class="nav-link">Packages</a></li>
    </ul>
    <div class="mt-auto">
      <hr>
      <form action="logout.php" method="POST">
        <button class="btn btn-outline-light w-100" type="submit">Logout</button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <main class="main-content flex-grow-1">
    <nav class="navbar navbar-dark fixed-top shadow-sm custom-navbar">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <span class="navbar-title text-white">Montra Studio</span>

        <div class="d-flex align-items-center gap-3">

          <!-- Notification dropdown -->
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
                  <div class="notif-item">
                    <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                  </div>
                <?php endwhile; ?>
              <?php endif; ?>
              <a href="view_all_notifications.php" class="view-all">View all notifications</a>
            </div>
          </div>

          <!-- Admin profile -->
          <div class="dropdown">
            <button class="btn btn-dark border-0" data-bs-toggle="dropdown">
              <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" width="35" height="35" class="rounded-circle">
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li class="dropdown-header text-center">
                <strong><?php echo htmlspecialchars($admin['name']); ?></strong><br>
                <small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <div class="content-wrapper p-5" style="margin-top: 50px;">
      <div >
        <h1 class="mb-0">All Notifications</h1>
      </div>

      <?php if ($notifications->num_rows > 0): ?>
        <?php while ($row = $notifications->fetch_assoc()): ?>
          <div class="notification-item <?php echo !$row['is_read'] ? 'unread' : ''; ?>">
            <p class="notification-message"><?php echo htmlspecialchars($row['message']); ?></p>
            <small class="notification-date"><?php echo date('M d, Y • h:i A', strtotime($row['created_at'])); ?></small>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-center text-muted" style="padding: 30px;">No notifications yet.</div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const notifIcon = document.querySelector('.notification-dropdown i');
  const notifDropdown = document.querySelector('.notification-dropdown');
  notifIcon.addEventListener('click', () => notifDropdown.classList.toggle('show'));
  document.addEventListener('click', e => {
    if (!notifDropdown.contains(e.target)) notifDropdown.classList.remove('show');
  });
});
</script>
</body>
</html>
