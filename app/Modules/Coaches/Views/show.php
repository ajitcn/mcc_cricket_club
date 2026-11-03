<?php

$coach = is_array($coach ?? null) ? $coach : [];
$kpis = is_array($coach['kpis'] ?? null) ? $coach['kpis'] : [];
$activeCoach = (int) ($coach['is_active'] ?? 0) === 1 && (int) ($coach['is_enabled'] ?? 0) === 1;
$statusBadge = static function (string $status): string {
    $normalized = strtolower(str_replace('_', '-', trim($status)));
    $class = match ($normalized) {
        'approved', 'active' => 'mcc-badge-success',
        'rejected', 'cancelled', 'inactive' => 'mcc-badge-danger',
        default => 'mcc-badge-warning',
    };

    return '<span class="mcc-badge mcc-coach-status-badge ' . $class . '">' . esc($status) . '</span>';
};
$weekdays = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];
$localDateTimePart = static function (?string $value, string $format): string {
    if ($value === null || trim($value) === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string) config('App')->appTimezone))
            ->format($format);
    } catch (Throwable) {
        return '';
    }
};
$displayTime = static function (?string $value): string {
    $value = substr(trim((string) $value), 0, 5);
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $value);

    return $parsed === false ? $value : $parsed->format('h:i A');
};
?>
<?= view('staff_header', [
    'title' => 'Coach profile',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>
<div class="mcc-coach-profile-page">
<section class="mcc-card mcc-detail-hero mcc-coach-profile-card" aria-labelledby="coach-heading">
    <div>
        <span class="mcc-eyebrow">Coach profile</span>
        <h2 id="coach-heading"><?= esc($coach['display_name']) ?></h2>
        <p class="mcc-muted"><?= esc($coach['email']) ?></p>
        <div class="mcc-detail-badges" aria-label="Coach status">
            <?= $statusBadge($activeCoach ? 'Active' : 'Inactive') ?>
        </div>
    </div>
    <div class="mcc-detail-actions" aria-label="Coach profile actions">
        <?php if (service('authorization')->can('coach.update', (string) ($coach['branch_id'] ?? 0))): ?>
            <a class="mcc-button mcc-button-primary" href="<?= site_url('coaches/' . (int) $coach['id'] . '/edit') ?>">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <span class="mcc-button-label">Edit profile</span>
            </a>
        <?php endif; ?>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('coaches') ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span class="mcc-button-label">Back to coaches</span>
        </a>
    </div>
    <div class="mcc-detail-grid">
        <dl class="mcc-definition-list">
            <dt>Specialization</dt>
            <dd><?= esc($coach['specialization']) ?></dd>
            <dt>Experience</dt>
            <dd>
                <?= $coach['experience_years'] === null
                    ? 'Not provided'
                    : esc((string) $coach['experience_years']) ?>
            </dd>
            <dt>Branch scope</dt>
            <dd>
                <span class="mcc-badge mcc-badge-neutral mcc-coach-branch-badge">
                    <span class="mcc-coach-branch-value"><?= esc($coach['branch_scope'] ?? $coach['branch_name']) ?></span>
                </span>
            </dd>
            <dt>Availability</dt>
            <dd><?= nl2br(esc($coach['availability'] ?? 'Not provided')) ?></dd>
        </dl>
        <dl class="mcc-definition-list">
            <dt>Qualification</dt>
            <dd><?= nl2br(esc($coach['qualification'] ?? 'Not provided')) ?></dd>
            <dt>Certifications</dt>
            <dd><?= nl2br(esc($coach['certifications'] ?? 'Not provided')) ?></dd>
            <dt>Assigned branches</dt>
            <dd><?= count($coach['branches']) ?></dd>
        </dl>
    </div>
</section>
<?php if (($can_assign ?? false) && ($branches ?? []) !== []): ?>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="branch-assignment-heading">
    <div class="mcc-card-heading">
        <h2 id="branch-assignment-heading">Branch assignments</h2>
    </div>
    <div class="mcc-coach-form-fields">
        <?php foreach ($branches as $branch): ?>
            <?php $assigned = in_array((int) $branch['id'], array_map('intval', array_column($coach['branches'], 'id')), true); ?>
            <form class="mcc-coach-form" method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/branches') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="branch_id" value="<?= (int) $branch['id'] ?>">
                <input type="hidden" name="action" value="<?= $assigned ? 'revoke' : 'assign' ?>">
                <label>
                    <?= esc($branch['name']) ?>
                    <input name="reason" required maxlength="255" placeholder="Reason for change">
                </label>
                <button class="mcc-button <?= $assigned ? 'mcc-button-secondary' : 'mcc-button-primary' ?>" type="submit">
                    <i class="fa-solid <?= $assigned ? 'fa-link-slash' : 'fa-link' ?>" aria-hidden="true"></i>
                    <?= $assigned ? 'Remove assignment' : 'Assign branch' ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php if (($coach['kpi_visible'] ?? false) === true): ?>
<section class="mcc-card mcc-coach-kpi-card" aria-labelledby="kpi-heading">
    <div class="mcc-card-heading">
        <h2 id="kpi-heading">Coach KPIs</h2>
    </div>
    <div class="metric-grid mcc-coach-kpi-grid">
        <div class="metric-card">
            <span class="mcc-eyebrow">Sessions conducted</span>
            <strong><?= (int) ($kpis['sessions_conducted'] ?? 0) ?></strong>
            <span class="mcc-muted">Ended, non-cancelled sessions</span>
        </div>
        <div class="metric-card">
            <span class="mcc-eyebrow">Assigned players</span>
            <strong><?= (int) ($kpis['assigned_players'] ?? 0) ?></strong>
            <span class="mcc-muted">Active primary assignments</span>
        </div>
        <div class="metric-card">
            <span class="mcc-eyebrow">Attendance compliance</span>
            <strong>
                <?= $kpis['attendance_compliance'] === null
                    ? 'Not yet available'
                    : esc($kpis['attendance_compliance'] . '%') ?>
            </strong>
            <span class="mcc-muted">Recorded roster attendance</span>
        </div>
        <div class="metric-card">
            <span class="mcc-eyebrow">Player feedback</span>
            <strong><?= $kpis['player_feedback_rating'] === null ? 'Not yet available' : esc((string) $kpis['player_feedback_rating']) . '/5' ?></strong>
            <span class="mcc-muted">Average approved session ratings</span>
        </div>
        <div class="metric-card">
            <span class="mcc-eyebrow">Performance improvement</span>
            <strong>
                <?= $kpis['performance_improvement_index'] === null
                    ? 'Not yet available'
                    : esc($kpis['performance_improvement_index'] . '%') ?>
            </strong>
            <span class="mcc-muted">Average change across valid approved metrics</span>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if (($feedback_options ?? []) !== []): ?>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="feedback-heading">
    <div class="mcc-card-heading">
        <h2 id="feedback-heading">Player feedback rating</h2>
    </div>
    <form class="mcc-coach-form" method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/feedback') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= esc('/coaches/' . (int) $coach['id']) ?>">
        <div class="mcc-coach-form-fields">
            <div class="mcc-coach-field">
                <label for="feedback-session">Session and player with recorded attendance</label>
                <select id="feedback-session" name="session_id" required>
                    <?php foreach ($feedback_options as $feedbackOption): ?>
                        <option value="<?= (int) $feedbackOption['session_id'] ?>" data-player-id="<?= (int) $feedbackOption['player_id'] ?>">
                            <?= esc(mcc_user_datetime($feedbackOption['ends_at']) . ' · ' . $feedbackOption['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-coach-field">
                <label for="feedback-player">Player</label>
                <select id="feedback-player" name="player_id" required>
                    <?php foreach ($feedback_options as $feedbackOption): ?>
                        <option value="<?= (int) $feedbackOption['player_id'] ?>"><?= esc($feedbackOption['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-coach-field">
                <label for="feedback-rating">Rating</label>
                <select id="feedback-rating" name="rating" required>
                    <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                        <option value="<?= $rating ?>"><?= $rating ?>/5</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mcc-coach-field">
                <label for="feedback-reason">Reason</label>
                <input id="feedback-reason" name="reason" maxlength="255" required>
            </div>
        </div>
        <div class="mcc-coach-form-actions mcc-detail-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-star" aria-hidden="true"></i> Save rating</button>
        </div>
    </form>
</section>
<?php endif; ?>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="feedback-list-heading">
    <div class="mcc-card-heading">
        <h2 id="feedback-list-heading">Feedback history</h2>
    </div>
    <?php if (($feedback ?? []) === []): ?>
        <p class="mcc-empty-state">No feedback is available for Players with recorded attendance.</p>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-coach-related-table">
                <thead>
                    <tr>
                        <th scope="col">Session</th>
                        <th scope="col">Player</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Date</th>
                        <th scope="col">Comment</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedback as $feedbackRow): ?>
                        <tr>
                            <th scope="row">
                                <?= esc(mcc_user_datetime($feedbackRow['starts_at'] ?? null)) ?>
                            </th>
                            <td><?= esc($feedbackRow['player_name']) ?></td>
                            <td><?= esc($feedbackRow['branch_name']) ?></td>
                            <td><?= esc((string) $feedbackRow['rating']) ?>/5</td>
                            <td><?= esc(mcc_user_date($feedbackRow['created_at'] ?? null)) ?></td>
                            <td><?= esc($feedbackRow['comment'] ?? 'Not provided') ?></td>
                            <td>
                                <?php if (($feedback_permissions[(int) $feedbackRow['id']] ?? false) === true): ?>
                                    <form method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/feedback') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= esc('/coaches/' . (int) $coach['id']) ?>">
                                        <input type="hidden" name="feedback_id" value="<?= (int) $feedbackRow['id'] ?>">
                                        <input type="hidden" name="expected_version" value="<?= (int) ($feedbackRow['version'] ?? 0) ?>">
                                        <input type="hidden" name="session_id" value="<?= (int) $feedbackRow['session_id'] ?>">
                                        <input type="hidden" name="player_id" value="<?= (int) $feedbackRow['player_id'] ?>">
                                        <label>
                                            <span class="sr-only">Correct rating</span>
                                            <select name="rating" required>
                                                <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                                                    <option value="<?= $rating ?>" <?= (int) $feedbackRow['rating'] === $rating ? 'selected' : '' ?>>
                                                        <?= $rating ?>/5
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </label>
                                        <label>
                                            <span class="sr-only">Correction reason</span>
                                            <input name="reason" maxlength="255" required placeholder="Correction reason">
                                        </label>
                                        <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Correct</button>
                                    </form>
                                <?php else: ?>
                                    <span class="mcc-muted">View only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="players-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Player assignments</span>
            <h2 id="players-heading">Assigned players</h2>
        </div>
        <span class="mcc-badge mcc-badge-neutral">
            <?= (int) ($coach['assigned_players_count'] ?? 0) ?> assigned
        </span>
    </div>
    <p class="mcc-muted">Open the authorized active player assignments for this coach.</p>
    <div class="mcc-coach-summary-actions">
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('coaches/' . (int) $coach['id'] . '/players') ?>">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            View players
        </a>
    </div>
</section>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="sessions-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Session assignments</span>
            <h2 id="sessions-heading">Sessions</h2>
        </div>
        <span class="mcc-badge mcc-badge-neutral">
            <?= (int) ($coach['assigned_sessions_count'] ?? 0) ?> assigned
        </span>
    </div>
    <p class="mcc-muted">Open the authorized sessions assigned to this coach.</p>
    <div class="mcc-coach-summary-actions">
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('coaches/' . (int) $coach['id'] . '/sessions') ?>">
            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
            View sessions
        </a>
    </div>
</section>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="leave-heading">
    <div class="mcc-card-heading">
        <h2 id="leave-heading">Upcoming leave / unavailability</h2>
    </div>
    <?php if (($leave_branches ?? []) !== []): ?>
        <form class="mcc-coach-form" method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/leave') ?>">
            <?= csrf_field() ?>
            <div class="mcc-coach-form-fields">
                <div class="mcc-coach-field">
                    <label for="coach-leave-branch">Branch</label>
                    <select id="coach-leave-branch" name="branch_id" required>
                        <?php foreach ($leave_branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>"><?= esc($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-leave-from">Start</label>
                    <input id="coach-leave-from" name="unavailable_from" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-datetime-submit-format="Y-m-d" data-mcc-date-picker-time-input="coach-leave-from-time" required>
                    <input id="coach-leave-from-time" name="unavailable_from_time" type="time" required aria-label="Leave start time">
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-leave-to">End</label>
                    <input id="coach-leave-to" name="unavailable_to" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-datetime-submit-format="Y-m-d" data-mcc-date-picker-time-input="coach-leave-to-time" required>
                    <input id="coach-leave-to-time" name="unavailable_to_time" type="time" required aria-label="Leave end time">
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-leave-reason">Reason</label>
                    <input id="coach-leave-reason" name="reason" maxlength="255" required>
                </div>
            </div>
            <div class="mcc-coach-form-actions mcc-detail-actions">
                <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Request leave</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($coach['leave'] === []): ?>
        <p class="mcc-empty-state">No upcoming leave or unavailability recorded.</p>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-coach-related-table">
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">To</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coach['leave'] as $leave): ?>
                        <tr>
<td><?= esc(mcc_user_date($leave['unavailable_from'] ?? null)) ?></td>
<td><?= esc(mcc_user_date($leave['unavailable_to'] ?? null)) ?></td>
                            <td><?= esc($leave['branch_name']) ?></td>
                            <td><?= esc($leave['reason'] ?? 'Not provided') ?></td>
                            <td><?= $statusBadge((string) ($leave['status'] ?? 'PENDING')) ?></td>
                            <?php
                            $leaveStatus = (string) ($leave['status'] ?? 'PENDING');
                            $canManageLeave = ($leave_permissions[(int) $leave['branch_id']] ?? false)
                                && in_array($leaveStatus, ['PENDING', 'APPROVED'], true);
                            $canCancelOwnLeave = ($own_pending_leave[(int) $leave['id']] ?? false)
                                && $leaveStatus === 'PENDING';
                            ?>
                            <?php if ($canManageLeave || $canCancelOwnLeave): ?>
                                <td class="mcc-coach-leave-actions">
                                    <?php if ($canManageLeave): ?>
                                    <form method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/leave') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="leave_id" value="<?= (int) $leave['id'] ?>">
                                        <input type="hidden" name="branch_id" value="<?= (int) $leave['branch_id'] ?>">
                                        <label>
                                            <span class="sr-only">Leave start</span>
                                            <input id="leave-from-<?= (int) $leave['id'] ?>" name="unavailable_from" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-datetime-submit-format="Y-m-d" data-mcc-date-picker-time-input="leave-from-time-<?= (int) $leave['id'] ?>" value="<?= esc($localDateTimePart((string) $leave['unavailable_from'], 'd-m-Y')) ?>" required>
                                            <input id="leave-from-time-<?= (int) $leave['id'] ?>" name="unavailable_from_time" type="time" value="<?= esc($localDateTimePart((string) $leave['unavailable_from'], 'H:i')) ?>" required aria-label="Leave start time">
                                        </label>
                                        <label>
                                            <span class="sr-only">Leave end</span>
                                            <input id="leave-to-<?= (int) $leave['id'] ?>" name="unavailable_to" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-datetime-submit-format="Y-m-d" data-mcc-date-picker-time-input="leave-to-time-<?= (int) $leave['id'] ?>" value="<?= esc($localDateTimePart((string) $leave['unavailable_to'], 'd-m-Y')) ?>" required>
                                            <input id="leave-to-time-<?= (int) $leave['id'] ?>" name="unavailable_to_time" type="time" value="<?= esc($localDateTimePart((string) $leave['unavailable_to'], 'H:i')) ?>" required aria-label="Leave end time">
                                        </label>
                                        <label>
                                            <span class="sr-only">Leave reason</span>
                                            <input name="reason" maxlength="255" value="<?= esc((string) ($leave['reason'] ?? '')) ?>" required>
                                        </label>
                                        <label>
                                            <span class="sr-only">Correction reason</span>
                                            <input name="correction_reason" maxlength="255" placeholder="Correction reason" required>
                                        </label>
                                        <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Save correction</button>
                                    </form>
                                    <?php if ($leaveStatus === 'PENDING'): ?>
                                        <form method="post" action="<?= site_url('coaches/leave/' . (int) $leave['id'] . '/approve') ?>">
                                            <?= csrf_field() ?>
                                            <input name="reason" maxlength="255" placeholder="Approval note (optional)">
                                            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve</button>
                                        </form>
                                        <form method="post" action="<?= site_url('coaches/leave/' . (int) $leave['id'] . '/reject') ?>">
                                            <?= csrf_field() ?>
                                            <input name="reason" required maxlength="255" placeholder="Rejection reason">
                                            <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= site_url('coaches/leave/' . (int) $leave['id'] . '/cancel') ?>">
                                        <?= csrf_field() ?>
                                        <input name="reason" required maxlength="255" placeholder="Cancellation reason">
                                            <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel leave</button>
                                    </form>
                                    <?php elseif ($canCancelOwnLeave): ?>
                                        <form method="post" action="<?= site_url('coaches/leave/' . (int) $leave['id'] . '/cancel') ?>">
                                            <?= csrf_field() ?>
                                            <input name="reason" required maxlength="255" placeholder="Cancellation reason">
                                            <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel request</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td><span class="mcc-muted">View only</span></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="mcc-card mcc-coach-related-card" aria-labelledby="availability-heading">
    <div class="mcc-card-heading">
        <h2 id="availability-heading">Availability</h2>
    </div>
    <?php if (($availability_branches ?? []) !== []): ?>
        <form class="mcc-coach-form" method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/availability') ?>">
            <?= csrf_field() ?>
            <div class="mcc-coach-form-fields">
                <div class="mcc-coach-field">
                    <label for="coach-availability-branch">Branch</label>
                    <select id="coach-availability-branch" name="branch_id" required>
                        <?php foreach ($availability_branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>"><?= esc($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-availability-weekday">Recurring weekday</label>
                    <select id="coach-availability-weekday" name="weekday">
                        <option value="">One-time date instead</option>
                        <?php foreach ($weekdays as $day => $label): ?>
                            <option value="<?= $day ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-availability-date">One-time date</label>
                    <input id="coach-availability-date" name="available_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d">
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-availability-start">Start time</label>
                    <input id="coach-availability-start" name="start_time" type="time" required>
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-availability-end">End time</label>
                    <input id="coach-availability-end" name="end_time" type="time" required>
                </div>
                <div class="mcc-coach-field">
                    <label for="coach-availability-reason">Reason</label>
                    <input id="coach-availability-reason" name="reason" maxlength="255" required>
                </div>
            </div>
            <p class="mcc-muted">Use either a recurring weekday or a one-time date.</p>
            <div class="mcc-coach-form-actions mcc-detail-actions">
                <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-clock" aria-hidden="true"></i> Save availability</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if (($coach['availability_records'] ?? []) === []): ?>
        <p class="mcc-empty-state">No availability records are available.</p>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-coach-related-table">
                <thead>
                    <tr>
                        <th scope="col">Pattern</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Time</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coach['availability_records'] as $availability): ?>
                        <?php $active = ($availability['cancelled_at'] ?? null) === null; ?>
                        <tr>
                            <th scope="row">
                                <?= $availability['available_date'] !== null
                                    ? esc($availability['available_date'])
                                    : esc($weekdays[(int) $availability['weekday']] ?? 'Unknown weekday') ?>
                            </th>
                            <td><?= esc($availability['branch_name']) ?></td>
                            <td><?= esc($displayTime($availability['start_time'] ?? null)) ?>–<?= esc($displayTime($availability['end_time'] ?? null)) ?></td>
                            <td><?= esc($availability['reason'] ?? 'Not provided') ?></td>
                            <td><?= $statusBadge($active ? 'Active' : 'Cancelled') ?></td>
                            <td>
                                <?php if ($active && ($availability_permissions[(int) $availability['id']] ?? false)): ?>
                                    <form method="post" action="<?= site_url('coaches/' . (int) $coach['id'] . '/availability') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="availability_id" value="<?= (int) $availability['id'] ?>">
                                        <input type="hidden" name="branch_id" value="<?= (int) $availability['branch_id'] ?>">
                                        <select name="weekday">
                                            <option value="">One-time date</option>
                                            <?php foreach ($weekdays as $day => $label): ?>
                                                <option value="<?= $day ?>" <?= (int) $availability['weekday'] === $day ? 'selected' : '' ?>><?= esc($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input name="available_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc((string) ($availability['available_date'] ?? '')) ?>">
                                        <input name="start_time" type="time" value="<?= esc(substr((string) $availability['start_time'], 0, 5)) ?>" required>
                                        <input name="end_time" type="time" value="<?= esc(substr((string) $availability['end_time'], 0, 5)) ?>" required>
                                        <input name="reason" maxlength="255" value="<?= esc((string) ($availability['reason'] ?? '')) ?>" required>
                                        <input name="correction_reason" maxlength="255" placeholder="Correction reason" required>
                                        <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Save correction</button>
                                    </form>
                                    <form method="post" action="<?= site_url('coaches/availability/' . (int) $availability['id'] . '/cancel') ?>">
                                        <?= csrf_field() ?>
                                        <input name="reason" maxlength="255" placeholder="Cancellation reason" required>
                                        <button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="mcc-muted">View only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>
<?= view('staff_footer') ?>
