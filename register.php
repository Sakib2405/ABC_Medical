<?php
session_start();
$page_title = "Register - ABC Medical";
$message_html = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db_connect.php'; // Ensure this file sets up $conn (MySQLi connection)

    if (!$conn || $conn->connect_error) {
        error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error"));
        $message_html = "<div class='message error-message'>An internal server error occurred. Please try again later.</div>";
    } else {
        $name = trim($_POST['name']);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password_plain = $_POST['password'];

        $errors = [];
        if (empty($name)) {
            $errors[] = "Full Name is required.";
        }
        if (empty($email)) {
            $errors[] = "Email Address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if (empty($password_plain)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password_plain) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }

        if (!empty($errors)) {
            $message_html = "<div class='message error-message'><ul>";
            foreach ($errors as $error) {
                $message_html .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $message_html .= "</ul></div>";
        } else {
            $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
            // Assuming 'role' column exists and defaults to 'user' if not specified in INSERT
            // Or, if you want to explicitly set a default role:
            // $default_role = 'user';
            // $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            // $stmt->bind_param("ssss", $name, $email, $password_hashed, $default_role);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)"); 
            if ($stmt) {
                $stmt->bind_param("sss", $name, $email, $password_hashed);
                if ($stmt->execute()) {
                    $message_html = "<div class='message success-message'>Registration successful! Redirecting to <a href='login.php'>Login Page</a>...</div>";
                    header("refresh:3;url=login.php"); 
                    $stmt->close(); // Close statement
                    if (isset($conn) && $conn instanceof mysqli) { // Check before closing
                        $conn->close(); // Close connection
                    }
                    exit(); // Exit after redirect
                } else {
                    if ($conn->errno == 1062) {
                         $message_html = "<div class='message error-message'>Error: This email address is already registered. Please <a href='login.php'>login</a>.</div>";
                    } else {
                        error_log("SQL Execute Error: " . $stmt->error);
                        $message_html = "<div class='message error-message'>An error occurred during registration. Please try again.</div>";
                    }
                }
                if ($stmt instanceof mysqli_stmt) { // Check if $stmt is a valid statement object before closing
                    $stmt->close();
                }
            } else {
                error_log("SQL Prepare Error: " . $conn->error);
                $message_html = "<div class='message error-message'>An error occurred preparing your registration. Please try again.</div>";
            }
        }
        if (isset($conn) && $conn instanceof mysqli) { // Close connection if it was opened and not closed yet
             $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="register.css"> 
</head>
<body>
    <div class="page-wrapper">
        <div class="dynamic-background">
            </div>

        <header class="form-page-header">
            <a href="index.php" class="logo">
                <i class="fas fa-hospital-symbol"></i>
                <span>ABC Medical</span>
            </a>
        </header>

        <main class="form-main-content">
            <div class="register-form-container">
                <div class="form-card">
                    <h2>Create Your Account</h2>
                    <p class="subtitle">Join us to manage your health efficiently.</p>

                    <?php if (!empty($message_html)) echo $message_html; ?>

                    <form method="post" action="register.php" id="registrationFormTable" novalidate>
                        <table class="registration-table">
                            <tr>
                                <td class="label-cell"><label for="name"><i class="fas fa-user-alt"></i> Full Name</label></td>
                                <td class="input-cell">
                                    <input type="text" name="name" id="name" placeholder="e.g., John Doe" required
                                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                    <small class="error-text"></small>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-cell"><label for="email"><i class="fas fa-envelope"></i> Email Address</label></td>
                                <td class="input-cell">
                                    <input type="email" name="email" id="email" placeholder="e.g., you@example.com" required
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                    <small class="error-text"></small>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-cell"><label for="password"><i class="fas fa-lock"></i> Password</label></td>
                                <td class="input-cell">
                                    <input type="password" name="password" id="password" placeholder="Min. 6 characters" required>
                                    <small class="error-text"></small>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" class="button-cell">
                                    <button type="submit" class="btn-register"><i class="fas fa-user-plus"></i> Register Now</button>
                                </td>
                            </tr>
                        </table>
                        <div class="form-footer-links">
                            <p>Already have an account? <a href="login.php">Sign In</a></p>
                            <p><a href="index.php" class="back-to-home"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const form = document.getElementById('registrationFormTable');
        if (form) {
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                if (nameInput.value.trim() === '') {
                    displayError(nameInput, 'Full name is required.');
                    isValid = false;
                } else {
                    clearError(nameInput);
                }

                if (emailInput.value.trim() === '') {
                    displayError(emailInput, 'Email is required.');
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
                } else if (passwordInput.value.trim().length < 6) {
                    displayError(passwordInput, 'Password must be at least 6 characters long.');
                    isValid = false;
                } else {
                    clearError(passwordInput);
                }

                if (!isValid) {
                    event.preventDefault(); 
                }
            });

            function displayError(inputElement, message) {
                // Find the parent <td> (input-cell) and then the <small> tag within it
                const inputCell = inputElement.closest('.input-cell');
                if (inputCell) {
                    const errorTextElement = inputCell.querySelector('.error-text');
                    if (errorTextElement) {
                        errorTextElement.textContent = message;
                        inputElement.classList.add('input-error-table'); // Use a new class for table input errors
                    }
                }
            }

            function clearError(inputElement) {
                const inputCell = inputElement.closest('.input-cell');
                if (inputCell) {
                    const errorTextElement = inputCell.querySelector('.error-text');
                    if (errorTextElement) {
                        errorTextElement.textContent = '';
                        inputElement.classList.remove('input-error-table');
                    }
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
