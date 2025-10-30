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

// --- Fetch admin details (FOR TOP BAR) ---
$admin = [
    'name' => 'Unknown Admin',
    'email' => 'N/A',
    'address' => 'N/A',
    'contact_number' => 'N/A'
];

$query = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row) $admin = $row;
    $stmt->close();
}

// --- Fetch latest notifications (FOR TOP BAR) ---
$notifs = [];
$notifCount = 0;
$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
if ($stmt = $conn->prepare($notifQuery)) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $notifs[] = $r;
    }
    $notifCount = count($notifs);
    $stmt->close();
}

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

// --- Combine notification counts (FOR BELL ICON) ---
$msgNotifCount = count($msgNotifs);
$totalNotifCount = $notifCount + $msgNotifCount;

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

// --- Fetch all users (clients) (FOR CHAT LIST) ---
$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        
        -- 1. Count unread messages sent BY this user TO the admin
        (SELECT COUNT(*) 
         FROM messages m 
         WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0 AND m.sender_type = 'user') as unread_count,
         
        -- 2. Get the timestamp of the last message exchanged with this user
        (SELECT MAX(m2.sent_at) 
         FROM messages m2 
         WHERE (m2.sender_id = u.id AND m2.receiver_id = ?) OR (m2.receiver_id = u.id AND m2.sender_id = ?)) as last_message_time
         
    FROM
        users u
    
    -- 3. Order by the new data
    ORDER BY
        unread_count DESC,    -- Users with unread messages first
        last_message_time DESC, -- Then, most recent conversations
        u.first_name ASC      -- Finally, alphabetically
";

$stmt_users = $conn->prepare($sql);
// We bind the admin_id 3 times (for ?, ?, ?)
$stmt_users->bind_param("iii", $admin_id, $admin_id, $admin_id);
$stmt_users->execute();
$users_result = $stmt_users->get_result();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Chat | Montra Studio</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/dashboard-design.css"> 
  <link rel="stylesheet" href="../css/chat-design.css"> 
  <style>
  .user-item {
    /* This is needed so the badge can be positioned inside it */
    position: relative; 
  }
  .user-unread-badge {
    background-color: #e74c3c;
    color: white;
    font-size: 11px;
    font-weight: bold;
    border-radius: 50%;
    padding: 2px 6px;
    position: absolute;
    top: 10px; /* Adjust as needed */
    right: 15px; /* Adjust as needed */
    min-width: 20px;
    text-align: center;
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
        <li><a href="packages.php" class="nav-link"><i class="fa-solid fa-box-archive"></i> Packages</a></li>
        <li><a href="admin_chat.php" class="nav-link active"><i class="fa-solid fa-comments"></i> Customer Service</a></li>
          <li><a href="admin_view_album.php" class="nav-link"><i class="fa-solid fa-images"></i> Manage Images</a></li>
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
          <h2>Chat</h2>
          <p>Message clients directly.</p>
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
            <?php if ($totalNotifCount > 0): ?>
              <span class="notif-count position-absolute translate-middle badge rounded-pill bg-danger">
                <?php echo $totalNotifCount; ?>
              </span>
            <?php endif; ?>

           <div class="dropdown-content">
                <?php if (empty($notifs) && empty($msgNotifs)): ?>
                    <p class="px-3 py-3 mb-0 text-center text-muted">No new notifications</p>
                <?php else: ?>
                    <div class="notif-list-wrapper"> 
                        
                        <?php foreach ($notifs as $n): ?>
                            <div class="notif-item px-3 py-2">
                                <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
                                <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($msgNotifs)): ?>
                            <div class="notif-item px-3 pt-2 text-muted small" style="border-top: 1px solid #eee; background: #fafafa;">
                              Unread Messages
                            </div>
                            <?php foreach ($msgNotifs as $m): ?>
                                <div class="notif-item px-3 py-2">
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>:</strong>
                                        <?php 
                                          $message = htmlspecialchars($m['message']);
                                          echo strlen($message) > 35 ? substr($message, 0, 35) . '...' : $message;
                                        ?>
                                    </p>
                                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($m['sent_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div> 
                <?php endif; ?>
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
        <div class="dash-card chat-card">
          <div class="chat-layout">
            
            <div class="user-list">
              <div class="user-list-header">Clients</div>
             <div class="user-list-body" id="userListContainer">
  <?php while ($user = $users_result->fetch_assoc()) { 
      $displayName = trim($user['first_name'] . ' ' . $user['last_name']);
      if ($displayName === '') {
          $displayName = $user['email'] ?? 'Unknown User';
      }
      $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
  ?>
    <div 
      class="user-item" 
      data-user-id="<?php echo $user['id']; ?>" 
      data-user-name="<?php echo $safeName; ?>"
      onclick="openChat(<?php echo $user['id']; ?>, '<?php echo $safeName; ?>')"
    >
      <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="User">
     <div class="user-item-info">
  <strong><?php echo $safeName; ?></strong>
  <small><?php echo htmlspecialchars($user['email']); ?></small>
</div>

<?php if (isset($user['unread_count']) && $user['unread_count'] > 0): ?>
    <span class="user-unread-badge">
        <?php echo $user['unread_count']; ?>
    </span>
<?php endif; ?>
    </div>
  <?php } ?>
</div>

            </div>

            <div class="chat-box">
              <div class="chat-header" id="chatHeader">Select a client to start chatting</div>
              <div class="chat-messages" id="chatMessages">
                <div class="chat-placeholder">
                  <i class="fas fa-comments"></i>
                  <p>Select a conversation from the list on the left to view messages.</p>
                </div>
              </div>
              <div class="chat-input" id="chatInput" style="display: none;">
                <input type="text" id="messageText" class="form-control" placeholder="Type your message...">
                <button class="btn btn-primary" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
              </div>
            </div>

          </div>
        </div>
      </div> 

    </main>
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

  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

  // Mark messages as read (affects the chat icon badge)
  fetch(`../api/mark_messages_read.php?user_id=${selectedUserId}&admin_id=${adminId}`)
    .then(res => res.json())
    .then(data => {
        // Update the chat icon badge count
        const chatBadge = document.querySelector('.chat-notif .notif-count');
        if (chatBadge) {
            if (data.new_total_unread > 0) {
                chatBadge.textContent = data.new_total_unread;
            } else {
                chatBadge.remove();
            }
        }
        // We also need to update the main bell notification badge
        // This is harder as it requires re-fetching all notif types
        // For now, we'll just remove the message part
        // TODO: A more robust solution would reload the bell notif count
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

// --- Live Search for User List ---
document.getElementById('searchInput').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const users = document.querySelectorAll('#userListContainer .user-item');
    
    users.forEach(user => {
        const name = user.getAttribute('data-user-name').toLowerCase();
        if (name.includes(filter)) {
            user.style.display = '';
        } else {
            user.style.display = 'none';
        }
    });
});

</script>
</body>
</html>