<?php
session_start();

// --- DEBUGGING FLAGS (REMOVE OR SET TO 0 FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING FLAGS ---

$page_title = "My Records - ABC Medical";

// --- Database Connection Details ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null;
$feedback_message = ''; // For general feedback (errors/info)
$feedback_type = ''; // 'success', 'error', 'info'

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("Database connection failed in view_my_records.php: " . $conn->connect_error);
        throw new Exception("Database connection failed. Please try again later.");
    }
    $conn->set_charset("utf8mb4");
    date_default_timezone_set('Asia/Dhaka'); // Set timezone for consistency
} catch (Exception $e) {
    $feedback_message = $e->getMessage();
    $feedback_type = 'error';
    // If connection fails, no further DB operations will be attempted.
}

// --- User Authentication and Data Retrieval ---
$is_logged_in = false;
$user_id = $_SESSION['user_id'] ?? null;
$user_phone = null;
$user_display_name = 'Guest';
$user_email = null;

if ($conn && $conn->ping()) { // Only proceed if DB connection is alive
    if ($user_id) {
        // Fetch user's name, phone, and email from the database
        $stmt_user = $conn->prepare("SELECT name, phone, email FROM users WHERE id = ? LIMIT 1");
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            if ($stmt_user->execute()) {
                $result_user = $stmt_user->get_result();
                if ($result_user->num_rows === 1) {
                    $user_data = $result_user->fetch_assoc();
                    $user_display_name = htmlspecialchars($user_data['name']);
                    $user_phone = htmlspecialchars($user_data['phone']);
                    $user_email = htmlspecialchars($user_data['email']);
                    $is_logged_in = true; // User successfully authenticated
                } else {
                    // User ID in session doesn't match a valid user in DB
                    $feedback_message = "Your session is invalid. Please log in again.";
                    $feedback_type = 'error';
                    session_unset(); session_destroy(); // Invalidate session
                }
            } else {
                error_log("Error executing user data fetch in view_my_records.php: " . $stmt_user->error);
                $feedback_message = "A system error occurred while retrieving your details.";
                $feedback_type = 'error';
            }
            $stmt_user->close();
        } else {
            error_log("Error preparing user data fetch statement in view_my_records.php: " . $conn->error);
            $feedback_message = "A system error occurred. Please try again later.";
            $feedback_type = 'error';
        }
    } else {
        // No user ID in session
        $feedback_message = "You are not logged in. Please log in to view your records.";
        $feedback_type = 'info';
    }
} else {
    // DB connection failed, feedback_message and feedback_type are already set by catch block
}


$patient_appointments = [];
$patient_pharmacy_orders = [];

