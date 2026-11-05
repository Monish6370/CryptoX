
<?php
require_once "auth.php";
require_once "../db.php";
require_once "../csrf.php";

$csrfToken = csrf_token();

// Handle block/unblock requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_token_is_valid($_POST["csrf_token"] ?? null)) {
        $_SESSION["admin_message"] = "Invalid request. Please try again.";
        header("Location: users.php");
        exit;
    }

    $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!$userId || !in_array($action, ["block", "unblock"], true)) {
        $_SESSION["admin_message"] = "Invalid user request.";
        header("Location: users.php");
        exit;
    }

    if ($userId === (int)$_SESSION["admin_user_id"]) {
        $_SESSION["admin_message"] = "You cannot change your own account status.";
        header("Location: users.php");
        exit;
    }

    $newStatus = ($action === "block") ? "blocked" : "active";

    $sql = "UPDATE users
            SET status = ?
            WHERE id = ? AND role = 'user'";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $newStatus, $userId);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION["admin_message"] = "User account status updated successfully.";
            } else {
                $_SESSION["admin_message"] = "User not found or account status is already unchanged.";
            }
        } else {
            $_SESSION["admin_message"] = "Unable to update user status.";
        }

        mysqli_stmt_close($stmt);
    } else {
        $_SESSION["admin_message"] = "Something went wrong. Please try again.";
    }

    mysqli_close($conn);
    header("Location: users.php");
    exit;
}

// Search
$search = trim($_GET["search"] ?? "");

// User statistics
$statsQuery = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_users
    FROM users
    WHERE role = 'user'
");

$stats = $statsQuery ? mysqli_fetch_assoc($statsQuery) : [];

$totalUsers = (int)($stats["total_users"] ?? 0);
$activeUsers = (int)($stats["active_users"] ?? 0);
$blockedUsers = (int)($stats["blocked_users"] ?? 0);

// Fetch users
if ($search !== "") {
    $sql = "SELECT id, name, email, created_at, referred_by, role, status
            FROM users
            WHERE name LIKE ? OR email LIKE ?
            ORDER BY id DESC
            LIMIT 100";

    $stmt = mysqli_prepare($conn, $sql);
    $searchTerm = "%" . $search . "%";

    mysqli_stmt_bind_param($stmt, "ss", $searchTerm, $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT id, name, email, created_at, referred_by, role, status
            FROM users
            ORDER BY id DESC
            LIMIT 100";

    $result = mysqli_query($conn, $sql);
}

if (!$result) {
    error_log("Users query failed: " . mysqli_error($conn));
}

$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | CryptoX Admin</title>

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

/* SIDEBAR */

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
    font-size: 30px;
    font-weight: 750;
    margin-top: 13px;
}

.stat-note {
    color: #75819a;
    font-size: 11px;
    margin-top: 8px;
}

.gold {
    color: var(--gold);
}

.green {
    color: #32d69a;
}

