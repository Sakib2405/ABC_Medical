<?php
session_start();
$appointment_id = $_GET['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Confirmed! - ABC Medical</title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .confirmation-box {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .confirmation-box i {
            color: #28a745; /* Green checkmark */
            font-size: 4em;
            margin-bottom: 20px;
        }
        .confirmation-box h1 {
            color: #28a745;
            font-size: 2em;
            margin-bottom: 15px;
        }
        .confirmation-box p {
            font-size: 1.1em;
            color: #555;
            margin-bottom: 25px;
        }
        .confirmation-box .button-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px; /* Spacing between buttons */
            margin-top: 30px;
        }
        .confirmation-box .button-link {
            display: inline-flex; /* Use flex for icon alignment */
            align-items: center;
            gap: 8px; /* Spacing between icon and text */
            padding: 12px 25px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background-color 0.3s ease;
            min-width: 180px; /* Ensure buttons are somewhat consistent in size */
            justify-content: center; /* Center content horizontally */
        }
        .confirmation-box .button-link.secondary {
            background-color: #6c757d; /* Gray for secondary action */
        }
        .confirmation-box .button-link:hover {
            background-color: #0056b3;
        }
        .confirmation-box .button-link.secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="confirmation-box">
        <i class="fas fa-check-circle"></i>
        <h1>Appointment Confirmed!</h1>
        <?php if ($appointment_id): ?>
            <p>Your Appointment ID is: <strong>#APPT-<?= htmlspecialchars($appointment_id) ?></strong></p>
            <p>You will receive a confirmation shortly.</p>
        <?php else: ?>
            <p>Your appointment has been successfully booked!</p>
        <?php endif; ?>

        <div class="button-group">
            <a href="doctors_serial.php" class="button-link">
                <i class="fas fa-calendar-plus"></i> Book Another Appointment
            </a>
            <a href="index.php" class="button-link secondary">
                <i class="fas fa-home"></i> Go to Homepage
            </a>
        </div>
    </div>
</body>
</html>