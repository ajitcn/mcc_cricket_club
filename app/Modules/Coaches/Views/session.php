<?php

$session = is_array($session ?? null) ? $session : [];
$coach = is_array($coach ?? null) ? $coach : [];
$rosterCount = (int) ($session['roster_snapshot_count'] ?? 0);
$recordedCount = (int) ($session['attendance_recorded_count'] ?? 0);
$attendanceLabel = $rosterCount === 0
    ? 'Not prepared'
    : ($recordedCount >= $rosterCount ? 'Recorded' : 'Partial');
?>
<?= view('staff_header', [
    'title' => 'Coach session details',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>
<main class="container py-4 mcc-coach-detail-page">
    <section class="mcc-card mcc-detail-hero" aria-labelledby="coach-session-heading">
        <div>
            <span class="mcc-eyebrow">Coach session</span>
            <h1 id="coach-session-heading"><?= esc($coach['display_name'] ?? 'Coach') ?></h1>
            <p class="mcc-muted">Session #<?= (int) $session['id'] ?></p>
        </div>
        <div class="mcc-detail-actions" aria-label="Session actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('coaches/' . (int) $coach['id'] . '/sessions') ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span class="mcc-button-label">Back to sessions</span>
            </a>
        </div>
    </section>

    <section class="mcc-card" aria-labelledby="session-information-heading">
        <div class="mcc-card-heading">
            <h2 id="session-information-heading">Session information</h2>
            <span class="mcc-badge mcc-badge-neutral"><?= esc(ucfirst((string) $session['status'])) ?></span>
        </div>
        <dl class="mcc-definition-list">
            <dt>Branch</dt>
            <dd><?= esc($session['branch_name'] ?? 'Authorized branch') ?></dd>
            <dt>Batch</dt>
            <dd><?= esc($session['batch_name'] ?? 'Not assigned') ?></dd>
            <dt>Program</dt>
            <dd><?= esc($session['program_name'] ?? 'Not assigned') ?></dd>
            <dt>Assigned Coach</dt>
            <dd><?= esc($session['coach_name'] ?? $coach['display_name'] ?? 'Not assigned') ?></dd>
            <dt>Starts</dt>
            <dd><?= esc(mcc_user_datetime($session['starts_at'] ?? null)) ?></dd>
            <dt>Ends</dt>
            <dd><?= esc(mcc_user_datetime($session['ends_at'] ?? null)) ?></dd>
            <dt>Ground</dt>
            <dd><?= esc($session['ground_name'] ?? 'Not assigned') ?></dd>
            <dt>Attendance</dt>
            <dd><?= esc($attendanceLabel) ?><?= $rosterCount > 0 ? ' (' . $recordedCount . '/' . $rosterCount . ')' : '' ?></dd>
        </dl>
    </section>

    <section class="mcc-card" aria-labelledby="session-attendance-heading">
        <div class="mcc-card-heading">
            <h2 id="session-attendance-heading">Attendance</h2>
        </div>
        <?php if ($canAttendanceRecord && $session['status'] === 'scheduled'): ?>
            <a class="mcc-button mcc-button-primary" href="<?= site_url('attendance/sessions/' . (int) $session['id']) ?>">Open attendance</a>
        <?php elseif ($canAttendanceView): ?>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('attendance/sessions/' . (int) $session['id']) ?>">View attendance</a>
        <?php else: ?>
            <p class="mcc-empty-state">Attendance actions are unavailable for your permissions.</p>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
