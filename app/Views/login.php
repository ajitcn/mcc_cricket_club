<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - MCC Cricket Club</title>
    <link rel="stylesheet" href="<?= site_url('assets/css/app.css') ?>">
    <script src="<?= site_url('assets/js/app.js') . '?v=' . (int) filemtime(FCPATH . 'assets/js/app.js') ?>" defer></script>
</head>
<body class="mcc-auth-page">
    <a class="mcc-skip-link" href="#main-content">Skip to content</a>
    <main id="main-content" class="mcc-auth-shell">
        <section class="mcc-auth-branding" aria-label="MCC Cricket Club">
            <span class="mcc-brand-mark" aria-hidden="true">M</span>
            <span class="mcc-eyebrow">MCC Cricket Club</span>
            <h1>Academy operations, clearly organized.</h1>
            <p>Manage secure, multi-branch cricket club operations from one workspace.</p>
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
                <p>Use your MCC account to continue.</p>
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
            <form class="mcc-form" method="post" action="<?= site_url('auth/login') ?>">
                <?= csrf_field() ?>
                <div class="mcc-form-field">
                    <label for="email">Email</label>
                    <input
                        class="form-control"
                        id="email"
                        name="email"
                        type="email"
                        value="<?= esc($email ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
                <div class="mcc-form-field">
                    <label for="password">Password</label>
                    <div class="mcc-auth-password-field">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input
                            class="form-control"
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                    </div>
                </div>
                <div class="mcc-auth-actions">
                    <button class="mcc-button mcc-button-primary mcc-button-block" type="submit">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                        <span>Sign in</span>
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
