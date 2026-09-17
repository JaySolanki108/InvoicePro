<?php
session_start();

require_once "config.php";

/* =========================================================
   CHECK LOGIN
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

$message = "";
$messageType = "";

/* =========================================================
   UPDATE PROFILE
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {

    $companyName = trim($_POST['company_name'] ?? "");
    $phone       = trim($_POST['phone'] ?? "");
    $address     = trim($_POST['address'] ?? "");

    if ($companyName === "") {
        $message = "Company Name is required.";
        $messageType = "error";
    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET company_name = ?, phone = ?, address = ?
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param(
                "sssi",
                $companyName,
                $phone,
                $address,
                $userId
            );

            if ($stmt->execute()) {
                $message = "Profile updated successfully.";
                $messageType = "success";
            } else {
                $message = "Unable to update profile.";
                $messageType = "error";
            }

            $stmt->close();
        } else {
            $message = "Database error.";
            $messageType = "error";
        }
    }
}

/* =========================================================
   GET CURRENT USER DATA
========================================================= */
$stmt = $conn->prepare("
    SELECT id, company_name, company_email, phone, address, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

/* =========================================================
   IF USER NOT FOUND
========================================================= */
if (!$user) {
    session_destroy();
    header("Location: Login.php");
    exit;
}

/* =========================================================
   USER DATA
========================================================= */
$userName   = $user['company_name'];
$fullName   = $user['company_name'];
$email      = $user['company_email'];
$phone      = !empty($user['phone']) ? $user['phone'] : "Not added";
$address    = !empty($user['address']) ? $user['address'] : "Not added";

$memberSince = !empty($user['created_at'])
    ? date("d M Y", strtotime($user['created_at']))
    : "Not available";

/* Avatar based on company name */
$avatarSeed = urlencode($userName);

$avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed="
    . $avatarSeed
    . "&backgroundColor=c0aede";

/* =========================================================
   DISPLAY MESSAGE FROM POST
========================================================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>InvoicePro - My Account</title>

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
            --primary-blue: #1d4ed8;
            --primary-btn: #2563eb;
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
            cursor: pointer;
            font-size: 14px;
        }

        .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #c0aede;
        }

        /* =====================================================
           PAGE CONTAINER
        ===================================================== */

        .page-container {
            padding: 24px 32px;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .header-icon {
            width: 56px;
            height: 56px;
            background: #eff6ff;
            color: var(--primary-btn);
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

        /* =====================================================
           MESSAGE
        ===================================================== */

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .message.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* =====================================================
           CARDS
        ===================================================== */

        .card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 20px;
        }

        /* =====================================================
           PROFILE TOP CARD
        ===================================================== */

        .profile-top-card {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .profile-left {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .avatar-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
        }

        .avatar-wrapper img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            background: #c0aede;
        }

        .camera-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 32px;
            height: 32px;
            background: var(--primary-btn);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border: 2px solid #fff;
        }

        .profile-info h2 {
            font-size: 20px;
            color: var(--text-dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .profile-info h2 i {
            color: var(--primary-btn);
            font-size: 16px;
        }

        .profile-details-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .profile-detail-item i {
            width: 16px;
            text-align: center;
            color: #94a3b8;
        }

        .btn-outline {
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--primary-btn);
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-outline:hover {
            border-color: var(--primary-btn);
            background: #f0f5ff;
        }

        /* =====================================================
           PERSONAL INFORMATION
        ===================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .disabled-input {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
            min-height: 43px;
            word-break: break-word;
        }

        /* =====================================================
           ACCOUNT SETTINGS
        ===================================================== */

        .settings-list {
            display: flex;
            flex-direction: column;
        }

        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: 0.2s;
        }

        .setting-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .setting-item:hover .setting-text h3 {
            color: var(--primary-btn);
        }

        .setting-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .setting-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eff6ff;
            color: var(--primary-btn);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .setting-text h3 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
            transition: 0.2s;
        }

        .setting-text p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .setting-chevron {
            color: var(--text-light);
        }

        /* =====================================================
           LOGOUT BUTTON
        ===================================================== */

        .btn-logout-full {
            width: 100%;
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: var(--danger);
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            transition: 0.2s;
            text-decoration: none;
        }

        .btn-logout-full:hover {
            background: #fee2e2;
            border-color: var(--danger);
        }

        /* =====================================================
           MODAL
        ===================================================== */

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .modal-header h2 {
            font-size: 19px;
            color: var(--text-dark);
        }

        .close-modal {
            width: 34px;
            height: 34px;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            border-radius: 50%;
            cursor: pointer;
            font-size: 15px;
        }

        .close-modal:hover {
            background: #e2e8f0;
        }

        .form-group {
            margin-bottom: 17px;
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
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 13px;
            outline: none;
            font-size: 14px;
            color: var(--text-dark);
            background: #fff;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary-btn);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }

        .form-group textarea {
            height: 90px;
            resize: vertical;
        }

        .email-note {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .btn-cancel {
            border: 1px solid var(--border);
            background: #fff;
            color: #64748b;
            padding: 10px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-save {
            border: none;
            background: var(--primary-btn);
            color: #fff;
            padding: 10px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-save:hover {
            background: #1d4ed8;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {

            .sidebar {
                width: 210px;
            }

            .header {
                padding: 16px 20px;
            }

            .search-bar {
                width: 240px;
            }

            .page-container {
                padding: 20px;
            }

            .profile-top-card {
                gap: 20px;
            }
        }

        @media (max-width: 650px) {

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
                padding: 14px 16px;
            }

            .search-bar {
                width: 200px;
            }

            .header-right {
                gap: 10px;
            }

            .user-profile span {
                display: none;
            }

            .page-container {
                padding: 16px;
            }

            .profile-top-card {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .profile-left {
                gap: 16px;
            }

            .avatar-wrapper {
                width: 80px;
                height: 80px;
            }
        }

    </style>
</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

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
            <a href="Bill_History.php" class="nav-item">
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
            <a href="Profile.php" class="nav-item active">
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


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">

    <!-- HEADER -->

    <div class="header">

        <div class="search-bar">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                placeholder="Search anything..."
            >

        </div>

        <div class="header-right">

            <div class="notification">
                <i class="fa-regular fa-bell"></i>
            </div>

            <div class="user-profile">

                <img
                    src="<?php echo htmlspecialchars($avatarUrl); ?>"
                    alt="User"
                >

                <span>
                    <?php echo htmlspecialchars($userName); ?>
                </span>

                <i
                    class="fa-solid fa-chevron-down"
                    style="font-size:12px;color:#64748b;"
                ></i>

            </div>

        </div>

    </div>


    <!-- =====================================================
         PAGE CONTENT
    ===================================================== -->

    <div class="page-container">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div class="header-icon">
                <i class="fa-regular fa-user"></i>
            </div>

            <div class="header-text">

                <h1>My Account</h1>

                <p>
                    View and manage your profile information and account settings.
                </p>

            </div>

        </div>


        <!-- SUCCESS / ERROR MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="message <?php echo $messageType; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             PROFILE TOP CARD
        ================================================= -->

        <div class="card profile-top-card">

            <div class="profile-left">

                <div class="avatar-wrapper">

                    <img
                        src="<?php echo htmlspecialchars($avatarUrl); ?>"
                        alt="Profile Picture"
                    >

                    <div class="camera-btn">
                        <i class="fa-solid fa-camera"></i>
                    </div>

                </div>


                <div class="profile-info">

                    <h2>

                        <?php echo htmlspecialchars($fullName); ?>

                        <i class="fa-solid fa-circle-check"></i>

                    </h2>


                    <div class="profile-details-list">

                        <div class="profile-detail-item">

                            <i class="fa-regular fa-envelope"></i>

                            <?php echo htmlspecialchars($email); ?>

                        </div>


                        <div class="profile-detail-item">

                            <i class="fa-solid fa-phone"></i>

                            <?php echo htmlspecialchars($phone); ?>

                        </div>


                        <div class="profile-detail-item">

                            <i class="fa-regular fa-calendar"></i>

                            Member since
                            <?php echo htmlspecialchars($memberSince); ?>

                        </div>

                    </div>

                </div>

            </div>


            <button
                class="btn-outline"
                onclick="openEditProfile()"
            >

                <i class="fa-solid fa-pen"></i>

                Edit Profile

            </button>

        </div>


        <!-- =================================================
             PERSONAL INFORMATION CARD
        ================================================= -->

        <div class="card">

            <div class="card-header">

                <i
                    class="fa-regular fa-user"
                    style="color:#3b82f6;"
                ></i>

                Personal Information

            </div>


            <div class="info-grid">

                <div class="input-group">

                    <label>Company Name</label>

                    <div class="disabled-input">

                        <?php echo htmlspecialchars($fullName); ?>

                    </div>

                </div>


                <div class="input-group">

                    <label>Email Address</label>

                    <div class="disabled-input">

                        <?php echo htmlspecialchars($email); ?>

                    </div>

                </div>


                <div class="input-group">

                    <label>Phone Number</label>

                    <div class="disabled-input">

                        <?php echo htmlspecialchars($phone); ?>

                    </div>

                </div>


                <div class="input-group">

                    <label>Address</label>

                    <div class="disabled-input">

                        <?php echo htmlspecialchars($address); ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             ACCOUNT SETTINGS CARD
        ================================================= -->

        <div class="card">

            <div class="card-header">

                <i
                    class="fa-solid fa-gear"
                    style="color:#3b82f6;"
                ></i>

                Account Settings

            </div>


            <div class="settings-list">

                <!-- CHANGE PASSWORD -->

                <div class="setting-item">

                    <div class="setting-left">

                        <div class="setting-icon">

                            <i class="fa-solid fa-lock"></i>

                        </div>

                        <div class="setting-text">

                            <h3>Change Password</h3>

                            <p>
                                Update your account password
                            </p>

                        </div>

                    </div>

                    <i class="fa-solid fa-chevron-right setting-chevron"></i>

                </div>


                <!-- NOTIFICATION SETTINGS -->

                <div class="setting-item">

                    <div class="setting-left">

                        <div class="setting-icon">

                            <i class="fa-regular fa-bell"></i>

                        </div>

                        <div class="setting-text">

                            <h3>Notification Settings</h3>

                            <p>
                                Manage your notifications
                            </p>

                        </div>

                    </div>

                    <i class="fa-solid fa-chevron-right setting-chevron"></i>

                </div>


                <!-- PRIVACY -->

                <div class="setting-item">

                    <div class="setting-left">

                        <div class="setting-icon">

                            <i class="fa-solid fa-shield-halved"></i>

                        </div>

                        <div class="setting-text">

                            <h3>Privacy & Security</h3>

                            <p>
                                Control your data and security
                            </p>

                        </div>

                    </div>

                    <i class="fa-solid fa-chevron-right setting-chevron"></i>

                </div>


                <!-- HELP -->

                <div class="setting-item">

                    <div class="setting-left">

                        <div class="setting-icon">

                            <i class="fa-regular fa-circle-question"></i>

                        </div>

                        <div class="setting-text">

                            <h3>Help & Support</h3>

                            <p>
                                Get help or contact us
                            </p>

                        </div>

                    </div>

                    <i class="fa-solid fa-chevron-right setting-chevron"></i>

                </div>

            </div>


            <!-- LOGOUT -->

            <a
                href="Login.php"
                class="btn-logout-full"
            >

                <i class="fa-solid fa-arrow-right-from-bracket"></i>

                Log Out

            </a>

        </div>

    </div>

</div>


<!-- =========================================================
     EDIT PROFILE MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="editProfileModal"
>

    <div class="modal">

        <div class="modal-header">

            <h2>Edit Profile</h2>

            <button
                type="button"
                class="close-modal"
                onclick="closeEditProfile()"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <form
            method="POST"
            action=""
        >

            <div class="form-group">

                <label>
                    Company Name
                </label>

                <input
                    type="text"
                    name="company_name"
                    value="<?php echo htmlspecialchars($fullName); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Email Address
                </label>

                <input
                    type="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    readonly
                >

                <div class="email-note">
                    Email address cannot be changed from this page.
                </div>

            </div>


            <div class="form-group">

                <label>
                    Phone Number
                </label>

                <input
                    type="text"
                    name="phone"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                    placeholder="Enter phone number"
                >

            </div>


            <div class="form-group">

                <label>
                    Address
                </label>

                <textarea
                    name="address"
                    placeholder="Enter your address"
                ><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeEditProfile()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_profile"
                    class="btn-save"
                >
                    <i class="fa-solid fa-check"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    const editModal =
        document.getElementById("editProfileModal");


    function openEditProfile() {

        editModal.classList.add("show");

    }


    function closeEditProfile() {

        editModal.classList.remove("show");

    }


    /* Close when clicking outside modal */

    editModal.addEventListener("click", function(event) {

        if (event.target === editModal) {

            closeEditProfile();

        }

    });


    /* Close with Escape key */

    document.addEventListener("keydown", function(event) {

        if (event.key === "Escape") {

            closeEditProfile();

        }

    });

</script>

</body>
</html>