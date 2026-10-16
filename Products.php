<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

require_once "config.php";

$user_id = (int)$_SESSION['user_id'];
$message = "";
$messageType = "";

/* =========================
   DELETE PRODUCT
========================= */
if (isset($_GET['delete'])) {

    $delete_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM products
        WHERE id = ? AND user_id = ?
    ");

    $stmt->bind_param("ii", $delete_id, $user_id);

    if ($stmt->execute()) {
        $message = "Product deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Unable to delete product.";
        $messageType = "error";
    }

    $stmt->close();

    header(
        "Location: Products.php?msg=" .
        urlencode($message) .
        "&type=" .
        $messageType
    );
    exit();
}

/* =========================
   FLASH MESSAGE
========================= */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? "success";
}

/* =========================
   ADD PRODUCT
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {

    $product_name = trim($_POST['product_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($product_name === "") {

        $message = "Product name is required.";
        $messageType = "error";

    } elseif ($price < 0) {

        $message = "Price cannot be negative.";
        $messageType = "error";

    } elseif ($stock < 0) {

        $message = "Stock cannot be negative.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO products
            (product_name, description, price, stock, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssdii",
            $product_name,
            $description,
            $price,
            $stock,
            $user_id
        );

        if ($stmt->execute()) {
            $message = "Product added successfully.";
            $messageType = "success";
        } else {
            $message = "Unable to add product.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

/* =========================
   EDIT PRODUCT
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_product'])) {

    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $product_name = trim($_POST['product_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($product_name === "") {

        $message = "Product name is required.";
        $messageType = "error";

    } elseif ($price < 0) {

        $message = "Price cannot be negative.";
        $messageType = "error";

    } elseif ($stock < 0) {

        $message = "Stock cannot be negative.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare("
            UPDATE products
            SET product_name = ?,
                description = ?,
                price = ?,
                stock = ?
            WHERE id = ? AND user_id = ?
        ");

        $stmt->bind_param(
            "ssdiii",
            $product_name,
            $description,
            $price,
            $stock,
            $edit_id,
            $user_id
        );

        if ($stmt->execute()) {
            $message = "Product updated successfully.";
            $messageType = "success";
        } else {
            $message = "Unable to update product.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

/* =========================
   FETCH EDIT PRODUCT
========================= */
$editProduct = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT id, product_name, description, price, stock
        FROM products
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $editProduct = $result->fetch_assoc();
    }

    $stmt->close();
}

/* =========================
   FETCH PRODUCTS
========================= */
$products = [];

