<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brain Dump | TODO LIST</title>
    <link rel="stylesheet" href="todo_style.css">
    <link rel="stylesheet" href="brain_dump_style.css">
</head>
<body>

<nav>
    <div class="nav-left">
        <span class="nav-brand">TODO LIST</span>
        <a href="todo.php" class="nav-link">Tasks</a>
        <a href="brain_dump.php" class="nav-link active">Brain Dump</a>
    </div>
    <div class="nav-right">
        <span class="nav-welcome">Hi, <?php echo htmlspecialchars($username); ?></span>
        <a href="logout.php" class="nav-logout">Logout</a>
    </div>
</nav>

<div id="bd-root">

    <section id="bd-bucket-view" class="bd-view">
        <div class="bd-bucket-header">
            <h1>Brain Dump</h1>
            <button id="bd-new-bucket-btn" class="bd-btn-primary">+ New bucket</button>
        </div>
        <div id="bd-bucket-grid" class="bd-bucket-grid"></div>
    </section>

    <section id="bd-canvas-view" class="bd-view" style="display:none;">
        <div class="bd-canvas-topbar">
            <button id="bd-back-btn" class="bd-btn-ghost">&larr; Buckets</button>
            <h2 id="bd-canvas-title"></h2>
            <div class="bd-add-bar">
                <button id="bd-type-toggle" class="bd-type-toggle" data-type="info">Note</button>
                <input type="text" id="bd-add-input" placeholder="Dump a keyword or thought…" maxlength="120">
                <button id="bd-add-btn" class="bd-add-btn">+</button>
            </div>
        </div>
        <div id="bd-canvas-viewport" class="bd-canvas-viewport">
            <div id="bd-canvas-inner" class="bd-canvas-inner">
                <svg id="bd-lines" class="bd-lines"></svg>
                <div id="bd-bands"></div>
                <div id="bd-nodes"></div>
            </div>
        </div>
        <p class="bd-hint">Drag the green dot on a node onto another node to connect them · double-click text to rename · scroll to pan · pinch/wheel to zoom</p>
    </section>

</div>

<script src="brain_dump.js"></script>
</body>
</html>
