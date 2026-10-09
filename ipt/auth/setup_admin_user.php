<?php
include 'db.php';

$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "Admin";

$sql = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
if(mysqli_query($conn, $sql)){
    echo "Admin user created!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
