<?php

/* =====================================================
   DATABASE CONNECTION
===================================================== */

session_start();

require_once "config.php";


/* =====================================================
   LOGIN STATUS
===================================================== */

$isLoggedIn = isset($_SESSION['user_id']);

$userName = "";

if ($isLoggedIn) {

    $userId = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT company_name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        $userName = $user['company_name'];

    }

    $stmt->close();
}


/* =====================================================
   HOW IT WORKS DATA
===================================================== */

$steps = [

    [
        'no' => '01',
        'icon' => 'fa-user-plus',
        'color' => '#ef4444',
        'bg' => '#fef2f2',
        'title' => 'Create Your Account',
        'desc' => 'Sign up and get started in seconds.'
    ],

    [
        'no' => '02',
        'icon' => 'fa-file-invoice',
        'color' => '#3b82f6',
        'bg' => '#eff6ff',
        'title' => 'Create Your Bill',
        'desc' => 'Add customer and product details and generate your invoice.'
    ],

    [
        'no' => '03',
        'icon' => 'fa-chart-column',
        'color' => '#f97316',
        'bg' => '#fff7ed',
        'title' => 'Manage Your Business',
        'desc' => 'Track invoices, products and billing history from one place.'
    ]

];


/* =====================================================
   SAFE USER NAME
===================================================== */

