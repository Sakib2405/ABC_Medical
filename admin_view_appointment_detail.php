<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "View Appointment Detail - ABC Medical Admin";
$appointment_details = null;
$error_message = "";

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $appointment_id = $_GET['id'];

    // Fetch appointment details - Admin can view any appointment
    $sql = "SELECT 
                a.id as appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.status as appointment_status,
                a.patient_notes,
                a.doctor_notes,
                a.cancellation_reason,
                a.created_at as booking_date,
                a.created_by_admin_id, /* Assuming this column exists */
                pat.name as patient_name, 
                pat.email as patient_email,
                pat.id as patient_id,
                doc.name as doctor_name,
                doc.specialization as doctor_specialization,
                doc.email as doctor_email,
                doc.phone as doctor_phone,
                ser.name as service_name,
                ser.description as service_description,
                ser.duration_minutes as service_duration,
                admin_creator.name as admin_creator_name /* To show which admin booked it, if applicable */
            FROM appointments a
            JOIN users pat ON a.user_id = pat.id
            JOIN doctors doc ON a.doctor_id = doc.id
            LEFT JOIN services ser ON a.service_id = ser.id
            LEFT JOIN users admin_creator ON a.created_by_admin_id = admin_creator.id AND admin_creator.role = 'admin'
            WHERE a.id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $appointment_details = $result->fetch_assoc();
            $page_title = "Appointment #" . htmlspecialchars($appointment_details['appointment_id']) . " Details - Admin";
        } else {
            $error_message = "Appointment not found.";
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement for admin appointment details: " . $conn->error);
        $error_message = "An error occurred while fetching appointment details.";
    }
} else {
    $error_message = "Invalid appointment ID specified.";
}

// $conn->close(); // Close connection at the end of the script
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_view_appointment_detail.css" /> </head>
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
                    <h1>Appointment Detail View</h1>
                     <?php if ($appointment_details): ?>
                        <p class="header-breadcrumb">Admin Panel / Appointments / Details for #<?= htmlspecialchars($appointment_details['appointment_id']) ?></p>
                    <?php else: ?>
                        <p class="header-breadcrumb">Admin Panel / Appointments / Details</p>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                     <a href="admin_manage_appointments.php" class="btn-back-admin"><i class="fas fa-arrow-left"></i> Back to Appointments List</a>
                </div>
            </header>

            <?php if (!empty($error_message)): ?>
                <div class="message error-message-admin-detail standalone-message-admin"><?= $error_message ?></div>
            <?php elseif ($appointment_details): ?>
                <section class="admin-content-section view-appointment-detail-section">
                    <div class="appointment-detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-calendar-alt"></i>
                            <h3>Appointment #<?= htmlspecialchars($appointment_details['appointment_id']) ?></h3>
                            <span class="status-badge-detail status-<?= strtolower(htmlspecialchars($appointment_details['appointment_status'])) ?>">
                                <?= htmlspecialchars($appointment_details['appointment_status']) ?>
                            </span>
                        </div>
                        <div class="detail-card-body">
                            <h4>Patient Information</h4>
                            <div class="detail-block">
                                <p><strong>Name:</strong> <a href="admin_edit_user.php?user_id=<?= $appointment_details['patient_id'] ?>"><?= htmlspecialchars($appointment_details['patient_name']) ?></a> (ID: <?= $appointment_details['patient_id'] ?>)</p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($appointment_details['patient_email']) ?></p>
                            </div>

                            <h4>Doctor & Service Information</h4>
                            <div class="detail-block">
                                <p><strong>Doctor:</strong> <a href="admin_edit_doctor.php?doctor_id=<?= $appointment_details['doctor_id'] /* Assuming doctor_id is in $appointment_details */ ?>"><?= htmlspecialchars($appointment_details['doctor_name']) ?></a> (<?= htmlspecialchars($appointment_details['doctor_specialization']) ?>)</p>
                                <p><strong>Doctor Contact:</strong> <?= htmlspecialchars($appointment_details['doctor_email'] ?? 'N/A') ?> | <?= htmlspecialchars($appointment_details['doctor_phone'] ?? 'N/A') ?></p>
                                <p><strong>Service:</strong> <?= htmlspecialchars($appointment_details['service_name'] ?? 'N/A') ?></p>
                                <?php if(!empty($appointment_details['service_duration'])): ?>
                                <p><strong>Approx. Duration:</strong> <?= htmlspecialchars($appointment_details['service_duration']) ?> minutes</p>
                                <?php endif; ?>
                            </div>
                            
                            <h4>Appointment Timing</h4>
                            <div class="detail-block">
                                <p><strong>Date:</strong> <?= date("l, F d, Y", strtotime($appointment_details['appointment_date'])) ?></p>
                                <p><strong>Time:</strong> <?= date("h:i A", strtotime($appointment_details['appointment_time'])) ?></p>
                                <p><strong>Booked On:</strong> <?= date("M d, Y, h:i A", strtotime($appointment_details['booking_date'])) ?></p>
                                <?php if(!empty($appointment_details['admin_creator_name'])): ?>
                                <p><strong>Booked By Admin:</strong> <?= htmlspecialchars($appointment_details['admin_creator_name']) ?> (ID: <?= htmlspecialchars($appointment_details['created_by_admin_id']) ?>)</p>
                                <?php endif; ?>
                            </div>

                            <?php if(!empty($appointment_details['patient_notes'])): ?>
                            <div class="detail-block notes-detail">
                                <h4>Patient Notes:</h4>
                                <p><?= nl2br(htmlspecialchars($appointment_details['patient_notes'])) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($appointment_details['doctor_notes'])): ?>
                            <div class="detail-block notes-detail doctor-notes-detail">
                                <h4>Doctor's Notes / Summary:</h4>
                                <p><?= nl2br(htmlspecialchars($appointment_details['doctor_notes'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if($appointment_details['appointment_status'] === 'Cancelled' && !empty($appointment_details['cancellation_reason'])): ?>
                            <div class="detail-block notes-detail cancellation-notes-detail">
                                <h4>Cancellation Reason:</h4>
                                <p><?= nl2br(htmlspecialchars($appointment_details['cancellation_reason'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="detail-card-footer">
                            <a href="admin_edit_appointment.php?id=<?= $appointment_details['appointment_id'] ?>" class="btn-action-card-detail edit"><i class="fas fa-edit"></i> Edit Appointment</a>
                            <?php if ($appointment_details['appointment_status'] == 'Confirmed' || $appointment_details['appointment_status'] == 'Pending'): ?>
                            <form method="POST" action="admin_manage_appointments.php?<?= http_build_query(array_diff_key($_GET, array_flip(['id']))) // Preserve filters, remove current id ?>" class="inline-form-detail">
                                <input type="hidden" name="appointment_id" value="<?= $appointment_details['appointment_id'] ?>">
                                <button type="submit" name="action" value="cancel_appointment" class="btn-action-card-detail cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');"><i class="fas fa-times-circle"></i> Cancel</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <p class="no-details-admin">Could not load appointment details. Please try again or contact support.</p>
            <?php endif; ?>
        </main>
    </div>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
</body>
</html>
