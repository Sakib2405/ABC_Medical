<?php
// --- Enable error reporting for debugging (REMOVE FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$page_title = "Manage Doctors - ABC Medical Admin";

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error_message'] = "You must be logged in as an admin to access this page.";
    header("Location: admin_login.php");
    exit;
}
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- 2. DATABASE CONNECTION ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Admin Manage Doctors - DB Connection Error: " . $conn->connect_error);
    die("DATABASE CONNECTION FAILED. Please check configuration. (Err: ADM_MD_DB_CONN)");
}
$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Dhaka');

// --- Configuration ---
define('UPLOAD_DIR_DOCTORS', 'uploads/doctor_profiles/'); // Ensure this directory exists and is writable

$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

// --- Helper function ---
function sanitize_input($data, $conn_obj = null) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn_obj) {
        $data = $conn_obj->real_escape_string($data);
    }
    return $data;
}

// --- Handle Actions (Add, Edit, Update Status) ---
$action = $_GET['action'] ?? 'list'; // Default action
$doctor_id_to_edit = null;
$doctor_to_edit_data = null;

// Fetch specializations for dropdowns
$specializations = [];
$result_spec = $conn->query("SELECT id, name FROM specializations ORDER BY name ASC");
if ($result_spec) {
    while ($row_spec = $result_spec->fetch_assoc()) {
        $specializations[] = $row_spec;
    }
    $result_spec->free();
}


