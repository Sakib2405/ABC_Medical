<?php
session_start();

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}

$page_title = "Admin Reports - ABC Medical";

$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    // In a real app, log this error and show a more user-friendly error page
    die("Database Connection Failed: " . $conn->connect_error . ". Please check configuration.");
}
$conn->set_charset("utf8mb4");

// --- 3. Fetch Report Data ---

// Initialize report data variables
$total_patients = 0;
$total_doctors = 0;
$new_users_last_7_days = 0;

$total_appointments = 0;
$appointments_by_status = [];
$appointments_per_doctor = [];

// --- User Statistics ---
// Total Patients
$result_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'"); // Assuming 'user' role for patients
if ($result_patients) $total_patients = $result_patients->fetch_assoc()['count'];

// Total Doctors
$result_doctors_count = $conn->query("SELECT COUNT(*) as count FROM doctors"); // Or from users table if doctors are there: SELECT COUNT(*) as count FROM users WHERE role = 'doctor'
if ($result_doctors_count) $total_doctors = $result_doctors_count->fetch_assoc()['count'];

// New Users (patients + doctors, or just patients) in the last 7 days
// Using Bangladesh timezone for 'today' reference
$dhaka_tz = new DateTimeZone('Asia/Dhaka');
$date_7_days_ago = (new DateTime('now', $dhaka_tz))->modify('-7 days')->format('Y-m-d H:i:s');

$sql_new_users = "SELECT COUNT(*) as count FROM users WHERE created_at >= ?";
$stmt_new_users = $conn->prepare($sql_new_users);
if ($stmt_new_users) {
    $stmt_new_users->bind_param("s", $date_7_days_ago);
    $stmt_new_users->execute();
    $result_new_users = $stmt_new_users->get_result();
    if ($result_new_users) $new_users_last_7_days = $result_new_users->fetch_assoc()['count'];
    $stmt_new_users->close();
}


// --- Appointment Statistics ---
// Total Appointments
$result_total_apt = $conn->query("SELECT COUNT(*) as count FROM appointments");
if ($result_total_apt) $total_appointments = $result_total_apt->fetch_assoc()['count'];

// Appointments by Status
$sql_apt_status = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status ORDER BY status";
$result_apt_status = $conn->query($sql_apt_status);
if ($result_apt_status && $result_apt_status->num_rows > 0) {
    while ($row = $result_apt_status->fetch_assoc()) {
        $appointments_by_status[] = $row;
    }
}

// Appointments per Doctor
$sql_apt_doctor = "SELECT d.name as doctor_name, d.specialty, COUNT(a.id) as appointment_count
                   FROM appointments a
                   JOIN doctors d ON a.doctor_id = d.id
                   GROUP BY a.doctor_id, d.name, d.specialty
                   ORDER BY appointment_count DESC";
$result_apt_doctor = $conn->query($sql_apt_doctor);
if ($result_apt_doctor && $result_apt_doctor->num_rows > 0) {
    while ($row = $result_apt_doctor->fetch_assoc()) {
        $appointments_per_doctor[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="admin_reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-reports-container">
        <header class="reports-page-header">
            <h1><i class="fas fa-chart-line"></i> Admin Reports Dashboard</h1>
            <p>Overview of system activity and key metrics.</p>
            <p class="report-date">Report Generated: <?= htmlspecialchars((new DateTime('now', $dhaka_tz))->format('F j, Y, g:i A')); ?> (Bangladesh Time)</p>
        </header>

        <section class="report-section">
            <h2><i class="fas fa-users"></i> User Statistics</h2>
            <div class="report-grid">
                <div class="report-card">
                    <div class="card-icon user-icon"><i class="fas fa-user-injured"></i></div>
                    <div class="card-content">
                        <h3>Total Patients</h3>
                        <p class="stat-number"><?= htmlspecialchars($total_patients); ?></p>
                    </div>
                </div>
                <div class="report-card">
                    <div class="card-icon doctor-icon"><i class="fas fa-user-md"></i></div>
                    <div class="card-content">
                        <h3>Total Doctors</h3>
                        <p class="stat-number"><?= htmlspecialchars($total_doctors); ?></p>
                    </div>
                </div>
                <div class="report-card">
                     <div class="card-icon new-user-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="card-content">
                        <h3>New Users (Last 7 Days)</h3>
                        <p class="stat-number"><?= htmlspecialchars($new_users_last_7_days); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="report-section">
            <h2><i class="fas fa-calendar-check"></i> Appointment Statistics</h2>
            <div class="report-grid">
                <div class="report-card">
                    <div class="card-icon appointment-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="card-content">
                        <h3>Total Appointments</h3>
                        <p class="stat-number"><?= htmlspecialchars($total_appointments); ?></p>
                    </div>
                </div>
            </div>
            <div class="report-subsection">
                <h3>Appointments by Status</h3>
                <?php if (!empty($appointments_by_status)): ?>
                    <ul class="status-list">
                        <?php foreach ($appointments_by_status as $status_data): ?>
                            <li>
                                <span class="status-name status-<?= strtolower(htmlspecialchars($status_data['status'])); ?>">
                                    <?= htmlspecialchars(ucfirst($status_data['status'])); ?>:
                                </span>
                                <span class="status-count"><?= htmlspecialchars($status_data['count']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No appointment status data available.</p>
                <?php endif; ?>
            </div>

            <div class="report-subsection">
                <h3>Appointments per Doctor</h3>
                <?php if (!empty($appointments_per_doctor)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Doctor Name</th>
                                <th>Specialty</th>
                                <th>Total Appointments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments_per_doctor as $doc_data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc_data['doctor_name']); ?></td>
                                    <td><?= htmlspecialchars($doc_data['specialty'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($doc_data['appointment_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No appointments found for any doctor or data not available.</p>
                <?php endif; ?>
            </div>
        </section>

        <p style="text-align:center; margin-top:30px;">
            <a href="admin_dashboard.php" class="button-secondary"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
        </p>
    </div>
</body>
</html>