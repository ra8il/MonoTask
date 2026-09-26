<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];