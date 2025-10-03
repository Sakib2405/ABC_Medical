<?php
// --- Enable error reporting for debugging (REMOVE FOR PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$page_title = "Manage Pharmacy Orders - ABC Medical Admin";

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
    error_log("Admin Manage Pharmacy Orders - DB Connection Error: " . $conn->connect_error);
    die("DATABASE CONNECTION FAILED. (Err: ADM_MPO_DB_CONN)");
}
$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Dhaka');

$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

// Possible statuses for dropdowns
$possible_order_statuses = ['Pending Confirmation', 'Pending Prescription', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'On Hold'];
$possible_rx_statuses = ['Pending Verification', 'Verified', 'Rejected', 'Not Required', 'Contact Customer'];


// --- Handle Status Updates ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $order_id_to_update = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);

    if (!$order_id_to_update) {
        $feedback_message = "Invalid Order ID for update.";
        $feedback_type = 'error';
    } else {
        if ($_POST['action'] == 'update_order_status') {
            $new_order_status = $conn->real_escape_string(trim($_POST['order_status']));
            if (in_array($new_order_status, $possible_order_statuses)) {
                $stmt_update = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $stmt_update->bind_param("si", $new_order_status, $order_id_to_update);
                if ($stmt_update->execute()) {
                    $feedback_message = "Order status for ID #{$order_id_to_update} updated to '{$new_order_status}'.";
                    $feedback_type = 'success';
                } else {
                    $feedback_message = "Error updating order status: " . $stmt_update->error; $feedback_type = 'error';
                }
                $stmt_update->close();
            } else {
                $feedback_message = "Invalid order status value."; $feedback_type = 'error';
            }
        } elseif ($_POST['action'] == 'update_rx_status') {
            $new_rx_status = $conn->real_escape_string(trim($_POST['prescription_status']));
            if (in_array($new_rx_status, $possible_rx_statuses)) {
                $stmt_update = $conn->prepare("UPDATE orders SET prescription_status = ? WHERE order_id = ?");
                $stmt_update->bind_param("si", $new_rx_status, $order_id_to_update);
                if ($stmt_update->execute()) {
                    $feedback_message = "Prescription status for Order ID #{$order_id_to_update} updated to '{$new_rx_status}'.";
                    $feedback_type = 'success';
                } else {
                    $feedback_message = "Error updating prescription status: " . $stmt_update->error; $feedback_type = 'error';
                }
                $stmt_update->close();
            } else {
                $feedback_message = "Invalid prescription status value."; $feedback_type = 'error';
            }
        }
    }
}


// --- Fetch Orders for Listing ---
$filter_order_status = isset($_GET['filter_order_status']) ? $conn->real_escape_string($_GET['filter_order_status']) : '';
$filter_rx_status = isset($_GET['filter_rx_status']) ? $conn->real_escape_string($_GET['filter_rx_status']) : '';

$sql_orders = "SELECT
                order_id, customer_name, customer_phone, order_date,
                total_amount, order_status, payment_method,
                prescription_image_url, prescription_status
              FROM orders ";

$conditions = [];
$params = [];
$types = "";

if (!empty($filter_order_status)) {
    $conditions[] = "order_status = ?";
    $params[] = $filter_order_status;
    $types .= "s";
}
if (!empty($filter_rx_status)) {
    $conditions[] = "prescription_status = ?";
    $params[] = $filter_rx_status;
    $types .= "s";
}

if (count($conditions) > 0) {
    $sql_orders .= " WHERE " . implode(" AND ", $conditions);
}
$sql_orders .= " ORDER BY order_date DESC";

