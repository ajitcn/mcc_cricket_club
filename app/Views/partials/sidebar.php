<?php
$currentPath = trim(uri_string(), '/');
$groups = service('navigation')->staffGroups();
$icon = static function (string $name): string {
    $icons = [
        'home' => 'fa-house',
        'settings' => 'fa-gear',
        'users' => 'fa-users',
        'inbox' => 'fa-inbox',
        'user' => 'fa-user',
        'calendar' => 'fa-calendar-days',
        'check' => 'fa-check',
        'chart' => 'fa-chart-line',
        'clipboard' => 'fa-clipboard-list',
        'comments' => 'fa-comments',
        'layers' => 'fa-layer-group',
        'trophy' => 'fa-trophy',
    ];
    return '<i class="mcc-nav-fa-icon fa-solid ' . esc($icons[$name] ?? $icons['user']) . '" aria-hidden="true"></i>';
};
$icons = [
    'CRM' => 'users',
    'Admissions' => 'inbox',
    'Players' => 'user',
    'Achievements' => 'trophy',
    'Coaching' => 'users',
    'Attendance' => 'check',
    'Competitions' => 'calendar',
    'Grounds & Equipment' => 'settings',
    'Reports' => 'chart',
    'Communication' => 'inbox',
    'Portals' => 'user',
    'Scheduling' => 'calendar',
    'Administration' => 'settings',
];
$slug = static fn (string $label): string => 'mcc-nav-group-' . strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $label));
?>
<aside class="mcc-sidebar" id="mcc-sidebar" aria-label="Primary navigation">
    <div class="mcc-brand">
        <div class="mcc-brand-logo-wrap">
            <img
                class="mcc-brand-logo"
                src="<?= site_url('assets/img/logo.png') ?>"
                alt="MCC Cricket Club"
                onerror="this.hidden = true"
            >
        </div>
        <button class="mcc-sidebar-collapse" type="button" data-sidebar-collapse aria-controls="mcc-sidebar" aria-expanded="true" aria-label="Collapse navigation" title="Collapse navigation"><i class="mcc-sidebar-collapse-icon fa-solid fa-chevron-left" aria-hidden="true"></i><span class="mcc-sidebar-control-label">Collapse</span></button>
    </div>
    <div class="mcc-sidebar-label">Main menu</div>
    <nav class="mcc-sidebar-nav">
        <a class="mcc-nav-link mcc-nav-dashboard <?= $currentPath === '' ? 'is-active' : '' ?>" href="<?= site_url('/') ?>" title="Dashboard"><span class="mcc-nav-icon"><?= $icon('home') ?></span><span class="mcc-nav-link-label">Dashboard</span></a>
        <?php foreach ($groups as $group): $groupId = $slug($group['label']); $activeGroup = false; foreach ($group['items'] as $item) { if (service('navigation')->isActive($currentPath, $item)) { $activeGroup = true; break; } } ?>
            <div class="mcc-nav-group" data-nav-group data-default-open="<?= $activeGroup ? 'true' : 'false' ?>">
                <button class="mcc-nav-group-toggle" type="button" data-nav-group-toggle aria-controls="<?= esc($groupId) ?>" aria-expanded="<?= $activeGroup ? 'true' : 'false' ?>" title="<?= esc($group['label']) ?>">
                    <span class="mcc-nav-icon"><?= $icon($icons[$group['label']] ?? 'user') ?></span><span class="mcc-nav-group-label"><?= esc($group['label']) ?></span><i class="mcc-nav-group-chevron fa-solid fa-chevron-<?= $activeGroup ? 'down' : 'right' ?>" aria-hidden="true"></i>
                </button>
                <div class="mcc-nav-group-items" id="<?= esc($groupId) ?>" <?= $activeGroup ? '' : 'hidden' ?>>
                    <?php foreach ($group['items'] as $item): $active = $item['state'] === 'implemented' && service('navigation')->isActive($currentPath, $item); ?>
                        <?php if ($item['state'] === 'implemented'): ?><a class="mcc-nav-link <?= $active ? 'is-active' : '' ?>" href="<?= site_url((string) $item['url']) ?>" title="<?= esc($item['label']) ?>"><span class="mcc-nav-icon"><?= $icon($item['icon']) ?></span><span class="mcc-nav-link-label"><?= esc($item['label']) ?></span></a><?php else: ?><span class="mcc-nav-link mcc-nav-link-disabled" aria-disabled="true" title="<?= esc($item['label']) ?> — Coming Soon"><span class="mcc-nav-icon"><?= $icon($item['icon']) ?></span><span class="mcc-nav-link-label"><?= esc($item['label']) ?></span><span class="mcc-coming-soon">Coming Soon</span></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>
    <div class="mcc-sidebar-footer"><span class="mcc-status-dot"></span><span class="mcc-sidebar-footer-copy">Secure workspace</span><small class="mcc-sidebar-footer-copy">v1.0 foundation</small></div>
</aside>
