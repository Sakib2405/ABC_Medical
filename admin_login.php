<?php
session_start();
$page_title = "Admin Login - ABC Medical";
$error_message_html = "";

// If already logged in as admin
if (isset($_SESSION['admin_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}
// If logged in as regular user
if (isset($_SESSION['user_id']) && (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] === false)) {
    header("Location: profile.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db_connect.php';

    if (!$conn || $conn->connect_error) {
        error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error"));
        $error_message_html = "<div class='message error-message-admin'>Internal server error. Please try again.</div>";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $errors = [];
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (empty($password)) {
            $errors[] = "Password is required.";
        }

        if (!empty($errors)) {
            $error_message_html = "<div class='message error-message-admin'><ul>";
            foreach ($errors as $error) {
                $error_message_html .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $error_message_html .= "</ul></div>";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin' LIMIT 1");

            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                $error_message_html = "<div class='message error-message-admin'>Database error.</div>";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $admin_user = $result->fetch_assoc()) {
                    // Comparing plain password directly
                    if ($password === $admin_user['password']) {
                        $_SESSION['admin_id'] = $admin_user['id'];
                        $_SESSION['admin_name'] = $admin_user['name'];
                        $_SESSION['user_id'] = $admin_user['id'];
                        $_SESSION['user'] = $admin_user['name'];
                        $_SESSION['is_admin'] = true;

                        session_regenerate_id(true);
                        $error_message_html = "<div class='message success-message-admin'>Login successful! Redirecting...</div>";
                        header("refresh:2;url=admin_dashboard.php");
                        $stmt->close();
                        $conn->close();
                        exit();
                    } else {
                        $error_message_html = "<div class='message error-message-admin'>Incorrect email or password.</div>";
                    }
                } else {
                    $error_message_html = "<div class='message error-message-admin'>Admin not found.</div>";
                }
                $stmt->close();
            }
        }
        if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin_login.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="admin-login-page-wrapper">
    <div class="admin-login-form-panel">
        <div class="admin-logo-container">
            <a href="index.php">
                <i class="fas fa-shield-alt"></i> <h1>ABC Medical Admin</h1>
            </a>
        </div>
        <form method="post" action="admin_login.php" id="adminLoginForm" novalidate>
            <h3>Administrator Access</h3>

            <?= $error_message_html ?>

            <div class="form-group-admin">
                <label for="email">Admin Email <span class="required-star">*</span></label>
                <div class="input-wrapper-admin">
                    <i class="fas fa-user-shield input-icon-admin"></i>
                    <input type="email" name="email" id="email" placeholder="Enter admin email" required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <small class="error-text-admin"></small>
            </div>

            <div class="form-group-admin">
                <label for="password">Password <span class="required-star">*</span></label>
                <div class="input-wrapper-admin">
                    <i class="fas fa-key input-icon-admin"></i>
                    <input type="password" name="password" id="password" placeholder="Enter admin password" required>
                    <span class="toggle-password-admin"><i class="fas fa-eye"></i></span>
                </div>
                <small class="error-text-admin"></small>
            </div>

            <button type="submit" class="btn-admin-login">Login <i class="fas fa-sign-in-alt"></i></button>

            <div class="form-footer-links-admin">
                <p><a href="login.php" class="back-to-user-login"><i class="fas fa-user"></i> User Login</a></p>
                <p><a href="index.php" class="back-home-admin"><i class="fas fa-home"></i> Back to Homepage</a></p>
            </div>
        </form>
    </div>
</div>

<script>
    const adminForm = document.getElementById('adminLoginForm');
    if (adminForm) {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const togglePasswordAdmin = document.querySelector('.toggle-password-admin');

        adminForm.addEventListener('submit', function(event) {
            let isValid = true;

            if (emailInput.value.trim() === '') {
                showError(emailInput, 'Email is required.');
                isValid = false;
            } else {
                clearError(emailInput);
            }

            if (passwordInput.value.trim() === '') {
                showError(passwordInput, 'Password is required.');
                isValid = false;
            } else {
                clearError(passwordInput);
            }

            if (!isValid) event.preventDefault();
        });

        if (togglePasswordAdmin) {
            togglePasswordAdmin.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        function showError(input, message) {
            const formGroup = input.closest('.form-group-admin');
            const errorText = formGroup.querySelector('.error-text-admin');
            if (errorText) {
                errorText.textContent = message;
                input.classList.add('input-error-admin');
            }
        }

        function clearError(input) {
            const formGroup = input.closest('.form-group-admin');
            const errorText = formGroup.querySelector('.error-text-admin');
            if (errorText) {
                errorText.textContent = '';
                input.classList.remove('input-error-admin');
            }
        }
    }
</script>
</body>
</html>