.red {
    color: #ff7185;
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

/* USERS PANEL */

.users-panel {
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

/* SEARCH */

.search-form {
    display: flex;
    gap: 8px;
}

.search-form input {
    background: #0b1425;
    border: 1px solid #2b3952;
    color: white;
    padding: 11px 13px;
    border-radius: 9px;
    outline: none;
    width: 220px;
    font-size: 12px;
}

.search-form input:focus {
    border-color: var(--gold);
}

.search-btn {
    background: var(--gold);
    color: #151515;
    border: none;
    border-radius: 9px;
    padding: 10px 16px;
    font-weight: 750;
    cursor: pointer;
}

.clear-btn {
    color: #aab5c9;
    padding: 10px;
    font-size: 12px;
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
    padding: 16px 18px;
    font-size: 11px;
    letter-spacing: .7px;
    text-transform: uppercase;
}

td {
    padding: 17px 18px;
    border-bottom: 1px solid #222e43;
    color: #cbd3e1;
    font-size: 13px;
}

tbody tr:hover {
    background: #172238;
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

.user-id {
    color: #8a96aa;
}

.role {
    color: #c4a8ff;
    font-weight: 700;
    text-transform: capitalize;
}

/* STATUS */

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.active-status {
    background: rgba(50,214,154,.12);
    color: #32d69a;
    border: 1px solid rgba(50,214,154,.2);
}

.blocked-status {
    background: rgba(255,113,133,.12);
    color: #ff7185;
    border: 1px solid rgba(255,113,133,.2);
}

/* ACTION BUTTONS */

.action-btn {
    border: none;
    border-radius: 7px;
    padding: 9px 13px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.block-btn {
    background: rgba(255,113,133,.14);
    color: #ff8293;
}

.block-btn:hover {
    background: #a83242;
    color: white;
}

.unblock-btn {
    background: rgba(50,214,154,.13);
    color: #32d69a;
}

.unblock-btn:hover {
    background: #178653;
    color: white;
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

    .search-form {
        width: 100%;
    }

    .search-form input {
        width: 100%;
        min-width: 0;
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

/* Success message */
.message {
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #047857;
}

/* Users panel */
.users-panel {
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

/* Search */
.search-form input {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    color: #1E293B;
}

.search-form input::placeholder {
    color: #94A3B8;
}

.search-form input:focus {
    border-color: #0D9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.10);
}

.search-btn {
    background: #0D9488;
    color: #FFFFFF;
}

.search-btn:hover {
    background: #0F766E;
}

.clear-btn {
    color: #64748B;
}

/* Users table */
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

.user-email,
.user-id {
    color: #64748B;
}

.role {
    color: #0D9488;
}

/* Status labels */
.active-status {
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #BBF7D0;
}

.blocked-status {
    background: #FEE2E2;
    color: #B91C1C;
    border: 1px solid #FECACA;
}

/* Action buttons */
.block-btn {
    background: #FEE2E2;
    color: #B91C1C;
}

.block-btn:hover {
    background: #DC2626;
    color: #FFFFFF;
}

.unblock-btn {
    background: #DCFCE7;
    color: #15803D;
}

.unblock-btn:hover {
    background: #16A34A;
    color: #FFFFFF;
}

/* Empty state and footer */
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

        <a href="users.php" class="active">
            <span class="nav-icon">♙</span> Manage Users
        </a>

        <a href="referrals.php">
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
            <div class="breadcrumb">Dashboard / Users</div>
            <h1>Manage Users</h1>
            <p class="subtitle">Monitor and manage all registered CryptoX accounts.</p>
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
            <div class="stat-label">Total Users</div>
            <div class="stat-number gold">
                <?= number_format($totalUsers) ?>
            </div>
            <div class="stat-note">Registered user accounts</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-number green">
                <?= number_format($activeUsers) ?>
            </div>
            <div class="stat-note">Accounts currently active</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Blocked Users</div>
            <div class="stat-number red">
                <?= number_format($blockedUsers) ?>
            </div>
            <div class="stat-note">Accounts currently restricted</div>
        </div>

    </section>

    <!-- REGISTERED USERS -->

    <section class="users-panel">

        <div class="panel-header">
            <div>
                <div class="panel-title">Registered Accounts</div>
                <div class="panel-subtitle">
                    Latest 100 accounts
                </div>
            </div>

            <form method="GET" action="users.php" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search name or email..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button type="submit" class="search-btn">Search</button>

                <?php if ($search !== ""): ?>
                    <a href="users.php" class="clear-btn">Clear</a>
                <?php endif; ?>

            </form>
        </div>

        <div class="table-wrap">
            <table>

                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>User Details</th>
                        <th>Role</th>
                        <th>Referred By</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result && mysqli_num_rows($result) > 0): ?>

                    <?php while ($user = mysqli_fetch_assoc($result)): ?>

                        <tr>

                            <td class="user-id">
                                #<?= (int)$user["id"] ?>
                            </td>

                            <td>
                                <div class="user-name">
                                    <?= htmlspecialchars($user["name"]) ?>
                                </div>

                                <div class="user-email">
                                    <?= htmlspecialchars($user["email"]) ?>
                                </div>
                            </td>

                            <td class="role">
                                <?= htmlspecialchars($user["role"]) ?>
                            </td>

                            <td>
                                <?= $user["referred_by"] !== null
                                    ? "#" . (int)$user["referred_by"]
                                    : "-" ?>
                            </td>

                            <td>
                                <span class="status <?= $user["status"] === "active" ? "active-status" : "blocked-status" ?>">
                                    <?= htmlspecialchars($user["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($user["created_at"]) ?>
                            </td>

                            <td>
                                <?php if ($user["role"] === "user"): ?>

                                    <form method="POST" action="users.php">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int)$user["id"] ?>"
                                        >

                                        <?php if ($user["status"] === "active"): ?>

                                            <input type="hidden" name="action" value="block">

                                            <button
                                                type="submit"
                                                class="action-btn block-btn"
                                                onclick="return confirm('Are you sure you want to block this user?')"
                                            >
                                                Block User
                                            </button>

                                        <?php else: ?>

                                            <input type="hidden" name="action" value="unblock">

                                            <button
                                                type="submit"
                                                class="action-btn unblock-btn"
                                                onclick="return confirm('Are you sure you want to unblock this user?')"
                                            >
                                                Unblock
                                            </button>

                                        <?php endif; ?>

                                    </form>

                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty">
                            <?= $search !== ""
                                ? "No users found matching your search."
                                : "No registered users found." ?>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>
        </div>

        <div class="footer-note">
            Showing up to 100 accounts. User management actions are protected by CSRF validation.
        </div>

    </section>

</main>

</body>
</html>

<?php
if ($result) {
    mysqli_free_result($result);
}

if (isset($stmt) && $search !== "") {
    mysqli_stmt_close($stmt);
}

if ($statsQuery) {
    mysqli_free_result($statsQuery);
}

mysqli_close($conn);
?>
    