<?= view('staff_header', ['title' => $title]) ?>

<main class="container py-4">
    <h1><?= esc($ground['name']) ?></h1>
    <?php if (! empty($ground['can_update'])): ?>
        <p><a class="mcc-button mcc-button-secondary" href="<?= site_url('grounds/' . (int) $ground['id'] . '/edit') ?>">Edit ground</a></p>
    <?php endif; ?>
    <p>
        <?= esc($ground['ground_type']) ?>
        &middot;
        <?= esc(ucfirst($ground['availability_status'] ?? ($ground['is_active'] ? 'active' : 'inactive'))) ?>
    </p>
    <p>
        Bookings recorded: <?= (int) ($ground['kpis']['booking_count'] ?? 0) ?>
        &middot;
        Maintenance periods: <?= (int) ($ground['kpis']['maintenance_period_count'] ?? 0) ?>
    </p>

    <h2>Maintenance and unavailable periods</h2>
    <?php if ($ground['maintenance'] === []): ?>
        <p class="mcc-empty-state">No maintenance periods are recorded.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($ground['maintenance'] as $period): ?>
                <li>
                    <?= esc(mcc_user_datetime($period['starts_at'] ?? null)) ?>
                    to
                    <?= esc(mcc_user_datetime($period['ends_at'] ?? null)) ?>
                    &mdash;
                    <?= esc($period['reason']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Booking history</h2>
    <?php if ($ground['bookings'] === []): ?>
        <p class="mcc-empty-state">No session bookings are recorded.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($ground['bookings'] as $booking): ?>
                <li>
                    Session #<?= (int) $booking['id'] ?>
                    &mdash;
                    <?= esc(mcc_user_datetime($booking['starts_at'] ?? null)) ?>
                    to
                    <?= esc(mcc_user_datetime($booking['ends_at'] ?? null)) ?>
                    &mdash;
                    <?= esc($booking['status']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Audit history</h2>
    <?php if (($ground['audit_history'] ?? []) === []): ?>
        <p class="mcc-empty-state">No Ground audit events are available.</p>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only">Ground audit history</caption>
                <thead>
                    <tr>
                        <th scope="col">Event</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Timestamp</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Metadata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ground['audit_history'] as $event): ?>
                        <tr>
                            <td><?= esc(ucwords(str_replace('_', ' ', (string) $event['action']))) ?></td>
                            <td><?= esc($event['actor_name'] ?? ('User #' . (int) ($event['actor_user_id'] ?? 0))) ?></td>
                            <td><?= esc(mcc_user_datetime($event['created_at'] ?? null)) ?></td>
                            <td><?= esc((string) ($event['reason'] ?? '—')) ?></td>
                            <td>
                                <?php $before = $event['before_metadata'] ?? []; ?>
                                <?php $after = $event['after_metadata'] ?? []; ?>
                                <?php if ($before !== [] || $after !== []): ?>
                                    <details>
                                        <summary>View details</summary>
                                        <pre><?= esc(json_encode(['before' => $before, 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                                    </details>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2>Add maintenance</h2>
    <form method="post" action="<?= site_url('grounds/' . $ground['id'] . '/maintenance') ?>" class="row" data-ground-submit-feedback>
        <?= csrf_field() ?>
        <input name="starts_at" required placeholder="DD-MM-YYYYTHH:MM:SS+05:30">
        <input name="ends_at" required placeholder="DD-MM-YYYYTHH:MM:SS+05:30">
        <input name="reason" required maxlength="255">
        <div class="col-12 text-right">
            <p class="mcc-alert mcc-ground-response" data-ground-form-feedback hidden role="status"></p>
        </div>
        <div class="col-12 text-right">
            <button class="mcc-button mcc-button-primary" type="submit">Save period</button>
        </div>
    </form>
</main>

<?= view('staff_footer') ?>
