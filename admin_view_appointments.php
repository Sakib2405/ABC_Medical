<?php
// --- Enable error reporting for debugging (REMOVE FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$page_title = "Manage Appointments - ABC Medical Admin";

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error_message'] = "You must be logged in as an admin to access this page.";
    header("Location: admin_login.php");
    exit;
}
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- 2. DATABASE CONNECTION ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Admin Manage Appointments - DB Connection Error: " . $conn->connect_error);
    die("DATABASE CONNECTION FAILED. (Err: ADM_VA_DB_CONN)");
}
$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Dhaka');

$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

// --- Possible Statuses for Appointments ---
$possible_statuses = ['Scheduled', 'Pending Confirmation', 'Confirmed', 'Completed', 'Cancelled', 'No Show'];

// --- Handle Status Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $appointment_id_to_update = filter_var($_POST['appointment_id'], FILTER_VALIDATE_INT);
    $new_status = $conn->real_escape_string(trim($_POST['new_status']));

    if ($appointment_id_to_update && in_array($new_status, $possible_statuses)) {
        $stmt_update = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("si", $new_status, $appointment_id_to_update);
            if ($stmt_update->execute()) {
                $feedback_message = "Status for Appointment ID #{$appointment_id_to_update} updated to '{$new_status}'.";
                $feedback_type = 'success';
            } else {
                $feedback_message = "Error updating status: " . $stmt_update->error; $feedback_type = 'error';
            }
            $stmt_update->close();
        } else {
            $feedback_message = "Error preparing status update query: " . $conn->error; $feedback_type = 'error';
        }
    } else {
        $feedback_message = "Invalid data for status update."; $feedback_type = 'error';
    }
}

// --- Fetch Data for Filters ---
$doctors_for_filter = [];
$result_docs = $conn->query("SELECT id, name FROM doctors WHERE is_active = TRUE ORDER BY name ASC");
if ($result_docs) {
    while ($doc_row = $result_docs->fetch_assoc()) {
        $doctors_for_filter[] = $doc_row;
    }
    $result_docs->free();
}

// --- Get Filter Parameters ---
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$filter_doctor_id = isset($_GET['doctor_id']) ? filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT) : '';
$filter_date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
$view_action = isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id']);
$view_appointment_id = $view_action ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

$appointments_list = [];
$single_appointment_details = null;

// --- Build SQL Query ---
$sql_base = "SELECT a.id, a.patient_name, a.patient_phone, a.patient_email,
                    d.name AS doctor_name, d.id as doctor_id_val,
                    a.appointment_date, a.appointment_time,
                    a.reason, a.status, a.consultation_fee, a.created_at
             FROM appointments a
             JOIN doctors d ON a.doctor_id = d.id";
$conditions = [];
$params = [];
$types = "";

