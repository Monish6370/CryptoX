
<?php
session_start();
require_once "csrf.php";
$csrfToken = csrf_token();

if (!isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

require_once "db.php";

$sql = "SELECT name,email FROM users WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$name = $user["name"];
$email = $user["email"];

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
        $message = "Your session expired. Refresh the page and try again.";
        $messageType = "error";
    } elseif ($name === "" || $email === "") {
        $message = "Name and Email are required.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a Valid Email Address";
        $messageType = "error";
    } elseif (strlen($name) > 100 || strlen($email) > 255) {
        $message = "Character limit exceeds";
        $messageType = "error";
    } else {
        $checkSql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "si", $email, $_SESSION["user_id"]);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $existingUser = mysqli_fetch_assoc($checkResult);

        if ($existingUser) {
            $message = "This email is already being used by another account";
            $messageType = "error";
        } else {
            $updateSql = "UPDATE users SET name = ?,email = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);

            mysqli_stmt_bind_param($updateStmt, "ssi", $name, $email, $_SESSION["user_id"]);

            mysqli_stmt_execute($updateStmt);

            $_SESSION["user_name"] = $name;
            $_SESSION["user_email"] = $email;

            header("location: profile.php?updated=1");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | CryptoX</title>

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

        .edit-page {
            min-height: 100vh;
            padding: 35px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 10% 10%, rgba(37, 99, 235, 0.07), transparent 42%),
                #F4F6FA;
        }

        .edit-card {
            width: 100%;
            max-width: 520px;
            padding: 35px;
            border: 1px solid #E2E8F0;
            border-radius: 22px;
            background: #FFFFFF;
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.08);
        }

        .edit-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 35px;
            font-size: 21px;
            font-weight: 700;
            color: #17243B;
        }

        .edit-brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 23px;
        }

        .edit-tag {
            color: #2563EB;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        h1 {
            margin: 12px 0 8px;
            font-size: 32px;
            letter-spacing: -0.8px;
            color: #17243B;
        }

        .edit-subtitle {
            margin-bottom: 28px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.7;
        }

        .edit-form label {
            display: block;
            margin: 20px 0 9px;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        .edit-form input {
            width: 100%;
            padding: 15px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            outline: none;
            background: #F8FAFC;
            color: #17243B;
            font-size: 14px;
            transition: border-color 0.25s ease,
                box-shadow 0.25s ease;
        }

        .edit-form input:focus {
            border-color: #2563EB;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .edit-hint {
            margin-top: 8px;
            color: #64748B;
            font-size: 12px;
            line-height: 1.5;
        }

        .edit-submit {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-top: 28px;
            padding: 15px 17px;
            border: 1px solid #2563EB;
            border-radius: 11px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 5px 18px rgba(37, 99, 235, 0.15);
            transition: transform 0.25s ease,
                box-shadow 0.25s ease,
                background 0.25s ease;
        }

        .edit-submit span {
            font-size: 19px;
            transition: transform 0.25s ease;
        }

        .edit-submit:hover {
            transform: translateY(-3px);
            background: #1D4ED8;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25);
        }

        .edit-submit:hover span {
            transform: translateX(4px);
        }

        .edit-links {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .edit-links a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            background: #FFFFFF;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
        }

        .edit-links a span {
            color: #2563EB;
            font-size: 19px;
            transition: transform 0.25s ease;
        }

        .edit-links a:hover {
            transform: translateY(-3px);
            border-color: #2563EB;
            background: #EFF6FF;
            color: #1D4ED8;
            box-shadow: 0 8px 22px rgba(37, 99, 235, 0.10);
        }

        .edit-links a:hover span {
            transform: translateX(4px);
        }

        .error-message {
            padding: 12px 15px;
            margin: 18px 0;
            border: 1px solid #FECACA;
            border-radius: 10px;
            background: #FEF2F2;
            color: #B91C1C;
            font-size: 14px;
        }

        .success-message {
            padding: 12px 15px;
            margin: 18px 0;
            border: 1px solid #86EFAC;
            border-radius: 10px;
            background: #F0FDF4;
            color: #15803D;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .edit-card {
                padding: 24px;
            }

            h1 {
                font-size: 28px;
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

    <div class="edit-page">
        <main class="edit-card">

            <div class="edit-brand">
                <span class="edit-brand-icon">◇</span>
                <span>CryptoX</span>
            </div>

            <span class="edit-tag">ACCOUNT SETTINGS</span>

            <h1>Edit Profile</h1>

            <?php if (!empty($message)): ?>
                <p class="<?= $messageType === 'error' ? 'error-message' : 'success-message' ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <p class="edit-subtitle">
                Keep your account information accurate and up to date.
            </p>

            <form class="edit-form" action="edit_profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <label for="name">Full Name</label>
                <input type="text" id="name" name="name"
                    value="<?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="100"
                    autocomplete="name" required>

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                    value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255"
                    autocomplete="email" required>

                <p class="edit-hint">
                    Your updated details will appear on your profile after saving.
                </p>

                <button type="submit" class="edit-submit">
                    Save Changes <span>↗</span>
                </button>

            </form>

            <div class="edit-links">
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
