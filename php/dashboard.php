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
// Total users on the website
$userCountSql = "SELECT COUNT(*) AS total_users FROM users";
$userCountResult = $conn->query($userCountSql);
$totalUsers = $userCountResult->fetch_assoc()['total_users'] ?? 0;

// Total bookings in the past month
$monthStart = date('Y-m-d', strtotime('-1 month'));
$bookingsMonthSql = "SELECT COUNT(*) AS total_bookings FROM bookings WHERE preferred_date >= ?";
$stmt = $conn->prepare($bookingsMonthSql);
$stmt->bind_param("s", $monthStart);
$stmt->execute();
$bookingsMonthResult = $stmt->get_result();
$totalBookingsMonth = $bookingsMonthResult->fetch_assoc()['total_bookings'] ?? 0;
$stmt->close();

// Package with most bookings
$topPackageSql = "
    SELECT package_name, COUNT(*) AS total
    FROM bookings
    GROUP BY package_name
    ORDER BY total DESC
    LIMIT 1
";
$topPackageResult = $conn->query($topPackageSql);
$topPackage = $topPackageResult->fetch_assoc();
$topPackageName = $topPackage['package_name'] ?? 'N/A';
$topPackageCount = $topPackage['total'] ?? 0;

// Prepare data for bookings trend chart (last 6 months)
$bookingsTrendData = [];
$months = [];

for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i month"));
    $monthEnd = date('Y-m-t', strtotime("-$i month"));

    $months[] = date('M Y', strtotime($monthStart)); // e.g., "Oct 2025"

    $trendSql = "SELECT COUNT(*) AS total FROM bookings WHERE preferred_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($trendSql);
    $stmt->bind_param("ss", $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['total'] ?? 0;
    $bookingsTrendData[] = $count;
    $stmt->close();
}

// Convert PHP arrays to JSON for Chart.js
$monthsJson = json_encode($months);
$bookingsTrendJson = json_encode($bookingsTrendData);

// Fetch bookings count per package
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
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'Approved'
    ORDER BY b.preferred_date ASC
    LIMIT 5
";
$recentBookingsResult = $conn->query($recentBookingsSql);
$recentBookings = [];
if ($recentBookingsResult) {
    while ($row = $recentBookingsResult->fetch_assoc()) {
        $recentBookings[] = $row;
    }
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
  <title>Montra Studio | Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <div class="d-flex">

    <!-- Sidebar -->
    <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
  
      <ul class="nav nav-pills flex-column mb-auto">
        <br/><br/><br/>
        <li><a href="#" class="nav-link active">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
        <li><a href="packages.php" class="nav-link">Packages</a></li>
      </ul>
      <div class="mt-auto">
        <hr class="text-secondary">
        <form action="logout.php" method="POST">
          <button class="btn btn-outline-light w-100" type="submit">Logout</button>
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
        <button class="btn btn-dark border-0" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" 
               alt="Admin" width="35" height="35" class="rounded-circle">
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
<div class="container mt-5 pt-5">
  <div class="row g-4">

    <!-- Total Users Card -->
    <div class="col-md-4">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Total Users</h5>
          <p class="card-text fs-3 fw-bold"><?php echo $totalUsers; ?></p>
        </div>
      </div>
    </div>

    <!-- Total Bookings (Past Month) Card -->
    <div class="col-md-4">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h5 class="card-title">TTotal Bookings</h5>
          <p class="card-text fs-3 fw-bold"><?php echo $totalBookingsMonth; ?></p>
        </div>
      </div>
    </div>

    <!-- Top Package Card -->
    <div class="col-md-4">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Top Package</h5>
          <p class="card-text fs-5"><?php echo htmlspecialchars($topPackageName); ?></p>
          <p class="card-text fs-6"><?php echo $topPackageCount; ?> bookings</p>
        </div>
      </div>
    </div>


  <!-- Charts Section -->


    <!-- Bookings Trend Line Chart -->
   <!-- Bookings Trend Line Chart -->
<div class="col-md-6">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title text-center">Bookings Trend (Last 6 Months)</h5>
      <div class="chart-container">
        <canvas id="bookingsChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Bookings by Package Pie Chart -->
<div class="col-md-6">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title text-center">Bookings by Package</h5>
      <div class="chart-container">
        <canvas id="packageChart"></canvas>
      </div>
    </div>
  </div>
</div>

  </div>
</div>
<!-- Load Chart.js first -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Line chart
  const ctxLine = document.getElementById('bookingsChart').getContext('2d');
  new Chart(ctxLine, {
    type: 'line',
    data: {
      labels: <?php echo $monthsJson; ?>,
      datasets: [{
        label: 'Bookings',
        data: <?php echo $bookingsTrendJson; ?>,
        fill: true,
        backgroundColor: 'rgba(54, 162, 235, 0.2)',
        borderColor: 'rgba(54, 162, 235, 1)',
        tension: 0.3,
        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
        pointRadius: 5
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
      scales: { y: { beginAtZero: true, stepSize: 1 } }
    }
  });

  // Pie chart
  const ctxPie = document.getElementById('packageChart').getContext('2d');
  new Chart(ctxPie, {
    type: 'pie',
    data: {
      labels: <?php echo $packageLabelsJson; ?>,
      datasets: [{
        label: 'Bookings per Package',
        data: <?php echo $packageCountsJson; ?>,
        backgroundColor: [
          'rgba(255, 99, 132, 0.7)',
          'rgba(54, 162, 235, 0.7)',
          'rgba(255, 206, 86, 0.7)',
          'rgba(75, 192, 192, 0.7)',
          'rgba(153, 102, 255, 0.7)',
          'rgba(255, 159, 64, 0.7)'
        ],
        borderColor: [
          'rgba(255, 99, 132, 1)',
          'rgba(54, 162, 235, 1)',
          'rgba(255, 206, 86, 1)',
          'rgba(75, 192, 192, 1)',
          'rgba(153, 102, 255, 1)',
          'rgba(255, 159, 64, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } }
    }
  });

});
</script>

<!-- Recent Bookings Section -->
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Recent Approved Bookings</h5>
      <?php if (empty($recentBookings)): ?>
        <p class="text-muted mb-0">No recent bookings.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($recentBookings as $booking): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>  
                booked <strong><?php echo htmlspecialchars($booking['package_name']); ?></strong>
              </div>
              <div>
                <span class="badge bg-success"><?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
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
