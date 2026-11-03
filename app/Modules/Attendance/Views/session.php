<?php
$attendanceStatusMeta = [
    'PRESENT' => [
        'label' => 'Present',
        'class' => 'mcc-attendance-status-present',
        'icon' => 'fa-circle-check',
    ],
    'ABSENT' => [
        'label' => 'Absent',
        'class' => 'mcc-attendance-status-absent',
        'icon' => 'fa-circle-xmark',
    ],
    'LATE' => [
        'label' => 'Late',
        'class' => 'mcc-attendance-status-late',
        'icon' => 'fa-clock',
    ],
    'LEAVE' => [
        'label' => 'Excused',
        'class' => 'mcc-attendance-status-excused',
        'icon' => 'fa-person-walking-luggage',
    ],
    'MEDICAL' => [
        'label' => 'Medical',
        'class' => 'mcc-attendance-status-excused',
        'icon' => 'fa-notes-medical',
    ],
    'RAIN_CANCELLED' => [
        'label' => 'Rain cancelled',
        'class' => 'mcc-attendance-status-cancelled',
        'icon' => 'fa-cloud-showers-heavy',
    ],
    'COACH_CANCELLED' => [
        'label' => 'Coach cancelled',
        'class' => 'mcc-attendance-status-cancelled',
        'icon' => 'fa-ban',
    ],
];
$sessionStatus = strtolower((string) $session['status']);
$sessionStatusMeta = $sessionStatus === 'cancelled'
    ? [
        'label' => 'Cancelled',
        'class' => 'mcc-attendance-status-cancelled',
        'icon' => 'fa-lock',
    ]
    : [
        'label' => 'Scheduled',
        'class' => 'mcc-attendance-status-ready',
        'icon' => 'fa-calendar-check',
    ];
$sessionId = filter_var($session['id'] ?? null, FILTER_VALIDATE_INT);
$sessionId = is_int($sessionId) && $sessionId > 0 ? $sessionId : null;
$recordedPlayers = array_filter(
    $players,
    static fn (array $player): bool => $player['attendance_id'] !== null
);
$attendanceAlreadyRecorded = $players !== []
    && count($recordedPlayers) === count($players);
