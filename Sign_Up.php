<?php

$error = '';
$success = '';

require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        empty($company_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        /*
         * Check whether email already exists
         */

        $check_stmt = $conn->prepare(
            "SELECT id FROM users WHERE company_email = ? LIMIT 1"
        );

        if ($check_stmt) {

            $check_stmt->bind_param("s", $email);

            $check_stmt->execute();

            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {

                $error = "An account with this email already exists.";

            } else {

                /*
                 * Securely hash the password
                 */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                 * Insert new user
                 */

                $insert_stmt = $conn->prepare(
                    "INSERT INTO users
                    (company_name, company_email, password)
                    VALUES (?, ?, ?)"
                );

                if ($insert_stmt) {

                    $insert_stmt->bind_param(
                        "sss",
                        $company_name,
                        $email,
                        $hashed_password
                    );

                    if ($insert_stmt->execute()) {

                        /*
                         * Account created successfully
                         * Redirect to Login page
                         */

                        header("Location: Login.php");
                        exit();

                    } else {

                        $error = "Unable to create account. Please try again.";

                    }

                    $insert_stmt->close();

                } else {

                    $error = "Something went wrong. Please try again.";

                }
            }

            $check_stmt->close();

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

    <title>InvoicePro - Sign Up</title>


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


        html,
        body {

            width: 100%;
            min-height: 100%;

        }


        body {

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

            padding: 15px 20px;

        }



        /* =====================================================
           SIGN UP CARD
        ===================================================== */

        .signup-card {

            width: 100%;

            max-width: 420px;

            padding: 22px 32px 20px;

            background: rgba(255, 255, 255, 0.98);

            border: 1px solid rgba(226, 232, 240, 0.9);

            border-radius: 17px;

            box-shadow:
                0 18px 45px rgba(15, 23, 42, 0.08),
                0 4px 12px rgba(15, 23, 42, 0.03);

        }



        /* =====================================================
           SIGN UP HEADER
        ===================================================== */

        .signup-header {

            text-align: center;

            margin-bottom: 17px;

        }


        .brand {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            margin-bottom: 9px;

        }


        .signup-icon {

            width: 43px;

            height: 43px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 11px;

            background: var(--primary-light);

            color: var(--primary-blue);

            font-size: 18px;

        }


        .brand-name {

            color: #1e3a8a;

            font-size: 21px;

            font-weight: 800;

            letter-spacing: -0.5px;

        }


        .brand-name span {

            color: var(--primary-blue);

        }


        .signup-header h1 {

            color: var(--text-dark);

            font-size: 25px;

            line-height: 1.2;

            font-weight: 800;

            letter-spacing: -0.6px;

        }



        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {

            padding: 8px 11px;

            margin-bottom: 13px;

            border-radius: 8px;

            font-size: 12px;

            line-height: 1.4;

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

            margin-bottom: 11px;

        }


        .form-group label {

            display: block;

            margin-bottom: 5px;

            color: var(--text-dark);

            font-size: 12px;

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

            left: 13px;

            top: 50%;

            transform: translateY(-50%);

            color: var(--text-light);

            font-size: 14px;

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

            height: 41px;

            padding: 0 40px;

            color: var(--text-dark);

            background: #ffffff;

            border: 1px solid var(--border-color);

            border-radius: 8px;

            outline: none;

            font-size: 13px;

            font-weight: 500;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

        }


        .password-input {

            padding-right: 45px;

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
                0 0 0 3px rgba(59, 130, 246, 0.09);

        }



        /* =====================================================
           PASSWORD TOGGLE
        ===================================================== */

        .password-toggle {

            position: absolute;

            right: 7px;

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

            font-size: 14px;

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
                scale(0.95);

        }


        .password-toggle:focus {

            outline: none;

        }



        /* =====================================================
           CREATE ACCOUNT BUTTON
        ===================================================== */

        .btn-primary {

            width: 100%;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            margin-top: 4px;

            border: none;

            border-radius: 8px;

            background: var(--primary-blue);

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 7px 16px rgba(59, 130, 246, 0.18);

            transition:
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .btn-primary:hover {

            background: var(--primary-dark);

            transform: translateY(-1px);

            box-shadow:
                0 9px 19px rgba(59, 130, 246, 0.23);

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

            gap: 11px;

            margin: 14px 0;

            color: var(--text-light);

            font-size: 9px;

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

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            border: 1px solid var(--border-color);

            border-radius: 8px;

            background: #ffffff;

            color: var(--text-dark);

            font-size: 12px;

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
                0 5px 13px rgba(15, 23, 42, 0.05);

            transform: translateY(-1px);

        }


        .btn-google:active {

            transform: translateY(0);

        }


        .btn-google svg {

            flex-shrink: 0;

        }



        /* =====================================================
           LOGIN LINK
        ===================================================== */

        .login-link {

            margin-top: 14px;

            text-align: center;

            color: var(--text-muted);

            font-size: 11px;

            line-height: 1.4;

        }


        .login-link a {

            color: var(--primary-blue);

            text-decoration: none;

            font-weight: 700;

            margin-left: 3px;

        }


        .login-link a:hover {

            text-decoration: underline;

        }



        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 450px) {

            .main-wrapper {

                padding: 12px;

            }


            .signup-card {

                max-width: 410px;

                padding: 21px 21px 19px;

                border-radius: 15px;

            }


            .signup-header {

                margin-bottom: 16px;

            }


            .brand {

                margin-bottom: 8px;

            }


            .signup-icon {

                width: 41px;

                height: 41px;

                font-size: 17px;

            }


            .brand-name {

                font-size: 20px;

            }


            .signup-header h1 {

                font-size: 24px;

            }

        }



        /* =====================================================
           SHORT HEIGHT SCREEN
        ===================================================== */

        @media (max-height: 680px) {

            .main-wrapper {

                padding: 8px 15px;

            }


            .signup-card {

                padding: 17px 30px 15px;

            }


            .signup-header {

                margin-bottom: 13px;

            }


            .brand {

                margin-bottom: 6px;

            }


            .signup-icon {

                width: 38px;

                height: 38px;

                font-size: 16px;

            }


            .brand-name {

                font-size: 19px;

            }


            .signup-header h1 {

                font-size: 23px;

            }


            .form-group {

                margin-bottom: 8px;

            }


            .form-group label {

                margin-bottom: 4px;

            }


            .form-control {

                height: 38px;

            }


            .password-toggle {

                height: 29px;

                width: 29px;

            }


            .btn-primary {

                height: 39px;

            }


            .divider {

                margin: 11px 0;

            }


            .btn-google {

                height: 37px;

            }


            .login-link {

                margin-top: 10px;

            }

        }

    </style>

</head>


<body>


    <main class="main-wrapper">

        <div class="signup-card">


            <!-- =================================================
                 INVOICEPRO BRAND
            ================================================= -->

            <div class="signup-header">

                <div class="brand">

                    <div class="signup-icon">

                        <i class="fa-solid fa-file-invoice-dollar"></i>

                    </div>

                    <div class="brand-name">

                        Invoice<span>Pro</span>

                    </div>

                </div>


                <h1>

                    Sign Up

                </h1>

            </div>



            <!-- =================================================
                 ERROR MESSAGE
            ================================================= -->

            <?php if (!empty($error)): ?>

                <div class="alert alert-error">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 SUCCESS MESSAGE
            ================================================= -->

            <?php if (!empty($success)): ?>

                <div class="alert alert-success">

                    <?php echo htmlspecialchars($success); ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 SIGN UP FORM
            ================================================= -->

            <form
                method="POST"
                action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
            >


                <!-- COMPANY NAME -->

                <div class="form-group">

                    <label for="company_name">

                        Company Name

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-building input-icon"></i>

                        <input
                            type="text"
                            id="company_name"
                            name="company_name"
                            class="form-control"
                            placeholder="Enter your company name"
                            autocomplete="organization"
                            value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- COMPANY EMAIL -->

                <div class="form-group">

                    <label for="email">

                        Company Email

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-regular fa-envelope input-icon"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter your company email"
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
                            placeholder="Create a password"
                            autocomplete="new-password"
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



                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirm_password">

                        Confirm Password

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock input-icon"></i>


                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control password-input"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="confirmPasswordToggle"
                            aria-label="Show password"
                            title="Show password"
                        >

                            <i
                                class="fa-regular fa-eye"
                                id="confirmPasswordIcon"
                            ></i>

                        </button>

                    </div>

                </div>



                <!-- CREATE ACCOUNT -->

                <button
                    type="submit"
                    class="btn-primary"
                >

                    Create Account

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
                 GOOGLE SIGN UP
            ================================================= -->

            <button
                type="button"
                class="btn-google"
            >

                <svg
                    width="17"
                    height="17"
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
                 LOGIN LINK
            ================================================= -->

            <div class="login-link">

                Already have an account?

                <a href="Login.php">

                    Login

                </a>

            </div>


        </div>

    </main>



    <!-- =====================================================
         PASSWORD TOGGLE
    ===================================================== -->

    <script>

        /* =====================================================
           PASSWORD
        ===================================================== */

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



        /* =====================================================
           CONFIRM PASSWORD
        ===================================================== */

        const confirmPasswordInput =
            document.getElementById("confirm_password");

        const confirmPasswordToggle =
            document.getElementById("confirmPasswordToggle");

        const confirmPasswordIcon =
            document.getElementById("confirmPasswordIcon");


        confirmPasswordToggle.addEventListener(
            "click",
            function () {

                if (
                    confirmPasswordInput.type === "password"
                ) {

                    confirmPasswordInput.type = "text";

                    confirmPasswordIcon.classList.remove(
                        "fa-eye"
                    );

                    confirmPasswordIcon.classList.add(
                        "fa-eye-slash"
                    );

                    confirmPasswordToggle.setAttribute(
                        "aria-label",
                        "Hide password"
                    );

                    confirmPasswordToggle.setAttribute(
                        "title",
                        "Hide password"
                    );

                } else {

                    confirmPasswordInput.type = "password";

                    confirmPasswordIcon.classList.remove(
                        "fa-eye-slash"
                    );

                    confirmPasswordIcon.classList.add(
                        "fa-eye"
                    );

                    confirmPasswordToggle.setAttribute(
                        "aria-label",
                        "Show password"
                    );

                    confirmPasswordToggle.setAttribute(
                        "title",
                        "Show password"
                    );

                }

            }
        );

    </script>


</body>

</html>