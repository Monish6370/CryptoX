<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$rewards = [];
$pendingTotal = 0.0;
$approvedTotal = 0.0;
$paidTotal = 0.0;
$error = "";

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COALESCE(SUM(CASE WHEN status = 'pending'
            THEN reward_amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'approved'
            THEN reward_amount ELSE 0 END), 0) AS approved_total,
        COALESCE(SUM(CASE WHEN status = 'paid'
            THEN reward_amount ELSE 0 END), 0) AS paid_total
     FROM referral_rewards
     WHERE earner_user_id = ?"
);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $totals = mysqli_fetch_assoc($result);

    if ($totals) {
        $pendingTotal = (float) $totals["pending_total"];
        $approvedTotal = (float) $totals["approved_total"];
        $paidTotal = (float) $totals["paid_total"];
    }

    mysqli_stmt_close($stmt);
} else {
    $error = "Unable to load referral totals.";
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        referred_user_id,
        level_number,
        qualifying_fee,
        reward_rate,
        reward_amount,
        activity_reference,
        status,
        created_at
     FROM referral_rewards
     WHERE earner_user_id = ?
     ORDER BY id DESC
     LIMIT 100"
);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userId);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $rewards[] = $row;
        }
    } else {
        $error = "Unable to load referral history.";
    }

    mysqli_stmt_close($stmt);
} else {
    $error = "Unable to load referral history.";
}

