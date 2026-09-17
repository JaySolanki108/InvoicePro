<?php
session_start();
require_once 'config.php';

/* =========================================================
   LOGIN CHECK
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

/* =========================================================
   USER DETAILS
========================================================= */
$userName = "User";

$stmtUser = $conn->prepare("SELECT company_name FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userResult = $stmtUser->get_result();

if ($userResult->num_rows > 0) {
    $userRow = $userResult->fetch_assoc();
    $userName = $userRow['company_name'];
}

$stmtUser->close();

/* =========================================================
   DELETE PRODUCT
========================================================= */
$deleteMessage = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $deleteId = (int)$_GET['delete'];

    $stmtDelete = $conn->prepare(
        "DELETE FROM products WHERE id = ? AND user_id = ?"
    );
    $stmtDelete->bind_param("ii", $deleteId, $userId);

    if ($stmtDelete->execute()) {
        $deleteMessage = "Product deleted successfully.";
    }

    $stmtDelete->close();

    header("Location: Products.php?deleted=1");
    exit;
}

/* =========================================================
   ADD PRODUCT
========================================================= */
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {

    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock'] ?? '';

    if ($productName === '') {
        $error = "Product name is required.";
    } elseif ($price === '' || !is_numeric($price) || $price < 0) {
        $error = "Please enter a valid price.";
    } elseif ($stock === '' || !is_numeric($stock) || $stock < 0 || floor($stock) != $stock) {
        $error = "Please enter a valid stock quantity.";
    } else {

        $price = (float)$price;
        $stock = (int)$stock;

        $stmtAdd = $conn->prepare(
            "INSERT INTO products
            (user_id, product_name, description, price, stock)
            VALUES (?, ?, ?, ?, ?)"
        );

        $stmtAdd->bind_param(
            "issdi",
            $userId,
            $productName,
            $description,
            $price,
            $stock
        );

        if ($stmtAdd->execute()) {
            $stmtAdd->close();

            header("Location: Products.php?added=1");
            exit;
        } else {
            $error = "Unable to add product. Please try again.";
        }

        $stmtAdd->close();
    }
}

/* =========================================================
   EDIT PRODUCT
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_product'])) {

    $productId = (int)($_POST['product_id'] ?? 0);
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock'] ?? '';

    if ($productId <= 0) {
        $error = "Invalid product.";
    } elseif ($productName === '') {
        $error = "Product name is required.";
    } elseif ($price === '' || !is_numeric($price) || $price < 0) {
        $error = "Please enter a valid price.";
    } elseif ($stock === '' || !is_numeric($stock) || $stock < 0 || floor($stock) != $stock) {
        $error = "Please enter a valid stock quantity.";
    } else {

        $price = (float)$price;
        $stock = (int)$stock;

        $stmtEdit = $conn->prepare(
            "UPDATE products
             SET product_name = ?, description = ?, price = ?, stock = ?
             WHERE id = ? AND user_id = ?"
        );

        $stmtEdit->bind_param(
            "ssdi ii",
            $productName,
            $description,
            $price,
            $stock,
            $productId,
            $userId
        );

        /*
           Correct bind_param types:
           s = product name
           s = description
           d = price
           i = stock
           i = product id
           i = user id
        */
        $stmtEdit->close();
    }
}

