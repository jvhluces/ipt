<?php
session_start();

/* =========================================================
   DATABASE CONNECTION
========================================================= */
require_once __DIR__ . '/../config/db.php';


/* =========================================================
   ADMIN AUTHENTICATION
========================================================= */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =========================================================
   HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DASHBOARD COUNTS
========================================================= */

/* ---------------------------------------------------------
   TOTAL JOIN RECORDS
--------------------------------------------------------- */
$totalJoined = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalJoined = (int)($row['total'] ?? 0);
}


/* ---------------------------------------------------------
   UNIQUE AUDIENCE
--------------------------------------------------------- */
$totalAudience = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalAudience = (int)($row['total'] ?? 0);
}


/* ---------------------------------------------------------
   EVENTS WITH AUDIENCE
--------------------------------------------------------- */
$eventsWithAudience = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT event_id) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $eventsWithAudience = (int)($row['total'] ?? 0);
}


/* =========================================================
   PARTICIPANT LIST
========================================================= */

$participants = [];

$sql = "
    SELECT
        ep.participation_id,
        ep.event_id,
        ep.user_id,
        ep.joined_at,

        e.event_name,
        e.event_date,
        e.location,
        e.category,
        e.status AS event_status,

        u.fullname,
        u.username,
        u.email,
        u.contact,
        u.address

    FROM event_participants ep

    INNER JOIN events e
        ON ep.event_id = e.event_id

    INNER JOIN users u
        ON ep.user_id = u.user_id

    WHERE u.role = 'Audience'

    ORDER BY ep.joined_at DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $participants[] = $row;

    }

}


