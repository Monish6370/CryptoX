<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";
require_once "csrf.php";

$userId = (int) $_SESSION["user_id"];

$message = "";
$messageType = "";

// Display message after redirect
if (isset($_SESSION["add_money_message"])) {
    $message = $_SESSION["add_money_message"];
    $messageType = $_SESSION["add_money_message_type"];

    unset($_SESSION["add_money_message"]);
    unset($_SESSION["add_money_message_type"]);
}

// Handle balance request
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {

        $message = "Invalid request. Please refresh and try again.";
        $messageType = "error";

    } else {

        $amount = trim($_POST["amount"] ?? "");

        if (
            !preg_match('/^\d{1,16}(\.\d{1,2})?$/', $amount)
            || (float) $amount <= 0
        ) {

            $message = "Please enter a valid amount.";
            $messageType = "error";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO deposit_requests (user_id, amount)
                 VALUES (?, ?)"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "is",
                    $userId,
                    $amount
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    $_SESSION["add_money_message"] =
                        "Your balance request has been submitted successfully.";

                    $_SESSION["add_money_message_type"] = "success";

                    header("Location: add_money.php");
                    exit;

                } else {

                    $message = "Unable to submit your request. Please try again.";
                    $messageType = "error";

                    mysqli_stmt_close($stmt);
                }

            } else {

                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// Fetch user's balance requests
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, amount, status, created_at
     FROM deposit_requests
     WHERE user_id = ?
     ORDER BY id DESC"
);

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);

