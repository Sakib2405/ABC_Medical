<?php
session_start();

// Set error reporting for development (IMPORTANT: CHANGE FOR PRODUCTION)
// In a production environment, set display_errors to 0 and log errors to a file.
ini_set('display_errors', 1); // Set to 0 in production
error_reporting(E_ALL); // Set to 0 or E_ERROR | E_WARNING | E_PARSE in production

$page_title = "My Records - ABC Medical";

// --- DATABASE CONNECTION ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null;
$feedback_message = ''; // For general feedback messages
$feedback_type = ''; // 'success', 'error', 'info'

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        // Log the actual connection error for debugging, but show a generic message to the user.
        error_log("Database connection error: " . $conn->connect_error);
        throw new Exception("Connection failed: Please try again later."); // Generic message
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // This catch block handles the initial database connection failure.
    $feedback_message = "Sorry, we're experiencing technical difficulties. Please try again later.";
    $feedback_type = 'error';
    // Redirect to homepage with an error message if DB connection fails
    // Using exit after header to ensure no further code execution.
    header("Location: index.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
    exit;
}

// Set default timezone for PHP date/time functions to Dhaka (Bangladesh)
date_default_timezone_set('Asia/Dhaka');

// --- USER AUTHENTICATION & DATA FETCH ---
$is_logged_in = false;
$user_phone = null;
$user_id = null;
$user_display_name = 'Guest';
$user_email = null;

// Check if user is logged in via session
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Fetch user details from the database based on user_id
    // This ensures that the session user_id still corresponds to a valid user in the DB
    $stmt_user = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        if ($stmt_user->execute()) {
            $result_user = $stmt_user->get_result();
            if ($result_user->num_rows === 1) {
                $user_data_db = $result_user->fetch_assoc();
                $is_logged_in = true;
                $user_display_name = htmlspecialchars($user_data_db['name']);
                $user_email = htmlspecialchars($user_data_db['email']);
                $user_phone = htmlspecialchars($user_data_db['phone']);
            } else {
                // User ID in session does not match a record or no phone found. Invalidate session.
                session_unset();
                session_destroy();
                // Consider regenerating session ID on next login for security: session_regenerate_id(true);
                $feedback_message = "Your session is invalid or user data not found. Please log in again.";
                $feedback_type = 'error';
            }
        } else {
            error_log("Error executing user fetch query: " . $stmt_user->error);
            $feedback_message = "Failed to retrieve user details due to a system error. Please try again.";
            $feedback_type = 'error';
        }
        $stmt_user->close();
    } else {
        error_log("Error preparing user fetch statement: " . $conn->error);
        $feedback_message = "A system error occurred while preparing user data. Please try again.";
        $feedback_type = 'error';
    }
}

$patient_appointments = [];
$patient_pharmacy_orders = [];

