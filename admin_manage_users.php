<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "Manage Users - ABC Medical Admin";

$action_message = ""; // For success/error messages from actions

// --- Handle potential actions (e.g., role change, delete user) ---
// This is simplified. In a real app, these would be more robust and likely on separate handler scripts or use POST requests.

if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $target_user_id = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);

    if ($target_user_id && $target_user_id != $_SESSION['admin_id']) { // Prevent admin from altering their own role/status this way easily
        if ($action === 'toggle_role') {
            // Fetch current role
            $stmt_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
            if ($stmt_role) {
                $stmt_role->bind_param("i", $target_user_id);
                $stmt_role->execute();
                $result_role = $stmt_role->get_result();
                if ($current_user = $result_role->fetch_assoc()) {
                    $new_role = ($current_user['role'] === 'admin') ? 'user' : 'admin';
                    $stmt_update_role = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                    if ($stmt_update_role) {
                        $stmt_update_role->bind_param("si", $new_role, $target_user_id);
                        if ($stmt_update_role->execute()) {
                            $action_message = "<div class='message success-message-admin-table'>User role updated successfully.</div>";
                        } else {
                            $action_message = "<div class='message error-message-admin-table'>Error updating user role.</div>";
                        }
                        $stmt_update_role->close();
                    }
                }
                $stmt_role->close();
            }
        } elseif ($action === 'delete_user') {
            // Implement soft delete or permanent delete with caution
            $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
             if ($stmt_delete) {
                $stmt_delete->bind_param("i", $target_user_id);
                if ($stmt_delete->execute()) {
                    $action_message = "<div class='message success-message-admin-table'>User deleted successfully.</div>";
                } else {
                    $action_message = "<div class='message error-message-admin-table'>Error deleting user. They might have related records.</div>";
                }
                $stmt_delete->close();
            }
        }
    } elseif ($target_user_id == $_SESSION['admin_id']) {
        $action_message = "<div class='message warning-message-admin-table'>Administrators cannot change their own role or delete their own account via this interface.</div>";
    }
}


// --- Fetch Users from Database ---
$users_list = [];
// Exclude the currently logged-in admin from the list if desired, or handle differently
// $current_admin_id = $_SESSION['admin_id'];
// $sql_users = "SELECT id, name, email, role, created_at FROM users WHERE id != ? ORDER BY created_at DESC";
$sql_users = "SELECT id, name, email, role, profile_pic_url, created_at FROM users ORDER BY name ASC";
$stmt_users = $conn->prepare($sql_users);

if ($stmt_users) {
    // $stmt_users->bind_param("i", $current_admin_id); // If excluding current admin
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $users_list[] = $row;
    }
    $stmt_users->close();
} else {
    error_log("Error fetching users: " . $conn->error);
    $action_message = "<div class='message error-message-admin-table'>Could not retrieve user list.</div>";
}

// $conn->close(); // Close at the end of the script
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_manage_users.css" /> </head>
<body>
    <div class="admin-page-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <a href="admin_dashboard.php" class="admin-sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <span>ABC Medical Admin</span>
                </a>
            </div>
            <nav class="admin-sidebar-nav">
                <a href="admin_dashboard.php" class="admin-nav-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
                <a href="admin_manage_users.php" class="admin-nav-item active"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
                <a href="admin_manage_doctors.php" class="admin-nav-item"><i class="fas fa-user-md"></i> <span>Manage Doctors</span></a>
                <a href="admin_manage_appointments.php" class="admin-nav-item"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a>
                <a href="admin_manage_services.php" class="admin-nav-item"><i class="fas fa-briefcase-medical"></i> <span>Services</span></a>
                <a href="admin_reports.php" class="admin-nav-item"><i class="fas fa-chart-line"></i> <span>Reports</span></a>
                <a href="admin_site_settings.php" class="admin-nav-item"><i class="fas fa-cogs"></i> <span>Site Settings</span></a>
                <a href="logout.php" class="admin-nav-item admin-logout-item"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </nav>
             <div class="admin-sidebar-footer">
                <p>&copy; <?= date("Y") ?> ABC Medical</p>
            </div>
        </aside>

        <main class="admin-main-content">
            <header class="admin-main-header">
                <div class="header-left">
                    <h1>Manage Users</h1>
                    <p class="header-breadcrumb">Admin Panel / Users</p>
                </div>
                <div class="header-right">
                     <a href="admin_add_user.php" class="btn-add-new"><i class="fas fa-user-plus"></i> Add New User</a>
                </div>
            </header>

            <?php if (!empty($action_message)) echo $action_message; ?>

            <section class="admin-content-section">
                <div class="table-container-admin">
                    <table class="admin-table users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users_list)): ?>
                                <?php foreach ($users_list as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td>
                                        <img src="<?= !empty($user['profile_pic_url']) ? htmlspecialchars($user['profile_pic_url']) : 'https://placehold.co/40x40/CBD5E0/4A5568?text=' . strtoupper(substr($user['name'],0,1)) . '&font=montserrat' ?>" 
                                             alt="<?= htmlspecialchars($user['name']) ?>" class="user-table-photo"
                                             onerror="this.onerror=null;this.src='https://placehold.co/40x40/CBD5E0/4A5568?text=U';">
                                    </td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="role-badge role-<?= strtolower(htmlspecialchars($user['role'])) ?>">
                                            <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date("M d, Y", strtotime($user['created_at'])) ?></td>
                                    <td class="action-buttons-admin">
                                        <a href="admin_edit_user.php?user_id=<?= $user['id'] ?>" class="btn-action-admin edit" title="Edit User"><i class="fas fa-edit"></i></a>
                                        <?php if ($user['id'] != $_SESSION['admin_id']): // Prevent deleting self ?>
                                        <a href="admin_manage_users.php?action=toggle_role&user_id=<?= $user['id'] ?>" 
                                           class="btn-action-admin role-toggle <?= ($user['role'] === 'admin') ? 'demote' : 'promote' ?>" 
                                           title="<?= ($user['role'] === 'admin') ? 'Demote to User' : 'Promote to Admin' ?>"
                                           onclick="return confirm('Are you sure you want to <?= ($user['role'] === 'admin') ? 'demote this admin to user' : 'promote this user to admin' ?>?');">
                                            <i class="fas <?= ($user['role'] === 'admin') ? 'fa-user-slash' : 'fa-user-shield' ?>"></i>
                                        </a>
                                        <a href="admin_manage_users.php?action=delete_user&user_id=<?= $user['id'] ?>" 
                                           class="btn-action-admin delete" title="Delete User" 
                                           onclick="return confirm('Are you sure you want to PERMANENTLY delete this user? This action cannot be undone.');">
                                           <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <?php else: ?>
                                            <span class="self-admin-note" title="Cannot modify own account here"><i class="fas fa-lock"></i></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-records-admin">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
</body>
</html>
