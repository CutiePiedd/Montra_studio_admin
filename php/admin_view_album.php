<?php
session_start();
require_once '../api/db_connect.php';

// --- Start: PHP from packages.php (Template) ---

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch admin details (Combined for efficiency)
$adminQuery = "SELECT name, email, address, contact_number FROM admins WHERE id = ?";
$stmt = $conn->prepare($adminQuery);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    if (!$admin) {
        $admin = ['name' => 'Unknown', 'email' => 'N/A', 'address' => 'N/A', 'contact_number' => 'N/A'];
    }
    $stmt->close();
} else {
    die("Query failed: " . $conn->error);
}

// Fetch notifications
$notifQuery = "SELECT id, message, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($notifQuery);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifs = $stmt->get_result();
$notifCount = $notifs->num_rows;

// --- End: PHP from packages.php ---


// --- Start: PHP from admin_view_album.php (Content) ---

// CHANGED: Don't 'die'. Set user_id to null if not present.
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Fetch all users for the dropdown (this runs always)
$all_users_query = "SELECT id, first_name, last_name FROM users ORDER BY last_name, first_name";
$all_users_result = $conn->query($all_users_query);
$all_users = []; // Initialize
if ($all_users_result && $all_users_result->num_rows > 0) {
    while ($row = $all_users_result->fetch_assoc()) {
        $all_users[] = $row;
    }
}

// Initialize variables for the "no user selected" state
$user = null;
$images = null; // We'll use this to check later
$album_id = null;
$filter = $_GET['filter'] ?? '';