// Only attempt to fetch records if user is logged in AND database connection is okay
if ($is_logged_in && $conn && $conn->ping()) {
    // --- Fetch Appointment History ---
    $stmt_appts = $conn->prepare(
        "SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status, a.consultation_fee, d.name AS doctor_name
         FROM appointments a
         JOIN doctors d ON a.doctor_id = d.id
         WHERE a.patient_phone = ? -- Filter by phone, assuming unique for user
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    if ($stmt_appts) {
        $stmt_appts->bind_param("s", $user_phone);
        if ($stmt_appts->execute()) {
            $result_appts = $stmt_appts->get_result();
            while ($row = $result_appts->fetch_assoc()) {
                $patient_appointments[] = $row;
            }
        } else {
            error_log("Error fetching appointments in view_my_records.php: " . $stmt_appts->error);
            $feedback_message = "Error retrieving appointment history.";
            $feedback_type = 'error';
        }
        $stmt_appts->close();
    } else {
        error_log("Error preparing appointment statement in view_my_records.php: " . $conn->error);
        $feedback_message = "A system error occurred while preparing appointment data.";
        $feedback_type = 'error';
    }

    // --- Fetch Pharmacy Order History ---
    $stmt_orders = $conn->prepare(
        "SELECT order_id, order_date, total_amount, order_status, payment_method, prescription_image_url, prescription_status
         FROM orders
         WHERE customer_phone = ?
         ORDER BY order_date DESC"
    );
    if ($stmt_orders) {
        $stmt_orders->bind_param("s", $user_phone);
        if ($stmt_orders->execute()) {
            $result_orders = $stmt_orders->get_result();
            while ($order_row = $result_orders->fetch_assoc()) {
                $order_items = [];
                $stmt_items = $conn->prepare(
                    "SELECT oi.quantity_ordered, oi.price_per_unit, m.name AS medicine_name, m.strength, m.form
                     FROM order_items oi
                     JOIN medicines m ON oi.medicine_id = m.id
                     WHERE oi.order_id = ?"
                );
                if ($stmt_items) {
                    $stmt_items->bind_param("i", $order_row['order_id']);
                    if ($stmt_items->execute()) {
                        $result_items = $stmt_items->get_result();
                        while ($item_row = $result_items->fetch_assoc()) {
                            $order_items[] = $item_row;
                        }
                    } else {
                        error_log("Error fetching order items for order " . $order_row['order_id'] . " in view_my_records.php: " . $stmt_items->error);
                    }
                    $stmt_items->close();
                } else {
                    error_log("Error preparing order items statement in view_my_records.php: " . $conn->error);
                }
                $order_row['items'] = $order_items;
                $patient_pharmacy_orders[] = $order_row;
            }
        } else {
            error_log("Error fetching orders in view_my_records.php: " . $stmt_orders->error);
            $feedback_message = "Error retrieving pharmacy order history.";
            $feedback_type = 'error';
        }
        $stmt_orders->close();
    } else {
        error_log("Error preparing orders statement in view_my_records.php: " . $conn->error);
        $feedback_message = "A system error occurred while preparing order data.";
        $feedback_type = 'error';
    }
}

// Close DB connection if it was successfully established
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
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        .my-records-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            position: relative; /* For fixed elements if needed */
        }
        .my-records-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .my-records-header h1 {
            color: #007bff;
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .my-records-header p {
            color: #666;
            font-size: 1.1em;
        }

        /* Feedback Messages */
        .feedback-message {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .feedback-message i {
            font-size: 1.2em;
        }
        .feedback-message p {
            margin: 0;
        }

        /* User Welcome / Important Notice */
        .welcome-user {
            background-color: #e0ffe0;
            color: #228b22;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.1em;
            font-weight: 600;
        }
        .important-notice {
            padding: 15px;
            margin-top: 20px;
            border-left: 5px solid #007bff;
            background-color: #e7f3ff;
            color: #004085;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
        }
        .important-notice i {
            font-size: 1.5em;
        }

        /* Tabs Styling */
        .record-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            overflow-x: auto; /* Allow horizontal scroll on small screens */
        }
        .tab-link {
            background-color: #f1f1f1;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 20px;
            transition: 0.3s;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap; /* Prevent wrapping */
            flex-shrink: 0; /* Prevent shrinking */
        }
        .tab-link:hover {
            background-color: #ddd;
        }
        .tab-link.active {
            background-color: #007bff;
            color: white;
            border-bottom: 2px solid #0056b3;
        }
        .tab-content {
            padding: 20px 0px;
            border-top: none;
        }
        .tab-content h2 {
            color: #343a40;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table Styling */
        .table-responsive-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            min-width: 700px; /* Ensures table doesn't get too squished */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); /* Subtle shadow for table */
        }
        table, th, td {
            border: 1px solid #eee;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
            white-space: nowrap;
        }
        .status-scheduled, .status-order-pending { background-color: #007bff; } /* Blue */
        .status-confirmed, .status-order-processed { background-color: #28a745; } /* Green */
        .status-completed, .status-order-delivered { background-color: #17a2b8; } /* Teal */
        .status-cancelled, .status-order-cancelled { background-color: #dc3545; } /* Red */
        .status-pending, .status-rx-pending { background-color: #ffc107; color: #333;} /* Yellow */
        .status-order-shipped { background-color: #6c757d; } /* Gray */
        .status-rx-verified { background-color: #28a745; }
        .status-rx-rejected { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; } /* Fallback */


        /* Order Card Styling */
        .order-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .order-header h3 {
            margin: 0;
            font-size: 1.2em;
            color: #007bff;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .order-header .order-date {
            font-size: 0.8em;
            color: #666;
            margin-left: 10px;
        }
        .view-order-details-link {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .view-order-details-link:hover {
            text-decoration: underline;
        }
        .order-summary-details p {
            margin: 5px 0;
            font-size: 0.95em;
        }
        .order-items-summary-list {
            margin-top: 15px;
            border-top: 1px dashed #eee;
            padding-top: 10px;
        }
        .order-items-summary-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .order-items-summary-list ul li {
            padding: 5px 0;
            border-bottom: 1px dotted #f0f0f0;
            font-size: 0.9em;
            color: #555;
        }
        .order-items-summary-list ul li:last-child {
            border-bottom: none;
        }
        .prescription-link-inline {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #007bff;
            text-decoration: none;
            font-weight: normal;
        }
        .prescription-link-inline:hover {
            text-decoration: underline;
        }
        .no-records {
            text-align: center;
            padding: 20px;
            color: #555;
            font-style: italic;
        }

        /* Footer and Action Buttons */
        .my-records-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 0.9em;
            color: #888;
        }
        .my-records-footer p {
            margin-bottom: 5px;
        }
        .my-records-footer .footer-link {
            color: #007bff;
            text-decoration: none;
            margin: 0 5px;
        }
        .my-records-footer .footer-link:hover {
            text-decoration: underline;
        }
        .my-records-footer .button-link-secondary,
        .my-records-footer .button-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 5px;
            min-width: 120px;
            justify-content: center;
        }
        .my-records-footer .button-link { background-color: #007bff; color: white; border: 1px solid #007bff; }
        .my-records-footer .button-link:hover { background-color: #0056b3; border-color: #0056b3; }
        .my-records-footer .button-link-secondary { background-color: #6c757d; color: white; border: 1px solid #6c757d; }
        .my-records-footer .button-link-secondary:hover { background-color: #5a6268; border-color: #5a6268; }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .my-records-container {
                margin: 20px auto;
                padding: 15px;
            }
            .my-records-header h1 {
                font-size: 2em;
            }
            .record-tabs {
                flex-wrap: wrap; /* Allow tabs to wrap on smaller screens */
            }
            .tab-link {
                flex-grow: 1; /* Make tabs grow to fill space */
                padding: 10px 15px;
                font-size: 0.9em;
            }
            .tab-content h2 {
                font-size: 1.5em;
            }
            table th, table td {
                padding: 10px;
                font-size: 0.9em;
            }
        }

        @media (max-width: 600px) {
            table {
                /* Make table responsive on very small screens by hiding headers and using data-label */
                border: 0;
            }
            table thead {
                display: none;
            }
            table tr {
                margin-bottom: 10px;
                display: block;
                border: 1px solid #ddd;
                border-radius: 8px;
            }
            table td {
                display: block;
                text-align: right;
                font-size: 0.8em;
                border-bottom: 1px dotted #eee;
            }
            table td::before {
                content: attr(data-label);
                float: left;
                font-weight: bold;
                text-transform: uppercase;
                color: #555;
            }
            table td:last-child {
                border-bottom: 0;
            }
            .status-badge {
                float: right;
                margin-top: -5px; /* Adjust badge position */
            }
        }
    </style>
</head>
<body>
    <div class="my-records-container">
        <header class="my-records-header">
            <h1><i class="fas fa-book-medical"></i> My Medical Records</h1>
            <p>A summary of your appointments and pharmacy orders with ABC Medical.</p>
        </header>

        <?php if (!empty($feedback_message)): ?>
            <div class="feedback-message <?= htmlspecialchars($feedback_type); ?>">
                <i class="fas <?= $feedback_type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?>"></i>
                <p><?= $feedback_message; ?></p>
                <?php if (!$is_logged_in): // Only show login/register buttons if not logged in ?>
                    <p style="margin-top: 15px;">
                        <a href="login.php" class="button-link-secondary" style="margin-right: 10px;"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php" class="button-link"><i class="fas fa-user-plus"></i> Register</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_logged_in): ?>
            <p class="welcome-user">Welcome, <strong><?= $user_display_name; ?></strong> (Phone: <?= $user_phone; ?>)</p>
            <?php if ($user_email): ?>
                <p class="welcome-user" style="font-size: 0.9em;">Email: <?= $user_email; ?></p>
            <?php endif; ?>

            <div class="important-notice user-privacy-notice">
                <i class="fas fa-user-shield"></i> <strong>Privacy & Demo Note:</strong> This is a simplified records view. Real patient portals are secured with robust authentication and manage data with greater detail and privacy controls.
            </div>

            <div class="record-tabs">
                <button class="tab-link active" onclick="openRecordTab(event, 'appointments')"><i class="fas fa-calendar-alt"></i> Appointment History</button>
                <button class="tab-link" onclick="openRecordTab(event, 'pharmacy_orders')"><i class="fas fa-pills"></i> Pharmacy Orders</button>
            </div>

            <div id="appointments" class="tab-content" style="display:block;">
                <h2><i class="fas fa-calendar-alt"></i> Appointment History</h2>
                <?php if (!empty($patient_appointments)): ?>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Appt. ID</th>
                                    <th>Patient Name</th>
                                    <th>Patient Phone</th>
                                    <th>Doctor Name</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Fee (৳)</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patient_appointments as $appt): ?>
                                    <?php $status_class = 'status-' . strtolower(str_replace(' ', '-', $appt['status'])); ?>
                                    <tr>
                                        <td data-label='Appt. ID'>#<?= htmlspecialchars($appt['id']); ?></td>
                                        <td data-label='Patient Name'><?= htmlspecialchars($appt['patient_name']); ?></td>
                                        <td data-label='Patient Phone'><?= htmlspecialchars($appt['patient_phone']); ?></td>
                                        <td data-label='Doctor Name'>Dr. <?= htmlspecialchars($appt['doctor_name']); ?></td>
                                        <td data-label='Date'><?= htmlspecialchars(date("d M, Y", strtotime($appt['appointment_date']))); ?></td>
                                        <td data-label='Time'><?= htmlspecialchars(date("h:i A", strtotime($appt['appointment_time']))); ?></td>
                                        <td data-label='Fee (৳)'><?= isset($appt['consultation_fee']) ? number_format($appt['consultation_fee'], 2) : 'N/A'; ?></td>
                                        <td data-label='Reason'><?= htmlspecialchars($appt['reason'] ?: '-'); ?></td>
                                        <td data-label='Status'><span class="status-badge <?= $status_class; ?>"><?= htmlspecialchars($appt['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-records">No appointment history found for this account.</p>
                <?php endif; ?>
            </div>

            <div id="pharmacy_orders" class="tab-content">
                <h2><i class="fas fa-pills"></i> Pharmacy Order History</h2>
                <?php if (!empty($patient_pharmacy_orders)): ?>
                    <?php foreach ($patient_pharmacy_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <h3>Order #<?= htmlspecialchars($order['order_id']); ?>
                                    <span class="order-date">(<?= htmlspecialchars(date("d M Y, h:i A", strtotime($order['order_date']))); ?>)</span>
                                </h3>
                                <a href="order_confirmation.php?order_id=<?= htmlspecialchars($order['order_id']); ?>&type=pharmacy_order" class="view-order-details-link" title="View Full Order Details">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                            <div class="order-summary-details">
                                <p><strong>Total Amount:</strong> ৳<?= htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                <p><strong>Order Status:</strong> <span class="status-badge status-order-<?= strtolower(str_replace(' ', '-', $order['order_status'])); ?>"><?= htmlspecialchars($order['order_status']); ?></span></p>
                                <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']); ?></p>
                                <?php if(!empty($order['prescription_image_url'])): ?>
                                    <p><strong>Prescription:</strong>
                                        <?php
                                        // Assuming prescription_image_url stores a relative path like 'uploads/prescriptions/img.jpg'
                                        $prescription_web_path = '/' . ltrim($order['prescription_image_url'], '/');
                                        ?>
                                        <a href="<?= htmlspecialchars($prescription_web_path); ?>" target="_blank" class="prescription-link-inline">
                                            <i class="fas fa-eye"></i> View Uploaded Rx
                                        </a>
                                        (Status: <span class="status-badge status-rx-<?= strtolower(str_replace(' ', '-', htmlspecialchars($order['prescription_status'] ?? 'Unknown'))); ?>"><?= htmlspecialchars($order['prescription_status'] ?? 'Unknown'); ?></span>)
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($order['items'])): ?>
                            <div class="order-items-summary-list">
                                <strong>Items:</strong>
                                <ul>
                                    <?php foreach($order['items'] as $item): ?>
                                        <li><?= htmlspecialchars($item['medicine_name']); ?> (<?= htmlspecialchars($item['strength'] ?? '') . ' ' . htmlspecialchars($item['form'] ?? ''); ?>) - Qty: <?= htmlspecialchars($item['quantity_ordered']); ?> @ ৳<?= htmlspecialchars(number_format($item['price_per_unit'],2)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-records">No pharmacy order history found for this account.</p>
                <?php endif; ?>
            </div>
        <?php endif; // End check for is_logged_in ?>

        <footer class="my-records-footer">
            <p>
                <a href="index.php" class="button-link-secondary"><i class="fas fa-home"></i> Back to Homepage</a>
                <a href="doctors_serial.php" class="button-link"><i class="fas fa-user-md"></i> Book New Appointment</a>
            </p>
            <p>&copy; <?= date("Y"); ?> ABC Medical. All rights reserved.</p>
        </footer>
    </div>

<script>
function openRecordTab(evt, recordType) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(recordType).style.display = "block";
    evt.currentTarget.className += " active";
}
// Automatically open the first tab when the page loads
document.addEventListener("DOMContentLoaded", function() {
    // Only trigger if a user is logged in, otherwise the login prompt is displayed
    <?php if ($is_logged_in): ?>
        var firstTab = document.querySelector('.record-tabs .tab-link');
        if (firstTab) {
            firstTab.click();
        }
    <?php endif; ?>
});
</script>
</body>
</html>