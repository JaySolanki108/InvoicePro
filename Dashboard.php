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
   HELPER
========================================================= */

function clean($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['invoice_csrf'])) {
    $_SESSION['invoice_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['invoice_csrf'];

/* =========================================================
   DELETE INVOICE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_invoice'
) {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $_SESSION['dashboard_message'] = "Invalid request.";
        $_SESSION['dashboard_message_type'] = "error";
        header("Location: Dashboard.php");
        exit;
    }

    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);

    if ($invoiceId <= 0) {
        $_SESSION['dashboard_message'] = "Invalid invoice.";
        $_SESSION['dashboard_message_type'] = "error";
        header("Location: Dashboard.php");
        exit;
    }

    try {
        $db->beginTransaction();

        $checkStmt = $db->prepare("
            SELECT id
            FROM invoices
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $checkStmt->execute([$invoiceId, $userId]);

        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $db->rollBack();

            $_SESSION['dashboard_message'] = "Invoice not found.";
            $_SESSION['dashboard_message_type'] = "error";
            header("Location: Dashboard.php");
            exit;
        }

        $itemDeleteStmt = $db->prepare("
            DELETE FROM invoice_items
            WHERE invoice_id = ?
        ");
        $itemDeleteStmt->execute([$invoiceId]);

        $invoiceDeleteStmt = $db->prepare("
            DELETE FROM invoices
            WHERE id = ? AND user_id = ?
        ");
        $invoiceDeleteStmt->execute([$invoiceId, $userId]);

        if ($invoiceDeleteStmt->rowCount() !== 1) {
            throw new RuntimeException("Invoice could not be deleted.");
        }

        $db->commit();

        $_SESSION['dashboard_message'] = "Invoice deleted successfully.";
        $_SESSION['dashboard_message_type'] = "success";

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log("Invoice deletion failed: " . $e->getMessage());

        $_SESSION['dashboard_message'] = "Unable to delete invoice. Please try again.";
        $_SESSION['dashboard_message_type'] = "error";
    }

    header("Location: Dashboard.php");
    exit;
}

/* =========================================================
   UPDATE INVOICE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_invoice'
) {
    header('Content-Type: application/json; charset=utf-8');

    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid request."
        ]);
        exit;
    }

    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $issueDate = trim($_POST['issue_date'] ?? '');
    $itemsJson = $_POST['items'] ?? '';

    if ($invoiceId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid invoice."]);
        exit;
    }

    if ($customerName === '') {
        echo json_encode(["success" => false, "message" => "Customer name is required."]);
        exit;
    }

    if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Please enter a valid customer email."]);
        exit;
    }

    if ($customerPhone === '') {
        echo json_encode(["success" => false, "message" => "Customer phone number is required."]);
        exit;
    }

    if ($customerAddress === '') {
        echo json_encode(["success" => false, "message" => "Customer address is required."]);
        exit;
    }

    if ($issueDate === '') {
        $issueDate = date("Y-m-d");
    }

    $dateObject = DateTime::createFromFormat('!Y-m-d', $issueDate);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $issueDate) {
        echo json_encode(["success" => false, "message" => "Please enter a valid invoice date."]);
        exit;
    }

    $items = json_decode($itemsJson, true);

    if (!is_array($items) || count($items) === 0) {
        echo json_encode(["success" => false, "message" => "Please add at least one product."]);
        exit;
    }

    try {
        /* Verify invoice ownership */
        $invoiceCheckStmt = $db->prepare("
            SELECT id
            FROM invoices
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $invoiceCheckStmt->execute([$invoiceId, $userId]);

        if (!$invoiceCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(["success" => false, "message" => "Invoice not found."]);
            exit;
        }

        /* Validate products belong to this user */
        $validItems = [];
        $subtotal = 0.00;

        $productStmt = $db->prepare("
            SELECT id, product_name
            FROM products
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $rate = filter_var($item['rate'] ?? null, FILTER_VALIDATE_FLOAT);

            if (
                $productId <= 0 ||
                $quantity <= 0 ||
                $rate === false ||
                !is_finite((float) $rate) ||
                $rate <= 0
            ) {
                continue;
            }

            $productStmt->execute([$productId, $userId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                continue;
            }

            $rate = round((float) $rate, 2);
            $amount = round($quantity * $rate, 2);

            $subtotal += $amount;

            $validItems[] = [
                "product_id" => $productId,
                "product_name" => $product['product_name'],
                "quantity" => $quantity,
                "rate" => $rate,
                "amount" => $amount
            ];
        }

        if (count($validItems) === 0) {
            echo json_encode([
                "success" => false,
                "message" => "Please select a valid product and enter quantity and rate."
            ]);
            exit;
        }

        $subtotal = round($subtotal, 2);
        $tax = 0.00;
        $total = round($subtotal + $tax, 2);

        /* Update invoice and its items atomically */
        $db->beginTransaction();

        $invoiceUpdateStmt = $db->prepare("
            UPDATE invoices
            SET
                customer_name = ?,
                customer_email = ?,
                customer_phone = ?,
                customer_address = ?,
                issue_date = ?,
                subtotal = ?,
                tax = ?,
                total = ?
            WHERE id = ? AND user_id = ?
        ");

        $invoiceUpdateStmt->execute([
            $customerName,
            $customerEmail,
            $customerPhone,
            $customerAddress,
            $issueDate,
            $subtotal,
            $tax,
            $total,
            $invoiceId,
            $userId
        ]);

        $deleteItemsStmt = $db->prepare("
            DELETE FROM invoice_items
            WHERE invoice_id = ?
        ");
        $deleteItemsStmt->execute([$invoiceId]);

        $insertItemStmt = $db->prepare("
            INSERT INTO invoice_items (
                invoice_id,
                product_id,
                product_name,
                quantity,
                rate,
                amount
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($validItems as $item) {
            $insertItemStmt->execute([
                $invoiceId,
                $item['product_id'],
                $item['product_name'],
                $item['quantity'],
                $item['rate'],
                $item['amount']
            ]);
        }

        $db->commit();

        echo json_encode([
            "success" => true,
            "message" => "Invoice updated successfully."
        ]);
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log("Invoice update failed: " . $e->getMessage());

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Unable to update invoice. Please try again."
        ]);
        exit;
    }
}

/* =========================================================
   DASHBOARD STATISTICS
========================================================= */

$totalInvoices = 0;
$paidInvoices = 0;
$pendingInvoices = 0;
$totalSales = 0.00;

try {
    $stmtInvoiceStats = $db->prepare("
        SELECT
            COUNT(*) AS total_invoices,
            COALESCE(SUM(CASE WHEN total > 0 THEN 1 ELSE 0 END), 0) AS paid_invoices,
            COALESCE(SUM(CASE WHEN total = 0 THEN 1 ELSE 0 END), 0) AS pending_invoices,
            COALESCE(SUM(total), 0) AS total_sales
        FROM invoices
        WHERE user_id = ?
    ");

    $stmtInvoiceStats->execute([$userId]);
    $invoiceStats = $stmtInvoiceStats->fetch(PDO::FETCH_ASSOC);

    if ($invoiceStats) {
        $totalInvoices = (int) ($invoiceStats['total_invoices'] ?? 0);
        $paidInvoices = (int) ($invoiceStats['paid_invoices'] ?? 0);
        $pendingInvoices = (int) ($invoiceStats['pending_invoices'] ?? 0);
        $totalSales = (float) ($invoiceStats['total_sales'] ?? 0);
    }

} catch (PDOException $e) {
    error_log("Dashboard statistics query failed: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load dashboard statistics.");
}

$formattedTotalSales = number_format($totalSales, 2);

/* =========================================================
   LOAD RECENT INVOICES
========================================================= */

$recentInvoices = [];

try {
    $stmtRecentInvoices = $db->prepare("
        SELECT
            id,
            invoice_number,
            customer_name,
            customer_email,
            customer_phone,
            customer_address,
            issue_date,
            subtotal,
            tax,
            total
        FROM invoices
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 3
    ");

    $stmtRecentInvoices->execute([$userId]);
    $recentInvoices = $stmtRecentInvoices->fetchAll(PDO::FETCH_ASSOC);

    $itemStmt = $db->prepare("
        SELECT
            id,
            product_id,
            product_name,
            quantity,
            rate,
            amount
        FROM invoice_items
        WHERE invoice_id = ?
        ORDER BY id ASC
    ");

    foreach ($recentInvoices as &$invoice) {
        $itemStmt->execute([(int) $invoice['id']]);
        $invoice['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($invoice);

} catch (PDOException $e) {
    error_log("Recent invoices query failed: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load recent invoices.");
}

/* =========================================================
   LOAD PRODUCTS FOR INVOICE UPDATE
========================================================= */

$products = [];

try {
    $productListStmt = $db->prepare("
        SELECT
            id,
            product_name,
            description,
            price,
            stock
        FROM products
        WHERE user_id = ?
        ORDER BY product_name ASC
    ");

    $productListStmt->execute([$userId]);
    $products = $productListStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Product list query failed: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load products.");
}

/* =========================================================
   FLASH MESSAGE
========================================================= */

$dashboardMessage = $_SESSION['dashboard_message'] ?? '';
$dashboardMessageType = $_SESSION['dashboard_message_type'] ?? '';

unset(
    $_SESSION['dashboard_message'],
    $_SESSION['dashboard_message_type']
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

    <title>
        Dashboard | Invoice Pro
    </title>


    <!-- GOOGLE FONT -->

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <style>

        :root {

            --black: #080808;

            --black-soft: #0d0d0d;

            --black-card: #111111;

            --black-hover: #151515;

            --gold: #d4af37;

            --gold-light: #f2d675;

            --gold-soft:
                rgba(212,175,55,0.08);

            --gold-border:
                rgba(212,175,55,0.24);

            --gold-border-hover:
                rgba(212,175,55,0.55);

            --white: #ffffff;

            --text: #eeeeee;

            --muted: #888888;

            --danger: #ef4444;

            --success: #22c55e;

        }


        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family:
                'Inter',
                sans-serif;

        }


        html,
        body {

            width: 100%;

            min-height: 100%;

        }


        body {

            background:
                var(--black);

            color:
                var(--white);

        }


        a {

            text-decoration: none;

            color: inherit;

        }


        button,
        input,
        textarea,
        select {

            font-family:
                'Inter',
                sans-serif;

        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {

            width: 100%;

            height: 76px;

            background:
                var(--black);

            border-bottom:
                1px solid var(--gold-border);

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                0 45px;

            position: sticky;

            top: 0;

            z-index: 1000;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            font-size: 21px;

            font-weight: 800;

            letter-spacing:
                -0.5px;

            color:
                var(--gold-light);

        }


        .brand-icon {

            width: 40px;

            height: 40px;

            border:
                1px solid var(--gold);

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            color:
                var(--gold);

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.06
                );

        }


        .brand-text {

            line-height: 1;

        }


        .brand-text span {

            color:
                var(--gold);

        }


        .nav-right {

            display: flex;

            align-items: center;

            gap: 8px;

        }


        .nav-link {

            height: 39px;

            padding: 0 12px;

            border-radius: 9px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            color: var(--muted);

            border: 1px solid transparent;

            background: transparent;

            font-size: 11px;

            font-weight: 600;

            text-decoration: none;

            transition: all 0.25s ease;

            cursor: pointer;

            white-space: nowrap;

        }


        .nav-link i {

            color: var(--gold);

            font-size: 12px;

        }


        .nav-link:hover {

            color: var(--white);

            background: var(--gold-soft);

            border-color: var(--gold-border);

        }


        .nav-icon {

            width: 39px;

            height: 39px;

            border-radius: 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            color:
                var(--gold);

            border:
                1px solid var(--gold-border);

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.06
                );

            transition:
                all 0.25s ease;

            cursor: pointer;

        }


        .nav-icon:hover {

            color:
                var(--black);

            background:
                var(--gold);

            border-color:
                var(--gold);

            transform:
                translateY(-1px);

            box-shadow:
                0 5px 18px
                rgba(
                    212,
                    175,
                    55,
                    0.20
                );

        }


        /* =====================================================
           DASHBOARD
        ===================================================== */

        .dashboard {

            width: 100%;

            min-height:
                calc(100vh - 76px);

            padding:
                48px 45px 60px;

            background:
                var(--black);

        }


        .dashboard-container {

            width: 100%;

            max-width: 1180px;

            margin: 0 auto;

        }


        /* =====================================================
           FLASH MESSAGE
        ===================================================== */

        .flash-message {

            display: flex;

            align-items: center;

            gap: 10px;

            padding:
                13px 16px;

            border-radius: 9px;

            margin-bottom: 22px;

            font-size: 13px;

            font-weight: 600;

        }


        .flash-success {

            color:
                #86efac;

            background:
                rgba(
                    34,
                    197,
                    94,
                    0.08
                );

            border:
                1px solid
                rgba(
                    34,
                    197,
                    94,
                    0.25
                );

        }


        .flash-error {

            color:
                #fca5a5;

            background:
                rgba(
                    239,
                    68,
                    68,
                    0.08
                );

            border:
                1px solid
                rgba(
                    239,
                    68,
                    68,
                    0.25
                );

        }


        /* =====================================================
           WELCOME
        ===================================================== */

        .welcome-section {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 30px;

            margin-bottom:
                34px;

        }


        .welcome-content h1 {

            font-size: 31px;

            line-height: 1.2;

            font-weight: 800;

            letter-spacing:
                -1px;

            color:
                var(--white);

            margin-bottom: 9px;

        }


        .welcome-content h1 span {

            color:
                var(--gold);

        }


        .welcome-content p {

            font-size: 14px;

            color:
                var(--muted);

            line-height: 1.6;

        }


        .create-invoice-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            padding:
                13px 20px;

            background:
                var(--gold);

            color:
                var(--black);

            border:
                1px solid var(--gold);

            border-radius: 9px;

            font-size: 13px;

            font-weight: 700;

            white-space: nowrap;

            transition:
                all 0.25s ease;

        }


        .create-invoice-btn:hover {

            background:
                var(--gold-light);

            border-color:
                var(--gold-light);

            transform:
                translateY(-2px);

            box-shadow:
                0 10px 28px
                rgba(
                    212,
                    175,
                    55,
                    0.18
                );

        }


        /* =====================================================
           STATS
        ===================================================== */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 14px;

            margin-bottom:
                34px;

        }


        .stat-card {

            background:
                var(--black-card);

            border:
                1px solid var(--gold-border);

            border-radius: 12px;

            padding: 19px;

            min-height: 112px;

            transition:
                all 0.25s ease;

        }


        .stat-card:hover {

            border-color:
                var(--gold-border-hover);

            background:
                var(--black-hover);

            transform:
                translateY(-2px);

        }


        .stat-top {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin-bottom:
                16px;

        }


        .stat-label {

            font-size: 11px;

            color:
                var(--muted);

            text-transform:
                uppercase;

            letter-spacing:
                0.7px;

            font-weight: 600;

        }


        .stat-icon {

            width: 34px;

            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 8px;

            color:
                var(--gold);

            background:
                var(--gold-soft);

            border:
                1px solid var(--gold-border);

            font-size: 13px;

        }


        .stat-value {

            font-size: 22px;

            font-weight: 800;

            color:
                var(--white);

            letter-spacing:
                -0.5px;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

        }


        /* =====================================================
           SECTION HEADER
        ===================================================== */

        .section-header {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin-bottom:
                14px;

        }


        .section-title {

            font-size: 16px;

            font-weight: 700;

            color:
                var(--white);

        }


        .section-line {

            height: 1px;

            flex: 1;

            margin-left: 16px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.07
                );

        }


        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .actions-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 14px;

        }


        .action-card {

            display: flex;

            align-items: center;

            gap: 15px;

            min-height: 92px;

            padding:
                18px 20px;

            background:
                var(--black-card);

            border:
                1px solid var(--gold-border);

            border-radius: 12px;

            transition:
                all 0.25s ease;

        }


        .action-card:hover {

            border-color:
                var(--gold-border-hover);

            background:
                var(--black-hover);

            transform:
                translateY(-2px);

        }


        .action-icon {

            flex-shrink: 0;

            width: 43px;

            height: 43px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            color:
                var(--gold);

            background:
                var(--gold-soft);

            border:
                1px solid var(--gold-border);

            font-size: 15px;

        }


        .action-content {

            flex: 1;

        }


        .action-content h3 {

            font-size: 14px;

            font-weight: 700;

            color:
                var(--white);

            margin-bottom: 5px;

        }


        .action-content p {

            font-size: 11px;

            color:
                var(--muted);

            line-height: 1.5;

        }


        .action-arrow {

            color:
                var(--gold);

            font-size: 12px;

        }


        /* =====================================================
           RECENT INVOICES
        ===================================================== */

        .recent-section {

            margin-top: 34px;

        }


        .recent-card {

            background:
                var(--black-card);

            border:
                1px solid var(--gold-border);

            border-radius: 12px;

            overflow: hidden;

        }


        .recent-table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        .recent-table {

            width: 100%;

            min-width: 820px;

            border-collapse:
                collapse;

        }


        .recent-table th {

            text-align: left;

            padding:
                14px 18px;

            font-size: 10px;

            text-transform:
                uppercase;

            letter-spacing:
                0.6px;

            color:
                var(--muted);

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.035
                );

            border-bottom:
                1px solid var(--gold-border);

            font-weight: 700;

        }


        .recent-table td {

            padding:
                15px 18px;

            font-size: 12px;

            color:
                var(--text);

            border-bottom:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.055
                );

            vertical-align:
                middle;

        }


        .recent-table tbody tr:last-child td {

            border-bottom: none;

        }


        .recent-table tbody tr {

            transition:
                background 0.2s ease;

        }


        .recent-table tbody tr:hover {

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.035
                );

        }


        .invoice-number {

            color:
                var(--gold-light);

            font-weight: 700;

        }


        .customer-name {

            color:
                var(--white);

            font-weight: 600;

        }


        .invoice-date {

            color:
                var(--muted);

        }


        .invoice-amount {

            font-weight: 700;

            color:
                var(--white);

        }


        .status-badge {

            display: inline-flex;

            align-items: center;

            padding:
                5px 9px;

            border-radius: 999px;

            font-size: 10px;

            font-weight: 700;

        }


        .status-paid {

            color:
                #86efac;

            background:
                rgba(
                    34,
                    197,
                    94,
                    0.10
                );

            border:
                1px solid
                rgba(
                    34,
                    197,
                    94,
                    0.22
                );

        }


        .status-pending {

            color:
                #fde68a;

            background:
                rgba(
                    234,
                    179,
                    8,
                    0.10
                );

            border:
                1px solid
                rgba(
                    234,
                    179,
                    8,
                    0.22
                );

        }


        /* =====================================================
           INVOICE ACTIONS
        ===================================================== */

        .invoice-actions {

            display: flex;

            align-items: center;

            gap: 7px;

        }


        .invoice-action {

            width: 31px;

            height: 31px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 7px;

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.055
                );

            border:
                1px solid var(--gold-border);

            color:
                var(--gold);

            cursor: pointer;

            transition:
                all 0.2s ease;

        }


        .invoice-action:hover {

            background:
                var(--gold);

            color:
                var(--black);

            border-color:
                var(--gold);

            transform:
                translateY(-1px);

        }


        .invoice-action.delete-action {

            color:
                #f87171;

            border-color:
                rgba(
                    248,
                    113,
                    113,
                    0.22
                );

            background:
                rgba(
                    248,
                    113,
                    113,
                    0.05
                );

        }


        .invoice-action.delete-action:hover {

            background:
                var(--danger);

            border-color:
                var(--danger);

            color:
                var(--white);

        }


        .view-all-row {

            display: flex;

            align-items: center;

            justify-content:
                flex-end;

            padding:
                13px 18px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.055
                );

        }


        .view-all-link {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            color:
                var(--gold);

            font-size: 11px;

            font-weight: 700;

        }


        .view-all-link:hover {

            color:
                var(--gold-light);

        }


        .empty-recent {

            padding:
                45px 20px;

            text-align: center;

        }


        .empty-recent-icon {

            width: 50px;

            height: 50px;

            margin:
                0 auto 13px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            color:
                var(--gold);

            background:
                var(--gold-soft);

            border:
                1px solid var(--gold-border);

        }


        .empty-recent h3 {

            font-size: 14px;

            margin-bottom: 6px;

        }


        .empty-recent p {

            font-size: 11px;

            color:
                var(--muted);

        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal-overlay {

            position: fixed;

            inset: 0;

            background:
                rgba(
                    0,
                    0,
                    0,
                    0.78
                );

            backdrop-filter:
                blur(5px);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 25px;

            z-index: 3000;

        }


        .modal-overlay.show {

            display: flex;

        }


        .modal {

            width: 100%;

            max-width: 850px;

            max-height:
                92vh;

            overflow-y: auto;

            background:
                #101010;

            border:
                1px solid var(--gold-border);

            border-radius: 14px;

            box-shadow:
                0 30px 90px
                rgba(
                    0,
                    0,
                    0,
                    0.55
                );

        }


        .modal-header {

            position: sticky;

            top: 0;

            z-index: 5;

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                17px 20px;

            background:
                #101010;

            border-bottom:
                1px solid var(--gold-border);

        }


        .modal-title {

            display: flex;

            align-items: center;

            gap: 11px;

            font-size: 15px;

            font-weight: 700;

        }


        .modal-title i {

            color:
                var(--gold);

        }


        .modal-close {

            width: 32px;

            height: 32px;

            border-radius: 7px;

            border:
                1px solid var(--gold-border);

            background:
                var(--gold-soft);

            color:
                var(--gold);

            cursor: pointer;

        }


        .modal-close:hover {

            background:
                var(--gold);

            color:
                var(--black);

        }


        .modal-body {

            padding:
                22px;

        }


        /* =====================================================
           SHOW INVOICE
        ===================================================== */

        .invoice-paper {

            background:
                #ffffff;

            color:
                #171717;

            border-radius: 8px;

            padding:
                30px;

        }


        .invoice-paper-top {

            display: flex;

            justify-content:
                space-between;

            gap: 25px;

            padding-bottom:
                20px;

            border-bottom:
                2px solid
                #d4af37;

        }


        .invoice-company h2 {

            font-size: 22px;

            margin-bottom: 6px;

            color:
                #111111;

        }


        .invoice-company p {

            font-size: 11px;

            line-height: 1.7;

            color:
                #666666;

        }


        .invoice-paper-heading {

            text-align:
                right;

        }


        .invoice-paper-heading h1 {

            font-size: 25px;

            letter-spacing:
                1px;

            margin-bottom: 7px;

        }


        .invoice-paper-heading p {

            font-size: 11px;

            color:
                #666666;

            margin-bottom: 4px;

        }


        .invoice-customer-area {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 30px;

            margin:
                22px 0;

        }


        .invoice-box-title {

            font-size: 10px;

            text-transform:
                uppercase;

            letter-spacing:
                0.7px;

            color:
                #888888;

            margin-bottom:
                9px;

            font-weight: 700;

        }


        .invoice-box p {

            font-size: 12px;

            color:
                #333333;

            line-height: 1.65;

        }


        .invoice-products {

            width: 100%;

            border-collapse:
                collapse;

            margin-top:
                10px;

        }


        .invoice-products th {

            padding:
                10px;

            background:
                #111111;

            color:
                #ffffff;

            font-size: 10px;

            text-align: left;

        }


        .invoice-products td {

            padding:
                10px;

            border-bottom:
                1px solid #eeeeee;

            font-size: 11px;

            color:
                #333333;

        }


        .invoice-total-area {

            width: 290px;

            max-width: 100%;

            margin-left: auto;

            margin-top:
                18px;

        }


        .invoice-total-row {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                7px 0;

            font-size: 12px;

            color:
                #666666;

        }


        .invoice-total-final {

            border-top:
                1px solid #dddddd;

            margin-top: 5px;

            padding-top:
                12px;

            color:
                #111111;

            font-size: 15px;

            font-weight: 800;

        }


        /* =====================================================
           UPDATE FORM
        ===================================================== */

        .edit-section {

            margin-bottom:
                22px;

        }


        .edit-section-title {

            font-size: 13px;

            font-weight: 700;

            color:
                var(--gold-light);

            margin-bottom:
                12px;

        }


        .edit-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 13px;

        }


        .edit-group {

            display: flex;

            flex-direction:
                column;

            gap: 6px;

        }


        .edit-group.full {

            grid-column:
                1 / -1;

        }


        .edit-group label {

            font-size: 10px;

            font-weight: 600;

            color:
                var(--muted);

            text-transform:
                uppercase;

            letter-spacing:
                0.5px;

        }


        .edit-control {

            width: 100%;

            height: 40px;

            padding:
                0 12px;

            border:
                1px solid
                var(--gold-border);

            border-radius: 8px;

            outline: none;

            background:
                #0b0b0b;

            color:
                var(--white);

            font-size: 12px;

        }


        textarea.edit-control {

            height: 70px;

            padding-top:
                10px;

            resize: vertical;

        }


        .edit-control:focus {

            border-color:
                var(--gold);

        }


        .edit-items {

            border:
                1px solid var(--gold-border);

            border-radius: 9px;

            overflow-x: auto;

        }


        .edit-items table {

            width: 100%;

            min-width: 650px;

            border-collapse:
                collapse;

        }


        .edit-items th {

            padding:
                10px;

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.06
                );

            color:
                var(--muted);

            font-size: 9px;

            text-transform:
                uppercase;

            text-align: left;

        }


        .edit-items td {

            padding:
                8px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.06
                );

        }


        .edit-item-input {

            width: 100%;

            height: 35px;

            border:
                1px solid
                var(--gold-border);

            border-radius: 6px;

            background:
                #0b0b0b;

            color:
                var(--white);

            padding:
                0 8px;

            outline: none;

            font-size: 11px;

        }


        .edit-item-input:focus {

            border-color:
                var(--gold);

        }


        .remove-edit-item {

            width: 31px;

            height: 31px;

            border-radius: 6px;

            border:
                1px solid
                rgba(
                    239,
                    68,
                    68,
                    0.25
                );

            background:
                rgba(
                    239,
                    68,
                    68,
                    0.07
                );

            color:
                #f87171;

            cursor: pointer;

        }


        .remove-edit-item:hover {

            background:
                var(--danger);

            color:
                #ffffff;

        }


        .add-edit-item {

            margin-top:
                10px;

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                8px 12px;

            border-radius: 7px;

            border:
                1px solid var(--gold-border);

            background:
                transparent;

            color:
                var(--gold);

            font-size: 11px;

            font-weight: 700;

            cursor: pointer;

        }


        .add-edit-item:hover {

            background:
                var(--gold-soft);

        }


        .edit-total-box {

            width: 290px;

            max-width: 100%;

            margin:
                18px 0 0 auto;

            padding:
                14px;

            border:
                1px solid var(--gold-border);

            border-radius: 9px;

            background:
                rgba(
                    212,
                    175,
                    55,
                    0.035
                );

        }


        .edit-total-row {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            font-size: 11px;

            color:
                var(--muted);

            padding:
                5px 0;

        }


        .edit-total-final {

            border-top:
                1px solid var(--gold-border);

            margin-top:
                7px;

            padding-top:
                10px;

            font-size: 14px;

            color:
                var(--gold-light);

            font-weight: 800;

        }


        .modal-footer {

            display: flex;

            justify-content:
                flex-end;

            gap: 10px;

            padding:
                15px 20px;

            border-top:
                1px solid var(--gold-border);

        }


        .modal-btn {

            height: 38px;

            padding:
                0 16px;

            border-radius: 8px;

            border:
                1px solid var(--gold-border);

            font-size: 11px;

            font-weight: 700;

            cursor: pointer;

        }


        .modal-btn-cancel {

            background:
                transparent;

            color:
                var(--muted);

        }


        .modal-btn-cancel:hover {

            color:
                var(--white);

            border-color:
                rgba(
                    255,
                    255,
                    255,
                    0.25
                );

        }


        .modal-btn-save {

            background:
                var(--gold);

            color:
                var(--black);

            border-color:
                var(--gold);

        }


        .modal-btn-save:hover {

            background:
                var(--gold-light);

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 950px) {

            .navbar {

                padding:
                    0 28px;

            }


            .dashboard {

                padding:
                    40px 28px 50px;

            }


            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 700px) {

            .navbar {

                height: 68px;

                padding:
                    0 18px;

            }


            .brand {

                font-size: 17px;

                gap: 9px;

            }


            .brand-icon {

                width: 35px;

                height: 35px;

                border-radius: 8px;

            }


            .nav-right {

                gap: 5px;

            }


            .nav-link {

                height: 35px;

                padding: 0 8px;

                gap: 5px;

                font-size: 10px;

                border-radius: 8px;

            }


            .nav-link i {

                font-size: 11px;

            }


            .nav-icon {

                width: 35px;

                height: 35px;

                border-radius: 8px;

            }


            .dashboard {

                min-height:
                    calc(100vh - 68px);

                padding:
                    32px 18px 45px;

            }


            .welcome-section {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap: 20px;

                margin-bottom:
                    28px;

            }


            .welcome-content h1 {

                font-size: 27px;

            }


            .create-invoice-btn {

                width: 100%;

            }


            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

                gap: 10px;

            }


            .actions-grid {

                grid-template-columns:
                    1fr;

            }


            .edit-grid {

                grid-template-columns:
                    1fr;

            }


            .edit-group.full {

                grid-column:
                    auto;

            }


            .invoice-customer-area {

                grid-template-columns:
                    1fr;

                gap: 18px;

            }

        }


        @media (max-width: 480px) {

            .navbar {

                padding:
                    0 14px;

            }


            .brand-text {

                font-size: 16px;

            }


            .nav-link span {

                display: none;

            }


            .nav-link {

                width: 35px;

                padding: 0;

            }


            .dashboard {

                padding:
                    28px 14px 40px;

            }


            .welcome-content h1 {

                font-size: 24px;

            }


            .stats-grid {

                gap: 8px;

            }


            .stat-card {

                padding: 14px;

                min-height: 100px;

            }


            .stat-value {

                font-size: 18px;

            }


            .modal-overlay {

                padding:
                    10px;

            }


            .modal-body {

                padding:
                    14px;

            }


            .invoice-paper {

                padding:
                    18px;

            }


            .invoice-paper-top {

                flex-direction:
                    column;

            }


            .invoice-paper-heading {

                text-align:
                    left;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="navbar">


    <a
        href="Dashboard.php"
        class="brand"
    >

        <div class="brand-icon">

            <i
                class="fa-solid fa-file-invoice"
            ></i>

        </div>


        <div class="brand-text">

            Invoice
            <span>Pro</span>

        </div>

    </a>


    <nav class="nav-right">


        <a
            href="History_Invoice.php"
            class="nav-link"
            title="Invoices"
        >

            <i
                class="fa-solid fa-file-invoice"
            ></i>

            <span>
                Invoices
            </span>

        </a>


        <a
            href="Products.php"
            class="nav-link"
            title="Products"
        >

            <i
                class="fa-solid fa-box"
            ></i>

            <span>
                Products
            </span>

        </a>


        <a
            href="Profile.php"
            class="nav-icon"
            title="Profile"
            aria-label="Profile"
        >

            <i
                class="fa-solid fa-user"
            ></i>

        </a>


        <div
            class="nav-icon"
            title="Change Theme"
            role="button"
            aria-label="Change Theme"
        >

            <i
                class="fa-solid fa-moon"
            ></i>

        </div>


        <a
            href="Logout.php"
            class="nav-icon"
            title="Logout"
            aria-label="Logout"
        >

            <i
                class="fa-solid fa-right-from-bracket"
            ></i>

        </a>

    </nav>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="dashboard">


<div class="dashboard-container">


<?php if ($dashboardMessage !== ''): ?>

    <div
        class="flash-message
        <?php
        echo $dashboardMessageType === 'success'
            ? 'flash-success'
            : 'flash-error';
        ?>"
    >

        <i
            class="fa-solid
            <?php
            echo $dashboardMessageType === 'success'
                ? 'fa-circle-check'
                : 'fa-circle-exclamation';
            ?>"
        ></i>

        <?php
        echo clean($dashboardMessage);
        ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     WELCOME
========================================================= -->

<section class="welcome-section">


    <div class="welcome-content">

        <h1>

            Welcome to
            <span>Invoice Pro</span>

        </h1>


        <p>

            Manage your invoices easily and keep your
            billing organized.

        </p>

    </div>


    <a
        href="Create_Invoice.php"
        class="create-invoice-btn"
    >

        <i
            class="fa-solid fa-plus"
        ></i>

        Create New Invoice

    </a>


</section>


<!-- =========================================================
     STATISTICS
========================================================= -->

<section class="stats-grid">


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">

                Total Invoices

            </div>

            <div class="stat-icon">

                <i
                    class="fa-solid fa-file-invoice"
                ></i>

            </div>

        </div>


        <div class="stat-value">

            <?php
            echo number_format(
                $totalInvoices
            );
            ?>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">

                Paid

            </div>

            <div class="stat-icon">

                <i
                    class="fa-solid fa-circle-check"
                ></i>

            </div>

        </div>


        <div class="stat-value">

            <?php
            echo number_format(
                $paidInvoices
            );
            ?>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">

                Pending

            </div>

            <div class="stat-icon">

                <i
                    class="fa-solid fa-clock"
                ></i>

            </div>

        </div>


        <div class="stat-value">

            <?php
            echo number_format(
                $pendingInvoices
            );
            ?>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">

                Revenue

            </div>

            <div class="stat-icon">

                <i
                    class="fa-solid fa-indian-rupee-sign"
                ></i>

            </div>

        </div>


        <div class="stat-value">

            ₹<?php
            echo $formattedTotalSales;
            ?>

        </div>

    </div>


</section>


<!-- =========================================================
     QUICK ACTIONS
========================================================= -->

<section class="quick-actions">


    <div class="section-header">

        <div class="section-title">

            Quick Actions

        </div>

        <div class="section-line"></div>

    </div>


    <div class="actions-grid">


        <a
            href="History_Invoice.php"
            class="action-card"
        >

            <div class="action-icon">

                <i
                    class="fa-solid fa-clock-rotate-left"
                ></i>

            </div>


            <div class="action-content">

                <h3>

                    Invoice History

                </h3>


                <p>

                    View and manage your
                    previous invoices.

                </p>

            </div>


            <div class="action-arrow">

                <i
                    class="fa-solid fa-arrow-right"
                ></i>

            </div>

        </a>


        <a
            href="Products.php"
            class="action-card"
        >

            <div class="action-icon">

                <i
                    class="fa-solid fa-box"
                ></i>

            </div>


            <div class="action-content">

                <h3>

                    Products

                </h3>


                <p>

                    Manage your products
                    and pricing.

                </p>

            </div>


            <div class="action-arrow">

                <i
                    class="fa-solid fa-arrow-right"
                ></i>

            </div>

        </a>


    </div>


</section>


<!-- =========================================================
     RECENT INVOICES
========================================================= -->

<section class="recent-section">


    <div class="section-header">

        <div class="section-title">

            Recent Invoices

        </div>

        <div class="section-line"></div>

    </div>


    <div class="recent-card">


        <?php if (count($recentInvoices) > 0): ?>


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

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $recentInvoices
                        as $invoice
                    ): ?>


                        <?php

                        $invoiceId =
                            (int)$invoice['id'];

                        $invoiceTotal =
                            (float)$invoice['total'];

                        $isPaid =
                            $invoiceTotal > 0;

                        ?>


                        <tr>


                            <td>

                                <span
                                    class="invoice-number"
                                >

                                    <?php
                                    echo clean(
                                        $invoice[
                                            'invoice_number'
                                        ]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="customer-name"
                                >

                                    <?php
                                    echo clean(
                                        $invoice[
                                            'customer_name'
                                        ]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="invoice-date"
                                >

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

                                </span>

                            </td>


                            <td>

                                <span
                                    class="invoice-amount"
                                >

                                    ₹<?php

                                    echo number_format(
                                        $invoiceTotal,
                                        2
                                    );

                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="status-badge
                                    <?php
                                    echo $isPaid
                                        ? 'status-paid'
                                        : 'status-pending';
                                    ?>"
                                >

                                    <?php
                                    echo $isPaid
                                        ? 'Paid'
                                        : 'Pending';
                                    ?>

                                </span>

                            </td>


                            <td>


                                <div
                                    class="invoice-actions"
                                >


                                    <!-- SHOW -->

                                    <button
                                        type="button"
                                        class="invoice-action"
                                        title="Show Invoice"
                                        onclick="openShowModal(<?php echo $invoiceId; ?>)"
                                    >

                                        <i
                                            class="fa-solid fa-eye"
                                        ></i>

                                    </button>


                                    <!-- UPDATE -->

                                    <button
                                        type="button"
                                        class="invoice-action"
                                        title="Update Invoice"
                                        onclick="openEditModal(<?php echo $invoiceId; ?>)"
                                    >

                                        <i
                                            class="fa-solid fa-pen"
                                        ></i>

                                    </button>


                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        action="Dashboard.php"
                                        class="delete-form"
                                        onsubmit="return confirmDelete('<?php echo clean($invoice['invoice_number']); ?>')"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_invoice"
                                        >


                                        <input
                                            type="hidden"
                                            name="invoice_id"
                                            value="<?php echo $invoiceId; ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo clean($csrfToken); ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="invoice-action delete-action"
                                            title="Delete Invoice"
                                        >

                                            <i
                                                class="fa-solid fa-trash"
                                            ></i>

                                        </button>

                                    </form>


                                </div>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


            <div class="view-all-row">

                <a
                    href="History_Invoice.php"
                    class="view-all-link"
                >

                    View All Invoices

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


        <?php else: ?>


            <div class="empty-recent">

                <div
                    class="empty-recent-icon"
                >

                    <i
                        class="fa-solid fa-file-circle-plus"
                    ></i>

                </div>


                <h3>

                    No invoices yet

                </h3>


                <p>

                    Create your first invoice
                    to see it here.

                </p>

            </div>


        <?php endif; ?>


    </div>


</section>


</div>

</main>


<!-- =========================================================
     SHOW INVOICE MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="showModal"
    onclick="closeModalOutside(event, 'showModal')"
>


    <div class="modal">


        <div class="modal-header">

            <div class="modal-title">

                <i
                    class="fa-solid fa-file-invoice"
                ></i>

                Invoice Details

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('showModal')"
            >

                <i
                    class="fa-solid fa-xmark"
                ></i>

            </button>

        </div>


        <div
            class="modal-body"
            id="showInvoiceContent"
        ></div>


    </div>

</div>


<!-- =========================================================
     UPDATE INVOICE MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="editModal"
    onclick="closeModalOutside(event, 'editModal')"
>


    <div class="modal">


        <div class="modal-header">

            <div class="modal-title">

                <i
                    class="fa-solid fa-pen"
                ></i>

                Update Invoice

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('editModal')"
            >

                <i
                    class="fa-solid fa-xmark"
                ></i>

            </button>

        </div>


        <form
            id="updateInvoiceForm"
        >


            <div
                class="modal-body"
                id="editInvoiceContent"
            ></div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="modal-btn modal-btn-cancel"
                    onclick="closeModal('editModal')"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="modal-btn modal-btn-save"
                    id="saveInvoiceBtn"
                >

                    <i
                        class="fa-solid fa-check"
                    ></i>

                    Save Changes

                </button>

            </div>


        </form>


    </div>

</div>


<script>

/* =========================================================
   INVOICE DATA
========================================================= */

const invoices = <?php

echo json_encode(
    $recentInvoices,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

?>;


const products = <?php

echo json_encode(
    $products,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

?>;


const csrfToken =
    <?php
    echo json_encode($csrfToken);
    ?>;


/* =========================================================
   FIND INVOICE
========================================================= */

function getInvoice(invoiceId)
{

    return invoices.find(
        invoice =>
            Number(invoice.id) ===
            Number(invoiceId)
    );

}


/* =========================================================
   OPEN SHOW MODAL
========================================================= */

function openShowModal(invoiceId)
{

    const invoice =
        getInvoice(invoiceId);


    if (!invoice) {

        alert("Invoice not found.");

        return;
    }


    const items =
        invoice.items || [];


    let itemsHtml = "";


    if (items.length > 0) {

        itemsHtml = items.map(
            (item, index) => `

                <tr>

                    <td>
                        ${escapeHtml(item.product_name)}
                    </td>

                    <td>
                        ${Number(item.quantity)}
                    </td>

                    <td>
                        ₹${Number(item.rate).toFixed(2)}
                    </td>

                    <td>
                        ₹${Number(item.amount).toFixed(2)}
                    </td>

                </tr>

            `
        ).join("");

    } else {

        itemsHtml = `

            <tr>

                <td colspan="4">
                    No products found.
                </td>

            </tr>

        `;

    }


    const html = `

        <div class="invoice-paper">


            <div class="invoice-paper-top">


                <div class="invoice-company">

                    <h2>
                        Invoice Pro
                    </h2>

                    <p>
                        Professional Invoice Management
                    </p>

                </div>


                <div class="invoice-paper-heading">

                    <h1>
                        INVOICE
                    </h1>

                    <p>
                        Invoice No:
                        <strong>
                            ${escapeHtml(invoice.invoice_number)}
                        </strong>
                    </p>

                    <p>
                        Date:
                        ${formatDate(invoice.issue_date)}
                    </p>

                </div>


            </div>


            <div class="invoice-customer-area">


                <div class="invoice-box">

                    <div class="invoice-box-title">
                        Bill To
                    </div>

                    <p>
                        <strong>
                            ${escapeHtml(invoice.customer_name)}
                        </strong>
                    </p>

                    <p>
                        ${escapeHtml(invoice.customer_email)}
                    </p>

                    <p>
                        ${escapeHtml(invoice.customer_phone)}
                    </p>

                    <p>
                        ${escapeHtml(invoice.customer_address)}
                    </p>

                </div>


                <div class="invoice-box">

                    <div class="invoice-box-title">
                        Invoice Summary
                    </div>

                    <p>
                        Status:
                        <strong>
                            ${
                                Number(invoice.total) > 0
                                ? "Paid"
                                : "Pending"
                            }
                        </strong>
                    </p>

                    <p>
                        Subtotal:
                        ₹${Number(invoice.subtotal).toFixed(2)}
                    </p>

                    <p>
                        Tax:
                        ₹${Number(invoice.tax).toFixed(2)}
                    </p>

                </div>


            </div>


            <table class="invoice-products">


                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Qty
                        </th>

                        <th>
                            Rate
                        </th>

                        <th>
                            Amount
                        </th>

                    </tr>

                </thead>


                <tbody>

                    ${itemsHtml}

                </tbody>


            </table>


            <div class="invoice-total-area">


                <div class="invoice-total-row">

                    <span>
                        Subtotal
                    </span>

                    <strong>
                        ₹${Number(invoice.subtotal).toFixed(2)}
                    </strong>

                </div>


                <div class="invoice-total-row">

                    <span>
                        Tax
                    </span>

                    <strong>
                        ₹${Number(invoice.tax).toFixed(2)}
                    </strong>

                </div>


                <div
                    class="invoice-total-row
                    invoice-total-final"
                >

                    <span>
                        Total
                    </span>

                    <strong>
                        ₹${Number(invoice.total).toFixed(2)}
                    </strong>

                </div>


            </div>


        </div>

    `;


    document.getElementById(
        "showInvoiceContent"
    ).innerHTML = html;


    document.getElementById(
        "showModal"
    ).classList.add("show");


    document.body.style.overflow =
        "hidden";

}


/* =========================================================
   OPEN EDIT MODAL
========================================================= */

function openEditModal(invoiceId)
{

    const invoice =
        getInvoice(invoiceId);


    if (!invoice) {

        alert("Invoice not found.");

        return;
    }


    const items =
        invoice.items || [];


    let itemRows = "";


    items.forEach(
        (item, index) => {

            itemRows +=
                createEditItemRow(
                    item,
                    index
                );

        }
    );


    if (items.length === 0) {

        itemRows =
            createEditItemRow(
                {
                    product_id: "",
                    quantity: 1,
                    rate: 0
                },
                0
            );

    }


    const html = `

        <input
            type="hidden"
            id="edit_invoice_id"
            value="${invoice.id}"
        >


        <div class="edit-section">


            <div class="edit-section-title">

                Customer Information

            </div>


            <div class="edit-grid">


                <div class="edit-group">

                    <label>
                        Customer Name
                    </label>

                    <input
                        type="text"
                        id="edit_customer_name"
                        class="edit-control"
                        value="${escapeAttribute(invoice.customer_name)}"
                        required
                    >

                </div>


                <div class="edit-group">

                    <label>
                        Customer Email
                    </label>

                    <input
                        type="email"
                        id="edit_customer_email"
                        class="edit-control"
                        value="${escapeAttribute(invoice.customer_email)}"
                        required
                    >

                </div>


                <div class="edit-group">

                    <label>
                        Customer Phone
                    </label>

                    <input
                        type="text"
                        id="edit_customer_phone"
                        class="edit-control"
                        value="${escapeAttribute(invoice.customer_phone)}"
                        required
                    >

                </div>


                <div class="edit-group">

                    <label>
                        Issue Date
                    </label>

                    <input
                        type="date"
                        id="edit_issue_date"
                        class="edit-control"
                        value="${escapeAttribute(invoice.issue_date)}"
                        required
                    >

                </div>


                <div class="edit-group full">

                    <label>
                        Customer Address
                    </label>

                    <textarea
                        id="edit_customer_address"
                        class="edit-control"
                        required
                    >${escapeHtml(invoice.customer_address)}</textarea>

                </div>


            </div>


        </div>


        <div class="edit-section">


            <div class="edit-section-title">

                Products

            </div>


            <div class="edit-items">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Rate
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Remove
                            </th>

                        </tr>

                    </thead>


                    <tbody
                        id="editItemsBody"
                    >

                        ${itemRows}

                    </tbody>


                </table>


            </div>


            <button
                type="button"
                class="add-edit-item"
                onclick="addEditItem()"
            >

                <i
                    class="fa-solid fa-plus"
                ></i>

                Add Product

            </button>


            <div class="edit-total-box">


                <div class="edit-total-row">

                    <span>
                        Subtotal
                    </span>

                    <strong
                        id="editSubtotal"
                    >
                        ₹0.00
                    </strong>

                </div>


                <div class="edit-total-row">

                    <span>
                        Tax
                    </span>

                    <strong>
                        ₹0.00
                    </strong>

                </div>


                <div
                    class="edit-total-row
                    edit-total-final"
                >

                    <span>
                        Total
                    </span>

                    <strong
                        id="editTotal"
                    >
                        ₹0.00
                    </strong>

                </div>


            </div>


        </div>

    `;


    document.getElementById(
        "editInvoiceContent"
    ).innerHTML = html;


    calculateEditTotal();


    document.getElementById(
        "editModal"
    ).classList.add("show");


    document.body.style.overflow =
        "hidden";

}


/* =========================================================
   CREATE EDIT PRODUCT OPTIONS
========================================================= */

function getProductOptions(selectedId)
{

    let options = `

        <option value="">
            Select Product
        </option>

    `;


    products.forEach(
        product => {

            const selected =
                Number(product.id) ===
                Number(selectedId)
                    ? "selected"
                    : "";


            options += `

                <option
                    value="${product.id}"
                    ${selected}
                >
                    ${escapeHtml(product.product_name)}
                </option>

            `;

        }
    );


    return options;

}


/* =========================================================
   CREATE EDIT ITEM ROW
========================================================= */

function createEditItemRow(
    item,
    index
)
{

    const productId =
        item.product_id || "";


    const quantity =
        item.quantity || 1;


    const rate =
        item.rate || 0;


    return `

        <tr class="edit-item-row">


            <td>

                <select
                    class="edit-item-input edit-product"
                    onchange="productChanged(this)"
                    required
                >

                    ${getProductOptions(productId)}

                </select>

            </td>


            <td>

                <input
                    type="number"
                    class="edit-item-input edit-quantity"
                    value="${quantity}"
                    min="1"
                    step="1"
                    oninput="calculateEditTotal()"
                    required
                >

            </td>


            <td>

                <input
                    type="number"
                    class="edit-item-input edit-rate"
                    value="${Number(rate).toFixed(2)}"
                    min="0.01"
                    step="0.01"
                    oninput="calculateEditTotal()"
                    required
                >

            </td>


            <td>

                <input
                    type="text"
                    class="edit-item-input edit-amount"
                    value="₹${(
                        Number(quantity) *
                        Number(rate)
                    ).toFixed(2)}"
                    readonly
                >

            </td>


            <td>

                <button
                    type="button"
                    class="remove-edit-item"
                    onclick="removeEditItem(this)"
                    title="Remove Product"
                >

                    <i
                        class="fa-solid fa-trash"
                    ></i>

                </button>

            </td>


        </tr>

    `;

}


/* =========================================================
   ADD PRODUCT
========================================================= */

function addEditItem()
{

    const body =
        document.getElementById(
            "editItemsBody"
        );


    const row =
        document.createElement("tr");


    row.className =
        "edit-item-row";


    row.innerHTML =
        createEditItemRow(
            {
                product_id: "",
                quantity: 1,
                rate: 0
            },
            body.children.length
        ).replace(
            /^<tr[^>]*>|<\/tr>$/g,
            ""
        );


    body.appendChild(row);


    calculateEditTotal();

}


/* =========================================================
   REMOVE PRODUCT
========================================================= */

function removeEditItem(button)
{

    const body =
        document.getElementById(
            "editItemsBody"
        );


    if (
        body.children.length <= 1
    ) {

        alert(
            "At least one product is required."
        );

        return;
    }


    button
        .closest("tr")
        .remove();


    calculateEditTotal();

}


/* =========================================================
   PRODUCT CHANGE
========================================================= */

function productChanged(select)
{

    const row =
        select.closest("tr");


    const product =
        products.find(
            p =>
                Number(p.id) ===
                Number(select.value)
        );


    if (product) {

        const rateInput =
            row.querySelector(
                ".edit-rate"
            );


        rateInput.value =
            Number(
                product.price
            ).toFixed(2);

    }


    calculateEditTotal();

}


/* =========================================================
   CALCULATE TOTAL
========================================================= */

function calculateEditTotal()
{

    const rows =
        document.querySelectorAll(
            "#editItemsBody .edit-item-row"
        );


    let subtotal = 0;


    rows.forEach(
        row => {

            const quantity =
                Number(
                    row.querySelector(
                        ".edit-quantity"
                    )?.value || 0
                );


            const rate =
                Number(
                    row.querySelector(
                        ".edit-rate"
                    )?.value || 0
                );


            const amount =
                quantity * rate;


            const amountInput =
                row.querySelector(
                    ".edit-amount"
                );


            if (amountInput) {

                amountInput.value =
                    "₹" +
                    amount.toFixed(2);

            }


            subtotal += amount;

        }
    );


    const subtotalElement =
        document.getElementById(
            "editSubtotal"
        );


    const totalElement =
        document.getElementById(
            "editTotal"
        );


    if (subtotalElement) {

        subtotalElement.textContent =
            "₹" +
            subtotal.toFixed(2);

    }


    if (totalElement) {

        totalElement.textContent =
            "₹" +
            subtotal.toFixed(2);

    }

}


/* =========================================================
   SUBMIT UPDATE
========================================================= */

document
    .getElementById(
        "updateInvoiceForm"
    )
    .addEventListener(
        "submit",
        async function(event)
        {

            event.preventDefault();


            const saveButton =
                document.getElementById(
                    "saveInvoiceBtn"
                );


            const rows =
                document.querySelectorAll(
                    "#editItemsBody .edit-item-row"
                );


            const items = [];


            rows.forEach(
                row => {

                    const productId =
                        Number(
                            row.querySelector(
                                ".edit-product"
                            )?.value || 0
                        );


                    const quantity =
                        Number(
                            row.querySelector(
                                ".edit-quantity"
                            )?.value || 0
                        );


                    const rate =
                        Number(
                            row.querySelector(
                                ".edit-rate"
                            )?.value || 0
                        );


                    if (
                        productId > 0 &&
                        quantity > 0 &&
                        rate > 0
                    ) {

                        items.push({
                            product_id:
                                productId,

                            quantity:
                                quantity,

                            rate:
                                rate
                        });

                    }

                }
            );


            if (items.length === 0) {

                alert(
                    "Please add at least one valid product."
                );

                return;
            }


            const formData =
                new FormData();


            formData.append(
                "action",
                "update_invoice"
            );


            formData.append(
                "csrf_token",
                csrfToken
            );


            formData.append(
                "invoice_id",
                document.getElementById(
                    "edit_invoice_id"
                ).value
            );


            formData.append(
                "customer_name",
                document.getElementById(
                    "edit_customer_name"
                ).value.trim()
            );


            formData.append(
                "customer_email",
                document.getElementById(
                    "edit_customer_email"
                ).value.trim()
            );


            formData.append(
                "customer_phone",
                document.getElementById(
                    "edit_customer_phone"
                ).value.trim()
            );


            formData.append(
                "customer_address",
                document.getElementById(
                    "edit_customer_address"
                ).value.trim()
            );


            formData.append(
                "issue_date",
                document.getElementById(
                    "edit_issue_date"
                ).value
            );


            formData.append(
                "items",
                JSON.stringify(items)
            );


            saveButton.disabled =
                true;


            saveButton.innerHTML = `

                <i
                    class="fa-solid fa-spinner fa-spin"
                ></i>

                Saving...

            `;


            try {

                const response =
                    await fetch(
                        "Dashboard.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );


                const result =
                    await response.json();


                if (
                    result.success
                ) {

                    alert(
                        result.message
                    );


                    window.location.reload();

                } else {

                    alert(
                        result.message ||
                        "Unable to update invoice."
                    );

                }


            } catch (error) {

                alert(
                    "Something went wrong while updating the invoice."
                );

            } finally {

                saveButton.disabled =
                    false;

                saveButton.innerHTML = `

                    <i
                        class="fa-solid fa-check"
                    ></i>

                    Save Changes

                `;

            }

        }
    );


/* =========================================================
   DELETE CONFIRMATION
========================================================= */

function confirmDelete(
    invoiceNumber
)
{

    return confirm(
        "Delete " +
        invoiceNumber +
        "?\n\n" +
        "This will permanently remove the invoice and its items from the database."
    );

}


/* =========================================================
   CLOSE MODAL
========================================================= */

function closeModal(modalId)
{

    const modal =
        document.getElementById(
            modalId
        );


    if (modal) {

        modal.classList.remove(
            "show"
        );

    }


    document.body.style.overflow =
        "";

}


/* =========================================================
   CLOSE OUTSIDE
========================================================= */

function closeModalOutside(
    event,
    modalId
)
{

    if (
        event.target.id ===
        modalId
    ) {

        closeModal(
            modalId
        );

    }

}


/* =========================================================
   ESCAPE KEY
========================================================= */

document.addEventListener(
    "keydown",
    function(event)
    {

        if (
            event.key === "Escape"
        ) {

            closeModal(
                "showModal"
            );

            closeModal(
                "editModal"
            );

        }

    }
);


/* =========================================================
   FORMAT DATE
========================================================= */

function formatDate(dateString)
{

    if (!dateString) {

        return "";

    }


    const parts =
        dateString.split("-");


    if (parts.length !== 3) {

        return dateString;

    }


    return (
        parts[2] +
        " " +
        getMonthName(
            Number(parts[1])
        ) +
        " " +
        parts[0]
    );

}


function getMonthName(month)
{

    const months = [

        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec"

    ];


    return months[
        month - 1
    ] || "";

}


/* =========================================================
   HTML ESCAPE
========================================================= */

function escapeHtml(value)
{

    return String(
        value ?? ""
    )
    .replace(
        /&/g,
        "&amp;"
    )
    .replace(
        /</g,
        "&lt;"
    )
    .replace(
        />/g,
        "&gt;"
    )
    .replace(
        /"/g,
        "&quot;"
    )
    .replace(
        /'/g,
        "&#039;"
    );

}


function escapeAttribute(value)
{

    return escapeHtml(
        value
    );

}

</script>


</body>

</html>
