<?php

session_start();

require_once "config.php";

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
         * Find user by email
         */

        $stmt = $conn->prepare(
            "SELECT id, company_name, company_email, password, phone, address
             FROM users
             WHERE company_email = ?
             LIMIT 1"
        );

        if ($stmt) {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                /*
                 * Verify password
                 */

                if (password_verify($password, $user['password'])) {

                    /*
                     * Login successful
                     */

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['company_name'] = $user['company_name'];
                    $_SESSION['company_email'] = $user['company_email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['address'] = $user['address'];
                    $_SESSION['logged_in'] = true;

                    /*
                     * Remember Me
                     *
                     * Session login is maintained normally.
                     * Persistent remember functionality can be
                     * added later if required.
                     */

                    header("Location: Dashboard.php");
                    exit();

                } else {

                    $error = "Incorrect email or password.";

                }

            } else {

                $error = "Incorrect email or password.";

            }

            $stmt->close();

        } else {

            $error = "Something went wrong. Please try again.";

        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>InvoicePro - Login</title>


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

        :root {

            --primary-blue: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #eff6ff;

            --text-dark: #0f172a;
            --text-body: #475569;
            --text-muted: #64748b;
            --text-light: #94a3b8;

            --bg-body: #f7f9fc;

            --border-color: #e2e8f0;

            --white: #ffffff;

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

            width: 100%;
            height: 100%;

        }


        body {

            width: 100%;
            min-height: 100vh;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    #eef5ff 0%,
                    transparent 28%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    #fff2ed 0%,
                    transparent 25%
                ),
                var(--bg-body);

            color: var(--text-body);

            overflow-x: hidden;

        }



        /* =====================================================
           MAIN WRAPPER
        ===================================================== */

        .main-wrapper {

            width: 100%;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px 20px;

        }



        /* =====================================================
           LOGIN CARD
        ===================================================== */

        .login-card {

            width: 100%;

            max-width: 440px;

            padding: 32px 38px 28px;

            background: rgba(255, 255, 255, 0.98);

            border: 1px solid rgba(226, 232, 240, 0.9);

            border-radius: 18px;

            box-shadow:
                0 20px 55px rgba(15, 23, 42, 0.08),
                0 4px 12px rgba(15, 23, 42, 0.03);

        }



        /* =====================================================
           LOGIN HEADER
        ===================================================== */

        .login-header {

            text-align: center;

            margin-bottom: 25px;

        }


        .brand {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            margin-bottom: 16px;

        }


        .login-icon {

            width: 52px;

            height: 52px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 14px;

            background: var(--primary-light);

            color: var(--primary-blue);

            font-size: 22px;

        }


        .brand-name {

            color: #1e3a8a;

            font-size: 24px;

            font-weight: 800;

            letter-spacing: -0.6px;

        }


        .brand-name span {

            color: var(--primary-blue);

        }


        .login-header h1 {

            color: var(--text-dark);

            font-size: 30px;

            line-height: 1.2;

            font-weight: 800;

            letter-spacing: -0.8px;

        }



        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {

            padding: 11px 13px;

            margin-bottom: 18px;

            border-radius: 9px;

            font-size: 13px;

            line-height: 1.5;

            text-align: center;

        }


        .alert-error {

            color: #dc2626;

            background: #fef2f2;

            border: 1px solid #fecaca;

        }


        .alert-success {

            color: #059669;

            background: #ecfdf5;

            border: 1px solid #a7f3d0;

        }



        /* =====================================================
           FORM GROUP
        ===================================================== */

        .form-group {

            margin-bottom: 18px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            color: var(--text-dark);

            font-size: 13px;

            font-weight: 700;

        }



        /* =====================================================
           INPUT WRAPPER
        ===================================================== */

        .input-wrapper {

            position: relative;

            width: 100%;

        }


        .input-icon {

            position: absolute;

            left: 15px;

            top: 50%;

            transform: translateY(-50%);

            color: var(--text-light);

            font-size: 15px;

            pointer-events: none;

            z-index: 2;

            transition: 0.2s ease;

        }


        .input-wrapper:focus-within .input-icon {

            color: var(--primary-blue);

        }



        /* =====================================================
           INPUT
        ===================================================== */

        .form-control {

            width: 100%;

            height: 47px;

            padding: 0 44px;

            color: var(--text-dark);

            background: #ffffff;

            border: 1px solid var(--border-color);

            border-radius: 9px;

            outline: none;

            font-size: 14px;

            font-weight: 500;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

        }


        .password-input {

            padding-right: 48px;

        }


        .form-control::placeholder {

            color: #a1adbd;

            font-weight: 400;

        }


        .form-control:hover {

            border-color: #cbd5e1;

        }


        .form-control:focus {

            border-color: var(--primary-blue);

            box-shadow:
                0 0 0 3px rgba(59, 130, 246, 0.10);

        }



        /* =====================================================
           PASSWORD TOGGLE
        ===================================================== */

        .password-toggle {

            position: absolute;

            right: 12px;

            top: 50%;

            transform: translateY(-50%);

            width: 32px;

            height: 32px;

            display: flex;

            align-items: center;

            justify-content: center;

            border: none;

            background: transparent;

            color: var(--text-light);

            font-size: 15px;

            cursor: pointer;

            border-radius: 6px;

            transition:
                color 0.2s ease,
                background 0.2s ease;

            z-index: 3;

        }


        .password-toggle:hover {

            color: var(--primary-blue);

            background: var(--primary-light);

        }


        .password-toggle:active {

            transform:
                translateY(-50%)
                scale(0.96);

        }


        .password-toggle:focus {

            outline: none;

        }



        /* =====================================================
           FORM ACTIONS
        ===================================================== */

        .form-actions {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-top: -2px;

            margin-bottom: 18px;

        }


        .checkbox-group {

            display: flex;

            align-items: center;

            gap: 7px;

            color: var(--text-muted);

            font-size: 12px;

            cursor: pointer;

        }


        .checkbox-group input {

            width: 14px;

            height: 14px;

            accent-color: var(--primary-blue);

            cursor: pointer;

        }


        .forgot-link {

            color: var(--primary-blue);

            text-decoration: none;

            font-size: 12px;

            font-weight: 600;

        }


        .forgot-link:hover {

            text-decoration: underline;

        }



        /* =====================================================
           LOGIN BUTTON
        ===================================================== */

        .btn-primary {

            width: 100%;

            height: 47px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            border: none;

            border-radius: 9px;

            background: var(--primary-blue);

            color: #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 8px 18px rgba(59, 130, 246, 0.18);

            transition:
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .btn-primary:hover {

            background: var(--primary-dark);

            transform: translateY(-1px);

            box-shadow:
                0 11px 22px rgba(59, 130, 246, 0.24);

        }


        .btn-primary:active {

            transform: translateY(0);

        }



        /* =====================================================
           DIVIDER
        ===================================================== */

        .divider {

            display: flex;

            align-items: center;

            gap: 13px;

            margin: 21px 0;

            color: var(--text-light);

            font-size: 10px;

            font-weight: 600;

        }


        .divider::before,
        .divider::after {

            content: "";

            flex: 1;

            height: 1px;

            background: var(--border-color);

        }



        /* =====================================================
           GOOGLE BUTTON
        ===================================================== */

        .btn-google {

            width: 100%;

            height: 45px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            border: 1px solid var(--border-color);

            border-radius: 9px;

            background: #ffffff;

            color: var(--text-dark);

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                transform 0.2s ease;

        }


        .btn-google:hover {

            background: #f8fafc;

            border-color: #cbd5e1;

            box-shadow:
                0 5px 14px rgba(15, 23, 42, 0.06);

            transform: translateY(-1px);

        }


        .btn-google:active {

            transform: translateY(0);

        }


        .btn-google svg {

            flex-shrink: 0;

        }



        /* =====================================================
           SIGN UP LINK
        ===================================================== */

        .signup-link {

            text-align: center;

            margin-top: 17px;

            color: var(--text-muted);

            font-size: 12px;

            line-height: 1.5;

        }


        .signup-link a {

            color: var(--primary-blue);

            text-decoration: none;

            font-weight: 700;

            margin-left: 3px;

        }


        .signup-link a:hover {

            text-decoration: underline;

        }



        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 600px) {

            .main-wrapper {

                padding: 20px 15px;

            }


            .login-card {

                max-width: 430px;

                padding: 30px 28px 27px;

            }

        }



        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 450px) {

            .main-wrapper {

                padding: 15px 12px;

            }


            .login-card {

                padding: 27px 20px 24px;

                border-radius: 15px;

            }


            .brand {

                gap: 8px;

                margin-bottom: 13px;

            }


            .login-icon {

                width: 47px;

                height: 47px;

                border-radius: 12px;

                font-size: 20px;

            }


            .brand-name {

                font-size: 22px;

            }


            .login-header {

                margin-bottom: 21px;

            }


            .login-header h1 {

                font-size: 27px;

            }

        }



        /* =====================================================
           SHORT HEIGHT SCREEN
        ===================================================== */

        @media (max-height: 700px) and (min-width: 601px) {

            .main-wrapper {

                padding: 12px 20px;

            }


            .login-card {

                padding-top: 22px;

                padding-bottom: 20px;

            }


            .brand {

                margin-bottom: 9px;

            }


            .login-icon {

                width: 44px;

                height: 44px;

                font-size: 18px;

            }


            .brand-name {

                font-size: 21px;

            }


            .login-header {

                margin-bottom: 17px;

            }


            .login-header h1 {

                font-size: 25px;

            }


            .form-group {

                margin-bottom: 12px;

            }


            .form-actions {

                margin-bottom: 13px;

            }


            .divider {

                margin: 14px 0;

            }


            .signup-link {

                margin-top: 12px;

            }

        }

    </style>

