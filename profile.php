
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$profileMessage = "";

if (isset($_GET["updated"]) && $_GET["updated"] == "1") {
    $profileMessage = "Profile updated successfully!";
}

$name = $_SESSION["user_name"] ?? "";
$email = $_SESSION["user_email"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | CryptoX</title>

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

        .profile-page {
            min-height: 100vh;
            padding: 35px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 10% 10%, rgba(37, 99, 235, 0.07), transparent 42%),
                #F4F6FA;
        }

        .profile-card {
            width: 100%;
            max-width: 520px;
            padding: 35px;
            border: 1px solid #E2E8F0;
            border-radius: 22px;
            background: #FFFFFF;
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.08);
        }

        .profile-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 35px;
            font-size: 21px;
            font-weight: 700;
            color: #17243B;
        }

        .profile-brand-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 23px;
        }

        .profile-tag {
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

        .profile-subtitle {
            margin-bottom: 28px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
        }

        .detail-label {
            display: block;
            margin: 18px 0 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        .detail-value {
            width: 100%;
            padding: 15px;
            border: 1px solid #E2E8F0;
            border-radius: 11px;
            background: #F8FAFC;
            color: #17243B;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        /* Edit Profile button */
        .edit-profile-btn {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 15px 16px;
            margin-top: 22px;
            border: 1px solid #2563EB;
            border-radius: 11px;
            background: #2563EB;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 5px 18px rgba(37, 99, 235, 0.15);
            transition: transform 0.25s ease,
                box-shadow 0.25s ease,
                background 0.25s ease;
        }

        .edit-profile-btn span {
            font-size: 19px;
            transition: transform 0.25s ease;
        }

        .edit-profile-btn:hover {
            transform: translateY(-3px);
            background: #1D4ED8;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25);
        }

        .edit-profile-btn:hover span {
            transform: rotate(-12deg) scale(1.15);
        }

        /* Navigation buttons */
        .profile-links {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .profile-links a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
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

        .profile-links a span {
            color: #2563EB;
            font-size: 19px;
            transition: transform 0.25s ease;
        }

        .profile-links a:hover,
        .profile-links a:focus-visible {
            background: #EFF6FF;
            border-color: #2563EB;
            color: #1D4ED8;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.10);
            outline: none;
        }

        .profile-links a:hover span,
        .profile-links a:focus-visible span {
            transform: translateX(5px);
        }

        .profile-links a:active,
        .edit-profile-btn:active {
            transform: translateY(0) scale(0.98);
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
            .profile-card {
                padding: 24px;
            }

            h1 {
                font-size: 28px;
            }
        }

        /* Respect reduced-motion preferences */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

    <div class="profile-page">
        <main class="profile-card">

            <div class="profile-brand">
                <span class="profile-brand-icon">◇</span>
                <span>CryptoX</span>
            </div>

            <span class="profile-tag">ACCOUNT SETTINGS</span>

            <h1>My Profile</h1>

            <p class="profile-subtitle">
                Manage and view your personal account details.
            </p>

            <?php if ($profileMessage !== ""): ?>
                <p class="success-message">
                    <?= htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <label class="detail-label">Full Name</label>
            <div class="detail-value">
                <?= htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>
            </div>

            <label class="detail-label">Email Address</label>
            <div class="detail-value">
                <?= htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>
            </div>

            <a href="edit_profile.php" class="edit-profile-btn">
                Edit Profile <span>✎</span>
            </a>

            <div class="profile-links">
                <a href="dashboard.php">
                    Back to Dashboard <span>↗</span>
                </a>

                <a href="index.php">
                    Back to Home <span>↗</span>
                </a>

                <a href="logout.php">
                    Logout <span>↗</span>
                </a>
            </div>

        </main>
    </div>

</body>

</html>
