<?php

session_start();

require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| LOGIN USER
|--------------------------------------------------------------------------
| This login page is ONLY for:
| - Audience
| - Organizer
|
| Admin accounts must use the separate Admin login page.
|
| Login identifier:
| - Username
| - Email
|
| Self-deactivated account:
| - account_status = Deactivated
| - User can still log in
| - Successful login automatically reactivates the account
|
| Admin-deactivated account:
| - account_status = Inactive
| - Login is NOT allowed
|--------------------------------------------------------------------------
*/


/* =========================================================
   HELPER FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   FORM PROCESSING
========================================================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get login input
    |--------------------------------------------------------------------------
    */

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate empty fields
    |--------------------------------------------------------------------------
    */

    if ($login === '' || $password === '') {

        $error = "Please enter your username/email and password.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Find account using USERNAME OR EMAIL
        |--------------------------------------------------------------------------
        */

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                user_id,
                username,
                email,
                password,
                role,
                account_status,
                deactivation_reason,
                deactivated_until
             FROM users
             WHERE username = ?
                OR email = ?
             LIMIT 1"
        );


        if (!$stmt) {

            $error = "Unable to process your login. Please try again.";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $login,
                $login
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $user = $result
                ? mysqli_fetch_assoc($result)
                : null;

            mysqli_stmt_close($stmt);


            /*
            |--------------------------------------------------------------------------
            | Account not found
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $error = "Invalid username/email or password.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Verify password FIRST
                |--------------------------------------------------------------------------
                */

                if (
                    !password_verify(
                        $password,
                        $user['password']
                    )
                ) {

                    $error = "Invalid username/email or password.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize role and account status
                    |--------------------------------------------------------------------------
                    */

                    $role = trim($user['role'] ?? '');

                    $accountStatus = strtolower(
                        trim($user['account_status'] ?? 'active')
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN PROTECTION
                    |--------------------------------------------------------------------------
                    |
                    | Admin accounts MUST NOT be able to login through
                    | login_user.php.
                    |
                    | They must use the separate Admin login page.
                    |--------------------------------------------------------------------------
                    */

                    if ($role === 'Admin') {

                        $error =
                            "This login page is for Audience and Organizer accounts only. " .
                            "Please use the Administrator login page.";

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SELF-DEACTIVATED ACCOUNT
                    |--------------------------------------------------------------------------
                    |
                    | Deactivated means the USER chose to deactivate
                    | their own account.
                    |
                    | They are still allowed to login.
                    |
                    | After successful password verification:
                    | - Change status to Active
                    | - Clear deactivation reason
                    | - Clear deactivation date
                    |--------------------------------------------------------------------------
                    */

                    elseif ($accountStatus === 'deactivated') {

                        $reactivateStmt = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET
                                account_status = 'Active',
                                deactivation_reason = NULL,
                                deactivated_until = NULL
                             WHERE user_id = ?"
                        );


                        if (!$reactivateStmt) {

                            $error =
                                "Unable to reactivate your account. Please try again.";

                        } else {

                            mysqli_stmt_bind_param(
                                $reactivateStmt,
                                "i",
                                $user['user_id']
                            );

                            if (
                                !mysqli_stmt_execute(
                                    $reactivateStmt
                                )
                            ) {

                                mysqli_stmt_close(
                                    $reactivateStmt
                                );

                                $error =
                                    "Unable to reactivate your account. Please try again.";

                            } else {

                                mysqli_stmt_close(
                                    $reactivateStmt
                                );

                                /*
                                |--------------------------------------------------------------------------
                                | Reactivation successful
                                |--------------------------------------------------------------------------
                                */

                                session_regenerate_id(true);

                                $_SESSION['user_id'] =
                                    (int) $user['user_id'];

                                $_SESSION['role'] =
                                    $role;

                                $_SESSION['username'] =
                                    $user['username'];

                                /*
                                |--------------------------------------------------------------------------
                                | Optional session flag
                                |--------------------------------------------------------------------------
                                */

                                $_SESSION['account_reactivated'] = true;


                                /*
                                |--------------------------------------------------------------------------
                                | Redirect according to role
                                |--------------------------------------------------------------------------
                                */

                                if ($role === 'Organizer') {

                                    header(
                                        "Location: ../organizer/org_dash.php"
                                    );
                                    exit();

                                }

                                if ($role === 'Audience') {

                                    header(
                                        "Location: ../users/user.php"
                                    );
                                    exit();

                                }

                                /*
                                |--------------------------------------------------------------------------
                                | Unknown role
                                |--------------------------------------------------------------------------
                                */

                                session_unset();
                                session_destroy();

                                $error =
                                    "Your account role is not recognized.";
                            }
                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ACTIVE ACCOUNT
                    |--------------------------------------------------------------------------
                    */

                    elseif ($accountStatus === 'active') {

                        /*
                        |--------------------------------------------------------------------------
                        | Only Audience and Organizer are allowed
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $role !== 'Audience' &&
                            $role !== 'Organizer'
                        ) {

                            $error =
                                "This login page is only for Audience and Organizer accounts.";

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Secure session
                            |--------------------------------------------------------------------------
                            */

                            session_regenerate_id(true);

                            $_SESSION['user_id'] =
                                (int) $user['user_id'];

                            $_SESSION['role'] =
                                $role;

                            $_SESSION['username'] =
                                $user['username'];


                            /*
                            |--------------------------------------------------------------------------
                            | Redirect
                            |--------------------------------------------------------------------------
                            */

                            if ($role === 'Organizer') {

                                header(
                                    "Location: ../organizer/org_dash.php"
                                );
                                exit();
                            }

                            if ($role === 'Audience') {

                                header(
                                    "Location: ../users/user.php"
                                );
                                exit();
                            }
                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN-DEACTIVATED / BLOCKED / SUSPENDED ACCOUNT
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    |
                    | These accounts are NOT automatically reactivated.
                    |
                    | Only the Admin can reactivate them.
                    |--------------------------------------------------------------------------
                    */

                    elseif (
                        $accountStatus === 'inactive' ||
                        $accountStatus === 'blocked' ||
                        $accountStatus === 'suspended'
                    ) {

                        $error =
                            "Your account is currently deactivated or inactive. " .
                            "Please contact the administrator.";

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UNKNOWN ACCOUNT STATUS
                    |--------------------------------------------------------------------------
                    */

                    else {

                        $error =
                            "Your account cannot be accessed at this time. " .
                            "Please contact the administrator.";
                    }
                }
            }
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

    <title>Login | Event System</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #f8fafc
                );
            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 430px;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 35px;
            box-shadow:
                0 20px 50px rgba(15, 23, 42, 0.10);
            border: 1px solid #e5e7eb;
        }

        .login-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 18px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow:
                0 10px 25px rgba(37, 99, 235, 0.25);
        }

        .login-title {
            text-align: center;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }

        .login-subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            min-height: 48px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            background: #f8fafc;
            border-color: #d1d5db;
            color: #64748b;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        .login-btn {
            width: 100%;
            min-height: 48px;
            border: none;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .login-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .register-link {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
            color: #6b7280;
        }

        .register-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .info-text {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #9ca3af;
        }

        .reactivated-note {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .deactivated-note {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        @media (max-width: 480px) {

            .login-wrapper {
                padding: 15px;
            }

            .login-card {
                padding: 25px 20px;
                border-radius: 16px;
            }

        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <!-- ICON -->
        <div class="login-icon">
            <i class="bi bi-calendar-event"></i>
        </div>


        <!-- TITLE -->
        <h2 class="login-title">
            Welcome Back
        </h2>

        <p class="login-subtitle">
            Sign in to your Event System account
        </p>


        <!-- =====================================================
             SELF-DEACTIVATED MESSAGE
        ====================================================== -->

        <?php if (isset($_GET['deactivated'])): ?>

            <div class="deactivated-note">

                <i class="bi bi-check-circle-fill me-1"></i>

                Your account has been deactivated successfully.
                You have been logged out.
                You can log in again anytime to reactivate your account.

            </div>

        <?php endif; ?>


        <!-- =====================================================
             DELETED MESSAGE
        ====================================================== -->

        <?php if (isset($_GET['deleted'])): ?>

            <div class="alert alert-success">

                <i class="bi bi-check-circle-fill me-1"></i>

                Your account has been permanently deleted.

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ERROR
        ====================================================== -->

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                <?= e($error); ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             LOGIN FORM
        ====================================================== -->

        <form
            method="POST"
            action="login_user.php"
        >

            <!-- USERNAME / EMAIL -->

            <div class="mb-3">

                <label
                    for="login"
                    class="form-label"
                >
                    Username / Email
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>

                    <input
                        type="text"
                        id="login"
                        name="login"
                        class="form-control"
                        placeholder="Enter username or email"
                        value="<?= e($_POST['login'] ?? ''); ?>"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-btn"
            >

                <i class="bi bi-box-arrow-in-right me-1"></i>

                Login

            </button>

        </form>


        <!-- REGISTER -->

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Register here
            </a>

        </div>


        <!-- INFO -->

        <div class="info-text">

            Audience and Organizer accounts only.

        </div>

    </div>

</div>

</body>
</html>