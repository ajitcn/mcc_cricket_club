<?php

$award = is_array($award ?? null) ? $award : [];
$achievements = is_array($achievements ?? null) ? $achievements : [];
$branches = is_array($branches ?? null) ? $branches : [];
$recipients = is_array($recipients ?? null)
    ? $recipients
    : ['players' => [], 'coaches' => []];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn (string $key): string => trim((string) ($fieldErrors[$key] ?? ''));
$isEdit = (bool) ($isEdit ?? false);
$activeBranchId = ($activeBranchId ?? null) === null ? null : (int) $activeBranchId;
$value = static fn (string $key, mixed $fallback = '') => old($key, $award[$key] ?? $fallback);
$selectedBranch = (string) old('branch_id', $award['branch_id'] ?? ($activeBranchId ?? ''));
$recipientType = (string) $value('recipient_type', 'player');
if (! in_array($recipientType, ['player', 'coach'], true)) {
    $recipientType = 'player';
}
$recipientId = old('recipient_id');
if ($recipientId === null || $recipientId === '') {
    $recipientId = $recipientType === 'coach'
        ? ($award['coach_id'] ?? '')
        : ($award['player_id'] ?? '');
}
$action = $isEdit
    ? site_url('achievements/awards/' . (int) $award['id'])
    : site_url('achievements/awards');
