<?php

session_start();

require_once '../config/db.php';


if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if (isset($_POST['change_username'])) {

        $new_username = trim($_POST['username'] ?? '');

        if ($new_username === '') {
            header("Location: user_settings.php?error=username_empty");
            exit();
        }

        if (strlen($new_username) < 3) {
            header("Location: user_settings.php?error=username_short");
            exit();
        }

        if (strlen($new_username) > 50) {
            header("Location: user_settings.php?error=username_long");
            exit();
        }

        /* Check if username already exists */
        $checkStmt = mysqli_prepare(
            $conn,
            "SELECT user_id
             FROM users
             WHERE username = ?
             AND user_id != ?
             LIMIT 1"
        );

        if (!$checkStmt) {
            header("Location: user_settings.php?error=update_failed");
            exit();
        }

        mysqli_stmt_bind_param(
            $checkStmt,
            "si",
            $new_username,
            $user_id
        );

        mysqli_stmt_execute($checkStmt);

        $checkResult = mysqli_stmt_get_result($checkStmt);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {

            mysqli_stmt_close($checkStmt);

            header("Location: user_settings.php?error=username_exists");
            exit();
        }

        mysqli_stmt_close($checkStmt);

        /* Update username */
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET username = ?
             WHERE user_id = ?"
        );

        if (!$updateStmt) {
            header("Location: user_settings.php?error=update_failed");
            exit();
        }

        mysqli_stmt_bind_param(
            $updateStmt,
            "si",
            $new_username,
            $user_id
        );

        if (mysqli_stmt_execute($updateStmt)) {

            mysqli_stmt_close($updateStmt);

            /* Update session username */
            $_SESSION['username'] = $new_username;

            header("Location: user_settings.php?success=username");
            exit();

        } else {

            mysqli_stmt_close($updateStmt);

            header("Location: user_settings.php?error=update_failed");
            exit();
        }
    }


    /* =====================================================
       CHANGE PASSWORD
    ===================================================== */

    if (isset($_POST['change_password'])) {

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (
            $current_password === '' ||
            $new_password === '' ||
            $confirm_password === ''
        ) {
            header("Location: user_settings.php?error=password_empty");
            exit();
        }

        if (strlen($new_password) < 6) {
            header("Location: user_settings.php?error=password_short");
            exit();
        }

        if ($new_password !== $confirm_password) {
            header("Location: user_settings.php?error=password_mismatch");
            exit();
        }

        /* Get current password */
        $passwordStmt = mysqli_prepare(
            $conn,
            "SELECT password
             FROM users
             WHERE user_id = ?
             LIMIT 1"
        );

        if (!$passwordStmt) {
            header("Location: user_settings.php?error=update_failed");
            exit();
        }

        mysqli_stmt_bind_param(
            $passwordStmt,
            "i",
            $user_id
        );

        mysqli_stmt_execute($passwordStmt);

        $passwordResult = mysqli_stmt_get_result($passwordStmt);

        $passwordRow = $passwordResult
            ? mysqli_fetch_assoc($passwordResult)
            : null;

        mysqli_stmt_close($passwordStmt);

        if (!$passwordRow) {
            header("Location: user_settings.php?error=account_not_found");
            exit();
        }

        /* Verify current password */
        if (
            !password_verify(
                $current_password,
                $passwordRow['password']
            )
        ) {
            header("Location: user_settings.php?error=wrong_password");
            exit();
        }

        /* Prevent using the same password */
        if (
            password_verify(
                $new_password,
                $passwordRow['password']
            )
        ) {
            header("Location: user_settings.php?error=same_password");
            exit();
        }

        /* Hash new password */
        $hashedPassword = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        /* Update password */
        $updatePasswordStmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET password = ?
             WHERE user_id = ?"
        );

        if (!$updatePasswordStmt) {
            header("Location: user_settings.php?error=update_failed");
            exit();
        }

        mysqli_stmt_bind_param(
            $updatePasswordStmt,
            "si",
            $hashedPassword,
            $user_id
        );

        if (mysqli_stmt_execute($updatePasswordStmt)) {

            mysqli_stmt_close($updatePasswordStmt);

            header("Location: user_settings.php?success=password");
            exit();

        } else {

            mysqli_stmt_close($updatePasswordStmt);

            header("Location: user_settings.php?error=update_failed");
            exit();
        }
    }


    /* =====================================================
       DEACTIVATE ACCOUNT
    ===================================================== */

    if (isset($_POST['deactivate_account'])) {

        /*
         * The account remains login-capable after self-deactivation,
         * similar to a temporary deactivation flow.
         *
         * Required database columns:
         *   deactivation_reason VARCHAR(255) NULL
         *   deactivated_until DATETIME NULL
         */
        $reason = trim($_POST['deactivation_reason'] ?? '');
        $duration = trim($_POST['deactivation_duration'] ?? '');
        $customReason = trim($_POST['deactivation_custom_reason'] ?? '');

        $allowedReasons = [
            'taking_break'       => 'I need a break from the Event System.',
            'not_using'          => 'I am not using the Event System right now.',
            'privacy'            => 'I have privacy concerns.',
            'notifications'      => 'I want to take a break from notifications.',
            'personal'           => 'Personal reasons.',
            'other'              => 'Other reason.'
        ];

        $allowedDurations = [
            '1_day',
            '7_days',
            '30_days',
            '90_days',
            '180_days',
            'until_change'
        ];

        if (!array_key_exists($reason, $allowedReasons)) {
            header("Location: user_settings.php?error=deactivation_reason");
            exit();
        }

        if ($reason === 'other') {
            if ($customReason === '') {
                header("Location: user_settings.php?error=deactivation_custom_reason");
                exit();
            }

            if (mb_strlen($customReason) > 255) {
                header("Location: user_settings.php?error=deactivation_custom_reason_long");
                exit();
            }

            $reasonText = $customReason;
        } else {
            $reasonText = $allowedReasons[$reason];
        }

        if (!in_array($duration, $allowedDurations, true)) {
            header("Location: user_settings.php?error=deactivation_duration");
            exit();
        }

        $deactivatedUntil = null;

        switch ($duration) {
            case '1_day':
                $deactivatedUntil = date('Y-m-d H:i:s', strtotime('+1 day'));
                break;

            case '7_days':
                $deactivatedUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
                break;

            case '30_days':
                $deactivatedUntil = date('Y-m-d H:i:s', strtotime('+30 days'));
                break;

            case '90_days':
                $deactivatedUntil = date('Y-m-d H:i:s', strtotime('+90 days'));
                break;

            case '180_days':
                $deactivatedUntil = date('Y-m-d H:i:s', strtotime('+180 days'));
                break;

            case 'until_change':
                $deactivatedUntil = null;
                break;
        }

        /*
         * Keep the account status as Deactivated so the Admin panel can
         * clearly show that the user deactivated the account.
         * login_user.php will allow a deactivated user to log in and
         * automatically reactivate the account.
         */
        $status = 'Deactivated';

        $deactivateStmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET account_status = ?,
                 deactivation_reason = ?,
                 deactivated_until = ?
             WHERE user_id = ?"
        );

        if (!$deactivateStmt) {
            header("Location: user_settings.php?error=deactivate_failed");
            exit();
        }

        mysqli_stmt_bind_param(
            $deactivateStmt,
            "ssss",
            $status,
            $reasonText,
            $deactivatedUntil,
            $user_id
        );

        if (mysqli_stmt_execute($deactivateStmt)) {

            mysqli_stmt_close($deactivateStmt);

            session_unset();
            session_destroy();

            header("Location: ../auth/login_user.php?deactivated=1");
            exit();

        } else {

            mysqli_stmt_close($deactivateStmt);

            header("Location: user_settings.php?error=deactivate_failed");
            exit();
        }
    }


    /* =====================================================
       DELETE ACCOUNT
    ===================================================== */

    if (isset($_POST['delete_account'])) {

        /*
         * Delete participation records first.
         */
        $deleteParticipantsStmt = mysqli_prepare(
            $conn,
            "DELETE FROM event_participants
             WHERE user_id = ?"
        );

        if ($deleteParticipantsStmt) {

            mysqli_stmt_bind_param(
                $deleteParticipantsStmt,
                "i",
                $user_id
            );

            mysqli_stmt_execute($deleteParticipantsStmt);

            mysqli_stmt_close($deleteParticipantsStmt);
        }


        /*
         * Delete reviews.
         */
        $deleteReviewsStmt = mysqli_prepare(
            $conn,
            "DELETE FROM event_reviews
             WHERE user_id = ?"
        );

        if ($deleteReviewsStmt) {

            mysqli_stmt_bind_param(
                $deleteReviewsStmt,
                "i",
                $user_id
            );

            mysqli_stmt_execute($deleteReviewsStmt);

            mysqli_stmt_close($deleteReviewsStmt);
        }


        /*
         * Delete user account.
         */
        $deleteUserStmt = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE user_id = ?"
        );

        if (!$deleteUserStmt) {
            header("Location: user_settings.php?error=delete_failed");
            exit();
        }

        mysqli_stmt_bind_param(
            $deleteUserStmt,
            "i",
            $user_id
        );

        if (mysqli_stmt_execute($deleteUserStmt)) {

            mysqli_stmt_close($deleteUserStmt);

            session_unset();
            session_destroy();

            header("Location: ../auth/login_user.php?deleted=1");
            exit();

        } else {

            mysqli_stmt_close($deleteUserStmt);

            header("Location: user_settings.php?error=delete_failed");
            exit();
        }
    }
}


