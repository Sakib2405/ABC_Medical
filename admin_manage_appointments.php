<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "Manage Appointments - ABC Medical Admin";

$action_message = ""; // For success/error messages from actions
$appointments_list = [];

// --- Filtering Logic ---
$filter_status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'all';
$filter_doctor_id = isset($_GET['doctor_id']) ? filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT) : 'all';
$filter_date_from = isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '';

// --- Handle Actions (Confirm, Cancel, Mark Complete) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $action = $_POST['action'];
    $appointment_id = filter_var($_POST['appointment_id'], FILTER_VALIDATE_INT);

    if ($appointment_id) {
        $new_status = '';
        $log_message = '';

        switch ($action) {
            case 'confirm_appointment':
                $new_status = 'Confirmed';
                $log_message = 'confirmed';
                break;
            case 'cancel_appointment':
                $new_status = 'Cancelled';
                // Optionally, you could have a reason for cancellation field in a modal
                // For now, just update status
                $log_message = 'cancelled';
                break;
            case 'mark_completed':
                $new_status = 'Completed';
                $log_message = 'marked as completed';
                break;
        }

        if (!empty($new_status)) {
            $stmt_update = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("si", $new_status, $appointment_id);
                if ($stmt_update->execute()) {
                    $action_message = "<div class='message success-message-admin-table'>Appointment #{$appointment_id} has been {$log_message}.</div>";
                } else {
                    error_log("Appointment Update Error: " . $stmt_update->error);
                    $action_message = "<div class='message error-message-admin-table'>Error updating appointment status.</div>";
                }
                $stmt_update->close();
            } else {
                error_log("Appointment Update Prepare Error: " . $conn->error);
                $action_message = "<div class='message error-message-admin-table'>Could not prepare status update.</div>";
            }
        }
    }
}


// --- Fetch Appointments from Database with Filtering ---
$sql_appointments = "SELECT 
                        a.id, 
                        u.name as patient_name, 
                        d.name as doctor_name, 
                        s.name as service_name,
                        a.appointment_date, 
                        a.appointment_time, 
                        a.status,
                        a.created_at as booked_on
                    FROM appointments a
                    JOIN users u ON a.user_id = u.id
                    JOIN doctors d ON a.doctor_id = d.id
                    LEFT JOIN services s ON a.service_id = s.id
                    WHERE 1=1"; // Start with a true condition to append filters

$params = [];
$types = "";

