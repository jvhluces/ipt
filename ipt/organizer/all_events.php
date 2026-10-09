
<?php

session_start();

require_once '../config/db.php';




if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: ../auth/login_user.php");
    exit();
}


$user_id = (int) $_SESSION['user_id'];




$userData = null;


$stmtUser = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        username,
        fullname,
        email,
        contact,
        profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);


if ($stmtUser) {

    mysqli_stmt_bind_param(
        $stmtUser,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmtUser);

    $resultUser = mysqli_stmt_get_result(
        $stmtUser
    );

    $userData = mysqli_fetch_assoc(
        $resultUser
    );

    mysqli_stmt_close($stmtUser);
}


if (!$userData) {

    session_destroy();

    header(
        "Location: ../auth/login_user.php"
    );

    exit();
}



$organizerName =
    $userData['fullname']
    ?? 'Organizer';


$organizerEmail =
    $userData['email']
    ?? '';


$organizerContact =
    $userData['contact']
    ?? '';



function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}




$profileImage = '';


if (!empty($userData['profile_pic'])) {

    $cleanProfile = ltrim(
        str_replace(
            '\\',
            '/',
            $userData['profile_pic']
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



$allEvents = mysqli_query(
    $conn,

    "SELECT
        events.*,

        users.fullname AS organizer_name,

        users.email AS organizer_email

     FROM events

     LEFT JOIN users
        ON events.organizer_id =
           users.user_id

     ORDER BY
        events.event_date ASC"
);

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
        All Events - Event System
    </title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            min-height: 100vh;

            background: #f4f6f9;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            color: #333;
        }



        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            width: 260px;

            height: 100vh;

            background: #111827;

            color: white;

            z-index: 2000;

            box-shadow:
                4px 0 15px
                rgba(0, 0, 0, 0.15);

            overflow-y: auto;

            overflow-x: hidden;

            display: flex;

            flex-direction: column;
        }


        .sidebar::-webkit-scrollbar {

            width: 6px;
        }


        .sidebar::-webkit-scrollbar-track {

            background: #111827;
        }


        .sidebar::-webkit-scrollbar-thumb {

            background: #374151;

            border-radius: 10px;
        }


        .sidebar-header {

            height: 70px;

            padding: 0 20px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.1);

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .sidebar-header .brand {

            margin: 0;

            color: white;

            text-decoration: none;

            font-weight: 700;

            font-size: 20px;

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .sidebar-header .brand i {

            color: #60a5fa;
        }


        .close-sidebar {

            display: none;

            background: none;

            border: none;

            color: white;

            font-size: 24px;

            cursor: pointer;
        }



        .sidebar-profile {

            padding: 20px;

            display: flex;

            align-items: center;

            gap: 12px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.1);
        }


        .sidebar-avatar {

            width: 44px;

            height: 44px;

            border-radius: 50%;

            background: #374151;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            overflow: hidden;

        }


        .sidebar-avatar img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;
        }


        .sidebar-profile-info {

            min-width: 0;
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

            color: #9ca3af;

            font-size: 12px;

            margin-top: 2px;
        }



        .sidebar-menu {

            padding:
                15px 12px;

            flex: 1;
        }


        .sidebar-menu a {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 11px 13px;

            margin-bottom: 4px;

            border-radius: 9px;

            color: #cbd5e1;

            text-decoration: none;

            transition:
                background 0.2s ease,
                color 0.2s ease;
        }


        .sidebar-menu a:hover {

            background: #1f2937;

            color: white;

        }


        .sidebar-menu a.active {

            background: #2563eb;

            color: white;
        }


        .sidebar-menu i {

            font-size: 17px;

            width: 20px;

            text-align: center;
        }



        .menu-title {

            color: #6b7280;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.08em;

            padding:
                10px 12px 8px;
        }



        .sidebar-divider {

            height: 1px;

            background:
                rgba(255, 255, 255, 0.1);

            margin:
                15px 5px;
        }


        .sidebar-footer {

            padding: 12px;

            border-top:
                1px solid
                rgba(255, 255, 255, 0.08);
        }


        .sidebar-footer a {

            display: flex;

            align-items: center;

            gap: 12px;

            color: #fca5a5;

            padding: 11px 13px;

            border-radius: 9px;

            margin: 0;

            text-decoration: none;

            font-size: 14px;

            transition: background 0.2s ease, color 0.2s ease;
        }


        .sidebar-footer a:hover {

            background: #1f2937;

            color: white;
        }



        .overlay {

            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(0, 0, 0, 0.45);

            z-index: 1500;
        }


        .overlay.show {

            display: block;
        }


        .top-navbar {

            position: fixed;

            top: 0;

            right: 0;

            left: 260px;

            height: 70px;

            background: #111827;

            color: white;

            padding:
                0 25px;

            display: flex;

            align-items: center;

            gap: 15px;

            z-index: 1000;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.15);
        }


        .menu-btn {

            display: none;

            background: transparent;

            border: none;

            color: white;

            font-size: 26px;

            cursor: pointer;

            padding: 5px;
        }


        .nav-title {

            font-size: 20px;

            font-weight: 700;
        }


        .nav-right {

            margin-left: auto;

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .back-dashboard {

            color: white;

            text-decoration: none;

            border:
                1px solid
                rgba(255, 255, 255, 0.3);

            padding:
                8px 14px;

            border-radius: 7px;

            transition:
                0.2s ease;
        }


        .back-dashboard:hover {

            background:
                rgba(255, 255, 255, 0.1);

            color: white;
        }


        /* =====================================================
           MAIN CONTENT

           THIS IS THE IMPORTANT FIX.

           Sidebar width = 260px.
           Main content begins after sidebar.
        ====================================================== */

        .main-content {

            margin-left: 260px;

            padding:
                100px 35px 40px;

            min-height: 100vh;

            width: calc(100% - 260px);
        }


        .main-container {

            width: 100%;

            max-width: 1200px;

            margin: 0 auto;
        }


        .page-header {

            background: white;

            padding: 25px;

            border-radius: 14px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.06);

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;
        }


        .page-header h2 {

            margin: 0;

            font-weight: 700;

            color: #111827;
        }


        .page-header p {

            margin:
                5px 0 0;

            color: #6b7280;
        }


        .event-card {

            background: white;

            border-radius: 14px;

            padding: 22px;

            margin-bottom: 18px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.06);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .event-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 6px 18px
                rgba(0, 0, 0, 0.10);
        }

        .event-top {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 15px;
        }


        .event-title {

            font-size: 21px;

            font-weight: 700;

            margin-bottom: 5px;

            color: #111827;
        }


        .organizer {

            color: #6b7280;

            font-size: 14px;
        }


        .event-info {

            display: flex;

            flex-wrap: wrap;

            gap: 18px;

            margin-top: 18px;
        }


        .event-info span {

            color: #555;

            font-size: 14px;
        }


        .event-info i {

            margin-right: 6px;
        }



        .description {

            margin-top: 15px;

            color: #666;

            line-height: 1.6;

            white-space: pre-line;
        }


        .category-badge {

            background: #eef2ff;

            color: #3730a3;

            padding:
                5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;
        }



        .action-area {

            margin-top: 18px;

            display: flex;

            gap: 8px;

            flex-wrap: wrap;
        }



        .empty-state {

            background: white;

            padding: 50px;

            text-align: center;

            border-radius: 14px;

            color: #777;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.06);
        }


        .empty-state i {

            color: #9ca3af;
        }



        @media (max-width: 991.98px) {

            .sidebar {

                transform:
                    translateX(-100%);

                transition:
                    transform 0.25s ease;
            }


            .sidebar.open {

                transform:
                    translateX(0);
            }


            .close-sidebar {

                display: block;
            }


            .top-navbar {

                left: 0;

                height: 64px;

                padding:
                    0 15px;
            }


            .menu-btn {

                display: block;
            }


            .back-dashboard {

                display: none;
            }


            .main-content {

                margin-left: 0;

                width: 100%;

                padding:
                    89px 20px 35px;
            }


            .nav-title {

                font-size: 18px;
            }

        }


        @media (max-width: 700px) {

            .page-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }


            .page-header .btn {

                width: 100%;
            }


            .event-top {

                flex-direction: column;
            }


            .event-info {

                flex-direction: column;

                gap: 8px;
            }


            .event-card {

                padding: 18px;
            }


            .main-content {

                padding:
                    84px 15px 30px;
            }

        }

    </style>

    <link
        rel="stylesheet"
        href="../assets/css/organizer/sidebar.css"
    >

