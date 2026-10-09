<?php

session_start();

require_once __DIR__ . '/../config/db.php';




if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: ../auth/login.php");
    exit();
}




$currentPage = basename($_SERVER['PHP_SELF']);




function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


$totalEvents = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM events"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEvents = (int)$row['total'];
}



$totalAudience = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT ep.user_id) AS total
     FROM event_participants ep
     INNER JOIN users u
        ON ep.user_id = u.user_id
     WHERE u.role = 'Audience'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalAudience = (int)$row['total'];
}



$totalOrganizers = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'Organizer'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalOrganizers = (int)$row['total'];
}




$totalJoins = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalJoins = (int)$row['total'];
}




$upcomingEvents = 0;
$ongoingEvents = 0;
$endedEvents = 0;




$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE status = 'Upcoming'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $upcomingEvents = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE status = 'Ongoing'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $ongoingEvents = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE status = 'Ended'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $endedEvents = (int)$row['total'];
}




$eventReports = [];

$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.category,
        e.event_date,
        e.location,
        e.capacity,
        e.status,
        e.organizer_id,

        COALESCE(
            COUNT(ep.participation_id),
            0
        ) AS participant_count,

        u.fullname AS organizer_name

    FROM events e

    LEFT JOIN event_participants ep
        ON e.event_id = ep.event_id

    LEFT JOIN users u
        ON e.organizer_id = u.user_id

    GROUP BY
        e.event_id,
        e.event_name,
        e.category,
        e.event_date,
        e.location,
        e.capacity,
        e.status,
        e.organizer_id,
        u.fullname

    ORDER BY e.event_date DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $eventReports[] = $row;
    }
}



$categoryReports = [];

$sql = "
    SELECT
        category,
        COUNT(*) AS total

    FROM events

    WHERE category IS NOT NULL
      AND TRIM(category) <> ''

    GROUP BY category

    ORDER BY total DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $categoryReports[] = $row;
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

