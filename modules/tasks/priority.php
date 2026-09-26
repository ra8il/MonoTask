<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once '../../config/db.php';

if(isset($_POST['task-id']) && isset($_POST['priority'])) {
    $allowed = ['low', 'medium', 'high', 'critical'];
    if(in_array($_POST['priority'], $allowed)) {
        $stmt = $con->prepare("UPDATE tasks SET priority = :priority
                               WHERE id = :id AND username = :username");
        $stmt->execute([
            ':priority' => $_POST['priority'],
            ':id'       => $_POST['task-id'],
            ':username' => $username
        ]);
    }
}

header("Location: /Mini_Project/todo.php");
exit();