<?php
// --- REPLACE WITH THE PASSWORD YOU WANT TO HASH FOR YOUR ADMIN ---
$plainPassword = 'admin123'; 

// --- NO NEED TO EDIT BELOW THIS LINE ---

// Hash the password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Output the results
// It's good practice to use htmlspecialchars when echoing user-generated or sensitive data,
// though in this temporary script context for $plainPassword, it's mainly for consistency.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Hash Generator</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        strong { color: #007bff; }
        .warning { color: #dc3545; font-weight: bold; margin-top: 15px; border: 1px solid #dc3545; padding:10px; background-color: #f8d7da }
        code { background-color: #e9ecef; padding: 2px 5px; border-radius: 3px; font-family: monospace; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Password Hash Generator</h1>
        <p>This script is for generating a hashed password to manually insert into your database.</p>
        
        <p><strong>Plain Password (for your reference ONLY - DO NOT store this directly in the database):</strong></p>
        <p><code><?= htmlspecialchars($plainPassword); ?></code></p>
        
        <p><strong>Hashed Password (use this in your SQL INSERT statement):</strong></p>
        <p><code><?= htmlspecialchars($hashedPassword); ?></code></p>

        <p class="warning">IMPORTANT: Delete this file (<code>hash_my_password.php</code>) from your server immediately after you have copied the hashed password. Leaving this file on a live server is a security risk.</p>
    </div>
</body>
</html>
