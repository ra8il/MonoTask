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
<body class="bd-page">

<?php include 'includes/nav.php'; ?>

<main id="bd-root">

    <!-- ============ Thought pit list ============ -->
    <section id="bd-pit-view" class="bd-view">
        <div class="bd-pit-inner">
            <div class="bd-pit-header">
                <div>
                    <h1>Thought pits</h1>
                    <p class="bd-sub">One pit per topic or scenario. Open a pit to dump nodes into it and link them together.</p>
                </div>
                <button id="bd-new-pit-btn" class="bd-btn bd-btn-primary" type="button">New thought pit</button>
            </div>
            <div id="bd-pit-grid" class="bd-pit-grid"></div>
        </div>
    </section>

    <!-- ============ Canvas ============ -->
    <section id="bd-canvas-view" class="bd-view bd-canvas-view" hidden>

        <header class="bd-topbar">
            <button id="bd-back-btn" class="bd-btn bd-btn-ghost" type="button">&larr; Thought pits</button>
            <h2 id="bd-pit-title"></h2>

            <form id="bd-add-form" class="bd-add-form" autocomplete="off">
                <input id="bd-add-heading" class="bd-input" type="text" maxlength="120" placeholder="Heading" required>
                <input id="bd-add-content" class="bd-input bd-input-wide" type="text" maxlength="1000" placeholder="Details (optional)">
                <div id="bd-add-swatches" class="bd-swatches" role="group" aria-label="Node color"></div>
                <button id="bd-add-btn" class="bd-btn bd-btn-primary" type="submit">Add node</button>
            </form>
        </header>

        <div id="bd-canvas-viewport" class="bd-viewport">
            <div id="bd-canvas-inner" class="bd-inner">
                <svg id="bd-lines" class="bd-lines" width="1" height="1"></svg>
                <div id="bd-guides"></div>
                <div id="bd-nodes"></div>
            </div>
            <div id="bd-empty" class="bd-empty-hint" hidden>
                <strong>This thought pit is empty.</strong>
                Type a heading above and press Add node. New nodes stack top to bottom; drag them anywhere and they snap to their neighbours.
            </div>
        </div>

        <footer class="bd-statusbar">
            <div class="bd-counts">
                <span><b id="bd-node-count">0</b> <span id="bd-node-label">nodes</span></span>
                <span><b id="bd-conn-count">0</b> <span id="bd-conn-label">connections</span></span>
            </div>

            <div class="bd-zoom" role="group" aria-label="Zoom">
                <button id="bd-zoom-out" class="bd-btn bd-btn-ghost bd-btn-sm" type="button" aria-label="Zoom out">&minus;</button>
                <span id="bd-zoom-label">100%</span>
                <button id="bd-zoom-in" class="bd-btn bd-btn-ghost bd-btn-sm" type="button" aria-label="Zoom in">+</button>
                <button id="bd-fit" class="bd-btn bd-btn-ghost bd-btn-sm" type="button">Fit</button>
            </div>

            <div class="bd-actions">
                <button id="bd-tidy" class="bd-btn bd-btn-sm" type="button">Tidy up</button>
                <button id="bd-reset" class="bd-btn bd-btn-sm" type="button">Reset connections</button>
                <button id="bd-flush" class="bd-btn bd-btn-sm bd-btn-outline-danger" type="button">Delete all</button>
            </div>
        </footer>
    </section>

    <!-- ============ Modals ============ -->
    <div id="bd-modal-pit" class="bd-modal" aria-hidden="true">
        <form id="bd-pit-form" class="bd-panel" autocomplete="off">
            <h3>New thought pit</h3>
            <label class="bd-field">
                <span class="bd-label">Name</span>
                <input id="bd-pit-name" class="bd-input" type="text" maxlength="100" required placeholder="e.g. Trip planning, Startup ideas">
            </label>
            <div class="bd-field">
                <span class="bd-label">Accent color</span>
                <div id="bd-pit-swatches" class="bd-swatches"></div>
            </div>
            <div class="bd-panel-actions">
                <button type="button" class="bd-btn" data-cancel>Cancel</button>
                <button type="submit" class="bd-btn bd-btn-primary">Create thought pit</button>
            </div>
        </form>
    </div>

    <div id="bd-modal-node" class="bd-modal" aria-hidden="true">
        <form id="bd-edit-form" class="bd-panel" autocomplete="off">
            <h3>Edit node</h3>
            <label class="bd-field">
                <span class="bd-label">Heading</span>
                <input id="bd-edit-heading" class="bd-input" type="text" maxlength="120" required>
            </label>
            <label class="bd-field">
                <span class="bd-label">Content</span>
                <textarea id="bd-edit-content" class="bd-input bd-textarea" maxlength="1000" rows="5" placeholder="Shown when you hover the node"></textarea>
            </label>
            <div class="bd-field">
                <span class="bd-label">Border color</span>
                <div id="bd-edit-swatches" class="bd-swatches"></div>
            </div>
            <div class="bd-panel-actions">
                <button id="bd-edit-delete" type="button" class="bd-btn bd-btn-outline-danger bd-push-left">Delete node</button>
                <button type="button" class="bd-btn" data-cancel>Cancel</button>
                <button type="submit" class="bd-btn bd-btn-primary">Save</button>
            </div>
        </form>
    </div>

    <div id="bd-modal-confirm" class="bd-modal" aria-hidden="true">
        <div class="bd-panel" role="alertdialog" aria-labelledby="bd-confirm-title" aria-describedby="bd-confirm-msg">
            <h3 id="bd-confirm-title"></h3>
            <p id="bd-confirm-msg" class="bd-confirm-msg"></p>
            <div class="bd-panel-actions">
                <button id="bd-confirm-cancel" type="button" class="bd-btn" data-cancel>Cancel</button>
                <button id="bd-confirm-ok" type="button" class="bd-btn bd-btn-danger">Confirm</button>
            </div>
        </div>
    </div>

    <div id="bd-toast" class="bd-toast" role="status" aria-live="polite"></div>
</main>

<script src="brain_dump.js"></script>
</body>
</html>