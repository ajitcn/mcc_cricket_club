<?php

$editing = is_array($plan ?? null);
$options = is_array($options ?? null)
    ? $options
    : [
        'branches' => [],
        'players' => [],
        'coaches' => [],
        'programs' => [],
        'batches' => [],
    ];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static function (string $field) use ($fieldErrors): string {
    return trim((string) ($fieldErrors[$field] ?? ''));
};
$selectedBranch = (string) old('branch_id', $plan['branch_id'] ?? '');
$selectedPlayer = (string) old('player_id', $plan['player_id'] ?? '');
$selectedCoach = (string) old('coach_user_id', $plan['coach_user_id'] ?? '');
$selectedStatus = (string) old('status', $plan['status'] ?? 'active');
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="'
    . site_url($editing ? 'development/' . (int) $plan['id'] : 'development')
    . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Back to development</span>'
    . '</a></div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<section class="mcc-card mcc-development-form-card" aria-labelledby="development-form-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Player development</span>
            <h2 id="development-form-heading">
                <?= $editing ? 'Edit development plan' : 'Create development plan' ?>
            </h2>
            <p class="mcc-card-subtitle">
                Choose the authorized development context and plan details.
            </p>
        </div>
    </div>

    <?php if (! empty($error)): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert">
            <?= esc($error) ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?= site_url($editing ? 'development/' . (int) $plan['id'] : 'development') ?>"
        class="mcc-form-grid mcc-development-form"
        data-development-submit-form
    >
        <?= csrf_field() ?>

        <?php if ($editing): ?>
            <input type="hidden" name="branch_id" value="<?= esc($selectedBranch) ?>">
        <?php endif; ?>

        <div class="mcc-form-field mcc-form-field-wide">
            <label for="development-title">Plan title</label>
            <input
                class="form-control"
                id="development-title"
                name="title"
                type="text"
                maxlength="150"
                value="<?= esc(old('title', $plan['title'] ?? '')) ?>"
                aria-describedby="development-title-error"
                required
            >
            <?php if ($fieldError('title') !== ''): ?>
                <p class="mcc-field-error" id="development-title-error" role="alert">
                    <?= esc($fieldError('title')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-branch">Branch</label>
            <select
                class="form-control"
                id="development-branch"
                name="branch_id"
                data-development-branch
                <?= $editing ? 'disabled' : '' ?>
                aria-describedby="development-branch-error"
                required
            >
                <option value="">Select a branch</option>
                <?php foreach ($options['branches'] as $branch): ?>
                    <option
                        value="<?= (int) $branch['id'] ?>"
                        <?= (string) $branch['id'] === $selectedBranch ? 'selected' : '' ?>
                    >
                        <?= esc($branch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('branch_id') !== ''): ?>
                <p class="mcc-field-error" id="development-branch-error" role="alert">
                    <?= esc($fieldError('branch_id')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field mcc-development-player-search" data-development-player-search data-search-url="<?= esc(site_url('development/player-search')) ?>">
            <label for="development-player-search">Player</label>
            <input class="form-control" id="development-player-search" type="search" autocomplete="off" placeholder="Search Player by name or number" value="<?= $selectedPlayer !== '' ? esc((string) ($plan['player_name'] ?? '')) : '' ?>" required>
            <input id="development-player" name="player_id" type="hidden" value="<?= esc($selectedPlayer) ?>">
            <div class="mcc-live-search-results" data-development-player-results role="listbox" hidden></div>
            <p class="mcc-form-help" data-development-player-selected <?= $selectedPlayer === '' ? 'hidden' : '' ?>>Selected Player #<?= esc($selectedPlayer) ?></p>
            <?php if ($fieldError('player_id') !== ''): ?>
                <p class="mcc-field-error" id="development-player-error" role="alert">
                    <?= esc($fieldError('player_id')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-coach">Coach</label>
            <select
                class="form-control selectpicker btSelect"
                id="development-coach"
                name="coach_user_id"
                data-development-coach
                data-bt-select
                data-live-search="true"
                data-actions-box="true"
                title="Select a Coach"
                aria-describedby="development-coach-error"
                required
            >
                <option value="" hidden selected>Select an eligible Coach</option>
                <?php if ($options['coaches'] === []): ?>
                    <option value="" disabled>No eligible Coaches are available.</option>
                <?php endif; ?>
                <?php foreach ($options['coaches'] as $coach): ?>
                    <option value="<?= (int) $coach['id'] ?>" data-branch-id="<?= (int) $coach['branch_id'] ?>" <?= (string) $coach['id'] === $selectedCoach ? 'selected' : '' ?>><?= esc($coach['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('coach_user_id') !== ''): ?>
                <p class="mcc-field-error" id="development-coach-error" role="alert">
                    <?= esc($fieldError('coach_user_id')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-status">Status</label>
            <select
                class="form-control"
                id="development-status"
                name="status"
                aria-describedby="development-status-error"
            >
                <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('status') !== ''): ?>
                <p class="mcc-field-error" id="development-status-error" role="alert">
                    <?= esc($fieldError('status')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-target-date">Target date</label>
            <input
                class="form-control"
                id="development-target-date"
                name="target_date"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                placeholder="DD-MM-YYYY"
                data-mcc-date-picker
                data-mcc-date-picker-no-toggle
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input(old('target_date', $plan['target_date'] ?? null))) ?>"
                aria-describedby="development-target-date-error"
            >
            <?php if ($fieldError('target_date') !== ''): ?>
                <p class="mcc-field-error" id="development-target-date-error" role="alert">
                    <?= esc($fieldError('target_date')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field mcc-form-field-wide">
            <label for="development-notes">Plan details</label>
            <textarea
                class="form-control"
                id="development-notes"
                name="notes"
                maxlength="10000"
                aria-describedby="development-notes-error"
            ><?= esc(old('notes', $plan['notes'] ?? '')) ?></textarea>
            <?php if ($fieldError('notes') !== ''): ?>
                <p class="mcc-field-error" id="development-notes-error" role="alert">
                    <?= esc($fieldError('notes')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-actions mcc-development-actions">
            <div class="mcc-alert mcc-development-form-feedback" data-development-response hidden role="status"></div>
            <button class="mcc-button mcc-button-primary" type="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span class="mcc-button-label"><?= $editing ? 'Save changes' : 'Save plan' ?></span>
            </button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url($editing ? 'development/' . (int) $plan['id'] : 'development') ?>">
                Cancel
            </a>
        </div>
    </form>
</section>

<script>
    (() => {
        const container = document.querySelector('[data-development-player-search]');
        if (!container) {
            return;
        }
        const input = container.querySelector('#development-player-search');
        const hidden = container.querySelector('#development-player');
        const results = container.querySelector('[data-development-player-results]');
        const selected = container.querySelector('[data-development-player-selected]');
        const branch = document.querySelector('[data-development-branch]');
        let timer;
        let highlightedIndex = -1;

        const highlight = (index) => {
            const options = Array.from(results.querySelectorAll('.mcc-live-search-result'));
            highlightedIndex = options.length === 0 ? -1 : Math.max(0, Math.min(index, options.length - 1));
            options.forEach((option, optionIndex) => {
                option.classList.toggle('is-highlighted', optionIndex === highlightedIndex);
                option.setAttribute('aria-selected', optionIndex === highlightedIndex ? 'true' : 'false');
            });
            if (options[highlightedIndex]) {
                options[highlightedIndex].scrollIntoView({block: 'nearest'});
            }
        };

        input.addEventListener('input', () => {
            hidden.value = '';
            selected.hidden = true;
            highlightedIndex = -1;
            window.clearTimeout(timer);
            const query = input.value.trim();
            if (query.length < 2) {
                results.replaceChildren();
                results.hidden = true;
                return;
            }
            timer = window.setTimeout(async () => {
                const params = new URLSearchParams({q: query});
                if (branch && branch.value) {
                    params.set('branch_id', branch.value);
                }
                const response = await fetch(container.dataset.searchUrl + '?' + params.toString(), {headers: {Accept: 'application/json'}});
                const payload = await response.json();
                results.replaceChildren();
                (payload.players || []).forEach((player) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'mcc-live-search-result';
                    option.textContent = player.full_name + ' · ' + player.branch_name + ' · Player #' + player.id;
                    option.addEventListener('click', () => {
                        input.value = player.full_name;
                        hidden.value = player.id;
                        selected.textContent = 'Selected Player #' + player.id + ' · ' + player.branch_name;
                        selected.hidden = false;
                        results.hidden = true;
                    });
                    results.appendChild(option);
                });
                highlight(-1);
                results.hidden = results.children.length === 0;
            }, 250);
        });

        input.addEventListener('keydown', (event) => {
            const options = results.querySelectorAll('.mcc-live-search-result');
            if (results.hidden || options.length === 0) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(highlightedIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(highlightedIndex - 1);
            } else if (event.key === 'Enter' && highlightedIndex >= 0) {
                event.preventDefault();
                options[highlightedIndex].click();
            } else if (event.key === 'Escape') {
                results.hidden = true;
            }
        });
    })();
</script>

<?= view('staff_footer') ?>
