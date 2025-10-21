<?php
session_start();
$page_title = "Buy Medicines Online - ABC Medical Pharmacy";

// --- Database Connection ---
$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical'; // Your database name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // It's better to log the error and show a generic message in production
    die("Connection Failed: " . $conn->connect_error . " (DB_CONNECT_ERR)");
}
$conn->set_charset("utf8mb4");

// --- Initialize Cart in Session if it doesn't exist ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // cart will store [medicine_id => quantity]
}

// --- Handle Cart Actions ---
$action_feedback = '';
$action_type = '';

if (isset($_POST['action'])) {
    $medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;

    if ($_POST['action'] === 'add_to_cart' && $medicine_id > 0) {
        // Fetch medicine details to check stock
        $stmt_check = $conn->prepare("SELECT name, stock_quantity FROM medicines WHERE id = ? AND is_active = TRUE");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $medicine_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $medicine_to_add = $result_check->fetch_assoc();
            $stmt_check->close();

            if ($medicine_to_add) {
                $current_quantity_in_cart = isset($_SESSION['cart'][$medicine_id]) ? $_SESSION['cart'][$medicine_id] : 0;
                if ($medicine_to_add['stock_quantity'] > $current_quantity_in_cart) {
                    $_SESSION['cart'][$medicine_id] = $current_quantity_in_cart + 1;
                    $action_feedback = htmlspecialchars($medicine_to_add['name']) . " added to cart.";
                    $action_type = 'success';
                } else {
                    $action_feedback = "Sorry, " . htmlspecialchars($medicine_to_add['name']) . " is out of stock or not enough quantity.";
                    $action_type = 'error';
                }
            } else {
                $action_feedback = "Medicine not found or not available.";
                $action_type = 'error';
            }
        } else {
            $action_feedback = "Database error preparing statement for add to cart.";
            $action_type = 'error';
        }
    } elseif ($_POST['action'] === 'update_quantity' && $medicine_id > 0) {
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        if ($quantity > 0) {
             // Check stock before updating
            $stmt_stock = $conn->prepare("SELECT stock_quantity FROM medicines WHERE id = ? AND is_active = TRUE");
            if($stmt_stock){
                $stmt_stock->bind_param("i", $medicine_id);
                $stmt_stock->execute();
                $res_stock = $stmt_stock->get_result();
                $med_stock = $res_stock->fetch_assoc();
                $stmt_stock->close();
                if($med_stock && $quantity <= $med_stock['stock_quantity']){
                    $_SESSION['cart'][$medicine_id] = $quantity;
                    $action_feedback = "Cart quantity updated.";
                    $action_type = 'success';
                } else {
                    $action_feedback = "Not enough stock for the requested quantity for item ID " . htmlspecialchars($medicine_id) . ".";
                    $action_type = 'error';
                }
            } else {
                $action_feedback = "Database error preparing statement for update quantity.";
                $action_type = 'error';
            }
        } else { // Quantity is 0 or less, remove item
            if (isset($_SESSION['cart'][$medicine_id])) {
                unset($_SESSION['cart'][$medicine_id]);
                $action_feedback = "Item removed from cart.";
                $action_type = 'info';
            } else {
                $action_feedback = "Item not found in cart to remove.";
                $action_type = 'error';
            }
        }
    } elseif ($_POST['action'] === 'remove_from_cart' && $medicine_id > 0) {
        if (isset($_SESSION['cart'][$medicine_id])) {
            unset($_SESSION['cart'][$medicine_id]);
            $action_feedback = "Item removed from cart.";
            $action_type = 'info';
        } else {
            $action_feedback = "Item not found in cart to remove.";
            $action_type = 'error';
        }
    } elseif ($_POST['action'] === 'empty_cart') {
        $_SESSION['cart'] = [];
        $action_feedback = "Cart emptied.";
        $action_type = 'info';
    }
    // Redirect to avoid form resubmission on refresh
    header("Location: buy_medicine.php?feedback=" . urlencode($action_feedback) . "&type=" . $action_type);
    exit;
}

