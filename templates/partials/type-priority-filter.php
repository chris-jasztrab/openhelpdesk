<?php
/**
 * Filters the #priority_id <select> to the priorities allowed by the currently
 * selected #type_id, using the $typePriorityMap passed by the route
 * ([typeId => [allowed priority id, ...]] for types that restrict). A type with
 * no entry is unrestricted.
 *
 * On initial load the current selection is kept even if it's no longer offered
 * (so an existing record's stored priority isn't silently dropped); when the
 * type is changed, an out-of-range selection resets to the blank option.
 *
 * Requires elements with ids `type_id` and `priority_id` on the page.
 */
?>
<script>
(function () {
    var typePriorities = <?= json_encode((object) ($typePriorityMap ?? [])) ?>;
    var typeSel = document.getElementById('type_id');
    var priSel  = document.getElementById('priority_id');
    if (!typeSel || !priSel) return;
    function filterPriorities(reset) {
        var allowed = typePriorities[String(parseInt(typeSel.value) || 0)] || null;
        Array.prototype.forEach.call(priSel.options, function (opt) {
            if (opt.value === '') return; // keep the blank / "none" option
            var ok = !allowed || allowed.indexOf(parseInt(opt.value)) !== -1;
            if (!ok && opt.selected && !reset) { opt.hidden = false; opt.disabled = false; return; }
            opt.hidden   = !ok;
            opt.disabled = !ok;
            if (!ok && opt.selected && reset) priSel.value = '';
        });
    }
    typeSel.addEventListener('change', function () { filterPriorities(true); });
    filterPriorities(false);
})();
</script>
