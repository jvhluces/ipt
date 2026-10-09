<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
| Current file:
| ipt/admin/event.php
|
| Database:
| ipt/config/db.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/db.php';


/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: ../auth/login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| CURRENT PAGE
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| DELETE EVENT
|--------------------------------------------------------------------------
|
| Admin can delete an event only if it has no participants.
| This prevents accidental deletion of event participation records.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_event'])
) {

    $eventId = (int)($_POST['event_id'] ?? 0);

    if ($eventId <= 0) {

        $errorMessage = "Invalid event selected.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | CHECK PARTICIPANTS
        |--------------------------------------------------------------------------
        */

        $checkParticipants = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM event_participants
             WHERE event_id = ?"
        );

        if ($checkParticipants) {

            $checkParticipants->bind_param(
                "i",
                $eventId
            );

            $checkParticipants->execute();

            $participantResult =
                $checkParticipants->get_result();

            $participantRow =
                $participantResult->fetch_assoc();

            $participantCount =
                (int)($participantRow['total'] ?? 0);

            $checkParticipants->close();


            if ($participantCount > 0) {

                $errorMessage =
                    "This event cannot be deleted because it already has "
                    . $participantCount
                    . " participant(s). You may change its status instead.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | DELETE EVENT
                |--------------------------------------------------------------------------
                */

                $deleteEvent = $conn->prepare(
                    "DELETE FROM events
                     WHERE event_id = ?"
                );

                if ($deleteEvent) {

                    $deleteEvent->bind_param(
                        "i",
                        $eventId
                    );

                    if ($deleteEvent->execute()) {

                        $successMessage =
                            "Event deleted successfully.";

                    } else {

                        $errorMessage =
                            "Unable to delete the event. "
                            . "Please try again.";

                    }

                    $deleteEvent->close();

                } else {

                    $errorMessage =
                        "Database error while deleting the event.";

                }

            }

        } else {

            $errorMessage =
                "Unable to check event participants.";

        }

    }
}


/*
|--------------------------------------------------------------------------
| UPDATE EVENT STATUS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_status'])
) {

    $eventId =
        (int)($_POST['event_id'] ?? 0);

    $newStatus =
        trim($_POST['status'] ?? '');


    $allowedStatuses = [
        'Upcoming',
        'Ongoing',
        'Ended'
    ];


    if ($eventId <= 0) {

        $errorMessage =
            "Invalid event selected.";

    } elseif (!in_array($newStatus, $allowedStatuses, true)) {

        $errorMessage =
            "Invalid event status.";

    } else {

        $updateStatus = $conn->prepare(
            "UPDATE events
             SET status = ?
             WHERE event_id = ?"
        );

        if ($updateStatus) {

            $updateStatus->bind_param(
                "si",
                $newStatus,
                $eventId
            );

            if ($updateStatus->execute()) {

                $successMessage =
                    "Event status updated to "
                    . $newStatus
                    . ".";

            } else {

                $errorMessage =
                    "Unable to update event status.";

            }

            $updateStatus->close();

        } else {

            $errorMessage =
                "Database error while updating status.";

        }

    }
}


/*
|--------------------------------------------------------------------------
| UPDATE EVENT DETAILS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_event'])
) {

    $eventId =
        (int)($_POST['event_id'] ?? 0);

    $eventName =
        trim($_POST['event_name'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $category =
        trim($_POST['category'] ?? '');

    $eventDate =
        trim($_POST['event_date'] ?? '');

    $location =
        trim($_POST['location'] ?? '');

    $capacity =
        (int)($_POST['capacity'] ?? 0);

    $status =
        trim($_POST['status'] ?? '');


    $allowedCategories = [
        'Wedding',
        'Birthday',
        'Meeting',
        'Seminar',
        'Workshop',
        'Community Event'
    ];


    $allowedStatuses = [
        'Upcoming',
        'Ongoing',
        'Ended'
    ];


    if ($eventId <= 0) {

        $errorMessage =
            "Invalid event selected.";

    } elseif ($eventName === '') {

        $errorMessage =
            "Event name is required.";

    } elseif ($category === '') {

        $errorMessage =
            "Please select an event category.";

    } elseif (!in_array($category, $allowedCategories, true)) {

        $errorMessage =
            "Invalid event category.";

    } elseif ($eventDate === '') {

        $errorMessage =
            "Event date and time are required.";

    } elseif ($location === '') {

        $errorMessage =
            "Event location is required.";

    } elseif ($capacity <= 0) {

        $errorMessage =
            "Capacity must be greater than zero.";

    } elseif (!in_array($status, $allowedStatuses, true)) {

        $errorMessage =
            "Invalid event status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | CHECK CURRENT PARTICIPANTS
        |--------------------------------------------------------------------------
        */

        $participantCheck = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM event_participants
             WHERE event_id = ?"
        );

        $currentParticipants = 0;

        if ($participantCheck) {

            $participantCheck->bind_param(
                "i",
                $eventId
            );

            $participantCheck->execute();

            $participantResult =
                $participantCheck->get_result();

            $participantRow =
                $participantResult->fetch_assoc();

            $currentParticipants =
                (int)($participantRow['total'] ?? 0);

            $participantCheck->close();

        }


        /*
        |--------------------------------------------------------------------------
        | CAPACITY CANNOT BE LOWER THAN CURRENT PARTICIPANTS
        |--------------------------------------------------------------------------
        */

        if ($capacity < $currentParticipants) {

            $errorMessage =
                "Capacity cannot be lower than the current "
                . $currentParticipants
                . " participant(s).";

        } else {

            $updateEvent = $conn->prepare(
                "UPDATE events
                 SET
                    event_name = ?,
                    description = ?,
                    category = ?,
                    event_date = ?,
                    location = ?,
                    capacity = ?,
                    status = ?
                 WHERE event_id = ?"
            );

            if ($updateEvent) {

                $updateEvent->bind_param(
                    "sssssisi",
                    $eventName,
                    $description,
                    $category,
                    $eventDate,
                    $location,
                    $capacity,
                    $status,
                    $eventId
                );

                if ($updateEvent->execute()) {

                    $successMessage =
                        "Event details updated successfully.";

                } else {

                    $errorMessage =
                        "Unable to update the event.";

                }

                $updateEvent->close();

            } else {

                $errorMessage =
                    "Database error while updating the event.";

            }

        }

    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');

$statusFilter =
    trim($_GET['status'] ?? 'All');

$categoryFilter =
    trim($_GET['category'] ?? 'All');


$allowedStatuses = [
    'All',
    'Upcoming',
    'Ongoing',
    'Ended'
];


