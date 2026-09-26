<?php
require_once '../includes/session_guard.php';
require_once '../config/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if(empty($q)) {
    echo json_encode([]);
    exit();
}

$search = "%{$q}%";
$stmt = $con->prepare("SELECT * FROM tasks
                       WHERE username = :username
                       AND deleted_at IS NULL
                       AND task LIKE :search
                       ORDER BY is_pinned DESC, priority DESC, sort_order ASC");
$stmt->execute([':username' => $username, ':search' => $search]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($tasks);
exit();