</head>


<body>


<aside
    class="sidebar"
    id="sidebar"
>



    <div class="sidebar-header">

        <a href="org_dash.php" class="brand">

            <i class="bi bi-calendar-event"></i>

            Event System

        </a>


        <button
            type="button"
            class="close-sidebar"
            onclick="closeSidebar()"
            aria-label="Close menu"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>



    <div class="sidebar-profile">


        <div class="sidebar-avatar">


            <?php if (!empty($profileImage)): ?>

                <img
                    src="<?= e($profileImage); ?>"
                    alt="Profile"
                >

            <?php else: ?>

                <i class="bi bi-person-fill"></i>

            <?php endif; ?>


        </div>


        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name">
                <?= e($organizerName); ?>
            </div>
            <div class="sidebar-profile-role">
                Organizer
            </div>
        </div>


    </div>


    <!-- SIDEBAR MENU -->

    <div class="sidebar-menu">


        <!-- MAIN -->

        <div class="sidebar-label">

            Main Menu

        </div>


        <!-- HOME -->

        <a
            href="org_dash.php"
        >

            <i class="bi bi-house-door"></i>

            Home

        </a>


        <!-- ALL EVENTS -->

        <a
            href="all_events.php"
            class="active"
        >

            <i class="bi bi-calendar3"></i>

            All Events

        </a>


        <!-- EVENT REVIEWS -->

        <a
            href="event_reviews.php"
        >

            <i class="bi bi-star"></i>

            Event Reviews

        </a>


      

        <div class="sidebar-label">

            Account

        </div>




        <a
            href="org_dash.php#profile"
        >

            <i class="bi bi-person"></i>

            My Profile

        </a>



        <a
            href="org_dash.php#settings"
        >

            <i class="bi bi-gear"></i>

            Settings

        </a>


 

        <a
            href="help.php"
        >

            <i class="bi bi-question-circle"></i>

            Help & Support

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


