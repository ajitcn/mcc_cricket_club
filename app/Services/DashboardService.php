<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardModel;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardService
{
    public function data(array $filters = []): array
    {
        $authorization = service('authorization');
        $authorizedBranches = array_values(array_filter(
            array_map('intval', (array) session('authorizedBranches')),
            static fn (int $id): bool => $id > 0
        ));
        $canAllBranches = $authorization->can('rbac.manage')
            && in_array('Super Admin', service('auth')->roles(), true);
        $requestedBranch = (int) ($filters['branch_id'] ?? 0);
        $allBranches = strtolower((string) ($filters['scope'] ?? '')) === 'all';
        $branches = $allBranches && $canAllBranches
            ? $authorizedBranches
            : ($requestedBranch > 0 && in_array($requestedBranch, $authorizedBranches, true)
                ? [$requestedBranch]
                : $authorizedBranches);
        $model = new DashboardModel();
        $now = new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $today = $now->format('Y-m-d');
        $selectedMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($filters['month'] ?? ''))
            ? (string) $filters['month']
            : $now->format('Y-m');
        $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['date'] ?? ''))
            ? (string) $filters['date']
            : $today;
        [$periodStart, $periodEnd] = $this->sessionPeriod(
            $now,
            (string) ($filters['date'] ?? ''),
            (string) ($filters['month'] ?? '')
        );
        $periodEndUtc = $periodEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $can = static fn (string $permission): bool => $authorization->can($permission);
        $cards = [];
        $sections = [
            'attention' => [],
            'attentionVisible' => false,
            'sessions' => [],
            'trials' => [],
            'activity' => [],
        ];
        $analytics = [
            'players' => [],
            'playersVisible' => false,
            'ageGroups' => [],
            'batches' => [],
            'batchesVisible' => false,
            'coaching' => [],
            'coachingVisible' => false,
            'coachingByBranch' => [],
            'coachingByBatch' => [],
            'workload' => [],
            'branches' => [],
            'branchesVisible' => false,
            'coaches' => [],
            'coachesVisible' => false,
            'trials' => ['pending' => 0, 'attended' => 0, 'no_show' => 0],
            'trialsVisible' => false,
            'attendance' => [
                'recorded' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
                'medical' => 0,
                'rain_cancelled' => 0,
                'coach_cancelled' => 0,
            ],
            'attendanceVisible' => false,
        ];

        if ($can('player.view')) {
            $activePlayers = $model->countBranchRows('players', $branches, ['active']);
            $newPlayers = $model->countCreatedSince('players', $branches, $monthStart->format('Y-m-d H:i:s'));
            $cards[] = [
                'label' => 'Active players',
                'value' => $activePlayers,
                'description' => $newPlayers . ' added this month',
                'icon' => 'fa-users',
                'url' => 'players',
            ];
            $analytics['players'] = $model->statusSummary(
                'players',
                $branches,
                ['active', 'inactive', 'suspended', 'left_academy', 'alumni']
            );
            $analytics['playersVisible'] = true;
            $analytics['batches'] = $model->batchDistribution($branches);
            $analytics['batchesVisible'] = true;
            $analytics['ageGroups'] = $model->ageGroupDistribution($branches);
        }

        if ($can('player.view') && $can('coach.view')) {
            $analytics['branches'] = $model->branchDistribution($branches, $periodEndUtc);
            $analytics['branchesVisible'] = true;
            $cards[] = [
                'label' => 'Total Branches',
                'value' => count($model->authorizedBranchOptions($branches)),
                'description' => 'Active authorized branches in scope',
                'icon' => 'fa-building',
                'url' => 'branches',
            ];
        }

        if ($can('coach.view')) {
            $analytics['coaches'] = $model->coachDirectory($branches);
            $analytics['coachesVisible'] = true;
            $cards[] = [
                'label' => 'Active Coaches',
                'value' => $model->countActiveCoaches($branches),
                'description' => 'Authorized active Coach assignments',
                'icon' => 'fa-user-tie',
                'url' => 'coaches',
            ];
        }

        if ($can('lead.view')) {
            $sections['attentionVisible'] = true;
            $leadCount = $model->countBranchRows('leads', $branches);
            $newLeads = $model->countCreatedSince('leads', $branches, $monthStart->format('Y-m-d H:i:s'));
            $followUps = $model->followUpSummary($branches, $now->format('Y-m-d H:i:s'), $today);
            $trialSummary = $model->trialSummary($branches);
            $cards[] = [
                'label' => 'Total leads',
                'value' => $leadCount,
                'description' => $newLeads . ' added this month',
                'icon' => 'fa-user-plus',
                'url' => 'crm/leads',
            ];
            $cards[] = [
                'label' => 'Active trials',
                'value' => $trialSummary['pending'],
                'description' => $trialSummary['attended'] . ' attended, ' . $trialSummary['no_show'] . ' no-show',
                'icon' => 'fa-person-running',
                'url' => 'crm/trials',
            ];
            $cards[] = [
                'label' => 'Follow-ups',
                'value' => $followUps['pending'],
                'description' => $followUps['overdue'] . ' overdue · ' . $followUps['today'] . ' due today',
                'icon' => 'fa-phone-volume',
                'url' => 'crm/follow-ups',
            ];
            $analytics['trials'] = $trialSummary;
            $analytics['trialsVisible'] = true;
            $sections['trials'] = $model->upcomingTrials($branches, $today);
            $sections['activity']['leads'] = $model->recentLeads($branches, 4);
            $sections['activity']['followUps'] = $model->recentFollowUps($branches, 4);
            $sections['attention']['followUps'] = $model->overdueFollowUps($branches, $now->format('Y-m-d H:i:s'));
            $sections['attention']['trials'] = $model->trialsAwaitingSelection($branches);
        }

        if ($can('admission.view')) {
            $sections['attentionVisible'] = true;
            $openAdmissions = $model->countBranchRows(
                'admissions',
                $branches,
                ['INTAKE', 'IN_REVIEW', 'DOCUMENTS_PENDING', 'APPROVED']
            );
            $newAdmissions = $model->countCreatedSince('admissions', $branches, $monthStart->format('Y-m-d H:i:s'));
            $cards[] = [
                'label' => 'Open admissions',
                'value' => $openAdmissions,
                'description' => $newAdmissions . ' started this month',
                'icon' => 'fa-clipboard-list',
                'url' => 'admissions',
            ];
            $sections['activity']['admissions'] = $model->recentAdmissions($branches, 4);
            $sections['attention']['admissions'] = $model->statusSummary(
                'admissions',
                $branches,
                ['INTAKE', 'IN_REVIEW', 'DOCUMENTS_PENDING']
            );
        }

        if ($can('session.update')) {
            $periodStartUtc = $periodStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $nowUtc = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $eliteSessions = $model->countEliteSessionsForPeriod($branches, $periodStartUtc, $periodEndUtc);
            $upcomingSessions = $model->upcomingSessionsForPeriod(
                $branches,
                $periodStartUtc,
                $periodEndUtc,
                $nowUtc
            );
            $cards[] = [
                'label' => 'Upcoming sessions',
                'value' => $model->countUpcomingSessionsForPeriod(
                    $branches,
                    $periodStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    $periodEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                ),
                'description' => 'Scheduled after now in the selected period',
                'icon' => 'fa-calendar-days',
                'url' => 'sessions',
            ];
            $cards[] = [
                'label' => 'Group coaching sessions',
                'value' => $model->countGroupSessionsForPeriod($branches, $periodStartUtc, $periodEndUtc),
                'description' => 'Sessions with more than one active roster Player',
                'icon' => 'fa-people-group',
                'url' => 'sessions',
            ];
            $cards[] = [
                'label' => 'Personal coaching sessions',
                'value' => $model->countPersonalSessionsForPeriod($branches, $periodStartUtc, $periodEndUtc),
                'description' => 'Sessions with exactly one active roster Player',
                'icon' => 'fa-user-check',
                'url' => 'sessions',
            ];
            $analytics['coaching'] = [
                'group' => $model->countGroupSessionsForPeriod($branches, $periodStartUtc, $periodEndUtc),
                'personal' => $model->countPersonalSessionsForPeriod($branches, $periodStartUtc, $periodEndUtc),
                'elite' => $eliteSessions,
            ];
            $coachingDistribution = $model->coachingDistribution($branches, $periodStartUtc, $periodEndUtc);
            $analytics['coachingByBranch'] = $coachingDistribution['by_branch'];
            $admissionCoaching = $model->admissionCoachingDistribution($branches);
            foreach ($admissionCoaching as $branchId => $counts) {
                if (! isset($analytics['coachingByBranch'][$branchId])) {
                    $analytics['coachingByBranch'][$branchId] = $counts;
                }
            }
            if ((int) ($analytics['coaching']['group'] ?? 0)
                + (int) ($analytics['coaching']['personal'] ?? 0)
                + (int) ($analytics['coaching']['elite'] ?? 0) === 0
            ) {
                $analytics['coaching']['group'] = array_sum(array_column($admissionCoaching, 'group'));
                $analytics['coaching']['personal'] = array_sum(array_column($admissionCoaching, 'personal'));
                $analytics['coaching']['elite'] = array_sum(array_column($admissionCoaching, 'elite'));
            }
            $analytics['coachingByBatch'] = $coachingDistribution['by_batch'];
            if ($analytics['coachingByBatch'] === []) {
                $analytics['coachingByBatch'] = $model->admissionCoachingByBatch($branches);
            }
            $analytics['workload'] = $model->coachWorkload(
                $branches,
                $periodStartUtc,
                $periodEndUtc
            );
            $analytics['coachingVisible'] = true;
            $sections['sessions'] = $upcomingSessions;

            $cards[] = [
                'label' => 'Elite Coaching',
                'value' => $eliteSessions,
                'description' => 'Explicit Elite sessions in the selected period',
                'icon' => 'fa-star',
                'url' => 'sessions',
            ];
        }

        if ($can('attendance.view')) {
            $attendance = $model->attendanceSummary($branches);
            $cards[] = [
                'label' => 'Attendance records',
                'value' => $attendance['recorded'],
                'description' => 'Recorded source events · ' . $attendance['present'] . ' present',
                'icon' => 'fa-clipboard-check',
                'url' => 'attendance',
            ];
            $analytics['attendance'] = $attendance;
            $analytics['attendanceVisible'] = true;
        }

        if ($can('match.view')) {
            $cards[] = [
                'label' => 'Scheduled matches',
                'value' => $model->countBranchRows('matches', $branches, ['scheduled']),
                'description' => 'Persisted match records',
                'icon' => 'fa-baseball',
                'url' => 'matches',
            ];
            $sections['activity']['matches'] = $model->upcomingRows('matches', 'starts_at', $branches);
        }

        if ($can('tournament.view')) {
            $cards[] = [
                'label' => 'Active tournaments',
                'value' => $model->countBranchRows('tournaments', $branches, ['active']),
                'description' => 'Persisted tournament records',
                'icon' => 'fa-trophy',
                'url' => 'tournaments',
            ];
            $sections['activity']['tournaments'] = $model->upcomingRows('tournaments', 'starts_on', $branches);
        }

        if ($can('development.view')) {
            $cards[] = [
                'label' => 'Active development plans',
                'value' => $model->countBranchRows('development_plans', $branches, ['active']),
                'description' => 'Persisted plans; no derived KPI',
                'icon' => 'fa-route',
                'url' => 'development',
            ];
        }

        if ($can('rbac.manage')) {
            $cards[] = [
                'label' => 'Enabled staff',
                'value' => $model->countEnabledUsers($branches),
                'description' => 'Accounts currently enabled',
                'icon' => 'fa-user-shield',
                'url' => 'admin/users',
            ];
        }

        $quickActions = [];
        foreach ([
            ['lead.create', 'Create lead', 'crm/leads/new', 'fa-user-plus'],
            ['admission.create', 'New admission', 'admissions', 'fa-clipboard-list'],
            ['player.update', 'Manage players', 'players', 'fa-users'],
            ['session.create', 'Schedule session', 'sessions', 'fa-calendar-plus'],
            ['attendance.record', 'Record attendance', 'attendance', 'fa-clipboard-check'],
        ] as [$permission, $label, $url, $icon]) {
            if ($can($permission)) {
                $quickActions[] = ['label' => $label, 'url' => $url, 'icon' => $icon];
            }
        }

        return [
            'cards' => $cards,
            'sections' => $sections,
            'analytics' => $analytics,
            'quickActions' => $quickActions,
            'contextDate' => $now->format('l, d F Y'),
            'branchOptions' => $model->authorizedBranchOptions($authorizedBranches),
            'selectedBranch' => $requestedBranch > 0 && in_array($requestedBranch, $authorizedBranches, true)
                ? $requestedBranch
                : 0,
            'allBranchesSelected' => $allBranches && $canAllBranches,
            'canAllBranches' => $canAllBranches,
            'selectedMonth' => $selectedMonth,
            'selectedDate' => $selectedDate,
            'unavailableMetrics' => [
                'Medical metrics — Not available on this dashboard',
                'Coach availability - Not available on this dashboard',
                'Performance/KPI - Not available on this dashboard',
            ],
        ];
    }

    /**
     * Resolve the selected day or month into an MCC-local half-open period.
     * An explicit day takes precedence over an explicit month; with no filter,
     * the current local day is used.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function sessionPeriod(DateTimeImmutable $now, string $date, string $month): array
    {
        $timezone = new DateTimeZone(config('App')->appTimezone);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

            if ($start !== false) {
                return [$start, $start->modify('+1 day')];
            }
        }

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $start = DateTimeImmutable::createFromFormat('!Y-m', $month, $timezone);

            if ($start !== false) {
                return [$start, $start->modify('+1 month')];
            }
        }

        $start = $now->setTime(0, 0, 0);

        return [$start, $start->modify('+1 day')];
    }
}
