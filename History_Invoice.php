<?php
session_start();
require_once "config.php";

/* -------------------------------------------
   LOGIN CHECK
-------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* -------------------------------------------
   GET USER DETAILS
-------------------------------------------- */
$userName = "User";

$stmtUser = $conn->prepare("
    SELECT company_name, company_email, phone, address
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userResult = $stmtUser->get_result();

if ($userResult->num_rows > 0) {
    $user = $userResult->fetch_assoc();
    $userName = $user['company_name'];
}

$stmtUser->close();

/* -------------------------------------------
   SEARCH + DATE FILTER
-------------------------------------------- */
$search = trim($_GET['search'] ?? '');
$dateRange = $_GET['date_range'] ?? '';

$where = "WHERE user_id = ?";
$params = [$userId];
$types = "i";

/* Search */
if ($search !== '') {
    $where .= " AND (
        invoice_number LIKE ?
        OR customer_name LIKE ?
        OR customer_email LIKE ?
    )";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}

/* Date Range */
if ($dateRange === '7') {
    $where .= " AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($dateRange === '30') {
    $where .= " AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($dateRange === 'month') {
    $where .= " AND YEAR(issue_date) = YEAR(CURDATE())
               AND MONTH(issue_date) = MONTH(CURDATE())";
}

/* -------------------------------------------
   STATUS COUNTS
   Note:
   Current invoices table does not contain a
   payment_status column.

   Therefore:
   - Paid = invoices with total > 0
   - Pending = invoices with total = 0
   - Overdue = 0

   This keeps the page compatible with the
   existing database structure.
-------------------------------------------- */

$totalInvoices = 0;
$paidInvoices = 0;
$pendingInvoices = 0;
$overdueInvoices = 0;

/* Total invoices */
$stmtStats = $conn->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN total > 0 THEN 1 ELSE 0 END) AS paid,
        SUM(CASE WHEN total = 0 THEN 1 ELSE 0 END) AS pending
    FROM invoices
    $where
");

$stmtStats->bind_param($types, ...$params);
$stmtStats->execute();

$statsResult = $stmtStats->get_result();

if ($statsResult->num_rows > 0) {
    $stats = $statsResult->fetch_assoc();

    $totalInvoices = (int) ($stats['total'] ?? 0);
    $paidInvoices = (int) ($stats['paid'] ?? 0);
    $pendingInvoices = (int) ($stats['pending'] ?? 0);
}

$stmtStats->close();

/*
   Overdue is kept at 0 because the current
   invoices table has no due_date/payment_status.
*/
$overdueInvoices = 0;

/* -------------------------------------------
   PAGINATION
-------------------------------------------- */
$perPage = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

/* Count filtered invoices */
$stmtCount = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM invoices
    $where
");

$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();

$countResult = $stmtCount->get_result();
$countData = $countResult->fetch_assoc();

$totalEntries = (int) ($countData['total'] ?? 0);

$stmtCount->close();