$heading = $isEdit ? 'Edit Achievement Award' : 'Create Achievement Award';
?>
<?= view('staff_header', [
    'title' => $heading,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . esc(site_url('achievements/awards')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to Awards</span></a>',
]) ?>
<div class="mcc-achievements-page mcc-achievement-award-form-page">
    <section class="mcc-card mcc-scheduling-card mcc-achievement-award-card" aria-labelledby="award-form-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Award details</span>
                <h2 id="award-form-heading"><?= esc($heading) ?></h2>
            </div>
            <span class="mcc-badge mcc-badge-success">Final award</span>
        </div>

        <form
            method="post"
            action="<?= esc($action) ?>"
            class="mcc-form-grid mcc-achievement-award-form"
            data-achievement-award-form
        >
            <?= csrf_field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
            <?php endif; ?>

            <div class="mcc-form-field">
                <label for="award-branch">1. Branch</label>
                <select
                    class="form-control"
                    id="award-branch"
                    name="branch_id"
                    data-award-branch
                    required
                    aria-describedby="award-branch-help<?= $fieldError('branch_id') !== '' ? ' award-branch-error' : '' ?>"
                >
                    <option value="">Select a branch</option>
                    <?php foreach ($branches as $branch): ?>
                        <option
                            value="<?= (int) $branch['id'] ?>"
                            <?= $selectedBranch === (string) $branch['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small id="award-branch-help">Only branches you are authorized to manage are listed.</small>
                <?php if ($fieldError('branch_id') !== ''): ?>
                    <p class="mcc-field-error" id="award-branch-error" role="alert">
                        <?= esc($fieldError('branch_id')) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mcc-form-field">
                <label for="award-achievement">2. Achievement</label>
                <select
                    class="form-control selectpicker btSelect"
                    id="award-achievement"
                    name="achievement_id"
                    data-bt-select
                    data-live-search="true"
                    data-actions-box="true"
                    title="Select an Achievement"
                    data-award-achievement-selector
                    required
                    aria-describedby="award-achievement-help<?= $fieldError('achievement_id') !== '' ? ' award-achievement-error' : '' ?>"
                >
                    <option value="">Select an active Achievement</option>
                    <?php foreach ($achievements as $item): ?>
                        <option
                            value="<?= (int) $item['id'] ?>"
                            data-branch-id="<?= (int) $item['branch_id'] ?>"
                            <?= (string) $value('achievement_id') === (string) $item['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($item['title'] . ' · ' . $item['category']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($achievements === []): ?>
                        <option value="" disabled>No active Achievements are available.</option>
                    <?php endif; ?>
                </select>
                <small id="award-achievement-help">Search the full list of active Achievements you can use.</small>
                <p class="mcc-form-help" data-award-achievement-empty hidden role="status">
                    No active Achievements are available for this branch.
                    <a href="<?= site_url('achievements/new') ?>">Create an Achievement</a>
                </p>
                <?php if ($fieldError('achievement_id') !== ''): ?>
                    <p class="mcc-field-error" id="award-achievement-error" role="alert">
                        <?= esc($fieldError('achievement_id')) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mcc-form-field">
                <label for="award-type">3. Recipient type</label>
                <select
                    class="form-control"
                    id="award-type"
                    name="recipient_type"
                    data-award-recipient-type
                    required
                    aria-describedby="award-type-help<?= $fieldError('recipient_type') !== '' ? ' award-type-error' : '' ?>"
                >
                    <option value="player" <?= $recipientType === 'player' ? 'selected' : '' ?>>Player</option>
                    <option value="coach" <?= $recipientType === 'coach' ? 'selected' : '' ?>>Coach</option>
                </select>
                <small id="award-type-help">Choose Player or Coach. Exactly one recipient is required.</small>
                <?php if ($fieldError('recipient_type') !== ''): ?>
                    <p class="mcc-field-error" id="award-type-error" role="alert">
                        <?= esc($fieldError('recipient_type')) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mcc-form-field" data-award-recipient-field>
                <label for="award-player">4. Player recipient</label>
                <select
                    class="form-control selectpicker btSelect"
                    id="award-player"
                    name="recipient_id"
                    data-bt-select
                    data-live-search="true"
                    data-actions-box="true"
                    data-award-recipient-selector
                    data-recipient-type="player"
                    title="Select a Player"
                    <?= $recipientType === 'player' ? 'required' : 'disabled' ?>
                    aria-describedby="award-recipient-help<?= $fieldError('recipient_id') !== '' ? ' award-recipient-error' : '' ?>"
                >
                    <option value="">Select an active Player</option>
                    <?php foreach (($recipients['players'] ?? []) as $recipient): ?>
                        <option
                            value="<?= (int) $recipient['id'] ?>"
                            data-branch-id="<?= (int) $recipient['branch_id'] ?>"
                            <?= $recipientType === 'player' && (string) $recipientId === (string) $recipient['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($recipient['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (($recipients['players'] ?? []) === []): ?>
                        <option value="" disabled>No active Players are available.</option>
                    <?php endif; ?>
                </select>
                <small id="award-recipient-help">Only active Players in the selected branch are shown.</small>
            </div>

            <div class="mcc-form-field" data-award-recipient-field>
                <label for="award-coach">4. Coach recipient</label>
                <select
                    class="form-control selectpicker btSelect"
                    id="award-coach"
                    name="recipient_id"
                    data-bt-select
                    data-live-search="true"
                    data-actions-box="true"
                    data-award-recipient-selector
                    data-recipient-type="coach"
                    title="Select a Coach"
                    <?= $recipientType === 'coach' ? 'required' : 'disabled' ?>
                    aria-describedby="award-recipient-help<?= $fieldError('recipient_id') !== '' ? ' award-recipient-error' : '' ?>"
                >
                    <option value="">Select an active Coach</option>
                    <?php foreach (($recipients['coaches'] ?? []) as $recipient): ?>
                        <option
                            value="<?= (int) $recipient['id'] ?>"
                            data-branch-id="<?= (int) $recipient['branch_id'] ?>"
                            <?= $recipientType === 'coach' && (string) $recipientId === (string) $recipient['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($recipient['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (($recipients['coaches'] ?? []) === []): ?>
                        <option value="" disabled>No active Coaches are available.</option>
                    <?php endif; ?>
                </select>
                <small>Only active Coaches in the selected branch are shown.</small>
            </div>

            <?php if ($fieldError('recipient_id') !== ''): ?>
                <p class="mcc-field-error mcc-form-field-wide" id="award-recipient-error" role="alert">
                    <?= esc($fieldError('recipient_id')) ?>
                </p>
            <?php endif; ?>

            <div class="mcc-form-field mcc-form-field-wide">
                <label for="award-note">5. Nomination note <span class="mcc-muted">(optional)</span></label>
                <textarea
                    class="form-control"
                    id="award-note"
                    name="nomination_note"
                    maxlength="2000"
                    rows="5"
                    aria-describedby="award-note-help<?= $fieldError('nomination_note') !== '' ? ' award-note-error' : '' ?>"
                ><?= esc((string) $value('nomination_note')) ?></textarea>
                <small id="award-note-help">Add context for the award. Keep the note to 2,000 characters or fewer.</small>
                <?php if ($fieldError('nomination_note') !== ''): ?>
                    <p class="mcc-field-error" id="award-note-error" role="alert">
                        <?= esc($fieldError('nomination_note')) ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($isEdit): ?>
                <div class="mcc-form-field mcc-form-field-wide">
                    <label for="award-reason">Correction reason</label>
                    <textarea
                        class="form-control"
                        id="award-reason"
                        name="correction_reason"
                        maxlength="1000"
                        rows="3"
                        required
                    ><?= esc((string) $value('correction_reason')) ?></textarea>
                    <small>Explain what was corrected and why.</small>
                </div>
            <?php endif; ?>

            <?php if (! empty($error)): ?>
                <div class="mcc-alert mcc-alert-danger mcc-form-field-wide" role="alert">
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>

            <div class="mcc-form-actions mcc-form-field-wide mcc-achievement-award-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <?= $isEdit ? 'Save changes' : 'Save Award' ?>
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/awards') ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>
<?= view('staff_footer') ?>
