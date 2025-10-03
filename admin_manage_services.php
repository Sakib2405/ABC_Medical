<?php
session_start();
$page_title = "Admin - Manage Services";

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// --- 2. Database Connection ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical'; // Your database name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- Initialize variables ---
$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

$edit_mode = false;
$service_id_to_edit = null;
$service_name = '';
$description = '';
$duration_minutes = 30; // Default duration
$is_active = true;


// --- 4. Handle Actions (Add, Update, Delete) ---

// -- Handle Delete --
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $service_id_to_delete = (int)$_GET['id'];
    // IMPORTANT: Add CSRF token check here in a real application
    // if (!verify_csrf_token($_GET['token'])) { die("Invalid CSRF token"); }

    // Optional: Check if service is linked to any appointments before deleting, or handle via DB constraints
    $stmt_delete = $conn->prepare("DELETE FROM services WHERE id = ?");
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $service_id_to_delete);
        if ($stmt_delete->execute()) {
            $feedback_message = "Service deleted successfully.";
            $feedback_type = 'success';
        } else {
            $feedback_message = "Error deleting service: " . $stmt_delete->error;
            $feedback_type = 'error';
        }
        $stmt_delete->close();
    } else {
        $feedback_message = "Error preparing delete statement: " . $conn->error;
        $feedback_type = 'error';
    }
    // Redirect to clean URL after action
    header("Location: admin_manage_services.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
    exit;
}


