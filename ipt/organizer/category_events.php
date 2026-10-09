
<?php

session_start();

require_once '../config/db.php';


if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: login_user.php");
    exit();
}


$user_id = (int) $_SESSION['user_id'];


function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}



$allowed_categories = [
    'Wedding',
    'Party',
    'Meeting',
    'Seminar',
    'Workshop',
    'Community Event'
];



$category = trim($_GET['category'] ?? '');


if (
    !in_array(
        $category,
        $allowed_categories,
        true
    )
) {
    header("Location: org_dash.php");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $event_id = (int) ($_POST['event_id'] ?? 0);


    if (
        $action === 'delete_event' &&
        $event_id > 0
    ) {

        

        $checkStmt = mysqli_prepare(
            $conn,
            "SELECT
                event_id,
                organizer_id,
                status,
                category
             FROM events
             WHERE event_id = ?
             LIMIT 1"
        );


        if ($checkStmt) {

            mysqli_stmt_bind_param(
                $checkStmt,
                "i",
                $event_id
            );

            mysqli_stmt_execute($checkStmt);

            mysqli_stmt_store_result($checkStmt);


            if (
                mysqli_stmt_num_rows($checkStmt) === 1
            ) {

                mysqli_stmt_bind_result(
                    $checkStmt,
                    $dbEventId,
                    $eventOrganizerId,
                    $eventStatus,
                    $eventCategory
                );

                mysqli_stmt_fetch($checkStmt);


                
                if (
                    (int) $eventOrganizerId !== $user_id
                ) {

                    $_SESSION['delete_error'] =
                        "You are not allowed to delete this event.";

                }



                elseif ($eventStatus !== 'Upcoming') {

                    $_SESSION['delete_error'] =
                        "Only upcoming events can be deleted.";

                }


               

                else {

                    $deleteStmt = mysqli_prepare(
                        $conn,
                        "DELETE FROM events
                         WHERE event_id = ?
                           AND organizer_id = ?
                           AND status = 'Upcoming'
                         LIMIT 1"
                    );


                    if ($deleteStmt) {

                        mysqli_stmt_bind_param(
                            $deleteStmt,
                            "ii",
                            $event_id,
                            $user_id
                        );


                        if (
                            mysqli_stmt_execute(
                                $deleteStmt
                            )
                        ) {

                            if (
                                mysqli_stmt_affected_rows(
                                    $deleteStmt
                                ) === 1
                            ) {

                                $_SESSION['delete_success'] =
                                    "The event was successfully deleted.";

                            } else {

                                $_SESSION['delete_error'] =
                                    "The event could not be deleted.";
                            }

                        } else {

                            $_SESSION['delete_error'] =
                                "Something went wrong while deleting the event.";
                        }


                        mysqli_stmt_close($deleteStmt);

                    } else {

                        $_SESSION['delete_error'] =
                            "Unable to prepare the delete request.";
                    }
                }

            } else {

                $_SESSION['delete_error'] =
                    "The selected event no longer exists.";
            }


            mysqli_stmt_close($checkStmt);

        } else {

            $_SESSION['delete_error'] =
                "Something went wrong while checking the event.";
        }


        

        header(
            "Location: category_events.php?category=" .
            urlencode($category)
        );

        exit();
    }
}



$delete_success = $_SESSION['delete_success'] ?? '';
$delete_error   = $_SESSION['delete_error'] ?? '';

unset($_SESSION['delete_success']);
unset($_SESSION['delete_error']);



$safe_category = mysqli_real_escape_string(
    $conn,
    $category
);


$upcoming = mysqli_query(
    $conn,

    "SELECT
        events.*,
        users.fullname AS organizer_name

     FROM events

     LEFT JOIN users
        ON events.organizer_id = users.user_id

     WHERE events.category = '$safe_category'
       AND events.status = 'Upcoming'

     ORDER BY events.event_date ASC"
);


$ongoing = mysqli_query(
    $conn,

    "SELECT
        events.*,
        users.fullname AS organizer_name

     FROM events

     LEFT JOIN users
        ON events.organizer_id = users.user_id

     WHERE events.category = '$safe_category'
       AND events.status = 'Ongoing'

     ORDER BY events.event_date ASC"
);

