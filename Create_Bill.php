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

$userId = (int)$_SESSION['user_id'];


/* =========================================================
   HELPER
========================================================= */

function clean($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* =========================================================
   GET LOGGED-IN USER / COMPANY DETAILS
========================================================= */

$userName = "User";
$companyName = "Company";
$companyEmail = "";
$companyPhone = "";
$companyAddress = "";
$memberSince = "";

$stmt = $conn->prepare("
    SELECT id, company_name, company_email, phone, address, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$userResult = $stmt->get_result();

if ($userResult->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit;
}

$user = $userResult->fetch_assoc();

$stmt->close();

$companyName = $user['company_name'] ?? "Company";
$companyEmail = $user['company_email'] ?? "";
$companyPhone = $user['phone'] ?? "";
$companyAddress = $user['address'] ?? "";

$userName = $companyName;

if (!empty($user['created_at'])) {
    $memberSince = date("d M Y", strtotime($user['created_at']));
}


/* =========================================================
   AJAX - CREATE INVOICE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invoice') {

    header('Content-Type: application/json');

    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $issueDate = trim($_POST['issue_date'] ?? '');
    $itemsJson = $_POST['items'] ?? '';

    /* -------------------------
       Validation
    ------------------------- */

    if ($customerName === '') {
        echo json_encode([
            "success" => false,
            "message" => "Customer name is required."
        ]);
        exit;
    }

    if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "success" => false,
            "message" => "Please enter a valid customer email."
        ]);
        exit;
    }

    if ($customerPhone === '') {
        echo json_encode([
            "success" => false,
            "message" => "Customer phone number is required."
        ]);
        exit;
    }

    if ($customerAddress === '') {
        echo json_encode([
            "success" => false,
            "message" => "Customer address is required."
        ]);
        exit;
    }

    if ($issueDate === '') {
        $issueDate = date("Y-m-d");
    }

    $items = json_decode($itemsJson, true);

    if (!is_array($items) || count($items) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Please add at least one product."
        ]);
        exit;
    }


    /* =====================================================
       VALIDATE PRODUCTS FROM DATABASE
    ===================================================== */

    $validItems = [];
    $subtotal = 0;

    $productStmt = $conn->prepare("
        SELECT id, product_name, price
        FROM products
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    foreach ($items as $item) {

        $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $rate = isset($item['rate']) ? (float)$item['rate'] : 0;

        if ($productId <= 0 || $quantity <= 0 || $rate <= 0) {
            continue;
        }

        $productStmt->bind_param("ii", $productId, $userId);
        $productStmt->execute();

        $productResult = $productStmt->get_result();

        if ($productResult->num_rows !== 1) {
            continue;
        }

        $product = $productResult->fetch_assoc();

        $productName = $product['product_name'];

        /*
         * Rate comes from the form so the user can adjust
         * the selling rate if required.
         */
        $amount = $quantity * $rate;

        $subtotal += $amount;

        $validItems[] = [
            "product_id" => $productId,
            "product_name" => $productName,
            "quantity" => $quantity,
            "rate" => $rate,
            "amount" => $amount
        ];
    }

    $productStmt->close();


    if (count($validItems) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Please select a valid product and enter quantity and rate."
        ]);
        exit;
    }


    /* =====================================================
       TAX
    ===================================================== */

    $tax = 0;
    $total = $subtotal + $tax;


    /* =====================================================
       GENERATE INVOICE NUMBER
    ===================================================== */

    $invoiceNumber = "INV-" . date("Ymd") . "-" . strtoupper(substr(uniqid(), -5));


    /* =====================================================
       DATABASE TRANSACTION
    ===================================================== */

    $conn->begin_transaction();

    try {

        /* -------------------------
           INSERT INVOICE
        ------------------------- */

        $invoiceStmt = $conn->prepare("
            INSERT INTO invoices
            (
                user_id,
                invoice_number,
                customer_name,
                customer_email,
                customer_phone,
                customer_address,
                issue_date,
                subtotal,
                tax,
                total
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $invoiceStmt->bind_param(
            "issssssddd",
            $userId,
            $invoiceNumber,
            $customerName,
            $customerEmail,
            $customerPhone,
            $customerAddress,
            $issueDate,
            $subtotal,
            $tax,
            $total
        );

        if (!$invoiceStmt->execute()) {
            throw new Exception("Unable to create invoice.");
        }

        $invoiceId = $conn->insert_id;

        $invoiceStmt->close();


        /* -------------------------
           INSERT INVOICE ITEMS
        ------------------------- */

        $itemStmt = $conn->prepare("
            INSERT INTO invoice_items
            (
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

            $itemProductId = $item['product_id'];
            $itemProductName = $item['product_name'];
            $itemQuantity = $item['quantity'];
            $itemRate = $item['rate'];
            $itemAmount = $item['amount'];

            $itemStmt->bind_param(
                "iisidd",
                $invoiceId,
                $itemProductId,
                $itemProductName,
                $itemQuantity,
                $itemRate,
                $itemAmount
            );

            if (!$itemStmt->execute()) {
                throw new Exception("Unable to save invoice items.");
            }
        }

        $itemStmt->close();


        /* -------------------------
           COMMIT
        ------------------------- */

        $conn->commit();


        echo json_encode([
            "success" => true,
            "message" => "Invoice created successfully.",
            "invoice_id" => $invoiceId,
            "invoice_number" => $invoiceNumber,
            "issue_date" => date("d-m-Y", strtotime($issueDate)),
            "subtotal" => number_format($subtotal, 2, '.', ''),
            "tax" => number_format($tax, 2, '.', ''),
            "total" => number_format($total, 2, '.', '')
        ]);

        exit;

    } catch (Exception $e) {

        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);

        exit;
    }
}


/* =========================================================
   LOAD PRODUCTS FROM DATABASE
========================================================= */

$products = [];

$productListStmt = $conn->prepare("
    SELECT id, product_name, description, price, stock
    FROM products
    WHERE user_id = ?
    ORDER BY product_name ASC
");

$productListStmt->bind_param("i", $userId);
$productListStmt->execute();

$productListResult = $productListStmt->get_result();

while ($productRow = $productListResult->fetch_assoc()) {
    $products[] = $productRow;
}

$productListStmt->close();


/* =========================================================
   CURRENT DATE
========================================================= */

$currentDate = date("d M Y");
$issueDate = date("d-m-Y");

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>InvoicePro - Create Invoice</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>

        :root {
            --bg-body: #f4f7fa;
            --bg-sidebar: #172136;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --primary-blue: #2563eb;
            --primary-light: #eff6ff;
            --danger: #ef4444;
            --success: #16a34a;
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

        /* SIDEBAR */

        .sidebar {
            width: 225px;
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
        }

        .logout:hover {
            color: #fff;
        }

        /* MAIN */

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        /* HEADER */

        .header {
            background: var(--white);
            padding: 14px 25px;
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
            width: 320px;
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
            padding: 9px 10px 9px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: var(--input-bg);
            font-size: 13px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .current-date {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-date i {
            color: var(--primary-blue);
            font-size: 14px;
        }

        /* PAGE */

        .page-container {
            padding: 18px 25px;
            max-width: 100%;
            margin: 0 auto;
            width: 100%;
        }

        .page-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .page-header {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .header-title-area {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 46px;
            height: 46px;
            background: var(--primary-light);
            color: var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
        }

        .header-text h1 {
            font-size: 20px;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-weight: 700;
        }

        .header-text p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* SECTION */

        .form-section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 13px;
        }

        /* GRID */

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* INPUTS */

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-group label span {
            color: var(--danger);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 13px;
            top: 13px;
            color: var(--text-light);
            font-size: 13px;
            z-index: 1;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px 10px 37px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-dark);
            outline: none;
            transition: 0.2s;
            height: 39px;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
        }

        .form-control::placeholder {
            color: #cbd5e1;
        }

        textarea.form-control {
            resize: none;
            min-height: 39px;
            height: 39px;
        }

        /* PRODUCTS TABLE */

        .table-container {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow-x: auto;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: var(--input-bg);
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 10px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .table-input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            color: var(--text-dark);
            outline: none;
            background: #fff;
        }

        .table-input:focus {
            border-color: var(--primary-blue);
        }

        select.table-input {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 8px center;
            padding-right: 30px;
        }

        .readonly-input {
            background-color: var(--input-bg);
            color: var(--text-muted);
        }

        .btn-delete {
            color: var(--danger);
            background: none;
            border: none;
            font-size: 15px;
            cursor: pointer;
            padding: 6px;
            transition: 0.2s;
        }

        .btn-delete:hover {
            color: #b91c1c;
            transform: scale(1.1);
        }

        .btn-add-item {
            background: transparent;
            color: var(--primary-blue);
            border: 1px solid #bfdbfe;
            padding: 9px 14px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: 0.2s;
        }

        .btn-add-item:hover {
            background: var(--primary-light);
        }

        /* TOTALS */

        .totals-box {
            background: var(--input-bg);
            padding: 17px;
            border-radius: 8px;
            border: 1px solid var(--border);
            margin-top: 14px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .totals-value {
            color: var(--text-dark);
            font-weight: 600;
        }

        .totals-row.total-final {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary-blue);
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            margin-bottom: 0;
        }

        /* BUTTONS */

        .actions-row {
            display: flex;
            gap: 14px;
            margin-top: 24px;
        }

        .btn-large {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-reset {
            background: var(--white);
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
        }

        .btn-reset:hover {
            background: var(--primary-light);
        }

        .btn-submit {
            background: var(--primary-blue);
            color: #fff;
            border: 1px solid var(--primary-blue);
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* PREVIEW */

        .invoice-preview {
            display: none;
            margin-top: 24px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
        }

        .invoice-preview.show {
            display: block;
        }

        .invoice-preview-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-blue);
        }

        .company-name {
            font-size: 23px;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .company-details {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .invoice-heading {
            text-align: right;
        }

        .invoice-heading h2 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .invoice-heading p {
            color: var(--text-muted);
            font-size: 12px;
        }

        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 22px 0;
        }

        .invoice-info-box {
            min-width: 0;
        }

        .invoice-info h4 {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .customer-detail {
            font-size: 13px;
            margin-bottom: 6px;
            line-height: 1.5;
        }

        .customer-detail strong {
            color: var(--text-dark);
            font-weight: 600;
            margin-right: 5px;
        }

        .customer-detail span {
            color: var(--text-muted);
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 0;
        }

        .preview-table th {
            background: var(--bg-sidebar);
            color: #fff;
            padding: 10px;
            font-size: 11px;
        }

        .preview-table td {
            padding: 10px;
            font-size: 12px;
            border-bottom: 1px solid var(--border);
        }

        .preview-totals {
            width: 300px;
            max-width: 100%;
            margin-left: auto;
            margin-top: 18px;
        }

        .preview-total-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        .preview-total-row.final {
            border-top: 2px solid var(--border);
            margin-top: 7px;
            padding-top: 12px;
            color: var(--primary-blue);
            font-size: 17px;
            font-weight: 700;
        }

        .preview-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }

        .preview-action-btn {
            padding: 10px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-print {
            background: var(--primary-blue);
            color: #fff;
            border: 1px solid var(--primary-blue);
        }

        .btn-print:hover {
            background: #1d4ed8;
        }

        .btn-download {
            background: #fff;
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
        }

        .btn-download:hover {
            background: var(--primary-light);
        }

        /* EMPTY PRODUCT */

        .no-products {
            padding: 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }

        .no-products a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .sidebar {
                width: 200px;
            }

            .grid-3 {
                grid-template-columns: 1fr;
            }

            .invoice-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {

            body {
                overflow: auto;
            }

            .sidebar {
                width: 180px;
            }

            .header {
                padding: 12px 18px;
            }

            .search-bar {
                width: 220px;
            }

            .page-container {
                padding: 15px;
            }

            .page-card {
                padding: 18px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 18px;
            }

            .invoice-preview-header {
                flex-direction: column;
            }

            .invoice-heading {
                text-align: left;
            }

            .preview-actions {
                flex-direction: column;
            }

            .preview-action-btn {
                width: 100%;
            }
        }

        @media (max-width: 500px) {

            .sidebar {
                width: 70px;
            }

            .sidebar-logo {
                justify-content: center;
                padding: 20px 10px;
                font-size: 0;
            }

            .sidebar-logo i {
                font-size: 23px;
            }

            .nav-menu {
                padding: 0 8px;
            }

            .nav-item {
                justify-content: center;
                padding: 12px 8px;
                font-size: 0;
            }

            .nav-item i {
                font-size: 16px;
            }

            .logout {
                justify-content: center;
                margin: 0 8px;
                padding: 14px 5px;
                font-size: 0;
            }

            .logout i {
                font-size: 16px;
            }

            .search-bar {
                width: 170px;
            }

            .current-date {
                font-size: 11px;
            }
        }

        /* PRINT */

        @media print {

            body {
                display: block;
                background: #fff;
                overflow: visible;
            }

            .sidebar,
            .header,
            #invoiceForm,
            .preview-actions {
                display: none !important;
            }

            .main-content {
                overflow: visible;
            }

            .page-container {
                padding: 0;
            }

            .invoice-preview {
                display: block !important;
                border: none;
                margin: 0;
            }
        }

    
        /* =========================================================
           DASHBOARD-MATCHED SIZE
           Visual sizing only — functionality remains unchanged.
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
            border-radius: 7px;
        }

        .nav-item a,
        .logout a {
            gap: 11px;
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
            height: 36px;
        }

        .header-right {
            gap: 10px;
        }

        .user-profile {
            gap: 8px;
            font-size: 12px;
        }

        .user-profile img {
            width: 30px;
            height: 30px;
        }

        .invoice-container {
            padding: 18px 25px;
            max-width: none;
            width: 100%;
        }

        .page-header {
            gap: 11px;
            margin-bottom: 18px;
        }

        .page-header-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            font-size: 18px;
        }

        .page-header-text h1 {
            font-size: 17px;
            margin-bottom: 3px;
        }

        .page-header-text p {
            font-size: 11px;
        }

        .alert {
            padding: 9px 12px;
            border-radius: 7px;
            margin-bottom: 14px;
            font-size: 11px;
        }

        .no-products-warning {
            padding: 10px 12px;
            border-radius: 7px;
            font-size: 11px;
            margin-bottom: 14px;
        }

        .details-grid {
            gap: 12px;
        }

        .card {
            border-radius: 10px;
            padding: 18px;
        }

        .form-section-title {
            gap: 7px;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 11px;
        }

        .form-group label {
            font-size: 11px;
            margin-bottom: 5px;
        }

        .input-icon {
            left: 11px;
            font-size: 12px;
        }

        .form-control {
            padding: 9px 10px;
            border-radius: 7px;
            font-size: 12px;
            height: 36px;
        }

        .form-control.with-icon {
            padding-left: 34px;
        }

        textarea.form-control {
            min-height: 60px;
        }

        .products-card {
            margin-top: 12px;
        }

        .products-header {
            margin-bottom: 10px;
        }

        .products-header > div {
            font-size: 11px !important;
        }

        .btn-primary {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            gap: 6px;
        }

        .product-row-header {
            grid-template-columns: 2fr 1fr 100px 120px 45px;
            gap: 10px;
            padding: 0 10px 7px;
            font-size: 10px;
        }

        .product-row {
            grid-template-columns: 2fr 1fr 100px 120px 45px;
            gap: 10px;
            padding: 9px;
            border-radius: 7px;
            margin-bottom: 7px;
        }

        .product-row .form-control {
            height: 34px;
            padding: 8px;
            font-size: 11px;
        }

        .amount-display {
            font-size: 11px;
            padding: 8px 7px;
            border-radius: 6px;
        }

        .remove-btn {
            width: 30px;
            height: 30px;
            border-radius: 6px;
        }

        .empty-products {
            padding: 25px 15px;
            border-radius: 7px;
        }

        .empty-products i {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .bottom-section {
            gap: 18px;
            margin-top: 18px;
        }

        .notes-area textarea {
            min-height: 105px;
        }

        .totals-box {
            padding: 14px;
            border-radius: 7px;
        }

        .totals-row {
            margin-bottom: 8px;
            font-size: 11px;
        }

        .totals-row.bold {
            font-size: 14px;
            margin-top: 11px;
            padding-top: 11px;
        }

        .discount-input,
        .tax-input {
            width: 55px;
            padding: 4px 6px;
            font-size: 11px;
            margin-left: 6px;
        }

        .bottom-actions {
            gap: 12px;
            margin-top: 18px;
        }

        .btn-large {
            padding: 10px;
            border-radius: 7px;
            font-size: 13px;
            gap: 7px;
        }

        @media (max-width: 1100px) {
            .sidebar {
                width: 205px;
                min-width: 205px;
            }

            .invoice-container {
                padding: 18px 20px;
            }

            .product-row,
            .product-row-header {
                grid-template-columns: 1.5fr 1fr 80px 100px 40px;
            }
        }

        @media (max-width: 700px) {
            .header {
                padding: 12px 18px;
            }

            .invoice-container {
                padding: 15px;
            }

            .search-bar {
                width: 200px;
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

        <li class="nav-item"
            onclick="window.location.href='Dashboard.php'">
            <i class="fa-solid fa-house"></i>
            Home
        </li>

        <li class="nav-item active"
            onclick="window.location.href='Create_Invoice.php'">
            <i class="fa-solid fa-file-circle-plus"></i>
            Create Invoice
        </li>

        <li class="nav-item"
            onclick="window.location.href='History_Invoice.php'">
            <i class="fa-solid fa-file-invoice"></i>
            Invoice History
        </li>

        <li class="nav-item"
            onclick="window.location.href='Create_Bill.php'">
            <i class="fa-solid fa-file-circle-plus"></i>
            Create Bill
        </li>

        <li class="nav-item"
            onclick="window.location.href='Bill_History.php'">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Bill History
        </li>

        <li class="nav-item"
            onclick="window.location.href='Products.php'">
            <i class="fa-solid fa-box-open"></i>
            Products
        </li>

        <li class="nav-item"
            onclick="window.location.href='Profile.php'">
            <i class="fa-regular fa-user"></i>
            Profile
        </li>

    </ul>

    <div class="logout"
         onclick="window.location.href='Login.php'">

        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        Logout

    </div>

</div>


<!-- ================= MAIN ================= -->

<div class="main-content">


    <!-- HEADER -->

    <div class="header">

        <div class="search-bar">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input type="text"
                   placeholder="Search anything...">

        </div>

        <div class="header-right">

            <div class="current-date">

                <i class="fa-regular fa-calendar"></i>

                <?php echo clean($currentDate); ?>

            </div>

        </div>

    </div>


    <!-- PAGE -->

    <div class="page-container">


        <form id="invoiceForm"
              onsubmit="createInvoice(event)">

            <div class="page-card">


                <!-- PAGE HEADER -->

                <div class="page-header">

                    <div class="header-title-area">

                        <div class="header-icon">

                            <i class="fa-regular fa-file-lines"></i>

                        </div>

                        <div class="header-text">

                            <h1>Create Invoice</h1>

                            <p>
                                Fill in the details below to create a new invoice.
                            </p>

                        </div>

                    </div>

                </div>


                <!-- CUSTOMER DETAILS -->

                <div class="form-section">

                    <div class="section-title">
                        Customer Details
                    </div>

                    <div class="grid-2">


                        <div class="form-group">

                            <label>
                                Customer Name <span>*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-regular fa-user"></i>

                                <input
                                    type="text"
                                    id="customerName"
                                    class="form-control"
                                    placeholder="Enter customer name"
                                    required
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Email <span>*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-regular fa-envelope"></i>

                                <input
                                    type="email"
                                    id="customerEmail"
                                    class="form-control"
                                    placeholder="Enter customer email"
                                    required
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Phone Number <span>*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-phone"></i>

                                <input
                                    type="text"
                                    id="customerPhone"
                                    class="form-control"
                                    placeholder="Enter phone number"
                                    required
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Address <span>*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-location-dot"></i>

                                <textarea
                                    id="customerAddress"
                                    class="form-control"
                                    placeholder="Enter customer address"
                                    required
                                ></textarea>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- INVOICE DETAILS -->

                <div class="form-section">

                    <div class="section-title">
                        Invoice Details
                    </div>

                    <div class="grid-3">


                        <div class="form-group">

                            <label>
                                Invoice Number
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-hashtag"></i>

                                <input
                                    type="text"
                                    class="form-control readonly-input"
                                    value="Auto Generated"
                                    readonly
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Issue Date
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-regular fa-calendar"></i>

                                <input
                                    type="date"
                                    id="issueDate"
                                    class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>"
                                >

                            </div>

                        </div>


                    </div>

                </div>


                <!-- PRODUCTS -->

                <div class="form-section">

                    <div class="section-title">
                        Add Products / Services
                    </div>

                    <div class="table-container">

                        <table>

                            <thead>

                                <tr>

                                    <th style="width: 50px;">
                                        #
                                    </th>

                                    <th style="width: 40%;">
                                        Product / Service
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Rate (₹)
                                    </th>

                                    <th>
                                        Amount (₹)
                                    </th>

                                    <th style="width: 50px;"></th>

                                </tr>

                            </thead>

                            <tbody id="itemsBody">

                                <tr class="item-row">

                                    <td class="row-number"
                                        style="color: var(--text-dark); font-weight: 500;">
                                        1
                                    </td>

                                    <td>

                                        <select class="table-input product-name"
                                                onchange="productChanged(this)">

                                            <option value="">
                                                Select Product
                                            </option>

                                            <?php foreach ($products as $product): ?>

                                                <option
                                                    value="<?php echo (int)$product['id']; ?>"
                                                    data-price="<?php echo htmlspecialchars($product['price']); ?>"
                                                >
                                                    <?php echo clean($product['product_name']); ?>
                                                    <?php if ((int)$product['stock'] <= 0): ?>
                                                        (Out of Stock)
                                                    <?php endif; ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            class="table-input quantity"
                                            value="1"
                                            min="1"
                                            step="1"
                                        >

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            class="table-input rate"
                                            placeholder="0.00"
                                            min="0"
                                            step="0.01"
                                        >

                                    </td>

                                    <td>

                                        <input
                                            type="text"
                                            class="table-input readonly-input amount"
                                            placeholder="0.00"
                                            value="0.00"
                                            readonly
                                        >

                                    </td>

                                    <td style="text-align: center;">

                                        <button
                                            type="button"
                                            class="btn-delete"
                                            onclick="deleteItem(this)"
                                            title="Delete Item"
                                        >

                                            <i class="fa-regular fa-trash-can"></i>

                                        </button>

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>


                    <?php if (count($products) === 0): ?>

                        <div class="no-products">

                            No products found.

                            <a href="Products.php">
                                Add products first
                            </a>

                        </div>

                    <?php endif; ?>


                    <button
                        type="button"
                        class="btn-add-item"
                        onclick="addItem()"
                    >

                        <i class="fa-solid fa-plus"></i>

                        Add Item

                    </button>

                </div>


                <!-- TOTALS -->

                <div class="totals-box">

                    <div class="totals-row">

                        <span>
                            Subtotal
                        </span>

                        <span id="subtotal"
                              class="totals-value">
                            ₹ 0.00
                        </span>

                    </div>


                    <div class="totals-row">

                        <span>
                            Tax (0%)
                        </span>

                        <span id="tax"
                              class="totals-value">
                            ₹ 0.00
                        </span>

                    </div>


                    <div class="totals-row total-final">

                        <span>
                            Total
                        </span>

                        <span id="total">
                            ₹ 0.00
                        </span>

                    </div>

                </div>


                <!-- ACTIONS -->

                <div class="actions-row">

                    <button
                        type="reset"
                        class="btn-large btn-reset"
                        onclick="resetInvoice()"
                    >

                        <i class="fa-solid fa-rotate-right"></i>

                        Reset

                    </button>


                    <button
                        type="submit"
                        class="btn-large btn-submit"
                        id="createInvoiceButton"
                    >

                        <i class="fa-regular fa-paper-plane"></i>

                        Create Invoice

                    </button>

                </div>

            </div>

        </form>


        <!-- ================= PREVIEW ================= -->

        <div id="invoicePreview"
             class="invoice-preview">


            <!-- HEADER -->

            <div class="invoice-preview-header">

                <div>

                    <div class="company-name">

                        <?php echo clean($companyName); ?>

                    </div>

                    <div class="company-details">

                        <div>
                            <?php echo clean($companyAddress); ?>
                        </div>

                        <div>
                            <?php echo clean($companyPhone); ?>
                        </div>

                        <div>
                            <?php echo clean($companyEmail); ?>
                        </div>

                    </div>

                </div>


                <div class="invoice-heading">

                    <h2>
                        INVOICE
                    </h2>

                    <p>
                        Invoice No:
                        <strong id="previewInvoiceNumber"></strong>
                    </p>

                    <p>
                        Date:
                        <strong id="previewIssueDate"></strong>
                    </p>

                </div>

            </div>


            <!-- FROM / BILL TO -->

            <div class="invoice-info">


                <!-- FROM -->

                <div class="invoice-info-box">

                    <h4>
                        From
                    </h4>

                    <p class="customer-detail">

                        <strong>
                            Company:
                        </strong>

                        <span>
                            <?php echo clean($companyName); ?>
                        </span>

                    </p>

                    <p class="customer-detail">

                        <strong>
                            Email:
                        </strong>

                        <span>
                            <?php echo clean($companyEmail); ?>
                        </span>

                    </p>

                    <p class="customer-detail">

                        <strong>
                            Contact:
                        </strong>

                        <span>
                            <?php echo clean($companyPhone); ?>
                        </span>

                    </p>

                    <p class="customer-detail">

                        <strong>
                            Address:
                        </strong>

                        <span>
                            <?php echo clean($companyAddress); ?>
                        </span>

                    </p>

                </div>


                <!-- BILL TO -->

                <div class="invoice-info-box">

                    <h4>
                        Bill To
                    </h4>

                    <p class="customer-detail">

                        <strong>
                            Customer Name:
                        </strong>

                        <span id="previewCustomerName"></span>

                    </p>

                    <p class="customer-detail">

                        <strong>
                            Email:
                        </strong>

                        <span id="previewCustomerEmail"></span>

                    </p>

                    <p class="customer-detail">

                        <strong>
                            Contact:
                        </strong>

                        <span id="previewCustomerPhone"></span>

                    </p>

                    <p class="customer-detail">

                        <strong>
                            Address:
                        </strong>

                        <span id="previewCustomerAddress"></span>

                    </p>

                </div>

            </div>


            <!-- PREVIEW ITEMS -->

            <table class="preview-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Product / Service
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

                    </tr>

                </thead>

                <tbody id="previewItemsBody">

                </tbody>

            </table>


            <!-- PREVIEW TOTALS -->

            <div class="preview-totals">

                <div class="preview-total-row">

                    <span>
                        Subtotal
                    </span>

                    <strong id="previewSubtotal">
                        ₹ 0.00
                    </strong>

                </div>


                <div class="preview-total-row">

                    <span>
                        Tax (0%)
                    </span>

                    <strong id="previewTax">
                        ₹ 0.00
                    </strong>

                </div>


                <div class="preview-total-row final">

                    <span>
                        Total
                    </span>

                    <strong id="previewTotal">
                        ₹ 0.00
                    </strong>

                </div>

            </div>


            <!-- PREVIEW ACTIONS -->

            <div class="preview-actions">

                <button
                    type="button"
                    class="preview-action-btn btn-print"
                    onclick="printInvoice()"
                >

                    <i class="fa-solid fa-print"></i>

                    Print Invoice

                </button>


                <button
                    type="button"
                    class="preview-action-btn btn-download"
                    onclick="downloadInvoice()"
                >

                    <i class="fa-solid fa-download"></i>

                    Download Invoice

                </button>

            </div>

        </div>

    </div>

</div>


<!-- ================= JAVASCRIPT ================= -->

<script>

/* =========================================================
   PRODUCT DATA
========================================================= */

const productData = <?php echo json_encode($products); ?>;


/* =========================================================
   PRODUCT CHANGE
========================================================= */

function productChanged(select) {

    const row = select.closest(".item-row");

    const selectedOption =
        select.options[select.selectedIndex];

    if (!selectedOption || !selectedOption.value) {

        row.querySelector(".rate").value = "";

        calculateRow(row);

        return;
    }

    const price =
        parseFloat(
            selectedOption.getAttribute("data-price")
        ) || 0;

    row.querySelector(".rate").value =
        price.toFixed(2);

    calculateRow(row);
}


/* =========================================================
   ADD ITEM
========================================================= */

function addItem() {

    const tbody =
        document.getElementById("itemsBody");

    const row =
        document.createElement("tr");

    row.className = "item-row";

    let productOptions = `
        <option value="">
            Select Product
        </option>
    `;

    productData.forEach(product => {

        productOptions += `
            <option
                value="${product.id}"
                data-price="${product.price}"
            >
                ${escapeHtml(product.product_name)}
                ${parseInt(product.stock) <= 0 ? " (Out of Stock)" : ""}
            </option>
        `;

    });


    row.innerHTML = `

        <td class="row-number"
            style="color: var(--text-dark); font-weight: 500;">
            1
        </td>

        <td>

            <select
                class="table-input product-name"
                onchange="productChanged(this)"
            >

                ${productOptions}

            </select>

        </td>

        <td>

            <input
                type="number"
                class="table-input quantity"
                value="1"
                min="1"
                step="1"
            >

        </td>

        <td>

            <input
                type="number"
                class="table-input rate"
                placeholder="0.00"
                min="0"
                step="0.01"
            >

        </td>

        <td>

            <input
                type="text"
                class="table-input readonly-input amount"
                placeholder="0.00"
                value="0.00"
                readonly
            >

        </td>

        <td style="text-align:center;">

            <button
                type="button"
                class="btn-delete"
                onclick="deleteItem(this)"
                title="Delete Item"
            >

                <i class="fa-regular fa-trash-can"></i>

            </button>

        </td>

    `;

    tbody.appendChild(row);

    updateRowNumbers();

    attachCalculationEvents(row);
}


/* =========================================================
   DELETE ITEM
========================================================= */

function deleteItem(button) {

    const tbody =
        document.getElementById("itemsBody");

    const rows =
        tbody.querySelectorAll(".item-row");

    if (rows.length === 1) {

        const row = rows[0];

        row.querySelector(".product-name").value = "";

        row.querySelector(".quantity").value = 1;

        row.querySelector(".rate").value = "";

        row.querySelector(".amount").value = "0.00";

    } else {

        button.closest(".item-row").remove();

    }

    updateRowNumbers();

    calculateTotals();
}


/* =========================================================
   ROW NUMBERS
========================================================= */

function updateRowNumbers() {

    const rows =
        document.querySelectorAll(".item-row");

    rows.forEach((row, index) => {

        row.querySelector(".row-number").textContent =
            index + 1;

    });
}


/* =========================================================
   CALCULATE ROW
========================================================= */

function calculateRow(row) {

    const quantity =
        parseFloat(
            row.querySelector(".quantity").value
        ) || 0;

    const rate =
        parseFloat(
            row.querySelector(".rate").value
        ) || 0;

    const amount =
        quantity * rate;

    row.querySelector(".amount").value =
        amount.toFixed(2);

    calculateTotals();
}


/* =========================================================
   CALCULATE TOTALS
========================================================= */

function calculateTotals() {

    const rows =
        document.querySelectorAll(".item-row");

    let subtotal = 0;

    rows.forEach(row => {

        const quantity =
            parseFloat(
                row.querySelector(".quantity").value
            ) || 0;

        const rate =
            parseFloat(
                row.querySelector(".rate").value
            ) || 0;

        const amount =
            quantity * rate;

        row.querySelector(".amount").value =
            amount.toFixed(2);

        subtotal += amount;

    });

    const tax = 0;

    const total =
        subtotal + tax;


    document.getElementById("subtotal").textContent =
        "₹ " + subtotal.toFixed(2);

    document.getElementById("tax").textContent =
        "₹ " + tax.toFixed(2);

    document.getElementById("total").textContent =
        "₹ " + total.toFixed(2);
}


/* =========================================================
   ATTACH EVENTS
========================================================= */

function attachCalculationEvents(row) {

    const quantity =
        row.querySelector(".quantity");

    const rate =
        row.querySelector(".rate");


    quantity.addEventListener(
        "input",
        function() {
            calculateRow(row);
        }
    );


    rate.addEventListener(
        "input",
        function() {
            calculateRow(row);
        }
    );
}


/* =========================================================
   INITIALIZE
========================================================= */

document.querySelectorAll(".item-row").forEach(row => {

    attachCalculationEvents(row);

});


/* =========================================================
   CREATE INVOICE + SAVE DATABASE
========================================================= */

async function createInvoice(event) {

    event.preventDefault();


    const customerName =
        document.getElementById("customerName").value.trim();

    const customerEmail =
        document.getElementById("customerEmail").value.trim();

    const customerPhone =
        document.getElementById("customerPhone").value.trim();

    const customerAddress =
        document.getElementById("customerAddress").value.trim();

    const issueDateInput =
        document.getElementById("issueDate").value;


    const rows =
        document.querySelectorAll(".item-row");


    const items = [];

    rows.forEach(row => {

        const productId =
            parseInt(
                row.querySelector(".product-name").value
            ) || 0;

        const selectedOption =
            row.querySelector(".product-name")
               .options[
                    row.querySelector(".product-name").selectedIndex
                ];

        const productName =
            selectedOption
                ? selectedOption.textContent.trim()
                : "";

        const quantity =
            parseInt(
                row.querySelector(".quantity").value
            ) || 0;

        const rate =
            parseFloat(
                row.querySelector(".rate").value
            ) || 0;


        if (
            productId > 0 &&
            quantity > 0 &&
            rate > 0
        ) {

            items.push({

                product_id: productId,

                product_name: productName,

                quantity: quantity,

                rate: rate

            });

        }

    });


    if (items.length === 0) {

        alert(
            "Please add at least one product/service with quantity and rate."
        );

        return;
    }


    calculateTotals();


    /* =====================================================
       BUTTON LOADING
    ===================================================== */

    const button =
        document.getElementById("createInvoiceButton");

    const originalButton =
        button.innerHTML;

    button.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';

    button.disabled = true;


    try {

        const formData =
            new FormData();

        formData.append(
            "action",
            "create_invoice"
        );

        formData.append(
            "customer_name",
            customerName
        );

        formData.append(
            "customer_email",
            customerEmail
        );

        formData.append(
            "customer_phone",
            customerPhone
        );

        formData.append(
            "customer_address",
            customerAddress
        );

        formData.append(
            "issue_date",
            issueDateInput
        );

        formData.append(
            "items",
            JSON.stringify(items)
        );


        const response =
            await fetch(
                "Create_Invoice.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const result =
            await response.json();


        if (!result.success) {

            alert(result.message);

            button.innerHTML =
                originalButton;

            button.disabled = false;

            return;
        }


        /* =================================================
           SHOW PREVIEW
        ================================================= */


        document.getElementById(
            "previewCustomerName"
        ).textContent =
            customerName;


        document.getElementById(
            "previewCustomerEmail"
        ).textContent =
            customerEmail;


        document.getElementById(
            "previewCustomerPhone"
        ).textContent =
            customerPhone;


        document.getElementById(
            "previewCustomerAddress"
        ).textContent =
            customerAddress;


        document.getElementById(
            "previewInvoiceNumber"
        ).textContent =
            result.invoice_number;


        document.getElementById(
            "previewIssueDate"
        ).textContent =
            result.issue_date;


        /* =================================================
           PREVIEW ITEMS
        ================================================= */

        const previewBody =
            document.getElementById(
                "previewItemsBody"
            );

        previewBody.innerHTML = "";


        let previewIndex = 1;


        items.forEach(item => {

            const amount =
                item.quantity * item.rate;


            const previewRow =
                document.createElement("tr");


            previewRow.innerHTML = `

                <td>
                    ${previewIndex}
                </td>

                <td>
                    ${escapeHtml(item.product_name)}
                </td>

                <td>
                    ${item.quantity}
                </td>

                <td>
                    ₹ ${item.rate.toFixed(2)}
                </td>

                <td>
                    ₹ ${amount.toFixed(2)}
                </td>

            `;


            previewBody.appendChild(
                previewRow
            );


            previewIndex++;

        });


        /* =================================================
           PREVIEW TOTALS
        ================================================= */

        document.getElementById(
            "previewSubtotal"
        ).textContent =
            "₹ " + result.subtotal;


        document.getElementById(
            "previewTax"
        ).textContent =
            "₹ " + result.tax;


        document.getElementById(
            "previewTotal"
        ).textContent =
            "₹ " + result.total;


        /* =================================================
           SHOW PREVIEW
        ================================================= */

        document.getElementById(
            "invoicePreview"
        ).classList.add("show");


        document.getElementById(
            "invoicePreview"
        ).scrollIntoView({
            behavior: "smooth"
        });


        alert(
            "Invoice created successfully and saved to database."
        );


    } catch (error) {

        console.error(error);

        alert(
            "Something went wrong while creating the invoice."
        );

    }


    button.innerHTML =
        originalButton;

    button.disabled = false;

}


/* =========================================================
   PRINT
========================================================= */

function printInvoice() {

    window.print();

}


/* =========================================================
   DOWNLOAD PDF
========================================================= */

async function downloadInvoice() {

    const { jsPDF } = window.jspdf;


    const preview =
        document.getElementById(
            "invoicePreview"
        );


    if (!preview.classList.contains("show")) {

        alert(
            "Please create the invoice first."
        );

        return;
    }


    const button =
        document.querySelector(
            ".btn-download"
        );


    const originalText =
        button.innerHTML;


    button.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Creating PDF...';

    button.disabled = true;


    try {

        const canvas =
            await html2canvas(
                preview,
                {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: "#ffffff"
                }
            );


        const imgData =
            canvas.toDataURL(
                "image/png"
            );


        const pdf =
            new jsPDF(
                "p",
                "mm",
                "a4"
            );


        const pageWidth =
            pdf.internal.pageSize.getWidth();

        const pageHeight =
            pdf.internal.pageSize.getHeight();


        const margin = 10;


        const usableWidth =
            pageWidth -
            (margin * 2);


        const imageHeight =
            (canvas.height *
                usableWidth) /
            canvas.width;


        let heightLeft =
            imageHeight;

        let position =
            margin;


        pdf.addImage(
            imgData,
            "PNG",
            margin,
            position,
            usableWidth,
            imageHeight
        );


        heightLeft -=
            pageHeight -
            (margin * 2);


        while (heightLeft > 0) {

            position =
                heightLeft -
                imageHeight +
                margin;


            pdf.addPage();


            pdf.addImage(
                imgData,
                "PNG",
                margin,
                position,
                usableWidth,
                imageHeight
            );


            heightLeft -=
                pageHeight -
                (margin * 2);

        }


        const invoiceNumber =
            document.getElementById(
                "previewInvoiceNumber"
            ).textContent ||
            "Invoice";


        pdf.save(
            invoiceNumber + ".pdf"
        );


    } catch (error) {

        console.error(error);

        alert(
            "Unable to download invoice. Please try again."
        );

    }


    button.innerHTML =
        originalText;

    button.disabled = false;

}


/* =========================================================
   RESET
========================================================= */

function resetInvoice() {

    setTimeout(function() {

        const tbody =
            document.getElementById(
                "itemsBody"
            );


        const rows =
            tbody.querySelectorAll(
                ".item-row"
            );


        rows.forEach(
            (row, index) => {

                if (index > 0) {

                    row.remove();

                }

            }
        );


        const firstRow =
            tbody.querySelector(
                ".item-row"
            );


        if (firstRow) {

            firstRow.querySelector(
                ".product-name"
            ).value = "";


            firstRow.querySelector(
                ".quantity"
            ).value = 1;


            firstRow.querySelector(
                ".rate"
            ).value = "";


            firstRow.querySelector(
                ".amount"
            ).value = "0.00";

        }


        updateRowNumbers();

        calculateTotals();


        document.getElementById(
            "invoicePreview"
        ).classList.remove(
            "show"
        );


    }, 50);

}


/* =========================================================
   HTML ESCAPE
========================================================= */

function escapeHtml(text) {

    const div =
        document.createElement(
            "div"
        );

    div.textContent =
        text;

    return div.innerHTML;

}


/* =========================================================
   INITIAL CALCULATION
========================================================= */

calculateTotals();

</script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

</body>
</html>