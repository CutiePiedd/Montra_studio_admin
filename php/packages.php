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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Manage Packages</title>
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
  </style>
</head>
<body>
  
    <!-- Sidebar -->
    <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
<br/><br/><br/>
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link">User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link">Bookings</a></li>
        <li><a href="packages.php" class="nav-link active">Packages</a></li>
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

      <br/> <br/> <br/> <br/> 
      <h2 class="fw-semibold mb-4">Manage Packages</h2>

      <div class="row g-4">
        <div class="col-md-3">
          <div class="card h-100" onclick="window.location.href='edit_packages_maincharacter.php'">
            <img src="../uploads/sample_main.jpg" class="card-img-top" alt="Main Character Package">
            <div class="card-body text-center">
              <h5 class="card-title">Main Character Package</h5>
              <p class="card-text">Edit details and images for Main Character.</p>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card h-100" onclick="window.location.href='edit_packages_couple.php'">
            <img src="../uploads/sample_couple.jpg" class="card-img-top" alt="Couple Package">
            <div class="card-body text-center">
              <h5 class="card-title">Couple Package</h5>
              <p class="card-text">Edit details and images for Couple Package.</p>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card h-100" onclick="window.location.href='edit_packages_family.php'">
            <img src="../uploads/sample_family.jpg" class="card-img-top" alt="Family Package">
            <div class="card-body text-center">
              <h5 class="card-title">Family Package</h5>
              <p class="card-text">Edit details and images for Family Package.</p>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card h-100" onclick="window.location.href='edit_packages_squad.php'">
            <img src="../uploads/sample_solo.jpg" class="card-img-top" alt="Squad Package">
            <div class="card-body text-center">
              <h5 class="card-title">Squad Goals Package</h5>
              <p class="card-text">Edit details and images for Solo Package.</p>
            </div>
          </div>
        </div>
      </div>
    </main>
 

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