</head>


<body>


    <!-- =====================================================
         LOGIN PAGE
    ===================================================== -->

    <main class="main-wrapper">

        <div class="login-card">


            <!-- =================================================
                 LOGIN HEADER
            ================================================= -->

            <div class="login-header">

                <div class="brand">

                    <div class="login-icon">

                        <i class="fa-solid fa-file-invoice-dollar"></i>

                    </div>

                    <div class="brand-name">

                        Invoice<span>Pro</span>

                    </div>

                </div>


                <h1>

                    Welcome Back

                </h1>

            </div>



            <!-- =================================================
                 ERROR MESSAGE
            ================================================= -->

            <?php if(!empty($error)): ?>

                <div class="alert alert-error">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 SUCCESS MESSAGE
            ================================================= -->

            <?php if(!empty($success)): ?>

                <div class="alert alert-success">

                    <?php echo htmlspecialchars($success); ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 LOGIN FORM
            ================================================= -->

            <form
                method="POST"
                action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
            >


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">

                        Email Address

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-regular fa-envelope input-icon"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter your email"
                            autocomplete="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">

                        Password

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock input-icon"></i>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control password-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                            title="Show password"
                        >

                            <i
                                class="fa-regular fa-eye"
                                id="passwordIcon"
                            ></i>

                        </button>

                    </div>

                </div>



                <!-- REMEMBER / FORGOT PASSWORD -->

                <div class="form-actions">

                    <label class="checkbox-group">

                        <input
                            type="checkbox"
                            name="remember"
                        >

                        <span>

                            Remember Me

                        </span>

                    </label>


                    <a
                        href="#"
                        class="forgot-link"
                    >

                        Forgot Password?

                    </a>

                </div>



                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="btn-primary"
                >

                    Login

                    <i class="fa-solid fa-arrow-right"></i>

                </button>


            </form>



            <!-- =================================================
                 OR DIVIDER
            ================================================= -->

            <div class="divider">

                <span>

                    OR

                </span>

            </div>



            <!-- =================================================
                 GOOGLE LOGIN
            ================================================= -->

            <button
                type="button"
                class="btn-google"
            >

                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >

                    <path
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                        fill="#4285F4"
                    />

                    <path
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                        fill="#34A853"
                    />

                    <path
                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                        fill="#FBBC05"
                    />

                    <path
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                        fill="#EA4335"
                    />

                </svg>


                Continue with Google

            </button>



            <!-- =================================================
                 SIGN UP LINK
            ================================================= -->

            <div class="signup-link">

                Don't have an account?

                <a href="Sign_Up.php">

                    Sign Up

                </a>

            </div>


        </div>

    </main>



    <!-- =====================================================
         PASSWORD SHOW / HIDE
    ===================================================== -->

    <script>

        const passwordInput =
            document.getElementById("password");

        const passwordToggle =
            document.getElementById("passwordToggle");

        const passwordIcon =
            document.getElementById("passwordIcon");


        passwordToggle.addEventListener(
            "click",
            function () {

                if (passwordInput.type === "password") {

                    passwordInput.type = "text";

                    passwordIcon.classList.remove(
                        "fa-eye"
                    );

                    passwordIcon.classList.add(
                        "fa-eye-slash"
                    );

                    passwordToggle.setAttribute(
                        "aria-label",
                        "Hide password"
                    );

                    passwordToggle.setAttribute(
                        "title",
                        "Hide password"
                    );

                } else {

                    passwordInput.type = "password";

                    passwordIcon.classList.remove(
                        "fa-eye-slash"
                    );

                    passwordIcon.classList.add(
                        "fa-eye"
                    );

                    passwordToggle.setAttribute(
                        "aria-label",
                        "Show password"
                    );

                    passwordToggle.setAttribute(
                        "title",
                        "Show password"
                    );

                }

            }
        );

    </script>


</body>

</html>