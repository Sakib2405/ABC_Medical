<?php
// Simulate user session and data
// In a real application, you would get this from a user session after login.
session_start(); // Start the session (if not already started in a global include)

// Simulate a logged-in user. Replace with actual session data.
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Valued User';
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'patient'; // 'patient' or 'doctor', etc.

// Simulate some dynamic counts (you would fetch these from your database)
$upcoming_appointments_count = 2; // Example
$active_prescriptions_count = 3;  // Example
$new_messages_count = 1;          // Example

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="dashboard.css"> </head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Welcome to Your Dashboard, <?= htmlspecialchars($user_name); ?>!</h1>
            <p>Your central hub for managing your health information.</p>
            <nav class="dashboard-nav">
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="dashboard-main">
            <div class="dashboard-grid">

                <div class="dashboard-card">
                    <div class="card-icon-placeholder">📅</div> <h2>Appointments</h2>
                    <p>You have <strong><?= $upcoming_appointments_count; ?></strong> upcoming appointment(s).</p>
                    <a href="appointments.php" class="card-link">View Appointments</a>
                    <a href="schedule_appointment.php" class="card-link secondary">Schedule New</a>
                </div>

                <div class="dashboard-card">
                    <div class="card-icon-placeholder">℞</div> <h2>Prescriptions</h2>
                    <p>You have <strong><?= $active_prescriptions_count; ?></strong> active prescription(s).</p>
                    <a href="prescriptions.php" class="card-link">View Prescriptions</a>
                    <?php if ($user_role === 'doctor'): // Example: Doctor specific link ?>
                        <a href="prescribe_medication.php" class="card-link secondary">Prescribe New</a>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card">
                    <div class="card-icon-placeholder">✉️</div> <h2>Messages</h2>
                    <p>You have <strong><?= $new_messages_count; ?></strong> new message(s).</p>
                    <a href="messages.php" class="card-link">View Messages</a>
                </div>

                <div class="dashboard-card">
                    <div class="card-icon-placeholder">📂</div> <h2>Medical Records</h2>
                    <p>Access your test results, reports, and history.</p>
                    <a href="medical_records.php" class="card-link">View Records</a>
                </div>

                <?php if ($user_role === 'doctor' || $user_role === 'admin'): // Doctor/Admin specific card ?>
                <div class="dashboard-card">
                    <div class="card-icon-placeholder">👥</div>
                    <h2>Manage Patients</h2>
                    <p>View and manage patient information.</p>
                    <a href="manage_patients.php" class="card-link">Go to Patient List</a>
                </div>
                <?php endif; ?>

                <div class="dashboard-card">
                     <div class="card-icon-placeholder">⚙️</div> <h2>Settings</h2>
                    <p>Manage your account details and preferences.</p>
                    <a href="profile.php" class="card-link">Account Settings</a>
                </div>

            </div>
        </main>

        <footer class="dashboard-footer">
            <p>&copy; <?= date("Y"); ?> Your Health Portal. All rights reserved.</p>
        </footer>
    </div>
    <script type='text/javascript' src='//pl27012931.profitableratecpm.com/23/01/9e/23019e8e62b0d680b7c22119518abe76.js'></script>
<script async="async" data-cfasync="false" src="//pl27013164.profitableratecpm.com/518b5bf3a8f610d01ac4771c391ef67d/invoke.js"></script>
</body>
</html>