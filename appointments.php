<?php
session_start();

// --- DEBUGGING FLAGS (REMOVE OR SET TO 0 FOR PRODUCTION) ---
ini_set('display_errors', 1); // Display errors on screen (for development)
ini_set('display_startup_errors', 1); // Display startup errors
error_reporting(E_ALL); // Report all PHP errors
// --- END DEBUGGING FLAGS ---

// --- Database Connection Details ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null; // Initialize connection variable
$page_title = "My Appointments - ABC Medical";
$feedback_message = ''; // Message for success, info, or general errors
$feedback_type = '';   // 'success', 'info', 'error'

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        // Log critical connection error to server's error logs
        error_log("DB Connection Failed in appointments.php: " . $conn->connect_error);
        throw new Exception("Database connection failed. Please try again later.");
    }
    $conn->set_charset("utf8mb4");
    date_default_timezone_set('Asia/Dhaka'); // Set local timezone
} catch (Exception $e) {
    // Catch any connection-related exceptions
    $feedback_message = $e->getMessage();
    $feedback_type = 'error';
    // No further DB operations will be attempted if connection failed
}

// --- User Authentication and Data Retrieval ---
$is_logged_in = false;
$user_id = $_SESSION['user_id'] ?? null; // Get user ID from session
$user_display_name = 'Guest';
$patient_appointments = []; // Array to hold fetched appointments

// Filters from GET parameters
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Array of valid statuses for the filter dropdown
$valid_statuses = ['All', 'Scheduled', 'Confirmed', 'Completed', 'Cancelled', 'Pending'];


// Handle feedback from actions like cancellation
if (isset($_GET['action_feedback']) && isset($_GET['action_type'])) {
    $feedback_message = htmlspecialchars(urldecode($_GET['action_feedback']));
    $feedback_type = htmlspecialchars($_GET['action_type']);
}


// Proceed only if database connection was successful
if ($conn && $conn->ping()) { // ping() checks if the connection is alive
    if ($user_id) {
        $stmt_user = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            if ($stmt_user->execute()) {
                $result_user = $stmt_user->get_result();
                if ($result_user->num_rows === 1) {
                    $user_data = $result_user->fetch_assoc();
                    $user_display_name = htmlspecialchars($user_data['name']);
                    $is_logged_in = true; // User is authenticated
                } else {
                    // User ID in session does not exist or invalid in DB
                    $feedback_message = "Your session is invalid. Please log in again.";
                    $feedback_type = 'error';
                    session_unset(); // Clear all session data
                    session_destroy(); // Destroy the session
                    error_log("Session user_id: " . $user_id . " not found in DB or invalid on appointments.php");
                }
            } else {
                // Error executing user data fetch
                $feedback_message = "Error retrieving your user details. Please try again.";
                $feedback_type = 'error';
                error_log("Failed to execute user data fetch in appointments.php: " . $stmt_user->error);
            }
            $stmt_user->close();
        } else {
            // Error preparing user data fetch statement
            $feedback_message = "A system error occurred. Please try again later.";
            $feedback_type = 'error';
            error_log("Failed to prepare user data fetch statement in appointments.php: " . $conn->error);
        }
    } else {
        // No user ID in session
        $feedback_message = "You are not logged in. Please log in to view your appointments.";
        $feedback_type = 'info'; // Use info type for "not logged in"
    }

    // --- Fetch Appointments (only if user is logged in and user_id is available) ---
    if ($is_logged_in && $user_id) { // Use $user_id for fetching appointments
        $where_clauses = ["a.patient_id = ?"];
        $params = [$user_id];
        $param_types = "i";

        // Add date range filter
        if (!empty($filter_start_date)) {
            // Validate date format, prevent SQL injection
            if (DateTime::createFromFormat('Y-m-d', $filter_start_date) !== false) {
                $where_clauses[] = "a.appointment_date >= ?";
                $params[] = $filter_start_date;
                $param_types .= "s";
            } else {
                $feedback_message = "Invalid start date format. Showing all dates.";
                $feedback_type = 'info';
                $filter_start_date = ''; // Clear invalid filter
            }
        }
        if (!empty($filter_end_date)) {
            // Validate date format, prevent SQL injection
            if (DateTime::createFromFormat('Y-m-d', $filter_end_date) !== false) {
                $where_clauses[] = "a.appointment_date <= ?";
                $params[] = $filter_end_date;
                $param_types .= "s";
            } else {
                $feedback_message = "Invalid end date format. Showing all dates.";
                $feedback_type = 'info';
                $filter_end_date = ''; // Clear invalid filter
            }
        }

        // Add status filter
        if (!empty($filter_status) && $filter_status !== 'All' && in_array($filter_status, $valid_statuses)) {
            $where_clauses[] = "a.status = ?";
            $params[] = $filter_status;
            $param_types .= "s";
        }

        $where_sql = " WHERE " . implode(" AND ", $where_clauses);

        $sql_appointments = "
            SELECT
                a.id,
                a.patient_name,
                a.doctor_id, -- Needed for reschedule link
                d.name AS doctor_name,
                a.appointment_date,
                a.appointment_time,
                a.consultation_fee,
                a.reason,
                a.status,
                a.report_url
            FROM
                appointments a
            JOIN
                doctors d ON a.doctor_id = d.id
            " . $where_sql . "
            ORDER BY
                a.appointment_date DESC, a.appointment_time DESC
        ";

        $stmt_appointments = $conn->prepare($sql_appointments);
        if ($stmt_appointments) {
            // --- FIX FOR bind_param WARNINGS ---
            $bind_params = [$param_types]; // Start with the type string
            for ($i = 0; $i < count($params); $i++) {
                $bind_params[] = &$params[$i]; // Add references to the actual parameters
            }
            // Use call_user_func_array with the references
            call_user_func_array([$stmt_appointments, 'bind_param'], $bind_params);
            // --- END FIX ---

            if ($stmt_appointments->execute()) {
                $result_appointments = $stmt_appointments->get_result();
                if ($result_appointments->num_rows > 0) {
                    while ($row = $result_appointments->fetch_assoc()) {
                        $patient_appointments[] = $row;
                    }
                } else {
                    $feedback_message = "No appointments found matching your filters.";
                    $feedback_type = 'info';
                }
            } else {
                $feedback_message = "Error fetching your appointments from the database.";
                $feedback_type = 'error';
                error_log("Failed to execute appointments query for user_id: " . $user_id . " Error: " . $stmt_appointments->error);
            }
            $stmt_appointments->close();
        } else {
            $feedback_message = "A system error occurred while preparing to fetch appointments.";
            $feedback_type = 'error';
            error_log("Failed to prepare appointments query in appointments.php: " . $conn->error);
        }
    }
}

