<?php
session_start();

// Check if the user is logged in AND is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connect.php'; // Include database connection

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$page_title = "Edit Doctor Schedule Slot - ABC Medical Admin";

$message_html = "";
$doctor_info = null;
$slot_details = null;
$doctor_id = null;
$slot_id = null;

// 1. Get Doctor ID and Slot ID from URL and validate
if (isset($_GET['doctor_id']) && filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT)) {
    $doctor_id = $_GET['doctor_id'];
} else {
    $message_html = "<div class='message error-message-admin-form'>Doctor ID not specified or invalid.</div>";
}

if (isset($_GET['slot_id']) && filter_var($_GET['slot_id'], FILTER_VALIDATE_INT)) {
    $slot_id = $_GET['slot_id'];
} else {
    if (empty($message_html)) { // Avoid overwriting previous error
        $message_html = "<div class='message error-message-admin-form'>Schedule Slot ID not specified or invalid.</div>";
    }
    $doctor_id = null; // Invalidate doctor_id as well if slot_id is missing, to prevent further operations
}

// Fetch doctor's basic information if doctor_id is valid
if ($doctor_id) {
    $stmt_doc = $conn->prepare("SELECT id, name, specialization FROM doctors WHERE id = ?");
    if ($stmt_doc) {
        $stmt_doc->bind_param("i", $doctor_id);
        $stmt_doc->execute();
        $result_doc = $stmt_doc->get_result();
        if ($result_doc->num_rows === 1) {
            $doctor_info = $result_doc->fetch_assoc();
            $page_title = "Edit Slot for " . htmlspecialchars($doctor_info['name']) . " - Admin";
        } else {
            $message_html = "<div class='message error-message-admin-form'>Doctor not found.</div>";
            $doctor_id = null; // Invalidate doctor_id
        }
        $stmt_doc->close();
    } else {
        error_log("Fetch Doctor Info Error: " . $conn->error);
        $message_html = "<div class='message error-message-admin-form'>Error fetching doctor details.</div>";
        $doctor_id = null;
    }
}

// Fetch existing slot data if doctor_id and slot_id are valid
if ($doctor_id && $slot_id && $_SERVER["REQUEST_METHOD"] != "POST") { // Only fetch if not a POST or if POST had errors (handled later)
    $stmt_fetch_slot = $conn->prepare("SELECT id, day_of_week, start_time, end_time, break_start_time, break_end_time FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
    if ($stmt_fetch_slot) {
        $stmt_fetch_slot->bind_param("ii", $slot_id, $doctor_id);
        $stmt_fetch_slot->execute();
        $result_slot = $stmt_fetch_slot->get_result();
        if ($result_slot->num_rows === 1) {
            $slot_details = $result_slot->fetch_assoc();
        } else {
            $message_html = "<div class='message error-message-admin-form'>Schedule slot not found for this doctor.</div>";
            $slot_details = null; // Ensure form doesn't show if slot not found
        }
        $stmt_fetch_slot->close();
    } else {
        error_log("Fetch Slot Error: " . $conn->error);
        $message_html = "<div class='message error-message-admin-form'>Error fetching schedule slot details.</div>";
    }
}


// Handle Form Submission (POST) to update the slot
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_schedule_slot']) && $doctor_id && $slot_id) {
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $break_start_time = !empty($_POST['break_start_time']) ? $_POST['break_start_time'] : NULL;
    $break_end_time = !empty($_POST['break_end_time']) ? $_POST['break_end_time'] : NULL;

    $errors = [];
    // Validation (similar to add slot)
    if (empty($day_of_week) || empty($start_time) || empty($end_time)) {
        $errors[] = "Day, start time, and end time are required.";
    }
    if (strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = "Start time must be before end time.";
    }
    if (($break_start_time && !$break_end_time) || (!$break_start_time && $break_end_time)) {
        $errors[] = "Both break start and end times are required if one is provided, or leave both empty.";
    }
    if ($break_start_time && $break_end_time && strtotime($break_start_time) >= strtotime($break_end_time)) {
        $errors[] = "Break start time must be before break end time.";
    }
    if ($break_start_time && (strtotime($break_start_time) < strtotime($start_time) || strtotime($break_end_time) > strtotime($end_time))) {
        $errors[] = "Break times must be within the working slot.";
    }
    // TODO: Add overlap check with other existing slots for the same day, EXCLUDING the current slot_id.

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE doctor_schedules SET day_of_week = ?, start_time = ?, end_time = ?, break_start_time = ?, break_end_time = ? WHERE id = ? AND doctor_id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("sssssii", $day_of_week, $start_time, $end_time, $break_start_time, $break_end_time, $slot_id, $doctor_id);
            if ($stmt_update->execute()) {
                $message_html = "<div class='message success-message-admin-form'>Schedule slot updated successfully.</div>";
                // Re-fetch to show updated values
                $stmt_refetch = $conn->prepare("SELECT id, day_of_week, start_time, end_time, break_start_time, break_end_time FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
                if($stmt_refetch){
                    $stmt_refetch->bind_param("ii", $slot_id, $doctor_id);
                    $stmt_refetch->execute();
                    $result_refetch = $stmt_refetch->get_result();
                    $slot_details = $result_refetch->fetch_assoc(); // Update $slot_details
                    $stmt_refetch->close();
                }
            } else {
                error_log("Update Schedule Execute Error: " . $stmt_update->error);
                $message_html = "<div class='message error-message-admin-form'>Could not update schedule slot. Check for overlaps or errors.</div>";
            }
            $stmt_update->close();
        } else {
            error_log("Update Schedule Prepare Error: " . $conn->error);
            $message_html = "<div class='message error-message-admin-form'>Error preparing to update schedule.</div>";
        }
    } else {
        // If errors, repopulate $slot_details with POST data to keep form values
        $slot_details = [
            'id' => $slot_id, // Keep the original slot ID
            'day_of_week' => $day_of_week,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'break_start_time' => $break_start_time,
            'break_end_time' => $break_end_time
        ];
        $message_html = "<div class='message error-message-admin-form'><ul>";
        foreach ($errors as $error) {
            $message_html .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $message_html .= "</ul></div>";
    }
}

