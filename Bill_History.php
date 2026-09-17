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

$userId = (int) $_SESSION['user_id'];

/* =========================================================
   GET USER DETAILS
========================================================= */
$userName = "User";

$userStmt = $conn->prepare("SELECT company_name FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows > 0) {
    $userData = $userResult->fetch_assoc();
    $userName = $userData['company_name'];
}

$userStmt->close();

/* =========================================================
   GET BILL HISTORY
========================================================= */
$bills = [];

$billStmt = $conn->prepare("
    SELECT 
        id,
        bill_number,
        customer_name,
        bill_date,
        total
    FROM bills
    WHERE user_id = ?
    ORDER BY id DESC
");

$billStmt->bind_param("i", $userId);
$billStmt->execute();

$billResult = $billStmt->get_result();

while ($row = $billResult->fetch_assoc()) {

    /*
       Current database schema does not have payment_status.
       Therefore bills are shown as Pending for now.
    */
    $row['status'] = 'Pending';

    $bills[] = $row;
}

$billStmt->close();

/* =========================================================
   STATISTICS
========================================================= */
$totalBills = count($bills);

$paidBills = 0;
$pendingBills = 0;
$overdueBills = 0;

foreach ($bills as $bill) {

    if ($bill['status'] === 'Paid') {
        $paidBills++;
    } elseif ($bill['status'] === 'Overdue') {
        $overdueBills++;
    } else {
        $pendingBills++;
    }
}

/* =========================================================
   ESCAPE FUNCTION
========================================================= */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>InvoicePro - Bill History</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>

        :root {
            --bg-body: #f4f7fa;
            --bg-sidebar: #172136;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --primary-blue: #1d4ed8;
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

        /* =====================================================
           SIDEBAR
        ===================================================== */

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

        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            min-width: 0;
        }

        /* =====================================================
           HEADER
        ===================================================== */

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
            cursor: pointer;
        }

        .notification::after {
            content: '2';
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
            cursor: pointer;
            font-size: 14px;
        }

        .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        /* =====================================================
           PAGE CONTAINER
        ===================================================== */

        .page-container {
            padding: 24px 32px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* =====================================================
           PAGE HEADER
        ===================================================== */

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

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* =====================================================
           STATS
        ===================================================== */

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
            background-color: #f0f5ff;
            border-color: #dbeafe;
        }

        .stat-blue .stat-icon {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-blue p {
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

        .stat-green p {
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

        .stat-yellow p {
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

        .stat-red p {
            color: #ef4444;
        }

        /* =====================================================
           TABLE CARD
        ===================================================== */

        .table-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        /* =====================================================
           FILTERS
        ===================================================== */

        .filters-row {
            padding: 20px 24px;
            display: flex;
            gap: 16px;
            border-bottom: 1px solid var(--border);
        }

        .filter-input {
            position: relative;
            flex: 1;
            max-width: 350px;
        }

        .filter-input i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .filter-control {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-dark);
            outline: none;
            background: #fff;
        }

        .filter-control:focus {
            border-color: var(--primary-btn);
        }

        .filter-select {
            position: relative;
            width: 200px;
        }

        .filter-select select {
            appearance: none;
            cursor: pointer;
            background: #fff;
        }

        /* =====================================================
           TABLE
        ===================================================== */

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            font-size: 12px;
            color: var(--text-dark);
            font-weight: 600;
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
            white-space: nowrap;
        }

        td {
            font-size: 13px;
            color: var(--text-muted);
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            font-weight: 500;
        }

        td.highlight {
            color: var(--primary-btn);
            font-weight: 600;
        }

        .empty-row {
            text-align: center;
            padding: 55px 20px;
            color: var(--text-muted);
        }

        .empty-row i {
            font-size: 42px;
            color: #cbd5e1;
            margin-bottom: 14px;
        }

        .empty-row h3 {
            color: var(--text-dark);
            margin-bottom: 6px;
            font-size: 16px;
        }

        .empty-row p {
            font-size: 13px;
            margin-bottom: 18px;
        }

        .empty-create-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            background: var(--primary-btn);
            color: #fff;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        /* =====================================================
           STATUS BADGES
        ===================================================== */

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
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

        /* =====================================================
           ACTION BUTTONS
        ===================================================== */

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
            color: var(--primary-btn);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
            text-decoration: none;
        }

        .btn-icon:hover {
            border-color: var(--primary-btn);
            background: #f0f5ff;
        }

        /* =====================================================
           PAGINATION
        ===================================================== */

        .pagination-row {
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--text-muted);
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
        }

        .page-btn.active {
            background: var(--primary-btn);
            color: #fff;
            border-color: var(--primary-btn);
        }

        .page-btn:hover:not(.active) {
            background: #f8fafc;
        }

        .page-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-row {
                flex-wrap: wrap;
            }

            .filter-input,
            .filter-select {
                max-width: none;
                width: calc(50% - 8px);
            }
        }

        @media (max-width: 700px) {

            .sidebar {
                width: 210px;
            }

            .header {
                padding: 14px 18px;
            }

            .search-bar {
                width: 220px;
            }

            .page-container {
                padding: 18px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 18px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                flex-direction: column;
            }

            .filter-input,
            .filter-select {
                width: 100%;
                max-width: none;
            }

            .table-card {
                overflow-x: auto;
            }

            table {
                min-width: 850px;
            }
        }

    </style>