// Close the database connection if it was successfully established
if ($conn && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Roboto', Arial, sans-serif; /* Prefer Roboto, fallback to Arial */
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 1200px; /* Increased max-width for filters */
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            box-sizing: border-box; /* Include padding in width */
        }
        h1 {
            font-family: 'Montserrat', sans-serif; /* Use Montserrat for headings */
            text-align: center;
            color: #007bff;
            margin-bottom: 30px;
            font-size: 2.2em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .feedback-message {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); /* Added subtle shadow */
            line-height: 1.4;
        }
        .feedback-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .feedback-message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .feedback-message.success { /* Added success style */
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .feedback-message i {
            font-size: 1.2em;
        }
        .feedback-message p {
            margin: 0;
        }
        .feedback-message .button-link, .feedback-message .button-link-secondary {
            margin-top: 10px; /* Space between text and buttons */
            font-size: 0.9em;
            padding: 8px 15px;
            min-width: auto; /* Allow buttons to size naturally */
            flex-shrink: 0; /* Prevent buttons from shrinking */
        }

        /* Filter Section */
        .filter-section {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .filter-group {
            flex: 1; /* Allows groups to grow */
            min-width: 180px; /* Minimum width before wrapping */
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .filter-group input[type="date"],
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            -webkit-appearance: none; /* Remove default dropdown arrow */
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cpath%20fill%3D%22%23495057%22%20d%3D%22M205.957%2090.009L128%20167.966%2050.043%2090.009z%22%2F%3E%3C%2Fsvg%3E'); /* Custom dropdown arrow */
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
        }
        .filter-group input[type="date"]:focus,
        .filter-group select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        .filter-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 600;
        }
        .filter-buttons .btn-primary-filter {
            background-color: #007bff;
            color: white;
        }
        .filter-buttons .btn-primary-filter:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .filter-buttons .btn-secondary-filter {
            background-color: #e9ecef;
            color: #333;
        }
        .filter-buttons .btn-secondary-filter:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
        }


        /* Table Styling */
        .table-responsive-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e9ecef; /* Added border to wrapper */
            border-radius: 8px; /* Rounded corners for wrapper */
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); /* Subtle shadow for table */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0; /* Remove bottom margin if wrapper has it */
            min-width: 950px; /* Adjusted min-width to accommodate new columns */
        }
        table thead tr {
            background-color: #f2f2f2; /* Light gray header */
            border-bottom: 2px solid #e9ecef;
        }
        th, td {
            padding: 14px 18px; /* More padding */
            text-align: left;
            border: none; /* Remove individual cell borders */
            vertical-align: middle; /* Align content vertically */
        }
        th {
            color: #333;
            font-weight: 700; /* Bolder headers */
            font-size: 0.95em;
            text-transform: uppercase; /* Uppercase for headers */
        }
        tbody tr:nth-child(odd) { /* Zebra striping */
            background-color: #ffffff;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: #e6f7ff; /* Light blue on hover */
        }
        /* Style for data-label (for responsive tables) */
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                border: 1px solid #ddd;
                margin-bottom: 10px;
                border-radius: 8px;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            td:before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                color: #555;
            }
            /* Specific adjustments for status badge and action button alignment */
            td[data-label='Status'] {
                text-align: right;
            }
            td[data-label='Actions'] {
                text-align: right;
                display: flex; /* Use flex to align buttons */
                flex-wrap: wrap; /* Allow buttons to wrap */
                justify-content: flex-end; /* Align to the right */
                gap: 5px; /* Space between buttons */
                padding-top: 10px; /* Add some space above buttons */
                border-top: 1px solid #eee; /* Separator for actions */
                margin-top: 10px;
            }
            .action-button {
                width: auto; /* Let buttons size naturally */
                flex-grow: 1; /* Allow them to grow if space allows */
                max-width: 100%; /* Prevent overflow */
            }
        }


        .status-badge {
            display: inline-block;
            padding: 6px 12px; /* Slightly larger padding */
            border-radius: 20px; /* More rounded */
            font-size: 0.8em;
            font-weight: 700; /* Bolder text */
            color: white;
            text-transform: uppercase; /* Uppercase for consistency */
            letter-spacing: 0.5px;
        }
        .status-scheduled { background-color: #007bff; }
        .status-confirmed { background-color: #28a745; }
        .status-completed { background-color: #17a2b8; }
        .status-cancelled { background-color: #dc3545; }
        .status-pending { background-color: #ffc107; color: #333;}

        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            font-size: 0.85em; /* Slightly smaller font for action buttons */
            font-weight: 600;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.2s ease, transform 0.2s ease;
            gap: 5px;
            margin-right: 5px; /* Space between action buttons if multiple */
            white-space: nowrap; /* Prevent button text from wrapping */
        }
        .action-button.view-details {
            background-color: #6c757d; /* Gray for details */
            color: white;
        }
        .action-button.view-details:hover {
            background-color: #5a6268;
            transform: translateY(-1px);
        }
        .action-button.view-report {
            background-color: #17a2b8; /* Teal */
            color: white;
        }
        .action-button.view-report:hover {
            background-color: #138496;
            transform: translateY(-1px);
        }
        .action-button.reschedule { /* Style for reschedule */
            background-color: #ffc107; /* Yellow */
            color: #333; /* Dark text for contrast */
        }
        .action-button.reschedule:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
        }
        .action-button.cancel { /* Style for cancel */
            background-color: #dc3545; /* Red */
            color: white;
        }
        .action-button.cancel:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }


        .page-actions {
            margin-top: 40px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .button-link, .button-link-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 200px;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); /* Added shadow to main buttons */
        }
        .button-link {
            background-color: #007bff;
            color: white;
            border: 1px solid #007bff;
        }
        .button-link:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px); /* Lift on hover */
        }
        .button-link-secondary {
            background-color: #6c757d;
            color: white;
            border: 1px solid #6c757d;
        }
        .button-link-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: translateY(-2px); /* Lift on hover */
        }
        .welcome-user-message {
            background-color: #e0ffe0;
            color: #228b22;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.1em;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(34,139,34,0.1);
        }
        .no-appointments-message {
            text-align: center;
            padding: 30px;
            font-size: 1.1em;
            color: #777;
            background-color: #fdfdfd;
            border: 1px dashed #ced4da;
            border-radius: 8px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>

        <?php if (!empty($feedback_message)): // Display feedback messages (error, info) ?>
            <div class="feedback-message <?= htmlspecialchars($feedback_type) ?>">
                <i class="fas <?= $feedback_type === 'error' ? 'fa-exclamation-triangle' : ($feedback_type === 'success' ? 'fa-check-circle' : 'fa-info-circle') ?>"></i>
                <p><?= $feedback_message ?></p>
                <?php if ($feedback_type === 'error' || ($feedback_type === 'info' && !$is_logged_in)): // Show login/register buttons if not logged in or on error ?>
                    <div style="width: 100%; text-align: center;">
                        <a href="login.php" class="button-link-secondary"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php" class="button-link"><i class="fas fa-user-plus"></i> Register</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_logged_in): ?>
            <p class="welcome-user-message">Welcome, <?= $user_display_name ?>! Here are your scheduled appointments.</p>

            <section class="filter-section">
                <form action="appointments.php" method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <?php foreach ($valid_statuses as $status_option): ?>
                                <option value="<?= htmlspecialchars($status_option) ?>" <?= ($filter_status === $status_option) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($status_option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn-primary-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                        <button type="button" class="btn-secondary-filter" onclick="window.location.href='appointments.php'">
                            <i class="fas fa-sync-alt"></i> Reset
                        </button>
                    </div>
                </form>
            </section>

            <?php if (!empty($patient_appointments)): ?>
                <div class='table-responsive-wrapper'>
                    <table>
                        <thead>
                            <tr>
                                <th>Appt. ID</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Fee (৳)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_datetime = new DateTime('now', new DateTimeZone('Asia/Dhaka')); // Use Bangladesh timezone
                            foreach($patient_appointments as $row):
                                $status_class = 'status-' . strtolower(str_replace(' ', '-', htmlspecialchars($row["status"])));
                                $appointment_datetime_str = $row["appointment_date"] . ' ' . $row["appointment_time"];
                                $appointment_datetime = new DateTime($appointment_datetime_str, new DateTimeZone('Asia/Dhaka'));
                                $is_future_appointment = $appointment_datetime > $current_datetime;
                            ?>
                                <tr>
                                    <td data-label='Appt. ID'><?= htmlspecialchars($row["id"]) ?></td>
                                    <td data-label='Doctor'>Dr. <?= htmlspecialchars($row["doctor_name"]) ?></td>
                                    <td data-label='Date'><?= htmlspecialchars(date("d M, Y", strtotime($row["appointment_date"]))) ?></td>
                                    <td data-label='Time'><?= htmlspecialchars(date("h:i A", strtotime($row["appointment_time"]))) ?></td>
                                    <td data-label='Fee (৳)'><?= (isset($row["consultation_fee"]) ? htmlspecialchars(number_format($row["consultation_fee"], 2)) : 'N/A') ?></td>
                                    <td data-label='Status'><span class='status-badge <?= $status_class ?>'><?= htmlspecialchars($row["status"]) ?></span></td>
                                    <td data-label='Actions'>
                                        <a href="appointment_details.php?id=<?= htmlspecialchars($row['id']) ?>" class="action-button view-details">
                                            <i class="fas fa-info-circle"></i> Details
                                        </a>
                                        <?php if (strtolower($row['status']) === 'completed' && !empty($row['report_url'])): ?>
                                            <a href="<?= htmlspecialchars($row['report_url']) ?>" target="_blank" class="action-button view-report">
                                                <i class="fas fa-file-alt"></i> Report
                                            </a>
                                        <?php elseif ($is_future_appointment && (strtolower($row['status']) === 'scheduled' || strtolower($row['status']) === 'confirmed' || strtolower($row['status']) === 'pending')): ?>
                                            <a href="doctor_availability.php?doctor_id=<?= htmlspecialchars($row['doctor_id']) ?>" class="action-button reschedule">
                                                <i class="fas fa-sync-alt"></i> Reschedule
                                            </a>
                                            <a href="cancel_appointment.php?id=<?= htmlspecialchars($row['id']) ?>" class="action-button cancel" onclick="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.');">
                                                <i class="fas fa-times-circle"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: // If logged in but no appointments after filtering ?>
                <p class="no-appointments-message">No appointments found matching your criteria.</p>
            <?php endif; ?>

        <?php endif; // End check for $is_logged_in ?>

        <div class="page-actions">
            <a href="index.php" class="button-link-secondary"><i class="fas fa-home"></i> Go to Homepage</a>
            <a href="doctors_serial.php" class="button-link"><i class="fas fa-user-md"></i> Book New Appointment</a>
        </div>
    </div>

</body>
</html>