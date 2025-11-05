<?php
session_start();
$page_title = "Order Confirmation - ABC Medical Pharmacy";

$db_host = 'sql104.infinityfree.com';
$db_user = 'if0_39322006';
$db_pass = '24052002S';
$db_name = 'if0_39322006_ABC_Medical';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error . " (DB_CONNECT_ERR)");
}
$conn->set_charset("utf8mb4");

$order_id = null;
$order_details = null;
$order_items_list = [];
$error_message = '';
$success_message_from_session = '';

// Retrieve message from GET parameters, which is how it's passed from medicine_checkout.php
if (isset($_GET['message'])) {
    $success_message_from_session = htmlspecialchars(urldecode($_GET['message']));
}


if (isset($_GET['order_id'])) {
    $order_id = filter_var($_GET['order_id'], FILTER_VALIDATE_INT);

    if ($order_id === false || $order_id <= 0) {
        $error_message = "Invalid Order ID provided.";
    } else {
        // Fetch Order Details from 'orders' table
        // ADDED 'prescription_status' TO THE SELECT QUERY
        $stmt_order = $conn->prepare("SELECT *, prescription_status FROM orders WHERE order_id = ?");
        if ($stmt_order) {
            $stmt_order->bind_param("i", $order_id);
            $stmt_order->execute();
            $result_order = $stmt_order->get_result();
            if ($result_order->num_rows > 0) {
                $order_details = $result_order->fetch_assoc();

                // Fetch Order Items from 'order_items' and join with 'medicines'
                $stmt_items = $conn->prepare(
                    "SELECT oi.*, m.name AS medicine_name, m.strength, m.form
                     FROM order_items oi
                     JOIN medicines m ON oi.medicine_id = m.id
                     WHERE oi.order_id = ?"
                );
                if ($stmt_items) {
                    $stmt_items->bind_param("i", $order_id);
                    $stmt_items->execute();
                    $result_items = $stmt_items->get_result();
                    while ($item_row = $result_items->fetch_assoc()) {
                        $order_items_list[] = $item_row;
                    }
                    $stmt_items->close();
                } else {
                    $error_message = "Could not retrieve order items details: " . $conn->error;
                }
            } else {
                $error_message = "Order not found. Please check the Order ID.";
            }
            $stmt_order->close();
        } else {
            $error_message = "Error preparing statement to fetch order details: " . $conn->error;
        }
    }
} else {
    $error_message = "No Order ID provided.";
}

