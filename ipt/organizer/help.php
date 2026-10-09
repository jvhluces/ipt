<?php
session_start();

require_once __DIR__ . '/../config/db.php';



if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strcasecmp(trim($_SESSION['role']), 'Organizer') !== 0
) {
    header("Location: ../auth/login_user.php");
    exit();
}



$user_id = (int) $_SESSION['user_id'];

$fullname = 'Organizer';
$profile_pic = '';

$stmt = $conn->prepare("
    SELECT fullname, profile_pic
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $stmt->bind_result(
        $db_fullname,
        $db_profile_pic
    );

    if ($stmt->fetch()) {

        if (!empty($db_fullname)) {
            $fullname = $db_fullname;
        }

        if (!empty($db_profile_pic)) {
            $profile_pic = $db_profile_pic;
        }
    }

    $stmt->close();
}



function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


$avatar = '';

if (!empty($profile_pic)) {

    $clean_pic = trim($profile_pic);

    $clean_pic = str_replace('\\', '/', $clean_pic);

    $clean_pic = explode('?', $clean_pic)[0];


    if (
        strpos($clean_pic, 'http://') === 0 ||
        strpos($clean_pic, 'https://') === 0
    ) {

        $avatar = $clean_pic;

    } else {

        while (strpos($clean_pic, '../') === 0) {
            $clean_pic = substr($clean_pic, 3);
        }

        
        while (strpos($clean_pic, './') === 0) {
            $clean_pic = substr($clean_pic, 2);
        }

        $clean_pic = ltrim($clean_pic, '/');


       
        $project_folder = basename(__DIR__);

       

        if (strpos($clean_pic, 'ipt/') === 0) {
            $clean_pic = substr($clean_pic, 4);
        }


      
        if (
            strpos($clean_pic, 'profiles/') === 0 &&
            strpos($clean_pic, 'uploads/profiles/') !== 0
        ) {
            $clean_pic = 'uploads/' . $clean_pic;
        }


      
        $possible_files = [

            __DIR__ . '/../' . $clean_pic,

            __DIR__ . '/../uploads/profiles/' . basename($clean_pic),

            __DIR__ . '/../profiles/' . basename($clean_pic)

        ];


        $possible_browser_paths = [

            '../' . $clean_pic,

            '../uploads/profiles/' . basename($clean_pic),

            '../profiles/' . basename($clean_pic)

        ];


        foreach ($possible_files as $index => $full_path) {

            if (is_file($full_path)) {

                $avatar =
                    $possible_browser_paths[$index];

            
                $avatar .= '?v=' . filemtime($full_path);

                break;
            }
        }
    }
}



