<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

function getUserSystemSetting($conn, $settingName, $default = '1')
{
    try {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT setting_value
             FROM system_settings
             WHERE setting_name = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return $default;
        }

        mysqli_stmt_bind_param($stmt, "s", $settingName);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (!$result) {
            mysqli_stmt_close($stmt);
            return $default;
        }

        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$row || !isset($row['setting_value'])) {
            return $default;
        }

        return $row['setting_value'];

    } catch (Throwable $e) {
        return $default;
    }
}


/* =========================================================
   REVIEW SETTING
========================================================= */
$allowEventReviews = getUserSystemSetting(
    $conn,
    'allow_event_reviews',
    '1'
);

$reviewsEnabled = ((string) $allowEventReviews === '1');


/* =========================================================
   GET CURRENT USER
========================================================= */
$user = null;

$stmtUser = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        fullname,
        email,
        contact,
        address,
        organization_name,
        profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$stmtUser) {
    die("Failed to load user information.");
}

mysqli_stmt_bind_param(
    $stmtUser,
    "i",
    $userId
);

mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);

$user = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login_user.php");
    exit();
}


/* =========================================================
   PROFILE IMAGE
========================================================= */
$profilePic = trim(
    (string) ($user['profile_pic'] ?? '')
);

if ($profilePic !== '') {

    if (
        filter_var(
            $profilePic,
            FILTER_VALIDATE_URL
        ) ||
        str_starts_with(
            $profilePic,
            'data:'
        )
    ) {

        $profileImage = $profilePic;

    } else {

        $profilePic = str_replace(
            '\\',
            '/',
            $profilePic
        );

        $profilePic = ltrim(
            $profilePic,
            '/'
        );

        if (
            str_starts_with(
                $profilePic,
                'uploads/'
            )
        ) {

            $profileImage =
                '../' . $profilePic;

        } else {

            $profileImage =
                '../uploads/profiles/' .
                basename($profilePic);
        }
    }

} else {

    $profileImage =
        'https://ui-avatars.com/api/?name=' .
        urlencode(
            $user['fullname'] ?? 'User'
        ) .
        '&background=27ae60&color=fff&bold=true';
}


/* =========================================================
   CATEGORIES
========================================================= */
$categories = [
    'All',
    'Wedding',
    'Birthday',
    'Meeting',
    'Seminar',
    'Workshop',
    'Community Event'
];


/* =========================================================
   FILTERS
========================================================= */
$activeStatus =
    $_GET['status'] ?? 'Upcoming';

$activeCategory =
    $_GET['category'] ?? 'All';

$search =
    trim($_GET['search'] ?? '');


$allowedStatuses = [
    'Upcoming',
    'Ongoing',
    'Joined',
    'Ended'
];


/* =========================================================
   VALIDATE STATUS
========================================================= */
if (
    !in_array(
        $activeStatus,
        $allowedStatuses,
        true
    )
) {

    $activeStatus = 'Upcoming';
}


/* =========================================================
   VALIDATE CATEGORY
========================================================= */
if (
    $activeCategory !== 'All' &&
    !in_array(
        $activeCategory,
        $categories,
        true
    )
) {

    $activeCategory = 'All';
}


/* =========================================================
   EVENT QUERY
========================================================= */

$sql = "

    SELECT

        e.event_id,
        e.event_name,
        e.description,
        e.event_date,
        e.location,
        e.capacity,
        e.category,
        e.status,
        e.organizer_id,

        u.fullname AS organizer_name,
        u.organization_name,
      


        /* =========================================
           TOTAL PARTICIPANTS
        ========================================= */

        (
            SELECT COUNT(*)
            FROM event_participants ep2
            WHERE ep2.event_id = e.event_id
        ) AS participant_count,


        /* =========================================
           CHECK IF CURRENT USER JOINED
        ========================================= */

        EXISTS (

            SELECT 1

            FROM event_participants ep3

            WHERE ep3.event_id = e.event_id

              AND ep3.user_id = ?

        ) AS is_joined,


        /* =========================================
           CHECK IF CURRENT USER ALREADY REVIEWED
        ========================================= */

        EXISTS (

            SELECT 1

            FROM event_reviews er

            WHERE er.event_id = e.event_id

              AND er.user_id = ?

        ) AS has_review


    FROM events e


    LEFT JOIN users u

        ON e.organizer_id = u.user_id


    WHERE 1 = 1

