
<?php
session_start();
require_once "csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

$message = "";

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
    http_response_code(403);
    exit("Your session expired. Please return to your profile and try again.");
}

if ($name === "" || $email === "") {
    die("Name and email are required.");
}

if (strlen($name) > 100 || strlen($email) > 255) {
    die("Name or email is too long.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Please enter a valid email address.");
}

// Check whether another user already uses this email.
$check = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE email = ? AND id != ?"
);

mysqli_stmt_bind_param(
    $check,
    "si",
    $email,
    $_SESSION["user_id"]
);

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    die("This email is already being used by another account.");
}

mysqli_stmt_close($check);

// Update the logged-in user's record.
$stmt = mysqli_prepare(
    $conn,
    "UPDATE users SET name = ?, email = ? WHERE id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "ssi",
    $name,
    $email,
    $_SESSION["user_id"]
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION["user_name"] = $name;
    $_SESSION["user_email"] = $email;

    mysqli_stmt_close($stmt);

    header("Location: profile.php?updated=1");
    exit;
} else {
    mysqli_stmt_close($stmt);
    die("Profile update failed. Please try again.");
}
?>
