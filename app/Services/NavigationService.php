<?php

declare(strict_types=1);

namespace App\Services;

final class NavigationService
{
    /**
     * @return list<array{permission: string, label: string, url: string, active_prefix: string}>
     */
    public function dashboard(): array
    {
        return $this->visible([
            $this->item('rbac.manage', 'Administration', 'admin'),
            $this->item('lead.view', 'CRM', 'crm/leads', 'crm/leads'),
            $this->item('lead.view', 'Follow-Ups', 'crm/follow-ups', 'crm/follow-ups'),
            $this->item('lead.view', 'Trials', 'crm/trials', 'crm/trials'),
            $this->item('admission.view', 'Admission Test', 'admission-tests', 'admission-tests'),
              $this->item('admission.view', 'Meeting Follow-up', 'meeting-followups', 'meeting-followups'),
            $this->item('admission.view', 'Admissions', 'admissions'),
            $this->item('player.view', 'Players', 'players'),
            $this->item('program.view', 'Programs', 'programs'),
            $this->item('batch.view', 'Batches', 'batches'),
            $this->item('session.update', 'Scheduling', 'sessions'),
            $this->item('attendance.view', 'Attendance', 'attendance'),
            $this->item('fitness.view', 'Fitness', 'fitness'),
        ]);
    }

    /**
     * @return list<array{permission: string, label: string, url: string, active_prefix: string}>
     */
    public function staff(): array
    {
        $items = [];

        foreach ($this->staffGroups() as $group) {
            $items = array_merge($items, $group['items']);
        }

        return $items;
    }

    /**
     * @return list<array{
     *     label: string,
     *     items: list<array{
     *         permission: ?string,
     *         label: string,
     *         url: ?string,
     *         active_prefix: ?string,
     *         state: string,
     *         icon: string
     *     }>
     * }>
     */
    public function staffGroups(): array
    {
        $definitions = [
            [
                'label' => 'CRM',
                'items' => [
                    $this->item('lead.view', 'Leads', 'crm/leads', 'crm/leads', 'implemented', 'users'),
                    $this->item('lead.view', 'Follow-Ups', 'crm/follow-ups', 'crm/follow-ups', 'implemented', 'calendar'),
                    $this->item('lead.view', 'Trials', 'crm/trials', 'crm/trials', 'implemented', 'calendar'),
                    $this->item('admission.view', 'Admission Test', 'admission-tests', 'admission-tests', 'implemented', 'clipboard'),
                     $this->item('admission.view', 'Meeting Follow-up', 'meeting-followups', 'meeting-followups', 'implemented', 'calendar'),
                    $this->item('marketing.view', 'Marketing Dashboard', 'marketing', 'marketing', 'implemented', 'inbox'),
                ],
            ],
            [
                'label' => 'Admissions',
                'items' => [
                    $this->item('admission.view', 'Admissions', 'admissions', 'admissions', 'implemented', 'inbox'),
                    $this->item('admission.view', 'Fee Installments', 'admissions/fee-installments', 'admissions/fee-installments', 'implemented', 'inbox'),
                    $this->item(
                        'admission.view',
                        'Age Groups',
                        'admission-masters/age_group',
                        'admission-masters/age_group',
                        'implemented',
                        'users',
                    ),
                    $this->item('batch.view', 'Batches', 'batches', 'batches', 'implemented', 'users'),
                ],
            ],
            [
                'label' => 'Players',
                'items' => [
                    $this->item('player.view', 'Players', 'players', 'players', 'implemented', 'user'),
                    $this->item('player.goal.set', 'Goals', 'player-goals', 'player-goals', 'implemented', 'check'),
                    $this->item('player.session_plan.view', 'Session Plans', 'player-session-plans', 'player-session-plans', 'implemented', 'clipboard'),
                    $this->item('player.feedback.view', 'Coach Feedback', 'player-feedback', 'player-feedback', 'implemented', 'comments'),
                    $this->item('player360.view', 'Player 360°', 'player-360', 'player-360', 'implemented', 'user'),
                    $this->item('development.view', 'Development', 'development', 'development', 'implemented', 'user'),
                    $this->item('fitness.view', 'Fitness', 'fitness', 'fitness', 'implemented', 'check'),
                    $this->item('document.view', 'Documents', 'documents', 'documents', 'implemented', 'inbox'),
                ],
            ],
            [
                'label' => 'Achievements',
                'items' => [
                    $this->item(
                        'achievement.view',
                        'Achievement Catalog',
                        'achievements',
                        'achievements',
                        'implemented',
                        'trophy',
                        ['achievements/awards', 'achievements/reports'],
                    ),
                    $this->item(
                        'achievement.view',
                        'Awards',
                        'achievements/awards',
                        'achievements/awards',
                        'implemented',
                        'trophy',
                    ),
                    $this->item(
                        'report.view',
                        'Achievement Reports',
                        'achievements/reports',
                        'achievements/reports',
                        'implemented',
                        'chart',
                    ),
                ],
            ],
            [
                'label' => 'Coaching',
                'items' => [
                    $this->item('coach.view', 'Coaches', 'coaches', 'coaches', 'implemented', 'users'),
                    $this->item('program.view', 'Programs', 'programs', 'programs', 'implemented', 'layers'),
                    $this->item('session.update', 'Sessions', 'sessions', 'sessions', 'implemented', 'calendar'),
                    $this->item('performance.view', 'Performance', 'performance', 'performance', 'implemented', 'chart'),
                ],
            ],
            [
                'label' => 'Attendance',
                'items' => [
                    $this->item('attendance.view', 'Attendance', 'attendance', 'attendance', 'implemented', 'check'),
                ],
            ],
            [
                'label' => 'Competitions',
                'items' => [
                    $this->item('match.view', 'Matches', 'matches', 'matches', 'implemented', 'calendar'),
                    $this->item(
                        'tournament.view',
                        'Tournaments',
                        'tournaments',
                        'tournaments',
                        'implemented',
                        'calendar',
                        ['tournaments/fixtures'],
                    ),
                    $this->item('tournament.view', 'Fixtures', 'tournaments/fixtures', 'tournaments/fixtures', 'implemented', 'calendar'),
                    $this->item('opponent.view', 'Opponents', 'matches/opponents', 'matches/opponents', 'implemented', 'users'),
                ],
            ],
            [
                'label' => 'Grounds & Equipment',
                'items' => [
                    $this->item('ground.view', 'Grounds', 'grounds', 'grounds', 'implemented', 'settings'),
                    $this->item('equipment.view', 'Equipment', 'equipment', 'equipment', 'implemented', 'settings'),
                    $this->item('equipment.view', 'Equipment Assignments', 'equipment/assignments', 'equipment/assignments', 'implemented', 'settings'),
                ],
            ],
            [
                'label' => 'Reports',
                'items' => [
                    $this->item('report.view', 'Report Center', 'reports', 'reports', 'implemented', 'check'),
                ],
            ],
            [
                'label' => 'Communication',
                'items' => [
                    $this->item('notification.view', 'Notifications', 'notifications', 'notifications', 'implemented', 'inbox'),
                ],
            ],
            [
                'label' => 'Portals',
                'items' => [
                    $this->item('parent_portal.view', 'Parent Portal', 'parent-portal', 'parent-portal', 'implemented', 'user'),
                ],
            ],
            [
                'label' => 'Administration',
                'items' => [
                    $this->item('rbac.manage', 'Branches', 'admin/branches', 'admin', 'implemented', 'settings'),
                    $this->item('rbac.manage', 'Users', 'admin/users', 'admin', 'implemented', 'users'),
                    $this->item('rbac.manage', 'Roles', 'admin/roles', 'admin', 'implemented', 'settings'),
                    $this->item('rbac.manage', 'Permissions', 'admin/permissions', 'admin', 'implemented', 'check'),
                    $this->item('rbac.manage', 'Lead Sources', 'admin/lead-sources', 'admin', 'implemented', 'inbox'),
                    $this->item('rbac.manage', 'Lead Statuses', 'admin/lead-statuses', 'admin', 'implemented', 'check'),
                    $this->item('audit.view', 'Audit Logs', 'admin/audit', 'admin', 'implemented', 'check'),
                ],
            ],
        ];

        return array_values(array_filter(array_map(
            function (array $group): array {
                $implemented = array_values(array_filter(
                    $group['items'],
                    static fn (array $item): bool => $item['state'] === 'implemented'
                ));
                $planned = array_values(array_filter(
                    $group['items'],
                    static fn (array $item): bool => $item['state'] === 'coming_soon'
                ));
                $group['items'] = array_merge($this->visible($implemented), $planned);

                return $group;
            },
            $definitions
        ), static fn (array $group): bool => $group['items'] !== []));
    }

