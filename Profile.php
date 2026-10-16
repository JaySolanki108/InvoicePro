
<?php
session_start();
require_once "config.php";

/* AUTHENTICATION */
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

if (!isset($db) || !($db instanceof PDO)) {
    http_response_code(500);
    exit("Database connection is not configured correctly.");
}

$userId = (int) $_SESSION['user_id'];
$message = "";
$messageType = "";

/* ESCAPE OUTPUT */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* CSRF TOKEN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/* FLASH MESSAGE */
if (isset($_SESSION['profile_flash'])) {
    $message = $_SESSION['profile_flash']['message'] ?? "";
    $messageType = $_SESSION['profile_flash']['type'] ?? "success";
    unset($_SESSION['profile_flash']);
}

/* UPDATE PROFILE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    $postedToken = $_POST['csrf_token'] ?? "";

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $message = "Your session token is invalid. Please refresh the page and try again.";
        $messageType = "error";
    } else {
        $companyName = trim($_POST['company_name'] ?? "");
        $phone = trim($_POST['phone'] ?? "");
        $address = trim($_POST['address'] ?? "");
        $about = trim($_POST['about'] ?? "");

        if ($companyName === "") {
            $message = "Company Name is required.";
            $messageType = "error";
        } elseif (mb_strlen($companyName) > 150) {
            $message = "Company Name must not exceed 150 characters.";
            $messageType = "error";
        } elseif (mb_strlen($phone) > 30) {
            $message = "Phone Number must not exceed 30 characters.";
            $messageType = "error";
        } elseif (mb_strlen($address) > 500) {
            $message = "Address must not exceed 500 characters.";
            $messageType = "error";
        } elseif (mb_strlen($about) > 2000) {
            $message = "About must not exceed 2000 characters.";
            $messageType = "error";
        } else {
            try {
                /*
                 * Update only the currently authenticated user's profile.
                 * Company email is intentionally not editable here.
                 */
                $stmt = $db->prepare(
                    "UPDATE users
                     SET company_name = :company_name,
                         phone = :phone,
                         address = :address,
                         about = :about
                     WHERE id = :user_id"
                );

                $stmt->execute([
                    ':company_name' => $companyName,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':about' => $about,
                    ':user_id' => $userId
                ]);

                $_SESSION['profile_flash'] = [
                    'message' => "Profile updated successfully.",
                    'type' => "success"
                ];

                /* Prevent duplicate form submission */
                header("Location: Profile.php");
                exit;
            } catch (PDOException $exception) {
                error_log("Profile update error: " . $exception->getMessage());

                $message = "Unable to update profile. Please try again.";
                $messageType = "error";
            }
        }
    }
}

/* GET CURRENT USER DATA */
try {
    $stmt = $db->prepare(
        "SELECT id, company_name, company_email, phone, address, about, created_at
         FROM users
         WHERE id = :user_id
         LIMIT 1"
    );

    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log("Profile fetch error: " . $exception->getMessage());

    http_response_code(500);
    exit("Unable to load your profile right now.");
}

if (!$user) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $cookieParams = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $cookieParams["path"],
            $cookieParams["domain"],
            $cookieParams["secure"],
            $cookieParams["httponly"]
        );
    }

    session_destroy();
    header("Location: Login.php");
    exit;
}

/* PROFILE VALUES */
$fullName = $user['company_name'] ?? "";
$email = $user['company_email'] ?? "";

$phone = trim((string)($user['phone'] ?? "")) !== ""
    ? $user['phone']
    : "Not added";

$address = trim((string)($user['address'] ?? "")) !== ""
    ? $user['address']
    : "Not added";

$about = trim((string)($user['about'] ?? "")) !== ""
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
<meta name="robots" content="noindex, nofollow">

<title>Profile - Invoice Pro</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --black:#080808;
    --black-soft:#0d0d0d;
    --black-card:#111111;
    --gold:#d4af37;
    --gold-light:#f2d675;
    --gold-soft:rgba(212,175,55,.08);
    --gold-border:rgba(212,175,55,.24);
    --gold-border-hover:rgba(212,175,55,.55);
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

