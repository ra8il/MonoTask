<?php
session_start();
if(!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}
$username = $_SESSION['username'];
require_once '../config/db.php';

header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$order = $data['order'] ?? [];

foreach($order as $position => $task_id) {
    $stmt = $con->prepare("UPDATE tasks SET sort_order = :pos
                           WHERE id = :id AND username = :username");
    $stmt->execute([
        ':pos'      => $position,
        ':id'       => (int)$task_id,
        ':username' => $username
    ]);
}

echo json_encode(['success' => true]);
exit();