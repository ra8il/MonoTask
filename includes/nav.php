<nav>
    <div class="nav-left">
        <span class="nav-brand">📝 MyApp</span>
        <a href="/Mini_Project/todo.php"
           class="nav-link <?php echo ($active === 'todo') ? 'active' : ''; ?>">
           📝 Todo
        </a>
        <a href="/Mini_Project/eisenhower.php"
           class="nav-link <?php echo ($active === 'eisenhower') ? 'active' : ''; ?>">
           ⚡ Eisenhower
        </a>
        <a href="brain_dump.php" 
        class="nav-link <?php echo ($active === 'brain_dump') ? 'active' : ''; ?>">
           🧠 Brain Dump
        </a>
        <a href="/Mini_Project/pomodoro.php"
           class="nav-link <?php echo ($active === 'pomodoro') ? 'active' : ''; ?>">
           🍅 Pomodoro
        </a>
    </div>
    <div class="nav-right">
        <span class="nav-welcome">
            👋 <?php echo htmlspecialchars($username); ?>
        </span>
        <a href="/Mini_Project/logout.php" class="nav-logout">Logout</a>
    </div>
</nav>