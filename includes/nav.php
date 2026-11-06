<?php
// Shared navigation. Before including, set:  $active = 'todo';  (and $username if you have it)
$active   = $active ?? '';
$username = $username ?? ($_SESSION['username'] ?? '');
?>

<nav>
    <div class="nav-left">
        <span class="nav-brand">
        <img class="mono_task_icon" src="assets\icons\MonoTask Notepad Logo.png" alt="">    
        MonoTask</span>

        <a href="/Mini_Project/todo.php"
           class="nav-link <?php echo ($active === 'todo') ? 'active' : ''; ?>">
           ✅ Todo
        </a>

        <a href="/Mini_Project/eisenhower.php"
           class="nav-link <?php echo ($active === 'eisenhower') ? 'active' : ''; ?>">
           ⚡ Eisenhower
        </a>

        <a href="/Mini_Project/brain_dump.php"
           class="nav-link <?php echo ($active === 'brain_dump') ? 'active' : ''; ?>">
           🧠 Brain Dump
        </a>

        <a href="/Mini_Project/pomodoro.php"
           class="nav-link <?php echo ($active === 'pomodoro') ? 'active' : ''; ?>">
           🍅 Pomodoro
        </a>

        <a href="/Mini_Project/quick_notes.php"
           class="nav-link <?php echo ($active === 'quick_notes') ? 'active' : ''; ?>">
           📝 Quick Notes
        </a>
    </div>

    <div class="nav-right">
        <span class="nav-welcome">
            👋 <?php echo htmlspecialchars($username); ?>
        </span>

        <a href="/Mini_Project/logout.php" id="nav-logout-link" class="nav-logout">Logout</a>
    </div>
</nav>

<!-- ===== Logout confirmation popup ===== -->
<div id="lo-modal" class="lo-modal" aria-hidden="true">
    <div class="lo-box" role="alertdialog" aria-labelledby="lo-title" aria-describedby="lo-msg">
        <h3 id="lo-title">Log out?</h3>
        <p id="lo-msg">Are you sure you want to log out of MonoTask?</p>
        <div class="lo-actions">
            <button type="button" id="lo-cancel" class="lo-btn">Stay logged in</button>
            <button type="button" id="lo-confirm" class="lo-btn lo-danger">Log out</button>
        </div>
    </div>
</div>

<style>
    .mono_task_icon {
        height: 30px;
        width: 30px;
    }
    .nav-brand {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 3px;
        gap: 3px;
    }

    .lo-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(0, 0, 0, 0.62);
    }
    .lo-modal.open { display: flex; animation: lo-fade 0.14s ease; }
    .lo-box {
        width: 100%;
        max-width: 380px;
        padding: 24px;
        box-sizing: border-box;
        background: hsl(0, 0%, 25%);
        border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.55);
        font-family: Arial, sans-serif;
        animation: lo-rise 0.16s ease;
    }
    .lo-box h3 { margin: 0 0 10px; color: white; font-size: 18px; }
    .lo-box p { margin: 0 0 20px; color: rgba(255, 255, 255, 0.7); font-size: 14px; line-height: 1.5; }
    .lo-actions { display: flex; justify-content: flex-end; gap: 10px; }
    .lo-btn {
        font: inherit;
        font-size: 13px;
        font-weight: bold;
        padding: 9px 16px;
        border: none;
        border-radius: 8px;
        color: white;
        background: hsl(0, 0%, 35%);
        cursor: pointer;
        transition: background 0.15s;
    }
    .lo-btn:hover { background: hsl(0, 0%, 42%); }
    .lo-btn:focus-visible { outline: 2px solid #00bc00; outline-offset: 2px; }
    .lo-danger { background: #ff4444; }
    .lo-danger:hover { background: #cc0000; }
    @keyframes lo-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes lo-rise { from { transform: translateY(8px); opacity: 0; } to { transform: none; opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .lo-modal, .lo-box { animation: none; } }

</style>

<script>
(function () {
    var link    = document.getElementById('nav-logout-link');
    var modal   = document.getElementById('lo-modal');
    var cancel  = document.getElementById('lo-cancel');
    var confirm = document.getElementById('lo-confirm');
    if (!link || !modal) return;

    function open() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        cancel.focus();
    }
    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        link.focus();
    }

    // plain click opens the popup; ctrl/cmd/shift/middle-click keep normal link behaviour
    link.addEventListener('click', function (e) {
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        open();
    });

    cancel.addEventListener('click', close);
    confirm.addEventListener('click', function () { window.location.href = link.href; });
    modal.addEventListener('pointerdown', function (e) { if (e.target === modal) close(); });

    // capture phase so page-level Escape handlers (e.g. Quick Notes) don't also react
    document.addEventListener('keydown', function (e) {
        if (!modal.classList.contains('open')) return;
        if (e.key === 'Escape') {
            e.stopImmediatePropagation();
            e.preventDefault();
            close();
        } else if (e.key === 'Tab') {            // keep focus inside the dialog
            e.preventDefault();
            (document.activeElement === cancel ? confirm : cancel).focus();
        }
    }, true);
})();
</script>