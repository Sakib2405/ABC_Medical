<?php
// --- Enable error reporting for debugging (REMOVE FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$page_title = "Manage Medicines - ABC Medical Admin";

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
    error_log("Admin Manage Medicines - DB Connection Error: " . $conn->connect_error);
    die("DATABASE CONNECTION FAILED. (Err: ADM_MM_DB_CONN)");
}
$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Dhaka');

// --- Configuration ---
define('UPLOAD_DIR_MEDICINES', 'uploads/medicine_images/');

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

// Fetch medicine categories for dropdowns
$categories = [];
$result_cat = $conn->query("SELECT id, name FROM medicine_categories ORDER BY name ASC");
if ($result_cat) {
    while ($row_cat = $result_cat->fetch_assoc()) {
        $categories[] = $row_cat;
    }
    $result_cat->free();
}

// --- Handle Actions ---
$action = $_GET['action'] ?? 'list';
$medicine_id_to_edit = null;
$medicine_to_edit_data = null;

// --- ADD OR UPDATE MEDICINE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_medicine']) || isset($_POST['update_medicine']))) {
    $name = sanitize_input($_POST['name'], $conn);
    $generic_name = sanitize_input($_POST['generic_name'], $conn);
    $manufacturer = sanitize_input($_POST['manufacturer'], $conn);
    $strength = sanitize_input($_POST['strength'], $conn);
    $form = sanitize_input($_POST['form'], $conn);
    $unit_price = filter_var($_POST['unit_price'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $stock_quantity = filter_var($_POST['stock_quantity'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0;
    $description = sanitize_input($_POST['description'], $conn); // Textarea
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $expiry_date_str = sanitize_input($_POST['expiry_date']);
    $expiry_date = !empty($expiry_date_str) ? date('Y-m-d', strtotime($expiry_date_str)) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $current_image_url = $_POST['current_image_url'] ?? null;
    $image_url_final = $current_image_url;

    // Basic Validations
    if (empty($name) || $unit_price === null || $stock_quantity === null) {
        $feedback_message = "Medicine Name, Unit Price, and Stock Quantity are required.";
        $feedback_type = 'error';
    } else {
        // Handle file upload
        if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] == UPLOAD_ERR_OK) {
            if (!is_dir(UPLOAD_DIR_MEDICINES)) @mkdir(UPLOAD_DIR_MEDICINES, 0755, true);
            
            $image_name = uniqid('med_', true) . '_' . basename($_FILES['image_url']['name']);
            $target_file = UPLOAD_DIR_MEDICINES . $image_name;
            $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($image_file_type, $allowed_types) && $_FILES['image_url']['size'] <= 2000000) { // Max 2MB
                if (move_uploaded_file($_FILES['image_url']['tmp_name'], $target_file)) {
                    $image_url_final = $target_file;
                    if (isset($_POST['update_medicine']) && $current_image_url && $current_image_url !== $image_url_final && file_exists($current_image_url)) {
                        @unlink($current_image_url);
                    }
                } else {
                    $feedback_message = "Error uploading medicine image."; $feedback_type = 'error';
                }
            } else {
                $feedback_message = "Invalid image type/size (Max 2MB; JPG, PNG, GIF, WEBP)."; $feedback_type = 'error';
            }
        } elseif (isset($_FILES['image_url']) && $_FILES['image_url']['error'] != UPLOAD_ERR_NO_FILE) {
            $feedback_message = "File upload error: " . $_FILES['image_url']['error']; $feedback_type = 'error';
        }

        if ($feedback_type !== 'error') {
            if (isset($_POST['add_medicine'])) {
                $sql = "INSERT INTO medicines (name, generic_name, manufacturer, strength, form, unit_price, stock_quantity, requires_prescription, description, image_url, category_id, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssdiissssi", $name, $generic_name, $manufacturer, $strength, $form, $unit_price, $stock_quantity, $requires_prescription, $description, $image_url_final, $category_id, $expiry_date, $is_active);
            } elseif (isset($_POST['update_medicine'])) {
                $medicine_id_to_update_post = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
                if (!$medicine_id_to_update_post) {
                    $feedback_message = "Invalid Medicine ID for update."; $feedback_type = 'error';
                } else {
                    $sql = "UPDATE medicines SET name=?, generic_name=?, manufacturer=?, strength=?, form=?, unit_price=?, stock_quantity=?, requires_prescription=?, description=?, image_url=?, category_id=?, expiry_date=?, is_active=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssdiissssii", $name, $generic_name, $manufacturer, $strength, $form, $unit_price, $stock_quantity, $requires_prescription, $description, $image_url_final, $category_id, $expiry_date, $is_active, $medicine_id_to_update_post);
                }
            }

            if (isset($stmt) && $stmt->execute()) {
                $feedback_message = "Medicine " . (isset($_POST['add_medicine']) ? "added" : "updated") . " successfully.";
                $feedback_type = 'success';
                $action = 'list';
            } elseif(isset($stmt)) {
                $feedback_message = "Database Error: " . $stmt->error; $feedback_type = 'error';
            }
            if(isset($stmt)) $stmt->close();
        }
    }
    // Retain form values on error
    if ($feedback_type === 'error' && (isset($_POST['add_medicine']) || isset($_POST['update_medicine']))) {
        $action = isset($_POST['add_medicine']) ? 'add' : 'edit';
        $medicine_to_edit_data = $_POST; // Repopulate form
        if (isset($_POST['update_medicine'])) {
            $medicine_id_to_edit = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
            $medicine_to_edit_data['image_url'] = $current_image_url; // Keep old image if new one failed
        }
    }
}


