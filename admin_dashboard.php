<?php
// --- Enable error reporting for debugging (REMOVE FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$page_title = "Admin Dashboard - ABC Medical";

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}
$admin_name = $_SESSION['admin_name'] ?? 'Admin'; // Get admin name from session

// --- 2. DATABASE CONNECTION ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log("Admin Dashboard - DB Connection Error: " . $conn->connect_error . " (Host: $db_host, User: $db_user, DB: $db_name)");
    die("DATABASE CONNECTION FAILED. Please check your database configuration. Details: " . $conn->connect_error . " (Err: ADM_DB_CONN)");
}
$conn->set_charset("utf8mb4");

// --- 3. Fetch Data for Dashboard Cards (Stats) ---
$stats = [
    'total_users' => 0,
    'total_doctors' => 0,
    'total_appointments' => 0,
    'pending_appointments' => 0,
    'total_services' => 0, // Assuming a 'services' table exists
    'total_medicines' => 0,
];

// Query for Total Regular Users (assuming 'users' table and 'role' column)
$result_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' OR role = 'patient'");
if ($result_users) {
    $stats['total_users'] = $result_users->fetch_assoc()['count'];
    $result_users->free();
} else {
    error_log("Error fetching total users: " . $conn->error);
}

// Query for Total Doctors
$result_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = TRUE");
if ($result_doctors) {
    $stats['total_doctors'] = $result_doctors->fetch_assoc()['count'];
    $result_doctors->free();
} else {
    error_log("Error fetching total doctors: " . $conn->error);
}

// Query for Total Appointments
$result_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments");
if ($result_appointments) {
    $stats['total_appointments'] = $result_appointments->fetch_assoc()['count'];
    $result_appointments->free();
} else {
    error_log("Error fetching total appointments: " . $conn->error);
}

// Query for Pending/Scheduled Appointments
$result_pending_apt = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Scheduled' OR status = 'Pending Confirmation'"); // Adjusted status
if ($result_pending_apt) {
    $stats['pending_appointments'] = $result_pending_apt->fetch_assoc()['count'];
    $result_pending_apt->free();
} else {
    error_log("Error fetching pending appointments: " . $conn->error);
}

// Query for Total Services (assuming 'services' table)
$result_services = $conn->query("SELECT COUNT(*) as count FROM services WHERE is_active = TRUE");
if ($result_services) {
    $stats['total_services'] = $result_services->fetch_assoc()['count'];
    $result_services->free();
} else {
    error_log("Error fetching total services: " . $conn->error);
}

// Query for Total Medicines
$result_medicines = $conn->query("SELECT COUNT(*) as count FROM medicines WHERE is_active = TRUE");
if ($result_medicines) {
    $stats['total_medicines'] = $result_medicines->fetch_assoc()['count'];
    $result_medicines->free();
} else {
    error_log("Error fetching total medicines: " . $conn->error);
}


// --- 4. Fetch Recent Upcoming Appointments ---
$recent_appointments = [];
$recent_appointments_error = false; // Flag for error
// Using existing $conn
$sql_recent_appointments = "SELECT a.id, a.patient_name, d.name as doctor_name, a.appointment_date, a.appointment_time, a.status
                           FROM appointments a
                           JOIN doctors d ON a.doctor_id = d.id
                           WHERE a.status = 'Scheduled' OR a.status = 'Pending Confirmation'
                           ORDER BY a.appointment_date ASC, a.appointment_time ASC
                           LIMIT 5"; // Get upcoming 5

$result_recent_apt = $conn->query($sql_recent_appointments);
if ($result_recent_apt) {
    while ($row = $result_recent_apt->fetch_assoc()) {
        $recent_appointments[] = $row;
    }
    $result_recent_apt->free();
} else {
    error_log("Error fetching recent appointments: " . $conn->error);
    $recent_appointments_error = true;
}

