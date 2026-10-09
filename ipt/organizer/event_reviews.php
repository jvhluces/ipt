<?php

session_start();

require_once __DIR__ . '/../config/db.php';


if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$organizerId = (int) $_SESSION['user_id'];



$stmtUser = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        fullname,
        email,
        contact,
        profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$stmtUser) {
    die("Failed to prepare organizer query.");
}

mysqli_stmt_bind_param(
    $stmtUser,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);

$organizer = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);


if (!$organizer) {

    session_destroy();

    header("Location: ../auth/login_user.php");
    exit();
}


/* ======================================================
   ORGANIZER NAME
====================================================== */

$organizerName =
    !empty($organizer['fullname'])
        ? $organizer['fullname']
        : 'Organizer';


/* ======================================================
   PROFILE IMAGE
====================================================== */

$profileImage = '';

if (!empty($organizer['profile_pic'])) {

    $cleanProfile = ltrim(
        str_replace(
            '\\',
            '/',
            $organizer['profile_pic']
        ),
        '/'
    );

    $possibleProfilePaths = [

        '../' . $cleanProfile,

        '../uploads/profiles/' .
        basename($cleanProfile),

        '../uploads/profile/' .
        basename($cleanProfile),

        '../uploads/' .
        basename($cleanProfile),

        '../assets/uploads/profiles/' .
        basename($cleanProfile),

        '../assets/images/' .
        basename($cleanProfile)

    ];

    foreach (
        $possibleProfilePaths
        as $path
    ) {

        if (file_exists($path)) {

            $profileImage = $path;

            break;
        }
    }
}


$selectedEvent =
    isset($_GET['event_id'])
        ? (int) $_GET['event_id']
        : 0;

$selectedRating =
    isset($_GET['rating'])
        ? (int) $_GET['rating']
        : 0;



$totalReviews = 0;

$stmtTotal = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_reviews er
     INNER JOIN events e
        ON er.event_id = e.event_id
     WHERE e.organizer_id = ?"
);

mysqli_stmt_bind_param(
    $stmtTotal,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtTotal);

$resultTotal = mysqli_stmt_get_result($stmtTotal);

$rowTotal = mysqli_fetch_assoc($resultTotal);

$totalReviews =
    (int) ($rowTotal['total'] ?? 0);

mysqli_stmt_close($stmtTotal);


/* ======================================================
   AVERAGE RATING
====================================================== */

$averageRating = 0;

$stmtAverage = mysqli_prepare(
    $conn,
    "SELECT AVG(er.rating) AS average_rating
     FROM event_reviews er
     INNER JOIN events e
        ON er.event_id = e.event_id
     WHERE e.organizer_id = ?"
);

mysqli_stmt_bind_param(
    $stmtAverage,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtAverage);

$resultAverage = mysqli_stmt_get_result($stmtAverage);

$rowAverage = mysqli_fetch_assoc($resultAverage);

$averageRating =
    (float) (
        $rowAverage['average_rating']
        ?? 0
    );

mysqli_stmt_close($stmtAverage);


/* ======================================================
   RATING COUNTS
====================================================== */

$ratingCounts = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0
];

$stmtRatings = mysqli_prepare(
    $conn,
    "SELECT
        er.rating,
        COUNT(*) AS total
     FROM event_reviews er
     INNER JOIN events e
        ON er.event_id = e.event_id
     WHERE e.organizer_id = ?
     GROUP BY er.rating"
);

mysqli_stmt_bind_param(
    $stmtRatings,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtRatings);

$resultRatings = mysqli_stmt_get_result($stmtRatings);

while (
    $ratingRow =
    mysqli_fetch_assoc($resultRatings)
) {

    $rating =
        (int) $ratingRow['rating'];

    if (isset($ratingCounts[$rating])) {

        $ratingCounts[$rating] =
            (int) $ratingRow['total'];
    }
}

mysqli_stmt_close($stmtRatings);


