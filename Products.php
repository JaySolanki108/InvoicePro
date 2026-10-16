
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
   CSRF TOKEN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($text, $type = 'success')
{
    $_SESSION['products_message'] = [
        'text' => $text,
        'type' => $type
    ];

    header("Location: Products.php");
    exit();
}

/* =========================
   HANDLE POST ACTIONS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add', 'edit', 'delete'], true)) {

        if (!hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )) {
            redirectWithMessage(
                'Invalid security token. Please refresh and try again.',
                'error'
            );
        }

        /* =========================
           DELETE PRODUCT
        ========================= */
        if ($action === 'delete') {

            $delete_id = (int)($_POST['product_id'] ?? 0);

            if ($delete_id <= 0) {
                redirectWithMessage('Invalid product.', 'error');
            }

            try {
                $stmt = $db->prepare("
                    DELETE FROM products
                    WHERE id = :id AND user_id = :user_id
                ");

                $stmt->execute([
                    'id' => $delete_id,
                    'user_id' => $user_id
                ]);

                if ($stmt->rowCount() > 0) {
                    redirectWithMessage('Product deleted successfully.');
                }

                redirectWithMessage(
                    'Product not found or could not be deleted.',
                    'error'
                );

            } catch (Throwable $e) {
                error_log('Product delete error: ' . $e->getMessage());

                redirectWithMessage(
                    'Unable to delete product. It may be linked to an existing invoice.',
                    'error'
                );
            }
        }

        /* =========================
           VALIDATE ADD / EDIT
        ========================= */
        $product_name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceInput = $_POST['price'] ?? '';
        $stockInput = $_POST['stock'] ?? '';

        $edit_id = (int)($_POST['product_id'] ?? 0);

        if ($action === 'edit' && $edit_id <= 0) {
            redirectWithMessage('Invalid product.', 'error');
        }

        if ($product_name === '') {
            redirectWithMessage('Product name is required.', 'error');
        }

        if (
            !is_numeric($priceInput) ||
            !is_finite((float)$priceInput) ||
            (float)$priceInput < 0
        ) {
            redirectWithMessage(
                'Please enter a valid non-negative price.',
                'error'
            );
        }

        if (
            filter_var($stockInput, FILTER_VALIDATE_INT) === false ||
            (int)$stockInput < 0
        ) {
            redirectWithMessage(
                'Stock must be a non-negative whole number.',
                'error'
            );
        }

        $price = (float)$priceInput;
        $stock = (int)$stockInput;

        /* =========================
           ADD PRODUCT
        ========================= */
        if ($action === 'add') {

            try {
                $stmt = $db->prepare("
                    INSERT INTO products
                        (product_name, description, price, stock, user_id)
                    VALUES
                        (:name, :description, :price, :stock, :user_id)
                ");

                $stmt->execute([
                    'name' => $product_name,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'user_id' => $user_id
                ]);

                redirectWithMessage('Product added successfully.');

            } catch (Throwable $e) {
                error_log('Product add error: ' . $e->getMessage());

                redirectWithMessage(
                    'Unable to add product. Please try again.',
                    'error'
                );
            }
        }

        /* =========================
           EDIT PRODUCT
        ========================= */
        if ($action === 'edit') {

            try {
                $checkStmt = $db->prepare("
                    SELECT id
                    FROM products
                    WHERE id = :id AND user_id = :user_id
                    LIMIT 1
                ");

                $checkStmt->execute([
                    'id' => $edit_id,
                    'user_id' => $user_id
                ]);

                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    redirectWithMessage('Product not found.', 'error');
                }

                $stmt = $db->prepare("
                    UPDATE products
                    SET
                        product_name = :name,
                        description = :description,
                        price = :price,
                        stock = :stock
                    WHERE id = :id AND user_id = :user_id
                ");

                $stmt->execute([
                    'name' => $product_name,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'id' => $edit_id,
                    'user_id' => $user_id
                ]);

                redirectWithMessage('Product updated successfully.');

            } catch (Throwable $e) {
                error_log('Product update error: ' . $e->getMessage());

                redirectWithMessage(
                    'Unable to update product. Please try again.',
                    'error'
                );
            }
        }
    }
}

/* =========================
   FLASH MESSAGE
========================= */
if (isset($_SESSION['products_message'])) {
    $message = $_SESSION['products_message']['text'] ?? '';
    $messageType = $_SESSION['products_message']['type'] ?? 'success';

    unset($_SESSION['products_message']);
}

/* =========================
   FETCH EDIT PRODUCT
========================= */
$editProduct = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    if ($edit_id > 0) {
        try {
            $stmt = $db->prepare("
                SELECT id, product_name, description, price, stock
                FROM products
                WHERE id = :id AND user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                'id' => $edit_id,
                'user_id' => $user_id
            ]);

            $editProduct = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$editProduct) {
                $message = 'Product not found.';
                $messageType = 'error';
            }

        } catch (Throwable $e) {
            error_log('Fetch edit product error: ' . $e->getMessage());
            $message = 'Unable to load product details.';
            $messageType = 'error';
        }
    }
}

