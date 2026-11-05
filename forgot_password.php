<?php
session_start();

require_once "csrf.php";
require_once "db.php";

$csrfToken = csrf_token();
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
        $message = "Session expired. Please refresh and try again.";
        $messageType = "error";
    } else {
        $email = trim($_POST["email"] ?? "");
        $recoveryKey = trim($_POST["recovery_key"] ?? "");
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !preg_match('/\A[a-f0-9]{64}\z/i', $recoveryKey) ||
            strlen($newPassword) < 8 ||
            $newPassword !== $confirmPassword
        ) {
            $message = "Check your details. Passwords must match and contain at least 8 characters.";
            $messageType = "error";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare user query.");
                }

                mysqli_stmt_bind_param($stmt, "s", $email);

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    throw new RuntimeException("Could not find account.");
                }

                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                $valid = false;
                $userId = 0;

                if ($user) {
                    $userId = (int) $user["id"];

                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT key_hash, failed_attempts, locked_until, used_at
                         FROM recovery_keys
                         WHERE user_id = ?
                         FOR UPDATE"
                    );

                    if (!$stmt) {
                        throw new RuntimeException("Could not prepare recovery query.");
                    }

                    mysqli_stmt_bind_param($stmt, "i", $userId);

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        throw new RuntimeException("Could not check recovery key.");
                    }

                    $result = mysqli_stmt_get_result($stmt);
                    $recovery = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);

                    if ($recovery && $recovery["used_at"] === null) {
                        $isLocked = $recovery["locked_until"] !== null
                            && strtotime($recovery["locked_until"]) > time();

                        if (!$isLocked) {
                            $submittedHash = hash("sha256", $recoveryKey);

                            if (hash_equals($recovery["key_hash"], $submittedHash)) {
                                $valid = true;
                            } else {
                                $stmt = mysqli_prepare(
                                    $conn,
                                    "UPDATE recovery_keys
                                     SET locked_until =
                                         IF(
                                             failed_attempts + 1 >= 5,
                                             DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                                             locked_until
                                         ),
                                         failed_attempts = failed_attempts + 1
                                     WHERE user_id = ?"
                                );

                                if (!$stmt) {
                                    throw new RuntimeException("Could not update recovery attempts.");
                                }

                                mysqli_stmt_bind_param($stmt, "i", $userId);

                                if (!mysqli_stmt_execute($stmt)) {
                                    mysqli_stmt_close($stmt);
                                    throw new RuntimeException("Could not update recovery attempts.");
                                }

                                mysqli_stmt_close($stmt);
                            }
                        }
                    }
                }

                if ($valid) {
                    $passwordHash = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    if ($passwordHash === false) {
                        throw new RuntimeException("Could not hash password.");
                    }

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE users SET password = ? WHERE id = ?"
                    );

                    if (!$stmt) {
                        throw new RuntimeException("Could not prepare password update.");
                    }

                    mysqli_stmt_bind_param($stmt, "si", $passwordHash, $userId);

                    if (
                        !mysqli_stmt_execute($stmt) ||
                        mysqli_stmt_affected_rows($stmt) !== 1
                    ) {
                        mysqli_stmt_close($stmt);
                        throw new RuntimeException("Could not update password.");
                    }

                    mysqli_stmt_close($stmt);

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE recovery_keys
                         SET used_at = NOW(),
                             failed_attempts = 0,
                             locked_until = NULL
                         WHERE user_id = ? AND used_at IS NULL"
                    );

                    if (!$stmt) {
                        throw new RuntimeException("Could not prepare key invalidation.");
                    }

                    mysqli_stmt_bind_param($stmt, "i", $userId);

                    if (
                        !mysqli_stmt_execute($stmt) ||
                        mysqli_stmt_affected_rows($stmt) !== 1
                    ) {
                        mysqli_stmt_close($stmt);
                        throw new RuntimeException("Could not invalidate recovery key.");
                    }

                    mysqli_stmt_close($stmt);
                    mysqli_commit($conn);

                    $message = "Password reset successful. You can now log in with your new password.";
                    $messageType = "success";
                } else {
                    mysqli_commit($conn);

                    $message = "Recovery details are invalid, expired, already used, or temporarily locked.";
                    $messageType = "error";
                }
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log("CryptoX password recovery failed: " . $e->getMessage());

                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
        }
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#080913">
    <title>Reset Password | CryptoX</title>

    <style>
        :root {
            color-scheme: light;

            --bg: #F4F6FA;
            --panel: #FFFFFF;
            --blue: #2563EB;
            --blue-light: #EFF6FF;
            --blue-dark: #1D4ED8;
            --text: #17243B;
            --muted: #64748B;
            --line: #E2E8F0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 28px 18px;
            display: grid;
            place-items: center;
            overflow-x: hidden;
            font-family: Inter, Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(ellipse at 10% 5%,
                    rgba(37, 99, 235, .07),
                    transparent 42%),
                var(--bg);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .35;
            background-image:
                linear-gradient(rgba(37, 99, 235, .035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37, 99, 235, .035) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 90%);
        }

        .orb {
            display: none;
        }

        .shell {
            position: relative;
            width: 100%;
            max-width: 530px;
            animation: enter .5s ease both;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 22px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -.7px;
            color: var(--text);
            text-decoration: none;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--blue);
            color: #FFFFFF;
            font-size: 25px;
            box-shadow: 0 5px 18px rgba(37, 99, 235, .18);
        }

        .brand-name span {
            color: var(--blue);
        }

        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 11px;
            border: 1px solid #BFDBFE;
            border-radius: 30px;
            background: var(--blue-light);
            color: var(--blue-dark);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--blue);
        }

        .card {
            position: relative;
            overflow: hidden;
            padding: clamp(24px, 5vw, 39px);
            border: 1px solid var(--line);
            border-radius: 23px;
            background: var(--panel);
            box-shadow: 0 20px 55px rgba(15, 23, 42, .08);
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 12%;
            width: 76%;
            height: 3px;
            background: linear-gradient(90deg,
                    transparent,
                    var(--blue),
                    #60A5FA,
                    transparent);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--blue);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 19px;
            height: 2px;
            background: var(--blue);
        }

        h1 {
            margin: 17px 0 12px;
            font-size: clamp(30px, 6vw, 41px);
            line-height: 1.12;
            letter-spacing: -1.5px;
            color: var(--text);
        }

        .gradient-text {
            color: var(--blue);
            background: none;
            -webkit-text-fill-color: initial;
        }

        .intro {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .steps {
            display: flex;
            gap: 7px;
            margin: 24px 0 25px;
        }

        .step {
            flex: 1;
            height: 3px;
            border-radius: 5px;
            background: #DBEAFE;
        }

        .step.active {
            background: var(--blue);
        }

        .message {
            margin: 18px 0;
            padding: 14px 15px;
            border: 1px solid var(--line);
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .message.success {
            border-color: #86EFAC;
            background: #F0FDF4;
            color: #15803D;
        }

        .message.error {
            border-color: #FECACA;
            background: #FEF2F2;
            color: #B91C1C;
        }

        .field {
            margin-top: 19px;
        }

        label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 9px;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        label span {
            color: #94A3B8;
            font-size: 10px;
            font-weight: 500;
        }

        input {
            display: block;
            width: 100%;
            min-height: 49px;
            padding: 14px 15px;
            border: 1px solid var(--line);
            border-radius: 12px;
            outline: none;
            background: #FFFFFF;
            color: var(--text);
            font: inherit;
            font-size: 13px;
            transition: border-color .2s, box-shadow .2s;
        }

        input::placeholder {
            color: #94A3B8;
        }

        input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
        }

        .hint {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .submit {
            width: 100%;
            min-height: 51px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 27px;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: var(--blue);
            color: #FFFFFF;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 7px 20px rgba(37, 99, 235, .16);
            transition: transform .2s, box-shadow .2s, background .2s;
        }

        .submit:hover {
            transform: translateY(-2px);
            background: var(--blue-dark);
            box-shadow: 0 10px 25px rgba(37, 99, 235, .22);
        }

        .back {
            display: block;
            margin-top: 19px;
            color: var(--blue);
            font-size: 12px;
            text-align: center;
            text-decoration: none;
        }

        .back:hover {
            color: var(--blue-dark);
            text-decoration: underline;
        }

        .divider {
            height: 1px;
            margin: 26px 0 18px;
            background: var(--line);
        }

        .footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--muted);
            font-size: 10px;
        }

        @keyframes enter {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 480px) {
            .topbar {
                align-items: flex-start;
            }

            .status {
                font-size: 9px;
            }

            .card {
                border-radius: 19px;
            }

            .footer {
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
    <div class="orb one"></div>
    <div class="orb two"></div>

    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-icon">◇</div>
                <div class="brand-name">Crypto<span>X</span></div>
            </div>

            <div class="status">
                <span class="status-dot"></span>
                SECURE RESET
            </div>
        </header>

        <section class="card">
            <div class="eyebrow">Account recovery / 02</div>

            <h1>
                Access lost?
                <br>
                <span class="gradient-text">Take it back.</span>
            </h1>

            <p class="intro">
                Verify your recovery key and create a new password.
                Your recovery key can be used only once.
            </p>

            <div class="steps" aria-hidden="true">
                <span class="step active"></span>
                <span class="step active"></span>
                <span class="step active"></span>
            </div>

            <?php if ($message !== ""): ?>
                <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>" role="status">
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <?php if ($messageType !== "success"): ?>
                <form method="POST" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>">

                    <div class="field">
                        <label for="email">
                            Registered email
                            <span>01 / 03</span>
                        </label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email"
                            required>
                    </div>

                    <div class="field">
                        <label for="recovery_key">
                            Recovery key
                            <span>02 / 03</span>
                        </label>
                        <input type="password" id="recovery_key" name="recovery_key"
                            placeholder="Paste your 64-character key" autocomplete="off" spellcheck="false" required>
                        <p class="hint">
                            Use the recovery key you saved when setting up account recovery.
                        </p>
                    </div>

                    <div class="field">
                        <label for="new_password">
                            New password
                            <span>03 / 03</span>
                        </label>
                        <input type="password" id="new_password" name="new_password" placeholder="At least 8 characters"
                            minlength="8" autocomplete="new-password" required>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Enter the password again" minlength="8" autocomplete="new-password" required>
                    </div>

                    <button class="submit" type="submit">
                        Reset password <span aria-hidden="true">↗</span>
                    </button>
                </form>
            <?php endif; ?>

            <a class="back" href="login.php">← Return to login</a>

            <div class="divider"></div>

            <footer class="footer">
                <span>◈ CryptoX Security Protocol</span>
                <span>PRIVATE · ONE-TIME KEY</span>
            </footer>
        </section>
    </main>
</body>

</html>