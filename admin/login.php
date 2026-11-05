<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../csrf.php";
require_once "../db.php";

$message = "";
$csrfToken = csrf_token();

// Already logged-in admin
if (
    isset($_SESSION["admin_user_id"]) &&
    ($_SESSION["admin_role"] ?? "") === "admin"
) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {

        $message = "Your session expired. Refresh the page and try again.";

    } else {

        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        $sql = "SELECT id, name, email, password, role, status
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $email);

            if (mysqli_stmt_execute($stmt)) {

                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);

                if (
                    $user &&
                    password_verify($password, $user["password"]) &&
                    $user["role"] === "admin" &&
                    $user["status"] === "active"
                ) {

                    session_regenerate_id(true);

                    // Store admin-specific session data.
                    $_SESSION["admin_user_id"] = (int) $user["id"];
                    $_SESSION["admin_name"] = $user["name"];
                    $_SESSION["admin_email"] = $user["email"];
                    $_SESSION["admin_role"] = $user["role"];

                    mysqli_stmt_close($stmt);
                    mysqli_close($conn);

                    header("Location: dashboard.php");
                    exit;

                } else {

                    $message = "Invalid admin email or password.";

                }

            } else {

                $message = "Something went wrong. Please try again.";

            }

            mysqli_stmt_close($stmt);

        } else {

            $message = "Something went wrong. Please try again.";

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

    <title>Admin Login | CryptoX</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #1E293B;
            background: #F1F5F9;
        }

        a {
            text-decoration: none;
        }

        .login-page {
            min-height: 100vh;
            padding: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 15% 20%, rgba(13, 148, 136, 0.09), transparent 42%),
                #F1F5F9;
        }

        .login-layout {
            width: 100%;
            max-width: 1050px;
            min-height: 610px;
            display: grid;
            grid-template-columns: 1fr 0.9fr;
            overflow: hidden;
            border: 1px solid #E2E8F0;
            border-radius: 28px;
            background: #FFFFFF;
            box-shadow: 0 25px 70px rgba(30, 41, 59, 0.09);
        }

        .login-visual {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 42px;
            background:
                radial-gradient(circle at 75% 45%, rgba(45, 212, 191, 0.22), transparent 42%),
                linear-gradient(145deg, #F0FDFA, #CCFBF1 75%);
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #1E293B;
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -0.7px;
        }

        .login-brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: #0D9488;
            color: #FFFFFF;
            font-size: 22px;
        }

        .login-visual-copy {
            position: relative;
            z-index: 2;
            margin-top: 45px;
        }

        .login-tag {
            display: inline-block;
            padding: 9px 13px;
            border: 1px solid #99F6E4;
            border-radius: 30px;
            color: #0F766E;
            background: rgba(255, 255, 255, 0.75);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        .login-visual h2 {
            margin: 24px 0 16px;
            color: #1E293B;
            font-size: clamp(32px, 4vw, 47px);
            line-height: 1.12;
            letter-spacing: -1.8px;
        }

        .login-visual-copy p {
            max-width: 350px;
            color: #64748B;
            font-size: 15px;
            line-height: 1.8;
        }

        .crypto-art {
            position: absolute;
            top: 37%;
            right: -25px;
            width: 220px;
            height: 220px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(13, 148, 136, 0.2);
            border-radius: 50%;
            background: rgba(13, 148, 136, 0.04);
            box-shadow:
                0 0 0 22px rgba(13, 148, 136, 0.035),
                0 0 80px rgba(13, 148, 136, 0.12);
        }

        .crypto-coin {
            width: 145px;
            height: 145px;
            display: grid;
            place-items: center;
            border: 8px solid #99F6E4;
            border-radius: 50%;
            background: linear-gradient(145deg, #5EEAD4, #0D9488 65%, #0F766E);
            box-shadow: 0 12px 40px rgba(13, 148, 136, 0.2);
            color: #FFFFFF;
            font-size: 76px;
            font-weight: 700;
            transform: rotate(-12deg);
        }

        .login-visual-footer {
            position: relative;
            z-index: 2;
            color: #64748B;
            font-size: 12px;
        }

        .login-form-side {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 48px 42px;
            background: #FFFFFF;
        }

        .login-form-content {
            width: 100%;
            max-width: 360px;
        }

        .login-form-content h1 {
            margin: 0 0 10px;
            color: #1E293B;
            font-size: 34px;
            letter-spacing: -1.3px;
        }

        .login-subtitle {
            margin-bottom: 32px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.7;
        }

        .login-form-content form {
            display: grid;
            gap: 10px;
        }

        .login-form-content label {
            margin-top: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        .login-form-content input {
            width: 100%;
            padding: 15px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            outline: none;
            background: #F8FAFC;
            color: #1E293B;
            font: inherit;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .login-form-content input::placeholder {
            color: #94A3B8;
        }

        .login-form-content input:focus {
            border-color: #0D9488;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .auth-submit {
            margin-top: 20px;
            padding: 15px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(110deg, #0D9488, #0F766E);
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .auth-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 9px 25px rgba(13, 148, 136, 0.2);
        }

        .auth-message {
            margin-bottom: 18px;
            padding: 13px;
            border: 1px solid #FECACA;
            border-radius: 10px;
            background: #FEF2F2;
            color: #B91C1C;
            font-size: 14px;
            line-height: 1.5;
        }

        .login-type-switch {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            padding: 5px;
            border-radius: 12px;
            background: #F1F5F9;
        }

        .login-type-switch a {
            flex: 1;
            padding: 12px;
            border-radius: 9px;
            color: #64748B;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
        }

        .login-type-switch a.active {
            background: #0D9488;
            color: #FFFFFF;
            box-shadow: 0 3px 8px rgba(13, 148, 136, 0.15);
        }

        .auth-back {
            display: block;
            margin-top: 12px;
            color: #64748B;
            text-align: center;
            font-size: 13px;
        }

        .auth-back:hover {
            color: #0D9488;
        }

        @media (max-width: 760px) {
            .login-page {
                padding: 20px;
            }

            .login-layout {
                max-width: 470px;
                grid-template-columns: 1fr;
            }

            .login-visual {
                min-height: 280px;
                padding: 28px;
            }

            .login-visual-copy {
                margin-top: 35px;
            }

            .login-visual h2 {
                max-width: 280px;
                margin: 18px 0 10px;
                font-size: 32px;
            }

            .login-visual-copy p {
                max-width: 280px;
                font-size: 13px;
            }

            .crypto-art {
                top: 35%;
                right: -65px;
                width: 150px;
                height: 150px;
            }

            .crypto-coin {
                width: 100px;
                height: 100px;
                font-size: 52px;
            }

            .login-visual-footer {
                margin-top: 28px;
            }

            .login-form-side {
                padding: 35px 28px;
            }
        }

        @media (max-width: 380px) {
            .login-page {
                padding: 10px;
            }

            .login-visual,
            .login-form-side {
                padding: 24px 20px;
            }
        }
    </style>

</head>

<body>

    <div class="login-page">

        <div class="login-layout">

            <section class="login-visual">

                <a href="../index.php" class="login-brand">

                    <span class="login-brand-icon">◇</span>

                    <span>CryptoX</span>

                </a>

                <div class="crypto-art" aria-hidden="true">

                    <div class="crypto-coin">₿</div>

                </div>

                <div class="login-visual-copy">

                    <span class="login-tag">SECURE ADMIN ACCESS</span>

                    <h2>Control your<br>platform.</h2>

                    <p>
                        Manage users, monitor trades and oversee
                        your CryptoX platform from one secure place.
                    </p>

                </div>

                <div class="login-visual-footer">
                    CryptoX Administration Portal
                </div>

            </section>

            <section class="login-form-side">

                <div class="login-form-content">

                    <div class="login-type-switch">

                        <a href="../login.php">User Login</a>

                        <a href="login.php" class="active">Admin Login</a>

                    </div>

                    <h1>Welcome Back</h1>

                    <p class="login-subtitle">
                        Sign in to access your CryptoX administration panel.
                    </p>

                    <?php if ($message !== ""): ?>

                        <div class="auth-message">
                            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                        </div>

                    <?php endif; ?>

                    <form action="login.php" method="POST">

                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>">

                        <label for="email">Admin Email</label>

                        <input type="email" id="email" name="email" placeholder="Enter admin email"
                            autocomplete="username" required>

                        <label for="password">Password</label>

                        <input type="password" id="password" name="password" placeholder="Enter your password"
                            autocomplete="current-password" required>

                        <button type="submit" class="auth-submit">
                            Login to Admin Panel →
                        </button>

                    </form>

                    <a href="../login.php" class="auth-back">
                        ← Back to User Login
                    </a>

                </div>

            </section>

        </div>

    </div>

</body>

</html>