<?php
$achievement = is_array($achievement ?? null) ? $achievement : [];
$branches = is_array($branches ?? null) ? $branches : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn (string $key): string => trim((string) ($fieldErrors[$key] ?? ''));
$value = static fn (string $key, mixed $fallback = ''): mixed => old($key, $achievement[$key] ?? $fallback);
?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . esc(site_url('achievements')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to catalog</span></a>',
]) ?>
<main class="container py-4 mcc-achievements-page">
    <section class="mcc-card" aria-labelledby="achievement-form-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Achievements</span>
                <h2 id="achievement-form-heading">Create Achievement</h2>
                <p class="mcc-card-subtitle">Configure a branch-scoped title for future award workflows.</p>
            </div>
        </div>
        <form method="post" action="<?= site_url('achievements') ?>" class="mcc-form-grid">
            <?= csrf_field() ?>
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
            <div class="mcc-form-field">
                <label for="achievement-title">Title <span aria-hidden="true">*</span></label>
                <input class="form-control" id="achievement-title" name="title" maxlength="150" value="<?= esc((string) $value('title')) ?>" required>
            </div>
            <?php if ($fieldError('title') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('title')) ?></p><?php endif; ?>
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
                <button class="mcc-button mcc-button-primary" type="submit">Create Achievement</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements') ?>">Cancel</a>
            </div>
        </form>
    </section>
</main>
<?= view('staff_footer') ?>