/* ======================================================
   ORGANIZER EVENTS
====================================================== */

$organizerEvents = [];

$stmtEvents = mysqli_prepare(
    $conn,
    "SELECT
        event_id,
        event_name,
        event_date,
        status
     FROM events
     WHERE organizer_id = ?
     ORDER BY event_date DESC"
);

mysqli_stmt_bind_param(
    $stmtEvents,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtEvents);

$resultEvents = mysqli_stmt_get_result($stmtEvents);

while (
    $event =
    mysqli_fetch_assoc($resultEvents)
) {

    $organizerEvents[] = $event;
}

mysqli_stmt_close($stmtEvents);


/* ======================================================
   REVIEW QUERY
====================================================== */

$reviewSql = "

    SELECT

        er.review_id,
        er.event_id,
        er.user_id,
        er.rating,
        er.feedback,
        er.created_at,

        e.event_name,
        e.event_date,
        e.status,

        u.fullname AS reviewer_name,
        u.email AS reviewer_email,
        u.profile_pic AS reviewer_profile

    FROM event_reviews er

    INNER JOIN events e
        ON er.event_id = e.event_id

    INNER JOIN users u
        ON er.user_id = u.user_id

    WHERE e.organizer_id = ?

";

$reviewParams = [
    $organizerId
];

$reviewTypes = "i";


/* ======================================================
   EVENT FILTER
====================================================== */

if ($selectedEvent > 0) {

    $reviewSql .= "
        AND er.event_id = ?
    ";

    $reviewParams[] = $selectedEvent;

    $reviewTypes .= "i";
}


/* ======================================================
   RATING FILTER
====================================================== */

if (
    $selectedRating >= 1 &&
    $selectedRating <= 5
) {

    $reviewSql .= "
        AND er.rating = ?
    ";

    $reviewParams[] = $selectedRating;

    $reviewTypes .= "i";
}


/* ======================================================
   ORDER
====================================================== */

$reviewSql .= "

    ORDER BY
        er.created_at DESC,
        er.review_id DESC

";


/* ======================================================
   PREPARE REVIEW QUERY
====================================================== */

$stmtReviews = mysqli_prepare(
    $conn,
    $reviewSql
);

if (!$stmtReviews) {

    die(
        "Failed to prepare review query."
    );
}


/* ======================================================
   DYNAMIC BIND
====================================================== */

$bindReviewValues = [];

$bindReviewValues[] = $reviewTypes;

foreach (
    $reviewParams
    as $key => $value
) {

    $bindReviewValues[] =
        &$reviewParams[$key];
}

call_user_func_array(
    [
        $stmtReviews,
        'bind_param'
    ],
    $bindReviewValues
);


/* ======================================================
   EXECUTE
====================================================== */

mysqli_stmt_execute($stmtReviews);

$resultReviews =
    mysqli_stmt_get_result($stmtReviews);

$reviews = [];

if ($resultReviews) {

    while (
        $review =
        mysqli_fetch_assoc($resultReviews)
    ) {

        $reviews[] = $review;
    }
}

mysqli_stmt_close($stmtReviews);


/* ======================================================
   EVENT REVIEW SUMMARY
====================================================== */

$eventSummaries = [];

$stmtSummary = mysqli_prepare(
    $conn,
    "SELECT
        e.event_id,
        e.event_name,
        e.status,
        COUNT(er.review_id) AS review_count,
        AVG(er.rating) AS average_rating

     FROM events e

     LEFT JOIN event_reviews er
        ON e.event_id = er.event_id

     WHERE e.organizer_id = ?

     GROUP BY
        e.event_id,
        e.event_name,
        e.status

     HAVING review_count > 0

     ORDER BY
        average_rating DESC,
        review_count DESC"
);

mysqli_stmt_bind_param(
    $stmtSummary,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtSummary);

$resultSummary =
    mysqli_stmt_get_result($stmtSummary);

