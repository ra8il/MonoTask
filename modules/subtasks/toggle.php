<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once '../../config/db.php';

if(isset($_POST['subtask-id'])) {
    $stmt = $con->prepare("UPDATE subtasks SET is_done = !is_done
                           WHERE id = :id AND username = :username");
    $stmt->execute([':id' => $_POST['subtask-id'], ':username' => $username]);
}

header("Location: /Mini_Project/todo.php");
exit();