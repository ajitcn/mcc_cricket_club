<?php

$statusLabels = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'suspended' => 'Suspended',
    'left_academy' => 'Left academy',
    'alumni' => 'Alumni',
];
$admissionStatusLabel = trim((string) ($player['admission_status'] ?? ''));
$admissionStatusLabel = $admissionStatusLabel === ''
    ? 'Not available'
    : ucwords(strtolower(str_replace(['_', '-'], ' ', $admissionStatusLabel)));
$relationshipLabels = [
    'guardian' => 'Parent / guardian',
    'emergency-contact' => 'Emergency contact',
    'primary-contact' => 'Primary contact',
];
$playerStatus = (string) ($player['status'] ?? '');
$statusClass = preg_replace('/[^a-z0-9-]/i', '', $playerStatus) ?: 'unknown';
$statusLabel = $statusLabels[$playerStatus] ?? 'Status unavailable';
$profile = is_array($player['profile'] ?? null) ? $player['profile'] : [];
$options = is_array($assignment_options ?? null)
    ? $assignment_options
    : ['branches' => [], 'batches' => [], 'coaches' => []];
$currentBatch = is_array($player['current_batch'] ?? null)
    ? $player['current_batch']
    : null;
$contacts = is_array($player['contacts'] ?? null) ? $player['contacts'] : [];
$statusHistory = is_array($player['status_history'] ?? null)
    ? $player['status_history']
    : [];
$branchHistory = is_array($player['branch_history'] ?? null)
    ? $player['branch_history']
    : [];
$batchHistory = is_array($player['batch_history'] ?? null)
    ? $player['batch_history']
    : [];
$coachHistory = is_array($player['coach_history'] ?? null)
    ? $player['coach_history']
    : [];
