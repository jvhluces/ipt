<?php

session_start();

require_once '../config/db.php';

$error = '';


/*
|--------------------------------------------------------------------------
| REGISTRATION PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname          = trim($_POST['fullname'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $contact           = trim($_POST['contact'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $username          = trim($_POST['username'] ?? '');
    $password          = $_POST['password'] ?? '';
    $organization_name = trim($_POST['organization_name'] ?? '');
    $role              = trim($_POST['role'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | REQUIRED FIELD VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $fullname === '' ||
        $email === '' ||
        $contact === '' ||
        $username === '' ||
        $password === '' ||
        $role === ''
    ) {

        $error = "Please complete all required fields.";


    /*
    |--------------------------------------------------------------------------
    | FULL NAME VALIDATION
    |--------------------------------------------------------------------------
    */

    } elseif (
        !preg_match(
            "/^[\p{L}]+(?:[ '\-][\p{L}]+)*$/u",
            $fullname
        )
    ) {

        $error =
            "Full name must contain letters only. Numbers and invalid characters are not allowed.";


    /*
    |--------------------------------------------------------------------------
    | EMAIL VALIDATION
    |--------------------------------------------------------------------------
    */

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error =
            "Please enter a valid email address.";


    /*
    |--------------------------------------------------------------------------
    | PASSWORD VALIDATION
    |--------------------------------------------------------------------------
    */

    } elseif (
        strlen($password) < 6
    ) {

        $error =
            "Password must be at least 6 characters.";


    /*
    |--------------------------------------------------------------------------
    | ROLE VALIDATION
    |--------------------------------------------------------------------------
    */

    } elseif (
        !in_array(
            $role,
            ['Audience', 'Organizer'],
            true
        )
    ) {

        $error =
            "Invalid role selected.";


    /*
    |--------------------------------------------------------------------------
    | ORGANIZATION NAME
    |--------------------------------------------------------------------------
    |
    | Organizer = REQUIRED
    | Audience   = OPTIONAL
    |
    */

    } elseif (
        $role === 'Organizer' &&
        $organization_name === ''
    ) {

        $error =
            "Organization name is required for Organizer accounts.";


    /*
    |--------------------------------------------------------------------------
    | ORGANIZATION NAME FORMAT
    |--------------------------------------------------------------------------
    */

    } elseif (
        $organization_name !== '' &&
        !preg_match(
            "/^[\p{L}\p{N}][\p{L}\p{N} .,'&()\-\/]*$/u",
            $organization_name
        )
    ) {

        $error =
            "Please enter a valid organization name.";


    /*
    |--------------------------------------------------------------------------
    | PROCESS REGISTRATION
    |--------------------------------------------------------------------------
    */

    } else {


        /*
        |--------------------------------------------------------------------------
        | CHECK USERNAME
        |--------------------------------------------------------------------------
        */

        $checkUsername = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE username = ?
             LIMIT 1"
        );


        if (!$checkUsername) {

            $error =
                "Database error. Please try again.";

        } else {

            $checkUsername->bind_param(
                "s",
                $username
            );

            $checkUsername->execute();

            $checkUsername->store_result();


            if ($checkUsername->num_rows > 0) {

                $error =
                    "Username is already taken.";

                $checkUsername->close();

            } else {

                $checkUsername->close();


                /*
                |--------------------------------------------------------------------------
                | CHECK EMAIL
                |--------------------------------------------------------------------------
                */

                $checkEmail = $conn->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );


                if (!$checkEmail) {

                    $error =
                        "Database error. Please try again.";

                } else {

                    $checkEmail->bind_param(
                        "s",
                        $email
                    );

                    $checkEmail->execute();

                    $checkEmail->store_result();


                    if ($checkEmail->num_rows > 0) {

                        $error =
                            "Email address is already registered.";

                        $checkEmail->close();

                    } else {

                        $checkEmail->close();


                        /*
                        |--------------------------------------------------------------------------
                        | HASH PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        $hashedPassword = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | INSERT USER
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $conn->prepare(
                            "INSERT INTO users
                            (
                                fullname,
                                email,
                                contact,
                                address,
                                username,
                                password,
                                organization_name,
                                role,
                                account_status
                            )
                            VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, 'Active')"
                        );


                        if (!$stmt) {

                            $error =
                                "Unable to create account. Please try again.";

                        } else {


                            $stmt->bind_param(
                                "ssssssss",
                                $fullname,
                                $email,
                                $contact,
                                $address,
                                $username,
                                $hashedPassword,
                                $organization_name,
                                $role
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | CREATE ACCOUNT
                            |--------------------------------------------------------------------------
                            */

                            if ($stmt->execute()) {

                                $new_id =
                                    $stmt->insert_id;


                                /*
                                |--------------------------------------------------------------------------
                                | CLOSE STATEMENT
                                |--------------------------------------------------------------------------
                                */

                                $stmt->close();


                                /*
                                |--------------------------------------------------------------------------
                                | AUTOMATIC LOGIN
                                |--------------------------------------------------------------------------
                                |
                                | The user is immediately logged in after
                                | successful registration.
                                |
                                */

                                session_regenerate_id(true);


                                $_SESSION['user_id'] =
                                    $new_id;

                                $_SESSION['role'] =
                                    $role;

                                $_SESSION['username'] =
                                    $username;

                                $_SESSION['organization_name'] =
                                    $organization_name;


                                /*
                                |--------------------------------------------------------------------------
                                | REDIRECT TO DASHBOARD
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
                                        "Location: ../user/user.php"
                                    );

                                    exit();
                                }


                            } else {

                                $error =
                                    "Registration failed. Please try again.";

                                $stmt->close();
                            }
                        }
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

    <title>
        Create Account | IPT Event System
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            min-height: 100vh;

            margin: 0;

            padding: 40px 15px;

            background: #f5f7fb;

            display: flex;

            align-items: center;

            justify-content: center;

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        .register-card {

            width: 100%;

            max-width: 850px;

            border: none;

            border-radius: 18px;

            overflow: hidden;

            background: #ffffff;

            box-shadow:
                0 15px 45px
                rgba(0, 0, 0, 0.08);
        }


        .register-header {

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #0b5ed7
                );

            color: #ffffff;

            padding: 28px 30px;
        }


        .register-icon {

            width: 52px;

            height: 52px;

            margin: 0 auto 12px;

            border-radius: 14px;

            background:
                rgba(255, 255, 255, 0.15);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;
        }


        .register-header h3 {

            margin: 0;

            font-size: 26px;

            font-weight: 700;
        }


        .register-header p {

            margin: 5px 0 0;

            opacity: 0.9;

            font-size: 14px;
        }


        .register-body {

            padding: 35px 40px 30px;
        }


        .section-title {

            font-size: 15px;

            font-weight: 700;

            color: #344054;

            margin-bottom: 20px;
        }


        .form-label {

            font-size: 14px;

            font-weight: 600;

            color: #344054;

            margin-bottom: 7px;
        }


        .required {

            color: #dc3545;
        }


        .form-control,
        .form-select {

            min-height: 46px;

            border: 1px solid #d0d5dd;

            border-radius: 9px;

            padding: 10px 13px;

            font-size: 14px;

            transition: 0.2s ease;
        }


        .form-control:focus,
        .form-select:focus {

            border-color: #0d6efd;

            box-shadow:
                0 0 0 3px
                rgba(13, 110, 253, 0.10);
        }


        .form-control.is-invalid,
        .form-select.is-invalid {

            border-color: #dc3545;

            box-shadow:
                0 0 0 3px
                rgba(220, 53, 69, 0.08);
        }


        .form-control.is-valid,
        .form-select.is-valid {

            border-color: #198754;

            box-shadow:
                0 0 0 3px
                rgba(25, 135, 84, 0.08);
        }


        .form-text {

            font-size: 12px;

            color: #667085;
        }


        .invalid-feedback {

            font-size: 12px;

            margin-top: 5px;
        }


        .alert {

            border-radius: 10px;

            font-size: 14px;

            margin-bottom: 25px;
        }


        .btn-register {

            min-height: 48px;

            border: none;

            border-radius: 9px;

            font-size: 15px;

            font-weight: 600;

            transition: 0.2s ease;
        }


        .btn-register:hover {

            transform: translateY(-1px);

            box-shadow:
                0 6px 15px
                rgba(13, 110, 253, 0.20);
        }


        .login-area {

            border-top: 1px solid #eaecf0;

            margin-top: 28px;

            padding-top: 22px;

            text-align: center;

            font-size: 14px;
        }


        .login-link {

            color: #0d6efd;

            text-decoration: none;

            font-weight: 600;
        }


        .login-link:hover {

            text-decoration: underline;
        }


        @media (max-width: 767.98px) {

            body {

                padding: 20px 12px;
            }


            .register-card {

                border-radius: 14px;
            }


            .register-header {

                padding: 24px 20px;
            }


            .register-header h3 {

                font-size: 23px;
            }


            .register-body {

                padding: 25px 20px;
            }

        }

    </style>

</head>


<body>


<div class="register-card">


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <div class="register-header text-center">


        <div class="register-icon">

            <i class="bi bi-person-plus-fill"></i>

        </div>


        <h3>
            Create Your Account
        </h3>


        <p>
            Register to access the IPT Event System
        </p>


    </div>


    <!-- =========================================================
         BODY
    ========================================================== -->

    <div class="register-body">


        <?php if ($error !== ''): ?>

            <div
                class="alert alert-danger d-flex align-items-center"
                role="alert"
            >

                <i
                    class="bi bi-exclamation-circle-fill me-2"
                ></i>


                <div>

                    <?= htmlspecialchars($error) ?>

                </div>

            </div>

        <?php endif; ?>


        <div class="section-title">

            <i class="bi bi-person-vcard me-1"></i>

            Account Information

        </div>


        <form
            method="POST"
            action="register.php"
            id="registerForm"
            novalidate
        >


            <div class="row g-3">


                <!-- FULL NAME -->

                <div class="col-12 col-md-6">


                    <label
                        for="fullname"
                        class="form-label"
                    >

                        Full Name

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="fullname"
                        id="fullname"
                        class="form-control"
                        placeholder="Enter your full name"
                        value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
                        autocomplete="name"
                        required
                    >


                    <div
                        id="fullnameError"
                        class="invalid-feedback"
                    >

                        Full name must contain letters only.
                        Numbers are not allowed.

                    </div>


                </div>


                <!-- EMAIL -->

                <div class="col-12 col-md-6">


                    <label
                        for="email"
                        class="form-label"
                    >

                        Email Address

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control"
                        placeholder="Enter your email address"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="email"
                        required
                    >


                </div>


                <!-- CONTACT -->

                <div class="col-12 col-md-6">


                    <label
                        for="contact"
                        class="form-label"
                    >

                        Contact Number

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="contact"
                        id="contact"
                        class="form-control"
                        placeholder="e.g. 09123456789"
                        value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>"
                        autocomplete="tel"
                        required
                    >


                </div>


                <!-- ADDRESS -->

                <div class="col-12 col-md-6">


                    <label
                        for="address"
                        class="form-label"
                    >

                        Address

                    </label>


                    <input
                        type="text"
                        name="address"
                        id="address"
                        class="form-control"
                        placeholder="Enter your address"
                        value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                        autocomplete="street-address"
                    >


                </div>


                <!-- USERNAME -->

                <div class="col-12 col-md-6">


                    <label
                        for="username"
                        class="form-label"
                    >

                        Username

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="username"
                        id="username"
                        class="form-control"
                        placeholder="Choose a username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >


                    <div class="form-text mt-1">

                        This will be used when logging in.

                    </div>


                </div>


                <!-- PASSWORD -->

                <div class="col-12 col-md-6">


                    <label
                        for="password"
                        class="form-label"
                    >

                        Password

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Minimum 6 characters"
                        autocomplete="new-password"
                        required
                    >


                    <div class="form-text mt-1">

                        Must be at least 6 characters.

                    </div>


                </div>


                <!-- ACCOUNT TYPE -->

                <div class="col-12 col-md-6">


                    <label
                        for="role"
                        class="form-label"
                    >

                        Account Type

                        <span class="required">
                            *
                        </span>

                    </label>


                    <select
                        name="role"
                        id="role"
                        class="form-select"
                        required
                    >


                        <option
                            value=""
                            disabled
                            <?= empty($_POST['role'])
                                ? 'selected'
                                : '' ?>
                        >

                            Select account type

                        </option>


                        <option
                            value="Audience"
                            <?= (
                                ($_POST['role'] ?? '') === 'Audience'
                            )
                                ? 'selected'
                                : '' ?>
                        >

                            Audience

                        </option>


                        <option
                            value="Organizer"
                            <?= (
                                ($_POST['role'] ?? '') === 'Organizer'
                            )
                                ? 'selected'
                                : '' ?>
                        >

                            Organizer

                        </option>


                    </select>


                </div>


                <!-- ORGANIZATION NAME -->

                <div class="col-12 col-md-6">


                    <label
                        for="organization_name"
                        class="form-label"
                    >

                        Organization Name

                        <span
                            id="organizationRequired"
                            class="required"
                        >
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="organization_name"
                        id="organization_name"
                        class="form-control"
                        placeholder="Enter your organization name"
                        value="<?= htmlspecialchars(
                            $_POST['organization_name'] ?? ''
                        ) ?>"
                        autocomplete="organization"
                    >


                    <div
                        id="organizationHelp"
                        class="form-text mt-1"
                    >

                        Required for Organizer accounts.

                    </div>


                    <div
                        id="organizationError"
                        class="invalid-feedback"
                    >

                        Organization name is required for
                        Organizer accounts.

                    </div>


                </div>


            </div>


            <!-- SUBMIT -->

            <div class="mt-4">


                <button
                    type="submit"
                    class="btn btn-primary btn-register w-100"
                >

                    <i
                        class="bi bi-person-check-fill me-2"
                    ></i>

                    Create Account

                </button>


            </div>


        </form>


        <!-- LOGIN -->

        <div class="login-area">


            <span class="text-muted">

                Already have an account?

            </span>


            <a
                href="login_user.php"
                class="login-link ms-1"
            >

                Login here

            </a>


        </div>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const fullnameInput =
    document.getElementById('fullname');

const organizationInput =
    document.getElementById('organization_name');

const organizationRequired =
    document.getElementById('organizationRequired');

const organizationHelp =
    document.getElementById('organizationHelp');

const organizationError =
    document.getElementById('organizationError');

const roleInput =
    document.getElementById('role');

const registerForm =
    document.getElementById('registerForm');


/*
|--------------------------------------------------------------------------
| FULL NAME VALIDATION
|--------------------------------------------------------------------------
*/

function validateFullname() {

    const fullname =
        fullnameInput.value.trim();


    if (fullname === '') {

        fullnameInput.classList.remove(
            'is-invalid'
        );

        return true;
    }


    const namePattern =
        /^[\p{L}]+(?:[ '\-][\p{L}]+)*$/u;


    if (!namePattern.test(fullname)) {

        fullnameInput.classList.add(
            'is-invalid'
        );

        return false;

    } else {

        fullnameInput.classList.remove(
            'is-invalid'
        );

        return true;
    }
}


function validateOrganization() {

    const role =
        roleInput.value;

    const organization =
        organizationInput.value.trim();



    if (role === 'Organizer') {

        organizationRequired.style.display =
            'inline';

        organizationInput.required =
            true;

        organizationHelp.textContent =
            'Required for Organizer accounts.';


        if (organization === '') {

            organizationInput.classList.add(
                'is-invalid'
            );

            organizationError.textContent =
                'Organization name is required for Organizer accounts.';

            return false;
        }


        const organizationPattern =
            /^[\p{L}\p{N}][\p{L}\p{N} .,'&()\-\/]*$/u;


        if (
            !organizationPattern.test(
                organization
            )
        ) {

            organizationInput.classList.add(
                'is-invalid'
            );

            organizationError.textContent =
                'Please enter a valid organization name.';

            return false;
        }


        organizationInput.classList.remove(
            'is-invalid'
        );

        return true;
    }



    if (role === 'Audience') {

        organizationRequired.style.display =
            'none';

        organizationInput.required =
            false;

        organizationHelp.textContent =
            'Optional for Audience accounts.';

        organizationInput.classList.remove(
            'is-invalid'
        );

        return true;
    }


    

    organizationRequired.style.display =
        'none';

    organizationInput.required =
        false;

    organizationHelp.textContent =
        'Select an account type first.';

    organizationInput.classList.remove(
        'is-invalid'
    );

    return true;
}



roleInput.addEventListener(
    'change',
    function () {

        validateOrganization();

    }
);




fullnameInput.addEventListener(
    'input',
    function () {

        validateFullname();

    }
);




organizationInput.addEventListener(
    'input',
    function () {

        validateOrganization();

    }
);




registerForm.addEventListener(
    'submit',
    function (event) {


        const fullnameValid =
            validateFullname();


        const organizationValid =
            validateOrganization();


    

        if (
            !fullnameValid ||
            !organizationValid
        ) {

            event.preventDefault();


            if (!fullnameValid) {

                fullnameInput.focus();

            } else if (!organizationValid) {

                organizationInput.focus();
            }

            return;
        }



    }
);




validateOrganization();

</script>


</body>

</html>