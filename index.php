<?php
session_start(); // Start the session at the very beginning

$site_name = "ABC Medical";
$page_title = $site_name . " - Your Health Partner"; // Set a default page title

// Determine if a user or admin is logged in
$is_user_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user']);
$is_admin_logged_in = isset($_SESSION['admin_name']); // Assuming 'admin_name' is set for admin login

$user_display_name = "";
if ($is_user_logged_in) {
    // If a regular user, display their name from the session
    $user_display_name = htmlspecialchars($_SESSION['user']);
} elseif ($is_admin_logged_in) {
    // If an admin, display 'Admin' prefix with their name
    $user_display_name = "Admin: " . htmlspecialchars($_SESSION['admin_name']);
}

// Set profile picture URL
$profile_pic_url = "https://placehold.co/80x80/E0F7FA/00796B?text=";
if (!empty($user_display_name)) {
    // Use the first letter of the display name for the placeholder if available
    $profile_pic_url .= strtoupper(substr($user_display_name, 0, 1));
} else {
    // Default placeholder text if no name is available (e.g., if session just started but no user set)
    $profile_pic_url .= "U";
}

// If user is logged in and a custom profile picture URL is available in session, use it
if ($is_user_logged_in && isset($_SESSION['user_profile_pic']) && !empty($_SESSION['user_profile_pic'])) {
    $profile_pic_url = htmlspecialchars($_SESSION['user_profile_pic']);
}

// Current year for copyright
$current_year = date("Y");

// --- Database Connection (No longer needed for recent orders if section is removed) ---
// The following DB connection and query for recent orders is removed
// as the "My Recent Medicine Orders" section is no longer displayed on index.php.

