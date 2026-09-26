<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require 'db.php';

$username = $_SESSION['username'];
$action   = $_REQUEST['action'] ?? '';

function input($key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function ownBucket($con, $username, $bucketId) {
    $stmt = $con->prepare("SELECT id FROM brain_dump_buckets WHERE id = :id AND username = :u");
    $stmt->execute([':id' => $bucketId, ':u' => $username]);
    return $stmt->fetch() !== false;
}

switch ($action) {

    case 'list_buckets':
        $stmt = $con->prepare(
            "SELECT b.id, b.name, b.accent_color, b.created_at,
                    (SELECT COUNT(*) FROM brain_dump_nodes n WHERE n.bucket_id = b.id) AS node_count
             FROM brain_dump_buckets b
             WHERE b.username = :u
             ORDER BY b.created_at DESC"
        );
        $stmt->execute([':u' => $username]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'create_bucket':
        $name  = trim(input('name', ''));
        $color = input('accent_color', '#00bc00');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name required']);
            break;
        }
        $stmt = $con->prepare("INSERT INTO brain_dump_buckets (username, name, accent_color) VALUES (:u, :n, :c)");
        $stmt->execute([':u' => $username, ':n' => $name, ':c' => $color]);
        echo json_encode(['id' => $con->lastInsertId(), 'name' => $name, 'accent_color' => $color, 'node_count' => 0]);
        break;

    case 'delete_bucket':
        $id = (int) input('bucket_id');
        if (!ownBucket($con, $username, $id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $stmt = $con->prepare("DELETE FROM brain_dump_buckets WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        break;

    case 'get_board':
        $id = (int) input('bucket_id');
        if (!ownBucket($con, $username, $id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $bStmt = $con->prepare("SELECT id, name, accent_color FROM brain_dump_buckets WHERE id = :id");
        $bStmt->execute([':id' => $id]);
        $bucket = $bStmt->fetch(PDO::FETCH_ASSOC);

        $nStmt = $con->prepare(
            "SELECT id, type, content, pos_x, pos_y, created_at
             FROM brain_dump_nodes WHERE bucket_id = :id ORDER BY created_at ASC"
        );
        $nStmt->execute([':id' => $id]);
        $nodes = $nStmt->fetchAll(PDO::FETCH_ASSOC);

        $cStmt = $con->prepare(
            "SELECT id, source_node_id, target_node_id
             FROM brain_dump_connections WHERE bucket_id = :id"
        );
        $cStmt->execute([':id' => $id]);
        $connections = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['bucket' => $bucket, 'nodes' => $nodes, 'connections' => $connections]);
        break;

    case 'create_node':
        $bucketId = (int) input('bucket_id');
        if (!ownBucket($con, $username, $bucketId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $type    = input('type', 'info') === 'heading' ? 'heading' : 'info';
        $content = trim(input('content', ''));
        $x       = (int) input('pos_x', 0);
        $y       = (int) input('pos_y', 0);
        if ($content === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Content required']);
            break;
        }
        $stmt = $con->prepare(
            "INSERT INTO brain_dump_nodes (bucket_id, type, content, pos_x, pos_y) VALUES (:b, :t, :c, :x, :y)"
        );
        $stmt->execute([':b' => $bucketId, ':t' => $type, ':c' => $content, ':x' => $x, ':y' => $y]);
        $id = $con->lastInsertId();
        $stmt2 = $con->prepare("SELECT id, type, content, pos_x, pos_y, created_at FROM brain_dump_nodes WHERE id = :id");
        $stmt2->execute([':id' => $id]);
        echo json_encode($stmt2->fetch(PDO::FETCH_ASSOC));
        break;

    case 'update_node_position':
        $id = (int) input('node_id');
        $x  = (int) input('pos_x');
        $y  = (int) input('pos_y');
        $stmt = $con->prepare("SELECT bucket_id FROM brain_dump_nodes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !ownBucket($con, $username, $row['bucket_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $u = $con->prepare("UPDATE brain_dump_nodes SET pos_x = :x, pos_y = :y WHERE id = :id");
        $u->execute([':x' => $x, ':y' => $y, ':id' => $id]);
        echo json_encode(['success' => true]);
        break;

    case 'update_node_content':
        $id      = (int) input('node_id');
        $content = trim(input('content', ''));
        $stmt = $con->prepare("SELECT bucket_id FROM brain_dump_nodes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !ownBucket($con, $username, $row['bucket_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        if ($content === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Content required']);
            break;
        }
        $u = $con->prepare("UPDATE brain_dump_nodes SET content = :c WHERE id = :id");
        $u->execute([':c' => $content, ':id' => $id]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_node':
        $id = (int) input('node_id');
        $stmt = $con->prepare("SELECT bucket_id FROM brain_dump_nodes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !ownBucket($con, $username, $row['bucket_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $d = $con->prepare("DELETE FROM brain_dump_nodes WHERE id = :id");
        $d->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        break;

    case 'create_connection':
        $bucketId = (int) input('bucket_id');
        $source   = (int) input('source_node_id');
        $target   = (int) input('target_node_id');
        if (!ownBucket($con, $username, $bucketId) || $source === $target) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $check = $con->prepare(
            "SELECT id FROM brain_dump_connections
             WHERE bucket_id = :b AND (
                 (source_node_id = :s  AND target_node_id = :t)
              OR (source_node_id = :t2 AND target_node_id = :s2)
             )"
        );
        $check->execute([':b' => $bucketId, ':s' => $source, ':t' => $target, ':t2' => $target, ':s2' => $source]);
        if ($check->fetch()) {
            echo json_encode(['error' => 'Connection already exists']);
            break;
        }
        $stmt = $con->prepare(
            "INSERT INTO brain_dump_connections (bucket_id, source_node_id, target_node_id) VALUES (:b, :s, :t)"
        );
        $stmt->execute([':b' => $bucketId, ':s' => $source, ':t' => $target]);
        echo json_encode(['id' => $con->lastInsertId(), 'source_node_id' => $source, 'target_node_id' => $target]);
        break;

    case 'delete_connection':
        $id = (int) input('connection_id');
        $stmt = $con->prepare("SELECT bucket_id FROM brain_dump_connections WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !ownBucket($con, $username, $row['bucket_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }
        $d = $con->prepare("DELETE FROM brain_dump_connections WHERE id = :id");
        $d->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
