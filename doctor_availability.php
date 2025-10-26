<?php
session_start();
$page_title = "Doctor Availability - ABC Medical";

$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error . " (DB_CONNECT_ERR)");
}
$conn->set_charset("utf8mb4");

// --- Timezone (IMPORTANT for correct date/time comparisons) ---
date_default_timezone_set('Asia/Dhaka'); // Set to your clinic's timezone

$doctor_id = null;
$doctor_data = null;
$available_slots = [];
$error_message = '';
$feedback_message = '';

// Get doctor_id from URL
if (isset($_GET['doctor_id'])) {
    $doctor_id = filter_var($_GET['doctor_id'], FILTER_VALIDATE_INT);
    if ($doctor_id === false || $doctor_id <= 0) {
        $error_message = "Invalid Doctor ID provided.";
        $doctor_id = null; // Invalidate
    }
} else {
    $error_message = "No Doctor ID provided.";
}

// Determine the date to display availability for
$today_obj = new DateTime('today');
$display_date_str = isset($_GET['date']) ? $_GET['date'] : $today_obj->format('Y-m-d');
try {
    $display_date_obj = new DateTime($display_date_str);
    $display_date_str = $display_date_obj->format('Y-m-d'); // Normalize format

    // Ensure display date is not in the past relative to today for booking purposes
    // If it's in the past, reset to today and give feedback.
    if ($display_date_obj < $today_obj) {
        $display_date_obj = $today_obj;
        $display_date_str = $display_date_obj->format('Y-m-d');
        $feedback_message = "You cannot view availability for past dates. Showing today's availability instead.";
    }
} catch (Exception $e) {
    $display_date_obj = $today_obj;
    $display_date_str = $display_date_obj->format('Y-m-d');
    $feedback_message = "Invalid date format provided, showing today's availability.";
}