$stmt_orders = $conn->prepare($sql_orders);
if ($stmt_orders) {
    if (!empty($types)) {
        $stmt_orders->bind_param($types, ...$params);
    }
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();
    $orders_list = [];
    while ($row = $result_orders->fetch_assoc()) {
        $orders_list[] = $row;
    }
    $stmt_orders->close();
} else {
    $feedback_message = "Error fetching orders: " . $conn->error;
    $feedback_type = 'error';
    $orders_list = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin_dashboard.css"> <link rel="stylesheet" href="admin_manage_pharmacy_orders.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-page-container">
        <header class="dashboard-header-main" style="margin-bottom: 20px;">
             <div class="header-content">
                <h1><i class="fas fa-dolly-flatbed"></i> Manage Pharmacy Orders</h1>
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

            <section class="filter-section">
                <form action="admin_manage_pharmacy_orders.php" method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="filter_order_status">Filter by Order Status:</label>
                        <select name="filter_order_status" id="filter_order_status">
                            <option value="">All Order Statuses</option>
                            <?php foreach ($possible_order_statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status); ?>" <?= ($filter_order_status === $status ? 'selected' : ''); ?>>
                                    <?= htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter_rx_status">Filter by Rx Status:</label>
                        <select name="filter_rx_status" id="filter_rx_status">
                            <option value="">All Rx Statuses</option>
                            <?php foreach ($possible_rx_statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status); ?>" <?= ($filter_rx_status === $status ? 'selected' : ''); ?>>
                                    <?= htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="filter-button"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="admin_manage_pharmacy_orders.php" class="filter-button clear-filters">Clear Filters</a>
                </form>
            </section>

            <section class="orders-list-section">
                <h2>Pharmacy Orders (<?= count($orders_list); ?>)</h2>
                <div class="table-responsive-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Date</th>
                                <th>Amount (৳)</th>
                                <th>Payment</th>
                                <th>Prescription</th>
                                <th>Rx Status</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders_list)): ?>
                                <?php foreach ($orders_list as $order): ?>
                                    <tr>
                                        <td data-label="Order ID">
                                            <a href="order_confirmation.php?order_id=<?= $order['order_id']; ?>&view=admin" title="View Order Details">
                                                #<?= htmlspecialchars($order['order_id']); ?>
                                            </a>
                                        </td>
                                        <td data-label="Customer"><?= htmlspecialchars($order['customer_name']); ?></td>
                                        <td data-label="Phone"><?= htmlspecialchars($order['customer_phone']); ?></td>
                                        <td data-label="Date"><?= htmlspecialchars(date("d M Y, h:i A", strtotime($order['order_date']))); ?></td>
                                        <td data-label="Amount (৳)"><?= htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                        <td data-label="Payment"><?= htmlspecialchars($order['payment_method']); ?></td>
                                        <td data-label="Prescription">
                                            <?php if(!empty($order['prescription_image_url'])): ?>
                                                <?php if (file_exists($order['prescription_image_url'])): ?>
                                                <a href="<?= htmlspecialchars($order['prescription_image_url']); ?>" target="_blank" class="view-rx-link" title="View Prescription">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php else: ?>
                                                <span>File Missing</span>
                                                <?php endif; ?>
                                            <?php else: echo 'N/A'; endif; ?>
                                        </td>
                                        <td data-label="Rx Status">
                                            <form action="admin_manage_pharmacy_orders.php<?= http_build_query($_GET) ? '?'.http_build_query($_GET) : '' ?>" method="POST" class="inline-update-form">
                                                <input type="hidden" name="action" value="update_rx_status">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id']; ?>">
                                                <select name="prescription_status" onchange="this.form.submit()">
                                                    <?php foreach ($possible_rx_statuses as $p_status): ?>
                                                        <option value="<?= htmlspecialchars($p_status); ?>" <?= ($order['prescription_status'] === $p_status ? 'selected' : ''); ?>>
                                                            <?= htmlspecialchars($p_status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <noscript><button type="submit"><i class="fas fa-save"></i></button></noscript>
                                            </form>
                                        </td>
                                        <td data-label="Order Status">
                                            <form action="admin_manage_pharmacy_orders.php<?= http_build_query($_GET) ? '?'.http_build_query($_GET) : '' ?>" method="POST" class="inline-update-form">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id']; ?>">
                                                <select name="order_status" onchange="this.form.submit()">
                                                    <?php foreach ($possible_order_statuses as $o_status): ?>
                                                        <option value="<?= htmlspecialchars($o_status); ?>" <?= ($order['order_status'] === $o_status ? 'selected' : ''); ?>>
                                                            <?= htmlspecialchars($o_status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                 <noscript><button type="submit"><i class="fas fa-save"></i></button></noscript>
                                            </form>
                                        </td>
                                        <td data-label="Actions" class="actions-cell">
                                            <a href="order_confirmation.php?order_id=<?= $order['order_id']; ?>&view=admin" class="action-link view-link" title="View Full Order Details">
                                                <i class="fas fa-search-plus"></i> Details
                                            </a>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-data-message">No pharmacy orders found matching the criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <footer class="dashboard-footer-main" style="margin-top: 30px;">
            <p>&copy; <?= date("Y"); ?> ABC Medical Admin Panel. All Rights Reserved.</p>
        </footer>
    </div>
    <?php $conn->close(); ?>
</body>
</html>