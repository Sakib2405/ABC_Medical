<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "Edit Doctor Profile - ABC Medical Admin";

$message_html = ""; // For success/error messages
$doctor_details = null;
$doctor_id = null;

// 1. Get Doctor ID from URL and validate
if (isset($_GET['doctor_id']) && filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT)) {
    $doctor_id = $_GET['doctor_id'];

    // 2. Fetch existing doctor data if not a POST request (or if POST failed and we need to re-show form)
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !empty($errors) /* Re-fetch if POST had errors */) {
        $stmt_fetch = $conn->prepare("SELECT id, name, specialization, email, phone, bio, profile_image_url FROM doctors WHERE id = ?");
        if ($stmt_fetch) {
            $stmt_fetch->bind_param("i", $doctor_id);
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();
            if ($result_fetch->num_rows === 1) {
                $doctor_details = $result_fetch->fetch_assoc();
            } else {
                $message_html = "<div class='message error-message-admin-form'>Doctor not found.</div>";
            }
            $stmt_fetch->close();
        } else {
            error_log("Fetch Doctor Prepare Error: " . $conn->error);
            $message_html = "<div class='message error-message-admin-form'>Error fetching doctor details.</div>";
        }
    }
} else {
    $message_html = "<div class='message error-message-admin-form'>Invalid Doctor ID specified.</div>";
}


// 3. Handle Form Submission (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_doctor']) && $doctor_id) {
    $doc_name = trim($_POST['name']);
    $doc_specialization = trim($_POST['specialization']);
    $doc_email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $doc_phone = trim($_POST['phone']);
    $doc_bio = trim($_POST['bio']);
    $doc_profile_image_url = trim($_POST['profile_image_url']); // Basic URL for now

    $errors = [];

    // Validation
    if (empty($doc_name)) {
        $errors[] = "Doctor's Name is required.";
    }
    if (empty($doc_specialization)) {
        $errors[] = "Specialization is required.";
    }
    if (empty($doc_email)) {
        $errors[] = "Email Address is required.";
    } elseif (!filter_var($doc_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists for ANOTHER doctor
        $stmt_check_email = $conn->prepare("SELECT id FROM doctors WHERE email = ? AND id != ?");
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("si", $doc_email, $doctor_id);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $errors[] = "This email address is already in use by another doctor.";
            }
            $stmt_check_email->close();
        } else {
            $errors[] = "Error checking email uniqueness.";
            error_log("Doctor Email check prepare error: " . $conn->error);
        }
    }
    // Add more validation for phone, bio, profile_image_url if needed

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE doctors SET name = ?, specialization = ?, email = ?, phone = ?, bio = ?, profile_image_url = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("ssssssi", $doc_name, $doc_specialization, $doc_email, $doc_phone, $doc_bio, $doc_profile_image_url, $doctor_id);
            if ($stmt_update->execute()) {
                $message_html = "<div class='message success-message-admin-form'>Doctor profile updated successfully!</div>";
                // Re-fetch data to show updated values in the form
                $stmt_fetch_updated = $conn->prepare("SELECT id, name, specialization, email, phone, bio, profile_image_url FROM doctors WHERE id = ?");
                if($stmt_fetch_updated){
                    $stmt_fetch_updated->bind_param("i", $doctor_id);
                    $stmt_fetch_updated->execute();
                    $result_updated = $stmt_fetch_updated->get_result();
                    $doctor_details = $result_updated->fetch_assoc();
                    $stmt_fetch_updated->close();
                }
            } else {
                error_log("Doctor Update Execute Error: " . $stmt_update->error);
                $message_html = "<div class='message error-message-admin-form'>Could not update doctor profile. Please try again.</div>";
            }
            $stmt_update->close();
        } else {
            error_log("Doctor Update Prepare Error: " . $conn->error);
            $message_html = "<div class='message error-message-admin-form'>An error occurred preparing the update. Please try again later.</div>";
        }
    } else {
        // If there are validation errors, $doctor_details should still hold the pre-POST values if fetched.
        // Or, to show what user just typed in case of error, repopulate from $_POST.
        // For simplicity, we rely on the initial fetch or the re-fetch after successful update.
        // If validation fails, we should ideally repopulate form with $_POST values.
        $doctor_details = [ // Repopulate form with submitted data if errors exist
            'id' => $doctor_id,
            'name' => $doc_name,
            'specialization' => $doc_specialization,
            'email' => $doc_email,
            'phone' => $doc_phone,
            'bio' => $doc_bio,
            'profile_image_url' => $doc_profile_image_url
        ];
        $message_html = "<div class='message error-message-admin-form'><ul>";
        foreach ($errors as $error) {
            $message_html .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $message_html .= "</ul></div>";
    }
}