while (
    $summary =
    mysqli_fetch_assoc($resultSummary)
) {

    $eventSummaries[] = $summary;
}

mysqli_stmt_close($stmtSummary);


/* ======================================================
   HELPER FUNCTIONS
====================================================== */

function renderStars($rating)
{
    $rating = (int) $rating;

    $html = '';

    for ($i = 1; $i <= 5; $i++) {

        if ($i <= $rating) {

            $html .=
                '<i class="bi bi-star-fill"></i>';

        } else {

            $html .=
                '<i class="bi bi-star"></i>';
        }
    }

    return $html;
}


function getStatusBadgeClass($status)
{
    return match ($status) {

        'Upcoming'
            => 'bg-primary',

        'Ongoing'
            => 'bg-warning text-dark',

        'Ended'
            => 'bg-success',

        default
            => 'bg-secondary'
    };
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
        Event Reviews | Event System
    </title>


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >


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

            background: #f5f7fb;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            color: #111827;

        }

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;

            width: 260px;
            height: 100vh;

            background: #111827;

            color: white;

            z-index: 1050;

            display: flex;

            flex-direction: column;

            transition:
                transform .3s ease;

        }


        .sidebar-header {

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

        }


        .brand {

            color: white;

            text-decoration: none;

            font-size: 20px;

            font-weight: 700;

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .brand i {

            color: #60a5fa;

        }


        .close-sidebar {

            display: none;

            background: transparent;

            border: 0;

            color: #cbd5e1;

            font-size: 24px;

        }


        .sidebar-profile {

            padding: 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .sidebar-avatar {

            width: 44px;
            height: 44px;

            border-radius: 50%;

            overflow: hidden;

            background: #374151;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

        }


        .sidebar-avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;

        }


        .sidebar-profile-name {

            font-size: 14px;

            font-weight: 600;

            color: white;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        .sidebar-profile-role {

            font-size: 12px;

            color: #9ca3af;

            margin-top: 2px;

        }


        .sidebar-menu {

            padding: 15px 12px;

            flex: 1;

            overflow-y: auto;

        }


        .sidebar-label {

            color: #6b7280;

            text-transform: uppercase;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: .08em;

            padding: 10px 12px 8px;

        }


        .sidebar-menu a {

            display: flex;

            align-items: center;

            gap: 12px;

            color: #cbd5e1;

            text-decoration: none;

            padding: 11px 13px;

            margin-bottom: 4px;

            border-radius: 9px;

            font-size: 14px;

            transition:
                background .2s ease,
                color .2s ease;

        }


        .sidebar-menu a i {

            font-size: 17px;

            width: 20px;

            text-align: center;

        }


        .sidebar-menu a:hover {

            background: #1f2937;

            color: white;

        }


        .sidebar-menu a.active {

            background: #2563eb;

            color: white;

        }


        .sidebar-footer {

            padding: 12px;

            border-top:
                1px solid
                rgba(255,255,255,.08);

        }


        .logout-link {

            color: #fca5a5 !important;

        }


        .logout-link:hover {

            background: rgba(239,68,68,.12) !important;

            color: #fecaca !important;

        }


        /* ==================================================
           OVERLAY
        ================================================== */

        .sidebar-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background: rgba(0,0,0,.45);

            z-index: 1040;

        }


        .sidebar-overlay.show {

            display: block;

        }


        /* ==================================================
           MAIN WRAPPER
        ================================================== */

        .main-wrapper {

            margin-left: 260px;

            min-height: 100vh;

        }


        /* ==================================================
           TOPBAR
        ================================================== */

        .topbar {

            height: 70px;

            background: white;

            border-bottom:
                1px solid
                #e5e7eb;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

            position: sticky;

            top: 0;

            z-index: 1000;

        }


        .topbar-left {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .burger-btn {

            display: none;

            border: 0;

            background: transparent;

            font-size: 25px;

            color: #111827;

        }


        .page-title {

            font-size: 20px;

            font-weight: 700;

            color: #111827;

        }


        .page-title i {

            color: #f59e0b;

        }


        .topbar-profile {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .topbar-avatar {

            width: 38px;
            height: 38px;

            border-radius: 50%;

            overflow: hidden;

            background: #e5e7eb;

            display: flex;

            align-items: center;

            justify-content: center;

        }


        .topbar-avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;

        }


        .topbar-name {

            font-size: 14px;

            font-weight: 600;

        }


        /* ==================================================
           MAIN CONTENT
        ================================================== */

        .main-content {

            padding: 30px;

        }


        .page-header {

            margin-bottom: 25px;

        }


        .page-header h1 {

            font-size: 28px;

            font-weight: 700;

            margin-bottom: 5px;

        }


        .page-header p {

            color: #6b7280;

            margin: 0;

        }


        /* ==================================================
           SUMMARY CARDS
        ================================================== */

        .summary-card {

            background: white;

            border:
                1px solid
                #e5e7eb;

            border-radius: 14px;

            padding: 22px;

            height: 100%;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,.04);

        }


        .summary-icon {

            width: 48px;
            height: 48px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;

            margin-bottom: 15px;

        }


        .summary-number {

            font-size: 28px;

            font-weight: 700;

        }


        .summary-label {

            color: #6b7280;

            font-size: 14px;

        }


        .rating-number {

            font-size: 28px;

            font-weight: 700;

        }


        .stars {

            color: #f59e0b;

            letter-spacing: 2px;

        }


        /* ==================================================
           CONTENT SECTIONS
        ================================================== */

        .content-card {

            background: white;

            border:
                1px solid
                #e5e7eb;

            border-radius: 14px;

            padding: 24px;

            margin-top: 25px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,.03);

        }


        .section-title {

            font-size: 19px;

            font-weight: 700;

            color: #111827;

            margin-bottom: 18px;

        }


        /* ==================================================
           RATING DISTRIBUTION
        ================================================== */

        .rating-row {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 12px;

        }


        .rating-label {

            width: 55px;

            font-size: 14px;

            white-space: nowrap;

        }


        .rating-progress {

            flex: 1;

            height: 9px;

            background: #e5e7eb;

            border-radius: 20px;

            overflow: hidden;

        }


        .rating-progress-bar {

            height: 100%;

            background: #f59e0b;

            border-radius: 20px;

        }


        .rating-total {

            width: 40px;

            text-align: right;

            color: #6b7280;

            font-size: 13px;

        }


        /* ==================================================
           EVENT SUMMARY
        ================================================== */

        .event-summary-card {

            border:
                1px solid
                #e5e7eb;

            border-radius: 12px;

            padding: 18px;

            height: 100%;

            background: white;

            transition:
                box-shadow .2s ease,
                transform .2s ease;

        }


        .event-summary-card:hover {

            box-shadow:
                0 7px 20px
                rgba(0,0,0,.07);

            transform: translateY(-2px);

        }


        .event-summary-name {

            font-weight: 700;

            color: #111827;

            margin-bottom: 8px;

        }


        /* ==================================================
           FILTER
        ================================================== */

        .filter-box {

            background: #f8fafc;

            border:
                1px solid
                #e5e7eb;

            border-radius: 12px;

            padding: 16px;

            margin-bottom: 20px;

        }


        .form-select {

            border-radius: 9px;

        }


        .form-select:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,.10);

        }


        /* ==================================================
           REVIEW CARD
        ================================================== */

        .review-card {

            border:
                1px solid
                #e5e7eb;

            border-radius: 14px;

            padding: 20px;

            margin-bottom: 16px;

            background: white;

            transition:
                box-shadow .2s ease;

        }


        .review-card:hover {

            box-shadow:
                0 6px 20px
                rgba(0,0,0,.06);

        }


        .review-header {

            display: flex;

            justify-content: space-between;

            gap: 15px;

            align-items: flex-start;

        }


        .reviewer {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .reviewer-avatar {

            width: 45px;
            height: 45px;

            border-radius: 50%;

            background: #e5e7eb;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            flex-shrink: 0;

        }


        .reviewer-avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;

        }


        .reviewer-name {

            font-weight: 700;

            color: #111827;

        }


        .reviewer-email {

            font-size: 12px;

            color: #6b7280;

        }


        .review-date {

            font-size: 12px;

            color: #9ca3af;

            text-align: right;

        }


        .review-event {

            margin-top: 15px;

            padding: 12px 14px;

            border-radius: 9px;

            background: #f8fafc;

            border:
                1px solid
                #eef2f7;

        }


        .review-event-name {

            font-weight: 600;

            color: #111827;

        }


        .review-feedback {

            margin-top: 15px;

            color: #4b5563;

            line-height: 1.6;

            word-break: break-word;

        }


        .review-feedback-empty {

            color: #9ca3af;

            font-style: italic;

        }


        /* ==================================================
           EMPTY STATE
        ================================================== */

        .empty-reviews {

            text-align: center;

            padding: 50px 20px;

            color: #6b7280;

        }


        .empty-reviews i {

            font-size: 48px;

            display: block;

            margin-bottom: 15px;

            color: #cbd5e1;

        }


        /* ==================================================
           MOBILE
        ================================================== */

        @media (max-width: 991px) {

            .sidebar {

                transform:
                    translateX(-100%);

            }


            .sidebar.open {

                transform:
                    translateX(0);

            }


            .close-sidebar {

                display: block;

            }


            .main-wrapper {

                margin-left: 0;

            }


            .burger-btn {

                display: block;

            }

        }


        @media (max-width: 768px) {

            .main-content {

                padding: 20px 15px;

            }


            .topbar {

                padding:
                    0 15px;

            }


            .topbar-name {

                display: none;

            }


            .review-header {

                flex-direction: column;

            }


            .review-date {

                text-align: left;

            }

        }


        @media (max-width: 576px) {

            .page-header h1 {

                font-size: 24px;

            }


            .content-card {

                padding: 18px;

            }


            .summary-card {

                padding: 18px;

            }


            .rating-number {

                font-size: 24px;

            }


            .rating-row {

                gap: 7px;

            }


            .rating-label {

                width: 48px;

            }

        }

    </style>

    <link
        rel="stylesheet"
        href="../assets/css/organizer/sidebar.css"
    >

