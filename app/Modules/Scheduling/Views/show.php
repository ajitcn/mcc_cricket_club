<?php
$session = is_array($session ?? null) ? $session : [];
$players = is_array($session['players'] ?? null) ? $session['players'] : [];
$attendanceSummary = is_array($session['attendance_summary'] ?? null)
    ? $session['attendance_summary']
    : [];
$history = is_array($session['history'] ?? null) ? $session['history'] : [];
$auditHistory = is_array($session['audit_history'] ?? null)
    ? $session['audit_history']
    : [];
$canUpdate = (bool) ($can_update ?? false);
$canCancel = (bool) ($can_cancel ?? false);
$canAttendanceView = (bool) ($can_attendance_view ?? false);
$canAttendanceCapture = (bool) ($can_attendance_capture ?? false);
$canComplete = (bool) ($can_complete ?? false);
$canAuditView = (bool) ($can_audit_view ?? false);
$attendanceRoster = is_array($attendance_roster ?? null) ? $attendance_roster : [];
$attendancePlayers = is_array($attendanceRoster['players'] ?? null) ? $attendanceRoster['players'] : [];
$attendanceStatuses = is_array($attendance_statuses ?? null) ? $attendance_statuses : [];
$attendanceStatuses = $attendanceStatuses !== []
    ? $attendanceStatuses
    : ['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'];