// Display feedback from GET parameters
if (isset($_GET['feedback']) && isset($_GET['type'])) {
    $action_feedback = htmlspecialchars(urldecode($_GET['feedback']));
    $action_type = htmlspecialchars($_GET['type']);
}


// --- Fetch Medicines for Display ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$medicines_on_display = [];
$sql_medicines = "SELECT id, name, generic_name, manufacturer, strength, form, unit_price, stock_quantity, requires_prescription
                  FROM medicines
                  WHERE is_active = TRUE AND stock_quantity > 0 ";
if (!empty($search_term)) {
    $sql_medicines .= " AND (name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ?) ";
}
$sql_medicines .= " ORDER BY name ASC LIMIT 50"; // Limit for performance

$stmt_medicines = $conn->prepare($sql_medicines);
if ($stmt_medicines) {
    if (!empty($search_term)) {
        $like_search_term = "%" . $search_term . "%";
        $stmt_medicines->bind_param("sss", $like_search_term, $like_search_term, $like_search_term);
    }
    $stmt_medicines->execute();
    $result_medicines = $stmt_medicines->get_result();
    if ($result_medicines) {
        while ($row = $result_medicines->fetch_assoc()) {
            $medicines_on_display[] = $row;
        }
    }
    $stmt_medicines->close();
} else {
    // Handle error if statement preparation fails for medicines list
    $action_feedback = "Error preparing statement for medicine list display.";
    $action_type = 'error';
}


