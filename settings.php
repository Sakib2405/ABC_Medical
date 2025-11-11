<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user']);
$page_title = "User Settings - ABC Medical";

$profile_update_message = "";
$password_change_message = "";
$profile_pic_message = "";
$two_fa_message = "";
$delete_account_message = "";

// Fetch current user data to pre-fill forms
$current_user_name = "";
$current_user_email = "";
$current_profile_pic_url = "https://placehold.co/150x150/EBF4FF/7F9CF5?text=" . strtoupper(substr($user_name, 0, 1)) . "&font=montserrat"; // Default placeholder

$stmt_user = $conn->prepare("SELECT name, email, profile_pic_url FROM users WHERE id = ?");
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($user_data = $result_user->fetch_assoc()) {
        $current_user_name = htmlspecialchars($user_data['name']);
        $current_user_email = htmlspecialchars($user_data['email']);
        if (!empty($user_data['profile_pic_url'])) {
            $current_profile_pic_url = htmlspecialchars($user_data['profile_pic_url']);
        }
        $_SESSION['user'] = $user_data['name']; 
    }
    $stmt_user->close();
} else {
    error_log("Error fetching user data for settings: " . $conn->error);
}


// Handle Profile Information Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $errors = [];
    if (empty($new_name)) {
        $errors[] = "Name cannot be empty.";
    }

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("si", $new_name, $user_id);
            if ($stmt_update->execute()) {
                $_SESSION['user'] = $new_name; 
                $current_user_name = $new_name; 
                $profile_update_message = "<div class='message success-message-settings'>Profile updated successfully!</div>";
            } else {
                error_log("Profile Update Error: " . $stmt_update->error);
                $profile_update_message = "<div class='message error-message-settings'>Could not update profile. Please try again.</div>";
            }
            $stmt_update->close();
        } else {
            error_log("Profile Update Prepare Error: " . $conn->error);
            $profile_update_message = "<div class='message error-message-settings'>An error occurred. Please try again later.</div>";
        }
    } else {
        $profile_update_message = "<div class='message error-message-settings'><ul>";
        foreach ($errors as $error) {
            $profile_update_message .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $profile_update_message .= "</ul></div>";
    }
}

// Handle Profile Picture Upload (Placeholder - actual upload needs more complex handling)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        // In a real app:
        // 1. Validate file type, size.
        // 2. Generate a unique filename.
        // 3. Move uploaded file to a secure directory (e.g., 'uploads/profile_pictures/').
        // 4. Update the 'profile_pic_url' in the 'users' table with the new file path/URL.
        // $target_dir = "uploads/profile_pictures/";
        // $new_filename = uniqid() . basename($_FILES["profile_picture"]["name"]);
        // $target_file = $target_dir . $new_filename;
        // if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
        //    $stmt_pic = $conn->prepare("UPDATE users SET profile_pic_url = ? WHERE id = ?");
        //    $stmt_pic->bind_param("si", $target_file, $user_id);
        //    $stmt_pic->execute(); $stmt_pic->close();
        //    $current_profile_pic_url = $target_file; // Update for display
        //    $profile_pic_message = "<div class='message success-message-settings'>Profile picture updated! (Placeholder)</div>";
        // } else { $profile_pic_message = "<div class='message error-message-settings'>Error uploading file. (Placeholder)</div>"; }
        $profile_pic_message = "<div class='message success-message-settings'>Profile picture 'uploaded' (demo only). In a real app, this would be saved.</div>";
        // For demo, let's just show a generic placeholder update
        $current_profile_pic_url = "https://placehold.co/150x150/90EE90/FFFFFF?text=" . strtoupper(substr($current_user_name, 0, 1)) . "&font=montserrat";


    } else {
        $profile_pic_message = "<div class='message error-message-settings'>Please select a file to upload.</div>";
    }
}


