<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once '../../config/db.php';

if(isset($_POST['task-id']) && !empty(trim($_POST['subtask'] ?? ''))) {
    $task_id = $_POST['task-id'];
    $subtask = trim($_POST['subtask']);

    $check = $con->prepare("SELECT id FROM tasks WHERE id = :id AND username = :username");
    $check->execute([':id' => $task_id, ':username' => $username]);

    if($check->rowCount() > 0) {
        $stmt = $con->prepare("INSERT INTO subtasks(task_id, username, subtask)
                               VALUES(:task_id, :username, :subtask)");
        $stmt->execute([
            ':task_id'  => $task_id,
            ':username' => $username,
            ':subtask'  => $subtask
        ]);
    }
}

header("Location: /Mini_Project/todo.php");
exit();