</head>


<body>


<!-- ======================================================
     SIDEBAR
====================================================== -->

<aside
    id="sidebar"
    class="sidebar"
>

    <!-- BRAND -->

    <div class="sidebar-header">

        <a
            href="org_dash.php"
            class="brand"
        >

            <i class="bi bi-calendar-event"></i>

            Event System

        </a>


        <button
            type="button"
            class="close-sidebar"
            onclick="closeSidebar()"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>


    <!-- PROFILE -->

    <div class="sidebar-profile">

        <div class="sidebar-avatar">

            <?php if ($profileImage !== ''): ?>

                <img
                    src="<?= htmlspecialchars($profileImage); ?>"
                    alt="Profile"
                >

            <?php else: ?>

                <i class="bi bi-person-fill text-light"></i>

            <?php endif; ?>

        </div>


        <div>

            <div class="sidebar-profile-name">

                <?= htmlspecialchars($organizerName); ?>

            </div>

            <div class="sidebar-profile-role">

                Organizer

            </div>

        </div>

    </div>


    <!-- MENU -->

    <div class="sidebar-menu">

        <div class="sidebar-label">

            Main Menu

        </div>


        <a href="org_dash.php">

            <i class="bi bi-house-door"></i>

            <span>Home</span>

        </a>


        <a href="all_events.php">

            <i class="bi bi-calendar3"></i>

            <span>All Events</span>

        </a>


        <a
            href="event_reviews.php"
            class="active"
        >

            <i class="bi bi-star"></i>

            <span>Event Reviews</span>

        </a>


        <div class="sidebar-label mt-2">

            Account

        </div>


        <a href="org_dash.php#profile">

            <i class="bi bi-person"></i>

            <span>My Profile</span>

        </a>


        <a href="org_dash.php#settings">

            <i class="bi bi-gear"></i>

            <span>Settings</span>

        </a>


        <a href="help.php">

            <i class="bi bi-question-circle"></i>

            <span>Help & Support</span>

        </a>

    </div>


    <!-- FOOTER -->

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