if (empty($avatar)) {

    $avatar =
        'https://ui-avatars.com/api/?name=' .
        urlencode($fullname) .
        '&background=27ae60&color=fff&size=128';
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

    <title>Help & Support | Event System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
    <link rel="stylesheet" href="../assets/css/organizer/help.css">
    <link rel="stylesheet" href="../assets/css/organizer/sidebar.css">


</head>


<body>


<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="toggleSidebar(false)"
></div>


<aside class="sidebar" id="sidebar">


    <div class="sidebar-header">

        <a href="org_dash.php" class="brand">

            <i class="bi bi-calendar-event"></i>

            Event System

        </a>

        <button
            type="button"
            class="close-sidebar"
            onclick="toggleSidebar(false)"
            aria-label="Close menu"
        >
            <i class="bi bi-x-lg"></i>
        </button>

    </div>



    <div class="sidebar-profile">

        <div class="sidebar-avatar">
            <img src="<?= e($avatar) ?>" alt="Profile">
        </div>


        <div class="sidebar-profile-info">

            <div class="sidebar-profile-name">
                <?= e($fullname) ?>
            </div>

            <div class="sidebar-profile-role">
                Organizer
            </div>

        </div>

    </div>


    <!-- NAVIGATION -->

    <div class="sidebar-menu">

        <div class="sidebar-label">
            Main Menu
        </div>


        <a
            href="org_dash.php"
        >
            <i class="bi bi-house-door"></i>
            <span>Home</span>
        </a>


        <a
            href="all_events.php"
        >
            <i class="bi bi-calendar3"></i>
            <span>All Events</span>
        </a>


        <a
            href="event_reviews.php"
        >
            <i class="bi bi-star"></i>
            <span>Event Reviews</span>
        </a>


        <div class="sidebar-label">
            Account
        </div>

        <a
            href="org_dash.php#profile"
        >
            <i class="bi bi-person"></i>
            <span>My Profile</span>
        </a>

        <a href="org_dash.php#settings">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>

        <a href="help.php" class="active">
            <i class="bi bi-question-circle"></i>
            <span>Help & Support</span>
        </a>

    </div>

    <div class="sidebar-footer">
        <a
            href="../auth/logout_user.php"
            class="logout-link"
            onclick="return confirm('Are you sure you want to logout?');"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>


<main class="main">


   

    <header class="topbar">


        <div class="d-flex align-items-center gap-3">


            <button
                type="button"
                class="mobile-menu"
                onclick="toggleSidebar(true)"
            >
                <i class="bi bi-list"></i>
            </button>


            <h1 class="page-title">
                Help & Support
            </h1>

        </div>


        <div class="top-right">


            <a
                href="organizer.php"
                class="top-profile"
            >

                <img
                    src="<?= e($avatar) ?>"
                    alt="Profile"
                >

                <span>
                    <?= e($fullname) ?>
                </span>

            </a>




            <a
                href="../auth/logout_user.php"
                class="btn btn-outline-danger btn-sm"
                onclick="return confirm('Are you sure you want to logout?');"
            >
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

    </header>



    <section class="content">



        <div class="hero">

            <h1>
                <i class="bi bi-question-circle"></i>
                How can we help?
            </h1>

            <p>
                Welcome to the Event System Help & Support page.
                Here you can find information about using the system,
                managing events, and your organizer account.
            </p>

        </div>


        <div class="help-card">

            <h4>
                <i class="bi bi-play-circle"></i>
                Getting Started
            </h4>


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <div class="step-content">

                    <h6>Open Your Dashboard</h6>

                    <p>
                        Use the Organizer Dashboard to view your
                        events and event information.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <div class="step-content">

                    <h6>Create an Event</h6>

                    <p>
                        Use the event management section to create
                        and publish your event.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <div class="step-content">

                    <h6>Manage Your Events</h6>

                    <p>
                        You can view and manage events according
                        to their current status.
                    </p>

                </div>

            </div>

        </div>



        <div class="help-card">

            <h4>
                <i class="bi bi-patch-question"></i>
                Frequently Asked Questions
            </h4>


            <div
                class="accordion"
                id="faqAccordion"
            >



                <div class="accordion-item">

                    <h2 class="accordion-header">

                        <button
                            class="accordion-button"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqOne"
                        >
                            Why can't I access the Help page when logged out?
                        </button>

                    </h2>


                    <div
                        id="faqOne"
                        class="accordion-collapse collapse show"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            The Help page is protected by the system's
                            authentication rule. You must be logged in
                            as an organizer before accessing the page.

                        </div>

                    </div>

                </div>


            

                <div class="accordion-item">

                    <h2 class="accordion-header">

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqTwo"
                        >
                            Why am I redirected to the login page?
                        </button>

                    </h2>


                    <div
                        id="faqTwo"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            If your login session has expired or you
                            are not currently logged in as an organizer,
                            the system will redirect you to the login page.

                        </div>

                    </div>

                </div>


                <div class="accordion-item">

                    <h2 class="accordion-header">

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqThree"
                        >
                            What happens when I logout?
                        </button>

                    </h2>


                    <div
                        id="faqThree"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Your current session is ended.
                            Protected organizer pages will require
                            you to log in again.

                        </div>

                    </div>

                </div>


                <div class="accordion-item">

                    <h2 class="accordion-header">

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqFour"
                        >
                            How do I return to my dashboard?
                        </button>

                    </h2>


                    <div
                        id="faqFour"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Click the
                            <strong>Dashboard</strong>
                            option in the sidebar.

                        </div>

                    </div>

                </div>



                <div class="accordion-item">

                    <h2 class="accordion-header">

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqFive"
                        >
                            How can I logout safely?
                        </button>

                    </h2>


                    <div
                        id="faqFive"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Click the
                            <strong>Logout</strong>
                            button in the sidebar or top navigation
                            and confirm the logout action.

                        </div>

                    </div>

                </div>


            </div>

        </div>



        <div class="help-card">

            <h4>
                <i class="bi bi-person-gear"></i>
                Account Help
            </h4>


            <p>
                Keep your organizer account information updated
                and make sure your profile details are correct.
            </p>


            <div class="row g-3">


                <div class="col-md-6">

                    <div class="contact-box">

                        <div class="contact-icon">
                            <i class="bi bi-person"></i>
                        </div>


                        <div>

                            <strong>
                                Profile
                            </strong>

                            <span>
                                Update your personal information
                                from your organizer profile page.
                            </span>

                        </div>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="contact-box">

                        <div class="contact-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>


                        <div>

                            <strong>
                                Account Security
                            </strong>

                            <span>
                                Keep your login credentials secure
                                and never share your password.
                            </span>

                        </div>

                    </div>

                </div>


            </div>

        </div>



        <div class="help-card">

            <h4>
                <i class="bi bi-headset"></i>
                Need More Help?
            </h4>


            <p>
                If you encounter an issue while using the IPT Event
                System, contact your system administrator or the
                person responsible for managing the system.
            </p>


            <div class="row g-3">


                <div class="col-md-6">

                    <div class="contact-box">

                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>


                        <div>

                            <strong>
                                Email Support
                            </strong>

                            <span>
                                Contact your system administrator
                                for account or system concerns.
                            </span>

                        </div>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="contact-box">

                        <div class="contact-icon">
                            <i class="bi bi-info-circle"></i>
                        </div>


                        <div>

                            <strong>
                                System Support
                            </strong>

                            <span>
                                Report technical problems or
                                unexpected system behavior.
                            </span>

                        </div>

                    </div>

                </div>


            </div>

        </div>


    </section>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>


<script src="../assets/js/organizer/help.js"></script>


</body>
</html>