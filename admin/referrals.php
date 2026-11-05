
<?php
require_once "auth.php";
require_once "../db.php";
require_once "../csrf.php";

$csrfToken = csrf_token();

// Referral statistics
$statsSql = "SELECT
    COUNT(*) AS total_rewards,
    COALESCE(SUM(reward_amount), 0) AS total_amount,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_rewards,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_rewards,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_rewards,
    SUM(CASE WHEN status = 'reversed' THEN 1 ELSE 0 END) AS reversed_rewards
    FROM referral_rewards";

$statsResult = mysqli_query($conn, $statsSql);
$stats = $statsResult ? mysqli_fetch_assoc($statsResult) : [];

$totalRewards = (int) ($stats["total_rewards"] ?? 0);
$totalAmount = (float) ($stats["total_amount"] ?? 0);
$pendingRewards = (int) ($stats["pending_rewards"] ?? 0);
$approvedRewards = (int) ($stats["approved_rewards"] ?? 0);
$paidRewards = (int) ($stats["paid_rewards"] ?? 0);
$reversedRewards = (int) ($stats["reversed_rewards"] ?? 0);

// Fetch referral rewards
$sql = "SELECT
            rr.id,
            rr.earner_user_id,
            rr.referred_user_id,
            rr.level_number,
            rr.qualifying_fee,
            rr.reward_rate,
            rr.reward_amount,
            rr.activity_reference,
            rr.status,
            rr.created_at,
            earner.name AS earner_name,
            earner.email AS earner_email,
            referred.name AS referred_name,
            referred.email AS referred_email
        FROM referral_rewards rr
        LEFT JOIN users earner
            ON rr.earner_user_id = earner.id
        LEFT JOIN users referred
            ON rr.referred_user_id = referred.id
        ORDER BY rr.created_at DESC
        LIMIT 100";

$result = mysqli_query($conn, $sql);

if (!$result) {
    error_log("Referral query failed: " . mysqli_error($conn));
}

$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Rewards | CryptoX Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

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
    --border: rgba(255,255,255,.075);
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
        radial-gradient(ellipse at 70% -15%, rgba(105,72,190,.12), transparent 38%),
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
    background: rgba(12,17,29,.96);
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
    box-shadow: 0 5px 24px rgba(240,185,11,.2);
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
    background: rgba(255,255,255,.04);
}

