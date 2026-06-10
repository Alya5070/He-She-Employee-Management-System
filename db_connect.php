<?php
$servername = getenv('MYSQLHOST') ?: "localhost";
$username = getenv('MYSQLUSER') ?: "root";
$password = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : "";
$dbname = getenv('MYSQLDATABASE') ?: "ems";
$port = getenv('MYSQLPORT') ?: "3306";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
