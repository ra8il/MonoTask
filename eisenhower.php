<?php
session_start();

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

$host = "localhost";
$user = "demouser";
$pass = "r1h2lj3i4";
$db = "todo_list";

try {
    $con = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection Not Established! " . $e->getMessage());
}

// ADD TASK
if(isset($_POST['add-task'])) {
    $task     = trim($_POST['task']);
    $quadrant = $_POST['quadrant'];
    if(!empty($task)) {
        $query = "INSERT INTO eisenhower(task, quadrant, status, username)
                  VALUES(:task, :quadrant, 'pending', :username)";
        $stmt = $con->prepare($query);
        $stmt->execute([
            ':task'     => $task,
            ':quadrant' => $quadrant,
            ':username' => $username
        ]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// SOFT DELETE
if(isset($_POST['delete-task'])) {
    $id = $_POST['task-id'];
    $query = "UPDATE eisenhower SET deleted_at = NOW()
              WHERE id = :id AND username = :username";
    $stmt = $con->prepare($query);
    $stmt->execute([':id' => $id, ':username' => $username]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// RESTORE
if(isset($_POST['restore-task'])) {
    $id       = $_POST['task-id'];
    $quadrant = $_POST['task-quadrant'];
    $query = "UPDATE eisenhower SET deleted_at = NULL
              WHERE id = :id AND username = :username";
    $stmt = $con->prepare($query);
    $stmt->execute([':id' => $id, ':username' => $username]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// PERMANENT DELETE
if(isset($_POST['permanent-delete'])) {
    $id = $_POST['task-id'];
    $query = "DELETE FROM eisenhower WHERE id = :id AND username = :username";
    $stmt = $con->prepare($query);
    $stmt->execute([':id' => $id, ':username' => $username]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// TOGGLE STATUS
if(isset($_POST['toggle-status'])) {
    $id         = $_POST['task-id'];
    $current    = $_POST['current-status'];
    $new_status = ($current === 'pending') ? 'completed' : 'pending';
    $query = "UPDATE eisenhower SET status = :status
              WHERE id = :id AND username = :username";
    $stmt = $con->prepare($query);
    $stmt->execute([
        ':status'   => $new_status,
        ':id'       => $id,
        ':username' => $username
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// FETCH ACTIVE TASKS BY QUADRANT
$quadrants = ['imp_urg', 'imp_nurg', 'nimp_urg', 'nimp_nurg'];
$tasks = [];
foreach($quadrants as $q) {
    $query = "SELECT * FROM eisenhower
              WHERE quadrant = :q AND username = :username AND deleted_at IS NULL
              ORDER BY created_at DESC";
    $stmt = $con->prepare($query);
    $stmt->execute([':q' => $q, ':username' => $username]);
    $tasks[$q] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// FETCH DELETED TASKS
$query = "SELECT * FROM eisenhower
          WHERE username = :username AND deleted_at IS NOT NULL
          ORDER BY deleted_at DESC";
$stmt = $con->prepare($query);
$stmt->execute([':username' => $username]);
$deleted_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$config = [
    'imp_urg' => [
        'label'  => '🔴 Important & Urgent',
        'sub'    => 'Do First',
        'color'  => '#ff4444',
        'light'  => '#fff5f5',
        'border' => '#ffcccc',
    ],
    'imp_nurg' => [
        'label'  => '🔵 Important & Not Urgent',
        'sub'    => 'Schedule',
        'color'  => '#4488ff',
        'light'  => '#f0f5ff',
        'border' => '#c0d4ff',
    ],
    'nimp_urg' => [
        'label'  => '🟡 Not Important & Urgent',
        'sub'    => 'Delegate',
        'color'  => '#ffaa00',
        'light'  => '#fffbf0',
        'border' => '#ffe4a0',
    ],
    'nimp_nurg' => [
        'label'  => '⚫ Not Important & Not Urgent',
        'sub'    => 'Eliminate',
        'color'  => '#888888',
        'light'  => '#f5f5f5',
        'border' => '#dddddd',
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eisenhower Matrix</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: hsl(0, 0%, 15%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        nav {
            width: 100%;
            background: hsl(0, 0%, 10%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .nav-left {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .nav-brand {
            color: white;
            font-size: 18px;
            font-weight: bold;
            margin-right: 20px;
        }

        .nav-link {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-link.active {
            background: #ffaa00;
            color: white;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-welcome {
            color: rgba(255,255,255,0.5);
            font-size: 14px;
        }

        .nav-logout {
            color: white;
            text-decoration: none;
            background: #ff4444;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .nav-logout:hover {
            background: #cc0000;
        }

        .page-header {
            text-align: center;
            padding: 40px 20px 20px;
        }

        h1 {
            color: white;
            font-size: 28px;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .subtitle {
            color: rgba(255,255,255,0.4);
            font-size: 14px;
        }

        .matrix {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            max-width: 1000px;
            width: 100%;
            padding: 20px;
        }

        .quadrant {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            min-height: 320px;
        }

        .quadrant-header {
            padding: 16px 20px;
            color: white;
        }

        .quadrant-header h2 {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .quadrant-header p {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .add-form {
            display: flex;
            padding: 12px 16px;
            gap: 8px;
            border-bottom: 1px solid #eee;
            background: white;
        }

        .add-form input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            outline: none;
        }

        .add-form input:focus {
            border-color: #aaa;
        }

        .add-form button {
            border: none;
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: opacity 0.2s;
            flex-shrink: 0;
        }

        .add-form button:hover {
            opacity: 0.85;
        }

        .task-list {
            list-style: none;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .task-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #eee;
            background: #fafafa;
        }

        .task-row.completed {
            opacity: 0.5;
        }

        .task-row.completed .task-text {
            text-decoration: line-through;
            color: #999;
        }

        .task-text {
            flex: 1;
            font-size: 13px;
            color: #333;
            word-break: break-word;
            min-width: 0;
            line-height: 1.4;
        }

        .task-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .task-actions form {
            display: inline-flex;
            box-shadow: none;
            border-radius: 0;
            overflow: visible;
        }

        .btn-toggle {
            border: none;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .btn-toggle:hover { background: #c8e6c9; }

        .btn-toggle.undo {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .btn-toggle.undo:hover { background: #e1bee7; }

        .btn-delete {
            border: none;
            background: #ffebee;
            color: #c62828;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .btn-delete:hover { background: #ffcdd2; }

        .empty-msg {
            color: #bbb;
            font-size: 13px;
            text-align: center;
            padding: 20px 0;
        }

        /* TRASH SECTION */
        .trash-section {
            max-width: 1000px;
            width: 100%;
            padding: 0 20px 40px;
        }

        .trash-header {
            color: rgba(255,255,255,0.4);
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }

        .trash-header:hover {
            color: rgba(255,255,255,0.6);
        }

        .trash-count {
            background: #ff4444;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .trash-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }

        .trash-item {
            display: flex;
            align-items: center;
            background: hsl(0, 0%, 22%);
            padding: 12px 16px;
            border-radius: 12px;
            gap: 10px;
        }

        .trash-task-name {
            flex: 1;
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            text-decoration: line-through;
            word-break: break-word;
            min-width: 0;
        }

        .trash-quadrant {
            font-size: 11px;
            color: rgba(255,255,255,0.3);
            white-space: nowrap;
        }

        .btn-actions {
            display: flex;
            gap: 6px;
        }

        .btn-restore {
            border: none;
            background: #00bc00;
            color: white;
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .btn-restore:hover { background: #009900; }

        .btn-permanent {
            border: none;
            background: #880000;
            color: white;
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .btn-permanent:hover { background: #660000; }

        .trash-item form {
            display: inline-flex;
            box-shadow: none;
            border-radius: 0;
            overflow: visible;
        }

        @media (max-width: 600px) {
            .matrix { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'includes/nav.php'; ?>

    <div class="page-header">
        <h1>⚡ Eisenhower Matrix</h1>
        <p class="subtitle">Prioritize tasks by urgency and importance</p>
    </div>

    <!-- MATRIX -->
    <div class="matrix">
        <?php foreach($config as $key => $cfg): ?>
        <div class="quadrant">

            <div class="quadrant-header" style="background: <?php echo $cfg['color']; ?>">
                <h2><?php echo $cfg['label']; ?></h2>
                <p><?php echo $cfg['sub']; ?></p>
            </div>

            <form class="add-form" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                <input type="hidden" name="quadrant" value="<?php echo $key; ?>">
                <input type="text" name="task" placeholder="Add task..."
                    style="border-color: <?php echo $cfg['border']; ?>">
                <button type="submit" name="add-task"
                    style="background: <?php echo $cfg['color']; ?>">+</button>
            </form>

            <ul class="task-list" style="background: <?php echo $cfg['light']; ?>">
                <?php if(empty($tasks[$key])): ?>
                    <p class="empty-msg">No tasks here!</p>
                <?php else: ?>
                    <?php foreach($tasks[$key] as $task): ?>
                        <li class="task-row <?php echo $task['status'] === 'completed' ? 'completed' : ''; ?>">
                            <span class="task-text">
                                <?php echo htmlspecialchars($task['task']); ?>
                            </span>
                            <div class="task-actions">
                                <!-- TOGGLE -->
                                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                    <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                                    <input type="hidden" name="current-status" value="<?php echo $task['status']; ?>">
                                    <button type="submit" name="toggle-status"
                                        class="btn-toggle <?php echo $task['status'] === 'completed' ? 'undo' : ''; ?>">
                                        <?php echo $task['status'] === 'completed' ? '↩ Undo' : '✓ Done'; ?>
                                    </button>
                                </form>
                                <!-- SOFT DELETE -->
                                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                    <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" name="delete-task" class="btn-delete">🗑</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

        </div>
        <?php endforeach; ?>
    </div>

    <!-- TRASH -->
    <?php if(!empty($deleted_tasks)): ?>
    <div class="trash-section">
        <div class="trash-header" onclick="toggleTrash()">
            🗑 Trash
            <span class="trash-count"><?php echo count($deleted_tasks); ?></span>
            <span id="trash-arrow">▼</span>
        </div>

        <ul class="trash-list" id="trash-list">
            <?php foreach($deleted_tasks as $task): ?>
                <li class="trash-item">
                    <span class="trash-task-name">
                        <?php echo htmlspecialchars($task['task']); ?>
                    </span>
                    <span class="trash-quadrant">
                        <?php echo str_replace('_', ' ', strtoupper($task['quadrant'])); ?>
                    </span>
                    <div class="btn-actions">
                        <!-- RESTORE -->
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                            <input type="hidden" name="task-quadrant" value="<?php echo $task['quadrant']; ?>">
                            <button type="submit" name="restore-task" class="btn-restore">↩ Restore</button>
                        </form>
                        <!-- PERMANENT DELETE -->
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                            <button type="submit" name="permanent-delete" class="btn-permanent">✕ Remove</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <script>
        let trashOpen = true;
        function toggleTrash() {
            const list  = document.getElementById('trash-list');
            const arrow = document.getElementById('trash-arrow');
            if(trashOpen) {
                list.style.display = 'none';
                arrow.textContent  = '▶';
            } else {
                list.style.display = 'flex';
                arrow.textContent  = '▼';
            }
            trashOpen = !trashOpen;
        }
    </script>

</body>
</html>