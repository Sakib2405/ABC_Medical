<?php
session_start();
$page_title = "Admin - Site Settings";

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

// --- 3. Define Manageable Setting Keys ---
$setting_keys = [
    'site_name' => ['label' => 'Site Name / Clinic Title', 'type' => 'text', 'default' => 'My Clinic'],
    'clinic_phone' => ['label' => 'Main Clinic Phone Number', 'type' => 'tel', 'default' => ''],
    'clinic_email' => ['label' => 'Main Clinic Email Address', 'type' => 'email', 'default' => ''],
    'clinic_address' => ['label' => 'Clinic Address', 'type' => 'textarea', 'default' => ''],
    'operating_hours' => ['label' => 'Operating Hours', 'type' => 'textarea', 'default' => 'Sat - Thu: 9 AM - 8 PM\nFri: Closed'],
    'default_currency' => ['label' => 'Default Currency Code', 'type' => 'text', 'default' => 'BDT'],
    // Example for a boolean setting (like maintenance mode)
    // 'maintenance_mode' => ['label' => 'Enable Maintenance Mode', 'type' => 'checkbox', 'default' => '0']
];

// --- 4. Fetch Current Settings ---
$current_settings = [];
$sql_fetch_settings = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN (";
$placeholders = implode(',', array_fill(0, count(array_keys($setting_keys)), '?'));
$sql_fetch_settings .= $placeholders . ")";

$stmt_fetch = $conn->prepare($sql_fetch_settings);
if ($stmt_fetch) {
    $types = str_repeat('s', count(array_keys($setting_keys)));
    $stmt_fetch->bind_param($types, ...array_keys($setting_keys));
    $stmt_fetch->execute();
    $result_settings = $stmt_fetch->get_result();
    while ($row = $result_settings->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    $stmt_fetch->close();
}

// Populate with defaults if not found in DB
foreach ($setting_keys as $key => $details) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $details['default'];
    }
}


// --- 5. Handle Form Submission to Update Settings ---
$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IMPORTANT: Add CSRF token check here in a real application
    // if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) { die("Invalid CSRF token"); }

    $conn->begin_transaction();
    $all_updates_successful = true;

    try {
        $sql_update = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt_update = $conn->prepare($sql_update);

        if (!$stmt_update) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }

        foreach ($setting_keys as $key => $details) {
            $value_to_save = '';
            if ($details['type'] === 'checkbox') {
                $value_to_save = isset($_POST[$key]) ? '1' : '0';
            } else {
                $value_to_save = trim($_POST[$key] ?? $details['default']);
            }

            // Basic validation (can be expanded)
            if ($key === 'clinic_email' && !empty($value_to_save) && !filter_var($value_to_save, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format for " . htmlspecialchars($details['label']) . ".");
            }

            $stmt_update->bind_param("ss", $key, $value_to_save);
            if (!$stmt_update->execute()) {
                throw new Exception("Error updating setting '" . htmlspecialchars($key) . "': " . $stmt_update->error);
            }
            $current_settings[$key] = $value_to_save; // Update for immediate display
        }
        $stmt_update->close();
        $conn->commit();
        $feedback_message = "Site settings updated successfully!";
        $feedback_type = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $feedback_message = "Error updating settings: " . $e->getMessage();
        $feedback_type = 'error';
    }
}

// Generate CSRF token (simplified for example)
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
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="admin_site_settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container settings-container">
        <header class="admin-page-header">
            <h1><i class="fas fa-cog"></i> Site Configuration Settings</h1>
            <p>Manage global settings for your clinic application.</p>
        </header>

        <?php if ($feedback_message): ?>
            <div class="message-feedback <?= $feedback_type === 'success' ? 'success' : 'error'; ?>">
                <?= htmlspecialchars($feedback_message); ?>
            </div>
        <?php endif; ?>

        <form action="admin_site_settings.php" method="POST" class="settings-form">
            <?php foreach ($setting_keys as $key => $details): ?>
                <div class="form-group">
                    <label for="<?= htmlspecialchars($key); ?>"><?= htmlspecialchars($details['label']); ?>:</label>
                    <?php if ($details['type'] === 'textarea'): ?>
                        <textarea id="<?= htmlspecialchars($key); ?>" name="<?= htmlspecialchars($key); ?>" rows="4"><?= htmlspecialchars($current_settings[$key]); ?></textarea>
                    <?php elseif ($details['type'] === 'checkbox'): ?>
                        <div class="checkbox-wrapper">
                             <input type="checkbox" id="<?= htmlspecialchars($key); ?>" name="<?= htmlspecialchars($key); ?>" value="1" <?= ($current_settings[$key] == '1') ? 'checked' : ''; ?>>
                             <span>Enable this setting</span>
                        </div>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($details['type']); ?>" id="<?= htmlspecialchars($key); ?>" name="<?= htmlspecialchars($key); ?>" value="<?= htmlspecialchars($current_settings[$key]); ?>">
                    <?php endif; ?>
                    <?php if ($key === 'default_currency'): ?>
                        <small>e.g., BDT, USD, EUR. This is for display purposes.</small>
                    <?php elseif ($key === 'operating_hours'): ?>
                        <small>Enter each day/time range on a new line.</small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="button-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <a href="admin_dashboard.php" class="button-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</body>
</html>