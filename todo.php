<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: /Mini_Project/login.php");
    exit();
}
$username = $_SESSION['username'];
require_once 'config/db.php';

// AUTO RECURRING CHECK
$now = date('Y-m-d H:i:s');
$due_rec = $con->prepare("SELECT * FROM tasks
                           WHERE username = :u
                           AND is_recurring = 1
                           AND deleted_at IS NULL
                           AND next_due <= :now");
$due_rec->execute([':u' => $username, ':now' => $now]);

foreach($due_rec->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $nx   = new DateTime();
    $time = $t['recurring_time'] ?? '09:00';
    list($h, $m) = explode(':', $time);
    $nx->setTime((int)$h, (int)$m, 0);

    switch($t['recurring_type']) {
        case 'daily':
            $nx->modify('+1 day');
            break;
        case 'weekly':
            $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $nx->modify("next " . $days[$t['recurring_day_of_week']]);
            $nx->setTime((int)$h, (int)$m, 0);
            break;
        case 'monthly':
            $nx->modify('+1 month');
            $nx->setDate((int)$nx->format('Y'), (int)$nx->format('m'), (int)$t['recurring_day_of_month']);
            $nx->setTime((int)$h, (int)$m, 0);
            break;
        case 'custom':
            $nx->modify('+' . $t['custom_days'] . ' days');
            $nx->setTime((int)$h, (int)$m, 0);
            break;
    }

    $upd = $con->prepare("UPDATE tasks SET next_due = :next, status = 'pending' WHERE id = :id");
    $upd->execute([':next' => $nx->format('Y-m-d H:i:s'), ':id' => $t['id']]);
}

// FETCH ACTIVE TASKS
$stmt = $con->prepare("SELECT * FROM tasks
                       WHERE username = :u AND deleted_at IS NULL
                       ORDER BY is_pinned DESC,
                       FIELD(priority,'critical','high','medium','low'),
                       sort_order ASC");
$stmt->execute([':u' => $username]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH ALL SUBTASKS
$sub_stmt = $con->prepare("SELECT * FROM subtasks WHERE username = :u ORDER BY id ASC");
$sub_stmt->execute([':u' => $username]);
$all_subs = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

$subtasks_by_task = [];
foreach($all_subs as $sub) {
    $subtasks_by_task[$sub['task_id']][] = $sub;
}

// FETCH DELETED TASKS
$del_stmt = $con->prepare("SELECT * FROM tasks
                           WHERE username = :u AND deleted_at IS NOT NULL
                           ORDER BY deleted_at DESC");
$del_stmt->execute([':u' => $username]);
$deleted_tasks = $del_stmt->fetchAll(PDO::FETCH_ASSOC);

// SPLIT
$pinned_tasks    = array_filter($tasks, fn($t) => $t['is_pinned'] == 1 && $t['status'] !== 'completed');
$regular_tasks   = array_filter($tasks, fn($t) => $t['is_pinned'] == 0 && $t['status'] !== 'completed');
$completed_tasks = array_filter($tasks, fn($t) => $t['status'] === 'completed');

$active = 'todo';

// PRIORITY CONFIG
$priority_config = [
    'low'      => ['bg' => '#e8f5e9', 'color' => '#2e7d32'],
    'medium'   => ['bg' => '#fff3e0', 'color' => '#e65100'],
    'high'     => ['bg' => '#fff8e1', 'color' => '#f57f17'],
    'critical' => ['bg' => '#ffebee', 'color' => '#c62828'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo List</title>
    <link rel="stylesheet" href="assets/css/todo.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>

<?php include 'includes/nav.php'; ?>

<div class="container">

    <h1>📝 Todo List</h1>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search tasks...">
        <select class="sort-select" onchange="sortTasks(this.value)">
            <option value="default">Sort: Default</option>
            <option value="priority">Sort: Priority</option>
            <option value="due_date">Sort: Due Date</option>
            <option value="az">Sort: A → Z</option>
        </select>
    </div>

    <!-- ADD TASK FORM -->
    <form class="add-form" action="modules/tasks/add.php" method="post">

        <div class="add-form-top">
            <input type="text" name="task" placeholder="Enter new task...">
            <button type="submit" name="add-task">+</button>
        </div>

        <div class="form-row">
            <label>Priority:</label>
            <select name="priority" class="rc-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">🔴 Critical</option>
            </select>
            <label>Due:</label>
            <input type="datetime-local" name="due_date" class="rc-time">
        </div>

        <label class="recurring-toggle">
            <input type="checkbox" name="is_recurring" id="recurringCheck"
                   onchange="toggleRecurring(this)">
            🔁 Recurring task?
        </label>

        <div class="recurring-panel" id="recurringPanel">
            <div class="recurring-row">
                <label>Repeats</label>
                <select name="recurring_type" id="recurringType"
                        class="rc-select" onchange="onTypeChange(this.value)">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="recurring-row">
                <label>At time</label>
                <input type="time" name="recurring_time" id="recurringTime"
                       class="rc-time" value="09:00" onchange="updatePreview()">
            </div>
            <div class="recurring-row row-weekly" id="rowWeekly">
                <label>On day</label>
                <select name="day_of_week" class="rc-select" onchange="updatePreview()">
                    <option value="1">Monday</option>
                    <option value="2">Tuesday</option>
                    <option value="3">Wednesday</option>
                    <option value="4">Thursday</option>
                    <option value="5">Friday</option>
                    <option value="6">Saturday</option>
                    <option value="0">Sunday</option>
                </select>
            </div>
            <div class="recurring-row row-monthly" id="rowMonthly">
                <label>On date</label>
                <select name="day_of_month" class="rc-select" onchange="updatePreview()">
                    <?php for($d = 1; $d <= 28; $d++): ?>
                        <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="recurring-row row-custom" id="rowCustom">
                <label>Every</label>
                <input type="number" name="custom_days" class="rc-input"
                       min="1" max="365" value="2" onchange="updatePreview()">
                <span style="color:rgba(255,255,255,0.6);font-size:13px">days</span>
            </div>
            <div class="next-due-preview" id="nextDuePreview">✅ Calculating...</div>
        </div>

    </form>

    <!-- PINNED -->
    <?php if(!empty($pinned_tasks)): ?>
        <p class="section-header">📌 Pinned</p>
        <ul class="task-list" id="pinnedList">
            <?php foreach($pinned_tasks as $task):
                $subtasks  = $subtasks_by_task[$task['id']] ?? [];
                $total_sub = count($subtasks);
                $done_sub  = count(array_filter($subtasks, fn($s) => $s['is_done']));
                $progress  = $total_sub > 0 ? round(($done_sub / $total_sub) * 100) : 0;
                $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time();
                $pc = $priority_config[$task['priority'] ?? 'medium'];
            ?>
                <?php include 'includes/task_card.php'; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- REGULAR TASKS -->
    <p class="section-header">📋 Tasks</p>
    <ul class="task-list" id="taskList">
        <?php if(empty($regular_tasks)): ?>
            <p class="empty-msg">No tasks yet! Add one above 👆</p>
        <?php else: ?>
            <?php foreach($regular_tasks as $task):
                $subtasks  = $subtasks_by_task[$task['id']] ?? [];
                $total_sub = count($subtasks);
                $done_sub  = count(array_filter($subtasks, fn($s) => $s['is_done']));
                $progress  = $total_sub > 0 ? round(($done_sub / $total_sub) * 100) : 0;
                $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time();
                $pc = $priority_config[$task['priority'] ?? 'medium'];
            ?>
                <?php include 'includes/task_card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>

    <!-- COMPLETED -->
    <?php if(!empty($completed_tasks)): ?>
        <div class="trash-header" onclick="toggleSection('completedList','completedArrow')">
            ✅ Completed
            <span class="trash-count"><?php echo count($completed_tasks); ?></span>
            <span id="completedArrow">▼</span>
        </div>
        <ul class="task-list" id="completedList">
            <?php foreach($completed_tasks as $task):
                $subtasks  = $subtasks_by_task[$task['id']] ?? [];
                $total_sub = count($subtasks);
                $done_sub  = count(array_filter($subtasks, fn($s) => $s['is_done']));
                $progress  = $total_sub > 0 ? round(($done_sub / $total_sub) * 100) : 0;
                $is_overdue = false;
                $pc = $priority_config[$task['priority'] ?? 'medium'];
            ?>
                <?php include 'includes/task_card.php'; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- TRASH -->
    <?php if(!empty($deleted_tasks)): ?>
        <div class="trash-header" onclick="toggleSection('trashList','trashArrow')">
            🗑 Trash
            <span class="trash-count"><?php echo count($deleted_tasks); ?></span>
            <span id="trashArrow">▼</span>
        </div>
        <ul class="task-list" id="trashList">
            <?php foreach($deleted_tasks as $task): ?>
                <li class="task-card deleted">
                    <div class="task-top" style="cursor:default">
                        <span class="task-name-text"
                              style="color:rgba(255,255,255,0.5);text-decoration:line-through">
                            <?php echo htmlspecialchars($task['task']); ?>
                        </span>
                        <div class="btn-actions">
                            <form action="modules/tasks/restore.php" method="post">
                                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="btn-restore">↩ Restore</button>
                            </form>
                            <form action="modules/tasks/permanent_delete.php" method="post">
                                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="btn-permanent">✕ Remove</button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>

<script src="assets/js/todo.js"></script>
</body>
</html>