$stmt = $conn->prepare("
    SELECT id, product_name, description, price, stock
    FROM products
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

/* =========================
   PRODUCT SUMMARY
========================= */
$totalProducts = count($products);
$inStockCount = 0;
$lowOutCount = 0;
$inventoryValue = 0;

foreach ($products as $product) {

    $stock = (int)$product['stock'];
    $price = (float)$product['price'];

    if ($stock > 5) {
        $inStockCount++;
    } else {
        $lowOutCount++;
    }

    $inventoryValue += $price * $stock;
}

/* =========================
   PRODUCT STATUS
========================= */
function productStatus($stock)
{
    $stock = (int)$stock;

    if ($stock <= 0) {
        return [
            "label" => "Out of Stock",
            "class" => "out",
            "filter" => "out"
        ];
    }

    if ($stock <= 5) {
        return [
            "label" => "Low Stock",
            "class" => "low",
            "filter" => "low"
        ];
    }

    return [
        "label" => "In Stock",
        "class" => "in",
        "filter" => "in"
    ];
}

/* =========================
   PRODUCT ICON
========================= */
function productIcon($name)
{
    $name = strtolower($name);

    if (
        strpos($name, "laptop") !== false ||
        strpos($name, "computer") !== false ||
        strpos($name, "pc") !== false
    ) {
        return "fa-laptop";
    }

    if (
        strpos($name, "phone") !== false ||
        strpos($name, "mobile") !== false
    ) {
        return "fa-mobile-screen-button";
    }

    if (strpos($name, "keyboard") !== false) {
        return "fa-keyboard";
    }

    if (strpos($name, "mouse") !== false) {
        return "fa-computer-mouse";
    }

    if (
        strpos($name, "headphone") !== false ||
        strpos($name, "earphone") !== false ||
        strpos($name, "headset") !== false
    ) {
        return "fa-headphones";
    }

    if (strpos($name, "camera") !== false) {
        return "fa-camera";
    }

    if (strpos($name, "watch") !== false) {
        return "fa-clock";
    }

    if (strpos($name, "printer") !== false) {
        return "fa-print";
    }

    if (strpos($name, "book") !== false) {
        return "fa-book";
    }

    if (
        strpos($name, "chair") !== false ||
        strpos($name, "table") !== false ||
        strpos($name, "furniture") !== false
    ) {
        return "fa-chair";
    }

    return "fa-box";
}

$showAddModal = isset($_GET['add']);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Products - Invoice Pro</title>

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

:root {
    --black:#080808;
    --black-soft:#0d0d0d;
    --black-card:#111111;
    --black-hover:#151515;

    --gold:#d4af37;
    --gold-light:#f2d675;
    --gold-soft:rgba(212,175,55,0.08);
    --gold-glow:rgba(212,175,55,0.16);
    --gold-border:rgba(212,175,55,0.24);
    --gold-border-hover:rgba(212,175,55,0.55);

    --white:#fff;
    --text:#eee;
    --muted:#888;

    --success:#22c55e;
    --warning:#eab308;
    --danger:#ef4444;
}

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html {
    scroll-behavior:smooth;
}

body {
    font-family:"Inter",sans-serif;
    background:
        radial-gradient(
            circle at 50% -10%,
            rgba(212,175,55,0.055),
            transparent 34%
        ),
        var(--black);
    color:var(--text);
    min-height:100vh;
}

a {
    text-decoration:none;
    color:inherit;
}

/* =========================
   NAVBAR
========================= */

.topbar {
    height:68px;
    width:100%;
    padding:0 32px;

    display:flex;
    align-items:center;
    justify-content:space-between;

    background:rgba(8,8,8,0.96);
    border-bottom:1px solid var(--gold-border);

    position:sticky;
    top:0;
    z-index:1000;

    backdrop-filter:blur(12px);
}

.brand {
    display:flex;
    align-items:center;
    gap:10px;

    color:var(--white);
    font-size:17px;
    font-weight:700;
}

.brand i {
    width:34px;
    height:34px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:9px;

    color:var(--black);
    background:var(--gold);

    font-size:15px;

    box-shadow:
        0 0 20px rgba(212,175,55,0.12);
}

.nav-right {
    display:flex;
    align-items:center;
    gap:8px;
}

.nav-link {
    height:39px;
    padding:0 12px;

    border-radius:9px;

    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;

    color:var(--muted);

    border:1px solid transparent;
    background:transparent;

    font-size:11px;
    font-weight:600;

    transition:all .25s ease;
}

.nav-link i {
    color:var(--gold);
    font-size:12px;
}

.nav-link:hover {
    color:var(--white);
    background:var(--gold-soft);
    border-color:var(--gold-border);

    box-shadow:0 0 18px rgba(212,175,55,0.06);
}

.nav-icon {
    width:39px;
    height:39px;

    border-radius:9px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    color:var(--gold);

    border:1px solid var(--gold-border);
    background:rgba(212,175,55,0.06);

    transition:all .25s ease;

    cursor:pointer;
}

.nav-icon:hover {
    color:var(--black);
    background:var(--gold);
    border-color:var(--gold);

    transform:translateY(-1px);

    box-shadow:0 5px 20px rgba(212,175,55,0.22);
}

/* =========================
   MAIN
========================= */

.container {
    width:90%;
    max-width:1150px;
    margin:0 auto;

    padding:40px 0 60px;
}

/* =========================
   PAGE HEADER
========================= */

.page-header {
    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:20px;
    margin-bottom:25px;
}

.page-title {
    display:flex;
    align-items:center;
    gap:14px;
}

.page-title-icon {
    width:46px;
    height:46px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:12px;

    color:var(--gold);
    background:var(--gold-soft);
    border:1px solid var(--gold-border);

    font-size:17px;

    box-shadow:
        0 0 24px rgba(212,175,55,0.06);
}

.page-title-text h1 {
    color:var(--white);
    font-size:24px;
    margin-bottom:5px;
}

.page-title-text p {
    color:var(--muted);
    font-size:12px;
}

/* =========================
   ADD BUTTON
========================= */

.add-btn {
    height:40px;
    padding:0 16px;

    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;

    border:1px solid var(--gold);
    border-radius:9px;

    background:var(--gold);
    color:var(--black);

    font-family:inherit;
    font-size:11px;
    font-weight:700;

    cursor:pointer;

    transition:all .25s ease;

    box-shadow:
        0 5px 20px rgba(212,175,55,0.08);
}

.add-btn:hover {
    background:var(--gold-light);
    border-color:var(--gold-light);

    transform:translateY(-1px);

    box-shadow:
        0 8px 25px rgba(212,175,55,0.20);
}

/* =========================
   FLASH
========================= */

.flash {
    padding:13px 15px;

    border-radius:10px;
    margin-bottom:20px;

    display:flex;
    align-items:center;
    gap:9px;

    font-size:12px;
    font-weight:600;
}

.flash.success {
    color:#86efac;
    background:rgba(34,197,94,0.08);
    border:1px solid rgba(34,197,94,0.20);
}

.flash.error {
    color:#fca5a5;
    background:rgba(239,68,68,0.08);
    border:1px solid rgba(239,68,68,0.20);
}

/* =========================
   SUMMARY
========================= */

.summary-grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;

    margin-bottom:20px;
}

