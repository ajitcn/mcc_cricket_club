<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$sessions = is_array($result['sessions'] ?? null) ? $result['sessions'] : [];
$queryFilters = array_filter(
    $filters,
    static fn($value, $key): bool => $key !== 'page' && $value !== null && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$pageUrl = static function (int $targetPage) use ($queryFilters, $coach): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('coaches/' . (int) $coach['id'] . '/sessions')
        . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', [
    'title' => 'Coach sessions',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>
<div class="mcc-coach-detail-page">
    <section class="mcc-card mcc-detail-hero mcc-coach-detail-heading" aria-labelledby="coach-sessions-heading">
        <div>
            <span class="mcc-eyebrow">Coach profile</span>
            <h2 id="coach-sessions-heading"><?= esc($coach['display_name']) ?> · Sessions</h2>
            <p class="mcc-muted">Only sessions assigned to this coach in your authorized branches are shown.</p>
            <div class="mcc-detail-badges" aria-label="Session summary">
                <span class="mcc-badge mcc-badge-info">
                    <?= (int) ($result['total'] ?? 0) ?> assigned
                </span>
            </div>
        </div>
        <div class="mcc-detail-actions" aria-label="Coach sessions actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('coaches/' . (int) $coach['id']) ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span class="mcc-button-label">Back to coach</span>
            </a>
        </div>
    </section>
    <section class="mcc-card" aria-labelledby="session-filter-heading">
        <div class="mcc-card-heading">
            <h2 id="session-filter-heading">Filter sessions</h2>
        </div>
        <form method="get" class="mcc-coach-filter-form" aria-label="Filter coach sessions">
            <div class="mcc-coach-field">
                <label for="coach-session-status">Status</label>
                <select class="form-control" id="coach-session-status" name="status">
                    <option value="">All statuses</option>
                    <option value="scheduled" <?= ($filters['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <?php if (count($result['branches'] ?? []) > 1): ?>
                <div class="mcc-coach-field">
                    <label for="coach-session-branch">Branch</label>
                    <select class="form-control" id="coach-session-branch" name="branch_id">
                        <option value="">All authorized branches</option>
                        <?php foreach (($result['branches'] ?? []) as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                                <?= esc($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mcc-coach-filter-actions">
                <button class="mcc-button mcc-button-secondary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                </button>
                <a class="mcc-button mcc-button-ghost" href="<?= site_url('coaches/' . (int) $coach['id'] . '/sessions') ?>">Clear</a>
            </div>
        </form>
    </section>
    <section class="mcc-card mcc-coach-related-card" aria-labelledby="session-list-heading">
        <div class="mcc-card-heading">
            <h2 id="session-list-heading">Assigned sessions</h2>
            <span class="mcc-muted">Filtered session list</span>
        </div>
        <?php if ($sessions === []): ?>
            <p class="mcc-empty-state">No sessions are assigned to this coach in the selected scope.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-coach-related-table">
                    <caption class="visually-hidden">Sessions assigned to <?= esc($coach['display_name']) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Session</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Program</th>
                            <th scope="col">Starts</th>
                            <th scope="col">Ends</th>
                            <th scope="col">Status</th>
                            <th scope="col">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?= site_url('coaches/' . (int) $coach['id'] . '/sessions/' . (int) $session['id']) ?>">
                                        <?= esc($session['batch_name']) ?>
                                    </a>
                                </th>
                                <td><?= esc($session['branch_name']) ?></td>
                                <td><?= esc($session['program_name'] ?? 'Not assigned') ?></td>
                                <td><?= esc(mcc_user_datetime($session['starts_at'] ?? null)) ?></td>
                                <td><?= esc(mcc_user_datetime($session['ends_at'] ?? null)) ?></td>
                                <td><span class="mcc-badge mcc-badge-neutral"><?= esc(ucfirst((string) $session['status'])) ?></span></td>
                                <td>
                                    <?php $rosterCount = (int) ($session['roster_snapshot_count'] ?? 0); ?>
                                    <?php $recordedCount = (int) ($session['attendance_recorded_count'] ?? 0); ?>
                                    <span class="mcc-badge mcc-badge-neutral">
                                        <?= $rosterCount === 0 ? 'Not prepared' : ($recordedCount >= $rosterCount ? 'Recorded' : 'Partial') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ((int) ($result['pages'] ?? 1) > 1): ?>
            <nav class="mcc-pagination" aria-label="Coach session pages">
                <span>Page <?= (int) ($result['page'] ?? 1) ?> of <?= (int) ($result['pages'] ?? 1) ?></span>
                <div>
                    <?php if ((int) ($result['page'] ?? 1) > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ((int) ($result['page'] ?? 1) < (int) ($result['pages'] ?? 1)): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>
<?= view('staff_footer') ?>
