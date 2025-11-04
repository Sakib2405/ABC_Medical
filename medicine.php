<?php
session_start();
$page_title = "Admin - Manage Medicines";

// --- 1. Admin Authentication Check ---
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- Initialize variables ---
$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

$edit_mode = false;
$medicine_id_to_edit = null;
// Form fields
$name = '';
$generic_name = '';
$manufacturer = '';
$strength = '';
$form_type = ''; // 'form' is a keyword, using 'form_type'
$stock_quantity = 0;
$unit_price = 0.00;
$expiry_date = '';
$location_in_pharmacy = '';
$is_active = true;

$dhaka_tz = new DateTimeZone('Asia/Dhaka');
$today_date = (new DateTime('now', $dhaka_tz))->format('Y-m-d');


// --- Handle Actions (Add, Update, Delete) ---

// -- Handle Delete --
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $medicine_id_to_delete = (int)$_GET['id'];
    // CSRF token check would go here
    $stmt_delete = $conn->prepare("DELETE FROM medicines WHERE id = ?");
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $medicine_id_to_delete);
        if ($stmt_delete->execute()) {
            $feedback_message = "Medicine deleted successfully.";
            $feedback_type = 'success';
        } else {
            $feedback_message = "Error deleting medicine: " . $stmt_delete->error;
            $feedback_type = 'error';
        }
        $stmt_delete->close();
    } else {
        $feedback_message = "Error preparing delete statement: " . $conn->error;
        $feedback_type = 'error';
    }
    header("Location: medicine.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
    exit;
}

