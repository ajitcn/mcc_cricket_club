<?php
$achievement = is_array($achievement ?? null) ? $achievement : [];
$branches = is_array($branches ?? null) ? $branches : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn (string $key): string => trim((string) ($fieldErrors[$key] ?? ''));
$isEdit = (bool) ($isEdit ?? false);
$value = static function (string $key, mixed $fallback = '') use ($achievement, $isEdit): mixed {
    $oldValue = old($key);
    if ($oldValue !== null && $oldValue !== '') {
        return $oldValue;
    }

    return $achievement[$key] ?? $fallback;
};
$heading = $isEdit ? 'Edit Achievement' : 'Create Achievement';
$action = $isEdit
    ? site_url('achievements/' . (int) ($achievement['id'] ?? 0))
    : site_url('achievements');
?>
<?= view('staff_header', [
    'title' => $heading,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . esc(site_url('achievements')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to catalog</span></a>',
]) ?>
<div class="mcc-achievements-page" id="main-content">
    <section class="mcc-card mcc-scheduling-card mcc-achievement-form-card" aria-labelledby="achievement-form-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Achievements</span>
                <h2 id="achievement-form-heading"><?= esc($heading) ?></h2>
            </div>
        </div>
        <form method="post" action="<?= esc($action) ?>" class="mcc-form-grid">
            <?= csrf_field() ?>
            <div class="mcc-form-field mcc-form-field-wide">
                <label for="achievement-title">Title <span aria-hidden="true">*</span></label>
                <input class="form-control" id="achievement-title" name="title" maxlength="150" value="<?= esc((string) $value('title')) ?>" required>
            </div>
            <?php if ($fieldError('title') !== ''): ?>
                <p class="mcc-field-error" role="alert"><?= esc($fieldError('title')) ?></p>
            <?php endif; ?>
            <div class="mcc-form-field">
                <label for="achievement-branch">Branch <span aria-hidden="true">*</span></label>
                <select class="form-control" id="achievement-branch" name="branch_id" required>
                    <option value="">Select a branch</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (string) $value('branch_id') === (string) $branch['id'] ? 'selected' : '' ?>>
                            <?= esc($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($fieldError('branch_id') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('branch_id')) ?></p><?php endif; ?>
            <div class="mcc-form-field">
                <label for="achievement-category">Category <span aria-hidden="true">*</span></label>
                <input class="form-control" id="achievement-category" name="category" maxlength="100" value="<?= esc((string) $value('category')) ?>" required>
            </div>
            <?php if ($fieldError('category') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('category')) ?></p><?php endif; ?>
            <div class="mcc-form-field mcc-form-field-wide">
                <label for="achievement-description">Description</label>
                <textarea class="form-control" id="achievement-description" name="description" maxlength="5000" rows="4"><?= esc((string) $value('description')) ?></textarea>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-status">Status <span aria-hidden="true">*</span></label>
                <select class="form-control" id="achievement-status" name="is_active" required>
                    <option value="1" <?= (string) $value('is_active', '1') === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (string) $value('is_active', '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php if (! empty($error)): ?><div class="mcc-alert mcc-alert-danger mcc-form-field-wide" role="alert"><?= esc($error) ?></div><?php endif; ?>
            <div class="mcc-form-actions mcc-form-field-wide">
                <button class="mcc-button mcc-button-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Achievement' ?></button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements') ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>
<?= view('staff_footer') ?>
