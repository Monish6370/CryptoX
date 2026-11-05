<?php
require_once "auth.php";
require_once "../db.php";
require_once "../csrf.php";

$csrfToken = csrf_token();

// Fetch request statistics
$statsSql = "SELECT
    COUNT(*) AS total_requests,
    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_requests,
    COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_requests,
    COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_requests,
    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS approved_amount
    FROM deposit_requests";

$statsResult = mysqli_query($conn, $statsSql);
$stats = $statsResult ? mysqli_fetch_assoc($statsResult) : [];

$totalRequests = (int) ($stats["total_requests"] ?? 0);
$pendingRequests = (int) ($stats["pending_requests"] ?? 0);
$approvedRequests = (int) ($stats["approved_requests"] ?? 0);
$rejectedRequests = (int) ($stats["rejected_requests"] ?? 0);
$approvedAmount = (float) ($stats["approved_amount"] ?? 0);

// Fetch balance requests with user details
$sql = "SELECT
            dr.id,
            dr.user_id,
            dr.amount,
            dr.status,
            dr.created_at,
            dr.reviewed_at,
            u.name,
            u.email
        FROM deposit_requests dr
        LEFT JOIN users u ON dr.user_id = u.id
        ORDER BY
            CASE WHEN dr.status = 'pending' THEN 0 ELSE 1 END,
            dr.created_at DESC
        LIMIT 100";

$result = mysqli_query($conn, $sql);

if (!$result) {
    error_log("Balance requests query failed: " . mysqli_error($conn));
}

