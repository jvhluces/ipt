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

$user_id = (int) $_SESSION['user_id'];

function getSystemSetting($conn, $key, $default = '1')
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

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $key
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return $default;
        }

        $result = mysqli_stmt_get_result($stmt);

        $row = $result
            ? mysqli_fetch_assoc($result)
            : null;

        mysqli_stmt_close($stmt);

        if (
            !$row ||
            !isset($row['setting_value'])
        ) {
            return $default;
        }

        return $row['setting_value'];

    } catch (Throwable $e) {

        return $default;
    }
}

$allowReviews = getSystemSetting(
    $conn,
    'allow_event_reviews',
    '1'
);

if ((string) $allowReviews !== '1') {

    header(
        "Location: ../users/user.php?error=reviews_disabled"
    );

    exit();
}
$event_id = isset($_GET['event_id'])
    ? (int) $_GET['event_id']
    : (
        isset($_POST['event_id'])
            ? (int) $_POST['event_id']
            : 0
    );


if ($event_id <= 0) {

    header(
        "Location: ../users/user.php?error=event_not_found"
    );

    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        event_id,
        event_name,
        status
     FROM events
     WHERE event_id = ?
     LIMIT 1"
);


if (!$stmt) {

    header(
        "Location: ../users/user.php?error=database"
    );

    exit();
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $event_id
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header(
        "Location: ../users/user.php?error=database"
    );

    exit();
}


$result = mysqli_stmt_get_result($stmt);

$event = $result
    ? mysqli_fetch_assoc($result)
    : null;


mysqli_stmt_close($stmt);


if (!$event) {

    header(
        "Location: ../users/user.php?error=event_not_found"
    );

    exit();
}

if ($event['status'] !== 'Ended') {

    header(
        "Location: ../users/user.php?error=review_not_available"
    );

    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT participation_id
     FROM event_participants
     WHERE event_id = ?
     AND user_id = ?
     LIMIT 1"
);


if (!$stmt) {

    header(
        "Location: ../users/user.php?error=database"
    );

    exit();
}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $event_id,
    $user_id
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header(
        "Location: ../users/user.php?error=database"
    );

    exit();
}


$result = mysqli_stmt_get_result($stmt);

$participation = $result
    ? mysqli_fetch_assoc($result)
    : null;


mysqli_stmt_close($stmt);


if (!$participation) {

    header(
        "Location: ../users/user.php?error=not_participant"
    );

    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        review_id,
        rating,
        feedback
     FROM event_reviews
     WHERE event_id = ?
     AND user_id = ?
     LIMIT 1"
);


if (!$stmt) {

    header(
        "Location: ../users/user.php?error=database"
    );

    exit();
}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $event_id,
    $user_id
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header(
        "Location: ../users/user.php?error=database"
    );

    exit();
}


$result = mysqli_stmt_get_result($stmt);

$existingReview = $result
    ? mysqli_fetch_assoc($result)
    : null;


mysqli_stmt_close($stmt);

