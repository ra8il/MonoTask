<?php
session_start();
if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pomodoro | MyApp</title>
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

        /* NAV BAR */
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
            text-decoration: none;
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
            background: #ff6b6b;
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

        /* MAIN CONTENT */
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
            padding: 60px 20px;
            width: 100%;
            max-width: 500px;
        }

        /* MODE TABS */
        .mode-tabs {
            display: flex;
            background: hsl(0, 0%, 20%);
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
        }

        .mode-btn {
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.5);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .mode-btn.active {
            background: #ff6b6b;
            color: white;
        }

        .mode-btn:hover:not(.active) {
            color: white;
            background: rgba(255,255,255,0.1);
        }

        /* TIMER CARD */
        .timer-card {
            background: hsl(0, 0%, 20%);
            border-radius: 24px;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }

        /* CIRCULAR PROGRESS */
        .timer-ring-wrapper {
            position: relative;
            width: 220px;
            height: 220px;
        }

        .timer-ring {
            transform: rotate(-90deg);
        }

        .ring-bg {
            fill: none;
            stroke: hsl(0, 0%, 28%);
            stroke-width: 10;
        }

        .ring-progress {
            fill: none;
            stroke: #ff6b6b;
            stroke-width: 10;
            stroke-linecap: round;
            stroke-dasharray: 628;
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 1s linear, stroke 0.3s;
        }

        .timer-display {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .timer-time {
            color: white;
            font-size: 52px;
            font-weight: bold;
            letter-spacing: 2px;
            line-height: 1;
        }

        .timer-label {
            color: rgba(255,255,255,0.4);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 6px;
        }

        /* SESSION COUNT */
        .session-count {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .session-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: hsl(0, 0%, 35%);
            transition: background 0.3s;
        }

        .session-dot.done {
            background: #ff6b6b;
        }

        /* CONTROLS */
        .controls {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .btn-reset {
            border: none;
            background: hsl(0, 0%, 28%);
            color: rgba(255,255,255,0.7);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-reset:hover {
            background: hsl(0, 0%, 35%);
            color: white;
        }

        .btn-start {
            border: none;
            background: #ff6b6b;
            color: white;
            padding: 16px 48px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(255,107,107,0.4);
        }

        .btn-start:hover {
            background: #ff5252;
            transform: scale(1.03);
            box-shadow: 0 6px 24px rgba(255,107,107,0.5);
        }

        .btn-start.running {
            background: hsl(0, 0%, 35%);
            box-shadow: none;
        }

        .btn-start.running:hover {
            background: hsl(0, 0%, 40%);
            transform: scale(1.03);
        }

        /* STATS */
        .stats {
            display: flex;
            gap: 16px;
            width: 100%;
        }

        .stat-card {
            flex: 1;
            background: hsl(0, 0%, 20%);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        }

        .stat-number {
            color: #ff6b6b;
            font-size: 32px;
            font-weight: bold;
        }

        .stat-label {
            color: rgba(255,255,255,0.4);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        /* MOTIVATIONAL MESSAGE */
        .message {
            color: rgba(255,255,255,0.4);
            font-size: 14px;
            text-align: center;
            min-height: 20px;
            transition: all 0.3s;
        }

        /* RESPONSIVE */
        @media (max-width: 500px) {
            .timer-card {
                padding: 40px 24px;
            }

            .timer-time {
                font-size: 42px;
            }

            nav {
                padding: 14px 16px;
            }

            .nav-brand {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- NAV BAR -->
    <nav>
        <div class="nav-left">
            <span class="nav-brand">📝 MyApp</span>
            <a href="todo.php" class="nav-link">📝 Todo</a>
            <a href="eisenhower.php" class="nav-link">⚡ Eisenhower</a>
            <a href="pomodoro.php" class="nav-link active">🍅 Pomodoro</a>
        </div>
        <div class="nav-right">
            <span class="nav-welcome">
                👋 <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="logout.php" class="nav-logout">Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="container">

        <!-- MODE TABS -->
        <div class="mode-tabs">
            <button class="mode-btn active" onclick="setMode('pomodoro')">Pomodoro</button>
            <button class="mode-btn" onclick="setMode('short')">Short Break</button>
            <button class="mode-btn" onclick="setMode('long')">Long Break</button>
        </div>

        <!-- TIMER CARD -->
        <div class="timer-card">

            <!-- CIRCULAR PROGRESS RING -->
            <div class="timer-ring-wrapper">
                <svg class="timer-ring" width="220" height="220" viewBox="0 0 220 220">
                    <circle class="ring-bg" cx="110" cy="110" r="100"/>
                    <circle class="ring-progress" id="ring" cx="110" cy="110" r="100"/>
                </svg>
                <div class="timer-display">
                    <div class="timer-time" id="timer">25:00</div>
                    <div class="timer-label" id="mode-label">Focus Time</div>
                </div>
            </div>

            <!-- SESSION DOTS -->
            <div class="session-count" id="session-dots">
                <div class="session-dot" id="dot-1"></div>
                <div class="session-dot" id="dot-2"></div>
                <div class="session-dot" id="dot-3"></div>
                <div class="session-dot" id="dot-4"></div>
            </div>

            <!-- CONTROLS -->
            <div class="controls">
                <button class="btn-reset" onclick="resetTimer()" title="Reset">↺</button>
                <button class="btn-start" id="startBtn" onclick="toggleTimer()">START</button>
                <button class="btn-reset" onclick="skipSession()" title="Skip">⏭</button>
            </div>

        </div>

        <!-- STATS -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="stat-sessions">0</div>
                <div class="stat-label">Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-minutes">0</div>
                <div class="stat-label">Minutes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-breaks">0</div>
                <div class="stat-label">Breaks</div>
            </div>
        </div>

        <!-- MESSAGE -->
        <p class="message" id="message">Stay focused! You got this 💪</p>

    </div>

    <script>
        // CONFIG
        const MODES = {
            pomodoro: { minutes: 25, label: 'Focus Time',   color: '#ff6b6b' },
            short:    { minutes: 5,  label: 'Short Break',  color: '#4488ff' },
            long:     { minutes: 15, label: 'Long Break',   color: '#00bc00' },
        };

        const MESSAGES = {
            pomodoro: [
                'Stay focused! You got this 💪',
                'Deep work mode activated 🧠',
                'One task at a time 🎯',
                'You are doing great! 🔥',
            ],
            short: [
                'Take a breather! 😌',
                'Stretch a little 🧘',
                'Grab some water 💧',
            ],
            long: [
                'Great work! Take a proper break 🎉',
                'You earned this break! 🏆',
                'Rest well, come back stronger 💪',
            ]
        };

        // STATE
        let currentMode  = 'pomodoro';
        let totalSeconds = 25 * 60;
        let remaining    = totalSeconds;
        let interval     = null;
        let isRunning    = false;
        let sessions     = 0;
        let breaks       = 0;
        let minutesDone  = 0;
        const CIRCUMFERENCE = 2 * Math.PI * 100; // 628

        // ELEMENTS
        const timerEl    = document.getElementById('timer');
        const ringEl     = document.getElementById('ring');
        const labelEl    = document.getElementById('mode-label');
        const startBtn   = document.getElementById('startBtn');
        const messageEl  = document.getElementById('message');
        const statSessions = document.getElementById('stat-sessions');
        const statMinutes  = document.getElementById('stat-minutes');
        const statBreaks   = document.getElementById('stat-breaks');

        // SET MODE
        function setMode(mode) {
            stopTimer();
            currentMode  = mode;
            totalSeconds = MODES[mode].minutes * 60;
            remaining    = totalSeconds;

            // update ring color
            ringEl.style.stroke = MODES[mode].color;
            startBtn.style.background = MODES[mode].color;
            startBtn.style.boxShadow = `0 4px 20px ${MODES[mode].color}66`;

            // update tabs
            document.querySelectorAll('.mode-btn').forEach((btn, i) => {
                btn.classList.remove('active');
                if(['pomodoro','short','long'][i] === mode) {
                    btn.classList.add('active');
                    btn.style.background = MODES[mode].color;
                } else {
                    btn.style.background = '';
                }
            });

            labelEl.textContent = MODES[mode].label;
            updateDisplay();
            updateRing();
            setMessage(mode);
        }

        // TOGGLE START/PAUSE
        function toggleTimer() {
            if(isRunning) {
                stopTimer();
            } else {
                startTimer();
            }
        }

        function startTimer() {
            isRunning = true;
            startBtn.textContent = 'PAUSE';
            startBtn.classList.add('running');

            interval = setInterval(() => {
                remaining--;
                minutesDone = Math.floor((totalSeconds - remaining) / 60);
                updateDisplay();
                updateRing();

                if(remaining <= 0) {
                    clearInterval(interval);
                    interval  = null;
                    isRunning = false;
                    onSessionEnd();
                }
            }, 1000);
        }

        function stopTimer() {
            clearInterval(interval);
            interval  = null;
            isRunning = false;
            startBtn.textContent = 'START';
            startBtn.classList.remove('running');
        }

        function resetTimer() {
            stopTimer();
            remaining = totalSeconds;
            updateDisplay();
            updateRing();
        }

        function skipSession() {
            stopTimer();
            onSessionEnd();
        }

        // SESSION END
        function onSessionEnd() {
            if(currentMode === 'pomodoro') {
                sessions++;
                statSessions.textContent = sessions;
                statMinutes.textContent  = Math.floor(sessions * 25);
                updateSessionDots();

                // every 4 sessions → long break
                if(sessions % 4 === 0) {
                    setMessage('long');
                    setTimeout(() => setMode('long'), 500);
                } else {
                    setMessage('short');
                    setTimeout(() => setMode('short'), 500);
                }
            } else {
                breaks++;
                statBreaks.textContent = breaks;
                setMessage('pomodoro');
                setTimeout(() => setMode('pomodoro'), 500);
            }

            // browser notification
            if(Notification.permission === 'granted') {
                new Notification('⏰ Timer Done!', {
                    body: currentMode === 'pomodoro'
                        ? 'Focus session complete! Time for a break.'
                        : 'Break over! Back to work.',
                });
            }
        }

        // SESSION DOTS (4 dots = 1 cycle)
        function updateSessionDots() {
            const pos = ((sessions - 1) % 4) + 1;
            document.getElementById(`dot-${pos}`).classList.add('done');
            if(sessions % 4 === 0) {
                // reset dots after full cycle
                setTimeout(() => {
                    document.querySelectorAll('.session-dot')
                        .forEach(d => d.classList.remove('done'));
                }, 1000);
            }
        }

        // UPDATE CLOCK DISPLAY
        function updateDisplay() {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            timerEl.textContent =
                String(m).padStart(2, '0') + ':' +
                String(s).padStart(2, '0');
        }

        // UPDATE RING
        function updateRing() {
            const progress  = remaining / totalSeconds;
            const offset    = CIRCUMFERENCE * (1 - progress);
            ringEl.style.strokeDashoffset = offset;
        }

        // RANDOM MESSAGE
        function setMessage(mode) {
            const msgs = MESSAGES[mode];
            messageEl.textContent = msgs[Math.floor(Math.random() * msgs.length)];
        }

        // REQUEST NOTIFICATIONS
        if(Notification.permission !== 'granted') {
            Notification.requestPermission();
        }

        // INIT
        ringEl.style.strokeDasharray  = CIRCUMFERENCE;
        ringEl.style.strokeDashoffset = 0;
        updateDisplay();
    </script>

</body>
</html>