// --- ADD OR UPDATE DOCTOR ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_doctor']) || isset($_POST['update_doctor']))) {
    $name = sanitize_input($_POST['name'], $conn);
    $email = sanitize_input($_POST['email'], $conn);
    $phone = sanitize_input($_POST['phone'], $conn);
    $specialization_id = filter_var($_POST['specialization_id'], FILTER_VALIDATE_INT);
    $license_number = sanitize_input($_POST['license_number'], $conn);
    $bio = sanitize_input($_POST['bio'], $conn); // Textarea, basic sanitize
    $consultation_fee = filter_var($_POST['consultation_fee'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $years_of_experience = filter_var($_POST['years_of_experience'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $gender = in_array($_POST['gender'], ['Male', 'Female', 'Other']) ? $_POST['gender'] : null;
    $schedule_json = trim($_POST['schedule_json']); // Keep as string, validate JSON later
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $current_image_url = $_POST['current_profile_image_url'] ?? null;
    $profile_image_url_final = $current_image_url; // Default to current

    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || $specialization_id === false) {
        $feedback_message = "Name, Email, Phone, and Specialization are required.";
        $feedback_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback_message = "Invalid email format.";
        $feedback_type = 'error';
    } elseif (!empty($schedule_json) && json_decode($schedule_json) === null && json_last_error() !== JSON_ERROR_NONE) {
        $feedback_message = "Schedule JSON is not valid JSON. Error: " . json_last_error_msg();
        $feedback_type = 'error';
    } else {
        // Handle file upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
            if (!is_dir(UPLOAD_DIR_DOCTORS)) {
                mkdir(UPLOAD_DIR_DOCTORS, 0755, true);
            }
            $image_name = uniqid('doc_', true) . '_' . basename($_FILES['profile_image']['name']);
            $target_file = UPLOAD_DIR_DOCTORS . $image_name;
            $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($image_file_type, $allowed_types) && $_FILES['profile_image']['size'] <= 2000000) { // Max 2MB
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $profile_image_url_final = $target_file;
                    // Optionally delete old image if updating and new image is uploaded & current_image_url is set
                    if (isset($_POST['update_doctor']) && $current_image_url && $current_image_url !== $profile_image_url_final && file_exists($current_image_url)) {
                        @unlink($current_image_url);
                    }
                } else {
                    $feedback_message = "Sorry, there was an error uploading your profile image.";
                    $feedback_type = 'error';
                }
            } else {
                $feedback_message = "Invalid image file type or size (Max 2MB; JPG, JPEG, PNG, GIF allowed).";
                $feedback_type = 'error';
            }
        } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['profile_image']['error'] != UPLOAD_ERR_OK) {
            $feedback_message = "File upload error: " . $_FILES['profile_image']['error'];
            $feedback_type = 'error';
        }


        if ($feedback_type !== 'error') { // Proceed if no upload error or other validation error
            if (isset($_POST['add_doctor'])) {
                $sql = "INSERT INTO doctors (name, email, phone, specialization_id, license_number, bio, profile_image_url, consultation_fee, years_of_experience, gender, is_active, schedule_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssisssdissis", $name, $email, $phone, $specialization_id, $license_number, $bio, $profile_image_url_final, $consultation_fee, $years_of_experience, $gender, $is_active, $schedule_json);
            } elseif (isset($_POST['update_doctor'])) {
                $doctor_id_to_update_post = filter_var($_POST['doctor_id'], FILTER_VALIDATE_INT);
                if (!$doctor_id_to_update_post) {
                    $feedback_message = "Invalid Doctor ID for update."; $feedback_type = 'error';
                } else {
                    $sql = "UPDATE doctors SET name=?, email=?, phone=?, specialization_id=?, license_number=?, bio=?, profile_image_url=?, consultation_fee=?, years_of_experience=?, gender=?, is_active=?, schedule_json=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssisssdissisi", $name, $email, $phone, $specialization_id, $license_number, $bio, $profile_image_url_final, $consultation_fee, $years_of_experience, $gender, $is_active, $schedule_json, $doctor_id_to_update_post);
                }
            }

            if (isset($stmt) && $stmt->execute()) {
                $feedback_message = "Doctor " . (isset($_POST['add_doctor']) ? "added" : "updated") . " successfully.";
                $feedback_type = 'success';
                $action = 'list'; // Go back to list view
            } elseif(isset($stmt)) {
                $feedback_message = "Error: " . $stmt->error;
                $feedback_type = 'error';
            }
             if(isset($stmt)) $stmt->close();
        }
    }
     // If error, retain form values for correction (especially for update)
    if ($feedback_type === 'error' && isset($_POST['update_doctor'])) {
        $action = 'edit'; // Keep showing edit form
        $doctor_id_to_edit = filter_var($_POST['doctor_id'], FILTER_VALIDATE_INT);
        // Repopulate $doctor_to_edit_data with POSTed values to refill the form
        $doctor_to_edit_data = $_POST;
        $doctor_to_edit_data['profile_image_url'] = $current_image_url; // keep current image if new upload failed
    } elseif ($feedback_type === 'error' && isset($_POST['add_doctor'])) {
        $action = 'add'; // Keep showing add form
        $doctor_to_edit_data = $_POST; // Repopulate add form
    }

}

// --- TOGGLE ACTIVE STATUS ---
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $doctor_id_toggle = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $current_status = filter_var($_GET['status'], FILTER_VALIDATE_INT);

    if ($doctor_id_toggle && ($current_status === 0 || $current_status === 1)) {
        $new_status = $current_status == 1 ? 0 : 1;
        $stmt_toggle = $conn->prepare("UPDATE doctors SET is_active = ? WHERE id = ?");
        $stmt_toggle->bind_param("ii", $new_status, $doctor_id_toggle);
        if ($stmt_toggle->execute()) {
            $feedback_message = "Doctor status updated successfully.";
            $feedback_type = 'success';
        } else {
            $feedback_message = "Error updating status: " . $stmt_toggle->error;
            $feedback_type = 'error';
        }
        $stmt_toggle->close();
    } else {
        $feedback_message = "Invalid parameters for status toggle.";
        $feedback_type = 'error';
    }
    $action = 'list'; // Go back to list view
}


