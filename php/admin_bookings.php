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
$bookings_sql = "SELECT b.id, u.first_name, u.last_name, b.package_name, b.preferred_date, 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bookings Management | Montra Studio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <br/><br/><br/>
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link active">Bookings</a></li>
        <li><a href="packages.php" class="nav-link">Packages</a></li>
      </ul>
      <div class="mt-auto">
        <hr class="text-secondary">
        <form action="logout.php" method="POST">
          <button class="btn btn-outline-light w-100" type="submit">Logout</button>
        </form>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content flex-grow-1">
      <!-- Top navbar -->
      <nav class="navbar navbar-dark fixed-top shadow-sm custom-navbar">
        <div class="container-fluid d-flex justify-content-between align-items-center">
          <div class="navbar-left">
            <span class="navbar-title">Montra Studio</span>
          </div>
          <div class="navbar-right">
            <div class="dropdown">
              <button class="btn btn-dark border-0" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" alt="Admin" width="35" height="35" class="rounded-circle">
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

      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Package</th>
              <th>Date</th>
              <th>Time</th>
              <th>Total (₱)</th>
              <th>Status</th>
              <th>Receipt</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
              <?php while ($row = $bookings_result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['id']) ?></td>
                  <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                  <td><?= htmlspecialchars($row['package_name']) ?></td>
                  <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                  <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                  <td><?= number_format($row['total_price'], 2) ?></td>
                  <td>
                    <span class="badge <?= $row['status'] == 'pending' ? 'bg-warning' : ($row['status'] == 'approved' ? 'bg-success' : 'bg-danger') ?>">
                      <?= ucfirst($row['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($row['receipt_image'])): ?>
                      <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                           alt="Receipt"
                           style="max-width:80px;cursor:pointer;border-radius:6px;"
                           onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                    <?php else: ?>
                      <em>No receipt</em>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['status'] == 'pending'): ?>
                      <form action="update_booking_status.php" method="POST" class="d-flex gap-2">
                        <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                      </form>
                    <?php else: ?>
                      <em>No actions</em>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-center">No bookings found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Receipt Modal -->
  <div id="receiptModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:1000;">
    <img id="receiptPreview" src="" alt="Receipt" style="max-width:90%;max-height:90%;border-radius:10px;">
  </div>

  <script>
    function openReceiptModal(src) {
      const modal = document.getElementById('receiptModal');
      const preview = document.getElementById('receiptPreview');
      preview.src = src;
      modal.style.display = 'flex';
      modal.onclick = () => { modal.style.display = 'none'; preview.src = ''; };
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