/* =========================================================
   FIX EDIT QUERY
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_product'])) {

    $productId = (int)($_POST['product_id'] ?? 0);
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock'] ?? '';

    if ($productId <= 0) {
        $error = "Invalid product.";
    } elseif ($productName === '') {
        $error = "Product name is required.";
    } elseif ($price === '' || !is_numeric($price) || $price < 0) {
        $error = "Please enter a valid price.";
    } elseif ($stock === '' || !is_numeric($stock) || $stock < 0 || floor($stock) != $stock) {
        $error = "Please enter a valid stock quantity.";
    } else {

        $price = (float)$price;
        $stock = (int)$stock;

        $stmtEdit = $conn->prepare(
            "UPDATE products
             SET product_name = ?, description = ?, price = ?, stock = ?
             WHERE id = ? AND user_id = ?"
        );

        $stmtEdit->bind_param(
            "ssdiii",
            $productName,
            $description,
            $price,
            $stock,
            $productId,
            $userId
        );

        if ($stmtEdit->execute()) {
            header("Location: Products.php?updated=1");
            exit;
        } else {
            $error = "Unable to update product.";
        }

        $stmtEdit->close();
    }
}

/* =========================================================
   EDIT PRODUCT DATA
========================================================= */
$editProduct = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmtEditData = $conn->prepare(
        "SELECT id, product_name, description, price, stock
         FROM products
         WHERE id = ? AND user_id = ?"
    );

    $stmtEditData->bind_param("ii", $editId, $userId);
    $stmtEditData->execute();

    $editResult = $stmtEditData->get_result();

    if ($editResult->num_rows > 0) {
        $editProduct = $editResult->fetch_assoc();
    }

    $stmtEditData->close();
}

/* =========================================================
   FETCH PRODUCTS
========================================================= */
$products = [];

$stmtProducts = $conn->prepare(
    "SELECT id, product_name, description, price, stock
     FROM products
     WHERE user_id = ?
     ORDER BY id DESC"
);

$stmtProducts->bind_param("i", $userId);
$stmtProducts->execute();

$productResult = $stmtProducts->get_result();

while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
}

$stmtProducts->close();

/* =========================================================
   PRODUCT STATS
========================================================= */
$totalProducts = count($products);
$inStock = 0;
$lowStock = 0;
$outOfStock = 0;

foreach ($products as $product) {

    $stock = (int)$product['stock'];

    if ($stock <= 0) {
        $outOfStock++;
    } elseif ($stock <= 5) {
        $lowStock++;
    } else {
        $inStock++;
    }
}

/* =========================================================
   MESSAGES
========================================================= */
if (isset($_GET['added'])) {
    $success = "Product added successfully.";
}

if (isset($_GET['updated'])) {
    $success = "Product updated successfully.";
}

if (isset($_GET['deleted'])) {
    $success = "Product deleted successfully.";
}

/* =========================================================
   HELPER FUNCTION
========================================================= */
function productStatus($stock) {

    if ($stock <= 0) {
        return [
            'text' => 'Out of Stock',
            'class' => 'Out-of-Stock'
        ];
    }

    if ($stock <= 5) {
        return [
            'text' => 'Low Stock',
            'class' => 'Low-Stock'
        ];
    }

    return [
        'text' => 'In Stock',
        'class' => 'In-Stock'
    ];
}

function productIcon($name) {

    $name = strtolower($name);

    if (strpos($name, 'laptop') !== false || strpos($name, 'computer') !== false) {
        return 'fa-laptop';
    }

    if (strpos($name, 'mouse') !== false) {
        return 'fa-computer-mouse';
    }

    if (strpos($name, 'keyboard') !== false) {
        return 'fa-keyboard';
    }

    if (strpos($name, 'monitor') !== false || strpos($name, 'screen') !== false) {
        return 'fa-desktop';
    }

    if (strpos($name, 'headphone') !== false || strpos($name, 'earphone') !== false) {
        return 'fa-headphones';
    }

    if (strpos($name, 'camera') !== false || strpos($name, 'webcam') !== false) {
        return 'fa-video';
    }

    if (strpos($name, 'chair') !== false) {
        return 'fa-chair';
    }

    if (strpos($name, 'table') !== false) {
        return 'fa-table';
    }

    if (strpos($name, 'book') !== false || strpos($name, 'notebook') !== false) {
        return 'fa-book';
    }

    if (strpos($name, 'pen') !== false || strpos($name, 'pencil') !== false) {
        return 'fa-pen';
    }

    if (strpos($name, 'phone') !== false || strpos($name, 'mobile') !== false) {
        return 'fa-mobile-screen-button';
    }

    if (strpos($name, 'drive') !== false || strpos($name, 'usb') !== false) {
        return 'fa-hard-drive';
    }

    return 'fa-box';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>InvoicePro - Products</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>

:root {
    --bg-body: #f4f7fa;
    --bg-sidebar: #172136;
    --text-dark: #1e293b;
    --text-muted: #64748b;
    --text-light: #94a3b8;
    --primary-btn: #2563eb;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --white: #ffffff;
    --border: #e2e8f0;
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

/* Sidebar */

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
    background-color: var(--primary-btn);
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

/* Main */

.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

/* Header */

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
    background: #f8fafc;
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
}

.notification::after {
    content: '3';
    position: absolute;
    top: -5px;
    right: -5px;
    width: 16px;
    height: 16px;
    background: var(--danger);
    border-radius: 50%;
    border: 2px solid #fff;
    font-size: 10px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    font-size: 14px;
}

.user-profile img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
}

