<?php
session_start();

require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Organizer'){
    header("Location: ../login_user.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $event_name   = $_POST['event_name'];
    $description  = $_POST['description'];
    $event_date   = $_POST['event_date']; 
    $location     = $_POST['location'];
    $capacity     = $_POST['capacity'];
    $category     = $_POST['category'];
    $status       = $_POST['status']; 
    $organizer_id = $_SESSION['user_id']; 

    $sql = "INSERT INTO events (event_name, description, event_date, location, capacity, category, status, organizer_id)
            VALUES ('$event_name', '$description', '$event_date', '$location', '$capacity', '$category', '$status', '$organizer_id')";

    if(mysqli_query($conn, $sql)){
        header("Location: org_dash.php"); 
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
