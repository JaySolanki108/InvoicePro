<?php
session_start();
require_once "config.php";

$error = '';
$success = '';

if (empty($_SESSION['signup_csrf_token'])) {
    $_SESSION['signup_csrf_token'] = bin2hex(random_bytes(32));
}

function signupEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['signup_csrf_token'], $csrfToken)
    ) {
        $error = "Your session has expired. Please refresh and try again.";
    } elseif (
        $company_name === '' ||
        $email === '' ||
        $password === '' ||
        $confirm_password === ''
    ) {
        $error = "Please fill in all fields.";
    } elseif (strlen($company_name) > 150) {
        $error = "Company name must not exceed 150 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($email) > 254) {
        $error = "Email address is too long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    } elseif (strlen($password) > 72) {
        $error = "Password must not exceed 72 bytes. Please use a shorter password.";
    } else {
        try {
            $check_stmt = $db->prepare(
                "SELECT id FROM users
                 WHERE company_email = ?
                 LIMIT 1"
            );

            $check_stmt->execute([$email]);

            if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashed_password === false) {
                    throw new RuntimeException(
                        "Password hashing failed."
                    );
                }

                $insert_stmt = $db->prepare(
                    "INSERT INTO users
                    (company_name, company_email, password)
                    VALUES (?, ?, ?)"
                );

                $insert_stmt->execute([
                    $company_name,
                    $email,
                    $hashed_password
                ]);

                unset($_SESSION['signup_csrf_token']);

                header("Location: Login.php?registered=1");
                exit;
            }
        } catch (PDOException $e) {
            error_log(
                "Signup database error [SQLSTATE " .
                $e->getCode() . "]: " . $e->getMessage()
            );

            if ($e->getCode() === '23000') {
                $error = "An account with this email already exists.";
            } else {
                http_response_code(500);
                $error = "Unable to create account. Please try again.";
            }
        } catch (Throwable $e) {
            error_log(
                "Signup processing error: " . $e->getMessage()
            );

            http_response_code(500);
            $error = "Unable to create account. Please try again.";
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

    <title>Invoice Pro - Sign Up</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>
        :root {
            --black: #080808;
            --black-soft: #0d0d0d;
            --black-card: #111111;
            --black-hover: #151515;

            --gold: #d4af37;
            --gold-light: #f2d675;
            --gold-soft: rgba(212,175,55,0.08);
            --gold-border: rgba(212,175,55,0.24);
            --gold-border-hover: rgba(212,175,55,0.55);

            --white: #ffffff;
            --text: #eeeeee;
            --muted: #888888;
            --danger: #ef4444;
            --success: #22c55e;
        }

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
                    circle at 15% 15%,
                    rgba(212,175,55,0.08),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 85% 85%,
                    rgba(212,175,55,0.055),
                    transparent 28%
                ),
                var(--black);
            color: var(--text);
            overflow-x: hidden;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 20px;
            position: relative;
        }

        .page::before {
            content: "";
            position: fixed;
            width: 420px;
            height: 420px;
            border: 1px solid rgba(212,175,55,0.05);
            border-radius: 50%;
            top: -230px;
            left: -180px;
            pointer-events: none;
        }

        .page::after {
            content: "";
            position: fixed;
            width: 500px;
            height: 500px;
            border: 1px solid rgba(212,175,55,0.04);
            border-radius: 50%;
            right: -300px;
            bottom: -300px;
            pointer-events: none;
        }

        .signup-card {
            width: 100%;
            max-width: 450px;
            background: linear-gradient(
                145deg,
                rgba(19,19,19,0.98),
                rgba(13,13,13,0.98)
            );
            border: 1px solid var(--gold-border);
            border-radius: 16px;
            padding: 27px 32px 24px;
            box-shadow:
                0 25px 80px rgba(0,0,0,0.55),
                0 0 0 1px rgba(255,255,255,0.015);
            position: relative;
            overflow: hidden;
        }

        .signup-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(
                90deg,
                transparent,
                var(--gold),
                transparent
            );
            opacity: 0.85;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 23px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            border: 1px solid var(--gold-border-hover);
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            background: var(--gold-soft);
            font-size: 19px;
            box-shadow: inset 0 0 20px rgba(212,175,55,0.025);
        }

        .brand-name {
            color: var(--gold-light);
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .brand-name span {
            color: var(--gold);
        }

        .signup-header {
            text-align: center;
            margin-bottom: 21px;
        }

        .signup-header h1 {
            color: var(--white);
            font-size: 27px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.7px;
            margin-bottom: 7px;
        }

        .signup-header p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .alert {
            padding: 10px 13px;
            margin-bottom: 17px;
            border-radius: 9px;
            font-size: 12px;
            line-height: 1.5;
            text-align: center;
        }

        .alert-error {
            color: #fca5a5;
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
        }

        .alert-success {
            color: #86efac;
            background: rgba(34,197,94,0.08);
            border: 1px solid rgba(34,197,94,0.25);
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #dddddd;
            font-size: 12px;
            font-weight: 600;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #777777;
            font-size: 13px;
            pointer-events: none;
            z-index: 2;
            transition: color 0.2s ease;
        }

        .input-wrapper:focus-within .input-icon {
            color: var(--gold);
        }

        .form-control {
            width: 100%;
            height: 43px;
            padding: 0 43px;
            color: var(--text);
            background: #0c0c0c;
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 8px;
            outline: none;
            font-size: 13px;
            font-weight: 500;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .password-input {
            padding-right: 46px;
        }

        .form-control::placeholder {
            color: #666666;
            font-weight: 400;
        }

        .form-control:hover {
            border-color: rgba(212,175,55,0.28);
        }

        .form-control:focus {
            border-color: var(--gold);
            background: #101010;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.08);
        }

        .password-toggle {
            position: absolute;
            right: 9px;
            top: 50%;
            transform: translateY(-50%);
            width: 31px;
            height: 31px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            background: transparent;
            color: #777777;
            font-size: 13px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
            z-index: 3;
        }

        .password-toggle:hover {
            color: var(--gold);
            background: var(--gold-soft);
            border-color: var(--gold-border);
        }

        .password-toggle:focus {
            outline: none;
        }

        .btn-primary {
            width: 100%;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 3px;
            border: 1px solid var(--gold);
            border-radius: 8px;
            background: var(--gold);
            color: #080808;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--gold-light);
            border-color: var(--gold-light);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(212,175,55,0.18);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 19px 0;
            color: #555555;
            font-size: 9px;
            font-weight: 700;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.07);
        }

        .btn-google {
            width: 100%;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 8px;
            background: #0c0c0c;
            color: #dddddd;
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
            background: #131313;
            border-color: var(--gold-border);
            box-shadow: 0 6px 18px rgba(0,0,0,0.25);
            transform: translateY(-1px);
        }

        .btn-google:active {
            transform: translateY(0);
        }

        .btn-google svg {
            flex-shrink: 0;
        }

        .login-link {
            text-align: center;
            margin-top: 17px;
            color: #777777;
            font-size: 11px;
            line-height: 1.5;
        }

        .login-link a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 700;
            margin-left: 3px;
        }

        .login-link a:hover {
            color: var(--gold-light);
            text-decoration: underline;
        }

        @media (max-width: 500px) {
            .page {
                padding: 20px 13px;
            }

            .signup-card {
                max-width: 430px;
                padding: 26px 21px 22px;
                border-radius: 14px;
            }

            .brand {
                margin-bottom: 21px;
            }

            .brand-icon {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                font-size: 17px;
            }

            .brand-name {
                font-size: 21px;
            }

            .signup-header h1 {
                font-size: 25px;
            }
        }

        @media (max-width: 360px) {
            .signup-card {
                padding: 25px 18px 22px;
            }

            .form-control {
                height: 42px;
            }

            .btn-primary,
            .btn-google {
                height: 42px;
            }
        }

        @media (max-height: 700px) and (min-width: 501px) {
            .page {
                padding: 12px 20px;
            }

            .signup-card {
                padding-top: 23px;
                padding-bottom: 21px;
            }

            .brand {
                margin-bottom: 17px;
            }

            .signup-header {
                margin-bottom: 17px;
            }

            .signup-header h1 {
                font-size: 24px;
            }

            .form-group {
                margin-bottom: 10px;
            }

            .form-control {
                height: 40px;
            }

            .btn-primary {
                height: 42px;
            }

            .divider {
                margin: 13px 0;
            }

            .btn-google {
                height: 40px;
            }

            .login-link {
                margin-top: 12px;
            }
        }
    </style>
