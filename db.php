<?php
// Single shared DB connection for the whole app (login, register, todo, brain dump).
// Every page should do `require 'db.php';` instead of its own connection block.
//
// For deployment, set DB_HOST / DB_NAME / DB_USER / DB_PASS as environment
// variables on the server (or edit the fallbacks below). Keep this file out of
// version control if it contains real credentials.

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'todo_list';
$dbUser = getenv('DB_USER') ?: 'demouser';
$dbPass = getenv('DB_PASS') ?: 'r1h2lj3i4';

try {
    $con = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die("Connection Not Established!");
}

unset($dbHost, $dbName, $dbUser, $dbPass);