$ended = mysqli_query(
    $conn,

    "SELECT
        events.*,
        users.fullname AS organizer_name

     FROM events

     LEFT JOIN users
        ON events.organizer_id = users.user_id

     WHERE events.category = '$safe_category'
       AND events.status = 'Ended'

     ORDER BY events.event_date DESC"
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
        <?= e($category); ?> Events - Event System
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/organizer/category_events.css">

</head>


<body>



<nav class="top-navbar">


    <a
        href="org_dash.php"
        class="back-btn"
    >

        <i class="bi bi-arrow-left"></i>

        Dashboard

    </a>


    <span class="page-title">

        <?= e($category); ?> Events

    </span>


    <div class="create-btn">

        <a
            href="add.php?category=<?= urlencode($category); ?>"
            class="btn btn-primary"
        >

            <i class="bi bi-plus-circle"></i>

            Create <?= e($category); ?> Event

        </a>

    </div>

</nav>



<main class="main-content">


    <?php if ($delete_success !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= e($delete_success); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <?php if ($delete_error !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-circle-fill me-2"></i>

            <?= e($delete_error); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <div class="category-header">


        <div>

            <h1>

                <i class="bi bi-calendar-event"></i>

                <?= e($category); ?> Events

            </h1>


            <p>

                All events under the
                <?= e($category); ?>
                category.

            </p>

        </div>


        <div>

            <a
                href="add.php?category=<?= urlencode($category); ?>"
                class="btn btn-light"
            >

                <i class="bi bi-plus-circle"></i>

                Create Event

            </a>

        </div>

    </div>



    <section class="event-section">


        <div class="section-header">

            <h2 class="text-primary">

                <i class="bi bi-clock"></i>

                Upcoming

            </h2>

        </div>


        <?php if (
            !$upcoming ||
            mysqli_num_rows($upcoming) === 0
        ): ?>

            <div class="empty-state">

                No upcoming
                <?= e($category); ?>
                events.

            </div>

        <?php else: ?>


            <div class="events-wrapper">


                <?php while (
                    $row = mysqli_fetch_assoc($upcoming)
                ): ?>


                    <div class="event-card">


                    

                        <div class="organizer-label">

                            <i class="bi bi-person-circle"></i>

                            Posted by
                            <?= e(
                                $row['organizer_name']
                                ?? 'Unknown'
                            ); ?>

                        </div>


                    
                        <h5>

                            <?= e(
                                $row['event_name']
                            ); ?>

                        </h5>



                        <p>

                            <i class="bi bi-calendar"></i>

                            <?= date(
                                "F j, Y",
                                strtotime(
                                    $row['event_date']
                                )
                            ); ?>

                        </p>



                        <p>

                            <i class="bi bi-clock"></i>

                            <?= date(
                                "g:i A",
                                strtotime(
                                    $row['event_date']
                                )
                            ); ?>

                        </p>



                        <p>

                            <i class="bi bi-geo-alt"></i>

                            <?= e(
                                $row['location']
                            ); ?>

                        </p>


                     

                        <p class="event-description">

                            <?= e(
                                $row['description']
                            ); ?>

                        </p>



                        <span
                            class="badge bg-info status-badge"
                        >

                            Upcoming

                        </span>



                        <?php if (
                            (int) $row['organizer_id']
                            === $user_id
                        ): ?>

                            <div
                                class="card-actions upcoming-actions"
                            >


                                <a
                                    href="edit.php?id=<?= (int) $row['event_id']; ?>"
                                    class="btn btn-outline-primary btn-sm"
                                >

                                    <i class="bi bi-pencil"></i>

                                    Edit

                                </a>



                                <form
                                    method="POST"
                                    action="category_events.php?category=<?= urlencode($category); ?>"
                                    class="d-inline"
                                    onsubmit="return confirmDelete(
                                        '<?= e($row['event_name']); ?>'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete_event"
                                    >


                                    <input
                                        type="hidden"
                                        name="event_id"
                                        value="<?= (int) $row['event_id']; ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                    >

                                        <i class="bi bi-trash"></i>

                                        Delete

                                    </button>

                                </form>


                            </div>

                        <?php endif; ?>


                    </div>


                <?php endwhile; ?>


            </div>

        <?php endif; ?>


    </section>



    <section class="event-section">


        <div class="section-header">

            <h2 class="text-success">

                <i class="bi bi-play-circle"></i>

                Ongoing

            </h2>

        </div>


        <?php if (
            !$ongoing ||
            mysqli_num_rows($ongoing) === 0
        ): ?>

            <div class="empty-state">

                No ongoing
                <?= e($category); ?>
                events.

            </div>

        <?php else: ?>


            <div class="events-wrapper">


                <?php while (
                    $row = mysqli_fetch_assoc($ongoing)
                ): ?>


                    <div class="event-card">


                        <div class="organizer-label">

                            <i class="bi bi-person-circle"></i>

                            Posted by
                            <?= e(
                                $row['organizer_name']
                                ?? 'Unknown'
                            ); ?>

                        </div>


                        <h5>

                            <?= e(
                                $row['event_name']
                            ); ?>

                        </h5>


                        <p>

                            <i class="bi bi-calendar"></i>

                            <?= date(
                                "F j, Y",
                                strtotime(
                                    $row['event_date']
                                )
                            ); ?>

                        </p>


                        <p>

                            <i class="bi bi-clock"></i>

                            <?= date(
                                "g:i A",
                                strtotime(
                                    $row['event_date']
                                )
                            ); ?>

                        </p>


                        <p>

                            <i class="bi bi-geo-alt"></i>

                            <?= e(
                                $row['location']
                            ); ?>

                        </p>


                        <p class="event-description">

                            <?= e(
                                $row['description']
                            ); ?>

                        </p>


                        <span
                            class="badge bg-success status-badge"
                        >

                            Ongoing

                        </span>


                    

                        <?php if (
                            (int) $row['organizer_id']
                            === $user_id
                        ): ?>

                            <div
                                class="card-actions ongoing-actions"
                            >

                                <a
                                    href="edit.php?id=<?= (int) $row['event_id']; ?>"
                                    class="btn btn-outline-primary btn-sm"
                                >

                                    <i class="bi bi-pencil"></i>

                                    Edit Event

                                </a>

                            </div>

                        <?php endif; ?>


                    </div>


                <?php endwhile; ?>


            </div>

        <?php endif; ?>


    </section>


    <section class="event-section">


        <div class="section-header">

            <h2 class="text-secondary">

                <i class="bi bi-stop-circle"></i>

                Ended

            </h2>

        </div>


        <?php if (
            !$ended ||
            mysqli_num_rows($ended) === 0
        ): ?>

            <div class="empty-state">

                No ended
                <?= e($category); ?>
                events.

            </div>

        <?php else: ?>


            <div class="events-wrapper">


                <?php while (
                    $row = mysqli_fetch_assoc($ended)
                ): ?>


                    <div class="event-card">


                        <div class="organizer-label">

                            <i class="bi bi-person-circle"></i>

                            Posted by
                            <?= e(
                                $row['organizer_name']
                                ?? 'Unknown'
                            ); ?>

                        </div>


                        <h5>

                            <?= e(
                                $row['event_name']
                            ); ?>

                        </h5>


                        <p>

                            <i class="bi bi-calendar"></i>

                            <?= date(
                                "F j, Y",
                                strtotime(
                                    $row['event_date']
                                )
                            ); ?>

                        </p>


                        <p>

                            <i class="bi bi-clock"></i>

                            <?= date(
                                "g:i A",
                                strtotime(
                                    $row['event_date']
                                )
                            ); ?>

                        </p>


                        <p>

                            <i class="bi bi-geo-alt"></i>

                            <?= e(
                                $row['location']
                            ); ?>

                        </p>


                        <p class="event-description">

                            <?= e(
                                $row['description']
                            ); ?>

                        </p>


                        <span
                            class="badge bg-secondary status-badge"
                        >

                            Ended

                        </span>


                        <?php if (
                            (int) $row['organizer_id']
                            === $user_id
                        ): ?>

                            <div
                                class="card-actions ended-actions"
                            >

                                <a
                                    href="archive.php?id=<?= (int) $row['event_id']; ?>"
                                    class="btn btn-outline-secondary btn-sm"
                                    onclick="
                                        return confirm(
                                            'Archive this ended event?'
                                        );
                                    "
                                >

                                    <i class="bi bi-archive"></i>

                                    Archive

                                </a>

                            </div>

                        <?php endif; ?>


                    </div>


                <?php endwhile; ?>


            </div>

        <?php endif; ?>


    </section>


</main>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>



<script>

function confirmDelete(eventName)
{
    return confirm(
        "Are you sure you want to permanently delete \"" +
        eventName +
        "\"?\n\n" +
        "This event will be removed from the events table and cannot be recovered."
    );
}

</script>


</body>

</html>