$safeUserName = htmlspecialchars(
    $userName,
    ENT_QUOTES,
    'UTF-8'
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
        InvoicePro - Smart Billing for Modern Businesses
    </title>


    <!-- Google Font -->

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <style>

        /* =====================================================
           ROOT
        ===================================================== */

        :root {

            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #eff6ff;

            --text-dark: #0f172a;
            --text-body: #475569;
            --text-muted: #94a3b8;

            --bg-body: #f8fafc;
            --bg-white: #ffffff;

            --border: #e2e8f0;

            --success: #10b981;
            --orange: #f97316;
            --red: #ef4444;

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            margin: 0;
            padding: 0;

            box-sizing: border-box;

            font-family: 'Inter', sans-serif;

        }


        html {

            scroll-behavior: smooth;

        }


        body {

            background: var(--bg-white);

            color: var(--text-body);

            overflow-x: hidden;

        }


        a {

            text-decoration: none;

        }


        .container {

            width: 100%;

            max-width: 1240px;

            margin: 0 auto;

            padding: 0 28px;

        }



        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {

            position: sticky;

            top: 0;

            z-index: 1000;

            background: rgba(255,255,255,0.97);

            backdrop-filter: blur(12px);

            border-bottom: 1px solid #e8edf3;

        }


        .navbar-inner {

            min-height: 72px;

            display: flex;

            align-items: center;

        }


        .logo {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            color: #1e3a8a;

            font-size: 22px;

            font-weight: 800;

            white-space: nowrap;

        }


        .logo i {

            color: var(--primary);

            font-size: 25px;

        }


        .nav-links {

            display: flex;

            align-items: center;

            gap: 30px;

            margin-left: auto;

        }


        .nav-links a {

            position: relative;

            color: var(--text-body);

            font-size: 13px;

            font-weight: 600;

            padding: 27px 0;

            transition: 0.2s ease;

        }


        .nav-links a:hover,
        .nav-links a.active {

            color: var(--primary);

        }


        .nav-links a::after {

            content: "";

            position: absolute;

            left: 0;

            right: 0;

            bottom: 17px;

            height: 2px;

            border-radius: 10px;

            background: var(--primary);

            transform: scaleX(0);

            transition: 0.2s ease;

        }


        .nav-links a:hover::after,
        .nav-links a.active::after {

            transform: scaleX(1);

        }


        .nav-actions {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-left: 28px;

        }



        /* =====================================================
           BUTTONS
        ===================================================== */

        .btn {

            min-height: 42px;

            padding: 10px 20px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 700;

            transition: 0.25s ease;

        }


        .btn-outline {

            color: var(--primary);

            background: #ffffff;

            border: 1px solid #bfdbfe;

        }


        .btn-outline:hover {

            background: var(--primary-light);

            border-color: var(--primary);

            transform: translateY(-1px);

        }


        .btn-primary {

            color: #ffffff;

            background: var(--primary);

            border: 1px solid var(--primary);

            box-shadow: 0 8px 18px rgba(59,130,246,0.20);

        }


        .btn-primary:hover {

            background: var(--primary-dark);

            border-color: var(--primary-dark);

            transform: translateY(-2px);

            box-shadow: 0 12px 24px rgba(59,130,246,0.25);

        }



        /* =====================================================
           HERO
        ===================================================== */

        .hero {

            position: relative;

            overflow: hidden;

            background:
                radial-gradient(
                    circle at 88% 45%,
                    #edf4ff 0%,
                    #f7faff 25%,
                    #ffffff 55%
                );

        }


        .hero-inner {

            min-height: 610px;

            display: grid;

            grid-template-columns: 1fr 1fr;

            align-items: center;

            column-gap: 10px;

            padding-top: 50px;

            padding-bottom: 50px;

        }


        .hero-content {

            position: relative;

            z-index: 5;

            max-width: 600px;

        }


        .badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 7px 13px;

            margin-bottom: 20px;

            color: #ea580c;

            background: #fff7ed;

            border: 1px solid #fed7aa;

            border-radius: 30px;

            font-size: 11px;

            font-weight: 700;

        }


        .badge::before {

            content: "";

            width: 6px;

            height: 6px;

            border-radius: 50%;

            background: #f97316;

        }


        .hero h1 {

            max-width: 600px;

            color: var(--text-dark);

            font-size: clamp(44px, 4.3vw, 57px);

            line-height: 1.08;

            letter-spacing: -2.2px;

            font-weight: 800;

            margin-bottom: 21px;

        }


        .hero h1 span {

            color: var(--primary);

        }


        .hero-description {

            max-width: 550px;

            color: #52637a;

            font-size: 16px;

            line-height: 1.7;

            margin-bottom: 27px;

        }


        .hero-buttons {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 23px;

        }


        .hero-btn-large {

            min-height: 47px;

            padding: 12px 22px;

            border-radius: 10px;

            font-size: 14px;

        }


        .hero-features {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 10px;

            color: #64748b;

            font-size: 12px;

            font-weight: 600;

        }


        .hero-features span {

            display: flex;

            align-items: center;

            gap: 6px;

        }


        .hero-features i {

            width: 17px;

            height: 17px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: var(--primary-light);

            color: var(--primary);

            font-size: 8px;

        }


        .hero-divider {

            color: #cbd5e1;

        }



        /* =====================================================
           HERO VISUAL
        ===================================================== */

        .hero-visual {

            position: relative;

            width: 100%;

            height: 510px;

            display: flex;

            align-items: center;

            justify-content: center;

        }


        .mock-pedestal {

            position: absolute;

            left: 50%;

            bottom: 27px;

            transform: translateX(-50%);

            width: 455px;

            height: 68px;

            border-radius: 50%;

            background: #e5edff;

            box-shadow:
                0 25px 45px rgba(59,130,246,0.13),
                inset 0 -12px 20px rgba(255,255,255,0.85);

            z-index: 1;

        }


        .mock-invoice {

            position: absolute;

            left: 50%;

            top: 28px;

            transform: translateX(-50%);

            width: 320px;

            height: 425px;

            padding: 22px;

            display: flex;

            flex-direction: column;

            background: #ffffff;

            border: 1px solid #edf1f6;

            border-radius: 15px;

            box-shadow:
                0 30px 65px rgba(15,23,42,0.14),
                0 8px 20px rgba(59,130,246,0.07);

            z-index: 2;

        }


        .mock-inv-header {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 15px;

            padding-bottom: 13px;

            margin-bottom: 17px;

            border-bottom: 1px solid #f1f5f9;

        }


        .mock-inv-logo {

            color: #1e3a8a;

        }


        .mock-inv-logo i {

            color: var(--primary);

            font-size: 13px;

        }


        .mock-inv-logo span {

            font-size: 11px;

            font-weight: 800;

        }


        .mock-inv-title {

            margin-top: 7px;

            color: var(--text-dark);

            font-size: 15px;

            font-weight: 800;

        }


        .mock-inv-meta {

            color: var(--text-muted);

            font-size: 7px;

            line-height: 1.7;

            text-align: right;

        }


        .mock-inv-billto {

            color: var(--text-muted);

            font-size: 8px;

            line-height: 1.55;

            margin-bottom: 21px;

        }


        .mock-inv-billto strong {

            color: #334155;

        }


        .mock-inv-table {

            width: 100%;

            border-collapse: collapse;

            margin-bottom: 18px;

        }


        .mock-inv-table th {

            padding-bottom: 7px;

            border-bottom: 1px solid #e2e8f0;

            color: var(--text-muted);

            font-size: 7px;

            font-weight: 700;

            text-align: left;

        }


        .mock-inv-table td {

            padding: 8px 0;

            border-bottom: 1px solid #f8fafc;

            color: #334155;

            font-size: 7px;

        }


        .mock-inv-total {

            align-self: flex-end;

            display: flex;

            align-items: center;

            gap: 23px;

            color: var(--text-dark);

            font-size: 10px;

            font-weight: 800;

        }


        .mock-inv-signature {

            margin-top: auto;

            color: var(--text-dark);

            text-align: right;

            font-family: "Brush Script MT", cursive;

            font-size: 16px;

        }


        .thank-text {

            float: left;

            margin-top: 6px;

            color: var(--text-muted);

            font-family: 'Inter', sans-serif;

            font-size: 6px;

        }



        /* =====================================================
           FLOATING CARDS
        ===================================================== */

        .float-card {

            position: absolute;

            z-index: 5;

            background: #ffffff;

            border: 1px solid #eef2f7;

            border-radius: 13px;

            box-shadow: 0 15px 32px rgba(15,23,42,0.10);

            animation: float 5s ease-in-out infinite;

        }


        .float-rupee {

            top: 64px;

            left: 20px;

            width: 56px;

            height: 56px;

            display: flex;

            align-items: center;

            justify-content: center;

            border: none;

            border-radius: 50%;

            color: #ffffff;

            background: linear-gradient(135deg,#fca5a5,#f97316);

            font-size: 24px;

            box-shadow: 0 13px 25px rgba(249,115,22,0.24);

        }


        .float-amount {

            top: 45px;

            right: 8px;

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 11px 15px;

        }


        .float-amount-icon {

            width: 34px;

            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: var(--primary-light);

            color: var(--primary);

            font-size: 13px;

        }


        .float-amount-text p {

            color: var(--text-muted);

            font-size: 8px;

            margin-bottom: 2px;

        }


        .float-amount-text h4 {

            color: var(--text-dark);

            font-size: 15px;

        }


        .float-prof {

            top: 225px;

            left: 2px;

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 11px 14px;

        }


        .float-prof i {

            color: var(--primary);

            font-size: 17px;

        }


        .float-prof span {

            width: 80px;

            color: #334155;

            font-size: 9px;

            line-height: 1.35;

            font-weight: 700;

        }


        .float-growth {

            right: 3px;

            bottom: 88px;

            min-width: 118px;

            padding: 12px 14px;

        }


        .float-growth-title {

            margin-bottom: 6px;

            color: var(--text-muted);

            font-size: 8px;

            font-weight: 700;

        }


        .float-growth-val {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            color: var(--success);

            font-size: 15px;

            font-weight: 800;

        }


        .float-growth-bars {

            height: 24px;

            display: flex;

            align-items: flex-end;

            gap: 3px;

        }


        .float-growth-bars div {

            width: 5px;

            border-radius: 2px;

            background: #dbeafe;

        }


        .float-growth-bars div:nth-child(1) {

            height: 40%;

        }


        .float-growth-bars div:nth-child(2) {

            height: 60%;

        }


        .float-growth-bars div:nth-child(3) {

            height: 80%;

        }


        .float-growth-bars div:nth-child(4) {

            height: 100%;

            background: var(--primary);

        }


        .float-paid {

            left: 50%;

            bottom: 53px;

            transform: translateX(-50%);

            padding: 7px 18px;

            border: none;

            border-radius: 25px;

            color: #ffffff;

            background: var(--success);

            font-size: 11px;

            font-weight: 700;

            box-shadow: 0 10px 20px rgba(16,185,129,0.23);

        }


        @keyframes float {

            0%,100% {

                margin-top: 0;

            }

            50% {

                margin-top: -8px;

            }

        }



        /* =====================================================
           SECTION HEADINGS
        ===================================================== */

        .section-header {

            max-width: 700px;

            margin: 0 auto 45px;

            text-align: center;

        }


        .section-subtitle {

            display: inline-block;

            margin-bottom: 10px;

            color: var(--text-muted);

            font-size: 12px;

            font-weight: 600;

        }


        .section-title {

            margin-bottom: 12px;

            color: var(--text-dark);

            font-size: 32px;

            line-height: 1.25;

            letter-spacing: -0.7px;

            font-weight: 800;

        }


        .section-desc {

            max-width: 600px;

            margin: auto;

            color: var(--text-body);

            font-size: 14px;

            line-height: 1.7;

        }



        /* =====================================================
           HOW IT WORKS
        ===================================================== */

        .how-it-works {

            padding: 90px 0 100px;

            background: var(--bg-body);

        }


        .steps-container {

            position: relative;

            max-width: 980px;

            margin: auto;

            display: grid;

            grid-template-columns: repeat(3,1fr);

            gap: 25px;

        }


        .steps-container::before {

            content: "";

            position: absolute;

            top: 35px;

            left: 17%;

            right: 17%;

            height: 1px;

            background: #cbd5e1;

            z-index: 0;

        }


        .step-item {

            position: relative;

            z-index: 2;

            padding: 0 20px;

            text-align: center;

        }


        .step-icon {

            width: 70px;

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin: auto;

            margin-bottom: 16px;

            border: 5px solid #ffffff;

            border-radius: 50%;

            box-shadow: 0 7px 18px rgba(15,23,42,0.07);

            font-size: 22px;

        }


        .step-no {

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: 800;

        }


        .step-item h3 {

            margin-bottom: 9px;

            color: var(--text-dark);

            font-size: 16px;

        }


        .step-item p {

            color: var(--text-body);

            font-size: 13px;

            line-height: 1.65;

        }



        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {

            padding: 55px 0 25px;

            background: #ffffff;

            border-top: 1px solid var(--border);

        }


        .footer-grid {

            display: grid;

            grid-template-columns: 2fr 1fr 1fr 1.5fr;

            gap: 40px;

            padding-bottom: 35px;

            border-bottom: 1px solid var(--border);

        }


        .footer-brand .logo {

            margin-bottom: 12px;

        }


        .footer-description {

            max-width: 285px;

            color: var(--text-muted);

            font-size: 13px;

            line-height: 1.7;

        }


        .footer-col h4 {

            margin-bottom: 17px;

            color: var(--text-dark);

            font-size: 14px;

            font-weight: 700;

        }


        .footer-links {

            list-style: none;

            display: flex;

            flex-direction: column;

            gap: 11px;

        }


        .footer-links a {

            color: var(--text-body);

            font-size: 13px;

            transition: 0.2s ease;

        }


        .footer-links a:hover {

            color: var(--primary);

        }


        .footer-right {

            display: flex;

            flex-direction: column;

            justify-content: flex-end;

            align-items: flex-end;

            text-align: right;

        }


        .copyright {

            margin-bottom: 10px;

            color: var(--text-muted);

            font-size: 12px;

        }


        .footer-bottom-links {

            display: flex;

            align-items: center;

            justify-content: flex-end;

            flex-wrap: wrap;

            gap: 8px;

            color: var(--text-muted);

            font-size: 12px;

        }


        .footer-bottom-links a {

            color: var(--text-muted);

        }


        .footer-bottom-links a:hover {

            color: var(--primary);

        }



        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 1050px) {

            .container {

                padding: 0 22px;

            }


            .hero-inner {

                grid-template-columns: 0.95fr 1.05fr;

            }


            .hero h1 {

                font-size: 48px;

            }


            .hero-visual {

                transform: scale(0.95);

            }


            .float-rupee {

                left: 0;

            }


            .float-prof {

                left: -8px;

            }


            .float-amount {

                right: -5px;

            }


            .float-growth {

                right: -5px;

            }


            .footer-grid {

                grid-template-columns: 1.5fr 1fr 1fr;

            }


            .footer-right {

                grid-column: 1 / -1;

                align-items: flex-start;

                text-align: left;

            }


            .footer-bottom-links {

                justify-content: flex-start;

            }

        }



        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 800px) {

            .navbar-inner {

                min-height: 68px;

            }


            .nav-links {

                display: none;

            }


            .nav-actions {

                margin-left: auto;

            }


            .nav-actions .btn-primary {

                display: none;

            }


            .hero-inner {

                grid-template-columns: 1fr;

                min-height: auto;

                padding-top: 55px;

                padding-bottom: 25px;

                text-align: center;

            }


            .hero-content {

                max-width: 680px;

                margin: auto;

            }


            .hero-description {

                margin-left: auto;

                margin-right: auto;

            }


            .hero-buttons,
            .hero-features {

                justify-content: center;

            }


            .hero-visual {

                height: 455px;

                max-width: 650px;

                margin: -5px auto -25px;

                transform: scale(0.93);

            }


            .how-it-works {

                padding: 70px 0 80px;

            }


            .steps-container {

                grid-template-columns: 1fr;

                gap: 38px;

            }


            .steps-container::before {

                display: none;

            }


            .step-item {

                max-width: 420px;

                margin: auto;

            }


            .footer-grid {

                grid-template-columns: 1fr 1fr;

            }


            .footer-right {

                grid-column: 1 / -1;

                align-items: flex-start;

                text-align: left;

            }


            .footer-bottom-links {

                justify-content: flex-start;

            }

        }



        /* =====================================================
           SMALL MOBILE
        ===================================================== */

        @media (max-width: 560px) {

            .container {

                padding: 0 17px;

            }


            .logo {

                font-size: 20px;

            }


            .nav-actions .btn-outline {

                min-height: 39px;

                padding: 8px 14px;

            }


            .hero h1 {

                font-size: 37px;

                letter-spacing: -1.4px;

            }


            .hero-description {

                font-size: 14px;

            }


            .hero-buttons {

                flex-direction: column;

            }


            .hero-buttons .btn {

                width: 100%;

                max-width: 290px;

            }


            .hero-features {

                font-size: 11px;

            }


            .hero-visual {

                width: 130%;

                margin-left: -15%;

                height: 410px;

                margin-top: -20px;

                margin-bottom: -45px;

                transform: scale(0.78);

            }


            .how-it-works {

                padding: 65px 0 75px;

            }


            .section-title {

                font-size: 27px;

            }


            .footer-grid {

                grid-template-columns: 1fr;

            }


            .footer-right {

                grid-column: auto;

                align-items: flex-start;

                text-align: left;

            }


            .footer-bottom-links {

                justify-content: flex-start;

            }

        }

    </style>