// In a real application, you would also check if the current user is authorized to view this order.
// For example, if (isset($_SESSION['user_id']) && $order_details['user_id'] == $_SESSION['user_id']) { ... }

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
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .confirmation-page-container {
            max-width: 900px; /* Adjusted max-width for better content flow */
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 30px;
            box-sizing: border-box;
        }

        /* Header Styles */
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .confirmation-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.8rem;
            color: #28a745; /* Green for success */
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .confirmation-header h1 i {
            color: #28a745;
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
        .message-feedback.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-feedback.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message-feedback.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .message-feedback p {
            margin: 0;
            flex-grow: 1;
        }
        .message-feedback .button-link { /* Style for button inside message */
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease;
            margin-left: auto; /* Push to right */
        }
        .message-feedback .button-link:hover {
            background-color: #0056b3;
        }


        /* Order Details Sections */
        .order-details-wrapper {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 30px;
        }

        .order-main-info {
            background-color: #e8f5e9; /* Lighter green for main info */
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(40,167,69,0.1);
        }
        .order-main-info h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            color: #1a7a3e; /* Darker green */
            margin-top: 0;
            margin-bottom: 15px;
        }
        .order-main-info p {
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        .order-main-info p strong {
            color: #333;
        }
        /* Status badge styling (defined in global style or here) */
        .status-pending-confirmation,
        .status-pending-prescription,
        .status-processing,
        .status-shipped,
        .status-delivered,
        .status-cancelled,
        .status-verified, /* Added for prescription status */
        .status-rejected, /* Added for prescription status */
        .status-no /* Added for prescription status */
        {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
            color: white;
            margin-left: 10px; /* Space from label */
        }
        .status-pending-confirmation { background-color: #007bff; } /* Blue */
        .status-pending-prescription { background-color: #ffc107; color: #333;} /* Yellow */
        .status-processing { background-color: #6f42c1; } /* Purple */
        .status-shipped { background-color: #17a2b8; } /* Teal */
        .status-delivered { background-color: #28a745; } /* Green */
        .status-cancelled { background-color: #dc3545; } /* Red */
        .status-verified { background-color: #00b0ff; } /* Light Blue for Verified */
        .status-rejected { background-color: #ff5252; } /* Red for Rejected */
        .status-no { background-color: #9e9e9e; } /* Gray for No/N/A */


        .customer-shipping-info {
            display: flex;
            gap: 25px;
        }
        .info-column {
            flex: 1;
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border: 1px solid #e9ecef;
        }
        .info-column h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .info-column h3 i {
            color: #007bff;
        }
        .info-column p, .info-column address {
            font-size: 1rem;
            margin-bottom: 8px;
            white-space: pre-wrap; /* Preserve line breaks for address */
        }
        .info-column p strong {
            color: #333;
        }

        .prescription-info {
            background-color: #fdfae0; /* Light yellow for notice */
            border: 1px solid #ffeeba;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            text-align: center;
        }
        .prescription-info h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: #e0a800; /* Darker yellow */
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-bottom: 1px solid #ffeeba;
            padding-bottom: 10px;
        }
        .prescription-info h3 i {
            color: #e0a800;
        }
        .prescription-info a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        .prescription-thumbnail {
            max-width: 100px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .ordered-items-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .ordered-items-summary h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .ordered-items-summary h3 i {
            color: #28a745;
        }
        .ordered-items-summary table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .ordered-items-summary th, .ordered-items-summary td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .ordered-items-summary th {
            background-color: #f2f2f2;
            color: #333;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        .ordered-items-summary tbody tr:last-child td {
            border-bottom: none;
        }
        .ordered-items-summary tfoot td {
            font-weight: 600;
            font-size: 1.05em;
        }
        .ordered-items-summary tfoot .summary-label {
            text-align: right;
            padding-right: 20px;
        }
        .ordered-items-summary tfoot .grand-total-label {
            font-size: 1.2em;
            color: #2c3e50;
        }
        .ordered-items-summary tfoot .grand-total-value {
            font-size: 1.3em;
            color: #007bff;
            font-weight: 700;
        }
        .ordered-items-summary tfoot tr:last-child {
            border-top: 2px solid #ddd;
        }

        .order-notes {
            background-color: #f0f8ff; /* Light blue */
            border: 1px solid #d1e7fd;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .order-notes h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: #007bff;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #d1e7fd;
            padding-bottom: 10px;
        }
        .order-notes h3 i {
            color: #007bff;
        }
        .order-notes p {
            font-style: italic;
            color: #555;
        }

        .confirmation-actions {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .button-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .print-button {
            background-color: #6f42c1; /* Purple */
            color: white;
        }
        .print-button:hover {
            background-color: #5c3596;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        .continue-shopping-button {
            background-color: #007bff; /* Blue */
            color: white;
        }
        .continue-shopping-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,123,255,0.2);
        }
        .go-home-button { /* New button style */
            background-color: #28a745; /* Green */
            color: white;
        }
        .go-home-button:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(40,167,69,0.2);
        }


        /* Footer */
        .confirmation-footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid #eee;
            color: #777;
            font-size: 0.9em;
        }
        .confirmation-footer p {
            margin-bottom: 5px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .confirmation-page-container {
                padding: 20px;
                margin: 20px auto;
            }
            .confirmation-header h1 {
                font-size: 2.2rem;
            }
            .order-main-info, .info-column, .ordered-items-summary, .order-notes, .prescription-info {
                padding: 15px;
            }
            .customer-shipping-info {
                flex-direction: column;
                gap: 20px;
            }
            .info-column h3 {
                font-size: 1.4rem;
            }
            .ordered-items-summary h3 {
                font-size: 1.6rem;
            }
            .ordered-items-summary table,
            .ordered-items-summary thead,
            .ordered-items-summary tbody,
            .ordered-items-summary th,
            .ordered-items-summary td,
            .ordered-items-summary tr {
                display: block;
            }
            .ordered-items-summary thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .ordered-items-summary tr {
                border: 1px solid #ddd;
                margin-bottom: 10px;
                border-radius: 8px;
            }
            .ordered-items-summary td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            .ordered-items-summary td:before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                color: #555;
            }
            .ordered-items-summary tfoot tr {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                border: none;
            }
            .ordered-items-summary tfoot td {
                flex-basis: 48%;
                text-align: right !important;
                padding: 8px 15px;
            }
            .ordered-items-summary tfoot .summary-label {
                text-align: left !important;
            }
            .ordered-items-summary tfoot tr:last-child {
                border-top: 2px solid #ddd;
                width: 100%;
            }
             .confirmation-actions {
                flex-direction: column;
                gap: 10px;
            }
            .button-action {
                width: 100%;
            }
        }

        /* Print-specific styles - More aggressive for single-page potential */
        @media print {
            body {
                background-color: #fff;
                margin: 0;
                padding: 0;
                font-size: 9pt; /* Smaller base font for print */
                -webkit-print-color-adjust: exact; /* For background colors if needed */
                print-color-adjust: exact;
            }
            .confirmation-page-container {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: 100%;
                width: 100%;
                padding: 10mm 15mm; /* Reduced padding for more space */
            }
            .confirmation-header {
                text-align: center;
                border-bottom: 2px solid #333; /* Stronger header border */
                padding-bottom: 5mm;
                margin-bottom: 10mm; /* Reduced margin */
            }
            .confirmation-header h1 {
                font-size: 18pt; /* Smaller header font */
                color: #000;
                gap: 5mm;
                justify-content: center;
            }
            .confirmation-header h1 i {
                display: none; /* Hide icon on print */
            }
            /* Hide elements not needed for print */
            .message-feedback,
            .confirmation-actions,
            .confirmation-footer {
                display: none !important;
            }

            .order-details-wrapper {
                gap: 10mm; /* Reduced gap between sections */
            }
            .order-main-info, .info-column, .ordered-items-summary, .order-notes, .prescription-info {
                box-shadow: none;
                border: 1px solid #ccc; /* Add subtle border for print */
                background-color: #fff; /* Ensure white background */
                padding: 8mm; /* Reduced padding */
            }
            .order-main-info h2, .info-column h3, .ordered-items-summary h3, .order-notes h3, .prescription-info h3 {
                font-size: 13pt; /* Smaller section titles */
                color: #000;
                border-bottom: 1px solid #aaa;
                padding-bottom: 3mm;
                margin-bottom: 5mm;
            }
            .order-main-info h2 i, .info-column h3 i, .ordered-items-summary h3 i, .order-notes h3 i, .prescription-info h3 i {
                display: none; /* Hide icons in section headers */
            }
            .order-main-info p, .info-column p, .info-column address, .order-notes p, .prescription-info p {
                font-size: 10pt; /* Smaller body text */
                margin-bottom: 3mm;
            }
            .status-pending-confirmation, .status-pending-prescription, .status-processing, .status-shipped, .status-delivered, .status-cancelled, .status-verified, .status-rejected, .status-no {
                color: #000 !important;
                background-color: transparent !important;
                border: 1px solid #000;
                padding: 1mm 3mm; /* Very small padding for badges */
                font-size: 8pt; /* Tiny font for badges */
            }
            .customer-shipping-info {
                flex-direction: row; /* Prefer side-by-side if possible */
                gap: 10mm;
            }
            /* Table specific print adjustments */
            .ordered-items-summary table {
                min-width: unset; /* Allow table to shrink */
            }
            .ordered-items-summary th, .ordered-items-summary td {
                font-size: 9pt; /* Smallest font for table data */
                padding: 4mm 5mm; /* Reduced table cell padding */
            }
            .ordered-items-summary tfoot td {
                font-size: 10pt;
            }
            .ordered-items-summary tfoot .grand-total-value {
                font-size: 12pt;
            }
            .prescription-thumbnail {
                max-width: 50mm; /* Smaller thumbnail for print */
                border: 1px solid #000;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-page-container">
        <header class="confirmation-header">
            <h1><i class="fas fa-check-circle"></i> Order Confirmation</h1>
        </header>

        <?php if ($success_message_from_session): ?>
            <div class="message-feedback success">
                <i class="fas fa-check-circle"></i>
                <p><?= $success_message_from_session; /* Contains HTML for bold order ID */ ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message-feedback error">
                <i class="fas fa-times-circle"></i>
                <p><?= htmlspecialchars($error_message); ?></p>
                <a href="buy_medicine.php" class="button-link"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
            </div>
        <?php elseif ($order_details && !empty($order_items_list)): ?>
            <div class="order-details-wrapper">
                <section class="order-main-info">
                    <h2>Order #<?= htmlspecialchars($order_details['order_id']); ?></h2>
                    <p><strong>Order Date:</strong> <?= htmlspecialchars(date("F j, Y, g:i a", strtotime($order_details['order_date']))); ?></p>
                    <p><strong>Order Status:</strong> <span class="status-<?= strtolower(str_replace(' ', '-', $order_details['order_status'])); ?>"><?= htmlspecialchars($order_details['order_status']); ?></span></p>
                    <p>
                        <strong>Prescription Status:</strong>
                        <?php
                            $prescription_status_val = htmlspecialchars($order_details['prescription_status'] ?? 'No');
                            $prescription_status_class = 'status-' . strtolower(str_replace(' ', '-', $prescription_status_val));
                        ?>
                        <span class="status-<?= $prescription_status_class; ?>">
                            <?= $prescription_status_val; ?>
                        </span>
                    </p>
                    <p><strong>Payment Method:</strong> <?= htmlspecialchars($order_details['payment_method']); ?></p>
                </section>

                <section class="customer-shipping-info">
                    <div class="info-column">
                        <h3><i class="fas fa-user"></i> Customer Details</h3>
                        <p><strong>Name:</strong> <?= htmlspecialchars($order_details['customer_name']); ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($order_details['customer_phone']); ?></p>
                        <?php if (!empty($order_details['customer_email'])): ?>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order_details['customer_email']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="info-column">
                        <h3><i class="fas fa-shipping-fast"></i> Delivery Address</h3>
                        <address><?= nl2br(htmlspecialchars($order_details['delivery_address'])); ?></address>
                    </div>
                </section>

                <?php
                // Check if prescription_image_url exists and is not empty
                $has_prescription_url = !empty($order_details['prescription_image_url']);
                // Check if the file actually exists on the server
                $prescription_file_exists = $has_prescription_url && file_exists($order_details['prescription_image_url']);
                ?>
                <?php if ($has_prescription_url): // Show section if URL exists, even if file doesn't ?>
                <section class="prescription-info">
                    <h3><i class="fas fa-file-prescription"></i> Uploaded Prescription</h3>
                    <?php if ($prescription_file_exists): ?>
                    <p>
                        <a href="<?= htmlspecialchars($order_details['prescription_image_url']); ?>" target="_blank" title="View Prescription">
                            <img src="<?= htmlspecialchars($order_details['prescription_image_url']); ?>" alt="Prescription Thumbnail" class="prescription-thumbnail">
                            View Uploaded Prescription (<?= basename(htmlspecialchars($order_details['prescription_image_url'])); ?>)
                        </a>
                    </p>
                    <?php else: ?>
                    <p>A prescription was indicated for this order (Filename: <?= basename(htmlspecialchars($order_details['prescription_image_url'])); ?>). However, the uploaded file may not be directly accessible or was not found on the server.</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section class="ordered-items-summary">
                    <h3><i class="fas fa-pills"></i> Ordered Items</h3>
                    <table>
                        <thead>
                            <tr>
                                <th data-label="Medicine">Medicine</th>
                                <th data-label="Strength & Form">Strength & Form</th>
                                <th data-label="Unit Price">Unit Price</th>
                                <th data-label="Quantity">Quantity</th>
                                <th data-label="Total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items_list as $item): ?>
                                <tr>
                                    <td data-label="Medicine"><?= htmlspecialchars($item['medicine_name']); ?></td>
                                    <td data-label="Strength & Form"><?= htmlspecialchars($item['strength'] ?? ''); ?> - <?= htmlspecialchars($item['form'] ?? ''); ?></td>
                                    <td data-label="Unit Price">BDT <?= htmlspecialchars(number_format($item['price_per_unit'], 2)); ?></td>
                                    <td data-label="Quantity"><?= htmlspecialchars($item['quantity_ordered']); ?></td>
                                    <td data-label="Total">BDT <?= htmlspecialchars(number_format($item['total_price'], 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="summary-label">Subtotal:</td>
                                <td class="summary-value">BDT <?= htmlspecialchars(number_format($order_details['total_amount'], 2)); ?></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="summary-label">Delivery Fee:</td>
                                <td class="summary-value">As per policy (e.g., BDT 50.00 or Free)</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="summary-label grand-total-label">Grand Total:</td>
                                <td class="summary-value grand-total-value">BDT <?= htmlspecialchars(number_format($order_details['total_amount'], 2)); ?> <small>(+ Delivery Fee)</small></td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <?php if (!empty($order_details['notes'])): ?>
                <section class="order-notes">
                    <h3><i class="fas fa-sticky-note"></i> Your Notes</h3>
                    <p><?= nl2br(htmlspecialchars($order_details['notes'])); ?></p>
                </section>
                <?php endif; ?>

                <div class="confirmation-actions">
                    <button onclick="window.print()" class="button-action print-button"><i class="fas fa-print"></i> Print Order</button>
                    <a href="buy_medicine.php" class="button-action continue-shopping-button"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
                    <a href="index.php" class="button-action go-home-button"><i class="fas fa-home"></i> Go to Homepage</a>
                </div>
            </div>
        <?php elseif(!$error_message): // If no error but also no order details (e.g. ID was valid but nothing fetched) ?>
            <div class="message-feedback info">
                <i class="fas fa-info-circle"></i>
                <p>Could not retrieve order details. It's possible the order ID does not exist or there was an issue fetching the data.</p>
                <a href="buy_medicine.php" class="button-link"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
            </div>
        <?php endif; ?>

        <footer class="confirmation-footer">
            <p>&copy; <?= date("Y"); ?> ABC Medical Pharmacy. All rights reserved.</p>
            <p>If you have any questions about your order, please contact us with your Order ID.</p>
        </footer>
    </div>
</body>
</html>