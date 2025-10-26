<?php

session_start(); // Keep session_start() for potential user login status/preferences

$page_title = "Our Doctors - ABC Medical";

// --- DATABASE CONNECTION CONFIGURATION ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = null;
$feedback_message = '';
$feedback_type = '';

// --- ESTABLISH DATABASE CONNECTION ---
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        throw new Exception("Sorry, we're experiencing technical difficulties. Please try again later.");
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    $feedback_message = $e->getMessage();
    $feedback_type = 'error';
    // No redirect here, display message on page.
}

// Display feedback from GET parameters (if any)
if (isset($_GET['feedback']) && isset($_GET['type'])) {
    $feedback_message = htmlspecialchars(urldecode($_GET['feedback']));
    $feedback_type = htmlspecialchars($_GET['type']);
}

$doctors_list = [];
$specializations_list = [];

// Fetch specializations for the filter dropdown
if ($conn) {
    $sql_specializations = "SELECT id, name FROM specializations ORDER BY name ASC";
    $result_specializations = $conn->query($sql_specializations);
    if ($result_specializations) {
        while ($row = $result_specializations->fetch_assoc()) {
            $specializations_list[] = $row;
        }
    } else {
        error_log("Error fetching specializations: " . $conn->error);
    }
}

// --- Build Doctor Query with Filters (Search Filter Removed) ---
$filter_specialization = intval($_GET['specialization'] ?? 0); // Convert to int
$filter_gender = trim($_GET['gender'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1)); // Current page for pagination
$doctors_per_page = 12; // Number of doctors to display per page (2 rows of 6)

$where_clauses = ["d.is_active = TRUE"];
$params = [];
$param_types = "";

if ($filter_specialization > 0) {
    $where_clauses[] = "d.specialization_id = ?";
    $params[] = $filter_specialization;
    $param_types .= "i";
}