// Handle Password Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];
    // (Validation logic as before) ...
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirm password do not match.";
    }
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    }

    if (empty($errors)) {
        $stmt_fetch_pass = $conn->prepare("SELECT password FROM users WHERE id = ?");
        if ($stmt_fetch_pass) {
            $stmt_fetch_pass->bind_param("i", $user_id);
            $stmt_fetch_pass->execute();
            $result_pass = $stmt_fetch_pass->get_result();
            if ($user_pass_data = $result_pass->fetch_assoc()) {
                if (password_verify($current_password, $user_pass_data['password'])) {
                    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt_update_pass) {
                        $stmt_update_pass->bind_param("si", $new_password_hashed, $user_id);
                        if ($stmt_update_pass->execute()) {
                            $password_change_message = "<div class='message success-message-settings'>Password changed successfully!</div>";
                        } else { /* error handling */ $password_change_message = "<div class='message error-message-settings'>Could not change password.</div>"; }
                        $stmt_update_pass->close();
                    } else { /* error handling */ $password_change_message = "<div class='message error-message-settings'>Error preparing password update.</div>"; }
                } else {
                    $password_change_message = "<div class='message error-message-settings'>Incorrect current password.</div>";
                }
            }
            $stmt_fetch_pass->close();
        } else { /* error handling */ $password_change_message = "<div class='message error-message-settings'>Error fetching current password.</div>"; }
    } else {
        $password_change_message = "<div class='message error-message-settings'><ul>";
        foreach ($errors as $error) { $password_change_message .= "<li>" . htmlspecialchars($error) . "</li>"; }
        $password_change_message .= "</ul></div>";
    }
}

// Handle 2FA (Placeholder)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_2fa'])) {
    // In a real app: update 2FA status in DB, generate QR codes, etc.
    $current_2fa_status = isset($_POST['enable_2fa']) ? "Enabled" : "Disabled";
    $two_fa_message = "<div class='message success-message-settings'>Two-Factor Authentication is now " . $current_2fa_status . ". (Demo)</div>";
}