// If $doctor_details is still null after all checks and POST handling, it means doctor wasn't found initially.
if (!$doctor_details && $doctor_id && $_SERVER["REQUEST_METHOD"] != "POST") { // Avoid showing "not found" if it was a POST request that might have failed for other reasons
    $message_html = "<div class='message error-message-admin-form'>Doctor with ID " . htmlspecialchars($doctor_id) . " not found.</div>";
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
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_edit_doctor.css" /> </head>
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
                <a href="admin_manage_users.php" class="admin-nav-item"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
                <a href="admin_manage_doctors.php" class="admin-nav-item active"><i class="fas fa-user-md"></i> <span>Manage Doctors</span></a>
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
                    <h1>Edit Doctor Profile</h1>
                    <p class="header-breadcrumb">Admin Panel / Manage Doctors / Edit Doctor</p>
                </div>
                <div class="header-right">
                     <a href="admin_manage_doctors.php" class="btn-back-admin"><i class="fas fa-arrow-left"></i> Back to Doctor List</a>
                </div>
            </header>

            <?php if (!empty($message_html)) echo $message_html; ?>

            <?php if ($doctor_details): ?>
            <section class="admin-content-section edit-doctor-form-section">
                <form action="admin_edit_doctor.php?doctor_id=<?= htmlspecialchars($doctor_id) ?>" method="POST" class="admin-form" id="editDoctorForm" novalidate>
                    <div class="form-row-admin-edit">
                        <div class="form-group-admin-edit">
                            <label for="name">Doctor's Full Name <span class="required-star">*</span></label>
                            <input type="text" name="name" id="name" placeholder="e.g., Dr. Johnathan Smith" required 
                                   value="<?= htmlspecialchars($doctor_details['name'] ?? '') ?>">
                            <small class="error-text-admin-edit"></small>
                        </div>
                        <div class="form-group-admin-edit">
                            <label for="specialization">Specialization <span class="required-star">*</span></label>
                            <input type="text" name="specialization" id="specialization" placeholder="e.g., Cardiology" required
                                   value="<?= htmlspecialchars($doctor_details['specialization'] ?? '') ?>">
                            <small class="error-text-admin-edit"></small>
                        </div>
                    </div>

                    <div class="form-row-admin-edit">
                        <div class="form-group-admin-edit">
                            <label for="email">Email Address <span class="required-star">*</span></label>
                            <input type="email" name="email" id="email" placeholder="e.g., doctor@example.com" required
                                   value="<?= htmlspecialchars($doctor_details['email'] ?? '') ?>">
                            <small class="error-text-admin-edit"></small>
                        </div>
                        <div class="form-group-admin-edit">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" placeholder="e.g., (555) 123-4567"
                                   value="<?= htmlspecialchars($doctor_details['phone'] ?? '') ?>">
                            <small class="error-text-admin-edit"></small>
                        </div>
                    </div>

                    <div class="form-group-admin-edit">
                        <label for="bio">Biography / Short Description</label>
                        <textarea name="bio" id="bio" rows="4" placeholder="Enter a brief bio for the doctor..."><?= htmlspecialchars($doctor_details['bio'] ?? '') ?></textarea>
                        <small class="error-text-admin-edit"></small>
                    </div>
                    
                    <div class="form-group-admin-edit">
                        <label for="profile_image_url">Profile Image URL (Optional)</label>
                        <input type="url" name="profile_image_url" id="profile_image_url" placeholder="e.g., https://example.com/doctor.jpg"
                               value="<?= htmlspecialchars($doctor_details['profile_image_url'] ?? '') ?>">
                        <small class="form-text-admin-edit">Enter a direct URL to an image.</small>
                    </div>
                    <?php if (!empty($doctor_details['profile_image_url'])): ?>
                    <div class="current-image-preview">
                        <p>Current Image:</p>
                        <img src="<?= htmlspecialchars($doctor_details['profile_image_url']) ?>" alt="Current Profile Image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" >
                        <small style="display:none;">Image link broken or invalid.</small>
                    </div>
                    <?php endif; ?>


                    <button type="submit" name="update_doctor" class="btn-submit-admin-edit"><i class="fas fa-save"></i> Save Changes</button>
                </form>
            </section>
            <?php elseif (!$doctor_id): // Only show if no ID was passed, not if doctor wasn't found ?>
                 <?php endif; ?>
        </main>
    </div>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<script>
    // Basic client-side validation (similar to add user)
    const editDoctorForm = document.getElementById('editDoctorForm');
    if (editDoctorForm) {
        const nameInput = document.getElementById('name');
        const specializationInput = document.getElementById('specialization');
        const emailInput = document.getElementById('email');
        // Add other inputs if specific validation is needed

        editDoctorForm.addEventListener('submit', function(event) {
            let isValid = true;

            if (nameInput.value.trim() === '') {
                displayEditFormError(nameInput, 'Doctor\'s name is required.');
                isValid = false;
            } else {
                clearEditFormError(nameInput);
            }
            if (specializationInput.value.trim() === '') {
                displayEditFormError(specializationInput, 'Specialization is required.');
                isValid = false;
            } else {
                clearEditFormError(specializationInput);
            }
            if (emailInput.value.trim() === '') {
                displayEditFormError(emailInput, 'Email address is required.');
                isValid = false;
            } else if (!isValidEditEmail(emailInput.value.trim())) {
                displayEditFormError(emailInput, 'Please enter a valid email address.');
                isValid = false;
            } else {
                clearEditFormError(emailInput);
            }

            if (!isValid) {
                event.preventDefault();
            }
        });

        function displayEditFormError(inputElement, message) {
            const formGroup = inputElement.closest('.form-group-admin-edit');
            const errorTextElement = formGroup.querySelector('.error-text-admin-edit');
            if (errorTextElement) {
                errorTextElement.textContent = message;
                inputElement.classList.add('input-error-admin-edit');
            }
        }

        function clearEditFormError(inputElement) {
            const formGroup = inputElement.closest('.form-group-admin-edit');
            const errorTextElement = formGroup.querySelector('.error-text-admin-edit');
            if (errorTextElement) {
                errorTextElement.textContent = '';
                inputElement.classList.remove('input-error-admin-edit');
            }
        }

        function isValidEditEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    }
</script>
</body>
</html>
