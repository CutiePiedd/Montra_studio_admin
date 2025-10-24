<?php
session_start();
require_once '../api/db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}

$admin_id = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];

// Fetch all bookings
$bookings_sql = "SELECT b.id, u.first_name, u.last_name, b.package_name, b.addons, b.special_request, b.preferred_date, 
                        b.preferred_time, b.total_price, b.status, b.receipt_image 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 ORDER BY b.id DESC";
$bookings_result = $conn->query($bookings_sql);

// Fetch admin details
$query = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    $admin = $admin_result->fetch_assoc();

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
  <title>Bookings Management | Montra Studio</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>

.booking-modal {
  display: none;
  position: fixed;
  z-index: 1030; /* lower than bootstrap modal backdrop (1050+) but above page */
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
  </style>
</head>
<body>
   <div class="d-flex">

    <!-- Sidebar -->
    <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
  
      <ul class="nav nav-pills flex-column mb-auto">
        <br/><br/><br/>
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link active">Bookings</a></li>
        <li><a href="packages.php" class="nav-link">Packages</a></li>
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

      <!-- Top navbar -->
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
      <div class="d-flex justify-content-between align-items-center mb-4 mt-5 pt-5">
        <h2 class="fw-semibold text-dark">Bookings Management</h2>
      </div>

      <!-- Tabs for Booking Categories -->
<ul class="nav nav-tabs mt-4" id="bookingTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending Bookings</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">Approved Bookings</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">Rejected Bookings</button>
  </li>
</ul>

<div class="tab-content mt-3" id="bookingTabsContent">
  <!-- Pending Bookings -->
  <div class="tab-pane fade show active" id="pending" role="tabpanel">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-warning">
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Package</th>
             <th>Add ons</th>
              <th>Special Request</th>
            <th>Date</th>
            <th>Time</th>
            <th>Total (₱)</th>
            <th>Receipt</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          mysqli_data_seek($bookings_result, 0); // Reset result pointer
          $hasPending = false;
          while ($row = $bookings_result->fetch_assoc()):
            if ($row['status'] == 'pending'): 
              $hasPending = true; ?>
              <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['package_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['addons'] ?: '—')) ?></td>
              <td><?= nl2br(htmlspecialchars($row['special_request'] ?: '—')) ?></td>
                <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                <td><?= number_format($row['total_price'], 2) ?></td>
                <td>
                  <?php if ($row['receipt_image']): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                         style="max-width:80px;cursor:pointer;border-radius:8px;"
                         onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                  <?php else: ?><em>No receipt</em><?php endif; ?>
                </td>
                <td>
                  <form action="update_booking_status.php" method="POST" class="d-flex gap-2">
                    <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                      <i class="fa fa-check"></i> Approve
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                      <i class="fa fa-times"></i> Reject
                    </button>
                  </form>
                </td>
              </tr>
          <?php endif; endwhile; 
          if (!$hasPending): ?>
            <tr><td colspan="8" class="text-center">No pending bookings</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Approved Bookings -->
  <div class="tab-pane fade" id="approved" role="tabpanel">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-success">
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Package</th>
             <th>Add ons</th>
              <th>Special Request</th>
            <th>Date</th>
            <th>Time</th>
            <th>Total (₱)</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          mysqli_data_seek($bookings_result, 0);
          $hasApproved = false;
          while ($row = $bookings_result->fetch_assoc()):
            if ($row['status'] == 'approved'): 
              $hasApproved = true; ?>
              <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['package_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['addons'] ?: '—')) ?></td>
              <td><?= nl2br(htmlspecialchars($row['special_request'] ?: '—')) ?></td>
                <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                <td><?= number_format($row['total_price'], 2) ?></td>
                <td>
                  <?php if ($row['receipt_image']): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                         style="max-width:80px;cursor:pointer;border-radius:8px;"
                         onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                  <?php else: ?><em>No receipt</em><?php endif; ?>
                </td>
              </tr>
          <?php endif; endwhile;
          if (!$hasApproved): ?>
            <tr><td colspan="7" class="text-center">No approved bookings</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Rejected Bookings -->
  <div class="tab-pane fade" id="rejected" role="tabpanel">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-danger">
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Package</th>
             <th>Add ons</th>
              <th>Special Request</th>
            <th>Date</th>
            <th>Time</th>
            <th>Total (₱)</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          mysqli_data_seek($bookings_result, 0);
          $hasRejected = false;
          while ($row = $bookings_result->fetch_assoc()):
            if ($row['status'] == 'rejected'): 
              $hasRejected = true; ?>
              <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['package_name']) ?></td>
                  <td><?= nl2br(htmlspecialchars($row['addons'] ?: '—')) ?></td>
              <td><?= nl2br(htmlspecialchars($row['special_request'] ?: '—')) ?></td>
                <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                <td><?= number_format($row['total_price'], 2) ?></td>
                <td>
                  <?php if ($row['receipt_image']): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                         style="max-width:80px;cursor:pointer;border-radius:8px;"
                         onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                  <?php else: ?><em>No receipt</em><?php endif; ?>
                </td>
              </tr>
          <?php endif; endwhile;
          if (!$hasRejected): ?>
            <tr><td colspan="7" class="text-center">No rejected bookings</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

    </main>
  </div>

  <!-- Modal -->
<div id="bookingModal" class="modal">
  <div class="booking-modal-inner">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <div id="modalBody">Loading...</div>
  </div>
</div>


    </main>
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

  <!-- Receipt Modal -->
  <div id="receiptModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:1030;">
    <img id="receiptPreview" src="" alt="Receipt" style="max-width:90%;max-height:90%;border-radius:10px;">
  </div>
  
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
    function openReceiptModal(src) {
      const modal = document.getElementById('receiptModal');
      const preview = document.getElementById('receiptPreview');
      preview.src = src;
      modal.style.display = 'flex';
      modal.onclick = () => { modal.style.display = 'none'; preview.src = ''; };
    }
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
