<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "saletarget";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $con->connect_error);
}


?>