.summary-card {
    position:relative;

    min-height:88px;

    display:flex;
    align-items:center;
    gap:13px;

    padding:17px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.025),
            rgba(255,255,255,0)
        ),
        var(--black-card);

    border:1px solid rgba(255,255,255,0.07);
    border-radius:12px;

    overflow:hidden;

    transition:
        transform .25s ease,
        border-color .25s ease,
        box-shadow .25s ease;
}

.summary-card::before {
    content:"";

    position:absolute;

    top:0;
    left:18px;
    right:18px;

    height:1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(212,175,55,0.45),
            transparent
        );

    opacity:.45;
}

.summary-card:hover {
    transform:translateY(-2px);

    border-color:var(--gold-border-hover);

    box-shadow:
        0 10px 30px rgba(0,0,0,.35),
        0 0 25px rgba(212,175,55,.05);
}

.summary-icon {
    width:42px;
    height:42px;

    flex-shrink:0;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:10px;

    color:var(--gold);

    background:
        linear-gradient(
            145deg,
            rgba(212,175,55,0.13),
            rgba(212,175,55,0.04)
        );

    border:1px solid var(--gold-border);

    box-shadow:
        0 0 18px rgba(212,175,55,0.05);
}

.summary-card:hover .summary-icon {
    box-shadow:
        0 0 22px rgba(212,175,55,0.12);
}

.summary-content {
    min-width:0;
}

.summary-label {
    color:var(--muted);
    font-size:10px;
    font-weight:600;

    margin-bottom:5px;

    text-transform:uppercase;
    letter-spacing:.5px;
}

.summary-value {
    color:var(--white);
    font-size:18px;
    font-weight:700;
}

/* =========================
   PRODUCT CARD
========================= */

.product-card {
    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.018),
            transparent 35%
        ),
        var(--black-card);

    border:1px solid rgba(255,255,255,0.07);
    border-radius:14px;

    overflow:hidden;

    box-shadow:
        0 12px 40px rgba(0,0,0,.18);

    transition:
        border-color .25s ease,
        box-shadow .25s ease;
}

.product-card:hover {
    border-color:rgba(212,175,55,0.20);

    box-shadow:
        0 15px 45px rgba(0,0,0,.25),
        0 0 35px rgba(212,175,55,.025);
}

.card-top {
    padding:20px 22px;

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:18px;

    border-bottom:1px solid rgba(255,255,255,0.06);
}

.card-title h2 {
    color:var(--white);
    font-size:15px;
    margin-bottom:5px;
}

.card-title p {
    color:var(--muted);
    font-size:11px;
}

/* =========================
   CONTROLS
========================= */

.controls {
    display:flex;
    align-items:center;
    gap:9px;
}

.search-box {
    position:relative;
}

.search-box i {
    position:absolute;

    left:12px;
    top:50%;

    transform:translateY(-50%);

    color:#666;
    font-size:12px;

    transition:color .2s ease;
}