</head>

<body>

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <div class="sidebar">

        <div class="sidebar-logo">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            InvoicePro
        </div>

        <ul class="nav-menu">

            <li>
                <a href="Dashboard.php" class="nav-item">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>
            </li>

            <li>
                <a href="Create_Invoice.php" class="nav-item">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    Create Invoice
                </a>
            </li>

            <li>
                <a href="History_Invoice.php" class="nav-item">
                    <i class="fa-solid fa-file-invoice"></i>
                    Invoice History
                </a>
            </li>

            <li>
                <a href="Create_Bill.php" class="nav-item">
                    <i class="fa-solid fa-receipt"></i>
                    Create Bill
                </a>
            </li>

            <li>
                <a href="Bill_History.php" class="nav-item active">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Bill History
                </a>
            </li>

            <li>
                <a href="Products.php" class="nav-item">
                    <i class="fa-solid fa-box-open"></i>
                    Products
                </a>
            </li>

            <li>
                <a href="Profile.php" class="nav-item">
                    <i class="fa-regular fa-user"></i>
                    Account
                </a>
            </li>

        </ul>

        <a href="Login.php" class="logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Logout
        </a>

    </div>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <div class="main-content">

        <!-- HEADER -->

        <div class="header">

            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="topSearch"
                    placeholder="Search anything..."
                >
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

                    <?php echo e($userName); ?>

                    <i
                        class="fa-solid fa-chevron-down"
                        style="font-size: 12px; color: #64748b;"
                    ></i>

                </div>

            </div>

        </div>


        <!-- PAGE CONTENT -->

        <div class="page-container">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div class="header-title-area">

                    <div class="header-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <div class="header-text">

                        <h1>Bill History</h1>

                        <p>
                            View and manage all your generated bills.
                        </p>

                    </div>

                </div>

                <a href="Create_Bill.php" class="btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Create New Bill
                </a>

            </div>


            <!-- =================================================
                 STATS
            ================================================== -->

            <div class="stats-grid">

                <div class="stat-card stat-blue">

                    <div class="stat-icon">
                        <i class="fa-regular fa-file-lines"></i>
                    </div>

                    <p>Total Bills</p>

                    <h2>
                        <?php echo $totalBills; ?>
                    </h2>

                </div>


                <div class="stat-card stat-green">

                    <div class="stat-icon">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                    </div>

                    <p>Paid Bills</p>

                    <h2>
                        <?php echo $paidBills; ?>
                    </h2>

                </div>


                <div class="stat-card stat-yellow">

                    <div class="stat-icon">
                        <i class="fa-regular fa-clock"></i>
                    </div>

                    <p>Pending Bills</p>

                    <h2>
                        <?php echo $pendingBills; ?>
                    </h2>

                </div>


                <div class="stat-card stat-red">

                    <div class="stat-icon">
                        <i class="fa-solid fa-file-circle-xmark"></i>
                    </div>

                    <p>Overdue Bills</p>

                    <h2>
                        <?php echo $overdueBills; ?>
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 BILL TABLE
            ================================================== -->

            <div class="table-card">

                <!-- FILTERS -->

                <div class="filters-row">

                    <div class="filter-input">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="billSearch"
                            class="filter-control"
                            placeholder="Search by bill no, customer name..."
                        >

                    </div>


                    <div class="filter-input">

                        <i class="fa-regular fa-calendar"></i>

                        <input
                            type="date"
                            id="dateFilter"
                            class="filter-control"
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

                            <option value="All">All Status</option>
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Overdue">Overdue</option>

                        </select>

                    </div>

                </div>


                <!-- TABLE -->

                <table>

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Bill No.</th>

                            <th>Customer Name</th>

                            <th>Date</th>

                            <th>Amount</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody id="billTableBody">

                        <?php if (count($bills) > 0): ?>

                            <?php foreach ($bills as $index => $bill): ?>

                                <tr
                                    class="bill-row"
                                    data-search="<?php
                                        echo e(
                                            strtolower(
                                                $bill['bill_number'] . ' ' .
                                                $bill['customer_name']
                                            )
                                        );
                                    ?>"
                                    data-date="<?php echo e($bill['bill_date']); ?>"
                                    data-status="<?php echo e($bill['status']); ?>"
                                >

                                    <td
                                        style="
                                            color:var(--text-dark);
                                            font-weight:600;
                                        "
                                    >
                                        <?php echo $index + 1; ?>
                                    </td>


                                    <td class="highlight">
                                        <?php echo e($bill['bill_number']); ?>
                                    </td>


                                    <td class="highlight">
                                        <?php echo e($bill['customer_name']); ?>
                                    </td>


                                    <td class="highlight">

                                        <?php
                                        echo date(
                                            'd-m-Y',
                                            strtotime($bill['bill_date'])
                                        );
                                        ?>

                                    </td>


                                    <td
                                        style="
                                            color:var(--text-dark);
                                            font-weight:600;
                                        "
                                    >

                                        ₹
                                        <?php
                                        echo number_format(
                                            (float)$bill['total'],
                                            2
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <span
                                            class="badge <?php echo e($bill['status']); ?>"
                                        >
                                            <?php echo e($bill['status']); ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="actions">

                                            <!-- View -->
                                            <button
                                                type="button"
                                                class="btn-icon"
                                                title="View Bill"
                                                onclick="viewBill('<?php echo e($bill['bill_number']); ?>')"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </button>


                                            <!-- Download -->
                                            <button
                                                type="button"
                                                class="btn-icon"
                                                title="Download Bill"
                                                onclick="downloadBill('<?php echo e($bill['bill_number']); ?>')"
                                            >
                                                <i class="fa-solid fa-download"></i>
                                            </button>


                                            <!-- More -->
                                            <button
                                                type="button"
                                                class="btn-icon"
                                                style="
                                                    color:var(--text-muted);
                                                    border:none;
                                                "
                                                title="More Options"
                                                onclick="showBillOptions('<?php echo e($bill['bill_number']); ?>')"
                                            >
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr id="emptyDatabaseRow">

                                <td
                                    colspan="7"
                                    class="empty-row"
                                >

                                    <i class="fa-regular fa-file-lines"></i>

                                    <h3>No Bills Found</h3>

                                    <p>
                                        You haven't created any bills yet.
                                    </p>

                                    <a
                                        href="Create_Bill.php"
                                        class="empty-create-btn"
                                    >
                                        <i class="fa-solid fa-plus"></i>
                                        Create Your First Bill
                                    </a>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>


                <!-- PAGINATION -->

                <div class="pagination-row">

                    <div id="showingText">
                        <?php
                        if ($totalBills > 0) {
                            $showing = min(10, $totalBills);
                            echo "Showing 1 to " . $showing . " of " . $totalBills . " bills";
                        } else {
                            echo "Showing 0 of 0 bills";
                        }
                        ?>
                    </div>


                    <div
                        class="pagination-controls"
                        id="paginationControls"
                    >

                        <button
                            class="page-btn"
                            id="prevBtn"
                            onclick="changePage(-1)"
                        >
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>

                        <button
                            class="page-btn active"
                            id="pageNumber"
                        >
                            1
                        </button>

                        <button
                            class="page-btn"
                            id="nextBtn"
                            onclick="changePage(1)"
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        const searchInput = document.getElementById('billSearch');
        const topSearch = document.getElementById('topSearch');
        const statusFilter = document.getElementById('statusFilter');
        const dateFilter = document.getElementById('dateFilter');

        let currentPage = 1;
        const rowsPerPage = 10;

        function getRows() {
            return Array.from(
                document.querySelectorAll('.bill-row')
            );
        }


        /* =====================================================
           FILTER + SEARCH
        ===================================================== */

        function filterBills() {

            const searchValue =
                searchInput.value.toLowerCase().trim();

            const statusValue =
                statusFilter.value;

            const dateValue =
                dateFilter.value;

            const rows = getRows();

            rows.forEach(row => {

                const searchText =
                    row.dataset.search.toLowerCase();

                const rowStatus =
                    row.dataset.status;

                const rowDate =
                    row.dataset.date;

                const searchMatch =
                    searchText.includes(searchValue);

                const statusMatch =
                    statusValue === "All" ||
                    rowStatus === statusValue;

                const dateMatch =
                    dateValue === "" ||
                    rowDate === dateValue;

                if (
                    searchMatch &&
                    statusMatch &&
                    dateMatch
                ) {

                    row.dataset.filtered = "true";

                } else {

                    row.dataset.filtered = "false";

                }

            });

            currentPage = 1;

            renderPagination();

        }


        /* =====================================================
           TOP SEARCH
        ===================================================== */

        topSearch.addEventListener('input', function() {

            searchInput.value = this.value;

            filterBills();

        });


        searchInput.addEventListener(
            'input',
            filterBills
        );

        statusFilter.addEventListener(
            'change',
            filterBills
        );

        dateFilter.addEventListener(
            'change',
            filterBills
        );


        /* =====================================================
           PAGINATION
        ===================================================== */

        function renderPagination() {

            const rows = getRows();

            const filteredRows =
                rows.filter(
                    row => row.dataset.filtered !== "false"
                );

            const totalRows =
                filteredRows.length;

            const totalPages =
                Math.max(
                    1,
                    Math.ceil(totalRows / rowsPerPage)
                );

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            rows.forEach(row => {

                row.style.display = "none";

            });

            const start =
                (currentPage - 1) * rowsPerPage;

            const end =
                start + rowsPerPage;

            filteredRows
                .slice(start, end)
                .forEach(row => {

                    row.style.display = "table-row";

                });


            /* Showing text */

            const showingText =
                document.getElementById('showingText');

            if (totalRows === 0) {

                showingText.innerText =
                    "Showing 0 of 0 bills";

            } else {

                const first =
                    start + 1;

                const last =
                    Math.min(
                        end,
                        totalRows
                    );

                showingText.innerText =
                    "Showing " +
                    first +
                    " to " +
                    last +
                    " of " +
                    totalRows +
                    " bills";

            }


            /* Page number */

            document.getElementById(
                'pageNumber'
            ).innerText = currentPage;


            /* Buttons */

            document.getElementById(
                'prevBtn'
            ).disabled =
                currentPage <= 1;

            document.getElementById(
                'nextBtn'
            ).disabled =
                currentPage >= totalPages;

        }


        function changePage(direction) {

            const rows = getRows();

            const filteredRows =
                rows.filter(
                    row => row.dataset.filtered !== "false"
                );

            const totalPages =
                Math.max(
                    1,
                    Math.ceil(
                        filteredRows.length /
                        rowsPerPage
                    )
                );

            currentPage += direction;

            if (currentPage < 1) {
                currentPage = 1;
            }

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            renderPagination();

        }


        /* =====================================================
           VIEW BILL
        ===================================================== */

        function viewBill(billNumber) {

            alert(
                "Bill " +
                billNumber +
                " selected.\n\n" +
                "Bill details page can be connected here."
            );

        }


        /* =====================================================
           DOWNLOAD BILL
        ===================================================== */

        function downloadBill(billNumber) {

            alert(
                "Download for " +
                billNumber +
                " will be connected with the bill PDF/download system."
            );

        }


        /* =====================================================
           MORE OPTIONS
        ===================================================== */

        function showBillOptions(billNumber) {

            alert(
                "More options for " +
                billNumber
            );

        }


        /* =====================================================
           INITIAL LOAD
        ===================================================== */

        getRows().forEach(row => {

            row.dataset.filtered = "true";

        });

        renderPagination();

    </script>

</body>
</html>