<div
    class="overlay"
    id="overlay"
    onclick="closeSidebar()"
></div>




<nav class="top-navbar">



    <button
        type="button"
        class="menu-btn"
        onclick="toggleSidebar()"
        aria-label="Open menu"
    >

        <i class="bi bi-list"></i>

    </button>




    <span class="nav-title">

        Event System

    </span>


    <div class="nav-right">

        <a
            href="org_dash.php"
            class="back-dashboard"
        >

            <i class="bi bi-arrow-left"></i>

            Back to Dashboard

        </a>

    </div>


</nav>



<main class="main-content">


    <div class="main-container">

        <div class="page-header">


            <div>

                <h2>

                    <i class="bi bi-calendar3"></i>

                    All Events

                </h2>


                <p>

                    View all events created by organizers.

                </p>

            </div>


            <a
                href="add.php"
                class="btn btn-primary"
            >

                <i class="bi bi-plus-circle"></i>

                Create Event

            </a>


        </div>


        <?php if (
            !$allEvents ||
            mysqli_num_rows($allEvents) === 0
        ): ?>


            <div class="empty-state">


                <i
                    class="bi bi-calendar-x fs-1"
                ></i>


                <h4 class="mt-3">

                    No Events Available

                </h4>


                <p>

                    There are currently no events
                    in the system.

                </p>


            </div>


        <?php else: ?>



            <?php while (
                $row =
                mysqli_fetch_assoc($allEvents)
            ): ?>


                <?php


                $status = trim(
                    $row['status'] ?? ''
                );


                if ($status === 'Upcoming') {

                    $statusClass =
                        'bg-info';

                } elseif (
                    $status === 'Ongoing'
                ) {

                    $statusClass =
                        'bg-success';

                } elseif (
                    $status === 'Ended'
                ) {

                    $statusClass =
                        'bg-secondary';

                } else {

                    $statusClass =
                        'bg-dark';
                }



                $eventTimestamp = false;


                if (
                    !empty(
                        $row['event_date']
                    )
                ) {

                    $eventTimestamp =
                        strtotime(
                            $row['event_date']
                        );
                }

                ?>



                <div class="event-card">


                    <!-- EVENT TOP -->

                    <div class="event-top">


                        <div>


                            <div class="event-title">

                                <?= e(
                                    $row['event_name']
                                ); ?>

                            </div>



                            <div class="organizer">

                                <i
                                    class="bi bi-person-circle"
                                ></i>

                                Created by:

                                <strong>

                                    <?= e(
                                        $row['organizer_name']
                                        ??
                                        'Unknown Organizer'
                                    ); ?>

                                </strong>


                                <?php if (
                                    (int)
                                    $row['organizer_id']
                                    === $user_id
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            bg-primary
                                            ms-2
                                        "
                                    >

                                        You

                                    </span>

                                <?php endif; ?>


                            </div>


                        </div>



                        <span
                            class="
                                badge
                                <?= e(
                                    $statusClass
                                ); ?>
                            "
                        >

                            <?= e(
                                $status
                                ?: 'Unknown'
                            ); ?>

                        </span>


                    </div>



                    <div class="mt-3">

                        <span class="category-badge">

                            <?= e(
                                $row['category']
                                ?: 'Uncategorized'
                            ); ?>

                        </span>

                    </div>



                    <div class="event-info">



                        <span>

                            <i
                                class="bi bi-calendar"
                            ></i>


                            <?php if (
                                $eventTimestamp
                                !== false
                            ): ?>

                                <?= date(
                                    "F j, Y",
                                    $eventTimestamp
                                ); ?>

                            <?php else: ?>

                                No date

                            <?php endif; ?>


                        </span>


                        <span>

                            <i
                                class="bi bi-clock"
                            ></i>


                            <?php if (
                                $eventTimestamp
                                !== false
                            ): ?>

                                <?= date(
                                    "g:i A",
                                    $eventTimestamp
                                ); ?>

                            <?php else: ?>

                                No time

                            <?php endif; ?>


                        </span>


                        <span>

                            <i
                                class="bi bi-geo-alt"
                            ></i>

                            <?= e(
                                $row['location']
                                ?: 'No location'
                            ); ?>

                        </span>



                        <?php if (
                            isset(
                                $row['capacity']
                            ) &&
                            $row['capacity'] !== ''
                        ): ?>

                            <span>

                                <i
                                    class="bi bi-people"
                                ></i>

                                Capacity:

                                <?= e(
                                    $row['capacity']
                                ); ?>

                            </span>

                        <?php endif; ?>


                    </div>
 

                    <?php if (
                        !empty(
                            $row['description']
                        )
                    ): ?>

                        <div class="description">

                            <?= nl2br(
                                e(
                                    $row['description']
                                )
                            ); ?>

                        </div>

                    <?php endif; ?>

 
                    <div class="action-area">

 

                        <?php if (
                            (int)
                            $row['organizer_id']
                            !== $user_id
                        ): ?>


                            <a
                                href="../users/view_event.php?id=<?= (int) $row['event_id']; ?>"
                                class="
                                    btn
                                    btn-outline-primary
                                    btn-sm
                                "
                            >

                                <i
                                    class="bi bi-eye"
                                ></i>

                                View Details

                            </a>


                        <?php else: ?>

 


                            <?php if (
                                $status ===
                                'Upcoming'
                            ): ?>

 
                                <a
                                    href="edit.php?id=<?= (int) $row['event_id']; ?>"
                                    class="
                                        btn
                                        btn-outline-primary
                                        btn-sm
                                    "
                                >

                                    <i
                                        class="bi bi-pencil"
                                    ></i>

                                    Edit

                                </a>

 
                                <a
                                    href="delete.php?id=<?= (int) $row['event_id']; ?>"
                                    class="
                                        btn
                                        btn-outline-danger
                                        btn-sm
                                    "
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this event?'
                                        );
                                    "
                                >

                                    <i
                                        class="bi bi-trash"
                                    ></i>

                                    Delete

                                </a>


                            <?php elseif (
                                $status ===
                                'Ongoing'
                            ): ?>

 

                                <a
                                    href="edit.php?id=<?= (int) $row['event_id']; ?>"
                                    class="
                                        btn
                                        btn-outline-primary
                                        btn-sm
                                    "
                                >

                                    <i
                                        class="bi bi-pencil"
                                    ></i>

                                    Edit

                                </a>


                            <?php elseif (
                                $status ===
                                'Ended'
                            ): ?>

 

                                <a
                                    href="archive.php?id=<?= (int) $row['event_id']; ?>"
                                    class="
                                        btn
                                        btn-outline-secondary
                                        btn-sm
                                    "
                                    onclick="
                                        return confirm(
                                            'Archive this ended event?'
                                        );
                                    "
                                >

                                    <i
                                        class="bi bi-archive"
                                    ></i>

                                    Archive

                                </a>


                            <?php endif; ?>

 

                            <a
                                href="../users/view_event.php?id=<?= (int) $row['event_id']; ?>"
                                class="
                                    btn
                                    btn-outline-secondary
                                    btn-sm
                                "
                            >

                                <i
                                    class="bi bi-eye"
                                ></i>

                                View

                            </a>


                        <?php endif; ?>


                    </div>


                </div>


            <?php endwhile; ?>


        <?php endif; ?>


    </div>

</main>

 

<script src="../assets/js/organizer/all_events.js"></script>


</body>

</html>
