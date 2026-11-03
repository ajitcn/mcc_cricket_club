<?php
$title = $title ?? 'MCC Cricket Club';
$pageSubtitle = $pageSubtitle ?? null;
$nav = $nav ?? [];
$suppressGlobalAlerts = $suppressGlobalAlerts ?? false;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#172b4d">
    <meta name="mcc-timezone" content="<?= esc(config('App')->appTimezone) ?>">
    <title><?= esc($title) ?> - MCC Cricket Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
        crossorigin="anonymous">
    <link rel="stylesheet" href="<?= site_url('assets/css/app.css') . '?v=' . (int) filemtime(FCPATH . 'assets/css/app.css') ?>">
    <script src="<?= site_url('assets/js/app.js') . '?v=' . (int) filemtime(FCPATH . 'assets/js/app.js') ?>" defer></script>
</head>
<body class="mcc-app">
    <a class="mcc-skip-link" href="#main-content">Skip to content</a>
    <div class="mcc-app-shell">
        <?= view('partials/sidebar', ['nav' => $nav]) ?>
        <div class="mcc-main">
            <div class="mcc-mobile-backdrop" data-sidebar-close></div>
            <?= view('partials/topbar') ?>
            <main id="main-content" class="mcc-content">
                <div class="mcc-page-container">
                    <?= view('partials/breadcrumbs', [
                        'breadcrumb' => $title,
                        'breadcrumbTrail' => $breadcrumbTrail ?? [],
                    ]) ?>
                    <div class="mcc-page-heading">
                        <div>
                            <span class="mcc-eyebrow">MCC Cricket Club</span>
                            <h1><?= esc($title) ?></h1>
                            <?php if ($pageSubtitle !== null && $pageSubtitle !== ''): ?>
                                <p class="mcc-page-subtitle"><?= esc((string) $pageSubtitle) ?></p>
                            <?php endif; ?>
                        </div>
                        <?= $pageActions ?? '' ?>
                    </div>
                    <?php if (! $suppressGlobalAlerts): ?>
                        <?= view('partials/alerts', ['message' => $message ?? null, 'error' => $error ?? null]) ?>
                    <?php endif; ?>
