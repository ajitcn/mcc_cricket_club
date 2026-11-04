<?php

$players = is_array($players ?? null) ? $players : [];
$query = (string) ($query ?? '');
$selectedPlayer = is_array($selected_player ?? null) ? $selected_player : null;

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('fitness') . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Fitness</a>',
]) ?>

<section class="mcc-card" aria-labelledby="fitness-entry-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Fitness assessment</span>
            <h2 id="fitness-entry-heading"><?= $selectedPlayer !== null ? esc($selectedPlayer['full_name']) : 'New assessment' ?></h2>
            <p class="mcc-muted">Search and select a Player, then complete the assessment below.</p>
        </div>
    </div>

    <form id="fitness-assessment-form" method="post" action="<?= $selectedPlayer !== null ? site_url('fitness/players/' . (int) $selectedPlayer['id'] . '/assessments') : '#' ?>" data-fitness-assessment-base="<?= esc(site_url('fitness/players')) ?>" class="row">
        <?= csrf_field() ?>
        <div class="col-12 mb-3">
            <label for="fitness-player-search">Search and select Player</label>
            <input class="form-control" id="fitness-player-search" type="search" autocomplete="off" placeholder="Type at least 2 characters" value="<?= esc($selectedPlayer['full_name'] ?? '') ?>" data-player-search-url="<?= esc(site_url('fitness/player-search')) ?>">
            <input id="fitness-player-id" name="player_id" type="hidden" value="<?= $selectedPlayer !== null ? (int) $selectedPlayer['id'] : '' ?>">
            <div id="fitness-player-results" class="mcc-fitness-player-results" role="listbox" hidden></div>
            <div id="fitness-selected-player-info" class="mcc-fitness-selected-player" <?= $selectedPlayer === null ? 'hidden' : '' ?> role="status">
                <?php if ($selectedPlayer !== null): ?>
                    <strong><?= esc($selectedPlayer['full_name']) ?></strong>
                    <span><?= esc($selectedPlayer['branch_name']) ?> · Player #<?= (int) $selectedPlayer['id'] ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3"><label for="assessment-date">Month</label><input class="form-control" id="assessment-date" name="assessment_date" type="text" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" placeholder="DD-MM-YYYY" required></div>
        <div class="col-12 col-md-4 mb-3"><label for="age-group">Age Group</label><select class="form-control" id="age-group" name="age_group"><option value="">Choose age group</option><?php foreach (($age_groups ?? []) as $ageGroup): ?><option value="<?= esc($ageGroup['name']) ?>"><?= esc($ageGroup['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4 mb-3"><label for="bmi">BMI</label><input class="form-control" id="bmi" name="bmi" type="number" min="0" max="99.99" step="0.01"></div>
        <?php foreach (['squats' => 'Squats', 'broad_jump' => 'Broad Jump', 'shoulder_tap' => 'Shoulder Tap', 'bronco_test' => 'Bronco Test', 'running_between_wickets' => 'Running Between Wickets', 'plank' => 'Plank', 'crunches' => 'Crunches', 'thirty_meter_timing' => '30 Meter Timing'] as $field => $label): ?>
            <div class="col-12 col-md-4 mb-3"><label for="<?= esc($field) ?>"><?= esc($label) ?></label><input class="form-control" id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="number" min="0" step="0.01"></div>
        <?php endforeach; ?>
        <div class="col-12 mb-3"><label for="coach-notes">Notes</label><textarea class="form-control" id="coach-notes" name="coach_notes" rows="3"></textarea></div>
            <div class="col-12 mcc-form-actions"><button id="fitness-assessment-submit" class="mcc-button mcc-button-primary" type="submit" <?= $selectedPlayer === null ? 'disabled' : '' ?>>Save Fitness Assessment</button></div>
    </form>
</section>

<script>
    (() => {
        const search = document.getElementById('fitness-player-search');
        const hidden = document.getElementById('fitness-player-id');
        const results = document.getElementById('fitness-player-results');
        const selectedInfo = document.getElementById('fitness-selected-player-info');
        const form = document.getElementById('fitness-assessment-form');
        const submit = document.getElementById('fitness-assessment-submit');
        if (!search || !hidden || !results || !selectedInfo || !form || !submit) {
            return;
        }
        const updatePlayer = (playerId) => {
            form.action = playerId ? `${form.dataset.fitnessAssessmentBase}/${playerId}/assessments` : '#';
            submit.disabled = !playerId;
        };
        let timer;
        let highlightedIndex = -1;

        const chooseResult = (option) => {
            if (!option) {
                return;
            }
            search.value = option.dataset.playerName;
            hidden.value = option.dataset.playerId;
            updatePlayer(option.dataset.playerId);
            selectedInfo.replaceChildren();
            const name = document.createElement('strong');
            name.textContent = option.dataset.playerName;
            const context = document.createElement('span');
            context.textContent = `${option.dataset.playerBranch} · Player #${option.dataset.playerId}`;
            selectedInfo.append(name, context);
            selectedInfo.hidden = false;
            results.hidden = true;
        };

        search.addEventListener('keydown', (event) => {
            const options = Array.from(results.querySelectorAll('.mcc-fitness-player-result'));
            if (results.hidden || options.length === 0) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlightedIndex = Math.min(highlightedIndex + 1, options.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlightedIndex = Math.max(highlightedIndex - 1, 0);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                chooseResult(options[highlightedIndex] || options[0]);
                return;
            } else if (event.key === 'Escape') {
                results.hidden = true;
                return;
            } else {
                return;
            }
            options.forEach((option, index) => option.classList.toggle('is-highlighted', index === highlightedIndex));
        });

        search.addEventListener('input', () => {
            hidden.value = '';
            updatePlayer('');
            selectedInfo.hidden = true;
            highlightedIndex = -1;
            window.clearTimeout(timer);
            const query = search.value.trim();
            if (query.length < 2) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            timer = window.setTimeout(async () => {
                const response = await fetch(`${search.dataset.playerSearchUrl}?q=${encodeURIComponent(query)}`, {headers: {Accept: 'application/json'}});
                const payload = await response.json();
                results.innerHTML = '';
                (payload.players || []).forEach((player) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'mcc-fitness-player-result';
                    option.dataset.playerId = player.id;
                    option.dataset.playerName = player.full_name;
                    option.dataset.playerBranch = player.branch_name;
                    option.textContent = `${player.full_name} · ${player.branch_name} · Player #${player.id}`;
                    option.addEventListener('mouseenter', () => {
                        highlightedIndex = Array.from(results.children).indexOf(option);
                        results.querySelectorAll('.mcc-fitness-player-result').forEach((item) => item.classList.remove('is-highlighted'));
                        option.classList.add('is-highlighted');
                    });
                    option.addEventListener('click', () => chooseResult(option));
                    results.appendChild(option);
                });
                results.hidden = (payload.players || []).length === 0;
            }, 250);
        });
    })();
</script>

<?= view('staff_footer') ?>
