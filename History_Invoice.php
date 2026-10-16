
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

require_once "config.php";

$userId = (int)$_SESSION['user_id'];

/* =========================================================
   CSRF TOKEN
========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/* =========================================================
   HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   USER DETAILS
========================================================= */
$userStmt = $db->prepare("
    SELECT company_name, company_email, phone, address
    FROM users
    WHERE id = :user_id
    LIMIT 1
");

$userStmt->execute(['user_id' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

$companyName = $user['company_name'] ?? 'Invoice Pro';

/* =========================================================
   DELETE INVOICE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Invalid security token.'
        ];

        header("Location: History_Invoice.php");
        exit();
    }

    $invoiceId = (int)($_POST['invoice_id'] ?? 0);

    if ($invoiceId > 0) {
        try {
            $db->beginTransaction();

            $checkStmt = $db->prepare("
                SELECT id
                FROM invoices
                WHERE id = :invoice_id AND user_id = :user_id
                LIMIT 1
            ");

            $checkStmt->execute([
                'invoice_id' => $invoiceId,
                'user_id' => $userId
            ]);

            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Invoice not found.");
            }

            $itemStmt = $db->prepare("
                DELETE FROM invoice_items
                WHERE invoice_id = :invoice_id
            ");

            $itemStmt->execute(['invoice_id' => $invoiceId]);

            $invoiceStmt = $db->prepare("
                DELETE FROM invoices
                WHERE id = :invoice_id AND user_id = :user_id
            ");

            $invoiceStmt->execute([
                'invoice_id' => $invoiceId,
                'user_id' => $userId
            ]);

            if ($invoiceStmt->rowCount() <= 0) {
                throw new Exception("Invoice could not be deleted.");
            }

            $db->commit();

            $_SESSION['history_message'] = [
                'type' => 'success',
                'text' => 'Invoice deleted successfully.'
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log("Invoice delete error: " . $e->getMessage());

            $_SESSION['history_message'] = [
                'type' => 'error',
                'text' => 'Unable to delete invoice. Please try again.'
            ];
        }
    } else {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Invalid invoice.'
        ];
    }

    header("Location: History_Invoice.php");
    exit();
}

/* =========================================================
   UPDATE INVOICE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Invalid security token.'
        ];

        header("Location: History_Invoice.php");
        exit();
    }

    $invoiceId = (int)($_POST['invoice_id'] ?? 0);

    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $issueDate = trim($_POST['issue_date'] ?? '');

    $subtotalInput = $_POST['subtotal'] ?? 0;
    $taxInput = $_POST['tax'] ?? 0;

    if (
        $invoiceId <= 0 ||
        $customerName === '' ||
        $issueDate === ''
    ) {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Please fill all required fields.'
        ];

        header("Location: History_Invoice.php");
        exit();
    }

    $dateObject = DateTime::createFromFormat('!Y-m-d', $issueDate);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $issueDate) {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Please enter a valid invoice date.'
        ];

        header("Location: History_Invoice.php");
        exit();
    }

    if (
        !is_numeric($subtotalInput) ||
        !is_numeric($taxInput) ||
        (float)$subtotalInput < 0 ||
        (float)$taxInput < 0
    ) {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Please enter valid non-negative amounts.'
        ];

        header("Location: History_Invoice.php");
        exit();
    }

    if (
        $customerEmail !== '' &&
        !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
    ) {
        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Please enter a valid customer email.'
        ];

        header("Location: History_Invoice.php");
        exit();
    }

    $subtotal = (float)$subtotalInput;
    $tax = (float)$taxInput;
    $total = $subtotal + $tax;

    try {
        $checkStmt = $db->prepare("
            SELECT id
            FROM invoices
            WHERE id = :invoice_id AND user_id = :user_id
            LIMIT 1
        ");

        $checkStmt->execute([
            'invoice_id' => $invoiceId,
            'user_id' => $userId
        ]);

        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['history_message'] = [
                'type' => 'error',
                'text' => 'Invoice not found.'
            ];

            header("Location: History_Invoice.php");
            exit();
        }

        $updateStmt = $db->prepare("
            UPDATE invoices
            SET
                customer_name = :customer_name,
                customer_email = :customer_email,
                customer_phone = :customer_phone,
                customer_address = :customer_address,
                issue_date = :issue_date,
                subtotal = :subtotal,
                tax = :tax,
                total = :total
            WHERE id = :invoice_id AND user_id = :user_id
        ");

        $updateStmt->execute([
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_address' => $customerAddress,
            'issue_date' => $issueDate,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'invoice_id' => $invoiceId,
            'user_id' => $userId
        ]);

        $_SESSION['history_message'] = [
            'type' => 'success',
            'text' => 'Invoice updated successfully.'
        ];

    } catch (Throwable $e) {
        error_log("Invoice update error: " . $e->getMessage());

        $_SESSION['history_message'] = [
            'type' => 'error',
            'text' => 'Unable to update invoice. Please try again.'
        ];
    }

    header("Location: History_Invoice.php");
    exit();
}

/* =========================================================
   SEARCH + FILTER
========================================================= */
$search = trim($_GET['search'] ?? '');
$dateRange = $_GET['date_range'] ?? '';

$allowedDateRanges = ['', '7', '30', 'month'];

if (!in_array($dateRange, $allowedDateRanges, true)) {
    $dateRange = '';
}

$where = " WHERE i.user_id = :user_id ";
$params = ['user_id' => $userId];

if ($search !== '') {
    $where .= "
        AND (
            i.invoice_number LIKE :search_number
            OR i.customer_name LIKE :search_customer
            OR i.customer_email LIKE :search_email
        )
    ";

    $searchValue = '%' . $search . '%';

    $params['search_number'] = $searchValue;
    $params['search_customer'] = $searchValue;
    $params['search_email'] = $searchValue;
}

if ($dateRange === '7') {
    $where .= " AND i.issue_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";

} elseif ($dateRange === '30') {
    $where .= " AND i.issue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ";

} elseif ($dateRange === 'month') {
    $where .= "
        AND YEAR(i.issue_date) = YEAR(CURDATE())
        AND MONTH(i.issue_date) = MONTH(CURDATE())
    ";
}

/* =========================================================
   STATS
========================================================= */
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN i.total > 0 THEN 1 ELSE 0 END), 0) AS paid,
        COALESCE(SUM(CASE WHEN i.total = 0 THEN 1 ELSE 0 END), 0) AS pending
    FROM invoices i
    $where
");

$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalInvoices = (int)($stats['total'] ?? 0);
$paidInvoices = (int)($stats['paid'] ?? 0);
$pendingInvoices = (int)($stats['pending'] ?? 0);

/* =========================================================
   TOTAL REVENUE
========================================================= */
$revenueStmt = $db->prepare("
    SELECT COALESCE(SUM(i.total), 0) AS revenue
    FROM invoices i
    $where
");

$revenueStmt->execute($params);
$revenueData = $revenueStmt->fetch(PDO::FETCH_ASSOC);

$totalRevenue = (float)($revenueData['revenue'] ?? 0);

/* =========================================================
   PAGINATION
========================================================= */
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $db->prepare("
    SELECT COUNT(*) AS total
    FROM invoices i
    $where
");

$countStmt->execute($params);
$countData = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalRows = (int)($countData['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

/* =========================================================
   FETCH INVOICES
========================================================= */
$invoiceSql = "
    SELECT
        i.id,
        i.invoice_number,
        i.customer_name,
        i.customer_email,
        i.customer_phone,
        i.customer_address,
        i.issue_date,
        i.subtotal,
        i.tax,
        i.total
    FROM invoices i
    $where
    ORDER BY i.id DESC
    LIMIT :limit OFFSET :offset
";

$invoiceStmt = $db->prepare($invoiceSql);

foreach ($params as $key => $value) {
    $invoiceStmt->bindValue(':' . $key, $value);
}

$invoiceStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$invoiceStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$invoiceStmt->execute();
$invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   VIEW INVOICE
========================================================= */
$viewInvoice = null;

if (isset($_GET['view'])) {
    $viewId = (int)$_GET['view'];

    if ($viewId > 0) {
        $viewStmt = $db->prepare("
            SELECT
                i.id,
                i.invoice_number,
                i.customer_name,
                i.customer_email,
                i.customer_phone,
                i.customer_address,
                i.issue_date,
                i.subtotal,
                i.tax,
                i.total
            FROM invoices i
            WHERE i.id = :invoice_id
              AND i.user_id = :user_id
            LIMIT 1
        ");

        $viewStmt->execute([
            'invoice_id' => $viewId,
            'user_id' => $userId
        ]);

        $viewInvoice = $viewStmt->fetch(PDO::FETCH_ASSOC);

        if ($viewInvoice) {
            $itemStmt = $db->prepare("
                SELECT
                    product_name,
                    quantity,
                    rate,
                    amount
                FROM invoice_items
                WHERE invoice_id = :invoice_id
                ORDER BY id ASC
            ");

            $itemStmt->execute(['invoice_id' => $viewId]);

            $viewInvoice['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

/* =========================================================
   URL HELPER
========================================================= */
function pageUrl($pageNumber)
{
    $query = [];

    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $query['search'] = $_GET['search'];
    }

    if (isset($_GET['date_range']) && $_GET['date_range'] !== '') {
        $query['date_range'] = $_GET['date_range'];
    }

    $query['page'] = (int)$pageNumber;

    return 'History_Invoice.php?' . http_build_query($query);
}

/* =========================================================
   SESSION MESSAGE
========================================================= */
$message = $_SESSION['history_message'] ?? null;
unset($_SESSION['history_message']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Invoice History | Invoice Pro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --black: #080808;
            --black-soft: #0d0d0d;
            --black-card: #111111;
            --black-hover: #151515;

            --gold: #d4af37;
            --gold-light: #f2d675;

            --gold-soft: rgba(212,175,55,0.08);
            --gold-border: rgba(212,175,55,0.24);
            --gold-border-hover: rgba(212,175,55,0.55);

            --white: #ffffff;
            --text: #eeeeee;
            --muted: #888888;

            --success: #22c55e;
            --warning: #eab308;
            --danger: #ef4444;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--black);
            color: var(--text);
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }

        /* =====================================================
           TOP NAVBAR
        ===================================================== */

        .topbar {
            height: 76px;
            padding: 0 45px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background: rgba(8,8,8,0.96);

            border-bottom: 1px solid var(--gold-border);

            position: sticky;
            top: 0;
            z-index: 1000;

            backdrop-filter: blur(14px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;

            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--gold-light);
        }

        .brand i {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--gold);
            border-radius: 10px;
            color: var(--gold);
            background: rgba(212,175,55,0.06);
            font-size: 17px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link,
        .nav-icon {
            height: 39px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            color: var(--muted);
            border: 1px solid transparent;
            background: transparent;

            transition: all 0.25s ease;
        }

        .nav-link {
            padding: 0 12px;
            gap: 7px;
            font-size: 11px;
            font-weight: 600;
        }

        .nav-icon {
            width: 39px;
            cursor: pointer;
            font-size: 15px;
        }

        .nav-link:hover {
            color: var(--white);
            background: var(--gold-soft);
            border-color: var(--gold-border);
        }

        .nav-icon:hover {
            color: var(--black);
            background: var(--gold);
            border-color: var(--gold);
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(212,175,55,0.20);
        }

        .nav-link.active {
            color: var(--gold);
            background: var(--gold-soft);
            border-color: var(--gold-border);
        }

        /* =====================================================
           PAGE
        ===================================================== */

        .page {
            width: 100%;
            max-width: 1270px;
            margin: 0 auto;
            padding: 48px 45px 60px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 28px;
        }

        .page-title-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-icon {
            width: 46px;
            height: 46px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            color: var(--gold);
            background: var(--gold-soft);

            border: 1px solid var(--gold-border);

            font-size: 19px;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 5px;
        }

        .page-title p {
            font-size: 13px;
            color: var(--muted);
        }

        .create-btn {
            border: 1px solid var(--gold);
            background: var(--gold);
            color: #080808;

            padding: 11px 17px;

            border-radius: 9px;

            display: inline-flex;
            align-items: center;
            gap: 8px;

            font-size: 13px;
            font-weight: 700;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .create-btn:hover {
            background: var(--gold-light);
            border-color: var(--gold-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(212,175,55,0.18);
        }

        /* =====================================================
           MESSAGE
        ===================================================== */

        .message {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;

            font-size: 13px;

            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: rgba(34,197,94,0.08);
            border: 1px solid rgba(34,197,94,0.25);
            color: #86efac;
        }

        .message.error {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
        }

        /* =====================================================
           STATS
        ===================================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;

            margin-bottom: 34px;
        }

        .stat-card {
            background: var(--black-card);

            border: 1px solid var(--gold-border);

            border-radius: 12px;

            padding: 19px;

            transition: 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--gold-border-hover);
            background: var(--black-hover);
            transform: translateY(-2px);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 16px;
        }

        .stat-icon {
            width: 38px;
            height: 38px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;

            color: var(--gold);
            background: var(--gold-soft);

            border: 1px solid var(--gold-border);
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 23px;
            font-weight: 700;
            color: var(--white);
        }

        /* =====================================================
           FILTER CARD
        ===================================================== */

        .filter-card {
            background: var(--black-card);

            border: 1px solid var(--gold-border);

            border-radius: 12px;

            padding: 17px;

            margin-bottom: 18px;
        }

        .filters {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);

            color: #666;
            font-size: 13px;
        }

        .search-box input,
        .filter-select {
            width: 100%;

            height: 42px;

            background: #0c0c0c;

            border: 1px solid var(--gold-border);

            border-radius: 9px;

            color: var(--text);

            outline: none;

            font-size: 13px;

            transition: 0.2s ease;
        }

        .search-box input {
            padding: 0 14px 0 38px;
        }

        .filter-select {
            width: 175px;
            padding: 0 12px;
        }

        .search-box input:focus,
        .filter-select:focus {
            border-color: var(--gold-border-hover);
            box-shadow: 0 0 0 3px var(--gold-soft);
        }

        .filter-btn {
            height: 42px;

            padding: 0 16px;

            border-radius: 9px;

            border: 1px solid var(--gold-border);

            background: var(--gold-soft);

            color: var(--gold-light);

            cursor: pointer;

            font-size: 13px;
            font-weight: 600;

            transition: 0.2s ease;
        }

        .filter-btn:hover {
            border-color: var(--gold);
            background: rgba(212,175,55,0.14);
        }

        /* =====================================================
           TABLE
        ===================================================== */

        .table-card {
            background: var(--black-card);

            border: 1px solid var(--gold-border);

            border-radius: 12px;

            overflow: hidden;
        }

        .table-header {
            padding: 18px 20px;

            border-bottom: 1px solid var(--gold-border);

            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h2 {
            font-size: 15px;
            color: var(--white);
        }

        .result-count {
            color: var(--muted);
            font-size: 12px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 780px;
        }

        th {
            padding: 13px 20px;

            text-align: left;

            color: #777;

            font-size: 11px;
            font-weight: 600;

            text-transform: uppercase;
            letter-spacing: 0.5px;

            background: #0d0d0d;

            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        td {
            padding: 15px 20px;

            color: #ddd;

            font-size: 13px;

            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        tbody tr {
            transition: 0.2s ease;
        }

        tbody tr:hover {
            background: rgba(212,175,55,0.035);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .invoice-number {
            color: var(--gold-light);
            font-weight: 600;
        }

        .customer-name {
            color: var(--white);
            font-weight: 500;
        }

        .date-text {
            color: #999;
        }

        .amount {
            color: var(--white);
            font-weight: 600;
        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 9px;

            border-radius: 999px;

            font-size: 11px;
            font-weight: 600;
        }

        .status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status.paid {
            color: #86efac;
            background: rgba(34,197,94,0.08);
            border: 1px solid rgba(34,197,94,0.18);
        }

        .status.paid::before {
            background: var(--success);
        }

        .status.pending {
            color: #fde68a;
            background: rgba(234,179,8,0.08);
            border: 1px solid rgba(234,179,8,0.18);
        }

        .status.pending::before {
            background: var(--warning);
        }

        .status.overdue {
            color: #fca5a5;
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.18);
        }

        .status.overdue::before {
            background: var(--danger);
        }

        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn {
            width: 34px;
            height: 34px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            border: 1px solid var(--gold-border);

            background: #0c0c0c;

            color: #999;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .action-btn:hover {
            color: var(--gold-light);
            border-color: var(--gold-border-hover);
            background: var(--gold-soft);
        }

        .action-btn.delete:hover {
            color: #fca5a5;
            border-color: rgba(239,68,68,0.4);
            background: rgba(239,68,68,0.08);
        }

        /* =====================================================
           EMPTY
        ===================================================== */

        .empty-state {
            padding: 65px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 56px;
            height: 56px;

            margin: 0 auto 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background: var(--gold-soft);
            border: 1px solid var(--gold-border);

            color: var(--gold);

            font-size: 22px;
        }

        .empty-state h3 {
            color: var(--white);
            font-size: 16px;
            margin-bottom: 7px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 13px;
        }

        /* =====================================================
           PAGINATION
        ===================================================== */

        .pagination {
            padding: 17px 20px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .pagination-info {
            color: var(--muted);
            font-size: 12px;
        }

        .page-buttons {
            display: flex;
            gap: 5px;
        }

        .page-btn {
            min-width: 34px;
            height: 34px;

            padding: 0 9px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            border: 1px solid rgba(255,255,255,0.08);

            background: #0c0c0c;

            color: #999;

            font-size: 12px;

            transition: 0.2s ease;
        }

        .page-btn:hover {
            color: var(--gold-light);
            border-color: var(--gold-border);
        }

        .page-btn.active {
            background: var(--gold);
            border-color: var(--gold);
            color: #080808;
            font-weight: 700;
        }

        .page-btn.disabled {
            opacity: 0.35;
            pointer-events: none;
        }

        /* =====================================================
           MODAL
        ===================================================== */

        .modal-overlay {
            position: fixed;
            inset: 0;

            background: rgba(0,0,0,0.72);

            backdrop-filter: blur(7px);

            display: none;
            align-items: center;
            justify-content: center;

            padding: 20px;

            z-index: 2000;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            width: 100%;
            max-width: 650px;

            max-height: 90vh;

            overflow-y: auto;

            background: #111;

            border: 1px solid var(--gold-border);

            border-radius: 15px;

            box-shadow: 0 20px 70px rgba(0,0,0,.65);

            animation: modalIn .18s ease;
        }

        .modal.large {
            max-width: 760px;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 18px 20px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            border-bottom: 1px solid rgba(255,255,255,0.07);
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .modal-title-icon {
            width: 35px;
            height: 35px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: var(--gold-soft);
            border: 1px solid var(--gold-border);

            color: var(--gold);

            font-size: 14px;
        }

        .modal-title h3 {
            color: var(--white);
            font-size: 15px;
        }

        .modal-close {
            width: 34px;
            height: 34px;

            border: none;
            border-radius: 8px;

            background: transparent;

            color: #777;

            cursor: pointer;

            font-size: 16px;
        }

        .modal-close:hover {
            color: var(--white);
            background: rgba(255,255,255,0.06);
        }

        .modal-body {
            padding: 20px;
        }

        /* =====================================================
           VIEW DETAILS
        ===================================================== */

        .invoice-head {
            display: flex;
            justify-content: space-between;
            gap: 20px;

            padding-bottom: 18px;
            margin-bottom: 18px;

            border-bottom: 1px solid rgba(255,255,255,0.07);
        }

        .invoice-head h4 {
            color: var(--gold-light);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .invoice-head p {
            color: var(--muted);
            font-size: 12px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-box {
            padding: 13px;

            border-radius: 10px;

            background: #0c0c0c;

            border: 1px solid rgba(255,255,255,0.06);
        }

        .detail-label {
            color: #666;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;

            margin-bottom: 5px;
        }

        .detail-value {
            color: #eee;
            font-size: 13px;
            word-break: break-word;
        }

        .items-title {
            color: var(--white);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .items-table {
            width: 100%;
            min-width: 0;
        }

        .items-table th,
        .items-table td {
            padding: 10px 12px;
        }

        .invoice-total-box {
            margin-top: 18px;

            padding: 15px;

            border-radius: 11px;

            background: var(--gold-soft);

            border: 1px solid var(--gold-border);

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invoice-total-box span:first-child {
            color: #aaa;
            font-size: 13px;
        }

        .invoice-total-box strong {
            color: var(--gold-light);
            font-size: 20px;
        }

        /* =====================================================
           UPDATE FORM
        ===================================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: #999;
            font-size: 11px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;

            min-height: 42px;

            padding: 10px 12px;

            background: #0b0b0b;

            border: 1px solid rgba(255,255,255,0.08);

            border-radius: 9px;

            color: #eee;

            outline: none;

            font-size: 13px;

            transition: .2s ease;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 85px;
        }

        .form-control:focus {
            border-color: var(--gold-border-hover);
            box-shadow: 0 0 0 3px var(--gold-soft);
        }

        .form-control[readonly] {
            color: #777;
            cursor: not-allowed;
        }

        .modal-footer {
            padding: 16px 20px;

            border-top: 1px solid rgba(255,255,255,0.07);

            display: flex;
            justify-content: flex-end;
            gap: 9px;
        }

        .secondary-btn {
            height: 40px;

            padding: 0 15px;

            border-radius: 9px;

            border: 1px solid rgba(255,255,255,0.1);

            background: #0c0c0c;

            color: #aaa;

            cursor: pointer;

            font-size: 12px;
            font-weight: 600;
        }

        .secondary-btn:hover {
            color: var(--white);
            border-color: rgba(255,255,255,0.18);
        }

        .gold-btn {
            height: 40px;

            padding: 0 17px;

            border-radius: 9px;

            border: 1px solid var(--gold);

            background: var(--gold);

            color: #080808;

            cursor: pointer;

            font-size: 12px;
            font-weight: 700;
        }

        .gold-btn:hover {
            background: var(--gold-light);
            border-color: var(--gold-light);
        }

        /* =====================================================
           DELETE MODAL
        ===================================================== */

        .delete-modal {
            max-width: 430px;
        }

        .delete-content {
            text-align: center;
            padding: 10px 5px 5px;
        }

        .delete-icon {
            width: 58px;
            height: 58px;

            margin: 0 auto 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            color: #fca5a5;

            background: rgba(239,68,68,0.08);

            border: 1px solid rgba(239,68,68,0.25);

            font-size: 21px;
        }

        .delete-content h3 {
            color: var(--white);
            font-size: 17px;
            margin-bottom: 8px;
        }

        .delete-content p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .danger-btn {
            height: 40px;

            padding: 0 17px;

            border-radius: 9px;

            border: 1px solid var(--danger);

            background: var(--danger);

            color: white;

            cursor: pointer;

            font-size: 12px;
            font-weight: 700;
        }

        .danger-btn:hover {
            background: #dc2626;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-header {
                align-items: flex-start;
            }
        }

        @media (max-width: 700px) {

            .topbar {
                padding: 0 16px;
            }

            .brand span {
                display: none;
            }

            .nav-link span {
                display: none;
            }

            .nav-link {
                width: 40px;
                padding: 0;
            }

            .page {
                padding: 28px 16px 45px;
            }

            .page-header {
                flex-direction: column;
            }

            .create-btn {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .search-box,
            .filter-select,
            .filter-btn {
                width: 100%;
            }

            .detail-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .pagination {
                flex-direction: column;
                gap: 13px;
                align-items: flex-start;
            }
        }

        @media (max-width: 450px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-title h1 {
                font-size: 21px;
            }
        }

    </style>
</head>

<body>

<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<header class="topbar">

    <a href="Dashboard.php" class="brand">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>Invoice Pro</span>
    </a>

    <nav class="nav-right">

        <a
            href="Dashboard.php"
            class="nav-link"
            title="Dashboard"
        >
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <!-- PRODUCTS ADDED INSTEAD OF INVOICES -->

        <a
            href="Products.php"
            class="nav-link"
            title="Products"
        >
            <i class="fa-solid fa-box"></i>
            <span>Products</span>
        </a>

        <a
            href="Profile.php"
            class="nav-icon"
            title="Profile"
            aria-label="Profile"
        >
            <i class="fa-solid fa-user"></i>
        </a>

        <div
            class="nav-icon"
            title="Change Theme"
            role="button"
            aria-label="Change Theme"
        >
            <i class="fa-solid fa-moon"></i>
        </div>

        <a
            href="Logout.php"
            class="nav-icon"
            title="Logout"
            aria-label="Logout"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>

    </nav>

</header>


<main class="page">

    <!-- =====================================================
         PAGE HEADER
    ===================================================== -->

    <div class="page-header">

        <div class="page-title-area">

            <div class="page-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div class="page-title">
                <h1>Invoice History</h1>
                <p>View, manage and update all your invoices.</p>
            </div>

        </div>

        <a href="Create_Invoice.php" class="create-btn">
            <i class="fa-solid fa-plus"></i>
            Create New Invoice
        </a>

    </div>


    <!-- =====================================================
         MESSAGE
    ===================================================== -->

    <?php if ($message): ?>

        <div class="message <?= $message['type'] === 'success' ? 'success' : 'error' ?>">

            <i class="fa-solid <?= $message['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>

            <?= e($message['text']) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATS
    ===================================================== -->

    <section class="stats-grid">

        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    Total Invoices
                </span>

                <div class="stat-icon">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>

            </div>

            <div class="stat-value">
                <?= number_format($totalInvoices) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    Paid
                </span>

                <div class="stat-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

            </div>

            <div class="stat-value">
                <?= number_format($paidInvoices) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    Pending
                </span>

                <div class="stat-icon">
                    <i class="fa-solid fa-clock"></i>
                </div>

            </div>

            <div class="stat-value">
                <?= number_format($pendingInvoices) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    Total Sales
                </span>

                <div class="stat-icon">
                    <i class="fa-solid fa-indian-rupee-sign"></i>
                </div>

            </div>

            <div class="stat-value">
                ₹<?= number_format($totalRevenue, 2) ?>
            </div>

        </div>

    </section>


    <!-- =====================================================
         FILTERS
    ===================================================== -->

    <form method="GET" class="filter-card">

        <div class="filters">

            <div class="search-box">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    placeholder="Search invoice number, customer or email..."
                    value="<?= e($search) ?>"
                >

            </div>

            <select
                name="date_range"
                class="filter-select"
            >

                <option value="">All Dates</option>

                <option
                    value="7"
                    <?= $dateRange === '7' ? 'selected' : '' ?>
                >
                    Last 7 Days
                </option>

                <option
                    value="30"
                    <?= $dateRange === '30' ? 'selected' : '' ?>
                >
                    Last 30 Days
                </option>

                <option
                    value="month"
                    <?= $dateRange === 'month' ? 'selected' : '' ?>
                >
                    This Month
                </option>

            </select>

            <button type="submit" class="filter-btn">
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>

        </div>

    </form>


    <!-- =====================================================
         TABLE
    ===================================================== -->

    <section class="table-card">

        <div class="table-header">

            <h2>All Invoices</h2>

            <span class="result-count">
                <?= number_format($totalRows) ?> result<?= $totalRows == 1 ? '' : 's' ?>
            </span>

        </div>


        <?php if (count($invoices) > 0): ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Invoice No.</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($invoices as $index => $invoice): ?>

                            <?php
                                $status = ((float)$invoice['total'] > 0)
                                    ? 'paid'
                                    : 'pending';

                                $statusText = $status === 'paid'
                                    ? 'Paid'
                                    : 'Pending';

                                $rowNumber = $offset + $index + 1;
                            ?>

                            <tr>

                                <td>
                                    <?= $rowNumber ?>
                                </td>

                                <td>
                                    <span class="invoice-number">
                                        <?= e($invoice['invoice_number']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="customer-name">
                                        <?= e($invoice['customer_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="date-text">
                                        <?= date('d M Y', strtotime($invoice['issue_date'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="amount">
                                        ₹<?= number_format((float)$invoice['total'], 2) ?>
                                    </span>
                                </td>

                                <td>

                                    <span class="status <?= $status ?>">
                                        <?= $statusText ?>
                                    </span>

                                </td>

                                <td>

                                    <div class="actions">

                                        <!-- SEE -->

                                        <button
                                            type="button"
                                            class="action-btn"
                                            title="See Invoice"
                                            onclick="openViewModal(<?= (int)$invoice['id'] ?>)"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>


                                        <!-- UPDATE -->

                                        <button
                                            type="button"
                                            class="action-btn"
                                            title="Update Invoice"
                                            onclick="openUpdateModal(
                                                <?= (int)$invoice['id'] ?>,
                                                <?= htmlspecialchars(json_encode($invoice['invoice_number']), ENT_QUOTES, 'UTF-8') ?>,
                                                <?= htmlspecialchars(json_encode($invoice['customer_name']), ENT_QUOTES, 'UTF-8') ?>,
                                                <?= htmlspecialchars(json_encode($invoice['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                                                <?= htmlspecialchars(json_encode($invoice['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                                                <?= htmlspecialchars(json_encode($invoice['customer_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                                                <?= htmlspecialchars(json_encode($invoice['issue_date']), ENT_QUOTES, 'UTF-8') ?>,
                                                <?= (float)$invoice['subtotal'] ?>,
                                                <?= (float)$invoice['tax'] ?>
                                            )"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </button>


                                        <!-- DELETE -->

                                        <button
                                            type="button"
                                            class="action-btn delete"
                                            title="Delete Invoice"
                                            onclick="openDeleteModal(
                                                <?= (int)$invoice['id'] ?>,
                                                <?= htmlspecialchars(json_encode($invoice['invoice_number']), ENT_QUOTES, 'UTF-8') ?>
                                            )"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 PAGINATION
            ================================================= -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <div class="pagination-info">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </div>

                    <div class="page-buttons">

                        <a
                            href="<?= $page > 1 ? e(pageUrl($page - 1)) : '#' ?>"
                            class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                        >
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>


                        <?php

                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        for ($p = $startPage; $p <= $endPage; $p++):

                        ?>

                            <a
                                href="<?= e(pageUrl($p)) ?>"
                                class="page-btn <?= $p == $page ? 'active' : '' ?>"
                            >
                                <?= $p ?>
                            </a>

                        <?php endfor; ?>


                        <a
                            href="<?= $page < $totalPages ? e(pageUrl($page + 1)) : '#' ?>"
                            class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>

                    </div>

                </div>

            <?php endif; ?>


        <?php else: ?>

            <div class="empty-state">

                <div class="empty-icon">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>

                <h3>No invoices found</h3>

                <p>
                    <?= $search !== '' || $dateRange !== ''
                        ? 'Try changing your search or filter.'
                        : 'Create your first invoice to see it here.'
                    ?>
                </p>

            </div>

        <?php endif; ?>

    </section>

</main>


<!-- =========================================================
     VIEW MODAL
========================================================= -->

<?php if ($viewInvoice): ?>

<div class="modal-overlay show" id="viewModal">

    <div class="modal large">

        <div class="modal-header">

            <div class="modal-title">

                <div class="modal-title-icon">
                    <i class="fa-solid fa-eye"></i>
                </div>

                <h3>Invoice Details</h3>

            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeViewModal()"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <div class="modal-body">

            <div class="invoice-head">

                <div>
                    <h4>
                        <?= e($viewInvoice['invoice_number']) ?>
                    </h4>

                    <p>
                        Issued on
                        <?= date('d M Y', strtotime($viewInvoice['issue_date'])) ?>
                    </p>
                </div>

                <span class="status <?= (float)$viewInvoice['total'] > 0 ? 'paid' : 'pending' ?>">
                    <?= (float)$viewInvoice['total'] > 0 ? 'Paid' : 'Pending' ?>
                </span>

            </div>


            <div class="detail-grid">

                <div class="detail-box">

                    <div class="detail-label">
                        Customer Name
                    </div>

                    <div class="detail-value">
                        <?= e($viewInvoice['customer_name']) ?>
                    </div>

                </div>


                <div class="detail-box">

                    <div class="detail-label">
                        Email
                    </div>

                    <div class="detail-value">
                        <?= e($viewInvoice['customer_email'] ?: '-') ?>
                    </div>

                </div>


                <div class="detail-box">

                    <div class="detail-label">
                        Phone
                    </div>

                    <div class="detail-value">
                        <?= e($viewInvoice['customer_phone'] ?: '-') ?>
                    </div>

                </div>


                <div class="detail-box">

                    <div class="detail-label">
                        Address
                    </div>

                    <div class="detail-value">
                        <?= e($viewInvoice['customer_address'] ?: '-') ?>
                    </div>

                </div>

            </div>


            <?php if (!empty($viewInvoice['items'])): ?>

                <div class="items-title">
                    Invoice Items
                </div>

                <div class="table-wrap">

                    <table class="items-table">

                        <thead>

                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th>Amount</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($viewInvoice['items'] as $item): ?>

                                <tr>

                                    <td>
                                        <?= e($item['product_name']) ?>
                                    </td>

                                    <td>
                                        <?= e($item['quantity']) ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format((float)$item['rate'], 2) ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format((float)$item['amount'], 2) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>


            <div class="invoice-total-box">

                <span>
                    Total Amount
                </span>

                <strong>
                    ₹<?= number_format((float)$viewInvoice['total'], 2) ?>
                </strong>

            </div>

        </div>

    </div>

</div>

<?php endif; ?>


<!-- =========================================================
     UPDATE MODAL
========================================================= -->

<div class="modal-overlay" id="updateModal">

    <div class="modal">

        <div class="modal-header">

            <div class="modal-title">

                <div class="modal-title-icon">
                    <i class="fa-solid fa-pen"></i>
                </div>

                <h3>Update Invoice</h3>

            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeUpdateModal()"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="update"
            >

            <input
                type="hidden"
                name="invoice_id"
                id="update_invoice_id"
            >


            <div class="modal-body">

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Invoice Number
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="update_invoice_number"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Issue Date *
                        </label>

                        <input
                            type="date"
                            name="issue_date"
                            class="form-control"
                            id="update_issue_date"
                            required
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Customer Name *
                        </label>

                        <input
                            type="text"
                            name="customer_name"
                            class="form-control"
                            id="update_customer_name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Customer Email
                        </label>

                        <input
                            type="email"
                            name="customer_email"
                            class="form-control"
                            id="update_customer_email"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Customer Phone
                        </label>

                        <input
                            type="text"
                            name="customer_phone"
                            class="form-control"
                            id="update_customer_phone"
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Customer Address
                        </label>

                        <textarea
                            name="customer_address"
                            class="form-control"
                            id="update_customer_address"
                        ></textarea>

                    </div>


                    <div class="form-group">

                        <label>
                            Subtotal
                        </label>

                        <input
                            type="number"
                            name="subtotal"
                            class="form-control"
                            id="update_subtotal"
                            step="0.01"
                            min="0"
                            oninput="calculateUpdateTotal()"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tax
                        </label>

                        <input
                            type="number"
                            name="tax"
                            class="form-control"
                            id="update_tax"
                            step="0.01"
                            min="0"
                            oninput="calculateUpdateTotal()"
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Total
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="update_total"
                            readonly
                        >

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-btn"
                    onclick="closeUpdateModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="gold-btn"
                >
                    <i class="fa-solid fa-check"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     DELETE MODAL
========================================================= -->

<div class="modal-overlay" id="deleteModal">

    <div class="modal delete-modal">

        <div class="modal-header">

            <div class="modal-title">

                <div class="modal-title-icon">
                    <i class="fa-solid fa-trash"></i>
                </div>

                <h3>Delete Invoice</h3>

            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeDeleteModal()"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="delete"
            >

            <input
                type="hidden"
                name="invoice_id"
                id="delete_invoice_id"
            >


            <div class="modal-body">

                <div class="delete-content">

                    <div class="delete-icon">
                        <i class="fa-solid fa-trash"></i>
                    </div>

                    <h3>
                        Delete this invoice?
                    </h3>

                    <p>
                        You are about to delete
                        <strong id="delete_invoice_number"></strong>.
                        This action cannot be undone and its invoice items
                        will also be removed.
                    </p>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-btn"
                    onclick="closeDeleteModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="danger-btn"
                >
                    <i class="fa-solid fa-trash"></i>
                    Delete Invoice
                </button>

            </div>

        </form>

    </div>

</div>


<script>

    /* =========================================================
       VIEW
    ========================================================= */

    function openViewModal(invoiceId) {

        const url = new URL(window.location.href);

        url.searchParams.set("view", invoiceId);

        window.location.href = url.toString();
    }

    function closeViewModal() {

        const url = new URL(window.location.href);

        url.searchParams.delete("view");

        window.location.href = url.toString();
    }


    /* =========================================================
       UPDATE
    ========================================================= */

    function openUpdateModal(
        id,
        invoiceNumber,
        customerName,
        customerEmail,
        customerPhone,
        customerAddress,
        issueDate,
        subtotal,
        tax
    ) {

        document.getElementById("update_invoice_id").value = id;

        document.getElementById("update_invoice_number").value = invoiceNumber;

        document.getElementById("update_customer_name").value = customerName;

        document.getElementById("update_customer_email").value = customerEmail;

        document.getElementById("update_customer_phone").value = customerPhone;

        document.getElementById("update_customer_address").value = customerAddress;

        document.getElementById("update_issue_date").value = issueDate;

        document.getElementById("update_subtotal").value = subtotal;

        document.getElementById("update_tax").value = tax;

        calculateUpdateTotal();

        document.getElementById("updateModal").classList.add("show");
    }


    function closeUpdateModal() {

        document
            .getElementById("updateModal")
            .classList.remove("show");
    }


    function calculateUpdateTotal() {

        const subtotal =
            parseFloat(document.getElementById("update_subtotal").value) || 0;

        const tax =
            parseFloat(document.getElementById("update_tax").value) || 0;

        const total = subtotal + tax;

        document.getElementById("update_total").value =
            "₹" + total.toFixed(2);
    }


    /* =========================================================
       DELETE
    ========================================================= */

    function openDeleteModal(id, invoiceNumber) {

        document.getElementById("delete_invoice_id").value = id;

        document.getElementById("delete_invoice_number").textContent =
            invoiceNumber;

        document
            .getElementById("deleteModal")
            .classList.add("show");
    }


    function closeDeleteModal() {

        document
            .getElementById("deleteModal")
            .classList.remove("show");
    }


    /* =========================================================
       CLICK OUTSIDE MODAL
    ========================================================= */

    document.addEventListener("click", function(event) {

        const updateModal =
            document.getElementById("updateModal");

        const deleteModal =
            document.getElementById("deleteModal");

        if (event.target === updateModal) {
            closeUpdateModal();
        }

        if (event.target === deleteModal) {
            closeDeleteModal();
        }

    });


    /* =========================================================
       ESC KEY
    ========================================================= */

    document.addEventListener("keydown", function(event) {

        if (event.key === "Escape") {

            closeUpdateModal();
            closeDeleteModal();

            const viewModal =
                document.getElementById("viewModal");

            if (viewModal) {
                closeViewModal();
            }
        }

    });

</script>

</body>
</html>