if (!empty($filter_gender) && in_array($filter_gender, ['Male', 'Female', 'Other'])) {
    $where_clauses[] = "d.gender = ?";
    $params[] = $filter_gender;
    $param_types .= "s";
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- Get Total Doctors Count (for pagination) ---
$total_doctors = 0;
if ($conn) {
    $count_sql = "SELECT COUNT(d.id) AS total FROM doctors d LEFT JOIN specializations s ON d.specialization_id = s.id" . $where_sql;
    $stmt_count = $conn->prepare($count_sql);

    if ($stmt_count && !empty($params)) {
        $stmt_count->bind_param($param_types, ...$params);
    }

    if ($stmt_count && $stmt_count->execute()) {
        $count_result = $stmt_count->get_result();
        $total_doctors = $count_result->fetch_assoc()['total'];
    } else {
        error_log("Error counting doctors: " . ($stmt_count ? $stmt_count->error : $conn->error));
    }
    if ($stmt_count) $stmt_count->close();
}

$total_pages = ceil($total_doctors / $doctors_per_page);
$offset = ($current_page - 1) * $doctors_per_page;

// --- Fetch Doctors with Filters and Pagination ---
if ($conn) {
    $sql_doctors = "SELECT
                        d.id,
                        d.name,
                        d.email,
                        d.phone,
                        s.name AS specialization_name,
                        d.license_number,
                        d.bio,
                        d.profile_image_url,
                        d.consultation_fee,
                        d.years_of_experience,
                        d.gender
                    FROM
                        doctors d
                    LEFT JOIN
                        specializations s ON d.specialization_id = s.id"
                    . $where_sql .
                    " ORDER BY d.name ASC LIMIT ?, ?";

    $stmt_doctors = $conn->prepare($sql_doctors);

    if ($stmt_doctors) {
        // Add limit parameters to existing params and param_types
        $params[] = $offset;
        $params[] = $doctors_per_page;
        $param_types .= "ii"; // Add integer types for limit and offset

        // Use call_user_func_array for binding with dynamic number of parameters
        // The first argument is the statement, subsequent are the parameters.
        // Array_unshift adds param_types to the beginning of the params array
        call_user_func_array([$stmt_doctors, 'bind_param'], array_merge([$param_types], $params));

        if ($stmt_doctors->execute()) {
            $result_doctors = $stmt_doctors->get_result();
            while ($row = $result_doctors->fetch_assoc()) {
                $doctors_list[] = $row;
            }
        } else {
            error_log("Error fetching doctors with filters: " . $stmt_doctors->error);
            $feedback_message = "Error retrieving doctor information. Please try again later.";
            $feedback_type = 'error';
        }
        $stmt_doctors->close();
    } else {
        error_log("Error preparing doctor fetch statement: " . $conn->error);
        $feedback_message = "A system error occurred while preparing doctor data.";
        $feedback_type = 'error';
    }

    $conn->close();
}

// Featured doctors logic is no longer needed as the section is removed
// $featured_doctors = array_slice($doctors_list, 0, min(6, count($doctors_list)));
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
        /* General Layout and Typography */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5; /* Light gray background */
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .page-container {
            max-width: 1200px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 30px;
        }

        h1, h2, h3 {
            font-family: 'Montserrat', sans-serif;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 2.8rem;
            text-align: center;
            margin-bottom: 10px;
        }

        .sub-heading {
            text-align: center;
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 30px;
        }

        /* Feedback Message Styles */
        .message-feedback {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .message-feedback.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message-feedback.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-feedback i {
            font-size: 1.2em;
        }

        /* Filter Section (Simplified for no search) */
        .filter-search-bar {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .filter-group {
            flex: 1; /* Allows groups to grow */
            min-width: 180px; /* Minimum width before wrapping */
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .filter-group select { /* Only select remains */
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            -webkit-appearance: none; /* Remove default dropdown arrow */
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cpath%20fill%3D%22%23495057%22%20d%3D%22M205.957%2090.009L128%20167.966%2050.043%2090.009z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
        }
        .filter-group select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        .filter-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 600;
        }
        .filter-buttons .btn-primary-filter {
            background-color: #007bff;
            color: white;
        }
        .filter-buttons .btn-primary-filter:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .filter-buttons .btn-secondary-filter {
            background-color: #e9ecef;
            color: #333;
        }
        .filter-buttons .btn-secondary-filter:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
        }

        /* Doctors Section (Unified) */
        .doctors-section {
            margin-bottom: 40px;
        }
        .doctors-section h2 {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .doctors-section h2 i {
            font-size: 1.2em;
        }
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Adjusted for 6 in a row */
            gap: 20px; /* Slightly reduced gap for more compact layout */
        }

        /* Doctor Card Styles (Unified) */
        .doctor-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px; /* Slightly reduced padding for compactness */
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #e9ecef;
        }
        .doctor-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        .doctor-card .doc-img-container {
            width: 100px; /* Smaller image size for 6-in-a-row */
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 10px; /* Reduced margin */
            border: 3px solid #007bff; /* Slightly thinner border */
            box-shadow: 0 0 0 3px rgba(0,123,255,0.3);
            flex-shrink: 0;
        }
        .doctor-card .doc-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .doctor-card h3 {
            font-size: 1.2rem; /* Smaller font size for name */
            margin-top: 0;
            margin-bottom: 3px; /* Reduced margin */
            color: #34495e;
        }
        .doctor-card .specialization {
            color: #00796b;
            font-weight: 600;
            margin-bottom: 8px; /* Reduced margin */
            font-size: 0.85rem; /* Smaller font size */
        }
        .doctor-card .info-row {
            font-size: 0.8rem; /* Smaller font size */
            color: #555;
            margin-bottom: 3px;
        }
        .doctor-card .info-row strong {
            color: #333;
        }
        .doctor-card .fee {
            font-size: 0.95rem; /* Smaller font size */
            color: #e74c3c;
            font-weight: 700;
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .doctor-card .bio {
            font-size: 0.75rem; /* Even smaller font for bio */
            color: #666;
            margin-top: 10px;
            margin-bottom: 10px;
            border-top: 1px dashed #eee;
            padding-top: 10px;
            text-align: left;
            overflow: hidden; /* Ensure bio doesn't overflow */
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Limit bio to 2 lines */
            -webkit-box-orient: vertical;
        }
        .doctor-card .contact-info-short {
            font-size: 0.75rem; /* Smaller font size */
            color: #777;
            margin-top: 8px;
            line-height: 1.3;
            text-align: left;
        }
        .doctor-card .contact-info-short i {
            margin-right: 3px; /* Reduced margin */
            color: #007bff;
        }
        .doctor-card .btn-book-appointment {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 8px 12px; /* Reduced padding */
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 15px; /* Reduced margin */
            width: auto;
            box-shadow: 0 4px 10px rgba(40,167,69,0.15);
            font-size: 0.9rem; /* Smaller font size for button */
        }
        .doctor-card .btn-book-appointment:hover {
            background-color: #218838;
            transform: translateY(-3px);
        }
        .doctor-card .btn-book-appointment i {
            margin-right: 5px; /* Reduced margin */
        }

        .no-doctors-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2rem;
            color: #777;
            background-color: #fdfdfd;
            border-radius: 10px;
            border: 1px dashed #ced4da;
            margin-top: 30px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 40px;
            gap: 5px;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-decoration: none;
            color: #007bff;
            font-weight: 600;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }
        .pagination span.current-page {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            cursor: default;
        }
        .pagination span.disabled {
            color: #ced4da;
            cursor: not-allowed;
        }

        /* Footer */
        .page-footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid #eee;
            color: #777;
        }
        .page-footer p {
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        .page-footer .back-to-home-btn {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-bottom: 10px;
        }
        .page-footer .back-to-home-btn:hover {
            background-color: #5a6268;
        }
        .page-footer .back-to-home-btn i {
            margin-right: 5px;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .doctors-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .page-container {
                margin: 20px auto;
                padding: 20px;
            }
            h1 {
                font-size: 2.2rem;
            }
            .doctors-section h2 {
                font-size: 1.8rem;
            }
            .doctors-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .filter-search-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                min-width: unset;
                width: 100%;
            }
            .filter-buttons {
                flex-direction: column;
                gap: 10px;
            }
            .filter-buttons button {
                width: 100%;
            }
            .doctors-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
             .doctor-card .doc-img-container {
                width: 80px;
                height: 80px;
            }
            .doctor-card h3 {
                font-size: 1.1rem;
            }
            .doctor-card .specialization,
            .doctor-card .info-row,
            .doctor-card .fee,
            .doctor-card .bio,
            .doctor-card .contact-info-short,
            .doctor-card .btn-book-appointment {
                font-size: 0.7rem;
            }
            .doctor-card .btn-book-appointment {
                 padding: 6px 10px;
            }
        }
         @media (max-width: 480px) {
            .doctors-grid {
                grid-template-columns: 1fr;
            }
            .doctor-card .doc-img-container {
                width: 100px;
                height: 100px;
            }
            .doctor-card h3 {
                font-size: 1.4rem;
            }
            .doctor-card .specialization,
            .doctor-card .info-row,
            .doctor-card .fee,
            .doctor-card .contact-info-short {
                font-size: 0.9rem;
            }
            .doctor-card .bio {
                font-size: 0.85rem;
                -webkit-line-clamp: unset;
            }
            .doctor-card .btn-book-appointment {
                 padding: 10px 15px;
                 font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1>Meet Our Doctors</h1>
        <p class="sub-heading">Find the right specialist and book your appointment.</p>

        <?php if ($feedback_message): ?>
            <div class="message-feedback <?= htmlspecialchars($feedback_type); ?>">
                <i class="fas fa-info-circle"></i>
                <?= htmlspecialchars($feedback_message); ?>
            </div>
        <?php endif; ?>

        <section class="filter-search-bar">
            <form action="doctors_serial.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="specialization">Specialization:</label>
                    <select id="specialization" name="specialization">
                        <option value="0">All Specializations</option>
                        <?php foreach ($specializations_list as $spec): ?>
                            <option value="<?= htmlspecialchars($spec['id']); ?>" <?= ($filter_specialization == $spec['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($spec['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender">
                        <option value="">Any Gender</option>
                        <option value="Male" <?= ($filter_gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?= ($filter_gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?= ($filter_gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn-primary-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <button type="button" class="btn-secondary-filter" onclick="window.location.href='doctors_serial.php'">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </form>
        </section>

        <section class="doctors-section">
            <h2><i class="fas fa-user-md"></i> All Doctors</h2>
            <?php if (!empty($doctors_list)): ?>
                <div class="doctors-grid">
                    <?php foreach ($doctors_list as $doctor): ?>
                        <div class="doctor-card">
                            <div class="doc-img-container">
                                <?php
                                $image_path = !empty($doctor["profile_image_url"]) ? htmlspecialchars($doctor["profile_image_url"]) : 'images/default_doctor.png';
                                if (strpos($image_path, 'http') !== 0 && !file_exists($image_path)) {
                                    $image_path = 'images/default_doctor.png';
                                }
                                ?>
                                <img src="<?= $image_path; ?>" alt="Dr. <?= htmlspecialchars($doctor["name"]); ?>">
                            </div>
                            <h3>Dr. <?= htmlspecialchars($doctor["name"]); ?></h3>
                            <p class="specialization"><?= htmlspecialchars($doctor["specialization_name"] ?? 'N/A'); ?></p>

                            <?php if (!empty($doctor["years_of_experience"])): ?>
                                <p class="info-row"><strong>Experience:</strong> <?= htmlspecialchars($doctor["years_of_experience"]); ?> Years</p>
                            <?php endif; ?>

                            <?php if (isset($doctor["consultation_fee"])): ?>
                                <p class="fee"><strong>Consultation Fee:</strong> ৳<?= htmlspecialchars(number_format($doctor["consultation_fee"], 2)); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($doctor["bio"])): ?>
                                <p class="bio">
                                    <?php
                                    $bio_excerpt = strip_tags($doctor["bio"]);
                                    if (mb_strlen($bio_excerpt, 'UTF-8') > 100) {
                                        $bio_excerpt = mb_substr($bio_excerpt, 0, 100, 'UTF-8') . "...";
                                    }
                                    echo htmlspecialchars($bio_excerpt);
                                    ?>
                                </p>
                            <?php endif; ?>

                            <p class="contact-info-short">
                                <?php if (!empty($doctor["phone"])): ?>
                                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($doctor["phone"]); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($doctor["email"])): ?>
                                     <i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor["email"]); ?>
                                <?php endif; ?>
                            </p>
                            <a href="doctor_availability.php?doctor_id=<?= htmlspecialchars($doctor['id']); ?>" class="btn-book-appointment">
                                <i class="fas fa-calendar-check"></i> View Availability & Book
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $query_params = $_GET; // Get current GET parameters
                        unset($query_params['page']); // Remove page from array so we can add it
                        $base_query_string = http_build_query($query_params);
                        ?>

                        <a href="?page=1&<?= $base_query_string ?>" <?= ($current_page == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i> First
                        </a>

                        <a href="?page=<?= max(1, $current_page - 1); ?>&<?= $base_query_string ?>" <?= ($current_page == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-left"></i> Previous
                        </a>

                        <?php
                        // Display a limited range of pages
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) echo '<span>...</span>';

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?= $i; ?>&<?= $base_query_string ?>" <?= ($i == $current_page) ? 'class="current-page"' : ''; ?>>
                                <?= $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages) echo '<span>...</span>'; ?>

                        <a href="?page=<?= min($total_pages, $current_page + 1); ?>&<?= $base_query_string ?>" <?= ($current_page == $total_pages) ? 'class="disabled"' : ''; ?>>
                            Next <i class="fas fa-angle-right"></i>
                        </a>

                        <a href="?page=<?= $total_pages; ?>&<?= $base_query_string ?>" <?= ($current_page == $total_pages) ? 'class="disabled"' : ''; ?>>
                            Last <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p class="no-doctors-message">No doctors found matching your criteria at the moment.</p>
            <?php endif; ?>
        </section>

        <footer class="page-footer">
            <a href="index.php" class="back-to-home-btn">
                <i class="fas fa-home"></i> Back to Homepage
            </a>
            <p>&copy; <?= date("Y"); ?> ABC Medical. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>