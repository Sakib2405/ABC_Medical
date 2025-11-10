<?php
session_start();
// Simulate logged-in user (in a real app, this comes from session)
$patient_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Valued Patient';
$patient_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'patient001';

// --- Sample Data (replace with database queries in a real application) ---
$doctors = [
    ['id' => 'doc001', 'name' => 'Dr. Smith (Cardiologist)'],
    ['id' => 'doc030', 'name' => 'Dr. Eva Rahman (General Physician)'],
    ['id' => 'doc003', 'name' => 'Dr. Aalam (Pediatrician)'],
];

$services = [
    'Consultation' => 'General Consultation',
    'Checkup' => 'Routine Checkup',
    'FollowUp' => 'Follow-up Visit',
    'Vaccination' => 'Vaccination',
    'SpecialistConsult' => 'Specialist Consultation',
];

// --- Form processing ---
$selected_doctor = '';
$selected_service = '';
$appointment_date = '';
$appointment_time = '';
$notes = '';

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_doctor = trim($_POST['doctor'] ?? '');
    $selected_service = trim($_POST['service'] ?? '');
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Basic Validation
    if (empty($selected_doctor)) {
        $errors[] = "Please select a doctor.";
    }
    if (empty($selected_service)) {
        $errors[] = "Please select a service or specify reason.";
    }
    if (empty($appointment_date)) {
        $errors[] = "Please select an appointment date.";
    } else {
        // Validate date is not in the past
        $today = new DateTime('now', new DateTimeZone('Asia/Dhaka')); // Current date in Bangladesh
        $chosen_date = new DateTime($appointment_date, new DateTimeZone('Asia/Dhaka'));
        if ($chosen_date < $today->setTime(0,0,0)) { // Compare date part only
            $errors[] = "Appointment date cannot be in the past.";
        }
    }
    if (empty($appointment_time)) {
        $errors[] = "Please select an appointment time.";
    }
    // Add more specific time validation if needed (e.g., within clinic hours)

    if (empty($errors)) {
        // --- Simulate Saving Appointment (Database INSERT in real app) ---
        // In a real application:
        // 1. Check doctor's availability for the selected date and time.
        // 2. Prevent double booking.
        // 3. INSERT into 'appointments' table.

        $new_appointment_id = rand(1000, 9999); // Simulate new appointment ID
        $status = 'Scheduled';

        $appointment_data = [
            'id' => $new_appointment_id,
            'patient_id' => $patient_id,
            'patient_name' => $patient_name, // Usually from session
            'doctor_id' => $selected_doctor,
            'service' => $selected_service,
            'appointment_datetime' => $appointment_date . ' ' . $appointment_time,
            'notes' => $notes,
            'status' => $status,
            'booking_time' => date("Y-m-d H:i:s") // Current server time
        ];

        // For simulation:
        $success_message = "Appointment successfully scheduled for " . htmlspecialchars($patient_name) .
                           " with Dr. " . htmlspecialchars(array_column($doctors, 'name', 'id')[$selected_doctor]) .
                           " on " . htmlspecialchars($appointment_date) . " at " . htmlspecialchars($appointment_time) . ".";
        // Clear form fields after success
        // $selected_doctor = $selected_service = $appointment_date = $appointment_time = $notes = '';
    }
}

// Get today's date for min attribute in date picker (Bangladesh timezone)
$min_date = (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule New Appointment</title>
    <link rel="stylesheet" href="schedule_appointment..css"> <link rel="stylesheet" href="schedule_appointment.css">
</head>
<body>
    <div class="schedule-container">
        <header class="schedule-header">
            <h1>Schedule New Appointment</h1>
            <p>Book your appointment with ease.</p>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="feedback-message error">
                <strong>Please correct the following issues:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="feedback-message success">
                <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form action="schedule_appointment.php" method="POST" class="schedule-form">
            <div class="form-group">
                <label for="patient_name">Patient Name:</label>
                <input type="text" id="patient_name" name="patient_name_display" value="<?= htmlspecialchars($patient_name); ?>" readonly>
                <small>This is pre-filled for the logged-in user.</small>
            </div>

            <div class="form-group">
                <label for="doctor">Select Doctor:</label>
                <select id="doctor" name="doctor" required>
                    <option value="">-- Please select a doctor --</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= htmlspecialchars($doc['id']); ?>" <?= ($selected_doctor === $doc['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($doc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service">Service / Reason for Visit:</label>
                <select id="service" name="service" required>
                    <option value="">-- Select a service --</option>
                    <?php foreach ($services as $key => $value): ?>
                        <option value="<?= htmlspecialchars($key); ?>" <?= ($selected_service === $key) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($value); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Other" <?= ($selected_service === 'Other') ? 'selected' : ''; ?>>Other (please specify in notes)</option>
                </select>
            </div>

            <div class="form-group-inline">
                <div class="form-group">
                    <label for="appointment_date">Preferred Date:</label>
                    <input type="date" id="appointment_date" name="appointment_date" value="<?= htmlspecialchars($appointment_date); ?>" min="<?= $min_date; ?>" required>
                </div>

                <div class="form-group">
                    <label for="appointment_time">Preferred Time:</label>
                    <input type="time" id="appointment_time" name="appointment_time" value="<?= htmlspecialchars($appointment_time); ?>" required>
                    <small>Clinic hours: 9:00 AM - 5:00 PM. Availability will be confirmed.</small>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Additional Notes (Optional):</label>
                <textarea id="notes" name="notes" rows="4" placeholder="Any specific concerns or requests?"><?= htmlspecialchars($notes); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="button-primary">Request Appointment</button>
                <a href="dashboard.php" class="button-secondary">Cancel</a>
            </div>
        </form>
         <p class="availability-note">
            <strong>Note:</strong> Submitting this form is a request. The clinic will contact you to confirm the appointment based on doctor availability. For urgent matters, please call the clinic directly.
        </p>
    </div>
</body>
</html>