// CHANGED: Only run user-specific logic if a user_id IS provided
if ($user_id) {
    // Fetch user info
    $user_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name, email FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user = $user_result->fetch_assoc();

    // Only proceed if the user is valid
    if ($user) {
        // Check if album exists
        $album_query = $conn->prepare("SELECT id FROM albums WHERE user_id = ?");
        $album_query->bind_param("i", $user_id);
        $album_query->execute();
        $album_result = $album_query->get_result();

        if ($album_result->num_rows === 0) {
            // Create a new album if not exists
            $album_name = $user['full_name'] . "'s Album";
            $insert_album = $conn->prepare("INSERT INTO albums (user_id, album_name) VALUES (?, ?)");
            $insert_album->bind_param("is", $user_id, $album_name);
            $insert_album->execute();
            $album_id = $insert_album->insert_id;
        } else {
            $album_id = $album_result->fetch_assoc()['id'];
        }

        // Handle image upload
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
            $uploadDir = "../uploads/albums/$user_id/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $album_type = $_POST['album_type'];
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $file_name = basename($_FILES['images']['name'][$key]);
                $target_file = $uploadDir . time() . "_" . $file_name;
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $image_path = "uploads/albums/$user_id/" . time() . "_" . $file_name;
                    $stmt = $conn->prepare("INSERT INTO album_images (album_id, image_path, album_type) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $album_id, $image_path, $album_type);
                    $stmt->execute();
                }
            }
            header("Location: admin_view_album.php?user_id=$user_id");
            exit();
        }

        // Handle delete request
        if (isset($_GET['delete'])) {
            $image_id = intval($_GET['delete']);
            $img_query = $conn->prepare("SELECT image_path FROM album_images WHERE id = ?");
            $img_query->bind_param("i", $image_id);
            $img_query->execute();
            $img_result = $img_query->get_result();
            $image = $img_result->fetch_assoc();
            if ($image) {
                $filePath = "../" . $image['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $delete_query = $conn->prepare("DELETE FROM album_images WHERE id = ?");
                $delete_query->bind_param("i", $image_id);
                $delete_query->execute();
            }
            header("Location: admin_view_album.php?user_id=$user_id");
            exit();
        }

        // Fetch images for gallery (with filter)
        if (!empty($filter)) {
            $images_query = $conn->prepare("SELECT id, image_path, uploaded_at, album_type FROM album_images WHERE album_id = ? AND album_type = ? ORDER BY uploaded_at DESC");
            $images_query->bind_param("is", $album_id, $filter);
        } else {
            $images_query = $conn->prepare("SELECT id, image_path, uploaded_at, album_type FROM album_images WHERE album_id = ? ORDER BY album_type ASC, uploaded_at DESC");
            $images_query->bind_param("i", $album_id);
        }
        $images_query->execute();
        $images = $images_query->get_result();

    } else {
        // Handle case where user_id is invalid (e.g., user_id=99999)
        $user_id = null; // Reset user_id to null to trigger default state
    }
}
// --- End: PHP from admin_view_album.php ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Admin | <?= $user ? htmlspecialchars($user['full_name']) . "'s Album" : 'User Albums' ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard-design.css"> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <style>
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px; 
        }
        .gallery-card {
            position: relative;
            background-color: var(--card-bg, #fff);
            border-radius: var(--card-radius, 8px);
            border: 1px solid var(--border-color, #eee);
            box-shadow: var(--shadow, 0 4px 10px rgba(0,0,0,0.08));
            overflow: hidden;
            height: 220px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .gallery-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(231, 76, 60, 0.85);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            backdrop-filter: blur(3px);
            transition: background 0.2s ease;
        }
        .delete-btn:hover {
            background: #e74c3c;
        }
        .gallery-header {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color, #eee);
            text-transform: capitalize;
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
                <li class="nav-item">
                    <a href="admin_chat.php" class="nav-link">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_view_album.php" class="nav-link active">
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
                    <h2><?= $user ? htmlspecialchars($user['full_name']) . "'s Album" : 'User Albums' ?></h2>
                    <p><?= $user ? 'Manage photos for ' . htmlspecialchars($user['email']) : 'Select a user to manage their album' ?></p>
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

                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-light border-0 pt-3 pb-0">
                        <h5 class="card-title">Manage Album</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <form method="GET" action="">
                                    <div class="mb-3">
                                        <label for="user_selector" class="form-label fw-bold">Switch to User Album:</label>
                                        <select name="user_id" id="user_selector" class="form-select" onchange="this.form.submit()">
                                            <option value="">-- Select a User --</option>
                                            <?php if (empty($all_users)): ?>
                                                <option value="" disabled>No users found</option>
                                            <?php else: ?>
                                                <?php foreach ($all_users as $user_option): ?>
                                                    <option value="<?= $user_option['id'] ?>" <?= ($user_option['id'] == $user_id) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($user_option['last_name'] . ', ' . $user_option['first_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </form>
                                
                                <?php if (!$user): ?>
                                    <div class="alert alert-info mt-3" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Please select a user from the dropdown to upload images or manage their gallery.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <?php if ($user): ?>
                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                        <div class="mb-3">
                                            <label for="album_type" class="form-label fw-bold">1. Select Album Type:</label>
                                            <select name="album_type" id="album_type" class="form-select" required>
                                                <option value="maincharacter">Main Character</option>
                                                <option value="couple">Couple</option>
                                                <option value="family">Family</option>
                                                <option value="squad">Squad</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="images" class="form-label fw-bold">2. Choose Images (Multiple):</label>
                                            <input type="file" name="images[]" id="images" class="form-control" multiple required>
                                        </div>
                                        <button type="submit" name="upload" class="btn btn-primary"><i class="fa-solid fa-upload me-2"></i>Upload Images</button>
                                    </form>
                                    
                                    <hr>

                                    <form method="GET" action="">
                                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                        <div class="mb-3">
                                            <label for="filter_type" class="form-label fw-bold">Filter by Album Type:</label>
                                            <select name="filter" id="filter_type" class="form-select" onchange="this.form.submit()">
                                                <option value="">Show All</option>
                                                <option value="maincharacter" <?= $filter === 'maincharacter' ? 'selected' : '' ?>>Main Character</option>
                                                <option value="couple" <?= $filter === 'couple' ? 'selected' : '' ?>>Couple</option>
                                                <option value="family" <?= $filter === 'family' ? 'selected' : '' ?>>Family</option>
                                                <option value="squad" <?= $filter === 'squad' ? 'selected' : '' ?>>Squad</option>
                                            </select>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($user): ?>
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-light border-0 pt-3 pb-0">
                            <h5 class="card-title">Photo Gallery</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // CHANGED: Check $images is not null first
                            if ($images && $images->num_rows > 0):
                                $current_type = '';
                                while ($img = $images->fetch_assoc()):
                                    if (empty($filter) && $current_type !== $img['album_type']):
                                        if ($current_type !== '') echo "</div>"; // close previous grid
                                        $current_type = $img['album_type'];
                                        echo "<h3 class='gallery-header'>$current_type</h3>";
                                        echo "<div class='gallery-grid'>";
                                    endif;

                                    if (!empty($filter) && $current_type === ''):
                                        $current_type = $img['album_type']; // set flag
                                        echo "<h3 class='gallery-header'>Filtered: $current_type</h3>";
                                        echo "<div class='gallery-grid'>";
                                    endif;
                            ?>
                                    <div class="gallery-card">
                                        <img src="../<?= htmlspecialchars($img['image_path']) ?>" alt="Album Image">
                                        <a class="delete-btn" href="?user_id=<?= $user_id ?>&delete=<?= $img['id'] ?>" onclick="return confirm('Are you sure you want to delete this image?');">
                                            <i class="fa-solid fa-trash-can me-1"></i>Delete
                                        </a>
                                    </div>
                            <?php
                                endwhile;
                                echo "</div>"; // close the last grid
                            else:
                                echo "<p class='text-center text-muted mt-3'>No images found for this user.</p>";
                            endif;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

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

    
    <script>
        // --- Password Modal Script ---
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

            document.addEventListener('click', (e) => {
                if (notifDropdown && !notifDropdown.contains(e.target)) {
                    notifDropdown.classList.remove('show');
                }
                if (searchDropdown && !searchDropdown.contains(e.target)) {
                    searchDropdown.classList.remove('show');
                }
            });

            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            const searchBox = document.getElementById('searchBox');

            if(searchInput) {
                searchInput.addEventListener('input', async () => {
                    const query = searchInput.value.trim();

                    if (query.length < 2) {
                        searchResults.innerHTML = '<p class="text-muted px-2 py-1">Type to search...</p>';
                        return;
                    }

                    try {
                        const response = await fetch(`../php/admin_search.php?query=${encodeURIComponent(query)}`);
                        const text = await response.text(); 
                        
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error("Invalid JSON response:", text);
                            searchResults.innerHTML = '<p class="text-danger px-2 py-1">Error: Invalid response</p>';
                            return;
                        }

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
                                <a href="${item.link}" class="text-decoration-none text-dark d-block px-2">
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
            }
        });
    </script>
</body>
</html>