.search-box input,
.stock-filter {
    height:39px;

    background:var(--black-soft);

    border:1px solid rgba(255,255,255,0.09);
    border-radius:9px;

    color:var(--text);

    outline:none;

    font-family:inherit;
    font-size:11px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}

.search-box input {
    width:215px;
    padding:0 12px 0 34px;
}

.stock-filter {
    min-width:135px;
    padding:0 11px;

    cursor:pointer;
}

.search-box input:focus,
.stock-filter:focus {
    border-color:var(--gold-border-hover);

    box-shadow:
        0 0 0 3px rgba(212,175,55,0.05);
}

.search-box:focus-within i {
    color:var(--gold);
}

.stock-filter option {
    background:#111;
    color:#eee;
}

/* =========================
   TABLE
========================= */

.table-wrapper {
    width:100%;
    overflow-x:auto;
}

table {
    width:100%;
    border-collapse:collapse;
}

thead {
    background:
        linear-gradient(
            90deg,
            rgba(212,175,55,0.035),
            rgba(212,175,55,0.01),
            rgba(212,175,55,0.035)
        );
}

th {
    text-align:left;

    padding:13px 22px;

    color:#777;

    font-size:10px;
    font-weight:700;

    text-transform:uppercase;
    letter-spacing:.6px;
}

td {
    padding:15px 22px;

    border-top:1px solid rgba(255,255,255,0.055);

    color:#ddd;
    font-size:12px;
}

tbody tr {
    transition:
        background .2s ease,
        box-shadow .2s ease;
}

tbody tr:hover {
    background:rgba(212,175,55,0.025);

    box-shadow:
        inset 2px 0 0 rgba(212,175,55,0.35);
}

/* =========================
   PRODUCT INFO
========================= */

.product-info {
    display:flex;
    align-items:center;
    gap:11px;
}

.product-icon {
    width:37px;
    height:37px;

    flex-shrink:0;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:9px;

    color:var(--gold);

    background:var(--gold-soft);

    border:1px solid var(--gold-border);

    box-shadow:
        0 0 14px rgba(212,175,55,0.04);
}

.product-name {
    color:var(--white);

    font-size:12px;
    font-weight:600;

    margin-bottom:3px;
}

.product-description {
    max-width:260px;

    color:#666;

    font-size:10px;

    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.price {
    color:var(--white);
    font-weight:600;
}

.stock-number {
    color:#ddd;
    font-weight:600;
}

/* =========================
   STATUS
========================= */

.status {
    display:inline-flex;
    align-items:center;
    gap:6px;

    padding:6px 9px;

    border-radius:20px;

    font-size:10px;
    font-weight:700;
}

.status::before {
    content:"";

    width:6px;
    height:6px;

    border-radius:50%;
}

.status.in {
    color:#77e89a;
    background:rgba(34,197,94,0.08);
    border:1px solid rgba(34,197,94,0.10);
}

.status.in::before {
    background:var(--success);
    box-shadow:0 0 7px rgba(34,197,94,.45);
}

.status.low {
    color:#f4d96a;
    background:rgba(234,179,8,0.08);
    border:1px solid rgba(234,179,8,0.10);
}

.status.low::before {
    background:var(--warning);
    box-shadow:0 0 7px rgba(234,179,8,.45);
}

.status.out {
    color:#ff8585;
    background:rgba(239,68,68,0.08);
    border:1px solid rgba(239,68,68,0.10);
}

.status.out::before {
    background:var(--danger);
    box-shadow:0 0 7px rgba(239,68,68,.40);
}

/* =========================
   ACTIONS
========================= */

.actions {
    display:flex;
    align-items:center;
    gap:7px;
}

.action-btn {
    width:32px;
    height:32px;

    display:flex;
    align-items:center;
    justify-content:center;

    border:1px solid rgba(255,255,255,0.09);
    border-radius:8px;

    background:var(--black-soft);
    color:#888;

    cursor:pointer;

    transition:all .22s ease;
}

.action-btn:hover {
    color:var(--gold-light);

    border-color:var(--gold-border-hover);

    background:var(--gold-soft);

    transform:translateY(-1px);

    box-shadow:
        0 5px 15px rgba(212,175,55,0.08);
}

.action-btn.delete:hover {
    color:#ff8585;

    border-color:rgba(239,68,68,.35);

    background:rgba(239,68,68,.08);

    box-shadow:none;
}

/* =========================
   EMPTY STATE
========================= */

.empty-state {
    padding:60px 20px;

    text-align:center;
}

.empty-icon {
    width:58px;
    height:58px;

    margin:0 auto 16px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:13px;

    color:var(--gold);

    background:var(--gold-soft);

    border:1px solid var(--gold-border);

    box-shadow:
        0 0 25px rgba(212,175,55,0.06);

    font-size:21px;
}

.empty-state h3 {
    color:var(--white);
    font-size:15px;
    margin-bottom:7px;
}

.empty-state p {
    color:#666;
    font-size:11px;
    margin-bottom:18px;
}

.no-results {
    display:none;

    padding:45px 20px;

    text-align:center;

    color:#666;
    font-size:12px;
}

/* =========================
   MODAL
========================= */

.modal-overlay {
    position:fixed;
    inset:0;

    background:rgba(0,0,0,.78);

    display:flex;
    align-items:center;
    justify-content:center;

    padding:20px;

    z-index:2000;

    opacity:0;
    visibility:hidden;

    transition:opacity .2s ease;
}

.modal-overlay.active {
    opacity:1;
    visibility:visible;
}

.modal {
    width:100%;
    max-width:520px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.025),
            transparent 40%
        ),
        var(--black-card);

    border:1px solid var(--gold-border);
    border-radius:15px;

    box-shadow:
        0 25px 80px rgba(0,0,0,.65),
        0 0 45px rgba(212,175,55,.05);

    overflow:hidden;

    transform:translateY(12px) scale(.985);

    transition:
        transform .22s ease;
}

