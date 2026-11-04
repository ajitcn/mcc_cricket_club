<?php

$options = is_array($options ?? null) ? $options : [];
$branches = is_array($options['branches'] ?? null) ? $options['branches'] : [];
$coaches = is_array($options['coaches'] ?? null) ? $options['coaches'] : [];
$equipment = is_array($options['equipment'] ?? null) ? $options['equipment'] : [];
$assignment = is_array($assignment ?? null) ? $assignment : [];
$isEdit = (bool) ($isEdit ?? false);
$assignmentItems = [];
foreach (($assignment['items'] ?? []) as $assignmentItem) {
    $assignmentItems[(int) ($assignmentItem['equipment_id'] ?? 0)] = (int) ($assignmentItem['quantity_assigned'] ?? 0);
}

$selectedBranch = (string) old('branch_id', $assignment['branch_id'] ?? '');
$selectedCoach = (string) old('coach_user_id', $assignment['coach_user_id'] ?? '');
$assignedDate = (string) old('assigned_date', $assignment['assigned_date'] ?? '');
$formAction = $isEdit
    ? site_url('equipment/assignments/' . (int) ($assignment['id'] ?? 0))
    : site_url('equipment/assignments');

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('equipment/assignments') . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to assignments</a>',
]) ?>

<section class="mcc-card mcc-equipment-assignment-card" aria-labelledby="equipment-assignment-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Equipment management</span>
            <h2 id="equipment-assignment-heading"><?= $isEdit ? 'Edit equipment assignment' : 'Create equipment assignment' ?></h2>
            <p class="mcc-card-subtitle">
                Select the authorized branch and Coach, then record the equipment being issued.
            </p>
        </div>
    </div>

    <?php if ($branches === [] || $coaches === []): ?>
        <div class="mcc-alert mcc-alert-warning" role="alert">
            The assignment options are not available for this account. Confirm branch access and Coach setup before continuing.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= esc($formAction) ?>" class="mcc-equipment-assignment-form">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="version" value="<?= (int) ($assignment['version'] ?? 0) ?>">
        <?php endif; ?>

        <section class="mcc-equipment-assignment-section" aria-labelledby="assignment-details-heading">
            <div class="mcc-section-heading">
                <h3 id="assignment-details-heading">Assignment details</h3>
                <p>Choose where the equipment is being issued and to whom.</p>
            </div>

            <div class="mcc-equipment-assignment-meta">
                <div class="mcc-form-field">
                    <label for="assignment-branch">Branch <span aria-hidden="true">*</span></label>
                    <select class="form-control" id="assignment-branch" name="branch_id" required>
                        <option value="">Choose branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= $selectedBranch === (string) $branch['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($branches === []): ?>
                        <p class="mcc-field-error" role="alert">No authorized branches are available.</p>
                    <?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="assignment-coach">Coach <span aria-hidden="true">*</span></label>
                    <select class="form-control" id="assignment-coach" name="coach_user_id" required>
                        <option value="">Choose Coach</option>
                        <?php foreach ($coaches as $coach): ?>
                            <option value="<?= (int) $coach['id'] ?>" data-branch-id="<?= (int) $coach['branch_id'] ?>" <?= $selectedCoach === (string) $coach['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $coach['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($coaches === []): ?>
                        <p class="mcc-field-error" role="alert">No eligible Coaches are available.</p>
                    <?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="assigned-date">Assignment date <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="assigned-date" name="assigned_date" type="text" value="<?= esc($assignedDate) ?>" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" placeholder="DD-MM-YYYY" autocomplete="off" required>
                </div>
            </div>
        </section>

        <section class="mcc-equipment-assignment-section" aria-labelledby="equipment-items-heading">
            <div class="mcc-section-heading">
                <h3 id="equipment-items-heading">Equipment issued</h3>
                <p>Enter the quantity for each item. Leave items not being issued at zero.</p>
            </div>

            <?php if ($equipment !== []): ?>
                <div class="mcc-equipment-assignment-grid" data-equipment-assignment-items>
                    <?php foreach ($equipment as $item): ?>
                        <article class="mcc-equipment-assignment-item" data-branch-id="<?= (int) $item['branch_id'] ?>">
                            <div class="mcc-equipment-assignment-item-heading">
                                <label for="equipment-<?= (int) $item['id'] ?>"><?= esc((string) $item['name']) ?></label>
                                <span class="mcc-badge mcc-badge-muted">Available: <?= (int) $item['quantity'] ?></span>
                            </div>
                            <input class="form-control" id="equipment-<?= (int) $item['id'] ?>" name="equipment[<?= (int) $item['id'] ?>]" type="number" min="0" max="<?= (int) $item['quantity'] ?>" value="<?= esc((string) old('equipment.' . (int) $item['id'], $assignmentItems[(int) $item['id']] ?? '0')) ?>" inputmode="numeric">
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mcc-empty-state" role="status">No equipment inventory is available in the authorized branches.</div>
            <?php endif; ?>
        </section>

        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Assign equipment</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('equipment/assignments') ?>">Cancel</a>
        </div>
    </form>
</section>

<script>
    (() => {
        const branch = document.getElementById('assignment-branch');
        const coach = document.getElementById('assignment-coach');
        const items = document.querySelectorAll('[data-equipment-assignment-items] [data-branch-id]');

        if (!branch || !coach) {
            return;
        }

        const filterByBranch = () => {
            const branchId = branch.value;

            coach.querySelectorAll('option[data-branch-id]').forEach((option) => {
                const visible = !branchId || option.dataset.branchId === branchId;
                option.hidden = !visible;
                option.disabled = !visible;

                if (!visible && option.selected) {
                    coach.value = '';
                }
            });

            items.forEach((item) => {
                item.hidden = Boolean(branchId) && item.dataset.branchId !== branchId;
            });
        };

        branch.addEventListener('change', filterByBranch);
        filterByBranch();
    })();
</script>

<?= view('staff_footer') ?>
