<?= view('staff_header', ['title' => $title, 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>

<section class="mcc-card" aria-labelledby="password-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Account security</span>
            <h2 id="password-heading">Update password</h2>
        </div>
    </div>
    <form method="post" action="<?= site_url('auth/password') ?>" class="mcc-form">
        <?= csrf_field() ?>
        <div class="mcc-form-field">
            <label for="current-password">Current password</label>
            <input id="current-password" class="form-control" type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="mcc-form-field">
            <label for="new-password">New password</label>
            <input id="new-password" class="form-control" type="password" name="password" minlength="12" autocomplete="new-password" required>
        </div>
        <div class="mcc-form-field">
            <label for="password-confirmation">Confirm new password</label>
            <input id="password-confirmation" class="form-control" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
        </div>
        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Update password</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('/') ?>">Cancel</a>
        </div>
    </form>
</section>

<?= view('staff_footer') ?>
