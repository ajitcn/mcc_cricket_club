<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - MCC Cricket Club</title>
    <link rel="stylesheet" href="<?= site_url('assets/css/app.css') ?>">
    <script src="<?= site_url('assets/js/app.js') ?>" defer></script>
</head>
<body class="mcc-auth-page">
    <a class="mcc-skip-link" href="#main-content">Skip to content</a>
    <main id="main-content" class="mcc-auth-shell">
        <section class="mcc-auth-branding" aria-label="MCC Cricket Club">
            <span class="mcc-brand-mark" aria-hidden="true">M</span>
            <span class="mcc-eyebrow">MCC Cricket Club</span>
            <h1>Academy operations, clearly organized.</h1>
            <p>Access your secure student or parent portal from one workspace.</p>
        </section>
        <section class="mcc-auth-card">
            <div class="mcc-auth-logo-wrap">
                <img
                    class="mcc-auth-logo"
                    src="<?= site_url('assets/img/logo.png') ?>"
                    alt="MCC Cricket Club"
                    onerror="this.hidden = true"
                >
            </div>
            <div class="mcc-auth-card-heading">
                <span class="mcc-eyebrow">Welcome back</span>
                <h2>Sign in</h2>
                <p>Use your MCC portal account to continue.</p>
            </div>
            <?php if (! empty($message)): ?>
                <div class="mcc-alert mcc-alert-success" role="status">
                    <?= esc($message) ?>
                </div>
            <?php endif; ?>
            <?php if (! empty($error)): ?>
                <div class="mcc-alert mcc-alert-danger" role="alert">
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>
            <?php if (! empty($developmentOtp)): ?>
                <div class="mcc-alert mcc-alert-warning mcc-portal-debug-otp" role="status">
                    <strong>Development OTP — local testing only</strong>
                    <span><?= esc($developmentOtp) ?></span>
                </div>
            <?php endif; ?>
            <form class="mcc-form" method="post" action="<?= site_url('student-login') ?>">
                <?= csrf_field() ?>
                <fieldset class="mcc-form-field mcc-auth-choice-field">
                    <legend>Sign in as</legend>
                    <div class="mcc-auth-choice-options">
                        <label class="mcc-auth-choice">
                            <input
                                type="radio"
                                name="login_type"
                                value="student"
                                <?= ($loginType ?? 'student') === 'student' ? 'checked' : '' ?>
                            >
                            <span>Login as Student</span>
                        </label>
                        <label class="mcc-auth-choice">
                            <input
                                type="radio"
                                name="login_type"
                                value="parent"
                                <?= ($loginType ?? '') === 'parent' ? 'checked' : '' ?>
                            >
                            <span>Login as Parent</span>
                        </label>
                    </div>
                </fieldset>
                <div class="mcc-form-field">
                    <label for="portal-mobile">Mobile number</label>
                    <input
                        class="form-control"
                        id="portal-mobile"
                        name="mobile"
                        type="tel"
                        value="<?= esc($mobile ?? old('mobile', '')) ?>"
                        autocomplete="tel"
                        required
                    >
                </div>
                <?php if (! empty($otpRequested)): ?>
                    <div class="mcc-form-field">
                        <label for="portal-otp">WhatsApp OTP</label>
                        <input
                            class="form-control"
                            id="portal-otp"
                            name="otp"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            required
                        >
                    </div>
                <?php endif; ?>
                <div class="mcc-auth-actions">
                    <input type="hidden" name="action" value="<?= ! empty($otpRequested) ? 'verify' : 'request' ?>">
                    <button class="mcc-button mcc-button-primary mcc-button-block" type="submit">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                        <span><?= ! empty($otpRequested) ? 'Verify OTP' : 'Get OTP' ?></span>
                    </button>
                </div>
            </form>
            <?php if (! empty($otpRequested)): ?>
                <form class="mcc-form mcc-portal-resend-form" method="post" action="<?= site_url('student-login') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="login_type" value="<?= esc($loginType ?? 'student') ?>">
                    <input type="hidden" name="mobile" value="<?= esc($mobile ?? old('mobile', '')) ?>">
                    <input type="hidden" name="action" value="request">
                    <button class="mcc-button mcc-button-secondary mcc-button-block" type="submit">
                        Resend OTP
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