";


/* =========================================================
   INITIAL PARAMETERS
========================================================= */

$params = [
    $userId,
    $userId
];

$types = "ii";


/* =========================================================
   STATUS FILTER
========================================================= */

if ($activeStatus === 'Joined') {

    /*
     * Joined Events means:
     * show every event the current user joined,
     * regardless of whether it is Upcoming,
     * Ongoing, or Ended.
     */

    $sql .= "

        AND EXISTS (

            SELECT 1

            FROM event_participants ep4

            WHERE ep4.event_id = e.event_id

              AND ep4.user_id = ?

        )

    ";

    $params[] = $userId;

    $types .= "i";

} else {

    $sql .= "

        AND e.status = ?

    ";

    $params[] = $activeStatus;

    $types .= "s";
}


/* =========================================================
   CATEGORY FILTER
========================================================= */

if ($activeCategory !== 'All') {

    $sql .= "

        AND e.category = ?

    ";

    $params[] = $activeCategory;

    $types .= "s";
}


/* =========================================================
   SEARCH
========================================================= */

if ($search !== '') {

    $searchLike =
        '%' . $search . '%';

    $sql .= "

        AND (

            e.event_name LIKE ?

            OR e.category LIKE ?

            OR e.location LIKE ?

            OR u.fullname LIKE ?

            OR u.organization_name LIKE ?

            OR e.status LIKE ?

        )

    ";

    for ($i = 0; $i < 6; $i++) {

        $params[] = $searchLike;

        $types .= "s";
    }
}


/* =========================================================
   ORDER EVENTS
========================================================= */

$sql .= "

    ORDER BY

        CASE

            WHEN e.status = 'Upcoming'
                THEN 1

            WHEN e.status = 'Ongoing'
                THEN 2

            WHEN e.status = 'Ended'
                THEN 3

            ELSE 4

        END,

        e.event_date ASC

";


/* =========================================================
   PREPARE EVENT QUERY
========================================================= */

$stmtEvents = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmtEvents) {

    die(
        "Event query failed: " .
        mysqli_error($conn)
    );
}


/* =========================================================
   BIND DYNAMIC PARAMETERS
========================================================= */

$bindValues = [];

$bindValues[] = $types;

foreach (
    $params as $key => $value
) {

    $bindValues[] =
        &$params[$key];
}


call_user_func_array(
    [
        $stmtEvents,
        'bind_param'
    ],
    $bindValues
);


/* =========================================================
   EXECUTE
========================================================= */

mysqli_stmt_execute(
    $stmtEvents
);


$resultEvents =
    mysqli_stmt_get_result(
        $stmtEvents
    );


$events = [];


if ($resultEvents) {

    while (
        $row =
        mysqli_fetch_assoc(
            $resultEvents
        )
    ) {

        $events[] = $row;
    }
}


mysqli_stmt_close(
    $stmtEvents
);


/* =========================================================
   CATEGORY CLASS
========================================================= */

function getCategoryClass($category)
{
    return match ($category) {

        'Wedding'
            => 'category-wedding',

        'Birthday'
            => 'category-birthday',

        'Meeting'
            => 'category-meeting',

        'Seminar'
            => 'category-seminar',

        'Workshop'
            => 'category-workshop',

        'Community Event'
            => 'category-community',

        default
            => 'category-default'
    };
}


/* =========================================================
   STATUS CLASS
========================================================= */

function getStatusClass($status)
{
    return match ($status) {

        'Upcoming'
            => 'status-upcoming',

        'Ongoing'
            => 'status-ongoing',

        'Ended'
            => 'status-ended',

        default
            => 'status-default'
    };
}


