<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = "";
$messageType = "";

/* UPDATE PROFILE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    $companyName = trim($_POST['company_name'] ?? "");
    $phone = trim($_POST['phone'] ?? "");
    $address = trim($_POST['address'] ?? "");
    $about = trim($_POST['about'] ?? "");

    if ($companyName === "") {
        $message = "Company Name is required.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE users SET company_name = ?, phone = ?, address = ?, about = ? WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param(
                "ssssi",
                $companyName,
                $phone,
                $address,
                $about,
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

/* GET CURRENT USER DATA */
$stmt = $conn->prepare("
    SELECT id, company_name, company_email, phone, address, about, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: Login.php");
    exit;
}

$userName = $user['company_name'];
$fullName = $user['company_name'];
$email = $user['company_email'];

$phone = !empty($user['phone'])
    ? $user['phone']
    : "Not added";

$address = !empty($user['address'])
    ? $user['address']
    : "Not added";

$about = !empty($user['about'])
    ? $user['about']
    : "Not added";

$memberSince = !empty($user['created_at'])
    ? date("d M Y", strtotime($user['created_at']))
    : "Not available";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Profile - Invoice Pro</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

:root {
    --black:#080808;
    --black-soft:#0d0d0d;
    --black-card:#111111;
    --black-hover:#151515;

    --gold:#d4af37;
    --gold-light:#f2d675;
    --gold-soft:rgba(212,175,55,0.08);
    --gold-border:rgba(212,175,55,0.24);
    --gold-border-hover:rgba(212,175,55,0.55);

    --white:#fff;
    --text:#eee;
    --muted:#888;

    --danger:#ef4444;
    --success:#22c55e;
}

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body {
    font-family:"Inter",sans-serif;
    background:var(--black);
    color:var(--text);
    min-height:100vh;
}

/* ================= NAVBAR ================= */

.navbar {
    height:68px;
    padding:0 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;

    background:rgba(8,8,8,0.96);
    border-bottom:1px solid var(--gold-border);

    position:sticky;
    top:0;
    z-index:1000;
}

.brand {
    display:flex;
    align-items:center;
    gap:10px;

    color:var(--white);
    text-decoration:none;

    font-size:17px;
    font-weight:700;
}

.brand-icon {
    width:34px;
    height:34px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:9px;

    color:var(--black);
    background:var(--gold);

    font-size:15px;
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

    text-decoration:none;

    transition:all 0.25s ease;

    cursor:pointer;
    white-space:nowrap;
}

.nav-link i {
    color:var(--gold);
    font-size:12px;
}

.nav-link:hover {
    color:var(--white);
    background:var(--gold-soft);
    border-color:var(--gold-border);
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

    transition:all 0.25s ease;

    cursor:pointer;
}

.nav-icon:hover {
    color:var(--black);
    background:var(--gold);
    border-color:var(--gold);

    transform:translateY(-1px);

    box-shadow:0 5px 18px rgba(212,175,55,0.20);
}

/* ================= PAGE ================= */

.profile-page {
    padding:40px 24px 60px;
}

.profile-container {
    max-width:1050px;
    margin:auto;
}

.page-header {
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:25px;
}

.header-icon {
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
}

.header-text h1 {
    color:var(--white);
    font-size:24px;
    margin-bottom:5px;
}

.header-text p {
    color:var(--muted);
    font-size:12px;
}

/* ================= MESSAGE ================= */

.message {
    padding:13px 15px;
    border-radius:10px;
    margin-bottom:20px;

    font-size:12px;
    font-weight:500;
}

.message.success {
    color:#86efac;
    background:rgba(34,197,94,0.08);
    border:1px solid rgba(34,197,94,0.20);
}

.message.error {
    color:#fca5a5;
    background:rgba(239,68,68,0.08);
    border:1px solid rgba(239,68,68,0.20);
}

/* ================= CARDS ================= */

.card {
    background:var(--black-card);

    border:1px solid rgba(255,255,255,0.07);

    border-radius:14px;

    padding:24px;

    margin-bottom:18px;

    transition:border-color 0.25s ease;
}

.card:hover {
    border-color:var(--gold-border);
}

/* ================= PROFILE TOP ================= */

.profile-top-card {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:25px;
}

.profile-info h2 {
    color:var(--white);
    font-size:19px;
    margin-bottom:13px;
}

.profile-info h2 i {
    color:var(--gold);
    font-size:12px;
}

.profile-details-list {
    display:flex;
    flex-wrap:wrap;
    gap:12px 20px;
}

