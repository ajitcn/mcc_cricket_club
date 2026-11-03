<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student &amp; Parent Portal - MCC Cricket Club</title>
    <link rel="stylesheet" href="<?= site_url('assets/css/app.css') ?>">
</head>
<body class="mcc-app mcc-portal-body">
    <?php
    $portalType = $portalType ?? 'Student';
    $dashboard = is_array($dashboard ?? null) ? $dashboard : [];
    $portalPlayers = is_array($dashboard['players'] ?? null) ? $dashboard['players'] : [];
    $portalSessions = is_array($dashboard['sessions'] ?? null) ? $dashboard['sessions'] : [];
    $portalAttendance = is_array($dashboard['attendance'] ?? null) ? $dashboard['attendance'] : [];
    $portalGoals = is_array($dashboard['goals'] ?? null) ? $dashboard['goals'] : [];
    $portalSessionPlans = is_array($dashboard['session_plans'] ?? null) ? $dashboard['session_plans'] : [];
    $portalFeedback = is_array($dashboard['feedback'] ?? null) ? $dashboard['feedback'] : [];
    $portalPerformance = is_array($dashboard['performance'] ?? null) ? $dashboard['performance'] : [];
    $primaryPlayer = is_array($portalPlayers[0] ?? null) ? $portalPlayers[0] : [];
    $portalName = trim((string) ($primaryPlayer['full_name'] ?? '')) ?: ($portalType === 'Parent' ? 'Parent account' : 'Student account');
    $portalInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $portalName) ?: 'MC', 0, 2));
    $portalPage = $portalPage ?? 'dashboard';
    $portalAwards = is_array($portalAwards ?? null) ? $portalAwards : [];
    $portalAchievementsUrl = $portalType === 'Parent' ? 'parent-achievements' : 'student-achievements';
    $portalBaseUrl = $portalType === 'Parent' ? 'parent-portal' : 'student-portal';
    $portalPageTitles = [
        'dashboard' => ['Dashboard', 'Your academy overview at a glance.'],
        'profile' => ['Profile', 'Your personal and academy assignment details.'],
        'academy' => ['Branch & Program', 'Your current branch, program, batch, and training information.'],
        'coach' => ['Coach', 'Your current coaching assignment and training support.'],
        'schedule' => ['Schedule', 'Upcoming training sessions and attendance dates.'],
        'matches' => ['Matches', 'Authorized match information linked to your Player record.'],
        'tournaments' => ['Tournaments', 'Authorized tournament information linked to your Player record.'],
        'attendance' => ['Attendance', 'Your attendance record and current attendance rate.'],
        'development' => ['Development', 'Coach-recorded performance and development information.'],
        'progress' => ['Progress', 'Goals, session plans, and Coach feedback updates.'],
        'documents' => ['Documents', 'Authorized documents available for this portal account.'],
        'notices' => ['Notices', 'Important academy notices and updates.'],
    ];
    $portalHeading = $portalPageTitles[$portalPage] ?? $portalPageTitles['dashboard'];
    $attendanceCounts = [];
    foreach ($portalAttendance as $attendance) {
        $status = strtolower(trim((string) ($attendance['status'] ?? 'unknown')));
        $attendanceCounts[$status] = ($attendanceCounts[$status] ?? 0) + 1;
    }
    $attendanceTotal = array_sum($attendanceCounts);
    $attendancePresent = (int) ($attendanceCounts['present'] ?? 0);
    $attendanceRate = $attendanceTotal > 0 ? (int) round(($attendancePresent / $attendanceTotal) * 100) : 0;
    $branchName = trim((string) ($primaryPlayer['branch_name'] ?? '')) ?: 'Branch not assigned';
    $programName = trim((string) ($primaryPlayer['program_name'] ?? '')) ?: 'Program not assigned';
    $batchName = trim((string) ($primaryPlayer['batch_name'] ?? '')) ?: 'Batch not assigned';
    $groupCoachName = trim((string) ($primaryPlayer['group_coach_name'] ?? '')) ?: 'Not assigned';
    $personalCoachName = trim((string) ($primaryPlayer['personal_coach_name'] ?? '')) ?: 'Not assigned';
    ?>
    <div class="mcc-portal-dashboard">
        <header class="mcc-portal-header">
            <div class="mcc-portal-header-start">
                <button
                    class="mcc-portal-menu-toggle"
                    type="button"
                    data-portal-menu-toggle
                    aria-controls="portal-sidebar"
                    aria-expanded="false"
                    aria-label="Open portal navigation"
                >
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <a class="mcc-portal-brand" href="<?= site_url($portalType === 'Parent' ? 'parent-dashboard' : 'student-dashboard') ?>" aria-label="MCC Cricket Club portal home">
                    <img src="<?= site_url('assets/img/logo.png') ?>" alt="MCC Cricket Club">
                    <span class="mcc-portal-brand-copy">
                        <strong>MCC Cricket Club</strong>
                        <small>Student &amp; Parent Portal</small>
                    </span>
                </a>
            </div>
            <div class="mcc-portal-header-actions">
                <div class="mcc-portal-user-summary">
                    <span class="mcc-portal-avatar" aria-hidden="true"><?= esc($portalInitials) ?></span>
                    <span class="mcc-portal-user-copy">
                        <strong><?= esc($portalName) ?></strong>
                        <small><?= esc($portalType) ?></small>
                    </span>
                </div>
                <span class="mcc-status-badge mcc-status-badge-info"><?= esc($portalType) ?></span>
                <button class="mcc-portal-notification" type="button" aria-label="View 2 portal notifications">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <span aria-hidden="true">2</span>
                </button>
                <form method="post" action="<?= site_url('portal-logout') ?>">
                    <?= csrf_field() ?>
                    <button class="mcc-button mcc-button-secondary mcc-portal-logout-button" type="submit" aria-label="Logout from portal">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                        <span class="mcc-portal-logout-label">Logout</span>
                    </button>
                </form>
            </div>
        </header>

        <div class="mcc-portal-layout">
            <aside
                class="mcc-portal-sidebar"
                id="portal-sidebar"
                aria-label="Portal navigation"
                aria-hidden="true"
            >
                <button
                    class="mcc-portal-menu-close"
                    type="button"
                    data-portal-menu-close
                    aria-label="Close portal navigation"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <div class="mcc-portal-profile">
                    <span class="mcc-portal-profile-avatar" aria-hidden="true"><?= esc($portalInitials) ?></span>
                    <div>
                        <strong><?= esc($portalName) ?></strong>
                        <small>Portal account</small>
                    </div>
                </div>
                <nav class="mcc-portal-nav">
                    <span class="mcc-portal-nav-group">Overview</span>
                    <a class="<?= $portalPage === 'dashboard' ? 'is-active' : '' ?>" href="<?= site_url($portalType === 'Parent' ? 'parent-dashboard' : 'student-dashboard') ?>"><i class="fa-solid fa-grid-2" aria-hidden="true"></i><span>Dashboard</span></a>
                    <span class="mcc-portal-nav-group">My Profile</span>
                    <a class="<?= $portalPage === 'profile' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/profile') ?>"><i class="fa-regular fa-user" aria-hidden="true"></i><span>Profile</span></a>
                    <a class="<?= $portalPage === 'academy' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/academy') ?>"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><span>Branch &amp; Program</span></a>
                    <a class="<?= $portalPage === 'coach' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/coach') ?>"><i class="fa-solid fa-person-chalkboard" aria-hidden="true"></i><span>Coach</span></a>
                    <span class="mcc-portal-nav-group">Academy</span>
                    <a class="<?= $portalPage === 'schedule' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/schedule') ?>"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i><span>Schedule</span></a>
                    <a class="<?= $portalPage === 'matches' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/matches') ?>"><i class="fa-solid fa-baseball-bat-ball" aria-hidden="true"></i><span>Matches</span></a>
                    <a class="<?= $portalPage === 'tournaments' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/tournaments') ?>"><i class="fa-solid fa-trophy" aria-hidden="true"></i><span>Tournaments</span></a>
                    <a class="<?= $portalPage === 'achievements' ? 'is-active' : '' ?>" href="<?= site_url($portalAchievementsUrl) ?>"><i class="fa-solid fa-medal" aria-hidden="true"></i><span>Achievements</span></a>
                    <a class="<?= $portalPage === 'attendance' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/attendance') ?>"><i class="fa-regular fa-clipboard-check" aria-hidden="true"></i><span>Attendance</span></a>
                    <a class="<?= $portalPage === 'development' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/development') ?>"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Development</span></a>
                    <a class="<?= $portalPage === 'progress' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/progress') ?>"><i class="fa-solid fa-bullseye" aria-hidden="true"></i><span>Progress</span></a>
                    <a class="<?= $portalPage === 'documents' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/documents') ?>"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>Documents</span></a>
                    <a class="<?= $portalPage === 'notices' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/notices') ?>"><i class="fa-regular fa-bell" aria-hidden="true"></i><span>Notices</span></a>
                </nav>
                <div class="mcc-portal-sidebar-footer">
                    <form method="post" action="<?= site_url('portal-logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Logout</span></button>
                    </form>
                </div>
            </aside>
            <button
                class="mcc-portal-backdrop"
                type="button"
                data-portal-menu-close
                aria-label="Close portal navigation"
                aria-hidden="true"
            ></button>

            <main class="mcc-portal-main" id="dashboard" data-portal-page="<?= esc($portalPage) ?>">
                <section class="mcc-portal-page-heading" aria-labelledby="portal-page-heading">
                    <div>
                        <span class="mcc-eyebrow">MCC Cricket Club Portal</span>
                        <h1 id="portal-page-heading"><?= esc($portalHeading[0]) ?></h1>
                        <p><?= esc($portalHeading[1]) ?></p>
                    </div>
                    <span class="mcc-status-badge mcc-status-badge-info"><?= esc($portalType) ?></span>
                </section>
                <section class="mcc-portal-welcome mcc-card" id="profile" aria-labelledby="portal-welcome-heading">
                    <span class="mcc-eyebrow">MCC Cricket Club · Student &amp; Parent Portal</span>
                    <h1 id="portal-welcome-heading">Welcome back, <?= esc($portalName) ?></h1>
                    <p>Your <?= esc(strtolower($portalType)) ?> portal overview is ready. Keep track of training, attendance, and academy updates.</p>
                    <div class="mcc-portal-welcome-summary">
                        <span class="mcc-status-badge mcc-status-badge-info"><?= esc($portalType) ?></span>
                    <span><?= esc($branchName) ?> · <?= esc($programName) ?> · <?= esc($batchName) ?></span>
                    </div>
                </section>

                <section class="mcc-card mcc-portal-section mcc-portal-linked-players" id="portal-linked-players" aria-labelledby="portal-linked-players-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Relationship scope</span>
                            <h2 id="portal-linked-players-heading"><?= $portalType === 'Parent' ? 'Linked Players' : 'Player profile' ?></h2>
                        </div>
                        <span class="mcc-status-badge mcc-status-badge-info"><?= count($portalPlayers) ?> linked</span>
                    </div>
                    <?php if ($portalPlayers === []): ?>
                        <div class="mcc-empty-state" role="status">
                            <strong>No active Player record is linked</strong>
                            <span>Contact the academy if this account should be connected to a Player.</span>
                        </div>
                    <?php else: ?>
                        <div class="mcc-portal-linked-player-grid">
                            <?php foreach ($portalPlayers as $portalPlayer): ?>
                                <article class="mcc-portal-linked-player">
                                    <div>
                                        <span class="mcc-eyebrow">Player #<?= esc((string) ($portalPlayer['id'] ?? '—')) ?></span>
                                        <h3><?= esc((string) ($portalPlayer['full_name'] ?? 'Unnamed Player')) ?></h3>
                                    </div>
                                    <dl class="mcc-portal-mini-detail-grid">
                                        <div><dt>Branch</dt><dd><?= esc((string) ($portalPlayer['branch_name'] ?? 'Not assigned')) ?></dd></div>
                                        <div><dt>Program</dt><dd><?= esc((string) ($portalPlayer['program_name'] ?? 'Not assigned')) ?></dd></div>
                                        <div><dt>Batch</dt><dd><?= esc((string) ($portalPlayer['batch_name'] ?? 'Not assigned')) ?></dd></div>
                                        <div><dt>Group Coach</dt><dd><?= esc((string) ($portalPlayer['group_coach_name'] ?? 'Not assigned')) ?></dd></div>
                                        <div><dt>Personal Coach</dt><dd><?= esc((string) ($portalPlayer['personal_coach_name'] ?? 'Not assigned')) ?></dd></div>
                                        <div><dt>Status</dt><dd><span class="mcc-status-badge mcc-status-badge-success"><?= esc(ucfirst((string) ($portalPlayer['status'] ?? 'active'))) ?></span></dd></div>
                                    </dl>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($portalPage === 'achievements'): ?>
                    <section class="mcc-card mcc-portal-section" id="achievements" aria-labelledby="portal-achievements-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Published recognition</span>
                                <h2 id="portal-achievements-heading">Achievements</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Read only</span>
                        </div>
                        <p class="mcc-portal-section-description">
                            Published achievements connected to this portal account are shown here.
                        </p>
                        <?php if ($portalAwards === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No published achievements available</strong>
                                <span>Published recognition will appear here when it is available for this account.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-achievement-list">
                                <?php foreach ($portalAwards as $award): ?>
                                    <article class="mcc-portal-achievement-item">
                                        <div>
                                            <span class="mcc-eyebrow"><?= esc((string) $award['category']) ?></span>
                                            <h3><?= esc((string) $award['title']) ?></h3>
                                            <?php if (trim((string) ($award['description'] ?? '')) !== ''): ?>
                                                <p><?= esc((string) $award['description']) ?></p>
                                            <?php endif; ?>
                                            <small><?= esc((string) $award['branch_name']) ?></small>
                                        </div>
                                        <span class="mcc-status-badge mcc-status-badge-success">Published</span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="mcc-portal-kpi-grid" aria-label="Portal summary">
                    <?php foreach ([
                        ['label' => 'Attendance', 'value' => $attendanceRate . '%', 'note' => $attendancePresent . ' of ' . $attendanceTotal . ' recorded', 'icon' => 'fa-chart-line'],
                        ['label' => 'Upcoming sessions', 'value' => str_pad((string) count($portalSessions), 2, '0', STR_PAD_LEFT), 'note' => 'Scheduled sessions', 'icon' => 'fa-calendar-check'],
                        ['label' => 'Linked players', 'value' => str_pad((string) count($portalPlayers), 2, '0', STR_PAD_LEFT), 'note' => $portalType === 'Parent' ? 'Children linked' : 'Current account', 'icon' => 'fa-user-group'],
                        ['label' => 'Branch', 'value' => $branchName, 'note' => 'Authorized academy', 'icon' => 'fa-building'],
                    ] as $kpi): ?>
                        <article class="mcc-portal-kpi mcc-card">
                            <span><?= esc($kpi['label']) ?></span>
                            <strong><?= esc($kpi['value']) ?></strong>
                            <small><?= esc($kpi['note']) ?></small>
                            <i class="fa-regular <?= esc($kpi['icon']) ?>" aria-hidden="true"></i>
                        </article>
                    <?php endforeach; ?>
                </section>

                <div class="mcc-portal-content-grid">
                    <section class="mcc-card mcc-portal-section" id="schedule" aria-labelledby="portal-schedule-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Training plan</span>
                                <h2 id="portal-schedule-heading">Upcoming schedule</h2>
                            </div>
                            <a class="mcc-button mcc-button-secondary mcc-button-small" href="#schedule">View schedule</a>
                        </div>
                        <?php if ($portalSessions === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No upcoming sessions</strong>
                                <span>Your scheduled training sessions will appear here.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-schedule-list">
                                <?php foreach ($portalSessions as $session): ?>
                                    <?php
                                    $sessionDate = date_create((string) ($session['starts_at'] ?? ''));
                                    $sessionStatus = strtolower((string) ($session['status'] ?? 'scheduled'));
                                    $sessionClass = in_array($sessionStatus, ['cancelled', 'postponed'], true)
                                        ? 'warning'
                                        : 'success';
                                    ?>
                                    <article>
                                        <time datetime="<?= esc((string) ($session['starts_at'] ?? '')) ?>">
                                            <strong><?= esc($sessionDate ? $sessionDate->format('d') : '—') ?></strong>
                                            <span><?= esc($sessionDate ? strtoupper($sessionDate->format('M')) : '—') ?></span>
                                        </time>
                                        <div>
                                            <strong><?= esc($sessionDate ? $sessionDate->format('l · h:i A') : 'Time not available') ?></strong>
                                            <span><?= esc((string) ($session['batch_name'] ?? 'Training session')) ?> · <?= esc((string) ($session['branch_name'] ?? $branchName)) ?></span>
                                        </div>
                                        <span class="mcc-status-badge mcc-status-badge-<?= esc($sessionClass) ?>"><?= esc(ucfirst($sessionStatus)) ?></span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="attendance" aria-labelledby="portal-attendance-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Attendance overview</span>
                                <h2 id="portal-attendance-heading">Attendance summary</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-success">On track</span>
                        </div>
                        <div class="mcc-portal-attendance-total"><strong><?= esc((string) $attendanceRate) ?>%</strong><span>attendance rate</span></div>
                        <div
                            class="mcc-portal-progress"
                            role="progressbar"
                            aria-label="Attendance percentage"
                            aria-valuenow="<?= esc((string) $attendanceRate) ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        ><span style="width: <?= esc((string) $attendanceRate) ?>%"></span></div>
                        <div class="mcc-portal-attendance-grid">
                            <?php foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $status => $label): ?>
                                <span><b><?= esc(str_pad((string) ($attendanceCounts[$status] ?? 0), 2, '0', STR_PAD_LEFT)) ?></b> <?= esc($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mcc-portal-table-wrap mcc-portal-attendance-table-wrap">
                            <table class="mcc-portal-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Session</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($portalAttendance === []): ?>
                                        <tr><td colspan="3"><span class="mcc-muted">No attendance records available.</span></td></tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($portalAttendance, 0, 10) as $attendance): ?>
                                            <?php $attendanceDate = date_create((string) ($attendance['recorded_at'] ?? $attendance['starts_at'] ?? '')); ?>
                                            <?php $attendanceStatus = strtolower((string) ($attendance['status'] ?? 'unknown')); ?>
                                            <tr>
                                                <td><?= esc($attendanceDate ? $attendanceDate->format('d M Y') : 'Not available') ?></td>
                                                <td><?= esc($attendanceDate ? $attendanceDate->format('h:i A') : 'Training session') ?></td>
                                                <td><span class="mcc-status-badge mcc-status-badge-<?= esc($attendanceStatus === 'present' ? 'success' : 'warning') ?>"><?= esc(ucfirst($attendanceStatus)) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="mcc-portal-content-grid">
                    <section class="mcc-card mcc-portal-section" id="academy" aria-labelledby="portal-academy-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Academy assignment</span>
                                <h2 id="portal-academy-heading"><?= esc($branchName) ?></h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-success">Active</span>
                        </div>
                        <div class="mcc-portal-academy-summary">
                            <div><span class="mcc-portal-academy-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><strong><?= esc($branchName) ?></strong><small>Branch</small></div>
                            <div><span class="mcc-portal-academy-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span><strong><?= esc($programName) ?></strong><small>Program</small></div>
                            <div><span class="mcc-portal-academy-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><strong><?= esc($batchName) ?></strong><small>Batch</small></div>
                            <div><span class="mcc-portal-academy-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><strong><?= count($portalSessions) ?></strong><small>Upcoming sessions</small></div>
                        </div>
                        <div class="mcc-portal-info-grid">
                            <div><span>Branch</span><strong><?= esc($branchName) ?></strong></div>
                            <div><span>Program</span><strong><?= esc($programName) ?></strong></div>
                            <div><span>Batch</span><strong><?= esc($batchName) ?></strong></div>
                            <div><span>Training days</span><strong>See upcoming schedule</strong></div>
                            <div><span>Training time</span><strong>See upcoming schedule</strong></div>
                        </div>
                        <?php if ($portalSessions !== []): ?>
                            <?php $nextSession = $portalSessions[0]; $nextSessionDate = date_create((string) ($nextSession['starts_at'] ?? '')); ?>
                            <div class="mcc-portal-next-session">
                                <span class="mcc-eyebrow">Next scheduled session</span>
                                <strong><?= esc($nextSessionDate ? $nextSessionDate->format('l, d M Y · h:i A') : 'Time not available') ?></strong>
                                <span><?= esc((string) ($nextSession['branch_name'] ?? $branchName)) ?> · <?= esc((string) ($nextSession['batch_name'] ?? $batchName)) ?></span>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="coach" aria-labelledby="portal-coach-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Coaching team</span>
                                <h2 id="portal-coach-heading">Your coaches</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Read only</span>
                        </div>
                        <div class="mcc-portal-coach-grid">
                            <?php foreach ([
                                ['label' => 'Group Coach', 'name' => $groupCoachName, 'icon' => 'fa-users'],
                                ['label' => 'Personal Coach', 'name' => $personalCoachName, 'icon' => 'fa-user-tie'],
                            ] as $coachCard): ?>
                                <?php $coachAssigned = $coachCard['name'] !== 'Not assigned'; ?>
                                <article class="mcc-portal-coach-card">
                                    <span class="mcc-portal-coach-icon"><i class="fa-solid <?= esc($coachCard['icon']) ?>" aria-hidden="true"></i></span>
                                    <div>
                                        <span class="mcc-eyebrow"><?= esc($coachCard['label']) ?></span>
                                        <h3><?= esc($coachCard['name']) ?></h3>
                                        <span class="mcc-status-badge mcc-status-badge-<?= $coachAssigned ? 'success' : 'neutral' ?>">
                                            <?= $coachAssigned ? 'Assigned' : 'Not assigned' ?>
                                        </span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="mcc-portal-coach-meta">
                            <div><span>Branch</span><strong><?= esc($branchName) ?></strong></div>
                            <div><span>Program</span><strong><?= esc($programName) ?></strong></div>
                            <div><span>Batch</span><strong><?= esc($batchName) ?></strong></div>
                            <div><span>Upcoming sessions</span><strong><?= count($portalSessions) ?></strong></div>
                        </div>
                    </section>
                </div>

                <div class="mcc-portal-content-grid">
                    <section class="mcc-card mcc-portal-section" id="matches" aria-labelledby="portal-matches-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Match centre</span>
                                <h2 id="portal-matches-heading">Upcoming matches</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Squad watch</span>
                        </div>
                        <div class="mcc-empty-state" role="status">
                            <strong>Match information is not available in this portal view.</strong>
                            <span>Only authorized player-linked records are shown.</span>
                        </div>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="tournaments" aria-labelledby="portal-tournaments-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Competition calendar</span>
                                <h2 id="portal-tournaments-heading">Tournaments</h2>
                            </div>
                        </div>
                        <div class="mcc-empty-state" role="status">
                            <strong>Tournament information is not available in this portal view.</strong>
                            <span>Authorized tournament projections will appear when enabled.</span>
                        </div>
                    </section>
                </div>

                <section class="mcc-card mcc-portal-section" id="training" aria-labelledby="portal-training-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Training plan</span>
                            <h2 id="portal-training-heading">Training schedule</h2>
                        </div>
                        <span class="mcc-status-badge mcc-status-badge-success">Attendance up to date</span>
                    </div>
                    <div class="mcc-portal-table-wrap">
                        <table class="mcc-portal-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Branch</th>
                                    <th>Batch</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($portalSessions === []): ?>
                                    <tr><td colspan="5"><span class="mcc-muted">No upcoming sessions available.</span></td></tr>
                                <?php else: ?>
                                    <?php foreach ($portalSessions as $session): ?>
                                        <?php $sessionDate = date_create((string) ($session['starts_at'] ?? '')); ?>
                                        <tr>
                                            <td><?= esc($sessionDate ? $sessionDate->format('d M Y') : 'Not available') ?></td>
                                            <td><?= esc($sessionDate ? $sessionDate->format('h:i A') : 'Not available') ?></td>
                                            <td><?= esc((string) ($session['branch_name'] ?? 'Not available')) ?></td>
                                            <td><?= esc((string) ($session['batch_name'] ?? 'Not assigned')) ?></td>
                                            <td><span class="mcc-status-badge mcc-status-badge-info"><?= esc(ucfirst((string) ($session['status'] ?? 'scheduled'))) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="mcc-portal-content-grid">
                    <section class="mcc-card mcc-portal-section" id="development" aria-labelledby="portal-development-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Coach records</span>
                                <h2 id="portal-development-heading">Performance</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Read only</span>
                        </div>
                        <?php if ($portalPerformance === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No performance records available</strong>
                                <span>Coach-approved performance records will appear here.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-record-list">
                                <?php foreach (array_slice($portalPerformance, 0, 6) as $record): ?>
                                    <article>
                                        <div>
                                            <strong><?= esc(ucfirst((string) ($record['record_type'] ?? 'Performance'))) ?></strong>
                                            <span><?= esc(mcc_user_date($record['recorded_on'] ?? null)) ?></span>
                                        </div>
                                        <small>Runs: <?= esc((string) ($record['runs'] ?? '—')) ?> · Wickets: <?= esc((string) ($record['wickets'] ?? '—')) ?> · Catches: <?= esc((string) ($record['catches'] ?? '—')) ?></small>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="activity" aria-labelledby="portal-activity-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Latest updates</span>
                                <h2 id="portal-activity-heading">Recent activity</h2>
                            </div>
                        </div>
                        <div class="mcc-portal-activity-list">
                            <?php if ($portalGoals === [] && $portalSessionPlans === [] && $portalFeedback === []): ?>
                                <div class="mcc-empty-state" role="status">
                                    <strong>No coaching updates available</strong>
                                    <span>Goals, session plans, and feedback will appear here when recorded.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($portalGoals, 0, 3) as $goal): ?>
                                    <p><i class="fa-solid fa-bullseye" aria-hidden="true"></i><span><strong>Goal: <?= esc((string) ($goal['title'] ?? 'Untitled')) ?></strong><small><?= esc(mcc_user_date($goal['created_at'] ?? null)) ?> · <?= esc((string) ($goal['coach_name'] ?? 'Coach')) ?></small></span></p>
                                <?php endforeach; ?>
                                <?php foreach (array_slice($portalSessionPlans, 0, 2) as $plan): ?>
                                    <p><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span><strong>Session plan updated</strong><small><?= esc(mcc_user_date($plan['created_at'] ?? null)) ?> · <?= esc((string) ($plan['coach_name'] ?? 'Coach')) ?></small></span></p>
                                <?php endforeach; ?>
                                <?php foreach (array_slice($portalFeedback, 0, 2) as $feedback): ?>
                                    <p><i class="fa-solid fa-message" aria-hidden="true"></i><span><strong>Quarterly feedback available</strong><small><?= esc((string) ($feedback['feedback_year'] ?? '')) ?> · <?= esc((string) ($feedback['coach_name'] ?? 'Coach')) ?></small></span></p>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="mcc-card mcc-portal-section" id="notices" aria-labelledby="portal-important-notices-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Keep informed</span>
                            <h2 id="portal-important-notices-heading">Important notices</h2>
                        </div>
                    </div>
                    <div class="mcc-empty-state" role="status">
                        <strong>No notices available</strong>
                        <span>Academy notices will appear here when published for this portal account.</span>
                    </div>
                </section>

                <section class="mcc-card mcc-portal-section" id="documents" aria-labelledby="portal-documents-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Academy records</span>
                            <h2 id="portal-documents-heading">Documents</h2>
                        </div>
                        <a class="mcc-button mcc-button-secondary mcc-button-small" href="#documents">View documents</a>
                    </div>
                    <div class="mcc-empty-state" role="status">
                        <strong>Documents are not available in this portal view.</strong>
                        <span>Private documents remain restricted to authorized staff workflows.</span>
                    </div>
                </section>

                <section class="mcc-card mcc-portal-section" aria-labelledby="portal-actions-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Explore</span>
                            <h2 id="portal-actions-heading">Quick actions</h2>
                        </div>
                    </div>
                    <div class="mcc-portal-actions">
                        <a class="mcc-button mcc-button-primary" href="#schedule"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i>View schedule</a>
                        <a class="mcc-button mcc-button-secondary" href="#attendance"><i class="fa-regular fa-clipboard-check" aria-hidden="true"></i>View attendance</a>
                        <a class="mcc-button mcc-button-secondary" href="#profile"><i class="fa-regular fa-user" aria-hidden="true"></i>View profile</a>
                        <a class="mcc-button mcc-button-secondary" href="#contact"><i class="fa-regular fa-envelope" aria-hidden="true"></i>Contact academy</a>
                    </div>
                </section>

                <footer class="mcc-portal-footer">
                    <strong>MCC Cricket Club</strong>
                    <span>Need help? Contact the academy support desk.</span>
                </footer>
            </main>
        </div>
    </div>
    <script src="<?= site_url('assets/js/app.js') ?>"></script>
</body>
</html>
