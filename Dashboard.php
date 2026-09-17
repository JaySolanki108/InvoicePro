<?php

session_start();

require_once "config.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: Login.php");

    exit;
}


$userId = (int) $_SESSION['user_id'];


/* =========================================================
   GET LOGGED-IN USER DETAILS
========================================================= */

$userName = "User";

$stmtUser = $conn->prepare("
    SELECT company_name
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmtUser->bind_param("i", $userId);

$stmtUser->execute();

$userResult = $stmtUser->get_result();


if ($userResult->num_rows > 0) {

    $user = $userResult->fetch_assoc();

    $userName = $user['company_name'] ?? "User";
}


$stmtUser->close();


$safeUserName = htmlspecialchars(
    $userName,
    ENT_QUOTES,
    'UTF-8'
);


/* =========================================================
   CURRENT DATE
========================================================= */

$currentDate = date("d M Y");


/* =========================================================
   INVOICE STATISTICS
   Current database does not contain payment_status.

   Existing project logic:
   - total > 0  = Paid
   - total = 0  = Pending
   - Overdue     = Cannot be calculated
========================================================= */

$totalInvoices = 0;

$paidInvoices = 0;

$pendingInvoices = 0;

$totalSales = 0;

$totalTax = 0;


/* =========================================================
   GET INVOICE STATISTICS
========================================================= */

$stmtInvoiceStats = $conn->prepare("
    SELECT
        COUNT(*) AS total_invoices,

        COALESCE(
            SUM(
                CASE
                    WHEN total > 0 THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS paid_invoices,

        COALESCE(
            SUM(
                CASE
                    WHEN total = 0 THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_invoices,

        COALESCE(
            SUM(total),
            0
        ) AS total_sales,

        COALESCE(
            SUM(tax),
            0
        ) AS total_tax

    FROM invoices

    WHERE user_id = ?
");


$stmtInvoiceStats->bind_param(
    "i",
    $userId
);

$stmtInvoiceStats->execute();

$invoiceStatsResult =
    $stmtInvoiceStats->get_result();


if ($invoiceStatsResult->num_rows > 0) {

    $invoiceStats =
        $invoiceStatsResult->fetch_assoc();


    $totalInvoices =
        (int) ($invoiceStats['total_invoices'] ?? 0);


    $paidInvoices =
        (int) ($invoiceStats['paid_invoices'] ?? 0);


    $pendingInvoices =
        (int) ($invoiceStats['pending_invoices'] ?? 0);


    $totalSales =
        (float) ($invoiceStats['total_sales'] ?? 0);


    $totalTax =
        (float) ($invoiceStats['total_tax'] ?? 0);
}


$stmtInvoiceStats->close();


/* =========================================================
   PRODUCT STATISTICS
========================================================= */

$totalProducts = 0;

$totalProductValue = 0;


/* =========================================================
   GET PRODUCT STATISTICS
========================================================= */

$stmtProductStats = $conn->prepare("
    SELECT
        COUNT(*) AS total_products,

        COALESCE(
            SUM(price),
            0
        ) AS total_product_value

    FROM products

    WHERE user_id = ?
");


$stmtProductStats->bind_param(
    "i",
    $userId
);

$stmtProductStats->execute();

$productStatsResult =
    $stmtProductStats->get_result();


if ($productStatsResult->num_rows > 0) {

    $productStats =
        $productStatsResult->fetch_assoc();


    $totalProducts =
        (int) ($productStats['total_products'] ?? 0);


    $totalProductValue =
        (float) ($productStats['total_product_value'] ?? 0);
}


$stmtProductStats->close();


/* =========================================================
   INVOICE PERCENTAGES
========================================================= */

$paidInvoicePercent = 0;

$pendingInvoicePercent = 0;


if ($totalInvoices > 0) {

    $paidInvoicePercent =
        round(
            ($paidInvoices / $totalInvoices) * 100
        );


    $pendingInvoicePercent =
        round(
            ($pendingInvoices / $totalInvoices) * 100
        );
}


/* =========================================================
   PRODUCT AVERAGE PRICE
========================================================= */

$averageProductPrice = 0;


if ($totalProducts > 0) {

    $averageProductPrice =
        $totalProductValue / $totalProducts;
}


/* =========================================================
   RECENT INVOICES
========================================================= */

$recentInvoices = [];


$stmtRecentInvoices = $conn->prepare("
    SELECT
        id,
        invoice_number,
        customer_name,
        issue_date,
        total

    FROM invoices

    WHERE user_id = ?

    ORDER BY id DESC

    LIMIT 5
");


$stmtRecentInvoices->bind_param(
    "i",
    $userId
);

$stmtRecentInvoices->execute();

$recentInvoicesResult =
    $stmtRecentInvoices->get_result();


while (
    $invoiceRow =
    $recentInvoicesResult->fetch_assoc()
) {

    if (
        (float) $invoiceRow['total'] > 0
    ) {

        $invoiceStatus = "Paid";

    } else {

        $invoiceStatus = "Pending";
    }


    $recentInvoices[] = [

        'id' =>
            (int) $invoiceRow['id'],

        'invoice_number' =>
            $invoiceRow['invoice_number'],

        'customer_name' =>
            $invoiceRow['customer_name'],

        'issue_date' =>
            $invoiceRow['issue_date'],

        'total' =>
            (float) $invoiceRow['total'],

        'status' =>
            $invoiceStatus

    ];
}


$stmtRecentInvoices->close();


/* =========================================================
   TOTAL DOCUMENTS
========================================================= */

$totalDocuments =
    $totalInvoices;


/* =========================================================
   TOTAL SALES FORMATTED
========================================================= */

$formattedTotalSales =
    number_format(
        $totalSales,
        2
    );


$formattedTotalTax =
    number_format(
        $totalTax,
        2
    );


$formattedAverageProductPrice =
    number_format(
        $averageProductPrice,
        2
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>InvoicePro Dashboard</title>


    <!-- Google Font -->

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <style>

        :root {

            --bg-body: #f4f7fa;

            --bg-sidebar: #172136;

            --text-dark: #1e293b;

            --text-muted: #64748b;

            --text-light: #94a3b8;

            --primary: #2563eb;

            --primary-dark: #1d4ed8;

            --primary-light: #eff6ff;

            --success: #10b981;

            --success-light: #ecfdf5;

            --warning: #f59e0b;

            --warning-light: #fffbeb;

            --danger: #ef4444;

            --danger-light: #fef2f2;

            --purple: #8b5cf6;

            --purple-light: #f5f3ff;

            --white: #ffffff;

            --border: #e2e8f0;

            --input-bg: #f8fafc;

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: 'Inter', sans-serif;

        }


        body {

            background: var(--bg-body);

            color: var(--text-dark);

            display: flex;

            height: 100vh;

            overflow: hidden;

        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            width: 225px;

            min-width: 225px;

            background: var(--bg-sidebar);

            color: var(--white);

            display: flex;

            flex-direction: column;

            padding-bottom: 12px;

            box-shadow:
                2px 0 10px rgba(15, 23, 42, 0.08);

            z-index: 20;

        }


        .sidebar-logo {

            padding: 19px 20px;

            display: flex;

            align-items: center;

            gap: 9px;

            font-size: 20px;

            font-weight: 700;

            color: #fff;

            border-bottom:
                1px solid rgba(255,255,255,0.07);

        }


        .sidebar-logo i {

            color: #3b82f6;

            font-size: 21px;

        }


        .nav-menu {

            flex: 1;

            list-style: none;

            padding: 8px 12px 0;

            margin: 0;

        }


        .nav-item {

            padding: 10px 13px;

            margin-bottom: 5px;

            border-radius: 7px;

            cursor: pointer;

            display: flex;

            align-items: center;

            gap: 11px;

            color: #94a3b8;

            font-size: 13px;

            font-weight: 500;

            transition: 0.2s ease;

        }


        .nav-item i {

            width: 17px;

            text-align: center;

            font-size: 14px;

        }


        .nav-item:hover {

            background:
                rgba(59,130,246,0.12);

            color: #fff;

            transform: translateX(2px);

        }


        .nav-item.active {

            background: var(--primary);

            color: #fff;

            box-shadow:
                0 4px 10px rgba(37,99,235,0.20);

        }


        /* =====================================================
           LOGOUT
        ===================================================== */

        .logout {

            padding: 12px 13px;

            margin: 0 12px;

            color: #94a3b8;

            cursor: pointer;

            display: flex;

            align-items: center;

            gap: 11px;

            border-top:
                1px solid rgba(255,255,255,0.10);

            font-size: 13px;

            transition: 0.2s ease;

        }


        .logout i {

            width: 17px;

            text-align: center;

        }


        .logout:hover {

            color: #fff;

            transform: translateX(2px);

        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {

            flex: 1;

            min-width: 0;

            display: flex;

            flex-direction: column;

            overflow-y: auto;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .header {

            background: var(--white);

            min-height: 61px;

            padding: 12px 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            border-bottom:
                1px solid var(--border);

            box-shadow:
                0 1px 4px rgba(15,23,42,0.03);

            position: sticky;

            top: 0;

            z-index: 10;

        }


        .search-bar {

            position: relative;

            width: 300px;

        }


        .search-bar i {

            position: absolute;

            left: 13px;

            top: 50%;

            transform: translateY(-50%);

            color: var(--text-muted);

            font-size: 13px;

        }


        .search-bar input {

            width: 100%;

            height: 36px;

            padding: 9px 10px 9px 37px;

            border:
                1px solid var(--border);

            border-radius: 7px;

            outline: none;

            background: #f8fafc;

            font-size: 12px;

            color: var(--text-dark);

            transition: 0.2s ease;

        }


        .search-bar input:focus {

            background: #fff;

            border-color: #93c5fd;

            box-shadow:
                0 0 0 3px
                rgba(59,130,246,0.08);

        }


        .header-right {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .current-date {

            display: flex;

            align-items: center;

            gap: 8px;

            color: var(--text-muted);

            font-size: 12px;

            font-weight: 500;

            background: #f8fafc;

            border:
                1px solid var(--border);

            padding: 8px 12px;

            border-radius: 7px;

        }


        .current-date i {

            color: #3b82f6;

            font-size: 13px;

        }


        /* =====================================================
           DASHBOARD BODY
        ===================================================== */

        .dashboard-body {

            padding: 20px 25px;

            display: flex;

            flex-direction: column;

            gap: 18px;

        }


        /* =====================================================
           WELCOME SECTION
        ===================================================== */

        .welcome-section {

            background:
                linear-gradient(
                    135deg,
                    #ffffff,
                    #f8fbff
                );

            border:
                1px solid var(--border);

            border-radius: 12px;

            padding: 19px 20px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            box-shadow:
                0 2px 8px
                rgba(15,23,42,0.045);

        }


        .welcome-text span {

            display: inline-block;

            font-size: 10px;

            font-weight: 600;

            color: var(--primary);

            text-transform: uppercase;

            letter-spacing: 0.6px;

            margin-bottom: 5px;

        }


        .welcome-text h1 {

            font-size: 21px;

            line-height: 1.3;

            margin-bottom: 4px;

            letter-spacing: -0.4px;

        }


        .welcome-text p {

            color: var(--text-muted);

            font-size: 11px;

        }


        .welcome-icon {

            width: 52px;

            height: 52px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: var(--primary-light);

            color: var(--primary);

            font-size: 22px;

            flex-shrink: 0;

        }


        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .section-heading {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 12px;

        }


        .section-heading-left h2 {

            font-size: 15px;

            font-weight: 600;

            margin-bottom: 3px;

        }


        .section-heading-left p {

            color: var(--text-muted);

            font-size: 10px;

        }


        .section-link {

            text-decoration: none;

            color: var(--primary);

            font-size: 10px;

            font-weight: 600;

        }


        .quick-actions-section {

            width: 100%;

            background: var(--white);

            padding: 18px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            box-shadow:
                0 2px 8px
                rgba(15,23,42,0.045);

        }


        .quick-actions-grid {

            width: 100%;

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

        }


        .qa-card {

            background: #f8fafc;

            border:
                1px solid var(--border);

            min-height: 105px;

            border-radius: 9px;

            text-align: center;

            cursor: pointer;

            transition: 0.2s ease;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

        }


        .qa-card:hover {

            border-color: #93c5fd;

            transform: translateY(-3px);

            box-shadow:
                0 7px 16px
                rgba(15,23,42,0.07);

        }


        .qa-card i {

            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 11px;

            background: #fff;

            margin-bottom: 9px;

            font-size: 19px;

            box-shadow:
                0 2px 6px
                rgba(15,23,42,0.06);

        }


        .qa-card p {

            font-size: 11px;

            font-weight: 600;

            color: var(--text-dark);

        }


        .qa-blue i {

            color: #3b82f6;

        }


        .qa-green i {

            color: #10b981;

        }


        .qa-purple i {

            color: #8b5cf6;

        }


        .qa-orange i {

            color: #f59e0b;

        }


        /* =====================================================
           STATS
        ===================================================== */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

        }


        .stat-card {

            background: var(--white);

            padding: 15px;

            border-radius: 10px;

            border:
                1px solid var(--border);

            box-shadow:
                0 2px 8px
                rgba(15,23,42,0.045);

            position: relative;

            overflow: hidden;

            transition: 0.2s ease;

        }


        .stat-card:hover {

            transform: translateY(-2px);

            box-shadow:
                0 7px 16px
                rgba(15,23,42,0.07);

        }


        .stat-card::before {

            content: "";

            position: absolute;

            left: 0;

            top: 0;

            width: 3px;

            height: 100%;

        }


        .stat-blue::before {

            background: #3b82f6;

        }


        .stat-green::before {

            background: #10b981;

        }


        .stat-yellow::before {

            background: #f59e0b;

        }


        .stat-purple::before {

            background: #8b5cf6;

        }


        .stat-icon {

            width: 34px;

            height: 34px;

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 15px;

            margin-bottom: 9px;

        }


        .stat-blue .stat-icon {

            background: var(--primary-light);

            color: #3b82f6;

        }


        .stat-green .stat-icon {

            background: var(--success-light);

            color: #10b981;

        }


        .stat-yellow .stat-icon {

            background: var(--warning-light);

            color: #f59e0b;

        }


        .stat-purple .stat-icon {

            background: var(--purple-light);

            color: #8b5cf6;

        }


        .stat-card h3 {

            font-size: 11px;

            color: var(--text-muted);

            font-weight: 500;

            margin-bottom: 5px;

        }


        .stat-card h2 {

            font-size: 22px;

            color: var(--text-dark);

            margin-bottom: 5px;

            letter-spacing: -0.4px;

        }


        .stat-note {

            font-size: 9px;

            color: var(--text-muted);

        }


        /* =====================================================
           OVERVIEW GRID
        ===================================================== */

        .overview-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 18px;

        }


        .card {

            background: var(--white);

            padding: 18px;

            border-radius: 10px;

            border:
                1px solid var(--border);

            box-shadow:
                0 2px 8px
                rgba(15,23,42,0.045);

        }


        .card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding-bottom: 12px;

            margin-bottom: 10px;

            border-bottom:
                1px solid #f1f5f9;

        }


        .card-title {

            font-size: 14px;

            font-weight: 600;

        }


        .card-link {

            font-size: 10px;

            color: var(--primary);

            text-decoration: none;

            font-weight: 600;

        }


        /* =====================================================
           INVOICE PIE CHART
        ===================================================== */

        .invoice-overview {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 30px;

            min-height: 190px;

        }


        .pie-chart {

            width: 140px;

            height: 140px;

            border-radius: 50%;

            position: relative;

            flex-shrink: 0;

            box-shadow:
                0 6px 15px
                rgba(15,23,42,0.08);

        }


        .invoice-pie {

            background:
                conic-gradient(
                    #22c55e 0deg
                    <?php echo
                        ($totalInvoices > 0
                            ? ($paidInvoices / $totalInvoices) * 360
                            : 0);
                    ?>deg,

                    #fbbf24
                    <?php echo
                        ($totalInvoices > 0
                            ? ($paidInvoices / $totalInvoices) * 360
                            : 0);
                    ?>deg

                    <?php echo
                        ($totalInvoices > 0
                            ? (($paidInvoices + $pendingInvoices)
                                / $totalInvoices) * 360
                            : 0);
                    ?>deg,

                    #e2e8f0
                    <?php echo
                        ($totalInvoices > 0
                            ? (($paidInvoices + $pendingInvoices)
                                / $totalInvoices) * 360
                            : 0);
                    ?>deg 360deg
                );

        }


        .pie-chart::after {

            content: "";

            position: absolute;

            width: 76px;

            height: 76px;

            background: var(--white);

            border-radius: 50%;

            top: 50%;

            left: 50%;

            transform:
                translate(-50%, -50%);

        }


        .pie-center {

            position: absolute;

            z-index: 2;

            top: 50%;

            left: 50%;

            transform:
                translate(-50%, -50%);

            text-align: center;

            white-space: nowrap;

        }


        .pie-center strong {

            display: block;

            font-size: 22px;

            color: var(--text-dark);

        }


        .pie-center span {

            display: block;

            font-size: 9px;

            color: var(--text-muted);

            margin-top: 2px;

        }


        /* =====================================================
           LEGEND
        ===================================================== */

        .legend {

            display: flex;

            flex-direction: column;

            gap: 8px;

            min-width: 125px;

        }


        .legend-item {

            display: flex;

            align-items: center;

            gap: 7px;

            padding: 7px 8px;

            border-radius: 6px;

            background: #f8fafc;

            font-size: 10px;

            color: var(--text-muted);

        }


        .legend-dot {

            width: 9px;

            height: 9px;

            border-radius: 50%;

            flex-shrink: 0;

        }


        .dot-paid {

            background: #22c55e;

        }


        .dot-pending {

            background: #fbbf24;

        }


        .dot-empty {

            background: #cbd5e1;

        }


        .legend-item strong {

            margin-left: auto;

            color: var(--text-dark);

        }


        .legend-percent {

            font-size: 8px;

            color: var(--text-muted);

        }


        /* =====================================================
           PRODUCT SUMMARY
        ===================================================== */

        .product-summary {

            min-height: 190px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            gap: 14px;

        }


        .product-main {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 12px;

            background: #f8fafc;

            border-radius: 8px;

            border:
                1px solid #eef2f7;

        }


        .product-main-icon {

            width: 45px;

            height: 45px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: var(--purple-light);

            color: var(--purple);

            font-size: 19px;

        }


        .product-main-text span {

            display: block;

            font-size: 10px;

            color: var(--text-muted);

            margin-bottom: 3px;

        }


        .product-main-text strong {

            display: block;

            font-size: 22px;

            color: var(--text-dark);

        }


        .product-details {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

        }


        .product-detail {

            padding: 10px;

            border:
                1px solid var(--border);

            border-radius: 7px;

        }


        .product-detail span {

            display: block;

            font-size: 9px;

            color: var(--text-muted);

            margin-bottom: 4px;

        }


        .product-detail strong {

            font-size: 13px;

            color: var(--text-dark);

        }


        /* =====================================================
           RECENT INVOICES
        ===================================================== */

        .recent-card {

            padding: 18px;

        }


        .recent-table-wrapper {

            overflow-x: auto;

        }


        .recent-table {

            width: 100%;

            border-collapse: collapse;

        }


        .recent-table th {

            text-align: left;

            font-size: 9px;

            font-weight: 600;

            color: var(--text-muted);

            background: #f8fafc;

            padding: 9px 10px;

            border-bottom:
                1px solid var(--border);

            white-space: nowrap;

        }


        .recent-table td {

            font-size: 10px;

            color: var(--text-dark);

            padding: 10px;

            border-bottom:
                1px solid #f1f5f9;

            white-space: nowrap;

        }


        .recent-table tbody tr:hover {

            background: #fafcff;

        }


        .invoice-number {

            font-weight: 600;

            color: var(--primary);

        }


        .amount {

            font-weight: 600;

        }


        .status-badge {

            display: inline-flex;

            align-items: center;

            gap: 4px;

            padding: 4px 8px;

            border-radius: 20px;

            font-size: 8px;

            font-weight: 600;

        }


        .status-paid {

            background: var(--success-light);

            color: #059669;

        }


        .status-pending {

            background: var(--warning-light);

            color: #d97706;

        }


        .empty-state {

            min-height: 125px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            text-align: center;

            color: var(--text-muted);

        }


        .empty-state i {

            font-size: 28px;

            margin-bottom: 8px;

            color: #cbd5e1;

        }


        .empty-state h3 {

            font-size: 12px;

            color: var(--text-dark);

            margin-bottom: 4px;

        }


        .empty-state p {

            font-size: 9px;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {

            padding: 11px 25px;

            display: flex;

            justify-content: space-between;

            font-size: 10px;

            color: var(--text-muted);

            border-top:
                1px solid var(--border);

            margin-top: auto;

            background:
                rgba(255,255,255,0.55);

        }


        .footer-links a {

            color: var(--text-muted);

            text-decoration: none;

            margin-left: 12px;

        }


        .footer-links a:hover {

            color: var(--primary);

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .sidebar {

                width: 205px;

                min-width: 205px;

            }


            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .quick-actions-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 800px) {

            body {

                overflow: auto;

                height: auto;

                min-height: 100vh;

            }


            .sidebar {

                width: 100%;

                min-width: 0;

            }


            .main-content {

                overflow: visible;

            }


            .overview-grid {

                grid-template-columns: 1fr;

            }


            .header {

                padding: 12px 18px;

            }


            .dashboard-body {

                padding: 15px;

            }

        }


        @media (max-width: 500px) {

            .stats-grid {

                grid-template-columns:
                    1fr 1fr;

            }


            .quick-actions-grid {

                grid-template-columns:
                    1fr 1fr;

            }


            .welcome-section {

                padding: 15px;

            }


            .welcome-text h1 {

                font-size: 18px;

            }


            .welcome-icon {

                width: 45px;

                height: 45px;

                font-size: 19px;

            }


            .search-bar {

                width: 100%;

            }


            .header {

                gap: 10px;

            }


            .current-date {

                font-size: 9px;

                padding: 7px 8px;

            }


            .invoice-overview {

                flex-direction: column;

                gap: 18px;

                padding: 8px 0;

            }


            .legend {

                width: 100%;

            }


            .footer {

                flex-direction: column;

                gap: 8px;

            }

        }

    </style>

</head>


<body>


    <!-- =====================================================
         SIDEBAR
    ===================================================== -->

    <div class="sidebar">


        <div class="sidebar-logo">

            <i class="fa-solid fa-file-invoice-dollar"></i>

            InvoicePro

        </div>


        <ul class="nav-menu">


            <li
                class="nav-item active"
                onclick="window.location.href='Dashboard.php'"
            >

                <i class="fa-solid fa-house"></i>

                Home

            </li>


            <li
                class="nav-item"
                onclick="window.location.href='Create_Invoice.php'"
            >

                <i class="fa-solid fa-file-circle-plus"></i>

                Create Invoice

            </li>


            <li
                class="nav-item"
                onclick="window.location.href='History_Invoice.php'"
            >

                <i class="fa-solid fa-clock-rotate-left"></i>

                Invoice History

            </li>


            <li
                class="nav-item"
                onclick="window.location.href='Create_Bill.php'"
            >

                <i class="fa-solid fa-receipt"></i>

                Create Bill

            </li>


            <li
                class="nav-item"
                onclick="window.location.href='Bill_History.php'"
            >

                <i class="fa-solid fa-file-invoice"></i>

                Bill History

            </li>


            <li
                class="nav-item"
                onclick="window.location.href='Products.php'"
            >

                <i class="fa-solid fa-box-open"></i>

                Products

            </li>


            <li
                class="nav-item"
                onclick="window.location.href='Profile.php'"
            >

                <i class="fa-regular fa-user"></i>

                Profile

            </li>


        </ul>


        <div
            class="logout"
            onclick="window.location.href='Login.php'"
        >

            <i class="fa-solid fa-arrow-right-from-bracket"></i>

            Logout

        </div>


    </div>


    <!-- =====================================================
         MAIN CONTENT
    ===================================================== -->

    <div class="main-content">


        <!-- =================================================
             HEADER
        ================================================= -->

        <div class="header">


            <div class="search-bar">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    placeholder="Search anything..."
                >

            </div>


            <div class="header-right">

                <div class="current-date">

                    <i class="fa-regular fa-calendar"></i>

                    <?php echo $currentDate; ?>

                </div>

            </div>


        </div>


        <!-- =================================================
             DASHBOARD BODY
        ================================================= -->

        <div class="dashboard-body">


            <!-- =================================================
                 WELCOME
            ================================================= -->

            <div class="welcome-section">


                <div class="welcome-text">

                    <span>
                        Dashboard Overview
                    </span>


                    <h1>
                        Welcome back,
                        <?php echo $safeUserName; ?>
                    </h1>


                    <p>
                        Manage your invoices, products and billing activity from one place.
                    </p>

                </div>


                <div class="welcome-icon">

                    <i class="fa-solid fa-chart-line"></i>

                </div>


            </div>


            <!-- =================================================
                 QUICK ACTIONS
            ================================================= -->

            <div class="quick-actions-section">


                <div class="section-heading">

                    <div class="section-heading-left">

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Frequently used billing actions
                        </p>

                    </div>

                </div>


                <div class="quick-actions-grid">


                    <div
                        class="qa-card qa-blue"
                        onclick="window.location.href='Create_Invoice.php'"
                    >

                        <i class="fa-solid fa-file-circle-plus"></i>

                        <p>
                            Create Invoice
                        </p>

                    </div>


                    <div
                        class="qa-card qa-green"
                        onclick="window.location.href='Create_Bill.php'"
                    >

                        <i class="fa-solid fa-receipt"></i>

                        <p>
                            Create Bill
                        </p>

                    </div>


                    <div
                        class="qa-card qa-purple"
                        onclick="window.location.href='Products.php'"
                    >

                        <i class="fa-solid fa-box-open"></i>

                        <p>
                            View Products
                        </p>

                    </div>


                    <div
                        class="qa-card qa-orange"
                        onclick="window.location.href='Products.php'"
                    >

                        <i class="fa-solid fa-square-plus"></i>

                        <p>
                            Add Product
                        </p>

                    </div>


                </div>


            </div>


            <!-- =================================================
                 STATISTICS
            ================================================= -->

            <div>


                <div class="section-heading">

                    <div class="section-heading-left">

                        <h2>
                            Business Summary
                        </h2>

                        <p>
                            Live information from your account
                        </p>

                    </div>


                    <a
                        href="History_Invoice.php"
                        class="section-link"
                    >
                        View Invoice History
                    </a>

                </div>


                <div class="stats-grid">


                    <!-- TOTAL INVOICES -->

                    <div class="stat-card stat-blue">


                        <div class="stat-icon">

                            <i class="fa-regular fa-file-lines"></i>

                        </div>


                        <h3>
                            Total Invoices
                        </h3>


                        <h2>
                            <?php
                            echo number_format(
                                $totalInvoices
                            );
                            ?>
                        </h2>


                        <div class="stat-note">

                            All invoices in your account

                        </div>


                    </div>


                    <!-- PAID -->

                    <div class="stat-card stat-green">


                        <div class="stat-icon">

                            <i class="fa-regular fa-circle-check"></i>

                        </div>


                        <h3>
                            Paid Invoices
                        </h3>


                        <h2>
                            <?php
                            echo number_format(
                                $paidInvoices
                            );
                            ?>
                        </h2>


                        <div class="stat-note">

                            Based on invoice total

                        </div>


                    </div>


                    <!-- PENDING -->

                    <div class="stat-card stat-yellow">


                        <div class="stat-icon">

                            <i class="fa-regular fa-clock"></i>

                        </div>


                        <h3>
                            Pending Invoices
                        </h3>


                        <h2>
                            <?php
                            echo number_format(
                                $pendingInvoices
                            );
                            ?>
                        </h2>


                        <div class="stat-note">

                            Invoices with zero total

                        </div>


                    </div>


                    <!-- TOTAL SALES -->

                    <div class="stat-card stat-purple">


                        <div class="stat-icon">

                            <i class="fa-solid fa-indian-rupee-sign"></i>

                        </div>


                        <h3>
                            Total Sales
                        </h3>


                        <h2>
                            ₹<?php
                            echo $formattedTotalSales;
                            ?>
                        </h2>


                        <div class="stat-note">

                            Total invoice value

                        </div>


                    </div>


                </div>


            </div>


            <!-- =================================================
                 OVERVIEW
            ================================================= -->

            <div class="overview-grid">


                <!-- =================================================
                     INVOICE OVERVIEW
                ================================================= -->

                <div class="card">


                    <div class="card-header">


                        <div class="card-title">

                            Invoice Overview

                        </div>


                        <a
                            href="History_Invoice.php"
                            class="card-link"
                        >
                            View History
                        </a>


                    </div>


                    <div class="invoice-overview">


                        <div class="pie-chart invoice-pie">


                            <div class="pie-center">


                                <strong>

                                    <?php
                                    echo number_format(
                                        $totalInvoices
                                    );
                                    ?>

                                </strong>


                                <span>
                                    Invoices
                                </span>


                            </div>


                        </div>


                        <div class="legend">


                            <div class="legend-item">

                                <div
                                    class="legend-dot dot-paid"
                                ></div>


                                Paid


                                <strong>
                                    <?php
                                    echo number_format(
                                        $paidInvoices
                                    );
                                    ?>
                                </strong>


                            </div>


                            <div class="legend-item">

                                <div
                                    class="legend-dot dot-pending"
                                ></div>


                                Pending


                                <strong>
                                    <?php
                                    echo number_format(
                                        $pendingInvoices
                                    );
                                    ?>
                                </strong>


                            </div>


                            <div class="legend-item">

                                <div
                                    class="legend-dot dot-empty"
                                ></div>


                                No Value


                                <strong>

                                    <?php
                                    echo number_format(
                                        max(
                                            0,
                                            $totalInvoices
                                            -
                                            $paidInvoices
                                            -
                                            $pendingInvoices
                                        )
                                    );
                                    ?>

                                </strong>


                            </div>


                        </div>


                    </div>


                </div>


                <!-- =================================================
                     PRODUCT OVERVIEW
                ================================================= -->

                <div class="card">


                    <div class="card-header">


                        <div class="card-title">

                            Product Overview

                        </div>


                        <a
                            href="Products.php"
                            class="card-link"
                        >
                            View Products
                        </a>


                    </div>


                    <div class="product-summary">


                        <div class="product-main">


                            <div class="product-main-icon">

                                <i class="fa-solid fa-box-open"></i>

                            </div>


                            <div class="product-main-text">

                                <span>
                                    Total Products
                                </span>


                                <strong>

                                    <?php
                                    echo number_format(
                                        $totalProducts
                                    );
                                    ?>

                                </strong>

                            </div>


                        </div>


                        <div class="product-details">


                            <div class="product-detail">

                                <span>
                                    Average Price
                                </span>


                                <strong>

                                    ₹<?php
                                    echo $formattedAverageProductPrice;
                                    ?>

                                </strong>

                            </div>


                            <div class="product-detail">

                                <span>
                                    Combined Price
                                </span>


                                <strong>

                                    ₹<?php
                                    echo number_format(
                                        $totalProductValue,
                                        2
                                    );
                                    ?>

                                </strong>

                            </div>


                        </div>


                    </div>


                </div>


            </div>


            <!-- =================================================
                 RECENT INVOICES
            ================================================= -->

            <div class="card recent-card">


                <div class="card-header">


                    <div>

                        <div class="card-title">

                            Recent Invoices

                        </div>

                    </div>


                    <a
                        href="History_Invoice.php"
                        class="card-link"
                    >
                        View All
                    </a>


                </div>


                <?php if (!empty($recentInvoices)): ?>


                    <div class="recent-table-wrapper">


                        <table class="recent-table">


                            <thead>

                                <tr>

                                    <th>
                                        Invoice No.
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $recentInvoices
                                    as $invoice
                                ): ?>


                                    <tr>


                                        <td>

                                            <span
                                                class="invoice-number"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $invoice[
                                                        'invoice_number'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $invoice[
                                                    'customer_name'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo date(
                                                "d M Y",
                                                strtotime(
                                                    $invoice[
                                                        'issue_date'
                                                    ]
                                                )
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <span
                                                class="amount"
                                            >

                                                ₹<?php
                                                echo number_format(
                                                    $invoice[
                                                        'total'
                                                    ],
                                                    2
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>


                                            <?php if (
                                                $invoice[
                                                    'status'
                                                ] === "Paid"
                                            ): ?>


                                                <span
                                                    class="status-badge status-paid"
                                                >

                                                    <i
                                                        class="fa-solid fa-circle-check"
                                                    ></i>

                                                    Paid

                                                </span>


                                            <?php else: ?>


                                                <span
                                                    class="status-badge status-pending"
                                                >

                                                    <i
                                                        class="fa-solid fa-clock"
                                                    ></i>

                                                    Pending

                                                </span>


                                            <?php endif; ?>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty-state">


                        <i
                            class="fa-regular fa-file-lines"
                        ></i>


                        <h3>
                            No invoices yet
                        </h3>


                        <p>
                            Your latest invoices will appear here.
                        </p>


                    </div>


                <?php endif; ?>


            </div>


        </div>


        <!-- =================================================
             FOOTER
        ================================================= -->

        <div class="footer">


            <div>

                © 2025 InvoicePro. All rights reserved.

            </div>


            <div class="footer-links">


                <a href="#">
                    Privacy Policy
                </a>


                |


                <a href="#">
                    Terms of Service
                </a>


                |


                <a href="#">
                    Help
                </a>


            </div>


        </div>


    </div>


</body>

</html>