/* Page */

.page-container {
    padding: 24px 32px;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
}

/* Page Header */

.page-header {
    background: var(--white);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title-area {
    display: flex;
    align-items: center;
    gap: 16px;
}

.header-icon {
    width: 48px;
    height: 48px;
    background: #eff6ff;
    color: var(--primary-btn);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.header-text h1 {
    font-size: 20px;
    color: var(--text-dark);
    margin-bottom: 4px;
    font-weight: 700;
}

.header-text p {
    font-size: 13px;
    color: var(--text-muted);
}

.btn-primary {
    background: var(--primary-btn);
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
    text-decoration: none;
}

/* Messages */

.message {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 500;
}

.success-message {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.error-message {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* Stats */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-bottom: 24px;
}

.stat-card {
    padding: 20px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    border: 1px solid transparent;
}

.stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.stat-card p {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
}

.stat-card h2 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
}

.stat-blue {
    background-color: #f0f5ff;
    border-color: #dbeafe;
}

.stat-blue .stat-icon {
    background: #dbeafe;
    color: #3b82f6;
}

.stat-green {
    background-color: #f0fdf4;
    border-color: #dcfce7;
}

.stat-green .stat-icon {
    background: #dcfce7;
    color: #10b981;
}

.stat-yellow {
    background-color: #fefce8;
    border-color: #fef08a;
}

.stat-yellow .stat-icon {
    background: #fef08a;
    color: #eab308;
}

.stat-red {
    background-color: #fef2f2;
    border-color: #fee2e2;
}

.stat-red .stat-icon {
    background: #fee2e2;
    color: #ef4444;
}

/* Table */

.table-card {
    background: var(--white);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
}

.filters-row {
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    border-bottom: 1px solid var(--border);
}

.filter-input {
    position: relative;
    width: 320px;
}

.filter-input i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 14px;
}

.filter-control {
    width: 100%;
    padding: 10px 14px 10px 40px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-dark);
    outline: none;
}

.filter-select {
    position: relative;
    width: 180px;
}

.filter-select select {
    appearance: none;
    background: #fff;
    cursor: pointer;
}

/* Table */

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

th {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
    padding: 14px 24px;
    border-bottom: 1px solid var(--border);
    background: #f8fafc;
}

td {
    font-size: 13px;
    color: var(--text-dark);
    padding: 14px 24px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.product-thumb {
    width: 44px;
    height: 44px;
    background: #0f172a;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #60a5fa;
    font-size: 20px;
    overflow: hidden;
}

.product-name {
    font-weight: 600;
    color: #1e293b;
}

.product-description {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
    max-width: 230px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.category-text {
    color: #4338ca;
    font-weight: 500;
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    text-align: center;
}

.badge.In-Stock {
    background: #dcfce7;
    color: #166534;
}

.badge.Low-Stock {
    background: #fef3c7;
    color: #92400e;
}

.badge.Out-of-Stock {
    background: #fee2e2;
    color: #991b1b;
}

/* Actions */

.actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    border: 1px solid var(--border);
}

.btn-action.edit {
    color: #2563eb;
    background: #fff;
}

.btn-action.edit:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.btn-action.delete {
    color: #ef4444;
    background: #fff;
}

.btn-action.delete:hover {
    background: #fef2f2;
    border-color: #fecaca;
}

/* Empty */

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 42px;
    margin-bottom: 15px;
    color: #cbd5e1;
}

.empty-state h3 {
    color: var(--text-dark);
    font-size: 16px;
    margin-bottom: 6px;
}