$days_of_week_options = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_dashboard.css" /> <link rel="stylesheet" href="admin_edit_doctor_schedule_slot.css" /> </head>
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
                <a href="admin_manage_doctors.php" class="admin-nav-item active"><i class="fas fa-user-md"></i> <span>Manage Doctors</span></a>
                <a href="admin_manage_appointments.php" class="admin-nav-item"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a>
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
                    <h1>Edit Schedule Slot</h1>
                    <?php if ($doctor_info): ?>
                        <p class="header-breadcrumb">Admin Panel / Manage Doctors / Edit Slot for <?= htmlspecialchars($doctor_info['name']) ?></p>
                    <?php else: ?>
                        <p class="header-breadcrumb">Admin Panel / Manage Doctors / Edit Slot</p>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                     <a href="admin_view_doctor_schedule.php?doctor_id=<?= htmlspecialchars($doctor_id ?? '') ?>" class="btn-back-admin"><i class="fas fa-arrow-left"></i> Back to Doctor's Schedule</a>
                </div>
            </header>

            <?php if (!empty($message_html)) echo $message_html; ?>

            <?php if ($doctor_info && $slot_details): ?>
            <section class="admin-content-section edit-schedule-slot-section">
                 <div class="doctor-info-for-slot">
                    Editing slot for: <strong><?= htmlspecialchars($doctor_info['name']) ?></strong> (<?= htmlspecialchars($doctor_info['specialization']) ?>)
                </div>
                <form action="admin_edit_doctor_schedule_slot.php?doctor_id=<?= $doctor_id ?>&slot_id=<?= $slot_id ?>" method="POST" class="admin-form" id="editScheduleSlotForm">
                    <div class="form-row-admin-schedule-edit">
                        <div class="form-group-admin-schedule-edit">
                            <label for="day_of_week">Day of Week <span class="required-star">*</span></label>
                            <select name="day_of_week" id="day_of_week" required>
                                <option value="">-- Select Day --</option>
                                <?php foreach($days_of_week_options as $day): ?>
                                <option value="<?= $day ?>" <?= ($slot_details['day_of_week'] == $day) ? 'selected' : '' ?>><?= $day ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row-admin-schedule-edit">
                        <div class="form-group-admin-schedule-edit">
                            <label for="start_time">Start Time <span class="required-star">*</span></label>
                            <input type="time" name="start_time" id="start_time" required value="<?= htmlspecialchars($slot_details['start_time']) ?>">
                        </div>
                        <div class="form-group-admin-schedule-edit">
                            <label for="end_time">End Time <span class="required-star">*</span></label>
                            <input type="time" name="end_time" id="end_time" required value="<?= htmlspecialchars($slot_details['end_time']) ?>">
                        </div>
                    </div>
                    <div class="form-row-admin-schedule-edit">
                        <div class="form-group-admin-schedule-edit">
                            <label for="break_start_time">Break Start Time (Optional)</label>
                            <input type="time" name="break_start_time" id="break_start_time" value="<?= htmlspecialchars($slot_details['break_start_time'] ?? '') ?>">
                        </div>
                        <div class="form-group-admin-schedule-edit">
                            <label for="break_end_time">Break End Time (Optional)</label>
                            <input type="time" name="break_end_time" id="break_end_time" value="<?= htmlspecialchars($slot_details['break_end_time'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_schedule_slot" class="btn-submit-admin-schedule-edit"><i class="fas fa-save"></i> Update Slot</button>
                </form>
            </section>
            <?php elseif ($doctor_id && !$slot_details && empty($message_html)): 
                // This case handles if slot_id was valid but no record found, and no other message is set
                echo "<div class='message error-message-admin-form'>The specified schedule slot could not be found for this doctor.</div>";
            ?>
            <?php endif; // End if $doctor_info && $slot_details ?>
        </main>
    </div>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
</body>
</html>
