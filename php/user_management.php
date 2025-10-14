<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin | User Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Inter', sans-serif;
    }
    .sidebar {
      height: 100vh;
      background-color: #25384A;
      color: white;
    }
    .sidebar a {
      color: #ffffffb3;
      text-decoration: none;
    }
    .sidebar a:hover {
      color: white;
    }
    .content {
      padding: 30px;
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
      <h4 class="fw-bold text-white mb-4 ps-2">Montra Studio</h4>
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link active">User Management</a></li>
        <li><a href="#" class="nav-link">Bookings</a></li>
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

  <!-- Content -->
  <div class="flex-grow-1 content">
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

</body>
</html>