/* =========================
   FETCH PRODUCTS
========================= */
$products = [];

try {
    $stmt = $db->prepare("
        SELECT id, product_name, description, price, stock
        FROM products
        WHERE user_id = :user_id
        ORDER BY id DESC
    ");

    $stmt->execute(['user_id' => $user_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Fetch products error: ' . $e->getMessage());

    $message = 'Unable to load products.';
    $messageType = 'error';
}

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
            'label' => 'Out of Stock',
            'class' => 'out',
            'filter' => 'out'
        ];
    }

    if ($stock <= 5) {
        return [
            'label' => 'Low Stock',
            'class' => 'low',
            'filter' => 'low'
        ];
    }

    return [
        'label' => 'In Stock',
        'class' => 'in',
        'filter' => 'in'
    ];
}

/* =========================
   PRODUCT ICON
========================= */
function productIcon($name)
{
    $name = strtolower((string)$name);

    if (
        strpos($name, 'laptop') !== false ||
        strpos($name, 'computer') !== false ||
        strpos($name, 'pc') !== false
    ) return 'fa-laptop';

    if (
        strpos($name, 'phone') !== false ||
        strpos($name, 'mobile') !== false
    ) return 'fa-mobile-screen-button';

    if (strpos($name, 'keyboard') !== false) return 'fa-keyboard';
    if (strpos($name, 'mouse') !== false) return 'fa-computer-mouse';

    if (
        strpos($name, 'headphone') !== false ||
        strpos($name, 'earphone') !== false ||
        strpos($name, 'headset') !== false
    ) return 'fa-headphones';

    if (strpos($name, 'camera') !== false) return 'fa-camera';
    if (strpos($name, 'watch') !== false) return 'fa-clock';
    if (strpos($name, 'printer') !== false) return 'fa-print';
    if (strpos($name, 'book') !== false) return 'fa-book';

    if (
        strpos($name, 'chair') !== false ||
        strpos($name, 'table') !== false ||
        strpos($name, 'furniture') !== false
    ) return 'fa-chair';

    return 'fa-box';
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root{--black:#080808;--black-soft:#0d0d0d;--black-card:#111;--gold:#d4af37;--gold-light:#f2d675;--gold-soft:rgba(212,175,55,.08);--gold-border:rgba(212,175,55,.24);--white:#fff;--text:#eee;--muted:#888;--success:#22c55e;--warning:#eab308;--danger:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,sans-serif;background:radial-gradient(circle at 50% -10%,rgba(212,175,55,.055),transparent 34%),var(--black);color:var(--text);min-height:100vh}
a{text-decoration:none;color:inherit}
button,input,textarea,select{font-family:inherit}
.topbar{height:68px;padding:0 32px;display:flex;align-items:center;justify-content:space-between;background:rgba(8,8,8,.96);border-bottom:1px solid var(--gold-border);position:sticky;top:0;z-index:1000;backdrop-filter:blur(12px)}
.brand{display:flex;align-items:center;gap:10px;color:var(--white);font-size:17px;font-weight:700}
.brand i{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:9px;color:var(--black);background:var(--gold)}
.nav-right{display:flex;align-items:center;gap:8px}
.nav-link,.nav-icon{height:39px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;gap:7px;transition:.2s}
.nav-link{padding:0 12px;color:var(--muted);border:1px solid transparent;font-size:11px;font-weight:600}
.nav-link i,.nav-icon{color:var(--gold)}
.nav-link:hover,.nav-icon:hover{background:var(--gold-soft);border-color:var(--gold-border)}
.nav-icon{width:39px;border:1px solid var(--gold-border);background:rgba(212,175,55,.06);cursor:pointer}
.container{width:90%;max-width:1150px;margin:auto;padding:40px 0 60px}
.page-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:25px}
.page-title{display:flex;align-items:center;gap:14px}
.page-title-icon{width:46px;height:46px;display:flex;align-items:center;justify-content:center;border-radius:12px;color:var(--gold);background:var(--gold-soft);border:1px solid var(--gold-border)}
.page-title-text h1{color:var(--white);font-size:24px;margin-bottom:5px}
.page-title-text p{color:var(--muted);font-size:12px}
.add-btn,.submit-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--gold);border-radius:9px;background:var(--gold);color:var(--black);font-size:11px;font-weight:700;cursor:pointer;transition:.2s}
.add-btn{height:40px;padding:0 16px}
.add-btn:hover,.submit-btn:hover{background:var(--gold-light)}
.flash{padding:13px 15px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:9px;font-size:12px;font-weight:600}
.flash.success{color:#86efac;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2)}
.flash.error{color:#fca5a5;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2)}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.summary-card{min-height:88px;display:flex;align-items:center;gap:13px;padding:17px;background:var(--black-card);border:1px solid rgba(255,255,255,.07);border-radius:12px;transition:.2s}
.summary-card:hover{transform:translateY(-2px);border-color:rgba(212,175,55,.5)}
.summary-icon{width:42px;height:42px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:10px;color:var(--gold);background:var(--gold-soft);border:1px solid var(--gold-border)}
.summary-label{color:var(--muted);font-size:10px;font-weight:600;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
.summary-value{color:var(--white);font-size:18px;font-weight:700;overflow-wrap:anywhere}
.product-card{background:var(--black-card);border:1px solid rgba(255,255,255,.07);border-radius:14px;overflow:hidden}
.card-top{padding:20px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px;border-bottom:1px solid rgba(255,255,255,.06)}
.card-title h2{color:var(--white);font-size:15px;margin-bottom:5px}
.card-title p{color:var(--muted);font-size:11px}
.controls{display:flex;align-items:center;gap:9px}
.search-box{position:relative}
.search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#777;font-size:12px}
.search-box input,.stock-filter{height:39px;background:var(--black-soft);border:1px solid rgba(255,255,255,.09);border-radius:9px;color:var(--text);outline:none;font-size:11px}
.search-box input{width:215px;padding:0 12px 0 34px}
.stock-filter{min-width:135px;padding:0 11px;cursor:pointer}
.search-box input:focus,.stock-filter:focus,.form-group input:focus,.form-group textarea:focus{border-color:rgba(212,175,55,.6);box-shadow:0 0 0 3px rgba(212,175,55,.05)}
.stock-filter option{background:#111}
.table-wrapper{width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead{background:rgba(212,175,55,.025)}
th{text-align:left;padding:13px 22px;color:#777;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px}
td{padding:15px 22px;border-top:1px solid rgba(255,255,255,.055);color:#ddd;font-size:12px}
tbody tr:hover{background:rgba(212,175,55,.025)}
.product-info{display:flex;align-items:center;gap:11px}
.product-icon{width:37px;height:37px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:9px;color:var(--gold);background:var(--gold-soft);border:1px solid var(--gold-border)}
.product-name{color:var(--white);font-size:12px;font-weight:600;margin-bottom:3px}
.product-description{max-width:260px;color:#777;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.price,.stock-number{color:var(--white);font-weight:600}
.status{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:20px;font-size:10px;font-weight:700}
.status:before{content:"";width:6px;height:6px;border-radius:50%}
.status.in{color:#77e89a;background:rgba(34,197,94,.08)}
.status.in:before{background:var(--success)}
.status.low{color:#f4d96a;background:rgba(234,179,8,.08)}
.status.low:before{background:var(--warning)}
.status.out{color:#ff8585;background:rgba(239,68,68,.08)}
.status.out:before{background:var(--danger)}
.actions{display:flex;align-items:center;gap:7px}
.action-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.09);border-radius:8px;background:var(--black-soft);color:#aaa;cursor:pointer;transition:.2s}
.action-btn:hover{color:var(--gold-light);border-color:var(--gold-border);background:var(--gold-soft)}
.action-btn.delete:hover{color:#ff8585;border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.08)}
.empty-state,.no-results{text-align:center;padding:50px 20px;color:#777}
.empty-icon{width:58px;height:58px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;border-radius:13px;color:var(--gold);background:var(--gold-soft);border:1px solid var(--gold-border);font-size:21px}
.empty-state h3{color:var(--white);font-size:15px;margin-bottom:7px}
.empty-state p{font-size:11px;margin-bottom:18px}
.no-results{display:none;font-size:12px}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.78);display:flex;align-items:center;justify-content:center;padding:20px;z-index:2000;opacity:0;visibility:hidden;transition:.2s}
.modal-overlay.active{opacity:1;visibility:visible}
.modal{width:100%;max-width:520px;background:var(--black-card);border:1px solid var(--gold-border);border-radius:15px;overflow:hidden;box-shadow:0 25px 80px rgba(0,0,0,.65);transform:translateY(12px);transition:.2s}
.modal-overlay.active .modal{transform:translateY(0)}
.modal-header{padding:19px 22px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07)}
.modal-header h2{color:var(--white);font-size:16px}
.close-modal{width:32px;height:32px;border:1px solid rgba(255,255,255,.09);border-radius:8px;background:var(--black-soft);color:#aaa;cursor:pointer}
.close-modal:hover{color:var(--gold)}
.modal-body{padding:22px}
.form-group{margin-bottom:16px}
.form-group label{display:block;color:var(--muted);font-size:10px;font-weight:600;margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px}
.form-group input,.form-group textarea{width:100%;background:var(--black-soft);border:1px solid rgba(255,255,255,.09);border-radius:8px;color:var(--text);padding:11px 12px;outline:none;font-size:12px}
.form-group input{height:41px}
.form-group textarea{min-height:85px;resize:vertical}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.modal-footer{padding:16px 22px;display:flex;justify-content:flex-end;gap:9px;border-top:1px solid rgba(255,255,255,.07)}
.cancel-btn{height:39px;padding:0 15px;border:1px solid rgba(255,255,255,.09);border-radius:8px;background:transparent;color:var(--muted);font-size:11px;font-weight:600;cursor:pointer}
.submit-btn{height:39px;padding:0 17px;border-radius:8px}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}.card-top{align-items:flex-start;flex-direction:column}.controls{width:100%}.search-box{flex:1}.search-box input{width:100%}}
@media(max-width:700px){.topbar{height:62px;padding:0 16px}.brand{font-size:14px}.brand i{width:31px;height:31px}.nav-right{gap:5px}.nav-link{width:35px;height:35px;padding:0}.nav-link span{display:none}.nav-icon{width:35px;height:35px}.container{width:92%;padding:28px 0 45px}.page-header{align-items:flex-start;flex-direction:column}.page-title-text h1{font-size:21px}.add-btn{width:100%}.controls{flex-direction:column}.search-box,.search-box input,.stock-filter{width:100%;min-width:0}.form-row{grid-template-columns:1fr;gap:0}th,td{padding:13px 15px}}
@media(max-width:430px){.summary-grid{grid-template-columns:1fr}.summary-card{min-height:82px}.page-title-icon{width:42px;height:42px}.product-description{max-width:150px}}
</style>
</head>

<body>

<header class="topbar">
    <a href="Dashboard.php" class="brand">
        <i class="fa-solid fa-file-invoice"></i>
        <span>Invoice Pro</span>
    </a>

    <nav class="nav-right">
        <a href="Dashboard.php" class="nav-link" title="Dashboard">
            <i class="fa-solid fa-house"></i><span>Dashboard</span>
        </a>
        <a href="History_Invoice.php" class="nav-link" title="Invoices">
            <i class="fa-solid fa-file-invoice"></i><span>Invoices</span>
        </a>
        <a href="Profile.php" class="nav-icon" title="Profile" aria-label="Profile">
            <i class="fa-solid fa-user"></i>
        </a>
        <button type="button" class="nav-icon" title="Theme" aria-label="Theme">
            <i class="fa-solid fa-moon"></i>
        </button>
        <a href="Logout.php" class="nav-icon" title="Logout" aria-label="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </nav>
</header>

<main class="container">

    <div class="page-header">
        <div class="page-title">
            <div class="page-title-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="page-title-text">
                <h1>Products</h1>
                <p>Manage your products, prices and stock.</p>
            </div>
        </div>

        <button class="add-btn" type="button" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Add Product
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="flash <?= $messageType === 'error' ? 'error' : 'success' ?>">
            <i class="fa-solid <?= $messageType === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div><div class="summary-label">Total Products</div><div class="summary-value"><?= $totalProducts ?></div></div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="summary-label">In Stock</div><div class="summary-value"><?= $inStockCount ?></div></div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div><div class="summary-label">Low / Out</div><div class="summary-value"><?= $lowOutCount ?></div></div>
        </div>
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div><div class="summary-label">Inventory Value</div><div class="summary-value">₹<?= number_format($inventoryValue, 2) ?></div></div>
        </div>
    </div>

    <section class="product-card">
        <div class="card-top">
            <div class="card-title">
                <h2>Product List</h2>
                <p>View and manage all your products.</p>
            </div>

            <div class="controls">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="productSearch" placeholder="Search products..." autocomplete="off">
                </div>

                <select id="stockFilter" class="stock-filter">
                    <option value="all">All Stock</option>
                    <option value="in">In Stock</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                </select>
            </div>
        </div>

        <?php if ($products): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Product</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="productTableBody">
                    <?php foreach ($products as $product): ?>
                        <?php $status = productStatus($product['stock']); ?>
                        <tr class="product-row" data-status="<?= e($status['filter']) ?>">
                            <td>
                                <div class="product-info">
                                    <div class="product-icon">
                                        <i class="fa-solid <?= e(productIcon($product['product_name'])) ?>"></i>
                                    </div>
                                    <div>
                                        <div class="product-name"><?= e($product['product_name']) ?></div>
                                        <?php if ($product['description'] !== ''): ?>
                                            <div class="product-description"><?= e($product['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="price">₹<?= number_format((float)$product['price'], 2) ?></span></td>
                            <td><span class="stock-number"><?= (int)$product['stock'] ?></span></td>
                            <td><span class="status <?= e($status['class']) ?>"><?= e($status['label']) ?></span></td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="action-btn" title="Edit Product"
                                        onclick="editProduct(
                                            <?= (int)$product['id'] ?>,
                                            <?= e(json_encode($product['product_name'])) ?>,
                                            <?= e(json_encode($product['description'])) ?>,
                                            <?= json_encode((float)$product['price']) ?>,
                                            <?= (int)$product['stock'] ?>
                                        )">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                        <button type="submit" class="action-btn delete" title="Delete Product">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="no-results" id="noResults">
                <i class="fa-solid fa-magnifying-glass"></i>
                <div style="margin-top:10px">No products found.</div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-box-open"></i></div>
                <h3>No Products Yet</h3>
                <p>Add your first product to start managing your inventory.</p>
                <button class="add-btn" type="button" onclick="openAddModal()">
                    <i class="fa-solid fa-plus"></i> Add Product
                </button>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- ADD PRODUCT MODAL -->
<div class="modal-overlay <?= $showAddModal ? 'active' : '' ?>" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Add Product</h2>
            <button type="button" class="close-modal" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add">

            <div class="modal-body">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" maxlength="255" placeholder="Enter product name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter product description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" name="price" min="0" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" step="1" placeholder="0" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="submit-btn"><i class="fa-solid fa-plus"></i> Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div class="modal-overlay <?= $editProduct ? 'active' : '' ?>" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Edit Product</h2>
            <button type="button" class="close-modal" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="product_id" id="edit_id" value="<?= $editProduct ? (int)$editProduct['id'] : '' ?>">

            <div class="modal-body">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_product_name"
                        value="<?= e($editProduct['product_name'] ?? '') ?>" maxlength="255" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"><?= e($editProduct['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" name="price" id="edit_price" min="0" step="0.01"
                            value="<?= e($editProduct['price'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" id="edit_stock" min="0" step="1"
                            value="<?= e($editProduct['stock'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="submit-btn"><i class="fa-solid fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function editProduct(id, name, description, price, stock) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_product_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_stock').value = stock;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

const searchInput = document.getElementById('productSearch');
const stockFilter = document.getElementById('stockFilter');
const rows = document.querySelectorAll('.product-row');
const noResults = document.getElementById('noResults');

function filterProducts() {
    const searchText = searchInput.value.toLowerCase().trim();
    const selectedFilter = stockFilter.value;
    let visibleCount = 0;

    rows.forEach(function(row) {
        const matchesSearch = row.innerText.toLowerCase().includes(searchText);
        const matchesFilter = selectedFilter === 'all' ||
            row.getAttribute('data-status') === selectedFilter;

        if (matchesSearch && matchesFilter) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

searchInput.addEventListener('input', filterProducts);
stockFilter.addEventListener('change', filterProducts);

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});

document.getElementById('addModal').addEventListener('click', function(event) {
    if (event.target === this) closeAddModal();
});

document.getElementById('editModal').addEventListener('click', function(event) {
    if (event.target === this) closeEditModal();
});
</script>

</body>
</html>
