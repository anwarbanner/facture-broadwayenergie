/*
 * ERP UI helpers.
 * - List tables (inside .erp-table-shell) get data-label on each cell from their column headers,
 *   so erp-ui.css can show them as labelled cards on phones.
 * - After a creation, the new row of the list (the one whose links / forms point to the created record)
 *   is highlighted in blue, then fades back to white like the other rows.
 */
(function () {
    'use strict';

    function labelTable(table) {
        var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
            return th.textContent.trim();
        });
        if (!headers.length) {
            return;
        }

        table.classList.add('erp-cards');
        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
            var cells = row.children;
            if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
                cells[0].classList.add('erp-empty');
                return;
            }
            Array.prototype.forEach.call(cells, function (cell, index) {
                if (headers[index] && !cell.hasAttribute('data-label')) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });
    }

    function highlightCreatedRow() {
        var marker = document.querySelector('[data-erp-highlight]');
        if (!marker) {
            return;
        }
        var target = marker.getAttribute('data-erp-highlight').replace(/\/+$/, '');
        var rows = document.querySelectorAll('table tbody tr');
        for (var i = 0; i < rows.length; i++) {
            var links = rows[i].querySelectorAll('a[href], form[action]');
            for (var j = 0; j < links.length; j++) {
                var url = (links[j].href || links[j].action || '').split(/[?#]/)[0];
                // Exact record URL, or one of its sub-pages (/edit, /pdf, /void...) - never /12 for /1.
                if (url === target || url.indexOf(target + '/') === 0) {
                    var row = rows[i];
                    row.classList.add('erp-row-new');
                    var rect = row.getBoundingClientRect();
                    if (rect.top < 0 || rect.bottom > window.innerHeight) {
                        row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                    row.addEventListener('animationend', function () { this.classList.remove('erp-row-new'); }, { once: true });
                    return;
                }
            }
        }
    }

    // ---- Light / dark mode: header button, remembered on this device --------------------------
    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function paintToggle() {
        var dark = currentTheme() === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.title = dark ? 'Passer en mode clair' : 'Passer en mode sombre';
            var icon = button.querySelector('[data-theme-icon]');
            if (icon) { icon.className = 'fe ' + (dark ? 'fe-sun' : 'fe-moon'); }
            var label = button.querySelector('[data-theme-label]');
            if (label) { label.textContent = dark ? 'Mode clair' : 'Mode sombre'; }
        });
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('erp-theme', theme); } catch (e) {}
        paintToggle();
        // Charts and other widgets re-colour themselves
        document.dispatchEvent(new CustomEvent('erp-theme-change', { detail: { theme: theme } }));
    }

    window.erpTheme = { current: currentTheme, set: setTheme };

    function init() {
        paintToggle();
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
            });
        });
        document.querySelectorAll('.erp-table-shell table.table').forEach(labelTable);
        highlightCreatedRow();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
