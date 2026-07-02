<!-- Sortable admin list ---------------------------------------------------
     Auto-enhances any <table data-sortable-list data-reorder-url="..."> on
     the page with:
       - A leading drag-handle column (injected automatically).
       - Drag-and-drop row reordering (saves immediately to the endpoint).
       - Click-to-sort on any <th data-sort-col>; an unsaved-order toolbar
         appears so the user explicitly commits the new order.
     Each <tr> needs data-id="<row id>". Cells whose visible text isn't
     directly comparable (dates, badges) can opt in to a comparable string
     via data-sort-value on the <td>.

     The endpoint accepts POST JSON { ids: [1,2,3...] } with the X-CSRF-Token
     header and returns { success: bool, error?: string }.
-->
<style>
    th.sortable-handle-col,
    td.sortable-handle {
        width: 32px;
        padding-left: .35rem !important;
        padding-right: .35rem !important;
        text-align: center;
    }
    td.sortable-handle .bi-grip-vertical {
        cursor: grab;
        color: #94a3b8;
        font-size: 1.1rem;
    }
    td.sortable-handle .bi-grip-vertical:active { cursor: grabbing; }
    tr.sortable-ghost { opacity: .35; background: #eef2ff; }
    tr.sortable-chosen { background: #f1f5f9; }
    th.sortable-header { user-select: none; cursor: pointer; white-space: nowrap; }
    th.sortable-header:hover { background: rgba(99,102,241,.08); }
    th.sortable-header:focus-visible {
        outline: 2px solid var(--ld-primary, #4f46e5);
        outline-offset: -2px;
    }
    th.sortable-header .sortable-indicator { font-size: .8em; opacity: .55; }
    th.sortable-header[data-sort-dir] .sortable-indicator { opacity: 1; color: var(--ld-primary, #4f46e5); }
    .sortable-toolbar {
        border-top-left-radius: .375rem;
        border-top-right-radius: .375rem;
    }
    [data-bs-theme="dark"] tr.sortable-ghost { background: #2b3035; }
    [data-bs-theme="dark"] tr.sortable-chosen { background: #343a40; }
    [data-bs-theme="dark"] td.sortable-handle .bi-grip-vertical { color: #6c757d; }
</style>
<script src="/assets/vendor/sortablejs/Sortable.min.js"></script>
<script>
(function () {
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    document.querySelectorAll('table[data-sortable-list]').forEach(initTable);

    function initTable(table) {
        var tbody    = table.querySelector('tbody');
        var endpoint = table.dataset.reorderUrl || '';
        if (!tbody || !endpoint) return;

        injectHandleColumn(table, tbody);

        var savedOrder = currentOrder(tbody);
        var toolbar    = buildToolbar(table);

        if (window.Sortable) {
            Sortable.create(tbody, {
                handle: '.sortable-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function () {
                    var beforeCommit = currentOrder(tbody);
                    commit(true, function (ok) {
                        if (ok) showSavedToast(table);
                    });
                }
            });
        }

        // Header click sort — visual only; user clicks Save Order to persist.
        Array.from(table.querySelectorAll('th[data-sort-col]')).forEach(function (th) {
            decorateHeader(th);
            th.addEventListener('click', function () { applySort(th); });
            th.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applySort(th); }
            });
        });

        function applySort(th) {
            var dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
            table.querySelectorAll('th[data-sort-col]').forEach(function (other) {
                if (other !== th) {
                    delete other.dataset.sortDir;
                    var ind = other.querySelector('.sortable-indicator');
                    if (ind) ind.innerHTML = '<i class="bi bi-arrow-down-up"></i>';
                }
            });
            th.dataset.sortDir = dir;
            th.querySelector('.sortable-indicator').innerHTML = dir === 'asc'
                ? '<i class="bi bi-sort-down-alt"></i>'
                : '<i class="bi bi-sort-up"></i>';
            sortRows(tbody, columnIndex(th), dir);
            refreshToolbar();
        }

        function refreshToolbar() {
            toolbar.style.display = sameOrder(currentOrder(tbody), savedOrder) ? 'none' : 'flex';
        }

        function commit(silent, done) {
            var ids = currentOrder(tbody);
            postJson(endpoint, { ids: ids.map(function (s) { return parseInt(s, 10); }) })
                .then(function (j) {
                    var ok = !!(j && j.success);
                    if (ok) {
                        savedOrder = ids;
                        if (!silent) flash(toolbar, 'Saved');
                        refreshToolbar();
                    } else {
                        alert((j && j.error) || 'Could not save order.');
                    }
                    if (done) done(ok);
                })
                .catch(function () {
                    alert('Network error saving order.');
                    if (done) done(false);
                });
        }

        toolbar.querySelector('[data-sortable-save]').addEventListener('click', function () { commit(false); });
        toolbar.querySelector('[data-sortable-revert]').addEventListener('click', function () {
            restoreOrder(tbody, savedOrder);
            table.querySelectorAll('th[data-sort-col]').forEach(function (th) {
                delete th.dataset.sortDir;
                var ind = th.querySelector('.sortable-indicator');
                if (ind) ind.innerHTML = '<i class="bi bi-arrow-down-up"></i>';
            });
            refreshToolbar();
        });

        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(body || {})
            }).then(function (r) { return r.json(); });
        }
    }

    function injectHandleColumn(table, tbody) {
        var headRow = table.querySelector('thead tr');
        if (headRow && !headRow.querySelector('.sortable-handle-col')) {
            var th = document.createElement('th');
            th.className = 'sortable-handle-col';
            th.setAttribute('aria-label', 'Drag handle');
            headRow.insertBefore(th, headRow.firstChild);
        }
        Array.from(tbody.querySelectorAll('tr')).forEach(function (tr) {
            if (!tr.dataset.id || tr.querySelector('.sortable-handle')) return;
            var td = document.createElement('td');
            td.className = 'sortable-handle';
            td.innerHTML = '<i class="bi bi-grip-vertical" title="Drag to reorder"></i>';
            tr.insertBefore(td, tr.firstChild);
        });
        // Bump colspan on any single-cell "empty state" rows so they still span the table.
        Array.from(tbody.querySelectorAll('tr')).forEach(function (tr) {
            if (tr.dataset.id) return;
            var only = tr.children.length === 1 ? tr.children[0] : null;
            if (only && only.hasAttribute('colspan')) {
                only.setAttribute('colspan', String(parseInt(only.getAttribute('colspan'), 10) + 1));
            }
        });
    }

    function buildToolbar(table) {
        var bar = document.createElement('div');
        bar.className = 'sortable-toolbar align-items-center gap-2 px-3 py-2 border bg-warning bg-opacity-10 small';
        bar.style.display = 'none';
        bar.innerHTML =
            '<span class="me-auto"><i class="bi bi-exclamation-circle text-warning me-1"></i>'
          + 'Order changed — not yet saved to all dropdowns.</span>'
          + '<button type="button" class="btn btn-sm btn-outline-secondary" data-sortable-revert>'
          + '<i class="bi bi-arrow-counterclockwise me-1"></i>Revert</button>'
          + '<button type="button" class="btn btn-sm btn-primary" data-sortable-save>'
          + '<i class="bi bi-check2 me-1"></i>Save Order</button>';
        var wrap = table.closest('.card') || table.parentNode;
        wrap.parentNode.insertBefore(bar, wrap);
        return bar;
    }

    function decorateHeader(th) {
        th.classList.add('sortable-header');
        th.setAttribute('role', 'button');
        th.setAttribute('tabindex', '0');
        th.setAttribute('title', 'Click to sort');
        if (!th.querySelector('.sortable-indicator')) {
            var ind = document.createElement('span');
            ind.className = 'sortable-indicator ms-1';
            ind.innerHTML = '<i class="bi bi-arrow-down-up"></i>';
            th.appendChild(ind);
        }
    }

    function columnIndex(th) {
        var idx = 0, n = th;
        while ((n = n.previousElementSibling)) idx++;
        return idx;
    }

    function currentOrder(tbody) {
        return Array.from(tbody.querySelectorAll('tr[data-id]')).map(function (tr) { return tr.dataset.id; });
    }

    function restoreOrder(tbody, ids) {
        var map = {};
        Array.from(tbody.querySelectorAll('tr[data-id]')).forEach(function (tr) { map[tr.dataset.id] = tr; });
        ids.forEach(function (id) { if (map[id]) tbody.appendChild(map[id]); });
    }

    function sortRows(tbody, colIndex, dir) {
        var rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
        rows.sort(function (a, b) {
            var av = cellValue(a, colIndex);
            var bv = cellValue(b, colIndex);
            var an = parseFloat(av), bn = parseFloat(bv);
            var cmp;
            if (!isNaN(an) && !isNaN(bn) && av !== '' && bv !== '' && String(an) === av.trim() && String(bn) === bv.trim()) {
                cmp = an - bn;
            } else {
                cmp = av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' });
            }
            return dir === 'asc' ? cmp : -cmp;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
    }

    function cellValue(tr, idx) {
        var cell = tr.children[idx];
        if (!cell) return '';
        if (cell.dataset.sortValue !== undefined) return cell.dataset.sortValue;
        return (cell.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function sameOrder(a, b) {
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
        return true;
    }

    function flash(el, msg) {
        var badge = document.createElement('span');
        badge.className = 'badge bg-success ms-2';
        badge.innerHTML = '<i class="bi bi-check2 me-1"></i>' + msg;
        el.appendChild(badge);
        setTimeout(function () { badge.remove(); }, 1500);
    }

    // Floating "Saved" toast for silent drag-end commits — the toolbar is
    // hidden once savedOrder matches currentOrder, so its flash() badge
    // wouldn't be visible. This toast lives outside the toolbar.
    function showSavedToast(table) {
        var wrap = table.closest('.card') || table.parentNode;
        if (!wrap) return;
        var prev = wrap.querySelector('.sortable-saved-toast');
        if (prev) prev.remove();

        var toast = document.createElement('div');
        toast.className = 'sortable-saved-toast';
        toast.style.cssText =
            'position:absolute;top:8px;right:8px;'
          + 'background:#198754;color:#fff;padding:4px 12px;'
          + 'border-radius:.375rem;font-size:.875rem;font-weight:600;'
          + 'box-shadow:0 2px 8px rgba(0,0,0,.15);z-index:1050;'
          + 'transition:opacity .3s;';
        toast.innerHTML = '<i class="bi bi-check2 me-1"></i>Order saved';

        var prevPos = wrap.style.position;
        if (!prevPos || prevPos === 'static') wrap.style.position = 'relative';
        wrap.appendChild(toast);
        setTimeout(function () { toast.style.opacity = '0'; }, 1200);
        setTimeout(function () { toast.remove(); }, 1500);
    }
})();
</script>
