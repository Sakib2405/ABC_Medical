<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "Book New Appointment - ABC Medical Admin";

$booking_message = "";
$booking_success = false;

// --- Fetch Data for Dropdowns ---
$all_users = []; // To select a patient
$available_doctors = [];
$available_services = [];

// Fetch all registered users (potential patients)
$sql_users = "SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name ASC"; // Assuming 'user' role for patients
$result_users = $conn->query($sql_users);
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $all_users[] = $row;
    }
} else {
    error_log("Error fetching users for booking: " . $conn->error);
}

// Fetch Available Doctors (same as appointments.php)
$sql_doctors = "SELECT id, name, specialization FROM doctors ORDER BY name ASC";
$result_doctors = $conn->query($sql_doctors);
if ($result_doctors) {
    while ($row = $result_doctors->fetch_assoc()) {
        $available_doctors[] = $row;
    }
} else {
    error_log("Error fetching doctors for booking: " . $conn->error);
}

// Fetch Available Services (same as appointments.php)
$sql_services = "SELECT id, name FROM services ORDER BY name ASC";
$result_services = $conn->query($sql_services);
if ($result_services) {
    while ($row = $result_services->fetch_assoc()) {
        $available_services[] = $row;
    }
} else {
    error_log("Error fetching services for booking: " . $conn->error);
}


