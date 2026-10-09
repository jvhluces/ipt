<?php

session_start();


require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';


    if ($username === '' || $password === '') {

        $error = "Please enter your username and password.";

    } else {


        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                user_id,
                username,
                password,
                role,
                account_status
             FROM users
             WHERE username = ?
             LIMIT 1"
        );


        if (!$stmt) {

            $error =
                "Something went wrong while processing your login.";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $username
            );


            mysqli_stmt_execute($stmt);

            mysqli_stmt_store_result($stmt);



            if (mysqli_stmt_num_rows($stmt) === 1) {

                mysqli_stmt_bind_result(
                    $stmt,
                    $userId,
                    $dbUsername,
                    $hashedPassword,
                    $role,
                    $accountStatus
                );


                mysqli_stmt_fetch($stmt);



                $accountStatus = strtolower(
                    trim($accountStatus ?? 'active')
                );


                if ($accountStatus !== 'active') {

                    $error =
                        "Your admin account is inactive. Please contact the administrator.";

                }


                elseif ($role !== 'Admin') {

                    $error =
                        "Access denied. This login is for administrators only.";

                }

                elseif (
                    password_verify(
                        $password,
                        $hashedPassword
                    )
                ) {


                    session_regenerate_id(true);



                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role'] = $role;
                    $_SESSION['username'] = $dbUsername;


                    header(
                        "Location: ../admin/admin.php"
                    );

                    exit();

                } else {

                    $error =
                        "Invalid username or password.";

                }

            } else {

                $error =
                    "Invalid username or password.";

            }


            mysqli_stmt_close($stmt);
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

    <title>Admin Login | Event System</title>


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >


    <style>

        body {
            min-height: 100vh;
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            background: #ffffff;
        }

        .login-header {
            background: #2563eb;
            color: #ffffff;
            padding: 26px 20px;
            text-align: center;
        }

        .login-header .admin-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .login-header h3 {
            margin: 0;
            font-weight: 700;
        }

        .login-header p {
            margin: 7px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            min-height: 46px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 0.2rem
                rgba(37, 99, 235, 0.15);
        }

        .login-btn {
            min-height: 46px;
            border-radius: 10px;
            background: #2563eb;
            border: none;
            font-weight: 600;
        }

        .login-btn:hover {
            background: #1d4ed8;
        }

        .admin-note {
            margin-top: 18px;
            padding: 10px 12px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #6b7280;
            font-size: 12px;
            text-align: center;
        }

        .alert {
            border-radius: 10px;
            font-size: 14px;
        }

    </style>

</head>


<body>


<div class="login-wrapper">

    <div class="card shadow login-card">



        <div class="login-header">

            <div class="admin-icon">
                🔐
            </div>

            <h3>
                Admin Login
            </h3>

            <p>
                 Event System
            </p>

        </div>


        <div class="login-body">


            <?php if ($error !== ''): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="login.php"
            >


                <div class="mb-3">

                    <label
                        for="username"
                        class="form-label"
                    >
                        Admin Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Enter admin username"
                        value="<?= htmlspecialchars(
                            $_POST['username'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        autocomplete="username"
                        required
                    >

                </div>


                <div class="mb-3">

                    <label
                        for="password"
                        class="form-label"
                    >
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter admin password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary login-btn w-100"
                >
                    Login as Admin
                </button>


                <div class="admin-note">

                    This login is restricted to
                    authorized administrators.

                </div>


            </form>


        </div>

    </div>

</div>


</body>

</html>