/* =========================================================
   FORMAT EVENT DATE
========================================================= */

function formatEventDate($date)
{
    if (!$date) {

        return 'Date not available';
    }

    $timestamp =
        strtotime($date);

    if (!$timestamp) {

        return htmlspecialchars(
            $date
        );
    }

    return date(
        'F d, Y',
        $timestamp
    );
}


/* =========================================================
   FORMAT EVENT TIME
========================================================= */

function formatEventTime($date)
{
    if (!$date) {

        return 'Time not available';
    }

    $timestamp =
        strtotime($date);

    if (!$timestamp) {

        return '';
    }

    return date(
        'h:i A',
        $timestamp
    );
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

$successMessage = '';

if (
    isset($_GET['success'])
) {

    switch (
        $_GET['success']
    ) {

        case 'joined':

            $successMessage =

                '<i class="bi bi-check-circle-fill"></i>
                 You successfully joined the event.';

            break;


        case 'left':

            $successMessage =

                '<i class="bi bi-check-circle-fill"></i>
                 You successfully left the event.';

            break;
    }
}


/* =========================================================
   ERROR MESSAGE
========================================================= */

$errorMessage = '';

if (
    isset($_GET['error'])
) {

    switch (
        $_GET['error']
    ) {

        case 'already_joined':

            $errorMessage =

                '<i class="bi bi-info-circle-fill"></i>
                 You have already joined this event.';

            break;


        case 'event_full':

            $errorMessage =

                '<i class="bi bi-people-fill"></i>
                 This event is already full.';

            break;


        case 'ended_event':

            $errorMessage =

                '<i class="bi bi-calendar-x-fill"></i>
                 You cannot join an ended event.';

            break;


        case 'ongoing_event':

            $errorMessage =

                '<i class="bi bi-clock-fill"></i>
                 You cannot join an ongoing event.';

            break;


        case 'not_joined':

            $errorMessage =

                '<i class="bi bi-info-circle-fill"></i>
                 You are not a participant of this event.';

            break;


        case 'cannot_leave':

            $errorMessage =

                '<i class="bi bi-exclamation-circle-fill"></i>
                 You cannot leave this event.';

            break;


        case 'review_not_available':

            $errorMessage =

                '<i class="bi bi-star-slash-fill"></i>
                 Reviews are not available for this event.';

            break;


        case 'reviews_disabled':

            $errorMessage =

                '<i class="bi bi-star-slash"></i>
                 Event reviews are currently disabled by the administrator.';

            break;


        case 'not_participant':

            $errorMessage =

                '<i class="bi bi-person-x-fill"></i>
                 You must join the event before leaving a review.';

            break;


        case 'already_reviewed':

            $errorMessage =

                '<i class="bi bi-check-circle-fill"></i>
                 You have already reviewed this event.';

            break;


        case 'event_not_found':

            $errorMessage =

                '<i class="bi bi-calendar-x-fill"></i>
                 Event not found.';

            break;


        case 'join_disabled':

            $errorMessage =

                '<i class="bi bi-slash-circle-fill"></i>
                 Joining events is currently disabled by the administrator. You cannot join any event at this time.';

            break;


        case 'join_failed':

            $errorMessage =

                '<i class="bi bi-exclamation-triangle-fill"></i>
                 Failed to join the event. Please try again.';

            break;


        case 'leave_failed':

            $errorMessage =

                '<i class="bi bi-exclamation-triangle-fill"></i>
                 Failed to leave the event. Please try again.';

            break;


        case 'review_failed':

            $errorMessage =

                '<i class="bi bi-exclamation-triangle-fill"></i>
                 Failed to submit your review.';

            break;
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
        Audience Dashboard | Event System
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/users/user.css"
    >


    <style>

        .event-review-notice {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 7px 11px;

            border-radius: 8px;

            background: #f8f9fa;

            border: 1px solid #dee2e6;

            color: #6c757d;

            font-size: 0.78rem;

            line-height: 1.3;

            max-width: 100%;
        }


        .event-review-notice i {

            font-size: 0.9rem;

            flex-shrink: 0;
        }


        .event-review-notice span {

            display: inline-block;
        }



        @media (max-width: 576px) {

            .event-review-notice {

                font-size: 0.74rem;

                padding: 6px 9px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR OVERLAY
========================================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="closeSidebar()"
></div>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside
    class="sidebar"
    id="sidebar"
>


    <!-- PROFILE -->

    <div class="profile-box">

        <img
            src="<?= htmlspecialchars($profileImage); ?>"
            alt="Profile"
        >


        <div class="profile-name">

            <?= htmlspecialchars(
                $user['fullname'] ?? 'User'
            ); ?>

        </div>


        <div class="profile-role">

            Audience

        </div>

    </div>


    <!-- MENU -->

    <nav class="sidebar-menu">


        <div class="menu-label">

            Main

        </div>


        <!-- HOME -->

        <a
            href="user.php?status=Upcoming"
            class="<?= $activeStatus === 'Upcoming' ? 'active' : ''; ?>"
        >

            <i class="bi bi-house-door"></i>

            <span>
                Home
            </span>

        </a>


        <!-- JOINED EVENTS -->

        <a
            href="user.php?status=Joined"
            class="<?= $activeStatus === 'Joined' ? 'active' : ''; ?>"
        >

            <i class="bi bi-calendar-check"></i>

            <span>
                Joined Events
            </span>

        </a>


        <!-- ENDED EVENTS -->

        <a
            href="user.php?status=Ended&category=All"
            class="<?= $activeStatus === 'Ended' ? 'active' : ''; ?>"
        >

            <i class="bi bi-calendar-x"></i>

            <span>
                Ended Events
            </span>

        </a>


        <!-- ACCOUNT -->

        <div class="sidebar-section-title">

            Account

        </div>


        <!-- PROFILE -->

        <a href="user_profile.php">

            <i class="bi bi-person-circle"></i>

            <span>
                My Profile
            </span>

        </a>


        <!-- SETTINGS -->

        <a href="user_settings.php">

            <i class="bi bi-gear"></i>

            <span>
                Settings
            </span>

        </a>


        <!-- HELP -->

        <a href="help_user.php">

            <i class="bi bi-question-circle"></i>

            <span>
                Help &amp; Support
            </span>

        </a>




        <a
            href="../auth/logout_user.php"
            class="logout-link"
            onclick="return confirm('Are you sure you want to logout?');"
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>


    </nav>

</aside>


<!-- =========================================================
     MAIN AREA
========================================================= -->

<div class="main-area">


    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <header class="topbar">


        <div class="d-flex align-items-center gap-3">


            <!-- MOBILE MENU -->

            <button
                class="mobile-menu-btn"
                onclick="toggleSidebar()"
                type="button"
            >

                <i class="bi bi-list"></i>

            </button>


            <!-- TITLE -->

            <div>

                <h1 class="page-title">

                    Events

                </h1>


                <div class="page-subtitle">

                    Discover and join upcoming events

                </div>

            </div>

        </div>


        <!-- USER -->

        <div class="d-none d-md-flex align-items-center gap-2">

            <i class="bi bi-person-circle text-success"></i>

            <span class="small text-muted">

                <?= htmlspecialchars(
                    $user['fullname'] ?? 'User'
                ); ?>

            </span>

        </div>


    </header>


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <main class="content-area">


        <!-- =================================================
             SUCCESS MESSAGE
        ================================================= -->

        <?php if ($successMessage !== ''): ?>

            <div
                class="alert alert-success custom-alert alert-dismissible fade show"
                role="alert"
            >

                <?= $successMessage; ?>


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
        ================================================= -->

        <?php if ($errorMessage !== ''): ?>

            <div
                class="alert alert-danger custom-alert alert-dismissible fade show"
                role="alert"
            >

                <?= $errorMessage; ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATUS TABS
        ================================================= -->

        <div class="status-tabs">


            <?php foreach (
                $allowedStatuses
                as $statusTab
            ): ?>


                <?php

                $tabParams = [

                    'status'
                        => $statusTab,

                    'category'
                        => $activeCategory

                ];


                if ($search !== '') {

                    $tabParams['search'] =
                        $search;
                }


                $tabUrl =

                    'user.php?' .
                    http_build_query(
                        $tabParams
                    );

                ?>


                <a
                    href="<?= htmlspecialchars($tabUrl); ?>"
                    class="status-tab <?= $activeStatus === $statusTab ? 'active' : ''; ?>"
                >


                    <?php if (
                        $statusTab === 'Upcoming'
                    ): ?>

                        <i class="bi bi-calendar-event"></i>


                    <?php elseif (
                        $statusTab === 'Ongoing'
                    ): ?>

                        <i class="bi bi-clock"></i>


                    <?php elseif (
                        $statusTab === 'Joined'
                    ): ?>

                        <i class="bi bi-calendar-check"></i>


                    <?php else: ?>

                        <i class="bi bi-calendar-x"></i>

                    <?php endif; ?>


                    <?= htmlspecialchars(
                        $statusTab
                    ); ?>


                </a>


            <?php endforeach; ?>


        </div>


        <!-- =================================================
             SEARCH
        ================================================= -->

        <form
            method="GET"
            class="search-box"
        >


            <input
                type="hidden"
                name="status"
                value="<?= htmlspecialchars($activeStatus); ?>"
            >


            <input
                type="hidden"
                name="category"
                value="<?= htmlspecialchars($activeCategory); ?>"
            >


            <div class="input-group">


                <span
                    class="input-group-text bg-white border-0"
                >

                    <i class="bi bi-search text-muted"></i>

                </span>


                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search events, organizer, location..."
                    value="<?= htmlspecialchars($search); ?>"
                >


                <?php if ($search !== ''): ?>

                    <a
                        href="user.php?status=<?= urlencode($activeStatus); ?>&category=<?= urlencode($activeCategory); ?>"
                        class="btn btn-light"
                    >

                        Clear

                    </a>

                <?php endif; ?>


                <button
                    type="submit"
                    class="btn btn-success"
                >

                    Search

                </button>


            </div>


        </form>


        <!-- =================================================
             CATEGORIES
        ================================================= -->

        <div class="category-scroll">


            <?php foreach (
                $categories
                as $category
            ): ?>


                <?php

                $categoryParams = [

                    'status'
                        => $activeStatus,

                    'category'
                        => $category

                ];


                if ($search !== '') {

                    $categoryParams['search'] =
                        $search;
                }


                $categoryUrl =

                    'user.php?' .
                    http_build_query(
                        $categoryParams
                    );

                ?>


                <a
                    href="<?= htmlspecialchars($categoryUrl); ?>"
                    class="category-chip <?= $activeCategory === $category ? 'active' : ''; ?>"
                >

                    <?= htmlspecialchars(
                        $category
                    ); ?>

                </a>


            <?php endforeach; ?>


        </div>


        <!-- =================================================
             EVENTS
        ================================================= -->

        <?php if (empty($events)): ?>


            <!-- EMPTY -->

            <div class="empty-state">


                <i class="bi bi-calendar2-x"></i>


                <h5>

                    No Events Found

                </h5>


                <p>

                    There are no events matching your current filters.

                </p>


            </div>


        <?php else: ?>


            <!-- EVENT GRID -->

            <div
                class="row g-4"
                id="events"
            >


                <?php foreach (
                    $events
                    as $event
                ): ?>


                    <?php

                    /* =====================================
                       EVENT VARIABLES
                    ===================================== */

                    $eventId =
                        (int) $event['event_id'];


                    $eventStatus =
                        $event['status'];


                    $isJoined =
                        (int) $event['is_joined'] === 1;


                    $hasReview =
                        (int) $event['has_review'] === 1;


                    $participantCount =
                        (int) $event['participant_count'];


                    $capacity =
                        (int) $event['capacity'];


                    $isFull =
                        $capacity > 0 &&
                        $participantCount >= $capacity;


                    $categoryClass =
                        getCategoryClass(
                            $event['category']
                        );


                    $statusClass =
                        getStatusClass(
                            $eventStatus
                        );

                    ?>


                    <!-- =================================================
                         EVENT COLUMN
                    ================================================= -->

                    <div
                        class="col-xl-4 col-lg-6 col-md-6"
                    >


                        <div class="event-card">


                            <div class="event-card-body">


                                <!-- =====================================
                                     EVENT TOP
                                ====================================== -->

                                <div class="event-top">


                                    <h2 class="event-title">

                                        <?= htmlspecialchars(
                                            $event['event_name']
                                        ); ?>

                                    </h2>


                                    <span
                                        class="event-category <?= $categoryClass; ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $event['category']
                                        ); ?>

                                    </span>


                                </div>


                                <!-- =====================================
                                     ORGANIZER
                                ====================================== -->

                                <div class="event-organizer">


                                    <i class="bi bi-person-badge"></i>


                                    Organized by


                                    <strong>

                                        <?= htmlspecialchars(

                                            $event['organizer_name']

                                            ?: $event['organization_name']

                                            ?: 'Organizer'

                                        ); ?>

                                    </strong>


                                </div>


                                <!-- =====================================
                                     EVENT INFO
                                ====================================== -->

                                <div class="event-info">


                                    <!-- DATE -->

                                    <div class="event-info-item">


                                        <i class="bi bi-calendar3"></i>


                                        <span>

                                            <?= formatEventDate(
                                                $event['event_date']
                                            ); ?>

                                        </span>


                                    </div>


                                    <!-- TIME -->

                                    <div class="event-info-item">


                                        <i class="bi bi-clock"></i>


                                        <span>

                                            <?= formatEventTime(
                                                $event['event_date']
                                            ); ?>

                                        </span>


                                    </div>


                                    <!-- LOCATION -->

                                    <div class="event-info-item">


                                        <i class="bi bi-geo-alt"></i>


                                        <span>

                                            <?= htmlspecialchars(
                                                $event['location']
                                            ); ?>

                                        </span>


                                    </div>


                                    <!-- PARTICIPANTS -->

                                    <div class="event-info-item">


                                        <i class="bi bi-people"></i>


                                        <span>

                                            <?= $participantCount; ?>

                                            participant<?=

                                                $participantCount == 1
                                                    ? ''
                                                    : 's';

                                            ?>


                                            <?php if (
                                                $capacity > 0
                                            ): ?>

                                                / <?= $capacity; ?>

                                            <?php endif; ?>


                                        </span>


                                    </div>


                                </div>


                                <!-- =====================================
                                     STATUS
                                ====================================== -->

                                <div class="event-status-row">


                                    <span
                                        class="event-status <?= $statusClass; ?>"
                                    >


                                        <?php if (
                                            $eventStatus === 'Upcoming'
                                        ): ?>

                                            <i class="bi bi-calendar-check"></i>


                                        <?php elseif (
                                            $eventStatus === 'Ongoing'
                                        ): ?>

                                            <i class="bi bi-broadcast"></i>


                                        <?php elseif (
                                            $eventStatus === 'Ended'
                                        ): ?>

                                            <i class="bi bi-check-circle"></i>


                                        <?php endif; ?>


                                        <?= htmlspecialchars(
                                            $eventStatus
                                        ); ?>


                                    </span>


                                    <!-- ONGOING MESSAGE -->

                                    <?php if (
                                        $eventStatus === 'Ongoing'
                                    ): ?>

                                        <span
                                            class="small text-muted ms-2"
                                        >

                                            Happening now

                                        </span>

                                    <?php endif; ?>


                                    <!-- JOINED INDICATOR -->

                                    <?php if (
                                        $isJoined
                                    ): ?>

                                        <span
                                            class="small text-success ms-2"
                                        >

                                            <i class="bi bi-check-circle-fill"></i>

                                            Joined

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <!-- =====================================
                                     ACTIONS
                                ====================================== -->

                                <div class="event-actions">


                                    <!-- =================================
                                         VIEW DETAILS
                                    ================================== -->

                                    <a
                                        href="view_event.php?event_id=<?= $eventId; ?>"
                                        class="btn btn-outline-success btn-sm"
                                    >

                                        <i class="bi bi-eye"></i>

                                        View Details

                                    </a>


                                    <!-- =================================
                                         UPCOMING
                                    ================================== -->

                                    <?php if (
                                        $eventStatus === 'Upcoming'
                                    ): ?>


                                        <?php if (
                                            $isJoined
                                        ): ?>


                                            <!-- LEAVE EVENT -->

                                            <form
                                                method="POST"
                                                action="../actions/event_action.php"
                                                onsubmit="return confirm('Are you sure you want to leave this event?');"
                                                class="d-inline"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="leave"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="event_id"
                                                    value="<?= $eventId; ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-danger btn-sm"
                                                >

                                                    <i class="bi bi-box-arrow-left"></i>

                                                    Leave Event

                                                </button>


                                            </form>


                                        <?php elseif (
                                            $isFull
                                        ): ?>


                                            <!-- EVENT FULL -->

                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm"
                                                disabled
                                            >

                                                <i class="bi bi-people"></i>

                                                Event Full

                                            </button>


                                        <?php else: ?>


                                            <!-- JOIN EVENT -->

                                            <form
                                                method="POST"
                                                action="../actions/event_action.php"
                                                onsubmit="return confirm('Do you want to join this event?');"
                                                class="d-inline"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="join"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="event_id"
                                                    value="<?= $eventId; ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-success btn-sm"
                                                >

                                                    <i class="bi bi-calendar-plus"></i>

                                                    Join Event

                                                </button>


                                            </form>


                                        <?php endif; ?>


                                    <!-- =================================
                                         ONGOING
                                    ================================== -->

                                    <?php elseif (
                                        $eventStatus === 'Ongoing'
                                    ): ?>


                                        <?php if (
                                            $isJoined
                                        ): ?>


                                            <!-- USER JOINED -->

                                            <button
                                                type="button"
                                                class="btn btn-outline-success btn-sm"
                                                disabled
                                            >

                                                <i class="bi bi-check-circle"></i>

                                                You Joined

                                            </button>


                                        <?php endif; ?>


                                    <!-- =================================
                                         ENDED
                                    ================================== -->

                                    <?php elseif (
                                        $eventStatus === 'Ended'
                                    ): ?>


                                        <?php if (
                                            $isJoined
                                        ): ?>


                                            <!-- =================================
                                                 USER PARTICIPATED
                                            ================================== -->

                                            <?php if (
                                                !$reviewsEnabled
                                            ): ?>


                                                <!-- REVIEWS DISABLED -->

                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    disabled
                                                >

                                                    <i class="bi bi-star-slash"></i>

                                                    Reviews Disabled

                                                </button>


                                            <?php elseif (
                                                !$hasReview
                                            ): ?>


                                                <!-- LEAVE REVIEW -->

                                                <a
                                                    href="../actions/review_event.php?event_id=<?= $eventId; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >

                                                    <i class="bi bi-star"></i>

                                                    Leave Review

                                                </a>


                                            <?php else: ?>


                                                <!-- ALREADY REVIEWED -->

                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    disabled
                                                >

                                                    <i class="bi bi-check-circle"></i>

                                                    Reviewed

                                                </button>


                                            <?php endif; ?>


                                        <?php else: ?>


                                            <!-- =================================
                                                 USER DID NOT PARTICIPATE
                                            ================================== -->

                                            <div
                                                class="event-review-notice"
                                                title="Only participants who joined this event can leave a review."
                                            >

                                                <i class="bi bi-info-circle"></i>


                                                <span>

                                                    Review unavailable —
                                                    you did not participate
                                                    in this event.

                                                </span>


                                            </div>


                                        <?php endif; ?>


                                    <?php endif; ?>


                                </div>


                            </div>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </main>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script
    src="../assets/js/users/user.js"
></script>


</body>

</html>