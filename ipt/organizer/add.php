<?php
session_start();

require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Organizer') {
    header("Location: login_user.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$allowed_categories = [
    'Wedding',
    'Party',
    'Meeting',
    'Seminar',
    'Workshop',
    'Community Event'
];

$category = $_GET['category'] ?? '';

if (!in_array($category, $allowed_categories, true)) {
    header("Location: dashboard/org_dash.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $event_name  = mysqli_real_escape_string($conn, $_POST['event_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date  = mysqli_real_escape_string($conn, $_POST['event_date']);
    $location    = mysqli_real_escape_string($conn, $_POST['location']);
    $status      = mysqli_real_escape_string($conn, $_POST['status']);

    $safe_category = mysqli_real_escape_string($conn, $category);

    $sql = "INSERT INTO events
            (
                event_name,
                description,
                category,
                event_date,
                location,
                status,
                organizer_id
            )
            VALUES
            (
                '$event_name',
                '$description',
                '$safe_category',
                '$event_date',
                '$location',
                '$status',
                '$user_id'
            )";

    if (mysqli_query($conn, $sql)) {

        header(
            "Location: category_events.php?category=" .
            urlencode($category)
        );
        exit();

    } else {

        $error = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create <?= htmlspecialchars($category); ?> Event - Event System</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>
<link rel="stylesheet" href="../assets/css/organizer/add.css">

</head>

<body>


<div class="navbar">

    <a
        class="navbar-brand"
        href="dashboard/org_dash.php"
    >

        <i class="fa-solid fa-calendar-days me-2"></i>

        Event System

    </a>


    <a
        href="category_events.php?category=<?= urlencode($category); ?>"
        class="btn-back"
    >

        <i class="fa-solid fa-arrow-left me-1"></i>

        Back to <?= htmlspecialchars($category); ?>

    </a>

</div>


<div class="hero">

    <h1>
        Create <?= htmlspecialchars($category); ?> Event
    </h1>

    <p>
        You are creating an event under the
        <strong><?= htmlspecialchars($category); ?></strong>
        category.
    </p>

</div>


<div class="container">

    <div class="form-card">

        <?php if (isset($error)): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="add.php?category=<?= urlencode($category); ?>"
            autocomplete="off"
        >

            <div class="form-group">

                <label for="event_name">
                    Event Name:
                </label>

                <input
                    type="text"
                    id="event_name"
                    name="event_name"
                    placeholder="Enter event name"
                    required
                >

            </div>



            <div class="form-group">

                <label for="description">
                    Description:
                </label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Enter event description..."
                    rows="4"
                    required
                ></textarea>

            </div>



            <div class="form-group">

                <label for="event_date">
                    Date & Time:
                </label>

                <input
                    type="datetime-local"
                    id="event_date"
                    name="event_date"
                    required
                >

            </div>


            <div class="form-group">

                <label for="location">
                    Location:
                </label>

                <input
                    type="text"
                    id="location"
                    name="location"
                    placeholder="Search location..."
                    autocomplete="off"
                    required
                >

                <div id="suggestions"></div>

            </div>


            <div class="form-group">

                <label>
                    Category:
                </label>

                <div class="category-display">

                    <i class="fa-solid fa-tag me-2"></i>

                    <?= htmlspecialchars($category); ?>

                </div>

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label for="status">
                    Status:
                </label>

                <select
                    id="status"
                    name="status"
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


            <button
                type="submit"
                class="btn-submit"
            >

                <i class="fa-solid fa-plus me-2"></i>

                Create <?= htmlspecialchars($category); ?> Event

            </button>

        </form>

    </div>

</div>


<script src="../assets/js/organizer/add.js"></script>

</body>
</html>