.profile-detail-item {
    display:flex;
    align-items:center;
    gap:7px;

    color:var(--muted);

    font-size:11px;
}

.profile-detail-item i {
    color:var(--gold);
    width:14px;
    text-align:center;
}

/* ================= BUTTON ================= */

.btn-outline {
    height:39px;

    padding:0 15px;

    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;

    color:var(--gold);

    background:transparent;

    border:1px solid var(--gold-border);
    border-radius:9px;

    font-family:inherit;
    font-size:11px;
    font-weight:600;

    cursor:pointer;

    transition:all 0.25s ease;

    white-space:nowrap;
}

.btn-outline:hover {
    color:var(--black);
    background:var(--gold);
    border-color:var(--gold);
}

/* ================= SECTION ================= */

.card-title {
    display:flex;
    align-items:center;
    gap:9px;

    color:var(--white);

    font-size:15px;
    font-weight:600;

    margin-bottom:20px;
}

.card-title i {
    color:var(--gold);
    font-size:14px;
}

/* ================= INFO GRID ================= */

.info-grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:18px;
}

.info-item {
    padding:15px;

    border-radius:10px;

    background:var(--black-soft);

    border:1px solid rgba(255,255,255,0.05);
}

.info-label {
    color:var(--muted);

    font-size:10px;
    font-weight:600;

    text-transform:uppercase;

    letter-spacing:.6px;

    margin-bottom:7px;
}

.info-value {
    color:var(--text);

    font-size:12px;

    line-height:1.5;

    word-break:break-word;
}

.info-item.full {
    grid-column:1 / -1;
}

/* ================= MODAL ================= */

.modal-overlay {
    position:fixed;
    inset:0;

    display:none;
    align-items:center;
    justify-content:center;

    padding:20px;

    background:rgba(0,0,0,0.75);

    z-index:2000;
}

.modal-overlay.show {
    display:flex;
}

.modal {
    width:100%;
    max-width:520px;

    background:var(--black-card);

    border:1px solid var(--gold-border);

    border-radius:15px;

    box-shadow:0 20px 70px rgba(0,0,0,0.5);

    overflow:hidden;
}

.modal-header {
    padding:20px 22px;

    display:flex;
    align-items:center;
    justify-content:space-between;

    border-bottom:1px solid rgba(255,255,255,0.07);
}

.modal-header h2 {
    color:var(--white);
    font-size:16px;
}

.modal-close {
    width:32px;
    height:32px;

    display:flex;
    align-items:center;
    justify-content:center;

    border:0;
    border-radius:8px;

    color:var(--muted);
    background:transparent;

    cursor:pointer;

    transition:all .2s ease;
}

.modal-close:hover {
    color:var(--gold);
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

    padding:11px 12px;

    color:var(--text);

    background:var(--black-soft);

    border:1px solid rgba(255,255,255,0.09);

    border-radius:8px;

    outline:none;

    font-family:inherit;
    font-size:12px;

    transition:border-color .2s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color:var(--gold-border-hover);
}

.form-group input[readonly] {
    color:#777;
    cursor:not-allowed;
}

.form-group textarea {
    min-height:85px;
    resize:vertical;
}

.modal-footer {
    display:flex;
    justify-content:flex-end;
    gap:9px;

    margin-top:20px;
}

.btn-cancel {
    height:38px;
    padding:0 14px;

    color:var(--muted);

    background:transparent;

    border:1px solid rgba(255,255,255,0.09);
    border-radius:8px;

    font-family:inherit;
    font-size:11px;
    font-weight:600;

    cursor:pointer;
}

.btn-save {
    height:38px;
    padding:0 15px;

    color:var(--black);

    background:var(--gold);

    border:1px solid var(--gold);
    border-radius:8px;

    font-family:inherit;
    font-size:11px;
    font-weight:700;

    cursor:pointer;

    transition:all .2s ease;
}

.btn-save:hover {
    background:var(--gold-light);
}

/* ================= RESPONSIVE ================= */

@media (max-width:700px) {

    .navbar {
        padding:0 16px;
        height:62px;
    }

    .brand {
        font-size:14px;
    }

    .brand-icon {
        width:31px;
        height:31px;
    }

    .nav-right {
        gap:5px;
    }

    .nav-link {
        height:35px;
        padding:0 8px;
        gap:5px;
        font-size:10px;
        border-radius:8px;
    }

    .nav-link i {
        font-size:11px;
    }

    .nav-icon {
        width:35px;
        height:35px;
        border-radius:8px;
    }

    .profile-page {
        padding:28px 15px 45px;
    }

    .profile-top-card {
        flex-direction:column;
        align-items:flex-start;
    }

    .info-grid {
        grid-template-columns:1fr;
    }

    .info-item.full {
        grid-column:auto;
    }

    .profile-details-list {
        flex-direction:column;
        gap:9px;
    }
}

