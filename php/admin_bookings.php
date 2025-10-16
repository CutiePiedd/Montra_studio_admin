<?php
session_start();
require_once '../api/db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}

$adminName = $_SESSION['admin_name'];

// Fetch all bookings
$sql = "SELECT b.id, u.first_name, u.last_name, b.package_name, b.preferred_date, b.preferred_time, b.total_price, b.status, b. receipt_image
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        ORDER BY b.id DESC";
$result = $conn->query($sql);
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
      <h4 class="fw-bold text-white mb-4 ps-2">Montra Studio</h4>
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link active">Bookings</a></li>
        <li><a href="#" class="nav-link">Analytics</a></li>
        <li><a href="#" class="nav-link">Support</a></li>
      </ul>
      <div class="mt-auto">
        <hr class="text-secondary">
        <form action="logout.php" method="POST">
          <button class="btn btn-outline-light w-100" type="submit">Logout</button>
        </form>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-5 flex-grow-1">
      <div class="d-flex justify-content-between align-items-center mb-4">
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
    <th>Receipt</th> <!-- 👈 Added this here -->
    <th>Action</th>
  </tr>
</thead>

          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
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

    <!-- Receipt column -->
    <td>
      <?php if (!empty($row['receipt_image'])): ?>
        <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>"
             alt="Receipt"
             style="max-width:80px;cursor:pointer;border-radius:6px;transition:transform 0.2s;"
             onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
      <?php else: ?>
        <em>No receipt</em>
      <?php endif; ?>
    </td>

    <!-- Action column (make sure it is wrapped in its own <td>) -->
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
              <tr><td colspan="8" class="text-center">No bookings found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
  <div id="receiptModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:1000;">
  <img id="receiptPreview" src="" alt="Receipt" style="max-width:90%; max-height:90%; border-radius:10px;">
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