/* =========================================================
   PAGE
========================================================= */
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Event Audience | Event System</title>

    <link
        rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =====================================================
         DATATABLES
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
    >


    <style>

        /* =====================================================
           GLOBAL
        ===================================================== */

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #f5f7fb;

            color: #172033;

            overflow: hidden;
        }


        /* =====================================================
           SIDEBAR
           SAME STRUCTURE AS ADMIN DASHBOARD
        ===================================================== */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;
            bottom: 0;

            width: 272px;

            background: #111827;

            color: #ffffff;

            z-index: 1000;

            display: flex;

            flex-direction: column;

            overflow-y: auto;

            overflow-x: hidden;

            border-right: 1px solid #1f2937;
        }


        /* =====================================================
           SIDEBAR BRAND
        ===================================================== */

        .sidebar-brand {

            height: 78px;

            padding: 0 25px;

            display: flex;

            align-items: center;

            border-bottom: 1px solid #253044;

            flex-shrink: 0;
        }

        .brand-icon {

            width: 25px;

            height: 25px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #3b82f6;

            font-size: 21px;

            margin-right: 8px;
        }

        .brand-title {

            color: #f8fafc;

            font-size: 17px;

            font-weight: 700;

            line-height: 1.1;

            white-space: nowrap;
        }

        .brand-subtitle {

            display: block;

            margin-top: 5px;

            color: #9ca3af;

            font-size: 11px;

            font-weight: 400;

        }


        /* =====================================================
           ADMIN PROFILE
        ===================================================== */

        .admin-profile {

            padding: 22px 20px 23px;

            text-align: center;

            border-bottom: 1px solid #253044;

            flex-shrink: 0;
        }

        .admin-avatar {

            width: 66px;

            height: 66px;

            margin: 0 auto 13px;

            border-radius: 50%;

            background: #2563eb;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ffffff;

            font-size: 27px;

            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.35);
        }

        .admin-name {

            color: #ffffff;

            font-size: 15px;

            font-weight: 700;

            margin-bottom: 3px;
        }

        .admin-role {

            color: #9ca3af;

            font-size: 12px;
        }


        /* =====================================================
           SIDEBAR MENU
        ===================================================== */

        .sidebar-menu {

            flex: 1;

            padding: 22px 17px 12px;
        }

        .menu-section {

            color: #6b7280;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 0.08em;

            text-transform: uppercase;

            margin: 0 0 10px 12px;
        }


        /* =====================================================
           SIDEBAR LINKS
        ===================================================== */

        .sidebar-link {

            width: 100%;

            min-height: 51px;

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 0 15px;

            margin-bottom: 3px;

            border-radius: 9px;

            color: #d1d5db;

            text-decoration: none;

            font-size: 14px;

            font-weight: 500;

            transition:
                background 0.2s ease,
                color 0.2s ease;
        }

        .sidebar-link i {

            width: 20px;

            font-size: 18px;

            text-align: center;

            flex-shrink: 0;
        }

        .sidebar-link:hover {

            background: #1d2636;

            color: #ffffff;
        }

        .sidebar-link.active {

            background: #2563eb;

            color: #ffffff;

            box-shadow:
                0 3px 8px rgba(37, 99, 235, 0.25);
        }


        /* =====================================================
           SYSTEM SECTION
        ===================================================== */

        .system-section {

            margin-top: 14px;

            padding-top: 22px;

            border-top: 1px solid #253044;
        }


        /* =====================================================
           SIDEBAR LOGOUT
           SAME POSITION AS SCREENSHOT
        ===================================================== */

        .sidebar-logout {

            padding: 12px 17px 20px;

            border-top: 1px solid #253044;

            flex-shrink: 0;
        }

        .logout-link {

            width: 100%;

            min-height: 50px;

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 0 15px;

            border-radius: 9px;

            color: #f87171;

            text-decoration: none;

            font-size: 14px;

            font-weight: 500;

            transition:
                background 0.2s ease,
                color 0.2s ease;
        }

        .logout-link i {

            width: 20px;

            font-size: 18px;

            text-align: center;
        }

        .logout-link:hover {

            background: rgba(239, 68, 68, 0.10);

            color: #fca5a5;
        }


        /* =====================================================
           MAIN AREA
        ===================================================== */

        .main-area {

            margin-left: 272px;

            height: 100vh;

            min-width: 0;

            display: flex;

            flex-direction: column;
        }


        /* =====================================================
           TOPBAR
           SAME AS SCREENSHOT
        ===================================================== */

        .topbar {

            height: 74px;

            background: #ffffff;

            border-bottom: 1px solid #e5e7eb;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 28px;

            flex-shrink: 0;
        }

        .topbar-title {

            margin: 0;

            color: #172033;

            font-size: 21px;

            font-weight: 700;

            line-height: 1.2;
        }

        .topbar-subtitle {

            color: #94a3b8;

            font-size: 12px;

            margin-top: 5px;
        }

        .admin-indicator {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #64748b;

            font-size: 14px;
        }

        .admin-indicator i {

            color: #2563eb;

            font-size: 22px;
        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content-area {

            flex: 1;

            overflow-y: auto;

            padding: 28px;

            min-height: 0;
        }


        /* =====================================================
           CONTENT HEADER
        ===================================================== */

        .content-heading {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 24px;
        }

        .content-heading h1 {

            margin: 0;

            color: #172033;

            font-size: 27px;

            font-weight: 700;
        }

        .content-heading p {

            margin: 6px 0 0;

            color: #7b8798;

            font-size: 13px;
        }


        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stat-card {

            height: 100%;

            background: #ffffff;

            border: 1px solid #e1e6ed;

            border-radius: 14px;

            box-shadow:
                0 2px 8px rgba(15, 23, 42, 0.035);
        }

        .stat-card-body {

            min-height: 116px;

            padding: 21px;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .stat-label {

            color: #64748b;

            font-size: 12px;

            margin-bottom: 9px;
        }

        .stat-value {

            color: #172033;

            font-size: 28px;

            font-weight: 700;

            line-height: 1;
        }

        .stat-icon {

            width: 50px;

            height: 50px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;
        }

        .stat-icon.blue {

            background: #eff6ff;

            color: #2563eb;
        }

        .stat-icon.green {

            background: #ecfdf5;

            color: #059669;
        }

        .stat-icon.orange {

            background: #fff7ed;

            color: #ea580c;
        }


        /* =====================================================
           TABLE CARD
        ===================================================== */

        .table-card {

            margin-top: 28px;

            background: #ffffff;

            border: 1px solid #e1e6ed;

            border-radius: 14px;

            box-shadow:
                0 2px 8px rgba(15, 23, 42, 0.035);

            overflow: hidden;
        }

        .table-card-header {

            min-height: 84px;

            padding: 20px 22px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {

            margin: 0;

            color: #172033;

            font-size: 17px;

            font-weight: 700;
        }

        .table-title i {

            color: #059669;

            margin-right: 8px;
        }

        .table-description {

            margin: 5px 0 0;

            color: #94a3b8;

            font-size: 12px;
        }

        .join-count {

            padding: 6px 10px;

            background: #ecfdf5;

            color: #047857;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 700;

            white-space: nowrap;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-wrapper {

            padding: 0 20px 20px;

            overflow-x: auto;
        }

        #audienceTable {

            width: 100% !important;

            margin-top: 0 !important;
        }

        #audienceTable thead th {

            background: #f8fafc;

            color: #334155;

            border-bottom: 1px solid #dce3eb;

            font-size: 12px;

            font-weight: 700;

            white-space: nowrap;

            padding: 13px 11px;
        }

        #audienceTable tbody td {

            color: #475569;

            font-size: 12px;

            padding: 13px 11px;

            vertical-align: middle;

            border-bottom: 1px solid #eef2f7;
        }

        #audienceTable tbody tr:hover {

            background: #f8fafc;
        }

        .event-name {

            color: #172033;

            font-size: 13px;

            font-weight: 700;

            margin-bottom: 3px;
        }

        .small-muted {

            color: #94a3b8;

            font-size: 11px;
        }


        /* =====================================================
           BADGES
        ===================================================== */

        .category-badge {

            display: inline-block;

            padding: 5px 8px;

            background: #f1f5f9;

            color: #475569;

            border-radius: 5px;

            font-size: 10px;

            font-weight: 600;

            white-space: nowrap;
        }

        .status-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 5px 8px;

            border-radius: 15px;

            font-size: 10px;

            font-weight: 700;

            white-space: nowrap;
        }

        .status-upcoming {

            background: #dcfce7;

            color: #15803d;
        }

        .status-ongoing {

            background: #fff7ed;

            color: #c2410c;
        }

        .status-ended {

            background: #f1f5f9;

            color: #64748b;
        }

        .status-other {

            background: #e5e7eb;

            color: #475569;
        }


        /* =====================================================
           ACTION BUTTON
        ===================================================== */

        .view-event-btn {

            width: 34px;

            height: 34px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            background: #eff6ff;

            color: #2563eb;

            border: 1px solid #dbeafe;

            border-radius: 7px;

            text-decoration: none;

            transition: all 0.2s ease;
        }

        .view-event-btn:hover {

            background: #2563eb;

            color: #ffffff;
        }


        /* =====================================================
           DATATABLES
        ===================================================== */

        .dataTables_wrapper {

            padding-top: 18px;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {

            margin-bottom: 15px;
        }

        .dataTables_wrapper label {

            color: #64748b;

            font-size: 12px;
        }

        .dataTables_wrapper select,
        .dataTables_wrapper input {

            border: 1px solid #d5dce5;

            border-radius: 7px;

            padding: 6px 9px;

            color: #475569;

            background: #ffffff;

            font-size: 12px;

            outline: none;
        }

        .dataTables_wrapper input:focus,
        .dataTables_wrapper select:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .dataTables_wrapper .dataTables_info {

            color: #94a3b8;

            font-size: 11px;

            padding-top: 15px;
        }

        .dataTables_wrapper .dataTables_paginate {

            padding-top: 10px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {

            font-size: 11px;

            border-radius: 6px !important;
        }

        .dataTables_wrapper
        .dataTables_paginate
        .paginate_button.current {

            background: #2563eb !important;

            border-color: #2563eb !important;

            color: #ffffff !important;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        .mobile-menu-btn {

            display: none;

            width: 38px;

            height: 38px;

            border: 1px solid #e2e8f0;

            background: #ffffff;

            color: #334155;

            border-radius: 8px;

            align-items: center;

            justify-content: center;

            font-size: 20px;
        }

        .sidebar-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background: rgba(15, 23, 42, 0.45);

            z-index: 999;
        }


        @media (max-width: 1000px) {

            .sidebar {

                transform: translateX(-100%);

                transition: transform 0.25s ease;
            }

            .sidebar.open {

                transform: translateX(0);
            }

            .sidebar-overlay.open {

                display: block;
            }

            .main-area {

                margin-left: 0;
            }

            .mobile-menu-btn {

                display: inline-flex;
            }

            .topbar {

                padding: 0 18px;
            }

            .content-area {

                padding: 20px;
            }
        }


        @media (max-width: 650px) {

            .admin-indicator span {

                display: none;
            }

            .content-heading {

                flex-direction: column;
            }

            .content-heading h1 {

                font-size: 23px;
            }

            .table-card-header {

                align-items: flex-start;

                flex-direction: column;
            }

        }

    </style>

</head>


<body>



<aside
    class="sidebar"
    id="sidebar"
>



    <div class="sidebar-brand">

        <div class="brand-icon">

            <i class="bi bi-pc-display"></i>

        </div>

        <div>

            <div class="brand-title">
                 Event System
            </div>

            <span class="brand-subtitle">
                Administration Portal
            </span>

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


        <div class="menu-section">
            Main
        </div>

        <a
            href="admin.php"
            class="sidebar-link"
        >

            <i class="bi bi-grid-1x2-fill"></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- EVENT AUDIENCE -->

        <a
            href="event_audience.php"
            class="sidebar-link active"
        >

            <i class="bi bi-people-fill"></i>

            <span>
                Event Audience
            </span>

        </a>



        <a
            href="event.php"
            class="sidebar-link"
        >

            <i class="bi bi-calendar-event-fill"></i>

            <span>
                Events
            </span>

        </a>


        <a
            href="reports.php"
            class="sidebar-link"
        >

            <i class="bi bi-bar-chart-fill"></i>

            <span>
                Reports
            </span>

        </a>






        <div class="system-section">

            <div class="menu-section">
                System
            </div>


          

            <a
                href="settings.php"
                class="sidebar-link"
            >

                <i class="bi bi-gear-fill"></i>

                <span>
                    System Settings
                </span>

            </a>

        </div>


    </nav>


    <!-- LOGOUT -->

    <div class="sidebar-logout">

        <a
            href="../auth/logout.php"
            class="logout-link"
            onclick="return confirmLogout();"
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </div>


</aside>


<!-- MOBILE OVERLAY -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="closeSidebar()"
></div>


<!-- =========================================================
     MAIN AREA
========================================================= -->

<div class="main-area">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">


        <div class="d-flex align-items-center gap-3">


            <!-- MOBILE MENU -->

            <button
                type="button"
                class="mobile-menu-btn"
                onclick="toggleSidebar()"
            >

                <i class="bi bi-list"></i>

            </button>


            <div>

                <h1 class="topbar-title">
                    Event Audience
                </h1>

                <div class="topbar-subtitle">
                    Event System Management
                </div>

            </div>

        </div>


        <!-- ADMIN INDICATOR -->

        <div class="admin-indicator">

            <i class="bi bi-shield-check"></i>

            <span>
                Administrator
            </span>

        </div>


    </header>


    <!-- =====================================================
         CONTENT AREA
    ====================================================== -->

    <main class="content-area">


        <!-- PAGE HEADER -->

        <div class="content-heading">

            <div>

                <h1>
                    Event Audience Overview
                </h1>

                <p>
                    Monitor audience participation and event attendance records.
                </p>

            </div>

        </div>


        <!-- =================================================
             STAT CARDS
        ================================================== -->

        <div class="row g-4">


            <!-- TOTAL JOINED -->

            <div class="col-xl-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-card-body">

                        <div>

                            <div class="stat-label">
                                Total Join Records
                            </div>

                            <div
                                class="stat-value"
                                style="color:#2563eb;"
                            >
                                <?= number_format($totalJoined); ?>
                            </div>

                        </div>

                        <div class="stat-icon blue">

                            <i class="bi bi-person-check-fill"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- UNIQUE AUDIENCE -->

            <div class="col-xl-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-card-body">

                        <div>

                            <div class="stat-label">
                                Registered Audience
                            </div>

                            <div
                                class="stat-value"
                                style="color:#059669;"
                            >
                                <?= number_format($totalAudience); ?>
                            </div>

                        </div>

                        <div class="stat-icon green">

                            <i class="bi bi-people-fill"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- EVENTS WITH AUDIENCE -->

            <div class="col-xl-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-card-body">

                        <div>

                            <div class="stat-label">
                                Events With Audience
                            </div>

                            <div
                                class="stat-value"
                                style="color:#ea580c;"
                            >
                                <?= number_format($eventsWithAudience); ?>
                            </div>

                        </div>

                        <div class="stat-icon orange">

                            <i class="bi bi-calendar2-check-fill"></i>

                        </div>

                    </div>

                </div>

            </div>


        </div>

        <div class="table-card">

            <div class="table-card-header">

                <div>

                    <h2 class="table-title">

                        <i class="bi bi-person-vcard-fill"></i>

                        Event Participants

                    </h2>

                    <p class="table-description">

                        All audience users who have joined an event.

                    </p>

                </div>


                <div class="join-count">

                    <?= number_format($totalJoined); ?>

                    Join Record(s)

                </div>

            </div>


            <!-- TABLE -->

            <div class="table-wrapper">

                <table
                    id="audienceTable"
                    class="table"
                >

                    <thead>

                        <tr>

                            <th>
                                Event
                            </th>

                            <th>
                                Schedule
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Event Status
                            </th>

                            <th>
                                Audience Name
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Contact
                            </th>

                            <th>
                                Address
                            </th>

                            <th>
                                Joined At
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($participants as $row): ?>

                        <tr>


                            <!-- EVENT -->

                            <td>

                                <div class="event-name">

                                    <?= e($row['event_name']); ?>

                                </div>

                                <div class="small-muted">

                                    <i class="bi bi-geo-alt me-1"></i>

                                    <?= e($row['location']); ?>

                                </div>

                            </td>


                            <!-- SCHEDULE -->

                            <td>

                                <?php if (!empty($row['event_date'])): ?>

                                    <?= e(
                                        date(
                                            'M d, Y h:i A',
                                            strtotime($row['event_date'])
                                        )
                                    ); ?>

                                <?php else: ?>

                                    N/A

                                <?php endif; ?>

                     
                            <td>

                                <span class="category-badge">

                                    <?= e($row['category']); ?>

                                </span>

                            </td>



                            <td>

                                <?php

                                $status = trim(
                                    (string)($row['event_status'] ?? '')
                                );

                                if ($status === 'Upcoming'):

                                ?>

                                    <span class="status-badge status-upcoming">

                                        <i class="bi bi-circle-fill"></i>

                                        Upcoming

                                    </span>

                                <?php

                                elseif ($status === 'Ongoing'):

                                ?>

                                    <span class="status-badge status-ongoing">

                                        <i class="bi bi-circle-fill"></i>

                                        Ongoing

                                    </span>

                                <?php

                                elseif ($status === 'Ended'):

                                ?>

                                    <span class="status-badge status-ended">

                                        <i class="bi bi-circle-fill"></i>

                                        Ended

                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-other">

                                        <?= e($status ?: 'N/A'); ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="event-name">

                                    <?= e($row['fullname']); ?>

                                </div>

                                <?php if (!empty($row['username'])): ?>

                                    <div class="small-muted">

                                        @<?= e($row['username']); ?>

                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= e($row['email']); ?>

                            </td>


                

                            <td>

                                <?= e($row['contact']); ?>

                            </td>



                            <td>

                                <?= e($row['address']); ?>

                            </td>


                        

                            <td>

                                <?php if (!empty($row['joined_at'])): ?>

                                    <?= e(
                                        date(
                                            'M d, Y h:i A',
                                            strtotime($row['joined_at'])
                                        )
                                    ); ?>

                                <?php else: ?>

                                    N/A

                                <?php endif; ?>

                            </td>


                            <td>

                                <a
                                    href="event.php"
                                    class="view-event-btn"
                                    title="View Events"
                                >

                                    <i class="bi bi-calendar-event"></i>

                                </a>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                    <?php if (empty($participants)): ?>

                        <tr>

                            <td>
                                No participants yet.
                            </td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                        </tr>

                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


        </div>


    </main>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
></script>

<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"
></script>


<script>

/* =========================================================
   DATATABLE
========================================================= */

$(document).ready(function () {

    $('#audienceTable').DataTable({

        pageLength: 5,

        lengthMenu: [
            [5, 10, 25, 50, 100],
            [5, 10, 25, 50, 100]
        ],

        order: [
            [8, 'desc']
        ],

        language: {

            search: "",

            searchPlaceholder:
                "Search audience...",

            lengthMenu:
                "Show _MENU_ entries",

            emptyTable:
                "No audience participation records found.",

            zeroRecords:
                "No matching audience records found."

        },

        columnDefs: [

            {
                orderable: false,
                targets: 9
            }

        ]

    });

});



function toggleSidebar()
{
    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('open');

    overlay.classList.toggle('open');
}


function closeSidebar()
{
    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.remove('open');

    overlay.classList.remove('open');
}


/* =========================================================
   LOGOUT VALIDATION
========================================================= */

function confirmLogout()
{
    return confirm(
        "Are you sure you want to logout from the Admin Panel?"
    );
}


/* =========================================================
   CLOSE SIDEBAR AFTER CLICKING MENU
========================================================= */

document
    .querySelectorAll('.sidebar-link')
    .forEach(function (link) {

        link.addEventListener(
            'click',
            function () {

                if (window.innerWidth <= 1000) {

                    closeSidebar();

                }

            }
        );

    });


window.addEventListener(
    'resize',
    function () {

        if (window.innerWidth > 1000) {

            closeSidebar();

        }

    }
);

</script>


</body>

</html>