/* =========================================================
   GET CURRENT USER DATA
========================================================= */

$userStmt = mysqli_prepare(
    $conn,
    "SELECT fullname, username, profile_pic, account_status
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$userStmt) {
    die("Unable to load user information.");
}

mysqli_stmt_bind_param(
    $userStmt,
    "i",
    $user_id
);

mysqli_stmt_execute($userStmt);

$userResult = mysqli_stmt_get_result($userStmt);

$user = $userResult
    ? mysqli_fetch_assoc($userResult)
    : null;

mysqli_stmt_close($userStmt);

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: ../auth/login_user.php");
    exit();
}

$fullname = $user['fullname'] ?? 'User';
$username = $user['username'] ?? '';
$profilePic = $user['profile_pic'] ?? '';
$accountStatus = $user['account_status'] ?? 'Active';


/* =========================================================
   PROFILE IMAGE
========================================================= */

$avatar = '';

if (!empty($profilePic)) {

    $cleanPic = ltrim($profilePic, '/');

    $possiblePaths = [

        $cleanPic,

        'uploads/profiles/' .
        basename($cleanPic),

        'uploads/profile/' .
        basename($cleanPic),

        'uploads/' .
        basename($cleanPic),

        '../uploads/profiles/' .
        basename($cleanPic),

        '../uploads/profile/' .
        basename($cleanPic),

        '../uploads/' .
        basename($cleanPic),

        'assets/uploads/profiles/' .
        basename($cleanPic),

        'assets/images/' .
        basename($cleanPic)

    ];

    foreach ($possiblePaths as $path) {

        if (
            !filter_var($path, FILTER_VALIDATE_URL) &&
            file_exists($path)
        ) {

            $avatar = $path;

            $avatar .= '?v=' . filemtime($path);

            break;
        }
    }


    /* If profile_pic is already a URL */
    if (
        empty($avatar) &&
        filter_var(
            $profilePic,
            FILTER_VALIDATE_URL
        )
    ) {

        $avatar = $profilePic;
    }
}


