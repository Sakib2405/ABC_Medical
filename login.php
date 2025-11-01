<?php
session_start();
$page_title = "Secure Login - ABC Medical";
$error_message_html = ""; // Variable to store error messages

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: profile.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db_connect.php'; 

    if (!$conn || $conn->connect_error) {
        error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error"));
        $error_message_html = "<div class='message error-message-prof'>An internal server error occurred. Please try again later.</div>";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $errors = [];
        if (empty($email)) {
            $errors[] = "Email Address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (empty($password)) {
            $errors[] = "Password is required.";
        }

        if (!empty($errors)) {
            $error_message_html = "<div class='message error-message-prof'><ul>";
            foreach ($errors as $error) {
                $error_message_html .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $error_message_html .= "</ul></div>";
        } else {
            $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                error_log("SQL Prepare Error (Login): " . $conn->error);
                $error_message_html = "<div class='message error-message-prof'>Database error. Please try again later.</div>";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $row = $result->fetch_assoc()) {
                    if (password_verify($password, $row['password'])) {
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user'] = $row['name'];
                        $_SESSION['is_admin'] = ($row['role'] === 'admin');
                        session_regenerate_id(true);
                        
                        $error_message_html = "<div class='message success-message-prof'>Login successful! Redirecting...</div>";
                        // Redirect all users to index.php after successful login
                        header("refresh:2;url=index.php"); 
                        $stmt->close();
                        $conn->close();
                        exit();
                    } else {
                        $error_message_html = "<div class='message error-message-prof'>Incorrect email or password.</div>";
                    }
                } else {
                    $error_message_html = "<div class='message error-message-prof'>No user found with that email address.</div>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css"> </head>
<body>
    <div class="login-page-wrapper-centered">
        <div class="login-form-panel-centered">
            <div class="logo-prof-centered">
                <a href="index.php">
                    <i class="fas fa-clinic-medical"></i>
                    <h1>ABC Medical</h1>
                </a>
            </div>
            <form method="post" action="login.php" id="professionalLoginForm" novalidate>
                <h3>Member Login</h3>
                
                <?php if (!empty($error_message_html)) echo $error_message_html; ?>

                <div class="form-group-prof">
                    <label for="email">Email Address <span class="required-star">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" id="email" placeholder="Enter your email" required
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <small class="error-text-prof"></small>
                </div>
                
                <div class="form-group-prof">
                    <label for="password">Password <span class="required-star">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <span class="toggle-password"><i class="fas fa-eye"></i></span>
                    </div>
                    <small class="error-text-prof"></small>
                </div>
                
                <div class="form-options-prof">
                    <a href="forgot_password.php" class="forgot-password-link-prof">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login-prof">Sign In <i class="fas fa-arrow-right"></i></button>

                <div class="form-footer-links-prof">
                    <p>Don't have an account? <a href="register.php">Create one now</a></p>
                    <p><a href="index.php" class="back-home-prof"><i class="fas fa-chevron-left"></i> Back to Homepage</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Client-side validation
        const form = document.getElementById('professionalLoginForm');
        if (form) {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.querySelector('.toggle-password');

            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                if (emailInput.value.trim() === '') {
                    displayError(emailInput, 'Email address is required.');
                    isValid = false;
                } else if (!isValidEmail(emailInput.value.trim())) {
                    displayError(emailInput, 'Please enter a valid email address.');
                    isValid = false;
                } else {
                    clearError(emailInput);
                }

                if (passwordInput.value.trim() === '') {
                    displayError(passwordInput, 'Password is required.');
                    isValid = false;
                } else {
                    clearError(passwordInput);
                }

                if (!isValid) {
                    event.preventDefault();
                }
            });

            if (togglePassword) {
                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }

            function displayError(inputElement, message) {
                const formGroup = inputElement.closest('.form-group-prof');
                const errorTextElement = formGroup.querySelector('.error-text-prof');
                if (errorTextElement) {
                    errorTextElement.textContent = message;
                    inputElement.classList.add('input-error-prof');
                }
            }

            function clearError(inputElement) {
                 const formGroup = inputElement.closest('.form-group-prof');
                const errorTextElement = formGroup.querySelector('.error-text-prof');
                if (errorTextElement) {
                    errorTextElement.textContent = '';
                    inputElement.classList.remove('input-error-prof');
                }
            }

            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
        }
    </script>
    <script type='text/javascript' src='//pl27022957.profitableratecpm.com/df/f6/6f/dff66f651ce6a7255f2a34b68a269ff8.js'></script>
<div id="container-518b5bf3a8f610d01ac4771c391ef67d"></div>
</body>
</html>
