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

    var MIN_WIDTH  = 40;       // a column can't be dragged narrower than this
    var KEY_PREFIX = "ldcw:";  // localStorage namespace ("column widths")

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

    // Re-sum the header widths and re-pin the table. Exposed for pages that
    // re-fit columns after an inline edit (the ticket-list quick-change cells).
    function syncTableWidth(table) {
        var cells = headerCells(table);
        if (!cells) return;
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

    function addGrip(table, cells, index, key, floor) {
        var th = cells[index];
        th.classList.add("ld-resize-th");
        var minW = Math.max(MIN_WIDTH, floor || 0);

        var grip = document.createElement("span");
        grip.className = "ld-col-grip";
        grip.setAttribute("aria-hidden", "true");
        th.appendChild(grip);

        var startX, startWidth, startTableWidth, moved;

        grip.addEventListener("pointerdown", function (e) {
            e.preventDefault();
            e.stopPropagation();
            startX          = e.clientX;
            startWidth      = th.offsetWidth;
            startTableWidth = table.offsetWidth;
            moved           = false;
            grip.classList.add("ld-grip-active");
            document.body.classList.add("ld-col-resizing");
            grip.setPointerCapture(e.pointerId);
        });

        grip.addEventListener("pointermove", function (e) {
            if (startX === undefined) return;
            moved = true;
            var width = Math.max(minW, startWidth + (e.clientX - startX));
            th.style.width    = width + "px";
            table.style.width = (startTableWidth + (width - startWidth)) + "px";
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

        applyWidths(table, cells, widths);
        table.style.tableLayout = "fixed";
        ensureScrollParent(table);

        // Floor every column at its header word width (+ the # column's CSS
        // min-width). Repair any saved-too-narrow column up to its floor on
        // load, so headers/numbers that were previously dragged into clipping
        // come back whole.
        var floors  = [];
        var widened = false;
        for (var f = 0; f < cells.length; f++) {
            floors.push(columnFloor(cells[f]));
            if (cells[f].offsetWidth < floors[f]) {
                cells[f].style.width = floors[f] + "px";
                widened = true;
            }
        }
        if (widened) syncTableWidth(table);

        for (var g = 0; g < cells.length; g++) addGrip(table, cells, g, key, floors[g]);
    }

    function init() {
        var tables = document.querySelectorAll("table");
        for (var i = 0; i < tables.length; i++) enhance(tables[i], i);
    }

    window.LDColResize = { syncTableWidth: syncTableWidth };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            requestAnimationFrame(init);
        });
    } else {
        requestAnimationFrame(init);
    }
})();
</script>
