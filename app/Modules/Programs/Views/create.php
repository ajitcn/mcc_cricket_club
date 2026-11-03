<?php

$editing = ($mode ?? 'create') === 'edit';
$program = is_array($program ?? null) ? $program : [];
$fieldErrors = session()->getFlashdata('field_errors');
$nameError = is_array($fieldErrors) ? trim((string) ($fieldErrors['name'] ?? '')) : '';
$descriptionError = is_array($fieldErrors) ? trim((string) ($fieldErrors['description'] ?? '')) : '';
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4 mcc-program-batch-form-page mcc-program-form-page">
    <section class="mcc-card mcc-program-batch-form-card mcc-program-form-card" aria-labelledby="program-form-heading">
        <div class="mcc-card-heading mcc-program-batch-form-heading">
            <div>
                <span class="mcc-eyebrow">Coaching</span>
                <h1 id="program-form-heading"><?= $editing ? 'Edit program' : 'Create program' ?></h1>
                <p class="mcc-card-subtitle"><?= $editing ? 'Update the Program name without changing related Batches.' : 'Add a program that can be selected for batches.' ?></p>
            </div>
        </div>

        <form method="post" action="<?= site_url($editing ? 'programs/' . (int) $program['id'] : 'programs') ?>" class="mcc-program-batch-form">
            <?= csrf_field() ?>
            <div class="mcc-program-batch-form-fields mcc-program-form-fields">
                <div class="mcc-form-field">
                    <label for="program-name">Name <span aria-hidden="true">*</span></label>
                    <input
                        class="form-control"
                        id="program-name"
                        name="name"
                        type="text"
                        maxlength="150"
                        value="<?= esc((string) old('name', $program['name'] ?? '')) ?>"
                        aria-describedby="program-name-error"
                        required
                    >
                    <?php if ($nameError !== ''): ?>
                        <p class="mcc-field-error" id="program-name-error" role="alert"><?= esc($nameError) ?></p>
                    <?php endif; ?>
                </div>
                <div class="mcc-form-field">
                    <label for="program-description">
                        Description <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <textarea
                        class="form-control"
                        id="program-description"
                        name="description"
                        rows="5"
                        maxlength="5000"
                        aria-describedby="program-description-error"
                    ><?= esc((string) old('description', $program['description'] ?? '')) ?></textarea>
                    <?php if ($descriptionError !== ''): ?>
                        <p class="mcc-field-error" id="program-description-error" role="alert">
                            <?= esc($descriptionError) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (! empty($error)): ?>
                <p class="mcc-alert mcc-alert-danger mcc-program-batch-form-response" role="alert">
                    <?= esc($error) ?>
                </p>
            <?php endif; ?>
            <div class="mcc-program-batch-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <?= $editing ? 'Save changes' : 'Save program' ?>
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('programs') ?>">Cancel</a>
            </div>
        </form>
    </section>
</main>
<?= view('staff_footer') ?>
