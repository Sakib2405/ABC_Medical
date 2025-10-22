<?php
$servername = "sql104.infinityfree.com";
$username   = "if0_39322006";
$password   = "24052002S";
$database   = "if0_39322006_ABC_Medical";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    // Optional: Log to a file for debugging
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}
?>
