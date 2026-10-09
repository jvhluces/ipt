<?php

session_start();

require_once '../config/db.php';

/* =========================================================
   AUTHENTICATION
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   GET USER
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        fullname,
        email,
        contact,
        address,
        username,
        profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Database error.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (
    !$result ||
    mysqli_num_rows($result) === 0
) {
    mysqli_stmt_close($stmt);

    session_unset();
    session_destroy();

    header(
        "Location: ../auth/login_user.php?error=account_not_found"
    );

    exit();
}

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   USER VARIABLES
========================================================= */

$fullname = $user['fullname'] ?? '';
$email = $user['email'] ?? '';
$contact = $user['contact'] ?? '';
$address = $user['address'] ?? '';
$username = $user['username'] ?? '';
$profilePic = $user['profile_pic'] ?? '';


/* =========================================================
   PROFILE IMAGE
========================================================= */

function getProfileImage($profilePic, $fullname)
{
    $defaultAvatar =
        'https://ui-avatars.com/api/?name=' .
        urlencode($fullname ?: 'User') .
        '&background=2563eb&color=fff&size=200';

    $profilePic = trim((string) $profilePic);

    /* No saved profile picture */
    if ($profilePic === '') {
        return $defaultAvatar;
    }

    /* External image URL */
    if (filter_var($profilePic, FILTER_VALIDATE_URL)) {
        return $profilePic;
    }

    /* Normalize slashes */
    $cleanPic = str_replace('\\', '/', $profilePic);

    /* Remove query string */
    $cleanPic = preg_replace('/[?#].*$/', '', $cleanPic);

    /* Remove leading slash */
    $cleanPic = ltrim($cleanPic, '/');

    /* Remove ../ */
    while (strpos($cleanPic, '../') === 0) {
        $cleanPic = substr($cleanPic, 3);
    }

    $filename = basename($cleanPic);

    if (
        $filename === '' ||
        $filename === '.' ||
        $filename === '..'
    ) {
        return $defaultAvatar;
    }

    /*
        Current file:
        users/user_profile.php

        Upload folder:
        uploads/profiles/
    */

    $physicalPath =
        __DIR__ .
        '/../uploads/profiles/' .
        $filename;

    if (is_file($physicalPath)) {

        return
            '../uploads/profiles/' .
            rawurlencode($filename) .
            '?v=' .
            filemtime($physicalPath);
    }

    return $defaultAvatar;
}

$avatar = getProfileImage(
    $profilePic,
    $fullname
);


/* =========================================================
   EDIT MODE
========================================================= */

$editMode =
    isset($_GET['edit']) &&
    $_GET['edit'] === '1';


/* =========================================================
   MESSAGES
========================================================= */

$successMessage = '';
$errorMessage = '';

if (
    isset($_GET['success']) &&
    $_GET['success'] === 'updated'
) {
    $successMessage =
        'Profile updated successfully.';
}

