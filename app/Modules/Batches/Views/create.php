<?php

$batch = is_array($batch ?? null) ? $batch : [];
$editing = ($mode ?? 'create') === 'edit';
$isActive = (string) old('is_active', (string) ($batch['is_active'] ?? '1')) === '1';
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4 mcc-program-batch-form-page">
    <section class="mcc-card mcc-program-batch-form-card" aria-labelledby="batch-form-heading">
        <div class="mcc-card-heading mcc-program-batch-form-heading">
            <div>
                <span class="mcc-eyebrow">Coaching</span>
                <h1 id="batch-form-heading"><?= $editing ? 'Edit batch' : 'Create batch' ?></h1>
                <p class="mcc-card-subtitle">
                    <?= $editing ? 'Update this global Batch master record.' : 'Create a global Batch master record.' ?>
                </p>
            </div>
        </div>

        <form
            method="post"
            action="<?= site_url($editing ? 'batches/' . (int) $batch['id'] : 'batches') ?>"
            class="mcc-program-batch-form"
        >
            <?= csrf_field() ?>
            <fieldset class="mcc-admission-subsection">
                <legend>Batch Details</legend>
                <div class="mcc-program-batch-form-fields">
                    <div class="mcc-form-field">
                        <label for="batch-name">Name <span aria-hidden="true">*</span></label>
                        <input
                            class="form-control"
                            id="batch-name"
                            name="name"
                            type="text"
                            maxlength="150"
                            value="<?= esc((string) old('name', $batch['name'] ?? '')) ?>"
                            required
                        >
                    </div>
                    <div class="mcc-form-field">
                        <label for="batch-status">Status</label>
                        <select
                            class="form-control"
                            id="batch-status"
                            name="is_active"
                        >
                            <option value="1" <?= $isActive ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $isActive ? '' : 'selected' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </fieldset>
            <?php if (! empty($error)): ?>
                <p class="mcc-alert mcc-alert-danger mcc-program-batch-form-response" role="alert"><?= esc($error) ?></p>
            <?php endif; ?>
            <div class="mcc-program-batch-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <?= $editing ? 'Save changes' : 'Save batch' ?>
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('batches') ?>">Cancel</a>
            </div>
        </form>
    </section>
</main>
<?= view('staff_footer') ?>
