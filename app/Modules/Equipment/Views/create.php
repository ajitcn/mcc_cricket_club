<?php

$branches = is_array($branches ?? null) ? $branches : [];
$isSuperAdmin = (bool) ($isSuperAdmin ?? false);
$isEdit = (bool) ($isEdit ?? false);
$equipment = is_array($equipment ?? null) ? $equipment : [];
$activeBranchId = ($activeBranchId ?? null) === null ? null : (int) $activeBranchId;
$branchName = $isEdit
    ? (string) ($equipment['branch_name'] ?? '')
    : (string) ($activeBranchName ?? '');
$formAction = $isEdit
    ? site_url('equipment/' . (int) ($equipment['id'] ?? 0))
    : site_url('equipment');
?>
<?= view('staff_header', ['title' => $title]) ?>
<section class="mcc-card mcc-equipment-form-card" aria-labelledby="equipment-create-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Grounds &amp; Equipment</span>
            <h2 id="equipment-create-heading">Equipment details</h2>
            <p class="mcc-card-subtitle">
                <?= $isEdit ? 'Update this inventory record.' : 'Add an inventory record to an authorized branch.' ?>
            </p>
        </div>
    </div>

    <?php if (empty($canCreate)): ?>
        <div class="mcc-empty-state" role="status">
            <strong>No active authorized branch</strong>
            <span>
                <?php if (! $isSuperAdmin && $activeBranchId === null): ?>
                    Assign a branch or select an active branch in the shared navigation before creating equipment.
                <?php else: ?>
                    No active branch is available to select for this account.
                <?php endif; ?>
            </span>
        </div>
    <?php else: ?>
        <form method="post" action="<?= esc($formAction) ?>" class="mcc-equipment-form">
            <?= csrf_field() ?>

            <div class="mcc-equipment-form-fields">
                <div class="mcc-form-field">
                    <label for="equipment-name">Name <span aria-hidden="true">*</span></label>
                    <input
                        class="form-control"
                        id="equipment-name"
                        name="name"
                        type="text"
                        maxlength="150"
                        value="<?= esc((string) old('name', $equipment['name'] ?? '')) ?>"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="mcc-form-field">
                    <label for="equipment-quantity">Quantity <span aria-hidden="true">*</span></label>
                    <input
                        class="form-control"
                        id="equipment-quantity"
                        name="quantity"
                        type="number"
                        min="1"
                        step="1"
                        value="<?= esc((string) old('quantity', $equipment['quantity'] ?? '1')) ?>"
                        required
                    >
                </div>

                <div class="mcc-form-field">
                    <label for="equipment-branch">Branch <span aria-hidden="true">*</span></label>
                    <?php if ($isSuperAdmin && ! $isEdit): ?>
                        <select class="form-control" id="equipment-branch" name="branch_id" required>
                            <option value="">Select an authorized branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option
                                    value="<?= (int) $branch['id'] ?>"
                                    <?= (string) old('branch_id', '') === (string) $branch['id'] ? 'selected' : '' ?>
                                >
                                    <?= esc($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p class="mcc-equipment-fixed-branch">
                            <?= esc($branchName !== '' ? $branchName : 'Selected branch') ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mcc-equipment-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <?= $isEdit ? 'Save changes' : 'Save equipment' ?>
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('equipment') ?>">
                    Cancel
                </a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?= view('staff_footer') ?>