<title>Reports & Analytics | Event System</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>

  

    * {
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        margin: 0;
        padding: 0;
        background: #f5f7fb;
        color: #1f2937;
        overflow-x: hidden;
    }



    .top-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 64px;
        z-index: 1100;
        background: #2563eb;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    }

    .navbar-inner {
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 22px;
    }

    .navbar-brand-custom {
        color: #ffffff;
        text-decoration: none;
        font-size: 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .navbar-brand-custom i {
        font-size: 20px;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }




    .sidebar-toggle {
        display: none;
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 9px;
        background: rgba(255,255,255,.15);
        color: #ffffff;
        font-size: 22px;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .2s ease;
    }

    .sidebar-toggle:hover {
        background: rgba(255,255,255,.25);
    }


    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;

        width: 250px;

        background: #111827;
        color: #ffffff;

        z-index: 1200;

        overflow-y: auto;

        transform: translateX(0);

        transition: transform .25s ease;

        box-shadow:
            3px 0 15px rgba(0, 0, 0, .08);
    }


    .admin-sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .admin-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .admin-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,.15);
        border-radius: 20px;
    }


 

    .sidebar-close {
        display: none;

        position: absolute;

        top: 16px;
        right: 15px;

        width: 34px;
        height: 34px;

        border: 0;
        border-radius: 8px;

        background: rgba(255,255,255,.08);
        color: #ffffff;

        align-items: center;
        justify-content: center;

        font-size: 18px;

        cursor: pointer;
    }

    .sidebar-close:hover {
        background: rgba(255,255,255,.16);
    }


    .sidebar-brand {
        padding: 24px 20px;

        border-bottom:
            1px solid
            rgba(255,255,255,.08);
    }

    .sidebar-brand-title {
        display: flex;
        align-items: center;
        gap: 10px;

        font-size: 18px;
        font-weight: 700;

        color: #ffffff;
    }

    .sidebar-brand-title i {
        color: #60a5fa;
        font-size: 21px;
    }

    .sidebar-brand-subtitle {
        margin-top: 5px;

        font-size: 12px;
        color: #9ca3af;
    }


 

    .admin-profile {
        padding: 20px;

        border-bottom:
            1px solid
            rgba(255,255,255,.08);

        text-align: center;
    }

    .admin-avatar {
        width: 62px;
        height: 62px;

        border-radius: 50%;

        background:
            linear-gradient(
                135deg,
                #2563eb,
                #1d4ed8
            );

        display: flex;
        align-items: center;
        justify-content: center;

        margin: 0 auto 10px;

        font-size: 27px;
        color: #ffffff;

        box-shadow:
            0 5px 15px
            rgba(37,99,235,.25);
    }

    .admin-name {
        font-size: 14px;
        font-weight: 700;
        color: #ffffff;
    }

    .admin-role {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 3px;
    }



    .sidebar-menu {
        padding: 18px 12px 25px;
    }

    .sidebar-label {
        font-size: 10px;
        font-weight: 700;

        color: #6b7280;

        text-transform: uppercase;

        letter-spacing: .08em;

        padding:
            10px
            10px
            7px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;

        gap: 12px;

        width: 100%;

        padding:
            11px
            13px;

        margin-bottom: 4px;

        border-radius: 8px;

        color: #d1d5db;

        text-decoration: none;

        font-size: 14px;

        transition:
            background .2s ease,
            color .2s ease;
    }

    .sidebar-link i {
        width: 20px;
        text-align: center;
        font-size: 17px;
    }

    .sidebar-link:hover {
        background: #1f2937;
        color: #ffffff;
    }

    .sidebar-link.active {
        background: #2563eb;
        color: #ffffff;

        box-shadow:
            0 4px 12px
            rgba(37,99,235,.25);
    }

    .sidebar-divider {
        height: 1px;

        background:
            rgba(255,255,255,.08);

        margin:
            16px
            5px;
    }



    .sidebar-link.logout {
        color: #fca5a5;
    }

    .sidebar-link.logout:hover {
        background: #3f1d1d;
        color: #f87171;
    }




    .sidebar-overlay {
        display: none;

        position: fixed;

        inset: 0;

        background: rgba(0,0,0,.45);

        z-index: 1150;
    }

    .sidebar-overlay.show {
        display: block;
    }



    .main-wrapper {
        margin-left: 250px;

        padding-top: 64px;

        min-height: 100vh;

        width: calc(100% - 250px);
    }

    .main-content {
        width: 100%;
        max-width: 1500px;

        margin: 0 auto;

        padding: 30px;
    }


    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;

        margin-bottom: 25px;
    }

    .page-title {
        margin: 0;

        font-size: 28px;
        font-weight: 700;

        color: #111827;
    }

    .page-subtitle {
        margin: 6px 0 0;

        font-size: 14px;
        color: #6b7280;
    }



    .summary-grid {
        display: grid;

        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 20px;

        margin-bottom: 25px;
    }

    .report-card {
        min-height: 145px;

        border: 0;

        border-radius: 14px;

        background: #ffffff;

        box-shadow:
            0 4px 18px
            rgba(15,23,42,.06);

        transition:
            transform .2s ease,
            box-shadow .2s ease;
    }

    .report-card:hover {
        transform: translateY(-2px);

        box-shadow:
            0 8px 24px
            rgba(15,23,42,.09);
    }

    .report-card .card-body {
        padding: 22px;
    }

    .report-card h6 {
        color: #4b5563;
        font-size: 14px;
        font-weight: 600;
    }

    .report-card .display-6 {
        font-weight: 700;
        margin: 8px 0;
    }


    .chart-grid {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 20px;

        margin-bottom: 25px;
    }

    .content-card {
        border: 0;

        border-radius: 14px;

        background: #ffffff;

        box-shadow:
            0 4px 18px
            rgba(15,23,42,.06);
    }

    .content-card .card-body {
        padding: 22px;
    }

    .chart-container {
        position: relative;
        height: 320px;
    }



    .status-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 15px;

        margin-top: 18px;
    }

    .status-box {
        border: 1px solid #e5e7eb;

        border-radius: 10px;

        padding: 16px;

        background: #ffffff;
    }



    .table-card {
        overflow: hidden;

        border: 0;

        border-radius: 14px;

        background: #ffffff;

        box-shadow:
            0 4px 18px
            rgba(15,23,42,.06);

        margin-bottom: 25px;
    }

    .table-card-body {
        padding: 22px;
    }

    .table td,
    .table th {
        vertical-align: middle;
        white-space: nowrap;
    }

    .event-name {
        font-weight: 600;
        color: #111827;
    }

    .small-muted {
        font-size: 13px;
        color: #6c757d;
    }

    .table-responsive {
        border-radius: 10px;
    }


    .category-table {
        margin-bottom: 0;
    }



    @media (max-width: 991.98px) {

        .sidebar-toggle {
            display: flex;
        }

        .admin-sidebar {
            transform: translateX(-100%);
        }

        .admin-sidebar.open {
            transform: translateX(0);
        }

        .sidebar-close {
            display: flex;
        }

        .main-wrapper {
            margin-left: 0;

            width: 100%;
        }

        .main-content {
            padding: 25px 20px;
        }

        .summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .chart-grid {
            grid-template-columns:
                1fr;
        }

        .status-grid {
            grid-template-columns:
                1fr;
        }

    }


    @media (max-width: 575.98px) {

        .top-navbar {
            height: 60px;
        }

        .navbar-inner {
            height: 60px;
            padding: 0 14px;
        }

        .navbar-brand-custom {
            font-size: 15px;
        }

        .navbar-right .logout-btn {
            display: none;
        }

        .sidebar-toggle {
            width: 40px;
            height: 40px;
        }

        .main-wrapper {
            padding-top: 60px;
        }

        .main-content {
            padding: 20px 14px;
        }

        .page-header {
            align-items: flex-start;
        }

        .page-title {
            font-size: 23px;
        }

        .page-subtitle {
            font-size: 13px;
        }

        .summary-grid {
            grid-template-columns:
                1fr;

            gap: 14px;
        }

        .report-card {
            min-height: 130px;
        }

        .chart-container {
            height: 280px;
        }

        .content-card .card-body,
        .table-card-body {
            padding: 17px;
        }

    }

    @media (max-width: 380px) {

        .navbar-brand-custom span {
            display: none;
        }

        .main-content {
            padding-left: 10px;
            padding-right: 10px;
        }

    }

