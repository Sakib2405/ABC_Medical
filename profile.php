<?php
session_start();

// --- DATABASE CONNECTION (Use your actual connection details) ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical'; // Ensure this is your database name

$conn = null;
// Establish connection for both display and update logic
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        // In a production environment, you might redirect to a generic error page
        die("Database connection error. Please try again later.");
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error via Exception: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}


// --- Fetch Current User Data ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_user_id = $_SESSION['user_id'];
$user_data = null;

$stmt_fetch = $conn->prepare("SELECT id, name, email, role, profile_pic_url, created_at FROM users WHERE id = ?");
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $current_user_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        // Update session with potentially fresh data from DB for consistency
        $_SESSION['user_name'] = $user_data['name'];
        $_SESSION['user_email'] = $user_data['email'];
        $_SESSION['user_profile_pic'] = $user_data['profile_pic_url']; // Keep this updated for header/dropdowns
    } else {
        // User not found in DB despite session, invalidate session and redirect
        session_destroy();
        header("Location: login.php?error=user_not_found");
        exit;
    }
    $stmt_fetch->close();
} else {
    error_log("Error preparing statement to fetch user data: " . $conn->error);
    die("A system error occurred. Please try again.");
}

// Initialize form variables with current data
$name = $user_data['name'];
$email = $user_data['email']; // Email is usually non-editable directly
$profile_pic_url = $user_data['profile_pic_url'] ?? ''; // Handle NULL case

$errors = [];
$success_message = '';

// --- Configuration for File Upload ---
// IMPORTANT: Create this directory relative to your script and ensure it's writable (e.g., chmod 755 uploads)
$upload_directory = 'uploads/profile_pics/';
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_file_size = 2 * 1024 * 1024; // 2 MB (in bytes)

