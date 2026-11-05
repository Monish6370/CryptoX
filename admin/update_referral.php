
<?php

require_once "auth.php";
require_once "../db.php";
require_once "../csrf.php";

// Only POST requests are allowed.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: referrals.php");
    exit;
}

// Verify CSRF token.
if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
    mysqli_close($conn);
    die("Invalid request. Please refresh the page and try again.");
}

// Get reward ID and action.
$rewardId = filter_input(INPUT_POST, "reward_id", FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

// Validate input.
if (!$rewardId || $action !== "approve") {
    mysqli_close($conn);
    die("Invalid referral reward request.");
}

// Approve only pending rewards.
$sql = "UPDATE referral_rewards
        SET status = 'approved'
        WHERE id = ? AND status = 'pending'";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    mysqli_close($conn);
    die("Something went wrong. Please try again.");
}

mysqli_stmt_bind_param($stmt, "i", $rewardId);

if (mysqli_stmt_execute($stmt)) {

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $_SESSION["admin_message"] = "Referral reward approved successfully.";
    } else {
        $_SESSION["admin_message"] = "Reward not found or it has already been processed.";
    }

} else {
    $_SESSION["admin_message"] = "Unable to approve referral reward.";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

header("Location: referrals.php");
exit;

?>