</head>

<body>

<main class="page">
    <section class="signup-card">

        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>

            <div class="brand-name">
                Invoice<span>Pro</span>
            </div>
        </div>

        <div class="signup-header">
            <h1>Create Your Account</h1>
            <p>Set up your account to manage invoices and products.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?php echo signupEscape($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success" role="status">
                <?php echo signupEscape($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="Sign_Up.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo signupEscape($_SESSION['signup_csrf_token']); ?>"
            >

            <div class="form-group">
                <label for="company_name">Company Name</label>

                <div class="input-wrapper">
                    <i class="fa-solid fa-building input-icon"></i>

                    <input
                        type="text"
                        id="company_name"
                        name="company_name"
                        class="form-control"
                        placeholder="Enter your company name"
                        autocomplete="organization"
                        maxlength="150"
                        value="<?php echo signupEscape($_POST['company_name'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Company Email</label>

                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope input-icon"></i>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter your company email"
                        autocomplete="email"
                        maxlength="254"
                        value="<?php echo signupEscape($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control password-input"
                        placeholder="Create a password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <i class="fa-regular fa-eye" id="passwordIcon"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>

                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control password-input"
                        placeholder="Confirm your password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        id="confirmPasswordToggle"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <i class="fa-regular fa-eye" id="confirmPasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <span>Create Account</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>

        </form>

        <div class="divider">
            <span>OR</span>
        </div>

        <button type="button" class="btn-google" id="googleSignupButton">
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

        <div class="login-link">
            Already have an account?
            <a href="Login.php">Login</a>
        </div>

    </section>
</main>

<script>
function setupPasswordToggle(inputId, buttonId, iconId) {
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);
    const icon = document.getElementById(iconId);

    button.addEventListener("click", function () {
        const showPassword = input.type === "password";

        input.type = showPassword ? "text" : "password";

        icon.classList.toggle("fa-eye", !showPassword);
        icon.classList.toggle("fa-eye-slash", showPassword);

        button.setAttribute(
            "aria-label",
            showPassword ? "Hide password" : "Show password"
        );

        button.setAttribute(
            "title",
            showPassword ? "Hide password" : "Show password"
        );
    });
}

setupPasswordToggle(
    "password",
    "passwordToggle",
    "passwordIcon"
);

setupPasswordToggle(
    "confirm_password",
    "confirmPasswordToggle",
    "confirmPasswordIcon"
);

document.getElementById("googleSignupButton").addEventListener("click", function () {
    alert("Google sign-up is not configured yet. Please create your account using the form.");
});
</script>

</body>
</html>
```