// Only fetch records if user is truly logged in and phone is known (from DB fetch)
if ($is_logged_in && $user_phone) {
    // --- Fetch Appointment History ---
    $sql_appts = "SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status, a.consultation_fee, d.name AS doctor_name
                  FROM appointments a
                  JOIN doctors d ON a.doctor_id = d.id
                  WHERE a.patient_phone = ?
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $stmt_appts = $conn->prepare($sql_appts);
    if ($stmt_appts) {
        $stmt_appts->bind_param("s", $user_phone);
        if ($stmt_appts->execute()) {
            $result_appts = $stmt_appts->get_result();
            while ($row = $result_appts->fetch_assoc()) {
                $patient_appointments[] = $row;
            }
        } else {
            error_log("Error fetching appointments for phone " . $user_phone . ": " . $stmt_appts->error);
            // General error message for user, detailed error logged.
            if (empty($feedback_message)) { // Don't overwrite existing critical messages
                $feedback_message = "Could not retrieve appointment history due to a system error.";
                $feedback_type = 'error';
            }
        }
        $stmt_appts->close();
    } else {
        error_log("Error preparing appointment statement: " . $conn->error);
        if (empty($feedback_message)) {
            $feedback_message = "A system error occurred while preparing appointment data.";
            $feedback_type = 'error';
        }
    }

    // --- Fetch Pharmacy Order History ---
    $sql_orders = "SELECT order_id, order_date, total_amount, order_status, payment_method, prescription_image_url, prescription_status
                   FROM orders
                   WHERE customer_phone = ?
                   ORDER BY order_date DESC";
    $stmt_orders = $conn->prepare($sql_orders);
    if ($stmt_orders) {
        $stmt_orders->bind_param("s", $user_phone);
        if ($stmt_orders->execute()) {
            $result_orders = $stmt_orders->get_result();
            while ($order_row = $result_orders->fetch_assoc()) {
                $order_items = [];
                // Fetch items for each order
                $sql_items = "SELECT oi.quantity_ordered, oi.price_per_unit, m.name AS medicine_name, m.strength, m.form
                              FROM order_items oi
                              JOIN medicines m ON oi.medicine_id = m.id
                              WHERE oi.order_id = ?";
                $stmt_items = $conn->prepare($sql_items);
                if ($stmt_items) {
                    $stmt_items->bind_param("i", $order_row['order_id']);
                    if ($stmt_items->execute()) {
                        $result_items = $stmt_items->get_result();
                        while ($item_row = $result_items->fetch_assoc()) {
                            $order_items[] = $item_row;
                        }
                    } else {
                        error_log("Error fetching order items for order " . $order_row['order_id'] . ": " . $stmt_items->error);
                        // No global error message needed for item fetch issues; order itself might still display
                    }
                    $stmt_items->close();
                } else {
                    error_log("Error preparing order items statement: " . $conn->error);
                }
                $order_row['items'] = $order_items;
                $patient_pharmacy_orders[] = $order_row;
            }
        } else {
            error_log("Error fetching orders for phone " . $user_phone . ": " . $stmt_orders->error);
            if (empty($feedback_message)) {
                $feedback_message = "Could not retrieve pharmacy order history due to a system error.";
                $feedback_type = 'error';
            }
        }
        $stmt_orders->close();
    } else {
        error_log("Error preparing orders statement: " . $conn->error);
        if (empty($feedback_message)) {
            $feedback_message = "A system error occurred while preparing order data.";
            $feedback_type = 'error';
        }
    }
}

