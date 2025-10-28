<?php
session_start();
require_once '../api/db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];

// Fetch all bookings
$bookings_sql = "SELECT b.id, u.first_name, u.last_name, b.package_name, b.addons, b.special_request, b.preferred_date, 
                       b.preferred_time, b.total_price, b.status, b.receipt_image 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 ORDER BY b.preferred_date DESC, b.id DESC"; // Ordered by date
$bookings_result = $conn->query($bookings_sql);

// Fetch admin details
$query = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    $admin = $admin_result->fetch_assoc();

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bookings Management | Montra Studio</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/dashboard-design.css"> 
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body>
    
  <div class="dashboard-wrapper">
    
    <aside class="sidebar">
      <div class="sidebar-header">
        Montra Studio
      </div>
      
      <ul class="nav nav-pills flex-column mb-auto">
        <li><a href="dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
        <li><a href="user_management.php" class="nav-link"><i class="fa-solid fa-users"></i> User Management</a></li>
        <li><a href="admin_bookings.php" class="nav-link active"><i class="fa-solid fa-calendar-check"></i> Bookings</a></li>
        <li><a href="packages.php" class="nav-link"><i class="fa-solid fa-box-archive"></i> Packages</a></li>
         <li class="nav-item">
  <a href="admin_chat.php" class="nav-link">
    <i class="fas fa-comments"></i>
    <span>Messages</span>
  </a>
