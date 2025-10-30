<?php
session_start();
require_once '../api/db_connect.php';

// Ensure only admin can access
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'];

// --- Fetch unread messages list (to show inside notification dropdown) ---
$msgNotifs = [];
$msgNotifQuery = "
  SELECT m.id, m.message, m.sent_at, u.first_name, u.last_name
  FROM messages m
  JOIN users u ON m.sender_id = u.id
  WHERE m.receiver_id = ? AND m.sender_type = 'user' AND m.is_read = 0
  ORDER BY m.sent_at DESC
  LIMIT 5
";
if ($stmt = $conn->prepare($msgNotifQuery)) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $msgNotifs[] = $r;
    }
    $stmt->close();
}

// --- Fetch unread message count (FOR CHAT ICON) ---
$unreadMsgCount = 0;
$msgQuery = "SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND sender_type = 'user' AND is_read = 0";
if ($stmt = $conn->prepare($msgQuery)) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $msgData = $res->fetch_assoc();
    if ($msgData && isset($msgData['unread_count'])) {
        $unreadMsgCount = (int)$msgData['unread_count'];
    }
    $stmt->close();
}
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

// Fetch current package data from 'packages_couple'
$id = 1; // ID for the Couple package
$result = mysqli_query($conn, "SELECT * FROM packages_couple WHERE id=$id");
$package = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = $_POST['price'];
    $includes = mysqli_real_escape_string($conn, $_POST['includes']);

    // Handle main image upload
    $main_image = $package['main_image'];
    if (!empty($_FILES['main_image']['name'])) {
        $target_dir = "../../montra_website/uploads/"; // Using your provided path
        $filename = time() . "_" . basename($_FILES["main_image"]["name"]);
        move_uploaded_file($_FILES["main_image"]["tmp_name"], $target_dir . $filename);
        $main_image = $filename;
    }

    // Handle new image uploads (optional)
    $images = $package['images'];
    if (!empty($_FILES['images']['name'][0])) {
        $uploaded = [];
        $target_dir = "../../montra_website/uploads/"; // Using your provided path
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            $filename = time() . "_" . basename($_FILES["images"]["name"][$key]);
            move_uploaded_file($tmp_name, $target_dir . $filename);
            $uploaded[] = $filename;
        }
        $existing = !empty($package['images']) ? explode(",", $package['images']) : [];
        $images = implode(",", array_merge($existing, $uploaded));
    }

    // Update the 'packages_couple' table
    $update = "UPDATE packages_couple 
               SET name='$name', description='$description', price='$price', includes='$includes',
                   images='$images', main_image='$main_image'
               WHERE id=$id";

    if (mysqli_query($conn, $update)) {
        // Redirect back to this page
        header("Location: edit_packages_couple.php?success=1");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// Fetch notifications for topbar
$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($notifQuery);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifs = $stmt->get_result();
$notifCount = $notifs->num_rows;

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Couple Package | Montra Studio</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/dashboard-design.css"> 
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
  
  <style>
    .image-preview {
      width: 100%;
      height: 250px;
      border: 2px dashed var(--border-color);
      border-radius: var(--card-radius);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background-color: #fcfcfc;
    }
    .image-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .gallery-wrapper {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .gallery-img {
      position: relative;
      width: 120px;
      height: 100px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .gallery-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .delete-btn {
      position: absolute;
      top: 5px;
      right: 5px;
      background: rgba(220, 53, 69, 0.9);
      color: white;
      border: none;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
      cursor: pointer;
      text-decoration: none;
      line-height: 1;
      transition: all 0.2s ease;
    }
    .delete-btn:hover {
      background: rgba(220, 53, 69, 1);
      transform: scale(1.1);
    }
  </style>
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
        <li><a href="admin_bookings.php" class="nav-link"><i class="fa-solid fa-calendar-check"></i> Bookings</a></li>
        <li><a href="packages.php" class="nav-link active"><i class="fa-solid fa-box-archive"></i> Packages</a></li>
         <li class="nav-item">
  <a href="admin_chat.php" class="nav-link">
    <i class="fas fa-comments"></i>
    <span>Customer Service</span>
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
          <h2>Edit Package</h2>
          <p>Editing "<?php echo htmlspecialchars($package['name']); ?>"</p>
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
        <div class="notif-list-wrapper"> 
            <?php while ($n = $notifs->fetch_assoc()): ?>
                <div class="notif-item px-3 py-2">
                    <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                </div>
            <?php endwhile; ?>
        </div> <?php endif; ?>
    <a href="view_all_notifications.php" class="view-all">View all notifications</a>
</div>
          </div>
          <div class="chat-notif position-relative">
  <a href="admin_chat.php" class="text-dark">
    <i class="fas fa-comments icon-btn"></i>
    <?php if ($unreadMsgCount > 0): ?>
      <span class="notif-count position-absolute translate-middle badge rounded-pill bg-danger">
        <?php echo $unreadMsgCount; ?>
      </span>
    <?php endif; ?>
  </a>
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
        
        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success">Package updated successfully!</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <div class="row">

            <div class="col-lg-7 mb-4">
              <div class="dash-card h-100">
                <div class="dash-card-header">
                  <h3>Package Details</h3>
                </div>
                <div class="card-body p-4">
                  <div class="mb-3">
                    <label class="form-label">Package Name</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($package['name']) ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="5" required><?= htmlspecialchars($package['description']) ?></textarea>
                  </div>

                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Price</label>
                      <input type="number" class="form-control" name="price" step="0.01" value="<?= $package['price'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Includes (one per line)</label>
                      <textarea class="form-control" name="includes" rows="3" required><?= htmlspecialchars($package['includes']) ?></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-5 mb-4">
              <div class="dash-card h-100">
                <div class="dash-card-header">
                  <h3>Manage Images</h3>
                </div>
                <div class="card-body p-4">
                  <div class="mb-4">
                    <label class="form-label">Main Image (Cover Photo)</label>
                    <div class="image-preview mb-2">
                      <img src="http://localhost/montra_website/uploads/<?= htmlspecialchars($package['main_image']) ?>" alt="Main Image" id="mainImagePreview">
                    </div>
                    <input type="file" class="form-control" name="main_image" onchange="previewMainImage(event)">
                  </div>

                  <div class="mb-4">
                    <label class="form-label">Add More Gallery Images</label>
                    <input type="file" class="form-control mb-3" name="images[]" multiple>
                    <label class="form-label">Current Gallery</label>
                    <div class="gallery-wrapper">
                      <?php 
                      $imgs = explode(",", $package['images']);
                      foreach ($imgs as $img):
                        $img = trim($img);
                        if ($img): ?>
                          <div class="gallery-img">
                            <img src="http://localhost/montra_website/uploads/<?= htmlspecialchars($img) ?>" alt="Package Image">
                            <a href="delete_image_couple.php?package_id=<?= $package['id'] ?>&filename=<?= urlencode($img) ?>" 
                               onclick="return confirm('Are you sure you want to delete this image?');" 
                               class="delete-btn">✕</a>
                          </div>
                        <?php endif;
                      endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
          </div> <div class="row">
            <div class="col-12">
              <div class="dash-card">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                  <a href="packages.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Cancel
                  </a>
                  <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i> Save Changes
                  </button>
                </div>
              </div>
            </div>
          </div>
          
        </form>
      </div> </main>
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

  
  <script>
    // --- Your Page-Specific Scripts ---
    document.getElementById("changePasswordForm").addEventListener("submit", function(event) {
      const newPass = document.getElementById("newPassword").value;
      const confirmPass = document.getElementById("confirmPassword").value;
      if (newPass !== confirmPass) {
        alert("New passwords do not match!");
        event.preventDefault();
      }
    });

    function previewMainImage(event) {
      const reader = new FileReader();
      reader.onload = function(){
        const preview = document.getElementById('mainImagePreview');
        preview.src = reader.result;
      }
      reader.readAsDataURL(event.target.files[0]);
    }

    // --- Theme Scripts (for topbar) ---
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
let selectedUserId = null;
const adminId = <?php echo $admin_id; ?>;
const chatBox = document.getElementById('chatMessages');

// 🟦 Load messages (used by openChat and after sending)
function loadMessages() {
  if (!selectedUserId) return; // prevent running with no user selected

  fetch(`../api/get_messages.php?admin_id=${adminId}&user_id=${selectedUserId}`)
    .then(res => res.json())
    .then(messages => {
      chatBox.innerHTML = '';

      if (messages.length === 0) {
        chatBox.innerHTML = `<p class='text-center text-muted'>No messages yet. Start the conversation!</p>`;
      } else {
        messages.forEach(msg => {
          const msgEl = document.createElement('div');
          msgEl.classList.add('message', msg.sender_type === 'admin' ? 'admin' : 'user');
          msgEl.textContent = msg.message;
          chatBox.appendChild(msgEl);
        });
      }

      chatBox.scrollTop = chatBox.scrollHeight;
    })
    .catch(err => console.error('Error loading messages:', err));
}

// 🟩 Open chat with selected user
function openChat(userId, userName) {
  selectedUserId = userId;

  // Highlight selected user
  document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
  const el = document.querySelector(`.user-item[data-user-id='${userId}']`);
  if (el) el.classList.add('active');
  // --- 👇 ADD THIS BLOCK ---
  // Find and remove the badge from the list item instantly
  const userListBadge = el.querySelector('.user-unread-badge');
  if (userListBadge) {
      userListBadge.remove();
  }

  // Update header and show input area
  document.getElementById('chatHeader').textContent = "Chat with " + (userName || "User");
  document.getElementById('chatInput').style.display = "flex";

  // Hide placeholder
  const placeholder = document.querySelector('.chat-placeholder');
  if (placeholder) placeholder.style.display = 'none';

  // Load messages
  loadMessages();

  // Mark messages as read
  fetch(`../api/mark_messages_read.php?user_id=${selectedUserId}&admin_id=${adminId}`)
    .then(res => res.json())
    .then(() => {
      const badge = document.querySelector('.chat-notif .notif-count');
      if (badge) badge.remove();
    })
    .catch(err => console.error('Error marking messages read:', err));
}

// 🟨 Send message function
function sendMessage() {
  const messageInput = document.getElementById('messageText');
  const message = messageInput.value.trim();
  if (!message || !selectedUserId) return;

  const formData = new FormData();
  formData.append('sender_id', adminId);
  formData.append('receiver_id', selectedUserId);
  formData.append('sender_type', 'admin');
  formData.append('message', message);

  fetch('../api/send_message.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        messageInput.value = '';
        loadMessages(); // refresh immediately
      } else {
        console.error('Failed to send message:', data);
      }
    })
    .catch(err => console.error('Error sending message:', err));
}

// 🟧 Send message on Enter key
document.getElementById('messageText').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    sendMessage();
  }
});

// 🟪 Auto-refresh messages every 5 seconds if a user is open
setInterval(() => {
  if (selectedUserId) loadMessages();
}, 5000);
</script>
</body>
</html>