</head>


<body>


    <!-- =====================================================
         NAVBAR
    ===================================================== -->

    <nav class="navbar">

        <div class="container navbar-inner">


            <a
                href="Main.php"
                class="logo"
            >

                <i class="fa-solid fa-file-invoice-dollar"></i>

                <span>
                    InvoicePro
                </span>

            </a>


            <div class="nav-links">

                <a
                    href="Main.php"
                    class="active"
                >
                    Home
                </a>


                <a href="#how-it-works">
                    How It Works
                </a>

            </div>


            <div class="nav-actions">


                <?php if ($isLoggedIn): ?>

                    <a
                        href="Dashboard.php"
                        class="btn btn-outline"
                    >
                        Dashboard
                    </a>


                    <a
                        href="Profile.php"
                        class="btn btn-primary"
                    >
                        <?php echo $safeUserName; ?>
                    </a>

                <?php else: ?>

                    <a
                        href="Login.php"
                        class="btn btn-outline"
                    >
                        Login
                    </a>


                    <a
                        href="Sign_Up.php"
                        class="btn btn-primary"
                    >
                        Get Started
                    </a>

                <?php endif; ?>


            </div>


        </div>

    </nav>



    <!-- =====================================================
         HERO SECTION
    ===================================================== -->

    <section
        class="hero"
        id="home"
    >

        <div class="container hero-inner">


            <!-- HERO CONTENT -->

            <div class="hero-content">


                <div class="badge">

                    Smart Billing for Modern Businesses

                </div>


                <h1>

                    Create Professional Invoices.
                    <span>Simplify Your</span> Business.

                </h1>


                <p class="hero-description">

                    InvoicePro helps you create, manage and track invoices
                    effortlessly — all in one simple and professional platform.

                </p>


                <div class="hero-buttons">


                    <?php if ($isLoggedIn): ?>

                        <a
                            href="Create_Invoice.php"
                            class="btn btn-primary hero-btn-large"
                        >

                            Create Invoice

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>


                        <a
                            href="Dashboard.php"
                            class="btn btn-outline hero-btn-large"
                        >

                            Dashboard

                        </a>

                    <?php else: ?>

                        <a
                            href="Sign_Up.php"
                            class="btn btn-primary hero-btn-large"
                        >

                            Get Started Free

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>


                        <a
                            href="Login.php"
                            class="btn btn-outline hero-btn-large"
                        >

                            Login

                        </a>

                    <?php endif; ?>


                </div>


                <div class="hero-features">


                    <span>

                        <i class="fa-solid fa-check"></i>

                        Simple

                    </span>


                    <span class="hero-divider">
                        •
                    </span>


                    <span>

                        <i class="fa-solid fa-check"></i>

                        Fast

                    </span>


                    <span class="hero-divider">
                        •
                    </span>


                    <span>

                        <i class="fa-solid fa-check"></i>

                        Professional

                    </span>


                </div>


            </div>



            <!-- HERO VISUAL -->

            <div class="hero-visual">


                <div class="mock-pedestal"></div>


                <!-- INVOICE -->

                <div class="mock-invoice">


                    <div class="mock-inv-header">


                        <div class="mock-inv-logo">

                            <i class="fa-solid fa-file-invoice-dollar"></i>

                            <span>
                                InvoicePro
                            </span>


                            <div class="mock-inv-title">
                                INVOICE
                            </div>

                        </div>


                        <div class="mock-inv-meta">

                            # INV-2026-001

                            <br>

                            Mar 15, 2026

                        </div>


                    </div>


                    <div class="mock-inv-billto">

                        <strong>
                            Bill To:
                        </strong>

                        <br>

                        ABC Enterprises

                        <br>

                        Ahmedabad, Gujarat

                    </div>


                    <table class="mock-inv-table">


                        <tr>

                            <th>
                                Description
                            </th>

                            <th>
                                Qty
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Amount
                            </th>

                        </tr>


                        <tr>

                            <td>
                                Website Design
                            </td>

                            <td>
                                1
                            </td>

                            <td>
                                ₹8,000
                            </td>

                            <td>
                                ₹8,000
                            </td>

                        </tr>


                        <tr>

                            <td>
                                Development
                            </td>

                            <td>
                                1
                            </td>

                            <td>
                                ₹12,000
                            </td>

                            <td>
                                ₹12,000
                            </td>

                        </tr>


                        <tr>

                            <td>
                                Maintenance
                            </td>

                            <td>
                                1
                            </td>

                            <td>
                                ₹5,000
                            </td>

                            <td>
                                ₹5,000
                            </td>

                        </tr>


                    </table>


                    <div class="mock-inv-total">

                        <span>
                            Total
                        </span>

                        <span>
                            ₹25,000
                        </span>

                    </div>


                    <div class="mock-inv-signature">

                        <span class="thank-text">

                            Thank you for your business!

                        </span>

                        Jay

                    </div>


                </div>



                <!-- RUPEE FLOATING CARD -->

                <div class="float-card float-rupee">

                    <i class="fa-solid fa-indian-rupee-sign"></i>

                </div>



                <!-- TOTAL AMOUNT -->

                <div class="float-card float-amount">


                    <div class="float-amount-icon">

                        <i class="fa-solid fa-arrow-trend-up"></i>

                    </div>


                    <div class="float-amount-text">

                        <p>
                            Total Amount
                        </p>

                        <h4>
                            ₹25,000
                        </h4>

                    </div>


                </div>



                <!-- PROFESSIONAL INVOICE -->

                <div class="float-card float-prof">


                    <i class="fa-regular fa-file-lines"></i>


                    <span>

                        Professional

                        <br>

                        Invoices

                    </span>


                </div>



                <!-- BUSINESS GROWTH -->

                <div class="float-card float-growth">


                    <div class="float-growth-title">

                        Business Growth

                    </div>


                    <div class="float-growth-val">


                        +48%


                        <div class="float-growth-bars">

                            <div></div>

                            <div></div>

                            <div></div>

                            <div></div>

                        </div>


                    </div>


                </div>



                <!-- PAID -->

                <div class="float-card float-paid">

                    <i class="fa-solid fa-check"></i>

                    Paid

                </div>


            </div>


        </div>

    </section>



    <!-- =====================================================
         HOW IT WORKS
    ===================================================== -->

    <section
        class="how-it-works"
        id="how-it-works"
    >

        <div class="container">


            <div class="section-header">


                <span class="section-subtitle">

                    Simple process. Powerful results.

                </span>


                <h2 class="section-title">

                    How InvoicePro Works

                </h2>


                <p class="section-desc">

                    Get your billing organized in just three simple steps.

                </p>


            </div>



            <div class="steps-container">


                <?php foreach ($steps as $s): ?>


                    <div class="step-item">


                        <div
                            class="step-icon"
                            style="
                                background-color: <?php echo htmlspecialchars($s['bg'], ENT_QUOTES, 'UTF-8'); ?>;
                                color: <?php echo htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8'); ?>;
                            "
                        >

                            <i
                                class="fa-solid <?php echo htmlspecialchars($s['icon'], ENT_QUOTES, 'UTF-8'); ?>"
                            ></i>

                        </div>


                        <div
                            class="step-no"
                            style="
                                color: <?php echo htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8'); ?>;
                            "
                        >

                            <?php echo htmlspecialchars($s['no'], ENT_QUOTES, 'UTF-8'); ?>

                        </div>


                        <h3>

                            <?php echo htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'); ?>

                        </h3>


                        <p>

                            <?php echo htmlspecialchars($s['desc'], ENT_QUOTES, 'UTF-8'); ?>

                        </p>


                    </div>


                <?php endforeach; ?>


            </div>


        </div>

    </section>



    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer class="footer">


        <div class="container">


            <div class="footer-grid">


                <!-- BRAND -->

                <div class="footer-col footer-brand">


                    <a
                        href="Main.php"
                        class="logo"
                    >

                        <i class="fa-solid fa-file-invoice-dollar"></i>

                        <span>
                            InvoicePro
                        </span>

                    </a>


                    <p class="footer-description">

                        A simple and powerful billing and invoice
                        management system for businesses.

                    </p>


                </div>



                <!-- QUICK LINKS -->

                <div class="footer-col">


                    <h4>
                        Quick Links
                    </h4>


                    <ul class="footer-links">


                        <li>

                            <a href="Main.php">
                                Home
                            </a>

                        </li>


                        <li>

                            <a href="#how-it-works">
                                How It Works
                            </a>

                        </li>


                    </ul>


                </div>



                <!-- ACCOUNT -->

                <div class="footer-col">


                    <h4>
                        Account
                    </h4>


                    <ul class="footer-links">


                        <?php if ($isLoggedIn): ?>

                            <li>

                                <a href="Dashboard.php">
                                    Dashboard
                                </a>

                            </li>


                            <li>

                                <a href="Profile.php">
                                    Profile
                                </a>

                            </li>

                        <?php else: ?>

                            <li>

                                <a href="Login.php">
                                    Login
                                </a>

                            </li>


                            <li>

                                <a href="Sign_Up.php">
                                    Sign Up
                                </a>

                            </li>

                        <?php endif; ?>


                    </ul>


                </div>



                <!-- COPYRIGHT -->

                <div class="footer-col footer-right">


                    <div class="copyright">

                        © 2026 InvoicePro. All rights reserved.

                    </div>


                    <div class="footer-bottom-links">


                        <a href="#">
                            Privacy Policy
                        </a>


                        <span>
                            |
                        </span>


                        <a href="#">
                            Terms of Service
                        </a>


                        <span>
                            |
                        </span>


                        <a href="#">
                            Contact
                        </a>


                    </div>


                </div>


            </div>


        </div>


    </footer>


</body>

</html>