// Handle Delete Account (Placeholder)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account_confirm'])) {
    if (isset($_POST['confirm_delete_text']) && strtoupper(trim($_POST['confirm_delete_text'])) === "DELETE MY ACCOUNT") { // Made case-insensitive and trimmed
        // In a real app:
        // 1. Perform additional security checks (e.g., re-enter password).
        // 2. Soft delete or permanently delete user data based on policy.
        // 3. Invalidate session and redirect.
        $delete_account_message = "<div class='message warning-message-settings'>Account deletion initiated (Demo). You would be logged out.</div>";
        // session_destroy(); header("Location: login.php"); exit();
    } else {
        $delete_account_message = "<div class='message error-message-settings'>Confirmation text did not match. Account not deleted.</div>";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="profile.css" /> 
    <link rel="stylesheet" href="settings.css" /> 
</head>
<body>
    <div class="profile-page-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <i class="fas fa-clinic-medical"></i>
                    <span>ABC Medical</span>
                </a>
            </div>
        </aside>

        <main class="profile-main-content settings-main-content"> 
            <header class="profile-header">
                <h2>Account Settings</h2>
                <p class="header-subtitle">Manage your profile information, security, and preferences.</p>
            </header>

            <section class="settings-section profile-picture-section">
                <h3><i class="fas fa-user-tie"></i> Profile Picture</h3>
                <?php if (!empty($profile_pic_message)) echo $profile_pic_message; ?>
                <div class="current-profile-pic">
                    <img src="<?= $current_profile_pic_url ?>" alt="Current Profile Picture" id="profilePicPreview"
                         onerror="this.onerror=null;this.src='https://placehold.co/150x150/EBF4FF/7F9CF5?text=U&font=montserrat';">
                </div>
                <form action="settings.php" method="POST" class="settings-form" enctype="multipart/form-data">
                    <div class="form-group-settings">
                        <label for="profile_picture_input">Upload New Picture</label>
                        <input type="file" name="profile_picture" id="profile_picture_input" accept="image/png, image/jpeg, image/gif">
                        <small class="form-text">Recommended: Square image, max 2MB (JPG, PNG, GIF).</small>
                    </div>
                    <button type="submit" name="upload_picture" class="btn-submit-settings"><i class="fas fa-upload"></i> Upload Picture</button>
                </form>
            </section>

            <section class="settings-section update-profile-section">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                <?php if (!empty($profile_update_message)) echo $profile_update_message; ?>
                <form action="settings.php" method="POST" class="settings-form">
                    <div class="form-group-settings">
                        <label for="name">Full Name <span class="required-star">*</span></label>
                        <input type="text" name="name" id="name" value="<?= $current_user_name ?>" required>
                    </div>
                    <div class="form-group-settings">
                        <label for="email_display">Email Address</label>
                        <input type="email" name="email_display" id="email_display" value="<?= $current_user_email ?>" readonly class="readonly-input">
                        <small class="form-text">Email address cannot be changed here. Please contact support for assistance.</small>
                    </div>
                    <button type="submit" name="update_profile" class="btn-submit-settings"><i class="fas fa-save"></i> Save Profile Changes</button>
                </form>
            </section>

            <section class="settings-section change-password-section">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <?php if (!empty($password_change_message)) echo $password_change_message; ?>
                <form action="settings.php" method="POST" class="settings-form">
                    <div class="form-group-settings">
                        <label for="current_password">Current Password <span class="required-star">*</span></label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>
                    <div class="form-group-settings">
                        <label for="new_password">New Password <span class="required-star">*</span></label>
                        <input type="password" name="new_password" id="new_password" placeholder="Min. 6 characters" required>
                    </div>
                    <div class="form-group-settings">
                        <label for="confirm_password">Confirm New Password <span class="required-star">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-submit-settings"><i class="fas fa-exchange-alt"></i> Update Password</button>
                </form>
            </section>
            
            <section class="settings-section two-factor-section">
                <h3><i class="fas fa-shield-alt"></i> Two-Factor Authentication (2FA)</h3>
                <?php if (!empty($two_fa_message)) echo $two_fa_message; ?>
                <form action="settings.php" method="POST" class="settings-form">
                    <p>Enhance your account security by enabling Two-Factor Authentication.</p>
                    <div class="form-group-settings checkbox-group">
                        <input type="checkbox" name="enable_2fa" id="enable_2fa" <?= (isset($user_2fa_enabled) && $user_2fa_enabled) ? 'checked' : '' ?>>
                        <label for="enable_2fa">Enable Two-Factor Authentication</label>
                    </div>
                    <button type="submit" name="toggle_2fa" class="btn-submit-settings"><i class="fas fa-user-shield"></i> Update 2FA Status (Demo)</button>
                    <small class="form-text">You might be prompted to set up an authenticator app.</small>
                </form>
            </section>

            <section class="settings-section notification-preferences-section">
                <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                <form action="settings.php" method="POST" class="settings-form">
                    <div class="form-group-settings checkbox-group">
                        <input type="checkbox" name="email_notifications" id="email_notifications" checked>
                        <label for="email_notifications">Receive email notifications for appointment reminders.</label>
                    </div>
                     <div class="form-group-settings checkbox-group">
                        <input type="checkbox" name="sms_notifications" id="sms_notifications">
                        <label for="sms_notifications">Receive SMS notifications (if available).</label>
                    </div>
                    <div class="form-group-settings checkbox-group">
                        <input type="checkbox" name="newsletter_subscribe" id="newsletter_subscribe" checked>
                        <label for="newsletter_subscribe">Subscribe to our monthly health newsletter.</label>
                    </div>
                    <button type="submit" name="update_notifications" class="btn-submit-settings" disabled><i class="fas fa-bell-slash"></i> Update Notifications (Demo)</button>
                </form>
            </section>

            <section class="settings-section delete-account-section">
                <h3><i class="fas fa-trash-alt"></i> Delete Account</h3>
                <?php if (!empty($delete_account_message)) echo $delete_account_message; ?>
                <p class="warning-text"><strong>Warning:</strong> Deleting your account is permanent and cannot be undone. All your data, including medical records and appointment history associated with this account, will be permanently removed.</p>
                <form action="settings.php" method="POST" class="settings-form" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action is irreversible.');">
                    <div class="form-group-settings">
                        <label for="confirm_delete_text">To confirm, type "DELETE MY ACCOUNT" in the box below:</label>
                        <input type="text" name="confirm_delete_text" id="confirm_delete_text" required placeholder="DELETE MY ACCOUNT">
                    </div>
                    <button type="submit" name="delete_account_confirm" class="btn-delete-settings"><i class="fas fa-exclamation-triangle"></i> Delete My Account Permanently</button>
                </form>
            </section>

        </main>
    </div>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<script>
    // Preview profile picture before upload
    const profilePictureInput = document.getElementById('profile_picture_input');
    const profilePicPreview = document.getElementById('profilePicPreview');
    if (profilePictureInput && profilePicPreview) {
        profilePictureInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePicPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }
</script>
</body>
</html>
