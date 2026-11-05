
<?php

session_start();

// Clear only normal user session data.
unset(
    $_SESSION["user_id"],
    $_SESSION["user_name"],
    $_SESSION["user_email"],
    $_SESSION["role"]
);

// Keep the admin session active.

header("Location: login.php");
exit;

?>
