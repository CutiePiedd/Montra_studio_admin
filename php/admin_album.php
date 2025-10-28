<?php
session_start();
require_once '../api/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize search
$search = $_GET['search'] ?? '';

// Fetch matching users
if (!empty($search)) {
   $stmt = $conn->prepare("
    SELECT id, CONCAT(first_name, ' ', last_name) AS name, email 
    FROM users 
    WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ?
");

    $searchParam = "%$search%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin | User Albums</title>
  <link rel="stylesheet" href="../assets/css/admin_styles.css">
  <style>
    body {
      font-family: "Poppins", sans-serif;
      background: #f9f9f9;
      margin: 0;
      padding: 0;
    }
    .container {
      width: 90%;
      margin: 40px auto;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    form {
      display: flex;
      justify-content: center;
      margin-bottom: 30px;
    }
    input[type="text"] {
      width: 300px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px 0 0 8px;
      outline: none;
    }
    button {
      padding: 10px 15px;
      background: #007bff;
      border: none;
      color: white;
      border-radius: 0 8px 8px 0;
      cursor: pointer;
      transition: 0.3s;
    }
    button:hover {
      background: #0056b3;
    }
    .user-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      text-align: center;
      transition: 0.3s;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    .card h3 {
      margin: 10px 0 5px;
    }
    .card p {
      color: #666;
      margin-bottom: 15px;
    }
    .card a {
      display: inline-block;
      background: #28a745;
      color: white;
      text-decoration: none;
      padding: 8px 15px;
      border-radius: 6px;
      transition: 0.3s;
    }
    .card a:hover {
      background: #218838;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>User Albums</h2>
    <form method="GET" action="">
      <input type="text" name="search" placeholder="Search user name or email" value="<?= htmlspecialchars($search) ?>">
      <button type="submit">Search</button>
    </form>

    <div class="user-cards">
      <?php if (!empty($users) && $users->num_rows > 0): ?>
        <?php while ($user = $users->fetch_assoc()): ?>
          <div class="card">
            <h3><?= htmlspecialchars($user['name']) ?></h3>
            <p><?= htmlspecialchars($user['email']) ?></p>
            <a href="admin_view_album.php?user_id=<?= $user['id'] ?>">View Album</a>
          </div>
        <?php endwhile; ?>
      <?php elseif (!empty($search)): ?>
        <p style="text-align:center;">No users found for “<?= htmlspecialchars($search) ?>”.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