/* NAVBAR */
.navbar {
    min-height:68px;
    padding:0 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    background:rgba(8,8,8,.96);
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
    white-space:nowrap;
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
    min-height:39px;
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
    transition:all .25s ease;
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
    background:rgba(212,175,55,.06);
    transition:all .25s ease;
    cursor:pointer;
    text-decoration:none;
}

.nav-icon:hover {
    color:var(--black);
    background:var(--gold);
    border-color:var(--gold);
    transform:translateY(-1px);
    box-shadow:0 5px 18px rgba(212,175,55,.2);
}

/* PAGE */
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
    flex-shrink:0;
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
    line-height:1.6;
}

/* MESSAGES */
.message {
    padding:13px 15px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:12px;
    font-weight:500;
    line-height:1.6;
}

.message.success {
    color:#86efac;
    background:rgba(34,197,94,.08);
    border:1px solid rgba(34,197,94,.2);
}

.message.error {
    color:#fca5a5;
    background:rgba(239,68,68,.08);
    border:1px solid rgba(239,68,68,.2);
}

/* CARDS */
.card {
    background:var(--black-card);
    border:1px solid rgba(255,255,255,.07);
    border-radius:14px;
    padding:24px;
    margin-bottom:18px;
    transition:border-color .25s ease;
}

.card:hover {
    border-color:var(--gold-border);
}

/* PROFILE SUMMARY */
.profile-top-card {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:25px;
}

.profile-info {
    min-width:0;
}

.profile-info h2 {
    color:var(--white);
    font-size:19px;
    margin-bottom:13px;
    overflow-wrap:anywhere;
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
    overflow-wrap:anywhere;
}

.profile-detail-item i {
    color:var(--gold);
    width:14px;
    flex-shrink:0;
    text-align:center;
}

/* BUTTONS */
.btn-outline {
    min-height:39px;
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
    transition:all .25s ease;
    white-space:nowrap;
}

.btn-outline:hover {
    color:var(--black);
    background:var(--gold);
    border-color:var(--gold);
}

/* SECTION */
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

/* INFORMATION GRID */
.info-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
}

.info-item {
    min-width:0;
    padding:15px;
    border-radius:10px;
    background:var(--black-soft);
    border:1px solid rgba(255,255,255,.05);
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
    line-height:1.7;
    white-space:pre-wrap;
    overflow-wrap:anywhere;
}

.info-item.full {
    grid-column:1 / -1;
}

/* MODAL */
.modal-overlay {
    position:fixed;
    inset:0;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(0,0,0,.75);
    z-index:2000;
    overflow-y:auto;
}

.modal-overlay.show {
    display:flex;
}

.modal {
    width:100%;
    max-width:520px;
    max-height:calc(100vh - 40px);
    overflow-y:auto;
    background:var(--black-card);
    border:1px solid var(--gold-border);
    border-radius:15px;
    box-shadow:0 20px 70px rgba(0,0,0,.5);
}

.modal-header {
    padding:20px 22px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    border-bottom:1px solid rgba(255,255,255,.07);
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
    border:1px solid rgba(255,255,255,.09);
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
    flex-wrap:wrap;
    gap:9px;
    margin-top:20px;
}

.btn-cancel,
.btn-save {
    min-height:38px;
    padding:0 15px;
    border-radius:8px;
    font-family:inherit;
    font-size:11px;
    font-weight:600;
    cursor:pointer;
}

.btn-cancel {
    color:var(--muted);
    background:transparent;
    border:1px solid rgba(255,255,255,.09);
}

.btn-save {
    color:var(--black);
    background:var(--gold);
    border:1px solid var(--gold);
    font-weight:700;
}

.btn-save:hover {
    background:var(--gold-light);
}

button:focus-visible,
a:focus-visible,
input:focus-visible,
textarea:focus-visible {
    outline:2px solid var(--gold);
    outline-offset:3px;
}