if ($view_action && $view_appointment_id) {
    $conditions[] = "a.id = ?";
    $params[] = $view_appointment_id;
    $types .= "i";
} else {
    if ($filter_status) {
        if ($filter_status === 'pending') { // Handle special 'pending' filter from dashboard
            $conditions[] = "(a.status = 'Scheduled' OR a.status = 'Pending Confirmation')";
        } elseif (in_array($filter_status, $possible_statuses)) {
            $conditions[] = "a.status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }
    }
    if ($filter_doctor_id) {
        $conditions[] = "a.doctor_id = ?";
        $params[] = $filter_doctor_id;
        $types .= "i";
    }
    if ($filter_date_from) {
        $conditions[] = "a.appointment_date >= ?";
        $params[] = $filter_date_from;
        $types .= "s";
    }
    if ($filter_date_to) {
        $conditions[] = "a.appointment_date <= ?";
        $params[] = $filter_date_to;
        $types .= "s";
    }
}

$sql_final = $sql_base;
if (count($conditions) > 0) {
    $sql_final .= " WHERE " . implode(" AND ", $conditions);
}
if (!$view_action) {
    $sql_final .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
}


$stmt_appointments = $conn->prepare($sql_final);
if ($stmt_appointments) {
    if (!empty($types)) {
        $stmt_appointments->bind_param($types, ...$params);
    }
    $stmt_appointments->execute();
    $result_appointments = $stmt_appointments->get_result();
    if ($view_action && $view_appointment_id) {
        if ($result_appointments->num_rows === 1) {
            $single_appointment_details = $result_appointments->fetch_assoc();
        } else {
            $feedback_message = "Appointment #{$view_appointment_id} not found.";
            $feedback_type = 'error';
        }
    } else {
        while ($row = $result_appointments->fetch_assoc()) {
            $appointments_list[] = $row;
        }
    }
    $stmt_appointments->close();
} else {
    $feedback_message = "Error fetching appointments: " . $conn->error;
    $feedback_type = 'error';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin_dashboard.css"> <link rel="stylesheet" href="admin_view_appointments.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container">
        <header class="dashboard-header-main" style="margin-bottom: 20px;">
            <div class="header-content">
                <h1><i class="fas fa-calendar-alt"></i> View & Manage Appointments</h1>
                <p>Logged in as: <?= htmlspecialchars($admin_name); ?></p>
            </div>
            <nav class="admin-nav">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </header>

        <main class="admin-content-area">
            <?php if ($feedback_message): ?>
                <div class="message-feedback <?= htmlspecialchars($feedback_type); ?>">
                    <?= htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($view_action && $single_appointment_details): ?>
                <section class="appointment-detail-view">
                    <h2>Appointment Detail - ID #<?= htmlspecialchars($single_appointment_details['id']); ?></h2>
                    <div class="detail-grid">
                        <p><strong>Patient Name:</strong> <?= htmlspecialchars($single_appointment_details['patient_name']); ?></p>
                        <p><strong>Patient Phone:</strong> <?= htmlspecialchars($single_appointment_details['patient_phone']); ?></p>
                        <p><strong>Patient Email:</strong> <?= htmlspecialchars($single_appointment_details['patient_email'] ?: 'N/A'); ?></p>
                        <p><strong>Doctor:</strong> Dr. <?= htmlspecialchars($single_appointment_details['doctor_name']); ?></p>
                        <p><strong>Appointment Date:</strong> <?= htmlspecialchars(date("l, F j, Y", strtotime($single_appointment_details['appointment_date']))); ?></p>
                        <p><strong>Appointment Time:</strong> <?= htmlspecialchars(date("h:i A", strtotime($single_appointment_details['appointment_time']))); ?></p>
                        <p><strong>Reason:</strong><br><?= nl2br(htmlspecialchars($single_appointment_details['reason'] ?: 'N/A')); ?></p>
                        <p><strong>Consultation Fee:</strong> ৳<?= isset($single_appointment_details['consultation_fee']) ? number_format($single_appointment_details['consultation_fee'], 2) : 'N/A'; ?></p>
                        <p><strong>Current Status:</strong> 
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', htmlspecialchars($single_appointment_details['status']))); ?>">
                                <?= htmlspecialchars($single_appointment_details['status']); ?>
                            </span>
                        </p>
                        <p><strong>Booked On:</strong> <?= htmlspecialchars(date("d M Y, h:i A", strtotime($single_appointment_details['created_at']))); ?></p>
                    </div>
                    <div class="detail-actions">
                         <a href="admin_view_appointments.php" class="button-secondary"><i class="fas fa-list"></i> Back to List</a>
                         </div>
                </section>

            <?php else: // Show list and filters ?>
                <section class="filter-section">
                    <form action="admin_view_appointments.php" method="GET" class="filter-form">
                        <div class="filter-group">
                            <label for="filter_status">Status:</label>
                            <select name="status" id="filter_status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= ($filter_status === 'pending' ? 'selected' : ''); ?>>Pending (Scheduled/Pending Confirm)</option>
                                <?php foreach ($possible_statuses as $p_status): ?>
                                    <option value="<?= htmlspecialchars($p_status); ?>" <?= ($filter_status === $p_status ? 'selected' : ''); ?>>
                                        <?= htmlspecialchars($p_status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter_doctor_id">Doctor:</label>
                            <select name="doctor_id" id="filter_doctor_id">
                                <option value="">All Doctors</option>
                                <?php foreach ($doctors_for_filter as $doc): ?>
                                    <option value="<?= $doc['id']; ?>" <?= ($filter_doctor_id == $doc['id'] ? 'selected' : ''); ?>>
                                        Dr. <?= htmlspecialchars($doc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter_date_from">Date From:</label>
                            <input type="date" name="date_from" id="filter_date_from" value="<?= htmlspecialchars($filter_date_from); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="filter_date_to">Date To:</label>
                            <input type="date" name="date_to" id="filter_date_to" value="<?= htmlspecialchars($filter_date_to); ?>">
                        </div>
                        <button type="submit" class="filter-button"><i class="fas fa-filter"></i> Filter</button>
                        <a href="admin_view_appointments.php" class="filter-button clear-filters">Clear Filters</a>
                    </form>
                </section>

                <section class="appointments-list-section">
                    <h2>All Appointments (<?= count($appointments_list); ?>)</h2>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient Name</th>
                                    <th>Phone</th>
                                    <th>Doctor</th>
                                    <th>Date & Time</th>
                                    <th>Reason</th>
                                    <th>Fee (৳)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($appointments_list)): ?>
                                    <?php foreach ($appointments_list as $appt): ?>
                                        <tr>
                                            <td data-label="ID">#<?= htmlspecialchars($appt['id']); ?></td>
                                            <td data-label="Patient"><?= htmlspecialchars($appt['patient_name']); ?></td>
                                            <td data-label="Phone"><?= htmlspecialchars($appt['patient_phone']); ?></td>
                                            <td data-label="Doctor">Dr. <?= htmlspecialchars($appt['doctor_name']); ?></td>
                                            <td data-label="Date & Time"><?= htmlspecialchars(date("d M Y", strtotime($appt['appointment_date']))); ?> at <?= htmlspecialchars(date("h:i A", strtotime($appt['appointment_time']))); ?></td>
                                            <td data-label="Reason" class="reason-cell"><?= htmlspecialchars(substr($appt['reason'] ?: '-', 0, 30)) . (strlen($appt['reason'] ?: '-') > 30 ? '...' : ''); ?></td>
                                            <td data-label="Fee (৳)"><?= isset($appt['consultation_fee']) ? number_format($appt['consultation_fee'], 2) : 'N/A'; ?></td>
                                            <td data-label="Status">
                                                <form action="admin_view_appointments.php<?= !empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '' ?>" method="POST" class="inline-status-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?= $appt['id']; ?>">
                                                    <select name="new_status" onchange="this.form.submit()">
                                                        <?php foreach ($possible_statuses as $status_option): ?>
                                                            <option value="<?= htmlspecialchars($status_option); ?>" <?= ($appt['status'] === $status_option ? 'selected' : ''); ?>>
                                                                <?= htmlspecialchars($status_option); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <noscript><button type="submit" class="update-btn-noscript"><i class="fas fa-save"></i></button></noscript>
                                                </form>
                                            </td>
                                            <td data-label="Actions" class="actions-cell">
                                                <a href="admin_view_appointments.php?action=view&id=<?= $appt['id']; ?>" class="action-link view-link" title="View Full Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="no-data-message">No appointments found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer class="dashboard-footer-main" style="margin-top: 30px;">
            <p>&copy; <?= date("Y"); ?> ABC Medical Admin Panel. All Rights Reserved.</p>
        </footer>
    </div>
    <?php $conn->close(); ?>
</body>
</html>