if ($doctor_id) {
    $stmt_doctor = $conn->prepare("SELECT id, name, specialization_id, consultation_fee, schedule_json FROM doctors WHERE id = ? AND is_active = TRUE");
    if ($stmt_doctor) {
        $stmt_doctor->bind_param("i", $doctor_id);
        $stmt_doctor->execute();
        $result_doctor = $stmt_doctor->get_result();
        if ($result_doctor->num_rows > 0) {
            $doctor_data = $result_doctor->fetch_assoc();
            $schedule_json_str = $doctor_data['schedule_json'];
            $doctor_schedule_all_days = $schedule_json_str ? json_decode($schedule_json_str, true) : null;

            if ($doctor_schedule_all_days) {
                $day_of_week_short = strtolower($display_date_obj->format('D')); // mon, tue, etc.
                $doctor_day_schedule = $doctor_schedule_all_days[$day_of_week_short] ?? null;

                if ($doctor_day_schedule && isset($doctor_day_schedule['start']) && isset($doctor_day_schedule['end'])) {
                    $slot_duration_minutes = (int)($doctor_day_schedule['interval'] ?? 30); // Default 30 min slots
                    $current_time_check = new DateTime('now'); // For checking if slot is in the past for today

                    // Fetch booked slots for this doctor on this date
                    $booked_slots = [];
                    $stmt_booked = $conn->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled'");
                    if ($stmt_booked) {
                        $stmt_booked->bind_param("is", $doctor_id, $display_date_str);
                        $stmt_booked->execute();
                        $result_booked = $stmt_booked->get_result();
                        while ($row_booked = $result_booked->fetch_assoc()) {
                            $booked_slots[] = (new DateTime($row_booked['appointment_time']))->format('H:i');
                        }
                        $stmt_booked->close();
                    }

                    // Generate slots for primary working hours
                    $start_time_obj = new DateTime($display_date_str . ' ' . $doctor_day_schedule['start']);
                    $end_time_obj = new DateTime($display_date_str . ' ' . $doctor_day_schedule['end']);
                    $break_start_obj = isset($doctor_day_schedule['break_start']) ? new DateTime($display_date_str . ' ' . $doctor_day_schedule['break_start']) : null;
                    $break_end_obj = isset($doctor_day_schedule['break_end']) ? new DateTime($display_date_str . ' ' . $doctor_day_schedule['break_end']) : null;

                    $current_slot_time = clone $start_time_obj;
                    while ($current_slot_time < $end_time_obj) {
                        $slot_start_str = $current_slot_time->format('H:i');
                        $next_potential_slot_time = clone $current_slot_time;
                        $next_potential_slot_time->modify('+' . $slot_duration_minutes . ' minutes');

                        // Check if slot is within break time
                        $is_in_break = false;
                        if ($break_start_obj && $break_end_obj) {
                            if (($current_slot_time >= $break_start_obj && $current_slot_time < $break_end_obj) ||
                                ($next_potential_slot_time > $break_start_obj && $next_potential_slot_time <= $break_end_obj)) {
                                $is_in_break = true;
                            }
                        }
                        
                        // Only add slots that are in the future (or current time if today), not booked, not in break, and within valid end time
                        if (($display_date_obj > $today_obj || ($display_date_obj == $today_obj && $current_slot_time > $current_time_check)) &&
                             !$is_in_break && !in_array($slot_start_str, $booked_slots) && $next_potential_slot_time <= $end_time_obj) {
                            $available_slots[] = $slot_start_str;
                        }
                       
                        $current_slot_time->modify('+' . $slot_duration_minutes . ' minutes');
                         // If current slot became the break start, jump to break end
                        if ($break_start_obj && $current_slot_time == $break_start_obj) {
                            $current_slot_time = clone $break_end_obj;
                        }
                    }

                    // Handle potential afternoon sessions (e.g., for Friday Jumu'ah break)
                    if (isset($doctor_day_schedule['afternoon_start']) && isset($doctor_day_schedule['afternoon_end'])) {
                        $afternoon_start_obj = new DateTime($display_date_str . ' ' . $doctor_day_schedule['afternoon_start']);
                        $afternoon_end_obj = new DateTime($display_date_str . ' ' . $doctor_day_schedule['afternoon_end']);
                        $current_slot_time = clone $afternoon_start_obj;

                        while ($current_slot_time < $afternoon_end_obj) {
                             $slot_start_str = $current_slot_time->format('H:i');
                             $next_potential_slot_time = clone $current_slot_time;
                             $next_potential_slot_time->modify('+' . $slot_duration_minutes . ' minutes');

                            if (($display_date_obj > $today_obj || ($display_date_obj == $today_obj && $current_slot_time > $current_time_check)) &&
                                !in_array($slot_start_str, $booked_slots) && $next_potential_slot_time <= $afternoon_end_obj) {
                               $available_slots[] = $slot_start_str;
                           }
                           $current_slot_time->modify('+' . $slot_duration_minutes . ' minutes');
                        }
                        sort($available_slots); // Sort if afternoon slots were added
                    }


                } else {
                    $feedback_message = "Doctor is not available on " . $display_date_obj->format('l, F j, Y') . " or schedule is not set for this day.";
                }
            } else {
                $error_message = "Doctor's schedule is not configured. Please contact support.";
            }
        } else {
            $error_message = "Doctor not found or is not currently active.";
        }
        $stmt_doctor->close();
    } else {
        $error_message = "Database query error for doctor details: " . $conn->error;
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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Body and Container Styles */
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
            min-height: 100vh;
        }

        .availability-container {
            max-width: 800px;
            width: 95%;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 30px;
            box-sizing: border-box;
        }

        /* Header Styles */
        .availability-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .availability-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .availability-header h1 i {
            color: #007bff;
        }

        /* Message Styles (Error/Info) */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            line-height: 1.4;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .message a {
            color: #0056b3;
            text-decoration: underline;
            margin-left: auto; /* Push link to the right */
        }
        .message a:hover {
            color: #003366;
        }
        .message i {
            font-size: 1.2em;
        }

        /* Doctor Info Section */
        .doctor-info-section {
            background-color: #e9f7ef; /* Light green background */
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .doctor-info-section h2 {
            font-size: 1.8rem;
            color: #28a745; /* Green color for doctor name */
            margin-top: 0;
            margin-bottom: 10px;
        }
        .doctor-info-section p {
            font-size: 1rem;
            color: #555;
            margin-bottom: 5px;
        }
        .doctor-info-section p strong {
            color: #333;
        }

        /* Date Navigation */
        .date-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .nav-button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .nav-button.disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.7;
            transform: none;
        }
        .nav-button.disabled:hover {
            background-color: #cccccc; /* Keep same on hover */
        }
        .current-date-display {
            font-size: 1.1rem;
            color: #495057;
            font-weight: 500;
            text-align: center;
            flex-grow: 1;
        }
        .current-date-display strong {
            color: #2c3e50;
            font-weight: 700;
        }

        /* Date Picker */
        .date-selector-simple {
            text-align: center;
            margin-bottom: 30px;
            background-color: #f0f8ff;
            border: 1px solid #d1e7fd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .date-selector-simple label {
            font-weight: 600;
            color: #495057;
            margin-right: 10px;
        }
        .date-selector-simple input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            margin-right: 10px;
            cursor: pointer;
        }
        .date-selector-simple button {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .date-selector-simple button:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        /* Time Slots Section */
        .time-slots-section {
            margin-bottom: 30px;
            text-align: center;
        }
        .time-slots-section h3 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 25px;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            justify-content: center;
        }
        .slot-button {
            background-color: #007bff;
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 10px rgba(0,123,255,0.2);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .slot-button:hover {
            background-color: #0056b3;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,123,255,0.3);
        }
        .no-slots-message {
            text-align: center;
            padding: 30px;
            font-size: 1.1rem;
            color: #777;
            background-color: #fdfdfd;
            border-radius: 10px;
            border: 1px dashed #ced4da;
        }

        /* Footer */
        .availability-footer {
            margin-top: 40px;
            padding-top: 20px;
            text-align: center;
            border-top: 1px solid #eee;
            color: #777;
        }
        .availability-footer p {
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        .availability-footer a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .availability-footer a:hover {
            color: #0056b3;
        }
        .availability-footer a i {
            margin-right: 5px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .availability-container {
                padding: 20px;
                margin: 20px auto;
            }
            .availability-header h1 {
                font-size: 2rem;
            }
            .doctor-info-section h2 {
                font-size: 1.6rem;
            }
            .date-navigation {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            .nav-button {
                width: 100%;
                justify-content: center;
            }
            .current-date-display {
                margin: 5px 0;
            }
            .date-selector-simple {
                flex-direction: column;
                align-items: stretch;
            }
            .date-selector-simple input[type="date"],
            .date-selector-simple button {
                width: 100%;
                margin-right: 0;
                margin-top: 10px;
            }
            .slots-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            }
            .slot-button {
                font-size: 1rem;
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .availability-container {
                border-radius: 0; /* Full width on very small screens */
                padding: 15px;
                margin: 0;
            }
            .availability-header h1 {
                font-size: 1.8rem;
                gap: 5px;
            }
            .doctor-info-section {
                padding: 15px;
            }
            .doctor-info-section h2 {
                font-size: 1.4rem;
            }
            .slots-grid {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); /* Even smaller for dense display */
            }
            .slot-button {
                font-size: 0.9rem;
                padding: 8px;
            }
            .message {
                font-size: 0.9em;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="availability-container">
        <header class="availability-header">
            <h1><i class="fas fa-calendar-alt"></i> Doctor Availability</h1>
        </header>

        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-times-circle"></i>
                <?= htmlspecialchars($error_message); ?>
                <a href="doctors_serial.php">Go back to doctors list</a>
            </div>
        <?php endif; ?>

        <?php if ($feedback_message && !$error_message): ?>
            <div class="message info">
                <i class="fas fa-info-circle"></i>
                <?= htmlspecialchars($feedback_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($doctor_data && !$error_message): ?>
            <section class="doctor-info-section">
                <h2>Dr. <?= htmlspecialchars($doctor_data['name']); ?></h2>
                <p>Select an available time slot for your appointment.</p>
                <p><strong>Consultation Fee:</strong> BDT <?= htmlspecialchars(number_format($doctor_data['consultation_fee'], 2)); ?></p>
            </section>

            <section class="date-navigation">
                <?php
                    $prev_date = clone $display_date_obj;
                    $prev_date->modify('-1 day');
                    $is_prev_disabled = ($prev_date < $today_obj);
                ?>
                <a href="doctor_availability.php?doctor_id=<?= $doctor_id; ?>&date=<?= $prev_date->format('Y-m-d'); ?>" 
                   class="nav-button prev-day <?= $is_prev_disabled ? 'disabled' : ''; ?>"
                   <?= $is_prev_disabled ? 'aria-disabled="true"' : ''; ?>>
                    <i class="fas fa-chevron-left"></i> Previous Day
                </a>
                <span class="current-date-display">
                    Availability for: <strong><?= $display_date_obj->format('l, F j, Y'); ?></strong>
                </span>
                <?php
                    $next_date = clone $display_date_obj;
                    $next_date->modify('+1 day');
                ?>
                <a href="doctor_availability.php?doctor_id=<?= $doctor_id; ?>&date=<?= $next_date->format('Y-m-d'); ?>" class="nav-button next-day">
                    Next Day <i class="fas fa-chevron-right"></i>
                </a>
            </section>

            <div class="date-selector-simple">
                <form action="doctor_availability.php" method="GET">
                    <input type="hidden" name="doctor_id" value="<?= $doctor_id ?>">
                    <label for="date_picker">Or pick a date:</label>
                    <input type="date" id="date_picker" name="date" value="<?= $display_date_str ?>" min="<?= date('Y-m-d') ?>">
                    <button type="submit">Show Availability</button>
                </form>
            </div>

            <section class="time-slots-section">
                <h3>Available Time Slots</h3>
                <?php if (!empty($available_slots)): ?>
                    <div class="slots-grid">
                        <?php foreach ($available_slots as $slot): ?>
                            <?php
                                $slot_display_time = new DateTime($slot);
                                $checkout_url = sprintf(
                                    "checkout.php?doctor_id=%d&date=%s&time=%s&fee=%.2f",
                                    $doctor_data['id'],
                                    $display_date_str,
                                    urlencode($slot), // H:i format
                                    $doctor_data['consultation_fee']
                                );
                            ?>
                            <a href="<?= $checkout_url; ?>" class="slot-button">
                                <?= $slot_display_time->format('h:i A'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-slots-message">No available slots for Dr. <?= htmlspecialchars($doctor_data['name']); ?> on <?= $display_date_obj->format('F j, Y'); ?>. Please try another date.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <footer class="availability-footer">
            <p><a href="doctors_serial.php?specialization_id=<?= $doctor_data['specialization_id'] ?? '' ?>"><i class="fas fa-arrow-left"></i> Back to Doctors List</a></p>
            <p>&copy; <?= date("Y"); ?> ABC Medical Pharmacy. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>