.empty-state p {
    font-size: 13px;
}

/* Pagination */

.pagination-row {
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--text-muted);
}

/* Form Modal */

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: 20px;
}

.product-form-card {
    width: 100%;
    max-width: 520px;
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 20px 60px rgba(15,23,42,0.2);
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.form-header h2 {
    font-size: 20px;
    color: var(--text-dark);
}

.close-btn {
    width: 34px;
    height: 34px;
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 8px;
    cursor: pointer;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 7px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    font-size: 13px;
    color: var(--text-dark);
}

.form-group textarea {
    min-height: 90px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

.btn-secondary {
    padding: 11px 18px;
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 8px;
    color: var(--text-dark);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}

/* Responsive */

@media(max-width: 1000px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .page-container {
        padding: 20px;
    }

    .header {
        padding: 16px 20px;
    }

    .search-bar {
        width: 250px;
    }

    table {
        min-width: 900px;
    }

    .table-card {
        overflow-x: auto;
    }
}

</style>

</head>

<body>

<!-- SIDEBAR -->

<div class="sidebar">

    <div class="sidebar-logo">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        InvoicePro
    </div>

    <div class="nav-menu">

        <a href="Dashboard.php" class="nav-item">
            <i class="fa-solid fa-house"></i>
            Home
        </a>

        <a href="Create_Invoice.php" class="nav-item">
            <i class="fa-solid fa-file-circle-plus"></i>
            Create Invoice
        </a>

        <a href="History_Invoice.php" class="nav-item">
            <i class="fa-solid fa-file-invoice"></i>
            Invoice History
        </a>

        <a href="Create_Bill.php" class="nav-item">
            <i class="fa-solid fa-file-circle-plus"></i>
            Create Bill
        </a>

        <a href="Bill_History.php" class="nav-item">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Bill History
        </a>

        <a href="Products.php" class="nav-item active">
            <i class="fa-solid fa-box-open"></i>
            Products
        </a>

        <a href="Profile.php" class="nav-item">
            <i class="fa-regular fa-user"></i>
            Profile
        </a>

    </div>

    <a href="Login.php" class="logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        Logout
    </a>

</div>


<!-- MAIN CONTENT -->

<div class="main-content">

    <!-- HEADER -->

    <div class="header">

        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="globalSearch" placeholder="Search anything...">
        </div>

        <div class="header-right">

            <div class="notification">
                <i class="fa-regular fa-bell"></i>
            </div>

            <div class="user-profile">

                <img
                    src="https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=1d4ed8&color=fff"
                    alt="User"
                >

                <?php echo htmlspecialchars($userName); ?>

                <i
                    class="fa-solid fa-chevron-down"
                    style="font-size:12px;color:#64748b;"
                ></i>

            </div>

        </div>

    </div>


    <!-- PAGE -->

    <div class="page-container">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div class="header-title-area">

                <div class="header-icon">
                    <i class="fa-solid fa-cube"></i>
                </div>

                <div class="header-text">

                    <h1>Products</h1>

                    <p>
                        Manage your product list here. Add, edit or remove products.
                    </p>

                </div>

            </div>

            <a href="Products.php?add=1" class="btn-primary">
                <i class="fa-solid fa-plus"></i>
                Add Product
            </a>

        </div>


        <!-- MESSAGES -->

        <?php if ($success): ?>

            <div class="message success-message">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="message error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <!-- STATS -->

        <div class="stats-grid">

            <div class="stat-card stat-blue">

                <div class="stat-icon">
                    <i class="fa-solid fa-cube"></i>
                </div>

                <p>Total Products</p>

                <h2>
                    <?php echo $totalProducts; ?>
                </h2>

            </div>


            <div class="stat-card stat-green">

                <div class="stat-icon">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>

                <p>In Stock</p>

                <h2>
                    <?php echo $inStock; ?>
                </h2>

            </div>


            <div class="stat-card stat-yellow">

                <div class="stat-icon">
                    <i class="fa-solid fa-box"></i>
                </div>

                <p>Low Stock</p>

                <h2>
                    <?php echo $lowStock; ?>
                </h2>

            </div>


            <div class="stat-card stat-red">

                <div class="stat-icon">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <p>Out of Stock</p>

                <h2>
                    <?php echo $outOfStock; ?>
                </h2>

            </div>

        </div>


        <!-- TABLE -->

        <div class="table-card">

            <!-- FILTERS -->

            <div class="filters-row">

                <div class="filter-input">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="productSearch"
                        class="filter-control"
                        placeholder="Search by product name..."
                    >

                </div>


                <div class="filter-select">

                    <i
                        class="fa-solid fa-filter"
                        style="
                        position:absolute;
                        left:14px;
                        top:50%;
                        transform:translateY(-50%);
                        color:var(--text-muted);
                        font-size:14px;
                        pointer-events:none;
                        "
                    ></i>

                    <select
                        id="statusFilter"
                        class="filter-control"
                    >

                        <option value="all">
                            All Status
                        </option>

                        <option value="in">
                            In Stock
                        </option>

                        <option value="low">
                            Low Stock
                        </option>

                        <option value="out">
                            Out of Stock
                        </option>

                    </select>

                </div>

            </div>


            <!-- PRODUCT TABLE -->

            <?php if ($totalProducts > 0): ?>

            <table id="productsTable">

                <thead>

                    <tr>

                        <th style="width:50px;">
                            #
                        </th>

                        <th>
                            Product Name
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Price (₹)
                        </th>

                        <th>
                            Stock
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

                <?php foreach ($products as $index => $item): ?>

                    <?php

                    $status = productStatus((int)$item['stock']);

                    $icon = productIcon($item['product_name']);

                    ?>

                    <tr
                        data-name="<?php echo strtolower(htmlspecialchars($item['product_name'])); ?>"
                        data-status="<?php echo $status['class']; ?>"
                    >

                        <td style="color:#64748b;font-weight:500;">
                            <?php echo $index + 1; ?>
                        </td>


                        <td>

                            <div class="product-info">

                                <div class="product-thumb">

                                    <i class="fa-solid <?php echo $icon; ?>"></i>

                                </div>

                                <div>

                                    <div class="product-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $item['product_name']
                                        );
                                        ?>

                                    </div>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?php if (!empty($item['description'])): ?>

                                <div class="product-description"
                                     title="<?php echo htmlspecialchars($item['description']); ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $item['description']
                                    );
                                    ?>

                                </div>

                            <?php else: ?>

                                <span style="color:#94a3b8;">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>


                        <td style="font-weight:600;color:#1e293b;">

                            ₹ <?php
                            echo number_format(
                                (float)$item['price'],
                                2
                            );
                            ?>

                        </td>


                        <td style="font-weight:500;color:#475569;">

                            <?php echo (int)$item['stock']; ?>

                        </td>


                        <td>

                            <span
                                class="badge <?php echo $status['class']; ?>"
                            >

                                <?php
                                echo $status['text'];
                                ?>

                            </span>

                        </td>


                        <td>

                            <div class="actions">

                                <a
                                    href="Products.php?edit=<?php echo (int)$item['id']; ?>"
                                    class="btn-action edit"
                                    title="Edit Product"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                </a>


                                <a
                                    href="Products.php?delete=<?php echo (int)$item['id']; ?>"
                                    class="btn-action delete"
                                    title="Delete Product"
                                    onclick="return confirm('Are you sure you want to delete this product?');"
                                >

                                    <i class="fa-regular fa-trash-can"></i>

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>


            <div class="pagination-row">

                <div id="productCount">

                    Showing
                    <?php echo $totalProducts; ?>
                    product<?php echo $totalProducts != 1 ? 's' : ''; ?>

                </div>

            </div>


            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-box-open"></i>

                    <h3>
                        No Products Yet
                    </h3>

                    <p>
                        Add your first product to start managing your inventory.
                    </p>

                    <br>

                    <a
                        href="Products.php?add=1"
                        class="btn-primary"
                        style="display:inline-flex;"
                    >

                        <i class="fa-solid fa-plus"></i>
                        Add Product

                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =========================================================
     ADD PRODUCT MODAL
