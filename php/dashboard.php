<?php
session_start();
require_once '../api/db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['admin_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Montra Studio | Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  
</head>
<body>
  <div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
      <h4 class="fw-bold text-white mb-4 ps-2">Montra Studio</h4>
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="#" class="nav-link active">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
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

    <!-- Main content -->
    <main class="main-content p-5 flex-grow-1">
      <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-semibold text-dark">Welcome, <?php echo htmlspecialchars($adminName); ?> 👋</h2>
      </div>

      
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