<div
    id="sidebarOverlay"
    class="sidebar-overlay"
    onclick="closeSidebar()"
></div>



<div class="main-wrapper">



    <header class="topbar">

        <div class="topbar-left">

            <button
                type="button"
                class="burger-btn"
                onclick="openSidebar()"
            >

                <i class="bi bi-list"></i>

            </button>


            <div class="page-title">

                <i class="bi bi-star-fill"></i>

                Event Reviews

            </div>

        </div>
      

        <div class="topbar-profile">

          <a href="notif.php">Notification</a>
            <div class="topbar-avatar">

                <?php if ($profileImage !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($profileImage); ?>"
                        alt="Profile"
                    >

                <?php else: ?>

                    <i class="bi bi-person-fill text-secondary"></i>

                <?php endif; ?>

            </div>


            <span class="topbar-name">

                <?= htmlspecialchars($organizerName); ?>

            </span>

        </div>

    </header>



    <main class="main-content">



        <div class="page-header">

            <h1>

                Event Reviews

            </h1>

            <p>

                Monitor participant ratings and feedback
                for the events you created.

            </p>

        </div>



        <div class="row g-4">


            <!-- TOTAL REVIEWS -->

            <div class="col-xl-4 col-md-6">

                <div class="summary-card">

                    <div
                        class="summary-icon bg-primary bg-opacity-10 text-primary"
                    >

                        <i class="bi bi-chat-left-text"></i>

                    </div>


                    <div class="summary-number">

                        <?= $totalReviews; ?>

                    </div>


                    <div class="summary-label">

                        Total Reviews

                    </div>

                </div>

            </div>


            <div class="col-xl-4 col-md-6">

                <div class="summary-card">

                    <div
                        class="summary-icon bg-warning bg-opacity-10 text-warning"
                    >

                        <i class="bi bi-star-fill"></i>

                    </div>


                    <div class="rating-number">

                        <?= number_format(
                            $averageRating,
                            1
                        ); ?>

                        <span class="stars">

                            <?= renderStars(
                                round($averageRating)
                            ); ?>

                        </span>

                    </div>


                    <div class="summary-label">

                        Average Rating

                    </div>

                </div>

            </div>



            <div class="col-xl-4 col-md-12">

                <div class="summary-card">

                    <div
                        class="summary-icon bg-success bg-opacity-10 text-success"
                    >

                        <i class="bi bi-calendar-check"></i>

                    </div>


                    <div class="summary-number">

                        <?= count($eventSummaries); ?>

                    </div>


                    <div class="summary-label">

                        Events With Reviews

                    </div>

                </div>

            </div>


        </div>


        <div class="content-card">

            <div class="section-title">

                <i class="bi bi-bar-chart me-1"></i>

                Rating Distribution

            </div>


            <?php for (
                $rating = 5;
                $rating >= 1;
                $rating--
            ): ?>

                <?php

                $ratingCount =
                    $ratingCounts[$rating];

                $percentage =
                    $totalReviews > 0
                        ? (
                            $ratingCount /
                            $totalReviews
                        ) * 100
                        : 0;

                ?>


                <div class="rating-row">


                    <div class="rating-label">

                        <?= $rating; ?>

                        <i
                            class="bi bi-star-fill text-warning"
                        ></i>

                    </div>


                    <div class="rating-progress">

                        <div
                            class="rating-progress-bar"
                            style="width: <?= $percentage; ?>%;"
                        ></div>

                    </div>


                    <div class="rating-total">

                        <?= $ratingCount; ?>

                    </div>


                </div>


            <?php endfor; ?>

        </div>



        <div class="content-card">


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div class="section-title mb-0">

                    <i class="bi bi-bar-chart-line me-1"></i>

                    Event Rating Summary

                </div>


                <span class="small text-muted">

                    <?= count($eventSummaries); ?>

                    event<?= count($eventSummaries) === 1 ? '' : 's'; ?>

                </span>

            </div>


            <hr class="my-4">


            <?php if (empty($eventSummaries)): ?>


                <div class="empty-reviews">

                    <i class="bi bi-star"></i>

                    <h5>

                        No Reviews Yet

                    </h5>

                    <p class="mb-0">

                        Your events have not received any
                        reviews from participants yet.

                    </p>

                </div>


            <?php else: ?>


                <div class="row g-3">


                    <?php foreach (
                        $eventSummaries
                        as $summary
                    ): ?>


                        <div
                            class="col-xl-4 col-md-6"
                        >

                            <div class="event-summary-card">


                                <div class="event-summary-name">

                                    <?= htmlspecialchars(
                                        $summary['event_name']
                                    ); ?>

                                </div>


                                <span
                                    class="badge <?= getStatusBadgeClass(
                                        $summary['status']
                                    ); ?>"
                                >

                                    <?= htmlspecialchars(
                                        $summary['status']
                                    ); ?>

                                </span>


                                <div class="mt-3">

                                    <span class="stars">

                                        <?= renderStars(
                                            round(
                                                (float)
                                                $summary['average_rating']
                                            )
                                        ); ?>

                                    </span>


                                    <strong class="ms-2">

                                        <?= number_format(
                                            (float)
                                            $summary['average_rating'],
                                            1
                                        ); ?>

                                    </strong>

                                </div>


                                <small class="text-muted">

                                    <?= (int)
                                        $summary['review_count']; ?>

                                    review<?=

                                        (int)
                                        $summary['review_count'] === 1
                                            ? ''
                                            : 's';

                                    ?>

                                </small>


                                <div class="mt-3">

                                    <a
                                        href="event_reviews.php?event_id=<?= (int) $summary['event_id']; ?>"
                                        class="btn btn-outline-primary btn-sm"
                                    >

                                        <i class="bi bi-eye"></i>

                                        View Reviews

                                    </a>

                                </div>


                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>



        <div class="content-card">


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

                <div class="section-title mb-0">

                    <i class="bi bi-chat-square-text me-1"></i>

                    Participant Reviews

                </div>


                <span class="text-muted small">

                    <?= count($reviews); ?>

                    review<?= count($reviews) === 1 ? '' : 's'; ?>

                    shown

                </span>

            </div>



            <div class="filter-box">


                <form
                    method="GET"
                    class="row g-3"
                >


                    <div class="col-lg-5 col-md-6">

                        <label
                            class="form-label small fw-bold"
                        >

                            Filter by Event

                        </label>


                        <select
                            name="event_id"
                            class="form-select"
                        >

                            <option value="0">

                                All Events

                            </option>


                            <?php foreach (
                                $organizerEvents
                                as $event
                            ): ?>


                                <option
                                    value="<?= (int) $event['event_id']; ?>"
                                    <?= $selectedEvent === (int) $event['event_id']
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= htmlspecialchars(
                                        $event['event_name']
                                    ); ?>

                                </option>


                            <?php endforeach; ?>


                        </select>

                    </div>


                    <div class="col-lg-4 col-md-6">

                        <label
                            class="form-label small fw-bold"
                        >

                            Filter by Rating

                        </label>


                        <select
                            name="rating"
                            class="form-select"
                        >

                            <option value="0">

                                All Ratings

                            </option>


                            <?php for (
                                $r = 5;
                                $r >= 1;
                                $r--
                            ): ?>


                                <option
                                    value="<?= $r; ?>"
                                    <?= $selectedRating === $r
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= $r; ?>

                                    Star<?= $r > 1 ? 's' : ''; ?>

                                </option>


                            <?php endfor; ?>


                        </select>

                    </div>


                    

                    <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary flex-grow-1"
                        >

                            <i class="bi bi-funnel"></i>

                            Filter

                        </button>


                        <?php if (
                            $selectedEvent > 0 ||
                            ($selectedRating >= 1 && $selectedRating <= 5)
                        ): ?>

                            <a
                                href="event_reviews.php"
                                class="btn btn-outline-secondary"
                                title="Clear Filters"
                            >

                                <i class="bi bi-x-lg"></i>

                            </a>

                        <?php endif; ?>

                    </div>


                </form>


            </div>


            <?php if (empty($reviews)): ?>


                <div class="empty-reviews">

                    <i class="bi bi-chat-square-dots"></i>

                    <h5>

                        No Reviews Found

                    </h5>

                    <p class="mb-0">

                        There are no participant reviews
                        matching the selected filters.

                    </p>

                </div>


            <?php else: ?>


                <?php foreach (
                    $reviews
                    as $review
                ): ?>


                    <div class="review-card">



                        <div class="review-header">


                            <div class="reviewer">


                                <div class="reviewer-avatar">


                                    <?php if (
                                        !empty(
                                            $review['reviewer_profile']
                                        )
                                    ): ?>


                                        <img
                                            src="<?= htmlspecialchars(
                                                $review['reviewer_profile']
                                            ); ?>"
                                            alt="Reviewer"
                                        >


                                    <?php else: ?>


                                        <i class="bi bi-person-fill text-secondary"></i>


                                    <?php endif; ?>


                                </div>


                                <div>


                                    <div class="reviewer-name">

                                        <?= htmlspecialchars(
                                            $review['reviewer_name']
                                        ); ?>

                                    </div>


                                    <div class="reviewer-email">

                                        <?= htmlspecialchars(
                                            $review['reviewer_email']
                                        ); ?>

                                    </div>


                                </div>


                            </div>


                            <div class="review-date">

                                <?= date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $review['created_at']
                                    )
                                ); ?>

                            </div>


                        </div>



                        <div class="review-event">


                            <div class="small text-muted mb-1">

                                Review for

                            </div>


                            <div class="review-event-name">


                                <i
                                    class="bi bi-calendar-event text-primary me-1"
                                ></i>


                                <?= htmlspecialchars(
                                    $review['event_name']
                                ); ?>


                                <span
                                    class="badge <?= getStatusBadgeClass(
                                        $review['status']
                                    ); ?> ms-2"
                                >

                                    <?= htmlspecialchars(
                                        $review['status']
                                    ); ?>

                                </span>


                            </div>


                        </div>



                        <div class="mt-3">


                            <span class="stars">

                                <?= renderStars(
                                    $review['rating']
                                ); ?>

                            </span>


                            <strong class="ms-2">

                                <?= (int)
                                    $review['rating']; ?>

                                / 5

                            </strong>


                        </div>



                        <div class="review-feedback">


                            <?php if (
                                trim(
                                    (string)
                                    $review['feedback']
                                ) !== ''
                            ): ?>


                                <i
                                    class="bi bi-quote text-primary me-1"
                                ></i>


                                <?= nl2br(
                                    htmlspecialchars(
                                        $review['feedback']
                                    )
                                ); ?>


                            <?php else: ?>


                                <span class="review-feedback-empty">

                                    No written feedback was provided.

                                </span>


                            <?php endif; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


    </main>

</div>

<script src="../assets/js/organizer/event_reviews.js"></script>


</body>

</html>