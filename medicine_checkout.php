<?php
session_start(); // Always start the session at the very beginning

$page_title = "Checkout - ABC Medical Pharmacy";

// --- Database Connection Details ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // In a production environment, log this error securely and show a generic message.
    die("Connection Failed: " . $conn->connect_error . " (DB_CONNECT_ERR)");
}
$conn->set_charset("utf8mb4");

// --- Initialize variables ---
$cart_items_details = [];
$cart_subtotal = 0;
$feedback_message = '';
$feedback_type = ''; // Default type for messages (info, success, error, warning)

// User data for pre-filling form
$customer_name_prefill = '';
$customer_phone_prefill = '';
$customer_email_prefill = '';
$is_user_logged_in = false; // Flag to check if user is logged in

// Process feedback from GET parameters (e.g., from buy_medicine.php)
if (isset($_GET['feedback']) && isset($_GET['type'])) {
    $feedback_message = htmlspecialchars(urldecode($_GET['feedback']));
    $feedback_type = htmlspecialchars($_GET['type']);
}

// --- Fetch Logged-in User Details for Pre-filling ---
if (isset($_SESSION['user_id'])) {
    $is_user_logged_in = true;
    $current_user_id = $_SESSION['user_id'];

    $stmt_user_info = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $current_user_id);
        $stmt_user_info->execute();
        $result_user_info = $stmt_user_info->get_result();
        if ($user_data = $result_user_info->fetch_assoc()) {
            $customer_name_prefill = htmlspecialchars($user_data['name']);
            $customer_phone_prefill = htmlspecialchars($user_data['phone']);
            $customer_email_prefill = htmlspecialchars($user_data['email']);
        }
        $stmt_user_info->close();
    }
}

// --- Initial Cart Check and Details Fetch ---
// This block runs on every page load to populate the summary and validate cart
if (empty($_SESSION['cart'])) {
    // Only redirect if there isn't already a feedback message about an empty cart
    if (!isset($_GET['feedback']) || (strpos(urldecode($_GET['feedback']), "cart is empty") === false && strpos(urldecode($_GET['feedback']), "stock issues") === false)) {
        header("Location: buy_medicine.php?feedback=" . urlencode("Your cart is empty. Please add medicines first.") . "&type=info");
        exit;
    }
}

// Fetch Cart Item Details & Calculate Subtotal (Pre-submission and on error)
if (!empty($_SESSION['cart'])) {
    $cart_medicine_ids = array_keys($_SESSION['cart']);
    // Filter out any non-numeric or zero IDs from the cart array for safety
    $filtered_cart_medicine_ids = array_filter($cart_medicine_ids, function($id) {
        return is_numeric($id) && (int)$id > 0;
    });

    // If after filtering, there are no valid IDs, then the cart is effectively empty
    if (empty($filtered_cart_medicine_ids)) {
        $_SESSION['cart'] = []; // Clear session cart of invalid entries
        header("Location: buy_medicine.php?feedback=" . urlencode("Your cart is empty or contains invalid items.") . "&type=info");
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($filtered_cart_medicine_ids), '?'));
    $types = str_repeat('i', count($filtered_cart_medicine_ids));

    $sql_cart_details = "SELECT id, name, unit_price, requires_prescription, stock_quantity FROM medicines WHERE id IN ($placeholders) AND is_active = TRUE";
    $stmt_cart = $conn->prepare($sql_cart_details);
    if ($stmt_cart) {
        call_user_func_array([$stmt_cart, 'bind_param'], array_merge([$types], $filtered_cart_medicine_ids));
        $stmt_cart->execute();
        $result_cart_details = $stmt_cart->get_result();
        
        $new_feedback_message = ''; // Collect new messages
        $new_feedback_type = '';

        while ($cart_row = $result_cart_details->fetch_assoc()) {
            $current_id = $cart_row['id'];
            $quantity_in_session = $_SESSION['cart'][$current_id];
            
            // --- Stock Adjustment/Validation for display ---
            if ($quantity_in_session > $cart_row['stock_quantity']) {
                $quantity_in_session = $cart_row['stock_quantity']; // Adjust quantity to max available stock
                $_SESSION['cart'][$current_id] = $quantity_in_session; // Update session cart
                $new_feedback_message .= htmlspecialchars($cart_row['name']) . " quantity adjusted to " . htmlspecialchars($quantity_in_session) . " due to stock limit.<br>";
                $new_feedback_type = 'warning';
            }
            
            // If adjusted quantity is 0 or less, or medicine is effectively out of stock
            if ($quantity_in_session <= 0 || $cart_row['stock_quantity'] <= 0) {
                if (isset($_SESSION['cart'][$current_id])) {
                    unset($_SESSION['cart'][$current_id]); // Remove from session cart
                    $new_feedback_message .= htmlspecialchars($cart_row['name']) . " removed from cart due to insufficient stock.<br>";
                    $new_feedback_type = 'warning';
                }
                continue; // Skip this item for display/calculation
            }

            $cart_items_details[] = [
                'id' => $current_id,
                'name' => $cart_row['name'],
                'unit_price' => $cart_row['unit_price'],
                'quantity' => $quantity_in_session,
                'total_price' => $cart_row['unit_price'] * $quantity_in_session,
                'requires_prescription' => $cart_row['requires_prescription'],
                'stock_quantity' => $cart_row['stock_quantity']
            ];
            $cart_subtotal += ($cart_row['unit_price'] * $quantity_in_session);
        }
        $stmt_cart->close();

        // Append new feedback messages to existing one if any
        if (!empty($new_feedback_message)) {
            $feedback_message .= $new_feedback_message;
            if ($feedback_type !== 'error') { // Don't downgrade error to warning
                $feedback_type = $new_feedback_type;
            }
        }

        // Re-check if cart became effectively empty after all adjustments
        if (empty($cart_items_details)) {
             header("Location: buy_medicine.php?feedback=" . urlencode("Your cart is now empty due to stock issues. Please review available items.") . "&type=error");
             exit;
        }
    } else {
        $feedback_message = "Database error fetching cart details.";
        $feedback_type = 'error';
    }
}


