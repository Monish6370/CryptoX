
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "csrf.php";
require_once "db.php";

$csrfToken = csrf_token();
$userId = (int) $_SESSION["user_id"];
$recoveryKey = "";
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
        $message = "Your session expired. Please refresh and try again.";
        $messageType = "error";
    } else {
        $newKey = bin2hex(random_bytes(32));
        $keyHash = hash("sha256", $newKey);

        $sql = "
            INSERT INTO recovery_keys
                (user_id, key_hash, failed_attempts, locked_until, used_at)
            VALUES (?, ?, 0, NULL, NULL)
            ON DUPLICATE KEY UPDATE
                key_hash = VALUES(key_hash),
                failed_attempts = 0,
                locked_until = NULL,
                used_at = NULL
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            error_log("Recovery key query preparation failed.");
            $message = "Something went wrong. Please try again.";
            $messageType = "error";
        } else {
            mysqli_stmt_bind_param($stmt, "is", $userId, $keyHash);

            if (mysqli_stmt_execute($stmt)) {
                $recoveryKey = $newKey;
                $message = "Your new recovery key is ready. Save it securely.";
                $messageType = "success";
            } else {
                error_log("Recovery key could not be saved.");
                $message = "Could not generate the recovery key. Please try again.";
                $messageType = "error";
            }

            mysqli_stmt_close($stmt);
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
    <meta name="theme-color" content="#F4F6FA">
    <title>Recovery Key | CryptoX</title>

    <style>
        :root {
            color-scheme: light;
            --bg: #F4F6FA;
            --panel: #FFFFFF;
            --blue: #2563EB;
            --blue-light: #EFF6FF;
            --blue-dark: #1D4ED8;
            --muted: #64748B;
            --line: #E2E8F0;
            --text: #17243B;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 35px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background:
                radial-gradient(
                    ellipse at 15% 20%,
                    rgba(37, 99, 235, 0.07) 0%,
                    transparent 42%
                ),
                var(--bg);
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        .shell {
            width: 100%;
            max-width: 760px;
            animation: enter .5s ease both;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 22px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: var(--text);
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -.7px;
            text-decoration: none;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--blue);
            color: #FFFFFF;
            font-size: 23px;
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
            background: #EFF6FF;
            color: var(--blue-dark);
            font-size: 10px;
            font-weight: 700;
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
            padding: clamp(24px, 5vw, 38px);
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--panel);
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.08);
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 12%;
            width: 76%;
            height: 3px;
            background: linear-gradient(
                90deg,
                transparent,
                var(--blue),
                #60A5FA,
                transparent
            );
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--blue);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 19px;
            height: 2px;
            background: var(--blue);
        }

        h1 {
            margin: 18px 0 12px;
            font-size: clamp(30px, 5vw, 40px);
            line-height: 1.15;
            letter-spacing: -1.4px;
            color: var(--text);
        }

        .accent {
            color: var(--blue);
        }

        .intro {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .security-panel {
            display: flex;
            align-items: center;
            gap: 17px;
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #BFDBFE;
            border-radius: 16px;
            background: #F8FAFF;
        }

        .shield {
            flex-shrink: 0;
            width: 55px;
            height: 55px;
            display: grid;
            place-items: center;
            border: 1px solid #BFDBFE;
            border-radius: 15px;
            background: #EFF6FF;
            color: var(--blue);
            font-size: 27px;
        }

        .security-panel strong {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: var(--text);
        }

        .security-panel p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .message {
            margin: 20px 0;
            padding: 14px 16px;
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

        .key-label {
            display: block;
            margin: 24px 0 10px;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .key-box {
            padding: 17px;
            border: 1px solid #BFDBFE;
            border-radius: 13px;
            background: #F8FAFC;
            color: #1D4ED8;
            font: 12px/1.9 Consolas, "Courier New", monospace;
            overflow-wrap: anywhere;
            user-select: all;
        }

        .warning {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            margin-top: 15px;
            padding: 14px;
            border: 1px solid #FDE68A;
            border-radius: 12px;
            background: #FFFBEB;
            color: #92400E;
            font-size: 12px;
            line-height: 1.7;
        }

        .warning-icon {
            color: #D97706;
            font-size: 17px;
        }

        .warning strong {
            color: #92400E;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 25px;
        }

        .button {
            min-height: 49px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            padding: 13px 16px;
            border: 1px solid transparent;
            border-radius: 11px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s, border-color .2s;
        }

        .primary {
            background: var(--blue);
            color: #FFFFFF;
            box-shadow: 0 7px 20px rgba(37, 99, 235, 0.16);
        }

        .secondary {
            border-color: var(--line);
            background: #FFFFFF;
            color: var(--blue);
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .primary:hover {
            background: var(--blue-dark);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.24);
        }

        .secondary:hover {
            border-color: var(--blue);
            background: #EFF6FF;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #60A5FA;
            outline-offset: 4px;
        }

        .copy-button {
            width: 100%;
            margin-top: 12px;
        }

        .divider {
            height: 1px;
            margin: 28px 0 19px;
            background: var(--line);
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            color: #64748B;
            font-size: 11px;
            line-height: 1.7;
        }

        .footer a {
            color: var(--blue);
            text-decoration: none;
        }

        .footer a:hover {
            color: var(--blue-dark);
            text-decoration: underline;
        }

        @keyframes enter {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 22px 14px;
            }

            .card {
                border-radius: 20px;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .security-panel {
                align-items: flex-start;
                padding: 16px;
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
    <main class="shell">

        <header class="topbar">
            <a href="dashboard.php" class="brand">
                <span class="brand-icon">◇</span>
                <span class="brand-name">Crypto<span>X</span></span>
            </a>

            <div class="status">
                <span class="status-dot"></span>
                SECURITY CENTER
            </div>
        </header>

        <section class="card">

            <div class="eyebrow">Account protection / 01</div>

            <h1>
                Your key.<br>
                <span class="accent">Your control.</span>
            </h1>

            <p class="intro">
                Create a private recovery key to regain access to your
                account if you forget your password.
            </p>

            <div class="security-panel">
                <div class="shield" aria-hidden="true">◇</div>
                <div>
                    <strong>Secure account recovery</strong>
                    <p>
                        Your recovery key is generated securely.
                        Only its hash is stored in the database.
                    </p>
                </div>
            </div>

            <?php if ($message !== ""): ?>
                <div
                    class="message <?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>"
                    role="status"
                >
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <?php if ($recoveryKey !== ""): ?>
                <label class="key-label" for="recoveryKey">
                    YOUR NEW RECOVERY KEY
                </label>

                <div class="key-box" id="recoveryKey"><?= htmlspecialchars($recoveryKey, ENT_QUOTES, "UTF-8") ?></div>

                <button
                    class="button secondary copy-button"
                    type="button"
                    id="copyButton"
                    onclick="copyRecoveryKey()"
                >
                    <span aria-hidden="true">▣</span>
                    Copy recovery key
                </button>

                <div class="warning">
                    <span class="warning-icon" aria-hidden="true">⚠</span>
                    <div>
                        <strong>Keep this key private.</strong>
                        Save it in a secure location. The readable key
                        is not stored in the database. Generating another
                        key invalidates the previous one.
                    </div>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="generate_recovery_key.php"
                onsubmit="return confirm('Generate a new key? Your previous recovery key will stop working.');"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
                >

                <div class="actions">
                    <button class="button primary" type="submit">
                        Generate new key <span aria-hidden="true">↗</span>
                    </button>

                    <a class="button secondary" href="dashboard.php">
                        ← Dashboard
                    </a>
                </div>
            </form>

            <div class="divider"></div>

            <footer class="footer">
                <span>◈ CryptoX Security</span>
                <a href="dashboard.php">← Back to dashboard</a>
            </footer>

        </section>
    </main>

    <script>
        async function copyRecoveryKey() {
            const keyElement = document.getElementById("recoveryKey");
            const button = document.getElementById("copyButton");

            if (!keyElement || !button) {
                return;
            }

            const key = keyElement.textContent.trim();

            try {
                await navigator.clipboard.writeText(key);
                button.textContent = "✓ Copied to clipboard";
            } catch (error) {
                alert("Copy failed. Please select and copy the key manually.");
            }
        }
    </script>
</body>

</html>
