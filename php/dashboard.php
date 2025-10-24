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


$admin_id = $_SESSION['admin_id'];
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
  <title>Montra Studio | Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
  .upcoming-section {
  background: #fff;
  border-radius: 15px;
  padding: 20px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  max-width: 1200px;
  margin: 40px auto;
}

.booking-card {
  border: 1px solid #eee;
  border-radius: 12px;
  padding: 15px;
  margin-bottom: 15px;
  background: #f9fafc;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.booking-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.booking-card h4 {
  margin: 0 0 5px;
  color: #2c3e50;
}

.booking-card p {
  margin: 4px 0;
  font-size: 0.9rem;
  color: #555;
}
/* custom booking modal — avoids colliding with Bootstrap .modal */
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

</style>
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
          <h5 class="card-title">Total Bookings</h5>
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
<div class="upcoming-section">
  <h3>📅 Upcoming Bookings</h3>
  <div id="upcomingBookings">
    <p>Loading upcoming bookings...</p>
  </div>
</div>
<!-- Modal -->
<div id="bookingModal" class="booking-modal">
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
<script>
async function loadUpcomingBookings() {
  const container = document.getElementById('upcomingBookings');
  try {
    const response = await fetch('fetch_upcoming_bookings.php');
    const data = await response.json();

    if (data.length === 0) {
      container.innerHTML = '<p>No upcoming bookings at the moment.</p>';
      return;
    }

    let html = '';
    data.forEach(b => {
      html += `
        <div class="booking-card" onclick="showBookingDetails(${b.id})">
          <h4>${b.package_name}</h4>
          <p><strong>Booked by:</strong> ${b.first_name} ${b.last_name}</p>
          <p><strong>Date:</strong> ${b.preferred_date}</p>
          <p><strong>Time:</strong> ${b.preferred_time}</p>
        </div>
      `;
    });

    container.innerHTML = html;
  } catch (error) {
    container.innerHTML = '<p>Error loading bookings.</p>';
  }
}

// initial load
loadUpcomingBookings();

// refresh every 60 seconds
setInterval(loadUpcomingBookings, 60000);
</script>

<script>
function closeModal() {
  document.getElementById('bookingModal').style.display = 'none';
}

async function showBookingDetails(bookingId) {
  const modal = document.getElementById('bookingModal');
  const body = document.getElementById('modalBody');
  modal.style.display = 'flex';
  body.innerHTML = '<p>Loading booking details...</p>';

  try {
    const res = await fetch(`../api/get_booking_details.php?booking_id=${bookingId}`);
    const data = await res.json();

    if (!data.success) {
      body.innerHTML = `<p>Booking not found.</p>`;
      return;
    }

    body.innerHTML = `
      <h3>${data.package_name}</h3>
      <p><strong>Booked by:</strong> ${data.user_name}</p>
      <p><strong>Contact Person:</strong> ${data.contact_person}</p>
      <p><strong>Email:</strong> ${data.email}</p>
      <p><strong>Phone:</strong> ${data.phone}</p>
      <p><strong>Date:</strong> ${data.preferred_date}</p>
      <p><strong>Time:</strong> ${data.preferred_time}</p>
      <p><strong>Add-ons:</strong> ${data.addons || 'None'}</p>
      <p><strong>Special Request:</strong> ${data.special_request || 'None'}</p>
      <p><strong>Total Price:</strong> ₱${parseFloat(data.total_price).toLocaleString()}</p>
    `;
  } catch (err) {
    body.innerHTML = '<p>Failed to load booking details.</p>';
  }
}
</script>
<script>
function openBookingModal(content) {
  const modal = document.getElementById("bookingModal");
  const modalBody = document.getElementById("modalBody");
  modalBody.innerHTML = content;
  modal.style.display = "flex"; // use flex to center
}

function closeModal() {
  document.getElementById("bookingModal").style.display = "none";
}

window.onclick = function(event) {
  const modal = document.getElementById("bookingModal");
  if (event.target === modal) {
    modal.style.display = "none";
  }
};
</script>

</body>
</html>
