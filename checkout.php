<?php
session_start();

// --- DEBUGGING FLAGS (REMOVE OR SET TO 0 FOR PRODUCTION) ---
ini_set('display_errors', 1); // Display errors on screen (for development)
ini_set('display_startup_errors', 1); // Display startup errors
error_reporting(E_ALL); // Report all PHP errors
// --- END DEBUGGING FLAGS ---

// --- Database Connection Details (Directly in this file) ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null;
$error_message = "";
$success_message = "";

// Initialize appointment and patient data
$doctor_id = null;
$appointment_date_str = null;
$appointment_time_str = null;
$consultation_fee = 0.00;
$doctor_name = "N/A";

$patient_name = '';
$patient_phone = ''; // Will NOT be pre-filled from DB in this version
$patient_email = ''; // Will NOT be pre-filled from DB in this version
$reason_for_visit = '';

// Flag for form fields being read-only - will only apply to name in this version
$patient_details_readonly = false;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        error_log("DB Connection Failed in checkout.php: " . $conn->connect_error);
        die("<h1>Sorry, the system is currently unavailable. Please try again later.</h1>");
    }
    $conn->set_charset("utf8mb4");
    date_default_timezone_set('Asia/Dhaka'); // Set timezone for consistency
} catch (Exception $e) {
    error_log("Unhandled DB Connection Exception in checkout.php: " . $e->getMessage());
    die("<h1>An unexpected error occurred. Please try again later.</h1>");
}

// --- Helper function for sanitizing input ---
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// --- Enforce Login: Only logged-in users can book appointments ---
if (!isset($_SESSION['user_id'])) {
    // Store requested appointment details in session if available, then redirect to login
    if (isset($_GET['doctor_id'])) { // Simple check to see if they were trying to book
        $_SESSION['redirect_after_login'] = 'checkout.php?' . http_build_query($_GET);
    }
    header("Location: login.php?feedback=" . urlencode("Please log in to book an appointment.") . "&type=info");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// --- Populate Patient Name from Database for Logged-in User (NO PHONE/EMAIL FETCH HERE) ---
// This SELECT query explicitly excludes 'phone' and 'email' to avoid the 'Unknown column' error
$stmt_user_details = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1"); // MODIFIED LINE
if ($stmt_user_details) {
    $stmt_user_details->bind_param("i", $current_user_id);
    if ($stmt_user_details->execute()) {
        $result_user_details = $stmt_user_details->get_result();
        if ($result_user_details->num_rows === 1) {
            $user_db_data = $result_user_details->fetch_assoc();
            $patient_name = htmlspecialchars($user_db_data['name']);
            $patient_details_readonly = true; // Only name will be read-only
            // patient_phone and patient_email will be empty strings, user must input them
        } else {
            // User ID in session not found in DB - critical error, invalidate session
            session_unset(); session_destroy();
            header("Location: login.php?feedback=" . urlencode("Your user session is invalid. Please log in again.") . "&type=error");
            exit;
        }
    } else {
        error_log("Error executing user details fetch in checkout.php: " . $stmt_user_details->error);
        $error_message .= "A system error occurred while retrieving your patient name. Please try again.<br>";
    }
    $stmt_user_details->close();
} else {
    error_log("Error preparing user details fetch statement in checkout.php: " . $conn->error);
    $error_message .= "A system error occurred. Please try again later.<br>";
}

// --- Step 1: Retrieve and Display Appointment Details from GET parameters (Initial Load) ---
// This runs on initial GET request, or if there was a previous POST with errors
if (empty($error_message) && $_SERVER["REQUEST_METHOD"] == "GET") { // Only process GET if no DB errors on user fetch
    if (isset($_GET['doctor_id']) && isset($_GET['date']) && isset($_GET['time']) && isset($_GET['fee'])) {
        $doctor_id = filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT);
        $appointment_date_str = sanitize_input($_GET['date']);
        $appointment_time_str = sanitize_input($_GET['time']);
        $consultation_fee = filter_var($_GET['fee'], FILTER_VALIDATE_FLOAT);

        // Basic validation for GET parameters
        if ($doctor_id === false || $doctor_id <= 0) {
            $error_message .= "Invalid Doctor ID selected. Please go back and choose a valid doctor.<br>";
        } elseif (!DateTime::createFromFormat('Y-m-d', $appointment_date_str)) {
            $error_message .= "Invalid appointment date format. Please go back and re-select.<br>";
        } elseif (!DateTime::createFromFormat('H:i', $appointment_time_str) && !DateTime::createFromFormat('H:i:s', $appointment_time_str)) {
            $error_message .= "Invalid appointment time format. Please go back and re-select.<br>";
        } elseif ($consultation_fee === false || $consultation_fee < 0) {
            $error_message .= "Invalid Consultation Fee provided.<br>";
        } else {
            // Fetch doctor's name from database for display
            $stmt_doctor = $conn->prepare("SELECT name FROM doctors WHERE id = ? AND is_active = TRUE");
            if ($stmt_doctor) {
                $stmt_doctor->bind_param("i", $doctor_id);
                if ($stmt_doctor->execute()) {
                    $result_doctor = $stmt_doctor->get_result();
                    if ($result_doctor->num_rows > 0) {
                        $doctor_data = $result_doctor->fetch_assoc();
                        $doctor_name = $doctor_data['name'];
                    } else {
                        $error_message .= "Selected doctor not found or is not currently active. Please choose another.<br>";
                        $doctor_id = null; // Invalidate doctor if not found
                    }
                } else {
                    error_log("Error executing doctor fetch query in checkout.php: " . $stmt_doctor->error);
                    $error_message .= "A database error occurred while fetching doctor details.<br>";
                }
                $stmt_doctor->close();
            } else {
                error_log("Error preparing doctor fetch statement in checkout.php: " . $conn->error);
                $error_message .= "A system error occurred. Please try again later.<br>";
            }
        }
    } else {
        $error_message .= "Appointment details are missing. Please select a doctor and time slot from the availability page.<br>";
    }
}


