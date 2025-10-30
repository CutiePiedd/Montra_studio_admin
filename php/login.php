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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

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

    /* --- Glassmorphism card (Inspired by your image) --- */
    .glass-card {
      background: rgba(0, 0, 0, 0.25); /* Darker glass tint */
      backdrop-filter: blur(15px);
      -webkit-backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      padding: 2.5rem;
      width: 100%;
      max-width: 400px;
    }

    .card-icon {
      font-size: 3.5rem;
      color: rgba(255, 255, 255, 0.8);
      margin-bottom: 1rem;
    }

    .glass-card h4 {
      color: #fff;
      text-shadow: 0 2px 4px rgba(0,0,0,0.4);
      margin-bottom: 1.5rem;
    }

    /* --- Form Styling (Matches inspiration) --- */
    .input-group {
      border-bottom: 2px solid rgba(255, 255, 255, 0.3);
      margin-bottom: 1.5rem;
    }
    
    .input-group-text {
      background: transparent;
      border: none;
      color: #fff;
      font-size: 1.2rem;
      padding-left: 0;
    }

    .form-control {
      background: transparent;
      border: none;
      color: #fff;
      border-radius: 0;
      padding-left: 0.5rem;
    }

    .form-control::placeholder {
      color: #ddd;
    }

    .form-control:focus {
      background: transparent;
      box-shadow: none;
      color: #fff;
      border-color: transparent; /* Remove focus border */
    }

    .input-group:focus-within {
        border-bottom-color: #fff; /* Highlight bottom border on focus */
    }

    /* --- Options (Remember me / Forgot) --- */
    .form-check-label,
    .forgot-link {
        color: #ddd;
        font-size: 0.9rem;
    }
    .form-check-input {
        background-color: rgba(255,255,255,0.2);
        border-color: rgba(255,255,255,0.3);
    }
    .form-check-input:checked {
        background-color: #2563eb; /* Use button color */
        border-color: #2563eb;
    }
    .forgot-link {
        text-decoration: none;
    }
    .forgot-link:hover {
        color: #fff;
        text-decoration: underline;
    }

    /* --- Login Button --- */
    .btn-login {
      background: #2563eb; /* Solid blue */
      border: none;
      color: #fff;
      font-weight: 600;
      border-radius: 8px;
      padding: 0.75rem;
      transition: all 0.3s ease;
    }

    .btn-login:hover {
      background: #1d4ed8; /* Darker blue */
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .footer-text {
      color: rgba(255,255,255,0.6);
      margin-top: 1.5rem;
      text-align: center;
      font-size: 0.9rem;
    }
  </style>
</head>

<body>
  <div class="glass-card text-center">
    
    <i class="bi bi-camera card-icon"></i>
    <h4 class="fw-bold">Admin Login</h4>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
      
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
        <input type="email" name="email" class="form-control" placeholder="Email" required>
      </div>
      
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
        <input type="password" name="password" class="form-control" placeholder="Password" required>
      </div>
      
      <button type="submit" class="btn btn-login w-100">Login</button>
    </form>

    <p class="footer-text">© Montra Studio 2025</p>
  </div>
</body>
</html>