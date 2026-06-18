<!-- Resizable table columns --------------------------------------------------
     Drag the right edge ("grab bar") of any column header to resize that
     column; the widths persist per page in localStorage. Loaded site-wide
     from the app, base and public layouts, this auto-enhances every table
     that has a single header row of plain <th> cells.

     Opt a specific table out with the data-no-resize attribute.
-->
<style>
    .ld-resize-th { position: relative; }
    .ld-col-grip {
        position: absolute;
        top: 0;
        right: 0;
        width: 9px;
        height: 100%;
        cursor: col-resize;
        user-select: none;
        touch-action: none;
        z-index: 7;
    }
    .ld-col-grip::after {
        content: "";
        position: absolute;
        top: 15%;
        bottom: 15%;
        right: 3px;
        width: 2px;
        border-radius: 1px;
        /* Faint always-on hint so users can see where to grab to resize. */
        background: rgba(0, 0, 0, .12);
        transition: background-color .12s ease;
    }
    .ld-col-grip:hover::after,
    .ld-col-grip.ld-grip-active::after {
        background: var(--ld-primary, #4f46e5);
    }
    /* Keep the resize cursor and suppress text selection for the whole drag. */
    body.ld-col-resizing,
    body.ld-col-resizing * {
        cursor: col-resize !important;
        user-select: none !important;
    }
    @media (prefers-reduced-motion: reduce) {
        .ld-col-grip::after { transition: none; }
    }
</style>
<script>
(function () {
    "use strict";

    var MIN_WIDTH  = 40;        // a column can't be dragged narrower than this
    // localStorage namespace ("column widths"). Bumped to v2 to abandon widths
    // saved before the ticket-list priority-fit (v2.104.x): stale wide Type/Group
    // widths were overriding the new sizing and crushing Subject into a sliver.
    var KEY_PREFIX = "ldcw2:";

    // One storage bucket per logical page. Numeric path segments (ticket ids,
    // user ids, ...) collapse to ":id" so every detail page shares its widths.
    function pageKey() {
        return location.pathname.replace(/\/\d+(?=\/|$)/g, "/:id");
    }

    function loadWidths(key) {
        try { return JSON.parse(localStorage.getItem(key) || "null"); }
        catch (e) { return null; }
    }

    function saveWidths(key, count, widths) {
        try { localStorage.setItem(key, JSON.stringify({ n: count, w: widths })); }
        catch (e) { /* private mode / quota exceeded — just don't persist */ }
    }

    // The cells of the lone header row, or null if the table has no header,
    // a multi-row (grouped) header, or merged cells we can't map to columns.
    function headerCells(table) {
        var head = table.tHead;
        if (!head || head.rows.length !== 1) return null;
        var cells = head.rows[0].cells;
        if (cells.length < 2) return null;
        for (var i = 0; i < cells.length; i++) {
            if ((cells[i].colSpan || 1) > 1) return null;
        }
        return cells;
    }

    // Pin each column to a pixel width and the table to their sum, so a
    // widened column makes the whole table wider rather than squeezing others.
    function applyWidths(table, cells, widths) {
        var total = 0;
        for (var i = 0; i < cells.length; i++) {
            cells[i].style.width = widths[i] + "px";
            total += widths[i];
        }
        table.style.width = total + "px";
    }

    // The narrowest a header may be pinned: its own content on one line, so a
    // column can never be crushed until the header word(s) clip or wrap. We
    // measure by briefly pinching the cell to 1px under the fixed layout and
    // reading scrollWidth (= left-padding + content); scrollWidth drops the
    // right padding when content overflows, so we add it back plus a hair.
    function contentMinWidth(th) {
        var cs    = getComputedStyle(th);
        var saved = th.style.width;
        th.style.width = "1px";
        var w = th.scrollWidth;
        th.style.width = saved;
        return Math.ceil(w + (parseFloat(cs.paddingRight) || 0) + 2);
    }

    // The floor a column may be resized to: the wider of its header's
    // one-line width and any CSS min-width (e.g. the ticket # column, whose
    // numbers are wider than its "#" header).
    function columnFloor(th) {
        var cssMin = parseFloat(getComputedStyle(th).minWidth) || 0;
        return Math.max(contentMinWidth(th), cssMin);
    }

    // Index of the column marked data-flex-col (the one that absorbs slack and
    // truncates — e.g. the ticket Subject), or -1 if the table has none. When a
    // table has a flex column it runs in "fit mode": the table fills its
    // container at 100% width instead of growing to the sum of its columns, so
    // it never forces horizontal scroll on load.
    function flexColIndex(cells) {
        for (var i = 0; i < cells.length; i++) {
            if (cells[i].hasAttribute("data-flex-col")) return i;
        }
        return -1;
    }

    // Re-sum the header widths and re-pin the table. Exposed for pages that
    // re-fit columns after an inline edit (the ticket-list quick-change cells).
    // In fit mode the table stays at 100% so the flex column keeps absorbing.
    function syncTableWidth(table) {
        var cells = headerCells(table);
        if (!cells) return;
        if (flexColIndex(cells) >= 0) { table.style.width = "100%"; return; }
        var total = 0;
        for (var i = 0; i < cells.length; i++) total += cells[i].offsetWidth;
        table.style.width = total + "px";
    }

    // Wrap the table in a horizontal-scroll container so a widened table
    // scrolls instead of bursting out of its card. Skipped when an ancestor
    // already scrolls (e.g. Bootstrap's .table-responsive).
    function ensureScrollParent(table) {
        var el = table.parentElement;
        while (el && el !== document.body) {
            var ox = getComputedStyle(el).overflowX;
            if (ox === "auto" || ox === "scroll") return;
            el = el.parentElement;
        }
        var wrap = document.createElement("div");
        wrap.style.overflowX = "auto";
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    }

    function addGrip(table, cells, index, key, floors, flexIndex) {
        var th = cells[index];
        th.classList.add("ld-resize-th");
        var minW = Math.max(MIN_WIDTH, floors[index] || 0);

        var fitMode   = flexIndex >= 0;
        var isFlex    = index === flexIndex;
        var flexCell  = fitMode ? cells[flexIndex] : null;
        var flexFloor = fitMode ? Math.max(MIN_WIDTH, floors[flexIndex] || 0) : 0;

        var grip = document.createElement("span");
        grip.className = "ld-col-grip";
        grip.setAttribute("aria-hidden", "true");
        th.appendChild(grip);

        var startX, startWidth, startTableWidth, startFlexWidth, moved;

        grip.addEventListener("pointerdown", function (e) {
            e.preventDefault();
            e.stopPropagation();
            startX          = e.clientX;
            // Resizing a normal column while the table is elastic (100%) steals
            // space from the flex column; keep it unpinned so it absorbs. In
            // crowded/px mode we leave things as-is and just grow the table.
            if (fitMode && !isFlex && table.style.width === "100%") {
                flexCell.style.width = "";
            }
            startWidth      = th.offsetWidth;
            startTableWidth = table.offsetWidth;
            startFlexWidth  = flexCell ? flexCell.offsetWidth : 0;
            moved           = false;
            grip.classList.add("ld-grip-active");
            document.body.classList.add("ld-col-resizing");
            grip.setPointerCapture(e.pointerId);
        });

        grip.addEventListener("pointermove", function (e) {
            if (startX === undefined) return;
            moved = true;
            var dx = e.clientX - startX;
            if (fitMode && !isFlex && startFlexWidth > flexFloor) {
                // Steal from / give back to the flex column; the table stays at
                // 100% so widening this column never adds horizontal scroll —
                // it just narrows the Subject (which truncates with an ellipsis).
                var maxW  = startWidth + (startFlexWidth - flexFloor);
                var width = Math.min(Math.max(minW, startWidth + dx), Math.max(minW, maxW));
                th.style.width = width + "px";
            } else {
                // The flex column itself, or a column whose neighbour is already
                // at its floor: grow the table (horizontal scroll is acceptable
                // here because the user explicitly dragged to see more).
                var w = Math.max(minW, startWidth + dx);
                th.style.width    = w + "px";
                table.style.width = (startTableWidth + (w - startWidth)) + "px";
            }
        });

        function finish() {
            if (startX === undefined) return;
            startX = undefined;
            grip.classList.remove("ld-grip-active");
            document.body.classList.remove("ld-col-resizing");
            if (!moved) return;
            // Mark the column so content-driven re-fits leave it alone.
            th.dataset.ldResized = "1";
            var widths = [];
            for (var i = 0; i < cells.length; i++) widths.push(cells[i].offsetWidth);
            saveWidths(key, cells.length, widths);
        }

        grip.addEventListener("pointerup", finish);
        grip.addEventListener("pointercancel", finish);
        // A click on the grip must never reach an underlying header sort link.
        grip.addEventListener("click", function (e) {
            e.stopPropagation();
            e.preventDefault();
        });
    }

    function enhance(table, index) {
        if (table.dataset.ldResize) return;            // already done
        if (table.hasAttribute("data-no-resize")) return;
        if (table.offsetParent === null) return;       // hidden (modal, collapse)

        var cells = headerCells(table);
        if (!cells) return;

        table.dataset.ldResize = "done";

        var key = KEY_PREFIX + pageKey() + "#" + (table.id || ("t" + index));

        // Measure the natural rendered widths *before* switching to fixed
        // layout, so the table keeps its current look until the user resizes.
        var natural = [];
        for (var i = 0; i < cells.length; i++) natural.push(cells[i].offsetWidth);

        var stored = loadWidths(key);
        var widths = (stored && stored.n === cells.length && stored.w)
            ? stored.w
            : natural;

        var flexIndex = flexColIndex(cells);
        if (flexIndex >= 0) {
            // Fit mode: pin every column except the flex one, and keep the table
            // at 100% so the flex column soaks up the leftover width and the
            // table fills its container without forcing horizontal scroll.
            for (var p = 0; p < cells.length; p++) {
                cells[p].style.width = (p === flexIndex) ? "" : (widths[p] + "px");
            }
            table.style.width = "100%";
        } else {
            applyWidths(table, cells, widths);
        }
        table.style.tableLayout = "fixed";
        ensureScrollParent(table);

        // Floor every column at its header word width (+ the # column's CSS
        // min-width). Repair any saved-too-narrow column up to its floor on
        // load, so headers/numbers that were previously dragged into clipping
        // come back whole. The flex column stays elastic — never pinned.
        var floors  = [];
        var widened = false;
        for (var f = 0; f < cells.length; f++) {
            floors.push(columnFloor(cells[f]));
            if (f !== flexIndex && cells[f].offsetWidth < floors[f]) {
                cells[f].style.width = floors[f] + "px";
                widened = true;
            }
        }

        // Crowded fit mode: if the pinned columns leave the flex column below
        // its floor (it would otherwise collapse to nothing), pin it to the
        // floor and let the table grow. Horizontal scroll is unavoidable with
        // this many columns, but Subject stays visible and truncates instead of
        // vanishing.
        if (flexIndex >= 0 && cells[flexIndex].offsetWidth < floors[flexIndex]) {
            cells[flexIndex].style.width = floors[flexIndex] + "px";
            var sum = 0;
            for (var s = 0; s < cells.length; s++) sum += cells[s].offsetWidth;
            table.style.width = sum + "px";
        } else if (widened) {
            syncTableWidth(table);
        }

        for (var g = 0; g < cells.length; g++) addGrip(table, cells, g, key, floors, flexIndex);
    }

    function init() {
        var tables = document.querySelectorAll("table");
        for (var i = 0; i < tables.length; i++) enhance(tables[i], i);
    }

    // init is safe to re-call after dynamic content swaps: enhance() skips
    // tables it has already processed, so only new tables get grips.
    window.LDColResize = { syncTableWidth: syncTableWidth, init: init };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            requestAnimationFrame(init);
        });
    } else {
        requestAnimationFrame(init);
    }
})();
</script>