========================================================= -->

<?php if (isset($_GET['add'])): ?>

<div class="modal-overlay">

    <div class="product-form-card">

        <div class="form-header">

            <h2>
                Add Product
            </h2>

            <a
                href="Products.php"
                class="close-btn"
            >
                <i class="fa-solid fa-xmark"></i>
            </a>

        </div>


        <form method="POST">

            <div class="form-group">

                <label>
                    Product Name *
                </label>

                <input
                    type="text"
                    name="product_name"
                    placeholder="Enter product name"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Enter product description"
                ></textarea>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>
                        Price (₹) *
                    </label>

                    <input
                        type="number"
                        name="price"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Stock *
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        step="1"
                        placeholder="0"
                        required
                    >

                </div>

            </div>


            <div class="form-actions">

                <a
                    href="Products.php"
                    class="btn-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    name="add_product"
                    class="btn-primary"
                >

                    <i class="fa-solid fa-plus"></i>
                    Add Product

                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<!-- =========================================================
     EDIT PRODUCT MODAL
========================================================= -->

<?php if ($editProduct): ?>

<div class="modal-overlay">

    <div class="product-form-card">

        <div class="form-header">

            <h2>
                Edit Product
            </h2>

            <a
                href="Products.php"
                class="close-btn"
            >
                <i class="fa-solid fa-xmark"></i>
            </a>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="product_id"
                value="<?php echo (int)$editProduct['id']; ?>"
            >


            <div class="form-group">

                <label>
                    Product Name *
                </label>

                <input
                    type="text"
                    name="product_name"
                    value="<?php echo htmlspecialchars($editProduct['product_name']); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                ><?php echo htmlspecialchars($editProduct['description']); ?></textarea>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>
                        Price (₹) *
                    </label>

                    <input
                        type="number"
                        name="price"
                        step="0.01"
                        min="0"
                        value="<?php echo htmlspecialchars($editProduct['price']); ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Stock *
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        step="1"
                        value="<?php echo (int)$editProduct['stock']; ?>"
                        required
                    >

                </div>

            </div>


            <div class="form-actions">

                <a
                    href="Products.php"
                    class="btn-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    name="edit_product"
                    class="btn-primary"
                >

                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script>

