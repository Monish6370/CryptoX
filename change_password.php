
<?php
session_start();
require_once "csrf.php";
$csrfToken = csrf_token();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

$sql = "SELECT password FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user === false) {
    die("user not found");
}

$currentHash = $user["password"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $message = "";

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
        $message = "Your session expired. Refresh the page and try again.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } elseif (strlen($newPassword) < 8) {
        $message = "Password must be at least 8 Characters";
        $messageType = "error";
    } elseif (!password_verify($currentPassword, $currentHash)) {
        $message = "Current password is incorrect.";
        $messageType = "error";
    } elseif (password_verify($newPassword, $currentHash)) {
        $message = "New password must be different from your current password.";
        $messageType = "error";
    }

    if (empty($message)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateSql = "UPDATE users SET password = ? WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, "si", $newHash, $_SESSION["user_id"]);

        if (mysqli_stmt_execute($updateStmt)) {
            $message = "Password changed successfully!";
            $messageType = "success";
        } else {
            $message = "Password update failed. Please try again.";
            $messageType = "error";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | CryptoX</title>
    <link rel="stylesheet" href="style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #F4F6FA;
            color: #17243B;
        }

        .password-page {
            min-height: 100vh;
            padding: 35px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 10% 10%, rgba(37, 99, 235, 0.07), transparent 42%),
                #F4F6FA;
        }

        .password-card {
            width: 100%;
            max-width: 520px;
            padding: 35px;
            border: 1px solid #E2E8F0;
            border-radius: 22px;
            background: #FFFFFF;
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.08);
        }

        .password-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 35px;
            font-size: 21px;
            font-weight: 700;
            color: #17243B;
        }

        .password-brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 23px;
        }

        .password-tag {
            color: #2563EB;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        .password-card h1 {
            margin: 12px 0 8px;
            font-size: 30px;
            letter-spacing: -0.8px;
            color: #17243B;
        }

        .password-subtitle {
            margin-bottom: 25px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
        }

        .password-card label {
            display: block;
            margin: 18px 0 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        .password-card input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            outline: none;
            background: #F8FAFC;
            color: #17243B;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .password-card input:focus {
            border-color: #2563EB;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .password-hint {
            margin-top: 8px;
            color: #64748B;
            font-size: 12px;
        }

        .password-submit {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            border: none;
            border-radius: 11px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .password-submit:hover {
            transform: translateY(-2px);
            background: #1D4ED8;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.20);
        }

        .password-message {
            margin-bottom: 20px;
            padding: 13px 15px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .password-message.success {
            border: 1px solid #86EFAC;
            background: #F0FDF4;
            color: #15803D;
        }

        .password-message.error {
            border: 1px solid #FECACA;
            background: #FEF2F2;
            color: #B91C1C;
        }

        .password-links {
            display: grid;
            gap: 10px;
            margin-top: 20px;
        }

        .password-links a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 15px;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            background: #FFFFFF;
            color: #334155;
            font-size: 13px;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .password-links a span {
            color: #2563EB;
        }

        .password-links a:hover {
            border-color: #2563EB;
            background: #EFF6FF;
            color: #1D4ED8;
        }

        @media (max-width: 480px) {
            .password-card {
                padding: 24px;
            }

            .password-card h1 {
                font-size: 26px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

    <div class="password-page">
        <main class="password-card">

            <div class="password-brand">
                <span class="password-brand-icon">◇</span>
                <span>CryptoX</span>
            </div>

            <span class="password-tag">SECURITY SETTINGS</span>

            <h1>Change Password</h1>

            <p class="password-subtitle">
                Protect your account with a strong, unique password.
            </p>

            <?php if (!empty($message)): ?>
                <p class="password-message <?= ($messageType === 'success') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form action="change_password.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                    required>

                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password"
                    required>

                <p class="password-hint">Use at least 8 characters.</p>

                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8"
                    autocomplete="new-password" required>

                <button type="submit" class="password-submit">
                    Update Password
                </button>

            </form>

            <div class="password-links">
                <a href="profile.php">
                    Back to Profile <span>↗</span>
                </a>

                <a href="dashboard.php">
                    Back to Dashboard <span>↗</span>
                </a>
            </div>

        </main>
    </div>

</body>

</html>