/*
// If you still need DB connection for other reasons on index.php, keep this block
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null;
$recent_orders = []; // This variable is no longer populated or used

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("DB Connection Failed in index.php: " . $conn->connect_error);
    } else {
        $conn->set_charset("utf8mb4");

        if ($is_user_logged_in) {
            $user_db_id = $_SESSION['user_id'];
            $stmt_user_contact = $conn->prepare("SELECT email, phone FROM users WHERE id = ? LIMIT 1");
            if ($stmt_user_contact) {
                $stmt_user_contact->bind_param("i", $user_db_id);
                if ($stmt_user_contact->execute()) {
                    $result_user_contact = $stmt_user_contact->get_result();
                    $user_contact_data = $result_user_contact->fetch_assoc();
                    $stmt_user_contact->close();

                    if ($user_contact_data) {
                        $user_email = $user_contact_data['email'];
                        $user_phone = $user_contact_data['phone'];

                        $stmt_orders = $conn->prepare("
                            SELECT order_id, order_date, total_amount, order_status, payment_method
                            FROM orders
                            WHERE customer_email = ? OR customer_phone = ?
                            ORDER BY order_date DESC
                            LIMIT 3
                        ");
                        if ($stmt_orders) {
                            $stmt_orders->bind_param("ss", $user_email, $user_phone);
                            if ($stmt_orders->execute()) {
                                $result_orders = $stmt_orders->get_result();
                                while ($row = $result_orders->fetch_assoc()) {
                                    $recent_orders[] = $row;
                                }
                            } else {
                                error_log("Failed to execute orders fetch statement in index.php: " . $stmt_orders->error);
                            }
                            $stmt_orders->close();
                        } else {
                            error_log("Failed to prepare orders fetch statement in index.php: " . $conn->error);
                        }
                    }
                } else {
                    error_log("Error executing user contact fetch in index.php: " . $stmt_user_contact->error);
                }
            } else {
                error_log("Failed to prepare user contact fetch statement in index.php: " . $conn->error);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error during database operation in index.php: " . $e->getMessage());
} finally {
    if ($conn) {
        $conn->close();
    }
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* General Body and Container for 3D Perspective */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f4f8; /* Lighter, more modern background */
            color: #333;
            overflow-x: hidden; /* Prevent horizontal scroll from animations */
            perspective: 1000px; /* Enable 3D perspective for child elements */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- Global Animations & Effects --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseShadow {
            0% { box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            50% { box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
            100% { box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        }

        /* --- Header Styles (unchanged significantly for simplicity) --- */
        .site-header {
            background-color: #00796b; /* Dark teal */
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1000; /* Ensure header is above other elements */
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: white;
        }

        .logo i {
            font-size: 2.5rem;
            margin-right: 10px;
        }

        .logo h1 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 700;
        }

        .main-nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .main-nav li {
            margin-left: 30px;
        }

        .main-nav a {
            color: white;
            text-decoration: none;
            font-weight: 400;
            padding: 5px 0;
            transition: color 0.3s ease, border-bottom 0.3s ease;
            position: relative;
        }

        .main-nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: #e0f7fa;
            transition: width 0.3s ease;
        }

        .main-nav a:hover::after,
        .main-nav a.active::after {
            width: 100%;
        }

        .mobile-nav-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.8rem;
            color: white;
            cursor: pointer;
        }

        /* --- User Profile Dropdown Styles --- */
        .user-profile-dropdown {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1010;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .profile-trigger:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .profile-picture {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid #fff;
            object-fit: cover;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .profile-dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background-color: #ffffff;
            min-width: 200px;
            box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 10px 0;
            margin-top: 10px;
            animation: fadeIn 0.3s ease-out forwards; /* Fade in animation */
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
            color: #00796b;
            width: 16px;
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

        /* --- Main Content & Panels --- */
        .site-main {
            padding: 40px 0;
            position: relative;
            z-index: 1; /* Below header */
        }

        .content-area {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            animation: fadeIn 0.6s ease-out forwards;
        }

        /* --- Hero Section - Dynamic Elements --- */
        .hero-section {
            position: relative; /* For pseudo-elements */
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            padding: 50px 0;
            min-height: 450px; /* Slightly taller for more visual space */
            background: linear-gradient(to right, #e0f7fa, #ffffff);
            border-radius: 15px; /* More rounded */
            margin-bottom: 40px;
            overflow: hidden; /* Contain background elements */
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform-style: preserve-3d; /* Enable 3D transforms for children */
            perspective: 800px; /* Stronger perspective for parallax */
        }

        /* Subtle animated background shapes */
        .hero-section::before,
        .hero-section::after {
            content: '';
            position: absolute;
            background-color: rgba(0, 121, 107, 0.05); /* Lighter teal circles */
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: moveBackground 20s infinite alternate ease-in-out;
        }
        .hero-section::before {
            width: 300px;
            height: 300px;
            top: -50px;
            left: -50px;
            transform: translateZ(-100px); /* Push further back */
        }
        .hero-section::after {
            width: 200px;
            height: 200px;
            bottom: -30px;
            right: -30px;
            animation-delay: 5s; /* Stagger animation */
            transform: translateZ(-50px); /* Push further back */
        }
        @keyframes moveBackground {
            0% { transform: translate(0, 0) scale(1) translateZ(-100px); }
            25% { transform: translate(20px, 30px) scale(1.05) translateZ(-120px); }
            50% { transform: translate(-10px, -20px) scale(1) translateZ(-100px); }
            75% { transform: translate(15px, -10px) scale(0.95) translateZ(-80px); }
            100% { transform: translate(0, 0) scale(1) translateZ(-100px); }
        }

        .hero-content, .hero-image-container {
            z-index: 1; /* Bring content to front */
            transform-style: preserve-3d;
        }

        .hero-content h2 {
            font-size: 3rem; /* Slightly larger */
            color: #004d40;
            margin-bottom: 20px;
            line-height: 1.2;
            transform: translateZ(50px); /* Bring text slightly forward */
            animation: fadeIn 0.8s ease-out forwards;
        }

        .hero-content p {
            font-size: 1.15rem; /* Slightly larger */
            color: #555;
            line-height: 1.6;
            margin-bottom: 30px;
            transform: translateZ(30px); /* Bring text slightly forward */
            animation: fadeIn 1s ease-out forwards;
        }

        .hero-actions .btn {
            margin-right: 15px;
            margin-bottom: 10px;
            transform: translateZ(20px); /* Bring buttons slightly forward */
            animation: fadeIn 1.2s ease-out forwards;
        }

        .hero-image {
            max-width: 100%;
            height: auto;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            transition: transform 0.5s ease-out; /* Smooth transform for hover */
            transform: translateZ(0); /* Base Z position */
            animation: fadeIn 1s ease-out forwards;
        }

        /* Image hover effect */
        .hero-image-container:hover .hero-image {
            transform: translateZ(50px) scale(1.03); /* Lift and scale on hover */
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
        }

        /* --- Buttons --- */
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease, transform 0.1s ease; /* Add transform transition */
            cursor: pointer;
            border: none;
            text-align: center;
        }

        .btn-primary {
            background-color: #00796b;
            color: white;
        }

        .btn-primary:hover {
            background-color: #004d40;
            transform: translateY(-3px); /* Stronger lift */
        }

        .btn-secondary {
            background-color: #e0f7fa;
            color: #00796b;
            border: 1px solid #00796b;
        }

        .btn-secondary:hover {
            background-color: #b2dfdb;
            transform: translateY(-3px);
        }

        .btn-tertiary.admin-btn {
            background-color: #f0f0f0;
            color: #555;
            border: 1px solid #ccc;
        }

        .btn-tertiary.admin-btn:hover {
            background-color: #e0e0e0;
            transform: translateY(-3px);
        }

        /* --- Features Section --- */
        .features-section {
            padding: 60px 0;
            text-align: center;
            background-color: #fcfcfc;
            border-top: 1px solid #eee;
            position: relative;
            z-index: 0;
        }

        .section-title {
            font-size: 2rem;
            color: #004d40;
            margin-bottom: 40px;
            animation: fadeIn 0.8s ease-out forwards;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .feature-item {
            background-color: white;
            padding: 30px;
            border-radius: 12px; /* More rounded */
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            transform-style: preserve-3d; /* Enable 3D transforms for hover */
            position: relative;
            animation: fadeIn 0.8s ease-out forwards;
        }

        .feature-item:nth-child(2) { animation-delay: 0.1s; }
        .feature-item:nth-child(3) { animation-delay: 0.2s; }
        /* Add more delays if you have more items for staggered animation */


        .feature-item:hover {
            transform: translateY(-10px) rotateX(5deg) scale(1.02); /* Lift, slight rotation, slight scale */
            box-shadow: 0 15px 30px rgba(0,0,0,0.2); /* Stronger shadow */
            z-index: 10; /* Bring to front on hover */
        }

        .feature-item i {
            font-size: 3.5rem; /* Slightly larger icon */
            color: #00796b;
            margin-bottom: 20px;
            transform: translateZ(20px); /* Give icon depth */
        }

        .feature-item h3 {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 10px;
            transform: translateZ(15px);
        }

        .feature-item p {
            font-size: 0.95rem;
            color: #666;
            transform: translateZ(10px);
        }

        /* --- Dashboard Cards (User/Admin) - Dynamic Elements --- */
        .user-dashboard-panel, .admin-dashboard-panel {
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            animation: fadeIn 0.6s ease-out forwards;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .dashboard-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px; /* More rounded */
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            display: flex;
            flex-direction: column;
            transform-style: preserve-3d; /* Enable 3D transforms */
            position: relative;
            animation: fadeIn 0.8s ease-out forwards; /* Fade in */
        }

        .dashboard-card:nth-child(2) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.3s; }

        .dashboard-card:hover {
            transform: translateY(-10px) rotateY(3deg) scale(1.02); /* Lift, slight Y-axis rotation, slight scale */
            box-shadow: 0 12px 25px rgba(0,0,0,0.18);
            z-index: 10;
        }
        .dashboard-card i {
            font-size: 2.8rem; /* Slightly larger icon */
            color: #00796b;
            margin-bottom: 15px;
            transform: translateZ(20px); /* Give icon depth */
        }
        .dashboard-card h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 10px;
            transform: translateZ(15px);
        }
        .dashboard-card p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
            flex-grow: 1;
            transform: translateZ(10px);
        }
        .dashboard-card .btn {
            margin-top: auto;
            transform: translateZ(25px); /* Push button forward */
            transition: all 0.3s ease;
        }
        .dashboard-card:hover .btn {
            transform: translateZ(35px) scale(1.05); /* Make button pop more on hover */
        }

        /* --- Footer Styles --- */
        .site-footer {
            background-color: #263238;
            color: white;
            padding: 40px 0 20px;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-about, .footer-links, .footer-contact {
            flex: 1;
            min-width: 250px;
        }

        .footer-about h3, .footer-links h4, .footer-contact h4 {
            color: #80cbc4;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .footer-about p {
            line-height: 1.6;
            color: #bbb;
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer-contact p {
            margin-bottom: 10px;
            color: #bbb;
            display: flex;
            align-items: center;
        }

        .footer-contact i {
            margin-right: 10px;
            color: #80cbc4;
        }

        .footer-bottom {
            text-align: center;
            border-top: 1px solid #455a64;
            padding-top: 20px;
            margin-top: 20px;
            color: #bbb;
        }

        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .main-nav {
                width: 100%;
                margin-top: 15px;
                display: none;
                flex-direction: column;
                background-color: #00796b;
            }

            .main-nav.active {
                display: flex;
            }

            .main-nav ul {
                flex-direction: column;
                width: 100%;
            }

            .main-nav li {
                margin: 0;
                width: 100%;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .main-nav li:last-child {
                border-bottom: none;
            }

            .main-nav a {
                padding: 15px 20px;
                display: block;
            }

            .mobile-nav-toggle {
                display: block;
                position: absolute;
                top: 25px;
                right: 20px;
            }

            .user-profile-dropdown {
                top: 20px;
                right: 70px;
            }

            .hero-section {
                flex-direction: column;
                text-align: center;
            }

            .hero-content {
                max-width: 100%;
                text-align: center;
            }

            .hero-actions {
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .hero-actions .btn {
                margin-right: 0;
                margin-bottom: 15px;
                width: 80%;
                max-width: 300px;
            }

            .footer-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .dashboard-grid {
                grid-template-columns: 1fr; /* Stack dashboard cards */
            }
            .order-preview-list {
                grid-template-columns: 1fr; /* Stack order preview items */
            }
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
                                <i class="fas fa-user-doctor"></i>
                                <h3>Find Doctors</h3>
                                <p>Search for specialists and book consultations.</p>
                                <a href="doctors_serial.php" class="btn btn-primary">Find a Doctor</a>
                            </div>
                            <div class="dashboard-card">
                                <i class="fas fa-pills"></i>
                                <h3>Buy Medicine</h3>
                                <p>Order medicines online for home delivery.</p>
                                <a href="buy_medicine.php" class="btn btn-primary">Order Medicine</a>
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

            <?php else: // Guest view ?>
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
            <p>&copy; <?= $current_year; ?> <?= htmlspecialchars($site_name); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Mobile navigation toggle
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

        // Close the dropdown if the user clicks outside of it
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