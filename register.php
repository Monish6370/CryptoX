<?php

require_once "db.php";

mysqli_report(MYSQLI_REPORT_OFF);

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $message = "Please fill all fields";
        $messageType = "error";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters";
        $messageType = "error";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $referrerId = filter_input(INPUT_GET, "ref", FILTER_VALIDATE_INT);
        $referrerId = ($referrerId && $referrerId > 0) ? $referrerId : null;

        if ($referrerId !== null) {
            $sql = "SELECT id FROM users WHERE id = ? LIMIT 1";
            $check = mysqli_prepare($conn, $sql);

            if (!$check) {
                $message = "Registration failed. Please try again.";
                $messageType = "error";
                $referrerId = null;
            } else {
                mysqli_stmt_bind_param($check, "i", $referrerId);
                mysqli_stmt_execute($check);
                $checkResult = mysqli_stmt_get_result($check);
                $validReferrer = mysqli_fetch_assoc($checkResult);
                mysqli_stmt_close($check);

                if (!$validReferrer) {
                    $referrerId = null;
                }
            }
        }

        if ($message === "") {
            mysqli_begin_transaction($conn);

            try {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO users (name, email, password, referred_by)
                        VALUES (?, ?, ?, ?)"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare registration.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssi",
                    $name,
                    $email,
                    $hashedPassword,
                    $referrerId
                );

                if (!mysqli_stmt_execute($stmt)) {
                    $errorCode = mysqli_stmt_errno($stmt);
                    mysqli_stmt_close($stmt);

                    if ($errorCode === 1062) {
                        throw new DomainException("Email is already registered");
                    }

                    throw new RuntimeException("Could not create account.");
                }

                $newUserId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Create the new user's wallet automatically.
                $wallet = mysqli_prepare(
                    $conn,
                    "INSERT INTO wallets (user_id, balance)
    VALUES (?, 0.00)"
                );

                if (!$wallet) {
                    throw new RuntimeException("Could not prepare wallet creation.");
                }

                mysqli_stmt_bind_param($wallet, "i", $newUserId);

                if (!mysqli_stmt_execute($wallet)) {
                    mysqli_stmt_close($wallet);
                    throw new RuntimeException("Could not create wallet.");
                }

                mysqli_stmt_close($wallet);

                mysqli_commit($conn);

                $message = "Registration successful! You can now log in.";
                $messageType = "success";
            } catch (DomainException $e) {
                mysqli_rollback($conn);
                $message = $e->getMessage();
                $messageType = "error";
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $message = "Registration failed. Please check your database setup and try again.";
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
    <title>Create Account | CryptoX</title>

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

        .register-page {
            min-height: 100vh;
            padding: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 15% 20%, rgba(37, 99, 235, 0.08), transparent 42%),
                #F4F6FA;
        }

        .register-layout {
            width: 100%;
            max-width: 1050px;
            min-height: 650px;
            display: grid;
            grid-template-columns: 1fr 0.9fr;
            overflow: hidden;
            border: 1px solid #E2E8F0;
            border-radius: 28px;
            background: #FFFFFF;
            box-shadow: 0 25px 70px rgba(23, 36, 59, 0.09);
        }

        .register-visual {
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

        .register-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #17243B;
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -0.7px;
        }

        .register-brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 22px;
        }

        .register-copy {
            position: relative;
            z-index: 2;
            margin-top: 40px;
        }

        .register-tag {
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

        .register-copy h2 {
            margin: 24px 0 16px;
            color: #17243B;
            font-size: clamp(32px, 4vw, 47px);
            line-height: 1.12;
            letter-spacing: -1.8px;
        }

        .register-copy p {
            max-width: 350px;
            color: #64748B;
            font-size: 15px;
            line-height: 1.8;
        }

        .register-art {
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

        .register-coin {
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

        .register-footer {
            position: relative;
            z-index: 2;
            color: #64748B;
            font-size: 12px;
        }

        .register-form-side {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 42px;
            background: #FFFFFF;
        }

        .register-form-content {
            width: 100%;
            max-width: 360px;
        }

        .register-form-content h1 {
            margin: 0 0 10px;
            color: #17243B;
            font-size: 34px;
            letter-spacing: -1.3px;
        }

        .register-subtitle {
            margin-bottom: 22px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.7;
        }

        .register-form-content form {
            display: grid;
            gap: 9px;
        }

        .register-form-content label {
            margin-top: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        .register-form-content input {
            width: 100%;
            padding: 14px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            outline: none;
            background: #F8FAFC;
            color: #17243B;
            font: inherit;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .register-form-content input::placeholder {
            color: #94A3B8;
        }

        .register-form-content input:focus {
            border-color: #2563EB;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .auth-submit {
            margin-top: 17px;
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

        .register-alert {
            width: 100%;
            margin: 16px 0;
            padding: 14px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .register-success {
            color: #166534;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
        }

        .register-error {
            color: #B91C1C;
            background: #FEF2F2;
            border: 1px solid #FECACA;
        }

        .auth-switch {
            margin: 22px 0 12px;
            color: #64748B;
            text-align: center;
            font-size: 13px;
        }

        .auth-switch a {
            color: #2563EB;
            font-weight: 700;
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
            .register-page {
                padding: 20px;
            }

            .register-layout {
                max-width: 470px;
                grid-template-columns: 1fr;
            }

            .register-visual {
                min-height: 270px;
                padding: 28px;
            }

            .register-copy {
                margin-top: 30px;
            }

            .register-copy h2 {
                max-width: 280px;
                margin: 18px 0 10px;
                font-size: 32px;
            }

            .register-copy p {
                max-width: 280px;
                font-size: 13px;
            }

            .register-art {
                top: 35%;
                right: -65px;
                width: 150px;
                height: 150px;
            }

            .register-coin {
                width: 100px;
                height: 100px;
                font-size: 52px;
            }

            .register-footer {
                margin-top: 28px;
            }

            .register-form-side {
                padding: 32px 28px;
            }
        }

        @media (max-width: 380px) {
            .register-page {
                padding: 10px;
            }

            .register-visual,
            .register-form-side {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body>

    <div class="register-page">
        <div class="register-layout">

            <section class="register-visual">

                <a href="index.php" class="register-brand">
                    <span class="register-brand-icon">◇</span>
                    <span>CryptoX</span>
                </a>

                <div class="register-art" aria-hidden="true">
                    <div class="register-coin">₿</div>
                </div>

                <div class="register-copy">
                    <span class="register-tag">START YOUR JOURNEY</span>

                    <h2>Your next chapter<br>starts here.</h2>

                    <p>
                        Create your account and bring your digital
                        asset experience together in one place.
                    </p>
                </div>

                <div class="register-footer">
                    Simple account access. One CryptoX experience.
                </div>

            </section>

            <section class="register-form-side">
                <div class="register-form-content">

                    <h1>Create Account</h1>

                    <p class="register-subtitle">
                        Join CryptoX with a few simple details.
                    </p>

                    <?php if ($message !== ""): ?>
                        <div
                            class="register-alert <?= $messageType === "success" ? "register-success" : "register-error" ?>">
                            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                        </div>
                    <?php endif; ?>

                    <form
                        action="<?= htmlspecialchars('register.php' . (isset($_GET['ref']) && ctype_digit($_GET['ref']) ? '?ref=' . $_GET['ref'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                        method="POST">

                        <label for="name">Full Name</label>

                        <input type="text" id="name" name="name" placeholder="Enter your full name" autocomplete="name"
                            required>

                        <label for="email">Email Address</label>

                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email"
                            required>

                        <label for="password">Password</label>

                        <input type="password" id="password" name="password" placeholder="At least 8 characters"
                            minlength="8" autocomplete="new-password" required>

                        <button type="submit" class="auth-submit">
                            Create Account →
                        </button>

                    </form>

                    <p class="auth-switch">
                        Already have an account?
                        <a href="login.php">Login</a>
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