<?php

require_once "auth.php";
require_once "../db.php";
require_once "../csrf.php";

// Only POST requests are allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: balance_requests.php");
    exit;
}

// Validate CSRF token
if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
    $_SESSION["admin_message"] =
        "Invalid request. Please refresh the page and try again.";

    header("Location: balance_requests.php");
    exit;
}

// Validate request ID and action
$requestId = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if (!$requestId || !in_array($action, ["approve", "reject"], true)) {
    $_SESSION["admin_message"] = "Invalid balance request.";
    header("Location: balance_requests.php");
    exit;
}

try {

    // Start database transaction
    mysqli_begin_transaction($conn);

    // Lock request row to prevent simultaneous processing
    $sql = "SELECT user_id, amount, status
            FROM deposit_requests
            WHERE id = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception("Could not prepare request query.");
    }

    mysqli_stmt_bind_param($stmt, "i", $requestId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $request = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$request) {
        throw new Exception("Balance request not found.");
    }

    // Only pending requests can be processed
    if ($request["status"] !== "pending") {
        mysqli_rollback($conn);

        $_SESSION["admin_message"] =
            "This request has already been processed.";

        header("Location: balance_requests.php");
        exit;
    }

    $userId = (int)$request["user_id"];
    $amount = (string)$request["amount"];

    if ($action === "approve") {

        // Add requested amount to user's existing wallet
        $walletSql = "UPDATE wallets
                      SET balance = balance + ?
                      WHERE user_id = ?";

        $walletStmt = mysqli_prepare($conn, $walletSql);

        if (!$walletStmt) {
            throw new Exception("Could not prepare wallet update.");
        }

        mysqli_stmt_bind_param(
            $walletStmt,
            "si",
            $amount,
            $userId
        );

        if (!mysqli_stmt_execute($walletStmt)) {
            mysqli_stmt_close($walletStmt);
            throw new Exception("Wallet update failed.");
        }

        $walletUpdated = mysqli_stmt_affected_rows($walletStmt);

        mysqli_stmt_close($walletStmt);

        // Ensure the user's wallet exists
        if ($walletUpdated !== 1) {
            throw new Exception("User wallet not found. Balance was not credited.");
        }

        // Mark request as approved
        $updateSql = "UPDATE deposit_requests
                      SET status = 'approved',
                          reviewed_at = NOW()
                      WHERE id = ? AND status = 'pending'";

        $updateStmt = mysqli_prepare($conn, $updateSql);

        if (!$updateStmt) {
            throw new Exception("Could not prepare approval update.");
        }

        mysqli_stmt_bind_param($updateStmt, "i", $requestId);

        if (!mysqli_stmt_execute($updateStmt) ||
            mysqli_stmt_affected_rows($updateStmt) !== 1) {

            mysqli_stmt_close($updateStmt);
            throw new Exception("Could not approve balance request.");
        }

        mysqli_stmt_close($updateStmt);

        mysqli_commit($conn);

        $_SESSION["admin_message"] =
            "Balance request approved successfully. Wallet credited.";

    } else {

        // Reject request without changing wallet balance
        $updateSql = "UPDATE deposit_requests
                      SET status = 'rejected',
                          reviewed_at = NOW()
                      WHERE id = ? AND status = 'pending'";

        $updateStmt = mysqli_prepare($conn, $updateSql);

        if (!$updateStmt) {
            throw new Exception("Could not prepare rejection update.");
        }

        mysqli_stmt_bind_param($updateStmt, "i", $requestId);

        if (!mysqli_stmt_execute($updateStmt) ||
            mysqli_stmt_affected_rows($updateStmt) !== 1) {

            mysqli_stmt_close($updateStmt);
            throw new Exception("Could not reject balance request.");
        }

        mysqli_stmt_close($updateStmt);

        mysqli_commit($conn);

        $_SESSION["admin_message"] =
            "Balance request rejected successfully.";
    }

} catch (Throwable $e) {

    mysqli_rollback($conn);

    error_log("Balance request processing error: " . $e->getMessage());

    $_SESSION["admin_message"] =
        "Unable to process this request. Please check the database and try again.";
}

mysqli_close($conn);

header("Location: balance_requests.php");
exit;