$allowedCategories = [
    'All',
    'Wedding',
    'Birthday',
    'Meeting',
    'Seminar',
    'Workshop',
    'Community Event'
];


if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}


if (!in_array($categoryFilter, $allowedCategories, true)) {
    $categoryFilter = 'All';
}


/*
|--------------------------------------------------------------------------
| LOAD EVENTS
|--------------------------------------------------------------------------
*/

$events = [];


$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.description,
        e.category,
        e.event_date,
        e.location,
        e.capacity,
        e.status,
        e.organizer_id,

        u.fullname AS organizer_name,

        COUNT(ep.participation_id)
            AS participant_count

    FROM events e

    LEFT JOIN users u
        ON e.organizer_id = u.user_id

    LEFT JOIN event_participants ep
        ON e.event_id = ep.event_id

    WHERE 1 = 1
";


$params = [];
$types = '';


if ($search !== '') {

    $sql .= "
        AND (
            e.event_name LIKE ?
            OR e.location LIKE ?
            OR e.category LIKE ?
            OR u.fullname LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}



if ($statusFilter !== 'All') {

    $sql .= "
        AND e.status = ?
    ";

    $params[] =
        $statusFilter;

    $types .= 's';
}


if ($categoryFilter !== 'All') {

    $sql .= "
        AND e.category = ?
    ";

    $params[] =
        $categoryFilter;

    $types .= 's';
}



$sql .= "
    GROUP BY
        e.event_id,
        e.event_name,
        e.description,
        e.category,
        e.event_date,
        e.location,
        e.capacity,
        e.status,
        e.organizer_id,
        u.fullname

    ORDER BY
        CASE e.status
            WHEN 'Upcoming' THEN 1
            WHEN 'Ongoing' THEN 2
            WHEN 'Ended' THEN 3
            ELSE 4
        END,

        e.event_date ASC
";


$stmt = $conn->prepare($sql);


if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );

    }

    $stmt->execute();

    $result =
        $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $events[] = $row;

    }

    $stmt->close();

}




$totalEvents =
    count($events);

$upcomingCount = 0;
$ongoingCount = 0;
$endedCount = 0;


foreach ($events as $event) {

    if ($event['status'] === 'Upcoming') {
        $upcomingCount++;
    }

    if ($event['status'] === 'Ongoing') {
        $ongoingCount++;
    }

    if ($event['status'] === 'Ended') {
        $endedCount++;
    }

}




$groupedEvents = [
    'Upcoming' => [],
    'Ongoing' => [],
    'Ended' => []
];


foreach ($events as $event) {

    $eventStatus =
        $event['status'] ?? '';


    if (
        isset($groupedEvents[$eventStatus])
    ) {

        $groupedEvents[$eventStatus][] =
            $event;

    }

}



function categoryIcon($category)
{

    $icons = [

        'Wedding' =>
            'bi-heart-fill',

        'Birthday' =>
            'bi-balloon-fill',

        'Meeting' =>
            'bi-people-fill',

        'Seminar' =>
            'bi-mortarboard-fill',

        'Workshop' =>
            'bi-tools',

        'Community Event' =>
            'bi-globe2'

    ];


    return $icons[$category]
        ?? 'bi-calendar-event-fill';

}


function statusClass($status)
{

    if ($status === 'Upcoming') {
        return 'status-upcoming';
    }

    if ($status === 'Ongoing') {
        return 'status-ongoing';
    }

    if ($status === 'Ended') {
        return 'status-ended';
    }

    return 'status-default';

}




function formatEventDate($date)
{

    if (empty($date)) {
        return 'No date specified';
    }


    $timestamp =
        strtotime($date);


    if (!$timestamp) {
        return 'Invalid date';
    }


    return date(
        'M d, Y',
        $timestamp
    );

}