// Close connection after all database operations are complete
if ($conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="medical_records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Basic styles for feedback messages and other new elements if not in style.css or medical_records.css */
        .feedback-message {
            padding: 15px;
            margin-bottom: 20px;
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
        .feedback-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .feedback-message i {
            font-size: 1.2em;
        }
        .login-prompt {
            padding: 20px;
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            margin-top: 30px;
        }
        .login-prompt p {
            margin-bottom: 10px;
        }
        .login-prompt .button-link-secondary {
            display: inline-block;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .login-prompt .button-link-secondary:hover {
            background-color: #5a6268;
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
            padding: 6px 0px;
            border-top: none;
        }
        .table-responsive-wrapper {
            overflow-x: auto; /* Makes table horizontally scrollable on small screens */
            -webkit-overflow-scrolling: touch; /* Improves scrolling on iOS */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            min-width: 600px; /* Ensure table doesn't get too squished */
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
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            color: white;
        }
        /* Appointment Statuses */
        .status-badge.status-pending { background-color: #ffc107; color: #333; } /* Yellow */
        .status-badge.status-confirmed { background-color: #28a745; } /* Green */
        .status-badge.status-completed { background-color: #17a2b8; } /* Teal */
        .status-badge.status-cancelled { background-color: #dc3545; } /* Red */

        /* Order Statuses */
        .status-badge.status-order-pending { background-color: #ffc107; color: #333; }
        .status-badge.status-order-processed { background-color: #28a745; }
        .status-badge.status-order-shipped { background-color: #6c757d; } /* Gray */
        .status-badge.status-order-delivered { background-color: #17a2b8; }
        .status-badge.status-order-cancelled { background-color: #dc3545; }

        /* Prescription Statuses */
        .status-badge.status-rx-pending { background-color: #ffc107; color: #333; }
        .status-badge.status-rx-verified { background-color: #28a745; }
        .status-badge.status-rx-rejected { background-color: #dc3545; }
        .status-badge.status-unknown { background-color: #6c757d; } /* Fallback */

        .no-records {
            text-align: center;
            padding: 20px;
            color: #555;
            font-style: italic;
        }

        .order-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap; /* Allow wrapping on small screens */
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
    </style>
</head>
<body>
    <div class="my-records-container">
        <header class="my-records-header">
            <h1><i class="fas fa-book-medical"></i> My Medical Records</h1>
            <p>A summary of your appointments and pharmacy orders with ABC Medical.</p>
        </header>

        <?php if ($feedback_message): ?>
            <div class="feedback-message <?= htmlspecialchars($feedback_type); ?>">
                <i class="fas fa-info-circle"></i>
                <?= htmlspecialchars($feedback_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$is_logged_in): ?>
            <div class="message error-message login-prompt">
                <p><i class="fas fa-exclamation-triangle"></i> Please log in to view your records.</p>
                <p>To test this page, ensure your login process sets `$_SESSION['user_logged_in'] = true;` and `$_SESSION['user_id'] = 'YOUR_TEST_USER_ID';`.</p>
                <p><a href="index.php" class="button-link-secondary"><i class="fas fa-home"></i> Go to Homepage</a></p>
            </div>
        <?php else: ?>
            <section class="records-overview">
                <p class="welcome-user">Records for: <strong><?= $user_display_name; ?></strong> (Phone: <?= $user_phone; ?>)</p>
                <?php if ($user_email): ?>
                    <p class="welcome-user">Email: <?= $user_email; ?></p>
                <?php endif; ?>

                <div class="important-notice user-privacy-notice">
                     <i class="fas fa-user-shield"></i> **Privacy & Demo Note:** This is a simplified records view. Real patient portals are secured with robust authentication and manage data with greater detail and privacy controls.
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
                                        <th>Date & Time</th>
                                        <th>Doctor</th>
                                        <th>Reason</th>
                                        <th>Fee (৳)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patient_appointments as $appt): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($appt['id']); ?></td>
                                            <td><?= htmlspecialchars(date("d M Y", strtotime($appt['appointment_date']))); ?> at <?= htmlspecialchars(date("h:i A", strtotime($appt['appointment_time']))); ?></td>
                                            <td>Dr. <?= htmlspecialchars($appt['doctor_name']); ?></td>
                                            <td><?= htmlspecialchars($appt['reason'] ?: '-'); ?></td>
                                            <td><?= isset($appt['consultation_fee']) ? htmlspecialchars(number_format($appt['consultation_fee'], 2)) : 'N/A'; ?></td>
                                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $appt['status'])); ?>"><?= htmlspecialchars($appt['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="no-records">No appointment history found for this phone number.</p>
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
                                            // Ensure the URL is valid and properly escaped for HTML output.
                                            // Assuming prescription_image_url stores a relative path like 'uploads/prescriptions/img.jpg'
                                            $prescription_web_path = '/' . ltrim($order['prescription_image_url'], '/'); // Ensure only one leading slash
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
                        <p class="no-records">No pharmacy order history found for this phone number.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <footer class="my-records-footer">
            <p><a href="index.php"><i class="fas fa-home"></i> Back to Homepage</a></p>
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

    // Automatically open the first tab when the page loads if logged in
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($is_logged_in): ?>
            var firstTab = document.querySelector('.record-tabs .tab-link');
            if (firstTab) {
                firstTab.click(); // Simulate click to show first tab content
            }
        <?php endif; ?>
    });
</script>
</body>
</html>