// --- PREPARE DATA FOR EDIT FORM ---
if ($action === 'edit' && isset($_GET['id']) && $feedback_type !== 'error') { // Only fetch if not already populated due to error
    $doctor_id_to_edit = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($doctor_id_to_edit) {
        $stmt_edit = $conn->prepare("SELECT d.*, s.name as specialization_name FROM doctors d LEFT JOIN specializations s ON d.specialization_id = s.id WHERE d.id = ?");
        $stmt_edit->bind_param("i", $doctor_id_to_edit);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $doctor_to_edit_data = $result_edit->fetch_assoc();
        } else {
            $feedback_message = "Doctor not found for editing.";
            $feedback_type = 'error';
            $action = 'list';
        }
        $stmt_edit->close();
    } else {
        $feedback_message = "Invalid Doctor ID for editing.";
        $feedback_type = 'error';
        $action = 'list';
    }
}


// --- FETCH ALL DOCTORS FOR LISTING (Default Action) ---
$doctors_list = [];
if ($action === 'list') {
    $sql_list = "SELECT d.id, d.name, d.email, d.phone, s.name as specialization_name, d.is_active, d.consultation_fee
                 FROM doctors d
                 LEFT JOIN specializations s ON d.specialization_id = s.id
                 ORDER BY d.name ASC";
    $result_list = $conn->query($sql_list);
    if ($result_list) {
        while ($row = $result_list->fetch_assoc()) {
            $doctors_list[] = $row;
        }
        $result_list->free();
    } else {
        $feedback_message = "Error fetching doctors list: " . $conn->error;
        $feedback_type = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin_dashboard.css"> <link rel="stylesheet" href="admin_manage_doctors.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container">
        <header class="dashboard-header-main" style="margin-bottom: 20px;"> <div class="header-content">
                <h1><i class="fas fa-user-md"></i> Manage Doctors</h1>
                <p>Logged in as: <?= htmlspecialchars($admin_name); ?></p>
            </div>
            <nav class="admin-nav">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </header>

        <main class="admin-content-area">
            <?php if ($feedback_message): ?>
                <div class="message-feedback <?= htmlspecialchars($feedback_type); ?>">
                    <?= htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <?php
                $form_action_title = ($action === 'add') ? "Add New Doctor" : "Edit Doctor Profile";
                $form_button_text = ($action === 'add') ? "Add Doctor" : "Update Doctor";
                $form_post_action = ($action === 'add') ? "add_doctor" : "update_doctor";
                $current_data = $doctor_to_edit_data ?? []; // Use for pre-filling form
                ?>
                <section class="form-section doctor-form-section">
                    <h2><?= $form_action_title; ?></h2>
                    <form action="admin_manage_doctors.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="<?= $form_post_action; ?>" value="1">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="doctor_id" value="<?= htmlspecialchars($current_data['id'] ?? ''); ?>">
                            <input type="hidden" name="current_profile_image_url" value="<?= htmlspecialchars($current_data['profile_image_url'] ?? ''); ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($current_data['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($current_data['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($current_data['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="specialization_id">Specialization <span class="required">*</span></label>
                                <select name="specialization_id" id="specialization_id" required>
                                    <option value="">-- Select Specialization --</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?= $spec['id']; ?>" <?= (isset($current_data['specialization_id']) && $current_data['specialization_id'] == $spec['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($spec['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($current_data['license_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="consultation_fee">Consultation Fee (BDT)</label>
                                <input type="number" step="0.01" id="consultation_fee" name="consultation_fee" value="<?= htmlspecialchars($current_data['consultation_fee'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="years_of_experience">Years of Experience</label>
                                <input type="number" id="years_of_experience" name="years_of_experience" value="<?= htmlspecialchars($current_data['years_of_experience'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select name="gender" id="gender">
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male" <?= (isset($current_data['gender']) && $current_data['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?= (isset($current_data['gender']) && $current_data['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?= (isset($current_data['gender']) && $current_data['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group form-group-full">
                                <label for="bio">Biography / Details</label>
                                <textarea id="bio" name="bio" rows="4"><?= htmlspecialchars($current_data['bio'] ?? ''); ?></textarea>
                            </div>
                             <div class="form-group form-group-full">
                                <label for="schedule_json">Weekly Schedule (JSON format)</label>
                                <textarea id="schedule_json" name="schedule_json" rows="8" placeholder='e.g., {"mon": {"start": "09:00", "end": "17:00", "interval": 30}, "tue": null, ...}'><?= htmlspecialchars($current_data['schedule_json'] ?? ''); ?></textarea>
                                <small>Enter valid JSON. Example: <code>{"mon": {"start": "09:00", "end": "17:00", "interval": 30, "break_start": "13:00", "break_end": "14:00"}, "tue": null}</code>. Days not mentioned or set to <code>null</code> are off days.</small>
                            </div>
                            <div class="form-group">
                                <label for="profile_image">Profile Image (Max 2MB: JPG, PNG, GIF)</label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif">
                                <?php if ($action === 'edit' && !empty($current_data['profile_image_url']) && file_exists($current_data['profile_image_url'])): ?>
                                    <p class="current-image-notice">Current Image: <img src="<?= htmlspecialchars($current_data['profile_image_url']); ?>" alt="Current Profile Image" style="max-height: 50px; vertical-align: middle; margin-left:10px;"></p>
                                <?php elseif ($action === 'edit' && !empty($current_data['profile_image_url'])): ?>
                                     <p class="current-image-notice">Current Image Path: <?= htmlspecialchars($current_data['profile_image_url']); ?> (File not found on server)</p>
                                <?php endif; ?>
                            </div>
                            <div class="form-group form-group-checkbox">
                                <label for="is_active">
                                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= (isset($current_data['is_active']) && $current_data['is_active'] == 1) ? 'checked' : ($action === 'add' ? 'checked' : ''); ?>>
                                    Active Profile
                                </label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="button-primary"><i class="fas fa-save"></i> <?= $form_button_text; ?></button>
                            <a href="admin_manage_doctors.php" class="button-secondary">Cancel</a>
                        </div>
                    </form>
                </section>
            <?php endif; ?>


            <?php if ($action === 'list'): ?>
                <div class="action-bar">
                    <a href="admin_manage_doctors.php?action=add" class="button-primary add-new-button">
                        <i class="fas fa-plus-circle"></i> Add New Doctor
                    </a>
                    </div>
                <section class="list-section">
                    <h2>Existing Doctors (<?= count($doctors_list); ?>)</h2>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Specialization</th>
                                    <th>Fee (৳)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($doctors_list)): ?>
                                    <?php foreach ($doctors_list as $doctor): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($doctor['id']); ?></td>
                                            <td>Dr. <?= htmlspecialchars($doctor['name']); ?></td>
                                            <td><?= htmlspecialchars($doctor['email']); ?></td>
                                            <td><?= htmlspecialchars($doctor['phone']); ?></td>
                                            <td><?= htmlspecialchars($doctor['specialization_name'] ?? 'N/A'); ?></td>
                                            <td><?= isset($doctor['consultation_fee']) ? number_format($doctor['consultation_fee'],2) : 'N/A'; ?></td>
                                            <td>
                                                <span class="status-badge <?= $doctor['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?= $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="actions-cell">
                                                <a href="admin_manage_doctors.php?action=edit&id=<?= $doctor['id']; ?>" class="action-link edit-link" title="Edit Doctor">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="admin_manage_doctors.php?action=toggle_status&id=<?= $doctor['id']; ?>&status=<?= $doctor['is_active']; ?>"
                                                   class="action-link status-link <?= $doctor['is_active'] ? 'deactivate-link' : 'activate-link'; ?>"
                                                   title="<?= $doctor['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                   onclick="return confirm('Are you sure you want to <?= $doctor['is_active'] ? 'deactivate' : 'activate'; ?> this doctor?');">
                                                    <i class="fas <?= $doctor['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                                </a>
                                                </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="no-data-message">No doctors found. Please add a new doctor.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer class="dashboard-footer-main" style="margin-top: 30px;"> <p>&copy; <?= date("Y"); ?> ABC Medical Admin Panel. All Rights Reserved.</p>
        </footer>
    </div>
    <?php $conn->close(); ?>
</body>
</html>