/* =========================================================
   FALLBACK AVATAR
========================================================= */

if (empty($avatar)) {

    $avatar =
        'https://ui-avatars.com/api/?name=' .
        urlencode($fullname ?: 'User') .
        '&background=2563eb&color=fff';
}


/* =========================================================
   ESCAPE FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
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

<title>Settings -  Event System</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
>



<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
>


<link rel="stylesheet" href="../assets/css/users/user_settings.css">

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
            alt="Profile"
            onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=2563eb&color=fff';"
        >

        <div class="sidebar-profile-name">
            <?= e($fullname ?: 'User'); ?>
        </div>

        <div class="sidebar-profile-role">
            Audience
        </div>

    </div>


    <!-- MAIN -->

    <div class="sidebar-section-title">
        Main
    </div>


    <div class="sidebar-menu">

        <a href="user.php">
            <i class="bi bi-house"></i>
            <span>Home</span>
        </a>


        <a href="user.php#events">
            <i class="bi bi-calendar3"></i>
            <span>Events</span>
        </a>


        <a href="user.php#joined">
            <i class="bi bi-person-check"></i>
            <span>Joined Events</span>
        </a>


        <a href="user.php#reviews">
            <i class="bi bi-star"></i>
            <span>My Reviews</span>
        </a>

    </div>


    <!-- DIVIDER -->

    <div class="sidebar-divider"></div>


    <!-- ACCOUNT -->

    <div class="sidebar-section-title">
        Account
    </div>


    <div class="sidebar-menu">

        <a href="user_profile.php">
            <i class="bi bi-person-circle"></i>
            <span>My Profile</span>
        </a>


        <!-- ACTIVE SETTINGS -->

        <a
            href="user_settings.php"
            class="active"
        >
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>


        <!-- CORRECT HELP PATH -->

        <a href="help_user.php">
            <i class="bi bi-question-circle"></i>
            <span>Help &amp; Support</span>
        </a>

    </div>


    <!-- DIVIDER -->

    <div class="sidebar-divider"></div>


    <!-- LOGOUT -->

    <div class="logout-section">

        <a
            href="../auth/logout_user.php"
            onclick="return confirmLogout();"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

    </div>

</aside>


<!-- =====================================================
     SIDEBAR OVERLAY
===================================================== -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =====================================================
     TOP NAVBAR
===================================================== -->

<nav class="top-navbar">

    <div class="d-flex align-items-center gap-3">

        <!-- BURGER -->

        <button
            type="button"
            class="btn mobile-menu-btn"
            id="sidebarToggle"
            aria-label="Open menu"
            aria-expanded="false"
        >
            <i class="bi bi-list"></i>
        </button>


        <!-- TITLE -->

        <div class="top-navbar-title">

            <i class="bi bi-calendar-event"></i>

             Event System

        </div>

    </div>


    <!-- RIGHT -->

    <div class="top-navbar-right">

        <img
            src="<?= e($avatar); ?>"
            class="navbar-avatar"
            alt="Profile"
            onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=2563eb&color=fff';"
        >


        <span class="navbar-user-name">
            <?= e($fullname ?: 'User'); ?>
        </span>


        <!-- NAVBAR LOGOUT -->

        <a
            href="../auth/logout_user.php"
            class="btn btn-outline-light btn-sm navbar-logout"
            onclick="return confirmLogout();"
        >
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>

    </div>

</nav>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main-content">

    <div class="content-wrapper">

        <div class="settings-container">


            <!-- =================================================
                 ALERTS
            ================================================== -->

            <?php if (isset($_GET['success'])): ?>

                <div class="alert alert-success shadow-sm">

                    <i class="bi bi-check-circle-fill"></i>

                    <?php

                    if ($_GET['success'] === 'username') {

                        echo "Username updated successfully.";

                    } elseif ($_GET['success'] === 'password') {

                        echo "Password changed successfully.";

                    } elseif ($_GET['success'] === 'deactivated') {

                        echo "Your account has been deactivated successfully.";

                    }

                    ?>

                </div>

            <?php endif; ?>


            <?php if (isset($_GET['error'])): ?>

                <div class="alert alert-danger shadow-sm">

                    <i class="bi bi-exclamation-triangle-fill"></i>

                    <?php

                    $error = $_GET['error'];

                    switch ($error) {

                        case 'username_empty':
                            echo "Username cannot be empty.";
                            break;

                        case 'username_short':
                            echo "Username must be at least 3 characters.";
                            break;

                        case 'username_long':
                            echo "Username cannot exceed 50 characters.";
                            break;

                        case 'username_exists':
                            echo "That username is already being used.";
                            break;

                        case 'password_empty':
                            echo "Please complete all password fields.";
                            break;

                        case 'password_short':
                            echo "New password must be at least 6 characters.";
                            break;

                        case 'password_mismatch':
                            echo "New passwords do not match.";
                            break;

                        case 'wrong_password':
                            echo "Current password is incorrect.";
                            break;

                        case 'same_password':
                            echo "Your new password must be different from your current password.";
                            break;

                        case 'update_failed':
                            echo "Unable to update your account.";
                            break;

                        case 'deactivate_failed':
                            echo "Unable to deactivate your account.";
                            break;

                        case 'deactivation_reason':
                            echo "Please select a reason for deactivating your account.";
                            break;

                        case 'deactivation_custom_reason':
                            echo "Please enter your reason for deactivating your account.";
                            break;

                        case 'deactivation_custom_reason_long':
                            echo "Your custom reason cannot exceed 255 characters.";
                            break;

                        case 'deactivation_duration':
                            echo "Please select how long you want to deactivate your account.";
                            break;

                        case 'delete_failed':
                            echo "Unable to delete your account.";
                            break;

                        case 'account_not_found':
                            echo "Account not found.";
                            break;

                        default:
                            echo "Something went wrong.";
                            break;
                    }

                    ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 SETTINGS CARD
            ================================================== -->

            <div class="settings-card">


                <!-- HEADER -->

                <div class="settings-header">

                    <h1>

                        <i class="bi bi-gear"></i>

                        Settings

                    </h1>

                    <p>
                        Manage your account settings and preferences.
                    </p>

                </div>


                <!-- =================================================
                     PROFILE
                ================================================== -->

                <div class="settings-section">

                    <div class="section-title">

                        <i class="bi bi-person-circle"></i>

                        Profile

                    </div>


                    <div class="section-description">

                        Manage your personal information and profile picture.

                    </div>


                    <div class="profile-preview">

                        <img
                            src="<?= e($avatar); ?>"
                            class="profile-small"
                            alt="Profile"
                            onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname ?: 'User'); ?>&background=2563eb&color=fff';"
                        >


                        <div>

                            <div class="profile-preview-name">

                                <?= e($fullname ?: 'User'); ?>

                            </div>


                            <div class="profile-preview-username">

                                @<?= e($username); ?>

                            </div>

                        </div>

                    </div>


                    <div class="mt-3">

                        <a
                            href="user_profile.php"
                            class="btn btn-outline-primary"
                        >

                            <i class="bi bi-person"></i>

                            My Profile

                        </a>

                    </div>

                </div>


                <!-- =================================================
                     USERNAME
                ================================================== -->

                <div class="settings-section">

                    <div class="section-title">

                        <i class="bi bi-person-badge"></i>

                        Username

                    </div>


                    <div class="section-description">

                        Your username is used when logging in to your account.

                    </div>


                    <form
                        method="POST"
                        action="user_settings.php"
                        onsubmit="return validateUsername();"
                    >

                        <div class="mb-3">

                            <label
                                class="form-label"
                                for="username"
                            >
                                Username
                            </label>


                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control"
                                value="<?= e($username); ?>"
                                minlength="3"
                                maxlength="50"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            name="change_username"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-pencil-square"></i>

                            Change Username

                        </button>

                    </form>

                </div>


                <!-- =================================================
                     PASSWORD
                ================================================== -->

                <div class="settings-section">

                    <div class="section-title">

                        <i class="bi bi-lock"></i>

                        Change Password

                    </div>


                    <div class="section-description">

                        Update your password for your next login.

                    </div>


                    <form
                        method="POST"
                        action="user_settings.php"
                        onsubmit="return validatePassword();"
                    >

                        <div class="mb-3">

                            <label
                                class="form-label"
                                for="current_password"
                            >
                                Current Password
                            </label>


                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-control"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label
                                class="form-label"
                                for="new_password"
                            >
                                New Password
                            </label>


                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                class="form-control"
                                minlength="6"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label
                                class="form-label"
                                for="confirm_password"
                            >
                                Confirm New Password
                            </label>


                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                minlength="6"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            name="change_password"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-key"></i>

                            Change Password

                        </button>

                    </form>

                </div>


                <!-- =================================================
                     ACCOUNT STATUS
                ================================================== -->

                <div class="settings-section">

                    <div class="section-title">

                        <i class="bi bi-person-check"></i>

                        Account Status

                    </div>


                    <div class="section-description">

                        Current status of your Event System account.

                    </div>


                    <?php

                    $normalizedStatus = strtolower(trim($accountStatus));
                    $statusClass = $normalizedStatus === 'active'
                        ? 'status-active'
                        : 'status-deactivated';

                    $statusLabel = $normalizedStatus === 'active'
                        ? 'Active'
                        : 'Deactivated';

                    ?>

                    <span
                        class="status-badge <?= e($statusClass); ?>"
                    >

                        <i class="bi <?= $normalizedStatus === 'active' ? 'bi-check-circle' : 'bi-person-dash'; ?>"></i>

                        <?= e($statusLabel); ?>

                    </span>

                </div>


                <!-- =================================================
                     DANGER ZONE
                ================================================== -->

                <div class="settings-section danger-section">

                    <div class="danger-title">

                        <i class="bi bi-exclamation-triangle"></i>

                        Danger Zone

                    </div>


                    <div class="danger-description">

                        These actions affect your account.
                        Deactivation temporarily disables your account,
                        while deleting permanently removes your account.

                    </div>


                    <!-- DEACTIVATE -->

                    <form
                        method="POST"
                        action="user_settings.php"
                        class="mb-3"
                        id="deactivateForm"
                    >

                        <input type="hidden" name="deactivation_reason" id="deactivationReasonInput">
                        <input type="hidden" name="deactivation_custom_reason" id="deactivationCustomReasonInput">
                        <input type="hidden" name="deactivation_duration" id="deactivationDurationInput">

                        <button
                            type="button"
                            class="btn btn-warning danger-button"
                            onclick="return confirm('Are you sure you want to deactivate your account?');"
                            data-bs-toggle="modal"
                            data-bs-target="#deactivateModal"
                        >

                            <i class="bi bi-person-dash"></i>

                            Deactivate Account

                        </button>

                    </form>


                    <!-- DEACTIVATION MODAL -->
                    <div
                        class="modal fade"
                        id="deactivateModal"
                        tabindex="-1"
                        aria-labelledby="deactivateModalLabel"
                        aria-hidden="true"
                    >
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">

                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="deactivateModalLabel">
                                            <i class="bi bi-person-dash text-warning"></i>
                                            Deactivate Account
                                        </h5>
                                        <small class="text-muted">
                                            You can reactivate your account by logging in again.
                                        </small>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                </div>

                                <div class="modal-body">

                                    <div class="alert alert-warning d-flex gap-2 align-items-start">
                                        <i class="bi bi-info-circle-fill"></i>
                                        <div>
                                            Your account will be logged out after deactivation.
                                            You can log in again anytime to reactivate it.
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="deactivationReason" class="form-label fw-semibold">
                                            Why are you deactivating your account?
                                        </label>

                                        <select
                                            class="form-select"
                                            id="deactivationReason"
                                        >
                                            <option value="">Select a reason</option>
                                            <option value="taking_break">I need a break</option>
                                            <option value="not_using">I'm not using the Event System</option>
                                            <option value="privacy">Privacy concerns</option>
                                            <option value="notifications">Too many notifications</option>
                                            <option value="personal">Personal reasons</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <div
                                        class="mb-4 d-none"
                                        id="customReasonWrapper"
                                    >
                                        <label for="customReason" class="form-label fw-semibold">
                                            Your reason
                                        </label>
                                        <textarea
                                            class="form-control"
                                            id="customReason"
                                            rows="3"
                                            maxlength="255"
                                            placeholder="Tell us why you are deactivating..."
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label for="deactivationDuration" class="form-label fw-semibold">
                                            How long do you want to deactivate your account?
                                        </label>

                                        <select
                                            class="form-select"
                                            id="deactivationDuration"
                                        >
                                            <option value="">Select duration</option>
                                            <option value="1_day">1 day</option>
                                            <option value="7_days">7 days</option>
                                            <option value="30_days">30 days</option>
                                            <option value="90_days">90 days</option>
                                            <option value="180_days">180 days</option>
                                            <option value="until_change">Until I change it</option>
                                        </select>

                                        <div class="form-text">
                                            You can log in again anytime to reactivate your account.
                                        </div>
                                    </div>

                                    <div
                                        class="alert alert-danger mt-4 d-none"
                                        id="deactivationValidation"
                                    ></div>

                                </div>

                                <div class="modal-footer">
                                    <button
                                        type="button"
                                        class="btn btn-light"
                                        data-bs-dismiss="modal"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-warning"
                                        id="confirmDeactivationButton"
                                    >
                                        <i class="bi bi-person-dash"></i>
                                        Deactivate Account
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- DELETE -->

                    <form
                        method="POST"
                        action="user_settings.php"
                        onsubmit="return confirmDelete();"
                    >

                        <button
                            type="submit"
                            name="delete_account"
                            class="btn btn-outline-danger danger-button"
                        >

                            <i class="bi bi-trash3"></i>

                            Delete Account

                        </button>

                    </form>

                </div>


            </div>

        </div>

    </div>

</main>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const reasonSelect = document.getElementById('deactivationReason');
    const durationSelect = document.getElementById('deactivationDuration');
    const customReasonWrapper = document.getElementById('customReasonWrapper');
    const customReason = document.getElementById('customReason');
    const validationBox = document.getElementById('deactivationValidation');
    const confirmButton = document.getElementById('confirmDeactivationButton');
    const form = document.getElementById('deactivateForm');

    if (!reasonSelect || !durationSelect || !confirmButton || !form) {
        return;
    }

    reasonSelect.addEventListener('change', function () {
        if (this.value === 'other') {
            customReasonWrapper.classList.remove('d-none');
            customReason.required = true;
        } else {
            customReasonWrapper.classList.add('d-none');
            customReason.required = false;
            customReason.value = '';
        }

        validationBox.classList.add('d-none');
        validationBox.textContent = '';
    });

    confirmButton.addEventListener('click', function () {
        const reason = reasonSelect.value;
        const duration = durationSelect.value;
        const custom = customReason.value.trim();

        validationBox.classList.add('d-none');
        validationBox.textContent = '';

        if (!reason) {
            validationBox.textContent = 'Please select a reason first.';
            validationBox.classList.remove('d-none');
            return;
        }

        if (reason === 'other' && !custom) {
            validationBox.textContent = 'Please enter your reason first.';
            validationBox.classList.remove('d-none');
            customReason.focus();
            return;
        }

        if (!duration) {
            validationBox.textContent = 'Please select how long you want to deactivate your account.';
            validationBox.classList.remove('d-none');
            return;
        }

        const reasonText = reasonSelect.options[reasonSelect.selectedIndex].text;
        const durationText = durationSelect.options[durationSelect.selectedIndex].text;

        const finalConfirm = confirm(
            'Deactivate your account for ' + durationText + '?\n\n' +
            'Reason: ' + (reason === 'other' ? custom : reasonText) + '\n\n' +
            'You will be logged out. You can log in again anytime to reactivate your account.'
        );

        if (!finalConfirm) {
            return;
        }

        document.getElementById('deactivationReasonInput').value = reason;
        document.getElementById('deactivationCustomReasonInput').value = custom;
        document.getElementById('deactivationDurationInput').value = duration;

        const submitButton = document.createElement('input');
        submitButton.type = 'hidden';
        submitButton.name = 'deactivate_account';
        submitButton.value = '1';
        form.appendChild(submitButton);

        confirmButton.disabled = true;
        confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deactivating...';

        form.submit();
    });

});
</script>

<script src="../assets/js/users/user_settings.js"></script>


</body>

</html>