<?php

session_start();

require_once "csrf.php";
$csrfToken = csrf_token();

require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {

        $message = "Your session expired. Refresh the page and try again.";

    } else {

        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt === false) {

            $message = "Something went wrong. Please try again.";

        } else {

            mysqli_stmt_bind_param($stmt, "s", $email);

            if (mysqli_stmt_execute($stmt)) {

                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) > 0) {

                    $user = mysqli_fetch_assoc($result);

                    if (password_verify($password, $user["password"])) {

                        if ($user["status"] !== "active") {

                            $message = "Your account is blocked. Please contact support.";

                        } elseif ($user["role"] === "admin") {

                            mysqli_stmt_close($stmt);
                            mysqli_close($conn);

                            header("Location: admin/login.php");
                            exit;

                        } else {

                            session_regenerate_id(true);

                            $_SESSION["user_id"] = (int) $user["id"];
                            $_SESSION["user_name"] = $user["name"];
                            $_SESSION["user_email"] = $user["email"];
                            $_SESSION["role"] = $user["role"];

                            mysqli_stmt_close($stmt);
                            mysqli_close($conn);

                            header("Location: dashboard.php");
                            exit;
                        }

                    } else {

                        $message = "Invalid email or password.";

                    }

                } else {

                    $message = "Invalid email or password.";

                }

            } else {

                $message = "Something went wrong. Please try again.";

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

    <title>Login | CryptoX</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #17243B;
            background: #F4F6FA;
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
                radial-gradient(ellipse at 15% 20%, rgba(37, 99, 235, 0.08), transparent 42%),
                #F4F6FA;
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
            box-shadow: 0 25px 70px rgba(23, 36, 59, 0.09);
        }

        .login-visual {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 42px;
            background:
                radial-gradient(circle at 75% 45%, rgba(96, 165, 250, 0.24), transparent 42%),
                linear-gradient(145deg, #EFF6FF, #DBEAFE 75%);
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #17243B;
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
            background: #2563EB;
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
            border: 1px solid #BFDBFE;
            border-radius: 30px;
            color: #1D4ED8;
            background: rgba(255, 255, 255, 0.7);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        .login-visual h2 {
            margin: 24px 0 16px;
            color: #17243B;
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
            border: 1px solid rgba(37, 99, 235, 0.18);
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.04);
            box-shadow:
                0 0 0 22px rgba(37, 99, 235, 0.035),
                0 0 80px rgba(37, 99, 235, 0.12);
        }

        .crypto-coin {
            width: 145px;
            height: 145px;
            display: grid;
            place-items: center;
            border: 8px solid #BFDBFE;
            border-radius: 50%;
            background: linear-gradient(145deg, #60A5FA, #2563EB 65%, #1D4ED8);
            box-shadow: 0 12px 40px rgba(37, 99, 235, 0.2);
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
            color: #17243B;
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
            color: #17243B;
            font: inherit;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .login-form-content input::placeholder {
            color: #94A3B8;
        }

        .login-form-content input:focus {
            border-color: #2563EB;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .auth-submit {
            margin-top: 20px;
            padding: 15px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(110deg, #2563EB, #1D4ED8);
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .auth-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 9px 25px rgba(37, 99, 235, 0.22);
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

        .auth-switch {
            margin: 25px 0 14px;
            color: #64748B;
            text-align: center;
            font-size: 13px;
        }

        .auth-switch a {
            color: #2563EB;
            font-weight: 700;
        }

        .forgot-password {
            display: block;
            margin-top: 16px;
            color: #2563EB;
            text-align: right;
            font-size: 13px;
            font-weight: 600;
        }

        .forgot-password:hover {
            color: #1D4ED8;
            text-decoration: underline;
        }

        .auth-back {
            display: block;
            margin-top: 12px;
            color: #64748B;
            text-align: center;
            font-size: 13px;
        }

        .auth-back:hover,
        .auth-switch a:hover {
            color: #1D4ED8;
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
            background: #2563EB;
            color: #FFFFFF;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.15);
        }
    </style>

</head>

<body>

    <div class="login-page">

        <div class="login-layout">

            <section class="login-visual">

                <a href="index.php" class="login-brand">

                    <span class="login-brand-icon">◇</span>

                    <span>CryptoX</span>

                </a>

                <div class="crypto-art" aria-hidden="true">

                    <div class="crypto-coin">₿</div>

                </div>

                <div class="login-visual-copy">

                    <span class="login-tag">YOUR CRYPTO JOURNEY</span>

                    <h2>Own your<br>financial future.</h2>

                    <p>
                        Explore digital assets and manage your CryptoX
                        account from one place.
                    </p>

                </div>

                <div class="login-visual-footer">
                    A clearer way to explore digital assets.
                </div>

            </section>

            <section class="login-form-side">

                <div class="login-form-content">

                    <div class="login-type-switch">
                        <a href="login.php" class="active">User Login</a>
                        <a href="admin/login.php">Admin Login</a>
                    </div>

                    <h1>Welcome Back</h1>

                    <p class="login-subtitle">
                        Sign in to continue to your CryptoX account.
                    </p>

                    <?php if ($message !== ""): ?>

                        <div class="auth-message">
                            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                        </div>

                    <?php endif; ?>

                    <form action="login.php" method="POST">

                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <label for="email">Email Address</label>

                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email"
                            required>

                        <label for="password">Password</label>

                        <input type="password" id="password" name="password" placeholder="Enter your password"
                            autocomplete="current-password" required>

                        <button type="submit" class="auth-submit">
                            Login to Account →
                        </button>

                    </form>

                    <a href="forgot_password.php" class="forgot-password">
                        Forgot Password?
                    </a>

                    <p class="auth-switch">
                        Don't have an account?
                        <a href="register.php">Create Account</a>
                    </p>

                    <a href="index.php" class="auth-back">
                        ← Back to Home
                    </a>

                </div>

            </section>

        </div>

    </div>

</body>

</html>