// --- Step 2: Handle Form Submission (POST request for booking) ---
// This runs when the form is submitted.
// Note: $patient_name, $patient_phone, $patient_email will be populated from $_POST for validation/insertion
// NOT from $user_db_data directly for these two fields, as user needs to provide phone/email if not in DB.
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error_message)) { // Only proceed if no initial GET-related errors
    // Retrieve and sanitize all POST data
    $doctor_id = filter_var($_POST['doctor_id'] ?? '', FILTER_VALIDATE_INT);
    $appointment_date_str = sanitize_input($_POST['appointment_date'] ?? '');
    $appointment_time_str = sanitize_input($_POST['appointment_time'] ?? '');
    $consultation_fee = filter_var($_POST['consultation_fee'] ?? 0, FILTER_VALIDATE_FLOAT);

    // Patient details now come *directly from POST* for phone/email, and from DB for name
    $patient_name = sanitize_input($_POST['patient_name'] ?? ''); // This will be the DB name if readonly, or user input if not
    $patient_phone = sanitize_input($_POST['patient_phone'] ?? ''); // This will be user input
    $patient_email = filter_var($_POST['patient_email'] ?? '', FILTER_SANITIZE_EMAIL); // This will be user input
    $reason_for_visit = sanitize_input($_POST['reason_for_visit'] ?? '');

    // Re-fetch doctor_name for display in messages if ID is valid
    if ($doctor_id) {
        $stmt_doc_name_post = $conn->prepare("SELECT name FROM doctors WHERE id = ?");
        if ($stmt_doc_name_post) {
            $stmt_doc_name_post->bind_param("i", $doctor_id);
            if ($stmt_doc_name_post->execute()) {
                $result_doc_name_post = $stmt_doc_name_post->get_result();
                if ($doc_row_post = $result_doc_name_post->fetch_assoc()) {
                    $doctor_name = $doc_row_post['name'];
                }
            } else {
                error_log("Error fetching doctor name on POST in checkout.php: " . $stmt_doc_name_post->error);
            }
            $stmt_doc_name_post->close();
        }
    }

    // --- Server-side Validation for POST data ---
    // Validate hidden fields values
    if ($doctor_id === false || $doctor_id <= 0) $error_message .= "Doctor information is missing or invalid.<br>";
    if (empty($appointment_date_str) || !DateTime::createFromFormat('Y-m-d', $appointment_date_str)) $error_message .= "Appointment date is missing or invalid.<br>";
    if (empty($appointment_time_str) || (!DateTime::createFromFormat('H:i', $appointment_time_str) && !DateTime::createFromFormat('H:i:s', $appointment_time_str))) $error_message .= "Appointment time is missing or invalid.<br>";
    if ($consultation_fee === false || $consultation_fee < 0) $error_message .= "Consultation fee is invalid.<br>";

    // Validate patient details from POST (these are user-entered or from DB)
    if (empty($patient_name)) $error_message .= "Full Name is required.<br>";
    if (empty($patient_phone)) $error_message .= "Phone Number is required.<br>";
    // Specific Bangladeshi phone number format validation (01 and 11 digits total)
    if (!empty($patient_phone) && !preg_match('/^01[3-9]\d{8}$/', $patient_phone)) {
         $error_message .= "Invalid phone number format. Please use Bangladesh mobile format (e.g., 01xxxxxxxxx).<br>";
    }
    // Validate email format after sanitization
    if (!empty($patient_email) && !filter_var($patient_email, FILTER_VALIDATE_EMAIL)) {
        $error_message .= "Invalid email format.<br>";
    }


    // If all initial validations pass, proceed to check availability and insert
    if (empty($error_message)) {
        // Prevent double booking for the exact slot
        $stmt_check_booking = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
        if ($stmt_check_booking) {
            $stmt_check_booking->bind_param("iss", $doctor_id, $appointment_date_str, $appointment_time_str);
            if ($stmt_check_booking->execute()) {
                $result_check_booking = $stmt_check_booking->get_result();
                if ($result_check_booking->num_rows > 0) {
                    $error_message = "Sorry, this time slot was just booked by someone else. Please choose another slot.";
                }
            } else {
                error_log("Error executing availability check in checkout.php: " . $stmt_check_booking->error);
                $error_message = "A system error occurred while checking slot availability.";
            }
            $stmt_check_booking->close();
        } else {
            error_log("Error preparing availability check statement in checkout.php: " . $conn->error);
            $error_message = "A system error occurred (availability check).";
        }

        // If slot is still available, insert the appointment
        if (empty($error_message)) {
            // Updated INSERT query to include patient_id
            $stmt_insert = $conn->prepare("INSERT INTO appointments (patient_id, patient_name, patient_phone, patient_email, doctor_id, appointment_date, appointment_time, reason, consultation_fee, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')");
            if ($stmt_insert) {
                // Binding types: i for patient_id (int), sss for name/phone/email (string), i for doctor_id (int), s for date (string), s for time (string), s for reason (string), d for fee (double)
                $stmt_insert->bind_param("isssisssd", $current_user_id, $patient_name, $patient_phone, $patient_email, $doctor_id, $appointment_date_str, $appointment_time_str, $reason_for_visit, $consultation_fee);
                if ($stmt_insert->execute()) {
                    $new_appointment_id = $conn->insert_id;
                    // Redirect to confirmation page after successful booking
                    header("Location: appointment_confirmation.php?id=" . $new_appointment_id);
                    exit;

                } else {
                    error_log("Error inserting appointment in checkout.php: " . $stmt_insert->error);
                    $error_message = "Error booking appointment: " . $stmt_insert->error; // Show detailed error in dev
                }
                $stmt_insert->close();
            } else {
                error_log("Error preparing insert statement in checkout.php: " . $conn->error);
                $error_message = "A system error occurred (appointment save). " . $conn->error; // Show detailed error in dev
            }
        }
    }
}
$conn->close(); // Close the database connection at the end of the script
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Appointment - ABC Medical</title>
    <link rel="stylesheet" href="checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General styles for messages, if not already in checkout.css */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        .checkout-container-appointment {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .checkout-container-appointment h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 30px;
            font-size: 2.2em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .message {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message.error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.success-message { /* Success message is now handled by redirect, but keep styles */
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message i {
            font-size: 1.2em;
        }
        .message p {
            margin: 0;
        }
        .required {
            color: #dc3545; /* Red for required indicator */
            font-weight: bold;
        }
        .appointment-summary {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .appointment-summary h2 {
            color: #343a40;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.5em;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .appointment-summary p {
            margin-bottom: 8px;
            font-size: 1.05em;
            line-height: 1.4;
        }
        .appointment-summary p strong {
            color: #007bff;
        }
        .checkout-form-appointment h2 {
            color: #007bff;
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .checkout-form-appointment .form-group {
            margin-bottom: 20px;
        }
        .checkout-form-appointment label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .checkout-form-appointment input[type="text"],
        .checkout-form-appointment input[type="tel"],
        .checkout-form-appointment input[type="email"],
        .checkout-form-appointment textarea {
            width: calc(100% - 24px); /* Account for padding and border */
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .checkout-form-appointment input[type="text"]:focus,
        .checkout-form-appointment input[type="tel"]:focus,
        .checkout-form-appointment input[type="email"]:focus,
        .checkout-form-appointment textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }
        /* Readonly input style */
        .checkout-form-appointment input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.8;
        }
        textarea {
            resize: vertical; /* Allow vertical resizing */
            min-height: 80px; /* Minimum height for textarea */
        }
        .btn-confirm-appointment {
            background-color: #28a745; /* Green */
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: bold;
        }
        .btn-confirm-appointment:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .form-note {
            font-size: 0.85em;
            color: #777;
            text-align: center;
            margin-top: 20px;
        }
        .checkout-footer-appointment {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 0.9em;
            color: #888;
        }
        .checkout-footer-appointment .footer-link {
            color: #007bff;
            text-decoration: none;
            margin: 0 5px;
        }
        .checkout-footer-appointment .footer-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="checkout-container-appointment">
        <h1><i class="fas fa-calendar-check"></i> Confirm Your Appointment</h1>

        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-times-circle"></i>
                <?php echo $error_message; ?>
                <p style="margin-top: 15px;">
                    <a href="index.php" class="button-link-secondary" style="margin-right: 10px;"><i class="fas fa-home"></i> Go to Homepage</a>
                    <a href="doctors_serial.php" class="button-link"><i class="fas fa-user-md"></i> Browse Doctors</a>
                </p>
            </div>
        <?php endif; ?>

        <?php
        // Show the form and summary only if there are no errors from POST or if it's an initial GET request with valid doctor data.
        // If there are errors on a POST, we want to show the form again so user can correct.
        // If there's an error on GET or initial user fetch, the $error_message will be displayed.
        $show_form_and_summary = empty($error_message) || $_SERVER["REQUEST_METHOD"] == "POST";

        if ($show_form_and_summary):
            // On POST, if error, we need to ensure doctor details are still available for summary.
            // On GET, they are set from $_GET.
            // This is primarily for displaying the summary and form fields, not re-validating the doctor.
            if ($doctor_id === null && $_SERVER["REQUEST_METHOD"] == "POST") {
                // If doctor_id became null due to initial GET error, try to get from POST hidden fields
                $doctor_id = filter_var($_POST['doctor_id'] ?? '', FILTER_VALIDATE_INT);
                $appointment_date_str = sanitize_input($_POST['appointment_date'] ?? '');
                $appointment_time_str = sanitize_input($_POST['appointment_time'] ?? '');
                $consultation_fee = filter_var($_POST['consultation_fee'] ?? 0, FILTER_VALIDATE_FLOAT);

                if ($doctor_id) { // If doctor_id is valid, re-fetch name
                    $stmt_doc_reget = $conn->prepare("SELECT name FROM doctors WHERE id = ?");
                    if ($stmt_doc_reget) {
                        $stmt_doc_reget->bind_param("i", $doctor_id);
                        if ($stmt_doc_reget->execute()) {
                            $result_doc_reget = $stmt_doc_reget->get_result();
                            if ($doc_row_reget = $result_doc_reget->fetch_assoc()) {
                                $doctor_name = $doc_row_reget['name'];
                            }
                        }
                        $stmt_doc_reget->close();
                    }
                }
            }
        ?>
            <div class="appointment-summary">
                <h2>Appointment Details</h2>
                <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($doctor_name); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(isset($appointment_date_str) ? date("l, F j, Y", strtotime($appointment_date_str)) : 'N/A'); ?></p>
                <p><strong>Time:</strong> <?php echo htmlspecialchars(isset($appointment_time_str) ? date("h:i A", strtotime($appointment_time_str)) : 'N/A'); ?></p>
                <p><strong>Consultation Fee:</strong> ৳<?php echo htmlspecialchars(number_format($consultation_fee, 2)); ?></p>
            </div>

            <form action="checkout.php" method="POST" class="checkout-form-appointment">
                <h2>Your Details</h2>
                <input type="hidden" name="doctor_id" value="<?php echo htmlspecialchars($doctor_id ?? ''); ?>">
                <input type="hidden" name="appointment_date" value="<?php echo htmlspecialchars($appointment_date_str ?? ''); ?>">
                <input type="hidden" name="appointment_time" value="<?php echo htmlspecialchars($appointment_time_str ?? ''); ?>">
                <input type="hidden" name="consultation_fee" value="<?php echo htmlspecialchars($consultation_fee ?? 0); ?>">

                <div class="form-group">
                    <label for="patient_name">Full Name <span class="required">*</span>:</label>
                    <input type="text" id="patient_name" name="patient_name" value="<?= htmlspecialchars($patient_name) ?>" <?= $patient_details_readonly ? 'readonly' : '' ?> required>
                </div>
                <div class="form-group">
                    <label for="patient_phone">Phone Number <span class="required">*</span>:</label>
                    <input type="tel" id="patient_phone" name="patient_phone" placeholder="e.g., 01xxxxxxxxx" value="<?= htmlspecialchars($_POST['patient_phone'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="patient_email">Email Address (Optional):</label>
                    <input type="email" id="patient_email" name="patient_email" value="<?= htmlspecialchars($_POST['patient_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="reason_for_visit">Reason for Visit (Briefly, Optional):</label>
                    <textarea id="reason_for_visit" name="reason_for_visit" rows="3"><?= htmlspecialchars($reason_for_visit) ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-confirm-appointment"><i class="fas fa-check-circle"></i> Confirm & Book Appointment</button>
                </div>
                <p class="form-note">By clicking "Confirm & Book Appointment", you agree to our terms and conditions.</p>
            </form>
        <?php endif; ?>

        <footer class="checkout-footer-appointment">
             <p>
                <a href="index.php" class="footer-link"><i class="fas fa-home"></i> Homepage</a> |
                <a href="doctors_serial.php" class="footer-link"><i class="fas fa-user-md"></i> Find Doctors</a>
             </p>
             <p>&copy; <?= date("Y"); ?> ABC Medical. All rights reserved.</p>
        </footer>

    </div>
</body>
</html>