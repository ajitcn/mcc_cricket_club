<?php

$branchContext = service('branchContext')->navigationState();
$branches = $branchContext['branches'];
$activeBranchId = $branchContext['active_branch_id'];
?>
<?php if ($branchContext['show_selector']): ?>
    <form
        class="mcc-active-branch-selector"
        method="post"
        action="<?= site_url('auth/active-branch') ?>"
    >
        <?= csrf_field() ?>
        <label class="sr-only" for="active-branch-select">Active branch</label>
        <select
            class="form-control"
            id="active-branch-select"
            name="branch_id"
            required
        >
            <option value="" <?= $activeBranchId === null ? 'selected' : '' ?>>
                Select active branch
            </option>
            <?php foreach ($branches as $branch): ?>
                <option
                    value="<?= (int) $branch['id'] ?>"
                    <?= $activeBranchId === (int) $branch['id'] ? 'selected' : '' ?>
                >
                    <?= esc($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="mcc-button mcc-button-secondary" type="submit">
            Set branch
        </button>
    </form>
<?php endif; ?>