    /** @param array{url?: string, active_prefix?: string, active_exclude_prefixes?: list<string>} $item */
    public function isActive(string $currentPath, array $item): bool
    {
        $path = trim($currentPath, '/');
        $prefix = trim((string) ($item['active_prefix'] ?? $item['url'] ?? ''), '/');
        $excludedPrefixes = $item['active_exclude_prefixes'] ?? [];

        foreach ($excludedPrefixes as $excludedPrefix) {
            $excludedPrefix = trim($excludedPrefix, '/');
            if ($excludedPrefix !== '' && ($path === $excludedPrefix || str_starts_with($path, $excludedPrefix . '/'))) {
                return false;
            }
        }

        return $prefix !== ''
            && ($path === $prefix || str_starts_with($path, $prefix . '/'));
    }

    /**
     * @param list<array{
     *     permission: ?string,
     *     label: string,
     *     url: ?string,
     *     active_prefix: ?string,
     *     state: string,
     *     icon: string,
     *     active_exclude_prefixes: list<string>
     * }> $items
     * @return list<array{
     *     permission: ?string,
     *     label: string,
     *     url: ?string,
     *     active_prefix: ?string,
     *     state: string,
     *     icon: string,
     *     active_exclude_prefixes: list<string>
     * }>
     */
    private function visible(array $items): array
    {
        return array_values(array_filter(
            $items,
            static fn (array $item): bool => service('authorization')->can($item['permission'])
        ));
    }

    /**
     * @return array{permission: string, label: string, url: string, active_prefix: string, state: string, icon: string}
     */
    private function item(
        string $permission,
        string $label,
        string $url,
        ?string $activePrefix = null,
        string $state = 'implemented',
        string $icon = 'user',
        array $activeExcludePrefixes = []
    ): array {
        return [
            'permission' => $permission,
            'label' => $label,
            'url' => $url,
            'active_prefix' => $activePrefix ?? $url,
            'state' => $state,
            'icon' => $icon,
            'active_exclude_prefixes' => $activeExcludePrefixes,
        ];
    }

    /** @return array{permission: null, label: string, url: null, active_prefix: null, state: string, icon: string, active_exclude_prefixes: list<string>} */
    private function planned(string $label, string $icon): array
    {
        return [
            'permission' => null,
            'label' => $label,
            'url' => null,
            'active_prefix' => null,
            'state' => 'coming_soon',
            'icon' => $icon,
            'active_exclude_prefixes' => [],
        ];
    }
}