</li>
 <li class="nav-item">
                    <a href="admin_view_album.php" class="nav-link">
                        <i class="fas fa-images"></i>
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
          <h2>Bookings Management</h2>
          <p>Approve, reject, or view all bookings.</p>
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
                <?php while ($n = $notifs->fetch_assoc()): ?>
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
        <div class="row">
          <div class="col-12">
            <div class="dash-card">
              
              <ul class="nav nav-tabs" id="bookingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">Approved</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">Rejected</button>
                </li>
              </ul>

              <div class="tab-content mt-3" id="bookingTabsContent">
                
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                  <div class="table-wrapper">
                    <table class="table-borderless bookings-table table-hover align-middle">
                      <thead> <tr>
                          <th>#</th>
                          <th>Customer</th>
                          <th>Package</th>
                          <th>Add-ons</th>
                          <th>Special Request</th>
                          <th>Date</th>
                          <th>Time</th>
                          <th>Total (₱)</th>
                          <th>Receipt</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        mysqli_data_seek($bookings_result, 0); // Reset result pointer
                        $hasPending = false;
                        while ($row = $bookings_result->fetch_assoc()):
                          if ($row['status'] == 'pending'): 
                            $hasPending = true; ?>
                            <tr>
                              <td><?= htmlspecialchars($row['id']) ?></td>
                              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                              <td><?= htmlspecialchars($row['package_name']) ?></td>
                              <td><?= nl2br(htmlspecialchars($row['addons'] ?: '—')) ?></td>
                              <td><?= nl2br(htmlspecialchars($row['special_request'] ?: '—')) ?></td>
                              <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                              <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                              <td><?= number_format($row['total_price'], 2) ?></td>
                              <td>
                                <?php if ($row['receipt_image']): ?>
                                  <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                                       style="max-width:70px;cursor:pointer;border-radius:8px;"
                                       onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                                <?php else: ?><em>No receipt</em><?php endif; ?>
                              </td>
                              <td>
                                <form action="update_booking_status.php" method="POST" class="d-flex flex-column flex-lg-row gap-2">
                                  <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                                  <button type="submit" name="action" value="approve" class="btn btn-sm btn-outline-success">
                                    <i class="fa fa-check"></i> Approve
                                  </button>
                                  <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">
                                    <i class="fa fa-times"></i> Reject
                                  </button>
                                </form>
                              </td>
                            </tr>
                        <?php endif; endwhile; 
                        if (!$hasPending): ?>
                          <tr><td colspan="10" class="text-center text-muted py-5">No pending bookings</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="tab-pane fade" id="approved" role="tabpanel">
                  <div class="table-wrapper">
                    <table class="table-borderless bookings-table table-hover align-middle">
                      <thead> <tr>
                          <th>#</th>
                          <th>Customer</th>
                          <th>Package</th>
                          <th>Add-ons</th>
                          <th>Special Request</th>
                          <th>Date</th>
                          <th>Time</th>
                          <th>Total (₱)</th>
                          <th>Receipt</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        mysqli_data_seek($bookings_result, 0);
                        $hasApproved = false;
                        while ($row = $bookings_result->fetch_assoc()):
                          if ($row['status'] == 'approved'): 
                            $hasApproved = true; ?>
                            <tr>
                              <td><?= htmlspecialchars($row['id']) ?></td>
                              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                              <td><?= htmlspecialchars($row['package_name']) ?></td>
                              <td><?= nl2br(htmlspecialchars($row['addons'] ?: '—')) ?></td>
                              <td><?= nl2br(htmlspecialchars($row['special_request'] ?: '—')) ?></td>
                              <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                              <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                              <td><?= number_format($row['total_price'], 2) ?></td>
                              <td>
                                <?php if ($row['receipt_image']): ?>
                                  <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                                       style="max-width:70px;cursor:pointer;border-radius:8px;"
                                       onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                                <?php else: ?><em>No receipt</em><?php endif; ?>
                              </td>
                            </tr>
                        <?php endif; endwhile;
                        if (!$hasApproved): ?>
                          <tr><td colspan="9" class="text-center text-muted py-5">No approved bookings</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="tab-pane fade" id="rejected" role="tabpanel">
                  <div class="table-wrapper">
                    <table class="table-borderless bookings-table table-hover align-middle">
                      <thead> <tr>
                          <th>#</th>
                          <th>Customer</th>
                          <th>Package</th>
                          <th>Add-ons</th>
                          <th>Special Request</th>
                          <th>Date</th>
                          <th>Time</th>
                          <th>Total (₱)</th>
                          <th>Receipt</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        mysqli_data_seek($bookings_result, 0);
                        $hasRejected = false;
                        while ($row = $bookings_result->fetch_assoc()):
                          if ($row['status'] == 'rejected'): 
                            $hasRejected = true; ?>
                            <tr>
                              <td><?= htmlspecialchars($row['id']) ?></td>
                              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                              <td><?= htmlspecialchars($row['package_name']) ?></td>
                              <td><?= nl2br(htmlspecialchars($row['addons'] ?: '—')) ?></td>
                              <td><?= nl2br(htmlspecialchars($row['special_request'] ?: '—')) ?></td>
                              <td><?= htmlspecialchars($row['preferred_date']) ?></td>
                              <td><?= htmlspecialchars($row['preferred_time']) ?></td>
                              <td><?= number_format($row['total_price'], 2) ?></td>
                              <td>
                                <?php if ($row['receipt_image']): ?>
                                  <img src="../uploads/<?= htmlspecialchars($row['receipt_image']) ?>" 
                                       style="max-width:70px;cursor:pointer;border-radius:8px;"
                                       onclick="openReceiptModal('../uploads/<?= htmlspecialchars($row['receipt_image']) ?>')">
                                <?php else: ?><em>No receipt</em><?php endif; ?>
                              </td>
                            </tr>
                        <?php endif; endwhile;
                        if (!$hasRejected): ?>
                          <tr><td colspan="9" class="text-center text-muted py-5">No rejected bookings</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div> </div> </div>
        </div>
      </div>
      
    </main>
  </div> <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
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

 <div id="receiptModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:1070;"></div>
    <img id="receiptPreview" src="" alt="Receipt" style="max-width:90%;max-height:90%;border-radius:10px;">
  </div>
  

  <script>
    document.getElementById("changePasswordForm").addEventListener("submit", function(event) {
      const newPass = document.getElementById("newPassword").value;
      const confirmPass = document.getElementById("confirmPassword").value;
      if (newPass !== confirmPass) {
        alert("New passwords do not match!");
        event.preventDefault();
      }
    });

    function openReceiptModal(src) {
      const modal = document.getElementById('receiptModal');
      const preview = document.getElementById('receiptPreview');
      preview.src = src;
      modal.style.display = 'flex';
      modal.onclick = () => { modal.style.display = 'none'; preview.src = ''; };
    }
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {

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
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('searchInput');
  const searchResults = document.getElementById('searchResults');
  const searchBox = document.getElementById('searchBox');
  const searchToggle = document.getElementById('searchToggle');

  // Toggle search dropdown visibility
  searchToggle.addEventListener('click', () => {
    searchBox.classList.toggle('show');
    searchInput.focus();
  });

  // Close search dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!searchBox.contains(e.target)) {
      searchBox.classList.remove('show');
    }
  });

  // Live search
  searchInput.addEventListener('input', async () => {
    const query = searchInput.value.trim();

    if (query.length < 2) {
      searchResults.innerHTML = '<p class="text-muted px-2 py-1">Type to search...</p>';
      return;
    }

    try {
      const response = await fetch(`../php/admin_search.php?query=${encodeURIComponent(query)}`);
      const text = await response.text(); // for debugging
      // console.log(text); // uncomment to check raw output

      const data = JSON.parse(text);

      if (data.error) {
        searchResults.innerHTML = `<p class="text-danger px-2 py-1">Error: ${data.error}</p>`;
        return;
      }

      if (data.length === 0) {
        searchResults.innerHTML = '<p class="text-muted px-2 py-1">No results found</p>';
        return;
      }

      searchResults.innerHTML = data.map(item => `
        <div class="search-item py-2 border-bottom">
          <a href="${item.link}" class="text-decoration-none text-dark d-block">
            <strong>${item.label}</strong>
            <small class="d-block text-muted">${item.type} — ${item.sub}</small>
          </a>
        </div>
      `).join('');
    } catch (err) {
      console.error(err);
      searchResults.innerHTML = '<p class="text-danger px-2 py-1">Error fetching results</p>';
    }
  });
});
</script>
</body>
</html>