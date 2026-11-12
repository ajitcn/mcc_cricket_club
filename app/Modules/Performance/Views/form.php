<?php

$editing = is_array($record ?? null);
$record = $editing ? $record : [];
$options = is_array($formOptions ?? null) ? $formOptions : [];
$branches = is_array($options['branches'] ?? null) ? $options['branches'] : [];
$players = is_array($options['players'] ?? null) ? $options['players'] : [];
$sessions = is_array($options['sessions'] ?? null) ? $options['sessions'] : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldError = static function (string $field) use ($fieldErrors): string {
    return is_array($fieldErrors) ? trim((string) ($fieldErrors[$field] ?? '')) : '';
};
$value = static function (string $field, mixed $fallback = '') use ($record): string {
    return (string) old($field, $record[$field] ?? $fallback);
};
$action = $editing
    ? site_url('performance/' . (int) ($record['id'] ?? 0))
    : site_url('performance');
$metrics = [
    'runs', 'balls_faced', 'dismissals', 'fours', 'sixes', 'wickets',
    'runs_conceded', 'legal_balls', 'catches', 'stumpings', 'run_outs',
];
$metricTypes = [
    'runs' => ['batting'],
    'balls_faced' => ['batting'],
    'dismissals' => ['batting'],
    'fours' => ['batting'],
    'sixes' => ['batting'],
    'wickets' => ['bowling'],
    'runs_conceded' => ['bowling'],
    'legal_balls' => ['bowling'],
    'catches' => ['fielding'],
    'stumpings' => ['fielding'],
    'run_outs' => ['fielding'],
];
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('performance') . '">'
        . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to performance</span></a>',
]) ?>
<div class="mcc-scheduling-card mcc-performance-form-page">
    <form method="post" action="<?= esc($action) ?>" class="mcc-performance-form">
        <?= csrf_field() ?>
        <section class="mcc-card mcc-session-form-section" aria-labelledby="performance-form-heading">
            <div class="mcc-session-form-section-heading">
                <div>
                    <h3 id="performance-form-heading"><?= $editing ? 'Edit performance record' : 'Performance details' ?></h3>
                    <p>Capture verified batting, bowling, or fielding metrics for an authorized Player.</p>
                </div>
            </div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field">
                    <label for="performance-branch">Branch <span aria-hidden="true">*</span></label>
                    <select class="form-control" id="performance-branch" name="branch_id" data-performance-branch required>
                        <option value="">Choose branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= $value('branch_id') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc((string) $branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('branch_id') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('branch_id')) ?></p><?php endif; ?>
                </div>
                <div class="mcc-session-form-field">
                    <label for="performance-player">Player <span aria-hidden="true">*</span></label>
                    <select class="form-control" id="performance-player" name="player_id" data-performance-player required>
                        <option value="">Choose Player</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= (int) $player['id'] ?>" data-branch-id="<?= (int) $player['branch_id'] ?>" <?= $value('player_id') === (string) $player['id'] ? 'selected' : '' ?>><?= esc((string) $player['full_name']) ?> · <?= esc((string) $player['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('player_id') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('player_id')) ?></p><?php endif; ?>
                </div>
                <div class="mcc-session-form-field">
                    <label for="performance-session">Session <span class="mcc-form-optional">(optional)</span></label>
                    <select class="form-control" id="performance-session" name="session_id" data-performance-session>
                        <option value="">No linked session</option>
                        <?php foreach ($sessions as $session): ?>
                            <?php $sessionDate = date_create((string) ($session['starts_at'] ?? '')); ?>
                            <option value="<?= (int) $session['id'] ?>" data-branch-id="<?= (int) $session['branch_id'] ?>" <?= $value('session_id') === (string) $session['id'] ? 'selected' : '' ?>>#<?= (int) $session['id'] ?> · <?= esc($sessionDate ? $sessionDate->format('d-m-Y h:i A') : 'Date unavailable') ?> · <?= esc((string) $session['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-session-form-field">
                    <label for="performance-recorded-on">Recorded on <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="performance-recorded-on" name="recorded_on" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" required value="<?= esc(mcc_user_date_input($value('recorded_on', date('Y-m-d')))) ?>">
                </div>
                <div class="mcc-session-form-field">
                    <label for="performance-record-type">Record type <span aria-hidden="true">*</span></label>
                    <select class="form-control" id="performance-record-type" name="record_type" required>
                        <option value="">Choose type</option>
                        <?php if (! $editing): ?>
                            <option value="all" <?= $value('record_type') === 'all' ? 'selected' : '' ?>>All types</option>
                        <?php endif; ?>
                        <?php foreach (['batting', 'bowling', 'fielding'] as $type): ?>
                            <option value="<?= esc($type) ?>" <?= $value('record_type') === $type ? 'selected' : '' ?>><?= esc(ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="performance-metrics-heading">
            <div class="mcc-session-form-section-heading">
                <div>
                    <h3 id="performance-metrics-heading">Performance metrics</h3>
                    <p>Complete only the metrics relevant to the selected record type.</p>
                </div>
            </div>
            <div class="mcc-session-create-grid mcc-performance-metrics-grid">
                <?php foreach ($metrics as $metric): ?>
                    <?php if ($metric === 'runs'): ?>
                        <div class="mcc-performance-metric-group-title">Batting metrics</div>
                    <?php elseif ($metric === 'wickets'): ?>
                        <div class="mcc-performance-metric-group-title">Bowling metrics</div>
                    <?php elseif ($metric === 'catches'): ?>
                        <div class="mcc-performance-metric-group-title">Fielding metrics</div>
                    <?php endif; ?>
                    <div
                        class="mcc-session-form-field"
                        data-performance-metric
                        data-performance-types="<?= esc(implode(',', $metricTypes[$metric] ?? [])) ?>"
                    >
                        <label for="performance-<?= esc($metric) ?>"><?= esc(ucwords(str_replace('_', ' ', $metric))) ?> <span class="mcc-form-optional">(optional)</span></label>
                        <input class="form-control" id="performance-<?= esc($metric) ?>" name="<?= esc($metric) ?>" type="number" min="0" step="1" inputmode="numeric" value="<?= esc($value($metric)) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="performance-notes-heading">
            <div class="mcc-session-form-section-heading">
                <div>
                    <h3 id="performance-notes-heading">Coach notes</h3>
                    <p>Add optional context for this performance record.</p>
                </div>
            </div>
            <div class="mcc-session-form-field">
                <label for="performance-notes">Notes <span class="mcc-form-optional">(optional)</span></label>
                <textarea class="form-control" id="performance-notes" name="notes" rows="5" maxlength="5000"><?= esc($value('notes')) ?></textarea>
            </div>
            <?php if ($editing): ?>
                <div class="mcc-session-form-field">
                    <label for="performance-correction-reason">Correction reason <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="performance-correction-reason" name="correction_reason" maxlength="255" required value="<?= esc((string) old('correction_reason', '')) ?>">
                </div>
            <?php endif; ?>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><?= $editing ? 'Save changes' : 'Save performance' ?></button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('performance') ?>">Cancel</a>
            </div>
        </section>
    </form>
</div>
<?= view('staff_footer') ?>
