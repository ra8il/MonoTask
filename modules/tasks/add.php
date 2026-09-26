<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once '../../config/db.php';

if(isset($_POST['add-task'])) {
    $task     = trim($_POST['task']);
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    $is_recurring      = isset($_POST['is_recurring']) ? 1 : 0;
    $recurring_type    = $_POST['recurring_type']    ?? null;
    $recurring_time    = $_POST['recurring_time']    ?? '09:00';
    $day_of_week       = $_POST['day_of_week']       ?? null;
    $day_of_month      = $_POST['day_of_month']      ?? null;
    $custom_days       = $_POST['custom_days']       ?? null;
    $next_due          = null;

    if($is_recurring && $recurring_type) {
        if($recurring_type === 'custom') {
            $custom_days = max(1, min(365, (int)$custom_days));
        }

        $now  = new DateTime();
        $next = new DateTime();
        list($hour, $minute) = explode(':', $recurring_time);
        $next->setTime((int)$hour, (int)$minute, 0);

        switch($recurring_type) {
            case 'daily':
                if($next <= $now) $next->modify('+1 day');
                break;
            case 'weekly':
                $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                $next->modify("next " . $days[$day_of_week]);
                $next->setTime((int)$hour, (int)$minute, 0);
                break;
            case 'monthly':
                $next->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$day_of_month);
                $next->setTime((int)$hour, (int)$minute, 0);
                if($next <= $now) {
                    $next->modify('+1 month');
                    $next->setDate((int)$next->format('Y'), (int)$next->format('m'), (int)$day_of_month);
                    $next->setTime((int)$hour, (int)$minute, 0);
                }
                break;
            case 'custom':
                $next->modify("+{$custom_days} days");
                $next->setTime((int)$hour, (int)$minute, 0);
                break;
        }

        $next_due = $next->format('Y-m-d H:i:s');
    }

    $max = $con->prepare("SELECT MAX(sort_order) as mo FROM tasks WHERE username = :u");
    $max->execute([':u' => $username]);
    $sort_order = ($max->fetch(PDO::FETCH_ASSOC)['mo'] ?? 0) + 1;

    if(!empty($task)) {
        $stmt = $con->prepare("INSERT INTO tasks(task, status, username, priority, due_date,
                               is_recurring, recurring_type, recurring_time,
                               recurring_day_of_week, recurring_day_of_month,
                               next_due, custom_days, sort_order)
                               VALUES(:task, 'pending', :username, :priority, :due_date,
                               :is_recurring, :recurring_type, :recurring_time,
                               :day_of_week, :day_of_month,
                               :next_due, :custom_days, :sort_order)");
        $stmt->execute([
            ':task'           => $task,
            ':username'       => $username,
            ':priority'       => $priority,
            ':due_date'       => $due_date,
            ':is_recurring'   => $is_recurring,
            ':recurring_type' => $is_recurring ? $recurring_type : null,
            ':recurring_time' => $is_recurring ? $recurring_time : null,
            ':day_of_week'    => ($recurring_type === 'weekly')  ? $day_of_week  : null,
            ':day_of_month'   => ($recurring_type === 'monthly') ? $day_of_month : null,
            ':next_due'       => $next_due,
            ':custom_days'    => ($recurring_type === 'custom')  ? $custom_days  : null,
            ':sort_order'     => $sort_order,
        ]);
    }
}

header("Location: /Mini_Project/todo.php");
exit();