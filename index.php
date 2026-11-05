<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "referral_functions.php";

$pages = [
    "home" => "Home",
    "markets" => "Market overview",
    "trade" => "Trading workspace",
    "services" => "Platform features",
    "about" => "About CryptoX",
    "faq" => "Help center",
    "contact" => "Contact"
];

$page = strtolower(trim($_GET["page"] ?? "home"));

if (!isset($pages[$page])) {
    $page = "home";
}

$brand = "CryptoX";

$navItems = [
    ["Markets", "markets"],
    ["Trade", "trade"],
    ["Services", "services"],
    ["About", "about"],
    ["FAQ", "faq"],
    ["Contact", "contact"]
];

$tradeFeeRate = 0.001; // Illustrative 0.1% demo trading fee.

if (empty($_SESSION["trade_csrf"])) {
    $_SESSION["trade_csrf"] = bin2hex(random_bytes(32));
}

$tradeMessage = "";
$tradeMessageType = "error";
$tradeCoins = [];
$tradeBalance = null;
$userHoldings = [];
$tradeHistory = [];

/* -------------------------------------------------
   PROCESS DEMO BUY AND SELL ORDERS
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["place_demo_trade"])
) {
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }

    $postedToken = $_POST["trade_csrf"] ?? "";

    if (
        !is_string($postedToken)
        || !hash_equals($_SESSION["trade_csrf"], $postedToken)
    ) {
        $tradeMessage = "Invalid form submission. Refresh and try again.";
    } else {
        $userId = (int) $_SESSION["user_id"];

        $coinId = filter_input(
            INPUT_POST,
            "coin_id",
            FILTER_VALIDATE_INT
        );

        $tradeType = $_POST["trade_type"] ?? "";
        $amountInput = $_POST["amount_usd"] ?? "";

        if (
            !$coinId
            || !in_array($tradeType, ["buy", "sell"], true)
            || !is_string($amountInput)
            || !preg_match('/^\d{1,9}(\.\d{1,2})?$/', $amountInput)
            || (float) $amountInput <= 0
        ) {
            $tradeMessage = "Enter a valid coin and amount.";
        } else {
            $amountUsd = round((float) $amountInput, 2);

            try {
                mysqli_begin_transaction($conn);

                // Lock the coin row while processing this trade.
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id, name, symbol, price
                     FROM coins
                     WHERE id = ? AND is_active = 1
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare coin query.");
                }

                mysqli_stmt_bind_param($stmt, "i", $coinId);
                mysqli_stmt_execute($stmt);

                $coinResult = mysqli_stmt_get_result($stmt);
                $coin = mysqli_fetch_assoc($coinResult);

                mysqli_stmt_close($stmt);

                if (!$coin || (float) $coin["price"] <= 0) {
                    throw new RuntimeException("This demo coin is unavailable.");
                }

                $price = (float) $coin["price"];

                // Lock the user's wallet.
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT balance
                     FROM wallets
                     WHERE user_id = ?
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare wallet query.");
                }

                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);

                $walletResult = mysqli_stmt_get_result($stmt);
                $wallet = mysqli_fetch_assoc($walletResult);

                mysqli_stmt_close($stmt);

                if (!$wallet) {
                    throw new RuntimeException("Your demo wallet was not found.");
                }

                $balance = (float) $wallet["balance"];

                // Amount means the USD value of the crypto before fees.
                $total = $amountUsd;
                $fee = round($total * $tradeFeeRate, 8);
                $quantity = round($total / $price, 8);

                if ($quantity <= 0) {
                    throw new RuntimeException("The trade quantity is too small.");
                }

                // Lock an existing holding, if any.
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id, quantity
                     FROM holdings
                     WHERE user_id = ? AND coin_id = ?
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare holding query.");
                }

                mysqli_stmt_bind_param($stmt, "ii", $userId, $coinId);
                mysqli_stmt_execute($stmt);

                $holdingResult = mysqli_stmt_get_result($stmt);
                $holding = mysqli_fetch_assoc($holdingResult);

                mysqli_stmt_close($stmt);

                if ($tradeType === "buy") {
                    $debit = $total + $fee;

                    if ($balance < $debit) {
                        throw new RuntimeException(
                            "Insufficient demo wallet balance, including fee."
                        );
                    }

                    $newBalance = round($balance - $debit, 8);

                    $newQuantity = round(
                        (float) ($holding["quantity"] ?? 0) + $quantity,
                        8
                    );
                } else {
                    if (
                        !$holding
                        || (float) $holding["quantity"] + 0.000000001 < $quantity
                    ) {
                        throw new RuntimeException(
                            "You do not have enough of this coin to sell."
                        );
                    }

                    $newQuantity = max(
                        0,
                        round((float) $holding["quantity"] - $quantity, 8)
                    );

                    $newBalance = round(
                        $balance + $total - $fee,
                        8
                    );
                }

                // Update the virtual wallet.
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE wallets
                     SET balance = ?
                     WHERE user_id = ?"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare wallet update.");
                }

                mysqli_stmt_bind_param($stmt, "di", $newBalance, $userId);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException("Could not update wallet.");
                }

                mysqli_stmt_close($stmt);

                // Update an existing holding or create one on a buy.
                if ($holding) {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE holdings
                         SET quantity = ?
                         WHERE user_id = ? AND coin_id = ?"
                    );

                    if (!$stmt) {
                        throw new RuntimeException("Could not prepare holding update.");
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "dii",
                        $newQuantity,
                        $userId,
                        $coinId
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new RuntimeException("Could not update holding.");
                    }

                    mysqli_stmt_close($stmt);
                } else {
                    if ($tradeType !== "buy") {
                        throw new RuntimeException("No holding exists to sell.");
                    }

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO holdings (user_id, coin_id, quantity)
                         VALUES (?, ?, ?)"
                    );

                    if (!$stmt) {
                        throw new RuntimeException("Could not prepare holding insert.");
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iid",
                        $userId,
                        $coinId,
                        $newQuantity
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new RuntimeException("Could not create holding.");
                    }

                    mysqli_stmt_close($stmt);
                }

                // Save the trade record.
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO trades
                     (user_id, coin_id, trade_type, quantity, price, total, fee, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare trade record.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisdddd",
                    $userId,
                    $coinId,
                    $tradeType,
                    $quantity,
                    $price,
                    $total,
                    $fee
                );

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    throw new RuntimeException("Could not save trade history.");
                }

                // Capture the trade ID immediately after the INSERT.
                $tradeId = (int) mysqli_insert_id($conn);

                mysqli_stmt_close($stmt);

                // Create referral rewards from this trade's fee.
                createReferralRewards(
                    $conn,
                    (int) $userId,
                    (float) $fee,
                    "trade_" . $tradeId
                );

                // Commit the trade and referral rewards together.
                mysqli_commit($conn);


                $tradeMessage = sprintf(
                    "Demo %s successful: %.8f %s at $%.2f per coin. Fee: $%.4f.",
                    strtoupper($tradeType),
                    $quantity,
                    $coin["symbol"],
                    $price,
                    $fee
                );

                $tradeMessageType = "success";

                // Rotate the token after a successful POST.
                $_SESSION["trade_csrf"] = bin2hex(random_bytes(32));

            } catch (Throwable $e) {
                mysqli_rollback($conn);

                error_log("CryptoX demo trade error: " . $e->getMessage());

                $tradeMessage = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : "The demo trade failed. Please try again.";

                $tradeMessageType = "error";
            }
        }
    }
}

/* -------------------------------------------------
   LOAD DEMO COINS
------------------------------------------------- */

