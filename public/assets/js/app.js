(function () {
    'use strict';

    function initSidebar() {
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var collapse = document.querySelector('[data-sidebar-collapse]');
        var close = document.querySelector('[data-sidebar-close]');
        var storageKey = 'mcc.sidebar.collapsed';
        var isMobile = function () {
            return window.matchMedia('(max-width: 991px)').matches;
        };
        var setOpen = function (open) {
            document.body.classList.toggle('sidebar-open', open);

            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        };
        var setCollapsed = function (collapsed, persist) {
            document.body.classList.toggle('sidebar-collapsed', collapsed);

            if (!collapse) {
                return;
            }

            collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            collapse.setAttribute(
                'aria-label',
                collapsed ? 'Expand navigation' : 'Collapse navigation'
            );
            collapse.setAttribute(
                'title',
                collapsed ? 'Expand navigation' : 'Collapse navigation'
            );

            var label = collapse.querySelector('.mcc-sidebar-control-label');

            if (label) {
                label.textContent = collapsed ? 'Expand' : 'Collapse';
            }

            var arrow = collapse.querySelector('.mcc-sidebar-collapse-icon');

            if (arrow) {
                if (collapse.classList.contains('mcc-topbar-collapse')) {
                    arrow.classList.remove('fa-chevron-right', 'fa-chevron-left');
                    arrow.classList.add('fa-bars');
                } else {
                    arrow.classList.toggle('fa-chevron-right', collapsed);
                    arrow.classList.toggle('fa-chevron-left', !collapsed);
                }
            }

            if (persist) {
                try {
                    window.localStorage.setItem(
                        storageKey,
                        collapsed ? '1' : '0'
                    );
                } catch (ignore) {
                }
            }
        };

        if (collapse) {
            var saved = null;

            try {
                saved = window.localStorage.getItem(storageKey);
            } catch (ignore) {
            }

            setCollapsed(saved === '1', false);
            collapse.addEventListener('click', function () {
                if (!isMobile()) {
                    setCollapsed(
                        !document.body.classList.contains('sidebar-collapsed'),
                        true
                    );
                }
            });
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                setOpen(!document.body.classList.contains('sidebar-open'));
            });
        }

        if (close) {
            close.addEventListener('click', function () {
                setOpen(false);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        document.querySelectorAll('[data-nav-group-toggle]').forEach(function (button) {
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            var group = button.closest('[data-nav-group]');

            if (!panel || !group) {
                return;
            }

            var key = 'mcc.nav.' + button.getAttribute('aria-controls');
            var setIndicator = function (open) {
                var indicator = button.querySelector('.mcc-nav-group-chevron');

                if (!indicator) {
                    return;
                }

                indicator.classList.toggle('fa-chevron-down', open);
                indicator.classList.toggle('fa-chevron-right', !open);
            };
            var active = group.getAttribute('data-default-open') === 'true';
            var saved = null;

            try {
                saved = window.localStorage.getItem(key);
            } catch (ignore) {
            }

            var open = active || saved === '1';

            panel.hidden = !open;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            setIndicator(open);
            button.addEventListener('click', function () {
                var next = panel.hidden;

                if (next) {
                    document.querySelectorAll('[data-nav-group-toggle]').forEach(function (otherButton) {
                        if (otherButton === button) {
                            return;
                        }

                        var otherPanel = document.getElementById(otherButton.getAttribute('aria-controls'));
                        var otherGroup = otherButton.closest('[data-nav-group]');
                        var otherIndicator = otherButton.querySelector('.mcc-nav-group-chevron');

                        if (!otherPanel || !otherGroup) {
                            return;
                        }

                        otherPanel.hidden = true;
                        otherButton.setAttribute('aria-expanded', 'false');
                        otherGroup.setAttribute('data-default-open', 'false');

                        if (otherIndicator) {
                            otherIndicator.classList.remove('fa-chevron-down');
                            otherIndicator.classList.add('fa-chevron-right');
                        }

                        try {
                            window.localStorage.setItem(
                                'mcc.nav.' + otherButton.getAttribute('aria-controls'),
                                '0',
                            );
                        } catch (ignore) {
                        }
                    });
                }

                panel.hidden = !next;
                button.setAttribute('aria-expanded', next ? 'true' : 'false');
                setIndicator(next);

                try {
                    window.localStorage.setItem(key, next ? '1' : '0');
                } catch (ignore) {
                }
            });
        });
    }

    function initPortalNavigation() {
        var toggle = document.querySelector('[data-portal-menu-toggle]');
        var sidebar = document.getElementById('portal-sidebar');
        var backdrop = document.querySelector('.mcc-portal-backdrop');
        var closeButtons = document.querySelectorAll('[data-portal-menu-close]');
        var navigationLinks = document.querySelectorAll('.mcc-portal-nav a');

        if (! toggle || ! sidebar || document.documentElement.dataset.portalNavigationInitialized === 'true') {
            return;
        }

        document.documentElement.dataset.portalNavigationInitialized = 'true';

        var isMobile = function () {
            return window.matchMedia('(max-width: 991px)').matches;
        };

        var syncAccessibility = function (open) {
            var mobile = isMobile();
            sidebar.setAttribute('aria-hidden', mobile && ! open ? 'true' : 'false');

            if (backdrop) {
                backdrop.setAttribute('aria-hidden', mobile && open ? 'false' : 'true');
            }
        };

        var setOpen = function (open) {
            if (! isMobile()) {
                open = false;
            }

            document.body.classList.toggle('mcc-portal-nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute(
                'aria-label',
                open ? 'Close portal navigation' : 'Open portal navigation'
            );
            syncAccessibility(open);
        };

        syncAccessibility(false);

        toggle.addEventListener('click', function () {
            setOpen(! document.body.classList.contains('mcc-portal-nav-open'));
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setOpen(false);
            });
        });

        navigationLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        window.addEventListener('resize', function () {
            setOpen(false);
        });
    }

    function initLeadDatePopovers() {
        var buttons = document.querySelectorAll('[data-lead-date-toggle]');
        var closeAll = function (except) {
            buttons.forEach(function (button) {
                var holder = button.parentElement;

                if (holder !== except) {
                    holder.classList.remove('is-open');
                    button.setAttribute('aria-expanded', 'false');
                    button.nextElementSibling.setAttribute('aria-hidden', 'true');
                }
            });
        };

        buttons.forEach(function (button) {
            var holder = button.parentElement;
            var panel = button.nextElementSibling;
            var position = function () {
                var rect = button.getBoundingClientRect();
                var width = Math.min(260, window.innerWidth - 16);

                panel.style.display = 'grid';
                panel.style.minWidth = width + 'px';

                var panelRect = panel.getBoundingClientRect();
                var left = Math.min(
                    Math.max(8, rect.right - panelRect.width),
                    Math.max(8, window.innerWidth - panelRect.width - 8)
                );
                var top = rect.top - panelRect.height - 8;

                if (top < 8) {
                    top = Math.min(
                        rect.bottom + 8,
                        window.innerHeight - panelRect.height - 8
                    );
                }

                panel.style.left = Math.max(8, left) + 'px';
                panel.style.top = Math.max(8, top) + 'px';

                if (!holder.classList.contains('is-open')) {
                    panel.style.display = '';
                }
            };

            button.addEventListener('click', function () {
                var open = !holder.classList.contains('is-open');

                closeAll(open ? holder : null);
                holder.classList.toggle('is-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');

                if (open) {
                    position();
                }
            });
            button.addEventListener('mouseenter', position);
            button.addEventListener('focus', position);
        });

        document.addEventListener('click', function (event) {
            if (
                !event.target.closest('[data-lead-date-toggle]')
                && !event.target.closest('.mcc-lead-info-panel')
            ) {
                closeAll(null);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAll(null);
            }
        });
        window.addEventListener('resize', function () {
            buttons.forEach(function (button) {
                if (button.parentElement.classList.contains('is-open')) {
                    positionButton(button);
                }
            });
        });

        function positionButton(button) {
            button.dispatchEvent(new Event('mouseenter'));
        }
    }

    function initFilters() {
        document.querySelectorAll('[data-filter-toggle]').forEach(function (button) {
            var panel = document.getElementById(button.getAttribute('aria-controls'));

            if (!panel) {
                return;
            }

            panel.hidden = button.getAttribute('aria-expanded') !== 'true';
            button.addEventListener('click', function () {
                panel.hidden = !panel.hidden;
                button.setAttribute(
                    'aria-expanded',
                    panel.hidden ? 'false' : 'true'
                );
                var label = button.querySelector('.mcc-button-label');
                if (label && button.dataset.showLabel && button.dataset.hideLabel) {
                    label.textContent = panel.hidden
                        ? button.dataset.showLabel
                        : button.dataset.hideLabel;
                }
            });
        });
    }

    function initSessionPlanActionMenus() {
        var menus = document.querySelectorAll('[data-session-plan-action-menu], .mcc-detail-action-menu');
        var openMenu = null;
        var openSummary = null;
        var openDropdown = null;
        var openOverlay = null;
        var placeholder = null;

        if (!menus.length) {
            return;
        }

        function closeMenu() {
            if (!openMenu || !openSummary) {
                return;
            }

            if (openDropdown && placeholder) {
                openDropdown.classList.remove('mcc-detail-action-dropdown-portal');
                openDropdown.hidden = true;
                openDropdown.removeAttribute('style');
                placeholder.replaceWith(openDropdown);
            }

            if (openOverlay) {
                openOverlay.remove();
            }

            openMenu.removeAttribute('open');
            openMenu.classList.remove('is-open');
            var toggle = openMenu.querySelector('[data-player-actions-toggle]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            openMenu = null;
            openSummary = null;
            openDropdown = null;
            openOverlay = null;
            placeholder = null;
        }

        function positionMenu() {
            if (!openMenu || !openSummary) {
                return;
            }

            if (!openDropdown) {
                return;
            }

            var summaryRect = openSummary.getBoundingClientRect();
            var menuRect = openDropdown.getBoundingClientRect();
            var viewportPadding = 8;
            var gap = 6;
            var viewportWidth = window.innerWidth;
            var viewportHeight = window.innerHeight;
            var spaceAbove = Math.max(
                0,
                summaryRect.top - viewportPadding - gap
            );
            var spaceBelow = Math.max(
                0,
                viewportHeight - summaryRect.bottom - viewportPadding - gap
            );
            var showBelow = spaceBelow >= menuRect.height
                || spaceBelow >= spaceAbove;

            if (menuRect.height > Math.max(spaceAbove, spaceBelow)) {
                var availableHeight = Math.max(spaceAbove, spaceBelow);
                openDropdown.style.maxHeight = Math.max(0, availableHeight) + 'px';
                openDropdown.style.overflowY = 'auto';
                menuRect = openDropdown.getBoundingClientRect();
            }

            var preferredLeft = summaryRect.right - menuRect.width;
            if (preferredLeft < viewportPadding) {
                preferredLeft = summaryRect.left;
            }

            var maxLeft = Math.max(
                viewportPadding,
                viewportWidth - menuRect.width - viewportPadding
            );
            var left = Math.min(
                Math.max(viewportPadding, preferredLeft),
                maxLeft
            );
            var top = showBelow
                ? summaryRect.bottom + gap
                : summaryRect.top - menuRect.height - gap;
            var maxTop = Math.max(
                viewportPadding,
                viewportHeight - menuRect.height - viewportPadding
            );

            openDropdown.style.left = Math.min(
                Math.max(viewportPadding, left),
                Math.max(viewportPadding, viewportWidth - menuRect.width - viewportPadding)
            ) + 'px';
            openDropdown.style.top = Math.min(
                Math.max(viewportPadding, top),
                maxTop
            ) + 'px';
        }

        menus.forEach(function (menu) {
            var summary = menu.querySelector('summary, [data-player-actions-toggle]');

            if (!summary) {
                return;
            }

            summary.addEventListener('click', function (event) {
                event.preventDefault();

                var wasOpen = openMenu === menu;
                closeMenu();
                if (wasOpen) {
                    return;
                }

                var dropdown = menu.querySelector(
                    '.mcc-detail-action-dropdown, [data-player-actions-menu]'
                );
                if (!dropdown) {
                    return;
                }

                placeholder = document.createElement('span');
                placeholder.setAttribute('data-action-menu-placeholder', 'true');
                dropdown.parentNode.insertBefore(placeholder, dropdown);

                var overlay = document.createElement('div');
                overlay.className = 'mcc-action-menu-overlay';
                overlay.setAttribute('data-action-menu-overlay', 'true');
                document.body.appendChild(overlay);
                document.body.appendChild(dropdown);
                dropdown.classList.add('mcc-detail-action-dropdown-portal');
                dropdown.hidden = false;
                dropdown.style.position = 'fixed';
                dropdown.style.left = '0px';
                dropdown.style.top = '0px';
                dropdown.style.right = 'auto';
                dropdown.style.width = 'min(18rem, calc(100vw - 16px))';
                dropdown.style.maxWidth = 'min(18rem, calc(100vw - 1rem))';
                dropdown.style.maxHeight = '';
                dropdown.style.overflowY = '';
                dropdown.style.zIndex = '999999';
                openMenu = menu;
                openSummary = summary;
                openDropdown = dropdown;
                openOverlay = overlay;
                menu.setAttribute('open', '');
                menu.classList.add('is-open');
                summary.setAttribute('aria-expanded', 'true');
                positionMenu();
            });
        });

        document.addEventListener('click', function (event) {
            if (!(event.target instanceof Element)) {
                return;
            }

            if (openMenu && !openMenu.contains(event.target)
                && !event.target.closest('.mcc-detail-action-dropdown')) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('resize', positionMenu);
        document.addEventListener('scroll', positionMenu, true);

        if (window.MutationObserver) {
            var observer = new MutationObserver(function () {
                if (!openMenu || !openSummary) {
                    return;
                }

                if (!document.documentElement.contains(openSummary)
                    || !document.documentElement.contains(openMenu)) {
                    closeMenu();
                }
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        }
    }

    function initFeedbackPlayerSearch() {
        document.querySelectorAll('[data-feedback-player-search]').forEach(function (container) {
            if (container.dataset.feedbackPlayerSearchInitialized === 'true') {
                return;
            }

            var input = container.querySelector('[data-feedback-player-search-input]');
            var results = container.querySelector('[data-feedback-player-search-results]');
            var empty = container.querySelector('[data-feedback-player-search-empty]');
            var hint = container.querySelector('[data-feedback-player-search-hint]');
            var selectedId = container.querySelector('[data-feedback-player-search-id]');
            var selectedDisplay = container.querySelector('[data-feedback-player-search-selected]');
            var clearButton = container.querySelector('[data-feedback-player-search-clear]');
            var isAnnualSearch = container.dataset.feedbackPlayerSearchMode === 'annual';
            var isGoalSearch = container.dataset.feedbackPlayerSearchMode === 'goal';
            var isSessionPlanSearch = container.dataset.feedbackPlayerSearchMode === 'session-plan';
            var goalPage = isGoalSearch ? container.closest('.mcc-player-goal-create-page') : null;
            var goalForm = goalPage ? goalPage.querySelector('[data-goal-create-form]') : null;
            var goalPlayerId = goalPage ? goalPage.querySelector('[data-goal-player-id]') : null;
            var goalSelected = goalPage ? goalPage.querySelector('[data-goal-player-selected]') : null;
            var goalSelectedName = goalPage ? goalPage.querySelector('[data-goal-player-selected-name]') : null;
            var goalSelectedMeta = goalPage ? goalPage.querySelector('[data-goal-player-selected-meta]') : null;
            var goalFields = goalPage ? goalPage.querySelector('[data-goal-create-form]') : null;
            if (isGoalSearch && !clearButton && goalPage) {
                clearButton = goalPage.querySelector('[data-feedback-player-search-clear]');
            }
            var timer = null;
            var request = null;
            var activeIndex = -1;
            var resultIdPrefix = 'feedback-player-search-' + Math.random().toString(36).slice(2);

            if (!input || !results || !empty || !hint) {
                return;
            }

            function clearResults() {
                results.replaceChildren();
                results.hidden = true;
                empty.hidden = true;
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
            }

            function selectSearchPlayer(player) {
                if (selectedId) {
                    selectedId.value = player.id;
                }
                if (isGoalSearch && goalPlayerId) {
                    goalPlayerId.value = player.id;
                }
                input.value = player.player_name || 'Unnamed player';
                if (isGoalSearch && goalSelected) {
                    goalSelected.hidden = false;
                    if (goalSelectedName) {
                        goalSelectedName.textContent = player.player_name || 'Unnamed player';
                    }
                    if (goalSelectedMeta) {
                        goalSelectedMeta.textContent = 'Player ID ' + player.id
                            + ' · ' + (player.branch_name || 'Authorized branch');
                    }
                }
                if (isAnnualSearch && selectedDisplay) {
                    selectedDisplay.textContent = 'Selected: '
                        + (player.player_name || 'Unnamed player')
                        + ' · Player ID ' + player.id
                        + ' · ' + (player.branch_name || 'Authorized branch');
                    selectedDisplay.hidden = false;
                }
                if (isGoalSearch && goalFields) {
                    goalFields.hidden = false;
                }
                if (clearButton) {
                    clearButton.hidden = false;
                }
                clearResults();
                hint.textContent = isGoalSearch
                    ? 'Player selected. Complete the goal details.'
                    : 'Player selected. Choose a year and view the report.';
                hint.hidden = false;
            }

            function updateActiveSearchResult() {
                var options = results.querySelectorAll('[role="option"]');

                options.forEach(function (option, index) {
                    var isActive = index === activeIndex;
                    option.classList.toggle('is-active', isActive);
                    option.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                if (options[activeIndex]) {
                    input.setAttribute('aria-activedescendant', options[activeIndex].id);
                    options[activeIndex].scrollIntoView({ block: 'nearest' });
                }
            }

            function renderResults(items) {
                results.replaceChildren();
                empty.hidden = items.length !== 0;
                results.hidden = items.length === 0;
                activeIndex = items.length > 0 ? 0 : -1;

                items.forEach(function (player, index) {
                    var link = document.createElement('a');
                    var name = document.createElement('strong');
                    var meta = document.createElement('span');

                    link.className = 'mcc-feedback-player-search-result';
                    link.id = resultIdPrefix + '-' + index;
                    link.href = isAnnualSearch || isGoalSearch
                        ? '#'
                        : isSessionPlanSearch
                            ? container.dataset.createBase + '/' + encodeURIComponent(player.id) + '/session-plans/new'
                            : container.dataset.createBase + '/' + encodeURIComponent(player.id) + '/feedback/new';
                    link.setAttribute('role', 'option');
                    link.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
                    name.textContent = player.player_name || 'Unnamed player';
                    meta.textContent = 'Player ID ' + player.id + ' · ' + (player.branch_name || 'Authorized branch');
                    link.appendChild(name);
                    link.appendChild(meta);
                    if (isAnnualSearch || isGoalSearch) {
                        link.addEventListener('click', function (event) {
                            event.preventDefault();
                            activeIndex = index;
                            updateActiveSearchResult();
                            if (selectedId) {
                                selectedId.value = player.id;
                            }
                            if (isGoalSearch && goalPlayerId) {
                                goalPlayerId.value = player.id;
                            }
                            input.value = player.player_name || 'Unnamed player';
                            if (isGoalSearch && goalSelected) {
                                goalSelected.hidden = false;
                                if (goalSelectedName) {
                                    goalSelectedName.textContent = player.player_name || 'Unnamed player';
                                }
                                if (goalSelectedMeta) {
                                    goalSelectedMeta.textContent = 'Player ID ' + player.id
                                        + ' · ' + (player.branch_name || 'Authorized branch');
                                }
                            }
                            if (isAnnualSearch && selectedDisplay) {
                                selectedDisplay.textContent = 'Selected: '
                                    + (player.player_name || 'Unnamed player')
                                    + ' · Player ID ' + player.id
                                    + ' · ' + (player.branch_name || 'Authorized branch');
                                selectedDisplay.hidden = false;
                            }
                            if (isGoalSearch && goalFields) {
                                goalFields.hidden = false;
                            }
                            if (clearButton) {
                                clearButton.hidden = false;
                            }
                            results.hidden = true;
                            empty.hidden = true;
                            hint.textContent = 'Player selected. Choose a year and view the report.';
                            hint.hidden = false;
                        });
                    }
                    link.addEventListener('mouseenter', function () {
                        activeIndex = index;
                        updateActiveSearchResult();
                    });
                    results.appendChild(link);
                });

                updateActiveSearchResult();
            }

            input.addEventListener('input', function () {
                var term = input.value.trim();
                if ((isAnnualSearch || isGoalSearch) && selectedId) {
                    selectedId.value = '';
                }
                if ((isAnnualSearch || isGoalSearch) && goalPlayerId) {
                    goalPlayerId.value = '';
                }
                if (isAnnualSearch && selectedDisplay) {
                    selectedDisplay.hidden = true;
                }
                if (isGoalSearch && goalSelected) {
                    goalSelected.hidden = true;
                }
                if (isGoalSearch && goalFields) {
                    goalFields.hidden = true;
                }
                if (isAnnualSearch && clearButton) {
                    clearButton.hidden = true;
                }
                clearResults();
                hint.hidden = term !== '';

                if (request) {
                    request.abort();
                    request = null;
                }

                if (timer) {
                    window.clearTimeout(timer);
                }

                if (term === '') {
                    return;
                }

                timer = window.setTimeout(function () {
                    hint.textContent = 'Searching Players…';
                    hint.hidden = false;
                    var url = container.dataset.searchUrl + '?q=' + encodeURIComponent(term);
                    request = new AbortController();

                    fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        signal: request.signal,
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Player search failed.');
                            }

                            return response.json();
                        })
                        .then(function (payload) {
                            hint.textContent = 'Select one Player to continue.';
                            renderResults(Array.isArray(payload.results) ? payload.results : []);
                        })
                        .catch(function (error) {
                            if (error.name !== 'AbortError') {
                                hint.textContent = 'Player search failed. Try again.';
                                renderResults([]);
                            }
                        });
                }, 250);
            });

            input.addEventListener('keydown', function (event) {
                var options = results.querySelectorAll('[role="option"]');

                if (event.key === 'ArrowDown' && options.length > 0) {
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % options.length;
                    updateActiveSearchResult();
                    return;
                }

                if (event.key === 'ArrowUp' && options.length > 0) {
                    event.preventDefault();
                    activeIndex = (activeIndex - 1 + options.length) % options.length;
                    updateActiveSearchResult();
                    return;
                }

                if (event.key === 'Enter' && options.length > 0 && activeIndex >= 0) {
                    event.preventDefault();
                    options[activeIndex].click();
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    clearResults();
                }
            });

            if ((isAnnualSearch || isGoalSearch) && clearButton) {
                clearButton.addEventListener('click', function () {
                    input.value = '';
                    if (selectedId) {
                        selectedId.value = '';
                    }
                    if (selectedDisplay) {
                        selectedDisplay.hidden = true;
                        selectedDisplay.textContent = '';
                    }
                    if (isGoalSearch && goalPlayerId) {
                        goalPlayerId.value = '';
                    }
                    if (isGoalSearch && goalSelected) {
                        goalSelected.hidden = true;
                    }
                    if (isGoalSearch && goalFields) {
                        goalFields.hidden = true;
                    }
                    clearButton.hidden = true;
                    clearResults();
                    hint.textContent = 'Type a Player name or ID to search.';
                    hint.hidden = false;
                    input.focus();
                });
            }

            if (isAnnualSearch) {
                var form = container.closest('form');
                var playerError = container.querySelector('[data-feedback-player-search-error]');
                var yearError = form
                    ? form.querySelector('[data-annual-feedback-year-error]')
                    : null;
                var submit = form ? form.querySelector('button[type="submit"]') : null;

                if (form) {
                    form.addEventListener('submit', function (event) {
                        var year = form.querySelector('[name="year"]');
                        var valid = true;

                        if (playerError) {
                            playerError.hidden = true;
                            playerError.textContent = '';
                        }
                        if (yearError) {
                            yearError.hidden = true;
                            yearError.textContent = '';
                        }
                        if (!selectedId || !selectedId.value) {
                            valid = false;
                            if (playerError) {
                                playerError.textContent = 'Select one authorized Player.';
                                playerError.hidden = false;
                            }
                        }
                        if (!year || !year.value) {
                            valid = false;
                            if (yearError) {
                                yearError.textContent = 'Select a calendar year.';
                                yearError.hidden = false;
                            }
                        }
                        if (!valid) {
                            event.preventDefault();
                            return;
                        }
                        if (submit) {
                            submit.disabled = true;
                            submit.setAttribute('aria-busy', 'true');
                            submit.textContent = 'Generating Report…';
                        }
                    });
                }
            }

            if (isGoalSearch && goalForm) {
                goalForm.addEventListener('submit', function (event) {
                    if (!goalPlayerId || !goalPlayerId.value) {
                        event.preventDefault();
                        hint.textContent = 'Select one authorized Player before saving.';
                        hint.hidden = false;
                        input.focus();
                    }
                });
            }

            container.dataset.feedbackPlayerSearchInitialized = 'true';
        });
    }

    function initGoalPlayerSearch() {
        document.querySelectorAll('[data-goal-player-search]').forEach(function (container) {
            if (container.dataset.goalPlayerSearchInitialized === 'true') {
                return;
            }

            var input = container.querySelector('[data-goal-player-search-input]');
            var results = container.querySelector('[data-goal-player-search-results]');
            var empty = container.querySelector('[data-goal-player-search-empty]');
            var hint = container.querySelector('[data-goal-player-search-hint]');
            var page = container.closest('.mcc-player-goal-create-page');
            var form = page ? page.querySelector('[data-goal-create-form]') : null;
            var playerId = page ? page.querySelector('[data-goal-player-id]') : null;
            var selected = page ? page.querySelector('[data-goal-player-selected]') : null;
            var selectedName = page ? page.querySelector('[data-goal-player-selected-name]') : null;
            var selectedMeta = page ? page.querySelector('[data-goal-player-selected-meta]') : null;
            var clearButton = page ? page.querySelector('[data-goal-player-clear]') : null;
            var timer = null;
            var request = null;

            if (!input || !results || !empty || !hint || !form || !playerId || !selected) {
                return;
            }

            function clearResults() {
                results.replaceChildren();
                results.hidden = true;
                empty.hidden = true;
            }

            function clearSelection() {
                playerId.value = '';
                selected.hidden = true;
                form.hidden = true;
                if (selectedName) {
                    selectedName.textContent = '';
                }
                if (selectedMeta) {
                    selectedMeta.textContent = '';
                }
            }

            function selectPlayer(player) {
                playerId.value = String(player.id);
                input.value = player.player_name || '';
                if (selectedName) {
                    selectedName.textContent = player.player_name || 'Unnamed player';
                }
                if (selectedMeta) {
                    selectedMeta.textContent = 'Player ID ' + player.id + ' · '
                        + (player.branch_name || 'Authorized branch');
                }
                selected.hidden = false;
                form.hidden = false;
                clearResults();
                hint.textContent = 'Player selected. Complete the goal details.';
                hint.hidden = false;
            }

            function renderResults(items) {
                results.replaceChildren();
                empty.hidden = items.length !== 0;
                results.hidden = items.length === 0;

                items.forEach(function (player) {
                    var option = document.createElement('button');
                    var name = document.createElement('strong');
                    var meta = document.createElement('span');

                    option.className = 'mcc-feedback-player-search-result';
                    option.type = 'button';
                    option.setAttribute('role', 'option');
                    name.textContent = player.player_name || 'Unnamed player';
                    meta.textContent = 'Player ID ' + player.id + ' · '
                        + (player.branch_name || 'Authorized branch');
                    option.appendChild(name);
                    option.appendChild(meta);
                    option.addEventListener('click', function () {
                        selectPlayer(player);
                    });
                    results.appendChild(option);
                });
            }

            input.addEventListener('input', function () {
                var term = input.value.trim();

                clearSelection();
                clearResults();
                hint.hidden = term !== '';
                if (request) {
                    request.abort();
                    request = null;
                }
                if (timer) {
                    window.clearTimeout(timer);
                }
                if (term === '') {
                    hint.textContent = 'Enter a Player name or ID to search.';
                    hint.hidden = false;
                    return;
                }

                timer = window.setTimeout(function () {
                    hint.textContent = 'Searching Players…';
                    hint.hidden = false;
                    request = new AbortController();

                    fetch(container.dataset.searchUrl + '?q=' + encodeURIComponent(term), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        signal: request.signal,
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Player search failed.');
                            }

                            return response.json();
                        })
                        .then(function (payload) {
                            hint.textContent = 'Select one authorized Player to continue.';
                            renderResults(Array.isArray(payload.results) ? payload.results : []);
                        })
                        .catch(function (error) {
                            if (error.name !== 'AbortError') {
                                hint.textContent = 'Player search failed. Try again.';
                                renderResults([]);
                            }
                        });
                }, 250);
            });

            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    input.value = '';
                    clearSelection();
                    clearResults();
                    hint.textContent = 'Enter a Player name or ID to search.';
                    hint.hidden = false;
                    input.focus();
                });
            }

            document.addEventListener('click', function (event) {
                if (!container.contains(event.target)) {
                    clearResults();
                }
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    clearResults();
                }
            });
            form.addEventListener('submit', function (event) {
                if (!playerId.value) {
                    event.preventDefault();
                    hint.textContent = 'Select one authorized Player before saving.';
                    hint.hidden = false;
                    input.focus();
                }
            });

            container.dataset.goalPlayerSearchInitialized = 'true';
        });
    }

    function initFollowUpScheduleToggle() {
        document.querySelectorAll('[data-follow-up-schedule-toggle]').forEach(function (checkbox) {
            var fields = checkbox.form
                ? checkbox.form.querySelector('[data-follow-up-next-fields]')
                : null;

            if (! fields || checkbox.dataset.followUpScheduleInitialized === 'true') {
                return;
            }

            var date = fields.querySelector('[name="next_follow_up_at"]');
            var time = fields.querySelector('[name="next_follow_up_time"]');
            var sync = function () {
                var visible = checkbox.checked;
                fields.hidden = ! visible;

                [date, time].forEach(function (input) {
                    if (! input) {
                        return;
                    }

                    input.disabled = ! visible;
                    input.required = visible;
                    if (! visible) {
                        input.value = '';
                    }
                });
            };

            checkbox.addEventListener('change', sync);
            sync();
            checkbox.dataset.followUpScheduleInitialized = 'true';
        });
    }

    function initFollowUpActions() {
        var menus = document.querySelectorAll('[data-follow-up-actions]');
        var openMenu = null;
        var openToggle = null;
        var placeholder = null;

        if (! menus.length) {
            return;
        }

        var closeMenus = function () {
            if (openMenu && placeholder) {
                openMenu.hidden = true;
                openMenu.style.left = '';
                openMenu.style.top = '';
                openMenu.style.position = '';
                openMenu.style.zIndex = '';
                placeholder.parentNode.insertBefore(openMenu, placeholder.nextSibling);
                placeholder.remove();
            }

            document.querySelectorAll('[data-follow-up-actions-toggle]').forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            });
            openMenu = null;
            openToggle = null;
            placeholder = null;
        };

        menus.forEach(function (container) {
            var toggle = container.querySelector('[data-follow-up-actions-toggle]');
            var menu = container.querySelector('[data-follow-up-actions-menu]');

            if (! toggle || ! menu) {
                return;
            }

            toggle.addEventListener('click', function () {
                var shouldOpen = openMenu !== menu;
                closeMenus();
                if (! shouldOpen) {
                    return;
                }

                placeholder = document.createElement('span');
                placeholder.setAttribute('data-follow-up-actions-placeholder', 'true');
                menu.parentNode.insertBefore(placeholder, menu);
                document.body.appendChild(menu);
                menu.hidden = false;
                menu.style.position = 'fixed';
                menu.style.zIndex = '999999';
                var rect = toggle.getBoundingClientRect();
                var width = Math.min(menu.offsetWidth || 190, window.innerWidth - 16);
                var left = Math.min(rect.right - width, window.innerWidth - width - 8);
                var top = rect.bottom + 4;
                if (top + menu.offsetHeight > window.innerHeight && rect.top > menu.offsetHeight + 8) {
                    top = rect.top - menu.offsetHeight - 4;
                }
                menu.style.left = Math.max(8, left) + 'px';
                menu.style.top = Math.max(8, top) + 'px';
                openMenu = menu;
                openToggle = toggle;
                toggle.setAttribute('aria-expanded', 'true');
                var first = menu.querySelector('[role="menuitem"]');
                if (first) {
                    first.focus();
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (! event.target.closest('[data-follow-up-actions]')
                && ! event.target.closest('[data-follow-up-actions-menu]')) {
                closeMenus();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenus();
            }
        });
    }

    function initLeadImportModal() {
        var modal = document.querySelector('[data-lead-import-modal]');
        var opener = document.querySelector('[data-lead-import-open]');
        var form;
        var submit;
        var file;
        var validation;
        var success;
        var warning;
        var error;
        var validationMessage;
        var successMessage;
        var warningMessage;
        var errorMessage;
        var closeButtons;

        if (!modal || !opener) {
            return;
        }

        form = modal.querySelector('[data-lead-import-form]');
        submit = modal.querySelector('[data-lead-import-submit]');
        file = modal.querySelector('[data-lead-import-file]');
        validation = modal.querySelector('[data-lead-import-validation]');
        validationMessage = modal.querySelector('[data-lead-import-validation-message]');
        success = modal.querySelector('[data-lead-import-success]');
        warning = modal.querySelector('[data-lead-import-warning]');
        error = modal.querySelector('[data-lead-import-error]');
        successMessage = modal.querySelector('[data-lead-import-success-message]');
        warningMessage = modal.querySelector('[data-lead-import-warning-message]');
        errorMessage = modal.querySelector('[data-lead-import-error-message]');
        closeButtons = modal.querySelectorAll('[data-lead-import-close]');
        opener.addEventListener('click', function (event) {
            event.preventDefault();
            modal.hidden = false;
            modal.querySelector('[data-lead-import-file]').focus();
        });
        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                modal.hidden = true;
                opener.focus();
            });
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.hidden = true;
                opener.focus();
            }
        });
        form.addEventListener('submit', function (event) {
            var formData;

            event.preventDefault();
            validation.hidden = true;
            success.hidden = true;
            warning.hidden = true;
            error.hidden = true;
            if (!file.files.length) {
                validationMessage.textContent = 'Choose a CSV file to import.';
                validation.hidden = false;
                return;
            }

            submit.disabled = true;
            formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {Accept: 'application/json'}
            }).then(function (response) {
                return response.json().then(function (data) {
                    return {ok: response.ok, data: data};
                });
            }).then(function (response) {
                var data = response.data;
                var warningLines;
                var errorLines;

                if (!response.ok) {
                    validationMessage.textContent = data.error || 'The import request could not be processed.';
                    validation.hidden = false;
                    return;
                }

                if (data.imported > 0) {
                    successMessage.textContent = 'Imported ' + data.imported + ' of ' + data.total_rows + ' row(s) successfully.';
                    success.hidden = false;
                }
                warningLines = [];
                (data.errors || []).forEach(function (item) {
                    errorLines = errorLines || [];
                    errorLines.push('Row ' + item.row + ': ' + item.message);
                });
                (data.warnings || []).forEach(function (item) {
                    warningLines.push('Row ' + item.row + ': ' + item.message);
                });
                if (warningLines.length > 0) {
                    warningMessage.textContent = warningLines.length + ' warning(s) need attention.\n' + warningLines.join('\n');
                    warning.hidden = false;
                }
                if (errorLines && errorLines.length > 0) {
                    errorMessage.textContent = data.failed + ' row(s) failed validation.\n' + errorLines.join('\n');
                    error.hidden = false;
                }
            }).catch(function () {
                validationMessage.textContent = 'The import response could not be read. Please try again.';
                validation.hidden = false;
            }).finally(function () {
                submit.disabled = false;
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                modal.hidden = true;
                opener.focus();
            }
        });
    }

    function initStandaloneAdmissionActionMenus() {
        var toggles = document.querySelectorAll('[data-admission-actions-toggle]');
        var openDropdown = null;
        var openToggle = null;

        if (toggles.length === 0 || document.querySelector('[data-admission-modal]')) {
            return;
        }

        var closeMenus = function (restoreFocus) {
            document.querySelectorAll('[data-admission-actions-menu]').forEach(function (menu) {
                menu.hidden = true;
                menu.classList.remove('is-open');

                if (menu.dataset.admissionActionsPlaceholder) {
                    var placeholder = document.querySelector(
                        '[data-admission-actions-placeholder="'
                        + menu.dataset.admissionActionsPlaceholder
                        + '"]'
                    );

                    if (placeholder) {
                        placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
                        placeholder.remove();
                    }

                    delete menu.dataset.admissionActionsPlaceholder;
                    menu.style.left = '';
                    menu.style.top = '';
                    menu.style.position = '';
                    menu.style.right = '';
                    menu.style.zIndex = '';
                }
            });

            document.querySelectorAll('[data-admission-actions-toggle]').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });

            if (restoreFocus && openToggle) {
                openToggle.focus();
            }

            openDropdown = null;
            openToggle = null;

        };

        var positionDropdown = function (dropdown, toggle) {
            var rect = toggle.getBoundingClientRect();
            var width = dropdown.offsetWidth || 190;
            var height = dropdown.offsetHeight;
            var viewportWidth = document.documentElement.clientWidth;
            var left = rect.left + window.pageXOffset + rect.width - width;
            var top = rect.bottom + window.pageYOffset + 4;

            if (window.innerHeight - rect.bottom < height + 8 && rect.top >= height + 8) {
                top = rect.top + window.pageYOffset - height - 4;
            }

            dropdown.style.left = Math.max(
                8 + window.pageXOffset,
                Math.min(
                    left,
                    viewportWidth + window.pageXOffset - width - 8
                )
            ) + 'px';
            dropdown.style.top = top + 'px';
        };

        var openMenu = function (toggle) {
            var container = toggle.closest('[data-admission-actions]');
            var dropdown = container
                ? container.querySelector('[data-admission-actions-menu]')
                : null;

            if (!dropdown) {
                return;
            }

            var wasOpen = dropdown.classList.contains('is-open');
            closeMenus(false);
            if (wasOpen) {
                return;
            }

            var placeholder = document.createElement('span');
            var placeholderId = 'admission-actions-placeholder-' + Date.now();

            placeholder.setAttribute('data-admission-actions-placeholder', placeholderId);
            dropdown.parentNode.insertBefore(placeholder, dropdown);
            document.body.appendChild(dropdown);
            dropdown.dataset.admissionActionsPlaceholder = placeholderId;
            dropdown.hidden = false;
            dropdown.classList.add('is-open');
            dropdown.style.position = 'absolute';
            dropdown.style.right = 'auto';
            openDropdown = dropdown;
            openToggle = toggle;
            toggle.setAttribute('aria-expanded', 'true');
            positionDropdown(dropdown, toggle);
        };

        document.addEventListener('click', function (event) {
            var target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            var toggle = target.closest('[data-admission-actions-toggle]');
            if (toggle) {
                event.preventDefault();
                openMenu(toggle);
                return;
            }

            if (target.closest('[data-admission-actions-menu]')) {
                closeMenus(false);
                return;
            }

            if (! target.closest('[data-admission-actions]')) {
                closeMenus(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            var toggle = event.target instanceof Element && event.target.closest
                ? event.target.closest('[data-admission-actions-toggle]')
                : null;

            if (toggle && (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar')) {
                event.preventDefault();
                openMenu(toggle);
                return;
            }

            if (event.key === 'Escape' && openDropdown) {
                event.preventDefault();
                closeMenus(true);
                return;
            }

            if (! openDropdown || ! ['ArrowDown', 'ArrowUp'].includes(event.key)) {
                return;
            }

            var items = Array.from(openDropdown.querySelectorAll('a[href], button:not([disabled])'));
            if (items.length === 0) {
                return;
            }

            event.preventDefault();
            var currentIndex = items.indexOf(document.activeElement);
            var nextIndex = event.key === 'ArrowDown'
                ? (currentIndex + 1) % items.length
                : (currentIndex - 1 + items.length) % items.length;
            items[nextIndex].focus();
        });

        window.addEventListener('resize', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        });
        document.addEventListener('scroll', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        }, true);
    }

    function initAdmissionStatusModal() {
        var modal = document.querySelector('[data-admission-status-modal]');
        var form = modal ? modal.querySelector('[data-admission-status-form]') : null;
        var select = modal ? modal.querySelector('[data-admission-status-select]') : null;
        var reason = modal ? modal.querySelector('[data-admission-status-reason]') : null;
        var version = modal ? modal.querySelector('[data-admission-status-version-input]') : null;
        var submit = modal ? modal.querySelector('[data-admission-status-submit]') : null;
        var reference = modal ? modal.querySelector('[data-admission-status-reference]') : null;
        var formError = modal ? modal.querySelector('[data-admission-status-form-error]') : null;
        var pageMessage = document.querySelector('[data-admission-status-page-message]');
        var activeTrigger = null;
        var closeButtons;

        if (!modal || !form || !select || !reason || !version || !submit) {
            return;
        }

        closeButtons = modal.querySelectorAll('[data-admission-status-close], [data-admission-status-cancel]');

        var clearErrors = function () {
            formError.hidden = true;
            formError.textContent = '';
            modal.querySelectorAll('[data-admission-status-error]').forEach(function (error) {
                error.hidden = true;
                error.textContent = '';
            });
            modal.querySelectorAll('.is-invalid').forEach(function (field) {
                field.classList.remove('is-invalid');
            });
        };

        var setReasonRequirement = function () {
            reason.required = ['REJECTED', 'ON_HOLD'].includes(select.value);
        };

        var close = function () {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            if (activeTrigger) {
                activeTrigger.focus();
            }
            activeTrigger = null;
        };

        document.addEventListener('click', function (event) {
            var trigger = event.target instanceof Element
                ? event.target.closest('[data-admission-status-open]')
                : null;

            if (!trigger) {
                return;
            }

            event.preventDefault();
            activeTrigger = trigger;
            form.action = trigger.getAttribute('data-admission-status-action');
            version.value = trigger.getAttribute('data-admission-status-version') || '';
            reference.textContent = '#' + (trigger.getAttribute('data-admission-status-id') || '');
            select.value = trigger.getAttribute('data-admission-status-current') || 'TEST_SUBMITTED';
            reason.value = '';
            clearErrors();
            setReasonRequirement();
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            select.focus();
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', close);
        });
        select.addEventListener('change', setReasonRequirement);

        form.addEventListener('submit', function (event) {
            var formData;

            event.preventDefault();
            clearErrors();
            setReasonRequirement();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            submit.disabled = true;
            formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                return response.text().then(function (body) {
                    var data;

                    try {
                        data = JSON.parse(body);
                    } catch (ignore) {
                        data = {
                            status: false,
                            message: 'The status request could not be processed.'
                        };
                    }
                    return {ok: response.ok, data: data};
                });
            }).then(function (result) {
                var data = result.data;
                var row;
                var badge;

                if (!result.ok || !data.status) {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function (field) {
                            var error = modal.querySelector('[data-admission-status-error="' + field + '"]');
                            var input = modal.querySelector('[name="' + field + '"]');

                            if (field === '_form') {
                                formError.textContent = data.errors[field];
                                formError.hidden = false;
                                return;
                            }
                            if (error) {
                                error.textContent = data.errors[field];
                                error.hidden = false;
                            }
                            if (input) {
                                input.classList.add('is-invalid');
                            }
                        });
                    }
                    if (formError.hidden && data.message) {
                        formError.textContent = data.message;
                        formError.hidden = false;
                    }
                    return;
                }

                row = document.querySelector('[data-admission-test-row="' + activeTrigger.getAttribute('data-admission-status-id') + '"]');
                if (row) {
                    badge = row.querySelector('[data-admission-status-badge]');
                    if (badge) {
                        badge.textContent = data.status_label;
                        badge.className = 'mcc-badge mcc-status-badge ' + data.status_class;
                    }
                } else {
                    document.querySelectorAll('[data-admission-status-badge]').forEach(function (detailBadge) {
                        detailBadge.textContent = data.status_label;
                        detailBadge.className = 'mcc-badge mcc-status-badge ' + data.status_class;
                    });
                }
                activeTrigger.setAttribute('data-admission-status-version', data.version);
                activeTrigger.setAttribute('data-admission-status-current', data.status_code);
                if (pageMessage) {
                    pageMessage.textContent = data.message;
                    pageMessage.hidden = false;
                }
                close();
            }).catch(function () {
                formError.textContent = 'The status request could not be completed. Please try again.';
                formError.hidden = false;
            }).finally(function () {
                submit.disabled = false;
            });
        });
    }

    function initAdmissionActions() {
        var modal = document.querySelector('[data-admission-modal]');
        var frame = modal ? modal.querySelector('[data-admission-modal-frame]') : null;
        var frameWrap = modal ? modal.querySelector('[data-admission-modal-frame-wrap]') : null;
        var body = modal ? modal.querySelector('[data-admission-modal-body]') : null;
        var title = modal ? modal.querySelector('[data-admission-modal-title]') : null;
        var modalClose = modal ? modal.querySelector('[data-admission-modal-close]') : null;
        var panels = modal ? modal.querySelectorAll('[data-admission-panel]') : [];
        var activeTrigger = null;
        var activeAction = null;
        var admissionsUrl = modal ? modal.getAttribute('data-admission-base-url') : '/admissions';
        var openDropdown = null;
        var openToggle = null;

        if (!modal || !frame || !frameWrap || !title) {
            return;
        }

        var close = function () {
            var opener = activeTrigger;

            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            frame.removeAttribute('src');
            if (body) {
                body.innerHTML = '';
            }
            activeTrigger = null;
            activeAction = null;
            if (opener) {
                opener.focus();
            }
        };

        document.addEventListener('keydown', function (event) {
            if (modal.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var focusable = Array.from(modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), '
                + 'textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])'
            ));

            if (focusable.length === 0) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        var closeMenus = function (restoreFocus) {
            var toggleToRestore = openToggle;

            document.querySelectorAll('[data-admission-actions-menu]').forEach(function (menu) {
                menu.hidden = true;
                menu.classList.remove('is-open');

                if (menu.dataset.admissionActionsPlaceholder) {
                    var placeholder = document.querySelector(
                        '[data-admission-actions-placeholder="'
                        + menu.dataset.admissionActionsPlaceholder
                        + '"]'
                    );

                    if (placeholder && placeholder.matches('[data-admission-actions-placeholder]')) {
                        placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
                        placeholder.remove();
                    }

                    delete menu.dataset.admissionActionsPlaceholder;
                    menu.style.left = '';
                    menu.style.top = '';
                    menu.style.position = '';
                    menu.style.right = '';
                }
            });
            document.querySelectorAll('[data-admission-actions-toggle]').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });
            openDropdown = null;
            openToggle = null;

            if (restoreFocus && toggleToRestore) {
                toggleToRestore.focus();
            }
        };

        var positionDropdown = function (dropdown, toggle) {
            var rect = toggle.getBoundingClientRect();
            var dropdownWidth = dropdown.offsetWidth || 190;
            var dropdownHeight = dropdown.offsetHeight;
            var viewportWidth = document.documentElement.clientWidth;
            var left = rect.left + window.pageXOffset;
            var top = rect.bottom + window.pageYOffset + 4;
            var spaceBelow = window.innerHeight - rect.bottom;
            var spaceAbove = rect.top;

            if (spaceBelow < dropdownHeight + 8 && spaceAbove >= dropdownHeight + 8) {
                top = rect.top + window.pageYOffset - dropdownHeight - 4;
            }

            left = Math.min(
                Math.max(8 + window.pageXOffset, left + rect.width - dropdownWidth),
                Math.max(8 + window.pageXOffset, viewportWidth + window.pageXOffset - dropdownWidth - 8)
            );
            dropdown.style.left = left + 'px';
            dropdown.style.top = top + 'px';
        };

        var setFeedback = function (form, type, message) {
            var feedback = form ? form.querySelector('[data-admission-response]') : null;

            if (! feedback) {
                return;
            }

            feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
            feedback.classList.add(type === 'success' ? 'mcc-alert-success' : 'mcc-alert-danger');
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');

            if (typeof feedback.scrollIntoView === 'function') {
                feedback.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }
        };

        var clearFeedback = function (form) {
            var feedbackItems = form
                ? form.querySelectorAll('[data-admission-response]')
                : modal.querySelectorAll('[data-admission-response]');

            feedbackItems.forEach(function (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
            });
        };

        var setSubmitting = function (form, submitting) {
            var button = form.querySelector('button[type="submit"]');

            if (! button) {
                return;
            }

            if (submitting) {
                button.dataset.admissionOriginalLabel = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
                return;
            }

            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.dataset.admissionOriginalLabel) {
                button.innerHTML = button.dataset.admissionOriginalLabel;
                delete button.dataset.admissionOriginalLabel;
            }
        };

        var responseMessage = function (html, selector) {
            var parsed = new DOMParser().parseFromString(html, 'text/html');
            var message = parsed.querySelector(selector);

            return message ? message.textContent.trim() : '';
        };

        var refreshAfterSuccess = function (form, message) {
            setFeedback(form, 'success', message);
            window.setTimeout(function () {
                close();
                window.location.reload();
            }, 700);
        };

        document.addEventListener('click', function (event) {
            var target = event.target;
            var toggle;
            var menu;
            var dropdown;
            var trigger;
            var closeButton;

            if (!(target instanceof Element)) {
                return;
            }

            closeButton = target.closest('[data-admission-modal-close]');
            if (closeButton && modal.contains(closeButton)) {
                event.preventDefault();
                close();
                return;
            }

            toggle = target.closest('[data-admission-actions-toggle]');
            if (toggle) {
                event.preventDefault();
                menu = toggle.closest('[data-admission-actions]');
                dropdown = menu ? menu.querySelector('[data-admission-actions-menu]') : null;

                if (!dropdown) {
                    return;
                }

                var wasOpen = dropdown.classList.contains('is-open');

                closeMenus(false);
                if (!wasOpen) {
                    var placeholder = document.createElement('span');
                    var placeholderId = 'admission-actions-placeholder-' + Date.now();

                    placeholder.setAttribute('data-admission-actions-placeholder', placeholderId);
                    dropdown.parentNode.insertBefore(placeholder, dropdown);
                    document.body.appendChild(dropdown);
                    dropdown.dataset.admissionActionsPlaceholder = placeholderId;
                    dropdown.hidden = false;
                    dropdown.classList.add('is-open');
                    dropdown.style.position = 'absolute';
                    dropdown.style.right = 'auto';
                    dropdown.style.zIndex = '999999';
                    openDropdown = dropdown;
                    openToggle = toggle;
                    positionDropdown(dropdown, toggle);
                    toggle.setAttribute('aria-expanded', 'true');

                }
                return;
            }

            trigger = target.closest('[data-admission-action]');

            if (trigger) {
                event.preventDefault();
                if (trigger.getAttribute('data-admission-action') === 'view') {
                    event.stopPropagation();
                }
            } else if (!target.closest('[data-admission-actions]')) {
                closeMenus(false);
                return;
            }

            if (!trigger) {
                return;
            }

            activeTrigger = trigger;
            activeAction = trigger.getAttribute('data-admission-action');
            closeMenus();
            panels.forEach(function (panel) {
                panel.hidden = true;
            });
            frameWrap.hidden = true;
            clearFeedback();
            if (body) {
                body.innerHTML = '';
            }
            title.textContent = trigger.getAttribute('data-admission-name') + ' - ' + trigger.textContent.trim();
            modal.setAttribute('aria-hidden', 'false');
            modal.hidden = false;
            if (modalClose) {
                modalClose.focus();
            }

            if (trigger.getAttribute('data-admission-url')) {
                if ((activeAction === 'view' || activeAction === 'edit') && body) {
                    fetch(trigger.getAttribute('data-admission-url'), {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'text/html',
                        },
                    })
                        .then(function (response) {
                            if (! response.ok) {
                                throw new Error('Admission details could not be loaded.');
                            }

                            return response.text();
                        })
                        .then(function (html) {
                            if (! modal.hidden && activeTrigger === trigger) {
                                body.innerHTML = html;
                                if (activeAction === 'edit') {
                                    initDatePickers(body);
                                }

                            }
                        })
                        .catch(function () {
                            if (! modal.hidden && activeTrigger === trigger) {
                                body.textContent = 'Admission details could not be loaded.';
                            }
                        });

                    return;
                }

                frameWrap.hidden = false;
                frame.name = 'admission-action-frame';
                frame.src = trigger.getAttribute('data-admission-url');
                return;
            }

            var action = activeAction;
            var panel = modal.querySelector('[data-admission-panel="' + action + '"]');
            var form = panel ? panel.querySelector('[data-admission-modal-form]') : null;
            var admissionId = trigger.getAttribute('data-admission-id');

            if (!panel || !form) {
                return;
            }

            panel.hidden = false;
            frame.name = 'admission-action-frame';
            form.target = 'admission-action-frame';
            if (action === 'checklist') {
                var checklist = JSON.parse(trigger.getAttribute('data-admission-checklist') || '{}');

                panel.querySelectorAll('[data-admission-checklist-field]').forEach(function (input) {
                    input.checked = Number(checklist[input.getAttribute('data-admission-checklist-field')] || 0) === 1;
                });
                form.action = admissionsUrl + '/' + admissionId + '/checklist';
            }
            if (action === 'status') {
                var options = JSON.parse(trigger.getAttribute('data-admission-transitions') || '[]');
                var select = panel.querySelector('[data-admission-status-options]');
                var reason = panel.querySelector('[name="reason"]');
                var currentStatus = trigger.getAttribute('data-admission-current-status') || '';

                select.innerHTML = '';
                options.forEach(function (option) {
                    var element = document.createElement('option');

                    element.value = option;
                    element.textContent = option;
                    select.appendChild(element);
                });
                var syncReasonRequirement = function () {
                    var requiresReason = ['REJECTED', 'WITHDRAWN'].indexOf(select.value) !== -1
                        || (currentStatus === 'REJECTED' && select.value === 'IN_REVIEW');

                    reason.required = requiresReason;
                    reason.setAttribute('aria-required', requiresReason ? 'true' : 'false');
                };

                select.onchange = syncReasonRequirement;
                syncReasonRequirement();
                form.action = admissionsUrl + '/' + admissionId + '/status';
            }
            if (action === 'convert') {
                form.action = admissionsUrl + '/' + admissionId + '/convert';
            }
            if (action === 'document') {
                form.action = admissionsUrl + '/' + admissionId + '/documents';
            }
            if (action === 'profile-photo') {
                form.action = admissionsUrl + '/' + admissionId + '/documents';
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!(form instanceof HTMLFormElement)
                || ! modal.contains(form)
                || (! form.matches('[data-admission-modal-form]')
                    && ! form.matches('.mcc-admission-form'))) {
                return;
            }

            event.preventDefault();
            if (form.dataset.admissionSubmitting === 'true') {
                return;
            }

            form.dataset.admissionSubmitting = 'true';
            setSubmitting(form, true);
            clearFeedback(form);

            fetch(form.action, {
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                },
                method: 'POST',
            })
                .then(function (response) {
                    return response.text().then(function (html) {
                        return {
                            html: html,
                            response: response,
                        };
                    });
                })
                .then(function (result) {
                    if (modal.hidden) {
                        return;
                    }

                    var errorMessage = responseMessage(result.html, '.mcc-alert-danger');
                    var responseUrl = result.response.url || '';

                    if (errorMessage !== '') {
                        var responseForm = form;

                        if (form.matches('.mcc-admission-form')) {
                            body.innerHTML = result.html;
                            initDatePickers(body);
                            responseForm = body.querySelector('.mcc-admission-form') || form;
                        }
                        setSubmitting(form, false);
                        form.dataset.admissionSubmitting = 'false';
                        responseForm.dataset.admissionSubmitting = 'false';
                        setFeedback(responseForm, 'error', errorMessage);
                        return;
                    }

                    if (responseUrl.indexOf('/admissions/') !== -1
                        && responseUrl.endsWith('/page')) {
                        var successMessages = {
                            checklist: 'Checklist updated successfully.',
                            document: 'Document uploaded successfully.',
                            edit: 'Admission updated successfully.',
                            'profile-photo': 'Profile image uploaded successfully.',
                            status: 'Status updated successfully.',
                        };
                        var successMessage = successMessages[activeAction]
                            || (form.matches('.mcc-admission-form')
                                ? 'Admission updated successfully.'
                                : 'Admission updated successfully.');

                        refreshAfterSuccess(form, successMessage);
                        return;
                    }

                    if (responseUrl.indexOf('/players/') !== -1) {
                        refreshAfterSuccess(form, 'Admission converted successfully.');
                        return;
                    }

                    setSubmitting(form, false);
                    form.dataset.admissionSubmitting = 'false';
                    setFeedback(form, 'error', 'The admission update could not be completed.');
                })
                .catch(function () {
                    if (modal.hidden) {
                        return;
                    }

                    setSubmitting(form, false);
                    form.dataset.admissionSubmitting = 'false';
                    setFeedback(form, 'error', 'The admission update could not be completed.');
                });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                close();
            }
        });
        window.addEventListener('resize', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        });
        document.addEventListener('scroll', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        }, true);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                close();
            }
        });
        frame.addEventListener('load', function () {
            var path;
            var bodyText;

            try {
                path = frame.contentWindow.location.pathname;
                bodyText = frame.contentDocument.body.textContent || '';
            } catch (error) {
                return;
            }
            if (activeAction !== 'view'
                && ((path.indexOf('/admissions/') !== -1 && path.endsWith('/page'))
                || (path.endsWith('/admissions') && bodyText.indexOf('uploaded') !== -1))) {
                if (bodyText.indexOf('updated') !== -1 || bodyText.indexOf('created') !== -1
                    || bodyText.indexOf('uploaded') !== -1) {
                    close();
                    window.location.reload();
                }
            }
            if (path.indexOf('/players/') !== -1) {
                close();
                window.location.reload();
            }
        });
    }

    function initAdmissionTestWizard() {
        var wizard = document.querySelector('[data-admission-test-wizard]');

        if (! wizard) {
            return;
        }

        var steps = Array.prototype.slice.call(wizard.querySelectorAll('[data-wizard-step]'));
        var progress = Array.prototype.slice.call(wizard.querySelectorAll('[data-wizard-progress]'));
        var previous = wizard.querySelector('[data-wizard-previous]');
        var next = wizard.querySelector('[data-wizard-next]');
        var submit = wizard.querySelector('[data-wizard-submit]');
        var form = wizard.querySelector('form');
        var personalDetailsAction = wizard.dataset.personalDetailsAction || '';
        var currentStep = Number(wizard.dataset.initialStep || 1);
        var totalSteps = steps.length;

        var refreshReview = function () {
            wizard.querySelectorAll('[data-review-for]').forEach(function (review) {
                var field = review.getAttribute('data-review-for');
                var input = wizard.querySelector('[name="' + field + '"]:checked');

                if (! input) {
                    input = wizard.querySelector('[name="' + field + '"]');
                }

                var value = input ? input.value.trim() : '';
                review.textContent = value || (field.indexOf('answer_') === 0 ? 'Not answered' : 'Not rated');
            });
        };

        var showStep = function (step) {
            currentStep = Math.max(1, Math.min(totalSteps, step));
            steps.forEach(function (panel) {
                panel.hidden = Number(panel.dataset.wizardStep) !== currentStep;
            });
            progress.forEach(function (item) {
                var number = Number(item.dataset.wizardProgress);
                item.classList.toggle('is-current', number === currentStep);
                item.classList.toggle('is-complete', number < currentStep);
                item.setAttribute('aria-current', number === currentStep ? 'step' : 'false');
            });
            previous.disabled = currentStep === 1;
            next.hidden = currentStep === totalSteps;
            submit.hidden = currentStep !== totalSteps;
            if (currentStep === totalSteps) {
                refreshReview();
            }
        };

        next.addEventListener('click', function () {
            var active = wizard.querySelector('[data-wizard-step="' + currentStep + '"]');
            var requiredFields = active ? active.querySelectorAll('[required]') : [];
            var valid = true;

            requiredFields.forEach(function (field) {
                if (! field.checkValidity()) {
                    field.reportValidity();
                    valid = false;
                }
            });

            if (valid) {
                if (currentStep === 1 && personalDetailsAction && form) {
                    form.action = personalDetailsAction;
                    form.submit();
                    return;
                }

                showStep(currentStep + 1);
            }
        });
        previous.addEventListener('click', function () {
            showStep(currentStep - 1);
        });
        wizard.addEventListener('input', refreshReview);
        wizard.addEventListener('change', refreshReview);
        showStep(currentStep);
    }

    function initAdmissionTestPersonalDetailsAnchor() {
        var panel = document.querySelector('[data-admission-test-personal-details-panel]');
        var section = document.getElementById('personal-details');

        if (!panel || !section) {
            return;
        }

        var openFromHash = function () {
            if (window.location.hash !== '#personal-details') {
                return;
            }

            panel.open = true;
            window.requestAnimationFrame(function () {
                section.scrollIntoView({ block: 'start' });
            });
        };

        openFromHash();
        window.addEventListener('hashchange', openFromHash);
    }

    function initAdmissionTermsModal() {
        var modal = document.querySelector('[data-admission-terms-modal]');
        var openButtons = document.querySelectorAll('[data-admission-terms-open]');
        var closeButton = modal ? modal.querySelector('[data-admission-terms-close]') : null;
        var activeTrigger = null;

        if (!modal || !closeButton || openButtons.length === 0) {
            return;
        }

        var focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), '
            + 'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        var close = function () {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');

            if (activeTrigger) {
                activeTrigger.focus();
            }
        };

        var open = function (trigger) {
            activeTrigger = trigger;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            closeButton.focus();
        };

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                open(button);
            });
        });

        closeButton.addEventListener('click', close);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                close();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (modal.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var focusable = Array.from(modal.querySelectorAll(focusableSelector));
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (!first || !last) {
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    function initAdmissionFormFeedback() {
        document.querySelectorAll('[data-admission-submit-feedback]').forEach(function (form) {
            if (form.dataset.admissionFeedbackInitialized === 'true') {
                return;
            }

            form.dataset.admissionFeedbackInitialized = 'true';
            form.addEventListener('submit', function () {
                if (form.dataset.admissionSubmitting === 'true') {
                    return;
                }

                var button = form.querySelector('button[type="submit"]');

                if (! button) {
                    return;
                }

                form.dataset.admissionSubmitting = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
            });
        });
    }

    function initPlayerFormFeedback() {
        document.querySelectorAll('[data-player-submit-feedback]').forEach(function (form) {
            if (form.dataset.playerFeedbackInitialized === 'true') {
                return;
            }

            form.dataset.playerFeedbackInitialized = 'true';
            form.addEventListener('submit', function (event) {
                var button = form.querySelector('button[type="submit"]');
                var feedback = form.querySelector('[data-player-form-feedback]');

                if (! button || ! feedback) {
                    return;
                }

                event.preventDefault();

                if (form.dataset.playerSubmitting === 'true') {
                    return;
                }

                form.dataset.playerSubmitting = 'true';
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
                button.dataset.playerOriginalLabel = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

                fetch(form.action, {
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/html',
                    },
                    method: 'POST',
                })
                    .then(function (response) {
                        return response.text().then(function (html) {
                            return {
                                html: html,
                                response: response,
                            };
                        });
                    })
                    .then(function (result) {
                        var parsed = new DOMParser().parseFromString(result.html, 'text/html');
                        var error = parsed.querySelector('.mcc-alert-danger');
                        var success = parsed.querySelector('.mcc-alert-success');

                        if (! result.response.ok || error) {
                            restorePlayerForm(form);
                            showPlayerFormFeedback(
                                form,
                                'error',
                                error ? error.textContent.trim() : 'The player update could not be completed.'
                            );
                            return;
                        }

                        showPlayerFormFeedback(
                            form,
                            'success',
                            success ? success.textContent.trim() : 'Player updated successfully.'
                        );
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 700);
                    })
                    .catch(function () {
                        restorePlayerForm(form);
                        showPlayerFormFeedback(
                            form,
                            'error',
                            'The player update could not be completed. Please try again.'
                        );
                    });
            });
        });

        function restorePlayerForm(form) {
            var button = form.querySelector('button[type="submit"]');

            form.dataset.playerSubmitting = 'false';

            if (! button) {
                return;
            }

            button.disabled = false;
            button.removeAttribute('aria-busy');

            if (button.dataset.playerOriginalLabel) {
                button.innerHTML = button.dataset.playerOriginalLabel;
                delete button.dataset.playerOriginalLabel;
            }
        }

        function showPlayerFormFeedback(form, type, message) {
            var feedback = form.querySelector('[data-player-form-feedback]');

            if (! feedback) {
                return;
            }

            feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
            feedback.classList.add(type === 'success' ? 'mcc-alert-success' : 'mcc-alert-danger');
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');
        }
    }

    function initPlayerListActions() {
        var modal = document.querySelector('[data-player-modal]');
        var body = modal ? modal.querySelector('[data-player-modal-body]') : null;
        var title = modal ? modal.querySelector('[data-player-modal-title]') : null;
        var loading = modal ? modal.querySelector('[data-player-modal-loading]') : null;
        var baseUrl = modal ? modal.getAttribute('data-player-base-url') : '';
        var player360BaseUrl = baseUrl.replace(/\/players\/?$/, '') + '/player-360';
        var activeTrigger = null;
        var requestToken = 0;
        var openDropdown = null;
        var openToggle = null;

        try {
            var coachSuccessMessage = window.sessionStorage.getItem('mcc-player-coach-success');

            if (coachSuccessMessage) {
                window.sessionStorage.removeItem('mcc-player-coach-success');
                var playerPage = document.querySelector('main.container.py-4');
                var pageAlert = document.createElement('div');

                pageAlert.className = 'mcc-alert mcc-alert-success';
                pageAlert.setAttribute('role', 'status');
                pageAlert.textContent = coachSuccessMessage;
                if (playerPage) {
                    playerPage.insertBefore(pageAlert, playerPage.firstChild);
                }
            }
        } catch (error) {
            // The modal notification remains available if session storage is unavailable.
        }

        var actionLabels = {
            view: 'View details',
            profile: 'Edit player',
            status: 'Update status',
            branch: 'Assign branch',
            batch: 'Assign batch',
            coaches: 'Update Coaches',
            contacts: 'Manage contacts',
            goal: 'Set Goal',
            '360': 'Player 360 view',
        };
        var formActions = {
            profile: 'profile',
            status: 'status',
            branch: 'branch',
            batch: 'batch',
            coaches: 'coaches',
            contacts: 'contacts',
            goal: 'goal',
        };

        var hasStandaloneActionMenus = document.querySelector(
            '[data-player-feedback-action-menu]'
        );

        if ((!modal || !body || !title) && !hasStandaloneActionMenus) {
            return;
        }

        var closeMenus = function (restoreFocus) {
            var toggleToRestore = openToggle;

            document.querySelectorAll('[data-player-actions-menu]').forEach(function (menu) {
                menu.hidden = true;
                menu.classList.remove('is-open');

                if (menu.dataset.playerActionsPlaceholder) {
                    var placeholder = document.querySelector(
                        '[data-player-actions-placeholder="'
                        + menu.dataset.playerActionsPlaceholder
                        + '"]'
                    );

                    if (placeholder) {
                        placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
                        placeholder.remove();
                    }

                    delete menu.dataset.playerActionsPlaceholder;
                    menu.style.left = '';
                    menu.style.top = '';
                    menu.style.position = '';
                    menu.style.right = '';
                }
            });
            document.querySelectorAll('[data-player-actions-toggle]').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });
            openDropdown = null;
            openToggle = null;

            if (restoreFocus && toggleToRestore) {
                toggleToRestore.focus();
            }
        };

        var positionDropdown = function (dropdown, toggle) {
            var rect = toggle.getBoundingClientRect();
            var dropdownWidth = dropdown.offsetWidth || 210;
            var dropdownHeight = dropdown.offsetHeight;
            var viewportWidth = document.documentElement.clientWidth;
            var left = rect.left;
            var top = rect.bottom + 4;
            var spaceBelow = window.innerHeight - rect.bottom;
            var spaceAbove = rect.top;

            if (spaceBelow < dropdownHeight + 8 && spaceAbove > spaceBelow) {
                top = rect.top - dropdownHeight - 4;
            }

            var maxTop = Math.max(8, window.innerHeight - dropdownHeight - 8);
            top = Math.min(Math.max(8, top), maxTop);

            left = Math.min(
                Math.max(8, left + rect.width - dropdownWidth),
                Math.max(8, viewportWidth - dropdownWidth - 8)
            );
            dropdown.style.left = left + 'px';
            dropdown.style.top = top + 'px';
        };

        var close = function () {
            var opener = activeTrigger;

            requestToken += 1;
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            body.replaceChildren();
            activeTrigger = null;
            if (opener) {
                opener.focus();
            }
        };

        var showLoading = function () {
            body.replaceChildren();
            if (loading) {
                loading.hidden = false;
                body.appendChild(loading);
            }
        };

        var showMessage = function (message) {
            body.replaceChildren();
            var messageElement = document.createElement('p');

            messageElement.className = 'mcc-empty-state';
            messageElement.textContent = message;
            body.appendChild(messageElement);
        };

        var prepareDynamicForm = function (form) {
            if (form.matches('.mcc-player-photo-upload')) {
                var uploadButton = form.querySelector('button[type="submit"]');

                if (uploadButton && !uploadButton.closest('.mcc-modal-actions')) {
                    var uploadActions = document.createElement('div');

                    uploadActions.className = 'mcc-modal-actions';
                    uploadButton.parentNode.insertBefore(uploadActions, uploadButton);
                    uploadActions.appendChild(uploadButton);
                }
            }

            form.querySelectorAll('.mcc-form-actions').forEach(function (actions) {
                actions.classList.remove('mcc-form-actions');
                actions.classList.add('mcc-modal-actions');
            });
            form.querySelectorAll(
                '.mcc-modal-actions .mcc-button[type="submit"]'
            ).forEach(function (button) {
                button.classList.remove('mcc-button-secondary');
                button.classList.add('mcc-button-primary');
            });
            form.querySelectorAll('[data-mcc-datetime-submit-format="Y-m-d"]').forEach(function (input) {
                input.form.addEventListener('submit', function () {
                    var value = input.value;
                    var match = value.match(/^(\d{2})-(\d{2})-(\d{4})(.*)$/);

                    if (match) {
                        input.value = match[3] + '-' + match[2] + '-' + match[1] + match[4];
                    }
                });
            });
            if (form.matches('[data-player-coach-form]')) {
                form.dataset.playerFeedbackInitialized = 'true';
                var restoreCoachForm = function () {
                    var button = form.querySelector('button[type="submit"]');

                    form.dataset.playerSubmitting = 'false';
                    if (!button) {
                        return;
                    }

                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    if (button.dataset.playerOriginalLabel) {
                        button.innerHTML = button.dataset.playerOriginalLabel;
                        delete button.dataset.playerOriginalLabel;
                    }
                };
                var showCoachFeedback = function (type, message) {
                    var feedback = form.querySelector('[data-player-form-feedback]');

                    if (!feedback) {
                        return;
                    }

                    feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
                    feedback.classList.add(type === 'success' ? 'mcc-alert-success' : 'mcc-alert-danger');
                    feedback.textContent = message;
                    feedback.hidden = false;
                    feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');
                };

                form.addEventListener('submit', function (event) {
                    var button = form.querySelector('button[type="submit"]');
                    var feedback = form.querySelector('[data-player-form-feedback]');

                    event.preventDefault();

                    if (!button || form.dataset.playerSubmitting === 'true') {
                        return;
                    }

                    form.dataset.playerSubmitting = 'true';
                    if (feedback) {
                        feedback.hidden = true;
                        feedback.textContent = '';
                    }
                    button.dataset.playerOriginalLabel = button.innerHTML;
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

                    fetch(form.action, {
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {Accept: 'application/json'},
                        method: 'POST',
                    })
                        .then(function (response) {
                            return response.text().then(function (text) {
                                var payload = {};

                                try {
                                    payload = text ? JSON.parse(text) : {};
                                } catch (error) {
                                    payload = {};
                                }

                                return {payload: payload, response: response};
                            });
                        })
                        .then(function (result) {
                            if (!result.response.ok) {
                                restoreCoachForm();
                                showCoachFeedback(
                                    'error',
                                    result.payload.error || 'The coach update could not be completed.'
                                );
                                return;
                            }

                            var successMessage = result.payload.message || 'Player coaches updated successfully.';

                            showCoachFeedback('success', successMessage);
                            try {
                                window.sessionStorage.setItem('mcc-player-coach-success', successMessage);
                            } catch (error) {
                                // Continue with the modal notification if session storage is unavailable.
                            }
                            window.setTimeout(function () {
                                close();
                                window.location.reload();
                            }, 700);
                        })
                        .catch(function () {
                            restoreCoachForm();
                            showCoachFeedback(
                                'error',
                                'The coach update could not be completed. Please try again.'
                            );
                        });
                });
            }
            if (form.matches('.mcc-player-goal-form')) {
                form.addEventListener('submit', function (event) {
                    var feedback = form.querySelector('[data-player-form-feedback]');
                    var submit = form.querySelector('button[type="submit"]');

                    event.preventDefault();
                    if (feedback) {
                        feedback.hidden = true;
                        feedback.textContent = '';
                    }
                    if (submit) {
                        submit.disabled = true;
                        submit.setAttribute('aria-busy', 'true');
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {Accept: 'application/json'},
                    })
                        .then(function (response) {
                            return response.text().then(function (text) {
                                var payload = {};

                                try {
                                    payload = text ? JSON.parse(text) : {};
                                } catch (error) {
                                    payload = {};
                                }

                                if (! response.ok) {
                                    throw new Error(payload.error || 'The Goal could not be saved.');
                                }

                                return payload;
                            });
                        })
                        .then(function () {
                            close();
                            window.location.reload();
                        })
                        .catch(function (error) {
                            if (feedback) {
                                feedback.textContent = error.message || 'The Goal could not be saved.';
                                feedback.hidden = false;
                                feedback.setAttribute('role', 'alert');
                            }
                            if (submit) {
                                submit.disabled = false;
                                submit.removeAttribute('aria-busy');
                            }
                        });
                });
            }
            initDatePickers(form);
            initPlayerFormFeedback();
        };

        var loadForm = function (trigger, action, token) {
            var playerId = trigger.getAttribute('data-player-id');
            var expectedAction = formActions[action];
            var expectedUrl = baseUrl + '/' + playerId + '/' + expectedAction;

            fetch(baseUrl + '/' + playerId, {
                credentials: 'same-origin',
                headers: {Accept: 'text/html'},
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Player form could not be loaded.');
                    }

                    return response.text();
                })
                .then(function (html) {
                    if (token !== requestToken || modal.hidden) {
                        return;
                    }

                    var parsed = new DOMParser().parseFromString(html, 'text/html');
                    var form = Array.prototype.find.call(parsed.querySelectorAll('form'), function (candidate) {
                        var candidateUrl = candidate.getAttribute('action') || '';

                        return candidateUrl === expectedUrl
                            && (candidate.getAttribute('method') || 'get').toLowerCase() === 'post';
                    });

                    if (!form || !form.querySelector('input[name="csrf_test_name"], input[name="csrf_token"]')) {
                        throw new Error('You are not authorized to manage this Player assignment.');
                    }

                    var fragment = document.importNode(form, true);

                    fragment.id = 'player-modal-' + action + '-form-' + playerId;

                    body.replaceChildren(fragment);
                    prepareDynamicForm(fragment);
                })
                .catch(function (error) {
                    if (token === requestToken && !modal.hidden) {
                        showMessage(error.message || 'Player form could not be loaded.');
                    }
                });
        };

        var loadPlayer360 = function (trigger, token) {
            fetch(player360BaseUrl + '/' + trigger.getAttribute('data-player-id'), {
                credentials: 'same-origin',
                headers: {Accept: 'text/html'},
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Player 360 view could not be loaded.');
                    }

                    return response.text();
                })
                .then(function (html) {
                    if (token !== requestToken || modal.hidden) {
                        return;
                    }

                    var parsed = new DOMParser().parseFromString(html, 'text/html');
                    var content = parsed.querySelector('main.container.py-4');

                    if (!content) {
                        throw new Error('Player 360 view could not be loaded.');
                    }

                    body.replaceChildren(document.importNode(content, true));
                })
                .catch(function (error) {
                    if (token === requestToken && !modal.hidden) {
                        showMessage(error.message || 'Player 360 view could not be loaded.');
                    }
                });
        };

        document.addEventListener('click', function (event) {
            var target = event.target;
            var toggle;
            var menu;
            var dropdown;
            var trigger;

            if (!(target instanceof Element)) {
                return;
            }

            if (target.closest('[data-player-modal-close]') || target === modal) {
                event.preventDefault();
                close();
                return;
            }

            if (target.closest('[data-player-goal-cancel]')) {
                event.preventDefault();
                close();
                return;
            }

            toggle = target.closest('[data-player-actions-toggle]');
            if (toggle) {
                event.preventDefault();
                menu = toggle.closest('[data-player-actions]');
                dropdown = menu ? menu.querySelector('[data-player-actions-menu]') : null;

                if (!dropdown) {
                    return;
                }

                var wasOpen = dropdown.classList.contains('is-open');

                closeMenus(false);
                if (!wasOpen) {
                    var placeholder = document.createElement('span');
                    var placeholderId = 'player-actions-placeholder-' + Date.now();

                    placeholder.setAttribute('data-player-actions-placeholder', placeholderId);
                    dropdown.parentNode.insertBefore(placeholder, dropdown);
                    document.body.appendChild(dropdown);
                    dropdown.dataset.playerActionsPlaceholder = placeholderId;
                    dropdown.hidden = false;
                    dropdown.classList.add('is-open');
                    dropdown.style.position = 'fixed';
                    dropdown.style.zIndex = '99999';
                    dropdown.style.right = 'auto';
                    openDropdown = dropdown;
                    openToggle = toggle;
                    positionDropdown(dropdown, toggle);
                    toggle.setAttribute('aria-expanded', 'true');

                    var firstPlayerAction = dropdown.querySelector('[data-player-action]');
                    if (firstPlayerAction) {
                        window.setTimeout(function () {
                            if (dropdown.classList.contains('is-open')) {
                                firstPlayerAction.focus();
                            }
                        }, 0);
                    }
                }
                return;
            }

            trigger = target.closest('[data-player-action]');
            if (trigger) {
                event.preventDefault();
                closeMenus(false);
                activeTrigger = trigger;
                requestToken += 1;
                var token = requestToken;
                var action = trigger.getAttribute('data-player-action');
                var actionMenu = trigger.closest('[data-player-actions]');
                var playerName = trigger.dataset.playerName
                    || (actionMenu ? actionMenu.getAttribute('data-player-name') : '')
                    || ('Player #' + trigger.getAttribute('data-player-id'));

                if (action === 'view') {
                    var detailUrl = trigger.getAttribute('data-player-url');

                    if (detailUrl) {
                        window.location.assign(detailUrl);
                    }
                    return;
                }

                title.textContent = playerName + ' - ' + (actionLabels[action] || 'Player action');
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                showLoading();

                if (action === '360') {
                    loadPlayer360(trigger, token);
                    return;
                }

                if (formActions[action]) {
                    loadForm(trigger, action, token);
                }
                return;
            }

            if (!target.closest('[data-player-actions]')) {
                closeMenus();
            }
        });

        window.addEventListener('resize', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        });
        document.addEventListener('scroll', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        }, true);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (modal && !modal.hidden) {
                    close();
                } else {
                    closeMenus(true);
                }

                return;
            }

            if (!openDropdown || !openToggle) {
                return;
            }

            var menuItems = Array.from(openDropdown.querySelectorAll('[data-player-action]'));
            var focusedIndex = menuItems.indexOf(document.activeElement);

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();

                if (menuItems.length === 0) {
                    return;
                }

                var direction = event.key === 'ArrowDown' ? 1 : -1;
                var nextIndex = focusedIndex < 0
                    ? (direction > 0 ? 0 : menuItems.length - 1)
                    : (focusedIndex + direction + menuItems.length) % menuItems.length;
                menuItems[nextIndex].focus();
            } else if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();

                if (menuItems.length > 0) {
                    menuItems[event.key === 'Home' ? 0 : menuItems.length - 1].focus();
                }
            }
        });
    }

    function initCoachListActions() {
        var modal = document.querySelector('[data-coach-modal]');
        var body = modal ? modal.querySelector('[data-coach-modal-body]') : null;
        var title = modal ? modal.querySelector('[data-coach-modal-title]') : null;
        var loading = modal ? modal.querySelector('[data-coach-modal-loading]') : null;
        var activeTrigger = null;
        var requestToken = 0;
        var openDropdown = null;
        var openToggle = null;

        if (!modal || !body || !title) {
            return;
        }

        document.addEventListener('keydown', function (event) {
            if (modal.hidden || event.key !== 'Tab') {
                return;
            }

            var focusable = Array.from(modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), '
                + 'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ));

            if (focusable.length === 0) {
                event.preventDefault();
                modal.querySelector('[data-coach-modal-close]').focus();
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        var closeMenus = function () {
            document.querySelectorAll('[data-coach-actions-menu]').forEach(function (menu) {
                menu.hidden = true;
                menu.classList.remove('is-open');

                if (menu.dataset.coachActionsPlaceholder) {
                    var placeholder = document.querySelector(
                        '[data-coach-actions-placeholder="'
                        + menu.dataset.coachActionsPlaceholder
                        + '"]'
                    );

                    if (placeholder) {
                        placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
                        placeholder.remove();
                    }

                    delete menu.dataset.coachActionsPlaceholder;
                    menu.style.left = '';
                    menu.style.top = '';
                    menu.style.position = '';
                    menu.style.right = '';
                }
            });
            document.querySelectorAll('[data-coach-actions-toggle]').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });
            openDropdown = null;
            openToggle = null;
        };

        var positionDropdown = function (dropdown, toggle) {
            var rect = toggle.getBoundingClientRect();
            var dropdownWidth = dropdown.offsetWidth || 210;
            var dropdownHeight = dropdown.offsetHeight;
            var viewportWidth = document.documentElement.clientWidth;
            var left = rect.left + window.pageXOffset;
            var top = rect.bottom + window.pageYOffset + 4;
            var spaceBelow = window.innerHeight - rect.bottom;
            var spaceAbove = rect.top;

            if (spaceBelow < dropdownHeight + 8 && spaceAbove >= dropdownHeight + 8) {
                top = rect.top + window.pageYOffset - dropdownHeight - 4;
            }

            left = Math.min(
                Math.max(8 + window.pageXOffset, left + rect.width - dropdownWidth),
                Math.max(
                    8 + window.pageXOffset,
                    viewportWidth + window.pageXOffset - dropdownWidth - 8
                )
            );
            dropdown.style.left = left + 'px';
            dropdown.style.top = top + 'px';
        };

        var close = function () {
            var opener = activeTrigger;

            requestToken += 1;
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            body.replaceChildren();
            activeTrigger = null;
            if (opener) {
                opener.focus();
            }
        };

        var showLoading = function () {
            body.replaceChildren();
            if (loading) {
                loading.hidden = false;
                body.appendChild(loading);
            }
        };

        var showMessage = function (message) {
            body.replaceChildren();
            var messageElement = document.createElement('p');

            messageElement.className = 'mcc-empty-state';
            messageElement.textContent = message;
            body.appendChild(messageElement);
        };

        var showFeedback = function (form, type, message) {
            var feedback = form.querySelector('[data-coach-form-feedback]');

            if (!feedback) {
                return;
            }

            feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
            feedback.classList.add(type === 'success' ? 'mcc-alert-success' : 'mcc-alert-danger');
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');
        };

        var restoreForm = function (form) {
            var button = form.querySelector('button[type="submit"]');

            form.dataset.coachSubmitting = 'false';
            if (!button) {
                return;
            }

            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.dataset.coachOriginalLabel) {
                button.innerHTML = button.dataset.coachOriginalLabel;
                delete button.dataset.coachOriginalLabel;
            }
        };

        var enhanceForm = function (form) {
            var actionRow = form.querySelector('.col-12.text-right');
            var feedback = document.createElement('div');

            feedback.className = 'mcc-player-form-feedback mcc-coach-form-feedback';
            feedback.setAttribute('data-coach-form-feedback', '');
            feedback.hidden = true;
            if (actionRow) {
                actionRow.parentNode.insertBefore(feedback, actionRow);
            } else {
                form.appendChild(feedback);
            }

            form.addEventListener('submit', function (event) {
                var button = form.querySelector('button[type="submit"]');

                event.preventDefault();
                if (!button || form.dataset.coachSubmitting === 'true') {
                    return;
                }

                form.dataset.coachSubmitting = 'true';
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
                button.dataset.coachOriginalLabel = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

                fetch(form.action, {
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {Accept: 'text/html'},
                    method: 'POST',
                })
                    .then(function (response) {
                        return response.text().then(function (html) {
                            return {html: html, response: response};
                        });
                    })
                    .then(function (result) {
                        var parsed = new DOMParser().parseFromString(result.html, 'text/html');
                        var error = parsed.querySelector('.mcc-alert-danger');
                        var responseUrl = result.response.url || '';

                        if (!result.response.ok || error || responseUrl.endsWith('/edit')) {
                            restoreForm(form);
                            showFeedback(
                                form,
                                'error',
                                error
                                    ? error.textContent.trim()
                                    : 'The coach profile could not be saved.'
                            );
                            return;
                        }

                        showFeedback(form, 'success', 'Coach profile updated successfully.');
                        window.setTimeout(function () {
                            close();
                            window.location.reload();
                        }, 700);
                    })
                    .catch(function () {
                        restoreForm(form);
                        showFeedback(
                            form,
                            'error',
                            'The coach profile could not be saved. Please try again.'
                        );
                    });
            });
        };

        var loadEditForm = function (trigger, token) {
            var coachId = trigger.getAttribute('data-coach-id');
            var editUrl = trigger.getAttribute('data-coach-edit-url');

            fetch(editUrl, {
                credentials: 'same-origin',
                headers: {Accept: 'text/html'},
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Coach edit form could not be loaded.');
                    }

                    return response.text();
                })
                .then(function (html) {
                    if (token !== requestToken || modal.hidden) {
                        return;
                    }

                    var parsed = new DOMParser().parseFromString(html, 'text/html');
                    var form = Array.prototype.find.call(parsed.querySelectorAll('form[action]'), function (candidate) {
                        return (candidate.getAttribute('action') || '').endsWith('/coaches/' + coachId);
                    });

                    if (!form) {
                        throw new Error('Coach edit form could not be loaded.');
                    }

                    var fragment = document.importNode(form, true);

                    fragment.classList.add('mcc-coach-form');
                    body.replaceChildren(fragment);
                    enhanceForm(fragment);
                })
                .catch(function (error) {
                    if (token === requestToken && !modal.hidden) {
                        showMessage(error.message || 'Coach edit form could not be loaded.');
                    }
                });
        };

        document.addEventListener('click', function (event) {
            var target = event.target;
            var toggle;
            var menu;
            var dropdown;
            var trigger;

            if (!(target instanceof Element)) {
                return;
            }

            if (target.closest('[data-coach-modal-close]') || target === modal) {
                event.preventDefault();
                close();
                return;
            }

            toggle = target.closest('[data-coach-actions-toggle]');
            if (toggle) {
                event.preventDefault();
                menu = toggle.closest('[data-coach-actions]');
                dropdown = menu ? menu.querySelector('[data-coach-actions-menu]') : null;

                if (!dropdown) {
                    return;
                }

                var wasOpen = dropdown.classList.contains('is-open');

                closeMenus();
                if (!wasOpen) {
                    var placeholder = document.createElement('span');
                    var placeholderId = 'coach-actions-placeholder-' + Date.now();

                    placeholder.setAttribute('data-coach-actions-placeholder', placeholderId);
                    dropdown.parentNode.insertBefore(placeholder, dropdown);
                    document.body.appendChild(dropdown);
                    dropdown.dataset.coachActionsPlaceholder = placeholderId;
                    dropdown.hidden = false;
                    dropdown.classList.add('is-open');
                    dropdown.style.position = 'absolute';
                    dropdown.style.right = 'auto';
                    openDropdown = dropdown;
                    openToggle = toggle;
                    positionDropdown(dropdown, toggle);
                    toggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            trigger = target.closest('[data-coach-action]');
            if (trigger) {
                event.preventDefault();
                closeMenus();
                var action = trigger.getAttribute('data-coach-action');

                if (action === 'view') {
                    window.location.assign(trigger.getAttribute('data-coach-url'));
                    return;
                }

                activeTrigger = trigger;
                requestToken += 1;
                var token = requestToken;

                title.textContent = (trigger.getAttribute('data-coach-name') || 'Coach')
                    + ' - Edit coach';
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                var closeButton = modal.querySelector('[data-coach-modal-close]');
                if (closeButton) {
                    closeButton.focus();
                }
                showLoading();
                if (action === 'edit') {
                    loadEditForm(trigger, token);
                }
                return;
            }

            if (!target.closest('[data-coach-actions]')) {
                closeMenus();
            }
        });

        window.addEventListener('resize', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        });
        document.addEventListener('scroll', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        }, true);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (!modal.hidden) {
                    close();
                } else {
                    closeMenus();
                }
            }
        });
    }

    function syncSessionSkillSubmission(form) {
        var areaSelect = form.querySelector('[data-session-skill-area]');
        var optionSelect = form.querySelector('[data-session-skill-option]');

        if (! areaSelect || ! optionSelect) {
            return;
        }

        var selected = optionSelect.options[optionSelect.selectedIndex];
        if (! selected || selected.value === '' || selected.hidden
            || (selected.disabled && selected.dataset.skillRetired !== '1')
            || areaSelect.value === '' || selected.dataset.skillArea !== areaSelect.value) {
            optionSelect.value = '';
        }
        optionSelect.disabled = areaSelect.value === '';
        form.querySelectorAll('input[type="hidden"][name="skill_option_id"]').forEach(function (input) {
            input.value = optionSelect.value;
        });
        optionSelect.dispatchEvent(new Event('change', {bubbles: true}));
        optionSelect.dispatchEvent(new Event('bt-select-refresh'));
    }

    function initSessionWorkflowForms() {
        document.querySelectorAll('[data-session-submit-form]').forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            var feedback = form.querySelector('[data-session-response]');

            if (!button || !feedback || form.dataset.sessionWorkflowInitialized === 'true') {
                return;
            }

            form.dataset.sessionWorkflowInitialized = 'true';
            form.addEventListener('submit', function (event) {
                var originalLabel;

                if (form.dataset.sessionSubmitting === 'true') {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                form.dataset.sessionSubmitting = 'true';
                originalLabel = button.innerHTML;
                button.dataset.sessionWorkflowOriginalLabel = originalLabel;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                form.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
                syncSessionSkillSubmission(form);

                fetch(form.action, {
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {Accept: 'text/html'},
                    method: 'POST',
                }).then(function (response) {
                    return response.text().then(function (html) {
                        return {html: html, response: response};
                    });
                }).then(function (result) {
                    var parsed = new DOMParser().parseFromString(result.html, 'text/html');
                    var error = parsed.querySelector('.mcc-alert-danger, .message[role="alert"]');

                    if (!result.response.ok || error) {
                        throw new Error(error ? error.textContent.trim() : 'Session could not be saved. Please try again.');
                    }

                    feedback.classList.add('mcc-alert-success');
                    feedback.textContent = 'Session saved successfully.';
                    feedback.hidden = false;
                    feedback.setAttribute('role', 'status');
                    window.setTimeout(function () {
                        window.location.href = result.response.url || window.location.href;
                    }, 700);
                }).catch(function (error) {
                    form.dataset.sessionSubmitting = 'false';
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    form.removeAttribute('aria-busy');
                    button.innerHTML = button.dataset.sessionWorkflowOriginalLabel || originalLabel;
                    delete button.dataset.sessionWorkflowOriginalLabel;
                    feedback.classList.add('mcc-alert-danger');
                    feedback.textContent = error.message || 'Session could not be saved. Please try again.';
                    feedback.hidden = false;
                    feedback.setAttribute('role', 'alert');
                });
            });
        });
    }

    function initSessionOptionFilters() {
        document.querySelectorAll('[data-session-submit-form]').forEach(function (form) {
            var branch = form.querySelector('[data-session-branch]');
            if (!branch || form.dataset.sessionOptionsInitialized === 'true') {
                return;
            }

            form.dataset.sessionOptionsInitialized = 'true';
            var selects = form.querySelectorAll('[data-session-branch-options]');
            var coach = form.querySelector('[name="coach_user_id"]');
            var batch = form.querySelector('[name="batch_id"]');
            var ageGroup = form.querySelector('[name="age_group_id"]');
            var players = form.querySelector('[name="player_ids[]"]');
            var playerEmpty = form.querySelector('[data-session-player-empty]');
            var groupSize = form.querySelector('[data-session-group-size] strong');
            var playerDetails = form.querySelector('[data-session-player-details]');
            var filter = function () {
                var visiblePlayers = 0;
                selects.forEach(function (select) {
                    select.querySelectorAll('option[data-branch-id]').forEach(function (option) {
                        var visible = branch.value !== '' && option.dataset.branchId === branch.value;
                        if (visible && select === players) {
                            if (!coach || coach.value === '') {
                                visible = false;
                            } else if (!String(option.dataset.coachIds || '')
                                .split(',')
                                .map(function (value) { return value.trim(); })
                                .includes(coach.value)) {
                                visible = false;
                            } else if (batch
                                && batch.value !== ''
                                && option.dataset.playerBatch !== batch.value) {
                                visible = false;
                            } else if (ageGroup
                                && ageGroup.value !== ''
                                && option.dataset.playerAgeGroup !== ageGroup.value) {
                                visible = false;
                            }
                        }
                        option.hidden = !visible;
                        option.disabled = !visible;
                        if (!visible && option.selected) {
                            option.selected = false;
                        }
                        if (select === players && visible) {
                            visiblePlayers += 1;
                        }
                    });
                });
                if (playerEmpty) {
                    if (!players) {
                        playerEmpty.hidden = true;
                    } else {
                        playerEmpty.hidden = branch.value !== ''
                            && coach
                            && coach.value !== ''
                            && visiblePlayers > 0;
                        playerEmpty.textContent = branch.value === ''
                            ? 'Select a branch to load available Players.'
                            : !coach || coach.value === ''
                                ? 'Select a Coach to load authorized Players.'
                                : (batch && batch.value !== '') || (ageGroup && ageGroup.value !== '')
                                    ? 'No active Players match the selected Session Category for this Coach in this branch.'
                                    : 'No active Players are assigned to this Coach in this branch.';
                    }
                }
                if (players) {
                    players.disabled = branch.value === '' || !coach || coach.value === '';
                }
                if (groupSize && players) {
                    var selectedCount = Array.prototype.filter.call(players.selectedOptions, function (option) {
                        return !option.hidden && !option.disabled;
                    }).length;
                    groupSize.textContent = selectedCount + (selectedCount === 1 ? ' player' : ' players');
                }
                if (playerDetails && players) {
                    var selectedOptions = Array.prototype.filter.call(players.selectedOptions, function (option) {
                        return !option.hidden && !option.disabled;
                    });
                    playerDetails.replaceChildren();
                    if (selectedOptions.length === 0) {
                        var emptyDetails = document.createElement('p');
                        emptyDetails.className = 'mcc-form-help';
                        emptyDetails.textContent = 'Select a Player to view their branch and program details.';
                        playerDetails.appendChild(emptyDetails);
                    } else {
                        selectedOptions.forEach(function (option) {
                            var detail = document.createElement('div');
                            detail.className = 'mcc-session-player-detail';
                            var name = document.createElement('strong');
                            name.textContent = option.dataset.playerName || option.textContent.trim();
                            detail.appendChild(name);
                            var values = [
                                ['Player ID', option.value],
                                ['Branch', option.dataset.playerBranch || 'Not available'],
                            ];
                            if (option.dataset.playerBatch) {
                                values.push(['Batch', option.dataset.playerBatch]);
                            }
                            if (option.dataset.playerProgram) {
                                values.push(['Program', option.dataset.playerProgram]);
                            }
                            values.forEach(function (value) {
                                var item = document.createElement('span');
                                item.textContent = value[0] + ': ' + value[1];
                                detail.appendChild(item);
                            });
                            playerDetails.appendChild(detail);
                        });
                    }
                }
                refreshBtSelects(form);
            };

            branch.addEventListener('change', filter);
            if (coach) {
                coach.addEventListener('change', filter);
            }
            if (batch) {
                batch.addEventListener('change', filter);
            }
            if (ageGroup) {
                ageGroup.addEventListener('change', filter);
            }
            if (players) {
                players.addEventListener('change', filter);
            }
            filter();
        });
    }

    function initSessionSkillSelectors() {
        document.querySelectorAll('[data-session-skill-area]').forEach(function (areaSelect) {
            var form = areaSelect.form;
            var optionSelect = form ? form.querySelector('[data-session-skill-option]') : null;
            if (!optionSelect || optionSelect.dataset.sessionSkillInitialized === 'true') {
                return;
            }

            optionSelect.dataset.sessionSkillInitialized = 'true';
            var previousArea = areaSelect.value;
            var sync = function (clearStaleSelection) {
                var normalizeArea = function (value) {
                    return String(value || '')
                        .trim()
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
                };
                var area = normalizeArea(areaSelect.value);
                optionSelect.disabled = area === '';
                if (clearStaleSelection && area !== previousArea) {
                    optionSelect.value = '';
                }
                var matchingOptions = 0;
                optionSelect.querySelectorAll('option[data-skill-area]').forEach(function (option) {
                    var optionArea = normalizeArea(option.dataset.skillArea);
                    var visible = area !== '' && optionArea === area;
                    var retired = option.dataset.skillRetired === '1';
                    var selected = option.value !== '' && option.value === optionSelect.value;

                    if (retired && !selected) {
                        visible = false;
                    }
                    option.hidden = !visible;
                    option.style.display = visible ? '' : 'none';
                    option.disabled = !visible && !selected;
                    if (visible && !retired) {
                        matchingOptions += 1;
                    }
                });
                if (area !== '' && matchingOptions === 0) {
                    optionSelect.querySelectorAll('option[data-skill-area]').forEach(function (option) {
                        if (option.dataset.skillRetired !== '1') {
                            option.hidden = false;
                            option.style.display = '';
                            option.disabled = false;
                        }
                    });
                }
                var selected = optionSelect.options[optionSelect.selectedIndex];
                if (!selected || selected.value === '' || selected.hidden
                    || (selected.disabled && selected.dataset.skillRetired !== '1')
                    || area === '' || normalizeArea(selected.dataset.skillArea) !== area) {
                    optionSelect.value = '';
                }
                previousArea = area;
                optionSelect.dispatchEvent(new Event('bt-select-refresh'));
            };

            areaSelect.addEventListener('change', function () {
                sync(true);
                optionSelect.dispatchEvent(new Event('change', {bubbles: true}));
                optionSelect.dispatchEvent(new Event('bt-select-refresh'));
            });
            sync(false);

            var formSubmitKey = 'sessionSkillSubmitInitialized';
            if (form && form.dataset[formSubmitKey] !== 'true') {
                form.dataset[formSubmitKey] = 'true';
                form.addEventListener('submit', function () {
                    sync(true);
                    syncSessionSkillSubmission(form);
                });
            }
        });
    }

    function initSessionAvailability(root) {
        var scope = root || document;

        scope.querySelectorAll('[data-session-availability]').forEach(function (panel) {
            if (panel.dataset.sessionAvailabilityInitialized === 'true') {
                return;
            }

            var form = panel.closest('form') || document;
            var branch = form.querySelector('[data-session-branch]');
            var coach = form.querySelector('[name="coach_user_id"]');
            var date = form.querySelector('[name="starts_at"]');
            var message = panel.querySelector('[data-session-availability-message]');
            var link = panel.querySelector('[data-session-availability-link]');
            var request = null;

            if (!branch || !coach || !date || !message) {
                return;
            }

            panel.dataset.sessionAvailabilityInitialized = 'true';
            var refresh = function () {
                var coachId = coach.value;
                var branchId = branch.value;
                var dateValue = date.value;

                if (request) {
                    request.abort();
                    request = null;
                }
                if (link) {
                    link.hidden = true;
                }
                if (!coachId || !branchId || !/^\d{2}-\d{2}-\d{4}$/.test(dateValue)) {
                    message.textContent = 'Select a Coach and date to view configured availability.';
                    return;
                }

                var query = new URLSearchParams({
                    branch_id: branchId,
                    coach_user_id: coachId,
                    date: dateValue,
                });
                request = new AbortController();
                message.textContent = 'Loading Coach availability...';
                fetch(panel.dataset.sessionAvailabilityUrl + '?' + query.toString(), {
                    credentials: 'same-origin',
                    headers: {Accept: 'application/json'},
                    signal: request.signal,
                }).then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.error || 'Coach availability could not be loaded.');
                        }
                        return data;
                    });
                }).then(function (data) {
                    var windows = (data.windows || []).map(function (window) {
                        return window.start + '–' + window.end;
                    });
                    if (data.on_leave) {
                        message.textContent = 'This Coach has approved leave for the selected date.';
                    } else if (windows.length === 0) {
                        message.textContent = 'This Coach has no configured availability for the selected date.';
                    } else {
                        message.textContent = 'Configured availability: ' + windows.join(', ') + '.';
                    }
                    if (link && data.manage_url) {
                        link.href = data.manage_url;
                        link.hidden = false;
                    }
                }).catch(function (error) {
                    if (error.name !== 'AbortError') {
                        message.textContent = error.message || 'Coach availability could not be loaded.';
                    }
                });
            };

            coach.addEventListener('change', refresh);
            branch.addEventListener('change', refresh);
            date.addEventListener('change', refresh);
            date.addEventListener('input', refresh);
            refresh();
        });
    }

    function initSessionActions() {
        var modal = document.querySelector('[data-session-modal]');
        var body = modal ? modal.querySelector('[data-session-modal-body]') : null;
        var title = modal ? modal.querySelector('[data-session-modal-title]') : null;
        var activeTrigger = null;
        var openDropdown = null;
        var openToggle = null;

        var positionDropdown = function (dropdown, toggle) {
            var rect = toggle.getBoundingClientRect();
            var dropdownWidth = dropdown.offsetWidth || 210;
            var viewportWidth = document.documentElement.clientWidth;
            var left = rect.left + window.pageXOffset;
            var top = rect.bottom + window.pageYOffset + 4;

            left = Math.min(
                Math.max(8 + window.pageXOffset, left + rect.width - dropdownWidth),
                Math.max(8 + window.pageXOffset, viewportWidth + window.pageXOffset - dropdownWidth - 8)
            );
            dropdown.style.left = left + 'px';
            dropdown.style.top = top + 'px';
        };

        var closeMenus = function () {
            document.querySelectorAll('[data-session-actions-menu]').forEach(function (menu) {
                menu.hidden = true;
                menu.classList.remove('is-open');
                if (menu.dataset.sessionActionsPlaceholder) {
                    var placeholder = document.querySelector(
                        '[data-session-actions-placeholder="'
                        + menu.dataset.sessionActionsPlaceholder
                        + '"]'
                    );
                    if (placeholder) {
                        placeholder.parentNode.insertBefore(menu, placeholder.nextSibling);
                        placeholder.remove();
                    }
                    delete menu.dataset.sessionActionsPlaceholder;
                    menu.style.left = '';
                    menu.style.top = '';
                    menu.style.position = '';
                    menu.style.zIndex = '';
                }
            });
            document.querySelectorAll('[data-session-actions-toggle]').forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            });
            openDropdown = null;
            openToggle = null;
        };

        var close = function () {
            var opener = activeTrigger;

            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            body.replaceChildren();
            activeTrigger = null;
            if (opener) {
                opener.focus();
            }
        };

        var showLoading = function () {
            body.innerHTML = '<div class="mcc-session-modal-loading"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading...</div>';
        };

        var showFeedback = function (form, type, message) {
            var feedback = form.querySelector('[data-session-response]');
            if (!feedback) {
                return;
            }
            feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
            feedback.classList.add(type === 'success' ? 'mcc-alert-success' : 'mcc-alert-danger');
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');
        };

        var saveForm = function (form, event) {
            var button = form.querySelector('button[type="submit"]');
            var feedback = form.querySelector('[data-session-response]');

            if (!button || form.dataset.sessionSubmitting === 'true') {
                event.preventDefault();
                return;
            }
            event.preventDefault();
            form.dataset.sessionSubmitting = 'true';
            button.dataset.sessionOriginalLabel = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
            }
            fetch(form.action, {
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {Accept: 'text/html'},
                method: 'POST',
            }).then(function (response) {
                return response.text().then(function (html) {
                    return {html: html, response: response};
                });
            }).then(function (result) {
                var parsed = new DOMParser().parseFromString(result.html, 'text/html');
                var error = parsed.querySelector('.message[role="alert"], .mcc-alert-danger, .mcc-field-error');

                if (result.response.status === 409) {
                    throw new Error('This session changed while you were editing. Reload the page and try again.');
                }
                if (!result.response.ok || error) {
                    var returnedForm = parsed.querySelector('[data-session-edit-form]');
                    if (returnedForm) {
                        body.replaceChildren(document.importNode(returnedForm, true));
                        form = body.querySelector('[data-session-edit-form]');
                        if (form) {
                            initDatePickers(form);
                            initBtSelect(form);
                            initSessionSkillSelectors();
                            initSessionOptionFilters();
                            initSessionAvailability(body);
                        }
                    }
                    throw new Error(error ? error.textContent.trim() : 'Session could not be saved. Please try again.');
                }
                showFeedback(form, 'success', 'Session updated successfully.');
                window.setTimeout(function () {
                    close();
                    window.location.reload();
                }, 700);
            }).catch(function (error) {
                form.dataset.sessionSubmitting = 'false';
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.innerHTML = button.dataset.sessionOriginalLabel;
                delete button.dataset.sessionOriginalLabel;
                showFeedback(form, 'error', error.message || 'Session could not be saved. Please try again.');
            });
        };

        document.addEventListener('click', function (event) {
            var target = event.target;
            var toggle;
            var menu;
            var dropdown;
            var trigger;

            if (!(target instanceof Element)) {
                return;
            }
            if (target.closest('[data-session-modal-close]') || target === modal) {
                event.preventDefault();
                close();
                return;
            }
            toggle = target.closest('[data-session-actions-toggle]');
            if (toggle) {
                event.preventDefault();
                menu = toggle.closest('[data-session-actions]');
                dropdown = menu ? menu.querySelector('[data-session-actions-menu]') : null;
                if (!dropdown) {
                    return;
                }
                var wasOpen = dropdown.classList.contains('is-open');
                closeMenus();
                if (!wasOpen) {
                    var placeholder = document.createElement('span');
                    var placeholderId = 'session-actions-placeholder-' + Date.now();
                    placeholder.setAttribute('data-session-actions-placeholder', placeholderId);
                    dropdown.parentNode.insertBefore(placeholder, dropdown);
                    document.body.appendChild(dropdown);
                    dropdown.dataset.sessionActionsPlaceholder = placeholderId;
                    dropdown.hidden = false;
                    dropdown.classList.add('is-open');
                    dropdown.style.position = 'absolute';
                    dropdown.style.zIndex = '999999';
                    openDropdown = dropdown;
                    openToggle = toggle;
                    positionDropdown(dropdown, toggle);
                    toggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }
            trigger = target.closest('[data-session-action="edit"]');
            if (trigger) {
                event.preventDefault();
                closeMenus();
                activeTrigger = trigger;
                title.textContent = trigger.getAttribute('data-session-name') + ' - Edit session';
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                var closeButton = modal.querySelector('[data-session-modal-close]');
                if (closeButton) {
                    closeButton.focus();
                }
                showLoading();
                fetch(trigger.getAttribute('data-session-edit-url'), {
                    credentials: 'same-origin',
                    headers: {Accept: 'text/html'},
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Session edit form could not be loaded.');
                    }
                    return response.text();
                }).then(function (html) {
                    if (modal.hidden || activeTrigger !== trigger) {
                        return;
                    }
                    var parsed = new DOMParser().parseFromString(html, 'text/html');
                    var form = parsed.querySelector('[data-session-edit-form]');

                    if (!form) {
                        throw new Error('Session edit form could not be loaded.');
                    }
                    body.replaceChildren(document.importNode(form, true));
                    form = body.querySelector('[data-session-edit-form]');
                    if (form) {
                        initDatePickers(form);
                        initBtSelect(form);
                        initSessionSkillSelectors();
                        initSessionOptionFilters();
                        initSessionAvailability(body);
                    }
                }).catch(function (error) {
                    if (!modal.hidden && activeTrigger === trigger) {
                        body.textContent = error.message || 'Session edit form could not be loaded.';
                    }
                });
                return;
            }
            if (!target.closest('[data-session-actions]')) {
                closeMenus();
            }
        });
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form instanceof HTMLFormElement && form.matches('[data-session-edit-form]')) {
                saveForm(form, event);
            }
        });
        window.addEventListener('resize', function () {
            if (openDropdown && openToggle) {
                positionDropdown(openDropdown, openToggle);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.hidden) {
                close();
            }
        });
    }

    function initDatePickers(root) {
        var container = root || document;

        container.querySelectorAll('[data-mcc-date-picker]').forEach(function (input) {
            if (input.dataset.mccDatePickerInitialized === 'true') {
                return;
            }

            input.dataset.mccDatePickerInitialized = 'true';
            var wrapper = document.createElement('span');

            wrapper.className = 'mcc-date-picker';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            var popup = document.createElement('div');
            var displayDateFormat = input.getAttribute('data-mcc-date-format') || 'd-m-Y';
            var timeInputId = input.getAttribute('data-mcc-date-picker-time-input');
            var timeInput = timeInputId ? document.getElementById(timeInputId) : null;
            var preserveTime = input.hasAttribute('data-mcc-date-picker-preserve-time') && !timeInput;

            if (timeInput && ! input.required) {
                var updateTimeRequirement = function () {
                    timeInput.required = input.value.trim() !== '';
                };

                input.addEventListener('input', updateTimeRequirement);
                input.addEventListener('change', updateTimeRequirement);
                updateTimeRequirement();
            }

            popup.className = 'mcc-date-picker-popup';
            popup.hidden = true;
            popup.setAttribute('role', 'dialog');
            popup.setAttribute('aria-label', 'Choose date');
            popup.setAttribute('data-mcc-date-picker-overlay', '');
            document.body.appendChild(popup);
            popup.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            var positionPopup = function () {
                if (popup.hidden) {
                    return;
                }

                var inputRect = input.getBoundingClientRect();
                var popupWidth = popup.offsetWidth || 250;
                var popupHeight = popup.offsetHeight || 250;
                var edge = 8;
                var left = Math.min(
                    Math.max(edge, inputRect.left),
                    Math.max(edge, window.innerWidth - popupWidth - edge)
                );
                var top = inputRect.bottom + 6;

                if (top + popupHeight > window.innerHeight - edge
                    && inputRect.top - popupHeight - 6 >= edge) {
                    top = inputRect.top - popupHeight - 6;
                }

                if (top + popupHeight > window.innerHeight - edge) {
                    top = Math.max(edge, window.innerHeight - popupHeight - edge);
                }

                popup.style.left = Math.round(left) + 'px';
                popup.style.top = Math.round(top) + 'px';
            };
            var closePicker = function (returnFocus) {
                popup.hidden = true;
                wrapper.classList.remove('is-open');

                if (returnFocus) {
                    input.focus();
                }
            };
            popup.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closePicker(true);
                }
            });

            var date = function () {
                var value = input.value;

                if (/^\d{4}-\d{2}-\d{2}(?:T.*)?$/.test(value)) {
                    return new Date(value.substring(0, 10) + 'T00:00:00');
                }

                if (/^\d{2}-\d{2}-\d{4}(?:T.*)?$/.test(value)) {
                    var parts = value.split('-');

                    return new Date(parts[2] + '-' + parts[1] + '-' + parts[0] + 'T00:00:00');
                }

                return new Date();
            };
            var viewDate = date();
            var timezoneMeta = document.querySelector('meta[name="mcc-timezone"]');
            var configuredTimezone = timezoneMeta ? timezoneMeta.getAttribute('content') : undefined;
            var todayParts = new Intl.DateTimeFormat('en-CA', {
                timeZone: configuredTimezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).formatToParts(new Date());
            var todayPart = {};
            todayParts.forEach(function (part) {
                todayPart[part.type] = part.value;
            });
            var today = new Date(
                Number(todayPart.year),
                Number(todayPart.month) - 1,
                Number(todayPart.day)
            );
            if (input.value.trim() === '') {
                viewDate = today;
            }
            today.setHours(0, 0, 0, 0);
            var formatDate = function (value) {
                var day = String(value.getDate()).padStart(2, '0');
                var month = String(value.getMonth() + 1).padStart(2, '0');
                var year = value.getFullYear();

                var formattedDate = displayDateFormat === 'd-m-Y'
                    ? day + '-' + month + '-' + year
                    : year + '-' + month + '-' + day;

                if (preserveTime) {
                    var time = input.value.match(/T(\d{2}:\d{2})(?::\d{2})?([+-]\d{2}:\d{2}|Z)?/);

                    return formattedDate + 'T' + (time ? time[1] + (time[2] || '') : '00:00');
                }

                return formattedDate;
            };

            if (/^\d{4}-\d{2}-\d{2}$/.test(input.value) && displayDateFormat === 'd-m-Y') {
                input.value = formatDate(date());
            }

            var parseConstraint = function (value) {
                if (!value) {
                    return null;
                }

                var match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (!match) {
                    match = value.match(/^(\d{2})-(\d{2})-(\d{4})/);
                    if (!match) {
                        return null;
                    }

                    return new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
                }

                return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            };
            var minimumDate = parseConstraint(input.getAttribute('min'));
            var maximumDate = parseConstraint(input.getAttribute('max'));
            var dateAllowed = function (value) {
                var candidate = new Date(value.getFullYear(), value.getMonth(), value.getDate());

                return (! minimumDate || candidate >= minimumDate)
                    && (! maximumDate || candidate <= maximumDate);
            };
            var monthAllowed = function (year, month) {
                var first = new Date(year, month, 1);
                var last = new Date(year, month + 1, 0);

                return dateAllowed(first) || dateAllowed(last)
                    || (minimumDate && minimumDate >= first && minimumDate <= last)
                    || (maximumDate && maximumDate >= first && maximumDate <= last);
            };
            var clampDay = function (year, month, day) {
                var candidate = new Date(year, month, Math.min(day, new Date(year, month + 1, 0).getDate()));

                if (minimumDate && candidate < minimumDate) {
                    return new Date(minimumDate);
                }
                if (maximumDate && candidate > maximumDate) {
                    return new Date(maximumDate);
                }

                return candidate;
            };
            var rangeStartYear = minimumDate ? minimumDate.getFullYear() : today.getFullYear() - 100;
            var rangeEndYear = maximumDate ? maximumDate.getFullYear() : today.getFullYear() + 100;
            var render = function () {
                var current = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
                var year = current.getFullYear();
                var month = current.getMonth();

                popup.innerHTML = '<div class="mcc-date-picker-head">'
                    + '<button type="button" data-prev aria-label="Previous month">'
                    + '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i>'
                    + '</button>'
                    + '<label class="mcc-date-picker-select-label">'
                    + '<span class="mcc-sr-only">Month</span>'
                    + '<select data-month aria-label="Select month"></select>'
                    + '</label>'
                    + '<label class="mcc-date-picker-select-label">'
                    + '<span class="mcc-sr-only">Year</span>'
                    + '<select data-year aria-label="Select year"></select>'
                    + '</label>'
                    + '<button type="button" data-next aria-label="Next month">'
                    + '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>'
                    + '</button>'
                    + '</div>'
                    + '<div class="mcc-date-picker-week" aria-hidden="true">'
                    + 'Su Mo Tu We Th Fr Sa'
                    + '</div>'
                    + '<div class="mcc-date-picker-days" role="grid" aria-label="Choose day"></div>';

                var monthSelect = popup.querySelector('[data-month]');
                var yearSelect = popup.querySelector('[data-year]');
                var monthNames = Array.from({length: 12}, function (_, index) {
                    return new Date(2000, index, 1).toLocaleString(undefined, {month: 'long'});
                });

                monthNames.forEach(function (name, index) {
                    var option = new Option(name, String(index));
                    option.disabled = ! monthAllowed(year, index);
                    monthSelect.add(option);
                });
                for (var optionYear = rangeStartYear; optionYear <= rangeEndYear; optionYear++) {
                    var yearOption = new Option(String(optionYear), String(optionYear));
                    yearOption.disabled = ! monthNames.some(function (_, index) {
                        return monthAllowed(optionYear, index);
                    });
                    yearSelect.add(yearOption);
                }
                monthSelect.value = String(month);
                yearSelect.value = String(year);

                var days = popup.querySelector('.mcc-date-picker-days');
                var first = new Date(year, month, 1).getDay();
                var total = new Date(year, month + 1, 0).getDate();

                for (var i = 0; i < first; i++) {
                    days.appendChild(document.createElement('span'));
                }

                for (var day = 1; day <= total; day++) {
                    var button = document.createElement('button');

                    button.type = 'button';
                    button.textContent = day;
                    var buttonDate = new Date(year, month, day);
                    var buttonDateValue = formatDate(buttonDate);
                    button.dataset.date = buttonDateValue;
                    button.setAttribute('aria-label', buttonDateValue);
                    button.setAttribute('role', 'gridcell');
                    button.disabled = ! dateAllowed(buttonDate);
                    if (buttonDate.toDateString() === today.toDateString()) {
                        button.classList.add('is-today');
                        button.setAttribute('aria-current', 'date');
                    }
                    var selectedDate = date();
                    if (buttonDate.toDateString() === selectedDate.toDateString()) {
                        button.classList.add('is-selected');
                        button.setAttribute('aria-pressed', 'true');
                    } else {
                        button.setAttribute('aria-pressed', 'false');
                    }
                    button.addEventListener('click', function () {
                        if (this.disabled) {
                            return;
                        }

                        input.value = this.dataset.date;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        viewDate = new Date(year, month, Number(this.textContent));
                        closePicker(true);
                    });
                    days.appendChild(button);
                }

                monthSelect.addEventListener('change', function () {
                    var selected = clampDay(year, Number(this.value), date().getDate());
                    viewDate = selected;
                    render();
                    popup.querySelector('[data-month]').focus();
                });
                yearSelect.addEventListener('change', function () {
                    var selected = clampDay(Number(this.value), month, date().getDate());
                    viewDate = selected;
                    render();
                    popup.querySelector('[data-year]').focus();
                });

                var previousButton = popup.querySelector('[data-prev]');
                var nextButton = popup.querySelector('[data-next]');
                var previousMonth = month === 0 ? 11 : month - 1;
                var previousYear = month === 0 ? year - 1 : year;
                var nextMonth = month === 11 ? 0 : month + 1;
                var nextYear = month === 11 ? year + 1 : year;
                previousButton.disabled = ! monthAllowed(previousYear, previousMonth);
                nextButton.disabled = ! monthAllowed(nextYear, nextMonth);
                previousButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    viewDate = new Date(year, month - 1, 1);
                    render();
                    popup.querySelector('[data-prev]').focus();
                });
                nextButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    viewDate = new Date(year, month + 1, 1);
                    render();
                    popup.querySelector('[data-next]').focus();
                });
            };

            var openPicker = function () {
                popup.hidden = !popup.hidden;
                wrapper.classList.toggle('is-open', !popup.hidden);

                if (!popup.hidden) {
                    viewDate = date();
                    render();
                    positionPopup();
                    popup.querySelector('[data-prev]').focus();
                }
            };

            window.addEventListener('resize', positionPopup);
            window.addEventListener('scroll', positionPopup, true);

            input.addEventListener('click', openPicker);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'Enter') {
                    event.preventDefault();
                    openPicker();
                }

                if (event.key === 'Escape') {
                    closePicker(false);
                }
            });
            if (timeInput && input.form) {
                input.form.addEventListener('submit', function () {
                    var dateValue = input.value.substring(0, 10);
                    var timeValue = timeInput.value || '00:00';
                    var dateTimeFormat = input.getAttribute('data-mcc-datetime-submit-format');

                    if (dateTimeFormat === 'Y-m-d' && /^\d{2}:\d{2}$/.test(timeValue)) {
                        timeValue += ':00';
                    }

                    if (/^\d{2}-\d{2}-\d{4}$/.test(dateValue)) {
                        var dateParts = dateValue.split('-');

                        dateValue = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
                    }

                    var separator = dateTimeFormat === 'Y-m-d' ? ' ' : 'T';
                    input.value = dateValue === '' ? '' : dateValue + separator + timeValue;
                });
            }
            if (input.form && input.getAttribute('data-mcc-date-submit-format') === 'Y-m-d') {
                input.form.addEventListener('submit', function () {
                    var dateValue = input.value.substring(0, 10);

                    if (/^\d{2}-\d{2}-\d{4}$/.test(dateValue)) {
                        var dateParts = dateValue.split('-');

                        input.value = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
                    }
                });
            }
            if (input.form && input.getAttribute('data-mcc-datetime-submit-format') === 'Y-m-d') {
                input.form.addEventListener('submit', function () {
                    var dateTimeValue = input.value;
                    var dateTimeMatch = dateTimeValue.match(/^(\d{2})-(\d{2})-(\d{4})(.*)$/);

                    if (dateTimeMatch) {
                        input.value = dateTimeMatch[3] + '-'
                            + dateTimeMatch[2] + '-'
                            + dateTimeMatch[1]
                            + dateTimeMatch[4];
                    }
                });
            }
            document.addEventListener('click', function (event) {
                if (!wrapper.contains(event.target) && !popup.contains(event.target)) {
                    closePicker(false);

                }
            });
        });
    }

    function initAttendanceBranchBatchFilter() {
        var branch = document.getElementById('session-branch');
        var batch = document.getElementById('session-batch');

        if (!branch || !batch || batch.dataset.attendanceBranchFilterInitialized === 'true') {
            return;
        }

        var batchesByBranch;

        try {
            batchesByBranch = JSON.parse(
                batch.getAttribute('data-mcc-attendance-batches-by-branch') || '{}'
            );
        } catch (error) {
            return;
        }

        batch.dataset.attendanceBranchFilterInitialized = 'true';

        branch.addEventListener('change', function () {
            var selectedBatch = batch.value;
            var branchBatches = batchesByBranch[branch.value] || {};

            batch.innerHTML = '<option value="">All authorized batches</option>';

            Object.keys(branchBatches).forEach(function (batchId) {
                var option = document.createElement('option');

                option.value = batchId;
                option.textContent = branchBatches[batchId];
                option.selected = batchId === selectedBatch;
                batch.appendChild(option);
            });

            if (! Object.prototype.hasOwnProperty.call(branchBatches, selectedBatch)) {
                batch.value = '';
            }
        });
    }

    function initGroundFormFeedback() {
        document.querySelectorAll('[data-ground-submit-feedback]').forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            var feedback = form.querySelector('[data-ground-form-feedback]');

            if (!button || !feedback || form.dataset.groundInitialized === 'true') {
                return;
            }

            form.dataset.groundInitialized = 'true';
            form.addEventListener('submit', function (event) {
                var originalLabel;

                event.preventDefault();
                if (form.dataset.groundSubmitting === 'true') {
                    return;
                }
                form.dataset.groundSubmitting = 'true';
                originalLabel = button.innerHTML;
                button.dataset.groundOriginalLabel = originalLabel;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                form.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');

                fetch(form.action, {
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {Accept: 'text/html'},
                    method: 'POST',
                }).then(function (response) {
                    return response.text().then(function (html) {
                        return {html: html, response: response};
                    });
                }).then(function (result) {
                    var parsed = new DOMParser().parseFromString(result.html, 'text/html');
                    var error = parsed.querySelector('.mcc-alert-danger');

                    if (!result.response.ok || error) {
                        throw new Error(error ? error.textContent.trim() : 'Ground could not be saved. Please try again.');
                    }
                    feedback.classList.add('mcc-alert-success');
                    feedback.textContent = 'Ground saved successfully.';
                    feedback.hidden = false;
                    feedback.setAttribute('role', 'status');
                    window.setTimeout(function () {
                        window.location.href = result.response.url || window.location.href;
                    }, 700);
                }).catch(function (error) {
                    form.dataset.groundSubmitting = 'false';
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    form.removeAttribute('aria-busy');
                    button.innerHTML = button.dataset.groundOriginalLabel || originalLabel;
                    delete button.dataset.groundOriginalLabel;
                    feedback.classList.add('mcc-alert-danger');
                    feedback.textContent = error.message || 'Ground could not be saved. Please try again.';
                    feedback.hidden = false;
                    feedback.setAttribute('role', 'alert');
                });
            });
        });
    }

    function initBtSelect(root) {
        var scope = root || document;

        scope.querySelectorAll('[data-bt-select]').forEach(function (select) {
            if (select.dataset.btSelectInitialized === 'true') {
                return;
            }

            select.dataset.btSelectInitialized = 'true';
            var wrapper = document.createElement('div');
            wrapper.className = 'mcc-bt-select';
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);
            select.classList.add('mcc-bt-select-native');

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'mcc-bt-select-toggle';
            toggle.setAttribute('aria-haspopup', 'listbox');
            toggle.setAttribute('aria-expanded', 'false');
            var controlId = select.id || 'mcc-bt-select-' + Math.random().toString(36).slice(2);
            var menuId = controlId + '-menu';
            var placeholder = select.getAttribute('data-bt-select-placeholder')
                || select.getAttribute('title')
                || 'Select options';
            toggle.setAttribute('aria-controls', menuId);
            toggle.setAttribute('aria-label', select.getAttribute('title') || 'Select options');
            toggle.disabled = select.disabled;
            toggle.textContent = placeholder;
            wrapper.appendChild(toggle);

            var panel = document.createElement('div');
            panel.className = 'mcc-bt-select-menu';
            panel.id = menuId;
            panel.hidden = true;
            panel.setAttribute('role', 'listbox');
            var portalSelect = select.matches('[data-session-skill-option]');
            var panelPlaceholder = null;
            var multiple = select.multiple;
            if (multiple) {
                panel.setAttribute('aria-multiselectable', 'true');
            }
            wrapper.appendChild(panel);

            var search = document.createElement('input');
            search.type = 'search';
            search.className = 'mcc-bt-select-search';
            search.placeholder = select.getAttribute('data-bt-select-search-placeholder') || 'Search options';
            search.setAttribute('aria-label', 'Search options');
            panel.appendChild(search);

            var actions = document.createElement('div');
            actions.className = 'mcc-bt-select-actions';
            var count = document.createElement('span');
            count.className = 'mcc-bt-select-count';
            var selectAll = document.createElement('button');
            selectAll.type = 'button';
            selectAll.className = 'mcc-bt-select-select-all';
            selectAll.textContent = 'Select all';
            selectAll.hidden = !multiple;
            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'mcc-bt-select-clear';
            clear.textContent = 'Clear';
            actions.appendChild(count);
            actions.appendChild(selectAll);
            actions.appendChild(clear);
            panel.appendChild(actions);

            var options = document.createElement('div');
            options.className = 'mcc-bt-select-options';
            panel.appendChild(options);

            var empty = document.createElement('div');
            empty.className = 'mcc-bt-select-empty';
            empty.hidden = true;
            empty.setAttribute('role', 'status');
            empty.textContent = 'No results found.';
            panel.appendChild(empty);

            var stackingParents = [
                wrapper,
                wrapper.closest('.mcc-form-field'),
                wrapper.closest('.mcc-admission-subgroup'),
                wrapper.closest('.mcc-admission-section'),
            ].filter(function (parent, index, parents) {
                return parent && parents.indexOf(parent) === index;
            });
            var setStackingState = function (open) {
                stackingParents.forEach(function (parent) {
                    parent.classList.toggle('mcc-bt-select-open', open);
                });
            };
            var positionPortalPanel = function () {
                if (! portalSelect || panel.hidden) {
                    return;
                }

                var rect = toggle.getBoundingClientRect();
                var viewportWidth = document.documentElement.clientWidth;
                var viewportHeight = document.documentElement.clientHeight;
                var width = Math.min(rect.width, viewportWidth - 16);
                var optionHeight = Math.max(120, Math.min(240, viewportHeight - 140));

                options.style.maxHeight = optionHeight + 'px';
                options.style.overflowY = 'auto';
                options.style.overflowX = 'hidden';
                panel.style.maxHeight = Math.max(180, viewportHeight - 16) + 'px';
                panel.style.overflow = 'hidden';

                var left = Math.min(
                    Math.max(8, rect.left),
                    Math.max(8, viewportWidth - width - 8)
                );
                var top = rect.bottom + 4;
                var panelHeight = panel.getBoundingClientRect().height;

                if (top + panelHeight > viewportHeight - 8 && rect.top > panelHeight + 4) {
                    top = rect.top - panelHeight - 4;
                }

                if (top + panelHeight > viewportHeight - 8) {
                    top = viewportHeight - panelHeight - 8;
                }

                panel.style.left = left + 'px';
                panel.style.top = Math.max(8, top) + 'px';
                panel.style.width = width + 'px';
            };
            var portalPanel = function () {
                if (! portalSelect || panelPlaceholder) {
                    return;
                }

                panelPlaceholder = document.createComment('bt-select-menu-placeholder');
                panel.parentNode.insertBefore(panelPlaceholder, panel);
                document.body.appendChild(panel);
                panel.classList.add('mcc-bt-select-menu-portal');
                panel.dataset.btSelectPortal = 'true';
            };
            var restorePanel = function () {
                if (! panelPlaceholder) {
                    return;
                }

                panelPlaceholder.parentNode.insertBefore(panel, panelPlaceholder);
                panelPlaceholder.remove();
                panelPlaceholder = null;
                panel.classList.remove('mcc-bt-select-menu-portal');
                delete panel.dataset.btSelectPortal;
                panel.style.left = '';
                panel.style.top = '';
                panel.style.width = '';
            };
            var close = function () {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
                setStackingState(false);
                restorePanel();
            };
            var sync = function () {
                toggle.disabled = select.disabled;
                options.innerHTML = '';
                var selected = Array.prototype.filter.call(select.selectedOptions, function (option) {
                    return option.value !== '';
                }).map(function (option) {
                    return option.value;
                });
                Array.prototype.forEach.call(select.options, function (option) {
                    if (option.disabled || option.hidden) {
                        return;
                    }
                    var label = document.createElement('label');
                    label.className = 'mcc-bt-select-option';
                    label.dataset.btSelectValue = option.value;
                    var searchText = option.getAttribute('data-search-text');
                    if (searchText) {
                        label.setAttribute('data-search-text', searchText);
                    }
                    var input = document.createElement('input');
                    input.type = multiple ? 'checkbox' : 'radio';
                    input.value = option.value;
                    input.setAttribute('aria-label', option.textContent.trim());
                    input.checked = selected.indexOf(option.value) !== -1;
                    if (input.checked) {
                        input.setAttribute('aria-checked', 'true');
                    }
                    input.disabled = option.disabled;
                    label.classList.toggle('is-selected', input.checked);
                    label.classList.toggle('selected', input.checked);
                    input.addEventListener('change', function () {
                        if (!multiple) {
                            Array.prototype.forEach.call(select.options, function (candidate) {
                                candidate.selected = candidate === option;
                            });
                        }
                        option.selected = input.checked;
                        if (input.checked) {
                            input.setAttribute('aria-checked', 'true');
                        } else {
                            input.removeAttribute('aria-checked');
                        }
                        label.classList.toggle('is-selected', input.checked);
                        label.classList.toggle('selected', input.checked);
                        select.dispatchEvent(new Event('change', {bubbles: true}));
                        sync();
                        if (!multiple) {
                            close();
                        }
                    });
                    input.addEventListener('keydown', function (event) {
                        var visibleInputs = Array.prototype.filter.call(
                            options.querySelectorAll('.mcc-bt-select-option input'),
                            function (candidate) {
                                return !candidate.closest('.mcc-bt-select-option').hidden;
                            }
                        );
                        var currentIndex = visibleInputs.indexOf(input);

                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            var offset = event.key === 'ArrowDown' ? 1 : -1;
                            var nextIndex = (currentIndex + offset + visibleInputs.length) % visibleInputs.length;
                            if (visibleInputs.length > 0) {
                                visibleInputs[nextIndex].focus();
                            }
                        } else if (event.key === 'Escape') {
                            event.preventDefault();
                            close();
                            toggle.focus();
                        }
                    });
                    var text = document.createElement('span');
                    text.textContent = option.textContent;
                    label.appendChild(input);
                    label.appendChild(text);
                    options.appendChild(label);
                });
                count.textContent = multiple
                    ? selected.length + ' selected'
                    : (selected.length ? '1 selected' : 'No selection');
                var selectedLabels = Array.prototype.map.call(select.selectedOptions, function (option) {
                    return option.textContent.trim();
                });
                toggle.textContent = selected.length
                    ? (multiple ? selectedLabels.join(', ') : selectedLabels[0])
                    : placeholder;
                toggle.title = selectedLabels.join(', ');
            };
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                var open = panel.hidden;
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    setStackingState(true);
                    portalPanel();
                    sync();
                    positionPortalPanel();
                    search.focus();
                }
            });
            selectAll.addEventListener('click', function (event) {
                event.preventDefault();
                options.querySelectorAll('.mcc-bt-select-option').forEach(function (label) {
                    var option = Array.prototype.find.call(select.options, function (candidate) {
                        return candidate.value === label.dataset.btSelectValue;
                    });
                    if (option && !option.disabled && !label.hidden) {
                        option.selected = true;
                    }
                });
                select.dispatchEvent(new Event('change', {bubbles: true}));
                sync();
            });
            clear.addEventListener('click', function (event) {
                event.preventDefault();
                Array.prototype.forEach.call(select.options, function (option) {
                    option.selected = option.value === '';
                });
                options.querySelectorAll('.mcc-bt-select-option').forEach(function (label) {
                    label.classList.remove('is-selected', 'selected', 'checked');
                    var input = label.querySelector('input');
                    if (input) {
                        input.checked = false;
                        input.removeAttribute('aria-checked');
                    }
                });
                select.dispatchEvent(new Event('change', {bubbles: true}));
                sync();
            });
            search.addEventListener('input', function () {
                var query = search.value.toLowerCase();
                var visibleCount = 0;
                options.querySelectorAll('.mcc-bt-select-option').forEach(function (option) {
                    var searchableText = (option.textContent + ' ' + (option.getAttribute('data-search-text') || '')).toLowerCase();
                    option.hidden = searchableText.indexOf(query) === -1;
                    if (!option.hidden) {
                        visibleCount += 1;
                    }
                });
                empty.hidden = visibleCount > 0;
            });
            search.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    var firstVisible = options.querySelector('.mcc-bt-select-option:not([hidden]) input');
                    if (firstVisible) {
                        firstVisible.focus();
                    }
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                    toggle.focus();
                }
            });
            select.addEventListener('change', sync);
            select.addEventListener('bt-select-refresh', sync);
            document.addEventListener('click', function (event) {
                if (!wrapper.contains(event.target) && !panel.contains(event.target)) {
                    close();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    close();
                }
            });
            if (portalSelect) {
                window.addEventListener('resize', positionPortalPanel);
                window.addEventListener('scroll', positionPortalPanel, true);
            }
            sync();
        });
    }

    function refreshBtSelects(root) {
        (root || document).querySelectorAll('[data-bt-select]').forEach(function (select) {
            select.dispatchEvent(new Event('bt-select-refresh'));
        });
    }

    function initAdmissionBranchScopedFields() {
        document.querySelectorAll('[data-admission-branch-control]').forEach(function (branch) {
            if (branch.dataset.admissionBranchFilterInitialized === 'true') {
                return;
            }

            branch.dataset.admissionBranchFilterInitialized = 'true';
            var form = branch.closest('form');
            if (!form) {
                return;
            }

            var filterOptions = function () {
                var coaches = form.querySelectorAll('#admission-batch-coach, #admission-personal-coach');
                if (!coaches.length) {
                    return;
                }

                Array.prototype.forEach.call(coaches, function (select) {
                    var branchId = String(branch.value || '').trim();
                    var selectedCoachStillVisible = false;

                    Array.prototype.forEach.call(select.options, function (option) {
                        var optionBranch = String(option.getAttribute('data-branch-id') || '').trim();
                        var visible = option.value === '' || (branchId !== '' && optionBranch === branchId);
                        option.hidden = !visible;
                        option.disabled = !visible;

                        if (!visible && option.selected) {
                            option.selected = false;
                        }

                        if (visible && option.selected && option.value !== '') {
                            selectedCoachStillVisible = true;
                        }
                    });

                    if (!selectedCoachStillVisible && select.value !== '') {
                        select.value = '';
                    }

                    select.dispatchEvent(new Event('bt-select-refresh'));
                });
            };

            branch.addEventListener('change', filterOptions);
            branch.addEventListener('input', filterOptions);
            filterOptions();
        });
    }

    function initAdmissionTrialRatings() {
        document.querySelectorAll('form[data-admission-five-sections]').forEach(function (form) {
            var lead = form.querySelector('[data-admission-lead-control]');
            var trial = form.querySelector('[data-admission-trial-control]');
            var ratings = form.querySelector('[data-admission-trial-ratings]');

            if (!lead || !trial || !ratings || trial.dataset.admissionTrialInitialized === 'true') {
                return;
            }

            trial.dataset.admissionTrialInitialized = 'true';
            var empty = ratings.querySelector('[data-admission-rating-empty]');

            var fields = [
                ['ability_overall', 'overall'],
                ['ability_batting', 'batting'],
                ['ability_bowling', 'bowling'],
                ['ability_wicketkeeping', 'wicketkeeping'],
                ['ability_fitness', 'fitness'],
            ];

            var applyTrialRatings = function (force, ratingValues) {
                var selectedOption = trial.options[trial.selectedIndex];
                var hasTrial = selectedOption && selectedOption.value !== '';

                fields.forEach(function (field) {
                    var value = ratingValues && Object.prototype.hasOwnProperty.call(ratingValues, field[0])
                        ? ratingValues[field[0]]
                        : selectedOption
                            ? selectedOption.getAttribute('data-rating-' + field[1]) || ''
                            : '';
                    value = value === null || value === undefined ? '' : String(value);
                    var control = ratings.querySelector('[data-admission-trial-rating="' + field[0] + '"]');
                    if (control && (force || control.value === '')) {
                        setRatingControlValue(control, value);
                    }

                });

                if (empty) {
                    empty.textContent = '';
                    empty.hidden = empty.textContent.trim() === '';
                }
            };

            var loadTrialRatings = function (force) {
                var selectedOption = trial.options[trial.selectedIndex];
                var trialId = selectedOption && selectedOption.value !== '' ? selectedOption.value : '';
                var ratingsUrl = trial.getAttribute('data-admission-trial-ratings-url');

                if (trialId === '' || !ratingsUrl) {
                    applyTrialRatings(force, null);
                    return;
                }

                fetch(ratingsUrl.replace('__TRIAL__', encodeURIComponent(trialId)), {
                    credentials: 'same-origin',
                    headers: {Accept: 'application/json'},
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Unable to load Trial ratings.');
                        }

                        return response.json();
                    })
                    .then(function (payload) {
                        if (String(payload?.ratings?.lead_id || '') !== String(lead.value)) {
                            throw new Error('Trial does not belong to the selected Lead.');
                        }

                        applyTrialRatings(force, payload.ratings || {});
                    })
                    .catch(function () {
                        applyTrialRatings(force, null);
                    });
            };

            var filterTrials = function () {
                var leadId = lead.value;
                var selectedIsValid = false;

                Array.prototype.forEach.call(trial.options, function (option) {
                    var optionLeadId = option.getAttribute('data-lead-id');
                    var visible = option.value === '' || leadId !== '' && optionLeadId === leadId;
                    option.hidden = !visible;
                    option.disabled = !visible;

                    if (visible && option.selected) {
                        selectedIsValid = true;
                    }
                });

                trial.disabled = leadId === '';
                if (!selectedIsValid) {
                    trial.value = '';
                }
                trial.dispatchEvent(new Event('bt-select-refresh'));
                loadTrialRatings(false);
            };

            lead.addEventListener('change', function () {
                trial.value = '';
                filterTrials();
            });
            trial.addEventListener('change', function () {
                loadTrialRatings(true);
            });
            filterTrials();
        });
    }

    function ratingNumber(value) {
        if (value === null || value === undefined) {
            return null;
        }

        var numericValue = String(value).split('-')[0].trim();

        if (numericValue === '') {
            return null;
        }

        var parsedValue = Number(numericValue);

        return Number.isFinite(parsedValue) ? parsedValue : null;
    }

    function setRatingControlValue(control, value) {
        var numericValue = ratingNumber(value);
        var matchingOption = Array.prototype.find.call(control.options, function (option) {
            return numericValue !== null
                && option.value !== ''
                && ratingNumber(option.value) === numericValue;
        });

        control.value = matchingOption ? matchingOption.value : '';
    }


    function initAdmissionCoachingFields() {
        document.querySelectorAll('[data-admission-coaching-type-control]').forEach(function (controlGroup) {
            if (controlGroup.dataset.admissionCoachingInitialized === 'true') {
                return;
            }

            controlGroup.dataset.admissionCoachingInitialized = 'true';
            var form = controlGroup.closest('form');
            if (!form) {
                return;
            }

            var sync = function () {
                var selected = Array.prototype.map.call(
                    controlGroup.querySelectorAll('input[type="checkbox"]:checked'),
                    function (input) {
                        return input.value;
                    }
                );

                var visibleFieldCount = 0;
                form.querySelectorAll('[data-admission-coaching-dependent]').forEach(function (field) {
                    var show = selected.indexOf(field.dataset.admissionCoachingDependent) !== -1;
                    var wrapper = field.closest('.mcc-form-field');
                    if (wrapper) {
                        wrapper.hidden = !show;
                    }

                    if (show) {
                        visibleFieldCount += 1;
                    }

                    field.disabled = !show;
                    if (!show) {
                        field.value = '';
                    }
                });

                var dependentFields = controlGroup.querySelector('[data-admission-coaching-dependent-fields]');
                if (dependentFields) {
                    dependentFields.hidden = visibleFieldCount === 0;
                }
            };

            controlGroup.addEventListener('change', sync);
            sync();
        });
    }

    function initAdmissionEliteCampFields() {
        initAdmissionCoachingFields();
    }

    function initAdmissionFeeTotal() {
        document.querySelectorAll('[data-admission-fee-total]').forEach(function (totalField) {
            if (totalField.dataset.admissionFeeTotalInitialized === 'true') {
                return;
            }

            var form = totalField.closest('form');
            if (!form) {
                return;
            }

            var coaching = form.querySelector('[name="training_fee"]');
            var hostel = form.querySelector('[name="hostel_charges"]');
            if (!coaching || !hostel) {
                return;
            }

            var taxableFee = form.querySelector('[data-admission-taxable-fee]');
            var taxableFeeManual = form.querySelector('[data-admission-taxable-fee-manual]');
            var taxRate = form.querySelector('[data-admission-tax-rate]');
            var taxAmount = form.querySelector('[data-admission-calculated-tax]');

            var cents = function (value) {
                var normalized = String(value || '').trim();
                if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) {
                    return 0;
                }

                var parts = normalized.split('.');
                return (parseInt(parts[0], 10) * 100) + parseInt((parts[1] || '').padEnd(2, '0'), 10);
            };
            var update = function () {
                var totalCents = cents(coaching.value) + cents(hostel.value);
                totalField.value = '₹' + new Intl.NumberFormat('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(totalCents / 100);
            };

            var updateTax = function () {
                var totalCents = cents(coaching.value) + cents(hostel.value);
                if (taxableFee && (!taxableFeeManual || taxableFeeManual.value !== '1')) {
                    taxableFee.value = (totalCents / 100).toFixed(2);
                }

                var taxableCents = taxableFee ? cents(taxableFee.value) : totalCents;
                var rate = taxRate ? Number.parseFloat(taxRate.value) : 0;
                var calculatedTaxCents = Number.isFinite(rate)
                    ? Math.round(taxableCents * rate / 100)
                    : 0;
                var formatMoney = function (value) {
                    return '₹' + new Intl.NumberFormat('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(value / 100);
                };

                if (taxAmount) {
                    taxAmount.value = formatMoney(calculatedTaxCents);
                }
            };

            if (taxableFee) {
                taxableFee.addEventListener('input', function () {
                    if (taxableFeeManual) {
                        taxableFeeManual.value = '1';
                    }
                    updateTax();
                });
                taxableFee.addEventListener('change', updateTax);
            }
            if (taxRate) {
                taxRate.addEventListener('input', updateTax);
                taxRate.addEventListener('change', updateTax);
            }

            coaching.addEventListener('input', update);
            coaching.addEventListener('input', updateTax);
            coaching.addEventListener('change', update);
            hostel.addEventListener('input', update);
            hostel.addEventListener('change', update);
            hostel.addEventListener('input', updateTax);
            hostel.addEventListener('change', updateTax);
            totalField.dataset.admissionFeeTotalInitialized = 'true';
            update();
            updateTax();
        });
    }

    function initAdmissionInstallmentSchedule() {
        document.querySelectorAll('[data-admission-installment-schedule]').forEach(function (schedule) {
            if (schedule.closest('[data-admission-test-schedule]')) {
                return;
            }

            if (schedule.dataset.admissionInstallmentInitialized === 'true') {
                return;
            }

            var form = schedule.closest('form');
            var prefix = schedule.getAttribute('data-admission-installment-prefix') || 'installment_';
            var plan = form ? form.querySelector('[data-admission-installment-plan][name="'
                + prefix + 'plan"]') : null;
            var rows = schedule.querySelector('[data-admission-installment-rows]');
            var addButton = schedule.querySelector('[data-admission-installment-add]');
            var emptyState = schedule.parentElement
                ? schedule.parentElement.querySelector('[data-admission-installment-empty]')
                : null;
            var isInstallmentValueInput = function (input) {
                return /(?:^|_)installment_[1-4](?:_date)?\[\]$/.test(input.name);
            };

            if (!form || !plan || !rows || !addButton) {
                return;
            }

            var clearInputs = function (row) {
                row.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                });
            };

            var nextYear = function () {
                var years = Array.prototype.map.call(
                    rows.querySelectorAll('[name="' + prefix + 'year[]"]'),
                    function (input) {
                        return Number(input.value) || 0;
                    }
                );

                return Math.max.apply(null, years.concat([0])) + 1;
            };

            var addRow = function (year) {
                var row = document.createElement('tr');
                var yearNumber = year || nextYear();
                row.setAttribute('data-admission-installment-row', '');

                var yearCell = document.createElement('td');
                yearCell.setAttribute('data-label', 'Year');
                var yearLabel = document.createElement('label');
                yearLabel.className = 'mcc-sr-only';
                yearLabel.textContent = 'Year';
                var yearInput = document.createElement('input');
                yearInput.className = 'form-control';
                yearInput.name = prefix + 'year[]';
                yearInput.type = 'number';
                yearInput.min = '1';
                yearInput.max = '99';
                yearInput.step = '1';
                yearInput.value = yearNumber;
                yearInput.required = true;
                yearLabel.htmlFor = 'admission-' + prefix + 'year-' + Date.now();
                yearInput.id = yearLabel.htmlFor;
                yearCell.appendChild(yearLabel);
                yearCell.appendChild(yearInput);
                row.appendChild(yearCell);

                [1, 2, 3, 4].forEach(function (number) {
                    var cell = document.createElement('td');
                    var suffix = number === 1 ? 'st' : (number === 2 ? 'nd' : (number === 3 ? 'rd' : 'th'));
                    cell.setAttribute('data-label', number + suffix + ' Instalment');

                    var createInputGroup = function (labelText, input, labelFor) {
                        var group = document.createElement('div');
                        group.className = 'input-group mcc-admission-installment-input-group';
                        var prepend = document.createElement('span');
                        prepend.className = 'input-group-prepend';
                        var text = document.createElement('span');
                        text.className = 'input-group-text';
                        text.textContent = labelText;
                        prepend.appendChild(text);
                        var label = document.createElement('label');
                        label.className = 'mcc-sr-only';
                        label.htmlFor = labelFor;
                        label.textContent = labelText;
                        group.appendChild(prepend);
                        group.appendChild(label);
                        group.appendChild(input);

                        return group;
                    };
                    var dateInput = document.createElement('input');
                    dateInput.className = 'form-control';
                    dateInput.name = prefix + number + '_date[]';
                    dateInput.type = 'text';
                    dateInput.inputMode = 'numeric';
                    dateInput.autocomplete = 'off';
                    dateInput.placeholder = 'DD-MM-YYYY';
                    dateInput.setAttribute('data-mcc-date-picker', '');
                    dateInput.setAttribute('data-mcc-date-picker-no-toggle', '');
                    dateInput.setAttribute('data-mcc-date-format', 'd-m-Y');
                    dateInput.setAttribute('data-mcc-date-submit-format', 'Y-m-d');
                    dateInput.id = 'admission-' + prefix + number + '-date-' + Date.now();
                    cell.appendChild(createInputGroup('Date', dateInput, dateInput.id));

                    var amountInput = document.createElement('input');
                    amountInput.className = 'form-control';
                    amountInput.name = prefix + number + '[]';
                    amountInput.type = 'number';
                    amountInput.min = '0';
                    amountInput.step = '0.01';
                    amountInput.inputMode = 'decimal';
                    amountInput.placeholder = '0.00';
                    amountInput.id = 'admission-' + prefix + number + '-amount-' + Date.now();
                    cell.appendChild(createInputGroup('Amount', amountInput, amountInput.id));
                    row.appendChild(cell);
                });

                var actionCell = document.createElement('td');
                actionCell.setAttribute('data-label', 'Actions');
                var removeButton = document.createElement('button');
                removeButton.className = 'mcc-button mcc-button-secondary';
                removeButton.type = 'button';
                removeButton.textContent = 'Remove';
                removeButton.setAttribute('data-admission-installment-remove', '');
                actionCell.appendChild(removeButton);
                row.appendChild(actionCell);
                rows.appendChild(row);
                initDatePickers(row);
            };

            var sync = function () {
                var visible = plan.value === 'Instalment Plan';
                schedule.hidden = !visible;
                if (emptyState) {
                    emptyState.hidden = visible;
                }
                schedule.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !visible;
                    input.required = visible && ! isInstallmentValueInput(input);
                    if (!visible) {
                        input.value = '';
                    }
                });
            };

            plan.addEventListener('change', sync);
            addButton.addEventListener('click', function () {
                addRow();
                sync();
            });
            rows.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-admission-installment-remove]');
                if (!removeButton) {
                    return;
                }

                var row = removeButton.closest('[data-admission-installment-row]');
                if (!row) {
                    return;
                }

                if (rows.querySelectorAll('[data-admission-installment-row]').length > 1) {
                    row.remove();
                } else {
                    clearInputs(row);
                }
            });
            schedule.dataset.admissionInstallmentInitialized = 'true';
            sync();
        });
    }

    function initAdmissionTestInstallmentSchedules() {
        var rowSequence = 0;

        document.querySelectorAll('[data-admission-test-schedule] [data-admission-installment-schedule]').forEach(function (schedule) {
            if (schedule.dataset.admissionTestInstallmentInitialized === 'true') {
                return;
            }

            var form = schedule.closest('form');
            var prefix = schedule.getAttribute('data-admission-installment-prefix') || 'installment_';
            var idsField = schedule.getAttribute('data-admission-installment-ids-field') || prefix + 'ids[]';
            var planField = schedule.getAttribute('data-admission-installment-plan-field') || prefix + 'plan';
            var installmentPlanValue = schedule.getAttribute('data-admission-installment-plan-value') || 'Instalment Plan';
            var plan = form ? form.querySelector('[data-admission-installment-plan][name="'
                + planField + '"]') : null;
            var rows = schedule.querySelector('[data-admission-installment-rows]');
            var addButton = schedule.querySelector('[data-admission-installment-add]');
            var emptyState = schedule.parentElement
                ? schedule.parentElement.querySelector('[data-admission-installment-empty]')
                : null;
            var savedRows = [];
            var isInstallmentValueInput = function (input) {
                return /(?:^|_)installment_[1-4](?:_date)?\[\]$/.test(input.name);
            };

            if (form && form.id === 'admission-test-fees-form'
                && form.dataset.admissionTestFeesSubmitBound !== 'true') {
                form.addEventListener('submit', function (event) {
                    if (form.dataset.admissionTestFeesSubmitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.admissionTestFeesSubmitting = 'true';
                    var submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.setAttribute('aria-disabled', 'true');
                    }
                });
                form.dataset.admissionTestFeesSubmitBound = 'true';
            }

            try {
                savedRows = JSON.parse(schedule.getAttribute('data-admission-test-saved-rows') || '[]');
            } catch (error) {
                savedRows = [];
            }

            if (!form || !plan || !rows || !addButton) {
                return;
            }

            var nextId = function (kind) {
                rowSequence += 1;
                return 'admission-test-' + prefix.replace(/_$/, '') + '-' + kind + '-' + rowSequence;
            };

            var createInput = function (name, type, value, id) {
                var input = document.createElement('input');
                input.className = 'form-control';
                input.id = id;
                input.name = name;
                input.type = type;
                input.value = value || '';
                input.required = ! isInstallmentValueInput(input);
                if (type === 'number') {
                    input.min = '0';
                    input.step = '0.01';
                    input.inputMode = 'decimal';
                    input.placeholder = '0.00';
                } else {
                    input.inputMode = 'numeric';
                    input.autocomplete = 'off';
                    input.placeholder = 'DD-MM-YYYY';
                    input.setAttribute('data-mcc-date-picker', '');
                    input.setAttribute('data-mcc-date-picker-no-toggle', '');
                    input.setAttribute('data-mcc-date-format', 'd-m-Y');
                    input.setAttribute('data-mcc-date-submit-format', 'Y-m-d');
                }
                return input;
            };

            var addRow = function (source) {
                var row = document.createElement('tr');
                var rowId = nextId('row');
                row.setAttribute('data-admission-installment-row', '');
                row.setAttribute('data-admission-test-row-id', rowId);

                var yearCell = document.createElement('td');
                var yearId = nextId('year');
                var yearLabel = document.createElement('label');
                yearLabel.className = 'mcc-sr-only';
                yearLabel.htmlFor = yearId;
                yearLabel.textContent = 'Year';
                var yearInput = createInput(prefix + 'year[]', 'number', source.year_number || '', yearId);
                yearInput.min = '1';
                yearInput.max = '99';
                yearInput.step = '1';
                var persistedIds = document.createElement('input');
                persistedIds.type = 'hidden';
                persistedIds.name = idsField;
                persistedIds.value = JSON.stringify(source.installment_ids || {});
                persistedIds.setAttribute('data-admission-installment-ids', '');
                yearCell.appendChild(yearLabel);
                yearCell.appendChild(persistedIds);
                yearCell.appendChild(yearInput);
                row.appendChild(yearCell);

                [1, 2, 3, 4].forEach(function (number) {
                    var cell = document.createElement('td');
                    var dateId = nextId('date-' + number);
                    var amountId = nextId('amount-' + number);
                    var dateInput = createInput(
                        prefix + number + '_date[]',
                        'text',
                        source['installment_' + number + '_date'] || '',
                        dateId,
                    );
                    var amountInput = createInput(
                        prefix + number + '[]',
                        'number',
                        source['installment_' + number] || '',
                        amountId,
                    );
                    var group = document.createElement('div');
                    group.className = 'input-group mcc-admission-installment-input-group';
                    group.appendChild(dateInput);
                    group.appendChild(amountInput);
                    cell.appendChild(group);
                    row.appendChild(cell);
                });

                var actionCell = document.createElement('td');
                var removeButton = document.createElement('button');
                removeButton.className = 'mcc-button mcc-button-secondary';
                removeButton.type = 'button';
                removeButton.setAttribute('data-admission-installment-remove', '');
                removeButton.setAttribute('data-admission-test-remove-row', rowId);
                removeButton.textContent = 'Remove';
                actionCell.appendChild(removeButton);
                row.appendChild(actionCell);
                rows.appendChild(row);
                initDatePickers(row);
            };

            var removeAllRows = function () {
                rows.querySelectorAll('[data-admission-installment-row]').forEach(function (row) {
                    row.remove();
                });
            };

            var setEnabled = function (enabled) {
                rows.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !enabled;
                    input.required = enabled && ! isInstallmentValueInput(input);
                });
            };

            var sync = function () {
                var installment = plan.value === installmentPlanValue;
                schedule.hidden = !installment;
                if (emptyState) {
                    emptyState.hidden = installment;
                }

                if (!installment) {
                    rows.querySelectorAll('input').forEach(function (input) {
                        input.value = '';
                    });
                    setEnabled(false);
                    removeAllRows();
                    return;
                }

                if (rows.querySelectorAll('[data-admission-installment-row]').length === 0) {
                    if (savedRows.length > 0) {
                        savedRows.forEach(addRow);
                    } else {
                        addRow({year_number: 1});
                    }
                }
                setEnabled(true);
            };

            plan.addEventListener('change', sync);
            addButton.addEventListener('click', function () {
                if (plan.value !== 'Instalment Plan') {
                    return;
                }
                addRow({year_number: rows.querySelectorAll('[name="' + prefix + 'year[]"]').length + 1});
                setEnabled(true);
            });
            rows.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-admission-test-remove-row]');
                if (!removeButton) {
                    return;
                }
                var row = removeButton.closest('[data-admission-installment-row]');
                if (row && rows.querySelectorAll('[data-admission-installment-row]').length > 1) {
                    var persistedIds = row.querySelector('[data-admission-installment-ids]');
                    var removedField = schedule.getAttribute('data-admission-removed-field');
                    if (persistedIds && removedField) {
                        try {
                            var rowPersistedIds = row.getAttribute('data-admission-installment-persisted-ids');
                            var ids = JSON.parse(rowPersistedIds || persistedIds.value || '{}');
                            var existingRemovedIds = Array.prototype.map.call(
                                form.querySelectorAll('input[name="' + removedField + '[]"]'),
                                function (input) {
                                    return String(input.value || '').trim();
                                }
                            );
                            Object.keys(ids).forEach(function (number) {
                                var persistedId = String(ids[number] || '').trim();
                                if (!persistedId || existingRemovedIds.indexOf(persistedId) !== -1) {
                                    return;
                                }
                                var removedInput = document.createElement('input');
                                removedInput.type = 'hidden';
                                removedInput.name = removedField + '[]';
                                removedInput.value = persistedId;
                                removedInput.setAttribute('data-admission-removed-installment-id', '');
                                form.appendChild(removedInput);
                                existingRemovedIds.push(persistedId);
                            });
                        } catch (error) {
                            return;
                        }
                    }
                    row.remove();
                }
            });

            schedule.dataset.admissionTestInstallmentInitialized = 'true';
            setEnabled(plan.value === installmentPlanValue);
            if (plan.value === installmentPlanValue) {
                if (rows.querySelectorAll('[data-admission-installment-row]').length === 0) {
                    sync();
                } else {
                    schedule.hidden = false;
                    if (emptyState) {
                        emptyState.hidden = true;
                    }
                }
            } else {
                schedule.hidden = true;
                if (emptyState) {
                    emptyState.hidden = false;
                }
            }
        });
    }

    function initAchievementAwardForm() {
        document.querySelectorAll('[data-achievement-award-form]').forEach(function (form) {
            if (form.dataset.achievementAwardInitialized === 'true') {
                return;
            }

            var branch = form.querySelector('[data-award-branch]');
            var recipientType = form.querySelector('[data-award-recipient-type]');
            var achievement = form.querySelector('[data-award-achievement-selector]');
            var achievementEmpty = form.querySelector('[data-award-achievement-empty]');
            var selectors = Array.prototype.slice.call(
                form.querySelectorAll('[data-award-recipient-selector]')
            );

            if (!branch || !recipientType || selectors.length === 0) {
                return;
            }

            form.dataset.achievementAwardInitialized = 'true';

            var syncAchievements = function () {
                if (!achievement) {
                    return;
                }

                var visibleCount = 0;
                Array.prototype.forEach.call(achievement.options, function (option) {
                    if (!option.dataset.branchId) {
                        return;
                    }

                    var isActiveBranch = branch.value !== ''
                        && option.dataset.branchId === branch.value;
                    option.hidden = !isActiveBranch;
                    option.disabled = !isActiveBranch;

                    if (!isActiveBranch) {
                        option.selected = false;
                    } else {
                        visibleCount += 1;
                    }
                });

                if (achievementEmpty) {
                    achievementEmpty.hidden = branch.value === '' || visibleCount > 0;
                }

                achievement.dispatchEvent(new Event('bt-select-refresh'));
            };

            var syncRecipients = function () {
                selectors.forEach(function (select) {
                    var isActiveType = select.dataset.recipientType === recipientType.value;
                    var field = select.closest('.mcc-form-field');

                    if (field) {
                        field.hidden = !isActiveType;
                    }

                    select.disabled = !isActiveType;
                    select.required = isActiveType;

                    Array.prototype.forEach.call(select.options, function (option) {
                        if (!option.dataset.branchId) {
                            return;
                        }

                        var isActiveBranch = !branch.value
                            || option.dataset.branchId === branch.value;
                        option.hidden = !isActiveBranch;
                        option.disabled = !isActiveBranch;

                        if (!isActiveBranch) {
                            option.selected = false;
                        }
                    });

                    select.dispatchEvent(new Event('bt-select-refresh'));
                });
            };

            branch.addEventListener('change', syncRecipients);
            branch.addEventListener('change', syncAchievements);
            recipientType.addEventListener('change', syncRecipients);
            syncAchievements();
            syncRecipients();
        });
    }

    function initMatchFormFeedback() {
        document.querySelectorAll('[data-match-submit-form]').forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            var feedback = form.querySelector('[data-match-response]');
            var branch = form.querySelector('[data-match-branch]');
            var squad = form.querySelector('[name="player_ids[]"]');
            var playingXi = form.querySelector('[data-match-xi]');

            if (!button || !feedback || form.dataset.matchInitialized === 'true') {
                return;
            }
            form.dataset.matchInitialized = 'true';
            var filterOptions = function () {
                form.querySelectorAll('[data-match-branch-option] option[data-branch-id]').forEach(function (option) {
                    var visible = !branch.value || option.dataset.branchId === branch.value;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (!visible) {
                        option.selected = false;
                    }
                });
                if (playingXi && squad) {
                    var selectedSquad = Array.prototype.map.call(
                        squad.selectedOptions,
                        function (option) { return option.value; }
                    );
                    playingXi.querySelectorAll('option[data-player-id]').forEach(function (option) {
                        var inSquad = selectedSquad.indexOf(option.value) !== -1;
                        option.hidden = !inSquad || option.disabled;
                        option.disabled = !inSquad || option.disabled;
                        if (!inSquad) {
                            option.selected = false;
                        }
                    });
                }
                refreshBtSelects(form);
            };
            if (branch) {
                branch.addEventListener('change', filterOptions);
                filterOptions();
                refreshBtSelects(form);
            }
            if (squad) {
                squad.addEventListener('change', filterOptions);
                filterOptions();
            }
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (form.dataset.matchSubmitting === 'true') {
                    return;
                }
                form.dataset.matchSubmitting = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                form.setAttribute('aria-busy', 'true');
                button.dataset.matchOriginalLabel = button.innerHTML;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
                feedback.hidden = true;
                fetch(form.action, {
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {Accept: 'text/html'},
                    method: 'POST',
                }).then(function (response) {
                    return response.text().then(function (html) {
                        var parsed = new DOMParser().parseFromString(html, 'text/html');
                        var error = parsed.querySelector('.mcc-alert-danger, .alert-danger');

                        if (!response.ok || error) {
                            throw new Error(error
                                ? error.textContent.trim()
                                : 'Match could not be saved. Please try again.');
                        }

                        return response;
                    });
                }).then(function (response) {
                    feedback.classList.add('mcc-alert-success');
                    feedback.textContent = 'Match saved successfully.';
                    feedback.hidden = false;
                    window.setTimeout(function () {
                        window.location.href = response.url || window.location.href;
                    }, 700);
                }).catch(function (error) {
                    form.dataset.matchSubmitting = 'false';
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    form.removeAttribute('aria-busy');
                    button.innerHTML = button.dataset.matchOriginalLabel;
                    feedback.classList.add('mcc-alert-danger');
                    feedback.textContent = error.message || 'Match could not be saved. Please try again.';
                    feedback.hidden = false;
                });
            });
        });
    }

    function initTournamentFormFeedback() {
        document.querySelectorAll('[data-tournament-submit-form]').forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            var feedback = form.querySelector('[data-tournament-response]');
            var branch = form.querySelector('[data-tournament-branch]');

            if (!button || !feedback || form.dataset.tournamentInitialized === 'true') {
                return;
            }
            form.dataset.tournamentInitialized = 'true';
            var filterOptions = function () {
                form.querySelectorAll(
                    '[data-tournament-branch-option] option[data-branch-id]'
                ).forEach(function (option) {
                    var visible = !branch.value || option.dataset.branchId === branch.value;

                    option.hidden = !visible;
                    option.disabled = !visible;

                    if (!visible) {
                        option.selected = false;
                    }
                });
            };

            if (branch) {
                branch.addEventListener('change', filterOptions);
                filterOptions();
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (form.dataset.tournamentSubmitting === 'true') {
                    return;
                }

                form.dataset.tournamentSubmitting = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                form.setAttribute('aria-busy', 'true');
                button.dataset.tournamentOriginalLabel = button.innerHTML;
                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

                fetch(form.action, {
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/html'
                    },
                    method: 'POST'
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Tournament could not be saved. Please try again.');
                        }

                        feedback.classList.add('mcc-alert-success');
                        feedback.textContent = 'Tournament saved successfully.';
                        feedback.hidden = false;

                        window.setTimeout(function () {
                            window.location.href = response.url || window.location.href;
                        }, 700);
                    })
                    .catch(function (error) {
                        form.dataset.tournamentSubmitting = 'false';
                        button.disabled = false;
                        button.removeAttribute('aria-busy');
                        form.removeAttribute('aria-busy');
                        button.innerHTML = button.dataset.tournamentOriginalLabel;
                        feedback.classList.add('mcc-alert-danger');
                        feedback.textContent = error.message || 'Tournament could not be saved. Please try again.';
                        feedback.hidden = false;
                    });
            });
        });
    }

    function initFixtureMatchVisibility() {
        document.querySelectorAll('[data-fixture-match-wrapper]').forEach(function (wrapper) {
            var form = wrapper.closest('form');
            var home = form ? form.querySelector('#fixture-home') : null;
            var away = form ? form.querySelector('#fixture-away') : null;
            var match = wrapper.querySelector('#fixture-match');
            var endpoint = form ? form.dataset.fixtureMatchOptionsUrl : '';

            if (!home || !away || !match || !endpoint) {
                return;
            }

            var sync = function () {
                var selected = match.value;
                match.innerHTML = '<option value="">Select Match</option>';
                if (home.value === '' || away.value === '' || home.value === away.value) {
                    return;
                }

                fetch(endpoint + '?team1=' + encodeURIComponent(home.value) + '&team2=' + encodeURIComponent(away.value), {
                    credentials: 'same-origin',
                    headers: {Accept: 'application/json'},
                })
                    .then(function (response) { return response.ok ? response.json() : {matches: []}; })
                    .then(function (payload) {
                        (payload.matches || []).forEach(function (item) {
                            var option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = 'Match #' + item.id + ' — ' + item.your_team + ' V/S ' + item.opponent + ' — ' + (item.starts_at_display || item.starts_at || '');
                            option.selected = String(item.id) === String(selected);
                            match.appendChild(option);
                        });
                    });
            };

            home.addEventListener('change', sync);
            away.addEventListener('change', sync);
            sync();
        });
    }

    function initAttendanceFormFeedback() {
        document.querySelectorAll(
            '.attendance-save-form, [data-attendance-bulk-form]'
        ).forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            var feedback = form.querySelector('[data-attendance-form-feedback]');

            if (!button || !feedback || form.dataset.attendanceInitialized === 'true') {
                return;
            }

            form.dataset.attendanceInitialized = 'true';

            var clearFeedback = function () {
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
                feedback.setAttribute('role', 'status');
            };

            var showFeedback = function (type, message) {
                feedback.classList.remove('mcc-alert-success', 'mcc-alert-danger');
                feedback.classList.add(
                    type === 'success' ? 'mcc-alert-success' : 'mcc-alert-danger'
                );
                feedback.textContent = message;
                feedback.hidden = false;
                feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');
            };

            var restore = function () {
                form.dataset.attendanceSubmitting = 'false';
                button.disabled = false;
                button.removeAttribute('aria-busy');

                if (button.dataset.attendanceOriginalLabel) {
                    button.innerHTML = button.dataset.attendanceOriginalLabel;
                    delete button.dataset.attendanceOriginalLabel;
                }
            };

            form.addEventListener('submit', function (event) {
                if (form.dataset.attendanceSubmitting === 'true') {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                form.dataset.attendanceSubmitting = 'true';
                clearFeedback();
                button.dataset.attendanceOriginalLabel = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

                var formData = new FormData(form);
                if (form.matches('[data-attendance-bulk-form]')) {
                    var selectedCount = 0;
                    document.querySelectorAll('[data-attendance-player]').forEach(function (select) {
                        if (select.value !== '') {
                            formData.append(
                                'statuses[' + select.dataset.attendancePlayer + ']',
                                select.value
                            );
                            selectedCount++;
                        }
                    });
                    if (selectedCount === 0) {
                        restore();
                        showFeedback('error', 'Select an attendance status.');
                        return;
                    }
                }

                fetch(form.action, {
                    body: formData,
                    credentials: 'same-origin',
                    headers: {Accept: 'text/html'},
                    method: 'POST',
                })
                    .then(function (response) {
                        return response.text().then(function (body) {
                            return {body: body, response: response};
                        });
                    })
                    .then(function (result) {
                        var parsed = new DOMParser().parseFromString(result.body, 'text/html');
                        var serverError = parsed.querySelector('.message[role="alert"], .mcc-alert-danger');

                        if (result.response.status === 409) {
                            restore();
                            showFeedback(
                                'error',
                                'This attendance record changed in another session. Reload the latest attendance data and try again.'
                            );
                            return;
                        }

                        if (!result.response.ok || serverError) {
                            restore();
                            showFeedback(
                                'error',
                                serverError
                                    ? serverError.textContent.trim()
                                    : 'Attendance could not be saved. Please try again.'
                            );
                            return;
                        }

                        showFeedback('success', 'Attendance saved successfully.');
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 700);
                    })
                    .catch(function () {
                        restore();
                        showFeedback('error', 'Attendance could not be saved. Please try again.');
                    });
            });
        });
    }

    function initDevelopmentForms() {
        document.querySelectorAll('[data-development-submit-form]').forEach(function (form) {
            var button = form.querySelector('button[type="submit"]');
            var branch = form.querySelector('[data-development-branch]');
            var player = form.querySelector('[data-development-player]');
            var coach = form.querySelector('[data-development-coach]');

            if (form.dataset.developmentInitialized === 'true') {
                return;
            }

            form.dataset.developmentInitialized = 'true';

            var filterOptions = function (select) {
                if (! branch || ! select) {
                    return;
                }

                var branchId = branch.value;
                select.querySelectorAll('option[data-branch-id]').forEach(function (option) {
                    var visible = ! branchId || option.dataset.branchId === branchId;
                    option.hidden = ! visible;
                    option.disabled = ! visible;

                    if (! visible && option.selected) {
                        select.value = '';
                    }
                });
            };

            if (branch) {
                branch.addEventListener('change', function () {
                    filterOptions(player);
                    filterOptions(coach);
                });
                filterOptions(player);
                filterOptions(coach);
            }

            form.addEventListener('submit', function () {
                if (! button || button.disabled) {
                    return;
                }

                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.dataset.developmentOriginalLabel = button.innerHTML;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
            });
        });
    }

    function initSessionFeedbackModal() {
        var modal = document.querySelector('[data-session-feedback-modal]');
        if (!modal || modal.dataset.initialized === 'true') {
            return;
        }

        var form = modal.querySelector('[data-session-feedback-form]');
        var player = modal.querySelector('[data-session-feedback-player]');
        var status = modal.querySelector('[data-session-feedback-status]');
        var session = modal.querySelector('[data-session-feedback-session]');
        var playerId = modal.querySelector('[data-session-feedback-player-id]');
        var feedbackId = modal.querySelector('[data-session-feedback-id]');
        var feedbackVersion = modal.querySelector('[data-session-feedback-version]');
        var returnTo = modal.querySelector('[data-session-feedback-return]');
        var rating = modal.querySelector('[name="rating"]');
        var reason = modal.querySelector('[name="reason"]');
        var reasonLabel = modal.querySelector('[data-session-feedback-reason-label]');
        var heading = modal.querySelector('h2');
        if (!form || !player || !status || !session || !playerId || !feedbackId || !feedbackVersion || !returnTo || !rating || !reason) {
            return;
        }

        var close = function () {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('mcc-modal-open');
        };

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-session-feedback-open], [data-attendance-feedback-open]');
            if (trigger) {
                form.action = trigger.getAttribute('data-feedback-action') || '';
                player.textContent = trigger.getAttribute('data-feedback-player-name') || 'Player';
                status.textContent = trigger.getAttribute('data-feedback-status') || 'Attendance recorded';
                session.value = trigger.getAttribute('data-feedback-session') || '';
                playerId.value = trigger.getAttribute('data-feedback-player') || '';
                feedbackId.value = trigger.getAttribute('data-feedback-id') || '';
                feedbackVersion.value = trigger.getAttribute('data-feedback-version') || '';
                returnTo.value = trigger.getAttribute('data-feedback-return') || '';
                rating.value = trigger.getAttribute('data-feedback-rating') || '5';
                reason.value = '';
                heading.textContent = feedbackId.value ? 'Edit remark' : 'Add remark';
                if (reasonLabel) {
                    reasonLabel.textContent = feedbackId.value ? 'Correction reason' : 'Remark';
                }
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('mcc-modal-open');
                reason.focus();
                return;
            }

            if (modal.hidden) {
                return;
            }

            if (event.target.closest('[data-session-feedback-close]') || event.target === modal) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                close();
            }
        });

        modal.dataset.initialized = 'true';
    }

    function initNotificationModal() {
        var modal = document.querySelector('[data-notification-modal]');
        var opener = document.querySelector('[data-notification-modal-open]');
        if (!modal || !opener || modal.dataset.initialized === 'true') {
            return;
        }

        var branch = modal.querySelector('[data-notification-branch]');
        var recipients = modal.querySelector('[name="recipient_user_ids[]"]');
        var close = function () {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('mcc-modal-open');
        };
        var filterRecipients = function () {
            if (!branch || !recipients) {
                return;
            }
            var branchId = branch.value;
            recipients.querySelectorAll('option[data-branch-id]').forEach(function (option) {
                var visible = !branchId || option.dataset.branchId === branchId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) {
                    option.selected = false;
                }
            });
            recipients.dispatchEvent(new Event('bt-select-refresh'));
        };

        opener.addEventListener('click', function () {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('mcc-modal-open');
            if (branch) {
                branch.focus();
            }
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.closest('[data-notification-modal-close]')) {
                close();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                close();
            }
        });
        if (branch) {
            branch.addEventListener('change', filterRecipients);
        }
        filterRecipients();
        modal.dataset.initialized = 'true';
    }

    function initPerformanceAccordion() {
        var items = document.querySelectorAll('.mcc-performance-accordion-item');

        if (!items.length) {
            return;
        }

        items.forEach(function (item) {
            if (item.dataset.performanceAccordionInitialized === 'true') {
                return;
            }

            var button = item.querySelector('.mcc-performance-accordion-trigger');
            var panelId = button ? button.getAttribute('aria-controls') : null;
            var panel = panelId ? document.getElementById(panelId) : null;

            if (!button || !panel) {
                return;
            }

            var setOpen = function (open) {
                items.forEach(function (otherItem) {
                    var otherButton = otherItem.querySelector('.mcc-performance-accordion-trigger');
                    var otherPanelId = otherButton ? otherButton.getAttribute('aria-controls') : null;
                    var otherPanel = otherPanelId ? document.getElementById(otherPanelId) : null;

                    if (!otherButton || !otherPanel) {
                        return;
                    }

                    var shouldOpen = open && otherItem === item;
                    otherButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                    otherPanel.hidden = !shouldOpen;
                    otherItem.classList.toggle('is-open', shouldOpen);
                });
            };

            button.addEventListener('click', function () {
                setOpen(button.getAttribute('aria-expanded') !== 'true');
            });
            item.dataset.performanceAccordionInitialized = 'true';
        });
    }

    function initPerformanceHelpTooltips() {
        document.querySelectorAll('[data-mcc-help-toggle]').forEach(function (trigger) {
            if (trigger.dataset.mccHelpInitialized === 'true') {
                return;
            }

            var tooltipId = trigger.getAttribute('aria-controls');
            var tooltip = tooltipId ? document.getElementById(tooltipId) : null;
            var wrapper = trigger.closest('.mcc-performance-help-wrap');
            var hovered = false;
            var focused = false;
            var expanded = false;

            if (!tooltip || !wrapper) {
                return;
            }

            var sync = function () {
                var visible = hovered || focused || expanded;
                tooltip.hidden = !visible;
                trigger.setAttribute('aria-expanded', visible ? 'true' : 'false');
            };

            trigger.addEventListener('mouseenter', function () {
                hovered = true;
                sync();
            });
            trigger.addEventListener('mouseleave', function () {
                hovered = false;
                sync();
            });
            trigger.addEventListener('focus', function () {
                focused = true;
                sync();
            });
            trigger.addEventListener('blur', function () {
                focused = false;
                sync();
            });
            trigger.addEventListener('click', function () {
                expanded = !expanded;
                sync();
            });
            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    expanded = false;
                    trigger.blur();
                    sync();
                }
            });
            trigger.dataset.mccHelpInitialized = 'true';
        });
    }

    function initPerformanceFormFilters() {
        document.querySelectorAll('[data-performance-branch]').forEach(function (branch) {
            var form = branch.form;
            var player = form ? form.querySelector('[data-performance-player]') : null;
            var session = form ? form.querySelector('[data-performance-session]') : null;
            if (!form || !player || form.dataset.performanceFiltersInitialized === 'true') {
                return;
            }

            form.dataset.performanceFiltersInitialized = 'true';
            var filterOptions = function (select) {
                if (!select) {
                    return;
                }
                select.querySelectorAll('option[data-branch-id]').forEach(function (option) {
                    var visible = branch.value !== '' && option.dataset.branchId === branch.value;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (!visible && option.selected) {
                        option.selected = false;
                    }
                });
            };

            var filter = function () {
                filterOptions(player);
                filterOptions(session);
            };
            branch.addEventListener('change', filter);
            filter();
        });
    }

    function initPerformanceMetricFields() {
        document.querySelectorAll('#performance-record-type').forEach(function (typeSelect) {
            if (typeSelect.dataset.performanceMetricsInitialized === 'true') {
                return;
            }

            var form = typeSelect.form;
            if (!form) {
                return;
            }

            var sync = function () {
                var selectedType = typeSelect.value;
                form.querySelectorAll('[data-performance-metric]').forEach(function (field) {
                    var types = (field.dataset.performanceTypes || '').split(',');
                    var visible = selectedType === 'all'
                        || (selectedType !== '' && types.indexOf(selectedType) !== -1);
                    field.hidden = !visible;

                    var input = field.querySelector('input');
                    if (input) {
                        input.disabled = !visible;
                    }
                });
            };

            typeSelect.addEventListener('change', sync);
            typeSelect.dataset.performanceMetricsInitialized = 'true';
            sync();
        });
    }

    function initAdmissionAccordions() {
        document.querySelectorAll('form[data-admission-five-sections]').forEach(function (form) {
            form.querySelectorAll('[data-admission-installment-section]').forEach(function (content) {
                var target = content.getAttribute('data-admission-installment-section');
                var slot = form.querySelector('[data-admission-section-slot="' + target + '"]');

                if (slot && content.parentElement !== slot) {
                    slot.appendChild(content);
                }
            });

            var subsections = Array.prototype.slice.call(
                form.querySelectorAll('[data-admission-subsection]')
            );

            subsections.sort(function (left, right) {
                var leftOrder = parseInt(left.getAttribute('data-admission-subsection-order') || '999', 10);
                var rightOrder = parseInt(right.getAttribute('data-admission-subsection-order') || '999', 10);

                return leftOrder - rightOrder;
            });

            subsections.forEach(function (subsection) {
                var group = subsection.getAttribute('data-admission-subsection-target')
                    || subsection.getAttribute('data-admission-subsection');
                var slot = form.querySelector('[data-admission-section-slot="' + group + '"]');

                if (!slot || subsection.parentElement === slot) {
                    return;
                }

                if (subsection.classList.contains('mcc-admission-subsection-field')) {
                    var key = subsection.getAttribute('data-admission-subsection') || group;
                    var generated = slot.querySelector('[data-admission-generated-subsection="' + key + '"]');

                    if (!generated) {
                        var headings = {
                            'personal-student': 'Student Information',
                            'personal-address': 'Address',
                            'cricket-additional': 'Additional Cricket Information',
                            'coaching-admission': 'Admission and Trial',
                        };
                        generated = document.createElement('div');
                        generated.className = 'mcc-admission-subgroup';
                        generated.setAttribute('data-admission-generated-subsection', key);
                        generated.innerHTML = '<h3>' + (headings[key] || 'Admission Details')
                            + '</h3><div class="mcc-admission-section-body"><div class="row mcc-admission-fields"></div></div>';
                        slot.appendChild(generated);
                    }

                    generated.querySelector('.mcc-admission-fields').appendChild(subsection);
                    return;
                }

                slot.appendChild(subsection);
            });
        });

        document.querySelectorAll('details[data-admission-section]').forEach(function (section) {
            if (section.dataset.admissionAccordionInitialized === 'true') {
                return;
            }

            var form = section.closest('form');
            var sectionKey = section.getAttribute('data-admission-section');
            var storageKey = form
                ? 'mcc.admission.sections.' + (form.getAttribute('action') || window.location.pathname)
                : '';
            var storedState = null;

            if (storageKey) {
                try {
                    storedState = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
                } catch (error) {
                    storedState = null;
                }
            }

            var hasError = section.querySelector('.mcc-field-error, .has-error') !== null;
            if (hasError || section.getAttribute('data-admission-error') === 'true') {
                section.open = true;
            } else if (storedState && Object.prototype.hasOwnProperty.call(storedState, sectionKey)) {
                section.open = storedState[sectionKey] === true;
            }

            section.addEventListener('toggle', function () {
                if (!storageKey || !form) {
                    return;
                }

                var state = {};
                form.querySelectorAll('details[data-admission-section]').forEach(function (item) {
                    state[item.getAttribute('data-admission-section')] = item.open;
                });

                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(state));
                } catch (error) {
                    // Storage is optional; the accordion remains fully usable without it.
                }
            });

            section.dataset.admissionAccordionInitialized = 'true';
        });

        document.querySelectorAll('form[data-admission-five-sections] details[data-admission-subsection]').forEach(function (subsection) {
            if (subsection.querySelector('.mcc-field-error, .has-error') !== null) {
                subsection.open = true;
            }
        });
    }

    function initAdmissionSignatureCanvases() {
        document.querySelectorAll('[data-mcc-signature-canvas]').forEach(function (canvas) {
            if (canvas.dataset.mccSignatureInitialized === 'true') {
                return;
            }

            var inputId = canvas.getAttribute('data-mcc-signature-input');
            var input = inputId ? document.getElementById(inputId) : null;
            var context = canvas.getContext('2d');
            var drawing = false;

            if (!input || !context) {
                return;
            }

            var resizeCanvas = function () {
                var rect = canvas.getBoundingClientRect();
                var width = Math.max(320, Math.round(rect.width || 600));
                var height = Math.max(120, Math.round(width * 0.3));
                var image = input.value && input.value.indexOf('data:image/') === 0 ? input.value : '';

                canvas.width = width;
                canvas.height = height;
                context.lineCap = 'round';
                context.lineJoin = 'round';
                context.lineWidth = 2;
                context.strokeStyle = '#1f2937';

                if (image) {
                    var saved = new Image();
                    saved.onload = function () {
                        context.drawImage(saved, 0, 0, canvas.width, canvas.height);
                    };
                    saved.src = image;
                }
            };

            var point = function (event) {
                var rect = canvas.getBoundingClientRect();
                var source = event.touches ? event.touches[0] : event;

                return {
                    x: (source.clientX - rect.left) * (canvas.width / rect.width),
                    y: (source.clientY - rect.top) * (canvas.height / rect.height),
                };
            };

            var save = function () {
                input.value = canvas.toDataURL('image/png');
            };

            var start = function (event) {
                event.preventDefault();
                var location = point(event);
                drawing = true;
                context.beginPath();
                context.moveTo(location.x, location.y);
            };

            var move = function (event) {
                if (!drawing) {
                    return;
                }

                event.preventDefault();
                var location = point(event);
                context.lineTo(location.x, location.y);
                context.stroke();
                save();
            };

            var stop = function () {
                if (drawing) {
                    save();
                }
                drawing = false;
            };

            var clear = function () {
                context.clearRect(0, 0, canvas.width, canvas.height);
                input.value = '';
            };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            canvas.addEventListener('mouseup', stop);
            canvas.addEventListener('mouseleave', stop);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', stop);

            document.querySelectorAll('[data-mcc-signature-clear="' + canvas.id + '"]').forEach(function (button) {
                button.addEventListener('click', clear);
            });
            resizeCanvas();
            canvas.dataset.mccSignatureInitialized = 'true';
        });
    }

    function init() {
        initSessionPlanActionMenus();
        initFeedbackPlayerSearch();
        document.querySelectorAll('[data-copy-student-test-link]').forEach(function (button) {
            button.addEventListener('click', function () {
                var group = button.closest('.input-group');
                var input = group ? group.querySelector('[data-student-test-link]') : null;
                if (!input) {
                    return;
                }

                input.select();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(function () {
                        button.textContent = 'Copied';
                    });
                    return;
                }

                document.execCommand('copy');
                button.textContent = 'Copied';
            });
        });
        initAdmissionTestWizard();
        initAdmissionTestPersonalDetailsAnchor();
        initSidebar();
        initPortalNavigation();
        initLeadDatePopovers();
        initFilters();
        initFollowUpScheduleToggle();
        initFollowUpActions();
        initLeadImportModal();
        initAdmissionActions();
        initStandaloneAdmissionActionMenus();
        initAdmissionStatusModal();
        initAdmissionTermsModal();
        initAdmissionFormFeedback();
        initDatePickers();
        initAttendanceBranchBatchFilter();
        initAdmissionAccordions();
        initAdmissionSignatureCanvases();
        document.querySelectorAll('[data-mcc-datetime-submit-format="Y-m-d"]').forEach(function (input) {
            if (!input.form) {
                return;
            }

            input.form.addEventListener('submit', function () {
                var dateTimeValue = input.value;
                var dateTimeMatch = dateTimeValue.match(/^(\d{2})-(\d{2})-(\d{4})(.*)$/);

                if (dateTimeMatch) {
                    input.value = dateTimeMatch[3] + '-'
                        + dateTimeMatch[2] + '-'
                        + dateTimeMatch[1]
                        + dateTimeMatch[4];
                }
            });
        });
        initPlayerFormFeedback();
        initPlayerListActions();
        initCoachListActions();
        initSessionWorkflowForms();
        initSessionOptionFilters();
        initSessionAvailability();
        initSessionActions();
        initGroundFormFeedback();
        initBtSelect();
        initSessionSkillSelectors();
        initAchievementAwardForm();
        initMatchFormFeedback();
        initTournamentFormFeedback();
        initFixtureMatchVisibility();
        initAttendanceFormFeedback();
        initDevelopmentForms();
        initSessionFeedbackModal();
        initNotificationModal();
        initPerformanceAccordion();
        initPerformanceHelpTooltips();
        initPerformanceFormFilters();
        initPerformanceMetricFields();
        initAdmissionBranchScopedFields();
        initAdmissionTrialRatings();
        initAdmissionEliteCampFields();
        initAdmissionFeeTotal();
        initAdmissionInstallmentSchedule();
        initAdmissionTestInstallmentSchedules();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
