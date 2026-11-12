<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment receipt</title>
    <style>
        body { background: #f4f7fb; color: #1f2d45; font: 15px/1.5 Arial, sans-serif; margin: 0; padding: 2rem; }
        main { background: #fff; border: 1px solid #dfe7f2; border-radius: 14px; margin: 0 auto; max-width: 720px; padding: 2rem; }
        h1 { color: #173b76; font-size: 1.5rem; margin: 0 0 .35rem; }
        p { color: #697891; margin: 0 0 1.5rem; }
        dl { display: grid; gap: .8rem 1.2rem; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
        dt { color: #71809a; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
        dd { color: #233452; margin: .15rem 0 0; }
        .amount { color: #17633f; font-size: 1.3rem; font-weight: 800; }
        @media (max-width: 560px) { body { padding: 1rem; } main { padding: 1.25rem; } dl { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main>
        <h1>MCC Cricket Club payment receipt</h1>
        <p>Payment recorded against <?= esc((string) ($payment['admission_number'] ?? 'Admission')) ?>.</p>
        <dl>
            <div><dt>Receipt number</dt><dd><?= esc((string) ($payment['receipt_number'] ?? 'Not provided')) ?></dd></div>
            <div><dt>Payment date</dt><dd><?= esc((string) ($payment['payment_date'] ?? 'Not available')) ?></dd></div>
            <div><dt>Payment type</dt><dd><?= esc(ucfirst((string) ($payment['payment_type'] ?? 'Fee'))) ?></dd></div>
            <div><dt>Payment method</dt><dd><?= esc((string) ($payment['payment_method'] ?? 'Not provided')) ?></dd></div>
            <div><dt>Installment</dt><dd><?= esc((string) ($payment['year_label'] ?? '—')) ?> · <?= esc((string) ($payment['installment_number'] ?? '—')) ?></dd></div>
            <div><dt>Branch</dt><dd><?= esc((string) ($payment['branch_name'] ?? 'Not available')) ?></dd></div>
            <div><dt>Amount received</dt><dd class="amount"><?= esc(number_format((float) ($payment['amount_received'] ?? 0), 2)) ?></dd></div>
            <div><dt>Reference</dt><dd><?= esc((string) ($payment['reference_number'] ?? 'Not provided')) ?></dd></div>
        </dl>
    </main>
</body>
</html>
