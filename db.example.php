
<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$username = "YOUR_DATABASE_USERNAME";
$password = "YOUR_DATABASE_PASSWORD";
$database = "crypto_db";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    error_log("CryptoX database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}

mysqli_set_charset($conn, "utf8mb4");
?>