.nav-list a.active {
    color: var(--gold);
    background: linear-gradient(100deg, rgba(240,185,11,.13), rgba(155,114,255,.07));
    border-color: rgba(240,185,11,.15);
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
    background: rgba(255,100,124,.08);
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
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: "";
    position: absolute;
    width: 90px;
    height: 90px;
    right: -25px;
    top: -30px;
    background: rgba(240,185,11,.06);
    border-radius: 50%;
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

.gold { color: var(--gold); }
.green { color: #32d69a; }
.red { color: #ff7185; }
.blue { color: #80caff; }
.purple { color: #c4a8ff; }

/* REFERRAL PANEL */

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
    color: #8290a8;
    font-size: 12px;
    margin-top: 6px;
}

.record-count {
    background: #1b2941;
    border: 1px solid #34435c;
    color: var(--gold);
    padding: 8px 13px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
}

/* TABLE */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

th {
    background: #17233a;
    color: var(--gold);
    text-align: left;
    padding: 16px 17px;
    font-size: 11px;
    letter-spacing: .7px;
    text-transform: uppercase;
}

td {
    padding: 17px;
    border-bottom: 1px solid #222e43;
    color: #cbd3e1;
    font-size: 13px;
    white-space: nowrap;
}

tbody tr:hover {
    background: #172238;
}

.reward-id {
    color: #8b97ad;
}

.person-name {
    color: #fff;
    font-weight: 650;
}

.person-id {
    color: #8290a8;
    font-size: 11px;
    margin-top: 5px;
}

.person-email {
    color: #aab5c9;
    font-size: 11px;
    margin-top: 4px;
}

.level-badge {
    display: inline-block;
    background: rgba(196,168,255,.12);
    color: #c4a8ff;
    border: 1px solid rgba(196,168,255,.2);
    padding: 6px 10px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 700;
}

.amount {
    color: #32d69a;
    font-weight: 750;
}

/* REWARD STATUS */

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.status.pending {
    background: rgba(240,185,11,.12);
    color: #ffdb70;
    border: 1px solid rgba(240,185,11,.2);
}

.status.approved {
    background: rgba(50,214,154,.12);
    color: #32d69a;
    border: 1px solid rgba(50,214,154,.2);
}

.status.paid {
    background: rgba(128,202,255,.12);
    color: #80caff;
    border: 1px solid rgba(128,202,255,.2);
}

.status.reversed {
    background: rgba(255,113,133,.12);
    color: #ff8293;
    border: 1px solid rgba(255,113,133,.2);
}

/* APPROVE BUTTON */

.approve-btn {
    background: var(--gold);
    color: #151515;
    border: none;
    border-radius: 7px;
    padding: 9px 13px;
    font-size: 11px;
    font-weight: 750;
    cursor: pointer;
    transition: .2s;
}

.approve-btn:hover {
    background: #d9a509;
    transform: translateY(-1px);
}

.no-action {
    color: #68758b;
}

.empty {
    text-align: center;
    padding: 45px;
    color: #8d99ad;
}

.footer-note {
    padding: 17px 22px;
    color: #78869e;
    font-size: 11px;
}

/* RESPONSIVE */

@media(max-width: 1000px) {
    .sidebar {
        width: 215px;
    }

    .main {
        margin-left: 215px;
        padding: 25px;
    }

    .stats-grid {
        gap: 12px;
    }
}

@media(max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 17px;
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

/* Success message */
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

.stat-card::after {
    background: rgba(13, 148, 136, 0.07);
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

.purple {
    color: #7C3AED;
}

/* Referral panel */
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

.record-count {
    background: #CCFBF1;
    border: 1px solid #99F6E4;
    color: #0F766E;
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

.reward-id {
    color: #64748B;
}

.person-name {
    color: #1E293B;
}

.person-id {
    color: #64748B;
}

.person-email {
    color: #64748B;
}

/* Referral level */
.level-badge {
    background: #F3E8FF;
    color: #7E22CE;
    border: 1px solid #E9D5FF;
}

/* Reward amount */
.amount {
    color: #15803D;
    font-weight: 750;
}

/* Reward statuses */
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

.status.paid {
    background: #DBEAFE;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}

.status.reversed {
    background: #FEE2E2;
    color: #B91C1C;
    border: 1px solid #FECACA;
}

/* Approve button */
.approve-btn {
    background: #0D9488;
    color: #FFFFFF;
}

.approve-btn:hover {
    background: #0F766E;
    transform: translateY(-1px);
}

.no-action {
    color: #94A3B8;
}

.empty {
    color: #64748B;
}

.footer-note {
    color: #94A3B8;
    border-top: 1px solid #E2E8F0;
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

        <a href="referrals.php" class="active">
            <span class="nav-icon">♧</span> Referral Rewards
        </a>

        <a href="trades.php">
            <span class="nav-icon">⇄</span> Trades
        </a>

        <a href="balance_requests.php">
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
            <div class="breadcrumb">Dashboard / Referral Rewards</div>
            <h1>Referral Rewards</h1>
            <p class="subtitle">
                Monitor referral commissions and manage reward approvals.
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
            <div class="stat-label">Total Rewards</div>
            <div class="stat-number gold">
                <?= number_format($totalRewards) ?>
            </div>
            <div class="stat-note">All recorded referral rewards</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Reward Amount</div>
            <div class="stat-number green">
                <?= number_format($totalAmount, 4) ?>
            </div>
            <div class="stat-note">Combined recorded reward amount</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pending Approval</div>
            <div class="stat-number red">
                <?= number_format($pendingRewards) ?>
            </div>
            <div class="stat-note">Rewards awaiting admin review</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Approved Rewards</div>
            <div class="stat-number green">
                <?= number_format($approvedRewards) ?>
            </div>
            <div class="stat-note">Rewards approved by admin</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Paid Rewards</div>
            <div class="stat-number blue">
                <?= number_format($paidRewards) ?>
            </div>
            <div class="stat-note">Rewards marked as paid</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Reversed Rewards</div>
            <div class="stat-number purple">
                <?= number_format($reversedRewards) ?>
            </div>
            <div class="stat-note">Rewards marked as reversed</div>
        </div>

    </section>

    <!-- REFERRAL ACTIVITY -->

    <section class="panel">

        <div class="panel-header">
            <div>
                <div class="panel-title">Referral Activity</div>
                <div class="panel-subtitle">
                    Latest referral reward records
                </div>
            </div>

            <div class="record-count">
                <?= number_format($totalRewards) ?> TOTAL RECORDS
            </div>
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Reward ID</th>
                        <th>Earner</th>
                        <th>Referred User</th>
                        <th>Level</th>
                        <th>Reward Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result && mysqli_num_rows($result) > 0): ?>

                        <?php while ($reward = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td class="reward-id">
                                    #<?= (int) $reward["id"] ?>
                                </td>

                                <td>
                                    <div class="person-name">
                                        <?= htmlspecialchars($reward["earner_name"] ?? "Unknown") ?>
                                    </div>

                                    <div class="person-email">
                                        <?= htmlspecialchars($reward["earner_email"] ?? "No email") ?>
                                    </div>

                                    <div class="person-id">
                                        User #<?= (int) $reward["earner_user_id"] ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="person-name">
                                        <?= htmlspecialchars($reward["referred_name"] ?? "Unknown") ?>
                                    </div>

                                    <div class="person-email">
                                        <?= htmlspecialchars($reward["referred_email"] ?? "No email") ?>
                                    </div>

                                    <div class="person-id">
                                        User #<?= (int) $reward["referred_user_id"] ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="level-badge">
                                        Level <?= (int) $reward["level_number"] ?>
                                    </span>
                                </td>

                                <td class="amount">
                                    <?= number_format((float) $reward["reward_amount"], 4) ?>
                                </td>

                                <td>
                                    <span class="status <?= htmlspecialchars($reward["status"]) ?>">
                                        <?= htmlspecialchars($reward["status"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($reward["created_at"]) ?>
                                </td>

                                <td>

                                    <?php if ($reward["status"] === "pending"): ?>

                                        <form method="POST" action="update_referral.php">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="reward_id"
                                                value="<?= (int) $reward["id"] ?>"
                                            >

                                            <input type="hidden" name="action" value="approve">

                                            <button
                                                type="submit"
                                                class="approve-btn"
                                                onclick="return confirm('Approve this referral reward?')"
                                            >
                                                Approve Reward
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="no-action">—</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty">
                                No referral rewards found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="footer-note">
            Showing the latest 100 referral records. Pending rewards require admin approval.
        </div>

    </section>

</main>

</body>
</html>

<?php
if ($result) {
    mysqli_free_result($result);
}

if ($statsResult) {
    mysqli_free_result($statsResult);
}

mysqli_close($conn);
?>