if ($page === "markets" || $page === "trade") {
    $result = mysqli_query(
        $conn,
        "SELECT id, name, symbol, price
         FROM coins
         WHERE is_active = 1
         ORDER BY id"
    );

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tradeCoins[] = $row;
        }
    }
}

if ($page === "trade") {
    if (isset($_SESSION["user_id"])) {
        $userId = (int) $_SESSION["user_id"];

        // Current wallet balance.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT balance FROM wallets WHERE user_id = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);

            if ($row) {
                $tradeBalance = (float) $row["balance"];
            }

            mysqli_stmt_close($stmt);
        }

        // User holdings.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT c.name, c.symbol, c.price, h.quantity
             FROM holdings h
             JOIN coins c ON c.id = h.coin_id
             WHERE h.user_id = ?
             ORDER BY c.name"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $userHoldings[] = $row;
            }

            mysqli_stmt_close($stmt);
        }

        // Recent trade history.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT t.trade_type, t.quantity, t.price, t.total,
                    t.fee, t.created_at, c.symbol
             FROM trades t
             JOIN coins c ON c.id = t.coin_id
             WHERE t.user_id = ?
             ORDER BY t.id DESC
             LIMIT 10"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $tradeHistory[] = $row;
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
        content="Explore digital assets, follow market movements, and learn the basics of crypto with clarity.">

    <title><?= htmlspecialchars($pages[$page] . " — " . $brand, ENT_QUOTES, "UTF-8") ?></title>

    <link rel="stylesheet" href="style.css">