$goal = is_array($player['goal'] ?? null) ? $player['goal'] : null;
$sessionPlans = is_array($session_plans ?? null) ? $session_plans : [];
$feedbacks = is_array($feedbacks ?? null) ? $feedbacks : [];
$sessionProgress = is_array($player['session_progress'] ?? null) ? $player['session_progress'] : [];
$fullName = (string) ($profile['full_name'] ?? 'Player');
$nameParts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: ['P'];
$initials = strtoupper(substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) {
    $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
}
?>
<?= view('staff_header', [
    'title' => 'Player profile',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>

<div class="mcc-player-profile">
    <section class="mcc-card mcc-detail-hero mcc-player-hero" aria-labelledby="player-heading">
        <div class="mcc-player-identity">
            <div class="mcc-player-avatar-column">
                <?php if (! empty($player['profile_photo_id'])): ?>
                    <img
                        class="mcc-player-avatar mcc-player-avatar-image"
                        src="<?= site_url('documents/' . (int) $player['profile_photo_id'] . '/preview') ?>"
                        alt="Player profile photo"
                    >
                <?php else: ?>
                    <div class="mcc-player-avatar" aria-hidden="true">
                        <?= esc($initials) ?>
                    </div>
                <?php endif; ?>
                <?php if (($can_upload_photo ?? false)
                    && ($player['admission_status'] ?? '') === 'CONVERTED'): ?>
                    <form
                        method="post"
                        action="<?= site_url('players/' . $player['id'] . '/profile') ?>"
                        enctype="multipart/form-data"
                        class="mcc-player-photo-upload"
                    >
                        <label for="profile_photo">Profile image</label>
                        <input
                            class="form-control"
                            id="profile_photo"
                            name="profile_photo"
                            type="file"
                            accept="image/jpeg,image/png"
                            required
                        >
                        <?= csrf_field() ?>
                        <button class="mcc-button mcc-button-secondary" type="submit">
                            <i class="fa-solid fa-camera" aria-hidden="true"></i>
                            Upload image
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="mcc-player-heading-copy">
                <span class="mcc-eyebrow">Player profile</span>
                <h2 id="player-heading"><?= esc($fullName) ?></h2>
                <p class="mcc-player-reference">
                    Player #<?= (int) $player['id'] ?>
                    <?php if (($player['can_view_admission'] ?? false) && ! empty($player['admission_number'])): ?>
                        <span aria-hidden="true">·</span>
                        Admission reference <?= esc((string) $player['admission_number']) ?>
                    <?php endif; ?>
                </p>
                <div class="mcc-detail-badges" aria-label="Player status">
                    <span class="mcc-badge mcc-status-badge mcc-player-status-<?= esc($statusClass) ?>">
                        <?= esc($statusLabel) ?>
                    </span>
                    <span class="mcc-badge mcc-badge-info">
                        <?= esc($player['branch_name'] ?? 'Authorized branch') ?>
                    </span>
                </div>
                <div class="mcc-player-hero-contact" aria-label="Player contact details">
                    <?php if (! empty($profile['email'])): ?>
                        <a href="mailto:<?= esc((string) $profile['email']) ?>">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <?= esc($profile['email']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (! empty($profile['phone'])): ?>
                        <a href="tel:<?= esc((string) $profile['phone']) ?>">
                            <i class="fa-solid fa-phone" aria-hidden="true"></i>
                            <?= esc($profile['phone']) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mcc-detail-actions mcc-player-actions">
            <details class="mcc-detail-action-menu mcc-player-action-menu">
                <summary class="mcc-button mcc-button-primary">
                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                    Actions
                </summary>
                <div class="mcc-detail-action-dropdown mcc-player-action-dropdown" role="menu" aria-label="Player actions">
                    <a href="<?= esc(site_url('players/' . (int) $player['id'])) ?>" role="menuitem">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        View player
                    </a>
                    <?php if ($can_update ?? false): ?>
                        <a href="#player-profile-form-<?= (int) $player['id'] ?>" role="menuitem">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            Edit player
                        </a>
                    <?php endif; ?>
                    <?php if ($can_coach ?? false): ?>
                        <a href="#player-coaches-form-<?= (int) $player['id'] ?>" role="menuitem">
                            <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                            Update Coaches
                        </a>
                    <?php endif; ?>
                    <?php if ($can_transfer ?? false && count($options['branches'] ?? []) > 1): ?>
                        <a href="#player-branch-form-<?= (int) $player['id'] ?>" role="menuitem">
                            <i class="fa-solid fa-code-branch" aria-hidden="true"></i>
                            Assign Branch
                        </a>
                    <?php endif; ?>
                    <?php if ($can_batch ?? false): ?>
                        <a href="#player-batch-form-<?= (int) $player['id'] ?>" role="menuitem">
                            <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                            Assign Batch
                        </a>
                    <?php endif; ?>
                    <?php if ($can_documents ?? false): ?>
                        <a href="<?= esc(site_url('documents?player_id=' . (int) $player['id'])) ?>" role="menuitem">
                            <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
                            Documents
                        </a>
                    <?php endif; ?>
                    <?php if (($player['can_view_admission'] ?? false) && ! empty($player['admission_number'])): ?>
                        <a href="<?= esc(site_url('admissions/' . (int) $player['admission_id'] . '/page')) ?>" role="menuitem">
                            <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                            View Admission
                        </a>
                    <?php endif; ?>
                    <?php if ($can_360 ?? false): ?>
                        <a href="<?= esc(site_url('player-360/' . (int) $player['id'])) ?>" role="menuitem">
                            <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                            Player 360 view
                        </a>
                    <?php endif; ?>
                    <?php if ($can_view_session_plans ?? false): ?>
                        <a href="<?= esc(site_url('player-session-plans?player=' . (int) $player['id'])) ?>" role="menuitem">
                            <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                            Session Plans
                        </a>
                    <?php endif; ?>
                    <?php if ($can_manage_session_plans ?? false): ?>
                        <a href="<?= esc(site_url('players/' . (int) $player['id'] . '/session-plans/new')) ?>" role="menuitem">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            Create Session Plan
                        </a>
                    <?php endif; ?>
                    <?php if ($can_manage_feedback ?? false): ?>
                        <a href="<?= esc(site_url('players/' . (int) $player['id'] . '/feedback/new')) ?>" role="menuitem">
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                            Add 3-Month Feedback
                        </a>
                    <?php endif; ?>
                    <?php if ($can_view_feedback ?? false): ?>
                        <a href="<?= esc(site_url('player-feedback/annual?player_id=' . (int) $player['id'] . '&year=' . date('Y'))) ?>" role="menuitem">
                            <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                            Annual Feedback Report
                        </a>
                    <?php endif; ?>
                </div>
            </details>
            <a
                class="mcc-button mcc-button-secondary"
                href="<?= site_url('players') ?>"
                title="Back to players"
            >
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span class="mcc-button-label">Back to Players</span>
            </a>
        </div>
    </section>

    <section class="mcc-player-summary-grid" aria-label="Player summary">
        <div class="mcc-card mcc-player-summary-card">
            <div class="mcc-player-summary-card-heading">
                <i class="fa-solid fa-code-branch mcc-player-summary-icon" aria-hidden="true"></i>
                <span class="mcc-eyebrow">Branch</span>
            </div>
            <strong><?= esc($player['branch_name'] ?? 'Authorized branch') ?></strong>
            <span>Current branch ownership</span>
        </div>
        <div class="mcc-card mcc-player-summary-card">
            <div class="mcc-player-summary-card-heading">
                <i class="fa-solid fa-people-group mcc-player-summary-icon" aria-hidden="true"></i>
                <span class="mcc-eyebrow">Current batch</span>
            </div>
            <strong><?= esc($currentBatch['batch_name'] ?? 'Unassigned') ?></strong>
            <span>Active player placement</span>
        </div>
        <div class="mcc-card mcc-player-summary-card">
            <div class="mcc-player-summary-card-heading">
                <i class="fa-solid fa-user-tie mcc-player-summary-icon" aria-hidden="true"></i>
                <span class="mcc-eyebrow">Coaching</span>
            </div>
            <div class="mcc-player-coach-summary">
                <div aria-label="Group Coach">
                    <span class="mcc-eyebrow">Group Coach</span>
                    <strong><?= esc($player['group_coach_name'] ?? 'Not assigned') ?></strong>
                </div>
                <div aria-label="Personal Coach">
                    <span class="mcc-eyebrow">Personal Coach</span>
                    <strong><?= esc($player['personal_coach_name'] ?? 'Not assigned') ?></strong>
                </div>
            </div>
        </div>
        <div class="mcc-card mcc-player-summary-card">
            <div class="mcc-player-summary-card-heading">
                <i class="fa-solid fa-layer-group mcc-player-summary-icon" aria-hidden="true"></i>
                <span class="mcc-eyebrow">Age group</span>
            </div>
            <strong><?= esc($player['admission_age_group'] ?? 'Not provided') ?></strong>
            <span>Current player classification</span>
        </div>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="player-goal-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach development</span>
                <h2 id="player-goal-heading">Current goal</h2>
                <p class="mcc-card-subtitle">The latest goal set for this Player.</p>
            </div>
        </div>
        <?php if ($goal !== null): ?>
            <div class="mcc-player-goal-content">
                <h3><?= esc((string) ($goal['title'] ?? '')) ?></h3>
                <p><?= nl2br(esc((string) ($goal['description'] ?? ''))) ?></p>
                <span class="mcc-muted">Version <?= (int) ($goal['version'] ?? 1) ?></span>
            </div>
        <?php else: ?>
            <div class="mcc-empty-state mcc-detail-empty-state">
                <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                <strong>No goal has been set</strong>
                <span>An assigned Coach can set a goal from the Players list.</span>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($can_view_session_plans ?? false): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="player-session-plans-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Coach development</span>
                    <h2 id="player-session-plans-heading">Current Session Plans</h2>
                    <p class="mcc-card-subtitle">Practice plans currently recorded for this Player.</p>
                </div>
                <?php if ($can_manage_session_plans ?? false): ?>
                    <a class="mcc-button mcc-button-primary" href="<?= esc(site_url('players/' . (int) $player['id'] . '/session-plans/new')) ?>">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Create Session Plan
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($sessionPlans === []): ?>
                <div class="mcc-empty-state mcc-detail-empty-state" role="status">
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    <strong>No Session Plans have been recorded</strong>
                    <span>An assigned Coach can create a Session Plan for this Player.</span>
                </div>
            <?php else: ?>
                <div class="mcc-table-wrap mcc-table-responsive">
                    <table class="mcc-table mcc-player-session-plan-summary-table">
                        <caption class="visually-hidden">Current Session Plans for this Player</caption>
                        <thead>
                            <tr>
                                <th scope="col">Problem</th>
                                <th scope="col">Outcome</th>
                                <th scope="col">Coach</th>
                                <th scope="col">Created / Updated</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessionPlans as $sessionPlan): ?>
                                <tr>
                                    <td data-label="Problem"><?= esc((string) ($sessionPlan['problem'] ?? '')) ?></td>
                                    <td data-label="Outcome"><?= esc((string) ($sessionPlan['outcome'] ?? '')) ?></td>
                                    <td data-label="Coach"><?= esc((string) ($sessionPlan['coach_name'] ?? 'Not available')) ?></td>
                                    <td data-label="Created / Updated">
                                        <span class="mcc-table-secondary-text">Created <?= esc(mcc_user_datetime($sessionPlan['created_at'] ?? null)) ?></span>
                                        <span class="mcc-table-secondary-text">Updated <?= esc(mcc_user_datetime($sessionPlan['updated_at'] ?? null)) ?></span>
                                    </td>
                                    <td data-label="Actions">
                                        <details class="mcc-detail-action-menu mcc-player-session-plan-actions">
                                            <summary class="mcc-button mcc-button-secondary">
                                                <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                                <span>Actions</span>
                                            </summary>
                                            <div class="mcc-detail-action-dropdown" role="menu" aria-label="Session Plan actions">
                                                <a href="<?= esc(site_url('player-session-plans/' . (int) $sessionPlan['id'])) ?>" role="menuitem">
                                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                    <span>View Plan</span>
                                                </a>
                                                <?php if ($can_manage_session_plans ?? false): ?>
                                                    <a href="<?= esc(site_url('player-session-plans/' . (int) $sessionPlan['id'] . '/edit')) ?>" role="menuitem">
                                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                        <span>Edit Plan</span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($can_view_feedback ?? false): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="player-feedback-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Coach development</span>
                    <h2 id="player-feedback-heading">Coach–Student/Parent Feedbacks</h2>
                </div>
                <?php if ($can_manage_feedback ?? false): ?>
                    <a class="mcc-button mcc-button-primary" href="<?= esc(site_url('players/' . (int) $player['id'] . '/feedback/new')) ?>">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Add 3-Month Feedback
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($feedbacks === []): ?>
                <div class="mcc-empty-state"><strong>No feedback recorded</strong><span>Quarterly Coach–Student/Parent feedback will appear here.</span></div>
            <?php else: ?>
                <div class="mcc-table-wrap mcc-table-responsive mcc-player-feedback-detail-table-wrap">
                    <table class="mcc-table mcc-player-feedback-detail-table">
                        <thead>
                            <tr>
                                <th scope="col">Feedback period</th>
                                <th scope="col">Coach</th>
                                <th scope="col">Created / Updated</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php foreach ($feedbacks as $feedback): ?>
                    <?php
                    $period = ['Sep', 'Dec', 'Mar', 'Jun'][(int) $feedback['feedback_quarter'] - 1]
                        . ' ' . (int) $feedback['feedback_year'] . ' Feedback';
                    ?>
                    <tr>
                        <td data-label="Feedback period">
                            <span class="mcc-feedback-period-badge"><?= esc($period) ?></span>
                        </td>
                        <td data-label="Coach">
                            <strong><?= esc((string) ($feedback['coach_name'] ?? 'Not available')) ?></strong>
                        </td>
                        <td data-label="Created / Updated">
                            <div class="mcc-feedback-table-group mcc-feedback-timeline">
                                <span class="mcc-muted">Created <?= esc(mcc_user_datetime($feedback['created_at'] ?? null)) ?></span>
                                <span class="mcc-muted">Updated <?= esc(mcc_user_datetime($feedback['updated_at'] ?? null)) ?></span>
                            </div>
                        </td>
                        <td data-label="Actions">
                            <details class="mcc-detail-action-menu mcc-player-feedback-actions"
                                data-player-feedback-action-menu>
                                <summary class="mcc-button mcc-button-secondary">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                    <span>Actions</span>
                                </summary>
                                <div class="mcc-detail-action-dropdown" role="menu" aria-label="Feedback actions">
                                    <a href="<?= esc(site_url('player-feedback/' . (int) $feedback['id'])) ?>" role="menuitem">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        View Feedback
                                    </a>
                                    <?php if ($can_manage_feedback ?? false): ?>
                                        <a href="<?= esc(site_url('player-feedback/' . (int) $feedback['id'] . '/edit')) ?>" role="menuitem">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit Feedback
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($feedbacks === []): ?>
                <div class="mcc-form-actions"><a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-feedback/annual?player_id=' . (int) $player['id'] . '&year=' . date('Y'))) ?>">Annual Feedback Report</a></div>
            <?php endif; ?>
            <?php if ($feedbacks !== []): ?>
                <div class="mcc-form-actions"><a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-feedback/annual?player_id=' . (int) $player['id'] . '&year=' . date('Y'))) ?>">Annual Feedback Report</a></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($can_goal ?? false): ?>
        <div hidden>
            <form
                method="post"
                action="<?= esc(site_url('players/' . (int) $player['id'] . '/goal')) ?>"
                class="mcc-player-goal-form"
            >
                <?= csrf_field() ?>
                <input type="hidden" name="expected_version" value="<?= (int) ($goal['version'] ?? 0) ?>">
                <div class="mcc-form-field">
                    <label for="player-goal-title-<?= (int) $player['id'] ?>">Goal Title</label>
                    <input
                        class="form-control"
                        id="player-goal-title-<?= (int) $player['id'] ?>"
                        name="title"
                        type="text"
                        maxlength="150"
                        required
                        value="<?= esc((string) ($goal['title'] ?? '')) ?>"
                    >
                </div>
                <div class="mcc-form-field">
                    <label for="player-goal-description-<?= (int) $player['id'] ?>">Goal Description</label>
                    <textarea
                        class="form-control"
                        id="player-goal-description-<?= (int) $player['id'] ?>"
                        name="description"
                        rows="5"
                        maxlength="10000"
                        required
                    ><?= esc((string) ($goal['description'] ?? '')) ?></textarea>
                </div>
                <div class="mcc-alert mcc-alert-danger" data-player-form-feedback hidden></div>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-primary" type="submit">Save Goal</button>
                    <button class="mcc-button mcc-button-secondary" type="button" data-player-goal-cancel>Cancel</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="mcc-detail-grid mcc-player-detail-grid">
        <section class="mcc-card" id="player-profile-section" aria-labelledby="profile-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Identity</span>
                    <h2 id="profile-heading">Identity and profile</h2>
                    <p class="mcc-card-subtitle">Contact details and personal information</p>
                </div>
            </div>
            <?php if ($can_update ?? false): ?>
                <form id="player-profile-form-<?= (int) $player['id'] ?>" method="post" action="<?= site_url('players/' . $player['id'] . '/profile') ?>" class="row mcc-player-form" data-player-submit-feedback>
                    <div class="col-12 col-md-6 mb-2">
                        <label for="full_name">Full name <span aria-hidden="true">*</span></label>
                        <input
                            class="form-control"
                            id="full_name"
                            name="full_name"
                            required
                            maxlength="150"
                            autocomplete="name"
                            value="<?= esc((string) ($profile['full_name'] ?? '')) ?>"
                        >
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label for="email">Email</label>
                        <input
                            class="form-control"
                            id="email"
                            name="email"
                            type="email"
                            maxlength="190"
                            autocomplete="email"
                            value="<?= esc((string) ($profile['email'] ?? '')) ?>"
                        >
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label for="phone">Phone</label>
                        <input
                            class="form-control"
                            id="phone"
                            name="phone"
                            maxlength="40"
                            autocomplete="tel"
                            value="<?= esc((string) ($profile['phone'] ?? '')) ?>"
                        >
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <label for="date_of_birth">Date of birth</label>
                        <input
                            class="form-control"
                            id="date_of_birth"
                            name="date_of_birth"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            data-mcc-date-picker
                            data-mcc-date-picker-no-toggle
            data-mcc-date-format="d-m-Y"
            data-mcc-date-submit-format="Y-m-d"
            value="<?= esc(mcc_user_date_input($profile['date_of_birth'] ?? null)) ?>"
                        >
                    </div>
                    <input type="hidden" name="expected_updated_at" value="<?= esc($player['updated_at']) ?>">
                    <input type="hidden" name="expected_profile_revision" value="<?= (int) $player['profile_revision'] ?>">
                    <?= csrf_field() ?>
                    <div class="col-12 mcc-player-form-feedback" data-player-form-feedback hidden></div>
                    <div class="col-12 mcc-form-actions">
                        <button class="mcc-button mcc-button-primary" type="submit">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                            Save profile
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <dl class="mcc-detail-list">
                    <dt>Full name</dt>
                    <dd><?= esc($profile['full_name'] ?? 'Not provided') ?></dd>
                    <dt>Email</dt>
                    <dd>
                        <?php if (! empty($profile['email'])): ?>
                            <a href="mailto:<?= esc((string) $profile['email']) ?>">
                                <?= esc($profile['email']) ?>
                            </a>
                        <?php else: ?>
                            Not provided
                        <?php endif; ?>
                    </dd>
                    <dt>Phone</dt>
                    <dd>
                        <?php if (! empty($profile['phone'])): ?>
                            <a href="tel:<?= esc((string) $profile['phone']) ?>">
                                <?= esc($profile['phone']) ?>
                            </a>
                        <?php else: ?>
                            Not provided
                        <?php endif; ?>
                    </dd>
                    <dt>Date of birth</dt>
                    <dd><?= esc(mcc_user_date($profile['date_of_birth'] ?? null, 'Not provided')) ?></dd>
                </dl>
            <?php endif; ?>
        </section>

        <section class="mcc-card" aria-labelledby="admission-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Admission history</span>
                    <h2 id="admission-heading">Admission context</h2>
                    <p class="mcc-card-subtitle">How this player entered the academy</p>
                </div>
            </div>
            <dl class="mcc-detail-list">
                <?php if (($player['can_view_admission'] ?? false) && ! empty($player['admission_number'])): ?>
                    <dt>Admission</dt>
                    <dd>
                        <a href="<?= site_url('admissions/' . (int) $player['admission_id'] . '/page') ?>">
                            <?= esc((string) $player['admission_number']) ?>
                        </a>
                    </dd>
                    <dt>Conversion date</dt>
                    <dd><?= esc(mcc_user_datetime($player['admission_conversion_date'] ?? null)) ?></dd>
                    <dt>Branch</dt>
                    <dd><?= esc((string) ($player['admission_branch_name'] ?? 'Not available')) ?></dd>
                    <dt>Batch</dt>
                    <dd><?= esc((string) ($player['admission_batch_name'] ?? 'Not assigned')) ?></dd>
                    <dt>Age group</dt>
                    <dd><?= esc((string) ($player['admission_age_group'] ?? 'Not provided')) ?></dd>
                    <?php $admissionPeriod = is_array($player['admission_current_period'] ?? null) ? $player['admission_current_period'] : []; ?>
                    <dt>Current period</dt>
                    <dd>
                        <?= esc((string) ($admissionPeriod['number'] ?? 'Not available')) ?>
                        <?php if (! empty($admissionPeriod['start_date']) || ! empty($admissionPeriod['end_date'])): ?>
                            (<?= esc(mcc_user_date($admissionPeriod['start_date'] ?? null, 'Not provided')) ?>
                            – <?= esc(mcc_user_date($admissionPeriod['end_date'] ?? null, 'Open')) ?>)
                        <?php endif; ?>
                    </dd>
                    <?php foreach (['personal' => 'Personal Session', 'elite' => 'Elite Session'] as $progressKey => $progressLabel): ?>
                        <?php $progress = is_array($sessionProgress[$progressKey] ?? null) ? $sessionProgress[$progressKey] : []; ?>
                        <dt><?= esc($progressLabel) ?> progress</dt>
                        <dd>
                            <span class="mcc-session-progress-badge mcc-session-progress-<?= esc($progressKey) ?>">
                                <strong><?= (int) ($progress['completed'] ?? 0) ?>/<?= ($progress['target'] ?? null) === null ? '—' : (int) $progress['target'] ?></strong>
                                <span>completed</span>
                            </span>
                        </dd>
                    <?php endforeach; ?>
                    <dd>
                        <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) $player['admission_id'] . '/page') ?>">
                            View Admission
                        </a>
                    </dd>
                <?php else: ?>
                    <dt>Admission</dt>
                    <dd>Admission details are restricted.</dd>
                <?php endif; ?>
                <dt>Status</dt>
                <dd>
                    <span class="mcc-badge mcc-badge-neutral">
                        <?= esc($admissionStatusLabel) ?>
                    </span>
                </dd>
                <dt>Lead</dt>
                <dd><?= $player['lead_id'] === null ? 'Direct admission' : 'Linked lead' ?></dd>
                <dt>Trial</dt>
                <dd><?= $player['trial_id'] !== null ? 'Linked trial' : 'No linked trial' ?></dd>
                <dt>Last updated</dt>
                <dd><?= esc(mcc_user_datetime($player['updated_at'] ?? null)) ?></dd>
            </dl>
        </section>
    </div>

    <section class="mcc-card mcc-detail-card" aria-labelledby="status-heading">
        <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Lifecycle</span>
                    <h2 id="status-heading">Status and history</h2>
                    <p class="mcc-card-subtitle">Current lifecycle state and recorded changes</p>
                </div>
            <span class="mcc-badge mcc-status-badge mcc-player-status-<?= esc($statusClass) ?>">
                <?= esc($statusLabel) ?>
            </span>
        </div>
        <?php if ($can_update ?? false): ?>
            <form id="player-status-form-<?= (int) $player['id'] ?>" method="post" action="<?= site_url('players/' . $player['id'] . '/status') ?>" class="row mcc-player-form" data-player-submit-feedback>
                <div class="col-12 col-md-4 mb-2">
                    <label for="new-status">New status</label>
                    <select class="form-control" id="new-status" name="status" required>
                        <option value="">Select a new status</option>
                        <?php foreach ($statusLabels as $status => $label): ?>
                            <?php if ($status !== $playerStatus): ?>
                                <option value="<?= esc($status) ?>"><?= esc($label) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="status-reason">Reason <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="status-reason" name="reason" maxlength="255" required>
                </div>
                <input type="hidden" name="expected_updated_at" value="<?= esc($player['updated_at']) ?>">
                <input type="hidden" name="expected_profile_revision" value="<?= (int) $player['profile_revision'] ?>">
                <?= csrf_field() ?>
                <div class="col-12 mcc-player-form-feedback" data-player-form-feedback hidden></div>
                <div class="col-12 mcc-form-actions">
                    <button class="mcc-button mcc-button-primary" type="submit">
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                        Change status
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($statusHistory !== []): ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-player-status-table">
                    <caption class="visually-hidden">Player status history</caption>
                    <thead>
                        <tr>
                            <th scope="col">Change</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statusHistory as $event): ?>
                            <tr>
                                <th scope="row">
                                    <?= esc($statusLabels[$event['previous_status']] ?? $event['previous_status']) ?>
                                    to
                                    <?= esc($statusLabels[$event['new_status']] ?? $event['new_status']) ?>
                                </th>
                                <td data-label="Reason"><?= esc((string) ($event['reason'] ?? '')) ?></td>
                                <td data-label="Actor"><?= esc($event['actor_name'] ?? ('User #' . (int) $event['actor_user_id'])) ?></td>
                                <td data-label="Timestamp"><?= esc(mcc_user_datetime($event['created_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="mcc-empty-state mcc-detail-empty-state">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <strong>No status changes recorded</strong>
                <span>Status history will appear here after the player lifecycle changes.</span>
            </div>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="contacts-heading">
        <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Approved relationships</span>
                    <h2 id="contacts-heading">Contacts and relationships</h2>
                    <p class="mcc-card-subtitle">Approved parent, guardian, and emergency contacts</p>
                </div>
            <span class="mcc-muted"><?= count($contacts) ?> record<?= count($contacts) === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($contacts === []): ?>
            <div class="mcc-empty-state mcc-detail-empty-state">
                <i class="fa-solid fa-address-book" aria-hidden="true"></i>
                <strong>No approved contacts</strong>
                <span>Parent, guardian, and emergency contact records will appear here.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-contact-table mcc-player-contact-table">
                    <caption class="visually-hidden">Player contacts</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Relationship</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <th scope="row">
                                    <span class="mcc-table-primary-text"><?= esc($contact['full_name']) ?></span>
                                    <?php if ((int) $contact['is_primary'] === 1): ?>
                                        <span class="mcc-badge mcc-badge-info">Primary</span>
                                    <?php endif; ?>
                                </th>
                                <td data-label="Relationship">
                                    <?= esc($relationshipLabels[$contact['relationship_type']] ?? $contact['relationship_type']) ?>
                                </td>
                                <td data-label="Email">
                                    <?php if (! empty($contact['email'])): ?>
                                        <a href="mailto:<?= esc((string) $contact['email']) ?>">
                                            <?= esc($contact['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </td>
                                <td data-label="Phone">
                                    <?php if (! empty($contact['phone'])): ?>
                                        <a href="tel:<?= esc((string) $contact['phone']) ?>">
                                            <?= esc($contact['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($can_update ?? false): ?>
            <form id="player-contacts-form-<?= (int) $player['id'] ?>" method="post" action="<?= site_url('players/' . $player['id'] . '/contacts') ?>" class="row mcc-player-form mcc-related-form" data-player-submit-feedback>
                <div class="col-12 col-md-4 mb-2">
                    <label for="contact-name">Name <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="contact-name" name="full_name" required maxlength="150" autocomplete="name">
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="contact-type">Relationship</label>
                    <select class="form-control" id="contact-type" name="relationship_type" required>
                        <?php foreach ($relationshipLabels as $type => $label): ?>
                            <option value="<?= esc($type) ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="contact-email">Email</label>
                    <input class="form-control" id="contact-email" name="email" type="email" maxlength="190" autocomplete="email">
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="contact-phone">Phone</label>
                    <input class="form-control" id="contact-phone" name="phone" maxlength="40" autocomplete="tel">
                </div>
                <?= csrf_field() ?>
                <div class="col-12 mcc-player-form-feedback" data-player-form-feedback hidden></div>
                <div class="col-12 mcc-form-actions">
                    <button class="mcc-button mcc-button-primary" type="submit">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Add contact
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card mcc-placement-section" aria-labelledby="placement-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player placement</span>
                <h2 id="placement-heading">Branch-scoped placement</h2>
            </div>
        </div>

        <div class="mcc-placement-intro">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <p>Assignments are branch-scoped. Current placement is shown before the available changes.</p>
        </div>

        <div class="mcc-placement-current" aria-labelledby="current-placement-heading">
            <div class="mcc-card-heading">
                <div>
                    <h2 id="current-placement-heading">Current placement</h2>
                </div>
            </div>
            <div class="mcc-placement-current-row">
                <div class="mcc-placement-current-item">
                    <span>Branch</span>
                    <strong><?= esc($player['branch_name'] ?? 'Authorized branch') ?></strong>
                </div>
                <span class="mcc-placement-flow-arrow" aria-hidden="true">
                    <i class="fa-solid fa-arrow-right"></i>
                </span>
                <div class="mcc-placement-current-item">
                    <span>Batch</span>
                    <strong><?= esc(! empty($currentBatch['batch_name']) ? $currentBatch['batch_name'] : 'Not assigned') ?></strong>
                </div>
            </div>
        </div>

        <div class="mcc-placement-assignment-area" aria-labelledby="assignment-actions-heading">
            <div class="mcc-card-heading">
                <div>
                    <h2 id="assignment-actions-heading">Placement actions</h2>
                </div>
            </div>
            <p class="mcc-placement-section-note">Changes remain branch-scoped.</p>

            <div class="mcc-placement-branch-action">
        <?php if ($can_transfer ?? false && count($options['branches'] ?? []) > 1): ?>
            <form
                id="player-branch-form-<?= (int) $player['id'] ?>"
                method="post"
                action="<?= site_url('players/' . $player['id'] . '/branch') ?>"
                class="row mcc-player-form mcc-assignment-form"
                data-player-submit-feedback
            >
                <div class="col-12 mcc-assignment-form-heading">
                    <span class="mcc-eyebrow">Branch assignment</span>
                    <strong>Transfer branch</strong>
                    <span class="mcc-placement-form-help">Change the branch that owns this player placement.</span>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="target-branch">Transfer to branch</label>
                    <select class="form-control" id="target-branch" name="branch_id" required>
                        <?php foreach ($options['branches'] as $branch): ?>
                            <?php if ((int) $branch['id'] !== (int) $player['branch_id']): ?>
                                <option value="<?= (int) $branch['id'] ?>"><?= esc($branch['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="branch-effective-from">Effective from date</label>
                    <input
                        class="form-control"
                        id="branch-effective-from"
                        name="effective_from"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-picker-time-input="branch-effective-from-time"
                        data-mcc-datetime-submit-format="Y-m-d"
                        placeholder="DD-MM-YYYY"
                        required
                    >
                    <label for="branch-effective-from-time">Effective from time</label>
                    <input
                        class="form-control"
                        id="branch-effective-from-time"
                        type="time"
                        step="1"
                        required
                    >
                </div>
                <input type="hidden" name="expected_updated_at" value="<?= esc($player['updated_at']) ?>">
                <input type="hidden" name="expected_profile_revision" value="<?= (int) $player['profile_revision'] ?>">
                <?= csrf_field() ?>
                <div class="col-12 mcc-player-form-feedback" data-player-form-feedback hidden></div>
                <div class="col-12 mcc-form-actions">
                    <button class="mcc-button mcc-button-secondary" type="submit">
                        <i class="fa-solid fa-code-branch" aria-hidden="true"></i>
                        Transfer branch
                    </button>
                </div>
            </form>
        <?php endif; ?>
            </div>

            <div class="mcc-placement-assignment-grid">
        <?php if ($can_batch ?? false): ?>
            <form
                id="player-batch-form-<?= (int) $player['id'] ?>"
                method="post"
                action="<?= site_url('players/' . $player['id'] . '/batch') ?>"
                class="row mcc-player-form mcc-assignment-form"
                data-player-submit-feedback
            >
                <div class="col-12 mcc-assignment-form-heading">
                    <span class="mcc-eyebrow">Batch assignment</span>
                    <strong>Update batch</strong>
                    <span class="mcc-placement-form-help">Choose the player's active training batch within the branch.</span>
                </div>
                <div class="col-12 mcc-placement-assignment-value">
                    <span>Current batch</span>
                    <strong><?= esc(! empty($currentBatch['batch_name']) ? $currentBatch['batch_name'] : 'Not assigned') ?></strong>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="batch-id">Batch</label>
                    <select class="form-control selectpicker btSelect" id="batch-id" name="batch_id" data-bt-select>
                        <option value="">Remove current batch</option>
                        <?php foreach ($options['batches'] as $batch): ?>
                            <?php
                            $batchSelected = $currentBatch !== null
                                && (int) $currentBatch['batch_id'] === (int) $batch['id'];
                            ?>
                            <option
                                value="<?= (int) $batch['id'] ?>"
                                <?= $batchSelected ? 'selected' : '' ?>
                                <?= ! empty($batch['disabled']) ? 'disabled' : '' ?>
                            >
                                <?= esc($batch['name']) ?><?= ! empty($batch['historical']) ? ' (inactive historical)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="batch-from">Effective from date</label>
                    <input
                        class="form-control"
                        id="batch-from"
                        name="effective_from"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-picker-time-input="batch-from-time"
                        data-mcc-datetime-submit-format="Y-m-d"
                        placeholder="DD-MM-YYYY"
                        required
                    >
                    <label for="batch-from-time">Effective from time</label>
                    <input
                        class="form-control"
                        id="batch-from-time"
                        type="time"
                        step="1"
                        required
                    >
                </div>
                <div class="col-12 col-md-4 mb-2">
                    <label for="batch-to">Effective to date (optional)</label>
                    <input
                        class="form-control"
                        id="batch-to"
                        name="effective_to"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-picker-time-input="batch-to-time"
                        data-mcc-datetime-submit-format="Y-m-d"
                        placeholder="DD-MM-YYYY"
                    >
                    <label for="batch-to-time">Effective to time (required with a date)</label>
                    <input
                        class="form-control"
                        id="batch-to-time"
                        type="time"
                        step="1"
                    >
                </div>
                <input type="hidden" name="expected_updated_at" value="<?= esc($player['updated_at']) ?>">
                <input type="hidden" name="expected_profile_revision" value="<?= (int) $player['profile_revision'] ?>">
                <input type="hidden" name="current_batch_id" value="<?= $currentBatch !== null ? (int) $currentBatch['batch_id'] : '' ?>">
                <?= csrf_field() ?>
                <div class="col-12 mcc-player-form-feedback" data-player-form-feedback hidden></div>
                <div class="col-12 mcc-form-actions">
                    <button class="mcc-button mcc-button-secondary" type="submit">
                        <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                        Update batch
                    </button>
                </div>
            </form>
        <?php endif; ?>

            </div>

        <?php if (($can_batch ?? false) === false && ($can_coach ?? false) === false && ($can_transfer ?? false) === false): ?>
            <div class="mcc-empty-state mcc-detail-empty-state">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <strong>No placement actions available</strong>
                <span>Your current permissions do not include placement changes for this player.</span>
            </div>
        <?php endif; ?>
        </div>

        <?php if ($branchHistory !== [] || $batchHistory !== [] || $coachHistory !== []): ?>
            <div class="mcc-placement-history-panel">
                <div class="mcc-card-heading mcc-placement-history-heading">
                    <div>
                        <span class="mcc-eyebrow">Record history</span>
                        <h2>Placement history</h2>
                    </div>
                </div>
                <div class="mcc-table-wrap mcc-history-table-wrap">
                    <table class="mcc-table mcc-player-placement-table">
                        <caption class="visually-hidden">Placement history</caption>
                        <thead>
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">Assignment</th>
                                <th scope="col">Effective from</th>
                                <th scope="col">Effective to</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branchHistory as $event): ?>
                                <tr>
                                    <th scope="row">Branch</th>
                                    <td data-label="Assignment">
                                        <?= esc($event['branch_name'] ?? 'Authorized branch') ?>
                                    </td>
                                    <td data-label="Effective from">
                                        <?= esc(mcc_user_datetime($event['effective_from'] ?? null)) ?>
                                    </td>
                                    <td data-label="Effective to">
                                        <?= esc($event['effective_to'] ? mcc_user_datetime($event['effective_to']) : 'Current') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($batchHistory as $event): ?>
                                <tr>
                                    <th scope="row">Batch</th>
                                    <td data-label="Assignment">
                                        <?= esc($event['batch_name'] ?? ('Batch #' . (int) $event['batch_id'])) ?>
                                    </td>
                                    <td data-label="Effective from">
                                        <?= esc(mcc_user_datetime($event['effective_from'] ?? null)) ?>
                                    </td>
                                    <td data-label="Effective to">
                                        <?= esc($event['effective_to'] ? mcc_user_datetime($event['effective_to']) : 'Current') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($coachHistory as $event): ?>
                                <tr>
                                    <th scope="row">Coach</th>
                                    <td data-label="Assignment">
                                        <?= esc($event['coach_name'] ?? ('User #' . (int) $event['coach_user_id'])) ?>
                                    </td>
                                    <td data-label="Effective from"><?= esc($event['effective_from']) ?></td>
                                    <td data-label="Effective to"><?= esc($event['effective_to'] ?? 'Current') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="mcc-empty-state mcc-detail-empty-state">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <strong>No placement history recorded</strong>
                <span>Branch, batch, and coach changes will be listed here.</span>
            </div>
        <?php endif; ?>
    </section>

    <?php if (($can_performance ?? false) && is_array($player['performance'] ?? null)): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="performance-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Stored source records</span>
                    <h2 id="performance-heading">Performance</h2>
                </div>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('performance?q=' . (int) $player['id']) ?>">
                    View history
                </a>
            </div>
            <?php $performanceKpis = $player['performance']['kpis']; ?>
            <div class="mcc-kpi-grid">
                <div class="mcc-kpi-card">
                    <span class="mcc-eyebrow">Runs</span>
                    <strong><?= (int) $performanceKpis['runs'] ?></strong>
                </div>
                <div class="mcc-kpi-card">
                    <span class="mcc-eyebrow">Highest score</span>
                    <strong><?= esc((string) ($performanceKpis['highest_score'] ?? 'Not available')) ?></strong>
                </div>
                <div class="mcc-kpi-card">
                    <span class="mcc-eyebrow">Wickets</span>
                    <strong><?= (int) $performanceKpis['wickets'] ?></strong>
                </div>
                <div class="mcc-kpi-card">
                    <span class="mcc-eyebrow">Strike rate</span>
                    <strong><?= esc((string) ($performanceKpis['strike_rate'] ?? 'Not available')) ?></strong>
                </div>
            </div>
            <p class="mcc-muted">
                Calculated only from stored, non-deleted source records. No ranking or improvement score is shown.
            </p>
        </section>
    <?php endif; ?>
    <div class="mcc-detail-grid mcc-player-detail-grid mcc-player-secondary-grid">
        <section class="mcc-card mcc-detail-card" aria-labelledby="attendance-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Attendance</span>
                    <h2 id="attendance-heading">Attendance records</h2>
                    <p class="mcc-card-subtitle">Attendance is recorded against authorized session rosters.</p>
                </div>
            </div>
            <div class="mcc-empty-state mcc-detail-empty-state">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                <strong>Attendance details are not available here</strong>
                <span>Open an authorized attendance session to review or record attendance.</span>
            </div>
        </section>

        <section class="mcc-card mcc-detail-card" id="player-documents-section" aria-labelledby="documents-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Secure records</span>
                    <h2 id="documents-heading">Documents</h2>
                    <p class="mcc-card-subtitle">Private Player documents remain protected by document permissions.</p>
                </div>
            </div>
            <?php if ($can_documents ?? false): ?>
                <div class="mcc-empty-state mcc-detail-empty-state">
                    <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
                    <strong>Open authorized documents</strong>
                    <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('documents?player_id=' . (int) $player['id'])) ?>">
                        View documents
                    </a>
                </div>
            <?php else: ?>
                <div class="mcc-empty-state mcc-detail-empty-state">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <strong>Documents are restricted</strong>
                    <span>Your current permissions do not include private document access.</span>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php if (($can_coach ?? false) && is_array($coach_correction ?? null)): ?>
        <?php
        $coachCorrectionPlayer = is_array($coach_correction['player'] ?? null) ? $coach_correction['player'] : [];
        $coachCorrectionOptions = is_array($coach_correction['coaches'] ?? null) ? $coach_correction['coaches'] : [];
        $groupCoachValue = old('group_coach_id', (string) ($coachCorrectionPlayer['admission_group_coach_id'] ?? ''));
        $personalCoachValue = old('personal_coach_id', (string) ($coachCorrectionPlayer['admission_personal_coach_id'] ?? ''));
        ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="coaching-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Coaching</span>
                    <h2 id="coaching-heading">Coach assignments</h2>
                    <p class="mcc-card-subtitle">Current Group Coach and Personal Coach assignments</p>
                </div>
            </div>
            <form id="player-coaches-form-<?= (int) $player['id'] ?>"
              method="post"
              action="<?= esc(site_url('players/' . (int) $player['id'] . '/coaches')) ?>"
              class="mcc-player-form mcc-player-coach-correction-form"
              data-player-coach-form
              data-player-submit-feedback>
            <?= csrf_field() ?>
            <input type="hidden" name="expected_updated_at" value="<?= esc((string) ($coachCorrectionPlayer['updated_at'] ?? '')) ?>">
            <input type="hidden" name="expected_profile_revision" value="<?= (int) ($coachCorrectionPlayer['profile_revision'] ?? 0) ?>">

            <div class="row mcc-player-coach-correction-fields">
                <div class="mcc-form-field col-12 col-md-6">
                    <label for="player-group-coach-<?= (int) $player['id'] ?>">Group Coach</label>
                    <select class="form-control selectpicker btSelect"
                            id="player-group-coach-<?= (int) $player['id'] ?>"
                            name="group_coach_id"
                            data-bt-select
                            data-bt-select-clear
                            title="Select Group Coach">
                        <option value="" <?= $groupCoachValue === '' ? 'selected' : '' ?>>No Group Coach</option>
                        <?php foreach ($coachCorrectionOptions as $coach): ?>
                            <?php $coachId = (string) ($coach['id'] ?? ''); ?>
                            <option value="<?= esc($coachId) ?>"
                                    <?= $groupCoachValue === $coachId ? 'selected' : '' ?>
                                    <?= ! empty($coach['disabled']) ? 'disabled' : '' ?>>
                                <?= esc((string) ($coach['display_name'] ?? 'Coach')) ?><?= ! empty($coach['historical']) ? ' (inactive historical)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mcc-form-field col-12 col-md-6">
                    <label for="player-personal-coach-<?= (int) $player['id'] ?>">Personal Coach</label>
                    <select class="form-control selectpicker btSelect"
                            id="player-personal-coach-<?= (int) $player['id'] ?>"
                            name="personal_coach_id"
                            data-bt-select
                            data-bt-select-clear
                            title="Select Personal Coach">
                        <option value="" <?= $personalCoachValue === '' ? 'selected' : '' ?>>No Personal Coach</option>
                        <?php foreach ($coachCorrectionOptions as $coach): ?>
                            <?php $coachId = (string) ($coach['id'] ?? ''); ?>
                            <option value="<?= esc($coachId) ?>"
                                    <?= $personalCoachValue === $coachId ? 'selected' : '' ?>
                                    <?= ! empty($coach['disabled']) ? 'disabled' : '' ?>>
                                <?= esc((string) ($coach['display_name'] ?? 'Coach')) ?><?= ! empty($coach['historical']) ? ' (inactive historical)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mcc-form-field col-12">
                    <label for="player-coach-reason-<?= (int) $player['id'] ?>">Correction reason</label>
                    <textarea class="form-control"
                              id="player-coach-reason-<?= (int) $player['id'] ?>"
                              name="correction_reason"
                              maxlength="1000"
                              rows="4"
                              required><?= esc(old('correction_reason', '')) ?></textarea>
                </div>
            </div>

            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Save Coach Assignments</button>
            </div>
            <div class="mcc-player-form-feedback" data-player-form-feedback hidden></div>
            </form>
        </section>
    <?php endif; ?>
</div>

<?= view('staff_footer') ?>