if (isset($_GET['error'])) {

    $errorCode = $_GET['error'];

    $errors = [

        'fullname_required' =>
            'Full name is required.',

        'email_required' =>
            'Email address is required.',

        'invalid_email' =>
            'Please enter a valid email address.',

        'email_exists' =>
            'That email address is already being used.',

        'upload_folder' =>
            'The upload folder could not be created.',

        'upload_failed' =>
            'The profile picture could not be uploaded.',

        'file_too_large' =>
            'The selected image is larger than 5MB.',

        'invalid_image' =>
            'Please upload a valid image file.',

        'invalid_image_type' =>
            'Only JPG, PNG, GIF, and WEBP images are allowed.',

        'database' =>
            'A database error occurred.',

        'update_failed' =>
            'The profile could not be updated.'
    ];

    $errorMessage =
        $errors[$errorCode]
        ?? 'Something went wrong.';
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
        My Profile - Event System
    </title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/css/users/user_profile.css"
    >

</head>

<body>


<aside
    class="sidebar"
    id="sidebar"
>

    <!-- PROFILE -->
    <div class="sidebar-profile">

        <img
            src="<?= e($avatar); ?>"
            class="sidebar-avatar"
            alt="Profile Picture"
            onerror="
                this.onerror=null;
                this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=2563eb&color=fff&size=200';
            "
        >

        <div class="sidebar-profile-name">
            <?= e($fullname ?: 'User'); ?>
        </div>

        <div class="sidebar-profile-role">
            Audience
        </div>

    </div>


    <!-- MAIN MENU -->

    <div class="sidebar-section-title">
        Main
    </div>

    <div class="sidebar-menu">

        <a href="user.php">

            <i class="bi bi-house"></i>

            <span>
                Home
            </span>

        </a>


        <a href="user.php#events">

            <i class="bi bi-calendar3"></i>

            <span>
                Events
            </span>

        </a>


        <a href="user.php#joined">

            <i class="bi bi-person-check"></i>

            <span>
                Joined Events
            </span>

        </a>


        <a href="user.php#reviews">

            <i class="bi bi-star"></i>

            <span>
                My Reviews
            </span>

        </a>

    </div>


    <!-- DIVIDER -->

    <div class="sidebar-divider"></div>


    <!-- ACCOUNT MENU -->

    <div class="sidebar-section-title">
        Account
    </div>

    <div class="sidebar-menu">

        <a
            href="user_profile.php"
            class="active"
        >

            <i class="bi bi-person-circle"></i>

            <span>
                My Profile
            </span>

        </a>


        <a href="user_settings.php">

            <i class="bi bi-gear"></i>

            <span>
                Settings
            </span>

        </a>


        <a href="help_user.php">

            <i class="bi bi-question-circle"></i>

            <span>
                Help &amp; Support
            </span>

        </a>

    </div>


    <!-- DIVIDER -->

    <div class="sidebar-divider"></div>


    <!-- LOGOUT -->

    <div class="logout-section">

        <a
            href="../auth/logout_user.php"
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>


<!-- =========================================================
     SIDEBAR OVERLAY
========================================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<nav class="top-navbar">

    <div class="d-flex align-items-center gap-3">

        <!-- MOBILE MENU -->

        <button
            class="mobile-menu-btn"
            id="sidebarToggle"
            type="button"
            aria-label="Open navigation menu"
        >

            <i class="bi bi-list"></i>

        </button>


        <!-- TITLE -->

        <div class="top-navbar-title">

            <i class="bi bi-calendar-event"></i>

            <span>
                Event System
            </span>

        </div>

    </div>


    <!-- RIGHT SIDE -->

    <div class="top-navbar-right">

        <img
            src="<?= e($avatar); ?>"
            class="navbar-avatar"
            alt="Profile"
            onerror="
                this.onerror=null;
                this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=2563eb&color=fff&size=200';
            "
        >

        <span class="navbar-user-name">
            <?= e($fullname ?: 'User'); ?>
        </span>

    </div>

</nav>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main-content">

    <div class="content-wrapper">


        <!-- =================================================
             SUCCESS MESSAGE
        ================================================== -->

        <?php if ($successMessage): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-check-circle-fill me-1"></i>

                <?= e($successMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGE
        ================================================== -->

        <?php if ($errorMessage): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                <?= e($errorMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             PROFILE CARD
        ================================================== -->

        <div class="profile-card">


            <!-- PROFILE HEADER -->

            <div class="profile-header">

                <img
                    src="<?= e($avatar); ?>"
                    class="profile-image"
                    id="profileMainImage"
                    alt="Profile Picture"
                    onerror="
                        this.onerror=null;
                        this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=2563eb&color=fff&size=200';
                    "
                >

                <div class="profile-name">
                    <?= e($fullname ?: 'User'); ?>
                </div>

                <div class="profile-role">
                    Audience
                </div>

            </div>


            <?php if (!$editMode): ?>


                <!-- =================================================
                     VIEW PROFILE
                ================================================== -->

                <div class="profile-body">


                    <!-- USERNAME -->

                    <div class="info-box">

                        <div class="info-label">
                            Username
                        </div>

                        <div class="info-value">

                            <?= e(
                                $username ?: 'Not provided'
                            ); ?>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="info-box">

                        <div class="info-label">
                            Email
                        </div>

                        <div class="info-value">

                            <?= e(
                                $email ?: 'Not provided'
                            ); ?>

                        </div>

                    </div>


                    <!-- CONTACT -->

                    <div class="info-box">

                        <div class="info-label">
                            Contact Number
                        </div>

                        <div class="info-value">

                            <?= e(
                                $contact ?: 'Not provided'
                            ); ?>

                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="info-box">

                        <div class="info-label">
                            Address
                        </div>

                        <div class="info-value">

                            <?= e(
                                $address ?: 'Not provided'
                            ); ?>

                        </div>

                    </div>


                    <!-- EDIT BUTTON -->

                    <a
                        href="user_profile.php?edit=1"
                        class="edit-profile-btn mt-3"
                    >

                        <i class="bi bi-pencil-square"></i>

                        Edit Profile

                    </a>

                </div>


            <?php else: ?>


                <!-- =================================================
                     EDIT PROFILE
                ================================================== -->

                <div class="edit-section">


                    <!-- TITLE -->

                    <div class="edit-section-title">

                        <i class="bi bi-person-gear me-2"></i>

                        Edit Profile

                    </div>


                    <!-- FORM -->

                    <form
                        action="save_user_profile.php"
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <!-- PROFILE PICTURE -->

                        <div class="mb-4">

                            <label
                                for="profilePicInput"
                                class="form-label"
                            >
                                Profile Picture
                            </label>


                            <img
                                src="<?= e($avatar); ?>"
                                id="profilePreview"
                                class="current-profile-preview"
                                alt="Profile Preview"
                            >


                            <input
                                type="file"
                                name="profile_pic"
                                id="profilePicInput"
                                class="form-control"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                            >


                            <div class="form-text">

                                JPG, JPEG, PNG, GIF, or WEBP.
                                Maximum 5MB.

                            </div>

                        </div>


                        <!-- USERNAME -->

                        <div class="mb-3">

                            <label
                                for="username"
                                class="form-label"
                            >
                                Username
                            </label>

                            <input
                                type="text"
                                id="username"
                                class="form-control"
                                value="<?= e($username); ?>"
                                readonly
                            >

                            <div class="form-text">

                                Username cannot be changed here.

                            </div>

                        </div>


                        <!-- FULL NAME -->

                        <div class="mb-3">

                            <label
                                for="fullname"
                                class="form-label"
                            >
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="fullname"
                                id="fullname"
                                class="form-control"
                                value="<?= e($fullname); ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="mb-3">

                            <label
                                for="email"
                                class="form-label"
                            >
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                value="<?= e($email); ?>"
                                maxlength="150"
                                required
                            >

                        </div>


                        <!-- CONTACT -->

                        <div class="mb-3">

                            <label
                                for="contact"
                                class="form-label"
                            >
                                Contact Number
                            </label>

                            <input
                                type="text"
                                name="contact"
                                id="contact"
                                class="form-control"
                                value="<?= e($contact); ?>"
                                maxlength="30"
                                placeholder="Enter your contact number"
                            >

                        </div>


                        <!-- ADDRESS -->

                        <div class="mb-4">

                            <label
                                for="address"
                                class="form-label"
                            >
                                Address
                            </label>

                            <textarea
                                name="address"
                                id="address"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                                placeholder="Enter your address"
                            ><?= e($address); ?></textarea>

                        </div>


                        <!-- BUTTONS -->

                        <div class="row g-2">

                            <div class="col-md-6">

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >

                                    <i class="bi bi-check-circle me-1"></i>

                                    Save Changes

                                </button>

                            </div>


                            <div class="col-md-6">

                                <a
                                    href="user_profile.php"
                                    class="btn btn-outline-secondary cancel-btn"
                                >

                                    <i class="bi bi-x-circle me-1"></i>

                                    Cancel

                                </a>

                            </div>

                        </div>


                    </form>

                </div>


            <?php endif; ?>


        </div>

    </div>

</main>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>


<!-- =========================================================
     PROFILE JS
========================================================= -->

<script
    src="../assets/js/users/user_profile.js"
></script>

</body>
</html>