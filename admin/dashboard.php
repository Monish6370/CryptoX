<?php
require_once "auth.php";
require_once "../db.php";

function getCount($conn, $sql, $key = "total")
{
    $result = mysqli_query($conn, $sql);
    if (!$result)
        return 0;
    $row = mysqli_fetch_assoc($result);
    return (int) ($row[$key] ?? 0);
}

$totalUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users");
$activeUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE status='active'");
$blockedUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE status='blocked'");

$totalRewards = getCount($conn, "SELECT COUNT(*) AS total FROM referral_rewards");
$pendingRewards = getCount($conn, "SELECT COUNT(*) AS total FROM referral_rewards WHERE status='pending'");
$approvedRewards = getCount($conn, "SELECT COUNT(*) AS total FROM referral_rewards WHERE status='approved'");
$paidRewards = getCount($conn, "SELECT COUNT(*) AS total FROM referral_rewards WHERE status='paid'");
$reversedRewards = getCount($conn, "SELECT COUNT(*) AS total FROM referral_rewards WHERE status='reversed'");

$totalTrades = getCount($conn, "SELECT COUNT(*) AS total FROM trades");
$completedTrades = getCount($conn, "SELECT COUNT(*) AS total FROM trades WHERE status='completed'");
$cancelledTrades = getCount($conn, "SELECT COUNT(*) AS total FROM trades WHERE status='cancelled'");

$recentTrades = [];
$tradeSql = "SELECT t.id, t.trade_type, t.quantity, t.price, t.total,
                    t.status, t.created_at, u.name, u.email
             FROM trades t
             LEFT JOIN users u ON t.user_id = u.id
             ORDER BY t.created_at DESC
             LIMIT 6";

$tradeResult = mysqli_query($conn, $tradeSql);
if ($tradeResult) {
    while ($row = mysqli_fetch_assoc($tradeResult)) {
        $recentTrades[] = $row;
    }
}

$adminName = $_SESSION["admin_name"] ?? "Admin";
$initial = strtoupper(substr($adminName, 0, 1));

$activePercent = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;
$blockedPercent = $totalUsers > 0 ? round(($blockedUsers / $totalUsers) * 100) : 0;