.modal-overlay.active .modal {
    transform:translateY(0) scale(1);
}

.modal-header {
    padding:19px 22px;

    display:flex;
    align-items:center;
    justify-content:space-between;

    border-bottom:1px solid rgba(255,255,255,0.07);
}

.modal-header h2 {
    color:var(--white);
    font-size:16px;
}

.close-modal {
    width:32px;
    height:32px;

    display:flex;
    align-items:center;
    justify-content:center;

    border:1px solid rgba(255,255,255,0.09);
    border-radius:8px;

    background:var(--black-soft);
    color:#888;

    cursor:pointer;

    transition:all .2s ease;
}

.close-modal:hover {
    color:var(--gold);
    border-color:var(--gold-border);
    background:var(--gold-soft);
}

.modal-body {
    padding:22px;
}

.form-group {
    margin-bottom:16px;
}

.form-group label {
    display:block;

    color:var(--muted);

    font-size:10px;
    font-weight:600;

    margin-bottom:7px;

    text-transform:uppercase;
    letter-spacing:.5px;
}

.form-group input,
.form-group textarea {
    width:100%;

    background:var(--black-soft);

    border:1px solid rgba(255,255,255,0.09);
    border-radius:8px;

    color:var(--text);

    padding:11px 12px;

    outline:none;

    font-family:inherit;
    font-size:12px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}

.form-group input {
    height:41px;
}

.form-group textarea {
    min-height:85px;
    resize:vertical;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color:var(--gold-border-hover);

    box-shadow:
        0 0 0 3px rgba(212,175,55,0.045);
}

.form-row {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}

.modal-footer {
    padding:16px 22px;

    display:flex;
    justify-content:flex-end;
    gap:9px;

    border-top:1px solid rgba(255,255,255,0.07);
}

.cancel-btn {
    height:39px;

    padding:0 15px;

    border:1px solid rgba(255,255,255,0.09);
    border-radius:8px;

    background:transparent;
    color:var(--muted);

    font-family:inherit;
    font-size:11px;
    font-weight:600;

    cursor:pointer;

    transition:all .2s ease;
}

.cancel-btn:hover {
    color:var(--white);
    border-color:rgba(255,255,255,.18);
}

.submit-btn {
    height:39px;

    padding:0 17px;

    border:1px solid var(--gold);
    border-radius:8px;

    background:var(--gold);
    color:var(--black);

    font-family:inherit;
    font-size:11px;
    font-weight:700;

    cursor:pointer;

    transition:all .2s ease;

    box-shadow:
        0 4px 15px rgba(212,175,55,.08);
}

