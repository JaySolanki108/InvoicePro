<?php
session_start();
require_once "config.php";

$isLoggedIn = isset($_SESSION['user_id']);
$userName = "";

if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT company_name FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userName = $user['company_name'];
    }
    $stmt->close();
}

$steps = [
    [
        'no' => '01',
        'icon' => 'fa-user-plus',
        'title' => 'Create Your Account',
        'desc' => 'Sign up and get started in seconds.'
    ],
    [
        'no' => '02',
        'icon' => 'fa-file-invoice',
        'title' => 'Create Your Invoice',
        'desc' => 'Add customer and product details and generate your invoice.'
    ],
    [
        'no' => '03',
        'icon' => 'fa-chart-column',
        'title' => 'Manage Your Business',
        'desc' => 'Track invoices, products and billing history from one place.'
    ]
];

$safeUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice Pro - Smart Billing for Modern Businesses</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
    --black:#080808;
    --black-soft:#0d0d0d;
    --black-card:#111111;
    --black-hover:#151515;
    --gold:#d4af37;
    --gold-light:#f2d675;
    --gold-soft:rgba(212,175,55,.08);
    --gold-border:rgba(212,175,55,.24);
    --gold-border-hover:rgba(212,175,55,.55);
    --white:#fff;
    --text:#eee;
    --muted:#888;
    --success:#22c55e;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--text);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.container{width:100%;max-width:1270px;margin:0 auto;padding:0 45px}

