<?php
session_start();
$site_name = "ABC Medical";

// Determine if a user or admin is logged in
$is_user_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user']);
$is_admin_logged_in = isset($_SESSION['admin_name']); // Assuming you still use this for admin

$user_display_name = "";
if ($is_user_logged_in) {
    $user_display_name = htmlspecialchars($_SESSION['user']);
} elseif ($is_admin_logged_in) {
    $user_display_name = "Admin: " . htmlspecialchars($_SESSION['admin_name']);
}

// Placeholder for profile picture - in a real app, this would come from the database or a default
$profile_pic_url = "https://placehold.co/80x80/E0F7FA/00796B?text=" . strtoupper(substr($user_display_name, 0, 1));
if ($is_user_logged_in && isset($_SESSION['user_profile_pic'])) { // Example if you store profile pic URL in session
    $profile_pic_url = htmlspecialchars($_SESSION['user_profile_pic']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name); ?> - Your Health Partner</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css"> <style>
        /* Styles for the user profile dropdown */
        .user-profile-dropdown {
            position: absolute;
            top: 20px; /* Adjust as needed */
            right: 20px; /* Adjust as needed */
            z-index: 1010; /* Above header */
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%; /* Make it round */
            transition: background-color 0.3s ease;
        }
        
        .profile-trigger:hover {
            background-color: rgba(255, 255, 255, 0.1); /* Slight hover effect if header is dark */
        }

        .profile-picture {
            width: 45px; /* Size of the round profile picture */
            height: 45px;
            border-radius: 50%;
            border: 2px solid #fff; /* Optional: white border */
            object-fit: cover; /* Ensures the image covers the area, good for actual photos */
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .profile-dropdown-content {
            display: none;
            position: absolute;
            top: 100%; /* Position below the trigger */
            right: 0;
            background-color: #ffffff;
            min-width: 200px;
            box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 10px 0;
            margin-top: 10px; /* Space between trigger and dropdown */
        }

        .profile-dropdown-content.show-dropdown {
            display: block;
        }

        .dropdown-header {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
        }
        .dropdown-header strong {
            color: #333;
        }
        .dropdown-header span {
            font-size: 0.85em;
            color: #777;
            display: block;
        }

        .profile-dropdown-content a {
            color: #333;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 0.9rem;
            transition: background-color 0.2s ease;
        }

        .profile-dropdown-content a i {
            margin-right: 10px;
            color: #00796b; /* Teal color for icons */
            width: 16px; /* Align icons */
            text-align: center;
        }

        .profile-dropdown-content a:hover {
            background-color: #f1f1f1;
            color: #00796b;
        }
        .dropdown-divider {
            height: 1px;
            background-color: #eee;
            margin: 5px 0;
        }

        /* Logged-in specific content styling */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .dashboard-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .dashboard-card i {
            font-size: 2.5rem;
            color: #00796b; /* Teal */
            margin-bottom: 15px;
        }
        .dashboard-card h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 10px;
        }
        .dashboard-card p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }
        .dashboard-card .btn {
            margin-top: auto; /* Pushes button to bottom if card heights vary */
        }

    </style>
