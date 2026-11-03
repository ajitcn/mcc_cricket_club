<?php

$isEdit = ($mode ?? 'create') === 'edit';
$coach = is_array($coach ?? null) ? $coach : [];
?>
<?= view('staff_header', [
    'title' => $isEdit ? 'Edit coach profile' : 'Create coach profile',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>
<section class="mcc-card mcc-coach-form-card" aria-labelledby="coach-form-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Coach management</span>
            <h2 id="coach-form-heading">
                <?= $isEdit ? 'Update profile details' : 'Add profile for an existing coach user' ?>
            </h2>
        </div>
    </div>
    <form
        method="post"
        action="<?= $isEdit ? site_url('coaches/' . (int) $coach['id']) : site_url('coaches') ?>"
        class="mcc-coach-form"
    >
        <div class="mcc-coach-form-fields">
            <?php if (!$isEdit): ?>
                <div class="mcc-coach-field">
                    <label for="coach-user">Coach user</label>
                    <select class="form-control" id="coach-user" name="user_id" required>
                        <option value="">Select an existing coach user</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>">
                                <?= esc($user['display_name'] . ' — ' . $user['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <p class="mcc-help">
                    <?= esc($coach['display_name']) ?> · <?= esc($coach['email']) ?>
                </p>
            <?php endif; ?>
            <div class="mcc-coach-field">
                <label for="specialization">Specialization</label>
                <input class="form-control" id="specialization" name="specialization" maxlength="150" value="<?= esc((string) ($coach['specialization'] ?? '')) ?>">
            </div>
            <div class="mcc-coach-field">
                <label for="experience-years">Experience</label>
                <input class="form-control" id="experience-years" name="experience_years" type="text" maxlength="150" value="<?= esc((string) ($coach['experience_years'] ?? '')) ?>">
            </div>
            <div class="mcc-coach-field">
                <label for="qualification">Qualification</label>
                <textarea class="form-control" id="qualification" name="qualification" rows="3" maxlength="10000"><?= esc((string) ($coach['qualification'] ?? '')) ?></textarea>
            </div>
            <div class="mcc-coach-field">
                <label for="certifications">Certifications</label>
                <textarea class="form-control" id="certifications" name="certifications" rows="3" maxlength="10000"><?= esc((string) ($coach['certifications'] ?? '')) ?></textarea>
            </div>
            <div class="mcc-coach-field">
                <label for="availability">Availability notes</label>
                <textarea class="form-control" id="availability" name="availability" rows="3" maxlength="10000"><?= esc((string) ($coach['availability'] ?? '')) ?></textarea>
            </div>
            <div class="mcc-coach-field">
                <label for="is-active">Profile status</label>
                <select class="form-control" id="is-active" name="is_active">
                    <option value="1" <?= (int) ($coach['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int) ($coach['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php if ($isEdit): ?>
                <div class="mcc-coach-field mcc-form-field-full">
                    <label for="coach-change-reason">Reason for correction</label>
                    <input class="form-control" id="coach-change-reason" name="reason" maxlength="255" required>
                </div>
            <?php endif; ?>
        </div>
        <?= csrf_field() ?>
        <div class="mcc-coach-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save profile
            </button>
            <a class="mcc-button mcc-button-secondary ml-2" href="<?= $isEdit ? site_url('coaches/' . (int) $coach['id']) : site_url('coaches') ?>">Cancel</a>
        </div>
    </form>
</section>
<?= view('staff_footer') ?>