.submit-btn:hover {
    background:var(--gold-light);
    border-color:var(--gold-light);

    box-shadow:
        0 6px 20px rgba(212,175,55,.16);
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width:900px) {

    .summary-grid {
        grid-template-columns:repeat(2,1fr);
    }

    .card-top {
        align-items:flex-start;
        flex-direction:column;
    }

    .controls {
        width:100%;
    }

    .search-box {
        flex:1;
    }

    .search-box input {
        width:100%;
    }

    .stock-filter {
        flex:0 0 145px;
    }
}

@media(max-width:700px) {

    .topbar {
        height:62px;
        padding:0 16px;
    }

    .brand {
        font-size:14px;
    }

    .brand i {
        width:31px;
        height:31px;
        font-size:13px;
    }

    .nav-right {
        gap:5px;
    }

    .nav-link {
        width:35px;
        height:35px;
        padding:0;
    }

    .nav-link span {
        display:none;
    }

    .nav-icon {
        width:35px;
        height:35px;
    }

    .container {
        width:92%;
        padding:28px 0 45px;
    }

    .page-header {
        align-items:flex-start;
        flex-direction:column;
    }

    .page-title {
        width:100%;
    }

    .page-title-text h1 {
        font-size:21px;
    }

    .add-btn {
        width:100%;
    }

    .summary-grid {
        grid-template-columns:1fr 1fr;
    }

    .controls {
        flex-direction:column;
    }

    .search-box,
    .search-box input,
    .stock-filter {
        width:100%;
        min-width:0;
    }

    .stock-filter {
        flex:auto;
    }

    .form-row {
        grid-template-columns:1fr;
        gap:0;
    }

    th,
    td {
        padding:13px 15px;
    }

}

@media(max-width:430px) {

    .summary-grid {
        grid-template-columns:1fr;
    }

    .summary-card {
        min-height:82px;
    }

    .page-title-icon {
        width:42px;
        height:42px;
    }

    .page-title-text h1 {
        font-size:20px;
    }

    .product-description {
        max-width:150px;
    }

}

</style>

</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<header class="topbar">

    <a href="Dashboard.php" class="brand">

        <i class="fa-solid fa-file-invoice"></i>

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

        <a
            href="History_Invoice.php"
            class="nav-link"
            title="Invoices"
        >
            <i class="fa-solid fa-file-invoice"></i>
            <span>Invoices</span>
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


<!-- =========================
     MAIN
========================= -->

