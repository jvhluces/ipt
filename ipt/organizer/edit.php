<?php 

require_once '../config/db.php';

$allowed_categoriies = ['Wedding', 'Party', 'Meeting', 'Seminar', 'Workshop', 'Community Event'];

$event_id = 0;
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
} elseif (isset($_POST['event_id'])){
    $event_id = intval($_POST['event_id']);
}

if ($event_id == 0) {
    die("No Event ID");
}

$sql = "SELECT * FROM events WHERE event_id = $event_id";
$result = mysqli_query($conn, $sql);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    echo "Event not found.";
    exit();
}

$category = $event['category'];

$event_name = $event['event_name'];
$description  = $event['description'];
$event_date = $event['event_date'];
$location = $event['location'];
$status = $event['status'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = $_POST['event_date'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $status = $_POST['status'];

    $safe_category = mysqli_real_escape_string($conn, $category);

    $update_sql = "UPDATE events SET
        event_name='$event_name',
        description='$description',
        category= '$safe_category',
        event_date='$event_date',
        location='$location',
        status='$status'
        WHERE event_id = $event_id";

    if (mysqli_query($conn, $update_sql)) {
        header("Location: org_dash.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Event System</title> 
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/organizer/edit.css">
</head>
<body>

    
    <div class="navbar">
        <a class="navbar-brand fw-bold" href="org_dash.php"><i class="fa-solid fa-calendar-days me-2"></i>Event System</a>
        <a href="index.php" class="btn-back">Back to List</a>
    </div>

    <div class="hero">
        <h1>Edit Event Details</h1>
    </div>

    
    <div class="container">
        <div class="form-card">
            <form method="POST" action="edit.php?id=<?php echo $event_id; ?>" autocomplete="off">

                <div class="form-group">
                    <label for="event_name">Event Name:</label>
                    <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($event['event_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="event_date">Date & Time:</label>
                    <input type="datetime-local" id="event_date" name="event_date" value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>" required>
                </div>

                <div class="form-group">
                    <label for="location">Location:</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($event['location']); ?>" autocomplete="nope" required>
                    
                    <div class="suggestions" id="suggestions"></div>
                </div>

                <div class="form-group">
                    <label for="category">Category:</label>
                    <div class="category-display">

                    <i class="fa-solid fa-tag me-2"></i>

                    <?= htmlspecialchars($category); ?>

                </div>
                </div>

                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="Upcoming" <?php if($event['status']=="Upcoming") echo "selected"; ?>>Upcoming</option>
                        <option value="Ongoing" <?php if($event['status']=="Ongoing") echo "selected"; ?>>Ongoing</option>
                        <option value="Ended" <?php if($event['status']=="Ended") echo "selected"; ?>>Ended</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Update Event</button>
            </form>
        </div>
    </div>


<script src="../assets/js/organizer/edit.js"></script>


</body>
</html>