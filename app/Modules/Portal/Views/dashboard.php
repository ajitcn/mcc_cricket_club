<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student &amp; Parent Portal - MCC Cricket Club</title>
    <link rel="stylesheet" href="<?= site_url('assets/css/app.css?v=20261111-portal-v36') ?>">
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
    $portalMatches = is_array($dashboard['matches'] ?? null) ? $dashboard['matches'] : [];
    $portalTournaments = is_array($dashboard['tournaments'] ?? null) ? $dashboard['tournaments'] : [];
    $portalFees = is_array($dashboard['fees'] ?? null) ? $dashboard['fees'] : [];
    $portalDocuments = is_array($dashboard['documents'] ?? null) ? $dashboard['documents'] : [];
    $portalConsent = is_array($dashboard['consent'] ?? null) ? $dashboard['consent'] : [];
    $portalProfile = is_array($dashboard['profile'] ?? null) ? $dashboard['profile'] : [];
    $portalHostel = is_array($dashboard['hostel'] ?? null) ? $dashboard['hostel'] : [];
    $portalMedical = is_array($dashboard['medical'] ?? null) ? $dashboard['medical'] : [];
    $portalAdmissions = is_array($dashboard['admissions'] ?? null) ? $dashboard['admissions'] : [];
    $portalRenewals = is_array($dashboard['renewals'] ?? null) ? $dashboard['renewals'] : [];
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
        'fees' => ['Fees', 'Read-only installment and payment status for your linked admission.'],
        'attendance' => ['Attendance', 'Your attendance record and current attendance rate.'],
        'development' => ['Development', 'Coach-recorded performance and development information.'],
        'progress' => ['Progress', 'Goals, session plans, and Coach feedback updates.'],
        'admission' => ['Admission Details', 'Read-only admission and current enrollment information.'],
        'renewals' => ['Renewal History', 'Read-only history of admission periods and renewals.'],
        'goals' => ['Player Goals', 'Read-only goals recorded by your Coach.'],
        'session-plans' => ['Session Plans', 'Read-only individual training plans.'],
        'feedback' => ['Coach Feedback', 'Read-only quarterly Coach feedback records.'],
        'batting' => ['Batting', 'Read-only batting performance records.'],
        'bowling' => ['Bowling', 'Read-only bowling performance records.'],
        'fielding' => ['Fielding', 'Read-only fielding performance records.'],
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
                <div class="mcc-portal-topbar-context" aria-label="Current portal page">
                    <span><?= esc($portalHeading[0]) ?></span>
                    <small><?= esc($branchName) ?></small>
                </div>
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
                <div class="mcc-portal-navigation-label">
                    <span>Portal menu</span>
                    <small><?= esc($portalType) ?> access</small>
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
                    <a class="<?= $portalPage === 'fees' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/fees') ?>"><i class="fa-solid fa-receipt" aria-hidden="true"></i><span>Fees</span></a>
                    <a class="<?= $portalPage === 'achievements' ? 'is-active' : '' ?>" href="<?= site_url($portalAchievementsUrl) ?>"><i class="fa-solid fa-medal" aria-hidden="true"></i><span>Achievements</span></a>
                    <a class="<?= $portalPage === 'attendance' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/attendance') ?>"><i class="fa-regular fa-clipboard-check" aria-hidden="true"></i><span>Attendance</span></a>
                    <a class="<?= $portalPage === 'development' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/development') ?>"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Development</span></a>
                    <a class="<?= $portalPage === 'progress' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/progress') ?>"><i class="fa-solid fa-bullseye" aria-hidden="true"></i><span>Progress</span></a>
                    <span class="mcc-portal-nav-group">Player records</span>
                    <a class="<?= $portalPage === 'admission' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/admission') ?>"><i class="fa-solid fa-id-card" aria-hidden="true"></i><span>Admission Details</span></a>
                    <a class="<?= $portalPage === 'renewals' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/renewals') ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Renewal History</span></a>
                    <a class="<?= $portalPage === 'goals' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/goals') ?>"><i class="fa-solid fa-bullseye" aria-hidden="true"></i><span>Goals</span></a>
                    <a class="<?= $portalPage === 'session-plans' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/session-plans') ?>"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span>Session Plans</span></a>
                    <a class="<?= $portalPage === 'feedback' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/feedback') ?>"><i class="fa-solid fa-comments" aria-hidden="true"></i><span>Coach Feedback</span></a>
                    <a class="<?= $portalPage === 'batting' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/batting') ?>"><i class="fa-solid fa-baseball-bat-ball" aria-hidden="true"></i><span>Batting</span></a>
                    <a class="<?= $portalPage === 'bowling' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/bowling') ?>"><i class="fa-solid fa-baseball" aria-hidden="true"></i><span>Bowling</span></a>
                    <a class="<?= $portalPage === 'fielding' ? 'is-active' : '' ?>" href="<?= site_url($portalBaseUrl . '/fielding') ?>"><i class="fa-solid fa-hand" aria-hidden="true"></i><span>Fielding</span></a>
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
                <?php if ($portalPage === 'dashboard'): ?>
                    <?php
                    $nextSession = is_array($portalSessions[0] ?? null) ? $portalSessions[0] : [];
                    $nextSessionTitle = trim((string) ($nextSession['title'] ?? $nextSession['session_name'] ?? 'Training session')) ?: 'Training session';
                    $nextSessionDate = trim((string) ($nextSession['session_date'] ?? $nextSession['date'] ?? '')) ?: 'Schedule to be confirmed';
                    $nextSessionTime = trim((string) ($nextSession['start_time'] ?? $nextSession['time'] ?? ''));
                    $goalCount = count($portalGoals);
                    $planCount = count($portalSessionPlans);
                    $feedbackCount = count($portalFeedback);
                    $performanceCount = count($portalPerformance);
                    ?>
                    <section class="mcc-student-dashboard-v3" aria-labelledby="student-dashboard-v3-title">
                        <div class="mcc-student-dashboard-v3-hero">
                            <div class="mcc-student-dashboard-v3-hero-copy">
                                <span class="mcc-student-dashboard-v3-kicker">MCC PLAYER HUB</span>
                                <h1 id="student-dashboard-v3-title">Good to see you, <?= esc($portalName) ?>.</h1>
                                <p>Your training snapshot, upcoming work, and academy updates in one place.</p>
                                <div class="mcc-student-dashboard-v3-tags">
                                    <span><?= esc($branchName) ?></span>
                                    <span><?= esc($programName) ?></span>
                                    <span><?= esc($batchName) ?></span>
                                </div>
                            </div>
                            <div class="mcc-student-dashboard-v3-hero-profile" aria-label="Portal account summary">
                                <span class="mcc-student-dashboard-v3-avatar"><?= esc($portalInitials) ?></span>
                                <strong><?= esc($portalType) ?> portal</strong>
                                <small>Private academy view</small>
                            </div>
                        </div>

                        <div class="mcc-student-dashboard-v3-grid mcc-student-dashboard-v3-grid-top">
                            <article class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-next-session">
                                <div class="mcc-student-dashboard-v3-card-heading">
                                    <span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-blue"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
                                    <div>
                                        <span class="mcc-student-dashboard-v3-label">NEXT ON YOUR PLAN</span>
                                        <h2><?= esc($nextSessionTitle) ?></h2>
                                    </div>
                                </div>
                                <div class="mcc-student-dashboard-v3-session-meta">
                                    <span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= esc($nextSessionDate) ?></span>
                                    <?php if ($nextSessionTime !== ''): ?>
                                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i><?= esc($nextSessionTime) ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="mcc-student-dashboard-v3-link" href="<?= site_url($portalBaseUrl . '/schedule') ?>">Open schedule <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </article>

                            <article class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-attendance-card">
                                <div class="mcc-student-dashboard-v3-card-heading">
                                    <span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-green"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>
                                    <div>
                                        <span class="mcc-student-dashboard-v3-label">ATTENDANCE</span>
                                        <h2><?= esc((string) $attendanceRate) ?>%</h2>
                                    </div>
                                </div>
                                <div class="mcc-student-dashboard-v3-progress-track" aria-label="Attendance progress">
                                    <span style="width: <?= esc((string) min(100, max(0, $attendanceRate))) ?>%"></span>
                                </div>
                                <p><?= esc((string) $attendancePresent) ?> present out of <?= esc((string) $attendanceTotal) ?> recorded sessions</p>
                                <a class="mcc-student-dashboard-v3-link" href="<?= site_url($portalBaseUrl . '/attendance') ?>">View attendance <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </article>

                            <article class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-player-card">
                                <span class="mcc-student-dashboard-v3-label">PLAYER PROFILE</span>
                                <div class="mcc-student-dashboard-v3-player-name">
                                    <span class="mcc-student-dashboard-v3-player-avatar"><?= esc($portalInitials) ?></span>
                                    <div>
                                        <h2><?= esc($portalName) ?></h2>
                                        <small><?= esc($portalType) ?> account</small>
                                    </div>
                                </div>
                                <div class="mcc-student-dashboard-v3-player-facts">
                                    <span><strong>Coach</strong><?= esc($groupCoachName) ?></span>
                                    <span><strong>Personal coach</strong><?= esc($personalCoachName) ?></span>
                                </div>
                                <a class="mcc-student-dashboard-v3-link" href="<?= site_url($portalBaseUrl . '/profile') ?>">View profile <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </article>
                        </div>

                        <div class="mcc-student-dashboard-v3-grid mcc-student-dashboard-v3-grid-middle">
                            <section class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-focus-card">
                                <div class="mcc-student-dashboard-v3-card-heading">
                                    <div>
                                        <span class="mcc-student-dashboard-v3-label">YOUR DEVELOPMENT</span>
                                        <h2>Keep building your game</h2>
                                    </div>
                                    <a class="mcc-student-dashboard-v3-link" href="<?= site_url($portalBaseUrl . '/progress') ?>">View all</a>
                                </div>
                                <div class="mcc-student-dashboard-v3-development-grid">
                                    <a href="<?= site_url($portalBaseUrl . '/development') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-purple"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></span><strong><?= esc((string) $goalCount) ?></strong><small>Goals</small></a>
                                    <a href="<?= site_url($portalBaseUrl . '/development') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-orange"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span><strong><?= esc((string) $planCount) ?></strong><small>Session plans</small></a>
                                    <a href="<?= site_url($portalBaseUrl . '/development') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-teal"><i class="fa-solid fa-message" aria-hidden="true"></i></span><strong><?= esc((string) $feedbackCount) ?></strong><small>Coach feedback</small></a>
                                    <a href="<?= site_url($portalBaseUrl . '/development') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-pink"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><strong><?= esc((string) $performanceCount) ?></strong><small>Performance records</small></a>
                                </div>
                            </section>

                            <section class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-academy-card">
                                <div class="mcc-student-dashboard-v3-card-heading">
                                    <div>
                                        <span class="mcc-student-dashboard-v3-label">ACADEMY CONNECTION</span>
                                        <h2>Where you train</h2>
                                    </div>
                                    <a class="mcc-student-dashboard-v3-link" href="<?= site_url($portalBaseUrl . '/academy') ?>">Details</a>
                                </div>
                                <dl class="mcc-student-dashboard-v3-facts">
                                    <div><dt>Branch</dt><dd><?= esc($branchName) ?></dd></div>
                                    <div><dt>Program</dt><dd><?= esc($programName) ?></dd></div>
                                    <div><dt>Batch</dt><dd><?= esc($batchName) ?></dd></div>
                                    <div><dt>Training status</dt><dd><span class="mcc-student-dashboard-v3-status">Active</span></dd></div>
                                </dl>
                            </section>
                        </div>

                        <section class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-overview-card" aria-labelledby="student-dashboard-record-summary-title">
                            <div class="mcc-student-dashboard-v3-card-heading">
                                <div>
                                    <span class="mcc-student-dashboard-v3-label">ACADEMY RECORDS</span>
                                    <h2 id="student-dashboard-record-summary-title">Your records at a glance</h2>
                                </div>
                            </div>
                            <div class="mcc-student-dashboard-v3-development-grid">
                                <a href="<?= site_url($portalBaseUrl . '/admission') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-blue"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span><strong><?= esc((string) count($portalAdmissions)) ?></strong><small>Admissions</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/matches') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-orange"><i class="fa-solid fa-baseball-bat-ball" aria-hidden="true"></i></span><strong><?= esc((string) count($portalMatches)) ?></strong><small>Matches</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/tournaments') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-purple"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span><strong><?= esc((string) count($portalTournaments)) ?></strong><small>Tournaments</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/fees') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-green"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><strong><?= esc((string) count($portalFees)) ?></strong><small>Fee records</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/documents') ?>"><span class="mcc-student-dashboard-v3-icon mcc-student-dashboard-v3-icon-teal"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span><strong><?= esc((string) count($portalDocuments)) ?></strong><small>Documents</small></a>
                            </div>
                        </section>

                        <section class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-records-card">
                            <div class="mcc-student-dashboard-v3-card-heading">
                                <div>
                                    <span class="mcc-student-dashboard-v3-label">LATEST RECORDS</span>
                                    <h2>Your latest academy activity</h2>
                                </div>
                                <a class="mcc-student-dashboard-v3-link" href="<?= site_url($portalBaseUrl . '/progress') ?>">Open progress <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </div>
                            <div class="mcc-student-dashboard-v3-records-grid">
                                <div class="mcc-student-dashboard-v3-record-column">
                                    <h3><i class="fa-regular fa-calendar-days" aria-hidden="true"></i> Upcoming sessions</h3>
                                    <?php if ($portalSessions === []): ?>
                                        <p class="mcc-student-dashboard-v3-record-empty">No upcoming sessions recorded.</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($portalSessions, 0, 3) as $session): ?>
                                            <a class="mcc-student-dashboard-v3-record" href="<?= site_url($portalBaseUrl . '/schedule') ?>">
                                                <span class="mcc-student-dashboard-v3-record-dot mcc-student-dashboard-v3-record-dot-blue"></span>
                                                <span><strong><?= esc((string) ($session['batch_name'] ?? 'Training session')) ?></strong><small><?= esc((string) ($session['starts_at'] ?? 'Schedule date pending')) ?></small></span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="mcc-student-dashboard-v3-record-column">
                                    <h3><i class="fa-regular fa-clipboard-check" aria-hidden="true"></i> Recent attendance</h3>
                                    <?php if ($portalAttendance === []): ?>
                                        <p class="mcc-student-dashboard-v3-record-empty">No attendance records recorded.</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($portalAttendance, 0, 3) as $attendanceRecord): ?>
                                            <?php $attendanceStatus = ucfirst((string) ($attendanceRecord['status'] ?? 'Unknown')); ?>
                                            <a class="mcc-student-dashboard-v3-record" href="<?= site_url($portalBaseUrl . '/attendance') ?>">
                                                <span class="mcc-student-dashboard-v3-record-dot mcc-student-dashboard-v3-record-dot-green"></span>
                                                <span><strong><?= esc($attendanceStatus) ?></strong><small><?= esc((string) ($attendanceRecord['recorded_at'] ?? 'Recorded date pending')) ?></small></span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="mcc-student-dashboard-v3-record-column">
                                    <h3><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Latest development</h3>
                                    <?php if ($portalFeedback !== []): ?>
                                        <?php $latestFeedback = $portalFeedback[0]; ?>
                                        <a class="mcc-student-dashboard-v3-record" href="<?= site_url($portalBaseUrl . '/progress') ?>">
                                            <span class="mcc-student-dashboard-v3-record-dot mcc-student-dashboard-v3-record-dot-purple"></span>
                                            <span><strong>Coach feedback</strong><small><?= esc((string) ($latestFeedback['feedback_year'] ?? 'Current period')) ?> · <?= esc((string) ($latestFeedback['coach_name'] ?? 'Coach')) ?></small></span>
                                        </a>
                                    <?php elseif ($portalGoals !== []): ?>
                                        <?php $latestGoal = $portalGoals[0]; ?>
                                        <a class="mcc-student-dashboard-v3-record" href="<?= site_url($portalBaseUrl . '/progress') ?>">
                                            <span class="mcc-student-dashboard-v3-record-dot mcc-student-dashboard-v3-record-dot-orange"></span>
                                            <span><strong><?= esc((string) ($latestGoal['title'] ?? 'Current goal')) ?></strong><small><?= esc((string) ($latestGoal['coach_name'] ?? 'Coach')) ?></small></span>
                                        </a>
                                    <?php else: ?>
                                        <p class="mcc-student-dashboard-v3-record-empty">No development record recorded.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <section class="mcc-student-dashboard-v3-card mcc-student-dashboard-v3-quick-links">
                            <div class="mcc-student-dashboard-v3-card-heading">
                                <div>
                                    <span class="mcc-student-dashboard-v3-label">QUICK ACCESS</span>
                                    <h2>What would you like to check?</h2>
                                </div>
                            </div>
                            <div class="mcc-student-dashboard-v3-quick-link-grid">
                                <a href="<?= site_url($portalBaseUrl . '/schedule') ?>"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i><span>Schedule</span><small>Upcoming sessions</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/attendance') ?>"><i class="fa-regular fa-clipboard-check" aria-hidden="true"></i><span>Attendance</span><small>Session history</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/coach') ?>"><i class="fa-solid fa-person-chalkboard" aria-hidden="true"></i><span>Coach</span><small>Training support</small></a>
                                <a href="<?= site_url($portalBaseUrl . '/documents') ?>"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>Documents</span><small>Private records</small></a>
                            </div>
                        </section>
                    </section>
                <?php endif; ?>
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
                                            <div class="mcc-portal-achievement-meta">
                                                <small><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= esc((string) ($award['branch_name'] ?? 'Branch')) ?></small>
                                                <small><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= esc(mcc_user_date($award['published_at'] ?? $award['created_at'] ?? null)) ?></small>
                                            </div>
                                        </div>
                                        <span class="mcc-status-badge mcc-status-badge-success"><i class="fa-solid fa-check" aria-hidden="true"></i> Published</span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($portalPage === 'profile'): ?>
                    <section class="mcc-card mcc-portal-section" id="contact" aria-labelledby="portal-contact-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Account information</span>
                                <h2 id="portal-contact-heading">Profile and contact details</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Read only</span>
                        </div>
                        <p class="mcc-portal-section-description">
                            These details are shown from the academy record. Contact the academy to request a correction; portal users cannot edit official records directly.
                        </p>
                        <?php if ($portalProfile === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No profile details available</strong>
                                <span>Your account is not linked to an active academy record.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-consent-grid">
                                <?php foreach ($portalProfile as $profileRecord): ?>
                                    <article class="mcc-portal-consent-card">
                                        <div><span><?= $portalType === 'Parent' ? 'Guardian' : 'Student' ?></span><strong><?= esc((string) ($profileRecord['full_name'] ?? '—')) ?></strong></div>
                                        <div><span>Email</span><strong><?= esc((string) ($profileRecord['email'] ?? 'Not provided')) ?></strong></div>
                                        <div><span>Mobile</span><strong><?= esc((string) ($profileRecord['phone'] ?? 'Not provided')) ?></strong></div>
                                        <?php if ($portalType === 'Student'): ?>
                                            <div><span>Date of birth</span><strong><?= esc((string) ($profileRecord['date_of_birth'] ?? 'Not provided')) ?></strong></div>
                                        <?php endif; ?>
                                        <div><span>Branch</span><strong><?= esc((string) ($profileRecord['branch_name'] ?? 'Not assigned')) ?></strong></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="hostel" aria-labelledby="portal-hostel-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Accommodation</span>
                                <h2 id="portal-hostel-heading">Hostel and transport status</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Read only</span>
                        </div>
                        <p class="mcc-portal-section-description">
                            Requirement and payment-plan status is shown from the admission record. Allocation, route, and charge changes remain academy-managed.
                        </p>
                        <?php if ($portalHostel === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No hostel or transport record available</strong>
                                <span>Contact the academy for accommodation or transport assistance.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-consent-grid">
                                <?php foreach ($portalHostel as $hostelRecord): ?>
                                    <article class="mcc-portal-consent-card">
                                        <div><span>Admission</span><strong><?= esc((string) ($hostelRecord['admission_number'] ?? '—')) ?></strong></div>
                                        <div><span>Hostel requirement</span><strong><?= (int) ($hostelRecord['hostel_required'] ?? 0) === 1 ? 'Requested' : 'Not requested' ?></strong></div>
                                        <div><span>Approval status</span><strong><?= esc((string) ($hostelRecord['status'] ?? 'Not recorded')) ?></strong></div>
                                        <div><span>Payment plan</span><strong><?= esc((string) ($hostelRecord['hostel_payment_plan'] ?? 'Not recorded')) ?></strong></div>
                                        <div><span>Allocation</span><strong>Contact academy</strong></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="medical" aria-labelledby="portal-medical-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Health record status</span>
                                <h2 id="portal-medical-heading">Medical document status</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Status only</span>
                        </div>
                        <p class="mcc-portal-section-description">
                            The portal shows submission, verification, and expiry status only. Medical details and certificates remain restricted to authorized academy staff.
                        </p>
                        <?php if ($portalMedical === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No medical status available</strong>
                                <span>Contact the academy if a medical document has been submitted.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-consent-grid">
                                <?php foreach ($portalMedical as $medicalRecord): ?>
                                    <article class="mcc-portal-consent-card">
                                        <div><span>Submission</span><strong>Recorded</strong></div>
                                        <div><span>Verification</span><strong><?= esc((string) ($medicalRecord['verification_status'] ?? 'Pending')) ?></strong></div>
                                        <div><span>Security scan</span><strong><?= esc((string) ($medicalRecord['scanner_status'] ?? 'Pending')) ?></strong></div>
                                        <div><span>Expiry</span><strong><?= esc((string) ($medicalRecord['expiry_status'] ?? 'Not recorded')) ?></strong></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="consent" aria-labelledby="portal-consent-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Acknowledgements</span>
                                <h2 id="portal-consent-heading">Terms and signature status</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Read only</span>
                        </div>
                        <?php if ($portalConsent === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No linked admission acknowledgement found</strong>
                                <span>Terms and signature status will appear after the admission is linked to this Player.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-consent-grid">
                                <?php foreach ($portalConsent as $consentRecord): ?>
                                    <?php
                                    $declarationAccepted = (string) ($consentRecord['declaration_accepted'] ?? '') === '1';
                                    $studentSigned = trim((string) ($consentRecord['student_signature'] ?? '')) !== '';
                                    $parentSigned = trim((string) ($consentRecord['parent_signature'] ?? '')) !== '';
                                    $clubSigned = trim((string) ($consentRecord['authorized_signature'] ?? '')) !== '';
                                    ?>
                                    <article class="mcc-portal-consent-card">
                                        <div><span>Admission</span><strong><?= esc((string) ($consentRecord['admission_number'] ?? '—')) ?></strong></div>
                                        <div><span>Declaration</span><strong><?= $declarationAccepted ? 'Accepted' : 'Pending' ?></strong></div>
                                        <div><span>Student signature</span><strong><?= $studentSigned ? 'Recorded' : 'Pending' ?></strong></div>
                                        <div><span>Parent/Guardian signature</span><strong><?= $parentSigned ? 'Recorded' : 'Pending' ?></strong></div>
                                        <div><span>Club signature</span><strong><?= $clubSigned ? 'Recorded' : 'Pending' ?></strong></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <p class="mcc-portal-section-description">Historical signatures and acknowledgements are read-only in the portal.</p>
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

                <div class="mcc-portal-content-grid mcc-portal-overview-grid">
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

                <div class="mcc-portal-content-grid mcc-portal-academy-grid">
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

                <div class="mcc-portal-content-grid mcc-portal-competition-grid">
                    <section class="mcc-card mcc-portal-section" id="matches" aria-labelledby="portal-matches-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Match centre</span>
                                <h2 id="portal-matches-heading">Upcoming matches</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info">Squad watch</span>
                        </div>
                        <?php if ($portalMatches === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No player-linked matches available</strong>
                                <span>Authorized matches will appear here when your Player is selected.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-competition-list">
                                <?php foreach (array_slice($portalMatches, 0, 5) as $match): ?>
                                    <article>
                                        <div>
                                            <strong><?= esc((string) ($match['opponent'] ?? 'Match')) ?></strong>
                                            <small><?= esc(mcc_user_date($match['starts_at'] ?? null)) ?> · <?= esc((string) ($match['branch_name'] ?? 'Branch')) ?></small>
                                        </div>
                                        <?php
                                        $matchStatus = strtolower(trim((string) ($match['status'] ?? 'scheduled')));
                                        $matchStatusClass = match (true) {
                                            in_array($matchStatus, ['completed', 'complete', 'won', 'victory'], true) => 'success',
                                            in_array($matchStatus, ['cancelled', 'canceled', 'lost', 'abandoned'], true) => 'danger',
                                            in_array($matchStatus, ['postponed', 'delayed', 'draw'], true) => 'warning',
                                            in_array($matchStatus, ['live', 'in progress', 'playing'], true) => 'info',
                                            default => 'neutral',
                                        };
                                        ?>
                                        <span class="mcc-status-badge mcc-status-badge-<?= esc($matchStatusClass) ?>"><?= esc(ucfirst($matchStatus)) ?></span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="mcc-card mcc-portal-section" id="tournaments" aria-labelledby="portal-tournaments-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Competition calendar</span>
                                <h2 id="portal-tournaments-heading">Tournaments</h2>
                            </div>
                        </div>
                        <?php if ($portalTournaments === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No player-linked tournaments available</strong>
                                <span>Authorized tournaments will appear here when your Player is registered.</span>
                            </div>
                        <?php else: ?>
                            <div class="mcc-portal-competition-list">
                                <?php foreach (array_slice($portalTournaments, 0, 5) as $tournament): ?>
                                    <article>
                                        <div>
                                            <strong><?= esc((string) ($tournament['name'] ?? 'Tournament')) ?></strong>
                                            <small><?= esc((string) ($tournament['starts_on'] ?? 'Date pending')) ?> · <?= esc((string) ($tournament['branch_name'] ?? 'Branch')) ?></small>
                                        </div>
                                        <span class="mcc-status-badge mcc-status-badge-success"><?= esc(ucfirst((string) ($tournament['status'] ?? 'planned'))) ?></span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <?php if ($portalPage === 'fees'): ?>
                    <section class="mcc-card mcc-portal-section" id="fees" aria-labelledby="portal-fees-heading">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Read-only financial view</span>
                                <h2 id="portal-fees-heading">Fee installments</h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-info"><?= count($portalFees) ?> records</span>
                        </div>
                        <?php if ($portalFees === []): ?>
                            <div class="mcc-empty-state" role="status">
                                <strong>No fee installments available</strong>
                                <span>Fee schedules will appear here when they are linked to your active admission.</span>
                            </div>
                        <?php else: ?>
                            <?php
                            $feeScheduledTotal = array_sum(array_map(static fn (array $fee): float => (float) ($fee['amount'] ?? 0), $portalFees));
                            $feeCollectedTotal = array_sum(array_map(static fn (array $fee): float => (float) ($fee['total_received'] ?? 0), $portalFees));
                            $feePendingTotal = max(0, $feeScheduledTotal - $feeCollectedTotal);
                            ?>
                            <div class="mcc-portal-fee-summary" aria-label="Fee summary">
                                <div><span>Scheduled</span><strong><?= esc(number_format($feeScheduledTotal, 2)) ?></strong></div>
                                <div><span>Collected</span><strong class="is-collected"><?= esc(number_format($feeCollectedTotal, 2)) ?></strong></div>
                                <div><span>Pending</span><strong class="is-pending"><?= esc(number_format($feePendingTotal, 2)) ?></strong></div>
                            </div>
                            <?php
                            $paidFees = array_values(array_filter(
                                $portalFees,
                                static fn (array $fee): bool => ($fee['payment_status'] ?? '') === 'Paid'
                            ));
                            $pendingFees = array_values(array_filter(
                                $portalFees,
                                static fn (array $fee): bool => ($fee['payment_status'] ?? '') !== 'Paid'
                            ));
                            ?>
                            <div class="mcc-portal-fee-switch" role="tablist" aria-label="Fee views">
                                <input id="portal-fee-tab-paid" name="portal-fee-tab" type="radio" checked>
                                <label for="portal-fee-tab-paid" role="tab">Paid payments</label>
                                <input id="portal-fee-tab-pending" name="portal-fee-tab" type="radio">
                                <label for="portal-fee-tab-pending" role="tab">Installment / pending</label>
                            </div>
                            <div class="mcc-portal-fee-panels">
                            <?php foreach ([
                                ['key' => 'paid', 'title' => 'Paid payments', 'description' => 'Collected payments and available receipts.', 'rows' => $paidFees, 'empty' => 'No paid installments recorded.'],
                                ['key' => 'pending', 'title' => 'Installment / pending', 'description' => 'Upcoming and partially paid installments requiring attention.', 'rows' => $pendingFees, 'empty' => 'No pending installments.'],
                            ] as $feeGroup): ?>
                                <section class="mcc-portal-fee-group mcc-portal-fee-panel-<?= esc($feeGroup['key']) ?>" aria-label="<?= esc($feeGroup['title']) ?>">
                                    <div class="mcc-portal-fee-group-heading">
                                        <div>
                                            <h3><?= esc($feeGroup['title']) ?></h3>
                                            <p><?= esc($feeGroup['description']) ?></p>
                                        </div>
                                        <span class="mcc-status-badge mcc-status-badge-<?= $feeGroup['title'] === 'Paid payments' ? 'success' : 'warning' ?>">
                                            <?= count($feeGroup['rows']) ?> records
                                        </span>
                                    </div>
                                    <?php if ($feeGroup['rows'] === []): ?>
                                        <div class="mcc-empty-state" role="status"><?= esc($feeGroup['empty']) ?></div>
                                    <?php else: ?>
                                        <div class="mcc-portal-table-wrap">
                                            <table class="mcc-portal-table" aria-label="<?= esc($feeGroup['title']) ?>">
                                                <thead><tr><th>Type</th><th>Installment</th><th>Due date</th><th>Scheduled</th><th>Collected</th><th>Pending</th><th>Status</th><th>Receipt</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($feeGroup['rows'] as $fee): ?>
                                                        <?php
                                                        $scheduledAmount = (float) ($fee['amount'] ?? 0);
                                                        $collectedAmount = (float) ($fee['total_received'] ?? 0);
                                                        $pendingAmount = max(0, $scheduledAmount - $collectedAmount);
                                                        $feeStatus = (string) ($fee['payment_status'] ?? 'Unpaid');
                                                        $feeStatusClass = $feeStatus === 'Paid'
                                                            ? 'success'
                                                            : ($feeStatus === 'Partially paid' ? 'warning' : 'danger');
                                                        ?>
                                                        <tr>
                                                            <td><?= esc(ucfirst((string) ($fee['fee_type'] ?? 'Fee'))) ?></td>
                                                            <td><?= esc((string) ($fee['year_label'] ?? '—')) ?> · <?= esc((string) ($fee['installment_number'] ?? '—')) ?></td>
                                                            <td><?= esc((string) ($fee['due_date'] ?? '—')) ?></td>
                                                            <td><?= esc(number_format($scheduledAmount, 2)) ?></td>
                                                            <td><?= esc(number_format($collectedAmount, 2)) ?></td>
                                                            <td><?= esc(number_format($pendingAmount, 2)) ?></td>
                                                            <td><span class="mcc-status-badge mcc-status-badge-<?= esc($feeStatusClass) ?>"><?= esc($feeStatus) ?></span></td>
                                                            <td>
                                                                <?php if ((int) ($fee['latest_payment_id'] ?? 0) > 0): ?>
                                                                    <div class="mcc-portal-fee-actions">
                                                                        <a href="<?= esc(site_url($portalBaseUrl . '/fees/receipts/' . (int) $fee['latest_payment_id'])) ?>">View</a>
                                                                        <a href="<?= esc(site_url($portalBaseUrl . '/fees/receipts/' . (int) $fee['latest_payment_id']) . '?download=1') ?>">Download</a>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="mcc-muted">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                            </div>
                            <p class="mcc-portal-section-description">Payment changes, refunds, and receipts are managed by authorized academy staff.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="mcc-card mcc-portal-section" id="training" aria-labelledby="portal-training-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Training plan</span>
                            <h2 id="portal-training-heading">Training schedule</h2>
                        </div>
                        <?php if ($portalPage !== 'schedule'): ?>
                            <span class="mcc-status-badge mcc-status-badge-success">Attendance up to date</span>
                        <?php else: ?>
                            <span class="mcc-status-badge mcc-status-badge-info">Upcoming sessions</span>
                        <?php endif; ?>
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

                <div class="mcc-portal-content-grid mcc-portal-progress-grid">
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
                    <div class="mcc-portal-support-box">
                        <h3>Contact academy support</h3>
                        <p>Submit a structured request. Direct parent-to-coach chat is not enabled.</p>
                        <form method="post" action="<?= site_url($portalBaseUrl . '/support') ?>" class="mcc-form">
                            <?= csrf_field() ?>
                            <?php if ($portalType === 'Parent' && count($portalPlayers) > 1): ?>
                                <label>
                                    Player
                                    <select name="player_id" class="form-control" required>
                                        <?php foreach ($portalPlayers as $portalPlayer): ?>
                                            <option value="<?= (int) $portalPlayer['id'] ?>">
                                                <?= esc((string) $portalPlayer['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>
                            <label>
                                Category
                                <select name="category" class="form-control" required>
                                    <option value="academy">Academy</option>
                                    <option value="fees">Fees</option>
                                    <option value="schedule">Schedule</option>
                                    <option value="coach">Coach</option>
                                    <option value="documents">Documents</option>
                                    <option value="profile">Profile</option>
                                    <option value="other">Other</option>
                                </select>
                            </label>
                            <label>
                                Subject
                                <input name="subject" class="form-control" maxlength="180" required>
                            </label>
                            <label>
                                Message
                                <textarea name="message" class="form-control" rows="4" maxlength="5000" required></textarea>
                            </label>
                            <button class="mcc-button mcc-button-primary" type="submit">Send request</button>
                        </form>
                    </div>
                </section>

                <section class="mcc-card mcc-portal-section" id="admission" aria-labelledby="portal-admission-heading">
                    <div class="mcc-portal-section-heading"><div><span class="mcc-eyebrow">Enrollment record</span><h2 id="portal-admission-heading">Admission Details</h2></div></div>
                    <?php if ($portalAdmissions === []): ?><div class="mcc-empty-state">No admission details are available.</div><?php else: ?>
                        <div class="mcc-portal-record-grid">
                            <?php foreach ($portalAdmissions as $admission): ?>
                                <article class="mcc-portal-record-card">
                                    <div><span>Admission number</span><strong><?= esc((string) ($admission['admission_number'] ?? '—')) ?></strong></div>
                                    <div><span>Status</span><strong><?= esc(ucwords(strtolower((string) ($admission['status'] ?? '—')))) ?></strong></div>
                                    <div><span>Branch</span><strong><?= esc((string) ($admission['branch_name'] ?? '—')) ?></strong></div>
                                    <div><span>Admission date</span><strong><?= esc((string) ($admission['admission_date'] ?? $admission['joining_date'] ?? '—')) ?></strong></div>
                                    <div><span>Academic period</span><strong><?= esc((string) ($admission['year1_start_date'] ?? '—')) ?> — <?= esc((string) ($admission['year1_end_date'] ?? '—')) ?></strong></div>
                                    <div><span>Gender / age</span><strong><?= esc((string) ($admission['gender'] ?? '—')) ?> / <?= esc((string) ($admission['age'] ?? '—')) ?></strong></div>
                                </article>
                                <div class="mcc-portal-table-wrap mcc-portal-admission-details-wrap">
                                    <table class="mcc-portal-table" aria-label="Complete admission details">
                                        <tbody>
                                            <?php foreach ($admission as $field => $value): ?>
                                                <?php if (in_array($field, ['id', 'branch_id', 'converted_player_id', 'branch_name', 'installment_due_dates', 'coach_user_id', 'personal_coach_user_id', 'hostel_required'], true) || $value === null || trim((string) $value) === ''): ?>
                                                    <?php continue; ?>
                                                <?php endif; ?>
                                                <tr>
                                                    <th><?= esc(ucwords(str_replace('_', ' ', (string) $field))) ?></th>
                                                    <td><?= $field === 'declaration_accepted' ? esc(((int) $value === 1) ? 'True' : 'False') : esc((string) $value) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="mcc-card mcc-portal-section" id="renewals" aria-labelledby="portal-renewals-heading">
                    <div class="mcc-portal-section-heading"><div><span class="mcc-eyebrow">Enrollment history</span><h2 id="portal-renewals-heading">Renewal History</h2></div></div>
                    <?php if ($portalRenewals === []): ?><div class="mcc-empty-state">No renewal history is available.</div><?php else: ?>
                        <div class="mcc-portal-table-wrap"><table class="mcc-portal-table"><thead><tr><th>Period</th><th>Start</th><th>End</th><th>Age group</th><th>Plans</th><th>Status</th></tr></thead><tbody>
                            <?php foreach ($portalRenewals as $renewal): ?><tr><td><?= esc((string) ($renewal['period_number'] ?? '—')) ?></td><td><?= esc((string) ($renewal['start_date'] ?? '—')) ?></td><td><?= esc((string) ($renewal['end_date'] ?? '—')) ?></td><td><?= esc((string) ($renewal['age_group_label'] ?? '—')) ?></td><td><?= esc(implode(' / ', array_filter([(string) ($renewal['coaching_plan_label'] ?? ''), (string) ($renewal['fee_plan_label'] ?? ''), (string) ($renewal['hostel_plan_label'] ?? '')]))) ?></td><td><span class="mcc-status-badge mcc-status-badge-info"><?= esc(ucfirst((string) ($renewal['status'] ?? '—'))) ?></span></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>

                <?php foreach ([
                    'goals' => ['Player Goals', $portalGoals, ['Title' => 'title', 'Description' => 'description', 'Coach' => 'coach_name', 'Created' => 'created_at']],
                    'session-plans' => ['Session Plans', $portalSessionPlans, ['Problem' => 'problem', 'Outcome' => 'outcome', 'Coach' => 'coach_name', 'Created' => 'created_at']],
                    'feedback' => ['Coach Feedback', $portalFeedback, ['Year' => 'feedback_year', 'Quarter' => 'feedback_quarter', 'Feedback' => 'coach_feedback', 'Next focus' => 'next_focus_action']],
                ] as $recordPage => $recordConfig): ?>
                    <section class="mcc-card mcc-portal-section" id="<?= esc($recordPage) ?>" aria-labelledby="portal-<?= esc($recordPage) ?>-heading">
                        <div class="mcc-portal-section-heading"><div><span class="mcc-eyebrow">Read-only player record</span><h2 id="portal-<?= esc($recordPage) ?>-heading"><?= esc($recordConfig[0]) ?></h2></div></div>
                        <?php if ($recordConfig[1] === []): ?><div class="mcc-empty-state">No records are available.</div><?php else: ?><div class="mcc-portal-table-wrap"><table class="mcc-portal-table"><thead><tr><?php foreach (array_keys($recordConfig[2]) as $label): ?><th><?= esc($label) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($recordConfig[1] as $record): ?><tr><?php foreach ($recordConfig[2] as $key): ?><td><?= esc((string) ($record[$key] ?? '—')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <?php foreach (['batting', 'bowling', 'fielding'] as $performancePage): ?>
                    <section class="mcc-card mcc-portal-section" id="<?= esc($performancePage) ?>" aria-labelledby="portal-<?= esc($performancePage) ?>-heading">
                        <div class="mcc-portal-section-heading"><div><span class="mcc-eyebrow">Read-only performance</span><h2 id="portal-<?= esc($performancePage) ?>-heading"><?= esc(ucfirst($performancePage)) ?> Records</h2></div></div>
                        <?php $performanceType = match ($performancePage) { 'batting' => 'batting', 'bowling' => 'bowling', default => 'fielding' }; ?>
                        <?php $records = array_values(array_filter($portalPerformance, static fn (array $record): bool => strtolower((string) ($record['record_type'] ?? '')) === $performanceType)); ?>
                        <?php if ($records === []): ?><div class="mcc-empty-state">No <?= esc($performancePage) ?> records are available.</div><?php else: ?>
                            <div class="mcc-portal-table-wrap"><table class="mcc-portal-table"><thead><tr>
                                <th>Date</th>
                                <?php if ($performancePage === 'batting'): ?><th>Runs</th><th>Balls faced</th><th>Dismissals</th><th>Fours</th><th>Sixes</th><?php elseif ($performancePage === 'bowling'): ?><th>Wickets</th><th>Runs conceded</th><th>Legal balls</th><?php else: ?><th>Catches</th><th>Stumpings</th><th>Run outs</th><?php endif; ?>
                                <th>Notes</th>
                            </tr></thead><tbody><?php foreach ($records as $record): ?><tr>
                                <td><?= esc((string) ($record['recorded_on'] ?? '—')) ?></td>
                                <?php if ($performancePage === 'batting'): ?><td><?= esc((string) ($record['runs'] ?? '—')) ?></td><td><?= esc((string) ($record['balls_faced'] ?? '—')) ?></td><td><?= esc((string) ($record['dismissals'] ?? '—')) ?></td><td><?= esc((string) ($record['fours'] ?? '—')) ?></td><td><?= esc((string) ($record['sixes'] ?? '—')) ?></td><?php elseif ($performancePage === 'bowling'): ?><td><?= esc((string) ($record['wickets'] ?? '—')) ?></td><td><?= esc((string) ($record['runs_conceded'] ?? '—')) ?></td><td><?= esc((string) ($record['legal_balls'] ?? '—')) ?></td><?php else: ?><td><?= esc((string) ($record['catches'] ?? '—')) ?></td><td><?= esc((string) ($record['stumpings'] ?? '—')) ?></td><td><?= esc((string) ($record['run_outs'] ?? '—')) ?></td><?php endif; ?>
                                <td><?= esc((string) ($record['notes'] ?? '—')) ?></td>
                            </tr><?php endforeach; ?></tbody></table></div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <section class="mcc-card mcc-portal-section" id="documents" aria-labelledby="portal-documents-heading">
                    <div class="mcc-portal-section-heading">
                        <div>
                            <span class="mcc-eyebrow">Academy records</span>
                            <h2 id="portal-documents-heading">Documents</h2>
                        </div>
                        <a class="mcc-button mcc-button-secondary mcc-button-small" href="#documents">View documents</a>
                    </div>
                    <?php if ($portalDocuments === []): ?>
                        <div class="mcc-empty-state" role="status">
                            <strong>No published documents available</strong>
                            <span>Verified, non-sensitive documents will appear here when available.</span>
                        </div>
                    <?php else: ?>
                        <div class="mcc-portal-table-wrap">
                            <table class="mcc-portal-table" aria-label="Portal documents">
                                <thead><tr><th>Document</th><th>Type</th><th>Branch</th><th>Expires</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($portalDocuments as $document): ?>
                                        <tr>
                                            <td>
                                                <span><?= esc((string) ($document['original_name'] ?? 'Document')) ?></span>
                                                <a class="mcc-button mcc-button-secondary mcc-button-small" href="<?= site_url($portalBaseUrl . '/documents/' . (int) ($document['id'] ?? 0) . '/download') ?>">Download</a>
                                            </td>
                                            <td><?= esc(ucwords(str_replace('_', ' ', (string) ($document['document_type'] ?? 'Document')))) ?></td>
                                            <td><?= esc((string) ($document['branch_name'] ?? '—')) ?></td>
                                            <td><?= esc((string) ($document['expires_on'] ?? 'No expiry')) ?></td>
                                            <td><span class="mcc-status-badge mcc-status-badge-success">Verified</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
