<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "Add New User - ABC Medical Admin";

$message_html = ""; // For success/error messages

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $new_user_name = trim($_POST['name']);
    $new_user_email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $new_user_password_plain = $_POST['password'];
    $new_user_confirm_password = $_POST['confirm_password'];
    $new_user_role = $_POST['role']; // 'user' or 'admin'

    $errors = [];

    // Validation
    if (empty($new_user_name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($new_user_email)) {
        $errors[] = "Email Address is required.";
    } elseif (!filter_var($new_user_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $new_user_email);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $errors[] = "This email address is already registered.";
            }
            $stmt_check_email->close();
        } else {
            $errors[] = "Error checking email uniqueness.";
            error_log("Email check prepare error: " . $conn->error);
        }
    }

    if (empty($new_user_password_plain)) {
        $errors[] = "Password is required.";
    } elseif (strlen($new_user_password_plain) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($new_user_password_plain !== $new_user_confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($new_user_role) || !in_array($new_user_role, ['user', 'admin'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        $password_hashed = password_hash($new_user_password_plain, PASSWORD_DEFAULT);
        
        $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt_insert) {
            $stmt_insert->bind_param("ssss", $new_user_name, $new_user_email, $password_hashed, $new_user_role);
            if ($stmt_insert->execute()) {
                $message_html = "<div class='message success-message-admin-form'>User '" . htmlspecialchars($new_user_name) . "' added successfully as " . ucfirst($new_user_role) . ".</div>";
                // Optionally clear form fields or redirect
                // For now, we'll just show the message and keep the form.
            } else {
                error_log("User Insert Execute Error: " . $stmt_insert->error);
                $message_html = "<div class='message error-message-admin-form'>Could not add user. Please try again.</div>";
            }
            $stmt_insert->close();
        } else {
            error_log("User Insert Prepare Error: " . $conn->error);
            $message_html = "<div class='message error-message-admin-form'>An error occurred. Please try again later.</div>";
        }
    } else {
        $message_html = "<div class='message error-message-admin-form'><ul>";
        foreach ($errors as $error) {
            $message_html .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $message_html .= "</ul></div>";
    }
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
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_add_user.css" /> </head>
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
                    <h1>Add New User</h1>
                    <p class="header-breadcrumb">Admin Panel / Manage Users / Add User</p>
                </div>
                <div class="header-right">
                     <a href="admin_manage_users.php" class="btn-back-admin"><i class="fas fa-arrow-left"></i> Back to User List</a>
                </div>
            </header>

            <?php if (!empty($message_html)) echo $message_html; ?>

            <section class="admin-content-section add-user-form-section">
                <form action="admin_add_user.php" method="POST" class="admin-form" id="addUserForm" novalidate>
                    <div class="form-group-admin-add">
                        <label for="name">Full Name <span class="required-star">*</span></label>
                        <input type="text" name="name" id="name" placeholder="e.g., Jane Smith" required 
                               value="<?= isset($_POST['name']) && empty($errors) ? '' : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''); ?>">
                        <small class="error-text-admin-add"></small>
                    </div>

                    <div class="form-group-admin-add">
                        <label for="email">Email Address <span class="required-star">*</span></label>
                        <input type="email" name="email" id="email" placeholder="e.g., jane.smith@example.com" required
                               value="<?= isset($_POST['email']) && empty($errors) ? '' : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                        <small class="error-text-admin-add"></small>
                    </div>

                    <div class="form-row-admin-add">
                        <div class="form-group-admin-add">
                            <label for="password">Password <span class="required-star">*</span></label>
                            <input type="password" name="password" id="password" placeholder="Min. 6 characters" required>
                            <small class="error-text-admin-add"></small>
                        </div>
                        <div class="form-group-admin-add">
                            <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" required>
                            <small class="error-text-admin-add"></small>
                        </div>
                    </div>

                    <div class="form-group-admin-add">
                        <label for="role">Assign Role <span class="required-star">*</span></label>
                        <select name="role" id="role" required>
                            <option value="user" <?= (isset($_POST['role']) && $_POST['role'] == 'user' && empty($errors)) ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin' && empty($errors)) ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        <small class="error-text-admin-add"></small>
                    </div>

                    <button type="submit" name="add_user" class="btn-submit-admin-add"><i class="fas fa-user-plus"></i> Create User Account</button>
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
    // Basic client-side validation
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const roleSelect = document.getElementById('role');

        addUserForm.addEventListener('submit', function(event) {
            let isValid = true;

            // Validate Name
            if (nameInput.value.trim() === '') {
                displayFormError(nameInput, 'Full name is required.');
                isValid = false;
            } else {
                clearFormError(nameInput);
            }

            // Validate Email
            if (emailInput.value.trim() === '') {
                displayFormError(emailInput, 'Email address is required.');
                isValid = false;
            } else if (!isValidEmail(emailInput.value.trim())) {
                displayFormError(emailInput, 'Please enter a valid email address.');
                isValid = false;
            } else {
                clearFormError(emailInput);
            }

            // Validate Password
            if (passwordInput.value.trim() === '') {
                displayFormError(passwordInput, 'Password is required.');
                isValid = false;
            } else if (passwordInput.value.trim().length < 6) {
                displayFormError(passwordInput, 'Password must be at least 6 characters.');
                isValid = false;
            } else {
                clearFormError(passwordInput);
            }

            // Validate Confirm Password
            if (confirmPasswordInput.value.trim() === '') {
                displayFormError(confirmPasswordInput, 'Please confirm your password.');
                isValid = false;
            } else if (passwordInput.value.trim() !== confirmPasswordInput.value.trim()) {
                displayFormError(confirmPasswordInput, 'Passwords do not match.');
                isValid = false;
            } else {
                clearFormError(confirmPasswordInput);
            }
            
            // Validate Role
            if (roleSelect.value === '') {
                displayFormError(roleSelect, 'Please select a role for the user.');
                isValid = false;
            } else {
                clearFormError(roleSelect);
            }


            if (!isValid) {
                event.preventDefault();
            }
        });

        function displayFormError(inputElement, message) {
            const formGroup = inputElement.closest('.form-group-admin-add');
            const errorTextElement = formGroup.querySelector('.error-text-admin-add');
            if (errorTextElement) {
                errorTextElement.textContent = message;
                inputElement.classList.add('input-error-admin-add');
            }
        }

        function clearFormError(inputElement) {
            const formGroup = inputElement.closest('.form-group-admin-add');
            const errorTextElement = formGroup.querySelector('.error-text-admin-add');
            if (errorTextElement) {
                errorTextElement.textContent = '';
                inputElement.classList.remove('input-error-admin-add');
            }
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    }
</script>
</body>
</html>