function formatEventTime($date)
{

    if (empty($date)) {
        return '';
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
    Events |  Event System
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

    html {
        scroll-behavior: smooth;
    }

    body {
        margin: 0;
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

        box-shadow:
            0 2px 12px
            rgba(0, 0, 0, .08);
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

        background:
            rgba(255,255,255,.15);

        color: #ffffff;

        font-size: 22px;

        align-items: center;

        justify-content: center;

        cursor: pointer;
    }


    .sidebar-toggle:hover {
        background:
            rgba(255,255,255,.25);
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

        transform:
            translateX(0);

        transition:
            transform .25s ease;

        box-shadow:
            3px 0 15px
            rgba(0,0,0,.08);
    }


    .admin-sidebar::-webkit-scrollbar {
        width: 6px;
    }


    .admin-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }


    .admin-sidebar::-webkit-scrollbar-thumb {
        background:
            rgba(255,255,255,.15);

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

        background:
            rgba(255,255,255,.08);

        color: #ffffff;

        align-items: center;
        justify-content: center;

        cursor: pointer;
    }


    .sidebar-close:hover {
        background:
            rgba(255,255,255,.16);
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

        box-shadow:
            0 5px 15px
            rgba(37,99,235,.25);
    }


    .admin-name {

        font-size: 14px;

        font-weight: 700;
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


    /* =====================================================
       OVERLAY
    ====================================================== */

    .sidebar-overlay {

        display: none;

        position: fixed;

        inset: 0;

        background:
            rgba(0,0,0,.45);

        z-index: 1150;
    }


    .sidebar-overlay.show {
        display: block;
    }


    /* =====================================================
       MAIN
    ====================================================== */

    .main-wrapper {

        margin-left: 250px;

        padding-top: 64px;

        width:
            calc(100% - 250px);

        min-height: 100vh;
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

        align-items: flex-start;

        gap: 20px;

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


    /* =====================================================
       STAT CARDS
    ====================================================== */

    .stat-grid {

        display: grid;

        grid-template-columns:
            repeat(4, minmax(0,1fr));

        gap: 16px;

        margin-bottom: 25px;
    }


    .stat-card {

        border: 0;

        border-radius: 14px;

        background: #ffffff;

        padding: 18px;

        box-shadow:
            0 4px 18px
            rgba(15,23,42,.06);
    }


    .stat-card-content {

        display: flex;

        justify-content: space-between;

        align-items: center;
    }


    .stat-label {

        font-size: 13px;

        color: #6b7280;

        margin-bottom: 5px;
    }


    .stat-number {

        font-size: 27px;

        font-weight: 700;

        color: #111827;
    }


    .stat-icon {

        width: 48px;
        height: 48px;

        border-radius: 12px;

        display: flex;

        align-items: center;
        justify-content: center;

        font-size: 21px;
    }


    .stat-icon.all {
        background: #dbeafe;
        color: #2563eb;
    }


    .stat-icon.upcoming {
        background: #dbeafe;
        color: #2563eb;
    }


    .stat-icon.ongoing {
        background: #dcfce7;
        color: #16a34a;
    }


    .stat-icon.ended {
        background: #e5e7eb;
        color: #6b7280;
    }


    /* =====================================================
       FILTER CARD
    ====================================================== */

    .filter-card {

        background: #ffffff;

        border: 0;

        border-radius: 14px;

        padding: 20px;

        box-shadow:
            0 4px 18px
            rgba(15,23,42,.06);

        margin-bottom: 30px;
    }


    .filter-label {

        font-size: 12px;

        font-weight: 600;

        color: #6b7280;

        margin-bottom: 6px;
    }


    .search-box {

        position: relative;
    }


    .search-box i {

        position: absolute;

        left: 14px;

        top: 50%;

        transform:
            translateY(-50%);

        color: #9ca3af;
    }


    .search-box input {

        padding-left: 40px;

        height: 42px;
    }


    /* =====================================================
       STATUS SECTION
    ====================================================== */

    .status-section {

        margin-bottom: 38px;
    }


    .status-section-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 16px;

        gap: 10px;
    }


    .status-title-wrapper {

        display: flex;

        align-items: center;

        gap: 10px;
    }


    .status-title {

        margin: 0;

        font-size: 20px;

        font-weight: 700;

        color: #111827;
    }


    .status-indicator {

        width: 10px;
        height: 10px;

        border-radius: 50%;
    }


    .status-indicator.upcoming {
        background: #2563eb;
    }


    .status-indicator.ongoing {
        background: #16a34a;
    }


    .status-indicator.ended {
        background: #6b7280;
    }


    /* =====================================================
       CATEGORY GROUP
    ====================================================== */

    .category-group {

        margin-bottom: 22px;
    }


    .category-heading {

        display: flex;

        align-items: center;

        gap: 8px;

        font-size: 13px;

        font-weight: 700;

        color: #4b5563;

        margin-bottom: 10px;

        text-transform: uppercase;

        letter-spacing: .03em;
    }


    .category-heading i {
        color: #2563eb;
    }


    /* =====================================================
       EVENT GRID
    ====================================================== */

    .event-grid {

        display: grid;

        grid-template-columns:
            repeat(3, minmax(0,1fr));

        gap: 18px;
    }


    /* =====================================================
       EVENT CARD
    ====================================================== */

    .event-card {

        position: relative;

        background: #ffffff;

        border-radius: 14px;

        border: 1px solid #e5e7eb;

        overflow: hidden;

        transition:
            transform .2s ease,
            box-shadow .2s ease;

        box-shadow:
            0 3px 14px
            rgba(15,23,42,.04);
    }


    .event-card:hover {

        transform:
            translateY(-3px);

        box-shadow:
            0 9px 25px
            rgba(15,23,42,.09);
    }


    .event-card-top {

        height: 8px;
    }


    .event-card-top.upcoming {
        background: #2563eb;
    }


    .event-card-top.ongoing {
        background: #16a34a;
    }


    .event-card-top.ended {
        background: #6b7280;
    }


    .event-card-body {

        padding: 19px;
    }


    .event-card-header {

        display: flex;

        align-items: flex-start;

        justify-content: space-between;

        gap: 12px;

        margin-bottom: 12px;
    }


    .event-category {

        display: inline-flex;

        align-items: center;

        gap: 5px;

        padding: 5px 9px;

        border-radius: 20px;

        background: #eff6ff;

        color: #2563eb;

        font-size: 11px;

        font-weight: 700;
    }


    .event-title {

        margin: 0 0 5px;

        font-size: 17px;

        line-height: 1.35;

        font-weight: 700;

        color: #111827;

        word-break: break-word;
    }


    .event-description {

        font-size: 13px;

        color: #6b7280;

        line-height: 1.55;

        margin-bottom: 15px;

        display: -webkit-box;

        -webkit-line-clamp: 2;

        -webkit-box-orient: vertical;

        overflow: hidden;
    }


    .event-meta {

        display: flex;

        flex-direction: column;

        gap: 8px;

        margin-bottom: 15px;
    }


    .event-meta-item {

        display: flex;

        align-items: flex-start;

        gap: 8px;

        font-size: 12px;

        color: #4b5563;
    }


    .event-meta-item i {

        color: #6b7280;

        margin-top: 1px;

        width: 15px;
    }


    .event-organizer {

        padding-top: 12px;

        border-top:
            1px solid
            #f0f1f3;

        margin-bottom: 15px;
    }


    .organizer-label {

        font-size: 10px;

        color: #9ca3af;

        text-transform: uppercase;

        font-weight: 700;

        letter-spacing: .05em;
    }


    .organizer-name {

        margin-top: 3px;

        font-size: 13px;

        font-weight: 600;

        color: #374151;
    }


    .event-footer {

        display: flex;

        justify-content: space-between;

        align-items: center;

        gap: 10px;

        padding-top: 12px;

        border-top:
            1px solid
            #f0f1f3;
    }


    .participant-info {

        font-size: 12px;

        color: #6b7280;
    }


    .participant-info strong {
        color: #374151;
    }


    .event-actions {

        display: flex;

        gap: 5px;
    }


    .event-action-btn {

        width: 34px;
        height: 34px;

        border: 0;

        border-radius: 8px;

        display: flex;

        align-items: center;
        justify-content: center;

        cursor: pointer;
    }


    .btn-view {

        background: #eff6ff;

        color: #2563eb;
    }


    .btn-view:hover {
        background: #dbeafe;
    }


    .btn-edit {

        background: #fef3c7;

        color: #b45309;
    }


    .btn-edit:hover {
        background: #fde68a;
    }


    .btn-status {

        background: #ecfdf5;

        color: #059669;
    }


    .btn-status:hover {
        background: #d1fae5;
    }


    .btn-delete {

        background: #fef2f2;

        color: #dc2626;
    }


    .btn-delete:hover {
        background: #fee2e2;
    }


    /* =====================================================
       STATUS BADGES
    ====================================================== */

    .event-status {

        display: inline-flex;

        align-items: center;

        gap: 5px;

        font-size: 10px;

        font-weight: 700;

        padding: 5px 8px;

        border-radius: 20px;

        white-space: nowrap;
    }


    .status-upcoming {

        background: #dbeafe;

        color: #1d4ed8;
    }


    .status-ongoing {

        background: #dcfce7;

        color: #15803d;
    }


    .status-ended {

        background: #e5e7eb;

        color: #4b5563;
    }


    .status-default {

        background: #f3f4f6;

        color: #374151;
    }


    /* =====================================================
       EMPTY STATE
    ====================================================== */

    .empty-state {

        text-align: center;

        padding: 45px 20px;

        background: #ffffff;

        border-radius: 14px;

        border: 1px dashed #d1d5db;
    }


    .empty-state-icon {

        width: 62px;
        height: 62px;

        margin: 0 auto 15px;

        border-radius: 50%;

        background: #eff6ff;

        color: #2563eb;

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 27px;
    }


    .empty-state h5 {

        margin-bottom: 6px;

        font-weight: 700;
    }


    .empty-state p {

        color: #6b7280;

        margin-bottom: 0;
    }


    /* =====================================================
       MODAL
    ====================================================== */

    .modal-content {

        border: 0;

        border-radius: 15px;

        overflow: hidden;
    }


    .modal-header {

        border-bottom:
            1px solid
            #eef0f3;
    }


    .modal-footer {

        border-top:
            1px solid
            #eef0f3;
    }


    .detail-label {

        font-size: 11px;

        font-weight: 700;

        color: #9ca3af;

        text-transform: uppercase;

        letter-spacing: .05em;

        margin-bottom: 3px;
    }


    .detail-value {

        font-size: 14px;

        color: #374151;

        margin-bottom: 15px;
    }


    /* =====================================================
       RESPONSIVE
    ====================================================== */

    @media (max-width: 1199.98px) {

        .event-grid {

            grid-template-columns:
                repeat(2, minmax(0,1fr));

        }

        .stat-grid {

            grid-template-columns:
                repeat(2, minmax(0,1fr));

        }

    }


    @media (max-width: 991.98px) {

        .sidebar-toggle {
            display: flex;
        }


        .admin-sidebar {

            transform:
                translateX(-100%);
        }


        .admin-sidebar.open {

            transform:
                translateX(0);
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

    }


    @media (max-width: 767.98px) {

        .event-grid {

            grid-template-columns:
                1fr;
        }


        .page-title {

            font-size: 24px;
        }


        .filter-card {

            padding: 16px;
        }


        .status-section-header {

            align-items: flex-start;
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


        .main-wrapper {

            padding-top: 60px;
        }


        .main-content {

            padding: 20px 14px;
        }


        .stat-grid {

            grid-template-columns:
                1fr;
        }


        .page-header {

            margin-bottom: 20px;
        }


        .event-card-body {

            padding: 16px;
        }

    }

</style>
```

</head>

<body>

<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<nav class="top-navbar">

```
<div class="navbar-inner">


    <div class="d-flex align-items-center gap-2">


        <!-- BURGER -->

        <button
            type="button"
            class="sidebar-toggle"
            id="sidebarToggle"
            aria-label="Open navigation"
            aria-expanded="false"
        >

            <i class="bi bi-list"></i>

        </button>


        <!-- BRAND -->

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
```

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


    <!-- DASHBOARD -->

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
                Events
            </h1>

            <p class="page-subtitle">
                Manage and monitor all events created in the system.
            </p>

        </div>

    </div>



    <div class="stat-grid">


        <div class="stat-card">

            <div class="stat-card-content">

                <div>

                    <div class="stat-label">
                        Total Events
                    </div>

                    <div class="stat-number">
                        <?= $totalEvents; ?>
                    </div>

                </div>

                <div class="stat-icon all">

                    <i class="bi bi-calendar-event"></i>

                </div>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-card-content">

                <div>

                    <div class="stat-label">
                        Upcoming
                    </div>

                    <div class="stat-number">
                        <?= $upcomingCount; ?>
                    </div>

                </div>

                <div class="stat-icon upcoming">

                    <i class="bi bi-calendar-plus"></i>

                </div>

            </div>

        </div>




        <div class="stat-card">

            <div class="stat-card-content">

                <div>

                    <div class="stat-label">
                        Ongoing
                    </div>

                    <div class="stat-number">
                        <?= $ongoingCount; ?>
                    </div>

                </div>

                <div class="stat-icon ongoing">

                    <i class="bi bi-broadcast"></i>

                </div>

            </div>

        </div>



        <div class="stat-card">

            <div class="stat-card-content">

                <div>

                    <div class="stat-label">
                        Ended
                    </div>

                    <div class="stat-number">
                        <?= $endedCount; ?>
                    </div>

                </div>

                <div class="stat-icon ended">

                    <i class="bi bi-check-circle"></i>

                </div>

            </div>

        </div>


    </div>



    <div class="filter-card">


        <form
            method="GET"
            action="event.php"
        >

            <div class="row g-3 align-items-end">


                <!-- SEARCH -->

                <div class="col-lg-6">

                    <label class="filter-label">
                        Search Events
                    </label>

                    <div class="search-box">

                        <i class="bi bi-search"></i>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search event, organizer, location..."
                            value="<?= e($search); ?>"
                        >

                    </div>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="filter-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

                        <?php foreach ($allowedStatuses as $status): ?>

                            <option
                                value="<?= e($status); ?>"
                                <?= $statusFilter === $status ? 'selected' : ''; ?>
                            >
                                <?= e($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="col-md-4 col-lg-2">

                    <label class="filter-label">
                        Category
                    </label>

                    <select
                        name="category"
                        class="form-select"
                    >

                        <?php foreach ($allowedCategories as $category): ?>

                            <option
                                value="<?= e($category); ?>"
                                <?= $categoryFilter === $category ? 'selected' : ''; ?>
                            >
                                <?= e($category); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="col-md-4 col-lg-2 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary flex-grow-1"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Filter

                    </button>


                    <a
                        href="event.php"
                        class="btn btn-outline-secondary"
                        title="Clear filters"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                    </a>

                </div>


            </div>

        </form>

    </div>



    <?php if (empty($events)): ?>


        <div class="empty-state">

            <div class="empty-state-icon">

                <i class="bi bi-calendar-x"></i>

            </div>

            <h5>
                No events found
            </h5>

            <p>
                There are no events matching your current filters.
            </p>

        </div>


    <?php else: ?>


        <?php if (!empty($groupedEvents['Upcoming'])): ?>


            <section class="status-section">


                <div class="status-section-header">

                    <div class="status-title-wrapper">

                        <span class="status-indicator upcoming"></span>

                        <h2 class="status-title">
                            Upcoming Events
                        </h2>

                        <span class="badge bg-primary">
                            <?= count($groupedEvents['Upcoming']); ?>
                        </span>

                    </div>

                </div>


                <?php

                $upcomingByCategory = [];

                foreach (
                    $groupedEvents['Upcoming']
                    as $event
                ) {

                    $cat =
                        $event['category']
                        ?: 'Other';

                    $upcomingByCategory[$cat][] =
                        $event;
                }

                ?>


                <?php foreach ($upcomingByCategory as $category => $categoryEvents): ?>


                    <div class="category-group">


                        <div class="category-heading">

                            <i
                                class="bi <?= e(categoryIcon($category)); ?>"
                            ></i>

                            <?= e($category); ?>

                            <span class="badge bg-light text-dark">
                                <?= count($categoryEvents); ?>
                            </span>

                        </div>


                        <div class="event-grid">


                            <?php foreach ($categoryEvents as $event): ?>


                                <?php

                                $participants =
                                    (int)$event['participant_count'];

                                $capacity =
                                    (int)$event['capacity'];

                                ?>


                                <div class="event-card">


                                    <div class="event-card-top upcoming"></div>


                                    <div class="event-card-body">


                                        <div class="event-card-header">

                                            <div>

                                                <span class="event-category">

                                                    <i
                                                        class="bi <?= e(categoryIcon($event['category'])); ?>"
                                                    ></i>

                                                    <?= e($event['category']); ?>

                                                </span>

                                            </div>


                                            <span class="event-status status-upcoming">

                                                <i class="bi bi-clock"></i>

                                                Upcoming

                                            </span>

                                        </div>


                                        <h3 class="event-title">
                                            <?= e($event['event_name']); ?>
                                        </h3>


                                        <p class="event-description">

                                            <?= e(
                                                $event['description']
                                                ?: 'No event description provided.'
                                            ); ?>

                                        </p>


                                        <div class="event-meta">


                                            <div class="event-meta-item">

                                                <i class="bi bi-calendar3"></i>

                                                <span>

                                                    <?= e(
                                                        formatEventDate(
                                                            $event['event_date']
                                                        )
                                                    ); ?>

                                                    <?php if (!empty($event['event_date'])): ?>

                                                        ·

                                                        <?= e(
                                                            formatEventTime(
                                                                $event['event_date']
                                                            )
                                                        ); ?>

                                                    <?php endif; ?>

                                                </span>

                                            </div>


                                            <div class="event-meta-item">

                                                <i class="bi bi-geo-alt"></i>

                                                <span>
                                                    <?= e($event['location']); ?>
                                                </span>

                                            </div>


                                        </div>


                                        <div class="event-organizer">

                                            <div class="organizer-label">
                                                Organizer
                                            </div>

                                            <div class="organizer-name">

                                                <i class="bi bi-person me-1"></i>

                                                <?= e(
                                                    $event['organizer_name']
                                                    ?: 'Unknown Organizer'
                                                ); ?>

                                            </div>

                                        </div>


                                        <div class="event-footer">


                                            <div class="participant-info">

                                                <strong>
                                                    <?= $participants; ?>
                                                </strong>

                                                /

                                                <?= $capacity; ?>

                                                participants

                                            </div>


                                            <div class="event-actions">


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-view"
                                                    title="View Details"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-description="<?= e($event['description']); ?>"
                                                    data-category="<?= e($event['category']); ?>"
                                                    data-date="<?= e(formatEventDate($event['event_date']) . ' ' . formatEventTime($event['event_date'])); ?>"
                                                    data-location="<?= e($event['location']); ?>"
                                                    data-organizer="<?= e($event['organizer_name'] ?: 'Unknown Organizer'); ?>"
                                                    data-capacity="<?= $capacity; ?>"
                                                    data-participants="<?= $participants; ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </button>


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-edit"
                                                    title="Edit Event"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-description="<?= e($event['description']); ?>"
                                                    data-category="<?= e($event['category']); ?>"
                                                    data-date="<?= e($event['event_date'] ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : ''); ?>"
                                                    data-location="<?= e($event['location']); ?>"
                                                    data-capacity="<?= $capacity; ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-pencil-square"></i>

                                                </button>


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-status"
                                                    title="Change Status"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#statusEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-arrow-repeat"></i>

                                                </button>


                                                <?php if ($participants === 0): ?>

                                                    <button
                                                        type="button"
                                                        class="event-action-btn btn-delete"
                                                        title="Delete Event"
                                                        onclick="confirmDelete(<?= (int)$event['event_id']; ?>, '<?= e(addslashes($event['event_name'])); ?>')"
                                                    >

                                                        <i class="bi bi-trash3"></i>

                                                    </button>

                                                <?php endif; ?>


                                            </div>


                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            </section>


        <?php endif; ?>


     

        <?php if (!empty($groupedEvents['Ongoing'])): ?>


            <section class="status-section">


                <div class="status-section-header">

                    <div class="status-title-wrapper">

                        <span class="status-indicator ongoing"></span>

                        <h2 class="status-title">
                            Ongoing Events
                        </h2>

                        <span class="badge bg-success">
                            <?= count($groupedEvents['Ongoing']); ?>
                        </span>

                    </div>

                </div>


                <?php

                $ongoingByCategory = [];

                foreach (
                    $groupedEvents['Ongoing']
                    as $event
                ) {

                    $cat =
                        $event['category']
                        ?: 'Other';

                    $ongoingByCategory[$cat][] =
                        $event;
                }

                ?>


                <?php foreach ($ongoingByCategory as $category => $categoryEvents): ?>


                    <div class="category-group">


                        <div class="category-heading">

                            <i
                                class="bi <?= e(categoryIcon($category)); ?>"
                            ></i>

                            <?= e($category); ?>

                            <span class="badge bg-light text-dark">
                                <?= count($categoryEvents); ?>
                            </span>

                        </div>


                        <div class="event-grid">


                            <?php foreach ($categoryEvents as $event): ?>


                                <?php

                                $participants =
                                    (int)$event['participant_count'];

                                $capacity =
                                    (int)$event['capacity'];

                                ?>


                                <div class="event-card">


                                    <div class="event-card-top ongoing"></div>


                                    <div class="event-card-body">


                                        <div class="event-card-header">

                                            <div>

                                                <span class="event-category">

                                                    <i
                                                        class="bi <?= e(categoryIcon($event['category'])); ?>"
                                                    ></i>

                                                    <?= e($event['category']); ?>

                                                </span>

                                            </div>


                                            <span class="event-status status-ongoing">

                                                <i class="bi bi-broadcast"></i>

                                                Ongoing

                                            </span>

                                        </div>


                                        <h3 class="event-title">
                                            <?= e($event['event_name']); ?>
                                        </h3>


                                        <p class="event-description">

                                            <?= e(
                                                $event['description']
                                                ?: 'No event description provided.'
                                            ); ?>

                                        </p>


                                        <div class="event-meta">


                                            <div class="event-meta-item">

                                                <i class="bi bi-calendar3"></i>

                                                <span>

                                                    <?= e(
                                                        formatEventDate(
                                                            $event['event_date']
                                                        )
                                                    ); ?>

                                                    <?php if (!empty($event['event_date'])): ?>

                                                        ·

                                                        <?= e(
                                                            formatEventTime(
                                                                $event['event_date']
                                                            )
                                                        ); ?>

                                                    <?php endif; ?>

                                                </span>

                                            </div>


                                            <div class="event-meta-item">

                                                <i class="bi bi-geo-alt"></i>

                                                <span>
                                                    <?= e($event['location']); ?>
                                                </span>

                                            </div>


                                        </div>


                                        <div class="event-organizer">

                                            <div class="organizer-label">
                                                Organizer
                                            </div>

                                            <div class="organizer-name">

                                                <i class="bi bi-person me-1"></i>

                                                <?= e(
                                                    $event['organizer_name']
                                                    ?: 'Unknown Organizer'
                                                ); ?>

                                            </div>

                                        </div>


                                        <div class="event-footer">


                                            <div class="participant-info">

                                                <strong>
                                                    <?= $participants; ?>
                                                </strong>

                                                /

                                                <?= $capacity; ?>

                                                participants

                                            </div>


                                            <div class="event-actions">


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-view"
                                                    title="View Details"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-description="<?= e($event['description']); ?>"
                                                    data-category="<?= e($event['category']); ?>"
                                                    data-date="<?= e(formatEventDate($event['event_date']) . ' ' . formatEventTime($event['event_date'])); ?>"
                                                    data-location="<?= e($event['location']); ?>"
                                                    data-organizer="<?= e($event['organizer_name'] ?: 'Unknown Organizer'); ?>"
                                                    data-capacity="<?= $capacity; ?>"
                                                    data-participants="<?= $participants; ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </button>


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-edit"
                                                    title="Edit Event"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-description="<?= e($event['description']); ?>"
                                                    data-category="<?= e($event['category']); ?>"
                                                    data-date="<?= e($event['event_date'] ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : ''); ?>"
                                                    data-location="<?= e($event['location']); ?>"
                                                    data-capacity="<?= $capacity; ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-pencil-square"></i>

                                                </button>


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-status"
                                                    title="Change Status"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#statusEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-arrow-repeat"></i>

                                                </button>


                                            </div>


                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            </section>


        <?php endif; ?>


        <!-- =================================================
             ENDED
        ================================================== -->

        <?php if (!empty($groupedEvents['Ended'])): ?>


            <section class="status-section">


                <div class="status-section-header">

                    <div class="status-title-wrapper">

                        <span class="status-indicator ended"></span>

                        <h2 class="status-title">
                            Ended Events
                        </h2>

                        <span class="badge bg-secondary">
                            <?= count($groupedEvents['Ended']); ?>
                        </span>

                    </div>

                </div>


                <?php

                $endedByCategory = [];

                foreach (
                    $groupedEvents['Ended']
                    as $event
                ) {

                    $cat =
                        $event['category']
                        ?: 'Other';

                    $endedByCategory[$cat][] =
                        $event;
                }

                ?>


                <?php foreach ($endedByCategory as $category => $categoryEvents): ?>


                    <div class="category-group">


                        <div class="category-heading">

                            <i
                                class="bi <?= e(categoryIcon($category)); ?>"
                            ></i>

                            <?= e($category); ?>

                            <span class="badge bg-light text-dark">
                                <?= count($categoryEvents); ?>
                            </span>

                        </div>


                        <div class="event-grid">


                            <?php foreach ($categoryEvents as $event): ?>


                                <?php

                                $participants =
                                    (int)$event['participant_count'];

                                $capacity =
                                    (int)$event['capacity'];

                                ?>


                                <div class="event-card">


                                    <div class="event-card-top ended"></div>


                                    <div class="event-card-body">


                                        <div class="event-card-header">

                                            <div>

                                                <span class="event-category">

                                                    <i
                                                        class="bi <?= e(categoryIcon($event['category'])); ?>"
                                                    ></i>

                                                    <?= e($event['category']); ?>

                                                </span>

                                            </div>


                                            <span class="event-status status-ended">

                                                <i class="bi bi-check-circle"></i>

                                                Ended

                                            </span>

                                        </div>


                                        <h3 class="event-title">
                                            <?= e($event['event_name']); ?>
                                        </h3>


                                        <p class="event-description">

                                            <?= e(
                                                $event['description']
                                                ?: 'No event description provided.'
                                            ); ?>

                                        </p>


                                        <div class="event-meta">


                                            <div class="event-meta-item">

                                                <i class="bi bi-calendar3"></i>

                                                <span>

                                                    <?= e(
                                                        formatEventDate(
                                                            $event['event_date']
                                                        )
                                                    ); ?>

                                                    <?php if (!empty($event['event_date'])): ?>

                                                        ·

                                                        <?= e(
                                                            formatEventTime(
                                                                $event['event_date']
                                                            )
                                                        ); ?>

                                                    <?php endif; ?>

                                                </span>

                                            </div>


                                            <div class="event-meta-item">

                                                <i class="bi bi-geo-alt"></i>

                                                <span>
                                                    <?= e($event['location']); ?>
                                                </span>

                                            </div>


                                        </div>


                                        <div class="event-organizer">

                                            <div class="organizer-label">
                                                Organizer
                                            </div>

                                            <div class="organizer-name">

                                                <i class="bi bi-person me-1"></i>

                                                <?= e(
                                                    $event['organizer_name']
                                                    ?: 'Unknown Organizer'
                                                ); ?>

                                            </div>

                                        </div>


                                        <div class="event-footer">


                                            <div class="participant-info">

                                                <strong>
                                                    <?= $participants; ?>
                                                </strong>

                                                /

                                                <?= $capacity; ?>

                                                participants

                                            </div>


                                            <div class="event-actions">


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-view"
                                                    title="View Details"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-description="<?= e($event['description']); ?>"
                                                    data-category="<?= e($event['category']); ?>"
                                                    data-date="<?= e(formatEventDate($event['event_date']) . ' ' . formatEventTime($event['event_date'])); ?>"
                                                    data-location="<?= e($event['location']); ?>"
                                                    data-organizer="<?= e($event['organizer_name'] ?: 'Unknown Organizer'); ?>"
                                                    data-capacity="<?= $capacity; ?>"
                                                    data-participants="<?= $participants; ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </button>


                                                <button
                                                    type="button"
                                                    class="event-action-btn btn-edit"
                                                    title="Edit Event"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editEventModal"
                                                    data-id="<?= (int)$event['event_id']; ?>"
                                                    data-name="<?= e($event['event_name']); ?>"
                                                    data-description="<?= e($event['description']); ?>"
                                                    data-category="<?= e($event['category']); ?>"
                                                    data-date="<?= e($event['event_date'] ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : ''); ?>"
                                                    data-location="<?= e($event['location']); ?>"
                                                    data-capacity="<?= $capacity; ?>"
                                                    data-status="<?= e($event['status']); ?>"
                                                >

                                                    <i class="bi bi-pencil-square"></i>

                                                </button>


                                                <?php if ($participants === 0): ?>

                                                    <button
                                                        type="button"
                                                        class="event-action-btn btn-delete"
                                                        title="Delete Event"
                                                        onclick="confirmDelete(<?= (int)$event['event_id']; ?>, '<?= e(addslashes($event['event_name'])); ?>')"
                                                    >

                                                        <i class="bi bi-trash3"></i>

                                                    </button>

                                                <?php endif; ?>


                                            </div>


                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            </section>


        <?php endif; ?>


    <?php endif; ?>


</main>
```

</div>

<!-- =========================================================
     DELETE FORM
========================================================= -->

<form
    method="POST"
    action="event.php"
    id="deleteForm"
    style="display:none;"
>

```
<input
    type="hidden"
    name="event_id"
    id="deleteEventId"
>

<input
    type="hidden"
    name="delete_event"
    value="1"
>
```

</form>

<!-- =========================================================
     VIEW EVENT MODAL
========================================================= -->

<div
    class="modal fade"
    id="viewEventModal"
    tabindex="-1"
    aria-hidden="true"
>

```
<div class="modal-dialog modal-lg modal-dialog-centered">

    <div class="modal-content">


        <div class="modal-header">

            <div>

                <h5 class="modal-title">
                    Event Details
                </h5>

                <small class="text-muted">
                    Complete event information
                </small>

            </div>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="modal"
            ></button>

        </div>


        <div class="modal-body">


            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">

                <div>

                    <h4
                        id="viewEventName"
                        class="mb-1 fw-bold"
                    >
                    </h4>

                    <span
                        id="viewEventCategory"
                        class="badge bg-primary"
                    >
                    </span>

                </div>


                <span
                    id="viewEventStatus"
                    class="event-status"
                >
                </span>

            </div>


            <div class="row">


                <div class="col-md-6">

                    <div class="detail-label">
                        Event Date
                    </div>

                    <div
                        id="viewEventDate"
                        class="detail-value"
                    >
                    </div>


                    <div class="detail-label">
                        Location
                    </div>

                    <div
                        id="viewEventLocation"
                        class="detail-value"
                    >
                    </div>


                    <div class="detail-label">
                        Organizer
                    </div>

                    <div
                        id="viewEventOrganizer"
                        class="detail-value"
                    >
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="detail-label">
                        Capacity
                    </div>

                    <div
                        id="viewEventCapacity"
                        class="detail-value"
                    >
                    </div>


                    <div class="detail-label">
                        Participants
                    </div>

                    <div
                        id="viewEventParticipants"
                        class="detail-value"
                    >
                    </div>


                    <div class="detail-label">
                        Available Slots
                    </div>

                    <div
                        id="viewEventAvailable"
                        class="detail-value"
                    >
                    </div>

                </div>


                <div class="col-12">

                    <div class="detail-label">
                        Description
                    </div>

                    <div
                        id="viewEventDescription"
                        class="detail-value"
                    >
                    </div>

                </div>


            </div>


        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-secondary"
                data-bs-dismiss="modal"
            >
                Close
            </button>

        </div>


    </div>

</div>
```

</div>

<!-- =========================================================
     EDIT EVENT MODAL
========================================================= -->

<div
    class="modal fade"
    id="editEventModal"
    tabindex="-1"
    aria-hidden="true"
>

```
<div class="modal-dialog modal-lg modal-dialog-centered">

    <div class="modal-content">


        <form
            method="POST"
            action="event.php"
        >


            <div class="modal-header">

                <div>

                    <h5 class="modal-title">
                        Edit Event
                    </h5>

                    <small class="text-muted">
                        Update event information
                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <input
                    type="hidden"
                    name="event_id"
                    id="editEventId"
                >


                <div class="row g-3">


                    <div class="col-md-8">

                        <label class="form-label">
                            Event Name
                        </label>

                        <input
                            type="text"
                            name="event_name"
                            id="editEventName"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label">
                            Category
                        </label>

                        <select
                            name="category"
                            id="editEventCategory"
                            class="form-select"
                            required
                        >

                            <option value="Wedding">
                                Wedding
                            </option>

                            <option value="Birthday">
                                Birthday
                            </option>

                            <option value="Meeting">
                                Meeting
                            </option>

                            <option value="Seminar">
                                Seminar
                            </option>

                            <option value="Workshop">
                                Workshop
                            </option>

                            <option value="Community Event">
                                Community Event
                            </option>

                        </select>

                    </div>


                    <div class="col-12">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="editEventDescription"
                            class="form-control"
                            rows="4"
                        ></textarea>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Event Date & Time
                        </label>

                        <input
                            type="datetime-local"
                            name="event_date"
                            id="editEventDate"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Location
                        </label>

                        <input
                            type="text"
                            name="location"
                            id="editEventLocation"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Capacity
                        </label>

                        <input
                            type="number"
                            name="capacity"
                            id="editEventCapacity"
                            class="form-control"
                            min="1"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            id="editEventStatus"
                            class="form-select"
                            required
                        >

                            <option value="Upcoming">
                                Upcoming
                            </option>

                            <option value="Ongoing">
                                Ongoing
                            </option>

                            <option value="Ended">
                                Ended
                            </option>

                        </select>

                    </div>


                </div>


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
                    type="submit"
                    name="update_event"
                    value="1"
                    class="btn btn-primary"
                >

                    <i class="bi bi-check-lg me-1"></i>

                    Save Changes

                </button>

            </div>


        </form>


    </div>

</div>
```

</div>

<!-- =========================================================
     STATUS MODAL
========================================================= -->

<div
    class="modal fade"
    id="statusEventModal"
    tabindex="-1"
    aria-hidden="true"
>

```
<div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">


        <form
            method="POST"
            action="event.php"
        >


            <div class="modal-header">

                <h5 class="modal-title">
                    Change Event Status
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <input
                    type="hidden"
                    name="event_id"
                    id="statusEventId"
                >


                <p class="text-muted mb-3">

                    Change the status of:

                    <strong id="statusEventName"></strong>

                </p>


                <label class="form-label">
                    New Status
                </label>


                <select
                    name="status"
                    id="statusEventValue"
                    class="form-select"
                    required
                >

                    <option value="Upcoming">
                        Upcoming
                    </option>

                    <option value="Ongoing">
                        Ongoing
                    </option>

                    <option value="Ended">
                        Ended
                    </option>

                </select>


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
                    type="submit"
                    name="update_status"
                    value="1"
                    class="btn btn-primary"
                >

                    <i class="bi bi-check-lg me-1"></i>

                    Update Status

                </button>

            </div>


        </form>


    </div>

</div>
```

</div>

<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>

<!-- =========================================================
     FLASH MESSAGE
========================================================= -->

<?php if ($successMessage !== ''): ?>

```
<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            alert(
                <?= json_encode($successMessage); ?>
            );

        }
    );

</script>
```

<?php endif; ?>

<?php if ($errorMessage !== ''): ?>

```
<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            alert(
                <?= json_encode($errorMessage); ?>
            );

        }
    );

</script>
```

<?php endif; ?>

<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {


            /* =================================================
               SIDEBAR
            ================================================== */

            const sidebar =
                document.getElementById(
                    'adminSidebar'
                );

            const toggle =
                document.getElementById(
                    'sidebarToggle'
                );

            const closeBtn =
                document.getElementById(
                    'sidebarClose'
                );

            const overlay =
                document.getElementById(
                    'sidebarOverlay'
                );


            function isMobile() {

                return window.innerWidth <= 991.98;

            }


            function openSidebar() {

                if (!isMobile()) {
                    return;
                }

                sidebar.classList.add(
                    'open'
                );

                overlay.classList.add(
                    'show'
                );

                toggle.setAttribute(
                    'aria-expanded',
                    'true'
                );

                document.body.style.overflow =
                    'hidden';

            }


            function closeSidebar() {

                sidebar.classList.remove(
                    'open'
                );

                overlay.classList.remove(
                    'show'
                );

                toggle.setAttribute(
                    'aria-expanded',
                    'false'
                );

                document.body.style.overflow =
                    '';

            }


            toggle.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    if (
                        sidebar.classList.contains(
                            'open'
                        )
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


            document.addEventListener(
                'click',
                function (event) {

                    if (!isMobile()) {
                        return;
                    }

                    if (
                        !sidebar.classList.contains(
                            'open'
                        )
                    ) {
                        return;
                    }

                    if (
                        !sidebar.contains(
                            event.target
                        ) &&
                        !toggle.contains(
                            event.target
                        )
                    ) {

                        closeSidebar();

                    }

                }
            );


            document.querySelectorAll(
                '.sidebar-link'
            ).forEach(
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


            /* =================================================
               VIEW EVENT MODAL
            ================================================== */

            const viewModal =
                document.getElementById(
                    'viewEventModal'
                );


            viewModal.addEventListener(
                'show.bs.modal',
                function (event) {

                    const button =
                        event.relatedTarget;


                    const name =
                        button.dataset.name;

                    const description =
                        button.dataset.description;

                    const category =
                        button.dataset.category;

                    const date =
                        button.dataset.date;

                    const location =
                        button.dataset.location;

                    const organizer =
                        button.dataset.organizer;

                    const capacity =
                        parseInt(
                            button.dataset.capacity || 0
                        );

                    const participants =
                        parseInt(
                            button.dataset.participants || 0
                        );

                    const status =
                        button.dataset.status;


                    document.getElementById(
                        'viewEventName'
                    ).textContent =
                        name;


                    document.getElementById(
                        'viewEventCategory'
                    ).textContent =
                        category;


                    document.getElementById(
                        'viewEventDate'
                    ).textContent =
                        date || 'N/A';


                    document.getElementById(
                        'viewEventLocation'
                    ).textContent =
                        location || 'N/A';


                    document.getElementById(
                        'viewEventOrganizer'
                    ).textContent =
                        organizer || 'N/A';


                    document.getElementById(
                        'viewEventCapacity'
                    ).textContent =
                        capacity;


                    document.getElementById(
                        'viewEventParticipants'
                    ).textContent =
                        participants;


                    document.getElementById(
                        'viewEventAvailable'
                    ).textContent =
                        Math.max(
                            0,
                            capacity - participants
                        );


                    document.getElementById(
                        'viewEventDescription'
                    ).textContent =
                        description ||
                        'No description provided.';


                    const statusElement =
                        document.getElementById(
                            'viewEventStatus'
                        );


                    statusElement.textContent =
                        status;


                    statusElement.className =
                        'event-status ' +
                        (
                            status === 'Upcoming'
                                ? 'status-upcoming'
                                : status === 'Ongoing'
                                    ? 'status-ongoing'
                                    : status === 'Ended'
                                        ? 'status-ended'
                                        : 'status-default'
                        );

                }
            );


            /* =================================================
               EDIT EVENT MODAL
            ================================================== */

            const editModal =
                document.getElementById(
                    'editEventModal'
                );


            editModal.addEventListener(
                'show.bs.modal',
                function (event) {

                    const button =
                        event.relatedTarget;


                    document.getElementById(
                        'editEventId'
                    ).value =
                        button.dataset.id;


                    document.getElementById(
                        'editEventName'
                    ).value =
                        button.dataset.name;


                    document.getElementById(
                        'editEventDescription'
                    ).value =
                        button.dataset.description;


                    document.getElementById(
                        'editEventCategory'
                    ).value =
                        button.dataset.category;


                    document.getElementById(
                        'editEventDate'
                    ).value =
                        button.dataset.date;


                    document.getElementById(
                        'editEventLocation'
                    ).value =
                        button.dataset.location;


                    document.getElementById(
                        'editEventCapacity'
                    ).value =
                        button.dataset.capacity;


                    document.getElementById(
                        'editEventStatus'
                    ).value =
                        button.dataset.status;

                }
            );


            /* =================================================
               STATUS MODAL
            ================================================== */

            const statusModal =
                document.getElementById(
                    'statusEventModal'
                );


            statusModal.addEventListener(
                'show.bs.modal',
                function (event) {

                    const button =
                        event.relatedTarget;


                    document.getElementById(
                        'statusEventId'
                    ).value =
                        button.dataset.id;


                    document.getElementById(
                        'statusEventName'
                    ).textContent =
                        button.dataset.name;


                    document.getElementById(
                        'statusEventValue'
                    ).value =
                        button.dataset.status;

                }
            );

        }
    );


    /* =========================================================
       DELETE CONFIRMATION
    ========================================================== */

    function confirmDelete(
        eventId,
        eventName
    ) {

        const message =
            'Are you sure you want to delete "' +
            eventName +
            '"?\n\n' +
            'This action cannot be undone.';

        if (
            confirm(message)
        ) {

            document.getElementById(
                'deleteEventId'
            ).value =
                eventId;

            document.getElementById(
                'deleteForm'
            ).submit();

        }

    }

</script>

</body>

</html>
