<?php

$isCreate = $mode === 'create';
?>
<?= view('staff_header', [
    'title' => $isCreate ? 'Create user' : 'Edit user',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>

<form
    method="post"
    action="<?= $isCreate ? site_url('admin/users') : site_url('admin/users/' . $user['id']) ?>"
    class="mcc-user-form"
>
    <?= csrf_field() ?>

    <section class="mcc-card mcc-user-form-section" aria-labelledby="user-account-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Administration</span>
                <h2 id="user-account-heading">Account details</h2>
            </div>
        </div>
        <div class="mcc-user-form-grid">
            <div class="mcc-user-form-field">
                <label for="display_name">Display name</label>
                <input
                    class="form-control"
                    id="display_name"
                    name="display_name"
                    required
                    maxlength="150"
                    value="<?= esc($user['display_name'] ?? old('display_name')) ?>"
                >
            </div>
            <div class="mcc-user-form-field">
                <label for="email">Email</label>
                <input
                    class="form-control"
                    id="email"
                    name="email"
                    type="email"
                    required
                    maxlength="190"
                    value="<?= esc($user['email'] ?? old('email')) ?>"
                >
            </div>
        </div>
    </section>

    <section class="mcc-card mcc-user-form-section" aria-labelledby="user-security-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Security</span>
                <h2 id="user-security-heading"><?= $isCreate ? 'Temporary password' : 'Password' ?></h2>
            </div>
        </div>
        <div class="mcc-user-form-field mcc-user-password-field">
            <label for="<?= $isCreate ? 'password' : 'new_password' ?>">
                <?= $isCreate ? 'Temporary password' : 'New password' ?>
            </label>
            <input
                class="form-control"
                id="<?= $isCreate ? 'password' : 'new_password' ?>"
                name="<?= $isCreate ? 'password' : 'new_password' ?>"
                type="password"
                <?= $isCreate ? 'required' : '' ?>
                minlength="16"
                maxlength="1024"
                autocomplete="new-password"
            >
            <small>
                <?= $isCreate
                    ? 'Minimum 16 characters. The password is never displayed after submission.'
                    : 'Optional. Leave blank to keep the current password unchanged. Minimum 16 characters when entered.' ?>
            </small>
        </div>
    </section>

    <div class="mcc-user-form-actions">
        <button class="mcc-button mcc-button-primary mcc-lead-action" type="submit">
            <?= $isCreate ? 'Create user' : 'Save changes' ?>
        </button>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/users') ?>">
            Back to users
        </a>
    </div>
</form>

<?= view('staff_footer') ?>
