
<?php
require_once "auth.php";
require_once "../db.php";

// Trade statistics
$statsSql = "SELECT
    COUNT(*) AS total_trades,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_trades,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_trades,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS trading_volume,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN fee ELSE 0 END), 0) AS total_fees
    FROM trades";

$statsResult = mysqli_query($conn, $statsSql);
$stats = $statsResult ? mysqli_fetch_assoc($statsResult) : [];

$totalTrades = (int)($stats["total_trades"] ?? 0);
$completedTrades = (int)($stats["completed_trades"] ?? 0);
$cancelledTrades = (int)($stats["cancelled_trades"] ?? 0);
$tradingVolume = (float)($stats["trading_volume"] ?? 0);
$totalFees = (float)($stats["total_fees"] ?? 0);

// Fetch trades with user names
$sql = "SELECT
            t.id,
            t.user_id,
            t.coin_id,
            t.trade_type,
            t.quantity,
            t.price,
            t.total,
            t.fee,
            t.status,
            t.created_at,
            u.name AS user_name,
            u.email AS user_email
        FROM trades t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 100";

$result = mysqli_query($conn, $sql);

if (!$result) {
    error_log("Trades query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trades | CryptoX Admin</title>

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

/* TRADING PANEL */

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
    min-width: 1200px;
    border-collapse: collapse;
}

th {
    background: #17233a;
    color: var(--gold);
    text-align: left;
    padding: 16px 15px;
    font-size: 11px;
    letter-spacing: .7px;
    text-transform: uppercase;
}

td {
    padding: 17px 15px;
    border-bottom: 1px solid #222e43;
    color: #cbd3e1;
    font-size: 13px;
    white-space: nowrap;
}

tbody tr:hover {
    background: #172238;
}

.trade-id {
    color: #8b97ad;
}

.user-name {
    color: #fff;
    font-weight: 650;
}

.user-email {
    color: #8491a8;
    font-size: 11px;
    margin-top: 5px;
}

.coin-badge {
    display: inline-block;
    background: #1b2941;
    border: 1px solid #34435c;
    color: #cbd3e1;
    padding: 6px 10px;
    border-radius: 7px;
    font-size: 11px;
}

/* BUY AND SELL */

.buy,
.sell {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 750;
}

.buy {
    background: rgba(50,214,154,.12);
    color: #32d69a;
    border: 1px solid rgba(50,214,154,.2);
}

.sell {
    background: rgba(255,113,133,.12);
    color: #ff8293;
    border: 1px solid rgba(255,113,133,.2);
}

.total {
    color: #32d69a;
    font-weight: 750;
}

.fee {
    color: var(--gold);
    font-weight: 650;
}

/* TRADE STATUS */

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.status.completed {
    background: rgba(50,214,154,.12);
    color: #32d69a;
    border: 1px solid rgba(50,214,154,.2);
}

.status.cancelled {
    background: rgba(255,113,133,.12);
    color: #ff8293;
    border: 1px solid rgba(255,113,133,.2);
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


/* =====================================
   CRYPTOX ADMIN - LIGHT SLATE + TEAL
===================================== */

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

/* Main */
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

/* Trading panel */
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

.trade-id {
    color: #64748B;
}

.user-name {
    color: #1E293B;
}

.user-email {
    color: #64748B;
}

.coin-badge {
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    color: #475569;
}

/* Buy and Sell */
.buy {
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #BBF7D0;
}

.sell {
    background: #FEE2E2;
    color: #B91C1C;
    border: 1px solid #FECACA;
}

.total {
    color: #15803D;
}

.fee {
    color: #0D9488;
}

/* Trade status */
.status.completed {
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #BBF7D0;
}

.status.cancelled {
    background: #FEE2E2;
    color: #B91C1C;
    border: 1px solid #FECACA;
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

        <a href="referrals.php">
            <span class="nav-icon">♧</span> Referral Rewards
        </a>

        <a href="trades.php" class="active">
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
            <div class="breadcrumb">Dashboard / Trades</div>
            <h1>Trading Activity</h1>
            <p class="subtitle">
                Monitor trading activity, volume and execution records.
            </p>
        </div>

        <div class="admin-tag">ADMIN CONTROL</div>
    </div>

    <!-- STATISTICS -->

    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-label">Total Trades</div>
            <div class="stat-number gold">
                <?= number_format($totalTrades) ?>
            </div>
            <div class="stat-note">All recorded trade orders</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Completed Trades</div>
            <div class="stat-number green">
                <?= number_format($completedTrades) ?>
            </div>
            <div class="stat-note">Successfully completed trades</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Cancelled Trades</div>
            <div class="stat-number red">
                <?= number_format($cancelledTrades) ?>
            </div>
            <div class="stat-note">Cancelled trade orders</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Trading Volume</div>
            <div class="stat-number blue">
                <?= number_format($tradingVolume, 2) ?>
            </div>
            <div class="stat-note">Volume from completed trades</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Fees</div>
            <div class="stat-number gold">
                <?= number_format($totalFees, 2) ?>
            </div>
            <div class="stat-note">Fees from completed trades</div>
        </div>

    </section>

    <!-- TRADING RECORDS -->

    <section class="panel">

        <div class="panel-header">
            <div>
                <div class="panel-title">Recent Trading Records</div>
                <div class="panel-subtitle">
                    Latest trade activity across all users
                </div>
            </div>

            <div class="record-count">
                <?= number_format($totalTrades) ?> TOTAL TRADES
            </div>
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Trade ID</th>
                        <th>User</th>
                        <th>Coin ID</th>
                        <th>Trade Type</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result && mysqli_num_rows($result) > 0): ?>

                    <?php while ($trade = mysqli_fetch_assoc($result)): ?>

                        <tr>

                            <td class="trade-id">
                                #<?= (int)$trade["id"] ?>
                            </td>

                            <td>
                                <div class="user-name">
                                    <?= htmlspecialchars($trade["user_name"] ?? "Unknown") ?>
                                </div>

                                <div class="user-email">
                                    <?= htmlspecialchars($trade["user_email"] ?? "No email") ?>
                                    · #<?= (int)$trade["user_id"] ?>
                                </div>
                            </td>

                            <td>
                                <span class="coin-badge">
                                    #<?= (int)$trade["coin_id"] ?>
                                </span>
                            </td>

                            <td>
                                <span class="<?= $trade["trade_type"] === "buy" ? "buy" : "sell" ?>">
                                    <?= htmlspecialchars(ucfirst($trade["trade_type"])) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($trade["quantity"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($trade["price"]) ?>
                            </td>

                            <td class="total">
                                <?= htmlspecialchars($trade["total"]) ?>
                            </td>

                            <td class="fee">
                                <?= htmlspecialchars($trade["fee"]) ?>
                            </td>

                            <td>
                                <span class="status <?= htmlspecialchars($trade["status"]) ?>">
                                    <?= htmlspecialchars($trade["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($trade["created_at"]) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="empty">
                            No trading records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="footer-note">
            Showing the latest 100 trade records. Trading statistics are calculated from the trades database.
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
