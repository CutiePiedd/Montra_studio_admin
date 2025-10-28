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

// Fetch all notifications
$query = "
    SELECT 
        n.id,
        n.message,
        n.booking_id,
        n.created_at,
        n.is_read,
        b.package_name,
        b.preferred_date,
        b.preferred_time,
        b.contact_person,
        b.email,
        b.phone,
        b.special_request,
        b.addons,
        b.total_price,
        u.first_name,
        u.last_name
    FROM notifications n
    LEFT JOIN bookings b ON n.booking_id = b.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE n.admin_id = ?
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifications = $stmt->get_result();

// Fetch latest notifications for dropdown
$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt2 = $conn->prepare($notifQuery);
$stmt2->bind_param("i", $admin_id);
$stmt2->execute();
$notifDropdown = $stmt2->get_result();
$notifCount = $notifDropdown->num_rows;

// Fetch admin info
$queryAdmin = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
$stmt3 = $conn->prepare($queryAdmin);
$stmt3->bind_param("i", $admin_id);
$stmt3->execute();
$admin = $stmt3->get_result()->fetch_assoc();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Notifications | Montra Studio</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/dashboard-design.css"> 
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
  
  <style>
    /* New Notification Item Style */
    .notification-list-item {
      background: #fff;
      border-radius: 12px;
      margin-bottom: 1rem;
      padding: 1.25rem 1.5rem;
      border: 1px solid var(--border-color);
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .notification-list-item:last-child {
      margin-bottom: 0;
    }
    .notification-list-item:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow);
      border-color: var(--accent-color);
    }
    .notification-list-item.unread {
      background-color: var(--accent-light);
    }
    .notification-list-item p {
      margin: 0 0 4px 0;
      font-weight: 500;
      color: var(--text-primary);
    }
    .notification-list-item small {
      color: var(--text-secondary);
      font-size: 0.85rem;
    }

    /* Themed Custom Modal (from your code) */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      justify-content: center;
      align-items: center;
      z-index: 1060; /* Above Bootstrap's 1050 */
      animation: fadeIn 0.3s ease;
    }
    .modal-box {
      background: #fff;
      border-radius: var(--card-radius); /* Use theme variable */
      padding: 2rem;
      width: 500px;
      max-width: 90%;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      animation: slideUp 0.3s ease;
    }
    .modal-box h3 {
        font-weight: 600;
        color: var(--accent-color);
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }
    .modal-box .detail-item {
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    .modal-box .detail-item strong {
        color: var(--text-secondary);
        width: 140px;
        display: inline-block;
    }
    .modal-box .close-btn {
      float: right;
      font-size: 1.7rem;
      color: var(--text-secondary);
      cursor: pointer;
      line-height: 1;
      margin-top: -10px;
    }
    .modal-box .close-btn:hover { color: var(--text-primary); }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body>
  
  <div class="dashboard-wrapper">
    
    <aside class="sidebar">
      <div class="sidebar-header">
        Montra Studio
      </div>
      
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link"><i class="fa-solid fa-users"></i> User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link"><i class="fa-solid fa-calendar-check"></i> Bookings</a></li>
        <li><a href="packages.php" class="nav-link"><i class="fa-solid fa-box-archive"></i> Packages</a></li>
        <li><a href="admin_chat.php" class="nav-link"><i class="fa-solid fa-comments"></i> Chat</a></li>
         <li class="nav-item">
                    <a href="admin_view_album.php" class="nav-link">
                        <i class="fas fa-comments"></i>
                        <span>Manage Images</span>
                    </a>
                </li>
      </ul>
      
      <div class="sidebar-footer">
        <form action="logout.php" method="POST">
          <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
        </form>
      </div>
    </aside>

    <main class="main-content">

      <nav class="main-header">
        <div class="header-title">
          <h2>All Notifications</h2>
          <p>View all notifications and booking details.</p>
        </div>

        <div class="header-actions">

          <div class="search-dropdown position-relative">
            <i class="fas fa-search icon-btn" id="searchToggle"></i>
            <div id="searchBox" class="dropdown-content">
              <form id="searchForm" class="d-flex p-3">
                <input type="text" id="searchInput" name="query" class="form-control" placeholder="Search..." autocomplete="off">
              </form>
              <div id="searchResults" class="px-3 pb-2" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
          </div>
          
          <div class="notification-dropdown position-relative">
            <i class="fas fa-bell icon-btn" id="notifToggle"></i>
            <?php if ($notifCount > 0): ?>
              <span class="notif-count position-absolute translate-middle badge rounded-pill bg-danger">
                <?php echo $notifCount; ?>
              </span>
            <?php endif; ?>

            <div class="dropdown-content">
              <?php if ($notifCount === 0): ?>
                <p class="px-3 py-3 mb-0 text-center text-muted">No new notifications</p>
              <?php else: ?>
                <?php mysqli_data_seek($notifDropdown, 0); // Reset pointer ?>
                <?php while ($n = $notifDropdown->fetch_assoc()): ?>
                  <div class="notif-item px-3 py-2">
                    <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                  </div>
                <?php endwhile; ?>
              <?php endif; ?>
              <a href="view_all_notifications.php" class="view-all">View all notifications</a>
            </div>
          </div>
          
          <div class="dropdown">
            <div class="profile-info" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" alt="Admin">
              <span><?php echo htmlspecialchars($admin['name']); ?></span>
            </div>
            
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2" aria-labelledby="adminDropdown">
              <li class="dropdown-header text-center">
                <strong><?php echo htmlspecialchars($admin['name']); ?></strong><br>
                <small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li class="px-3">
                <p class="mb-1"><small><strong>Address:</strong> <?php echo htmlspecialchars($admin['address'] ?? 'N/A'); ?></small></p>
                <p class="mb-1"><small><strong>Contact:</strong> <?php echo htmlspecialchars($admin['contact_number'] ?? 'N/A'); ?></small></p>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <button class="dropdown-item text-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                  <i class="fas fa-key me-2"></i> Change Password
                </button>
              </li>
            </ul>
          </div>
          
        </div>
      </nav>

      <div class="container-fluid px-0 mt-4">
        <div class="dash-card">
          <div class="dash-card-header">
            <h3>Notification History</h3>
          </div>
          <div class="card-body p-4" style="max-height: calc(100vh - 230px); overflow-y: auto;">
            
            <?php if ($notifications->num_rows === 0): ?>
                <p class="text-center text-muted py-5">You have no notifications.</p>
            <?php else: ?>
                <?php mysqli_data_seek($notifications, 0); // Reset main query pointer ?>
                <?php while ($row = $notifications->fetch_assoc()):
                  $booker_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                ?>
                  <div class="notification-list-item <?php echo !$row['is_read'] ? 'unread' : ''; ?>"
                       data-id="<?php echo $row['id']; ?>"
                       data-booker="<?php echo htmlspecialchars($booker_name); ?>"
                       data-package="<?php echo htmlspecialchars($row['package_name'] ?? ''); ?>"
                       data-date="<?php echo htmlspecialchars($row['preferred_date'] ?? ''); ?>"
                       data-time="<?php echo htmlspecialchars($row['preferred_time'] ?? ''); ?>"
                       data-contact="<?php echo htmlspecialchars($row['contact_person'] ?? ''); ?>"
                       data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>"
                       data-phone="<?php echo htmlspecialchars($row['phone'] ?? ''); ?>"
                       data-request="<?php echo htmlspecialchars($row['special_request'] ?? ''); ?>"
                       data-addons="<?php echo htmlspecialchars($row['addons'] ?? ''); ?>"
                       data-price="<?php echo htmlspecialchars($row['total_price'] ?? ''); ?>">
                    
                    <p><?php echo htmlspecialchars($row['message']); ?></p>
                    <small><?php echo date('M d, Y • h:i A', strtotime($row['created_at'])); ?></small>
                  </div>
                <?php endwhile; ?>
            <?php endif; ?>
            
          </div>
        </div>
      </div>
      
    </main>
  </div> <div id="bookingsModal" class="modal-overlay" onclick="this.style.display='none'">
    <div class="modal-box" onclick="event.stopPropagation()">
      <span class="close-btn" onclick="document.getElementById('bookingsModal').style.display='none'">&times;</span>
      <h3 class="mb-3">Booking Details</h3>
      <div class="detail-item"><strong>Booked by:</strong> <span id="modal-booker"></span></div>
      <div class="detail-item"><strong>Package:</strong> <span id="modal-package"></span></div>
      <div class="detail-item"><strong>Preferred Date:</strong> <span id="modal-date"></span></div>
      <div class="detail-item"><strong>Preferred Time:</strong> <span id="modal-time"></span></div>
      <div class="detail-item"><strong>Contact Person:</strong> <span id="modal-contact"></span></div>
      <div class="detail-item"><strong>Email:</strong> <span id="modal-email"></span></div>
      <div class="detail-item"><strong>Phone:</strong> <span id="modal-phone"></span></div>
      <div class="detail-item"><strong>Special Request:</strong> <span id="modal-request"></span></div>
      <div class="detail-item"><strong>Add-ons:</strong> <span id="modal-addons"></span></div>
      <div class="detail-item"><strong>Total Price:</strong> ₱<span id="modal-price"></span></div>
    </div>
  </div>

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

  
  <script>
    document.querySelectorAll('.notification-list-item').forEach(item => {
      item.addEventListener('click', function() {
        
        // Mark as read visually
        this.classList.remove('unread');

        // Mark as read in the database (optional but good)
        const notifId = this.dataset.id;
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${notifId}`
        }).catch(console.error);
        
        // Open the modal
        const modal = document.getElementById("bookingsModal");
        modal.style.display = "flex";

        // Populate modal
        document.getElementById("modal-booker").innerText = this.dataset.booker || "—";
        document.getElementById("modal-package").innerText = this.dataset.package || "—";
        document.getElementById("modal-date").innerText = this.dataset.date || "—";
        document.getElementById("modal-time").innerText = this.dataset.time || "—";
        document.getElementById("modal-contact").innerText = this.dataset.contact || "—";
        document.getElementById("modal-email").innerText = this.dataset.email || "—";
        document.getElementById("modal-phone").innerText = this.dataset.phone || "—";
        document.getElementById("modal-request").innerText = this.dataset.request || "—";
        document.getElementById("modal-addons").innerText = this.dataset.addons || "—";
        
        let price = parseFloat(this.dataset.price);
        document.getElementById("modal-price").innerText = price ? price.toLocaleString('en-US') : "—";
      });
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {

      // --- Password Form Validation ---
      const changePasswordForm = document.getElementById("changePasswordForm");
      if(changePasswordForm) {
        changePasswordForm.addEventListener("submit", function(event) {
          const newPass = document.getElementById("newPassword").value;
          const confirmPass = document.getElementById("confirmPassword").value;
          if (newPass !== confirmPass) {
            alert("New passwords do not match!");
            event.preventDefault();
          }
        });
      }

      // --- Dropdown Toggles ---
      const notifToggle = document.getElementById('notifToggle');
      const notifDropdown = document.querySelector('.notification-dropdown');
      if(notifToggle) {
        notifToggle.addEventListener('click', (e) => {
          e.stopPropagation();
          notifDropdown.classList.toggle('show');
          document.querySelector('.search-dropdown').classList.remove('show');
        });
      }
      
      const searchToggle = document.getElementById('searchToggle');
      const searchDropdown = document.querySelector('.search-dropdown');
      if(searchToggle) {
        searchToggle.addEventListener('click', (e) => {
          e.stopPropagation();
          searchDropdown.classList.toggle('show');
          document.getElementById('searchInput').focus();
          document.querySelector('.notification-dropdown').classList.remove('show');
        });
      }

      // Close dropdowns if clicking outside
      document.addEventListener('click', (e) => {
        if (notifDropdown && !notifDropdown.contains(e.target)) {
          notifDropdown.classList.remove('show');
        }
        if (searchDropdown && !searchDropdown.contains(e.target)) {
          searchDropdown.classList.remove('show');
        }
      });

      // --- Search Handler ---
      const searchInput = document.getElementById('searchInput');
      if(searchInput) {
        searchInput.addEventListener('input', function() {
          const query = this.value.trim();
          const resultsDiv = document.getElementById('searchResults');

          if (query.length < 2) {
            resultsDiv.innerHTML = '';
            return;
          }

          fetch('search_handler.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
              if (data.length === 0) {
                resultsDiv.innerHTML = '<p class="text-muted p-2">No results found.</p>';
              } else {
                resultsDiv.innerHTML = data.map(item =>
                  `<div class="p-2 border-bottom" style="cursor: pointer;"><strong>${item.type}</strong>: ${item.name}</div>`
                ).join('');
              }
            })
            .catch(err => console.error(err));
        });
      }
    });
  </script>

</body>
</html>