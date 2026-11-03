<?php

$title = $title ?? 'Admission Test';
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
<body class="mcc-public-test-app">
    <a class="mcc-skip-link" href="#main-content">Skip to content</a>
    <div class="mcc-public-test-shell">
        <header class="mcc-public-test-brand" aria-label="MCC Cricket Club">
            <img src="<?= site_url('assets/img/logo.png') ?>" alt="MCC Cricket Club" class="mcc-public-test-logo">
        </header>
        <main id="main-content" class="mcc-public-test-main">
            <div class="mcc-public-test-container">