$feedbackByPlayer = [];
foreach (($session_feedback ?? []) as $feedbackRow) {
    $feedbackByPlayer[(int) ($feedbackRow['player_id'] ?? 0)] = $feedbackRow;
}
?>
<?= view('staff_header', ['title' => 'Attendance', 'nav' => $nav, 'error' => $error, 'message' => $message]) ?>
<div class="mcc-scheduling-detail mcc-attendance-session-page">
<nav class="breadcrumb" aria-label="Breadcrumb">
<?php if ($sessionId !== null): ?>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('sessions/' . $sessionId . '/page') ?>">&larr; Back to session</a>
<?php else: ?>
<span class="mcc-muted" role="status">The related Session Details link is unavailable.</span>
<?php endif ?>
</nav>
<section class="mcc-card mcc-session-detail-hero" aria-labelledby="session-heading">
<div class="mcc-detail-hero">
<div>
<span class="mcc-eyebrow">Attendance</span>
<h2 id="session-heading">Session #<?= (int) $session['id'] ?></h2>
<p class="mcc-muted">
<?= esc(mcc_user_datetime($session['starts_at_local'] ?? null)) ?>
<span aria-hidden="true">&ndash;</span>
<?= esc(mcc_user_datetime($session['ends_at_local'] ?? null)) ?>
</p>
</div>
<div class="mcc-page-actions attendance-session-header-actions">
<span class="mcc-badge mcc-status-badge <?= esc($sessionStatusMeta['class']) ?>">
<i class="fa-solid <?= esc($sessionStatusMeta['icon']) ?>" aria-hidden="true"></i>
<?= esc($sessionStatusMeta['label']) ?>
</span>
</div>
</div>
</section>
<section class="mcc-card mcc-session-detail-card" aria-labelledby="session-information-heading">
<div class="mcc-card-heading"><div><span class="mcc-eyebrow">Session information</span><h2 id="session-information-heading">Session details</h2></div></div>
<dl class="mcc-session-information">
<div><dt>Branch</dt><dd><?= esc((string) ($session['branch_name'] ?? 'Branch unavailable')) ?></dd></div>
<div><dt>Batch</dt><dd><?= esc((string) ($session['batch_name'] ?? 'Not assigned')) ?></dd></div>
<div><dt>Coach</dt><dd><?= esc((string) ($session['coach_name'] ?? 'Coach unavailable')) ?></dd></div>
<div><dt>Eligible Players</dt><dd><?= count($players) ?> Player<?= count($players) === 1 ? '' : 's' ?></dd></div>
</dl>
<?php if ($session['status'] !== 'scheduled'): ?>
<p class="mcc-alert mcc-alert-warning attendance-readonly-alert" role="alert"><i class="fa-solid fa-lock" aria-hidden="true"></i> Attendance is read-only for this session.</p>
<?php endif ?>
</section>
<?php
$summaryStatuses = ['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'];
$summaryCounts = array_fill_keys($summaryStatuses, 0);
foreach ($players as $summaryPlayer) {
    $summaryStatus = strtoupper((string) ($summaryPlayer['attendance_status'] ?? ''));
    if (array_key_exists($summaryStatus, $summaryCounts)) {
        $summaryCounts[$summaryStatus]++;
    }
}
$summaryClasses = [
    'PRESENT' => 'is-present',
    'ABSENT' => 'is-absent',
    'LEAVE' => 'is-leave',
    'MEDICAL' => 'is-medical',
    'LATE' => 'is-late',
    'RAIN_CANCELLED' => 'is-cancelled',
    'COACH_CANCELLED' => 'is-cancelled',
];
?>
<section class="mcc-card mcc-session-detail-card" aria-labelledby="attendance-summary-heading">
<div class="mcc-card-heading"><div><span class="mcc-eyebrow">Session overview</span><h2 id="attendance-summary-heading">Attendance summary</h2></div></div>
<div class="mcc-attendance-summary-grid">
<?php foreach ($summaryStatuses as $summaryStatus): ?>
<div class="mcc-attendance-summary-item <?= esc($summaryClasses[$summaryStatus]) ?>">
<span><?= esc(ucwords(strtolower(str_replace('_', ' ', $summaryStatus)))) ?></span>
<strong><?= (int) $summaryCounts[$summaryStatus] ?></strong>
</div>
<?php endforeach ?>
</div>
<div class="mcc-attendance-summary-meta" aria-label="Attendance totals">
<span><strong><?= count($players) ?></strong> eligible Players</span>
<span><strong><?= array_sum($summaryCounts) ?></strong> recorded</span>
</div>
</section>
<?php if ($attendanceAlreadyRecorded): ?>
<p class="mcc-alert mcc-alert-success" role="status">
<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
Attendance already recorded for every selected Player.
</p>
<?php endif ?>
<?php if (! empty($error)): ?>
<p class="mcc-alert mcc-alert-danger" role="alert">
<?= esc($error) ?>
</p>
<?php endif ?>
<?php if (! empty($message)): ?>
<p class="mcc-alert mcc-alert-success" role="status">
<?= esc($message) ?>
</p>
<?php endif ?>
<section class="mcc-card mcc-session-detail-card" aria-labelledby="players-heading">
<div class="mcc-card-heading">
    <div>
        <span class="mcc-eyebrow">Attendance</span>
        <h2 id="players-heading">Player attendance</h2>
    </div>
