<?php

$statusLabels = [
    'active' => 'Active',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$status = (string) ($plan['status'] ?? '');
$completed = $status === 'completed';
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static function (string $field) use ($fieldErrors): string {
    return trim((string) ($fieldErrors[$field] ?? ''));
};
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4 mcc-development-page mcc-development-detail-page">
    <section class="mcc-card mcc-development-overview" aria-labelledby="development-detail-heading">
        <div class="mcc-card-heading mcc-development-detail-heading">
            <div>
                <span class="mcc-eyebrow">Player development</span>
                <h1 id="development-detail-heading"><?= esc($plan['title']) ?></h1>
                <p class="mcc-card-subtitle">
                    <?= esc($plan['player_name'] ?? ('Player #' . (int) $plan['player_id'])) ?> -
                    <?= esc($plan['branch_name'] ?? ('Branch #' . (int) $plan['branch_id'])) ?>
                </p>
            </div>
            <span class="mcc-badge mcc-badge-<?= $status === 'active' ? 'success' : ($status === 'cancelled' ? 'danger' : 'neutral') ?> mcc-development-plan-status">
                <?= esc($statusLabels[$status] ?? $status) ?>
            </span>
        </div>

        <?php if ($message): ?>
            <div class="mcc-alert mcc-alert-success" role="status"><?= esc($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>
        <?php if ($completed): ?>
            <div class="mcc-alert mcc-alert-info" role="status">
                Completed development plans cannot be edited or extended.
            </div>
        <?php endif; ?>

        <dl class="mcc-development-summary">
            <div>
                <dt>Target date</dt>
                <dd><?= esc(mcc_user_date($plan['target_date'] ?? null, 'Not set')) ?></dd>
            </div>
            <div>
                <dt>Coach</dt>
                <dd><?= esc($plan['coach_name'] ?? ('Coach #' . (int) $plan['coach_user_id'])) ?></dd>
            </div>
            <div>
                <dt>Last updated</dt>
                <dd><?= esc($plan['updated_at'] ?? 'Not available') ?></dd>
            </div>
        </dl>

        <?php if (! empty($plan['notes'])): ?>
            <div class="mcc-development-notes">
                <h2>Notes</h2>
                <p><?= nl2br(esc($plan['notes'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="mcc-form-actions mcc-development-actions mcc-development-detail-actions">
            <?php if (! $completed): ?>
                <a class="mcc-button mcc-button-primary" href="<?= site_url('development/' . (int) $plan['id'] . '/edit') ?>">Edit plan</a>
            <?php endif; ?>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('development') ?>">Back to plans</a>
        </div>
    </section>

    <section class="mcc-card mcc-development-goals-section" aria-labelledby="development-goals-heading">
        <div class="mcc-card-heading mcc-development-goals-heading">
            <div>
                <span class="mcc-eyebrow">Targets</span>
                <h2 id="development-goals-heading">Goals and progress history</h2>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php foreach ($plan['goals'] as $goal): ?>
            <article class="mcc-development-goal">
                <div class="mcc-card-heading mcc-development-goal-heading">
                    <div>
                        <h3><?= esc($goal['goal']) ?></h3>
                        <p class="mcc-card-subtitle">
                            Current progress: <?= esc((string) ($goal['progress_value'] ?? 'Not recorded')) ?>
                            - Version <?= (int) ($goal['version'] ?? 1) ?>
                        </p>
                    </div>
                    <span class="mcc-badge mcc-badge-info"><?= esc($goal['status']) ?></span>
                </div>

                <div class="mcc-table-wrap">
                    <table class="mcc-table mcc-development-history-table">
                        <caption class="visually-hidden">Progress history for <?= esc($goal['goal']) ?></caption>
                        <thead>
                            <tr>
                                <th scope="col">Recorded</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($goal['history'] as $entry): ?>
                                <tr>
                                    <th scope="row" data-label="Recorded"><?= esc($entry['recorded_at']) ?></th>
                                    <td data-label="Progress"><?= esc((string) ($entry['progress_value'] ?? 'Not recorded')) ?></td>
                                    <td data-label="Note"><?= esc($entry['note'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($goal['history'] === []): ?>
                                <tr>
                                    <td colspan="3" class="mcc-empty-state">No progress has been recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (! $completed): ?>
                    <form
                        method="post"
                        action="<?= site_url('development/goals/' . (int) $goal['id'] . '/progress') ?>"
                        class="mcc-form-grid mcc-development-progress-form"
                        data-development-submit-form
                    >
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) ($goal['version'] ?? 1) ?>">
                    <div class="mcc-form-field">
                        <label for="development-progress-<?= (int) $goal['id'] ?>">Progress value</label>
                        <input
                            class="form-control"
                            id="development-progress-<?= (int) $goal['id'] ?>"
                            name="progress_value"
                            inputmode="decimal"
                            pattern="[0-9]+(\.[0-9]+)?"
                            placeholder="Optional numeric value"
                            value="<?= esc(old('progress_value', '')) ?>"
                            aria-describedby="development-progress-error-<?= (int) $goal['id'] ?>"
                        >
                        <?php if ($fieldError('progress_value') !== ''): ?>
                            <p class="mcc-field-error" id="development-progress-error-<?= (int) $goal['id'] ?>" role="alert">
                                <?= esc($fieldError('progress_value')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="mcc-form-field">
                        <label for="development-progress-note-<?= (int) $goal['id'] ?>">Progress note</label>
                        <input
                            class="form-control"
                            id="development-progress-note-<?= (int) $goal['id'] ?>"
                            name="note"
                            maxlength="10000"
                            value="<?= esc(old('note', '')) ?>"
                        >
                    </div>
                    <div class="mcc-form-actions mcc-development-actions">
                        <div class="mcc-alert mcc-development-form-feedback" data-development-response hidden role="status"></div>
                        <button class="mcc-button mcc-button-primary" type="submit">Record progress</button>
                    </div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($plan['goals'] === []): ?>
            <p class="mcc-empty-state">
                <i class="fa-solid fa-bullseye mcc-empty-icon" aria-hidden="true"></i>
                <strong>No goals added yet.</strong>
                <span>Add the first goal below.</span>
            </p>
        <?php endif; ?>

        <?php if (! $completed): ?>
            <form
                method="post"
                action="<?= site_url('development/' . (int) $plan['id'] . '/goals') ?>"
                class="mcc-form-grid mcc-development-goal-form"
                data-development-submit-form
            >
            <?= csrf_field() ?>
            <div class="mcc-form-field mcc-form-field-wide">
                <label for="development-goal">New goal</label>
                <input
                    class="form-control"
                    id="development-goal"
                    name="goal"
                    maxlength="255"
                    value="<?= esc(old('goal', '')) ?>"
                    aria-describedby="development-goal-error"
                    required
                >
                <?php if ($fieldError('goal') !== ''): ?>
                    <p class="mcc-field-error" id="development-goal-error" role="alert">
                        <?= esc($fieldError('goal')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-field">
                <label for="development-goal-target-date">Goal target date</label>
                <input
                    class="form-control"
                    id="development-goal-target-date"
                    name="target_date"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    data-mcc-date-picker
                    data-mcc-date-picker-no-toggle
                    data-mcc-date-format="d-m-Y"
                    data-mcc-date-submit-format="Y-m-d"
                    value="<?= esc(mcc_user_date_input(old('target_date', null))) ?>"
                    aria-describedby="development-goal-target-date-error"
                >
                <?php if ($fieldError('target_date') !== ''): ?>
                    <p class="mcc-field-error" id="development-goal-target-date-error" role="alert">
                        <?= esc($fieldError('target_date')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-actions mcc-development-actions">
                <div class="mcc-alert mcc-development-form-feedback" data-development-response hidden role="status"></div>
                <button class="mcc-button mcc-button-primary" type="submit">Add goal</button>
            </div>
            </form>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