/* =========================================================
   PRODUCT SEARCH
========================================================= */

const productSearch = document.getElementById("productSearch");
const statusFilter = document.getElementById("statusFilter");

function filterProducts() {

    const searchValue =
        productSearch ?
        productSearch.value.toLowerCase().trim() :
        "";

    const statusValue =
        statusFilter ?
        statusFilter.value :
        "all";

    const rows =
        document.querySelectorAll("#productsTable tbody tr");

    let visibleCount = 0;

    rows.forEach(row => {

        const name =
            row.getAttribute("data-name") || "";

        const status =
            row.getAttribute("data-status") || "";

        const searchMatch =
            name.includes(searchValue);

        let statusMatch = true;

        if (statusValue === "in") {
            statusMatch = status === "In-Stock";
        }

        if (statusValue === "low") {
            statusMatch = status === "Low-Stock";
        }

        if (statusValue === "out") {
            statusMatch = status === "Out-of-Stock";
        }

        if (searchMatch && statusMatch) {

            row.style.display = "";
            visibleCount++;

        } else {

            row.style.display = "none";

        }

    });

    const countElement =
        document.getElementById("productCount");

    if (countElement) {

        countElement.textContent =
            "Showing " +
            visibleCount +
            " product" +
            (visibleCount !== 1 ? "s" : "");

    }

}

if (productSearch) {
    productSearch.addEventListener(
        "input",
        filterProducts
    );
}

if (statusFilter) {
    statusFilter.addEventListener(
        "change",
        filterProducts
    );
}


/* =========================================================
   GLOBAL SEARCH
========================================================= */

const globalSearch =
    document.getElementById("globalSearch");

if (globalSearch) {

    globalSearch.addEventListener(
        "input",
        function() {

            if (productSearch) {

                productSearch.value =
                    this.value;

                filterProducts();

            }

        }
    );

}

</script>

</body>
</html>