// -- Handle Add/Update Form Submission --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check
    $medicine_id_to_edit = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : null;
    $name = trim($_POST['name']);
    $generic_name = trim($_POST['generic_name']);
    $manufacturer = trim($_POST['manufacturer']);
    $strength = trim($_POST['strength']);
    $form_type = trim($_POST['form_type']);
    $stock_quantity = (int)$_POST['stock_quantity'];
    $unit_price = (float)$_POST['unit_price'];
    $expiry_date = trim($_POST['expiry_date']);
    $location_in_pharmacy = trim($_POST['location_in_pharmacy']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if (empty($name)) $errors[] = "Medicine name is required.";
    if ($stock_quantity < 0) $errors[] = "Stock quantity cannot be negative.";
    if ($unit_price < 0) $errors[] = "Unit price cannot be negative.";
    if (empty($expiry_date)) {
        $errors[] = "Expiry date is required.";
    } else {
        $chosen_expiry_date = new DateTime($expiry_date, $dhaka_tz);
        $today_for_check = new DateTime('now', $dhaka_tz);
        if ($chosen_expiry_date < $today_for_check->setTime(0,0,0) && !$medicine_id_to_edit) { // Only for new entries, existing might be expired
             // $errors[] = "Expiry date cannot be in the past for new stock."; // Could be relaxed for existing entries
        }
    }


    if (empty($errors)) {
        if ($medicine_id_to_edit) { // Update
            $sql = "UPDATE medicines SET name = ?, generic_name = ?, manufacturer = ?, strength = ?, form = ?, stock_quantity = ?, unit_price = ?, expiry_date = ?, location_in_pharmacy = ?, is_active = ? WHERE id = ?";
            $stmt_action = $conn->prepare($sql);
            if ($stmt_action) {
                $stmt_action->bind_param("sssssidsisi", $name, $generic_name, $manufacturer, $strength, $form_type, $stock_quantity, $unit_price, $expiry_date, $location_in_pharmacy, $is_active, $medicine_id_to_edit);
                if ($stmt_action->execute()) {
                    $feedback_message = "Medicine updated successfully.";
                    $feedback_type = 'success';
                } else {
                    $feedback_message = "Error updating medicine: " . $stmt_action->error;
                    $feedback_type = 'error';
                }
            }
        } else { // Add
            $sql = "INSERT INTO medicines (name, generic_name, manufacturer, strength, form, stock_quantity, unit_price, expiry_date, location_in_pharmacy, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_action = $conn->prepare($sql);
            if ($stmt_action) {
                $stmt_action->bind_param("sssssidsis", $name, $generic_name, $manufacturer, $strength, $form_type, $stock_quantity, $unit_price, $expiry_date, $location_in_pharmacy, $is_active);
                if ($stmt_action->execute()) {
                    $feedback_message = "Medicine added successfully.";
                    $feedback_type = 'success';
                    // Clear form fields for next entry
                    $name = $generic_name = $manufacturer = $strength = $form_type = $expiry_date = $location_in_pharmacy = '';
                    $stock_quantity = 0; $unit_price = 0.00; $is_active = true;
                } else {
                    $feedback_message = "Error adding medicine: " . $stmt_action->error;
                    $feedback_type = 'error';
                }
            }
        }
        if (isset($stmt_action) && !$stmt_action) { // Check if $stmt_action failed to prepare
            $feedback_message = "Error preparing statement: " . $conn->error;
            $feedback_type = 'error';
        } elseif(isset($stmt_action)) {
             $stmt_action->close();
        }

    } else { // Validation errors occurred
        $feedback_message = "Please correct the following errors:<ul>";
        foreach ($errors as $error) {
            $feedback_message .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $feedback_message .= "</ul>";
        $feedback_type = 'error';
    }
    if ($medicine_id_to_edit && $feedback_type === 'success') {
        header("Location: medicine.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
        exit;
    }
}


// -- Handle Edit Mode (Populate form for editing) --
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $medicine_id_to_edit = (int)$_GET['id'];
    $stmt_edit_fetch = $conn->prepare("SELECT * FROM medicines WHERE id = ?");
    if ($stmt_edit_fetch) {
        $stmt_edit_fetch->bind_param("i", $medicine_id_to_edit);
        $stmt_edit_fetch->execute();
        $result_edit = $stmt_edit_fetch->get_result();
        if ($med_to_edit = $result_edit->fetch_assoc()) {
            $name = $med_to_edit['name'];
            $generic_name = $med_to_edit['generic_name'];
            $manufacturer = $med_to_edit['manufacturer'];
            $strength = $med_to_edit['strength'];
            $form_type = $med_to_edit['form'];
            $stock_quantity = $med_to_edit['stock_quantity'];
            $unit_price = $med_to_edit['unit_price'];
            $expiry_date = $med_to_edit['expiry_date'];
            $location_in_pharmacy = $med_to_edit['location_in_pharmacy'];
            $is_active = (bool)$med_to_edit['is_active'];
        } else {
            $feedback_message = "Medicine not found for editing."; $feedback_type = 'error'; $edit_mode = false;
        }
        $stmt_edit_fetch->close();
    } else {
        $feedback_message = "Error preparing to fetch medicine: " . $conn->error; $feedback_type = 'error';
    }
}

// Display feedback from GET (after redirect)
if (isset($_GET['feedback']) && isset($_GET['type'])) {
    $feedback_message = htmlspecialchars(urldecode($_GET['feedback']));
    $feedback_type = htmlspecialchars($_GET['type']);
}

// --- Fetch All Medicines for Display ---
$medicines_list = [];
$sql_medicines = "SELECT id, name, generic_name, manufacturer, strength, form, stock_quantity, unit_price, expiry_date, is_active FROM medicines ORDER BY name ASC";
$result_medicines = $conn->query($sql_medicines);
if ($result_medicines && $result_medicines->num_rows > 0) {
    while ($row = $result_medicines->fetch_assoc()) {
        $medicines_list[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="medicine.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container">
        <header class="admin-page-header">
            <h1><i class="fas fa-pills"></i> Manage Medicines</h1>
            <p>Add, edit, or remove medicines from the clinic inventory.</p>
        </header>

        <?php if ($feedback_message): ?>
            <div class="message-feedback <?= $feedback_type === 'success' ? 'success' : 'error'; ?>">
                <?= $feedback_message; // HTML is pre-formatted for error lists ?>
            </div>
        <?php endif; ?>

        <section class="form-section <?= $edit_mode ? 'edit-mode-active' : '' ?>">
            <h2><?= $edit_mode ? 'Edit Medicine Details' : 'Add New Medicine'; ?></h2>
            <form action="medicine.php<?= $edit_mode ? '?action=edit&id='.$medicine_id_to_edit : '' ?>" method="POST" class="medicine-form">
                <?php if ($edit_mode && $medicine_id_to_edit): ?>
                    <input type="hidden" name="medicine_id" value="<?= htmlspecialchars($medicine_id_to_edit); ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Medicine Name (Brand) <span class="required-star">*</span></label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="generic_name">Generic Name</label>
                        <input type="text" id="generic_name" name="generic_name" value="<?= htmlspecialchars($generic_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="manufacturer">Manufacturer</label>
                        <input type="text" id="manufacturer" name="manufacturer" value="<?= htmlspecialchars($manufacturer); ?>">
                    </div>
                    <div class="form-group">
                        <label for="strength">Strength (e.g., 500mg, 10ml)</label>
                        <input type="text" id="strength" name="strength" value="<?= htmlspecialchars($strength); ?>">
                    </div>
                    <div class="form-group">
                        <label for="form_type">Form (e.g., Tablet, Syrup)</label>
                        <input type="text" id="form_type" name="form_type" value="<?= htmlspecialchars($form_type); ?>">
                    </div>
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity <span class="required-star">*</span></label>
                        <input type="number" id="stock_quantity" name="stock_quantity" value="<?= htmlspecialchars($stock_quantity); ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="unit_price">Unit Price (BDT) <span class="required-star">*</span></label>
                        <input type="number" id="unit_price" name="unit_price" value="<?= htmlspecialchars(number_format($unit_price, 2, '.', '')); ?>" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date <span class="required-star">*</span></label>
                        <input type="date" id="expiry_date" name="expiry_date" value="<?= htmlspecialchars($expiry_date); ?>" min="<?= $today_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="location_in_pharmacy">Location in Pharmacy</label>
                        <input type="text" id="location_in_pharmacy" name="location_in_pharmacy" value="<?= htmlspecialchars($location_in_pharmacy); ?>">
                    </div>
                    <div class="form-group form-group-checkbox">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $is_active ? 'checked' : ''; ?>>
                        <label for="is_active" class="checkbox-label">Medicine is Active/Available</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-primary">
                        <i class="fas <?= $edit_mode ? 'fa-save' : 'fa-plus-circle'; ?>"></i> <?= $edit_mode ? 'Update Medicine' : 'Add Medicine'; ?>
                    </button>
                    <?php if ($edit_mode): ?>
                        <a href="medicine.php" class="button-secondary"><i class="fas fa-times-circle"></i> Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="list-section">
            <h2>Current Medicine Inventory</h2>
            <?php if (!empty($medicines_list)): ?>
                <div class="table-responsive">
                <table class="data-table medicines-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Generic Name</th>
                            <th>Manufacturer</th>
                            <th>Strength</th>
                            <th>Form</th>
                            <th>Stock</th>
                            <th>Price (BDT)</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicines_list as $medicine):
                            $is_expired = (!empty($medicine['expiry_date']) && $medicine['expiry_date'] < $today_date);
                            $is_low_stock = ($medicine['stock_quantity'] < 10 && $medicine['stock_quantity'] > 0); // Example low stock threshold
                        ?>
                            <tr class="<?= $is_expired ? 'expired-item' : ($is_low_stock ? 'low-stock-item' : '') ?>">
                                <td><?= htmlspecialchars($medicine['id']); ?></td>
                                <td><?= htmlspecialchars($medicine['name']); ?></td>
                                <td><?= htmlspecialchars($medicine['generic_name'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($medicine['manufacturer'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($medicine['strength'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($medicine['form'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($medicine['stock_quantity']); ?></td>
                                <td><?= htmlspecialchars(number_format($medicine['unit_price'], 2)); ?></td>
                                <td><?= htmlspecialchars(!empty($medicine['expiry_date']) ? (new DateTime($medicine['expiry_date']))->format('M Y') : 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge <?= $medicine['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?= $medicine['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <a href="medicine.php?action=edit&id=<?= $medicine['id']; ?>" class="action-button edit-button" title="Edit Medicine">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="medicine.php?action=delete&id=<?= $medicine['id']; ?>"
                                       class="action-button delete-button" title="Delete Medicine"
                                       onclick="return confirm('Are you sure you want to delete this medicine: <?= htmlspecialchars(addslashes($medicine['name'])); ?>? This cannot be undone.');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <p class="no-data-message">No medicines found in the inventory. Add new medicines using the form above.</p>
            <?php endif; ?>
        </section>
        <p style="text-align:center; margin-top:30px;">
            <a href="admin_dashboard.php" class="button-secondary"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
        </p>
    </div>
    <script type='text/javascript' src='//pl27022957.profitableratecpm.com/df/f6/6f/dff66f651ce6a7255f2a34b68a269ff8.js'></script>
https://www.profitableratecpm.com/yjv9z6i5?key=bdbf3f2cfdbeb88da28d9927dd0361ad
<div id="container-518b5bf3a8f610d01ac4771c391ef67d"></div>
</body>
</html>