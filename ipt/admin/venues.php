<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
| Current file:
| ipt/admin/venues.php
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
| VARIABLES
|--------------------------------------------------------------------------
*/

$totalVenueBookings = 0;
$bookedVenues = 0;
$availableVenues = 0;

$venueRows = [];

$errorMessage = '';

/*
|--------------------------------------------------------------------------
| TOTAL VENUE BOOKINGS
|--------------------------------------------------------------------------
|
| Since the current event system stores venue/location inside events,
| we use events.location instead of depending on the old venues table.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM events
    WHERE location IS NOT NULL
    AND TRIM(location) <> ''
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalVenueBookings = (int) ($row['total'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| TOTAL CURRENTLY BOOKED VENUES
|--------------------------------------------------------------------------
|
| Upcoming and Ongoing events are considered currently booked.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(DISTINCT TRIM(location)) AS total
    FROM events
    WHERE location IS NOT NULL
    AND TRIM(location) <> ''
    AND status IN ('Upcoming', 'Ongoing')
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $bookedVenues = (int) ($row['total'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| TOTAL UNIQUE VENUES
|--------------------------------------------------------------------------
*/

$totalUniqueVenues = 0;

$sql = "
    SELECT COUNT(DISTINCT TRIM(location)) AS total
    FROM events
    WHERE location IS NOT NULL
    AND TRIM(location) <> ''
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalUniqueVenues = (int) ($row['total'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| AVAILABLE VENUES
|--------------------------------------------------------------------------
|
| A venue is considered available if it is not currently being used
| by an Upcoming or Ongoing event.
|
|--------------------------------------------------------------------------
*/

$availableVenues = max(
    0,
    $totalUniqueVenues - $bookedVenues
);


/*
|--------------------------------------------------------------------------
| GET EVENT VENUE BOOKINGS
|--------------------------------------------------------------------------
|
| We use the actual structure from the events table:
|
| event_id
| event_name
| event_date
| location
| capacity
| category
| status
| organizer_id
|
| We also get the organizer name from users.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.location,
        e.capacity,
        e.category,
        e.status,
        e.organizer_id,
        u.fullname AS organizer_name
    FROM events e
    LEFT JOIN users u
        ON e.organizer_id = u.user_id
    WHERE e.location IS NOT NULL
    AND TRIM(e.location) <> ''
    ORDER BY e.event_date DESC, e.event_id DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $venueRows[] = $row;

    }

} else {

    $errorMessage = mysqli_error($conn);

}


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatDateTime($dateTime)
{
    if (empty($dateTime)) {
        return 'N/A';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return e($dateTime);
    }

    return date('M d, Y h:i A', $timestamp);
}


function statusBadge($status)
{
    $status = trim((string) $status);

    switch ($status) {

        case 'Upcoming':

            return '<span class="badge bg-primary">
                        <i class="bi bi-calendar-event"></i>
                        Upcoming
                    </span>';

        case 'Ongoing':

            return '<span class="badge bg-success">
                        <i class="bi bi-play-circle"></i>
                        Ongoing
                    </span>';

        case 'Ended':

            return '<span class="badge bg-secondary">
                        <i class="bi bi-check-circle"></i>
                        Ended
                    </span>';

        default:

            return '<span class="badge bg-dark">'
                . e($status ?: 'Unknown')
                . '</span>';
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

    <title>Venue Book List - QC Event System</title>


    <!-- Bootstrap -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >


    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >


    <!-- DataTables -->
    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"
    >
    <link rel="stylesheet" href="../assets/css/admin/venues.css">

</head>


<body class="bg-light">


<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="admin.php"
        >
            QC Event System
        </a>


        <div class="d-flex">

            <a
                href="../auth/logout.php"
                class="btn btn-outline-light"
            >
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

    </div>

</nav>


<!-- =========================================================
     MAIN CONTAINER
========================================================= -->

<div class="container-fluid">

    <div class="row">


        <!-- =================================================
             SIDEBAR
        ================================================== -->

        <nav class="col-md-2 bg-dark text-white p-3 sidebar">

            <h4 class="text-center mb-4">
                Admin Panel
            </h4>


            <ul class="nav flex-column">


                <!-- HOME -->

                <li class="nav-item mb-1">

                    <a
                        href="admin.php"
                        class="nav-link text-white"
                    >

                        <i class="bi bi-house"></i>

                        Home

                    </a>

                </li>


               


                <!-- EVENT AUDIENCE -->

                <li class="nav-item mb-1">

                    <a
                        href="event_audience.php"
                        class="nav-link text-white"
                    >

                        <i class="bi bi-people"></i>

                        Event Audience List

                    </a>

                </li>


                <!-- EVENTS -->

                <li class="nav-item mb-1">

                    <a
                        href="venues.php"
                        class="nav-link text-white"
                    >

                        <i class="bi bi-calendar-event"></i>

                        Events

                    </a>

                </li>


                <!-- REPORTS -->

                <li class="nav-item mb-1">

                    <a
                        href="reports.php"
                        class="nav-link text-white"
                    >

                        <i class="bi bi-bar-chart"></i>

                        Reports

                    </a>

                </li>


                <!-- USERS -->

                <li class="nav-item mb-1">

                    <a
                        href="users.php"
                        class="nav-link text-white"
                    >

                        <i class="bi bi-person"></i>

                        Users

                    </a>

                </li>


                <!-- SETTINGS -->

                <li class="nav-item mb-1">

                    <a
                        href="settings.php"
                        class="nav-link text-white"
                    >

                        <i class="bi bi-gear"></i>

                        QC System Settings

                    </a>

                </li>


            </ul>

        </nav>


        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <main class="col-md-10 p-4">


            <!-- PAGE TITLE -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="mb-1">
                        Venue Book List
                    </h2>

                    <p class="text-muted mb-0">
                        View all event venues and their current booking status.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 ERROR MESSAGE
            ================================================== -->

            <?php if (!empty($errorMessage)): ?>

                <div class="alert alert-danger">

                    <i class="bi bi-exclamation-triangle"></i>

                    Unable to load venue records.

                    <br>

                    <small>
                        <?= e($errorMessage); ?>
                    </small>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 DASHBOARD CARDS
            ================================================== -->

            <div class="row mb-4">


                <!-- TOTAL VENUE BOOKINGS -->

                <div class="col-md-4 mb-3">

                    <div class="card text-center shadow-sm h-100">

                        <div class="card-body">

                            <div class="mb-2">

                                <i
                                    class="bi bi-building text-primary"
                                    style="font-size: 2rem;"
                                ></i>

                            </div>

                            <h6 class="card-title text-muted">

                                Total Venue Bookings

                            </h6>

                            <p class="display-6 text-primary mb-0">

                                <?= $totalVenueBookings; ?>

                            </p>

                        </div>

                    </div>

                </div>


                <!-- BOOKED VENUES -->

                <div class="col-md-4 mb-3">

                    <div class="card text-center shadow-sm h-100">

                        <div class="card-body">

                            <div class="mb-2">

                                <i
                                    class="bi bi-calendar-check text-success"
                                    style="font-size: 2rem;"
                                ></i>

                            </div>

                            <h6 class="card-title text-muted">

                                Currently Booked Venues

                            </h6>

                            <p class="display-6 text-success mb-0">

                                <?= $bookedVenues; ?>

                            </p>

                        </div>

                    </div>

                </div>


                <!-- AVAILABLE VENUES -->

                <div class="col-md-4 mb-3">

                    <div class="card text-center shadow-sm h-100">

                        <div class="card-body">

                            <div class="mb-2">

                                <i
                                    class="bi bi-building-check text-warning"
                                    style="font-size: 2rem;"
                                ></i>

                            </div>

                            <h6 class="card-title text-muted">

                                Available Venues

                            </h6>

                            <p class="display-6 text-warning mb-0">

                                <?= $availableVenues; ?>

                            </p>

                        </div>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 VENUE BOOKING TABLE
            ================================================== -->

            <div class="card shadow-sm">

                <div class="card-body">


                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <h5 class="card-title mb-1">

                                Event Venue Bookings

                            </h5>

                            <small class="text-muted">

                                Venue information is based on the events table.

                            </small>

                        </div>

                    </div>


                    <div class="table-responsive">


                        <table
                            id="venueTable"
                            class="table table-striped table-hover"
                        >


                            <thead class="table-primary">

                                <tr>

                                    <th>
                                        Event
                                    </th>

                                    <th>
                                        Venue / Location
                                    </th>

                                    <th>
                                        Capacity
                                    </th>

                                    <th>
                                        Event Date
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Organizer
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (!empty($venueRows)): ?>


                                    <?php foreach ($venueRows as $row): ?>


                                        <tr>


                                            <!-- EVENT -->

                                            <td>

                                                <strong>

                                                    <?= e(
                                                        $row['event_name'] ?? 'Unnamed Event'
                                                    ); ?>

                                                </strong>

                                            </td>


                                            <!-- VENUE / LOCATION -->

                                            <td>

                                                <i class="bi bi-geo-alt text-danger"></i>

                                                <?= e(
                                                    $row['location'] ?? 'No location'
                                                ); ?>

                                            </td>


                                            <!-- CAPACITY -->

                                            <td>

                                                <span class="badge bg-light text-dark border">

                                                    <i class="bi bi-people"></i>

                                                    <?= e(
                                                        $row['capacity'] ?? 'N/A'
                                                    ); ?>

                                                </span>

                                            </td>


                                            <!-- EVENT DATE -->

                                            <td>

                                                <?= formatDateTime(
                                                    $row['event_date'] ?? ''
                                                ); ?>

                                            </td>


                                            <!-- CATEGORY -->

                                            <td>

                                                <?php if (!empty($row['category'])): ?>

                                                    <span class="badge bg-info text-dark">

                                                        <?= e(
                                                            $row['category']
                                                        ); ?>

                                                    </span>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        N/A
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- ORGANIZER -->

                                            <td>

                                                <?php if (!empty($row['organizer_name'])): ?>

                                                    <i class="bi bi-person"></i>

                                                    <?= e(
                                                        $row['organizer_name']
                                                    ); ?>

                                                <?php else: ?>

                                                    <span class="text-muted">

                                                        No organizer

                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- STATUS -->

                                            <td>

                                                <?= statusBadge(
                                                    $row['status'] ?? ''
                                                ); ?>

                                            </td>


                                            <!-- ACTION -->

                                            <td>

                                                <a
                                                    href="../organizer/view_event.php?id=<?= (int) $row['event_id']; ?>"
                                                    class="btn btn-sm btn-primary"
                                                    title="View Event"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <tr>

                                        <td
                                            colspan="8"
                                            class="text-center text-muted py-5"
                                        >

                                            <i
                                                class="bi bi-building"
                                                style="font-size: 2rem;"
                                            ></i>

                                            <div class="mt-2">

                                                No venue bookings found.

                                            </div>

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

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://code.jquery.com/jquery-3.7.0.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>


<script src="../assets/js/admin/venues.js"></script>


</body>

</html>