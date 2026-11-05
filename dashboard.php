<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "User";
$user_email = $_SESSION["user_email"] ?? "";

$balance = 0;

$stmt = mysqli_prepare($conn, "SELECT balance FROM wallets WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $balance = (float) $row["balance"];
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoX | Dashboard</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #0b0f16;
            color: #f1f3f5;
        }

        header {
            height: 76px;
            background: #111722;
            border-bottom: 1px solid #242d3b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6%;
        }

        .logo {
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -1px;
            color: #f0b90b;
        }

        .logo span {
            color: #ffffff;
        }

        nav {
            display: flex;
            gap: 32px;
        }

        nav a {
            color: #a6afbd;
            text-decoration: none;
            font-size: 14px;
            transition: .2s;
        }

        nav a:hover,
        nav a.active {
            color: #f0b90b;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 11px;
            background: #1c2533;
            border: 1px solid #303b4c;
            padding: 9px 15px;
            border-radius: 30px;
            font-size: 13px;
        }

        .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f0b90b;
            color: #111722;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .container {
            max-width: 1320px;
            width: 90%;
            margin: 38px auto;
        }

        .welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(115deg, #202b3b, #151e2b 65%, #28251a);
            border: 1px solid #344153;
            border-radius: 16px;
            padding: 38px;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .welcome::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            border: 35px solid rgba(240, 185, 11, .07);
            border-radius: 50%;
            right: 10%;
            top: -115px;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-label {
            color: #f0b90b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .welcome h1 {
            font-size: 31px;
            margin-bottom: 10px;
            letter-spacing: -.5px;
        }

        .welcome p {
            color: #a8b2c1;
            font-size: 14px;
        }

        .welcome-badge {
            position: relative;
            z-index: 1;
            background: rgba(240, 185, 11, .12);
            color: #f0b90b;
            border: 1px solid rgba(240, 185, 11, .3);
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            font-size: 20px;
            font-weight: 650;
        }

        .section-heading span {
            color: #8994a4;
            font-size: 12px;
        }

        .wallet-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
            margin-bottom: 38px;
        }

        .wallet-card {
            background: linear-gradient(125deg, #252c3a, #171e2b);
            border: 1px solid #394457;
            border-radius: 15px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .wallet-card::after {
            content: "$";
            position: absolute;
            right: 30px;
            top: 10px;
            font-size: 130px;
            font-weight: 800;
            color: rgba(255, 255, 255, .035);
        }

        .wallet-label {
            color: #a6b0c0;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .wallet-balance {
            font-size: 39px;
            font-weight: 750;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .wallet-note {
            color: #929eaf;
            font-size: 12px;
        }

        .wallet-actions {
            display: flex;
            gap: 12px;
            margin-top: 26px;
            position: relative;
            z-index: 1;
        }

        .btn {
            padding: 13px 21px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
            transition: .2s;
        }

        .btn-yellow {
            background: #f0b90b;
            color: #111722;
        }

        .btn-yellow:hover {
            background: #ffca28;
        }

        .btn-outline {
            border: 1px solid #596477;
            color: #e7ebf0;
        }

        .btn-outline:hover {
            border-color: #f0b90b;
        }

        .wallet-side {
            background: #161e2a;
            border: 1px solid #2c384a;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .wallet-side h3 {
            font-size: 15px;
            margin-bottom: 10px;
        }

        .wallet-side p {
            color: #8995a6;
            font-size: 13px;
            line-height: 1.7;
        }

        .wallet-side a {
            color: #f0b90b;
            text-decoration: none;
            font-size: 13px;
            margin-top: 17px;
            font-weight: 600;
        }

        .market-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 17px;
            margin-bottom: 38px;
        }

        .market-card {
            background: #151d29;
            border: 1px solid #293546;
            border-radius: 13px;
            padding: 21px;
            transition: .25s;
        }

        .market-card:hover {
            transform: translateY(-4px);
            border-color: #a88a2b;
            background: #192333;
        }

        .coin-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 23px;
        }

        .coin-icon {
            height: 43px;
            width: 43px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: bold;
            background: #272f3d;
            color: #f0b90b;
        }

        .coin-top h3 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .coin-top p {
            color: #8491a3;
            font-size: 12px;
        }

        .coin-price {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        .coin-change {
            font-size: 12px;
        }

        .positive {
            color: #0ecb81;
        }

        .negative {
            color: #f6465d;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 17px;
            margin-bottom: 38px;
        }

        .quick-card {
            display: flex;
            gap: 17px;
            align-items: flex-start;
            background: #151d29;
            border: 1px solid #293546;
            border-radius: 12px;
            padding: 23px;
            text-decoration: none;
            color: #f1f3f5;
            transition: .2s;
        }

        .quick-card:hover {
            border-color: #b18f2c;
            background: #1a2432;
            transform: translateY(-3px);
        }

        .quick-icon {
            min-width: 48px;
            height: 48px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #292719;
            color: #f0b90b;
            font-size: 21px;
        }

        .quick-card h3 {
            font-size: 15px;
            margin: 3px 0 8px;
        }

        .quick-card p {
            color: #8e9aaa;
            font-size: 12px;
            line-height: 1.7;
        }

        .account-card {
            background: #151d29;
            border: 1px solid #293546;
            border-radius: 14px;
            padding: 27px;
        }

        .account-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 17px;
            margin-top: 22px;
        }

        .detail-box {
            background: #1c2635;
            border: 1px solid #2b384b;
            border-radius: 9px;
            padding: 18px;
        }

        .detail-box span {
            display: block;
            color: #8995a6;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .detail-box strong {
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        footer {
            border-top: 1px solid #263142;
            color: #758195;
            text-align: center;
            padding: 25px;
            font-size: 12px;
            margin-top: 45px;
        }

        @media(max-width: 950px) {
            .market-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .wallet-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 650px) {
            header {
                padding: 0 4%;
                height: 65px;
            }

            .logo {
                font-size: 22px;
            }

            nav {
                gap: 13px;
            }

            nav a {
                font-size: 11px;
            }

            .profile {
                display: none;
            }

            .container {
                width: 92%;
                margin: 24px auto;
            }

            .welcome {
                padding: 25px 20px;
            }

            .welcome h1 {
                font-size: 22px;
            }

            .welcome-badge {
                display: none;
            }

            .wallet-card {
                padding: 23px;
            }

            .wallet-balance {
                font-size: 30px;
            }

            .market-grid {
                gap: 10px;
            }

            .market-card {
                padding: 15px;
            }

            .coin-price {
                font-size: 17px;
            }

            .quick-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .quick-card {
                padding: 15px;
                display: block;
            }

            .quick-icon {
                margin-bottom: 13px;
            }

            .account-details {
                grid-template-columns: 1fr;
            }

            .wallet-actions {
                flex-wrap: wrap;
            }
        }


        /* =====================================
   CRYPTOX - CLEAN WHITE + ROYAL BLUE
===================================== */

        :root {
            --bg: #F4F6FA;
            --card: #FFFFFF;
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --text: #17243B;
            --muted: #64748B;
            --border: #E2E8F0;
            --soft-blue: #EFF6FF;
            --success: #16A34A;
        }

        /* Page */
        body {
            background: var(--bg);
            color: var(--text);
        }

        /* Header */
        header {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.03);
        }

        .logo {
            color: var(--primary);
        }

        .logo span {
            color: var(--text);
        }

        nav a {
            color: var(--muted);
        }

        nav a:hover,
        nav a.active {
            color: var(--primary);
        }

        .profile {
            background: var(--soft-blue);
            border: 1px solid #DBEAFE;
            color: var(--text);
        }

        .avatar {
            background: var(--primary);
            color: #FFFFFF;
        }

        /* Welcome Section */
        .welcome {
            background: linear-gradient(115deg, #FFFFFF, #EFF6FF);
            border: 1px solid #DBEAFE;
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.06);
        }

        .welcome::after {
            border-color: rgba(37, 99, 235, 0.08);
        }

        .welcome-label {
            color: var(--primary);
        }

        .welcome h1 {
            color: var(--text);
        }

        .welcome p {
            color: var(--muted);
        }

        .welcome-badge {
            background: #DBEAFE;
            color: var(--primary-dark);
            border: 1px solid #BFDBFE;
        }

        /* Section Headings */
        .section-heading h2 {
            color: var(--text);
        }

        .section-heading span {
            color: var(--muted);
        }

        /* Wallet */
        .wallet-card {
            background: linear-gradient(125deg, #FFFFFF, #EFF6FF);
            border: 1px solid #DBEAFE;
            box-shadow: 0 8px 28px rgba(37, 99, 235, 0.06);
        }

        .wallet-card::after {
            color: rgba(37, 99, 235, 0.045);
        }

        .wallet-label {
            color: var(--muted);
        }

        .wallet-balance {
            color: var(--text);
        }

        .wallet-note {
            color: var(--muted);
        }

        .btn-yellow {
            background: var(--primary);
            color: #FFFFFF;
        }

        .btn-yellow:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            border: 1px solid var(--border);
            color: var(--primary);
            background: #FFFFFF;
        }

        .btn-outline:hover {
            border-color: var(--primary);
            background: var(--soft-blue);
        }

        /* Wallet Side Card */
        .wallet-side {
            background: #FFFFFF;
            border: 1px solid var(--border);
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.03);
        }

        .wallet-side h3 {
            color: var(--text);
        }

        .wallet-side p {
            color: var(--muted);
        }

        .wallet-side a {
            color: var(--primary);
        }

        /* Market Cards */
        .market-card {
            background: #FFFFFF;
            border: 1px solid var(--border);
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.025);
        }

        .market-card:hover {
            border-color: #93C5FD;
            background: #FFFFFF;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.08);
        }

        .coin-icon {
            background: var(--soft-blue);
            color: var(--primary);
        }

        .coin-top h3 {
            color: var(--text);
        }

        .coin-top p {
            color: var(--muted);
        }

        .coin-price {
            color: var(--text);
        }

        .positive {
            color: var(--success);
        }

        .negative {
            color: #DC2626;
        }

        /* Quick Access */
        .quick-card {
            background: #FFFFFF;
            border: 1px solid var(--border);
            color: var(--text);
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.025);
        }

        .quick-card:hover {
            border-color: #93C5FD;
            background: #FFFFFF;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.08);
        }

        .quick-icon {
            background: var(--soft-blue);
            color: var(--primary);
        }

        .quick-card h3 {
            color: var(--text);
        }

        .quick-card p {
            color: var(--muted);
        }

        /* Account Information */
        .account-card {
            background: #FFFFFF;
            border: 1px solid var(--border);
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.025);
        }

        .detail-box {
            background: #F8FAFC;
            border: 1px solid var(--border);
        }

        .detail-box span {
            color: var(--muted);
        }

        .detail-box strong {
            color: var(--text);
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border);
            color: var(--muted);
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">Crypto<span>X</span></div>

        <nav>
            <a href="dashboard.php" class="active">Overview</a>
            <a href="index.php">Markets</a>
            <a href="referrals.php">Referrals</a>
        </nav>

        <div class="profile">
            <div class="avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <?php echo htmlspecialchars($user_name); ?>
        </div>
    </header>

    <main class="container">

        <section class="welcome">
            <div class="welcome-content">
                <div class="welcome-label">CryptoX Dashboard</div>

                <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?></h1>

                <p>Your cryptocurrency account at a glance.</p>
            </div>

            <div class="welcome-badge">● Account Overview</div>
        </section>

        <section>
            <div class="section-heading">
                <h2>Wallet Overview</h2>
                <span>YOUR ACCOUNT</span>
            </div>

            <div class="wallet-grid">

                <div class="wallet-card">
                    <div class="wallet-label">Available Balance</div>

                    <div class="wallet-balance">
                        $<?php echo number_format($balance, 2); ?>
                    </div>

                    <div class="wallet-note">
                        Current wallet balance
                    </div>

                    <div class="wallet-actions">
                        <a href="add_money.php" class="btn btn-yellow">
                            + Add Money
                        </a>

                        <a href="index.php" class="btn btn-outline">
                            Start Trading
                        </a>
                    </div>
                </div>

                <div class="wallet-side">
                    <h3>Manage Your Wallet</h3>

                    <p>
                        Add funds to your account and manage your
                        balance requests from one place.
                    </p>

                    <a href="add_money.php">View wallet requests →</a>
                </div>

            </div>
        </section>

        <section>
            <div class="section-heading">
                <h2>Market Overview</h2>
                <span>LIVE PRICES · USD</span>
            </div>

            <div class="market-grid">

                <div class="market-card">
                    <div class="coin-top">
                        <div class="coin-icon">₿</div>
                        <div>
                            <h3>Bitcoin</h3>
                            <p>BTC</p>
                        </div>
                    </div>
                    <div class="coin-price" id="btc-price">Loading...</div>
                    <div class="coin-change" id="btc-change">--</div>
                </div>

                <div class="market-card">
                    <div class="coin-top">
                        <div class="coin-icon">Ξ</div>
                        <div>
                            <h3>Ethereum</h3>
                            <p>ETH</p>
                        </div>
                    </div>
                    <div class="coin-price" id="eth-price">Loading...</div>
                    <div class="coin-change" id="eth-change">--</div>
                </div>

                <div class="market-card">
                    <div class="coin-top">
                        <div class="coin-icon">B</div>
                        <div>
                            <h3>BNB</h3>
                            <p>BNB</p>
                        </div>
                    </div>
                    <div class="coin-price" id="bnb-price">Loading...</div>
                    <div class="coin-change" id="bnb-change">--</div>
                </div>

                <div class="market-card">
                    <div class="coin-top">
                        <div class="coin-icon">S</div>
                        <div>
                            <h3>Solana</h3>
                            <p>SOL</p>
                        </div>
                    </div>
                    <div class="coin-price" id="sol-price">Loading...</div>
                    <div class="coin-change" id="sol-change">--</div>
                </div>

            </div>
        </section>

        <section>
            <div class="section-heading">
                <h2>Quick Access</h2>
                <span>ACCOUNT SERVICES</span>
            </div>

            <div class="quick-grid">

                <a href="index.php" class="quick-card">
                    <div class="quick-icon">↗</div>
                    <div>
                        <h3>Trading</h3>
                        <p>Explore cryptocurrency markets and trading options.</p>
                    </div>
                </a>

                <a href="add_money.php" class="quick-card">
                    <div class="quick-icon">＋</div>
                    <div>
                        <h3>Add Money</h3>
                        <p>Submit balance requests and check their status.</p>
                    </div>
                </a>

                <a href="referrals.php" class="quick-card">
                    <div class="quick-icon">♧</div>
                    <div>
                        <h3>Referral Program</h3>
                        <p>Manage referrals and view your rewards.</p>
                    </div>
                </a>

                <a href="profile.php" class="quick-card">
                    <div class="quick-icon">♙</div>
                    <div>
                        <h3>My Profile</h3>
                        <p>View and update your account information.</p>
                    </div>
                </a>

                <a href="change_password.php" class="quick-card">
                    <div class="quick-icon">⌑</div>
                    <div>
                        <h3>Security</h3>
                        <p>Manage your password and account security.</p>
                    </div>
                </a>

                <a href="generate_recovery_key.php" class="quick-card">
                    <div class="quick-icon">◇</div>
                    <div>
                        <h3>Recovery Key</h3>
                        <p>Generate a recovery key to protect your account.</p>
                    </div>
                </a>

                <a href="delete_account.php" class="quick-card">
                    <div class="quick-icon">◆</div>
                    <div>
                        <h3>Delete Account</h3>
                        <p>Permanently delete your account.</p>
                    </div>
                </a>

                <a href="logout.php" class="quick-card">
                    <div class="quick-icon">⇥</div>
                    <div>
                        <h3>Logout</h3>
                        <p>Securely sign out of your account.</p>
                    </div>
                </a>

            </div>
        </section>

        <section class="account-card">
            <div class="section-heading">
                <h2>Account Information</h2>
                <span>PERSONAL DETAILS</span>
            </div>

            <div class="account-details">

                <div class="detail-box">
                    <span>Full Name</span>
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                </div>

                <div class="detail-box">
                    <span>Email Address</span>
                    <strong><?php echo htmlspecialchars($user_email); ?></strong>
                </div>

            </div>
        </section>

    </main>

    <footer>
        © <?php echo date("Y"); ?> CryptoX. All rights reserved.
    </footer>

    <script>
        async function loadMarketPrices() {
            try {
                const response = await fetch(
                    "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=bitcoin,ethereum,binancecoin,solana&price_change_percentage=24h"
                );

                if (!response.ok) {
                    throw new Error("Market data unavailable");
                }

                const coins = await response.json();

                const ids = {
                    bitcoin: "btc",
                    ethereum: "eth",
                    binancecoin: "bnb",
                    solana: "sol"
                };

                coins.forEach(coin => {
                    const id = ids[coin.id];

                    document.getElementById(id + "-price").textContent =
                        "$" + Number(coin.current_price).toLocaleString("en-US");

                    const change = Number(coin.price_change_percentage_24h || 0);

                    const element = document.getElementById(id + "-change");

                    element.textContent =
                        (change >= 0 ? "+" : "") + change.toFixed(2) + "% (24h)";

                    element.className =
                        "coin-change " + (change >= 0 ? "positive" : "negative");
                });

            } catch (error) {
                document.querySelectorAll(".coin-price").forEach(element => {
                    element.textContent = "Unavailable";
                });

                document.querySelectorAll(".coin-change").forEach(element => {
                    element.textContent = "Market data unavailable";
                });
            }
        }

        loadMarketPrices();
    </script>

</body>

</html>