$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Balance Requests | CryptoX Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #080b13;
            --panel: #101522;
            --panel2: #141b2b;
            --border: rgba(255, 255, 255, .075);
            --text: #f5f6fa;
            --muted: #818ba1;
            --gold: #f0b90b;
            --purple: #9b72ff;
            --green: #35d49a;
            --red: #ff647c;
            --blue: #64a8ff;
        }

        body {
            background:
                radial-gradient(ellipse at 70% -15%, rgba(105, 72, 190, .12), transparent 38%),
                var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* SIDEBAR - SAME AS DASHBOARD */

        .sidebar {
            width: 250px;
            position: fixed;
            inset: 0 auto 0 0;
            background: rgba(12, 17, 29, .96);
            border-right: 1px solid var(--border);
            padding: 27px 17px;
            display: flex;
            flex-direction: column;
            z-index: 20;
            backdrop-filter: blur(20px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 10px 34px;
        }

        .brand-mark {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffd65a, #d99400);
            color: #17130a;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            box-shadow: 0 5px 24px rgba(240, 185, 11, .2);
        }

        .brand-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .brand-name span {
            color: var(--gold);
        }

        .menu-label {
            color: #58647b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.7px;
            padding: 0 13px;
            margin-bottom: 12px;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-list a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            border-radius: 10px;
            color: #8994a9;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: .2s;
        }

        .nav-icon {
            width: 21px;
            text-align: center;
            font-size: 17px;
        }

        .nav-list a:hover {
            color: white;
            background: rgba(255, 255, 255, .04);
        }

        .nav-list a.active {
            color: var(--gold);
            background: linear-gradient(100deg, rgba(240, 185, 11, .13), rgba(155, 114, 255, .07));
            border-color: rgba(240, 185, 11, .15);
            box-shadow: inset 3px 0 var(--gold);
        }

        .sidebar-bottom {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .sidebar-bottom a {
            display: flex;
            gap: 12px;
            padding: 13px;
            border-radius: 9px;
            color: #ff7d8e;
            font-size: 13px;
            font-weight: 600;
        }

        .sidebar-bottom a:hover {
            background: rgba(255, 100, 124, .08);
        }

        /* MAIN CONTENT */

        .main {
            margin-left: 250px;
            padding: 35px 40px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 35px;
        }

        .breadcrumb {
            color: #75819a;
            font-size: 13px;
            margin-bottom: 9px;
        }

        h1 {
            font-size: 29px;
            font-weight: 750;
            letter-spacing: -.5px;
        }

        .subtitle {
            color: #8995aa;
            font-size: 13px;
            margin-top: 8px;
        }

        .admin-tag {
            padding: 10px 16px;
            background: #19243a;
            border: 1px solid #2b3952;
            border-radius: 30px;
            color: var(--gold);
            font-size: 12px;
            font-weight: 700;
        }

        /* MESSAGE */

        .message {
            background: #153c31;
            border: 1px solid #23664e;
            color: #70e0ae;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13px;
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: linear-gradient(145deg, #151f34, #101827);
            border: 1px solid #26334a;
            border-radius: 16px;
            padding: 23px;
        }

        .stat-label {
            color: #98a4b9;
            font-size: 13px;
        }

        .stat-number {
            font-size: 29px;
            font-weight: 750;
            margin-top: 13px;
        }

        .stat-note {
            color: #75819a;
            font-size: 11px;
            margin-top: 8px;
        }

        .gold {
            color: #f0b90b;
        }

        .green {
            color: #32d69a;
        }

        .red {
            color: #ff7185;
        }

        .blue {
            color: #80caff;
        }

        /* REQUESTS PANEL */

        .panel {
            background: #111b2e;
            border: 1px solid #25324a;
            border-radius: 16px;
            overflow: hidden;
        }

        .panel-header {
            padding: 23px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #26334a;
        }

        .panel-title {
            font-size: 17px;
            font-weight: 700;
        }

        .panel-subtitle {
            color: #8995aa;
            font-size: 12px;
            margin-top: 6px;
        }

        /* TABLE */

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            text-align: left;
            padding: 16px 20px;
            color: #8290a8;
            background: #151f34;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        td {
            padding: 17px 20px;
            border-bottom: 1px solid #26334a;
            font-size: 13px;
            color: #cbd3e1;
        }

        tbody tr:hover {
            background: #17243a;
        }

        .user-name {
            color: #f5f6fa;
            font-weight: 650;
        }

        .user-email {
            color: #8290a8;
            font-size: 11px;
            margin-top: 5px;
        }

        .amount {
            color: #f0b90b;
            font-weight: 750;
            white-space: nowrap;
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status.pending {
            background: #4a3b18;
            color: #f5cf64;
        }

        .status.approved {
            background: #153c31;
            color: #70e0ae;
        }

        .status.rejected {
            background: #49232e;
            color: #ff899a;
        }

        /* ACTION BUTTONS */

        .action-area {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-form {
            display: inline;
        }

        .action-btn {
            border: none;
            border-radius: 7px;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .approve-btn {
            background: #174b39;
            color: #70e0ae;
        }

        .reject-btn {
            background: #4a2530;
            color: #ff899a;
        }

        .action-btn:hover {
            opacity: .8;
        }

        .processed {
            color: #8290a8;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: #8995aa;
            font-size: 14px;
        }

        /* RESPONSIVE */

        @media(max-width: 1000px) {
            .sidebar {
                width: 220px;
            }

            .main {
                margin-left: 220px;
                padding: 25px 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 700px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 20px;
            }

            .brand {
                padding-bottom: 18px;
            }

            .nav-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .nav-list a {
                padding: 11px 9px;
                font-size: 11px;
            }

            .menu-label {
                display: none;
            }

            .sidebar-bottom {
                margin-top: 15px;
            }

            .main {
                margin-left: 0;
                padding: 20px 14px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .topbar,
            .panel-header {
                align-items: flex-start;
                flex-direction: column;
            }

            h1 {
                font-size: 24px;
            }
        }
        
        :root {
            --bg: #F1F5F9;
            --panel: #FFFFFF;
            --panel2: #F8FAFC;
            --border: #E2E8F0;
            --text: #1E293B;
            --muted: #64748B;
            --gold: #0D9488;
            --purple: #0D9488;
            --green: #16A34A;
            --red: #DC2626;
            --blue: #2563EB;
        }

        body {
            background: #F1F5F9;
            color: #1E293B;
        }

        /* Sidebar */
        .sidebar {
            background: #FFFFFF;
            border-right: 1px solid #E2E8F0;
            box-shadow: 2px 0 12px rgba(15, 23, 42, 0.03);
        }

        .brand-mark {
            background: #0D9488;
            color: #FFFFFF;
            box-shadow: none;
        }

        .brand-name span {
            color: #0D9488;
        }

        .menu-label {
            color: #94A3B8;
        }

        .nav-list a {
            color: #64748B;
        }

        .nav-list a:hover {
            color: #0D9488;
            background: #F0FDFA;
        }

        .nav-list a.active {
            color: #0D9488;
            background: #CCFBF1;
            border-color: #99F6E4;
            box-shadow: inset 3px 0 #0D9488;
        }

        .sidebar-bottom {
            border-top-color: #E2E8F0;
        }

        /* Main content */
        .main {
            background: #F1F5F9;
        }

        .breadcrumb,
        .subtitle {
            color: #64748B;
        }

        h1 {
            color: #1E293B;
        }

        .admin-tag {
            background: #CCFBF1;
            border: 1px solid #99F6E4;
            color: #0F766E;
        }

        /* Messages */
        .message {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #047857;
        }

        /* Statistics */
        .stat-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .stat-label {
            color: #64748B;
        }

        .stat-number {
            color: #1E293B;
        }

        .stat-note {
            color: #94A3B8;
        }

        .gold {
            color: #0D9488;
        }

        .green {
            color: #16A34A;
        }

        .red {
            color: #DC2626;
        }

        .blue {
            color: #2563EB;
        }

        /* Requests panel */
        .panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .panel-header {
            border-bottom: 1px solid #E2E8F0;
        }

        .panel-title {
            color: #1E293B;
        }

        .panel-subtitle {
            color: #64748B;
        }

        /* Table */
        th {
            background: #F8FAFC;
            color: #0F766E;
            border-bottom: 1px solid #E2E8F0;
        }

        td {
            color: #475569;
            border-bottom: 1px solid #E2E8F0;
        }

        tbody tr:hover {
            background: #F8FAFC;
        }

        .user-name {
            color: #1E293B;
        }

        .user-email {
            color: #64748B;
        }

        .amount {
            color: #0D9488;
            font-weight: 750;
        }

        /* Request statuses */
        .status.pending {
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid #FDE68A;
        }

        .status.approved {
            background: #DCFCE7;
            color: #15803D;
            border: 1px solid #BBF7D0;
        }

        .status.rejected {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }

        /* Action buttons */
        .approve-btn {
            background: #CCFBF1;
            color: #0F766E;
            border: 1px solid #99F6E4;
        }

        .reject-btn {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }

        .action-btn:hover {
            opacity: 0.8;
        }

        .processed {
            color: #64748B;
        }

        .empty-state {
            color: #64748B;
        }

        /* Responsive */
        @media (max-width: 700px) {
            .sidebar {
                background: #FFFFFF;
            }

            .nav-list a {
                background: #F8FAFC;
            }

            .nav-list a.active {
                background: #CCFBF1;
            }

            .panel-header {
                background: #FFFFFF;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">
            <div class="brand-mark">₿</div>
            <div class="brand-name">Crypto<span>X</span></div>
        </div>

        <div class="menu-label">WORKSPACE</div>

        <nav class="nav-list">

            <a href="dashboard.php">
                <span class="nav-icon">▦</span> Dashboard
            </a>

            <a href="users.php">
                <span class="nav-icon">♙</span> Manage Users
            </a>

            <a href="referrals.php">
                <span class="nav-icon">♧</span> Referral Rewards
            </a>

            <a href="trades.php">
                <span class="nav-icon">⇄</span> Trades
            </a>

            <a href="balance_requests.php" class="active">
                <span class="nav-icon">＋</span> Balance Requests
            </a>

        </nav>

        <div class="sidebar-bottom">
            <a href="logout.php">
                <span class="nav-icon">↪</span> Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <div class="topbar">
            <div>
                <div class="breadcrumb">Dashboard / Balance Requests</div>
                <h1>Balance Requests</h1>
                <p class="subtitle">
                    Review and manage user balance requests.
                </p>
            </div>

            <div class="admin-tag">ADMIN CONTROL</div>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTICS -->

        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-label">Total Requests</div>
                <div class="stat-number gold">
                    <?= number_format($totalRequests) ?>
                </div>
                <div class="stat-note">All user requests</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Pending Requests</div>
                <div class="stat-number red">
                    <?= number_format($pendingRequests) ?>
                </div>
                <div class="stat-note">Awaiting admin review</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Approved Requests</div>
                <div class="stat-number green">
                    <?= number_format($approvedRequests) ?>
                </div>
                <div class="stat-note">
                    Approved amount: $<?= number_format($approvedAmount, 2) ?>
                </div>
            </div>

        </section>

        <!-- ALL BALANCE REQUESTS -->

        <section class="panel">

            <div class="panel-header">
                <div>
                    <div class="panel-title">All Balance Requests</div>
                    <div class="panel-subtitle">
                        Latest 100 requests from registered users.
                    </div>
                </div>
            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($result && mysqli_num_rows($result) > 0): ?>

                            <?php while ($request = mysqli_fetch_assoc($result)): ?>

                                <tr>

                                    <td>#<?= (int) $request["id"] ?></td>

                                    <td>
                                        <div class="user-name">
                                            <?= htmlspecialchars($request["name"] ?? "Unknown User") ?>
                                        </div>

                                        <div class="user-email">
                                            <?= htmlspecialchars($request["email"] ?? "No email") ?>
                                        </div>
                                    </td>

                                    <td class="amount">
                                        $<?= number_format((float) $request["amount"], 2) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request["created_at"]) ?>
                                    </td>

                                    <td>
                                        <span class="status <?= htmlspecialchars($request["status"]) ?>">
                                            <?= htmlspecialchars($request["status"]) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php if ($request["status"] === "pending"): ?>

                                            <div class="action-area">

                                                <form method="POST" action="update_balance_request.php" class="action-form"
                                                    onsubmit="return confirm('Approve this balance request?');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($csrfToken) ?>">

                                                    <input type="hidden" name="request_id" value="<?= (int) $request["id"] ?>">

                                                    <input type="hidden" name="action" value="approve">

                                                    <button type="submit" class="action-btn approve-btn">
                                                        Approve
                                                    </button>
                                                </form>

                                                <form method="POST" action="update_balance_request.php" class="action-form"
                                                    onsubmit="return confirm('Reject this balance request?');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($csrfToken) ?>">

                                                    <input type="hidden" name="request_id" value="<?= (int) $request["id"] ?>">

                                                    <input type="hidden" name="action" value="reject">

                                                    <button type="submit" class="action-btn reject-btn">
                                                        Reject
                                                    </button>
                                                </form>

                                            </div>

                                        <?php else: ?>

                                            <span class="processed">
                                                Processed
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    No balance requests found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</body>

</html>

<?php
if ($result) {
    mysqli_free_result($result);
}

mysqli_close($conn);
?>