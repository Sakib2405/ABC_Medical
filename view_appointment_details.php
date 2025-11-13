<?php
// --- SIMULATED DATABASE ---
// In a real application, you would connect to your database and fetch appointment details
// based on the ID passed in the URL.

$appointments = [
    123 => [
        'id' => 123,
        'patient_name' => 'John Doe',
        'doctor_name' => 'Dr. Smith',
        'appointment_date' => '2025-06-15',
        'appointment_time' => '10:30 AM',
        'reason' => 'Annual Checkup',
        'notes' => 'Patient reports feeling generally well. Discussed preventative care.'
    ],
    124 => [
        'id' => 124,
        'patient_name' => 'Jane Alam',
        'doctor_name' => 'Dr. Eva Rahman',
        'appointment_date' => '2025-06-18',
        'appointment_time' => '03:00 PM',
        'reason' => 'Follow-up Consultation',
        'notes' => 'Review lab results and discuss ongoing treatment plan.'
    ],
    // Add more sample appointments if you like
];

// --- GET APPOINTMENT ID FROM URL ---
$appointment_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $appointment_id = (int)$_GET['id'];
}

// --- FETCH THE SPECIFIC APPOINTMENT ---
$appointment_details = null;
if ($appointment_id && isset($appointments[$appointment_id])) {
    $appointment_details = $appointments[$appointment_id];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment Details</title>
    <link rel="stylesheet" href="view_appointment_details.css">
</head>
<body>
    <div class="container">
        <h1>Appointment Details</h1>

        <?php if ($appointment_details): ?>
            <div class="appointment-card">
                <h2>Appointment #<?= htmlspecialchars($appointment_details['id']); ?></h2>
                <div class="detail-item">
                    <span class="label">Patient Name:</span>
                    <span class="value"><?= htmlspecialchars($appointment_details['patient_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Doctor Name:</span>
                    <span class="value"><?= htmlspecialchars($appointment_details['doctor_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Date:</span>
                    <span class="value"><?= htmlspecialchars($appointment_details['appointment_date']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Time:</span>
                    <span class="value"><?= htmlspecialchars($appointment_details['appointment_time']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Reason for Visit:</span>
                    <span class="value"><?= htmlspecialchars($appointment_details['reason']); ?></span>
                </div>
                <div class="detail-item notes">
                    <span class="label">Notes:</span>
                    <span class="value"><?= nl2br(htmlspecialchars($appointment_details['notes'])); ?></span>
                </div>
            </div>
            <p><a href="some_appointment_list_page.php">Back to Appointments List</a></p>
        <?php elseif ($appointment_id): ?>
            <p class="error-message">Appointment with ID <?= htmlspecialchars($appointment_id); ?> not found.</p>
            <p><a href="some_appointment_list_page.php">Back to Appointments List</a></p>
        <?php else: ?>
            <p class="error-message">No appointment ID provided or invalid ID.</p>
            <p>Please select an appointment to view its details. Example: <a href="?id=123">View Sample Appointment 123</a> or <a href="?id=124">View Sample Appointment 124</a></p>
        <?php endif; ?>

    </div>
</body>
</html>