if ($existingReview) {

    header(
        "Location: ../users/user.php?error=already_reviewed"
    );

    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $rating = isset($_POST['rating'])
        ? (int) $_POST['rating']
        : 0;

    $feedback = trim(
        $_POST['feedback'] ?? ''
    );
    if ($rating < 1 || $rating > 5) {

        header(
            "Location: review_event.php?event_id=" .
            $event_id .
            "&error=invalid_rating"
        );

        exit();
    }



    if (mb_strlen($feedback) > 1000) {

        header(
            "Location: review_event.php?event_id=" .
            $event_id .
            "&error=feedback_too_long"
        );

        exit();
    }



    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO event_reviews
            (
                event_id,
                user_id,
                rating,
                feedback
            )
         VALUES
            (
                ?,
                ?,
                ?,
                ?
            )"
    );


    if (!$stmt) {

        header(
            "Location: review_event.php?event_id=" .
            $event_id .
            "&error=review_failed"
        );

        exit();
    }


    mysqli_stmt_bind_param(
        $stmt,
        "iiis",
        $event_id,
        $user_id,
        $rating,
        $feedback
    );


    if (mysqli_stmt_execute($stmt)) {

        mysqli_stmt_close($stmt);

        header(
            "Location: ../users/user.php?reviewed=1"
        );

        exit();

    }




    $errorCode = mysqli_errno($conn);

    mysqli_stmt_close($stmt);


    if ($errorCode === 1062) {

        header(
            "Location: ../users/user.php?error=already_reviewed"
        );

        exit();
    }


    header(
        "Location: review_event.php?event_id=" .
        $event_id .
        "&error=review_failed"
    );

    exit();
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
        Leave Review -
        <?= htmlspecialchars(
            $event['event_name']
        ); ?>
    </title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">


    <style>



        body {

            background:
                linear-gradient(
                    135deg,
                    #f8fafc 0%,
                    #eef4ff 100%
                );

            min-height: 100vh;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;
        }



        .review-container {

            max-width: 650px;

            margin: 60px auto;

            padding: 0 15px;
        }


  

        .review-card {

            border: 1px solid #e5e7eb;

            border-radius: 20px;

            overflow: hidden;

            background: #ffffff;
        }




        .review-header {

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            color: white;

            padding: 30px 25px;
        }


        .review-header .star-icon {

            width: 62px;

            height: 62px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin: 0 auto 15px;

            border-radius: 50%;

            background: rgba(255,255,255,.16);

            font-size: 27px;
        }


        .review-header h3 {

            font-weight: 700;

            margin-bottom: 7px;
        }


        .review-event-name {

            color: rgba(255,255,255,.88);

            font-size: 14px;

            word-break: break-word;
        }



        .review-card .card-body {

            padding: 30px !important;
        }


        .form-label {

            color: #111827;

            font-size: 14px;
        }


        textarea {

            resize: vertical;

            min-height: 140px;

            border-radius: 12px !important;

            border-color: #dbe1e8 !important;
        }


        textarea:focus {

            border-color: #2563eb !important;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,.10) !important;
        }



        .stars {

            display: flex;

            flex-direction: row-reverse;

            justify-content: center;

            gap: 5px;

            margin-top: 12px;
        }


        .stars input {

            display: none;
        }


        .stars label {

            font-size: 45px;

            line-height: 1;

            color: #d1d5db;

            cursor: pointer;

            transition:
                color .18s ease,
                transform .18s ease;
        }


        .stars label:hover {

            transform: scale(1.12);
        }


        .stars label:hover,
        .stars label:hover ~ label,
        .stars input:checked ~ label {

            color: #fbbf24;
        }


 

        .review-actions {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 12px;
        }


        .review-actions .btn {

            border-radius: 10px;

            padding: 10px 18px;

            font-weight: 600;

            font-size: 14px;
        }


        .review-actions .btn-primary {

            background: #2563eb;

            border-color: #2563eb;
        }


        .review-actions .btn-primary:hover {

            background: #1d4ed8;

            border-color: #1d4ed8;
        }



        .alert {

            border-radius: 12px;

            font-size: 14px;
        }


        @media (max-width: 576px) {

            .review-container {

                margin: 25px auto;
            }


            .review-header {

                padding: 25px 18px;
            }


            .review-card .card-body {

                padding: 22px !important;
            }


            .stars {

                gap: 2px;
            }


            .stars label {

                font-size: 38px;
            }


            .review-actions {

                flex-direction: column;
            }


            .review-actions .btn {

                width: 100%;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="review-container">


        <div class="card review-card shadow-sm">

            <div class="review-header text-center">


                <div class="star-icon">

                    <i class="bi bi-star-fill"></i>

                </div>


                <h3>

                    Leave a Review

                </h3>


                <p class="mb-0 review-event-name">

                    <?= htmlspecialchars(
                        $event['event_name']
                    ); ?>

                </p>


            </div>


            <div class="card-body">

                <?php if (isset($_GET['error'])): ?>

                    <div class="alert alert-danger">

                        <?php

                        $error = $_GET['error'];


                        if (
                            $error ===
                            'invalid_rating'
                        ) {

                            echo
                                "Please select a rating from 1 to 5 stars.";

                        } elseif (
                            $error ===
                            'review_failed'
                        ) {

                            echo
                                "Unable to submit your review. Please try again.";

                        } elseif (
                            $error ===
                            'feedback_too_long'
                        ) {

                            echo
                                "Your feedback must not exceed 1000 characters.";

                        } else {

                            echo
                                "Something went wrong.";

                        }

                        ?>

                    </div>

                <?php endif; ?>

                <form
                    method="POST"
                    action="review_event.php?event_id=<?= $event_id; ?>"
                >


                    <input
                        type="hidden"
                        name="event_id"
                        value="<?= $event_id; ?>"
                    >


                    <!-- ==================================================
                         RATING
                    =================================================== -->

                    <div class="mb-4 text-center">


                        <label
                            class="form-label fw-bold d-block"
                        >

                            How would you rate this event?

                        </label>


                        <div class="stars">


                            <input
                                type="radio"
                                id="star5"
                                name="rating"
                                value="5"
                            >

                            <label
                                for="star5"
                                title="5 stars"
                            >
                                ★
                            </label>


                            <input
                                type="radio"
                                id="star4"
                                name="rating"
                                value="4"
                            >

                            <label
                                for="star4"
                                title="4 stars"
                            >
                                ★
                            </label>


                            <input
                                type="radio"
                                id="star3"
                                name="rating"
                                value="3"
                            >

                            <label
                                for="star3"
                                title="3 stars"
                            >
                                ★
                            </label>


                            <input
                                type="radio"
                                id="star2"
                                name="rating"
                                value="2"
                            >

                            <label
                                for="star2"
                                title="2 stars"
                            >
                                ★
                            </label>


                            <input
                                type="radio"
                                id="star1"
                                name="rating"
                                value="1"
                            >

                            <label
                                for="star1"
                                title="1 star"
                            >
                                ★
                            </label>


                        </div>

                    </div>

                    <div class="mb-4">


                        <label
                            class="form-label fw-bold"
                            for="feedback"
                        >

                            Your Feedback

                        </label>


                        <textarea
                            id="feedback"
                            name="feedback"
                            class="form-control"
                            placeholder="Tell us about your experience..."
                            maxlength="1000"
                        ></textarea>


                        <div class="form-text">

                            Maximum 1000 characters.

                        </div>


                    </div>

                    <div class="review-actions">


                        <a
                            href="../users/user.php"
                            class="btn btn-outline-secondary"
                        >

                            <i class="bi bi-arrow-left"></i>

                            Back

                        </a>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-send"></i>

                            Submit Review

                        </button>


                    </div>


                </form>


            </div>

        </div>

    </div>

</div>


</body>

</html>