</head>

<body class="crypto">

    <header class="header">
        <a class="brand" href="index.php">
            <span class="brand-mark">◈</span>CryptoX
        </a>

        <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>

        <nav class="nav">
            <a href="index.php" class="<?= $page === "home" ? "active" : "" ?>">Home</a>

            <?php foreach ($navItems as $item): ?>
                <a href="index.php?page=<?= urlencode($item[1]) ?>" class="<?= $page === $item[1] ? "active" : "" ?>">
                    <?= htmlspecialchars($item[0], ENT_QUOTES, "UTF-8") ?>
                </a>

                <?php if ($item[1] === "trade"): ?>
                    <a href="referrals.php">Referrals</a>
                <?php endif; ?>
            <?php endforeach; ?>
           
        </nav>

        <?php if (isset($_SESSION["user_id"])): ?>
            <a class="header-cta auth-cta" href="dashboard.php">
                <span>My Account</span>
                <span class="auth-arrow">↗</span>
            </a>
        <?php else: ?>
            <a class="header-cta auth-cta" href="login.php">
                <span>Login / Register</span>
                <span class="auth-arrow">↗</span>
            </a>
        <?php endif; ?>
    </header>

    <main>

        <?php if ($page === "home"): ?>

            <section class="hero" id="home">
                <div class="hero-copy">
                    <div class="eyebrow">
                        <span class="eyebrow-dot"></span>
                        THE NEXT ERA OF DIGITAL ASSETS
                    </div>

                    <h1>Trade the future.<br>Own the opportunity.</h1>

                    <p class="hero-desc">
                        A clearer way to explore digital assets, follow markets,
                        and manage your crypto portfolio.
                    </p>

                    <div class="hero-actions">
                        <a class="button primary" href=index.php?page=markets>
                            Explore Markets <span>↗</span>
                        </a>

                        <a class="button secondary" href="register.php">
                            Create Account <span>→</span>
                        </a>
                    </div>

                    <div class="trust-line">
                        <span class="avatar-stack"><i>✦</i><i>●</i><i>✿</i></span>
                        <span>Designed for real-world everyday needs</span>
                    </div>
                </div>

                <div class="hero-art image-art">
                    <img class="hero-image crypto-hero-image" src="images/crypto-hero.png"
                        alt="Golden Bitcoin coin with digital market chart visuals">

                    <div class="art-caption">
                        <span class="pulse"></span>
                        MARKET OVERVIEW <b>+2.45%</b>
                        <small>Illustrative market data</small>
                    </div>
                </div>

                <div class="hero-decoration decor-one"></div>
                <div class="hero-decoration decor-two"></div>
            </section>

            <section class="stats" aria-label="Highlights">
                <div class="stat"><strong>250+</strong><span>Digital assets</span></div>
                <div class="stat"><strong>120K+</strong><span>Community members</span></div>
                <div class="stat"><strong>24/7</strong><span>Market access</span></div>
                <div class="stat"><strong>Protected</strong><span>Account tools</span></div>
            </section>

            <section class="section market-section" id="markets">
                <div class="section-head">
                    <div>
                        <span class="section-kicker">A BETTER OVERVIEW</span>
                        <h2>Explore the market</h2>
                    </div>
                    <a class="text-link" href="index.php?page=markets">
                        Explore all <span>↗</span>
                    </a>
                </div>

                <div class="market-list">
                    <article class="market-card">
                        <div class="coin">₿</div>
                        <div class="card-info"><strong>BITCOIN</strong><span>BTC</span></div>
                        <div class="price"><strong>$67,432.28</strong><span class="trend">+2.45%</span></div>
                    </article>

                    <article class="market-card">
                        <div class="coin">◆</div>
                        <div class="card-info"><strong>ETHEREUM</strong><span>ETH</span></div>
                        <div class="price"><strong>$3,456.12</strong><span class="trend">+1.28%</span></div>
                    </article>

                    <article class="market-card">
                        <div class="coin">◆</div>
                        <div class="card-info"><strong>SOLANA</strong><span>SOL</span></div>
                        <div class="price"><strong>$184.22</strong><span class="trend">+3.12%</span></div>
                    </article>
                </div>

                <p class="fine-print">
                    Illustrative prices only. This demo is not a live exchange or investment service.
                </p>
            </section>

            <section class="section features-section" id="features">
                <div class="section-head">
                    <div>
                        <span class="section-kicker">BUILT AROUND YOU</span>
                        <h2>Everything you need to explore</h2>
                    </div>
                    <p class="section-intro">
                        Start with the basics, explore the market, and build your own informed approach.
                    </p>
                </div>

                <div class="feature-grid">
                    <article class="feature">
                        <div class="feature-icon">◈</div>
                        <h3>Market watchlists</h3>
                        <p>Keep an eye on the assets and price movements that matter to you.</p>
                    </article>

                    <article class="feature">
                        <div class="feature-icon">⌁</div>
                        <h3>Portfolio overview</h3>
                        <p>See a simple snapshot of your holdings and activity.</p>
                    </article>

                    <article class="feature">
                        <div class="feature-icon">♢</div>
                        <h3>Security first</h3>
                        <p>Explore account safety tools and practical security guidance.</p>
                    </article>

                    <article class="feature">
                        <div class="feature-icon">↗</div>
                        <h3>Learn as you go</h3>
                        <p>Understand crypto concepts with beginner-friendly explainers.</p>
                    </article>
                </div>
            </section>

            <section class="banner" id="start">
                <div class="banner-orb"></div>

                <div class="banner-copy">
                    <span class="section-kicker">START WITH CONFIDENCE</span>
                    <h2>Make your next move with more clarity.</h2>
                    <p>Start with the basics, explore the market, and build your own informed approach.</p>
                    <a class="button banner-button" href="index.php?page=trade">
                        Try demo trading <span>↗</span>
                    </a>
                </div>

                <div class="banner-symbol">₿</div>
            </section>

        <?php elseif ($page === "markets"): ?>

            <section class="section inner-page">
                <div class="page-heading">
                    <span class="section-kicker">CRYPTOX / MARKET OVERVIEW</span>
                    <h1>Explore digital assets</h1>
                    <p class="section-intro">Illustrative market values for demonstration only.</p>
                </div>

                <div class="market-list">
                    <?php foreach ($tradeCoins as $coin): ?>
                        <article class="market-card">
                            <div class="coin"><?= $coin["symbol"] === "BTC" ? "₿" : "◆" ?></div>
                            <div class="card-info">
                                <strong><?= htmlspecialchars($coin["name"], ENT_QUOTES, "UTF-8") ?></strong>
                                <span><?= htmlspecialchars($coin["symbol"], ENT_QUOTES, "UTF-8") ?></span>
                            </div>
                            <div class="price">
                                <strong>$<?= number_format((float) $coin["price"], 2) ?></strong>
                                <span class="trend">Demo price</span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <p class="fine-print">Sample values only; not live market data or investment advice.</p>
            </section>

        <?php elseif ($page === "trade"): ?>

            <section class="section inner-page">
                <div class="page-heading">
                    <span class="section-kicker">CRYPTOX / TRADING WORKSPACE</span>
                    <h1>Your demo trading workspace</h1>
                    <p class="section-intro">
                        Practice buying and selling with virtual funds. No real exchange is connected.
                    </p>
                </div>

                <?php if ($tradeMessage !== ""): ?>
                    <div class="demo-panel" role="status"
                        style="margin-bottom:20px;border:1px solid <?= $tradeMessageType === "success" ? "#27845b" : "#9b4242" ?>;">
                        <?= htmlspecialchars($tradeMessage, ENT_QUOTES, "UTF-8") ?>
                    </div>
                <?php endif; ?>

                <?php if (!isset($_SESSION["user_id"])): ?>

                    <div class="demo-panel">
                        <p>Log in to access your demo wallet and trading workspace.</p>
                        <a class="button primary" href="login.php">Login to continue ↗</a>
                    </div>

                <?php elseif ($tradeBalance === null): ?>

                    <div class="demo-panel">
                        <p>Your demo wallet was not found. Please contact support.</p>
                    </div>

                <?php else: ?>

                    <div class="demo-panel" style="margin-bottom:20px;">
                        <span class="section-kicker">VIRTUAL USD BALANCE</span>
                        <h2 style="margin:10px 0;">
                            $<?= number_format($tradeBalance, 2) ?>
                        </h2>
                        <p class="fine-print">Virtual demo funds only. No deposits or withdrawals.</p>
                    </div>

                    <div class="demo-panel">
                        <?php if (empty($tradeCoins)): ?>
                            <p>No active demo coins are available.</p>
                        <?php else: ?>
                            <form method="POST" action="index.php?page=trade">
                                <input type="hidden" name="trade_csrf"
                                    value="<?= htmlspecialchars($_SESSION["trade_csrf"], ENT_QUOTES, "UTF-8") ?>">

                                <div class="trade-grid">
                                    <label>
                                        Cryptocurrency
                                        <select name="coin_id" required>
                                            <?php foreach ($tradeCoins as $coin): ?>
                                                <option value="<?= (int) $coin["id"] ?>">
                                                    <?= htmlspecialchars(
                                                        $coin["name"] . " (" . $coin["symbol"] . ")",
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>
                                                    — $<?= number_format((float) $coin["price"], 2) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>
                                        Trade action
                                        <select name="trade_type" required>
                                            <option value="buy">Buy</option>
                                            <option value="sell">Sell</option>
                                        </select>
                                    </label>

                                    <label>
                                        Trade value (USD)
                                        <input type="number" name="amount_usd" min="0.01" max="100000000" step="0.01"
                                            placeholder="10.00" required>
                                    </label>

                                    <button class="button primary" type="submit" name="place_demo_trade" value="1">
                                        Execute demo trade ↗
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <p class="fine-print">
                            An illustrative 0.1% demo trading fee applies. Prices are not live market quotes.
                        </p>
                    </div>

                    <div class="demo-panel" style="margin-top:20px;">
                        <h2>My holdings</h2>

                        <?php if (empty($userHoldings)): ?>
                            <p class="fine-print">You don't own any demo coins yet.</p>
                        <?php else: ?>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;text-align:left;">
                                    <thead>
                                        <tr>
                                            <th>Asset</th>
                                            <th>Quantity</th>
                                            <th>Illustrative value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userHoldings as $holding): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($holding["name"], ENT_QUOTES, "UTF-8") ?>
                                                    (<?= htmlspecialchars($holding["symbol"], ENT_QUOTES, "UTF-8") ?>)
                                                </td>
                                                <td><?= number_format((float) $holding["quantity"], 8) ?></td>
                                                <td>
                                                    $<?= number_format(
                                                        (float) $holding["quantity"] * (float) $holding["price"],
                                                        2
                                                    ) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="demo-panel" style="margin-top:20px;">
                        <h2>Recent trades</h2>

                        <?php if (empty($tradeHistory)): ?>
                            <p class="fine-print">No demo trades yet.</p>
                        <?php else: ?>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;text-align:left;">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Asset</th>
                                            <th>Quantity</th>
                                            <th>Value</th>
                                            <th>Fee</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tradeHistory as $trade): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(strtoupper($trade["trade_type"]), ENT_QUOTES, "UTF-8") ?></td>
                                                <td><?= htmlspecialchars($trade["symbol"], ENT_QUOTES, "UTF-8") ?></td>
                                                <td><?= number_format((float) $trade["quantity"], 8) ?></td>
                                                <td>$<?= number_format((float) $trade["total"], 2) ?></td>
                                                <td>$<?= number_format((float) $trade["fee"], 4) ?></td>
                                                <td><?= htmlspecialchars($trade["created_at"], ENT_QUOTES, "UTF-8") ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
            </section>

            <section class="banner">
                <div class="banner-orb"></div>
                <div class="banner-copy">
                    <span class="section-kicker">EXPLORE CRYPTOX</span>
                    <h2>Keep learning with demo trading.</h2>
                    <p>Practice with virtual funds and learn how trades affect your wallet and holdings.</p>
                    <a class="button banner-button" href="index.php">Back to homepage ↗</a>
                </div>
                <div class="banner-symbol">◈</div>
            </section>

        <?php elseif ($page === "services"): ?>

            <section class="section inner-page">
                <div class="page-heading">
                    <span class="section-kicker">CRYPTOX / PLATFORM FEATURES</span>
                    <h1>Tools for your crypto journey</h1>
                    <p class="section-intro">Explore example tools designed to help users learn and review digital assets.
                    </p>
                </div>

                <div class="feature-grid">
                    <article class="feature">
                        <div class="feature-icon">◈</div>
                        <h3>Market watchlists</h3>
                        <p>Follow assets in one place.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">⌁</div>
                        <h3>Portfolio overview</h3>
                        <p>Review an illustrative portfolio snapshot.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">♢</div>
                        <h3>Security education</h3>
                        <p>Learn practical account safety habits.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">↗</div>
                        <h3>Learning center</h3>
                        <p>Build your knowledge step by step.</p>
                    </article>
                </div>
            </section>

        <?php elseif ($page === "about"): ?>

            <section class="section inner-page">
                <div class="page-heading">
                    <span class="section-kicker">CRYPTOX / ABOUT CRYPTOX</span>
                    <h1>Digital assets, made easier to understand</h1>
                    <p class="section-intro">
                        CryptoX is a concept platform focused on presenting crypto information in a clear, approachable
                        interface.
                    </p>
                </div>
            </section>

        <?php elseif ($page === "faq"): ?>

            <section class="section inner-page">
                <div class="page-heading">
                    <span class="section-kicker">CRYPTOX / HELP CENTER</span>
                    <h1>Frequently asked questions</h1>
                    <p class="section-intro">Quick answers about this CryptoX demo.</p>
                </div>

                <div class="faq-list">
                    <details class="faq-row">
                        <summary>Is this a real exchange?</summary>
                        <p>No. This is a simulated demo and does not connect to a real exchange.</p>
                    </details>
                    <details class="faq-row">
                        <summary>Are prices live?</summary>
                        <p>No. The prices in this project are illustrative values stored in your database.</p>
                    </details>
                    <details class="faq-row">
                        <summary>Can I create an account?</summary>
                        <p>You can register and log in using the site's account system.</p>
                    </details>
                    <details class="faq-row">
                        <summary>Is crypto risk-free?</summary>
                        <p>No. Digital assets are volatile and can involve substantial risk.</p>
                    </details>
                </div>
            </section>

        <?php elseif ($page === "contact"): ?>

            <section class="section inner-page">
                <div class="page-heading">
                    <span class="section-kicker">CRYPTOX / CONTACT</span>
                    <h1>Talk to the CryptoX team</h1>
                    <p class="section-intro">This demo contact form does not send messages to a server.</p>
                </div>

                <form class="demo-panel contact-form"
                    onsubmit="event.preventDefault();document.getElementById('contact-note').hidden=false;this.reset()">
                    <label>Your name<input required placeholder="Enter your name"></label>
                    <label>Email address<input type="email" required placeholder="you@example.com"></label>
                    <label>Message<textarea required rows="5" placeholder="How can we help?"></textarea></label>
                    <button class="button primary" type="submit">Send demo message ↗</button>
                    <p id="contact-note" class="fine-print" hidden>
                        Thanks! This is a demo only; the message was not sent to a server.
                    </p>
                </form>
            </section>

        <?php endif; ?>

    </main>

    <footer class="footer">
        <a class="brand" href="index.php"><span class="brand-mark">◈</span>CryptoX</a>

        <p>Explore digital assets, follow market movements, and learn the basics of crypto with clarity.</p>

        <div class="footer-bottom">
            <span>© 2026 CryptoX. Demo website.</span>
            <span>
                <a href="index.php?page=about">About</a> ·
                <a href="index.php?page=faq">FAQ</a> ·
                <a href="index.php?page=contact">Contact</a>
            </span>
        </div>
    </footer>

    <script src="script.js"></script>
</body>

</html>