</style>
```

</head>

<body>



<nav class="top-navbar">

<div class="navbar-inner">

    <div class="d-flex align-items-center gap-2">

        <button
            type="button"
            class="sidebar-toggle"
            id="sidebarToggle"
            aria-label="Open navigation"
            aria-controls="adminSidebar"
            aria-expanded="false"
        >
            <i class="bi bi-list"></i>
        </button>


    

        <a
            href="admin.php"
            class="navbar-brand-custom"
        >

            <i class="bi bi-calendar2-event"></i>

            <span>
                 Event System
            </span>

        </a>

    </div>


    <div class="navbar-right">

        <a
            href="../auth/logout.php"
            class="btn btn-outline-light btn-sm logout-btn"
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >
            <i class="bi bi-box-arrow-right me-1"></i>
            Logout
        </a>

    </div>

</div>

</nav>



<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<aside
    class="admin-sidebar"
    id="adminSidebar"
>

<button
    type="button"
    class="sidebar-close"
    id="sidebarClose"
    aria-label="Close navigation"
>
    <i class="bi bi-x-lg"></i>
</button>



<div class="sidebar-brand">

    <div class="sidebar-brand-title">

        <i class="bi bi-calendar2-event"></i>

        <span>
            Event System
        </span>

    </div>

    <div class="sidebar-brand-subtitle">
        Administration Portal
    </div>

</div>



<div class="admin-profile">

    <div class="admin-avatar">

        <i class="bi bi-shield-lock-fill"></i>

    </div>

    <div class="admin-name">
        Administrator
    </div>

    <div class="admin-role">
        Admin Panel
    </div>

</div>



<nav class="sidebar-menu">


    <div class="sidebar-label">
        Main
    </div>



    <a
        href="admin.php"
        class="sidebar-link <?= $currentPage === 'admin.php' ? 'active' : ''; ?>"
    >

        <i class="bi bi-grid-1x2-fill"></i>

        <span>
            Dashboard
        </span>

    </a>



    <a
        href="event_audience.php"
        class="sidebar-link <?= $currentPage === 'event_audience.php' ? 'active' : ''; ?>"
    >

        <i class="bi bi-people-fill"></i>

        <span>
            Event Audience
        </span>

    </a>


   

    <a
        href="event.php"
        class="sidebar-link <?= $currentPage === 'event.php' ? 'active' : ''; ?>"
    >

        <i class="bi bi-calendar-event-fill"></i>

        <span>
            Events
        </span>

    </a>

    <a
        href="reports.php"
        class="sidebar-link <?= $currentPage === 'reports.php' ? 'active' : ''; ?>"
    >

        <i class="bi bi-bar-chart-fill"></i>

        <span>
            Reports
        </span>

    </a>




    <div class="sidebar-divider"></div>


    <div class="sidebar-label">
        System
    </div>




    <a
        href="settings.php"
        class="sidebar-link <?= $currentPage === 'settings.php' ? 'active' : ''; ?>"
    >

        <i class="bi bi-gear-fill"></i>

        <span>
            System Settings
        </span>

    </a>


    <div class="sidebar-divider"></div>



    <a
        href="../auth/logout.php"
        class="sidebar-link logout"
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


</nav>
</aside>


<div class="main-wrapper">



<main class="main-content">


    <div class="page-header">

        <div>

            <h1 class="page-title">
                Reports & Analytics
            </h1>

            <p class="page-subtitle">
                Monitor event activity, participation, and system statistics.
            </p>

        </div>

    </div>



    <div class="summary-grid">

        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Events
                </h6>

                <p class="display-6 text-primary">
                    <?= $totalEvents; ?>
                </p>

                <small class="text-muted">
                    All events
                </small>

            </div>

        </div>


        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Audience
                </h6>

                <p class="display-6 text-success">
                    <?= $totalAudience; ?>
                </p>

                <small class="text-muted">
                    Unique users who joined
                </small>

            </div>

        </div>



        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Organizers
                </h6>

                <p class="display-6 text-warning">
                    <?= $totalOrganizers; ?>
                </p>

                <small class="text-muted">
                    Registered organizers
                </small>

            </div>

        </div>


   

        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Event Joins
                </h6>

                <p class="display-6 text-danger">
                    <?= $totalJoins; ?>
                </p>

                <small class="text-muted">
                    Participation records
                </small>

            </div>

        </div>


    </div>


  
    <div class="chart-grid">




        <div class="content-card">

            <div class="card-body">

                <h5 class="card-title mb-1">
                    Event Status Breakdown
                </h5>

                <p class="text-muted mb-3">
                    Current number of events by status.
                </p>

                <div class="chart-container">

                    <canvas id="eventStatusChart"></canvas>

                </div>

            </div>

        </div>

        <div class="content-card">

            <div class="card-body">

                <h5 class="card-title mb-1">
                    Events by Category
                </h5>

                <p class="text-muted mb-3">
                    Number of events under each category.
                </p>

                <div class="chart-container">

                    <canvas id="categoryChart"></canvas>

                </div>

            </div>

        </div>


    </div>


    <div class="content-card mb-4">

        <div class="card-body">

            <h5 class="card-title mb-1">
                Event Status Summary
            </h5>

            <p class="text-muted mb-0">
                Overview of current event statuses.
            </p>


            <div class="status-grid">



                <div class="status-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Upcoming Events
                        </span>

                        <span class="badge bg-primary">
                            <?= $upcomingEvents; ?>
                        </span>

                    </div>

                </div>



                <div class="status-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Ongoing Events
                        </span>

                        <span class="badge bg-success">
                            <?= $ongoingEvents; ?>
                        </span>

                    </div>

                </div>



                <div class="status-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Ended Events
                        </span>

                        <span class="badge bg-secondary">
                            <?= $endedEvents; ?>
                        </span>

                    </div>

                </div>


            </div>

        </div>

    </div>


    <div class="table-card">

        <div class="table-card-body">


            <div
                class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"
            >

                <div>

                    <h5 class="card-title mb-1">
                        Event Participation Report
                    </h5>

                    <p class="text-muted mb-0">
                        Audience participation for every event.
                    </p>

                </div>

                <span class="badge bg-primary">
                    <?= count($eventReports); ?> Event(s)
                </span>

            </div>


            <div class="table-responsive">

                <table
                    id="eventReportTable"
                    class="table table-striped table-hover"
                >

                    <thead class="table-primary">

                        <tr>

                            <th>
                                Event
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Schedule
                            </th>

                            <th>
                                Location
                            </th>

                            <th>
                                Organizer
                            </th>

                            <th>
                                Capacity
                            </th>

                            <th>
                                Participants
                            </th>

                            <th>
                                Available
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (!empty($eventReports)): ?>


                            <?php foreach ($eventReports as $row): ?>


                                <?php

                                $capacity = (int)($row['capacity'] ?? 0);

                                $participants =
                                    (int)($row['participant_count'] ?? 0);

                                $available = max(
                                    0,
                                    $capacity - $participants
                                );

                                $status = $row['status'] ?? '';

                                ?>


                                <tr>


                                    <!-- EVENT -->

                                    <td>

                                        <div class="event-name">

                                            <?= e($row['event_name']); ?>

                                        </div>

                                        <div class="small-muted">

                                            Event ID:
                                            <?= (int)$row['event_id']; ?>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= e($row['category']); ?>

                                        </span>

                                    </td>



                                    <td>

                                        <?php

                                        if (!empty($row['event_date'])) {

                                            echo e(
                                                date(
                                                    'M d, Y h:i A',
                                                    strtotime($row['event_date'])
                                                )
                                            );

                                        } else {

                                            echo 'N/A';

                                        }

                                        ?>

                                    </td>


                          
                                    <td>

                                        <?= e($row['location']); ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            $row['organizer_name']
                                                ?: 'N/A'
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= $capacity; ?>

                                    </td>


                                    <td>

                                        <span class="badge bg-success">

                                            <?= $participants; ?>

                                        </span>

                                    </td>


                           

                                    <td>

                                        <?php if ($available <= 0): ?>

                                            <span class="badge bg-danger">
                                                Full
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">

                                                <?= $available; ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <td>

                                        <?php if ($status === 'Upcoming'): ?>

                                            <span class="badge bg-primary">
                                                Upcoming
                                            </span>

                                        <?php elseif ($status === 'Ongoing'): ?>

                                            <span class="badge bg-success">
                                                Ongoing
                                            </span>

                                        <?php elseif ($status === 'Ended'): ?>

                                            <span class="badge bg-secondary">
                                                Ended
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-dark">

                                                <?= e(
                                                    $status ?: 'Unknown'
                                                ); ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="9" class="text-center py-4">

                                    <i class="bi bi-calendar-x fs-3 d-block mb-2 text-muted"></i>

                                    <span class="text-muted">
                                        No events found.
                                    </span>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>




    <div class="table-card">

        <div class="table-card-body">

            <h5 class="card-title mb-1">
                Category Summary
            </h5>

            <p class="text-muted mb-3">
                Number of events under each category.
            </p>


            <div class="table-responsive">

                <table class="table table-striped table-hover category-table">

                    <thead class="table-primary">

                        <tr>

                            <th>
                                Category
                            </th>

                            <th>
                                Number of Events
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (!empty($categoryReports)): ?>


                            <?php foreach ($categoryReports as $row): ?>

                                <tr>

                                    <td>

                                        <?= e($row['category']); ?>

                                    </td>

                                    <td>

                                        <span class="badge bg-primary">

                                            <?= (int)$row['total']; ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="2" class="text-center py-4">

                                    <span class="text-muted">
                                        No category data available.
                                    </span>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>


</main>


</div>


<script>

    const upcomingEvents =
        <?= $upcomingEvents; ?>;

    const ongoingEvents =
        <?= $ongoingEvents; ?>;

    const endedEvents =
        <?= $endedEvents; ?>;


    const categoryLabels = [

        <?php

        foreach ($categoryReports as $row) {

            echo json_encode(
                $row['category']
            ) . ',';

        }

        ?>

    ];


    const categoryData = [

        <?php

        foreach ($categoryReports as $row) {

            echo (int)$row['total'] . ',';

        }

        ?>

    ];

</script>



<script src="../assets/js/admin/reports.js"></script>


<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            const sidebar =
                document.getElementById('adminSidebar');

            const toggle =
                document.getElementById('sidebarToggle');

            const closeBtn =
                document.getElementById('sidebarClose');

            const overlay =
                document.getElementById('sidebarOverlay');

            const sidebarLinks =
                document.querySelectorAll(
                    '.admin-sidebar .sidebar-link'
                );



            function isMobile() {

                return window.innerWidth <= 991.98;

            }


        
            function openSidebar() {

                if (!isMobile()) {
                    return;
                }

                sidebar.classList.add('open');

                overlay.classList.add('show');

                toggle.setAttribute(
                    'aria-expanded',
                    'true'
                );

                document.body.style.overflow = 'hidden';

            }


      

            function closeSidebar() {

                sidebar.classList.remove('open');

                overlay.classList.remove('show');

                toggle.setAttribute(
                    'aria-expanded',
                    'false'
                );

                document.body.style.overflow = '';

            }


       
            toggle.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    if (
                        sidebar.classList.contains('open')
                    ) {

                        closeSidebar();

                    } else {

                        openSidebar();

                    }

                }
            );


        

            closeBtn.addEventListener(
                'click',
                function () {

                    closeSidebar();

                }
            );



            overlay.addEventListener(
                'click',
                function () {

                    closeSidebar();

                }
            );


      

            sidebarLinks.forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        function () {

                            if (isMobile()) {

                                closeSidebar();

                            }

                        }
                    );

                }
            );


         

            document.addEventListener(
                'click',
                function (event) {

                    if (!isMobile()) {
                        return;
                    }

                    if (
                        !sidebar.classList.contains('open')
                    ) {
                        return;
                    }

                    const clickedInsideSidebar =
                        sidebar.contains(event.target);

                    const clickedToggle =
                        toggle.contains(event.target);

                    if (
                        !clickedInsideSidebar &&
                        !clickedToggle
                    ) {

                        closeSidebar();

                    }

                }
            );


            document.addEventListener(
                'keydown',
                function (event) {

                    if (
                        event.key === 'Escape'
                    ) {

                        closeSidebar();

                    }

                }
            );


           
            window.addEventListener(
                'resize',
                function () {

                    if (!isMobile()) {

                        closeSidebar();

                    }

                }
            );

        }
    );

</script>

</body>

</html>
