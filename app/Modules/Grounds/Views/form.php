<?php

$isEdit = (bool) ($isEdit ?? false);
$ground = is_array($ground ?? null) ? $ground : [];
$branches = is_array($branches ?? null) ? $branches : [];
$action = $isEdit
    ? site_url('grounds/' . (int) ($ground['id'] ?? 0))
    : site_url('grounds');
$heading = $isEdit ? 'Edit ground' : 'Create ground';
$old = static function (string $key, mixed $fallback = '') use ($ground): mixed {
    return old($key, $ground[$key] ?? $fallback);
};
$selectedBranch = (string) $old('branch_id');
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4 mcc-ground-form-page">
    <section class="mcc-card mcc-ground-form-card" aria-labelledby="ground-form-heading">
        <div class="mcc-card-heading mcc-ground-form-heading">
            <div>
                <span class="mcc-eyebrow">Grounds &amp; Equipment</span>
                <h1 id="ground-form-heading"><?= esc($heading) ?></h1>
                <p class="mcc-card-subtitle">
                    Enter the ground details and choose its authorized branch.
                </p>
            </div>
        </div>

        <form
            method="post"
            action="<?= esc($action) ?>"
            class="mcc-ground-form"
            data-ground-submit-feedback
        >
            <?= csrf_field() ?>

            <div class="mcc-ground-form-section">
                <span class="mcc-eyebrow">Ground details</span>
                <div class="mcc-ground-form-fields">
                    <div class="mcc-form-field">
                        <label for="ground-branch">Branch <span aria-hidden="true">*</span></label>
                        <?php if ($isEdit): ?>
                            <input
                                class="form-control"
                                id="ground-branch"
                                value="<?= (int) ($ground['branch_id'] ?? 0) ?>"
                                readonly
                            >
                            <input
                                type="hidden"
                                name="branch_id"
                                value="<?= (int) ($ground['branch_id'] ?? 0) ?>"
                            >
                        <?php else: ?>
                            <select
                                class="form-control"
                                id="ground-branch"
                                name="branch_id"
                                required
                            >
                                <option value="" <?= $selectedBranch === '' ? 'selected' : '' ?>>
                                    Select a branch
                                </option>
                                <?php foreach ($branches as $branch): ?>
                                    <option
                                        value="<?= (int) $branch['id'] ?>"
                                        <?= $selectedBranch === (string) $branch['id'] ? 'selected' : '' ?>
                                    >
                                        <?= esc($branch['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="mcc-form-field">
                        <label for="ground-name">Name <span aria-hidden="true">*</span></label>
                        <input
                            class="form-control"
                            id="ground-name"
                            name="name"
                            maxlength="150"
                            value="<?= esc((string) $old('name')) ?>"
                            required
                        >
                    </div>

                    <div class="mcc-form-field">
                        <label for="ground-type">Type <span aria-hidden="true">*</span></label>
                        <select class="form-control" id="ground-type" name="ground_type" required>
                            <?php foreach (['indoor_net' => 'Indoor net', 'outdoor_net' => 'Outdoor net', 'venue' => 'Venue'] as $value => $label): ?>
                                <option
                                    value="<?= esc($value) ?>"
                                    <?= $old('ground_type', 'indoor_net') === $value ? 'selected' : '' ?>
                                >
                                    <?= esc($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mcc-form-field mcc-ground-form-field-wide">
                        <label for="ground-description">Description</label>
                        <textarea
                            class="form-control"
                            id="ground-description"
                            name="description"
                            maxlength="10000"
                            rows="4"
                        ><?= esc((string) $old('description')) ?></textarea>
                    </div>

                    <div class="mcc-form-field">
                        <label for="ground-status">Status <span aria-hidden="true">*</span></label>
                        <select class="form-control" id="ground-status" name="is_active" required>
                            <option value="1" <?= (int) $old('is_active', 1) === 1 ? 'selected' : '' ?>>
                                Active
                            </option>
                            <option value="0" <?= (int) $old('is_active', 1) === 0 ? 'selected' : '' ?>>
                                Inactive
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (! empty($message)): ?>
                <p class="mcc-alert mcc-alert-success mcc-ground-response" data-ground-form-feedback role="status">
                    <?= esc($message) ?>
                </p>
            <?php elseif (! empty($error)): ?>
                <p class="mcc-alert mcc-alert-danger mcc-ground-response" data-ground-form-feedback role="alert">
                    <?= esc($error) ?>
                </p>
            <?php else: ?>
                <p class="mcc-alert mcc-ground-response" data-ground-form-feedback hidden role="status"></p>
            <?php endif; ?>

            <div class="mcc-ground-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    Save ground
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('grounds') ?>">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</main>
<?= view('staff_footer') ?>
