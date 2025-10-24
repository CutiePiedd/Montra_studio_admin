<?php
session_start();
include('../api/db_connect.php');

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Admin account not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Montra Studio | Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* --- Background and layout --- */
    body {
      background: url('../images/bgg.jpg') no-repeat center center/cover;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Poppins', sans-serif;
      color: #fff;
      margin: 0;
      overflow: hidden;
    }

    /* --- Glassmorphism card --- */
    .glass-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 20px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      padding: 2rem;
      width: 100%;
      max-width: 380px;
    }

    .glass-card h4 {
      color: #fff;
      text-shadow: 0 2px 4px rgba(0,0,0,0.4);
    }

    .form-control {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: #fff;
    }

    .form-control::placeholder {
      color: #ddd;
    }

    .form-control:focus {
      background: rgba(255, 255, 255, 0.3);
      box-shadow: none;
      border: 1px solid rgba(255,255,255,0.4);
      color: #fff;
    }

    .btn-login {
      background: linear-gradient(135deg, #1a2a4f, #25384A);
      border: none;
      color: #fff;
      font-weight: 600;
      border-radius: 8px;
      transition: all 0.2s ease;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #2e4b78, #3c5a8a);
      transform: translateY(-2px);
    }

    .footer-text {
      color: rgba(255,255,255,0.6);
      margin-top: 1rem;
      text-align: center;
      font-size: 0.9rem;
    }
  </style>
</head>

<body>
  <div class="glass-card text-center">
    <h4 class="mb-4 fw-bold">Montra Studio Admin</h4>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3 text-start">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" placeholder="Enter email" required>
      </div>
      <div class="mb-3 text-start">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
      </div>
      <button type="submit" class="btn btn-login w-100 py-2">Login</button>
    </form>

    <p class="footer-text">© Montra Studio 2025</p>
  </div>
</body>
</html>
