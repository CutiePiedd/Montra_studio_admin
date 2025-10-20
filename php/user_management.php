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
  <title>Admin | User Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Inter', sans-serif;
    }
   
     .content {
      margin-left: 240px; /* same width as sidebar */
      padding: 30px;
      width: calc(100% - 240px);
    }

    .card {
      border-radius: 12px;
      border: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    table th {
      background-color: #25384A;
      color: white;
    }
  </style>
</head>
<body>
<div class="d-flex">
  <!-- Sidebar -->
 <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
     <br/><br/><br/>
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link active">User Management</a></li>
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

  <!-- Main Content -->
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
      <br/><br/><br/><br/>

<div >
    <h3 class="fw-bold mb-4" style="color:#25384A;">User Management</h3>

    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold" style="color:#25384A;">Registered Users</h5>
        <button class="btn btn-sm btn-outline-secondary" id="refreshUsers">Refresh</button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Registered On</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="userTableBody">
            <tr><td colspan="5" class="text-center text-muted">Loading users...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>




   </main>
  

<script>
async function loadUsers() {
  const response = await fetch('../api/fetch_users.php');
  const users = await response.json();
  
  const tbody = document.getElementById('userTableBody');
  tbody.innerHTML = '';

  if (users.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No users found.</td></tr>';
    return;
  }

  users.forEach((user, index) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${index + 1}</td>
      <td>${user.first_name} ${user.last_name}</td>
      <td>${user.email}</td>
      <td>${new Date(user.created_at).toLocaleString()}</td>
      <td>
        <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

async function deleteUser(id) {
  if (!confirm("Are you sure you want to delete this user?")) return;

  const formData = new FormData();
  formData.append('id', id);

  const response = await fetch('../api/delete_user.php', {
    method: 'POST',
    body: formData
  });
  const result = await response.json();
  alert(result.message);
  loadUsers();
}

document.getElementById('refreshUsers').addEventListener('click', loadUsers);
window.onload = loadUsers;
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