$attendanceStatusClasses = [
    'PRESENT' => 'is-present',
    'ABSENT' => 'is-absent',
    'LEAVE' => 'is-leave',
    'MEDICAL' => 'is-medical',
    'LATE' => 'is-late',
    'RAIN_CANCELLED' => 'is-cancelled',
    'COACH_CANCELLED' => 'is-cancelled',
];
$attendanceCanRecord = (bool) ($attendanceRoster['can_record'] ?? false);
$attendanceCanCorrect = (bool) ($attendanceRoster['can_correct'] ?? false);
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn(string $field): string => (string) ($fieldErrors[$field] ?? '');
$status = strtolower((string) ($session['status'] ?? 'unknown'));
$sessionType = match ((string) ($session['session_type'] ?? '')) {
    'one_to_one' => 'Personal',
    'group' => 'Group',
    'elite' => 'Elite',
    default => 'Not set',
};
$statusClass = match ($status) {
    'scheduled' => 'mcc-status-badge is-active',
    'completed' => 'mcc-status-badge is-completed',
    default => 'mcc-status-badge is-inactive',
};
$datePart = static function (string $value): string {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    return preg_match('/^(\d{2}-\d{2}-\d{4})/', $value, $matches) ? $matches[1] : '';
};
$timePart = static function (string $value): string {
    return preg_match('/(?:T| )(\d{2}:\d{2})/', $value, $matches) ? $matches[1] : '';
};
$historyDateTime = static function (mixed $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Not available';
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string) config('App')->appTimezone))
            ->format('d-m-Y h:i A');
    } catch (Throwable) {
        return $value;
    }
};
$metadataLabel = static function (string $key): string {
    return ucwords(str_replace(['_', '-'], ' ', $key));
};
$filterMetadata = static function (mixed $value) use (&$filterMetadata): mixed {
    $sensitive = '/(?:password|passphrase|token|secret|credential|storage[_-]?path|file[_-]?contents?|document[_-]?contents?)/i';
    if (! is_array($value)) {
        return $value;
    }

    $filtered = [];
    foreach ($value as $key => $childValue) {
        if (preg_match($sensitive, (string) $key)) {
            continue;
        }
        $filtered[$key] = $filterMetadata($childValue);
    }

    return $filtered;
};
$renderMetadata = static function (mixed $value, ?string $key = null) use (&$renderMetadata, $historyDateTime, $metadataLabel): string {
    $sensitive = '/(?:password|passphrase|token|secret|credential|storage[_-]?path|file[_-]?contents?|document[_-]?contents?)/i';
    if ($key !== null && preg_match($sensitive, $key)) {
        return '';
    }

    if (is_array($value)) {
        if ($value === []) {
            return '<span class="mcc-muted">No value</span>';
        }

        $html = '<dl class="mcc-session-metadata-list">';
        foreach ($value as $childKey => $childValue) {
            $childKey = (string) $childKey;
            if (preg_match($sensitive, $childKey)) {
                continue;
            }
            $childHtml = $renderMetadata($childValue, $childKey);
            if ($childHtml === '') {
                continue;
            }
            $html .= '<div><dt>' . esc($metadataLabel($childKey)) . '</dt><dd>' . $childHtml . '</dd></div>';
        }
        return $html . '</dl>';
    }

    if ($value === null || $value === '') {
        return '<span class="mcc-muted">Not available</span>';
    }

    if ($key !== null && preg_match('/(?:^|_)(?:at|date|timestamp)$/i', $key)) {
        $value = $historyDateTime($value);
    }

    return esc(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value);
};
$rescheduleStarts = (string) old('starts_at', '');
$sessionEndTime = $timePart((string) ($session['ends_at_local'] ?? ''));
?>
<?= view('staff_header', [
    'title' => 'Session #' . (int) ($session['id'] ?? 0),
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => $pageActions ?? '',
]) ?>
<div class="mcc-scheduling-detail">
    <section class="mcc-card mcc-session-detail-hero" aria-labelledby="session-details-heading">
        <div class="mcc-detail-hero">
            <div>
                <span class="mcc-eyebrow">Scheduling</span>
                <h2 id="session-details-heading">Session #<?= (int) ($session['id'] ?? 0) ?></h2>
                <p class="mcc-muted">
                    <?= esc(mcc_user_datetime($session['starts_at_local'] ?? null)) ?>
                </p>
            </div>
            <span class="<?= esc($statusClass) ?>">
                <?= esc(ucfirst($status)) ?>
            </span>
        </div>
    </section>

    <section class="mcc-card mcc-session-detail-card" aria-labelledby="session-information-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Session information</span>
                <h2 id="session-information-heading">Session details</h2>
            </div>
        </div>
        <dl class="mcc-session-information">
            <div>
                <dt>Session Type</dt>
                <dd><?= esc($sessionType) ?></dd>
            </div>
            <div>
                <dt>Description / Notes</dt>
                <dd>
                    <?php if (trim((string) ($session['description'] ?? '')) !== ''): ?>
                        <?= nl2br(esc((string) $session['description'])) ?>
                    <?php else: ?>
                        <span class="mcc-muted">No notes added.</span>
                    <?php endif ?>
                </dd>
            </div>
            <div>
                <dt>Branch</dt>
                <dd><?= esc((string) ($session['branch_name'] ?? 'Branch unavailable')) ?></dd>
            </div>
            <div>
                <dt>Program</dt>
                <dd><?= esc((string) ($session['program_name'] ?? 'No Program linked')) ?></dd>
            </div>
            <div>
                <dt>End time</dt>
                <dd><?= esc($sessionEndTime !== '' ? $sessionEndTime : 'Not available') ?></dd>
            </div>
            <div>
                <dt>Coach</dt>
                <dd>
                    <?= esc((string) ($session['coach_name'] ?? 'Coach unavailable')) ?>
                    <?php if (empty($session['coach_name'])): ?>
                        <span class="mcc-muted">No Coach is assigned to this session.</span>
                    <?php endif ?>
                </dd>
            </div>
            <div>
                <dt>Ground</dt>
                <dd>
                    <?= esc((string) ($session['ground_name'] ?? 'No Ground assigned')) ?>
                    <?php if (empty($session['ground_name'])): ?>
                        <span class="mcc-muted">This session has no Ground assignment.</span>
                    <?php endif ?>
                </dd>
            </div>
            <div>
                <dt>Skill focus</dt>
                <dd>
                    <?php if (! empty($session['skill_option_label'])): ?>
                        <?= esc((string) ($session['skill_area'] ?? '')) ?>
                        <span aria-hidden="true"> · </span>
                        <?= esc((string) $session['skill_option_label']) ?>
                    <?php else: ?>
                        <span class="mcc-muted">No skill selected</span>
                    <?php endif ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="mcc-card mcc-session-detail-card" aria-labelledby="session-category-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Session Category</span>
                <h2 id="session-category-heading">Session category</h2>
            </div>
        </div>
        <dl class="mcc-session-information">
            <div>
                <dt>Batch</dt>
                <dd><?= esc((string) ($session['batch_name'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Age Group</dt>
                <dd><?= esc((string) ($session['age_group_name'] ?? '—')) ?></dd>
            </div>
        </dl>
    </section>

    <?php if ($canAttendanceView): ?>
        <section class="mcc-card mcc-session-detail-card" aria-labelledby="attendance-summary-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Attendance</span>
                    <h2 id="attendance-summary-heading">Attendance summary</h2>
                </div>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('attendance/sessions/' . (int) $session['id']) ?>">
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    Open attendance
                </a>
            </div>
            <?php $attendanceCounts = array_fill_keys($attendanceStatuses, 0); ?>
            <?php $feedbackByPlayer = []; ?>
            <?php foreach (($session_feedback ?? []) as $feedbackRow): ?>
                <?php $feedbackByPlayer[(int) $feedbackRow['player_id']] = $feedbackRow; ?>
            <?php endforeach ?>
            <?php foreach ($attendanceSummary as $item): ?>
                <?php $summaryStatus = strtoupper((string) ($item['status'] ?? '')); ?>
                <?php if (array_key_exists($summaryStatus, $attendanceCounts)): ?>
                    <?php $attendanceCounts[$summaryStatus] = (int) $item['total']; ?>
                <?php endif ?>
            <?php endforeach ?>
            <div class="mcc-attendance-summary-grid">
                <?php foreach ($attendanceStatuses as $summaryStatus): ?>
                    <div class="mcc-attendance-summary-item <?= esc($attendanceStatusClasses[$summaryStatus] ?? 'is-neutral') ?>">
                        <span><?= esc(ucwords(strtolower(str_replace('_', ' ', $summaryStatus)))) ?></span>
                        <strong><?= (int) $attendanceCounts[$summaryStatus] ?></strong>
                    </div>
                <?php endforeach ?>
            </div>
            <?php $recordedCount = array_sum($attendanceCounts); ?>
            <div class="mcc-attendance-summary-meta" aria-label="Attendance totals">
                <span><strong><?= count($players) ?></strong> selected Players</span>
                <span><strong><?= (int) $recordedCount ?></strong> recorded</span>
            </div>
            <?php if ($attendancePlayers === []): ?>
                <p class="mcc-empty-state" role="status">No eligible Players are selected for this session.</p>
            <?php else: ?>
                <div class="mcc-table-wrap mcc-attendance-session-table-wrap">
                    <table class="mcc-table mcc-attendance-session-table">
                        <caption class="visually-hidden">Player Feedback / Remarks</caption>
                        <caption class="visually-hidden">Player attendance</caption>
                        <thead>
                            <tr>
                                <th scope="col">Player</th>
                                <th scope="col">Status</th>
                                <th scope="col">Updated</th>
                                <th scope="col">Feedback / remark</th>
                                <th scope="col">Attendance action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendancePlayers as $attendancePlayer): ?>
                                <?php $attendanceId = (int) ($attendancePlayer['attendance_id'] ?? 0); ?>
                                <?php $playerId = (int) ($attendancePlayer['player_id'] ?? 0); ?>
                                <?php $currentStatus = strtoupper((string) ($attendancePlayer['attendance_status'] ?? '')); ?>
                                <tr>
                                    <th scope="row" data-label="Player">
                                        <span><?= esc((string) ($attendancePlayer['full_name'] ?? ('Player #' . $playerId))) ?></span>
                                        <span class="mcc-badge mcc-badge-neutral">Player #<?= $playerId ?></span>
                                    </th>
                                    <td data-label="Status">
                                        <?php if ($currentStatus !== ''): ?>
                                            <span class="mcc-badge mcc-attendance-status <?= esc($attendanceStatusClasses[$currentStatus] ?? 'is-neutral') ?>">
                                                <?= esc(ucwords(strtolower(str_replace('_', ' ', $currentStatus)))) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="mcc-muted">Not recorded</span>
                                        <?php endif ?>
                                    </td>
                                    <td data-label="Updated">
                                        <?php if (!empty($attendancePlayer['attendance_updated_at'])): ?>
                                            <?= esc($historyDateTime($attendancePlayer['attendance_updated_at'])) ?>
                                            <?php if ((int) ($attendancePlayer['attendance_version'] ?? 1) > 1): ?>
                                                <span class="mcc-badge mcc-badge-neutral">Corrected</span>
                                            <?php endif ?>
                                        <?php else: ?>
                                            <span class="mcc-muted">Not recorded</span>
                                        <?php endif ?>
                                    </td>
                                    <td data-label="Feedback / remark">
                                        <?php $feedbackRow = $feedbackByPlayer[$playerId] ?? []; ?>
                                        <?php if (! empty($feedbackRow['feedback_id'])): ?>
                                            <span class="mcc-badge mcc-badge-success">Remark added</span>
                                            <?php if (! empty($feedbackRow['feedback_reason'])): ?>
                                                <p class="mcc-feedback-preview"><?= esc((string) $feedbackRow['feedback_reason']) ?></p>
                                            <?php endif ?>
                                            <?php if ($can_feedback_update): ?>
                                                <button
                                                    class="mcc-button mcc-button-secondary mcc-feedback-trigger"
                                                    type="button"
                                                    data-session-feedback-open
                                                    data-feedback-action="<?= esc(site_url('coaches/' . (int) ($session['coach_user_id'] ?? 0) . '/feedback')) ?>"
                                                    data-feedback-return="<?= esc('/sessions/' . (int) $session['id'] . '/page', 'attr') ?>"
                                                    data-feedback-id="<?= (int) $feedbackRow['feedback_id'] ?>"
                                                    data-feedback-session="<?= (int) $session['id'] ?>"
                                                    data-feedback-player="<?= $playerId ?>"
                                                    data-feedback-player-name="<?= esc((string) $attendancePlayer['full_name'], 'attr') ?>"
                                                    data-feedback-status="<?= esc((string) ($attendancePlayer['attendance_status'] ?? ''), 'attr') ?>"
                                                    data-feedback-rating="<?= (int) ($feedbackRow['rating'] ?? 0) ?>"
                                                    data-feedback-version="<?= (int) ($feedbackRow['feedback_version'] ?? 0) ?>"
                                                >Edit remark</button>
                                            <?php endif ?>
                                        <?php elseif (empty($attendancePlayer['attendance_id'])): ?>
                                            <span class="mcc-muted">Attendance required</span>
                                        <?php elseif ($can_feedback_update && in_array($status, ['scheduled', 'active', 'completed'], true)): ?>
                                            <span class="mcc-muted">No remark added</span>
                                            <button
                                                class="mcc-button mcc-button-secondary mcc-feedback-trigger"
                                                type="button"
                                                data-session-feedback-open
                                                data-feedback-action="<?= esc(site_url('coaches/' . (int) ($session['coach_user_id'] ?? 0) . '/feedback')) ?>"
                                                data-feedback-return="<?= esc('/sessions/' . (int) $session['id'] . '/page', 'attr') ?>"
                                                data-feedback-session="<?= (int) $session['id'] ?>"
                                                data-feedback-player="<?= $playerId ?>"
                                                data-feedback-player-name="<?= esc((string) $attendancePlayer['full_name'], 'attr') ?>"
                                                data-feedback-status="<?= esc((string) ($attendancePlayer['attendance_status'] ?? ''), 'attr') ?>"
                                                data-feedback-version="0"
                                            >Add remark</button>
                                        <?php else: ?>
                                            <span class="mcc-muted">View only</span>
                                        <?php endif ?>
                                    </td>
                                    <td data-label="Attendance action">
                                        <?php if ($attendanceId === 0 && $attendanceCanRecord && $status === 'scheduled'): ?>
                                            <form method="post" action="<?= site_url('attendance/sessions/' . (int) $session['id'] . '/players/' . $playerId) ?>" class="mcc-session-attendance-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= esc('/sessions/' . (int) $session['id'] . '/page') ?>">
                                                <select class="form-control" id="session-attendance-status-<?= $playerId ?>" name="status" aria-label="Status" required>
                                                    <option value="">Select status</option>
                                                    <?php foreach ($attendanceStatuses as $attendanceOption): ?>
                                                        <option value="<?= esc($attendanceOption) ?>" <?= old('status') === $attendanceOption ? 'selected' : '' ?>><?= esc(ucwords(strtolower(str_replace('_', ' ', $attendanceOption)))) ?></option>
                                                    <?php endforeach ?>
                                                </select>
                                                <button class="mcc-button mcc-button-primary" type="submit">Record</button>
                                            </form>
                                        <?php elseif ($attendanceId > 0 && $attendanceCanCorrect && $status === 'scheduled'): ?>
                                            <form method="post" action="<?= site_url('attendance/' . $attendanceId . '/correct') ?>" class="mcc-session-attendance-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= esc('/sessions/' . (int) $session['id'] . '/page') ?>">
                                                <input type="hidden" name="expected_version" value="<?= (int) ($attendancePlayer['attendance_version'] ?? 0) ?>">
                                                <label class="visually-hidden" for="session-attendance-correction-<?= $attendanceId ?>">Correct attendance status</label>
                                                <select class="form-control" id="session-attendance-correction-<?= $attendanceId ?>" name="status" required>
                                                    <?php foreach ($attendanceStatuses as $attendanceOption): ?>
                                                        <option value="<?= esc($attendanceOption) ?>" <?= $currentStatus === $attendanceOption ? 'selected' : '' ?>><?= esc(ucwords(strtolower(str_replace('_', ' ', $attendanceOption)))) ?></option>
                                                    <?php endforeach ?>
                                                </select>
                                                <label class="visually-hidden" for="session-attendance-reason-<?= $attendanceId ?>">Correction reason</label>
                                                <input class="form-control" id="session-attendance-reason-<?= $attendanceId ?>" name="reason" required maxlength="255" placeholder="Correction reason" value="<?= esc((string) old('reason', '')) ?>">
                                                <button class="mcc-button mcc-button-secondary" type="submit">Correct</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="mcc-muted">Read only</span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    <?php elseif ($canAttendanceCapture && $status === 'scheduled'): ?>
        <section class="mcc-card mcc-session-detail-card" aria-labelledby="attendance-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Attendance</span>
                    <h2 id="attendance-heading">Attendance</h2>
                </div>
            </div>
            <p class="mcc-muted">Attendance details are available to users with Attendance view access.</p>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('attendance/sessions/' . (int) $session['id']) ?>">
                Open attendance
            </a>
        </section>
    <?php endif ?>

    <?php if ($canUpdate && $status === 'scheduled'): ?>
        <section class="mcc-card mcc-session-detail-card" aria-labelledby="coach-assignment-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Coach assignment</span>
                    <h2 id="coach-assignment-heading">Assign or reassign Coach</h2>
                </div>
            </div>
            <?php if (($formOptions['coaches'] ?? []) === []): ?>
                <p class="mcc-empty-state" role="status">No active Coach is authorized for this branch.</p>
            <?php else: ?>
                <form class="mcc-session-inline-form" method="post" action="<?= site_url('sessions/' . (int) $session['id'] . '/coach') ?>">
                    <?= csrf_field() ?>
                    <div class="mcc-form-field">
                        <label for="session-coach-reassign">Coach</label>
                        <select class="form-control" id="session-coach-reassign" name="coach_user_id" required>
                            <?php $selectedCoachId = (int) old('coach_user_id', $session['coach_user_id'] ?? 0) ?>
                            <?php foreach ($formOptions['coaches'] as $coach): ?>
                                <option value="<?= (int) $coach['id'] ?>" <?= (int) $coach['id'] === $selectedCoachId ? 'selected' : '' ?>>
                                    <?= esc($coach['branch_name'] . ' · ' . $coach['display_name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="mcc-form-field">
                        <label for="session-coach-reason">Reason</label>
                        <input class="form-control" id="session-coach-reason" name="reason" maxlength="255" value="<?= esc((string) old('reason', '')) ?>" required>
                    </div>
                    <button class="mcc-button mcc-button-secondary" type="submit">
                        <i class="fa-solid fa-user-pen" aria-hidden="true"></i>
                        Reassign Coach
                    </button>
                </form>
            <?php endif ?>
        </section>
    <?php endif ?>

    <section class="mcc-card mcc-session-detail-card" aria-labelledby="session-history-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">History</span>
                <h2 id="session-history-heading">Session history</h2>
            </div>
        </div>
        <?php if ($canAuditView && $auditHistory === []): ?>
            <p class="mcc-empty-state">No session audit events are available.</p>
        <?php elseif ($canAuditView): ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-session-history-table">
                    <caption class="visually-hidden">Session audit events</caption>
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Event timestamp</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Linkage and metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditHistory as $event): ?>
                            <tr>
                                <td><?= esc(str_replace('_', ' ', (string) ($event['action'] ?? 'Session event'))) ?></td>
                                <td><?= esc($event['actor_name'] ?? ('User #' . (int) ($event['actor_user_id'] ?? 0))) ?></td>
                                <td><?= esc($historyDateTime($event['created_at'] ?? null)) ?></td>
                                <td><?= esc((string) ($event['reason'] ?? '—')) ?></td>
                                <td>
                                    <?php $before = $event['before_metadata'] ?? []; ?>
                                    <?php $after = $event['after_metadata'] ?? []; ?>
                                    <?php $beforeDisplay = $event['before_display_metadata'] ?? $before; ?>
                                    <?php $afterDisplay = $event['after_display_metadata'] ?? $after; ?>
                                    <?php $safeBefore = $filterMetadata($beforeDisplay); ?>
                                    <?php $safeAfter = $filterMetadata($afterDisplay); ?>
                                    <?php $beforeMalformed = (bool) ($event['before_metadata_malformed'] ?? false); ?>
                                    <?php $afterMalformed = (bool) ($event['after_metadata_malformed'] ?? false); ?>
                                    <?php if (!empty($event['cancelled_at'])): ?>
                                        <p class="mcc-session-history-note">Cancelled at: <?= esc($historyDateTime($event['cancelled_at'])) ?></p>
                                    <?php endif ?>
                                    <?php if ($beforeMalformed || $afterMalformed || $before !== [] || $after !== []): ?>
                                        <div class="mcc-session-metadata-summary">
                                            <div>
                                                <h3>Previous values</h3>
                                                <?php if ($before !== []): ?>
                                                    <?= $renderMetadata($safeBefore) ?>
                                                <?php elseif ($beforeMalformed): ?>
                                                    <span class="mcc-muted">Metadata unavailable</span>
                                                <?php else: ?>
                                                    <span class="mcc-muted">No metadata available</span>
                                                <?php endif ?>
                                            </div>
                                            <div>
                                                <h3>New values</h3>
                                                <?php if ($after !== []): ?>
                                                    <?= $renderMetadata($safeAfter) ?>
                                                <?php elseif ($afterMalformed): ?>
                                                    <span class="mcc-muted">Metadata unavailable</span>
                                                <?php else: ?>
                                                    <span class="mcc-muted">No metadata available</span>
                                                <?php endif ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="mcc-muted">No metadata available</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>

        <?php if ($history === []): ?>
            <p class="mcc-empty-state">Session lineage is not available.</p>
        <?php else: ?>
            <ul class="mcc-session-lineage">
                <?php foreach ($history as $item): ?>
                    <li>
                        <span>Session #<?= (int) $item['id'] ?></span>
                        <span class="mcc-badge mcc-badge-neutral"><?= esc($item['status']) ?></span>
                        <span><?= esc($historyDateTime($item['starts_at'] ?? null)) ?></span>
                        <?php if (!empty($item['cancellation_reason'])): ?>
                            <span><?= esc($item['cancellation_reason']) ?></span>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <?php if ($canCancel || ($canUpdate && $status === 'scheduled')): ?>
        <section class="mcc-card mcc-session-detail-card" aria-labelledby="session-actions-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Workflow</span>
                    <h2 id="session-actions-heading">Session actions</h2>
                </div>
            </div>
            <?php if ($status === 'scheduled' && $canUpdate): ?>
                <form method="post" action="<?= site_url('sessions/' . (int) $session['id'] . '/reschedule') ?>" class="mcc-session-inline-form" data-session-submit-form>
                    <?= csrf_field() ?>
                    <div class="mcc-form-field">
                        <label for="new-start">New start date</label>
                        <input class="form-control" id="new-start" name="starts_at" type="text" inputmode="numeric" autocomplete="off" value="<?= esc($datePart($rescheduleStarts)) ?>" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" data-mcc-date-picker-time-input="new-start-time" aria-describedby="new-start-error" required placeholder="DD-MM-YYYY">
                        <label for="new-start-time">New start time</label>
                        <input class="form-control" id="new-start-time" name="starts_time" type="time" value="<?= esc($timePart($rescheduleStarts)) ?>" aria-describedby="new-start-error" required>
                        <?php if ($fieldError('starts_at') !== '' || $fieldError('starts_time') !== ''): ?>
                            <p class="mcc-field-error" id="new-start-error" role="alert"><?= esc($fieldError('starts_at') ?: $fieldError('starts_time')) ?></p>
                        <?php endif ?>
                    </div>
                    <div class="mcc-form-field">
                        <label for="reschedule-reason">Reason</label>
                        <input class="form-control" id="reschedule-reason" name="reason" value="<?= esc((string) old('reason', '')) ?>" required maxlength="255">
                    </div>
                    <button class="mcc-button mcc-button-primary" type="submit">Reschedule session</button>
                    <div class="mcc-alert mcc-session-form-feedback" data-session-response hidden role="status"></div>
                </form>
            <?php endif ?>
            <?php if ($status === 'scheduled' && $canCancel): ?>
                <form method="post" action="<?= site_url('sessions/' . (int) $session['id'] . '/cancel') ?>" class="mcc-session-inline-form" data-session-submit-form>
                    <?= csrf_field() ?>
                    <div class="mcc-form-field">
                        <label for="cancel-reason">Cancellation reason</label>
                        <input class="form-control" id="cancel-reason" name="reason" value="<?= esc((string) old('cancel_reason', '')) ?>" required maxlength="255">
                    </div>
                    <button class="mcc-button mcc-button-danger" type="submit">Cancel session</button>
                    <div class="mcc-alert mcc-session-form-feedback" data-session-response hidden role="status"></div>
                </form>
            <?php elseif ($status !== 'scheduled'): ?>
                <p class="mcc-empty-state">Cancelled sessions are preserved and cannot be reused.</p>
            <?php endif ?>
        </section>
    <?php endif ?>
</div>
<div class="mcc-modal mcc-session-feedback-modal" data-session-feedback-modal hidden aria-hidden="true">
    <div class="mcc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="session-feedback-modal-title">
        <div class="mcc-modal-heading">
            <div>
                <span class="mcc-eyebrow">Player feedback</span>
                <h2 id="session-feedback-modal-title">Add remark</h2>
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
                    <label for="session-feedback-rating">Rating</label>
                    <select class="form-control" id="session-feedback-rating" name="rating" required>
                        <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                            <option value="<?= $rating ?>"><?= $rating ?>/5</option>
                        <?php endfor ?>
                    </select>
                </div>
                <div class="mcc-form-field">
                    <label for="session-feedback-reason"><span data-session-feedback-reason-label>Remark</span></label>
                    <textarea class="form-control" id="session-feedback-reason" name="reason" rows="4" maxlength="255" required><?= esc((string) old('reason', '')) ?></textarea>
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