$totalPages = max(1, (int) ceil($totalEntries / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

/* -------------------------------------------
   GET INVOICES
-------------------------------------------- */
$invoices = [];

$query = "
    SELECT
        id,
        invoice_number,
        customer_name,
        issue_date,
        total
    FROM invoices
    $where
    ORDER BY id DESC
    LIMIT ? OFFSET ?
";

$listParams = $params;
$listTypes = $types . "ii";

$listParams[] = $perPage;
$listParams[] = $offset;

$stmtInvoices = $conn->prepare($query);

$stmtInvoices->bind_param($listTypes, ...$listParams);
$stmtInvoices->execute();

$invoiceResult = $stmtInvoices->get_result();

while ($row = $invoiceResult->fetch_assoc()) {

    /*
       Current DB has no payment_status.
       We display:
       - Paid when total > 0
       - Pending when total = 0
    */
    if ((float)$row['total'] > 0) {
        $status = "Paid";
    } else {
        $status = "Pending";
    }

    $invoices[] = [
        'id' => $row['id'],
        'no' => $row['invoice_number'],
        'name' => $row['customer_name'],
        'date' => date("d-m-Y", strtotime($row['issue_date'])),
        'amount' => number_format((float)$row['total'], 2),
        'status' => $status
    ];
}

$stmtInvoices->close();

/* -------------------------------------------
   PAGINATION DISPLAY
-------------------------------------------- */
if ($totalEntries > 0) {
    $showingFrom = $offset + 1;
    $showingTo = min($offset + $perPage, $totalEntries);
} else {
    $showingFrom = 0;
    $showingTo = 0;
}

/* -------------------------------------------
   HELPER FOR FILTER URL
-------------------------------------------- */
function pageUrl($pageNumber, $search, $dateRange)
{
    $query = [
        'page' => $pageNumber
    ];

    if ($search !== '') {
        $query['search'] = $search;
    }

    if ($dateRange !== '') {
        $query['date_range'] = $dateRange;
    }

    return 'History_Invoice.php?' . http_build_query($query);
}

/* -------------------------------------------
   SAFE USER DATA
-------------------------------------------- */
$safeUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

$currentDate = date("d M Y");
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>InvoicePro - Invoice History</title>

    <!-- Google Fonts -->
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
            --primary-blue: #2563eb;
            --primary-light: #eff6ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --white: #ffffff;
            --border: #e2e8f0;
            --input-bg: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            width: 250px;
            background-color: var(--bg-sidebar);
            color: var(--white);
            display: flex;
            flex-direction: column;
            padding-bottom: 20px;
            flex-shrink: 0;
        }

        .sidebar-logo {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
        }

        .sidebar-logo i {
            color: #3b82f6;
            font-size: 24px;
        }

        .nav-menu {
            flex: 1;
            list-style: none;
            padding: 0 16px;
            margin-top: 10px;
        }

        .nav-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #94a3b8;
            transition: 0.2s;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .nav-item:hover,
        .nav-item.active {
            background-color: var(--primary-blue);
            color: #fff;
        }

        .logout {
            padding: 16px;
            margin: 0 16px;
            color: #94a3b8;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            text-decoration: none;
        }

        .logout:hover {
            color: #fff;
        }

        /* ================= MAIN ================= */

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        /* ================= HEADER ================= */

        .header {
            background: var(--white);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .search-bar {
            position: relative;
            width: 350px;
        }

        .search-bar i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .search-bar input {
            width: 100%;
            padding: 10px 10px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: var(--input-bg);
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .notification {
            position: relative;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            cursor: pointer;
            font-size: 14px;
        }

        .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #c7d2fe;
        }

        /* ================= PAGE ================= */

        .page-container {
            padding: 24px 32px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        .page-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        /* ================= PAGE HEADER ================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .header-title-area {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
            width: 56px;
            height: 56px;
            background: var(--primary-light);
            color: var(--primary-blue);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .header-text h1 {
            font-size: 22px;
            color: var(--text-dark);
            margin-bottom: 4px;
            font-weight: 700;
        }

        .header-text p {
            font-size: 14px;
            color: var(--text-muted);
        }

        .btn-primary {
            background: var(--primary-blue);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* ================= STATS ================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-card p {
            font-size: 13px;
            font-weight: 600;
        }

        .stat-card h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .stat-blue {
            background-color: #f8fafc;
            border: 1px solid var(--border);
        }

        .stat-blue .stat-icon {
            background: #eff6ff;
            color: #3b82f6;
        }

        .stat-blue p {
            color: var(--text-dark);
        }

        .stat-green {
            background-color: #f0fdf4;
            border: 1px solid #dcfce7;
        }

        .stat-green .stat-icon {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-green p {
            color: #16a34a;
        }

        .stat-yellow {
            background-color: #fefce8;
            border: 1px solid #fef08a;
        }

        .stat-yellow .stat-icon {
            background: #fef08a;
            color: #d97706;
        }

        .stat-yellow p {
            color: #d97706;
        }

        .stat-red {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
        }

        .stat-red .stat-icon {
            background: #fecaca;
            color: #dc2626;
        }

        .stat-red p {
            color: #dc2626;
        }

        /* ================= FILTERS ================= */

        .filters-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .filter-input {
            position: relative;
            flex: 1;
        }

        .filter-input i.left-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            z-index: 2;
        }

        .filter-input i.right-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .filter-control {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-dark);
            outline: none;
            background: #fff;
        }

        .filter-control:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px #eff6ff;
        }

        .filter-date {
            width: 250px;
        }

        .filter-date .filter-control {
            padding-right: 35px;
            appearance: none;
            cursor: pointer;
        }

        /* ================= TABLE ================= */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-bottom: 24px;
        }

        th {
            font-size: 12px;
            color: var(--text-dark);
            font-weight: 600;
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            background: var(--input-bg);
            white-space: nowrap;
        }

        td {
            font-size: 13px;
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }

        td.dark-text {
            color: var(--text-dark);
            font-weight: 600;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 75px;
        }

        .badge.Paid {
            background: #dcfce7;
            color: #166534;
        }

        .badge.Pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.Overdue {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ================= ACTION BUTTONS ================= */

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: #fff;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
            text-decoration: none;
        }

        .btn-icon:hover {
            background: #f0f5ff;
            border-color: #bfdbfe;
        }

        .btn-icon.more {
            color: var(--text-muted);
        }

        .btn-icon.more:hover {
            background: var(--input-bg);
            border-color: var(--border);
        }

        /* ================= EMPTY STATE ================= */

        .empty-state {
            text-align: center;
            padding: 55px 20px;
            color: var(--text-muted);
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .empty-state h3 {
            color: var(--text-dark);
            font-size: 16px;
            margin-bottom: 7px;
        }

        .empty-state p {
            font-size: 13px;
            margin-bottom: 18px;
        }

        /* ================= PAGINATION ================= */

        .pagination-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 10px;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
        }

        .page-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: #fff;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
        }

        .page-btn.active {
            background: var(--primary-blue);
            color: #fff;
            border-color: var(--primary-blue);
        }

        .page-btn:hover:not(.active) {
            background: #f8fafc;
        }

        .page-btn.disabled {
            opacity: 0.45;
            pointer-events: none;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1000px) {

            .sidebar {
                width: 220px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .search-bar {
                width: 250px;
            }
        }

        @media (max-width: 750px) {

            body {
                overflow: auto;
            }

            .sidebar {
                display: none;
            }

            .main-content {
                width: 100%;
            }

            .header {
                padding: 14px 18px;
            }

            .search-bar {
                width: 200px;
            }

            .header-right {
                gap: 10px;
            }

            .notification {
                display: none;
            }

            .page-container {
                padding: 18px;
            }

            .page-card {
                padding: 20px;
            }

            .page-header {
                align-items: flex-start;
                gap: 18px;
                flex-direction: column;
            }

            .filters-row {
                flex-direction: column;
            }

            .filter-date {
                width: 100%;
            }

            .pagination-row {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        @media (max-width: 500px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }

            .search-bar {
                width: 100%;
            }

            .user-profile {
                justify-content: flex-end;
            }
        }



        .current-date {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 8px 12px;
            border-radius: 7px;
        }

        .current-date i {
            font-size: 13px;
            color: var(--primary-blue);
        }

        /* =========================================================
           DASHBOARD-MATCHED COMPACT SIZE
           Sidebar, header and page dimensions match Dashboard /
           Create_Invoice compact layout.
        ========================================================= */

        .sidebar {
            width: 225px;
            min-width: 225px;
            padding-bottom: 12px;
        }

        .sidebar-logo {
            padding: 19px 20px;
            gap: 9px;
            font-size: 20px;
        }

        .sidebar-logo i {
            font-size: 21px;
        }

        .nav-menu {
            padding: 0 12px;
            margin-top: 4px;
        }

        .nav-item {
            padding: 10px 13px;
            margin-bottom: 5px;
            gap: 11px;
            font-size: 13px;
        }

        .nav-item i {
            width: 17px;
            font-size: 14px;
        }

        .logout {
            padding: 12px 13px;
            margin: 0 12px;
            gap: 11px;
            font-size: 13px;
        }

        .header {
            padding: 12px 25px;
        }

        .search-bar {
            width: 300px;
        }

        .search-bar i {
            left: 13px;
            font-size: 13px;
        }

        .search-bar input {
            padding: 9px 10px 9px 37px;
            border-radius: 7px;
            font-size: 12px;
        }

        .header-right {
            gap: 10px;
        }

        .notification {
            display: none;
        }

        .user-profile {
            gap: 8px;
            font-size: 13px;
        }

        .user-profile img {
            width: 30px;
            height: 30px;
        }

        .page-container {
            padding: 18px 25px;
        }

        .page-card {
            padding: 18px;
            border-radius: 10px;
        }

        .page-header {
            margin-bottom: 18px;
        }

        .header-title-area {
            gap: 11px;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            font-size: 18px;
        }

        .header-text h1 {
            font-size: 17px;
            margin-bottom: 3px;
        }

        .header-text p {
            font-size: 11px;
        }

        .btn-primary {
            padding: 9px 14px;
            border-radius: 7px;
            font-size: 12px;
            gap: 7px;
        }

        .stats-grid {
            gap: 12px;
            margin-bottom: 18px;
        }

        .stat-card {
            padding: 14px;
            border-radius: 9px;
            gap: 8px;
        }

        .stat-icon {
            width: 28px;
            height: 28px;
            font-size: 14px;
        }

        .stat-card p {
            font-size: 11px;
        }

        .stat-card h2 {
            font-size: 22px;
        }

        .filters-row {
            gap: 12px;
            margin-bottom: 18px;
        }

        .filter-input i.left-icon {
            left: 11px;
            font-size: 12px;
        }

        .filter-input i.right-icon {
            right: 11px;
            font-size: 12px;
        }

        .filter-control {
            padding: 9px 11px 9px 34px;
            border-radius: 7px;
            font-size: 12px;
        }

        .filter-date {
            width: 220px;
        }

        .table-wrapper {
            border-radius: 7px;
        }

        table {
            margin-bottom: 14px;
        }

        th {
            font-size: 10px;
            padding: 9px 10px;
        }

        td {
            font-size: 11px;
            padding: 9px 10px;
        }

        .badge {
            padding: 5px 9px;
            border-radius: 16px;
            font-size: 10px;
            min-width: 65px;
        }

        .actions {
            gap: 6px;
        }

        .btn-icon {
            width: 28px;
            height: 28px;
            border-radius: 5px;
            font-size: 12px;
        }

        .empty-state {
            padding: 38px 15px;
        }

        .empty-state-icon {
            width: 54px;
            height: 54px;
            margin-bottom: 12px;
            font-size: 21px;
        }

        .empty-state h3 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .empty-state p {
            font-size: 11px;
            margin-bottom: 14px;
        }

        .pagination-row {
            font-size: 11px;
            margin-top: 7px;
        }

        .pagination-controls {
            gap: 6px;
        }

        .page-btn {
            width: 28px;
            height: 28px;
            border-radius: 5px;
            font-size: 11px;
        }

        @media (max-width: 1000px) {
            .sidebar {
                width: 205px;
                min-width: 205px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .search-bar {
                width: 250px;
            }
        }

        @media (max-width: 750px) {
            .header {
                padding: 12px 18px;
            }

            .page-container {
                padding: 15px 18px;
            }

            .page-card {
                padding: 16px;
            }
        }

    </style>

</head>

<body>

    <!-- ================= SIDEBAR ================= -->

    <div class="sidebar">

        <div class="sidebar-logo">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            InvoicePro
        </div>

        <ul class="nav-menu">

            <a href="Dashboard.php" class="nav-item">
                <i class="fa-solid fa-house"></i>
                Home
            </a>

            <a href="Create_Invoice.php" class="nav-item">
                <i class="fa-solid fa-file-circle-plus"></i>
                Create Invoice
            </a>

            <a href="History_Invoice.php" class="nav-item active">
                <i class="fa-solid fa-file-invoice"></i>
                Invoice History
            </a>

            <a href="Create_Bill.php" class="nav-item">
                <i class="fa-solid fa-receipt"></i>
                Create Bill
            </a>

            <a href="Bill_History.php" class="nav-item">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Bill History
            </a>

            <a href="Products.php" class="nav-item">
                <i class="fa-solid fa-box-open"></i>
                Products
            </a>

            <a href="Profile.php" class="nav-item">
                <i class="fa-regular fa-user"></i>
                Account
            </a>

        </ul>

        <a href="Login.php" class="logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Logout
        </a>

    </div>


    <!-- ================= MAIN CONTENT ================= -->

    <div class="main-content">

        <!-- HEADER -->

        <div class="header">

            <form method="GET" class="search-bar">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    placeholder="Search anything..."
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <?php if ($dateRange !== ''): ?>
                    <input
                        type="hidden"
                        name="date_range"
                        value="<?php echo htmlspecialchars($dateRange, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                <?php endif; ?>

            </form>

            <div class="header-right">

                <div class="current-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo $currentDate; ?>
                </div>

                <div class="user-profile">

                    <img
                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=1d4ed8&color=fff"
                        alt="User"
                    >

                    <?php echo $safeUserName; ?>

                    <i
                        class="fa-solid fa-chevron-down"
                        style="font-size: 12px; color: #64748b;"
                    ></i>

                </div>

            </div>

        </div>


        <!-- PAGE -->

        <div class="page-container">

            <div class="page-card">

                <!-- PAGE HEADER -->

                <div class="page-header">

                    <div class="header-title-area">

                        <div class="header-icon">
                            <i class="fa-regular fa-file-lines"></i>
                        </div>

                        <div class="header-text">

                            <h1>Invoice History</h1>

                            <p>
                                View and manage all your invoices.
                            </p>

                        </div>

                    </div>

                    <a
                        href="Create_Invoice.php"
                        class="btn-primary"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Create New Invoice
                    </a>

                </div>


                <!-- STATS -->

                <div class="stats-grid">

                    <div class="stat-card stat-blue">

                        <div class="stat-icon">
                            <i class="fa-regular fa-file-lines"></i>
                        </div>

                        <p>Total Invoices</p>

                        <h2>
                            <?php echo $totalInvoices; ?>
                        </h2>

                    </div>


                    <div class="stat-card stat-green">

                        <div class="stat-icon">
                            <i class="fa-regular fa-circle-check"></i>
                        </div>

                        <p>Paid Invoices</p>

                        <h2>
                            <?php echo $paidInvoices; ?>
                        </h2>

                    </div>


                    <div class="stat-card stat-yellow">

                        <div class="stat-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>

                        <p>Pending Invoices</p>

                        <h2>
                            <?php echo $pendingInvoices; ?>
                        </h2>

                    </div>


                    <div class="stat-card stat-red">

                        <div class="stat-icon">
                            <i class="fa-regular fa-circle-xmark"></i>
                        </div>

                        <p>Overdue Invoices</p>

                        <h2>
                            <?php echo $overdueInvoices; ?>
                        </h2>

                    </div>

                </div>


                <!-- FILTERS -->

                <form
                    method="GET"
                    class="filters-row"
                >

                    <div class="filter-input">

                        <i class="fa-solid fa-magnifying-glass left-icon"></i>

                        <input
                            type="text"
                            name="search"
                            class="filter-control"
                            placeholder="Search by invoice no, customer name..."
                            value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                        >

                    </div>


                    <div class="filter-input filter-date">

                        <i class="fa-regular fa-calendar left-icon"></i>

                        <select
                            name="date_range"
                            class="filter-control"
                            onchange="this.form.submit()"
                        >

                            <option value="">
                                Select Date Range
                            </option>

                            <option
                                value="7"
                                <?php echo ($dateRange === '7') ? 'selected' : ''; ?>
                            >
                                Last 7 Days
                            </option>

                            <option
                                value="30"
                                <?php echo ($dateRange === '30') ? 'selected' : ''; ?>
                            >
                                Last 30 Days
                            </option>

                            <option
                                value="month"
                                <?php echo ($dateRange === 'month') ? 'selected' : ''; ?>
                            >
                                This Month
                            </option>

                        </select>

                        <i class="fa-solid fa-chevron-down right-icon"></i>

                    </div>

                </form>


                <!-- TABLE -->

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>
                                    Invoice No.
                                </th>

                                <th>
                                    Customer Name
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

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if (count($invoices) > 0): ?>

                                <?php foreach ($invoices as $inv): ?>

                                    <tr>

                                        <td class="dark-text">
                                            <?php echo $inv['id']; ?>
                                        </td>

                                        <td class="dark-text">
                                            <?php
                                            echo htmlspecialchars(
                                                $inv['no'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </td>

                                        <td class="dark-text">
                                            <?php
                                            echo htmlspecialchars(
                                                $inv['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php echo $inv['date']; ?>
                                        </td>

                                        <td class="dark-text">
                                            ₹ <?php echo $inv['amount']; ?>
                                        </td>

                                        <td>

                                            <span
                                                class="badge <?php echo $inv['status']; ?>"
                                            >
                                                <?php echo $inv['status']; ?>
                                            </span>

                                        </td>

                                        <td>

                                            <div class="actions">

                                                <a
                                                    href="History_Invoice.php?view=<?php echo (int)$inv['id']; ?>"
                                                    class="btn-icon"
                                                    title="View Invoice"
                                                >
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>

                                                <button
                                                    type="button"
                                                    class="btn-icon more"
                                                    title="More options"
                                                    onclick="showInvoiceNumber('<?php echo htmlspecialchars($inv['no'], ENT_QUOTES, 'UTF-8'); ?>')"
                                                >
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="7">

                                        <div class="empty-state">

                                            <div class="empty-state-icon">
                                                <i class="fa-regular fa-file-lines"></i>
                                            </div>

                                            <h3>
                                                No invoices found
                                            </h3>

                                            <p>
                                                <?php if ($search !== '' || $dateRange !== ''): ?>
                                                    No invoices match your current search or date filter.
                                                <?php else: ?>
                                                    You haven't created any invoices yet.
                                                <?php endif; ?>
                                            </p>

                                            <?php if ($search !== '' || $dateRange !== ''): ?>

                                                <a
                                                    href="History_Invoice.php"
                                                    class="btn-primary"
                                                    style="display:inline-flex;"
                                                >
                                                    Clear Filters
                                                </a>

                                            <?php else: ?>

                                                <a
                                                    href="Create_Invoice.php"
                                                    class="btn-primary"
                                                    style="display:inline-flex;"
                                                >
                                                    <i class="fa-solid fa-plus"></i>
                                                    Create Invoice
                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- PAGINATION -->

                <?php if ($totalEntries > 0): ?>

                    <div class="pagination-row">

                        <div>
                            Showing
                            <?php echo $showingFrom; ?>
                            to
                            <?php echo $showingTo; ?>
                            of
                            <?php echo $totalEntries; ?>
                            entries
                        </div>


                        <div class="pagination-controls">

                            <!-- PREVIOUS -->

                            <?php if ($page > 1): ?>

                                <a
                                    href="<?php echo pageUrl($page - 1, $search, $dateRange); ?>"
                                    class="page-btn"
                                >
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>

                            <?php else: ?>

                                <span class="page-btn disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>

                            <?php endif; ?>


                            <!-- PAGE NUMBERS -->

                            <?php

                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>

                                <a
                                    href="<?php echo pageUrl($i, $search, $dateRange); ?>"
                                    class="page-btn <?php echo ($i === $page) ? 'active' : ''; ?>"
                                >
                                    <?php echo $i; ?>
                                </a>

                            <?php endfor; ?>


                            <!-- NEXT -->

                            <?php if ($page < $totalPages): ?>

                                <a
                                    href="<?php echo pageUrl($page + 1, $search, $dateRange); ?>"
                                    class="page-btn"
                                >
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>

                            <?php else: ?>

                                <span class="page-btn disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- ================= JAVASCRIPT ================= -->

    <script>

        function showInvoiceNumber(invoiceNumber) {

            alert(
                "Invoice: " + invoiceNumber +
                "\n\nMore invoice actions can be added here."
            );

        }

    </script>

</body>

</html>