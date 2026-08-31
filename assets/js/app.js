(function () {
    'use strict';

    var body = document.body;
    var pageContent = document.querySelector('[data-page-content]');
    var pageLoadingStartedAt = Date.now();
    var pageLoadingTimer = null;
    var pageLoadingMinimum = 350;
    var openButtons = document.querySelectorAll('[data-sidebar-open]');
    var closeButtons = document.querySelectorAll('[data-sidebar-close]');
    var expandButtons = document.querySelectorAll('[data-sidebar-expand]');
    var sidebar = document.querySelector('#sidebar');
    var sidebarNav = document.querySelector('.sidebar-nav');
    var alertButtons = document.querySelectorAll('[data-dismiss-alert]');
    var confirmForms = document.querySelectorAll('[data-confirm]');
    var logoutLinks = document.querySelectorAll('[data-logout]');
    var accountToggles = document.querySelectorAll('[data-account-toggle]');
    var accountMenus = document.querySelectorAll('[data-account-menu]');
    var accountCloseButtons = document.querySelectorAll('[data-account-close]');
    var printButtons = document.querySelectorAll('[data-print-page]');
    var passwordToggles = document.querySelectorAll('[data-password-toggle]');
    var roleSelects = document.querySelectorAll('[data-role-select]');
    var authLoginForm = document.querySelector('.auth-mode-login .auth-form');
    var authIdentifierInput = authLoginForm ? authLoginForm.querySelector('#identifier') : null;
    var authPasswordInput = authLoginForm ? authLoginForm.querySelector('#password') : null;
    var filterForms = document.querySelectorAll('form.toolbar-filters');
    var confirmDialog = document.querySelector('[data-confirm-dialog]');
    var confirmDialogPanel = confirmDialog ? confirmDialog.querySelector('.confirm-dialog') : null;
    var confirmDialogTitle = confirmDialog ? confirmDialog.querySelector('#confirm-dialog-title') : null;
    var confirmDialogMessage = confirmDialog ? confirmDialog.querySelector('#confirm-dialog-message') : null;
    var confirmDialogNote = confirmDialog ? confirmDialog.querySelector('.confirm-dialog-note-text') : null;
    var confirmDialogAccept = confirmDialog ? confirmDialog.querySelector('[data-confirm-accept]') : null;
    var confirmDialogCancelButtons = confirmDialog ? confirmDialog.querySelectorAll('[data-confirm-cancel]') : [];
    var confirmState = null;
    var sidebarStorageKey = 'ruang-sehat-sidebar-collapsed';
    var archiveSummaryGrid = document.querySelector('[data-archive-summary]');
    var skipPageLoader = body && body.getAttribute('data-skip-page-loader') === '1';
    var archiveTransitioning = false;
    var archiveTransitionStorageKey = 'ruang-sehat-archive-filter-transition';
    var sidebarScrollFrame = null;
    var sidebarScrollStorageKey = 'ruang-sehat-sidebar-scroll';

    function addPageMotionSequence(selector, className, delayProperty, initialDelay, step, limit) {
        if (!pageContent) {
            return;
        }

        var elements = pageContent.querySelectorAll(selector);
        Array.prototype.forEach.call(elements, function (element, index) {
            if (limit && index >= limit) {
                return;
            }

            element.classList.add(className);
            element.style.setProperty(delayProperty, (initialDelay + (index * step)) + 'ms');
        });
    }

    function addPageMotionChildren(parentSelector, initialDelay, step, limit) {
        if (!pageContent) {
            return;
        }

        var parents = pageContent.querySelectorAll(parentSelector);
        Array.prototype.forEach.call(parents, function (parent) {
            var childIndex = 0;
            Array.prototype.forEach.call(parent.children, function (element) {
                if (limit && childIndex >= limit) {
                    return;
                }

                element.classList.add('page-motion-micro');
                element.style.setProperty('--page-motion-micro-delay', (initialDelay + (childIndex * step)) + 'ms');
                childIndex += 1;
            });
        });
    }

    function activateShellMotion() {
        var topbar = document.querySelector('.topbar');
        if (!topbar) {
            return;
        }

        topbar.classList.add('page-motion-shell');
        Array.prototype.forEach.call(topbar.querySelectorAll('.topbar-left, .topbar-right'), function (element, index) {
            element.classList.add('page-motion-shell-part');
            element.style.setProperty('--page-motion-shell-delay', (70 + (index * 45)) + 'ms');
        });
    }

    function activateSidebarMotion() {
        if (!sidebar) {
            return;
        }

        sidebar.classList.add('page-motion-sidebar');

        var brand = sidebar.querySelector('.brand');
        if (brand) {
            brand.classList.add('page-motion-sidebar-brand');
            brand.style.setProperty('--sidebar-motion-delay', '45ms');
        }

        var sidebarLabel = sidebar.querySelector('.sidebar-label');
        if (sidebarLabel) {
            sidebarLabel.classList.add('page-motion-sidebar-label');
            sidebarLabel.style.setProperty('--sidebar-motion-delay', '90ms');
        }

        Array.prototype.forEach.call(sidebar.querySelectorAll('.nav-section-label'), function (element, index) {
            element.classList.add('page-motion-nav-section-label');
            element.style.setProperty('--sidebar-motion-delay', (115 + (index * 48)) + 'ms');
        });

        Array.prototype.forEach.call(sidebar.querySelectorAll('.nav-item'), function (element, index) {
            element.classList.add('page-motion-nav-item');
            element.style.setProperty('--sidebar-motion-delay', (130 + (index * 30)) + 'ms');
        });

        Array.prototype.forEach.call(sidebar.querySelectorAll('.sidebar-bottom > *'), function (element, index) {
            element.classList.add('page-motion-sidebar-part');
            element.style.setProperty('--sidebar-motion-delay', (155 + (index * 52)) + 'ms');
        });
    }

    function activatePageMotion() {
        if (!body || !pageContent) {
            return;
        }

        var contentIndex = 0;
        Array.prototype.forEach.call(pageContent.children, function (element) {
            if (!element || element.classList.contains('page-loading-skeleton')) {
                return;
            }

            element.classList.add('page-motion-item');
            element.style.setProperty('--page-motion-delay', Math.min(contentIndex, 8) * 55 + 'ms');
            contentIndex += 1;
        });

        addPageMotionSequence('.stat-card, .panel', 'page-motion-card', '--page-motion-card-delay', 90, 42, 16);
        addPageMotionSequence('.profile-layout > *, .detail-layout > *, .prescription-row', 'page-motion-child', '--page-motion-child-delay', 100, 45, 16);
        addPageMotionSequence('.data-table tbody > tr', 'page-motion-row', '--page-motion-row-delay', 120, 32, 14);

        addPageMotionChildren('.page-hero-copy, .page-heading-action, .alert, .panel-header, .panel-header > div, .panel-body, .stat-card, .stat-top, .queue-summary, .queue-summary-copy, .mini-stats, .chart-insight, .chart-insight-copy, .chart-insight-number, .chart-scrubber, .chart-scrubber-meta, .notice-list, .notice-item, .quick-actions, .page-toolbar, .toolbar-filters, .search-box, .form-card-header, .form-card-body, .form-section, .form-grid, .form-field, .password-field-shell, .form-note, .form-actions, .profile-current-top, .profile-schedule-grid, .profile-schedule-item, .profile-guidance-card, .profile-access-list, .archive-summary-card, .archive-guidance, .archive-toolbar, .archive-record, .detail-card-header, .detail-card-body, .metric-list', 110, 28, 8);
        activateSidebarMotion();
        activateShellMotion();

        body.classList.add('page-motion-ready');
    }

    function finishPageLoading() {
        if (!body || !body.classList.contains('page-is-loading')) {
            return;
        }

        var elapsed = Date.now() - pageLoadingStartedAt;
        var delay = Math.max(0, pageLoadingMinimum - elapsed);
        if (pageLoadingTimer) {
            window.clearTimeout(pageLoadingTimer);
        }

        pageLoadingTimer = window.setTimeout(function () {
            body.classList.remove('page-is-loading');
            if (pageContent) {
                pageContent.setAttribute('aria-busy', 'false');
            }
            activatePageMotion();
        }, delay);
    }

    function startPageLoading() {
        if (!body) {
            return;
        }

        pageLoadingStartedAt = Date.now();
        body.classList.add('page-is-loading');
        body.classList.remove('page-motion-ready');
        if (pageContent) {
            pageContent.setAttribute('aria-busy', 'true');
        }
    }

    function isInternalNavigation(link, event) {
        if (!link || !link.href || link.hasAttribute('download') || link.hasAttribute('data-logout')) {
            return false;
        }
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }
        if (link.target && link.target !== '_self') {
            return false;
        }

        var href = link.getAttribute('href') || '';
        if (href === '' || href.charAt(0) === '#' || /^javascript:/i.test(href)) {
            return false;
        }

        try {
            var destination = new URL(link.href, window.location.href);
            return destination.origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function getSidebarScrollKey() {
        return sidebarScrollStorageKey + (window.innerWidth > 1180 ? '-wide' : '-compact');
    }

    function rememberSidebarScroll() {
        if (!sidebarNav) {
            return;
        }

        try {
            window.sessionStorage.setItem(getSidebarScrollKey(), String(Math.round(sidebarNav.scrollTop)));
        } catch (error) {
            // Session storage is optional; the current page state still works.
        }
    }

    function scheduleSidebarScrollSave() {
        if (!sidebarNav || sidebarScrollFrame !== null) {
            return;
        }

        if (typeof window.requestAnimationFrame !== 'function') {
            rememberSidebarScroll();
            return;
        }

        sidebarScrollFrame = window.requestAnimationFrame(function () {
            sidebarScrollFrame = null;
            rememberSidebarScroll();
        });
    }

    function restoreSidebarScroll() {
        if (!sidebarNav) {
            return;
        }

        var savedPosition = null;
        try {
            var savedValue = window.sessionStorage.getItem(getSidebarScrollKey());
            if (savedValue !== null && savedValue !== '') {
                savedPosition = parseInt(savedValue, 10);
            }
        } catch (error) {
            return;
        }

        if (savedPosition === null || !isFinite(savedPosition)) {
            return;
        }

        var applySavedPosition = function () {
            var maximumScroll = Math.max(0, sidebarNav.scrollHeight - sidebarNav.clientHeight);
            sidebarNav.scrollTop = Math.min(Math.max(0, savedPosition), maximumScroll);
        };

        applySavedPosition();
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(applySavedPosition);
        }
    }

    function getTableRowActionLink(row) {
        if (!row) {
            return null;
        }

        return row.querySelector('.action-cell a[href], a.table-action[href]');
    }

    function isTableRowInteractiveTarget(target) {
        return target
            && typeof target.closest === 'function'
            && Boolean(target.closest('a, button, input, select, textarea, form, label, summary, [contenteditable="true"]'));
    }

    function markTableActionRows() {
        var rows = document.querySelectorAll('.data-table tbody > tr');

        Array.prototype.forEach.call(rows, function (row) {
            if (getTableRowActionLink(row)) {
                row.setAttribute('data-row-action', 'true');
            }
        });
    }

    function handleTableRowClick(event) {
        var target = event.target;
        var row = target && typeof target.closest === 'function'
            ? target.closest('.data-table tbody > tr')
            : null;

        if (!row || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (isTableRowInteractiveTarget(target)) {
            return;
        }

        var link = getTableRowActionLink(row);
        if (!isInternalNavigation(link, event)) {
            return;
        }

        event.preventDefault();
        startPageLoading();
        window.location.href = link.href;
    }

    markTableActionRows();
    document.addEventListener('click', handleTableRowClick);

    function rememberArchiveTransition() {
        try {
            window.sessionStorage.setItem(archiveTransitionStorageKey, '1');
        } catch (error) {
            // Session storage is optional; the destination can still load normally.
        }
    }

    function animateArchiveFilterNavigation(link, event) {
        if (!archiveSummaryGrid || archiveTransitioning || !isInternalNavigation(link, event)) {
            return;
        }

        var activeLink = archiveSummaryGrid.querySelector('.archive-summary-card.is-active')
            || archiveSummaryGrid.querySelector('.archive-summary-card');

        event.preventDefault();

        if (!activeLink || activeLink === link) {
            return;
        }

        archiveTransitioning = true;
        archiveSummaryGrid.classList.add('is-transitioning');
        archiveSummaryGrid.setAttribute('aria-busy', 'true');
        link.classList.add('is-transition-target');

        var gridRect = archiveSummaryGrid.getBoundingClientRect();
        var fromRect = activeLink.getBoundingClientRect();
        var toRect = link.getBoundingClientRect();
        var travel = document.createElement('span');
        var travelColor = window.getComputedStyle(link).getPropertyValue('--archive-accent').trim() || '#5368c8';
        var fromX = fromRect.left - gridRect.left;
        var fromY = fromRect.top - gridRect.top;
        var toX = toRect.left - gridRect.left;
        var toY = toRect.top - gridRect.top;

        travel.className = 'archive-summary-travel';
        travel.setAttribute('aria-hidden', 'true');
        travel.style.setProperty('--archive-travel-color', travelColor);
        travel.style.width = fromRect.width + 'px';
        travel.style.height = fromRect.height + 'px';
        travel.style.transform = 'translate3d(' + fromX + 'px, ' + fromY + 'px, 0)';
        archiveSummaryGrid.appendChild(travel);

        window.requestAnimationFrame(function () {
            travel.classList.add('is-moving');
            window.requestAnimationFrame(function () {
                travel.style.width = toRect.width + 'px';
                travel.style.height = toRect.height + 'px';
                travel.style.transform = 'translate3d(' + toX + 'px, ' + toY + 'px, 0)';
            });
        });

        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var transitionDelay = reducedMotion ? 40 : 300;
        window.setTimeout(function () {
            rememberArchiveTransition();
            window.location.href = link.href;
        }, transitionDelay);
    }

    if (archiveSummaryGrid) {
        archiveSummaryGrid.addEventListener('click', function (event) {
            var target = event.target;
            var link = target && typeof target.closest === 'function' ? target.closest('[data-archive-filter]') : null;
            if (!link) {
                return;
            }

            animateArchiveFilterNavigation(link, event);
        }, true);
    }

    if (body && (body.classList.contains('page-is-loading') || skipPageLoader)) {
        window.setTimeout(finishPageLoading, pageLoadingMinimum);
        window.addEventListener('load', finishPageLoading);
        window.addEventListener('pageshow', finishPageLoading);

        document.addEventListener('click', function (event) {
            var target = event.target;
            var link = target && typeof target.closest === 'function' ? target.closest('a[href]') : null;
            if (!isInternalNavigation(link, event) || event.defaultPrevented || link.hasAttribute('data-archive-filter')) {
                return;
            }

            startPageLoading();
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.nodeName !== 'FORM' || form.hasAttribute('data-confirm')) {
                return;
            }

            startPageLoading();
        }, true);
    }

    if (skipPageLoader) {
        if (pageContent) {
            pageContent.setAttribute('aria-busy', 'false');
        }
        activatePageMotion();
        body.removeAttribute('data-skip-page-loader');
    }

    if (sidebarNav) {
        sidebarNav.addEventListener('scroll', scheduleSidebarScrollSave, { passive: true });
    }
    window.addEventListener('pagehide', rememberSidebarScroll);

    function isWideLayout() {
        return window.innerWidth > 1180;
    }

    function closeAccountMenus() {
        accountMenus.forEach(function (menu) {
            menu.hidden = true;
        });
        accountToggles.forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
    }

    function accountMenuFor(toggle) {
        var targetId = toggle.getAttribute('data-account-toggle');
        return targetId ? document.getElementById(targetId) : accountMenus[0];
    }

    function setAccountMenu(menu, isOpen) {
        if (!menu) {
            return;
        }

        if (isOpen) {
            accountMenus.forEach(function (otherMenu) {
                otherMenu.hidden = otherMenu !== menu;
            });
        } else {
            menu.hidden = true;
        }

        accountToggles.forEach(function (button) {
            button.setAttribute('aria-expanded', button.getAttribute('data-account-toggle') === menu.id && isOpen ? 'true' : 'false');
        });
    }

    function setSidebarCollapsed(isCollapsed) {
        closeAccountMenus();

        if (!isWideLayout()) {
            body.classList.remove('sidebar-collapsed');
            return;
        }

        body.classList.toggle('sidebar-collapsed', isCollapsed);
        try {
            window.localStorage.setItem(sidebarStorageKey, isCollapsed ? '1' : '0');
        } catch (error) {
            // Local storage is optional; the current page state still works.
        }
    }

    function syncSidebarMode() {
        if (!isWideLayout()) {
            body.classList.remove('sidebar-collapsed');
            body.classList.remove('sidebar-open');
            closeAccountMenus();
            return;
        }

        try {
            body.classList.toggle('sidebar-collapsed', window.localStorage.getItem(sidebarStorageKey) === '1');
        } catch (error) {
            body.classList.remove('sidebar-collapsed');
        }
    }

    function filterFormHasValue(form) {
        var controls = form.querySelectorAll('input[name], select[name], textarea[name]');

        return Array.prototype.some.call(controls, function (control) {
            return control.type !== 'hidden'
                && !control.disabled
                && String(control.value || '').trim() !== '';
        });
    }

    function syncFilterButton(form) {
        var button = form.querySelector('button[type="submit"]');

        if (!button) {
            return;
        }

        var hasValue = filterFormHasValue(form);
        button.setAttribute('data-filter-state', hasValue ? 'active' : 'idle');
        button.setAttribute('aria-label', hasValue ? 'Terapkan filter aktif' : 'Terapkan filter');
    }

    function getConfirmationCopy(message, isLogout) {
        if (isLogout) {
            return {
                title: 'Keluar dari sesi kerja?',
                action: 'Logout',
                note: 'Sesi kerja akan ditutup. Kamu perlu masuk kembali untuk melanjutkan.',
                kind: 'danger'
            };
        }

        var archiveMatch = message.match(/^Arsipkan data\s+([a-zA-ZÀ-ÿ]+)/i);
        if (archiveMatch) {
            return {
                title: 'Arsipkan data ' + archiveMatch[1].toLowerCase() + '?',
                action: 'Arsipkan data',
                note: 'Data tetap tersimpan dan dapat dipulihkan kembali dari menu Arsip.',
                kind: 'archive'
            };
        }

        if (/^Pulihkan data/i.test(message)) {
            return {
                title: 'Pulihkan data?',
                action: 'Pulihkan data',
                note: 'Data akan kembali ke daftar aktif dan bisa dipakai pada alur layanan berikutnya.',
                kind: 'archive'
            };
        }

        if (/^Hapus permanen/i.test(message)) {
            return {
                title: 'Hapus data permanen?',
                action: 'Hapus permanen',
                note: 'Tindakan ini tidak dapat dibatalkan. Data hanya bisa dihapus jika tidak memiliki relasi transaksi.',
                kind: 'danger'
            };
        }

        var deleteMatch = message.match(/^Hapus data\s+([a-zA-ZÀ-ÿ]+)/i);
        if (deleteMatch) {
            return {
                title: 'Hapus data ' + deleteMatch[1].toLowerCase() + '?',
                action: 'Hapus data',
                note: 'Jika data masih terhubung dengan transaksi lain, sistem akan mencegah penghapusannya.',
                kind: 'danger'
            };
        }

        if (/^Batalkan kunjungan/i.test(message)) {
            return {
                title: 'Batalkan kunjungan?',
                action: 'Batalkan kunjungan',
                note: 'Status kunjungan akan berubah menjadi Batal dan tidak bisa dipulihkan dari halaman ini.',
                kind: 'danger'
            };
        }

        if (/^Selesaikan resep/i.test(message)) {
            return {
                title: 'Selesaikan resep?',
                action: 'Selesaikan resep',
                note: 'Stok obat akan dikurangi sesuai jumlah yang tercatat pada resep.',
                kind: 'archive'
            };
        }

        return {
            title: 'Konfirmasi tindakan',
            action: 'Ya, lanjutkan',
            note: 'Periksa kembali pilihanmu. Data yang sudah diproses mungkin tidak dapat dikembalikan.',
            kind: 'danger'
        };
    }

    function closeConfirmDialog(shouldProceed) {
        if (!confirmState) {
            return;
        }

        var state = confirmState;
        confirmState = null;

        if (confirmDialog) {
            confirmDialog.hidden = true;
        }
        document.body.classList.remove('confirm-dialog-open');

        if (state.focusElement && document.contains(state.focusElement)) {
            state.focusElement.focus();
        }

        if (shouldProceed) {
            state.onConfirm();
        }
    }

    function openConfirmDialog(options) {
        if (!confirmDialog || !confirmDialogPanel || !confirmDialogTitle || !confirmDialogMessage || !confirmDialogAccept) {
            return false;
        }

        if (confirmState) {
            return true;
        }

        var copy = getConfirmationCopy(options.message || 'Lanjutkan tindakan ini?', options.isLogout === true);
        confirmState = {
            focusElement: options.focusElement || null,
            onConfirm: options.onConfirm
        };
        confirmDialogTitle.textContent = copy.title;
        confirmDialogMessage.textContent = options.message || 'Lanjutkan tindakan ini?';
        if (confirmDialogNote) {
            confirmDialogNote.textContent = copy.note;
        }
        confirmDialogAccept.textContent = copy.action;
        confirmDialogPanel.classList.toggle('is-archive', copy.kind === 'archive');
        confirmDialogPanel.classList.toggle('is-danger', copy.kind === 'danger');
        confirmDialog.hidden = false;
        document.body.classList.add('confirm-dialog-open');

        window.setTimeout(function () {
            var cancelButton = confirmDialog.querySelector('.confirm-dialog-actions [data-confirm-cancel]');
            if (cancelButton) {
                cancelButton.focus();
            } else {
                confirmDialogPanel.focus();
            }
        }, 0);

        return true;
    }

    function continueFormSubmit(form, submitter) {
        form.setAttribute('data-confirm-bypass', '1');
        if (typeof form.requestSubmit === 'function') {
            if (submitter) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
            return;
        }

        HTMLFormElement.prototype.submit.call(form);
    }

    syncSidebarMode();
    restoreSidebarScroll();

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (isWideLayout()) {
                setSidebarCollapsed(false);
                return;
            }

            body.classList.add('sidebar-open');
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (isWideLayout()) {
                setSidebarCollapsed(true);
                return;
            }

            body.classList.remove('sidebar-open');
            closeAccountMenus();
        });
    });

    expandButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setSidebarCollapsed(false);
        });
    });

    window.addEventListener('resize', syncSidebarMode);

    filterForms.forEach(function (form) {
        var controls = form.querySelectorAll('input[name], select[name], textarea[name]');
        var button = form.querySelector('button[type="submit"]');

        controls.forEach(function (control) {
            control.addEventListener('input', function () {
                syncFilterButton(form);
            });
            control.addEventListener('change', function () {
                syncFilterButton(form);

                if (control.nodeName !== 'SELECT' || control.getAttribute('data-filter-auto') === 'false') {
                    return;
                }

                if (form.getAttribute('data-filter-submitting') === 'true') {
                    return;
                }

                window.setTimeout(function () {
                    if (form.getAttribute('data-filter-submitting') === 'true') {
                        return;
                    }

                    form.setAttribute('data-filter-submitting', 'true');
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(button || undefined);
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }, 0);
            });
        });

        form.addEventListener('submit', function () {
            form.setAttribute('data-filter-submitting', 'true');

            if (!button) {
                return;
            }

            button.classList.add('is-submitting');
            button.setAttribute('aria-busy', 'true');
            button.disabled = true;

            var label = button.querySelector('span');
            if (label) {
                label.textContent = 'Memfilter…';
            }
        });

        syncFilterButton(form);
    });

    accountToggles.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            var menu = accountMenuFor(button);
            var isOpen = menu && !menu.hidden;
            setAccountMenu(menu, !isOpen);
        });
    });

    accountCloseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeAccountMenus();
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.account-menu') && !event.target.closest('[data-account-toggle]')) {
            closeAccountMenus();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (confirmState) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeConfirmDialog(false);
                return;
            }

            if (event.key === 'Tab') {
                var focusable = Array.prototype.slice.call(confirmDialog.querySelectorAll('button:not([disabled])'));
                if (focusable.length) {
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
                return;
            }
        }

        if (event.key === 'Escape') {
            closeAccountMenus();
            body.classList.remove('sidebar-open');
        }
    });

    confirmDialogCancelButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeConfirmDialog(false);
        });
    });

    if (confirmDialogAccept) {
        confirmDialogAccept.addEventListener('click', function () {
            closeConfirmDialog(true);
        });
    }

    alertButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var alert = button.closest('.alert');
            if (alert) {
                alert.remove();
            }
        });
    });

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-confirm-bypass') === '1') {
                form.removeAttribute('data-confirm-bypass');
                return;
            }

            var message = form.getAttribute('data-confirm') || 'Lanjutkan tindakan ini?';
            var submitter = event.submitter || null;
            if (openConfirmDialog({
                message: message,
                focusElement: submitter || form,
                onConfirm: function () {
                    continueFormSubmit(form, submitter);
                }
            })) {
                event.preventDefault();
                return;
            }

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    logoutLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var message = link.getAttribute('data-confirm-message') || 'Keluar dari sesi kerja sekarang?';
            if (openConfirmDialog({
                message: message,
                isLogout: true,
                focusElement: link,
                onConfirm: function () {
                    window.location.href = link.href;
                }
            })) {
                event.preventDefault();
                return;
            }

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    passwordToggles.forEach(function (button) {
        button.addEventListener('click', function () {
            var shell = button.closest('.auth-input-shell, .password-field-shell');
            var input = shell ? shell.querySelector('[data-password-input]') : null;
            if (!input) {
                return;
            }

            var willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.setAttribute('aria-pressed', willShow ? 'true' : 'false');
            button.setAttribute('aria-label', willShow ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });

    if (authLoginForm && authIdentifierInput && authPasswordInput) {
        authIdentifierInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && authIdentifierInput.value.trim() !== '') {
                event.preventDefault();
                authPasswordInput.focus();
            }
        });

        authLoginForm.addEventListener('submit', function () {
            var submitButton = authLoginForm.querySelector('button[type="submit"]');
            if (!submitButton) {
                return;
            }

            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            var submitLabel = submitButton.querySelector('span');
            if (submitLabel) {
                submitLabel.textContent = 'Memeriksa akses…';
            }
        });
    }

    roleSelects.forEach(function (select) {
        var form = select.closest('form');
        var doctorField = form ? form.querySelector('[data-role-doctor-field]') : null;
        var doctorSelect = doctorField ? doctorField.querySelector('select') : null;

        function syncDoctorField() {
            if (!doctorField || !doctorSelect) {
                return;
            }

            var isDoctor = select.value === 'Dokter';
            doctorField.hidden = !isDoctor;
            doctorSelect.disabled = !isDoctor;
            doctorSelect.required = isDoctor;
        }

        select.addEventListener('change', syncDoctorField);
        syncDoctorField();
    });

    printButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    document.querySelectorAll('[data-visit-chart]').forEach(function (chart) {
        var range = chart.querySelector('[data-chart-range]');
        var svg = chart.querySelector('.line-chart');
        var groups = chart.querySelectorAll('[data-chart-point]');
        var focusLine = chart.querySelector('[data-chart-focus-line]');
        var selectedLabel = chart.querySelector('[data-chart-selected-label]');
        var selectedValue = chart.querySelector('[data-chart-selected-value]');
        var selectedDate = chart.querySelector('[data-chart-selected-date]');
        var hoverTooltip = chart.querySelector('[data-chart-hover-tooltip]');
        var hoverLabel = chart.querySelector('[data-chart-hover-label]');
        var hoverValue = chart.querySelector('[data-chart-hover-value]');
        var hoverDate = chart.querySelector('[data-chart-hover-date]');
        var chartData = [];
        var isDragging = false;
        var lastPointerType = 'mouse';
        var hideTooltipTimer = null;

        try {
            chartData = JSON.parse(chart.getAttribute('data-chart-values') || '[]');
        } catch (error) {
            chartData = [];
        }

        if (!svg || !groups.length || !chartData.length) {
            return;
        }

        function clampIndex(index) {
            return Math.max(0, Math.min(chartData.length - 1, index));
        }

        function selectPoint(index) {
            var selectedIndex = clampIndex(index);
            var point = chartData[selectedIndex];
            var group = groups[selectedIndex];

            groups.forEach(function (pointGroup, pointIndex) {
                pointGroup.classList.toggle('is-selected', pointIndex === selectedIndex);
            });

            if (range) {
                range.value = selectedIndex;
            }

            if (focusLine && group) {
                focusLine.setAttribute('x1', group.getAttribute('data-x'));
                focusLine.setAttribute('x2', group.getAttribute('data-x'));
                focusLine.style.opacity = '1';
            }

            if (selectedLabel) {
                selectedLabel.textContent = point.label;
            }
            if (selectedValue) {
                selectedValue.textContent = point.total;
            }
            if (selectedDate) {
                selectedDate.textContent = point.date;
            }
            svg.setAttribute('aria-label', point.label + ': ' + point.total + ' kunjungan pada ' + point.date);
        }

        function hideHoverTooltip() {
            if (hoverTooltip) {
                hoverTooltip.hidden = true;
            }
        }

        function positionHoverTooltip(index) {
            if (!hoverTooltip || !hoverLabel || !hoverValue || !hoverDate) {
                return;
            }

            var group = groups[index];
            if (!group) {
                return;
            }

            var wrapperRect = chart.getBoundingClientRect();
            var pointNode = group.querySelector('.chart-point');
            var pointRect = pointNode ? pointNode.getBoundingClientRect() : group.getBoundingClientRect();
            var left = pointRect.left - wrapperRect.left + (pointRect.width / 2);
            var pointTop = pointRect.top - wrapperRect.top + (pointRect.height / 2);
            var top = pointTop - 10;
            var halfWidth = Math.min(86, Math.max(58, hoverTooltip.offsetWidth / 2));
            var minLeft = halfWidth + 4;
            var maxLeft = wrapperRect.width - halfWidth - 4;
            var placeBelow = top < (hoverTooltip.offsetHeight || 58) + 6;
            var tooltipTop = placeBelow ? pointTop + 10 : top;
            var tooltipCenter = Math.max(minLeft, Math.min(maxLeft, left));
            var tooltipWidth = hoverTooltip.offsetWidth || 116;
            var tooltipEdge = tooltipCenter - (tooltipWidth / 2);
            var caretLeft = Math.max(14, Math.min(tooltipWidth - 14, left - tooltipEdge));

            hoverLabel.textContent = chartData[index].label;
            hoverValue.textContent = chartData[index].total;
            hoverDate.textContent = chartData[index].date;
            hoverTooltip.classList.toggle('is-below', placeBelow);
            hoverTooltip.style.setProperty('--chart-tooltip-caret-left', caretLeft + 'px');
            hoverTooltip.style.left = tooltipCenter + 'px';
            hoverTooltip.style.top = Math.max(8, tooltipTop) + 'px';
            hoverTooltip.hidden = false;
        }

        function showPointFromPointer(event) {
            var index = indexFromPointer(event);
            selectPoint(index);
            positionHoverTooltip(index);
            if (hideTooltipTimer) {
                window.clearTimeout(hideTooltipTimer);
                hideTooltipTimer = null;
            }
        }

        function scheduleTooltipHide() {
            if (lastPointerType !== 'touch' || !hoverTooltip) {
                return;
            }

            if (hideTooltipTimer) {
                window.clearTimeout(hideTooltipTimer);
            }
            hideTooltipTimer = window.setTimeout(hideHoverTooltip, 1100);
        }

        function indexFromPointer(event) {
            var rect = svg.getBoundingClientRect();
            var viewBox = svg.viewBox && svg.viewBox.baseVal;
            var viewWidth = viewBox && viewBox.width ? viewBox.width : 720;
            var viewX = rect.width > 0 ? ((event.clientX - rect.left) / rect.width) * viewWidth : 0;
            var closestIndex = 0;
            var closestDistance = Infinity;

            groups.forEach(function (group, index) {
                var distance = Math.abs(viewX - (parseFloat(group.getAttribute('data-x')) || 0));
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestIndex = index;
                }
            });

            return closestIndex;
        }

        if (range) {
            range.addEventListener('input', function () {
                selectPoint(parseInt(range.value, 10) || 0);
            });
        }

        svg.addEventListener('pointerdown', function (event) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            isDragging = true;
            lastPointerType = event.pointerType || 'touch';
            svg.classList.add('is-dragging');
            if (svg.setPointerCapture) {
                svg.setPointerCapture(event.pointerId);
            }
            showPointFromPointer(event);
            event.preventDefault();
        });

        svg.addEventListener('pointermove', function (event) {
            if (isDragging || event.pointerType === 'mouse') {
                lastPointerType = event.pointerType || lastPointerType;
                showPointFromPointer(event);
            }
        });

        svg.addEventListener('pointerenter', function (event) {
            if (event.pointerType === 'mouse') {
                lastPointerType = 'mouse';
                showPointFromPointer(event);
            }
        });

        svg.addEventListener('pointerleave', function (event) {
            if (event.pointerType === 'mouse' && !isDragging) {
                hideHoverTooltip();
            }
        });

        function stopDragging() {
            isDragging = false;
            svg.classList.remove('is-dragging');
            scheduleTooltipHide();
        }

        svg.addEventListener('pointerup', stopDragging);
        svg.addEventListener('pointercancel', stopDragging);
        svg.addEventListener('lostpointercapture', stopDragging);

        groups.forEach(function (group, index) {
            group.addEventListener('click', function () {
                selectPoint(index);
            });
        });

        selectPoint(chartData.length - 1);
    });

    document.querySelectorAll('[data-auto-dismiss]').forEach(function (element) {
        window.setTimeout(function () {
            element.remove();
        }, 5200);
    });
})();