</div>
<div class="mcc-table-wrap mcc-attendance-session-table-wrap">
<table class="mcc-table mcc-attendance-session-table">
<caption class="visually-hidden">Player attendance and remarks</caption>
<thead>
<tr>
<th scope="col">Player</th>
<th scope="col">Status</th>
<th scope="col">Remark</th>
</tr>
</thead>
<tbody>
<?php foreach ($players as $player): ?>
<tr>
<th scope="row">
<?= esc($player['full_name']) ?>
<span class="mcc-badge mcc-badge-neutral">Player #<?= (int) $player['player_id'] ?></span>
</th>
<td class="attendance-status-cell" data-label="Status">
<?php
$currentStatus = strtoupper((string) ($player['attendance_status'] ?? ''));
$statusOptions = ['PRESENT', 'ABSENT', 'LATE', 'LEAVE'];
if ($currentStatus !== '' && ! in_array($currentStatus, $statusOptions, true)) {
    $statusOptions[] = $currentStatus;
}
$statusIsEditable = ($can_record || ! empty($can_correct))
    && $session['status'] === 'scheduled';
$selectedStatus = old('statuses.' . (int) $player['player_id'], $currentStatus);
?>
<select
    class="attendance-status-select"
    id="attendance-status-<?= (int) $player['player_id'] ?>"
    aria-label="Status for <?= esc((string) $player['full_name'], 'attr') ?>"
    <?= $statusIsEditable ? '' : 'disabled' ?>
    <?php if ($statusIsEditable): ?>data-attendance-player="<?= (int) $player['player_id'] ?>"<?php endif ?>
>
<option value="">Choose status</option>
<?php foreach ($statusOptions as $status): ?>
<option value="<?= esc($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>>
<?= esc($status === 'LEAVE' ? 'Excused' : ucwords(strtolower(str_replace('_', ' ', $status)))) ?>
</option>
<?php endforeach ?>
</select>
    <?php if (! $statusIsEditable && $player['attendance_id'] === null): ?>
<span class="mcc-muted">Recording unavailable</span>
<?php endif ?>
</td>
<td data-label="Feedback / remark">
    <?php $feedbackRow = $feedbackByPlayer[(int) $player['player_id']] ?? []; ?>
    <?php if (! empty($feedbackRow['feedback_id'])): ?>
        <span class="mcc-badge mcc-badge-success">Remark added</span>
        <?php if (! empty($feedbackRow['feedback_reason'])): ?>
            <p class="mcc-feedback-preview"><?= esc((string) $feedbackRow['feedback_reason']) ?></p>
        <?php endif ?>
    <?php elseif ($player['attendance_id'] === null): ?>
        <span class="mcc-muted">Attendance required</span>
    <?php elseif ($can_feedback_update && in_array(strtolower((string) $session['status']), ['scheduled', 'active', 'completed'], true)): ?>
        <span class="mcc-muted">No remark added</span>
    <?php else: ?>
        <span class="mcc-muted">View only</span>
    <?php endif ?>
    <?php if (! empty($player['attendance_id']) && $can_feedback_update): ?>
        <button
            class="mcc-button mcc-button-secondary mcc-feedback-trigger"
            type="button"
            data-attendance-feedback-open
            data-feedback-action="<?= esc(site_url('coaches/' . (int) ($session['coach_user_id'] ?? 0) . '/feedback')) ?>"
            data-feedback-return="<?= esc('/attendance/sessions/' . (int) $session['id'], 'attr') ?>"
            <?php if (! empty($feedbackRow['feedback_id'])): ?>data-feedback-id="<?= (int) $feedbackRow['feedback_id'] ?>"<?php endif ?>
            data-feedback-session="<?= (int) $session['id'] ?>"
            data-feedback-player="<?= (int) $player['player_id'] ?>"
            data-feedback-player-name="<?= esc((string) $player['full_name'], 'attr') ?>"
            data-feedback-status="<?= esc((string) ($player['attendance_status'] ?? ''), 'attr') ?>"
            data-feedback-rating="<?= (int) ($feedbackRow['rating'] ?? 0) ?>"
            data-feedback-version="<?= (int) ($feedbackRow['feedback_version'] ?? 0) ?>"
        >Feedback</button>
    <?php endif ?>
