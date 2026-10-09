<?php

session_start();

require_once __DIR__ . '/../config/db.php';


/* ==========================================================
   AUDIENCE ACCESS
========================================================== */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];


/* ==========================================================
   EVENT ID
========================================================== */

$event_id = isset($_GET['event_id'])
    ? (int) $_GET['event_id']
    : 0;

if ($event_id <= 0) {
    header("Location: user.php?error=event_not_found");
    exit();
}


/* ==========================================================
   GET EVENT DETAILS
========================================================== */

$query = mysqli_prepare(
    $conn,
    "SELECT
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
        u.email AS organizer_email,
        u.organization_name,

        (
            SELECT COUNT(*)
            FROM event_participants ep
            WHERE ep.event_id = e.event_id
        ) AS participant_count,

        CASE
            WHEN EXISTS (
                SELECT 1
                FROM event_participants ep2
                WHERE ep2.event_id = e.event_id
                AND ep2.user_id = ?
            )
            THEN 1
            ELSE 0
        END AS is_joined

    FROM events e

    LEFT JOIN users u
        ON e.organizer_id = u.user_id

    WHERE e.event_id = ?

    LIMIT 1"
);


if (!$query) {
    header("Location: user.php?error=database");
    exit();
}


mysqli_stmt_bind_param(
    $query,
    "ii",
    $user_id,
    $event_id
);


if (!mysqli_stmt_execute($query)) {

    mysqli_stmt_close($query);

    header("Location: user.php?error=database");
    exit();
}


$result = mysqli_stmt_get_result($query);


if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    mysqli_stmt_close($query);

    header("Location: user.php?error=event_not_found");
    exit();
}


$event = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);


/* ==========================================================
   EVENT VALUES
========================================================== */

$eventName = $event['event_name'] ?? 'Untitled Event';

$eventDescription =
    $event['description'] ?? '';

$eventDate =
    $event['event_date'] ?? '';

$eventLocation =
    $event['location'] ?? 'Not specified';

$eventCategory =
    $event['category'] ?? 'Other';

$eventStatus =
    $event['status'] ?? 'Upcoming';

$organizerName =
    $event['organizer_name'] ?? 'Unknown Organizer';

$organizerEmail =
    $event['organizer_email'] ?? 'Not available';

$organizationName =
    $event['organization_name'] ?? 'Not available';


/* ==========================================================
   PARTICIPANTS
========================================================== */

$participantCount =
    (int) ($event['participant_count'] ?? 0);

$capacity =
    (int) ($event['capacity'] ?? 0);

$isJoined =
    (int) ($event['is_joined'] ?? 0) === 1;


/*
 * capacity = 0 means unlimited
 */

$isFull =
    $capacity > 0 &&
    $participantCount >= $capacity;


/* ==========================================================
   DATE / TIME
========================================================== */

$formattedDate = 'Not available';
$formattedTime = 'Not available';

if (!empty($eventDate)) {

    $timestamp = strtotime($eventDate);

    if ($timestamp !== false) {

        $formattedDate =
            date("F j, Y", $timestamp);

        $formattedTime =
            date("g:i A", $timestamp);
    }
}


/* ==========================================================
   STATUS CLASS
========================================================== */

$statusClass = 'status-ended';

if ($eventStatus === 'Upcoming') {

    $statusClass = 'status-upcoming';

} elseif ($eventStatus === 'Ongoing') {

    $statusClass = 'status-ongoing';

}


/* ==========================================================
   CATEGORY CLASS
========================================================== */

$categoryClass = 'category';

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
    View Event -
    <?= htmlspecialchars($eventName); ?>
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
>


<link rel="stylesheet" href="../assets/css/users/view_event.css">

</head>


<body>



<nav class="navbar-custom">

    <h4>

        <i class="bi bi-calendar-event"></i>

        EventMS

    </h4>


    <a
        href="user.php"
        class="btn btn-outline-light btn-sm"
    >

        <i class="bi bi-arrow-left"></i>

        Back to Events

    </a>

</nav>



