<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once '../../config/db.php';

if(isset($_POST['task-id'])) {
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $stmt = $con->prepare("UPDATE tasks SET due_date = :due_date
                           WHERE id = :id AND username = :username");
    $stmt->execute([
        ':due_date' => $due_date,
        ':id'       => $_POST['task-id'],
        ':username' => $username
    ]);
}

header("Location: /Mini_Project/todo.php");
exit();