$conn->close(); // Close the database connection once all queries are done
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-dashboard-container">
        <header class="dashboard-header-main">
            <div class="header-content">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($admin_name); ?>!</p>
            </div>
            <nav class="admin-nav">
                <a href="admin_profile.php" title="Your Profile"><i class="fas fa-user-shield"></i> Profile</a>
                <a href="admin_site_settings.php" title="Site Settings"><i class="fas fa-cogs"></i> Settings</a>
                <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </header>

        <main class="dashboard-grid">
            <a href="admin_manage_users.php" class="dashboard-card users-card">
                <div class="card-icon"><i class="fas fa-users"></i></div>
                <div class="card-content">
                    <h3>Total Users</h3>
                    <p class="stat-number"><?= htmlspecialchars($stats['total_users']); ?></p>
                    <span>Manage Patients/Users</span>
                </div>
            </a>

            <a href="admin_manage_doctors.php" class="dashboard-card doctors-card">
                <div class="card-icon"><i class="fas fa-user-md"></i></div>
                <div class="card-content">
                    <h3>Total Doctors</h3>
                    <p class="stat-number"><?= htmlspecialchars($stats['total_doctors']); ?></p>
                    <span>Manage Doctor Profiles</span>
                </div>
            </a>

            <a href="admin_view_appointments.php" class="dashboard-card appointments-card">
                <div class="card-icon"><i class="fas fa-calendar-check"></i></div> <div class="card-content">
                    <h3>Total Appointments</h3>
                    <p class="stat-number"><?= htmlspecialchars($stats['total_appointments']); ?></p>
                    <span>View All Appointments</span>
                </div>
            </a>

            <a href="admin_view_appointments.php?status=pending" class="dashboard-card pending-appointments-card">
                <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="card-content">
                    <h3>Pending/Scheduled</h3>
                    <p class="stat-number"><?= htmlspecialchars($stats['pending_appointments']); ?></p>
                    <span>Manage Upcoming</span>
                </div>
            </a>

            <a href="admin_manage_services.php" class="dashboard-card services-card">
                <div class="card-icon"><i class="fas fa-concierge-bell"></i></div>
                <div class="card-content">
                    <h3>Clinic Services</h3>
                    <p class="stat-number"><?= htmlspecialchars($stats['total_services']); ?></p>
                    <span>Manage Services</span>
                </div>
            </a>

            <a href="admin_manage_medicines.php" class="dashboard-card medicines-card"> <div class="card-icon"><i class="fas fa-pills"></i></div>
                <div class="card-content">
                    <h3>Medicine Inventory</h3>
                    <p class="stat-number"><?= htmlspecialchars($stats['total_medicines']); ?></p>
                    <span>Manage Medicines</span>
                </div>
            </a>

            <a href="admin_manage_pharmacy_orders.php" class="dashboard-card pharmacy-orders-card"> <div class="card-icon"><i class="fas fa-dolly"></i></div>
                <div class="card-content">
                    <h3>Pharmacy Orders</h3>
                    <p class="stat-number">View</p> <span>Manage Online Orders</span>
                </div>
            </a>

        </main>

        <section class="recent-activity-section">
            <h2><i class="fas fa-clock"></i> Recent Upcoming Appointments</h2>
            <?php if ($recent_appointments_error): ?>
                <p class="error-message-table">Could not load recent appointments due to a database error.</p>
            <?php elseif (!empty($recent_appointments)): ?>
                <div class="table-responsive-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Appt. ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_appointments as $appt): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($appt['id']); ?></td>
                                    <td><?= htmlspecialchars($appt['patient_name']); ?></td>
                                    <td>Dr. <?= htmlspecialchars($appt['doctor_name']); ?></td>
                                    <td><?= htmlspecialchars(date("d M, Y", strtotime($appt['appointment_date']))); ?></td>
                                    <td><?= htmlspecialchars(date("h:i A", strtotime($appt['appointment_time']))); ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', htmlspecialchars($appt['status']))); ?>">
                                            <?= htmlspecialchars($appt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_view_appointments.php?action=view&id=<?= $appt['id']; ?>" class="action-link view-link" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="view-all-link">
                    <a href="admin_view_appointments.php">View All Appointments &raquo;</a>
                </div>
            <?php else: ?>
                <p class="no-data-message">No upcoming scheduled or pending appointments found.</p>
            <?php endif; ?>
        </section>

        <footer class="dashboard-footer-main">
            <p>&copy; <?= date("Y"); ?> ABC Medical Admin Panel. All Rights Reserved.</p>
        </footer>
    </div>
</body>
</html>