$pendingPercent = $totalRewards > 0 ? round(($pendingRewards / $totalRewards) * 100) : 0;
$approvedPercent = $totalRewards > 0 ? round(($approvedRewards / $totalRewards) * 100) : 0;
$paidPercent = $totalRewards > 0 ? round(($paidRewards / $totalRewards) * 100) : 0;

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoX | Admin Control Center</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet">

    <style>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        /* SIDEBAR */

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

        /* MAIN */

        .main {
            margin-left: 250px;
            padding: 27px 34px 35px;
            max-width: 1800px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 29px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 7px;
        }

        .breadcrumb span {
            color: var(--gold);
        }

        .topbar h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 25px;
            letter-spacing: -.7px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .live-status {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(53, 212, 154, .08);
            border: 1px solid rgba(53, 212, 154, .18);
            color: var(--green);
            padding: 9px 12px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
        }

        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 10px var(--green);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px 7px 7px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--panel);
        }

        .avatar {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: linear-gradient(135deg, #f6c84c, #9d6b00);
            color: #17130a;
            font-weight: 800;
        }

        .profile-text strong {
            display: block;
            font-size: 11px;
        }

        .profile-text small {
            display: block;
            color: var(--muted);
            font-size: 9px;
            margin-top: 3px;
        }

        /* HERO */

        .hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(240, 185, 11, .18);
            border-radius: 18px;
            padding: 27px 30px;
            margin-bottom: 27px;
            background:
                radial-gradient(circle at 88% 30%, rgba(155, 114, 255, .25), transparent 29%),
                radial-gradient(circle at 65% 120%, rgba(240, 185, 11, .13), transparent 35%),
                linear-gradient(110deg, #172137, #111727 75%);
        }

        .hero:after {
            content: "₿";
            position: absolute;
            right: 8%;
            top: -48px;
            font-size: 210px;
            font-weight: 800;
            color: rgba(255, 255, 255, .025);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-kicker {
            color: var(--gold);
            font-size: 10px;
            letter-spacing: 2px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .hero h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 25px;
            letter-spacing: -.7px;
            margin-bottom: 9px;
        }

        .hero h2 span {
            color: var(--gold);
        }

        .hero p {
            color: #a4afc3;
            font-size: 12px;
            max-width: 520px;
            line-height: 1.7;
        }

        .hero-bottom {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .hero-chip {
            border: 1px solid rgba(255, 255, 255, .11);
            background: rgba(255, 255, 255, .045);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 10px;
            color: #c6cede;
        }

        .hero-chip strong {
            color: var(--gold);
        }

        /* SECTION TITLES */

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 28px 0 15px;
        }

        .section-title h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
        }

        .section-title span {
            font-size: 10px;
            color: var(--muted);
        }

        /* METRIC CARDS */

        .metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .metric {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, #141d2d, #101522);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 19px;
            transition: .25s;
        }

        .metric:hover {
            transform: translateY(-3px);
            border-color: rgba(240, 185, 11, .28);
        }

        .metric:after {
            content: "";
            position: absolute;
            width: 95px;
            height: 95px;
            border-radius: 50%;
            right: -42px;
            top: -43px;
            background: var(--glow, rgba(240, 185, 11, .08));
            filter: blur(4px);
        }

        .metric-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 19px;
        }

        .metric-label {
            color: #9ba6ba;
            font-size: 11px;
            font-weight: 600;
        }

        .metric-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            font-size: 16px;
            background: var(--icon-bg);
            color: var(--icon-color);
        }

        .metric-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 29px;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .metric-note {
            color: #718098;
            font-size: 10px;
            margin-top: 8px;
        }

        .gold {
            --icon-bg: rgba(240, 185, 11, .12);
            --icon-color: #f0b90b;
            --glow: rgba(240, 185, 11, .12);
        }

        .purple {
            --icon-bg: rgba(155, 114, 255, .14);
            --icon-color: #ad8dff;
            --glow: rgba(155, 114, 255, .12);
        }

        .green {
            --icon-bg: rgba(53, 212, 154, .12);
            --icon-color: #35d49a;
            --glow: rgba(53, 212, 154, .12);
        }

        .red {
            --icon-bg: rgba(255, 100, 124, .12);
            --icon-color: #ff7188;
            --glow: rgba(255, 100, 124, .12);
        }

        .blue {
            --icon-bg: rgba(100, 168, 255, .12);
            --icon-color: #64a8ff;
            --glow: rgba(100, 168, 255, .12);
        }

        .orange {
            --icon-bg: rgba(255, 160, 80, .12);
            --icon-color: #ffa050;
            --glow: rgba(255, 160, 80, .12);
        }

        /* ANALYTICS PANELS */

        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 22px;
            min-width: 0;
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .panel-heading h3 {
            font-size: 13px;
            font-weight: 700;
        }

        .panel-heading span {
            color: var(--muted);
            font-size: 10px;
        }

        .breakdown-row {
            margin-bottom: 19px;
        }

        .breakdown-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 9px;
            font-size: 11px;
        }

        .breakdown-info span:first-child {
            color: #aab4c6;
        }

        .breakdown-info strong {
            color: white;
            font-size: 11px;
        }

        .track {
            width: 100%;
            height: 7px;
            border-radius: 10px;
            overflow: hidden;
            background: #242d3d;
        }

        .fill {
            height: 100%;
            border-radius: 10px;
            min-width: 0;
        }

        .fill.gold-fill {
            background: linear-gradient(90deg, #b78100, #f0c342);
        }

        .fill.green-fill {
            background: linear-gradient(90deg, #159b70, #35d49a);
        }

        .fill.red-fill {
            background: linear-gradient(90deg, #b83e59, #ff7188);
        }

        .fill.purple-fill {
            background: linear-gradient(90deg, #6741c0, #ad8dff);
        }

        .fill.blue-fill {
            background: linear-gradient(90deg, #3674c9, #64a8ff);
        }

        .empty-note {
            color: var(--muted);
            font-size: 11px;
            text-align: center;
            padding: 18px 0;
        }

        /* TRADING SUMMARY */

        .trade-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .trade-box {
            padding: 17px 12px;
            background: #151e2e;
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .trade-box small {
            color: var(--muted);
            font-size: 10px;
        }

        .trade-box strong {
            display: block;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 23px;
            margin-top: 10px;
        }

        /* RECENT TRADES */

        .table-panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 15px;
            overflow: hidden;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            background: #141c2b;
            color: #7e8aa0;
            text-align: left;
            padding: 14px 17px;
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
        }

        td {
            padding: 15px 17px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: #c7ceda;
        }

        tr:hover td {
            background: rgba(255, 255, 255, .018);
        }

        .user-cell strong {
            display: block;
            color: white;
            font-size: 11px;
        }

        .user-cell small {
            display: block;
            color: var(--muted);
            margin-top: 4px;
            font-size: 9px;
        }

        .trade-id {
            color: var(--gold);
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status.completed {
            color: var(--green);
            background: rgba(53, 212, 154, .10);
        }

        .status.cancelled {
            color: var(--red);
            background: rgba(255, 100, 124, .10);
        }

        .status.other {
            color: var(--blue);
            background: rgba(100, 168, 255, .10);
        }

        .no-data {
            text-align: center;
            padding: 35px 15px;
            color: var(--muted);
            font-size: 12px;
        }

        .footer {
            text-align: center;
            color: #647087;
            font-size: 10px;
            padding: 28px 0 5px;
        }

        .footer span {
            color: var(--gold);
        }

        /* RESPONSIVE */

        @media(max-width:1200px) {
            .metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media(max-width:900px) {
            .sidebar {
                width: 215px;
            }

            .main {
                margin-left: 215px;
                padding: 24px 20px;
            }

            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:650px) {
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
                margin-top: 5px;
            }

            .sidebar-bottom {
                margin-top: 12px;
            }

            .main {
                margin-left: 0;
                padding: 20px 14px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .hero {
                padding: 23px 19px;
            }

            .hero h2 {
                font-size: 21px;
            }

            .hero:after {
                right: -8%;
                font-size: 150px;
            }

            .metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .metric {
                padding: 15px;
            }

            .metric-value {
                font-size: 24px;
            }

            .metric-label {
                font-size: 10px;
            }

            .trade-summary {
                gap: 7px;
            }

            .trade-box {
                padding: 13px 9px;
            }

            .trade-box strong {
                font-size: 19px;
            }

            .panel {
                padding: 17px;
            }
        }

        @media(max-width:390px) {
            .metrics {
                grid-template-columns: 1fr;
            }

            .live-status {
                font-size: 9px;
            }
        }

        /* =====================================
   CRYPTOX ADMIN - LIGHT TEAL THEME
===================================== */

        :root {
            --bg: #F1F5F9;
            --panel: #FFFFFF;
            --panel2: #F8FAFC;
            --border: #E2E8F0;
            --text: #1E293B;
            --muted: #64748B;

            --gold: #0D9488;
            --purple: #7C3AED;
            --green: #16A34A;
            --red: #DC2626;
            --blue: #2563EB;
        }

        /* Main background */

        body {
            background: #F1F5F9;
            color: #1E293B;
        }

        /* Sidebar */

        .sidebar {
            background: #FFFFFF;
            border-right: 1px solid #E2E8F0;
            box-shadow: 3px 0 18px rgba(30, 41, 59, 0.035);
            backdrop-filter: none;
        }

        .brand-mark {
            background: linear-gradient(135deg, #14B8A6, #0F766E);
            color: #FFFFFF;
            box-shadow: 0 5px 18px rgba(13, 148, 136, 0.18);
        }

        .brand-name {
            color: #1E293B;
        }

        .brand-name span {
            color: #0D9488;
        }

        .menu-label {
            color: #94A3B8;
        }

        .nav-list a {
            color: #64748B;
            border-color: transparent;
        }

        .nav-list a:hover {
            color: #0F766E;
            background: #F0FDFA;
        }

        .nav-list a.active {
            color: #0F766E;
            background: #CCFBF1;
            border-color: #99F6E4;
            box-shadow: inset 3px 0 #0D9488;
        }

        .sidebar-bottom {
            border-top-color: #E2E8F0;
        }

        .sidebar-bottom a {
            color: #DC2626;
        }

        .sidebar-bottom a:hover {
            background: #FEF2F2;
        }

        /* Main content */

        .main {
            color: #1E293B;
        }

        .breadcrumb {
            color: #64748B;
        }

        .breadcrumb span {
            color: #0D9488;
        }

        .topbar h1 {
            color: #1E293B;
        }

        /* Admin status and profile */

        .live-status {
            background: #F0FDFA;
            border: 1px solid #99F6E4;
            color: #0F766E;
        }

        .live-dot {
            background: #0D9488;
            box-shadow: 0 0 8px rgba(13, 148, 136, 0.35);
        }

        .admin-profile {
            background: #FFFFFF;
            border-color: #E2E8F0;
        }

        .profile-text strong {
            color: #1E293B;
        }

        .profile-text small {
            color: #64748B;
        }

        .avatar {
            background: linear-gradient(135deg, #5EEAD4, #0D9488);
            color: #FFFFFF;
        }

        /* Welcome banner */

        .hero {
            border: 1px solid #99F6E4;
            background:
                radial-gradient(circle at 88% 30%, rgba(45, 212, 191, 0.16), transparent 29%),
                linear-gradient(110deg, #F0FDFA, #CCFBF1 75%);
        }

        .hero:after {
            color: rgba(13, 148, 136, 0.055);
        }

        .hero-kicker {
            color: #0D9488;
        }

        .hero h2 {
            color: #1E293B;
        }

        .hero h2 span {
            color: #0D9488;
        }

        .hero p {
            color: #64748B;
        }

        .hero-chip {
            border: 1px solid #99F6E4;
            background: rgba(255, 255, 255, 0.65);
            color: #475569;
        }

        .hero-chip strong {
            color: #0F766E;
        }

        /* Section headings */

        .section-title h2 {
            color: #1E293B;
        }

        .section-title span {
            color: #64748B;
        }

        /* Statistics cards */

        .metric {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(30, 41, 59, 0.035);
        }

        .metric:hover {
            border-color: #99F6E4;
            box-shadow: 0 8px 22px rgba(13, 148, 136, 0.07);
        }

        .metric-label {
            color: #64748B;
        }

        .metric-value {
            color: #1E293B;
        }

        .metric-note {
            color: #94A3B8;
        }

        /* Metric icon backgrounds */

        .gold {
            --icon-bg: #CCFBF1;
            --icon-color: #0D9488;
            --glow: rgba(13, 148, 136, 0.08);
        }

        .purple {
            --icon-bg: #EDE9FE;
            --icon-color: #7C3AED;
            --glow: rgba(124, 58, 237, 0.08);
        }

        .green {
            --icon-bg: #DCFCE7;
            --icon-color: #16A34A;
            --glow: rgba(22, 163, 74, 0.08);
        }

        .red {
            --icon-bg: #FEE2E2;
            --icon-color: #DC2626;
            --glow: rgba(220, 38, 38, 0.08);
        }

        .blue {
            --icon-bg: #DBEAFE;
            --icon-color: #2563EB;
            --glow: rgba(37, 99, 235, 0.08);
        }

        .orange {
            --icon-bg: #FFEDD5;
            --icon-color: #EA580C;
            --glow: rgba(234, 88, 12, 0.08);
        }

        /* Analytics panels */

        .panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(30, 41, 59, 0.035);
        }

        .panel-heading h3 {
            color: #1E293B;
        }

        .panel-heading span {
            color: #64748B;
        }

        .breakdown-info span:first-child {
            color: #64748B;
        }

        .breakdown-info strong {
            color: #1E293B;
        }

        .track {
            background: #E2E8F0;
        }

        .fill.gold-fill {
            background: linear-gradient(90deg, #0F766E, #2DD4BF);
        }

        .fill.green-fill {
            background: linear-gradient(90deg, #15803D, #4ADE80);
        }

        .fill.red-fill {
            background: linear-gradient(90deg, #B91C1C, #F87171);
        }

        .fill.purple-fill {
            background: linear-gradient(90deg, #6D28D9, #A78BFA);
        }

        .fill.blue-fill {
            background: linear-gradient(90deg, #1D4ED8, #60A5FA);
        }

        .empty-note {
            color: #64748B;
        }

        /* Trading summary */

        .trade-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
        }

        .trade-box small {
            color: #64748B;
        }

        .trade-box strong {
            color: #1E293B;
        }

        /* Recent trades table */

        .table-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(30, 41, 59, 0.035);
        }

        th {
            background: #F8FAFC;
            color: #64748B;
            border-bottom: 1px solid #E2E8F0;
        }

        td {
            color: #334155;
            border-top: 1px solid #F1F5F9;
        }

        tr:hover td {
            background: #F8FAFC;
        }

        .user-cell strong {
            color: #1E293B;
        }

        .user-cell small {
            color: #64748B;
        }

        .trade-id {
            color: #0D9488;
        }

        .status.completed {
            color: #15803D;
            background: #DCFCE7;
        }

        .status.cancelled {
            color: #B91C1C;
            background: #FEE2E2;
        }

        .status.other {
            color: #1D4ED8;
            background: #DBEAFE;
        }

        .no-data {
            color: #64748B;
        }

        /* Footer */

        .footer {
            color: #94A3B8;
        }

        .footer span {
            color: #0D9488;
        }
    </style>
</head>

<body>

    <aside class="sidebar">

        <div class="brand">
            <div class="brand-mark">₿</div>
            <div class="brand-name">Crypto<span>X</span></div>
        </div>

        <div class="menu-label">WORKSPACE</div>

        <nav class="nav-list">
            <a href="dashboard.php" class="active">
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


    <main class="main">

        <header class="topbar">
            <div>
                <div class="breadcrumb">Workspace / <span>Dashboard</span></div>
                <h1>Control Center</h1>
            </div>

            <div class="topbar-right">
                <div class="live-status">
                    <span class="live-dot"></span>
                    ADMIN ACCESS
                </div>

                <div class="admin-profile">
                    <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                    <div class="profile-text">
                        <strong><?= htmlspecialchars($adminName) ?></strong>
                        <small>Administrator</small>
                    </div>
                </div>
            </div>
        </header>


        <section class="hero">
            <div class="hero-content">
                <div class="hero-kicker">CRYPTOX · ADMINISTRATION</div>

                <h2>Welcome back, <span><?= htmlspecialchars($adminName) ?></span></h2>

                <p>
                    Your platform overview, account activity and trading operations
                    are all in one place. Stay in control of your CryptoX platform.
                </p>

                <div class="hero-bottom">
                    <div class="hero-chip">✦ <strong>Admin Panel</strong></div>
                    <div class="hero-chip">◈ Platform Overview</div>
                    <div class="hero-chip"><?= date("d M Y") ?></div>
                </div>
            </div>
        </section>


        <div class="section-title">
            <h2>Platform Overview</h2>
            <span>Live database counts</span>
        </div>


        <section class="metrics">

            <div class="metric gold">
                <div class="metric-head">
                    <span class="metric-label">Total Users</span>
                    <div class="metric-icon">♙</div>
                </div>
                <div class="metric-value"><?= number_format($totalUsers) ?></div>
                <div class="metric-note">Registered accounts</div>
            </div>

            <div class="metric green">
                <div class="metric-head">
                    <span class="metric-label">Active Users</span>
                    <div class="metric-icon">✓</div>
                </div>
                <div class="metric-value"><?= number_format($activeUsers) ?></div>
                <div class="metric-note"><?= $activePercent ?>% of all registered users</div>
            </div>

            <div class="metric red">
                <div class="metric-head">
                    <span class="metric-label">Blocked Users</span>
                    <div class="metric-icon">⊘</div>
                </div>
                <div class="metric-value"><?= number_format($blockedUsers) ?></div>
                <div class="metric-note"><?= $blockedPercent ?>% of all registered users</div>
            </div>

            <div class="metric purple">
                <div class="metric-head">
                    <span class="metric-label">Total Trades</span>
                    <div class="metric-icon">⇄</div>
                </div>
                <div class="metric-value"><?= number_format($totalTrades) ?></div>
                <div class="metric-note">Recorded trading orders</div>
            </div>

            <div class="metric gold">
                <div class="metric-head">
                    <span class="metric-label">Total Rewards</span>
                    <div class="metric-icon">♧</div>
                </div>
                <div class="metric-value"><?= number_format($totalRewards) ?></div>
                <div class="metric-note">Referral reward records</div>
            </div>

            <div class="metric orange">
                <div class="metric-head">
                    <span class="metric-label">Pending Rewards</span>
                    <div class="metric-icon">◷</div>
                </div>
                <div class="metric-value"><?= number_format($pendingRewards) ?></div>
                <div class="metric-note">Awaiting admin review</div>
            </div>

            <div class="metric green">
                <div class="metric-head">
                    <span class="metric-label">Approved Rewards</span>
                    <div class="metric-icon">✓</div>
                </div>
                <div class="metric-value"><?= number_format($approvedRewards) ?></div>
                <div class="metric-note">Approved by administrator</div>
            </div>

            <div class="metric blue">
                <div class="metric-head">
                    <span class="metric-label">Paid Rewards</span>
                    <div class="metric-icon">↗</div>
                </div>
                <div class="metric-value"><?= number_format($paidRewards) ?></div>
                <div class="metric-note">Marked as paid</div>
            </div>

        </section>


        <div class="section-title">
            <h2>Analytics & Distribution</h2>
            <span>Based on current records</span>
        </div>


        <section class="analytics-grid">

            <div class="panel">
                <div class="panel-heading">
                    <h3>User Account Breakdown</h3>
                    <span><?= number_format($totalUsers) ?> total</span>
                </div>

                <div class="breakdown-row">
                    <div class="breakdown-info">
                        <span>Active accounts</span>
                        <strong><?= $activeUsers ?> · <?= $activePercent ?>%</strong>
                    </div>
                    <div class="track">
                        <div class="fill green-fill" style="width:<?= $activePercent ?>%"></div>
                    </div>
                </div>

                <div class="breakdown-row">
                    <div class="breakdown-info">
                        <span>Blocked accounts</span>
                        <strong><?= $blockedUsers ?> · <?= $blockedPercent ?>%</strong>
                    </div>
                    <div class="track">
                        <div class="fill red-fill" style="width:<?= $blockedPercent ?>%"></div>
                    </div>
                </div>

                <div class="empty-note">
                    Percentages are calculated from the total registered user count.
                </div>
            </div>


            <div class="panel">
                <div class="panel-heading">
                    <h3>Referral Reward Breakdown</h3>
                    <span><?= number_format($totalRewards) ?> total</span>
                </div>

                <?php if ($totalRewards > 0): ?>

                    <div class="breakdown-row">
                        <div class="breakdown-info">
                            <span>Pending</span>
                            <strong><?= $pendingRewards ?> · <?= $pendingPercent ?>%</strong>
                        </div>
                        <div class="track">
                            <div class="fill gold-fill" style="width:<?= $pendingPercent ?>%"></div>
                        </div>
                    </div>

                    <div class="breakdown-row">
                        <div class="breakdown-info">
                            <span>Approved</span>
                            <strong><?= $approvedRewards ?> · <?= $approvedPercent ?>%</strong>
                        </div>
                        <div class="track">
                            <div class="fill purple-fill" style="width:<?= $approvedPercent ?>%"></div>
                        </div>
                    </div>

                    <div class="breakdown-row">
                        <div class="breakdown-info">
                            <span>Paid</span>
                            <strong><?= $paidRewards ?> · <?= $paidPercent ?>%</strong>
                        </div>
                        <div class="track">
                            <div class="fill green-fill" style="width:<?= $paidPercent ?>%"></div>
                        </div>
                    </div>

                    <div class="empty-note">
                        Reversed rewards: <?= number_format($reversedRewards) ?>
                    </div>

                <?php else: ?>
                    <div class="empty-note">No referral reward records available yet.</div>
                <?php endif; ?>

            </div>

        </section>


        <div class="section-title">
            <h2>Trading Activity</h2>
            <span>Current trade records</span>
        </div>


        <section class="panel">
            <div class="panel-heading">
                <h3>Trade Status Summary</h3>
                <span><?= number_format($totalTrades) ?> total trades</span>
            </div>

            <div class="trade-summary">
                <div class="trade-box">
                    <small>Total Trades</small>
                    <strong><?= number_format($totalTrades) ?></strong>
                </div>

                <div class="trade-box">
                    <small>Completed</small>
                    <strong style="color:var(--green)"><?= number_format($completedTrades) ?></strong>
                </div>

                <div class="trade-box">
                    <small>Cancelled</small>
                    <strong style="color:var(--red)"><?= number_format($cancelledTrades) ?></strong>
                </div>
            </div>
        </section>


        <div class="section-title">
            <h2>Recent Trades</h2>
            <span>Latest 6 records</span>
        </div>


        <section class="table-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Trade ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($recentTrades)): ?>

                            <?php foreach ($recentTrades as $trade): ?>
                                <?php
                                $tradeStatus = strtolower($trade["status"] ?? "");
                                $statusClass = in_array($tradeStatus, ["completed", "cancelled"])
                                    ? $tradeStatus : "other";
                                ?>

                                <tr>
                                    <td class="trade-id">#<?= (int) $trade["id"] ?></td>

                                    <td class="user-cell">
                                        <strong><?= htmlspecialchars($trade["name"] ?? "Unknown user") ?></strong>
                                        <small><?= htmlspecialchars($trade["email"] ?? "") ?></small>
                                    </td>

                                    <td><?= htmlspecialchars($trade["trade_type"] ?? "-") ?></td>
                                    <td><?= htmlspecialchars((string) $trade["quantity"]) ?></td>
                                    <td><?= number_format((float) $trade["price"], 2) ?></td>
                                    <td><?= number_format((float) $trade["total"], 2) ?></td>

                                    <td>
                                        <span class="status <?= $statusClass ?>">
                                            <?= htmlspecialchars($tradeStatus ?: "unknown") ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars($trade["created_at"] ?? "-") ?></td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    No trade records found yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>


        <footer class="footer">
            © <?= date("Y") ?> <span>CryptoX</span> · Admin Control Center
        </footer>

    </main>

</body>

</html>