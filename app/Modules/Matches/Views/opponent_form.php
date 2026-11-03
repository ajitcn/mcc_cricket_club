<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4">
    <?php
    $editing = (bool) ($editing ?? false);
    $opponent = $opponent ?? [];
    $fieldErrors = session()->getFlashdata('field_errors') ?? [];
    ?>
    <div class="mcc-page-heading"><div><a href="<?= site_url('matches/opponents') ?>">Back to Opponents</a><h1><?= $editing ? 'Edit opponent' : 'Create opponent' ?></h1></div></div>
    <form method="post" action="<?= site_url($editing ? 'matches/opponents/' . (int) $opponent['id'] : 'matches/opponents') ?>" class="mcc-card">
        <?= csrf_field() ?>
        <label for="opponent-name">Opponent name</label>
        <input id="opponent-name" class="form-control" name="name" maxlength="150" required value="<?= esc((string) old('name', $opponent['name'] ?? '')) ?>">
        <?php if (! empty($fieldErrors['name'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['name']) ?></p><?php endif ?>
        <?php if (! $editing): ?>
            <label for="opponent-branch">Branch</label>
            <select id="opponent-branch" class="form-control" name="branch_id" required>
                <option value="">Select branch</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>" <?= (string) old('branch_id') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                <?php endforeach ?>
            </select>
        <?php else: ?>
            <input type="hidden" name="branch_id" value="<?= (int) old('branch_id', $opponent['branch_id'] ?? 0) ?>">
        <?php endif ?>
        <?php if (! empty($fieldErrors['branch_id'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['branch_id']) ?></p><?php endif ?>
        <label for="opponent-active"><input id="opponent-active" type="checkbox" name="is_active" value="1" <?= (int) old('is_active', $opponent['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Active</label>
        <?php if (! empty($fieldErrors['is_active'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['is_active']) ?></p><?php endif ?>
        <div class="mcc-page-actions"><a class="mcc-button mcc-button-secondary" href="<?= site_url('matches/opponents') ?>">Cancel</a><button class="mcc-button mcc-button-primary" type="submit">Save opponent</button></div>
    </form>
</main>
<?= view('staff_footer') ?>
