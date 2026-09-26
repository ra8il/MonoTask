<?php
// Shared DB connection. Include this instead of repeating the connection
// block in every page. (login.php / index.php / todo.php can be switched
// over to `require 'db.php';` later — not changed here to keep this diff
// focused on the Brain Dump feature.)

$host = 'localhost';
$user = 'demouser';
$pass = "r1h2lj3i4";
$db   = "demodb";

try {
    $con = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection Not Established! " . $e->getMessage());
}
