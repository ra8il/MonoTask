<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require 'db.php';

$username = $_SESSION['username'];
$action   = $_REQUEST['action'] ?? '';

/* ---------------- helpers ---------------- */

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function fail($msg, $code = 400) {
    respond(['error' => $msg], $code);
}

function input($key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function cut($s, $max) {
    $s = trim((string) $s);
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}

function clean_color($v, $fallback) {
    $v = (string) $v;
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $fallback;
}

function clean_side($v) {
    $v = (string) $v;
    return in_array($v, ['top', 'right', 'bottom', 'left'], true) ? $v : null;
}

function clamp_coord($v) {
    return max(-1000000, min(1000000, (int) $v));
}

function owns_pit($con, $username, $pitId) {
    $s = $con->prepare("SELECT id FROM thought_pits WHERE id = :id AND username = :u");
    $s->execute([':id' => $pitId, ':u' => $username]);
    return $s->fetch() !== false;
}

function owns_node($con, $username, $nodeId) {
    $s = $con->prepare(
        "SELECT n.id FROM pit_nodes n
         JOIN thought_pits p ON p.id = n.pit_id
         WHERE n.id = :id AND p.username = :u"
    );
    $s->execute([':id' => $nodeId, ':u' => $username]);
    return $s->fetch() !== false;
}

function fetch_node($con, $id) {
    $s = $con->prepare(
        "SELECT id, heading, content, color, pos_x, pos_y, created_at FROM pit_nodes WHERE id = :id"
    );
    $s->execute([':id' => $id]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

/* Mutations must be POST; only list/get may be GET. */
$readOnly = ['list_pits', 'get_pit'];
if (!in_array($action, $readOnly, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Use POST for this action', 405);
}

/* ---------------- actions ---------------- */

try {
    switch ($action) {

        case 'list_pits':
            $s = $con->prepare(
                "SELECT p.id, p.name, p.accent_color, p.created_at,
                        (SELECT COUNT(*) FROM pit_nodes n WHERE n.pit_id = p.id) AS node_count
                 FROM thought_pits p
                 WHERE p.username = :u
                 ORDER BY p.created_at DESC, p.id DESC"
            );
            $s->execute([':u' => $username]);
            respond($s->fetchAll(PDO::FETCH_ASSOC));

        case 'create_pit':
            $name  = cut(input('name', ''), 100);
            $color = clean_color(input('accent_color', ''), '#00bc00');
            if ($name === '') fail('Give the thought pit a name');
            $s = $con->prepare("INSERT INTO thought_pits (username, name, accent_color) VALUES (:u, :n, :c)");
            $s->execute([':u' => $username, ':n' => $name, ':c' => $color]);
            respond([
                'id' => (int) $con->lastInsertId(),
                'name' => $name,
                'accent_color' => $color,
                'node_count' => 0,
            ]);

        case 'delete_pit':
            $id = (int) input('pit_id');
            $s = $con->prepare("DELETE FROM thought_pits WHERE id = :id AND username = :u");
            $s->execute([':id' => $id, ':u' => $username]);
            if ($s->rowCount() === 0) fail('Thought pit not found', 404);
            respond(['success' => true]);

        case 'get_pit':
            $id = (int) input('pit_id');
            $s = $con->prepare("SELECT id, name, accent_color FROM thought_pits WHERE id = :id AND username = :u");
            $s->execute([':id' => $id, ':u' => $username]);
            $pit = $s->fetch(PDO::FETCH_ASSOC);
            if (!$pit) fail('Thought pit not found', 404);

            $s = $con->prepare(
                "SELECT id, heading, content, color, pos_x, pos_y, created_at
                 FROM pit_nodes WHERE pit_id = :id ORDER BY id ASC"
            );
            $s->execute([':id' => $id]);
            $nodes = $s->fetchAll(PDO::FETCH_ASSOC);

            $s = $con->prepare(
                "SELECT id, from_node_id, from_side, to_node_id, to_side
                 FROM pit_connections WHERE pit_id = :id ORDER BY id ASC"
            );
            $s->execute([':id' => $id]);
            $connections = $s->fetchAll(PDO::FETCH_ASSOC);

            respond(['pit' => $pit, 'nodes' => $nodes, 'connections' => $connections]);

        case 'create_node':
            $pitId   = (int) input('pit_id');
            if (!owns_pit($con, $username, $pitId)) fail('Thought pit not found', 404);
            $heading = cut(input('heading', ''), 120);
            $content = cut(input('content', ''), 1000);
            $color   = clean_color(input('color', ''), '#8a8a8a');
            if ($heading === '') fail('A node needs a heading');
            $s = $con->prepare(
                "INSERT INTO pit_nodes (pit_id, heading, content, color, pos_x, pos_y)
                 VALUES (:p, :h, :c, :col, :x, :y)"
            );
            $s->execute([
                ':p' => $pitId,
                ':h' => $heading,
                ':c' => $content === '' ? null : $content,
                ':col' => $color,
                ':x' => clamp_coord(input('pos_x', 0)),
                ':y' => clamp_coord(input('pos_y', 0)),
            ]);
            respond(fetch_node($con, (int) $con->lastInsertId()));

        case 'update_node':
            $id = (int) input('node_id');
            if (!owns_node($con, $username, $id)) fail('Node not found', 404);
            $heading = cut(input('heading', ''), 120);
            $content = cut(input('content', ''), 1000);
            $color   = clean_color(input('color', ''), '#8a8a8a');
            if ($heading === '') fail('A node needs a heading');
            $s = $con->prepare("UPDATE pit_nodes SET heading = :h, content = :c, color = :col WHERE id = :id");
            $s->execute([
                ':h' => $heading,
                ':c' => $content === '' ? null : $content,
                ':col' => $color,
                ':id' => $id,
            ]);
            respond(fetch_node($con, $id));

        case 'update_positions':
            $list = json_decode((string) input('positions', '[]'), true);
            if (!is_array($list)) fail('Invalid positions');
            $s = $con->prepare(
                "UPDATE pit_nodes n
                 JOIN thought_pits p ON p.id = n.pit_id
                 SET n.pos_x = :x, n.pos_y = :y
                 WHERE n.id = :id AND p.username = :u"
            );
            $con->beginTransaction();
            foreach (array_slice($list, 0, 500) as $item) {
                if (!is_array($item) || !isset($item['id'])) continue;
                $s->execute([
                    ':x' => clamp_coord($item['x'] ?? 0),
                    ':y' => clamp_coord($item['y'] ?? 0),
                    ':id' => (int) $item['id'],
                    ':u' => $username,
                ]);
            }
            $con->commit();
            respond(['success' => true]);

        case 'delete_node':
            $id = (int) input('node_id');
            if (!owns_node($con, $username, $id)) fail('Node not found', 404);
            $s = $con->prepare("DELETE FROM pit_nodes WHERE id = :id");
            $s->execute([':id' => $id]);
            respond(['success' => true]);

        case 'create_connection':
            $pitId = (int) input('pit_id');
            if (!owns_pit($con, $username, $pitId)) fail('Thought pit not found', 404);
            $from  = (int) input('from_node_id');
            $to    = (int) input('to_node_id');
            $fs    = clean_side(input('from_side'));
            $ts    = clean_side(input('to_side'));
            if ($from === $to) fail('A node cannot connect to itself');
            if ($fs === null || $ts === null) fail('Invalid connection side');

            $s = $con->prepare("SELECT COUNT(*) FROM pit_nodes WHERE pit_id = :p AND id IN (:a, :b)");
            $s->execute([':p' => $pitId, ':a' => $from, ':b' => $to]);
            if ((int) $s->fetchColumn() !== 2) fail('Both nodes must be in this thought pit');

            $s = $con->prepare(
                "SELECT id FROM pit_connections
                 WHERE from_node_id = :a AND from_side = :fs AND to_node_id = :b AND to_side = :ts"
            );
            $s->execute([':a' => $from, ':fs' => $fs, ':b' => $to, ':ts' => $ts]);
            if ($s->fetch()) fail('Already connected', 409);

            try {
                $s = $con->prepare(
                    "INSERT INTO pit_connections (pit_id, from_node_id, from_side, to_node_id, to_side)
                     VALUES (:p, :a, :fs, :b, :ts)"
                );
                $s->execute([':p' => $pitId, ':a' => $from, ':fs' => $fs, ':b' => $to, ':ts' => $ts]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') fail('Already connected', 409);
                throw $e;
            }
            respond([
                'id' => (int) $con->lastInsertId(),
                'from_node_id' => $from,
                'from_side' => $fs,
                'to_node_id' => $to,
                'to_side' => $ts,
            ]);

        case 'delete_connection':
            $id = (int) input('connection_id');
            $s = $con->prepare(
                "DELETE c FROM pit_connections c
                 JOIN thought_pits p ON p.id = c.pit_id
                 WHERE c.id = :id AND p.username = :u"
            );
            $s->execute([':id' => $id, ':u' => $username]);
            if ($s->rowCount() === 0) fail('Connection not found', 404);
            respond(['success' => true]);

        case 'reset_connections':
            $pitId = (int) input('pit_id');
            if (!owns_pit($con, $username, $pitId)) fail('Thought pit not found', 404);
            $s = $con->prepare("DELETE FROM pit_connections WHERE pit_id = :p");
            $s->execute([':p' => $pitId]);
            respond(['success' => true, 'removed' => $s->rowCount()]);

        case 'clear_pit':
            $pitId = (int) input('pit_id');
            if (!owns_pit($con, $username, $pitId)) fail('Thought pit not found', 404);
            // connections go with the nodes (ON DELETE CASCADE)
            $s = $con->prepare("DELETE FROM pit_nodes WHERE pit_id = :p");
            $s->execute([':p' => $pitId]);
            respond(['success' => true, 'removed' => $s->rowCount()]);

        default:
            fail('Unknown action');
    }
} catch (PDOException $e) {
    if ($con->inTransaction()) $con->rollBack();
    error_log('brain_dump_api: ' . $e->getMessage());
    fail('Database error. Did you run brain_dump_schema.sql?', 500);
}