<main class="container">

    <div class="page-header">

        <div class="page-title">

            <div class="page-title-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

            <div class="page-title-text">

                <h1>Products</h1>

                <p>
                    Manage your products, prices and stock.
                </p>

            </div>

        </div>

        <button
            class="add-btn"
            type="button"
            onclick="openAddModal()"
        >
            <i class="fa-solid fa-plus"></i>
            Add Product
        </button>

    </div>


    <!-- =========================
         FLASH MESSAGE
    ========================= -->

    <?php if ($message !== ""): ?>

        <div
            class="flash <?php echo $messageType === 'error' ? 'error' : 'success'; ?>"
        >

            <i
                class="fa-solid
                <?php
                echo $messageType === 'error'
                    ? 'fa-circle-exclamation'
                    : 'fa-circle-check';
                ?>"
            ></i>

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         SUMMARY
    ========================= -->

    <div class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

            <div class="summary-content">

                <div class="summary-label">
                    Total Products
                </div>

                <div class="summary-value">
                    <?php echo $totalProducts; ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div class="summary-content">

                <div class="summary-label">
                    In Stock
                </div>

                <div class="summary-value">
                    <?php echo $inStockCount; ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="summary-content">

                <div class="summary-label">
                    Low / Out
                </div>

                <div class="summary-value">
                    <?php echo $lowOutCount; ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                <i class="fa-solid fa-indian-rupee-sign"></i>
            </div>

            <div class="summary-content">

                <div class="summary-label">
                    Inventory Value
                </div>

                <div class="summary-value">
                    ₹<?php echo number_format($inventoryValue, 2); ?>
                </div>

            </div>

        </div>

    </div>


    <!-- =========================
         PRODUCT LIST
    ========================= -->

    <section class="product-card">

        <div class="card-top">

            <div class="card-title">

                <h2>Product List</h2>

                <p>
                    View and manage all your products.
                </p>

            </div>

            <div class="controls">

                <div class="search-box">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="productSearch"
                        placeholder="Search products..."
                        autocomplete="off"
                    >

                </div>

                <select
                    id="stockFilter"
                    class="stock-filter"
                >

                    <option value="all">
                        All Stock
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


        <?php if (count($products) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Product</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody id="productTableBody">

                    <?php foreach ($products as $product): ?>

                        <?php

                        $status = productStatus($product['stock']);
                        $icon = productIcon($product['product_name']);

                        ?>

                        <tr
                            class="product-row"
                            data-status="<?php echo $status['filter']; ?>"
                        >

                            <td>

                                <div class="product-info">

                                    <div class="product-icon">

                                        <i
                                            class="fa-solid <?php echo $icon; ?>"
                                        ></i>

                                    </div>

                                    <div>

                                        <div class="product-name">

                                            <?php
                                            echo htmlspecialchars(
                                                $product['product_name']
                                            );
                                            ?>

                                        </div>

                                        <?php if (!empty($product['description'])): ?>

                                            <div class="product-description">

                                                <?php
                                                echo htmlspecialchars(
                                                    $product['description']
                                                );
                                                ?>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <span class="price">

                                    ₹<?php
                                    echo number_format(
                                        (float)$product['price'],
                                        2
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span class="stock-number">

                                    <?php
                                    echo (int)$product['stock'];
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="status <?php echo $status['class']; ?>"
                                >

                                    <?php echo $status['label']; ?>

                                </span>

                            </td>


                            <td>

                                <div class="actions">

                                    <button
                                        type="button"
                                        class="action-btn"
                                        title="Edit Product"
                                        onclick="editProduct(
                                            <?php echo (int)$product['id']; ?>,
                                            <?php echo htmlspecialchars(
                                                json_encode($product['product_name']),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>,
                                            <?php echo htmlspecialchars(
                                                json_encode($product['description']),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>,
                                            <?php echo (float)$product['price']; ?>,
                                            <?php echo (int)$product['stock']; ?>
                                        )"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="action-btn delete"
                                        title="Delete Product"
                                        onclick="deleteProduct(
                                            <?php echo (int)$product['id']; ?>
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


            <div
                class="no-results"
                id="noResults"
            >

                <i class="fa-solid fa-magnifying-glass"></i>

                <div style="margin-top:10px;">
                    No products found.
                </div>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-icon">

                    <i class="fa-solid fa-box-open"></i>

                </div>

                <h3>No Products Yet</h3>

                <p>
                    Add your first product to start managing your inventory.
                </p>

                <button
                    class="add-btn"
                    type="button"
                    onclick="openAddModal()"
                >

                    <i class="fa-solid fa-plus"></i>
                    Add Product

                </button>

            </div>

        <?php endif; ?>

    </section>

</main>


<!-- =========================
     ADD PRODUCT MODAL
========================= -->

<div
    class="modal-overlay <?php echo $showAddModal ? 'active' : ''; ?>"
    id="addModal"
>

    <div class="modal">

        <div class="modal-header">

            <h2>Add Product</h2>

            <button
                type="button"
                class="close-modal"
                onclick="closeAddModal()"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>

        <form method="POST">

            <div class="modal-body">

                <div class="form-group">

                    <label>Product Name</label>

                    <input
                        type="text"
                        name="product_name"
                        placeholder="Enter product name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Description</label>

                    <textarea
                        name="description"
                        placeholder="Enter product description"
                    ></textarea>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label>Price</label>

                        <input
                            type="number"
                            name="price"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Stock</label>

                        <input
                            type="number"
                            name="stock"
                            min="0"
                            placeholder="0"
                            required
                        >

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeAddModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="add_product"
                    class="submit-btn"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add Product
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================
     EDIT PRODUCT MODAL
========================= -->

<div
    class="modal-overlay <?php echo $editProduct ? 'active' : ''; ?>"
    id="editModal"
>

    <div class="modal">

        <div class="modal-header">

            <h2>Edit Product</h2>

            <button
                type="button"
                class="close-modal"
                onclick="closeEditModal()"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="edit_id"
                id="edit_id"
                value="<?php
                echo $editProduct
                    ? (int)$editProduct['id']
                    : '';
                ?>"
            >

            <div class="modal-body">

                <div class="form-group">

                    <label>Product Name</label>

                    <input
                        type="text"
                        name="product_name"
                        id="edit_product_name"
                        value="<?php
                        echo $editProduct
                            ? htmlspecialchars(
                                $editProduct['product_name']
                            )
                            : '';
                        ?>"
                        placeholder="Enter product name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Description</label>

                    <textarea
                        name="description"
                        id="edit_description"
                        placeholder="Enter product description"
                    ><?php
                    echo $editProduct
                        ? htmlspecialchars(
                            $editProduct['description']
                        )
                        : '';
                    ?></textarea>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label>Price</label>

                        <input
                            type="number"
                            name="price"
                            id="edit_price"
                            min="0"
                            step="0.01"
                            value="<?php
                            echo $editProduct
                                ? htmlspecialchars(
                                    $editProduct['price']
                                )
                                : '';
                            ?>"
                            placeholder="0.00"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Stock</label>

                        <input
                            type="number"
                            name="stock"
                            id="edit_stock"
                            min="0"
                            value="<?php
                            echo $editProduct
                                ? (int)$editProduct['stock']
                                : '';
                            ?>"
                            placeholder="0"
                            required
                        >

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeEditModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="edit_product"
                    class="submit-btn"
                >
                    <i class="fa-solid fa-check"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================
   ADD MODAL
========================= */

function openAddModal() {
    document
        .getElementById("addModal")
        .classList.add("active");
}

function closeAddModal() {
    document
        .getElementById("addModal")
        .classList.remove("active");
}


/* =========================
   EDIT MODAL
========================= */

function editProduct(
    id,
    name,
    description,
    price,
    stock
) {

    document.getElementById("edit_id").value = id;
    document.getElementById("edit_product_name").value = name;
    document.getElementById("edit_description").value = description;
    document.getElementById("edit_price").value = price;
    document.getElementById("edit_stock").value = stock;

    document
        .getElementById("editModal")
        .classList.add("active");
}

function closeEditModal() {
    document
        .getElementById("editModal")
        .classList.remove("active");
}


/* =========================
   DELETE
========================= */

function deleteProduct(id) {

    const confirmed = confirm(
        "Are you sure you want to delete this product?"
    );

    if (confirmed) {

        window.location.href =
            "Products.php?delete=" + id;
    }
}


/* =========================
   SEARCH + FILTER
========================= */

const searchInput =
    document.getElementById("productSearch");

const stockFilter =
    document.getElementById("stockFilter");

const rows =
    document.querySelectorAll(".product-row");

const noResults =
    document.getElementById("noResults");


function filterProducts() {

    if (!rows.length) {
        return;
    }

    const searchText =
        searchInput.value
            .toLowerCase()
            .trim();

    const selectedFilter =
        stockFilter.value;

    let visibleCount = 0;

    rows.forEach(function(row) {

        const rowText =
            row.innerText.toLowerCase();

        const rowStatus =
            row.getAttribute("data-status");

        const matchesSearch =
            rowText.includes(searchText);

        const matchesFilter =
            selectedFilter === "all" ||
            rowStatus === selectedFilter;

        if (
            matchesSearch &&
            matchesFilter
        ) {

            row.style.display = "";
            visibleCount++;

        } else {

            row.style.display = "none";
        }

    });

    if (noResults) {

        noResults.style.display =
            visibleCount === 0
                ? "block"
                : "none";
    }
}


if (searchInput) {

    searchInput.addEventListener(
        "input",
        filterProducts
    );
}

if (stockFilter) {

    stockFilter.addEventListener(
        "change",
        filterProducts
    );
}


/* =========================
   ESCAPE + OUTSIDE CLICK
========================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            closeAddModal();
            closeEditModal();

        }

    }
);


document
    .getElementById("addModal")
    .addEventListener(
        "click",
        function(event) {

            if (event.target === this) {
                closeAddModal();
            }

        }
    );


document
    .getElementById("editModal")
    .addEventListener(
        "click",
        function(event) {

            if (event.target === this) {
                closeEditModal();
            }

        }
    );

</script>

</body>
</html>