<div class="container-custom">

    <div class="details-card">


        <div class="event-title">

            <?= htmlspecialchars($eventName); ?>

        </div>


        <div class="mb-3">

            <span class="<?= $categoryClass; ?>">

                <?= htmlspecialchars($eventCategory); ?>

            </span>


            <span
                class="badge <?= $statusClass; ?>"
            >

                <?= htmlspecialchars($eventStatus); ?>

            </span>

        </div>



        <div class="info-grid">


            <!-- DATE -->

            <div class="info-box">

                <div class="info-label">

                    <i class="bi bi-calendar"></i>

                    Date

                </div>

                <div class="info-value">

                    <?= htmlspecialchars($formattedDate); ?>

                </div>

            </div>



            <!-- TIME -->

            <div class="info-box">

                <div class="info-label">

                    <i class="bi bi-clock"></i>

                    Time

                </div>

                <div class="info-value">

                    <?= htmlspecialchars($formattedTime); ?>

                </div>

            </div>



            <!-- LOCATION -->

            <div class="info-box">

                <div class="info-label">

                    <i class="bi bi-geo-alt"></i>

                    Location

                </div>

                <div class="info-value">

                    <?= htmlspecialchars($eventLocation); ?>

                </div>

            </div>



            <!-- PARTICIPANTS -->

            <div class="info-box">

                <div class="info-label">

                    <i class="bi bi-people"></i>

                    Participants

                </div>

                <div class="info-value">

                    <?= $participantCount; ?>

                    <?php if ($capacity > 0): ?>

                        / <?= $capacity; ?>

                    <?php else: ?>

                        participants

                    <?php endif; ?>

                </div>

            </div>



            <!-- ORGANIZER -->

            <div class="info-box">

                <div class="info-label">

                    <i class="bi bi-person"></i>

                    Organizer

                </div>

                <div class="info-value">

                    <?= htmlspecialchars($organizerName); ?>

                </div>

            </div>



            <!-- ORGANIZATION -->

            <div class="info-box">

                <div class="info-label">

                    <i class="bi bi-building"></i>

                    Organization

                </div>

                <div class="info-value">

                    <?= htmlspecialchars($organizationName); ?>

                </div>

            </div>


        </div>



        <!-- ==================================================
             DESCRIPTION
        =================================================== -->

        <div class="description-section">

            <h5>

                <i class="bi bi-card-text"></i>

                Description

            </h5>


            <div class="description">

                <?php if (trim($eventDescription) !== ''): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $eventDescription
                        )
                    ); ?>

                <?php else: ?>

                    <span class="text-muted">

                        No description available.

                    </span>

                <?php endif; ?>

            </div>

        </div>



        <!-- ==================================================
             ORGANIZER INFORMATION
        =================================================== -->

        <div class="organizer-section">

            <h5>

                <i class="bi bi-person-badge"></i>

                Organizer Information

            </h5>


            <div class="organizer-card">

                <h5>

                    <?= htmlspecialchars($organizerName); ?>

                </h5>


                <p class="mb-0">

                    <i class="bi bi-envelope"></i>

                    <?= htmlspecialchars($organizerEmail); ?>

                </p>

            </div>

        </div>



        <!-- ==================================================
             ACTIONS
        =================================================== -->

        <div class="actions">


            <!-- BACK -->

            <a
                href="user.php"
                class="btn btn-secondary"
            >

                <i class="bi bi-arrow-left"></i>

                Back

            </a>



            <!-- ==================================================
                 UPCOMING EVENT
            =================================================== -->

            <?php if ($eventStatus === 'Upcoming'): ?>


                <!-- ============================================
                     ALREADY JOINED
                ============================================= -->

                <?php if ($isJoined): ?>


                    <form
                        method="POST"
                        action="../actions/event_action.php"
                        onsubmit="
                            return confirm(
                                'Are you sure you want to leave this event?'
                            );
                        "
                    >

                        <input
                            type="hidden"
                            name="event_id"
                            value="<?= $event_id; ?>"
                        >


                        <button
                            type="submit"
                            name="leave"
                            value="1"
                            class="btn btn-outline-danger"
                        >

                            <i class="bi bi-box-arrow-left"></i>

                            Leave Event

                        </button>

                    </form>


                <?php elseif ($isFull): ?>


                    <button
                        type="button"
                        class="btn btn-secondary"
                        disabled
                    >

                        <i class="bi bi-people"></i>

                        Event Full

                    </button>


                <?php else: ?>


                    <form
                        method="POST"
                        action="../actions/event_action.php"
                        onsubmit="
                            return confirm(
                                'Do you want to join this event?'
                            );
                        "
                    >

                        <input
                            type="hidden"
                            name="event_id"
                            value="<?= $event_id; ?>"
                        >


                        <button
                            type="submit"
                            name="join"
                            value="1"
                            class="btn btn-success"
                        >

                            <i class="bi bi-person-plus"></i>

                            Join Event

                        </button>

                    </form>


                <?php endif; ?>


            <?php endif; ?>



            <!-- ==================================================
                 ONGOING EVENT
            =================================================== -->

            <?php if ($eventStatus === 'Ongoing'): ?>


                <span class="ongoing-notice">

                    <i class="bi bi-broadcast"></i>

                    Happening Now

                </span>


                <?php if ($isJoined): ?>

                    <span class="joined-notice">

                        <i class="bi bi-check-circle"></i>

                        You joined this event

                    </span>

                <?php endif; ?>


            <?php endif; ?>



            <!-- ==================================================
                 ENDED EVENT
            =================================================== -->

            <?php if ($eventStatus === 'Ended'): ?>


                <?php if ($isJoined): ?>

                    <a
                        href="../actions/review_event.php?event_id=<?= $event_id; ?>"
                        class="btn btn-warning"
                    >

                        <i class="bi bi-star"></i>

                        Leave Review

                    </a>

                <?php endif; ?>


            <?php endif; ?>


        </div>


    </div>

</div>


</body>

</html>