// --- Calculate Cart Totals & Fetch Cart Item Details ---
$cart_items_details = [];
$cart_subtotal = 0;
if (!empty($_SESSION['cart'])) {
    $cart_medicine_ids = array_keys($_SESSION['cart']);
    // Filter out any non-numeric or zero IDs from the cart array to prevent SQL injection attempts
    $filtered_cart_medicine_ids = array_filter($cart_medicine_ids, function($id) {
        return is_numeric($id) && (int)$id > 0;
    });

    if (!empty($filtered_cart_medicine_ids)) {
        // Create placeholders string for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($filtered_cart_medicine_ids), '?'));
        // Create type string for bind_param (all integers 'i')
        $types = str_repeat('i', count($filtered_cart_medicine_ids));

        $sql_cart_details = "SELECT id, name, unit_price, requires_prescription, stock_quantity FROM medicines WHERE id IN ($placeholders) AND is_active = TRUE";
        $stmt_cart = $conn->prepare($sql_cart_details);
        if ($stmt_cart) {
            // Use call_user_func_array to bind parameters dynamically
            // array_merge combines the types string with the actual parameter values
            call_user_func_array([$stmt_cart, 'bind_param'], array_merge([$types], $filtered_cart_medicine_ids));

            $stmt_cart->execute();
            $result_cart_details = $stmt_cart->get_result();
            while ($cart_row = $result_cart_details->fetch_assoc()) {
                $quantity = $_SESSION['cart'][$cart_row['id']];
                // Ensure quantity in cart doesn't exceed available stock
                if ($quantity > $cart_row['stock_quantity']) {
                    $quantity = $cart_row['stock_quantity'];
                    $_SESSION['cart'][$cart_row['id']] = $quantity; // Update cart to match stock
                    // Set feedback message, but don't redirect here to avoid redirect loop during cart calculation
                    // This message will be shown on the next page load if the redirect for action feedback doesn't happen
                    $action_feedback = "Quantity for " . htmlspecialchars($cart_row['name']) . " adjusted to available stock.";
                    $action_type = 'info';
                }
                // If stock becomes 0 or less, remove item from cart
                if ($cart_row['stock_quantity'] <= 0) {
                    unset($_SESSION['cart'][$cart_row['id']]);
                    $action_feedback = htmlspecialchars($cart_row['name']) . " removed from cart due to no stock.";
                    $action_type = 'info';
                    continue; // Skip adding this item to details
                }


                $cart_items_details[] = [
                    'id' => $cart_row['id'],
                    'name' => $cart_row['name'],
                    'unit_price' => $cart_row['unit_price'],
                    'quantity' => $quantity,
                    'total_price' => $cart_row['unit_price'] * $quantity,
                    'requires_prescription' => $cart_row['requires_prescription'],
                    'stock_quantity'        => $cart_row['stock_quantity'] // Pass stock quantity for max attribute in input
                ];
                $cart_subtotal += ($cart_row['unit_price'] * $quantity);
            }
            $stmt_cart->close();
        } else {
            $action_feedback = "Database error preparing statement for cart details.";
            $action_type = 'error';
        }
    } else {
        // If, after filtering, the cart is empty (e.g., only invalid IDs were in session)
        $_SESSION['cart'] = [];
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

        .pharmacy-container {
            max-width: 1300px; /* Wider container */
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 30px;
            box-sizing: border-box;
        }

        /* Header Styles */
        .pharmacy-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .pharmacy-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.8rem;
            color: #28a745; /* Green for pharmacy header */
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .pharmacy-header h1 i {
            color: #28a745;
        }
        .pharmacy-header p {
            font-size: 1.1rem;
            color: #6c757d;
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

        /* Prescription Notice */
        .pharmacy-prescription-notice {
            background-color: #fff3cd; /* Light yellow */
            color: #856404; /* Dark yellow text */
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.95rem;
        }
        .pharmacy-prescription-notice i {
            color: #e0a800; /* Darker yellow icon */
            font-size: 1.5em;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .pharmacy-prescription-notice p {
            margin: 0;
        }
        .pharmacy-prescription-notice strong {
            font-weight: 700;
        }

        /* Layout for Medicine List and Cart */
        .pharmacy-layout {
            display: flex;
            gap: 30px; /* Space between main and aside */
        }
        .medicine-list-section {
            flex: 3; /* Takes more space */
            background-color: #fdfdfd;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            min-width: 0; /* Allow shrinking */
        }
        .cart-section {
            flex: 1; /* Takes less space */
            background-color: #f8f9fa; /* Lighter background for cart */
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            min-width: 300px; /* Minimum width for cart */
        }

        .medicine-list-section h2, .cart-section h2 {
            font-family: 'Montserrat', sans-serif;
            color: #2c3e50;
            font-size: 2rem;
            margin-top: 0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-section h2 i {
            color: #007bff; /* Blue for cart icon */
        }

        /* Search Form */
        .search-form {
            display: flex;
            margin-bottom: 30px;
            gap: 10px;
        }
        .search-form input[type="text"] {
            flex-grow: 1;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .search-form input[type="text"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
        }
        .search-form button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 600;
        }
        .search-form button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .search-form button i {
            margin-right: 5px;
        }

        /* Medicine Grid */
        .medicine-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Adjusted for 3-4 columns */
            gap: 25px;
        }
        .medicine-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border 0.2s ease;
            border: 1px solid #eee;
            position: relative; /* For Rx/OTC tag positioning */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            border-color: #007bff;
        }
        .medicine-card h3 {
            font-size: 1.4rem;
            margin-top: 0;
            margin-bottom: 8px;
            color: #34495e;
        }
        .rx-tag, .otc-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 700;
            color: white;
            z-index: 10;
        }
        .rx-tag {
            background-color: #dc3545; /* Red for Rx */
        }
        .otc-tag {
            background-color: #28a745; /* Green for OTC */
        }
        .generic-name {
            font-style: italic;
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .manufacturer {
            color: #555;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        .strength-form {
            font-size: 0.95em;
            color: #495057;
            font-weight: 500;
            margin-bottom: 15px;
        }
        .price {
            font-size: 1.2em;
            color: #007bff; /* Blue for price */
            font-weight: 700;
            margin-bottom: 10px;
        }
        .stock {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .stock.low-stock {
            color: #ffc107; /* Orange for low stock */
            font-weight: 600;
        }
        .stock.out-of-stock {
            color: #dc3545; /* Red for out of stock */
            font-weight: 600;
        }

        .add-to-cart-form {
            margin-top: auto; /* Push button to bottom */
            display: flex;
            justify-content: center;
        }
        .button-add-to-cart, .button-disabled {
            background-color: #28a745; /* Green add button */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 600;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .button-add-to-cart:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .button-disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.8;
        }
        .button-disabled:hover {
            transform: none; /* Disable hover transform */
            background-color: #cccccc;
        }

        .no-medicines {
            text-align: center;
            grid-column: 1 / -1; /* Span all columns */
            padding: 50px;
            font-size: 1.1rem;
            color: #777;
            background-color: #fdfdfd;
            border-radius: 10px;
            border: 1px dashed #ced4da;
            margin-top: 20px;
        }

        /* Cart Section Styling */
        .cart-items-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        .cart-items-list li {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on small screens */
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f2f5;
            background-color: white;
        }
        .cart-items-list li:last-child {
            border-bottom: none;
        }
        .cart-item-info {
            flex-grow: 1;
            margin-right: 10px;
            font-size: 0.95rem;
        }
        .cart-item-info strong {
            color: #333;
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
        .cart-update-form {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0; /* Prevent shrinking */
            margin-top: 5px; /* For wrapping behavior */
        }
        .cart-quantity-input {
            width: 50px;
            padding: 6px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.9rem;
            text-align: center;
        }
        .button-update-qty, .button-remove-item {
            background: none;
            border: none;
            cursor: pointer;
            color: #007bff;
            font-size: 1rem;
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .button-remove-item {
            color: #dc3545;
        }
        .button-update-qty:hover, .button-remove-item:hover {
            color: #0056b3;
            transform: scale(1.1);
        }
        .button-remove-item:hover {
            color: #c82333;
        }
        .cart-item-total {
            flex-basis: 100%; /* Take full width on next line if wrapped */
            text-align: right;
            font-weight: 700;
            color: #2c3e50;
            margin-top: 8px; /* Space from controls above */
            font-size: 1rem;
        }

        .cart-summary {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            margin-top: 20px;
            text-align: right;
        }
        .cart-summary p {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .cart-summary strong {
            color: #007bff;
            font-size: 1.3em;
        }
        .button-primary, .button-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .button-primary {
            background-color: #007bff;
            color: white;
            box-shadow: 0 4px 10px rgba(0,123,255,0.2);
        }
        .button-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,123,255,0.3);
        }
        .button-secondary {
            background-color: #6c757d;
            color: white;
            box-shadow: 0 4px 10px rgba(108,117,125,0.2);
            margin-right: 15px; /* Space from primary button */
        }
        .button-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(108,117,125,0.3);
        }
        .empty-cart-button {
            float: left; /* Align to the left */
            margin-top: 5px; /* Adjust vertical alignment */
        }
        .checkout-note {
            font-size: 0.85rem;
            color: #777;
            margin-top: 15px;
            text-align: right;
        }

        .empty-cart-message {
            text-align: center;
            padding: 30px;
            font-size: 1.1rem;
            color: #777;
            background-color: #fdfdfd;
            border-radius: 10px;
            border: 1px dashed #ced4da;
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
        .pharmacy-footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid #eee;
            color: #777;
            font-size: 0.9em;
        }
        .pharmacy-footer p {
            margin-bottom: 5px;
        }

        /* Responsive Adjustments */
        @media (max-width: 1100px) {
            .pharmacy-layout {
                flex-direction: column; /* Stack sections on smaller screens */
            }
            .medicine-list-section, .cart-section {
                flex: none; /* Remove flex growing */
                width: 100%; /* Take full width */
                min-width: unset; /* Remove min-width constraint */
            }
            .cart-section {
                order: -1; /* Place cart above medicine list on small screens */
            }
        }

        @media (max-width: 768px) {
            .pharmacy-container {
                padding: 20px;
                margin: 20px auto;
            }
            .pharmacy-header h1 {
                font-size: 2.2rem;
            }
            .pharmacy-prescription-notice {
                font-size: 0.9em;
                padding: 12px 15px;
            }
            .medicine-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* More compact cards */
                gap: 20px;
            }
            .medicine-card {
                padding: 15px;
            }
            .medicine-card h3 {
                font-size: 1.2rem;
            }
            .price {
                font-size: 1.1em;
            }
            .button-add-to-cart, .button-disabled {
                font-size: 0.9rem;
                padding: 8px 15px;
            }
            .cart-items-list li {
                flex-direction: column; /* Stack cart item details */
                align-items: flex-start;
            }
            .cart-item-info {
                margin-bottom: 8px;
                width: 100%;
                text-align: left;
            }
            .cart-update-form {
                width: 100%;
                justify-content: flex-start;
                margin-bottom: 8px;
            }
            .cart-item-total {
                flex-basis: auto;
                width: 100%;
                text-align: left;
            }
            .button-primary, .button-secondary {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }
            .empty-cart-button {
                float: none; /* Remove float */
            }
            .cart-summary {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
            }
            .cart-summary p {
                width: 100%;
                text-align: right;
            }
        }

        @media (max-width: 480px) {
            .pharmacy-container {
                border-radius: 0;
                padding: 15px;
                margin: 0;
            }
            .pharmacy-header h1 {
                font-size: 1.8rem;
                gap: 10px;
            }
            .search-form {
                flex-direction: column;
                gap: 10px;
            }
            .search-form button {
                width: 100%;
            }
            .medicine-grid {
                grid-template-columns: 1fr; /* Single column layout */
            }
            .medicine-card {
                padding: 20px; /* Restore padding for single column */
            }
            .cart-quantity-input {
                width: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="pharmacy-container">
        <header class="pharmacy-header">
            <h1><i class="fas fa-prescription-bottle-alt"></i> Online Pharmacy</h1>
            <p>Browse and order your medicines conveniently. (Demo)</p>
        </header>

        <?php if ($action_feedback): ?>
            <div class="message-feedback <?= htmlspecialchars($action_type); ?>" role="alert">
                <i class="fas <?= $action_type === 'error' ? 'fa-times-circle' : ($action_type === 'success' ? 'fa-check-circle' : 'fa-info-circle') ?>"></i>
                <?= htmlspecialchars($action_feedback); ?>
            </div>
        <?php endif; ?>

        <div class="pharmacy-prescription-notice" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <p><strong>Important Notice:</strong> Many medicines require a valid prescription from a registered medical practitioner. You will be contacted for prescription verification for such items after placing an order. Selling prescription drugs without a prescription is illegal and harmful.</p>
        </div>

        <div class="pharmacy-layout">
            <main class="medicine-list-section">
                <h2>Available Medicines</h2>
                <form action="buy_medicine.php" method="GET" class="search-form">
                    <label for="search-input" class="sr-only">Search Medicines</label>
                    <input type="text" id="search-input" name="search" placeholder="Search by name, generic, or manufacturer..." value="<?= htmlspecialchars($search_term); ?>">
                    <button type="submit" aria-label="Search"><i class="fas fa-search"></i> Search</button>
                </form>

                <div class="medicine-grid">
                    <?php if (!empty($medicines_on_display)): ?>
                        <?php foreach ($medicines_on_display as $medicine): ?>
                            <div class="medicine-card">
                                <div>
                                    <h3><?= htmlspecialchars($medicine['name']); ?></h3>
                                    <?php if($medicine['requires_prescription']): ?>
                                        <span class="rx-tag" title="Prescription Required"><i class="fas fa-prescription"></i> Rx Required</span>
                                    <?php else: ?>
                                        <span class="otc-tag" title="Over-The-Counter">OTC</span>
                                    <?php endif; ?>
                                    <p class="generic-name">Generic: <?= htmlspecialchars($medicine['generic_name'] ?? 'N/A'); ?></p>
                                    <p class="manufacturer">Manufacturer: <?= htmlspecialchars($medicine['manufacturer'] ?? 'N/A'); ?></p>
                                    <p class="strength-form">
                                        Strength: <?= htmlspecialchars($medicine['strength'] ?? ''); ?> - Form: <?= htmlspecialchars($medicine['form'] ?? ''); ?>
                                    </p>
                                    <p class="price">BDT <?= htmlspecialchars(number_format($medicine['unit_price'], 2)); ?> / unit</p>
                                    <p class="stock <?= $medicine['stock_quantity'] > 0 && $medicine['stock_quantity'] < 10 ? 'low-stock' : ($medicine['stock_quantity'] == 0 ? 'out-of-stock' : ''); ?>">
                                        Stock: <?= $medicine['stock_quantity'] > 0 ? ($medicine['stock_quantity'] < 10 ? 'Low Stock (' . htmlspecialchars($medicine['stock_quantity']) . ')' : 'In Stock') : 'Out of Stock'; ?>
                                    </p>
                                </div>
                                <?php if ($medicine['stock_quantity'] > 0): ?>
                                <form action="buy_medicine.php" method="POST" class="add-to-cart-form">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="medicine_id" value="<?= htmlspecialchars($medicine['id']); ?>">
                                    <button type="submit" class="button-add-to-cart" aria-label="Add <?= htmlspecialchars($medicine['name']); ?> to cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                </form>
                                <?php else: ?>
                                     <button type="button" class="button-disabled" disabled aria-label="Out of Stock">Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-medicines">No medicines found matching your criteria or available at the moment.</p>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="cart-section">
                <h2><i class="fas fa-shopping-cart"></i> Your Cart</h2>
                <?php if (!empty($cart_items_details)): ?>
                    <ul class="cart-items-list">
                        <?php foreach ($cart_items_details as $item): ?>
                            <li>
                                <div class="cart-item-info">
                                    <strong><?= htmlspecialchars($item['name']); ?></strong> (BDT <?= htmlspecialchars(number_format($item['unit_price'],2)); ?>)
                                    <?php if($item['requires_prescription']): ?> <span class="rx-small-tag" title="Prescription Required">Rx</span><?php endif; ?>
                                </div>
                                <form action="buy_medicine.php" method="POST" class="cart-update-form">
                                    <input type="hidden" name="action" value="update_quantity">
                                    <input type="hidden" name="medicine_id" value="<?= htmlspecialchars($item['id']); ?>">
                                    <label for="qty-<?= htmlspecialchars($item['id']) ?>" class="sr-only">Quantity for <?= htmlspecialchars($item['name']) ?></label>
                                    <input type="number" id="qty-<?= htmlspecialchars($item['id']) ?>" name="quantity" value="<?= htmlspecialchars($item['quantity']); ?>" min="0" max="<?= htmlspecialchars($item['stock_quantity']) ?>" class="cart-quantity-input" aria-label="Quantity for <?= htmlspecialchars($item['name']) ?>" required>
                                    <button type="submit" class="button-update-qty" title="Update Quantity"><i class="fas fa-sync-alt"></i><span class="sr-only"> Update Quantity</span></button>
                                    <button type="submit" name="action" value="remove_from_cart" class="button-remove-item" title="Remove Item"><i class="fas fa-trash-alt"></i><span class="sr-only"> Remove Item</span></button>
                                </form>
                                <div class="cart-item-total">BDT <?= htmlspecialchars(number_format($item['total_price'], 2)); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="cart-summary">
                        <p><strong>Subtotal:</strong> BDT <?= htmlspecialchars(number_format($cart_subtotal, 2)); ?></p>
                        <div class="cart-actions">
                            <form action="buy_medicine.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="empty_cart">
                                <button type="submit" class="button-secondary empty-cart-button" aria-label="Empty Cart"><i class="fas fa-trash"></i> Empty Cart</button>
                            </form>
                            <a href="medicine_checkout.php" class="button-primary checkout-button" aria-label="Proceed to Checkout">Proceed to Checkout <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <p class="checkout-note">Checkout process is a demo and will require prescription verification for Rx items.</p>
                    </div>
                <?php else: ?>
                    <p class="empty-cart-message">Your cart is currently empty.</p>
                <?php endif; ?>
            </aside>
        </div>

        <footer class="pharmacy-footer">
            <p>&copy; <?= date("Y"); ?> ABC Medical Pharmacy. All rights reserved.</p>
            <p>Consult your doctor or pharmacist for any medical advice. This is a demo system.</p>
        </footer>
    </div>
    </body>
</html>