// Handle new appointment booking by admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_book_appointment'])) {
    $selected_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $selected_doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $selected_service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $appointment_date = trim($_POST['appointment_date']);
    $appointment_time = trim($_POST['appointment_time']);
    $admin_notes = htmlspecialchars(trim($_POST['admin_notes'])); // Notes by admin, could be patient_notes or a new field

    $errors = [];
    if (empty($selected_user_id)) {
        $errors[] = "Please select a patient.";
    }
    if (empty($selected_doctor_id)) {
        $errors[] = "Please select a doctor.";
    }
    if (empty($selected_service_id)) {
        $errors[] = "Please select a service.";
    }
    if (empty($appointment_date)) {
        $errors[] = "Please select an appointment date.";
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Appointment date cannot be in the past.";
    }
    if (empty($appointment_time)) {
        $errors[] = "Please select an appointment time.";
    }

    if (empty($errors)) {
        // In a real app: Check doctor's availability, prevent overlaps, etc.
        
        // For the 'patient_notes' field in the appointments table,
        // we can use the admin_notes or have a separate column for admin_added_notes.
        // Here, we'll use it as patient_notes.
        $stmt_insert = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, service_id, appointment_date, appointment_time, patient_notes, status, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, 'Confirmed', ?)");
        // Assuming 'created_by_admin_id' column exists in 'appointments' table to track who booked it.
        // If not, you can omit it or add it. 'Confirmed' status as admin is booking.
        
        if ($stmt_insert) {
            $admin_creator_id = $_SESSION['admin_id'];
            $stmt_insert->bind_param("iissssi", $selected_user_id, $selected_doctor_id, $selected_service_id, $appointment_date, $appointment_time, $admin_notes, $admin_creator_id);
            
            if ($stmt_insert->execute()) {
                $booked_patient_name = "";
                foreach($all_users as $u) { if($u['id'] == $selected_user_id) $booked_patient_name = $u['name']; break;}
                $booked_doctor_name = "";
                foreach($available_doctors as $d) { if($d['id'] == $selected_doctor_id) $booked_doctor_name = $d['name']; break;}


                $booking_message = "<div class='message success-message-admin-form'>Appointment successfully booked for " . htmlspecialchars($booked_patient_name) . " with " . htmlspecialchars($booked_doctor_name) . " on " . date("M d, Y", strtotime($appointment_date)) . " at " . date("h:i A", strtotime($appointment_time)) . ".</div>";
                $booking_success = true;
            } else {
                error_log("Admin Appointment Booking Execute Error: " . $stmt_insert->error);
                $booking_message = "<div class='message error-message-admin-form'>Could not book appointment. Please check for conflicts or try again.</div>";
            }
            $stmt_insert->close();
        } else {
            error_log("Admin Appointment Booking Prepare Error: " . $conn->error);
            $booking_message = "<div class='message error-message-admin-form'>An error occurred. Please try again later.</div>";
        }
    } else {
        $booking_message = "<div class='message error-message-admin-form'><ul>";
        foreach ($errors as $error) {
            $booking_message .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $booking_message .= "</ul></div>";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_book_appointment.css" /> </head>
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
                <a href="admin_manage_doctors.php" class="admin-nav-item"><i class="fas fa-user-md"></i> <span>Manage Doctors</span></a>
                <a href="admin_manage_appointments.php" class="admin-nav-item active"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a>
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
                    <h1>Book New Appointment</h1>
                    <p class="header-breadcrumb">Admin Panel / Appointments / Book New</p>
                </div>
                <div class="header-right">
                     <a href="admin_manage_appointments.php" class="btn-back-admin"><i class="fas fa-arrow-left"></i> Back to Appointments List</a>
                </div>
            </header>

            <?php if (!empty($booking_message)) echo $booking_message; ?>

            <section class="admin-content-section book-appointment-form-section">
                <form action="admin_book_appointment.php" method="POST" class="admin-form" id="adminBookAppointmentForm" novalidate>
                    
                    <div class="form-group-admin-book">
                        <label for="user_id">Select Patient <span class="required-star">*</span></label>
                        <select name="user_id" id="user_id" required>
                            <option value="">-- Choose a Patient --</option>
                            <?php foreach($all_users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= (isset($_POST['user_id']) && $_POST['user_id'] == $user['id'] && !$booking_success) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="error-text-admin-book"></small>
                    </div>

                    <div class="form-row-admin-book">
                        <div class="form-group-admin-book">
                            <label for="doctor_id">Select Doctor <span class="required-star">*</span></label>
                            <select name="doctor_id" id="doctor_id" required>
                                <option value="">-- Choose a Doctor --</option>
                                <?php foreach($available_doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>" <?= (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doctor['id'] && !$booking_success) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($doctor['name'] . (isset($doctor['specialization']) ? ' (' . $doctor['specialization'] . ')' : '')) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="error-text-admin-book"></small>
                        </div>
                        <div class="form-group-admin-book">
                            <label for="service_id">Select Service <span class="required-star">*</span></label>
                            <select name="service_id" id="service_id" required>
                                <option value="">-- Choose a Service --</option>
                                 <?php foreach($available_services as $service): ?>
                                <option value="<?= $service['id'] ?>" <?= (isset($_POST['service_id']) && $_POST['service_id'] == $service['id'] && !$booking_success) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($service['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="error-text-admin-book"></small>
                        </div>
                    </div>
                    <div class="form-row-admin-book">
                        <div class="form-group-admin-book">
                            <label for="appointment_date">Appointment Date <span class="required-star">*</span></label>
                            <input type="date" name="appointment_date" id="appointment_date" required 
                                   min="<?= date('Y-m-d') ?>" value="<?= (isset($_POST['appointment_date']) && !$booking_success) ? htmlspecialchars($_POST['appointment_date']) : '' ?>">
                            <small class="error-text-admin-book"></small>
                        </div>
                        <div class="form-group-admin-book">
                            <label for="appointment_time">Appointment Time <span class="required-star">*</span></label>
                            <input type="time" name="appointment_time" id="appointment_time" required
                                   value="<?= (isset($_POST['appointment_time']) && !$booking_success) ? htmlspecialchars($_POST['appointment_time']) : '' ?>">
                            <small class="error-text-admin-book"></small>
                        </div>
                    </div>
                    <div class="form-group-admin-book">
                        <label for="admin_notes">Notes (Optional)</label>
                        <textarea name="admin_notes" id="admin_notes" rows="3" placeholder="Any specific instructions or notes for this appointment..."><?= (isset($_POST['admin_notes']) && !$booking_success) ? htmlspecialchars($_POST['admin_notes']) : '' ?></textarea>
                        <small class="error-text-admin-book"></small>
                    </div>
                    <button type="submit" name="admin_book_appointment" class="btn-submit-admin-book"><i class="fas fa-calendar-check"></i> Book Appointment</button>
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
    const adminBookForm = document.getElementById('adminBookAppointmentForm');
    if (adminBookForm) {
        const userIdInput = document.getElementById('user_id');
        const doctorIdInput = document.getElementById('doctor_id');
        const serviceIdInput = document.getElementById('service_id');
        const dateInput = document.getElementById('appointment_date');
        const timeInput = document.getElementById('appointment_time');

        adminBookForm.addEventListener('submit', function(event) {
            let isValid = true;

            if (userIdInput.value === '') {
                displayFormError(userIdInput, 'Please select a patient.');
                isValid = false;
            } else { clearFormError(userIdInput); }

            if (doctorIdInput.value === '') {
                displayFormError(doctorIdInput, 'Please select a doctor.');
                isValid = false;
            } else { clearFormError(doctorIdInput); }

            if (serviceIdInput.value === '') {
                displayFormError(serviceIdInput, 'Please select a service.');
                isValid = false;
            } else { clearFormError(serviceIdInput); }
            
            if (dateInput.value === '') {
                displayFormError(dateInput, 'Please select a date.');
                isValid = false;
            } else { clearFormError(dateInput); }

            if (timeInput.value === '') {
                displayFormError(timeInput, 'Please select a time.');
                isValid = false;
            } else { clearFormError(timeInput); }

            if (!isValid) {
                event.preventDefault();
            }
        });

        function displayFormError(inputElement, message) {
            const formGroup = inputElement.closest('.form-group-admin-book');
            const errorTextElement = formGroup.querySelector('.error-text-admin-book');
            if (errorTextElement) {
                errorTextElement.textContent = message;
                inputElement.classList.add('input-error-admin-book');
            }
        }

        function clearFormError(inputElement) {
            const formGroup = inputElement.closest('.form-group-admin-book');
            const errorTextElement = formGroup.querySelector('.error-text-admin-book');
            if (errorTextElement) {
                errorTextElement.textContent = '';
                inputElement.classList.remove('input-error-admin-book');
            }
        }
    }
</script>
</body>
</html>