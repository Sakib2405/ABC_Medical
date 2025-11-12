<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$user_name = htmlspecialchars($_SESSION['user']);
$user_id = $_SESSION['user_id'];
$page_title = "View Appointment - ABC Medical";
$appointment_details = null;
$error_message = "";

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $appointment_id = $_GET['id'];

    // Fetch appointment details
    // Ensure the appointment belongs to the logged-in user OR if the user is an admin
    // For simplicity, this query assumes the user is viewing their own appointment.
    // For admin view, you might remove/adjust the "AND a.user_id = ?" part or check $_SESSION['is_admin']
    $sql = "SELECT 
                a.id as appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.status as appointment_status,
                a.patient_notes,
                a.doctor_notes,
                a.cancellation_reason,
                a.created_at as booking_date,
                u.name as patient_name, 
                u.email as patient_email,
                d.name as doctor_name,
                d.specialization as doctor_specialization,
                d.email as doctor_email,
                d.phone as doctor_phone,
                s.name as service_name,
                s.description as service_description,
                s.duration_minutes as service_duration
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.id = ? AND a.user_id = ?"; // Ensure user can only see their own appointments

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $appointment_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $appointment_details = $result->fetch_assoc();
            $page_title = "Appointment with " . htmlspecialchars($appointment_details['doctor_name']) . " - ABC Medical";
        } else {
            $error_message = "Appointment not found or you do not have permission to view it.";
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement for appointment details: " . $conn->error);
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="profile.css" /> <link rel="stylesheet" href="appointments.css" /> <link rel="stylesheet" href="view_appointment.css" /> </head>
<body>
    <div class="profile-page-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <i class="fas fa-clinic-medical"></i>
                    <span>ABC Medical</span>
                </a>
            </div>
        </aside>

        <main class="profile-main-content">
            <header class="profile-header">
                <h2>Appointment Details</h2>
                <?php if ($appointment_details): ?>
                <p class="header-subtitle">Consultation with <?= htmlspecialchars($appointment_details['doctor_name']) ?> on <?= date("F d, Y", strtotime($appointment_details['appointment_date'])) ?></p>
                <?php else: ?>
                <p class="header-subtitle">Information about a specific appointment.</p>
                <?php endif; ?>
            </header>

            <?php if (!empty($error_message)): ?>
                <div class="message error-message-appt standalone-message"><?= $error_message ?></div>
            <?php elseif ($appointment_details): ?>
                <section class="view-appointment-section">
                    <div class="appointment-summary-card">
                        <div class="card-header">
                            <i class="fas fa-calendar-check"></i>
                            <h3>Appointment #<?= htmlspecialchars($appointment_details['appointment_id']) ?></h3>
                            <span class="status-badge status-<?= strtolower(htmlspecialchars($appointment_details['appointment_status'])) ?>">
                                <?= htmlspecialchars($appointment_details['appointment_status']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-md"></i> Doctor:</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment_details['doctor_name']) ?> (<?= htmlspecialchars($appointment_details['doctor_specialization']) ?>)</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-briefcase-medical"></i> Service:</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment_details['service_name'] ?? 'N/A') ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-calendar-day"></i> Date:</span>
                                    <span class="detail-value"><?= date("l, F d, Y", strtotime($appointment_details['appointment_date'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-clock"></i> Time:</span>
                                    <span class="detail-value"><?= date("h:i A", strtotime($appointment_details['appointment_time'])) ?></span>
                                </div>
                                <?php if(!empty($appointment_details['service_duration'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-hourglass-half"></i> Approx. Duration:</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment_details['service_duration']) ?> minutes</span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-injured"></i> Patient:</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment_details['patient_name']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-at"></i> Patient Email:</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment_details['patient_email']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-calendar-plus"></i> Booked On:</span>
                                    <span class="detail-value"><?= date("M d, Y, h:i A", strtotime($appointment_details['booking_date'])) ?></span>
                                </div>
                            </div>

                            <?php if(!empty($appointment_details['patient_notes'])): ?>
                            <div class="notes-section">
                                <h4><i class="fas fa-sticky-note"></i> Your Notes for the Doctor:</h4>
                                <p><?= nl2br(htmlspecialchars($appointment_details['patient_notes'])) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($appointment_details['appointment_status'] === 'Completed' && !empty($appointment_details['doctor_notes'])): ?>
                            <div class="notes-section doctor-notes">
                                <h4><i class="fas fa-notes-medical"></i> Doctor's Summary / Notes:</h4>
                                <p><?= nl2br(htmlspecialchars($appointment_details['doctor_notes'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if($appointment_details['appointment_status'] === 'Cancelled' && !empty($appointment_details['cancellation_reason'])): ?>
                            <div class="notes-section cancellation-notes">
                                <h4><i class="fas fa-info-circle"></i> Cancellation Reason:</h4>
                                <p><?= nl2br(htmlspecialchars($appointment_details['cancellation_reason'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <?php if ($appointment_details['appointment_status'] !== 'Completed' && $appointment_details['appointment_status'] !== 'Cancelled'): ?>
                            <a href="reschedule_appointment.php?id=<?= $appointment_details['appointment_id'] ?>" class="btn-action-card reschedule"><i class="fas fa-edit"></i> Reschedule</a>
                            <a href="cancel_appointment.php?id=<?= $appointment_details['appointment_id'] ?>" class="btn-action-card cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');"><i class="fas fa-times-circle"></i> Cancel Appointment</a>
                            <?php endif; ?>
                            <a href="appointments.php" class="btn-action-card back"><i class="fas fa-arrow-left"></i> Back to All Appointments</a>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <p class="no-details">Could not load appointment details. Please try again or contact support.</p>
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
