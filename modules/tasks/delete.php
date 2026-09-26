<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once '../../config/db.php';

if(isset($_POST['task-id'])) {
    $stmt = $con->prepare("UPDATE tasks SET deleted_at = NOW()
                           WHERE id = :id AND username = :username");
    $stmt->execute([':id' => $_POST['task-id'], ':username' => $username]);
}

header("Location: /Mini_Project/todo.php");
exit();