// -- Handle Add/Update Form Submission --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IMPORTANT: Add CSRF token check here
    // if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) { die("Invalid CSRF token"); }

    $service_id_to_edit = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);
    $duration_minutes = (int)$_POST['duration_minutes'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if (empty($service_name)) {
        $errors[] = "Service name is required.";
    }
    if ($duration_minutes <= 0) {
        $errors[] = "Duration must be a positive number.";
    }

    if (empty($errors)) {
        if ($service_id_to_edit) { // Update existing service
            $stmt_update = $conn->prepare("UPDATE services SET service_name = ?, description = ?, duration_minutes = ?, is_active = ? WHERE id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("ssiii", $service_name, $description, $duration_minutes, $is_active, $service_id_to_edit);
                if ($stmt_update->execute()) {
                    $feedback_message = "Service updated successfully.";
                    $feedback_type = 'success';
                } else {
                    $feedback_message = "Error updating service: " . $stmt_update->error;
                    $feedback_type = 'error';
                }
                $stmt_update->close();
            } else {
                 $feedback_message = "Error preparing update statement: " . $conn->error;
                 $feedback_type = 'error';
            }
        } else { // Add new service
            $stmt_add = $conn->prepare("INSERT INTO services (service_name, description, duration_minutes, is_active) VALUES (?, ?, ?, ?)");
            if ($stmt_add) {
                $stmt_add->bind_param("ssii", $service_name, $description, $duration_minutes, $is_active);
                if ($stmt_add->execute()) {
                    $feedback_message = "Service added successfully.";
                    $feedback_type = 'success';
                    // Clear form fields after successful add
                    $service_name = $description = ''; $duration_minutes = 30; $is_active = true;
                } else {
                    $feedback_message = "Error adding service: " . $stmt_add->error;
                    $feedback_type = 'error';
                }
                $stmt_add->close();
            } else {
                $feedback_message = "Error preparing add statement: " . $conn->error;
                $feedback_type = 'error';
            }
        }
    } else {
        $feedback_message = "Please correct the following errors:<ul>";
        foreach ($errors as $error) {
            $feedback_message .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $feedback_message .= "</ul>";
        $feedback_type = 'error';
    }
     if ($service_id_to_edit && $feedback_type === 'success') { // If update was successful, redirect to clear POST
        header("Location: admin_manage_services.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
        exit;
    }
}

// -- Handle Edit Mode (Populate form for editing) --
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $service_id_to_edit = (int)$_GET['id'];
    $stmt_edit_fetch = $conn->prepare("SELECT service_name, description, duration_minutes, is_active FROM services WHERE id = ?");
    if ($stmt_edit_fetch) {
        $stmt_edit_fetch->bind_param("i", $service_id_to_edit);
        $stmt_edit_fetch->execute();
        $result_edit = $stmt_edit_fetch->get_result();
        if ($service_to_edit = $result_edit->fetch_assoc()) {
            $service_name = $service_to_edit['service_name'];
            $description = $service_to_edit['description'];
            $duration_minutes = $service_to_edit['duration_minutes'];
            $is_active = (bool)$service_to_edit['is_active'];
        } else {
            $feedback_message = "Service not found for editing.";
            $feedback_type = 'error';
            $edit_mode = false; // Reset edit mode if service not found
        }
        $stmt_edit_fetch->close();
    } else {
        $feedback_message = "Error preparing to fetch service for edit: " . $conn->error;
        $feedback_type = 'error';
    }
}

// Display feedback messages from GET parameters (after redirect)
if (isset($_GET['feedback']) && isset($_GET['type'])) {
    $feedback_message = htmlspecialchars($_GET['feedback']);
    $feedback_type = htmlspecialchars($_GET['type']);
}


// --- 3. Fetch All Services for Display ---
$services_list = [];
$sql_services = "SELECT id, service_name, description, duration_minutes, is_active, created_at FROM services ORDER BY service_name ASC";
$result_services = $conn->query($sql_services);
if ($result_services && $result_services->num_rows > 0) {
    while ($row = $result_services->fetch_assoc()) {
        $services_list[] = $row;
    }
}

// Generate CSRF token
// if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
// $csrf_token = $_SESSION['csrf_token'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="admin_manage_services.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container">
        <header class="admin-page-header">
            <h1><i class="fas fa-concierge-bell"></i> Manage Clinic Services</h1>
            <p>Add, edit, or remove services offered by the clinic.</p>
        </header>

        <?php if ($feedback_message): ?>
            <div class="message-feedback <?= $feedback_type === 'success' ? 'success' : 'error'; ?>">
                <?= $feedback_message; // Already HTML formatted if it's a list of errors, or htmlspecialchars from GET ?>
            </div>
        <?php endif; ?>

        <section class="form-section <?= $edit_mode ? 'edit-mode-active' : '' ?>">
            <h2><?= $edit_mode ? 'Edit Service' : 'Add New Service'; ?></h2>
            <form action="admin_manage_services.php<?= $edit_mode ? '?action=edit&id='.$service_id_to_edit : '' ?>" method="POST" class="service-form">
                <?php if ($edit_mode && $service_id_to_edit): ?>
                    <input type="hidden" name="service_id" value="<?= htmlspecialchars($service_id_to_edit); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="service_name">Service Name <span class="required-star">*</span></label>
                    <input type="text" id="service_name" name="service_name" value="<?= htmlspecialchars($service_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?= htmlspecialchars($description); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="duration_minutes">Duration (minutes) <span class="required-star">*</span></label>
                    <input type="number" id="duration_minutes" name="duration_minutes" value="<?= htmlspecialchars($duration_minutes); ?>" min="5" step="5" required>
                </div>
                <div class="form-group form-group-checkbox">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= $is_active ? 'checked' : ''; ?>>
                    <label for="is_active">Service is Active</label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-primary">
                        <i class="fas <?= $edit_mode ? 'fa-save' : 'fa-plus-circle'; ?>"></i> <?= $edit_mode ? 'Update Service' : 'Add Service'; ?>
                    </button>
                    <?php if ($edit_mode): ?>
                        <a href="admin_manage_services.php" class="button-secondary"><i class="fas fa-times-circle"></i> Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="list-section">
            <h2>Existing Services</h2>
            <?php if (!empty($services_list)): ?>
                <table class="data-table services-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service Name</th>
                            <th>Description</th>
                            <th>Duration (min)</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services_list as $service): ?>
                            <tr>
                                <td><?= htmlspecialchars($service['id']); ?></td>
                                <td><?= htmlspecialchars($service['service_name']); ?></td>
                                <td class="description-cell"><?= nl2br(htmlspecialchars($service['description'])); ?></td>
                                <td><?= htmlspecialchars($service['duration_minutes']); ?></td>
                                <td>
                                    <span class="status-badge <?= $service['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?= $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((new DateTime($service['created_at']))->format('M j, Y')); ?></td>
                                <td class="actions-cell">
                                    <a href="admin_manage_services.php?action=edit&id=<?= $service['id']; ?>" class="action-button edit-button" title="Edit Service">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="admin_manage_services.php?action=delete&id=<?= $service['id']; ?>&amp;token=<?= '' /* htmlspecialchars($csrf_token); */ ?>"
                                       class="action-button delete-button" title="Delete Service"
                                       onclick="return confirm('Are you sure you want to delete this service? This action cannot be undone.');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data-message">No services found. Please add a new service using the form above.</p>
            <?php endif; ?>
        </section>
        <p style="text-align:center; margin-top:30px;">
            <a href="admin_dashboard.php" class="button-secondary"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
        </p>
    </div>
</body>
</html>