/* NAVBAR */
.navbar{width:100%;height:76px;background:var(--black);border-bottom:1px solid var(--gold-border);position:sticky;top:0;z-index:1000}
.navbar-inner{height:76px;display:flex;align-items:center}
.logo{display:inline-flex;align-items:center;gap:12px;color:var(--gold-light);font-size:21px;font-weight:800;letter-spacing:-.5px;white-space:nowrap}
.logo i{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border:1px solid var(--gold);border-radius:10px;color:var(--gold);background:var(--gold-soft);font-size:17px}
.nav-links{display:flex;align-items:center;gap:30px;margin-left:auto}
.nav-links a{position:relative;color:#aaa;font-size:13px;font-weight:600;padding:30px 0;transition:.25s ease}
.nav-links a:hover,.nav-links a.active{color:var(--gold-light)}
.nav-links a::after{content:"";position:absolute;left:0;right:0;bottom:20px;height:2px;border-radius:10px;background:var(--gold);transform:scaleX(0);transition:.25s ease}
.nav-links a:hover::after,.nav-links a.active::after{transform:scaleX(1)}
.nav-actions{display:flex;align-items:center;gap:10px;margin-left:28px}
.btn{min-height:42px;padding:10px 20px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:9px;font-size:13px;font-weight:700;transition:.25s ease;cursor:pointer}
.btn-outline{color:var(--gold);background:var(--gold-soft);border:1px solid var(--gold-border)}
.btn-outline:hover{color:var(--black);background:var(--gold);border-color:var(--gold);transform:translateY(-1px);box-shadow:0 7px 20px rgba(212,175,55,.16)}
.btn-primary{color:var(--black);background:var(--gold);border:1px solid var(--gold);box-shadow:0 8px 22px rgba(212,175,55,.13)}
.btn-primary:hover{background:var(--gold-light);border-color:var(--gold-light);transform:translateY(-2px);box-shadow:0 12px 27px rgba(212,175,55,.22)}

/* HERO */
.hero{position:relative;overflow:hidden;background:radial-gradient(circle at 78% 40%,rgba(212,175,55,.08),transparent 32%),var(--black)}
.hero::before{content:"";position:absolute;width:600px;height:600px;right:-260px;top:-220px;border-radius:50%;background:rgba(212,175,55,.035);filter:blur(10px)}
.hero-inner{min-height:610px;display:grid;grid-template-columns:1fr 1fr;align-items:center;column-gap:15px;padding-top:48px;padding-bottom:48px}
.hero-content{position:relative;z-index:5;max-width:600px}
.badge{display:inline-flex;align-items:center;gap:8px;padding:7px 13px;margin-bottom:20px;color:var(--gold-light);background:var(--gold-soft);border:1px solid var(--gold-border);border-radius:30px;font-size:11px;font-weight:700}
.badge::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--gold);box-shadow:0 0 9px rgba(212,175,55,.55)}
.hero h1{max-width:600px;color:var(--white);font-size:clamp(42px,4.2vw,56px);line-height:1.08;letter-spacing:-2.2px;font-weight:800;margin-bottom:21px}
.hero h1 span{color:var(--gold)}
.hero-description{max-width:550px;color:#9b9b9b;font-size:16px;line-height:1.7;margin-bottom:27px}
.hero-buttons{display:flex;align-items:center;gap:12px;margin-bottom:23px}
.hero-btn-large{min-height:47px;padding:12px 22px;border-radius:10px;font-size:14px}
.hero-features{display:flex;align-items:center;flex-wrap:wrap;gap:10px;color:#858585;font-size:12px;font-weight:600}
.hero-features span{display:flex;align-items:center;gap:6px}
.hero-features i{width:17px;height:17px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--gold-soft);color:var(--gold);font-size:8px}
.hero-divider{color:#555}

/* HERO INVOICE */
.hero-visual{position:relative;width:100%;height:510px;display:flex;align-items:center;justify-content:center}
.mock-pedestal{position:absolute;left:50%;bottom:27px;transform:translateX(-50%);width:455px;height:68px;border-radius:50%;background:rgba(212,175,55,.08);box-shadow:0 25px 45px rgba(0,0,0,.35),inset 0 -12px 20px rgba(212,175,55,.05);z-index:1}
.mock-invoice{position:absolute;left:50%;top:28px;transform:translateX(-50%);width:320px;height:425px;padding:22px;display:flex;flex-direction:column;background:linear-gradient(145deg,#151515,#0e0e0e);border:1px solid var(--gold-border);border-radius:15px;box-shadow:0 30px 65px rgba(0,0,0,.5),0 8px 25px rgba(212,175,55,.06);z-index:2}
.mock-inv-header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding-bottom:13px;margin-bottom:17px;border-bottom:1px solid rgba(255,255,255,.08)}
.mock-inv-logo{color:var(--gold-light)}
.mock-inv-logo i{color:var(--gold);font-size:13px}
.mock-inv-logo span{font-size:11px;font-weight:800}
.mock-inv-title{margin-top:7px;color:#fff;font-size:15px;font-weight:800}
.mock-inv-meta{color:#777;font-size:7px;line-height:1.7;text-align:right}
.mock-inv-billto{color:#777;font-size:8px;line-height:1.55;margin-bottom:21px}
.mock-inv-billto strong{color:#ddd}
.mock-inv-table{width:100%;border-collapse:collapse;margin-bottom:18px}
.mock-inv-table th{padding-bottom:7px;border-bottom:1px solid rgba(255,255,255,.12);color:#777;font-size:7px;font-weight:700;text-align:left}
.mock-inv-table td{padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);color:#cfcfcf;font-size:7px}
.mock-inv-total{align-self:flex-end;display:flex;align-items:center;gap:23px;color:#fff;font-size:10px;font-weight:800}
.mock-inv-total span:last-child{color:var(--gold)}
.mock-inv-signature{margin-top:auto;color:var(--gold-light);text-align:right;font-family:"Brush Script MT",cursive;font-size:16px}
.thank-text{float:left;margin-top:6px;color:#666;font-family:'Inter',sans-serif;font-size:6px}

/* FLOATING CARDS */
.float-card{position:absolute;z-index:5;background:#111;border:1px solid var(--gold-border);border-radius:13px;box-shadow:0 15px 32px rgba(0,0,0,.35);animation:float 5s ease-in-out infinite}
.float-rupee{top:64px;left:20px;width:56px;height:56px;display:flex;align-items:center;justify-content:center;border:none;border-radius:50%;color:var(--black);background:linear-gradient(135deg,var(--gold-light),var(--gold));font-size:24px;box-shadow:0 13px 25px rgba(212,175,55,.20)}
.float-amount{top:45px;right:8px;display:flex;align-items:center;gap:10px;padding:11px 15px}
.float-amount-icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--gold-soft);color:var(--gold);font-size:13px}
.float-amount-text p{color:#777;font-size:8px;margin-bottom:2px}
.float-amount-text h4{color:#eee;font-size:15px}
.float-prof{top:225px;left:2px;display:flex;align-items:center;gap:10px;padding:11px 14px}
.float-prof i{color:var(--gold);font-size:17px}
.float-prof span{width:80px;color:#ccc;font-size:9px;line-height:1.35;font-weight:700}
.float-growth{right:3px;bottom:88px;min-width:118px;padding:12px 14px}
.float-growth-title{margin-bottom:6px;color:#777;font-size:8px;font-weight:700}
.float-growth-val{display:flex;align-items:flex-end;justify-content:space-between;color:var(--success);font-size:15px;font-weight:800}
.float-growth-bars{height:24px;display:flex;align-items:flex-end;gap:3px}
.float-growth-bars div{width:5px;border-radius:2px;background:#4b4021}
.float-growth-bars div:nth-child(1){height:40%}.float-growth-bars div:nth-child(2){height:60%}.float-growth-bars div:nth-child(3){height:80%}.float-growth-bars div:nth-child(4){height:100%;background:var(--gold)}
.float-paid{left:50%;bottom:53px;transform:translateX(-50%);padding:7px 18px;border:none;border-radius:25px;color:var(--black);background:var(--gold);font-size:11px;font-weight:700;box-shadow:0 10px 20px rgba(212,175,55,.18)}
@keyframes float{0%,100%{margin-top:0}50%{margin-top:-8px}}

/* HOW IT WORKS */
.how-it-works{padding:90px 0 100px;background:var(--black-soft);border-top:1px solid rgba(255,255,255,.04);border-bottom:1px solid rgba(255,255,255,.04)}
.section-header{max-width:700px;margin:0 auto 45px;text-align:center}
.section-subtitle{display:inline-block;margin-bottom:10px;color:var(--gold);font-size:12px;font-weight:600}
.section-title{margin-bottom:12px;color:#fff;font-size:32px;line-height:1.25;letter-spacing:-.7px;font-weight:800}
.section-desc{max-width:600px;margin:auto;color:#888;font-size:14px;line-height:1.7}
.steps-container{position:relative;max-width:980px;margin:auto;display:grid;grid-template-columns:repeat(3,1fr);gap:25px}
.steps-container::before{content:"";position:absolute;top:35px;left:17%;right:17%;height:1px;background:var(--gold-border);z-index:0}
.step-item{position:relative;z-index:2;padding:0 20px;text-align:center}
.step-icon{width:70px;height:70px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:1px solid var(--gold-border);border-radius:50%;box-shadow:0 7px 25px rgba(0,0,0,.25);background:var(--gold-soft);color:var(--gold);font-size:22px}
.step-no{margin-bottom:7px;color:var(--gold);font-size:13px;font-weight:800}
.step-item h3{margin-bottom:9px;color:#eee;font-size:16px}
.step-item p{color:#858585;font-size:13px;line-height:1.65}

/* FOOTER */
.footer{padding:55px 0 25px;background:var(--black);border-top:1px solid var(--gold-border)}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1.5fr;gap:40px;padding-bottom:35px;border-bottom:1px solid rgba(255,255,255,.07)}
.footer-brand .logo{margin-bottom:12px}
.footer-description{max-width:285px;color:#777;font-size:13px;line-height:1.7}
.footer-col h4{margin-bottom:17px;color:#eee;font-size:14px;font-weight:700}
.footer-links{list-style:none;display:flex;flex-direction:column;gap:11px}
.footer-links a{color:#858585;font-size:13px;transition:.2s ease}.footer-links a:hover{color:var(--gold)}
.footer-right{display:flex;flex-direction:column;justify-content:flex-end;align-items:flex-end;text-align:right}
.copyright{margin-bottom:10px;color:#666;font-size:12px}
.footer-bottom-links{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px;color:#666;font-size:12px}.footer-bottom-links a{color:#666}.footer-bottom-links a:hover{color:var(--gold)}

/* TABLET */
@media(max-width:1050px){
    .container{padding:0 28px}.hero-inner{grid-template-columns:.95fr 1.05fr}.hero h1{font-size:48px}.hero-visual{transform:scale(.95)}.float-rupee{left:0}.float-prof{left:-8px}.float-amount{right:-5px}.float-growth{right:-5px}.footer-grid{grid-template-columns:1.5fr 1fr 1fr}.footer-right{grid-column:1/-1;align-items:flex-start;text-align:left}.footer-bottom-links{justify-content:flex-start}
}
/* MOBILE */
@media(max-width:800px){
    .navbar,.navbar-inner{height:68px}.nav-links{display:none}.nav-actions{margin-left:auto}.nav-actions .btn-primary{display:none}.hero-inner{grid-template-columns:1fr;min-height:auto;padding-top:55px;padding-bottom:25px;text-align:center}.hero-content{max-width:680px;margin:auto}.hero-description{margin-left:auto;margin-right:auto}.hero-buttons,.hero-features{justify-content:center}.hero-visual{height:455px;max-width:650px;margin:-5px auto -25px;transform:scale(.93)}.how-it-works{padding:70px 0 80px}.steps-container{grid-template-columns:1fr;gap:38px}.steps-container::before{display:none}.step-item{max-width:420px;margin:auto}.footer-grid{grid-template-columns:1fr 1fr}.footer-right{grid-column:1/-1;align-items:flex-start;text-align:left}.footer-bottom-links{justify-content:flex-start}
}
@media(max-width:560px){
    .container{padding:0 17px}.logo{font-size:19px}.logo i{width:38px;height:38px}.nav-actions .btn-outline{min-height:39px;padding:8px 14px}.hero h1{font-size:37px;letter-spacing:-1.4px}.hero-description{font-size:14px}.hero-buttons{flex-direction:column}.hero-buttons .btn{width:100%;max-width:290px}.hero-features{font-size:11px}.hero-visual{width:130%;margin-left:-15%;height:410px;margin-top:-20px;margin-bottom:-45px;transform:scale(.78)}.how-it-works{padding:65px 0 75px}.section-title{font-size:27px}.footer-grid{grid-template-columns:1fr}.footer-right{grid-column:auto;align-items:flex-start;text-align:left}.footer-bottom-links{justify-content:flex-start}
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="container navbar-inner">
        <a href="Main.php" class="logo">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Invoice Pro</span>
        </a>
        <div class="nav-links">
            <a href="Main.php" class="active">Home</a>
            <a href="#how-it-works">How It Works</a>
        </div>
        <div class="nav-actions">
            <?php if ($isLoggedIn): ?>
                <a href="Dashboard.php" class="btn btn-outline">Dashboard</a>
                <a href="Profile.php" class="btn btn-primary"><?php echo $safeUserName; ?></a>
            <?php else: ?>
                <a href="Login.php" class="btn btn-outline">Login</a>
                <a href="Sign_Up.php" class="btn btn-primary">Get Started</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero" id="home">
    <div class="container hero-inner">
        <div class="hero-content">
            <div class="badge">Smart Billing for Modern Businesses</div>
            <h1>Create Professional Invoices. <span>Simplify Your</span> Business.</h1>
            <p class="hero-description">Invoice Pro helps you create, manage and track invoices effortlessly — all in one simple and professional platform.</p>
            <div class="hero-buttons">
                <?php if ($isLoggedIn): ?>
                    <a href="Create_Invoice.php" class="btn btn-primary hero-btn-large">Create Invoice <i class="fa-solid fa-arrow-right"></i></a>
                    <a href="Dashboard.php" class="btn btn-outline hero-btn-large">Dashboard</a>
                <?php else: ?>
                    <a href="Sign_Up.php" class="btn btn-primary hero-btn-large">Get Started Free <i class="fa-solid fa-arrow-right"></i></a>
                    <a href="Login.php" class="btn btn-outline hero-btn-large">Login</a>
                <?php endif; ?>
            </div>
            <div class="hero-features">
                <span><i class="fa-solid fa-check"></i>Simple</span>
                <span class="hero-divider">•</span>
                <span><i class="fa-solid fa-check"></i>Fast</span>
                <span class="hero-divider">•</span>
                <span><i class="fa-solid fa-check"></i>Professional</span>
            </div>
        </div>

        <div class="hero-visual">
            <div class="mock-pedestal"></div>
            <div class="mock-invoice">
                <div class="mock-inv-header">
                    <div class="mock-inv-logo">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Invoice Pro</span>
                        <div class="mock-inv-title">INVOICE</div>
                    </div>
                    <div class="mock-inv-meta"># INV-2026-001<br>Mar 15, 2026</div>
                </div>
                <div class="mock-inv-billto"><strong>Bill To:</strong><br>ABC Enterprises<br>Ahmedabad, Gujarat</div>
                <table class="mock-inv-table">
                    <tr><th>Description</th><th>Qty</th><th>Price</th><th>Amount</th></tr>
                    <tr><td>Website Design</td><td>1</td><td>₹8,000</td><td>₹8,000</td></tr>
                    <tr><td>Development</td><td>1</td><td>₹12,000</td><td>₹12,000</td></tr>
                    <tr><td>Maintenance</td><td>1</td><td>₹5,000</td><td>₹5,000</td></tr>
                </table>
                <div class="mock-inv-total"><span>Total</span><span>₹25,000</span></div>
                <div class="mock-inv-signature"><span class="thank-text">Thank you for your business!</span>Jay</div>
            </div>
            <div class="float-card float-rupee"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="float-card float-amount">
                <div class="float-amount-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div class="float-amount-text"><p>Total Amount</p><h4>₹25,000</h4></div>
            </div>
            <div class="float-card float-prof"><i class="fa-regular fa-file-lines"></i><span>Professional<br>Invoices</span></div>
            <div class="float-card float-growth">
                <div class="float-growth-title">Business Growth</div>
                <div class="float-growth-val">+48%<div class="float-growth-bars"><div></div><div></div><div></div><div></div></div></div>
            </div>
            <div class="float-card float-paid"><i class="fa-solid fa-check"></i> Paid</div>
        </div>
    </div>
</section>

<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Simple process. Powerful results.</span>
            <h2 class="section-title">How Invoice Pro Works</h2>
            <p class="section-desc">Get your billing organized in just three simple steps.</p>
        </div>
        <div class="steps-container">
            <?php foreach ($steps as $s): ?>
                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid <?php echo htmlspecialchars($s['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                    <div class="step-no"><?php echo htmlspecialchars($s['no'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <h3><?php echo htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($s['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-brand">
                <a href="Main.php" class="logo"><i class="fa-solid fa-file-invoice-dollar"></i><span>Invoice Pro</span></a>
                <p class="footer-description">A simple and powerful billing and invoice management system for businesses.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="Main.php">Home</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul class="footer-links">
                    <?php if ($isLoggedIn): ?>
                        <li><a href="Dashboard.php">Dashboard</a></li>
                        <li><a href="Profile.php">Profile</a></li>
                    <?php else: ?>
                        <li><a href="Login.php">Login</a></li>
                        <li><a href="Sign_Up.php">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-col footer-right">
                <div class="copyright">© 2026 Invoice Pro. All rights reserved.</div>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a><span>|</span><a href="#">Terms of Service</a><span>|</span><a href="#">Contact</a>
                </div>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
