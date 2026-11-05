
<?php

session_start();
require_once "csrf.php";
$csrfToken = csrf_token();
$deleteError = "";

if (!isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
        $deleteError = "Your session expired. Refresh the page and try again.";
    } elseif (isset($_POST["confirm_delete"])) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            die("Query preparation failed");
        }

        $userId = $_SESSION["user_id"];
        mysqli_stmt_bind_param($stmt, "i", $userId);

        if (mysqli_stmt_execute($stmt) === true) {
            if (mysqli_stmt_affected_rows($stmt) === 1) {
                mysqli_stmt_close($stmt);
                mysqli_close($conn);
                session_unset();
                session_destroy();
                header("location: register.php");
                exit;
            } else {
                $deleteError = "Account could not be deleted. Please try again.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $deleteError = "Something went wrong. Please try again.";
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
    <title>Delete Account | CryptoX</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: #F4F6FA;
            color: #17243B;
        }

        .delete-page {
            min-height: 100vh;
            padding: 30px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 15% 15%, rgba(37, 99, 235, 0.08), transparent 42%),
                #F4F6FA;
        }

        .delete-card {
            width: 100%;
            max-width: 500px;
            padding: 35px;
            border: 1px solid #E2E8F0;
            border-radius: 24px;
            background: #FFFFFF;
            box-shadow: 0 20px 55px rgba(23, 36, 59, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 35px;
            font-size: 21px;
            font-weight: 700;
            color: #17243B;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 23px;
        }

        .warning-icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin-bottom: 22px;
            border: 1px solid #FECACA;
            border-radius: 17px;
            background: #FEF2F2;
            color: #DC2626;
            font-size: 28px;
            font-weight: 700;
        }

        .tag {
            color: #DC2626;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        h1 {
            margin: 12px 0;
            font-size: 30px;
            letter-spacing: -0.8px;
            color: #17243B;
        }

        .description {
            margin-bottom: 22px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.8;
        }

        .notice {
            margin-bottom: 25px;
            padding: 16px;
            border: 1px solid #FECACA;
            border-radius: 12px;
            background: #FEF2F2;
            color: #991B1B;
            font-size: 13px;
            line-height: 1.7;
        }

        .delete-button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 11px;
            background: #DC2626;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .delete-button:hover {
            transform: translateY(-2px);
            background: #B91C1C;
        }

        .cancel {
            display: block;
            margin-top: 12px;
            padding: 14px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            background: #F8FAFC;
            color: #2563EB;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .cancel:hover {
            border-color: #2563EB;
            background: #EFF6FF;
        }

        .delete-error {
            margin: 16px 0;
            padding: 12px;
            border: 1px solid #FECACA;
            border-radius: 9px;
            background: #FEF2F2;
            color: #B91C1C;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .delete-card {
                padding: 24px;
            }

            h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>
    <div class="delete-page">
        <main class="delete-card">

            <div class="brand">
                <span class="brand-icon">◇</span>
                <span>CryptoX</span>
            </div>

            <div class="warning-icon">!</div>

            <span class="tag">ACCOUNT DELETION</span>

            <h1>Delete Account?</h1>

            <p class="description">
                Are you sure you want to permanently delete your
                CryptoX account? Please review this decision carefully.
            </p>

            <div class="notice">
                <strong>Warning:</strong> This action cannot be undone.
                Your account record will be permanently deleted.
            </div>

            <?php if ($deleteError !== ""): ?>
                <p role="alert" class="delete-error">
                    <?= htmlspecialchars($deleteError, ENT_QUOTES, "UTF-8") ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="delete_account.php">
                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <button
                    type="submit"
                    name="confirm_delete"
                    value="1"
                    class="delete-button"
                    onclick="return confirm('Are you sure you want to permanently delete your account?');"
                >
                    Yes, Delete My Account
                </button>
            </form>

            <a href="dashboard.php" class="cancel">
                Cancel and Go Back
            </a>

        </main>
    </div>
</body>
</html>