// --- Handle Form Submission (Place Order) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'place_order') {
    // Overwrite pre-fill values with POST data if form was submitted
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_email = trim($_POST['customer_email'] ?? '');
    $delivery_address = trim($_POST['delivery_address']);
    $payment_method = trim($_POST['payment_method'] ?? 'Cash on Delivery');
    $customer_notes = trim($_POST['customer_notes'] ?? '');

    // Reset feedback for new submission validation
    $feedback_message = '';
    $feedback_type = '';

    // Basic Validation
    if (empty($customer_name) || empty($customer_phone) || empty($delivery_address)) {
        $feedback_message .= "Please fill in all required fields: Full Name, Phone Number, and Delivery Address.<br>";
        $feedback_type = 'error';
    }
    if (!empty($customer_email) && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $feedback_message .= "Invalid email format.<br>";
        $feedback_type = 'error';
    }

    $order_status_initial = 'Pending Confirmation'; 

    // Proceed only if no validation errors so far
    if (empty($feedback_message) && empty($feedback_type)) { // Check both to be safe
        $conn->begin_transaction();
        try {
            // --- Final Cart & Stock Re-validation within transaction ---
            $current_cart_items_for_order = [];
            $current_cart_subtotal_for_order = 0;
            $can_proceed_with_order = true;

            if (empty($_SESSION['cart'])) {
                throw new Exception("Your cart is empty. Please add items before placing an order.");
            }

            foreach ($_SESSION['cart'] as $m_id => $qty_in_cart) {
                // Lock the row to prevent race conditions during checkout
                $stmt_check_stock = $conn->prepare("SELECT name, unit_price, stock_quantity FROM medicines WHERE id = ? AND is_active = TRUE FOR UPDATE");
                if (!$stmt_check_stock) {
                     throw new Exception("Database error preparing stock check statement.");
                }
                $stmt_check_stock->bind_param("i", $m_id);
                $stmt_check_stock->execute();
                $res_med = $stmt_check_stock->get_result()->fetch_assoc();
                $stmt_check_stock->close();

                if (!$res_med) {
                    throw new Exception("An item in your cart was not found or is inactive. Please review your cart.");
                }
                if ($qty_in_cart <= 0 || $qty_in_cart > $res_med['stock_quantity']) {
                    throw new Exception(htmlspecialchars($res_med['name']) . " is out of stock or requested quantity (" . htmlspecialchars($qty_in_cart) . ") not available (" . htmlspecialchars($res_med['stock_quantity']) . " in stock). Please review your cart.");
                }
                $current_cart_items_for_order[] = [
                    'id' => $m_id,
                    'name' => $res_med['name'],
                    'unit_price' => $res_med['unit_price'],
                    'quantity' => $qty_in_cart,
                    'total_price' => $res_med['unit_price'] * $qty_in_cart
                ];
                $current_cart_subtotal_for_order += ($res_med['unit_price'] * $qty_in_cart);
            }

            if (empty($current_cart_items_for_order)) { // Should not happen if $_SESSION['cart'] was checked earlier, but for safety
                 throw new Exception("Your cart is empty after final stock verification.");
            }

            // 1. Insert into 'orders' table
            $sql_insert_order = "INSERT INTO orders (customer_name, customer_phone, customer_email, delivery_address, total_amount, order_status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_order = $conn->prepare($sql_insert_order);
            if (!$stmt_order) {
                throw new Exception("Database error preparing order insert statement.");
            }
            $stmt_order->bind_param("ssssdsss", $customer_name, $customer_phone, $customer_email, $delivery_address, $current_cart_subtotal_for_order, $order_status_initial, $payment_method, $customer_notes);
            $stmt_order->execute();
            $new_order_id = $conn->insert_id;
            $stmt_order->close();

            if (!$new_order_id) {
                throw new Exception("Failed to create order. No order ID generated.");
            }

            // 2. Insert into 'order_items' table and Update 'medicines' stock
            $sql_insert_order_item = "INSERT INTO order_items (order_id, medicine_id, quantity_ordered, price_per_unit, total_price) VALUES (?, ?, ?, ?, ?)";
            $stmt_order_item = $conn->prepare($sql_insert_order_item);
            if (!$stmt_order_item) {
                throw new Exception("Database error preparing order item insert statement.");
            }

            $sql_update_stock = "UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?";
            $stmt_update_stock = $conn->prepare($sql_update_stock);
            if (!$stmt_update_stock) {
                throw new Exception("Database error preparing stock update statement.");
            }

            foreach ($current_cart_items_for_order as $item) {
                $stmt_order_item->bind_param("iiidd", $new_order_id, $item['id'], $item['quantity'], $item['unit_price'], $item['total_price']);
                $stmt_order_item->execute();
                if ($stmt_order_item->affected_rows === 0) {
                    throw new Exception("Failed to record order item for medicine ID " . htmlspecialchars($item['id']) . ".");
                }

                $stmt_update_stock->bind_param("ii", $item['quantity'], $item['id']);
                $stmt_update_stock->execute();
                if ($stmt_update_stock->affected_rows === 0) {
                    throw new Exception("Failed to update stock for medicine '" . htmlspecialchars($item['name']) . "' (possible concurrency issue or insufficient stock).");
                }
            }
            $stmt_order_item->close();
            $stmt_update_stock->close();

            $conn->commit(); // Commit the transaction
            $_SESSION['cart'] = []; // Clear the cart after successful order

            // Redirect to order_confirmation.php with order details
            $final_success_message = "Order placed successfully! Your Order ID is #<strong>" . htmlspecialchars($new_order_id) . "</strong>. We will contact you shortly for confirmation.";
            header("Location: order_confirmation.php?order_id=" . htmlspecialchars($new_order_id) . "&message=" . urlencode($final_success_message));
            exit;

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaction on any error
            $feedback_message = "Order placement failed: " . htmlspecialchars($e->getMessage());
            $feedback_type = 'error';
            // Re-direct to self to display feedback and retain POST data for re-submission attempts
            header("Location: medicine_checkout.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
            exit;
        }
    } else {
        // If there were validation errors before transaction, redirect to self to display them
        header("Location: medicine_checkout.php?feedback=" . urlencode($feedback_message) . "&type=" . $feedback_type);
        exit;
    }
}
$conn->close(); // Close the database connection
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
        /* General Body and Container Styles */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5; /* Light gray background */
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .checkout-page-container {
            max-width: 1100px; /* Adjusted max-width */
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 30px;
            box-sizing: border-box;
        }

        /* Header Styles */
        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .checkout-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.8rem;
            color: #007bff; /* Primary blue for header */
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .checkout-header h1 i {
            color: #007bff;
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
            line-height: 1.4;
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
        .message-feedback.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .message-feedback.warning { /* New warning style for stock adjustments */
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .message-feedback i {
            font-size: 1.2em;
            flex-shrink: 0;
        }


        /* Checkout Layout (Flexbox) */
        .checkout-layout {
            display: flex;
            gap: 40px; /* Space between sections */
        }

        .customer-details-section, .order-summary-section {
            background-color: #f8f9fa; /* Lighter background for sections */
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            flex: 1; /* Both sections take equal space */
        }

        .customer-details-section h2, .order-summary-section h2 {
            font-family: 'Montserrat', sans-serif;
            color: #2c3e50;
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .customer-details-section h2 i {
            color: #28a745; /* Green icon for customer info */
        }
        .order-summary-section h2 i {
            color: #007bff; /* Blue icon for summary */
        }

        /* Form Group Styling */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }
        .form-group .required {
            color: #dc3545; /* Red asterisk */
            margin-left: 4px;
        }
        .form-group input[type="text"],
        .form-group input[type="tel"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
        }
        .form-group input[readonly] { /* Style for readonly inputs */
            background-color: #e9ecef; /* Light gray background */
            cursor: not-allowed;
            opacity: 0.9;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        /* Payment Method */
        .payment-method h3 {
            font-size: 1.4rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .payment-method label {
            font-size: 1rem;
            cursor: pointer;
            display: block; /* Make it a block for better click area */
            margin-bottom: 10px;
        }
        .payment-method input[type="radio"] {
            margin-right: 8px;
            transform: scale(1.2); /* Slightly larger radio button */
        }

        /* Order Summary Section */
        .order-summary-section {
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Push button to bottom */
        }
        .summary-items-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            flex-grow: 1; /* Allow list to grow and fill space */
        }
        .summary-items-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.95rem;
            background-color: white;
        }
        .summary-items-list li:last-child {
            border-bottom: none;
        }
        .item-name {
            font-weight: 500;
            color: #333;
            flex-basis: 55%;
            text-align: left;
        }
        .item-qty {
            color: #6c757d;
            flex-basis: 15%;
            text-align: center;
        }
        .item-price {
            font-weight: 700;
            color: #2c3e50;
            flex-basis: 30%;
            text-align: right;
        }
        .rx-small-tag {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: 5px;
            vertical-align: middle;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1.1rem;
            color: #495057;
        }
        .summary-grand-total {
            font-size: 1.4rem;
            font-weight: 700;
            color: #007bff;
            padding: 15px 0;
            border-top: 2px solid #eee; /* Stronger border for grand total */
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
        }
        .summary-grand-total strong {
            color: #007bff;
        }

        .button-place-order {
            background-color: #28a745; /* Green place order button */
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 8px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            font-weight: 700;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            box-shadow: 0 6px 15px rgba(40,167,69,0.2);
        }
        .button-place-order:hover {
            background-color: #218838;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40,167,69,0.3);
        }

        .checkout-secure-note {
            text-align: center;
            font-size: 0.85rem;
            color: #777;
            margin-top: 20px;
        }
        .checkout-secure-note i {
            color: #6c757d;
            margin-right: 5px;
        }

        .empty-cart-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2rem;
            color: #777;
            background-color: #fdfdfd;
            border-radius: 10px;
            border: 1px dashed #ced4da;
            margin-top: 30px;
        }
        .empty-cart-message a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .empty-cart-message a:hover {
            text-decoration: underline;
        }

        /* Visually hidden for accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* Footer */
        .checkout-footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid #eee;
            color: #777;
            font-size: 0.9em;
        }
        .checkout-footer p {
            margin-bottom: 5px;
        }
        .checkout-footer a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .checkout-footer a:hover {
            color: #0056b3;
        }
        .checkout-footer a i {
            margin-right: 5px;
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .checkout-layout {
                flex-direction: column; /* Stack sections vertically */
                gap: 30px;
            }
            .customer-details-section, .order-summary-section {
                flex: none; /* Remove flex sizing */
                width: 100%; /* Take full width */
            }
            .order-summary-section {
                order: -1; /* Place order summary above customer details on smaller screens */
            }
        }

        @media (max-width: 600px) {
            .checkout-page-container {
                padding: 20px;
                margin: 20px auto;
                border-radius: 0; /* Full width on very small screens */
            }
            .checkout-header h1 {
                font-size: 2.2rem;
                gap: 10px;
            }
            .customer-details-section, .order-summary-section {
                padding: 18px;
            }
            .customer-details-section h2, .order-summary-section h2 {
                font-size: 1.6rem;
                margin-bottom: 20px;
            }
            .form-group input, .form-group textarea, .prescription-upload input[type="file"] {
                padding: 10px 12px;
                font-size: 0.95rem;
            }
            .button-place-order {
                font-size: 1.1rem;
                padding: 12px 20px;
            }
            .summary-items-list li {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            .summary-total {
                font-size: 1rem;
                padding: 8px 0;
            }
            .summary-grand-total {
                font-size: 1.2rem;
                padding: 12px 0;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-page-container">
        <header class="checkout-header">
            <h1><i class="fas fa-shopping-cart"></i> Checkout Your Medicines</h1>
        </header>

        <?php if ($feedback_message): ?>
            <div class="message-feedback <?= htmlspecialchars($feedback_type); ?>" role="alert">
                <i class="fas <?= $feedback_type === 'error' ? 'fa-times-circle' : ($feedback_type === 'success' ? 'fa-check-circle' : ($feedback_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle')) ?>"></i>
                <?= $feedback_message; /* $feedback_message might contain HTML, so not re-escaping */ ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items_details)): // Show empty cart message if cart is effectively empty after all checks ?>
            <p class="empty-cart-message">Your cart is empty or items are out of stock. <a href="buy_medicine.php">Continue shopping</a>.</p>
        <?php else: // Only show form if cart has items ?>
        <form action="medicine_checkout.php" method="POST" class="checkout-form-container">
            <input type="hidden" name="action" value="place_order">

            <div class="checkout-layout">
                <section class="customer-details-section">
                    <h2><i class="fas fa-user-circle"></i> Your Information</h2>
                    <div class="form-group">
                        <label for="customer_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($_POST['customer_name'] ?? $customer_name_prefill) ?>" <?= $is_user_logged_in ? 'readonly' : '' ?> required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="customer_phone">Phone Number <span class="required">*</span></label>
                        <!-- Removed 'readonly' attribute so logged-in users can edit their phone number for this order -->
                        <input type="tel" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($_POST['customer_phone'] ?? $customer_phone_prefill) ?>" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="customer_email">Email Address</label>
                        <input type="email" id="customer_email" name="customer_email" value="<?= htmlspecialchars($_POST['customer_email'] ?? $customer_email_prefill) ?>" <?= $is_user_logged_in ? 'readonly' : '' ?> aria-label="Email Address (Optional)">
                    </div>
                    <div class="form-group">
                        <label for="delivery_address">Delivery Address <span class="required">*</span></label>
                        <textarea id="delivery_address" name="delivery_address" rows="4" required aria-required="true"><?= htmlspecialchars($_POST['delivery_address'] ?? '') ?></textarea>
                    </div>
                     <div class="form-group">
                        <label for="customer_notes">Order Notes (Optional)</label>
                        <textarea id="customer_notes" name="customer_notes" rows="3" aria-label="Order Notes (Optional)"><?= htmlspecialchars($_POST['customer_notes'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group payment-method">
                        <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                        <label for="cod_payment_method">
                            <input type="radio" id="cod_payment_method" name="payment_method" value="Cash on Delivery" checked> Cash on Delivery (COD)
                        </label>
                        <p><small>Online payment methods coming soon!</small></p>
                    </div>
                </section>

                <aside class="order-summary-section">
                    <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                    <?php if (!empty($cart_items_details)): ?>
                        <ul class="summary-items-list">
                            <?php foreach ($cart_items_details as $item): ?>
                                <li>
                                    <span class="item-name"><?= htmlspecialchars($item['name']); ?> <?php if($item['requires_prescription']): ?><span class="rx-small-tag" title="Prescription Required">Rx</span><?php endif; ?></span>
                                    <span class="item-qty">Qty: <?= htmlspecialchars($item['quantity']); ?></span>
                                    <span class="item-price">BDT <?= htmlspecialchars(number_format($item['total_price'], 2)); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="summary-total">
                            <span>Subtotal:</span>
                            <span>BDT <?= htmlspecialchars(number_format($cart_subtotal, 2)); ?></span>
                        </div>
                        <div class="summary-total">
                            <span>Delivery Fee:</span>
                            <span>Calculated at Delivery <small>(May vary based on location)</small></span>
                        </div>
                        <hr>
                        <div class="summary-grand-total">
                            <strong>Grand Total:</strong>
                            <strong>BDT <?= htmlspecialchars(number_format($cart_subtotal, 2)); ?></strong>
                        </div>
                        <button type="submit" class="button-place-order" aria-label="Place Order">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>
                        <p class="checkout-secure-note"><i class="fas fa-lock"></i> This is a secure checkout process (Demo).</p>
                    <?php else: ?>
                        <p>Your cart is empty or items are out of stock. Please go back to shopping.</p>
                    <?php endif; ?>
                </aside>
            </div>
        </form>
        <?php endif; ?>


        <footer class="checkout-footer">
            <p>&copy; <?= date("Y"); ?> ABC Medical Pharmacy. All rights reserved.</p>
            <p><a href="buy_medicine.php" aria-label="Back to Pharmacy"><i class="fas fa-arrow-left"></i> Back to Pharmacy</a></p>
        </footer>
    </div>
</body>
</html>