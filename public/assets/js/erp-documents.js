/*
 * Commercial document forms (quotes, orders, invoices - sales and purchases).
 * - [data-contact-picker]: search a client / supplier or type a new name ("__new__:<name>", created on save).
 * - [data-doc-lines]: product / service / manual lines, price pre-filled from the catalogue,
 *   live totals with ONE VAT rate applied to the total HT (same rule as HandlesDocumentLines::pricedLines).
 */
(function ($) {
    'use strict';

    var NEW_PREFIX = '__new__:';

    function money(value) {
        return (Math.round(value * 100) / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function num(input) {
        var value = parseFloat(String(input && input.value || '').replace(',', '.'));
        return isNaN(value) ? 0 : value;
    }

    // ---- Contact picker --------------------------------------------------------------------------

    function initContactPicker(select) {
        if (!$ || !$.fn.select2 || $(select).data('select2')) {
            return;
        }
        var noun = select.getAttribute('data-noun') || 'contact';

        $(select).select2({
            width: '100%',
            language: 'fr',
            tags: true,
            placeholder: select.options[0] ? select.options[0].text : '',
            createTag: function (params) {
                var term = $.trim(params.term);
                if (term === '') {
                    return null;
                }
                return { id: NEW_PREFIX + term, text: term, isNew: true };
            },
            insertTag: function (data, tag) {
                // Existing matches first, "create" suggestion last.
                data.push(tag);
            },
            templateResult: function (item) {
                if (item.isNew) {
                    return $('<span>').append($('<i class="fe fe-user-plus me-1" aria-hidden="true">'))
                        .append(document.createTextNode(' Creer le ' + noun + ' « ' + item.text + ' »'));
                }
                return item.text;
            },
            templateSelection: function (item) {
                var isNew = item.isNew || String(item.id || '').indexOf(NEW_PREFIX) === 0;
                return isNew ? item.text.replace(/ \(nouveau\)$/, '') + ' (nouveau)' : item.text;
            }
        });
    }

    // ---- Lines -----------------------------------------------------------------------------------

    function DocumentLines(container) {
        this.container = container;
        this.body = container.querySelector('[data-lines-body]');
        this.template = container.querySelector('template[data-line-template]');
        var catalog = container.querySelector('[data-catalog]');
        this.catalog = catalog ? JSON.parse(catalog.textContent) : { product: {}, service: {} };
        this.tva = container.querySelector('[data-tva-rate]');
        this.bind();
        this.rows().forEach(this.applyMode.bind(this));
        this.recalc();
    }

    DocumentLines.prototype.rows = function () {
        return Array.prototype.slice.call(this.body.querySelectorAll('tr[data-line]'));
    };

    DocumentLines.prototype.applyMode = function (row) {
        var mode = row.querySelector('[data-line-mode]').value;
        row.querySelectorAll('[data-field]').forEach(function (field) {
            field.hidden = field.getAttribute('data-field') !== mode;
        });
    };

    DocumentLines.prototype.onModeChange = function (row) {
        var mode = row.querySelector('[data-line-mode]').value;
        // Only the field of the chosen type is kept, so the server never sees two articles on one line.
        row.querySelectorAll('[data-line-article]').forEach(function (select) {
            if (select.getAttribute('data-line-article') !== mode && select.value !== '') {
                select.value = '';
                if ($) { $(select).trigger('change.select2'); }
            }
        });
        if (mode !== 'manual') {
            row.querySelector('input[name="item_name[]"]').value = '';
        }
        this.applyMode(row);
        var target = row.querySelector('[data-field="' + mode + '"] select, [data-field="' + mode + '"] input');
        if (mode === 'manual' && target) {
            target.focus();
        } else if (target && $ && $(target).data('select2')) {
            $(target).select2('open');
        }
        this.recalc();
    };

    DocumentLines.prototype.onArticleChange = function (row, select) {
        var type = select.getAttribute('data-line-article');
        var price = this.catalog[type] ? this.catalog[type][select.value] : undefined;
        var priceInput = row.querySelector('[data-line-price]');
        if (price !== undefined && priceInput) {
            priceInput.value = Number(price).toFixed(2);
        }
        this.recalc();
    };

    DocumentLines.prototype.addRow = function (focus) {
        var fragment = this.template.content.cloneNode(true);
        var row = fragment.querySelector('tr[data-line]');
        this.body.appendChild(fragment);
        if (window.initSearchableSelects) {
            window.initSearchableSelects(row);
        }
        this.applyMode(row);
        this.recalc();
        var first = row.querySelector('[data-line-article="product"]');
        if (focus !== false && first && $ && $(first).data('select2')) {
            $(first).select2('open');
        }
        return row;
    };

    // ---- Lines imported from Excel (ImportController@lines) -------------------------------------

    DocumentLines.prototype.isBlank = function (row) {
        var mode = row.querySelector('[data-line-mode]').value;
        var field = row.querySelector('[data-field="' + mode + '"] select, [data-field="' + mode + '"] input');
        return !field || field.value === '';
    };

    DocumentLines.prototype.fillRow = function (row, line) {
        row.querySelector('[data-line-mode]').value = line.mode;
        this.applyMode(row);
        ['product', 'service'].forEach(function (type) {
            var select = row.querySelector('[data-line-article="' + type + '"]');
            select.value = line.mode === type ? String(line[type + '_id']) : '';
            if ($) { $(select).trigger('change.select2'); }
        });
        row.querySelector('input[name="item_name[]"]').value = line.mode === 'manual' ? line.name : '';
        row.querySelector('[data-line-qty]').value = line.quantity;
        var price = row.querySelector('[data-line-price]');
        if (price) { price.value = Number(line.price).toFixed(2); }
        var done = row.querySelector('[data-line-done]');
        if (done) { done.value = line.quantity; }
    };

    DocumentLines.prototype.importLines = function (input) {
        var self = this;
        var result = this.container.querySelector('[data-import-lines-result]');
        var file = input.files && input.files[0];
        if (!file) { return; }
        result.className = 'small mt-2 text-muted';
        result.textContent = 'Lecture du fichier...';

        var data = new FormData();
        data.append('file', file);
        data.append('side', input.getAttribute('data-side'));
        fetch(input.getAttribute('data-url'), {
            method: 'POST',
            body: data,
            headers: { 'X-CSRF-TOKEN': input.getAttribute('data-token'), 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (json) { return { ok: response.ok, json: json }; });
        }).then(function (answer) {
            var json = answer.json || {};
            var lines = json.lines || [];
            var warnings = json.warnings || (json.errors ? Object.values(json.errors).flat() : []);
            lines.forEach(function (line) {
                var row = self.rows().filter(self.isBlank.bind(self))[0] || self.addRow(false);
                self.fillRow(row, line);
            });
            self.recalc();
            result.className = 'small mt-2 ' + (lines.length ? 'text-success' : 'text-danger');
            result.textContent = lines.length
                ? lines.length + ' ligne(s) ajoutee(s) depuis ' + file.name + '. Verifiez puis enregistrez.'
                : 'Aucune ligne importee.';
            warnings.forEach(function (warning) {
                var item = document.createElement('div');
                item.className = 'text-warning-emphasis text-danger';
                item.textContent = warning;
                result.appendChild(item);
            });
        }).catch(function () {
            result.className = 'small mt-2 text-danger';
            result.textContent = "Le fichier n'a pas pu etre envoye. Reessayez.";
        }).finally(function () {
            input.value = '';
        });
    };

    DocumentLines.prototype.removeRow = function (row) {
        if (this.rows().length > 1) {
            row.remove();
        } else {
            // Last line: empty it instead of leaving a document without line.
            row.querySelectorAll('input').forEach(function (input) {
                input.value = input.hasAttribute('data-line-qty') ? 1 : '';
            });
            row.querySelectorAll('[data-line-article]').forEach(function (select) {
                select.value = '';
                if ($) { $(select).trigger('change.select2'); }
            });
        }
        this.recalc();
    };

    DocumentLines.prototype.recalc = function () {
        if (!this.container.querySelector('[data-total-ht]')) {
            return; // bon de livraison / de reception: quantities only
        }
        var totalHt = 0;
        this.rows().forEach(function (row) {
            var line = Math.round(num(row.querySelector('[data-line-qty]')) * num(row.querySelector('[data-line-price]')) * 100) / 100;
            row.querySelector('[data-line-total]').textContent = money(line);
            totalHt += line;
        });
        totalHt = Math.round(totalHt * 100) / 100;
        var tva = Math.round(totalHt * num(this.tva) / 100 * 100) / 100;
        this.container.querySelector('[data-total-ht]').textContent = money(totalHt);
        this.container.querySelector('[data-total-tva]').textContent = money(tva);
        this.container.querySelector('[data-total-ttc]').textContent = money(totalHt + tva);
    };

    DocumentLines.prototype.bind = function () {
        var self = this;
        var rowOf = function (el) { return el.closest('tr[data-line]'); };

        // jQuery delegation also catches the change events triggered by select2.
        var $c = $ ? $(this.container) : null;
        var onChange = function (event) {
            var target = event.target;
            if (target.matches('[data-line-mode]')) {
                self.onModeChange(rowOf(target));
            } else if (target.matches('[data-line-article]')) {
                self.onArticleChange(rowOf(target), target);
            }
        };
        if ($c) {
            $c.on('change', 'select', onChange);
        } else {
            this.container.addEventListener('change', onChange);
        }

        this.container.addEventListener('change', function (event) {
            if (event.target.matches('[data-import-lines]')) {
                self.importLines(event.target);
            }
        });
        this.container.addEventListener('input', function (event) {
            if (event.target.matches('[data-line-qty], [data-line-price], [data-tva-rate]')) {
                self.recalc();
            }
        });
        this.container.addEventListener('click', function (event) {
            if (event.target.closest('[data-add-line]')) {
                self.addRow(true);
            }
            var remove = event.target.closest('[data-remove-line]');
            if (remove) {
                self.removeRow(rowOf(remove));
            }
        });
        // Enter in the last price field adds a line instead of submitting the whole document.
        this.container.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || !event.target.matches('[data-line-qty], [data-line-price], input[name="item_name[]"]')) {
                return;
            }
            event.preventDefault();
            var rows = self.rows();
            if (rowOf(event.target) === rows[rows.length - 1]) {
                self.addRow(true);
            }
        });
    };

    function init() {
        document.querySelectorAll('select[data-contact-picker]').forEach(initContactPicker);
        document.querySelectorAll('[data-doc-lines]').forEach(function (container) {
            new DocumentLines(container);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window.jQuery);