// --- TOGGLE ACTIVE STATUS ---
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $medicine_id_toggle = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $current_med_status = filter_var($_GET['status'], FILTER_VALIDATE_INT);

    if ($medicine_id_toggle && ($current_med_status === 0 || $current_med_status === 1)) {
        $new_med_status = $current_med_status == 1 ? 0 : 1;
        $stmt_toggle = $conn->prepare("UPDATE medicines SET is_active = ? WHERE id = ?");
        $stmt_toggle->bind_param("ii", $new_med_status, $medicine_id_toggle);
        if ($stmt_toggle->execute()) {
            $feedback_message = "Medicine status updated."; $feedback_type = 'success';
        } else {
            $feedback_message = "Error updating status: " . $stmt_toggle->error; $feedback_type = 'error';
        }
        $stmt_toggle->close();
    } else {
        $feedback_message = "Invalid params for status toggle."; $feedback_type = 'error';
    }
    $action = 'list';
}

// --- PREPARE DATA FOR EDIT FORM ---
if ($action === 'edit' && isset($_GET['id']) && $feedback_type !== 'error') {
    $medicine_id_to_edit = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($medicine_id_to_edit) {
        $stmt_edit = $conn->prepare("SELECT * FROM medicines WHERE id = ?");
        $stmt_edit->bind_param("i", $medicine_id_to_edit);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $medicine_to_edit_data = $result_edit->fetch_assoc();
        } else {
            $feedback_message = "Medicine not found for editing."; $feedback_type = 'error'; $action = 'list';
        }
        $stmt_edit->close();
    } else {
        $feedback_message = "Invalid Medicine ID for editing."; $feedback_type = 'error'; $action = 'list';
    }
}

