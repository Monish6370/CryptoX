
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin-specific session.
if (
    !isset($_SESSION["admin_user_id"]) ||
    !isset($_SESSION["admin_role"]) ||
    $_SESSION["admin_role"] !== "admin"
) {
    header("Location: login.php");
    exit;
}