if ($filter_status !== 'all' && !empty($filter_status)) {
    $sql_appointments .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_doctor_id !== 'all' && !empty($filter_doctor_id)) {
    $sql_appointments .= " AND a.doctor_id = ?";
    $params[] = $filter_doctor_id;
    $types .= "i";
}
if (!empty($filter_date_from)) {
    $sql_appointments .= " AND a.appointment_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}
if (!empty($filter_date_to)) {
    $sql_appointments .= " AND a.appointment_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$sql_appointments .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
// Add LIMIT and OFFSET for pagination later if needed

$stmt_appointments = $conn->prepare($sql_appointments);

if ($stmt_appointments) {
    if (!empty($params)) {
        $stmt_appointments->bind_param($types, ...$params);
    }
    $stmt_appointments->execute();
    $result_appointments = $stmt_appointments->get_result();
    while ($row = $result_appointments->fetch_assoc()) {
        $appointments_list[] = $row;
    }
    $stmt_appointments->close();
} else {
    error_log("Error fetching appointments: " . $conn->error);
    $action_message = "<div class='message error-message-admin-table'>Could not retrieve appointments list.</div>";
}

// Fetch doctors for filter dropdown
$doctors_for_filter = [];
$sql_docs_filter = "SELECT id, name, specialization FROM doctors ORDER BY name ASC";
$result_docs_filter = $conn->query($sql_docs_filter);
if ($result_docs_filter) {
    while($row = $result_docs_filter->fetch_assoc()) {
        $doctors_for_filter[] = $row;
    }
}

$appointment_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'Rescheduled'];


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
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_manage_appointments.css" /> </head>
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
                    <h1>Manage Appointments</h1>
                    <p class="header-breadcrumb">Admin Panel / Appointments</p>
                </div>
                <div class="header-right">
                     <a href="admin_book_appointment.php" class="btn-add-new-appt"><i class="fas fa-calendar-plus"></i> Book New Appointment</a>
                </div>
            </header>

            <?php if (!empty($action_message)) echo $action_message; ?>

            <section class="admin-content-section appointment-filters">
                <form method="GET" action="admin_manage_appointments.php" class="filter-form-admin">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All Statuses</option>
                            <?php foreach($appointment_statuses as $status): ?>
                            <option value="<?= strtolower($status) ?>" <?= ($filter_status == strtolower($status)) ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="doctor_id">Doctor:</label>
                        <select name="doctor_id" id="doctor_id">
                            <option value="all" <?= ($filter_doctor_id == 'all') ? 'selected' : '' ?>>All Doctors</option>
                            <?php foreach($doctors_for_filter as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= ($filter_doctor_id == $doc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($doc['name']) ?> (<?= htmlspecialchars($doc['specialization']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_from">Date From:</label>
                        <input type="date" name="date_from" id="date_from" value="<?= $filter_date_from ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">Date To:</label>
                        <input type="date" name="date_to" id="date_to" value="<?= $filter_date_to ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-filter-admin"><i class="fas fa-filter"></i> Filter</button>
                        <a href="admin_manage_appointments.php" class="btn-clear-filter-admin"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </form>
            </section>

            <section class="admin-content-section">
                <div class="table-container-admin">
                    <table class="admin-table appointments-admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Booked On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($appointments_list)): ?>
                                <?php foreach ($appointments_list as $appt): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($appt['id']) ?></td>
                                    <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['doctor_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['service_name'] ?? 'N/A') ?></td>
                                    <td><?= date("M d, Y", strtotime($appt['appointment_date'])) ?></td>
                                    <td><?= date("h:i A", strtotime($appt['appointment_time'])) ?></td>
                                    <td>
                                        <span class="status-badge-admin status-<?= strtolower(htmlspecialchars($appt['status'])) ?>">
                                            <?= ucfirst(htmlspecialchars($appt['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date("M d, Y H:i", strtotime($appt['booked_on'])) ?></td>
                                    <td class="action-buttons-admin-appt">
                                        <a href="admin_view_appointment_detail.php?id=<?= $appt['id'] ?>" class="btn-action-admin view" title="View Details"><i class="fas fa-eye"></i></a>
                                        
                                        <form method="POST" action="admin_manage_appointments.php<?= http_build_query($_GET) // Preserve filters ?>" class="inline-form">
                                            <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                            <?php if ($appt['status'] == 'Pending'): ?>
                                                <button type="submit" name="action" value="confirm_appointment" class="btn-action-admin confirm" title="Confirm Appointment"><i class="fas fa-check-circle"></i></button>
                                            <?php endif; ?>
                                            <?php if ($appt['status'] != 'Completed' && $appt['status'] != 'Cancelled'): ?>
                                                <button type="submit" name="action" value="cancel_appointment" class="btn-action-admin cancel" title="Cancel Appointment" onclick="return confirm('Are you sure you want to cancel this appointment?');"><i class="fas fa-times-circle"></i></button>
                                            <?php endif; ?>
                                            <?php if ($appt['status'] == 'Confirmed' && strtotime($appt['appointment_date']) <= strtotime(date('Y-m-d')) ): // Can mark as completed if confirmed and date is today or past ?>
                                                <button type="submit" name="action" value="mark_completed" class="btn-action-admin complete" title="Mark as Completed"><i class="fas fa-clipboard-check"></i></button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="no-records-admin">No appointments found matching your criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </section>
        </main>
    </div>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
</body>
</html>
