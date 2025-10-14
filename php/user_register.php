<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Montra Studio | Create Account</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f9fafb;
      font-family: 'Inter', sans-serif;
    }
    .register-card {
      max-width: 400px;
      margin: auto;
      margin-top: 8%;
      background: #fff;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .btn-primary {
      background-color: #25384A;
      border: none;
    }
    .btn-primary:hover {
      background-color: #1f2f3e;
    }
  </style>
</head>
<body>
  <div class="register-card">
    <h4 class="fw-bold text-center mb-4">Create Your Account</h4>
    <form id="registerForm">
      <div class="mb-3">
        <label class="form-label">First Name</label>
        <input type="text" name="first_name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Last Name</label>
        <input type="text" name="last_name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Register</button>
      <div id="responseMessage" class="mt-3 text-center small"></div>
    </form>
  </div>

  <script>
    const form = document.getElementById('registerForm');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      const response = await fetch('../api/register_user.php', {
        method: 'POST',
        body: formData
      });
      const result = await response.json();
      const msg = document.getElementById('responseMessage');
      msg.innerHTML = result.message;
      msg.className = result.status === 'success' ? 'text-success' : 'text-danger';
      if (result.status === 'success') form.reset();
    });
  </script>
</body>
</html>
