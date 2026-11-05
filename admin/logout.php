
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear only admin session.
unset(
    $_SESSION["admin_user_id"],
    $_SESSION["admin_name"],
    $_SESSION["admin_email"],
    $_SESSION["admin_role"]
);

header("Location: login.php");
exit;

?>