</head>
<body>

    <?php if ($is_user_logged_in || $is_admin_logged_in): ?>
    <div class="user-profile-dropdown">
        <div class="profile-trigger" onclick="toggleProfileDropdown()">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="profile-picture"
                 onerror="this.onerror=null;this.src='https://placehold.co/80x80/CCCCCC/FFFFFF?text=U';">
        </div>
        <div class="profile-dropdown-content" id="profileDropdown">
            <div class="dropdown-header">
                <strong><?= $user_display_name ?></strong>
                <?php if ($is_user_logged_in && isset($_SESSION['user_email'])): // Assuming email is in session ?>
                    <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                <?php endif; ?>
            </div>
            <div class="dropdown-divider"></div>
            <?php if ($is_user_logged_in): ?>
                <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                <a href="appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a>
                <a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a>
            <?php elseif ($is_admin_logged_in): ?>
                <a href="admin_dashboard.php"><i class="fas fa-cogs"></i> Admin Dashboard</a>
                <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
            <?php endif; ?>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <?php endif; ?>

    <header class="site-header">
        <div class="container header-content">
            <div class="logo">
                <a href="index.php">
                    <i class="fas fa-hospital-symbol"></i>
                    <h1><?= htmlspecialchars($site_name); ?></h1>
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="information.php">Information</a></li>
                    <li><a href="buy_medicine.php">Buy Medicine</a></li>
                    <li><a href="doctors_serial.php">Doctor Serial</a></li>
                    <li><a href="emergency.php">Emergency</a></li>
                    <li><a href="blood_donation.php">Blood Bank</a></li>
                </ul>
            </nav>
            <button class="mobile-nav-toggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <main class="site-main">
        <div class="container content-area">
            <?php if ($is_user_logged_in): ?>
                <section class="panel user-dashboard-panel">
                    <div class="panel-header">
                        <h2>Welcome, <?= $user_display_name ?>!</h2>
                    </div>
                    <div class="panel-body">
                        <p>Here's a quick overview of your health portal. Access your information and manage your healthcare needs.</p>
                        <div class="dashboard-grid">
                            <div class="dashboard-card">
                                <i class="fas fa-calendar-check"></i>
                                <h3>Appointments</h3>
                                <p>View upcoming or schedule new appointments.</p>
                                <a href="appointments.php" class="btn btn-primary">Manage Appointments</a>
                            </div>
                            <div class="dashboard-card">
                                <i class="fas fa-notes-medical"></i>
                                <h3>My Records</h3>
                                <p>Access your medical history and test results.</p>
                                <a href="medical_records.php" class="btn btn-primary">View Records</a>
                            </div>
                            <div class="dashboard-card">
                                <i class="fas fa-user-doctor"></i>
                                <h3>Find Doctors</h3>
                                <p>Search for specialists and book consultations.</p>
                                <a href="doctors_serial.php" class="btn btn-primary">Find a Doctor</a>
                            </div>
                            <div class="dashboard-card">
                                <i class="fas fa-pills"></i>
                                <h3>Prescriptions</h3>
                                <p>View your current prescriptions and request refills.</p>
                                <a href="prescriptions.php" class="btn btn-primary">My Prescriptions</a>
                            </div>
                        </div>
                    </div>
                </section>

            <?php elseif ($is_admin_logged_in): ?>
                <section class="panel admin-dashboard-panel">
                     <div class="panel-header">
                        <h2>Administrator Dashboard</h2>
                    </div>
                    <div class="panel-body">
                        <p>Welcome, <?= $user_display_name ?>. Manage platform settings, users, and content.</p>
                         <div class="dashboard-grid">
                            <div class="dashboard-card">
                                <i class="fas fa-users"></i>
                                <h3>Manage Users</h3>
                                <p>View, edit, or add new user accounts.</p>
                                <a href="admin_manage_users.php" class="btn btn-primary">User Management</a>
                            </div>
                            <div class="dashboard-card">
                                <i class="fas fa-user-doctor"></i>
                                <h3>Manage Doctors</h3>
                                <p>Add, edit, or update doctor profiles and schedules.</p>
                                <a href="admin_manage_doctors.php" class="btn btn-primary">Doctor Management</a>
                            </div>
                            <div class="dashboard-card">
                                <i class="fas fa-chart-bar"></i>
                                <h3>Site Analytics</h3>
                                <p>View website usage statistics and reports.</p>
                                <a href="admin_analytics.php" class="btn btn-primary">View Analytics</a>
                            </div>
                             <div class="dashboard-card">
                                <i class="fas fa-cogs"></i>
                                <h3>System Settings</h3>
                                <p>Configure general site settings and parameters.</p>
                                <a href="admin_settings.php" class="btn btn-primary">System Settings</a>
                            </div>
                        </div>
                    </div>
                </section>

            <?php else: // Guest view (same as before) ?>
                <section class="hero-section">
                    <div class="hero-content">
                        <h2>Your Health, Our Priority</h2>
                        <p>Providing compassionate and comprehensive medical care. Access our services, find doctors, and manage your health online.</p>
                        <div class="hero-actions">
                            <a href="login.php" class="btn btn-primary"><i class="fas fa-user-md"></i> User Login</a>
                            <a href="register.php" class="btn btn-secondary"><i class="fas fa-user-plus"></i> Register Now</a>
                            <a href="admin_login.php" class="btn btn-tertiary admin-btn"><i class="fas fa-user-shield"></i> Admin Login</a>
                        </div>
                    </div>
                    <div class="hero-image-container">
                        <img src="https://placehold.co/600x400/e0f7fa/00796b?text=Modern+Clinic" alt="Modern Clinic Illustration" class="hero-image" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/FFFFFF?text=Image+Not+Found';">
                    </div>
                </section>

                <section class="features-section">
                    <div class="container">
                        <h2 class="section-title">Our Core Services</h2>
                        <div class="features-grid">
                            <div class="feature-item">
                                <i class="fas fa-calendar-check"></i>
                                <h3>Book Appointments</h3>
                                <p>Easily schedule appointments with our specialized doctors.</p>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-pills"></i>
                                <h3>Online Pharmacy</h3>
                                <p>Order your medicines online and get them delivered.</p>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-ambulance"></i>
                                <h3>Emergency Care</h3>
                                <p>24/7 emergency services for critical situations.</p>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <div class="footer-about">
                <h3><?= htmlspecialchars($site_name); ?></h3>
                <p>Committed to providing the best healthcare services. Your well-being is our utmost concern.</p>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="information.php">Information</a></li>
                    <li><a href="doctors_serial.php">Find a Doctor</a></li>
                    <li><a href="emergency.php">Emergency</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h4>Contact Us</h4>
                <p><i class="fas fa-map-marker-alt"></i> 123 Health St, MedCity</p>
                <p><i class="fas fa-phone"></i> (123) 456-7890</p>
                <p><i class="fas fa-envelope"></i> contact@abcmedical.com</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date("Y"); ?> <?= htmlspecialchars($site_name); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Basic mobile navigation toggle
        const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
        const mainNav = document.querySelector('.main-nav');

        if (mobileNavToggle && mainNav) {
            mobileNavToggle.addEventListener('click', () => {
                mainNav.classList.toggle('active');
            });
        }

        // Profile dropdown toggle
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show-dropdown');
            }
        }

        // Close dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-trigger') && !event.target.matches('.profile-picture')) {
                const dropdowns = document.getElementsByClassName("profile-dropdown-content");
                for (let i = 0; i < dropdowns.length; i++) {
                    let openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show-dropdown')) {
                        openDropdown.classList.remove('show-dropdown');
                    }
                }
            }
        }
    </script>
</body>
</html>