@media (max-width:520px) {

    .brand {
        font-size:13px;
    }

    .nav-link span {
        display:none;
    }

    .nav-link {
        width:35px;
        padding:0;
    }

    .header-text h1 {
        font-size:20px;
    }

    .card {
        padding:18px;
    }
}

</style>
</head>

<body>

<header class="navbar">

<a href="Dashboard.php" class="brand">
    <span class="brand-icon">
        <i class="fa-solid fa-file-invoice"></i>
    </span>
    Invoice Pro
</a>

<nav class="nav-right">

    <a href="Dashboard.php" class="nav-link" title="Dashboard">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="History_Invoice.php" class="nav-link" title="Invoices">
        <i class="fa-solid fa-file-invoice"></i>
        <span>Invoices</span>
    </a>

    <a href="Products.php" class="nav-link" title="Products">
        <i class="fa-solid fa-box"></i>
        <span>Products</span>
    </a>

    <div class="nav-icon"
         title="Change Theme"
         role="button"
         aria-label="Change Theme">
        <i class="fa-solid fa-moon"></i>
    </div>

    <a href="Logout.php"
       class="nav-icon"
       title="Logout"
       aria-label="Logout">
        <i class="fa-solid fa-right-from-bracket"></i>
    </a>

</nav>

</header>

<main class="profile-page">

<div class="profile-container">

    <div class="page-header">

        <div class="header-icon">
            <i class="fa-regular fa-user"></i>
        </div>

        <div class="header-text">
            <h1>My Profile</h1>
            <p>View and manage your profile information.</p>
        </div>

    </div>

    <?php if ($message !== ""): ?>

        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <!-- PROFILE SUMMARY -->

    <div class="card profile-top-card">

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
                    Member since <?php echo htmlspecialchars($memberSince); ?>
                </div>

            </div>

        </div>

        <button
            type="button"
            class="btn-outline"
            onclick="openEditProfile()">

            <i class="fa-solid fa-pen"></i>
            Edit Profile

        </button>

    </div>


    <!-- PERSONAL INFORMATION -->

    <div class="card">

        <div class="card-title">

            <i class="fa-solid fa-user"></i>

            Personal Information

        </div>

        <div class="info-grid">

            <div class="info-item">

                <div class="info-label">
                    Company Name
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($fullName); ?>
                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Email Address
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($email); ?>
                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Phone Number
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($phone); ?>
                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Address
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($address); ?>
                </div>

            </div>


            <div class="info-item full">

                <div class="info-label">
                    About
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($about); ?>
                </div>

            </div>

        </div>

    </div>

</div>

</main>


<!-- EDIT PROFILE MODAL -->

<div class="modal-overlay" id="editProfileModal">

    <div class="modal">

        <div class="modal-header">

            <h2>Edit Profile</h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeEditProfile()">

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <div class="modal-body">

            <form method="POST">

                <div class="form-group">

                    <label>Company Name</label>

                    <input
                        type="text"
                        name="company_name"
                        value="<?php echo htmlspecialchars($user['company_name']); ?>"
                        required>

                </div>


                <div class="form-group">

                    <label>Email Address</label>

                    <input
                        type="email"
                        value="<?php echo htmlspecialchars($user['company_email']); ?>"
                        readonly>

                </div>


                <div class="form-group">

                    <label>Phone Number</label>

                    <input
                        type="text"
                        name="phone"
                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">

                </div>


                <div class="form-group">

                    <label>Address</label>

                    <input
                        type="text"
                        name="address"
                        value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">

                </div>


                <div class="form-group">

                    <label>About</label>

                    <textarea name="about"><?php echo htmlspecialchars($user['about'] ?? ''); ?></textarea>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn-cancel"
                        onclick="closeEditProfile()">

                        Cancel

                    </button>

                    <button
                        type="submit"
                        name="update_profile"
                        class="btn-save">

                        <i class="fa-solid fa-check"></i>
                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

function openEditProfile() {
    document
        .getElementById("editProfileModal")
        .classList.add("show");
}

function closeEditProfile() {
    document
        .getElementById("editProfileModal")
        .classList.remove("show");
}

document
    .getElementById("editProfileModal")
    .addEventListener("click", function(event) {

        if (event.target === this) {
            closeEditProfile();
        }

    });

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeEditProfile();
    }

});

</script>

</body>
</html>