$requests = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Money | CryptoX</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg: #080b13;
            --panel: #101522;
            --border: rgba(255, 255, 255, .075);
            --text: #f5f6fa;
            --muted: #818ba1;
            --gold: #f0b90b;
            --green: #35d49a;
            --red: #ff647c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(ellipse at 70% -15%, rgba(105, 72, 190, .12), transparent 38%),
                var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            width: min(900px, calc(100% - 40px));
            margin: auto;
            padding: 45px 0 55px;
        }

        /* HEADER */

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 35px;
        }

        .brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .brand span {
            color: var(--gold);
        }

        .back-link {
            color: var(--muted);
            font-size: 12px;
            transition: .2s;
        }

        .back-link:hover {
            color: var(--gold);
        }

        /* PAGE HEADING */

        .page-heading {
            margin-bottom: 25px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 10px;
        }

        .breadcrumb span {
            color: var(--gold);
        }

        h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 29px;
            letter-spacing: -1px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-top: 7px;
            line-height: 1.6;
        }

        /* MAIN CARD */

        .card {
            background: linear-gradient(145deg, #141b2b, #101522);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 22px;
        }

        .card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 9px;
        }

        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
        }

        .card-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(240, 185, 11, .1);
            color: var(--gold);
            font-size: 19px;
        }

        .card-description {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* FORM */

        label {
            display: block;
            color: #d9dfea;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .amount-box {
            display: flex;
            align-items: center;
            background: #0b101b;
            border: 1px solid #30394b;
            border-radius: 11px;
            padding: 0 15px;
            transition: .2s;
        }

        .amount-box:focus-within {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(240, 185, 11, .08);
        }

        .currency {
            color: var(--gold);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 23px;
            font-weight: 700;
            padding-right: 12px;
        }

        input {
            width: 100%;
            min-width: 0;
            padding: 16px 0;
            border: none;
            outline: none;
            background: transparent;
            color: white;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 23px;
            font-weight: 600;
        }

        input::placeholder {
            color: #4d586e;
        }

        input::-webkit-inner-spin-button,
        input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            appearance: textfield;
            -moz-appearance: textfield;
        }

        .currency-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .hint {
            color: var(--muted);
            font-size: 11px;
            margin-top: 9px;
        }

        .submit-btn {
            width: 100%;
            margin-top: 21px;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(110deg, #ffd65a, #e99a00);
            color: #17130a;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(240, 185, 11, .15);
        }

        /* MESSAGES */

        .message {
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 12px;
            line-height: 1.6;
        }

        .success {
            background: rgba(53, 212, 154, .09);
            color: #70e6b5;
            border: 1px solid rgba(53, 212, 154, .2);
        }

        .error {
            background: rgba(255, 100, 124, .09);
            color: #ff9aa9;
            border: 1px solid rgba(255, 100, 124, .2);
        }

        /* REQUEST HISTORY */

        .history-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .count {
            color: var(--muted);
            font-size: 11px;
            padding: 7px 10px;
            background: rgba(255, 255, 255, .04);
            border: 1px solid var(--border);
            border-radius: 7px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 530px;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 13px 12px;
            color: #748097;
            font-size: 10px;
            letter-spacing: .8px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 15px 12px;
            font-size: 12px;
            color: #dce2ed;
            border-bottom: 1px solid rgba(255, 255, 255, .045);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, .018);
        }

        .request-id {
            color: var(--gold);
            font-weight: 700;
        }

        .amount {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .pending {
            color: #ffc85c;
            background: rgba(255, 200, 92, .09);
        }

        .approved {
            color: var(--green);
            background: rgba(53, 212, 154, .09);
        }

        .rejected {
            color: var(--red);
            background: rgba(255, 100, 124, .09);
        }

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: var(--muted);
            font-size: 12px;
        }

        .empty-icon {
            font-size: 25px;
            color: var(--gold);
            margin-bottom: 10px;
        }

        .footer {
            text-align: center;
            color: #59647a;
            font-size: 11px;
            margin-top: 28px;
        }

        @media (max-width: 600px) {
            .container {
                width: calc(100% - 28px);
                padding-top: 25px;
            }

            .header {
                margin-bottom: 28px;
            }

            h1 {
                font-size: 25px;
            }

            .card {
                padding: 21px 17px;
            }

            .card-title {
                font-size: 16px;
            }

            .history-header {
                align-items: flex-start;
            }
        }

        /* =====================================
   CRYPTOX - WHITE + ROYAL BLUE THEME
===================================== */

        :root {
            --bg: #F4F6FA;
            --panel: #FFFFFF;
            --border: #E2E8F0;
            --text: #17243B;
            --muted: #64748B;
            --gold: #2563EB;
            --green: #16A34A;
            --red: #DC2626;
        }

        /* Page Background */
        body {
            background: #F4F6FA;
            color: var(--text);
        }

        /* Header */
        .brand {
            color: #17243B;
        }

        .brand span {
            color: #2563EB;
        }

        .back-link {
            color: #64748B;
        }

        .back-link:hover {
            color: #2563EB;
        }

        /* Page Heading */
        .breadcrumb {
            color: #64748B;
        }

        h1 {
            color: #17243B;
        }

        .subtitle {
            color: #64748B;
        }

        .breadcrumb span {
            color: #2563EB;
        }

        /* Main Cards */
        .card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.035);
        }

        .card-title {
            color: #17243B;
        }

        .card-icon {
            background: #EFF6FF;
            color: #2563EB;
        }

        .card-description {
            color: #64748B;
        }

        /* Amount Form */
        label {
            color: #334155;
        }

        .amount-box {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
        }

        .amount-box:focus-within {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .currency {
            color: #2563EB;
        }

        input {
            color: #17243B;
        }

        input::placeholder {
            color: #94A3B8;
        }

        .currency-label {
            color: #64748B;
        }

        .hint {
            color: #64748B;
        }

        /* Submit Button */
        .submit-btn {
            background: #2563EB;
            color: #FFFFFF;
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.12);
        }

        .submit-btn:hover {
            background: #1D4ED8;
            box-shadow: 0 8px 22px rgba(37, 99, 235, 0.20);
        }

        /* Success and Error Messages */
        .success {
            background: #F0FDF4;
            color: #15803D;
            border: 1px solid #BBF7D0;
        }

        .error {
            background: #FEF2F2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }

        /* Request History */
        .count {
            color: #64748B;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
        }

        th {
            color: #64748B;
            border-bottom: 1px solid #E2E8F0;
        }

        td {
            color: #334155;
            border-bottom: 1px solid #F1F5F9;
        }

        tbody tr:hover {
            background: #F8FAFC;
        }

        .request-id {
            color: #2563EB;
        }

        .amount {
            color: #17243B;
        }

        /* Request Status */
        .pending {
            color: #B45309;
            background: #FEF3C7;
        }

        .approved {
            color: #15803D;
            background: #DCFCE7;
        }

        .rejected {
            color: #B91C1C;
            background: #FEE2E2;
        }

        /* Empty History */
        .empty {
            color: #64748B;
        }

        .empty-icon {
            color: #2563EB;
        }

        /* Footer */
        .footer {
            color: #94A3B8;
        }
    </style>
</head>

<body>

    <main class="container">

        <!-- HEADER -->

        <header class="header">

            <a href="dashboard.php" class="brand">
                Crypto<span>X</span>
            </a>

            <a href="dashboard.php" class="back-link">
                ← Back to Dashboard
            </a>

        </header>


        <!-- PAGE HEADING -->

        <section class="page-heading">

            <div class="breadcrumb">
                WORKSPACE / <span>ADD MONEY</span>
            </div>

            <h1>Add Money</h1>

            <p class="subtitle">
                Submit a balance request and track its approval status.
            </p>

        </section>


        <!-- ADD MONEY FORM -->

        <section class="card">

            <div class="card-heading">

                <h2 class="card-title">Enter Amount</h2>

                <div class="card-icon">＋</div>

            </div>

            <p class="card-description">
                Enter the amount you want to add to your account.
                Your request will be reviewed by the admin.
            </p>


            <?php if ($message !== ""): ?>

                <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, "UTF-8") ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>

            <?php endif; ?>


            <form method="POST" action="add_money.php">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">

                <label for="amount">AMOUNT (USD)</label>

                <div class="amount-box">

                    <span class="currency">$</span>

                    <input type="number" id="amount" name="amount" placeholder="0.00" min="0.01" step="0.01" required>

                    <span class="currency-label">USD</span>

                </div>

                <p class="hint">
                    Your balance will be updated after admin approval.
                </p>

                <button type="submit" class="submit-btn">
                    Submit Request →
                </button>

            </form>

        </section>


        <!-- REQUEST HISTORY -->

        <section class="card">

            <div class="history-header">

                <div>
                    <h2 class="card-title">My Requests</h2>

                    <p class="card-description" style="margin: 6px 0 0;">
                        Your previous balance requests.
                    </p>
                </div>

                <span class="count">
                    <?= mysqli_num_rows($requests) ?> Requests
                </span>

            </div>


            <?php if (mysqli_num_rows($requests) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php while ($row = mysqli_fetch_assoc($requests)): ?>

                                <tr>

                                    <td class="request-id">
                                        #<?= (int) $row["id"] ?>
                                    </td>

                                    <td class="amount">
                                        $<?= number_format((float) $row["amount"], 2) ?>
                                    </td>

                                    <td>

                                        <span class="status <?= htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8") ?>">
                                            <?= htmlspecialchars(ucfirst($row["status"]), ENT_QUOTES, "UTF-8") ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row["created_at"], ENT_QUOTES, "UTF-8") ?>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">＋</div>

                    <p>No requests found. Your requests will appear here.</p>

                </div>

            <?php endif; ?>

        </section>


        <footer class="footer">
            © <?= date("Y") ?> CryptoX. All rights reserved.
        </footer>

    </main>

</body>

</html>

<?php
?>