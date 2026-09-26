<li class="task-card
    <?php echo $task['is_pinned']  ? 'pinned'   : ''; ?>
    <?php echo $is_overdue         ? 'overdue'  : ''; ?>
    priority-<?php echo $task['priority'] ?? 'medium'; ?>"
    data-id="<?php echo $task['id']; ?>"
    data-priority="<?php echo $task['priority'] ?? 'medium'; ?>"
    data-due="<?php echo $task['due_date'] ?? ''; ?>"
    style="border-left: 4px solid <?php echo $pc['color']; ?>">

    <!-- TOP ROW -->
    <div class="task-top" onclick="toggleExpand(<?php echo $task['id']; ?>)">

        <span class="drag-handle">⠿</span>

        <span class="task-name-text">
            <?php if($task['is_pinned']): ?>📌 <?php endif; ?>
            <?php echo htmlspecialchars($task['task']); ?>
        </span>

        <div class="task-badges">
            <span class="badge badge-<?php echo $task['priority'] ?? 'medium'; ?>"
                  style="background:<?php echo $pc['bg']; ?>;color:<?php echo $pc['color']; ?>">
                <?php echo ucfirst($task['priority'] ?? 'medium'); ?>
            </span>

            <?php if($task['due_date']): ?>
                <span class="badge <?php echo $is_overdue ? 'badge-overdue' : 'badge-due'; ?>">
                    📅 <?php echo date('d M, h:i A', strtotime($task['due_date'])); ?>
                </span>
            <?php endif; ?>

            <?php if($task['is_recurring']): ?>
                <span class="badge badge-recurring">🔁</span>
            <?php endif; ?>

            <?php if($total_sub > 0): ?>
                <span class="badge badge-progress">
                    <?php echo $done_sub; ?>/<?php echo $total_sub; ?>
                </span>
            <?php endif; ?>
        </div>

        <span class="expand-arrow" id="arrow-<?php echo $task['id']; ?>">▼</span>

    </div>

    <!-- PROGRESS BAR -->
    <?php if($total_sub > 0): ?>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?php echo $progress; ?>%"></div>
            <span class="progress-label"><?php echo $progress; ?>%</span>
        </div>
    <?php endif; ?>

    <!-- EXPANDED -->
    <div class="task-expanded" id="expand-<?php echo $task['id']; ?>" style="display:none">

        <!-- SUBTASKS -->
        <div>
            <p class="subtask-title">Subtasks</p>

            <?php foreach($subtasks as $sub): ?>
                <div class="subtask-row">
                    <form action="modules/subtasks/toggle.php" method="post">
                        <input type="hidden" name="subtask-id" value="<?php echo $sub['id']; ?>">
                        <button type="submit"
                                class="subtask-check <?php echo $sub['is_done'] ? 'done' : ''; ?>">
                            <?php echo $sub['is_done'] ? '✓' : ''; ?>
                        </button>
                    </form>
                    <span class="subtask-text <?php echo $sub['is_done'] ? 'done' : ''; ?>">
                        <?php echo htmlspecialchars($sub['subtask']); ?>
                    </span>
                    <form action="modules/subtasks/delete.php" method="post">
                        <input type="hidden" name="subtask-id" value="<?php echo $sub['id']; ?>">
                        <button type="submit" class="subtask-del">×</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <form action="modules/subtasks/add.php" method="post" class="add-subtask-form">
                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                <input type="text" name="subtask" class="subtask-input" placeholder="Add subtask...">
                <button type="submit" class="subtask-add-btn">+</button>
            </form>
        </div>

        <!-- ACTIONS -->
        <div class="task-actions">

            <form action="modules/tasks/pin.php" method="post">
                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                <button type="submit" class="btn-action btn-pin">
                    <?php echo $task['is_pinned'] ? '📌 Unpin' : '📌 Pin'; ?>
                </button>
            </form>

            <form action="modules/tasks/priority.php" method="post">
                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                <select name="priority" class="priority-select" onchange="this.form.submit()">
                    <?php foreach(['low','medium','high','critical'] as $p): ?>
                        <option value="<?php echo $p; ?>"
                            <?php echo ($task['priority'] === $p) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <form action="modules/tasks/due_date.php" method="post">
                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                <input type="datetime-local" name="due_date" class="due-date-input"
                       value="<?php echo $task['due_date']
                           ? date('Y-m-d\TH:i', strtotime($task['due_date']))
                           : ''; ?>"
                       onchange="this.form.submit()">
            </form>

            <form action="modules/tasks/delete.php" method="post">
                <input type="hidden" name="task-id" value="<?php echo $task['id']; ?>">
                <button type="submit" class="btn-action btn-delete">🗑 Delete</button>
            </form>

        </div>
    </div>
</li>