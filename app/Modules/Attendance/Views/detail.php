<?php
$attendanceStatus = strtoupper((string) $attendance['status']);
$attendanceStatusMeta = [
    'PRESENT' => [
        'label' => 'Present',
        'class' => 'mcc-attendance-status-present',
        'icon' => 'fa-circle-check',
    ],
    'ABSENT' => [
        'label' => 'Absent',
        'class' => 'mcc-attendance-status-absent',
        'icon' => 'fa-circle-xmark',
    ],
    'LATE' => [
        'label' => 'Late',
        'class' => 'mcc-attendance-status-late',
        'icon' => 'fa-clock',
    ],
    'EXCUSED' => [
        'label' => 'Excused',
        'class' => 'mcc-attendance-status-excused',
        'icon' => 'fa-circle-info',
    ],
    'LEAVE' => [
        'label' => 'Leave',
        'class' => 'mcc-attendance-status-excused',
        'icon' => 'fa-person-walking-luggage',
    ],
    'MEDICAL' => [
        'label' => 'Medical',
        'class' => 'mcc-attendance-status-excused',
        'icon' => 'fa-notes-medical',
    ],
    'RAIN_CANCELLED' => [
        'label' => 'Rain cancelled',
        'class' => 'mcc-attendance-status-cancelled',
        'icon' => 'fa-cloud-showers-heavy',
    ],
    'COACH_CANCELLED' => [
        'label' => 'Coach cancelled',
        'class' => 'mcc-attendance-status-cancelled',
        'icon' => 'fa-ban',
    ],
];
$attendanceMeta = $attendanceStatusMeta[$attendanceStatus] ?? [
    'label' => ucwords(strtolower($attendanceStatus)),
    'class' => 'mcc-badge-neutral',
    'icon' => 'fa-circle-info',
];
?>
<?= view('staff_header', ['title' => 'Attendance detail', 'nav' => $nav, 'error' => $error, 'message' => $message]) ?>
<nav class="breadcrumb" aria-label="Breadcrumb">
<a href="<?= site_url('attendance/sessions/' . (int) $attendance['session_id']) ?>">&larr; Back to roster</a>
</nav>
<section aria-labelledby="attendance-heading">
<h2 id="attendance-heading">Attendance detail</h2>
<dl>
<dt>Player</dt>
<dd>
<?= esc($attendance['player_name']) ?>
</dd>
<dt>Session</dt>
<dd>
<?= (int) $attendance['session_id'] ?> (<?= esc(mcc_user_datetime($attendance['starts_at_local'] ?? null)) ?> &ndash; <?= esc(mcc_user_datetime($attendance['ends_at_local'] ?? null)) ?>)</dd>
<dt>Batch</dt>
<dd>
<?= esc((string) ($attendance['batch_name'] ?? 'Not assigned')) ?>
</dd>
<dt>Branch</dt>
<dd>
<?= esc($attendance['branch_name']) ?>
</dd>
<dt>Attendance status</dt>
<dd>
<span class="mcc-badge mcc-status-badge <?= esc($attendanceMeta['class']) ?>">
<i class="fa-solid <?= esc($attendanceMeta['icon']) ?>" aria-hidden="true"></i>
<?= esc($attendanceMeta['label']) ?>
</span>
</dd>
<dt>Recorded by</dt>
<dd>
<?= esc($attendance['recorded_by_name']) ?> at <?= esc(mcc_user_datetime($attendance['recorded_at_local'] ?? null)) ?></dd>
</dl>
</section>
<section aria-labelledby="history-heading">
<h2 id="history-heading">Attendance history</h2>
<div class="table-wrap">
<table>
<caption>Append-only attendance event history</caption>
<thead>
<tr>
<th scope="col">Changed at</th>
<th scope="col">Prior state</th>
<th scope="col">New state</th>
<th scope="col">Actor</th>
<th scope="col">Reason</th>
</tr>
</thead>
<tbody>
<?php foreach ($attendance['history'] as $item): ?>
<tr>
<td>
<?= esc(mcc_user_datetime($item['changed_at'] ?? null)) ?>
</td>
<td>
<?php $priorStatus = strtoupper((string) ($item['prior_status'] ?? '')); ?>
<?php $priorMeta = $attendanceStatusMeta[$priorStatus] ?? [
    'label' => 'Not recorded',
    'class' => 'mcc-badge-neutral',
]; ?>
<span class="mcc-badge mcc-status-badge <?= esc($priorMeta['class']) ?>">
<?= esc($priorMeta['label']) ?>
</span>
</td>
<td>
<?php $historyStatus = strtoupper((string) $item['new_status']); ?>
<?php $historyMeta = $attendanceStatusMeta[$historyStatus] ?? [
    'label' => ucwords(strtolower($historyStatus)),
    'class' => 'mcc-badge-neutral',
]; ?>
<span class="mcc-badge mcc-status-badge <?= esc($historyMeta['class']) ?>">
<?= esc($historyMeta['label']) ?>
</span>
</td>
<td>
<?= esc($item['actor_name']) ?>
</td>
<td>
<?= esc((string) ($item['reason'] ?? 'Initial attendance record')) ?>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>
<?= view('staff_footer') ?>
