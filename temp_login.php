<?php
// Start the session to be able to set session variables
session_start();

// --- SIMULATE USER LOGIN ---

// 1. Mark the user as "logged in" for the demo
$_SESSION['user_logged_in'] = true;

// 2. Set the user's phone number
// IMPORTANT: Replace '01711223344' with an actual phone number
// that exists in your 'orders' table (in the 'customer_phone' column)
// AND has one or more orders with an uploaded 'prescription_image_url'.
// Otherwise, the "My Prescriptions" page will show "No prescriptions found".
$_SESSION['user_phone'] = '01711223344'; // <<< CHANGE THIS TO A VALID TEST PHONE NUMBER

// Optional: You could also store other user details if needed by other pages
// $_SESSION['user_name'] = 'Demo Patient Name';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Demo Login Session Set</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f0f0f0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); display: inline-block; }
        p { font-size: 1.1em; margin-bottom: 20px; }
        a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1em;
        }
        a:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <p><strong>Demo login session has been set!</strong></p>
        <p>User is now simulated as logged in with phone number: <strong><?= htmlspecialchars($_SESSION['user_phone']); ?></strong></p>
        <p><a href="prescriptions.php">Go to My Prescriptions Page</a></p>
        <p><small>(Remember to use a phone number that has associated prescription records in your database to see results on the prescriptions page.)</small></p>
    </div>
</body>
</html>