$referredUsers = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, email, created_at
     FROM users
     WHERE referred_by = ?
     ORDER BY id DESC"
);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userId);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $referredUsers[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

$referralLink = "register.php?ref=" . $userId;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Referrals — CryptoX</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .referral-wrap {
            width: min(1100px, 92%);
            margin: 50px auto;
        }

        .referral-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin: 24px 0;
        }

        .referral-card,
        .referral-table-wrap {
            padding: 22px;
            border: 1px solid #303746;
            border-radius: 14px;
            background: #151b27;
            color: #f5f7fb;
        }

        .referral-card p {
            margin: 0 0 10px;
            color: #b8c1d1;
        }

        .referral-card h2 {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .referral-link {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .referral-link input {
            flex: 1;
            min-width: 220px;
            padding: 12px;
            border-radius: 8px;
        }

        .referral-table-wrap {
            overflow-x: auto;
        }

        .referral-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        .referral-table th,
        .referral-table td {
            padding: 13px 10px;
            text-align: left;
            border-bottom: 1px solid #303746;
        }

        .referral-table th {
            color: #b8c1d1;
        }

        .reward-status {
            text-transform: capitalize;
        }

        @media (max-width: 650px) {
            .referral-cards {
                grid-template-columns: 1fr;
            }
        }


        /* =====================================
   REFERRALS - WHITE + ROYAL BLUE THEME
===================================== */

        .referral-wrap {
            color: #17243B;
        }

        /* Referral Summary Cards */
        .referral-card,
        .referral-table-wrap {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            color: #17243B;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.035);
        }

        .referral-card p {
            color: #64748B;
        }

        .referral-card h2 {
            color: #17243B;
        }

        /* Referral Link */
        .referral-link input {
            background: #F8FAFC;
            color: #17243B;
            border: 1px solid #CBD5E1;
            outline: none;
        }

        .referral-link input:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        /* Copy Button */
        .referral-link .button.primary {
            background: #2563EB;
            color: #FFFFFF;
            border: none;
        }

        .referral-link .button.primary:hover {
            background: #1D4ED8;
        }

        /* Copy Message */
        #copyMessage {
            color: #16A34A;
            font-size: 13px;
        }

        /* Section Headings */
        .referral-wrap section>h2 {
            color: #17243B;
            margin-bottom: 16px;
            font-size: 21px;
        }

        /* Tables */
        .referral-table {
            background: #FFFFFF;
        }

        .referral-table th {
            color: #64748B;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
        }

        .referral-table td {
            color: #334155;
            border-bottom: 1px solid #F1F5F9;
        }

        .referral-table tbody tr:hover {
            background: #F8FAFC;
        }

        .referral-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Reward Status */
        .reward-status {
            color: #2563EB;
        }

        /* Error Message */
        .referral-card:has(+ .referral-cards) {
            color: #B91C1C;
        }

        /* Footer Note */
        .referral-wrap .fine-print {
            color: #64748B;
        }

        /* Mobile */
        @media (max-width: 650px) {
            .referral-wrap {
                margin-top: 30px;
            }

            .referral-card {
                padding: 20px;
            }

            .referral-link input {
                min-width: 100%;
            }
        }
    </style>
</head>

<body class="crypto">
    <header class="header">
        <a class="brand" href="index.php">
            <span class="brand-mark">◈</span>CryptoX
        </a>
        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="index.php?page=trade">Trade</a>
            <a href="dashboard.php">My Account</a>
        </nav>
    </header>

    <main class="referral-wrap">
        <div class="page-heading">
            <span class="section-kicker">CRYPTOX / REFERRALS</span>
            <h1>My referral rewards</h1>
            <p class="section-intro">
                Review your recorded demo referral rewards and their status.
            </p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="referral-card">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <div class="referral-cards">
            <div class="referral-card">
                <p>Pending rewards</p>
                <h2>$<?= number_format($pendingTotal, 8) ?></h2>
            </div>
            <div class="referral-card">
                <p>Approved rewards</p>
                <h2>$<?= number_format($approvedTotal, 8) ?></h2>
            </div>
            <div class="referral-card">
                <p>Paid rewards</p>
                <h2>$<?= number_format($paidTotal, 8) ?></h2>
            </div>
        </div>

        <section class="referral-card">
            <h2>Invite someone</h2>
            <p>Your referral link uses your CryptoX user ID.</p>

            <div class="referral-link">
                <input id="referralLink" type="text" readonly
                    value="<?= htmlspecialchars($referralLink, ENT_QUOTES, "UTF-8") ?>" aria-label="Your referral link">
                <button class="button primary" type="button" onclick="copyReferralLink()">Copy link</button>
            </div>

            <p id="copyMessage" role="status"></p>
        </section>

        <section style="margin-top: 28px;">
            <h2>My Referred Users</h2>

            <div class="referral-table-wrap">
                <table class="referral-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($referredUsers)): ?>
                            <tr>
                                <td colspan="4">No referred users yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($referredUsers as $referred): ?>
                                <tr>
                                    <td>
                                        <?= (int) $referred["id"] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($referred["name"], ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($referred["email"], ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($referred["created_at"], ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="margin-top: 28px;">
            <h2>Reward history</h2>

            <div class="referral-table-wrap">
                <table class="referral-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Referred user ID</th>
                            <th>Level</th>
                            <th>Fee</th>
                            <th>Rate</th>
                            <th>Reward</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rewards)): ?>
                            <tr>
                                <td colspan="7">No referral rewards recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rewards as $reward): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reward["created_at"], ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= (int) $reward["referred_user_id"] ?></td>
                                    <td><?= (int) $reward["level_number"] ?></td>
                                    <td>$<?= number_format((float) $reward["qualifying_fee"], 8) ?></td>
                                    <td><?= number_format((float) $reward["reward_rate"], 2) ?>%</td>
                                    <td>$<?= number_format((float) $reward["reward_amount"], 8) ?></td>
                                    <td class="reward-status">
                                        <?= htmlspecialchars($reward["status"], ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="fine-print" style="margin-top: 20px;">
            This is a demo rewards display. Rewards are not real-money payments.
        </p>
    </main>

    <script>
        function copyReferralLink() {
            const input = document.getElementById("referralLink");
            const message = document.getElementById("copyMessage");

            const fullLink = new URL(input.value, window.location.href).href;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(fullLink)
                    .then(() => message.textContent = "Referral link copied.")
                    .catch(() => {
                        input.value = fullLink;
                        input.select();
                        message.textContent = "Select and copy the link manually.";
                    });
            } else {
                input.value = fullLink;
                input.select();
                message.textContent = "Press Ctrl+C to copy the link.";
            }
        }
    </script>
</body>

</html>