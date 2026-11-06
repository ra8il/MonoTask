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

const MAX_CONTENT_BYTES = 12000000; // MEDIUMTEXT holds ~16 MB

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

function get_note($con, $username, $id) {
    $s = $con->prepare(
        "SELECT id, title, preview, content, updated_at
         FROM quick_notes WHERE id = :id AND username = :u"
    );
    $s->execute([':id' => $id, ':u' => $username]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

// A POST bigger than post_max_size arrives with an empty $_POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    fail('This note is too large to save. Shrink or remove some images.', 413);
}

$readOnly = ['list_notes', 'get_note'];
if (!in_array($action, $readOnly, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Use POST for this action', 405);
}

try {
    switch ($action) {

        case 'list_notes':
            $s = $con->prepare(
                "SELECT id, title, preview, updated_at
                 FROM quick_notes WHERE username = :u
                 ORDER BY updated_at DESC, id DESC"
            );
            $s->execute([':u' => $username]);
            respond($s->fetchAll(PDO::FETCH_ASSOC));

        case 'get_note':
            $note = get_note($con, $username, (int) input('note_id'));
            if (!$note) fail('Note not found', 404);
            respond($note);

        case 'create_note':
            $title = cut(input('title', ''), 150);
            $s = $con->prepare("INSERT INTO quick_notes (username, title, preview, content) VALUES (:u, :t, '', '')");
            $s->execute([':u' => $username, ':t' => $title]);
            respond(get_note($con, $username, (int) $con->lastInsertId()));

        case 'save_note':
            $id      = (int) input('note_id');
            $content = (string) input('content', '');
            if (strlen($content) > MAX_CONTENT_BYTES) fail('This note is too large to save. Shrink or remove some images.', 413);
            if (!get_note($con, $username, $id)) fail('Note not found', 404);
            $s = $con->prepare(
                "UPDATE quick_notes SET title = :t, preview = :p, content = :c
                 WHERE id = :id AND username = :u"
            );
            $s->execute([
                ':t' => cut(input('title', ''), 150),
                ':p' => cut(input('preview', ''), 200),
                ':c' => $content,
                ':id' => $id,
                ':u' => $username,
            ]);
            $row = get_note($con, $username, $id);
            respond(['id' => (int) $row['id'], 'updated_at' => $row['updated_at']]);

        case 'duplicate_note':
            $src = get_note($con, $username, (int) input('note_id'));
            if (!$src) fail('Note not found', 404);
            $title = cut(($src['title'] !== '' ? $src['title'] : 'Untitled note') . ' (copy)', 150);
            $s = $con->prepare(
                "INSERT INTO quick_notes (username, title, preview, content) VALUES (:u, :t, :p, :c)"
            );
            $s->execute([':u' => $username, ':t' => $title, ':p' => $src['preview'], ':c' => $src['content']]);
            respond(get_note($con, $username, (int) $con->lastInsertId()));

        case 'delete_note':
            $s = $con->prepare("DELETE FROM quick_notes WHERE id = :id AND username = :u");
            $s->execute([':id' => (int) input('note_id'), ':u' => $username]);
            if ($s->rowCount() === 0) fail('Note not found', 404);
            respond(['success' => true]);

        default:
            fail('Unknown action');
    }
} catch (PDOException $e) {
    error_log('quick_notes_api: ' . $e->getMessage());
    fail('Database error. Did you run quick_notes_schema.sql, and is the note under your server\'s max_allowed_packet?', 500);
}
