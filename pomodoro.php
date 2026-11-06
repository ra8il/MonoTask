
<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$active = 'pomodoro';
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
            background: hsl(0, 0%, 15%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* NAVBAR */
        nav {
            width: 100%;
            background: hsl(0, 0%, 10%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,.3);
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
            margin-right: 20px;
        }

        .nav-link {
            color: rgba(255,255,255,.7);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            transition: .2s;
        }

        .nav-link:hover {
            background: rgba(255,255,255,.1);
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
            color: rgba(255,255,255,.6);
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
        }

        .nav-logout:hover {
            background: #cc0000;
        }

        /* PAGE */
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
            padding: 45px 20px;
            width: 100%;
            max-width: 520px;
        }

        /* MODE TABS */
        .mode-tabs {
            display: flex;
            background: hsl(0, 0%, 20%);
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
            width: 100%;
        }

        .mode-btn {
            flex: 1;
            border: none;
            background: transparent;
            color: rgba(255,255,255,.6);
            padding: 12px 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            transition: .2s;
        }

        .mode-btn.active {
            color: white;
        }

        .mode-btn:hover {
            color: white;
        }

        /* CUSTOM COLOR SETTINGS */
        .color-settings {
            width: 100%;
            background: hsl(0, 0%, 20%);
            border: 1px solid hsl(0, 0%, 28%);
            border-radius: 14px;
            padding: 16px 18px;
            color: white;
        }

        .color-settings summary {
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .color-settings summary::-webkit-details-marker {
            display: none;
        }

        .color-settings summary::after {
            content: '+';
            font-size: 20px;
            color: rgba(255,255,255,.6);
        }

        .color-settings[open] summary::after {
            content: '−';
        }

        .settings-description {
            color: rgba(255,255,255,.5);
            font-size: 12px;
            line-height: 1.5;
            margin-top: 8px;
            margin-bottom: 16px;
        }

        .color-options {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .color-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            color: rgba(255,255,255,.85);
        }

        .color-option-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .color-option small {
            color: rgba(255,255,255,.4);
            font-size: 11px;
        }

        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .color-value {
            color: rgba(255,255,255,.6);
            font-size: 12px;
            font-family: monospace;
            text-transform: uppercase;
        }

        input[type="color"] {
            width: 42px;
            height: 34px;
            padding: 3px;
            border: 1px solid hsl(0, 0%, 35%);
            border-radius: 8px;
            background: hsl(0, 0%, 15%);
            cursor: pointer;
        }

        .settings-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .btn-default {
            background: transparent;
            border: 1px solid hsl(0, 0%, 38%);
            color: rgba(255,255,255,.8);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: .2s;
        }

        .btn-default:hover {
            background: hsl(0, 0%, 28%);
            color: white;
        }

        /* TIMER CARD */
        .timer-card {
            background: hsl(0, 0%, 20%);
            border-radius: 24px;
            padding: 40px 45px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 28px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0,0,0,.4);
        }

        /* PROGRESS RING */
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
            stroke-dasharray: 628.32;
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 1s linear, stroke .2s;
        }

        .timer-display {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 100%;
        }

        .timer-time {
            color: white;
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 2px;
            line-height: 1;
        }

        .timer-label {
            color: rgba(255,255,255,.5);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 8px;
        }

        /* SESSION DOTS */
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
            transition: background .3s;
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
            color: rgba(255,255,255,.8);
            width: 46px;
            height: 46px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s;
        }

        .btn-reset:hover {
            background: hsl(0, 0%, 35%);
            color: white;
        }

        .btn-start {
            border: none;
            background: #ff6b6b;
            color: white;
            padding: 15px 42px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 1px;
            transition: background .2s, transform .2s;
            box-shadow: 0 4px 20px rgba(255,107,107,.3);
        }

        .btn-start:hover {
            transform: scale(1.03);
        }

        .btn-start.running {
            background: hsl(0, 0%, 35%);
            box-shadow: none;
        }

        /* STATS */
        .stats {
            display: flex;
            gap: 12px;
            width: 100%;
        }

        .stat-card {
            flex: 1;
            background: hsl(0, 0%, 20%);
            border-radius: 14px;
            padding: 18px 8px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,.25);
        }

        .stat-number {
            color: #ff6b6b;
            font-size: 28px;
            font-weight: bold;
        }

        .stat-label {
            color: rgba(255,255,255,.5);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        .message {
            color: rgba(255,255,255,.55);
            font-size: 14px;
            text-align: center;
            min-height: 20px;
        }

        @media (max-width: 600px) {
            nav {
                padding: 14px 12px;
                flex-wrap: wrap;
                gap: 12px;
            }

            .nav-left {
                flex-wrap: wrap;
                gap: 4px;
            }

            .nav-brand {
                display: none;
            }

            .nav-link {
                padding: 8px;
                font-size: 12px;
            }

            .nav-right {
                gap: 8px;
            }

            .nav-welcome {
                font-size: 12px;
            }

            .container {
                padding: 30px 14px;
                gap: 20px;
            }

            .mode-btn {
                font-size: 11px;
                padding: 11px 4px;
            }

            .timer-card {
                padding: 35px 20px;
            }

            .timer-time {
                font-size: 42px;
            }

            .btn-start {
                padding: 14px 32px;
            }
        }
    </style>
</head>

<body>

<?php include 'includes/nav.php'; ?>

<main class="container">

    <!-- MODES -->
    <div class="mode-tabs">
        <button class="mode-btn active" data-mode="pomodoro"
                onclick="setMode('pomodoro')">Pomodoro</button>
        <button class="mode-btn" data-mode="short"
                onclick="setMode('short')">Short Break</button>
        <button class="mode-btn" data-mode="long"
                onclick="setMode('long')">Long Break</button>
    </div>

    <!-- CUSTOM COLOR EDITOR -->
    <details class="color-settings">
        <summary>🎨 Customize timer colors</summary>

        <p class="settings-description">
            Choose a color for each timer mode. Changes apply immediately
            and are saved in this browser.
        </p>

        <div class="color-options">
            <label class="color-option" for="color-pomodoro">
                <span class="color-option-left">
                    <strong>Pomodoro</strong>
                    <small>Focus sessions</small>
                </span>
                <span class="color-input-wrapper">
                    <span class="color-value" id="value-pomodoro">#FF6B6B</span>
                    <input type="color" id="color-pomodoro"
                           value="#ff6b6b" aria-label="Pomodoro color">
                </span>
            </label>

            <label class="color-option" for="color-short">
                <span class="color-option-left">
                    <strong>Short Break</strong>
                    <small>Short rest sessions</small>
                </span>
                <span class="color-input-wrapper">
                    <span class="color-value" id="value-short">#4488FF</span>
                    <input type="color" id="color-short"
                           value="#4488ff" aria-label="Short break color">
                </span>
            </label>

            <label class="color-option" for="color-long">
                <span class="color-option-left">
                    <strong>Long Break</strong>
                    <small>Long rest sessions</small>
                </span>
                <span class="color-input-wrapper">
                    <span class="color-value" id="value-long">#00BC00</span>
                    <input type="color" id="color-long"
                           value="#00bc00" aria-label="Long break color">
                </span>
            </label>
        </div>

        <div class="settings-actions">
            <button class="btn-default" type="button"
                    onclick="resetColors()">Restore default colors</button>
        </div>
    </details>

    <!-- TIMER -->
    <section class="timer-card">

        <div class="timer-ring-wrapper">
            <svg class="timer-ring" width="220" height="220"
                 viewBox="0 0 220 220">
                <circle class="ring-bg" cx="110" cy="110" r="100"/>
                <circle class="ring-progress" id="ring"
                        cx="110" cy="110" r="100"/>
            </svg>

            <div class="timer-display">
                <div class="timer-time" id="timer">25:00</div>
                <div class="timer-label" id="mode-label">Focus Time</div>
            </div>
        </div>

        <div class="session-count">
            <div class="session-dot" id="dot-1"></div>
            <div class="session-dot" id="dot-2"></div>
            <div class="session-dot" id="dot-3"></div>
            <div class="session-dot" id="dot-4"></div>
        </div>

        <div class="controls">
            <button class="btn-reset" onclick="resetTimer()"
                    title="Reset timer" aria-label="Reset timer">↺</button>
            <button class="btn-start" id="startBtn"
                    onclick="toggleTimer()">START</button>
            <button class="btn-reset" onclick="skipSession()"
                    title="Skip session" aria-label="Skip session">⏭</button>
        </div>

    </section>

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

    <p class="message" id="message">Stay focused! You got this 💪</p>

</main>

<script>
    // DEFAULT COLORS
    const DEFAULT_COLORS = {
        pomodoro: '#ff6b6b',
        short: '#4488ff',
        long: '#00bc00'
    };

    // LOAD SAVED COLORS
    function loadColors() {
        try {
            const saved = JSON.parse(
                localStorage.getItem('pomodoroColors')
            );

            return {
                pomodoro: validColor(saved?.pomodoro)
                    ? saved.pomodoro : DEFAULT_COLORS.pomodoro,
                short: validColor(saved?.short)
                    ? saved.short : DEFAULT_COLORS.short,
                long: validColor(saved?.long)
                    ? saved.long : DEFAULT_COLORS.long
            };
        } catch {
            return { ...DEFAULT_COLORS };
        }
    }

    function validColor(color) {
        return typeof color === 'string' &&
               /^#[0-9a-fA-F]{6}$/.test(color);
    }

    let colors = loadColors();

    // TIMER CONFIGURATION
    const MODES = {
        pomodoro: { minutes: 25, label: 'Focus Time' },
        short:    { minutes: 5,  label: 'Short Break' },
        long:     { minutes: 15, label: 'Long Break' }
    };

    const MESSAGES = {
        pomodoro: [
            'Stay focused! You got this 💪',
            'Deep work mode activated 🧠',
            'One task at a time 🎯',
            'You are doing great! 🔥'
        ],
        short: [
            'Take a breather! 😌',
            'Stretch a little 🧘',
            'Grab some water 💧'
        ],
        long: [
            'Great work! Take a proper break 🎉',
            'You earned this break! 🏆',
            'Rest well, come back stronger 💪'
        ]
    };

    // STATE
    let currentMode = 'pomodoro';
    let totalSeconds = MODES.pomodoro.minutes * 60;
    let remaining = totalSeconds;
    let interval = null;
    let isRunning = false;
    let sessions = 0;
    let breaks = 0;

    const CIRCUMFERENCE = 2 * Math.PI * 100;

    // ELEMENTS
    const timerEl = document.getElementById('timer');
    const ringEl = document.getElementById('ring');
    const labelEl = document.getElementById('mode-label');
    const startBtn = document.getElementById('startBtn');
    const messageEl = document.getElementById('message');

    const statSessions = document.getElementById('stat-sessions');
    const statMinutes = document.getElementById('stat-minutes');
    const statBreaks = document.getElementById('stat-breaks');

    // APPLY CURRENT MODE COLOR
    function applyModeColor() {
        const color = colors[currentMode];

        ringEl.style.stroke = color;
        startBtn.style.backgroundColor = isRunning
            ? '' : color;

        if (!isRunning) {
            startBtn.style.boxShadow =
                `0 4px 20px ${color}55`;
        }

        document.querySelectorAll('.mode-btn').forEach(btn => {
            const mode = btn.dataset.mode;
            btn.classList.toggle('active', mode === currentMode);
            btn.style.backgroundColor =
                mode === currentMode ? colors[mode] : '';
        });

        document.querySelectorAll('.session-dot.done').forEach(dot => {
            dot.style.backgroundColor = colors.pomodoro;
        });
    }

    // COLOR EDITOR
    function updateColor(mode, color) {
        if (!MODES[mode] || !validColor(color)) return;

        colors[mode] = color;

        document.getElementById(`value-${mode}`).textContent =
            color.toUpperCase();

        try {
            localStorage.setItem('pomodoroColors', JSON.stringify(colors));
        } catch (error) {
            console.warn('Could not save timer colors.', error);
        }

        applyModeColor();
    }

    function resetColors() {
        colors = { ...DEFAULT_COLORS };

        Object.keys(colors).forEach(mode => {
            document.getElementById(`color-${mode}`).value = colors[mode];
            document.getElementById(`value-${mode}`).textContent =
                colors[mode].toUpperCase();
        });

        try {
            localStorage.removeItem('pomodoroColors');
        } catch (error) {
            console.warn('Could not reset saved colors.', error);
        }

        applyModeColor();
    }

    // CONNECT COLOR PICKERS
    Object.keys(DEFAULT_COLORS).forEach(mode => {
        const picker = document.getElementById(`color-${mode}`);

        picker.value = colors[mode];
        document.getElementById(`value-${mode}`).textContent =
            colors[mode].toUpperCase();

        picker.addEventListener('input', () => {
            updateColor(mode, picker.value);
        });
    });

    // CHANGE MODE
    function setMode(mode) {
        if (!MODES[mode]) return;

        stopTimer();

        currentMode = mode;
        totalSeconds = MODES[mode].minutes * 60;
        remaining = totalSeconds;

        labelEl.textContent = MODES[mode].label;

        applyModeColor();
        updateDisplay();
        updateRing();
        setMessage(mode);
    }

    // START / PAUSE
    function toggleTimer() {
        if (isRunning) {
            stopTimer();
        } else {
            startTimer();
        }
    }

    function startTimer() {
        if (remaining <= 0) {
            remaining = totalSeconds;
            updateDisplay();
            updateRing();
        }

        isRunning = true;
        startBtn.textContent = 'PAUSE';
        startBtn.classList.add('running');
        startBtn.style.backgroundColor = '';
        startBtn.style.boxShadow = 'none';

        interval = setInterval(() => {
            remaining--;

            updateDisplay();
            updateRing();

            if (remaining <= 0) {
                clearInterval(interval);
                interval = null;
                isRunning = false;

                onSessionEnd();
            }
        }, 1000);
    }

    function stopTimer() {
        clearInterval(interval);
        interval = null;
        isRunning = false;

        startBtn.textContent = 'START';
        startBtn.classList.remove('running');

        applyModeColor();
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

    // SESSION COMPLETED
    function onSessionEnd() {
        const completedMode = currentMode;

        if (completedMode === 'pomodoro') {
            sessions++;
            statSessions.textContent = sessions;
            statMinutes.textContent = sessions * MODES.pomodoro.minutes;

            updateSessionDots();

            const nextMode = sessions % 4 === 0 ? 'long' : 'short';

            setMessage(nextMode);
            notifyUser(
                '⏰ Focus session complete!',
                'Time to take a break.'
            );

            setTimeout(() => setMode(nextMode), 500);
        } else {
            breaks++;
            statBreaks.textContent = breaks;

            setMessage('pomodoro');
            notifyUser(
                '⏰ Break complete!',
                'Ready to focus again?'
            );

            setTimeout(() => setMode('pomodoro'), 500);
        }
    }

    // SESSION DOTS
    function updateSessionDots() {
        const pos = ((sessions - 1) % 4) + 1;
        const dot = document.getElementById(`dot-${pos}`);

        dot.classList.add('done');
        dot.style.backgroundColor = colors.pomodoro;

        if (sessions % 4 === 0) {
            setTimeout(() => {
                document.querySelectorAll('.session-dot').forEach(d => {
                    d.classList.remove('done');
                    d.style.backgroundColor = '';
                });
            }, 1000);
        }
    }

    // TIMER DISPLAY
    function updateDisplay() {
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        timerEl.textContent =
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');
    }

    // PROGRESS RING
    function updateRing() {
        const progress = remaining / totalSeconds;
        const offset = CIRCUMFERENCE * (1 - progress);

        ringEl.style.strokeDasharray = CIRCUMFERENCE;
        ringEl.style.strokeDashoffset = offset;
    }

    // MOTIVATIONAL MESSAGE
    function setMessage(mode) {
        const messages = MESSAGES[mode];

        messageEl.textContent =
            messages[Math.floor(Math.random() * messages.length)];
    }

    // BROWSER NOTIFICATIONS
    function notifyUser(title, body) {
        if ('Notification' in window &&
            Notification.permission === 'granted') {
            new Notification(title, { body });
        }
    }

    if ('Notification' in window &&
        Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }

    // INITIALIZE
    ringEl.style.strokeDasharray = CIRCUMFERENCE;
    updateDisplay();
    updateRing();
    applyModeColor();
</script>

</body>
</html>