</td>
</tr>
<?php endforeach ?>
<?php if ($players === []): ?>
<tr>
<td colspan="3" class="mcc-empty-state attendance-empty-state">
<i class="fa-solid fa-users-slash" aria-hidden="true"></i>
<strong>No selected Players</strong>
<span>No selected Players are available for this session.</span>
<?php if ($sessionId !== null): ?>
<a href="<?= site_url('sessions/' . $sessionId . '/page') ?>">Return to Session Details</a>
<?php endif ?>
</td>
</tr>
<?php endif ?>
</tbody>
</table>
</div>
<?php if (($can_record || ! empty($can_correct)) && $session['status'] === 'scheduled' && $players !== []): ?>
<form
    class="mcc-attendance-bulk-form mcc-form-actions"
    method="post"
    action="<?= site_url('attendance/sessions/' . (int) $session['id'] . '/save') ?>"
    data-attendance-bulk-form
>
    <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
    <?php foreach ($players as $player): ?>
        <?php if (! empty($can_correct) && $player['attendance_id'] !== null): ?>
            <input
                type="hidden"
                name="versions[<?= (int) $player['player_id'] ?>]"
                value="<?= (int) ($player['attendance_version'] ?? 0) ?>"
            >
        <?php endif ?>
    <?php endforeach ?>
    <span class="mcc-form-help">Choose one status for each selected Player.</span>
    <div class="mcc-form-field attendance-correction-reason">
        <label for="attendance-correction-reason">Correction reason</label>
        <textarea
            id="attendance-correction-reason"
            name="correction_reason"
            rows="2"
            maxlength="255"
        ><?= esc((string) old('correction_reason', '')) ?></textarea>
        <span class="mcc-form-help">Required only when a saved status changes.</span>
    </div>
    <button class="mcc-button mcc-button-primary" type="submit">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
        Save Attendance
    </button>
    <div class="attendance-save-feedback" data-attendance-form-feedback hidden role="status"></div>
</form>
<?php endif ?>
</section>
</div>
<div class="mcc-modal mcc-session-feedback-modal" data-session-feedback-modal hidden aria-hidden="true">
    <div class="mcc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="attendance-feedback-modal-title">
        <div class="mcc-modal-heading">
            <div>
                <span class="mcc-eyebrow">Player feedback</span>
                <h2 id="attendance-feedback-modal-title">Add remark</h2>
            </div>
            <button class="mcc-modal-close" type="button" data-session-feedback-close aria-label="Close feedback editor">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="mcc-modal-body">
            <p class="mcc-muted"><strong data-session-feedback-player></strong> · <span data-session-feedback-status></span></p>
            <form method="post" data-session-feedback-form>
                <?= csrf_field() ?>
                <input type="hidden" name="session_id" data-session-feedback-session>
                <input type="hidden" name="player_id" data-session-feedback-player-id>
                <input type="hidden" name="feedback_id" data-session-feedback-id>
                <input type="hidden" name="expected_version" data-session-feedback-version>
                <input type="hidden" name="return_to" data-session-feedback-return>
                <div class="mcc-form-field">
                    <label for="attendance-feedback-rating">Rating</label>
                    <select class="form-control" id="attendance-feedback-rating" name="rating" required>
                        <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                            <option value="<?= $rating ?>"><?= $rating ?>/5</option>
                        <?php endfor ?>
                    </select>
                </div>
                <div class="mcc-form-field">
                    <label for="attendance-feedback-reason"><span data-session-feedback-reason-label>Remark</span></label>
                    <textarea class="form-control" id="attendance-feedback-reason" name="reason" rows="4" maxlength="255" required><?= esc((string) old('reason', '')) ?></textarea>
                </div>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-secondary" type="button" data-session-feedback-close>Cancel</button>
                    <button class="mcc-button mcc-button-primary" type="submit">Save remark</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= view('staff_footer') ?>







