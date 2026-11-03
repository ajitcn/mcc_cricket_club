<?php

$reference = 'ERR-' . strtoupper(substr(hash('sha256', (string) ($type ?? '') . '|' . (string) ($file ?? '') . '|' . (string) ($line ?? 0) . '|' . (string) ($message ?? '')), 0, 8));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request error - MCC Cricket Club</title>
</head>
<body>
    <main>
        <h1>We could not complete that request.</h1>
        <p>Please try again. If the problem continues, provide this reference to support:</p>
        <p><strong><?= esc($reference) ?></strong></p>
    </main>
</body>
</html>
