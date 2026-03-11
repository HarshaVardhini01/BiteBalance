<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$host = '127.0.0.1';
$user = 'root';
$pass = 'harsha';
$db = 'project';
$conn = new mysqli($host, $user, $pass, $db);
//echo "database connnected";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
