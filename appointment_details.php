<?php
session_start();

// --- DEBUGGING FLAGS (REMOVE OR SET TO 0 FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING FLAGS ---

// --- Database Connection Details ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null;
$page_title = "Appointment Details - ABC Medical";
$appointment_details = null;
$error_message = '';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        error_log("DB Connection Failed in appointment_details.php: " . $conn->connect_error);
        throw new Exception("Database connection failed. Please try again later.");
    }
    $conn->set_charset("utf8mb4");
    date_default_timezone_set('Asia/Dhaka'); // Set to your clinic's timezone
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // If not logged in, set error message and prevent further execution
    $error_message = "You must be logged in to view appointment details. Please <a href='login.php'>Login</a>.";
} else {
    // Only proceed if database connection is successful and user is logged in
    if ($conn && $conn->ping()) {
        $appointment_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

        if ($appointment_id === false || $appointment_id <= 0) {
            $error_message = "Invalid appointment ID provided.";
        } else {
            // Fetch appointment details, ensuring it belongs to the logged-in user
            $sql_details = "
                SELECT
                    a.id,
                    a.patient_name,
                    a.patient_phone,
                    a.patient_email,
                    d.name AS doctor_name,
                    s.name AS specialization_name,
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
                LEFT JOIN
                    specializations s ON d.specialization_id = s.id
                WHERE
                    a.id = ? AND a.patient_id = ?
                LIMIT 1
            ";

            $stmt_details = $conn->prepare($sql_details);
            if ($stmt_details) {
                $stmt_details->bind_param("ii", $appointment_id, $user_id);
                if ($stmt_details->execute()) {
                    $result_details = $stmt_details->get_result();
                    if ($result_details->num_rows === 1) {
                        $appointment_details = $result_details->fetch_assoc();
                    } else {
                        $error_message = "Appointment not found or does not belong to your account.";
                    }
                } else {
                    $error_message = "Error fetching appointment details: " . $stmt_details->error;
                    error_log("Error fetching appointment details for ID " . $appointment_id . ": " . $stmt_details->error);
                }
                $stmt_details->close();
            } else {
                $error_message = "Database query error: " . $conn->error;
                error_log("Failed to prepare appointment details query: " . $conn->error);
            }
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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base styles for body and container */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Ensures content is vertically centered */
        }
        .details-container {
            max-width: 700px;
            width: 95%; /* Adjust for smaller screens */
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            box-sizing: border-box; /* Include padding in element's total width/height */
        }

        /* Header styles */
        .details-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee; /* Separator line */
        }
        .details-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            color: #007bff; /* Primary brand color */
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px; /* Space between icon and text */
        }
        .details-header h1 i {
            font-size: 1.1em;
        }

        /* Section styling for grouped details */
        .details-section {
            margin-bottom: 25px;
            padding: 15px 20px;
            background-color: #f8f9fa; /* Light background for sections */
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); /* Subtle shadow for depth */
        }
        .details-section h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.6rem;
            color: #2c3e50; /* Darker heading color */
            margin-top: 0;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Individual detail item styling */
        .detail-item {
            display: flex;
            justify-content: space-between; /* Pushes label to left, value to right */
            padding: 8px 0;
            border-bottom: 1px dashed #eef; /* Dashed separator for items */
            font-size: 1rem;
        }
        .detail-item:last-child {
            border-bottom: none; /* No border for the last item in a section */
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            flex-basis: 40%; /* Allocate space for label */
            text-align: left;
        }
        .detail-value {
            color: #333;
            flex-basis: 60%; /* Allocate space for value */
            text-align: right;
            word-wrap: break-word; /* Prevents long text from overflowing */
        }
        .detail-value.reason-text {
            text-align: left; /* Specific alignment for the reason text */
            padding-top: 5px;
            font-style: italic;
            color: #666;
        }

        /* Status badge styling (reused from appointments.php) */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-scheduled { background-color: #007bff; }
        .status-confirmed { background-color: #28a745; }
        .status-completed { background-color: #17a2b8; }
        .status-cancelled { background-color: #dc3545; }
        .status-pending { background-color: #ffc107; color: #333;}

        /* Action button styles */
        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
            gap: 8px; /* Space between icon and text */
            margin-top: 20px;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); /* Consistent button shadow */
        }
        .action-button.go-back {
            background-color: #6c757d; /* Neutral gray for back button */
            color: white;
        }
        .action-button.go-back:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        .action-button.view-report {
            background-color: #17a2b8; /* Teal for report button */
            color: white;
            margin-left: 15px; /* Space from back button */
        }
        .action-button.view-report:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }
        .action-button.print-option { /* New style for print button */
            background-color: #6f42c1; /* Purple */
            color: white;
            margin-left: 15px;
        }
        .action-button.print-option:hover {
            background-color: #5c3596;
            transform: translateY(-2px);
        }

        /* Message box for errors/info */
        .message-box {
            padding: 20px;
            background-color: #fff3cd; /* Light yellow for info */
            color: #856404;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            text-align: center;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        .message-box.error {
            background-color: #f8d7da; /* Light red for error */
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .details-container {
                padding: 20px;
                margin: 20px auto;
            }
            .details-header h1 {
                font-size: 1.8rem;
            }
            .details-section h2 {
                font-size: 1.4rem;
            }
            /* Stack label and value on small screens */
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 0;
            }
            .detail-label, .detail-value {
                flex-basis: 100%; /* Take full width */
                text-align: left;
            }
            .detail-label {
                margin-bottom: 5px; /* Space between label and value */
            }
            /* Make action buttons full width */
            .action-button {
                width: 100%;
                margin-left: 0; /* Remove left margin if stacked */
                margin-bottom: 10px; /* Space between stacked buttons */
            }
            .action-button:last-of-type {
                margin-bottom: 0;
            }
            .action-button.print-option {
                margin-left: 0; /* Ensure no extra margin when stacked */
            }
        }

        /* Print-specific styles */
        @media print {
            body {
                background-color: #fff;
                margin: 0;
                padding: 0;
            }
            .details-container {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: 100%;
                width: 100%;
                padding: 20mm; /* Add some print margin */
            }
            .details-header {
                border-bottom: 2px solid #333; /* Stronger border for print */
                padding-bottom: 10mm;
                margin-bottom: 15mm;
            }
            .details-header h1 {
                font-size: 24pt;
                color: #000; /* Black for print */
            }
            .details-section {
                box-shadow: none;
                border: none;
                background-color: transparent;
                padding: 0;
                margin-bottom: 10mm;
            }
            .details-section h2 {
                font-size: 18pt;
                color: #000;
                margin-bottom: 5mm;
                text-align: left; /* Align section titles to left for print */
            }
            .detail-item {
                border-bottom: 1px solid #ddd;
                padding: 5mm 0;
                font-size: 12pt;
                flex-direction: row; /* Ensure side-by-side for print if space allows */
                justify-content: space-between;
                align-items: baseline;
            }
            .detail-item:last-child {
                border-bottom: none;
            }
            .detail-label {
                font-weight: bold;
                color: #000;
                flex-basis: 35%; /* Adjust width for print */
            }
            .detail-value {
                color: #333;
                flex-basis: 65%;
                text-align: right;
            }
            .detail-value.reason-text {
                text-align: left;
                font-size: 12pt;
            }
            .status-badge {
                padding: 4mm 8mm;
                font-size: 10pt;
            }

            /* Hide elements not needed for print */
            .action-button,
            .message-box {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="details-container">
        <header class="details-header">
            <h1><i class="fas fa-file-invoice"></i> Appointment Details</h1>
        </header>

        <?php if ($error_message): ?>
            <div class="message-box error">
                <p><?= $error_message ?></p>
                <?php if ($user_id): // If logged in but error occurred after login check ?>
                    <a href="appointments.php" class="action-button go-back"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
                <?php else: // If not logged in, suggest logging in ?>
                    <a href="login.php" class="action-button go-back"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        <?php elseif ($appointment_details): ?>
            <div class="details-section">
                <h2>Appointment #<?= htmlspecialchars($appointment_details['id']) ?></h2>
                <div class="detail-item">
                    <span class="detail-label">Doctor:</span>
                    <span class="detail-value">Dr. <?= htmlspecialchars($appointment_details['doctor_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Specialization:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment_details['specialization_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value"><?= htmlspecialchars(date("d M, Y", strtotime($appointment_details['appointment_date']))) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value"><?= htmlspecialchars(date("h:i A", strtotime($appointment_details['appointment_time']))) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Consultation Fee:</span>
                    <span class="detail-value">৳<?= htmlspecialchars(number_format($appointment_details['consultation_fee'], 2)) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patient Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment_details['patient_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patient Phone:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment_details['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patient Email:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment_details['patient_email'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <?php $status_class = 'status-' . strtolower(str_replace(' ', '-', htmlspecialchars($appointment_details["status"]))); ?>
                        <span class='status-badge <?= $status_class ?>'><?= htmlspecialchars($appointment_details["status"]) ?></span>
                    </span>
                </div>
            </div>

            <div class="details-section">
                <h2>Reason for Appointment</h2>
                <p class="detail-value reason-text">
                    <?= !empty($appointment_details['reason']) ? nl2br(htmlspecialchars($appointment_details['reason'])) : 'No reason provided.'; ?>
                </p>
            </div>

            <div style="text-align: center; margin-top: 30px;" class="print-hide-actions">
                <a href="appointments.php" class="action-button go-back"><i class="fas fa-arrow-left"></i> Back to My Appointments</a>
                <?php if (strtolower($appointment_details['status']) === 'completed' && !empty($appointment_details['report_url'])): ?>
                    <a href="<?= htmlspecialchars($appointment_details['report_url']) ?>" target="_blank" class="action-button view-report">
                        <i class="fas fa-file-alt"></i> View Report
                    </a>
                <?php endif; ?>
                <button type="button" onclick="window.print()" class="action-button print-option">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>