// --- FETCH ALL MEDICINES FOR LISTING (Default Action) ---
$medicines_list = [];
if ($action === 'list') {
    $sql_med_list = "SELECT m.id, m.name, m.generic_name, m.manufacturer, m.unit_price, m.stock_quantity, m.is_active, mc.name as category_name
                     FROM medicines m
                     LEFT JOIN medicine_categories mc ON m.category_id = mc.id
                     ORDER BY m.name ASC";
    $result_med_list = $conn->query($sql_med_list);
    if ($result_med_list) {
        while ($row = $result_med_list->fetch_assoc()) {
            $medicines_list[] = $row;
        }
        $result_med_list->free();
    } else {
        $feedback_message = "Error fetching medicines: " . $conn->error; $feedback_type = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin_dashboard.css"> <link rel="stylesheet" href="admin_manage_medicines.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container">
        <header class="dashboard-header-main" style="margin-bottom: 20px;">
             <div class="header-content">
                <h1><i class="fas fa-pills"></i> Manage Medicines</h1>
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
                $form_action_title = ($action === 'add') ? "Add New Medicine" : "Edit Medicine Details";
                $form_button_text = ($action === 'add') ? "Add Medicine" : "Update Medicine";
                $form_post_action = ($action === 'add') ? "add_medicine" : "update_medicine";
                $current_data = $medicine_to_edit_data ?? [];
                ?>
                <section class="form-section medicine-form-section">
                    <h2><?= $form_action_title; ?></h2>
                    <form action="admin_manage_medicines.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="<?= $form_post_action; ?>" value="1">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="medicine_id" value="<?= htmlspecialchars($current_data['id'] ?? ''); ?>">
                            <input type="hidden" name="current_image_url" value="<?= htmlspecialchars($current_data['image_url'] ?? ''); ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Medicine Name (Brand) <span class="required">*</span></label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($current_data['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="generic_name">Generic Name</label>
                                <input type="text" id="generic_name" name="generic_name" value="<?= htmlspecialchars($current_data['generic_name'] ?? ''); ?>">
                            </div>
                             <div class="form-group">
                                <label for="manufacturer">Manufacturer</label>
                                <input type="text" id="manufacturer" name="manufacturer" value="<?= htmlspecialchars($current_data['manufacturer'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="strength">Strength (e.g., 500mg)</label>
                                <input type="text" id="strength" name="strength" value="<?= htmlspecialchars($current_data['strength'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="form">Form (e.g., Tablet, Syrup)</label>
                                <input type="text" id="form" name="form" value="<?= htmlspecialchars($current_data['form'] ?? ''); ?>">
                            </div>
                             <div class="form-group">
                                <label for="category_id">Category</label>
                                <select name="category_id" id="category_id">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id']; ?>" <?= (isset($current_data['category_id']) && $current_data['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="unit_price">Unit Price (BDT) <span class="required">*</span></label>
                                <input type="number" step="0.01" id="unit_price" name="unit_price" value="<?= htmlspecialchars($current_data['unit_price'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="stock_quantity">Stock Quantity <span class="required">*</span></label>
                                <input type="number" id="stock_quantity" name="stock_quantity" value="<?= htmlspecialchars($current_data['stock_quantity'] ?? '0'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" id="expiry_date" name="expiry_date" value="<?= htmlspecialchars($current_data['expiry_date'] ?? ''); ?>">
                            </div>
                            <div class="form-group form-group-full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($current_data['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="image_url">Medicine Image (Max 2MB: JPG, PNG, GIF, WEBP)</label>
                                <input type="file" id="image_url" name="image_url" accept="image/*">
                                <?php if ($action === 'edit' && !empty($current_data['image_url'])): ?>
                                    <?php if(file_exists($current_data['image_url'])): ?>
                                    <p class="current-image-notice">Current: <img src="<?= htmlspecialchars($current_data['image_url']); ?>" alt="Current Image" style="max-height: 50px; vertical-align: middle; margin-left:10px;"></p>
                                    <?php else: ?>
                                    <p class="current-image-notice">Current path: <?= htmlspecialchars($current_data['image_url']); ?> (File not found)</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                             <div class="form-group form-group-checkbox">
                                <label for="requires_prescription">
                                    <input type="checkbox" id="requires_prescription" name="requires_prescription" value="1" <?= (isset($current_data['requires_prescription']) && $current_data['requires_prescription'] == 1) ? 'checked' : ''; ?>>
                                    Requires Prescription (Rx)
                                </label>
                            </div>
                            <div class="form-group form-group-checkbox">
                                <label for="is_active">
                                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= (isset($current_data['is_active']) && $current_data['is_active'] == 1) ? 'checked' : ($action === 'add' ? 'checked' : ''); ?>>
                                    Active (Listed for Sale)
                                </label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="button-primary"><i class="fas fa-save"></i> <?= $form_button_text; ?></button>
                            <a href="admin_manage_medicines.php" class="button-secondary">Cancel</a>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="action-bar">
                    <a href="admin_manage_medicines.php?action=add" class="button-primary add-new-button">
                        <i class="fas fa-plus-circle"></i> Add New Medicine
                    </a>
                    </div>
                <section class="list-section">
                    <h2>Medicine Inventory (<?= count($medicines_list); ?>)</h2>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Generic</th>
                                    <th>Manufacturer</th>
                                    <th>Category</th>
                                    <th>Price (৳)</th>
                                    <th>Stock</th>
                                    <th>Rx?</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($medicines_list)): ?>
                                    <?php foreach ($medicines_list as $med): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($med['id']); ?></td>
                                            <td><?= htmlspecialchars($med['name']); ?></td>
                                            <td><?= htmlspecialchars($med['generic_name'] ?: '-'); ?></td>
                                            <td><?= htmlspecialchars($med['manufacturer'] ?: '-'); ?></td>
                                            <td><?= htmlspecialchars($med['category_name'] ?? 'N/A'); ?></td>
                                            <td><?= number_format($med['unit_price'], 2); ?></td>
                                            <td><?= htmlspecialchars($med['stock_quantity']); ?></td>
                                            <td><?= $med['requires_prescription'] ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <span class="status-badge <?= $med['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?= $med['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="actions-cell">
                                                <a href="admin_manage_medicines.php?action=edit&id=<?= $med['id']; ?>" class="action-link edit-link" title="Edit Medicine">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="admin_manage_medicines.php?action=toggle_status&id=<?= $med['id']; ?>&status=<?= $med['is_active']; ?>"
                                                   class="action-link status-link <?= $med['is_active'] ? 'deactivate-link' : 'activate-link'; ?>"
                                                   title="<?= $med['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                   onclick="return confirm('Are you sure you want to <?= $med['is_active'] ? 'deactivate' : 'activate'; ?> this medicine?');">
                                                    <i class="fas <?= $med['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="no-data-message">No medicines found. Please add new medicines.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer class="dashboard-footer-main" style="margin-top: 30px;">
            <p>&copy; <?= date("Y"); ?> ABC Medical Admin Panel. All Rights Reserved.</p>
        </footer>
    </div>
    <?php $conn->close(); ?>
</body>
</html>