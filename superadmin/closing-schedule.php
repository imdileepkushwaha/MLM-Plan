<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/closing.php';
$pageTitle = 'Closing Schedule';

$cfg = closing_schedule_config();
closing_schedule_ensure_token($pdo);
$cfg = closing_schedule_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $before = feature_audit_snapshot($pdo);

    if ($action === 'regen_token') {
        closing_schedule_ensure_token($pdo, true);
        feature_audit_log($pdo, 'closing_schedule_token', 'Regenerated auto-closing cron token', $before, ['closing_auto_cron_token']);
        flash('success', 'Cron token regenerated. Update your cron URL.');
        header('Location: closing-schedule.php');
        exit;
    }

    if ($action === 'run_now') {
        $run = closing_schedule_run_auto($pdo, true);
        flash($run['ok'] ? 'success' : 'error', $run['skipped'] ? $run['message'] : ('Auto closing: ' . $run['message']));
        header('Location: closing-schedule.php');
        exit;
    }

    $enabled = isset($_POST['closing_auto_enabled']) ? '1' : '0';
    $freq = strtolower(trim((string) ($_POST['closing_auto_frequency'] ?? 'daily')));
    if (!in_array($freq, ['daily', 'weekly'], true)) {
        $freq = 'daily';
    }
    $weekday = max(1, min(7, (int) ($_POST['closing_auto_weekday'] ?? 1)));
    $time = trim((string) ($_POST['closing_auto_time'] ?? '00:00'));
    if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        $time = '00:00';
    }
    [$hh, $mm] = closing_schedule_parse_time($time);
    $time = sprintf('%02d:%02d', $hh, $mm);

    $tz = trim((string) ($_POST['closing_auto_timezone'] ?? 'Asia/Kolkata'));
    try {
        new DateTimeZone($tz);
    } catch (Throwable $e) {
        $tz = 'Asia/Kolkata';
    }

    feature_save($pdo, 'closing_auto_enabled', $enabled);
    feature_save($pdo, 'closing_auto_frequency', $freq);
    feature_save($pdo, 'closing_auto_weekday', (string) $weekday);
    feature_save($pdo, 'closing_auto_time', $time);
    feature_save($pdo, 'closing_auto_timezone', $tz);
    clear_setting_cache();

    feature_audit_log($pdo, 'closing_schedule_save', 'Updated auto binary closing schedule', $before, [
        'closing_auto_enabled', 'closing_auto_frequency', 'closing_auto_weekday',
        'closing_auto_time', 'closing_auto_timezone',
    ]);
    flash('success', 'Closing schedule saved.');
    header('Location: closing-schedule.php');
    exit;
}

$cfg = closing_schedule_config();
$token = closing_schedule_ensure_token($pdo);
$cfg = closing_schedule_config();
$next = closing_schedule_next_run($cfg);
$cronUrl = rtrim(APP_URL, '/') . '/cron/auto-closing.php?token=' . urlencode($token);
$weekdays = closing_schedule_weekdays();

require __DIR__ . '/includes/header.php';
?>
<section class="sa-hero">
    <div>
        <span class="sa-hero-kicker">Payout engine</span>
        <h1>Closing Schedule</h1>
        <p>Set when Admin binary closing runs automatically (daily or weekly + time).</p>
    </div>
</section>

<form method="post" class="sa-panel">
    <input type="hidden" name="action" value="save">
    <div class="sa-panel-head">
        <div>
            <h2>Auto binary closing</h2>
            <p>Server cron hits the URL below; when the slot is due, closing runs once.</p>
        </div>
    </div>
    <div class="sa-panel-body">
        <label class="sa-toggle-row">
            <input type="checkbox" name="closing_auto_enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
            Enable auto closing
        </label>

        <div class="sa-form-grid">
            <div class="form-group">
                <label>Frequency</label>
                <select name="closing_auto_frequency" id="closingFreq">
                    <option value="daily" <?= $cfg['frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $cfg['frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                </select>
            </div>
            <div class="form-group" id="weekdayWrap" style="<?= $cfg['frequency'] === 'weekly' ? '' : 'display:none' ?>">
                <label>Week day</label>
                <select name="closing_auto_weekday">
                    <?php foreach ($weekdays as $num => $label): ?>
                        <option value="<?= (int) $num ?>" <?= (int) $cfg['weekday'] === (int) $num ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Time (24h)</label>
                <input type="time" name="closing_auto_time" value="<?= e($cfg['time']) ?>" required>
            </div>
            <div class="form-group">
                <label>Timezone</label>
                <input type="text" name="closing_auto_timezone" value="<?= e($cfg['timezone']) ?>" placeholder="Asia/Kolkata">
                <span class="sa-field-hint">e.g. Asia/Kolkata</span>
            </div>
        </div>

        <p class="sa-field-hint" style="margin-top:0.85rem">
            Current: <strong><?= e(closing_schedule_label($cfg)) ?></strong>
            <?php if ($next): ?>
                · Next run ≈ <strong><?= e($next->format('D, d M Y H:i')) ?></strong>
            <?php endif; ?>
        </p>

        <div class="sa-form-actions">
            <button type="submit" class="btn btn-primary">Save schedule</button>
        </div>
    </div>
</form>

<div class="sa-panel">
    <div class="sa-panel-head">
        <div>
            <h2>Cron URL</h2>
            <p>Call every 5 minutes (or at least once near the scheduled time).</p>
        </div>
    </div>
    <div class="sa-panel-body">
        <div class="form-group span-2">
            <label>HTTP endpoint</label>
            <input type="text" readonly value="<?= e($cronUrl) ?>" onclick="this.select()" style="width:100%;font-family:ui-monospace,monospace;font-size:0.82rem">
            <span class="sa-field-hint">Example crontab: <code>*/5 * * * * curl -s "<?= e($cronUrl) ?>" >/dev/null</code></span>
        </div>
        <div class="sa-form-actions" style="gap:0.6rem;display:flex;flex-wrap:wrap">
            <form method="post" onsubmit="return confirm('Regenerate token? Old cron URLs will stop working.');">
                <input type="hidden" name="action" value="regen_token">
                <button type="submit" class="btn btn-outline">Regenerate token</button>
            </form>
            <form method="post" onsubmit="return confirm('Run binary closing now (force)?');">
                <input type="hidden" name="action" value="run_now">
                <button type="submit" class="btn btn-outline">Run closing now</button>
            </form>
        </div>
        <?php if ($cfg['last_run_at'] !== ''): ?>
            <p class="sa-field-hint" style="margin-top:1rem">
                Last auto run: <strong><?= e($cfg['last_run_at']) ?></strong>
                (slot <?= e($cfg['last_slot'] ?: '—') ?>)
                <?php if ($cfg['last_message'] !== ''): ?>
                    — <?= e($cfg['last_message']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var freq = document.getElementById('closingFreq');
    var wrap = document.getElementById('weekdayWrap');
    if (!freq || !wrap) return;
    freq.addEventListener('change', function () {
        wrap.style.display = freq.value === 'weekly' ? '' : 'none';
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