/* RESPONSIVE */
@media (max-width:700px) {
    .navbar {
        padding:0 16px;
        min-height:62px;
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
        min-height:35px;
        padding:0 8px;
        gap:5px;
        font-size:10px;
    }

    .nav-icon {
        width:35px;
        height:35px;
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
        gap:7px;
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

    .modal-overlay {
        padding:12px;
    }

    .modal {
        max-height:calc(100vh - 24px);
    }

    .modal-body {
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

    <nav class="nav-right" aria-label="Main navigation">
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

        <button type="button" class="nav-icon" title="Change Theme"
                aria-label="Change Theme" id="themeToggle">
            <i class="fa-solid fa-moon"></i>
        </button>

        <a href="Logout.php" class="nav-icon" title="Logout"
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
            <div class="message <?= e($messageType) ?>"
                 role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <!-- PROFILE SUMMARY -->
        <section class="card profile-top-card">
            <div class="profile-info">
                <h2>
                    <?= e($fullName) ?>
                    <i class="fa-solid fa-circle-check" aria-label="Profile"></i>
                </h2>

                <div class="profile-details-list">
                    <div class="profile-detail-item">
                        <i class="fa-regular fa-envelope"></i>
                        <span><?= e($email) ?></span>
                    </div>

                    <div class="profile-detail-item">
                        <i class="fa-solid fa-phone"></i>
                        <span><?= e($phone) ?></span>
                    </div>

                    <div class="profile-detail-item">
                        <i class="fa-regular fa-calendar"></i>
                        <span>Member since <?= e($memberSince) ?></span>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-outline" onclick="openEditProfile()">
                <i class="fa-solid fa-pen"></i>
                Edit Profile
            </button>
        </section>

        <!-- PERSONAL INFORMATION -->
        <section class="card">
            <div class="card-title">
                <i class="fa-solid fa-user"></i>
                Personal Information
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Company Name</div>
                    <div class="info-value"><?= e($fullName) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?= e($email) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?= e($phone) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?= e($address) ?></div>
                </div>

                <div class="info-item full">
                    <div class="info-label">About</div>
                    <div class="info-value"><?= e($about) ?></div>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- EDIT PROFILE MODAL -->
<div class="modal-overlay" id="editProfileModal"
     aria-hidden="true">

    <div class="modal" role="dialog" aria-modal="true"
         aria-labelledby="editProfileTitle">

        <div class="modal-header">
            <h2 id="editProfileTitle">Edit Profile</h2>

            <button type="button" class="modal-close"
                    onclick="closeEditProfile()" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <form method="POST" action="Profile.php" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input
                        type="text"
                        id="company_name"
                        name="company_name"
                        maxlength="150"
                        value="<?= e($user['company_name']) ?>"
                        autocomplete="organization"
                        required>
                </div>

                <div class="form-group">
                    <label for="company_email">Email Address</label>
                    <input
                        type="email"
                        id="company_email"
                        value="<?= e($user['company_email']) ?>"
                        readonly>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="30"
                        value="<?= e($user['phone'] ?? '') ?>"
                        autocomplete="tel">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input
                        type="text"
                        id="address"
                        name="address"
                        maxlength="500"
                        value="<?= e($user['address'] ?? '') ?>"
                        autocomplete="street-address">
                </div>

                <div class="form-group">
                    <label for="about">About</label>
                    <textarea
                        id="about"
                        name="about"
                        maxlength="2000"><?= e($user['about'] ?? '') ?></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel"
                            onclick="closeEditProfile()">
                        Cancel
                    </button>

                    <button type="submit" name="update_profile"
                            value="1" class="btn-save" id="saveProfileButton">
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
    const modal = document.getElementById("editProfileModal");

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");

    const companyName = document.getElementById("company_name");
    if (companyName) {
        companyName.focus();
    }
}

function closeEditProfile() {
    const modal = document.getElementById("editProfileModal");

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

document.getElementById("editProfileModal").addEventListener("click", function(event) {
    if (event.target === this) {
        closeEditProfile();
    }
});

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeEditProfile();
    }
});

/* Prevent accidental repeated clicks while submitting */
document.getElementById("profileForm").addEventListener("submit", function() {
    const button = document.getElementById("saveProfileButton");

    if (this.checkValidity()) {
        button.disabled = true;
        button.style.opacity = "0.7";
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    }
});

/* Theme button placeholder: connect this to your shared theme system if available */
document.getElementById("themeToggle").addEventListener("click", function() {
    // Add your existing site-wide theme switching logic here.
});
</script>

</body>
</html>