// --- Handle Form Submission for Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    // Get the current profile pic path stored in a hidden field from previous render
    $current_profile_pic_on_server = $_POST['current_profile_pic_url_hidden'] ?? '';

    // Basic Validation for Name
    if (empty($name)) {
        $errors[] = "Full name is required.";
    }

    // Default to current picture path; this will be updated if a new file is uploaded
    $new_profile_pic_path_for_db = $current_profile_pic_on_server;

    // Handle File Upload for profile_pic
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['profile_pic']['tmp_name'];
        $file_name_original = $_FILES['profile_pic']['name']; // Original name, not for saving
        $file_size = $_FILES['profile_pic']['size'];
        $file_type = mime_content_type($file_tmp_name); // More reliable MIME type detection
        $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($file_type, $allowed_mime_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, and GIF images are allowed. Detected: " . htmlspecialchars($file_type);
        }

        // Validate file size
        if ($file_size > $max_file_size) {
            $errors[] = "File size (" . round($file_size / 1024 / 1024, 2) . " MB) exceeds the 2MB limit.";
        }

        if (empty($errors)) {
            // Generate a unique filename to prevent overwrites and security issues
            $new_generated_file_name = uniqid('profile_', true) . '.' . $file_ext;
            $destination_path = $upload_directory . $new_generated_file_name;

            // Ensure upload directory exists
            if (!is_dir($upload_directory)) {
                if (!mkdir($upload_directory, 0755, true)) { // Create recursively, with 755 permissions
                    $errors[] = "Failed to create upload directory. Contact administrator.";
                    error_log("Failed to create directory: " . $upload_directory);
                }
            }

            if (empty($errors)) { // Check errors again after directory creation attempt
                if (move_uploaded_file($file_tmp_name, $destination_path)) {
                    $new_profile_pic_path_for_db = $destination_path; // This is the path to save in DB

                    // Delete old profile picture if a new one was uploaded and the old one exists on server
                    // AND ensure it's in our designated upload directory for security
                    if (!empty($current_profile_pic_on_server) &&
                        file_exists($current_profile_pic_on_server) &&
                        strpos($current_profile_pic_on_server, $upload_directory) === 0) {
                        
                        if (unlink($current_profile_pic_on_server)) {
                            // Successfully deleted old file
                        } else {
                            error_log("Failed to delete old profile pic: " . $current_profile_pic_on_server);
                            // Not critical enough to show user error, but log it
                        }
                    }
                } else {
                    $errors[] = "Error uploading file. Please try again. (File move failed: " . $_FILES['profile_pic']['error'] . ")";
                    error_log("Move uploaded file error: " . $_FILES['profile_pic']['error']);
                }
            }
        }
    } elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors (e.g., UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE)
        $php_upload_errors = [
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the PHP configured size.",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the form's MAX_FILE_SIZE directive.",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder for upload.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload.",
        ];
        $errors[] = $php_upload_errors[$_FILES['profile_pic']['error']] ?? "An unknown file upload error occurred.";
    } else {
        // No new file uploaded, check if user explicitly requested to remove the picture
        if (isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] === 'yes') {
            if (!empty($current_profile_pic_on_server) &&
                file_exists($current_profile_pic_on_server) &&
                strpos($current_profile_pic_on_server, $upload_directory) === 0) { // Safety check
                
                if (unlink($current_profile_pic_on_server)) {
                    $new_profile_pic_path_for_db = NULL; // Set to NULL in DB
                } else {
                    $errors[] = "Failed to remove existing profile picture. Please try again.";
                    error_log("Failed to unlink file during removal: " . $current_profile_pic_on_server);
                }
            } else {
                $new_profile_pic_path_for_db = NULL; // Already no pic or not a server-managed pic
            }
        }
    }

    // --- Final Database Update for Name and Profile Picture ---
    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE users SET name = ?, profile_pic_url = ? WHERE id = ?");
        if ($stmt_update) {
           // If profile_pic_to_save is empty string, convert to NULL for DB
           $profile_pic_to_save_for_db = !empty($new_profile_pic_path_for_db) ? $new_profile_pic_path_for_db : NULL;

           $stmt_update->bind_param("ssi", $name, $profile_pic_to_save_for_db, $current_user_id);
           if ($stmt_update->execute()) {
               $success_message = "Profile updated successfully!";
               // Refresh user_data array to show updated info on the page immediately
               $user_data['name'] = $name;
               $user_data['profile_pic_url'] = $profile_pic_to_save_for_db; // Update for display
               $_SESSION['user_name'] = $name; // Update session for dynamic header/dropdowns
               $_SESSION['user_profile_pic'] = $profile_pic_to_save_for_db; // Update session for dynamic header/dropdowns
           } else {
               $errors[] = "Error updating profile in database: " . $stmt_update->error;
               error_log("Profile DB update error: " . $stmt_update->error);
           }
           $stmt_update->close();
        } else {
           $errors[] = "Database error: Could not prepare update statement for profile. " . $conn->error;
           error_log("Profile update prepare error: " . $conn->error);
        }
    }
}
$conn->close(); // Close connection after all operations are done
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?= htmlspecialchars($user_data['name'] ?? 'User'); ?></title>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add some basic styling for the new elements if not in profile.css */
        .profile-actions {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .button-action, .button-homepage { /* Unified style for action buttons */
            display: block; /* Make buttons block for better stacking */
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-weight: bold;
        }
        .button-action:hover, .button-homepage:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        .form-actions-main {
            margin-top: 20px;
        }
        .button-primary {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-weight: bold;
        }
        .button-primary:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .feedback-message {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            animation: fadeInMessage 0.5s ease-out;
        }
        .feedback-message ul {
            margin: 0;
            padding-left: 20px;
            list-style-type: disc;
        }
        .feedback-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .feedback-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Basic styling for sidebar and main content if not fully covered by profile.css */
        .profile-container {
            display: flex;
            max-width: 1200px;
            margin: 40px auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden; /* To contain shadows and rounded corners */
        }
        .profile-page-header {
            background-color: #007bff;
            color: white;
            padding: 30px;
            text-align: center;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            margin-bottom: 20px;
        }
        .profile-page-header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        .profile-page-header p {
            margin: 5px 0 0;
            font-size: 1.1em;
            opacity: 0.9;
        }

        .profile-sidebar {
            flex: 0 0 280px; /* Fixed width sidebar */
            background-color: #f8f9fa;
            padding: 30px;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-main-content {
            flex-grow: 1; /* Main content takes remaining space */
            padding: 30px;
        }

        .profile-picture-container {
            margin-bottom: 25px;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 0 0 3px #007bff, 0 5px 15px rgba(0,0,0,0.1);
        }
        .user-id, .user-role {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .profile-meta {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .profile-meta p {
            margin: 5px 0;
            color: #555;
            font-size: 0.95em;
        }
        .profile-section {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        .profile-section h2 {
            font-size: 1.8em;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f2f2f2;
        }
        .profile-form .form-group {
            margin-bottom: 18px;
        }
        .profile-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #444;
        }
        .profile-form input[type="text"],
        .profile-form input[type="email"],
        .profile-form input[type="file"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box; /* Include padding in width */
            transition: border-color 0.3s ease;
        }
        .profile-form input[type="text"]:focus,
        .profile-form input[type="email"]:focus,
        .profile-form input[type="file"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .profile-form input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .profile-form small {
            display: block;
            margin-top: 5px;
            font-size: 0.85em;
            color: #6c757d;
        }
        .profile-form input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2); /* Make checkbox slightly larger */
        }
        .profile-form .form-group label[for="remove_profile_pic"] {
            display: inline-block;
            font-weight: normal;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-container {
                flex-direction: column;
                margin: 20px;
            }
            .profile-sidebar {
                border-right: none;
                border-bottom: 1px solid #eee;
                padding-bottom: 20px;
            }
            .profile-page-header {
                border-radius: 8px 8px 0 0;
                margin-bottom: 0; /* Adjust margin if header is inside container */
            }
            .profile-main-content {
                padding: 20px;
            }
            .profile-actions {
                flex-direction: row; /* Buttons side-by-side on smaller screens */
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
            }
            .button-action, .button-homepage {
                flex: 1 1 auto; /* Allow buttons to grow/shrink */
                max-width: calc(50% - 4px); /* Two buttons per row */
            }
        }
        @keyframes fadeInMessage {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <header class="profile-page-header">
            <h1>My Profile</h1>
            <p>View and manage your personal information.</p>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="feedback-message error">
                <strong><i class="fas fa-exclamation-circle"></i> Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="feedback-message success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-layout">
            <aside class="profile-sidebar">
                <div class="profile-picture-container">
                    <?php
                        // Construct the display URL for the profile picture
                        // If it's a relative path from the server, ensure it's accessible via web root.
                        // If $user_data['profile_pic_url'] is set to NULL in DB, it will be empty.
                        $display_pic_url = !empty($user_data['profile_pic_url'])
                            ? (strpos($user_data['profile_pic_url'], 'http') === 0 ? htmlspecialchars($user_data['profile_pic_url']) : '/' . htmlspecialchars($user_data['profile_pic_url']))
                            : 'https://placehold.co/150x150/EBF4FF/7F9CF5?text=U&font=montserrat'; // Default placeholder
                    ?>
                    <img src="<?= $display_pic_url; ?>" alt="Profile Picture" class="profile-picture">
                     <p class="user-id">User ID: <?= htmlspecialchars($user_data['id']); ?></p>
                     <p class="user-role">Role: <?= htmlspecialchars(ucfirst($user_data['role'])); // Capitalize first letter ?></p>
                </div>
                <div class="profile-meta">
                     <p><strong>Member Since:</strong>
                        <?php
                            try {
                                $created_at_date = new DateTime($user_data['created_at'], new DateTimeZone('UTC')); // Assuming created_at is UTC
                                $created_at_date->setTimezone(new DateTimeZone('Asia/Dhaka')); // Convert to Dhaka time
                                echo $created_at_date->format('F j, Y');
                            } catch (Exception $e) {
                                echo 'N/A'; // Handle potential date format errors
                                error_log("Date parsing error on profile page: " . $e->getMessage());
                            }
                        ?>
                     </p>
                </div>
                 <div class="profile-actions">
                    <a href="settings.php" class="button-action">Account Settings</a>
                    <a href="appointments.php" class="button-action">My Appointments</a>
                    <a href="medical_records.php" class="button-action">Medical Records</a>
                    <?php if ($user_data['role'] === 'admin'): ?>
                        <a href="admin_dashboard.php" class="button-action">Admin Dashboard</a>
                        <a href="admin_user_management.php" class="button-action">Manage Users</a>
                    <?php endif; ?>
                    <a href="logout.php" class="button-action logout-link">Logout</a>
                    <a href="index.php" class="button-homepage"><i class="fas fa-home"></i> Go to Homepage</a>
                </div>
            </aside>

            <main class="profile-main-content">
                <section class="profile-section" id="personal-info">
                    <h2>Personal Information</h2>
                    <form action="profile.php" method="POST" class="profile-form" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="current_profile_pic_url_hidden" value="<?= htmlspecialchars($user_data['profile_pic_url'] ?? ''); ?>">


                        <div class="form-group">
                            <label for="name">Full Name:</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user_data['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email_display">Email Address:</label>
                            <input type="email" id="email_display" name="email_display" value="<?= htmlspecialchars($user_data['email']); ?>" readonly>
                            <small>Email address cannot be changed here. Please contact support if necessary.</small>
                        </div>

                        <div class="form-group">
                            <label for="profile_pic">Upload New Profile Picture:</label>
                            <input type="file" id="profile_pic" name="profile_pic" accept="image/jpeg, image/png, image/gif">
                            <small>Max 2MB. Allowed formats: JPG, PNG, GIF. Leave empty to keep current or check "Remove" to clear.</small>
                        </div>

                        <?php if (!empty($user_data['profile_pic_url'])): // Only show remove option if a pic exists ?>
                        <div class="form-group">
                            <input type="checkbox" id="remove_profile_pic" name="remove_profile_pic" value="yes">
                            <label for="remove_profile_pic">Remove current profile picture</label>
                            <small>Check this box and click Save Changes to remove your existing picture.</small>
                        </div>
                        <?php endif; ?>

                        <div class="form-actions-main">
                            <button type="submit" class="button-primary">Save Changes</button>
                        </div>
                    </form>
                </section>

            </main>
        </div>
    </div>
</body>
</html>