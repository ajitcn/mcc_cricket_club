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
$oldEquipment = old('equipment', []);
$oldEquipment = is_array($oldEquipment) ? $oldEquipment : [];
foreach ($oldEquipment as $equipmentId => $quantity) {
    if ((int) $quantity > 0) {
        $assignmentItems[(int) $equipmentId] = (int) $quantity;
    }
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
                <p>Search and select equipment to add it with quantity 1. You can adjust or remove items below.</p>
            </div>

            <?php if ($equipment !== []): ?>
                <div class="mcc-form-field mcc-equipment-picker-field">
                    <label for="equipment-picker">Add equipment</label>
                    <select class="form-control btSelect" id="equipment-picker" multiple data-bt-select
                        data-bt-select-placeholder="Choose equipment"
                        data-bt-select-search-placeholder="Search equipment">
                        <?php foreach ($equipment as $item): ?>
                            <option value="<?= (int) $item['id'] ?>"
                                data-branch-id="<?= (int) $item['branch_id'] ?>"
                                data-available="<?= (int) $item['quantity'] ?>"
                                <?= array_key_exists((int) $item['id'], $assignmentItems) ? 'selected' : '' ?>>
                                <?= esc((string) $item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-equipment-assignment-grid" data-equipment-assignment-items></div>
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
        const picker = document.getElementById('equipment-picker');
        const itemList = document.querySelector('[data-equipment-assignment-items]');
        const quantities = <?= json_encode($assignmentItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        if (!branch || !coach || !picker || !itemList) {
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

            picker.querySelectorAll('option[data-branch-id]').forEach((option) => {
                const visible = !branchId || option.dataset.branchId === branchId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) {
                    option.selected = false;
                    delete quantities[option.value];
                }
            });

            renderItems();
        };

        const renderItems = () => {
            const selected = Array.from(picker.selectedOptions);
            itemList.replaceChildren();

            selected.forEach((option) => {
                const id = option.value;
                const available = Number(option.dataset.available || 0);
                const article = document.createElement('article');
                article.className = 'mcc-equipment-assignment-item';
                article.dataset.branchId = option.dataset.branchId || '';

                const heading = document.createElement('div');
                heading.className = 'mcc-equipment-assignment-item-heading';
                const label = document.createElement('span');
                label.className = 'mcc-table-primary-text';
                label.textContent = option.textContent.trim();
                const availableBadge = document.createElement('span');
                availableBadge.className = 'mcc-badge mcc-badge-muted';
                availableBadge.textContent = `Available: ${available}`;
                heading.append(label, availableBadge);

                const input = document.createElement('input');
                input.className = 'form-control';
                input.type = 'number';
                input.min = '1';
                input.max = String(available);
                input.inputMode = 'numeric';
                input.name = `equipment[${id}]`;
                input.value = String(Number(quantities[id] || 1));
                input.setAttribute('aria-label', `Quantity for ${option.textContent.trim()}`);
                input.addEventListener('input', () => {
                    quantities[id] = input.value;
                });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'mcc-button mcc-button-secondary mcc-button-small';
                remove.textContent = 'Remove';
                remove.addEventListener('click', () => {
                    option.selected = false;
                    delete quantities[id];
                    picker.dispatchEvent(new Event('change', { bubbles: true }));
                });

                article.append(heading, input, remove);
                itemList.append(article);
            });
        };

        const clearSelectedEquipment = () => {
            picker.querySelectorAll('option').forEach((option) => {
                option.selected = false;
            });

            Object.keys(quantities).forEach((id) => {
                delete quantities[id];
            });

            const wrapper = picker.closest('.mcc-bt-select');
            const toggle = wrapper?.querySelector('.mcc-bt-select-toggle');
            if (toggle) {
                toggle.textContent = picker.dataset.btSelectPlaceholder || 'Choose equipment';
                toggle.title = '';
            }

            wrapper?.querySelectorAll('.mcc-bt-select-option').forEach((label) => {
                label.classList.remove('is-selected', 'selected', 'checked');
                const input = label.querySelector('input');
                if (input) {
                    input.checked = false;
                    input.removeAttribute('aria-checked');
                }
            });

            const count = wrapper?.querySelector('.mcc-bt-select-count');
            if (count) {
                count.textContent = '0 selected';
            }

            filterByBranch();
        };

        let lastBranchId = branch.value;
        branch.addEventListener('change', () => {
            lastBranchId = branch.value;
            clearSelectedEquipment();
        });

        window.setInterval(() => {
            if (branch.value !== lastBranchId) {
                lastBranchId = branch.value;
                clearSelectedEquipment();
            }
        }, 150);
        picker.addEventListener('change', renderItems);
        filterByBranch();
    })();
</script>

<?= view('staff_footer') ?>
