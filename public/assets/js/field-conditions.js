/**
 * Conditional form fields — client half.
 *
 * The server decides this too, in evaluateFieldCondition() (src/helpers.php),
 * and the server's answer is the one that counts: a field whose condition fails
 * there is not required and its value is not saved, whatever this file did.
 * This exists so the form reacts as you type instead of after a round trip.
 *
 * The two implementations have to agree, so the operator semantics below are a
 * deliberate mirror of the PHP — including "0" counting as empty for `filled`
 * and `empty`, which is what makes an unticked checkbox read as unanswered
 * rather than as the answer "no".
 *
 * Evaluation is one non-recursive pass over current values. A condition on a
 * field that is itself conditionally hidden just reads that field's current
 * value, so two fields pointing at each other settle rather than loop.
 */
window.FieldConditions = (function () {
    'use strict';

    /**
     * The single scalar a field's conditions compare against — the mirror of
     * customFieldPostedValue() in PHP. Collapses multi-input types to the part
     * that decides whether the field counts as answered.
     */
    function readValue(dynRoot, fieldId) {
        if (!dynRoot) return '';
        var wrap = dynRoot.querySelector(
            '.dynamic-field-wrap[data-field-kind="custom"][data-field-key="' + fieldId + '"]'
        );
        if (!wrap) return '';

        var picker = wrap.querySelector('.user-picker-value');
        if (picker) return picker.value || '';

        var checkbox = wrap.querySelector('input[type="checkbox"]');
        if (checkbox) return checkbox.checked ? '1' : '0';

        var dependent = wrap.querySelector('.dep-l1');
        if (dependent) return dependent.value || '';

        // CC: PHP takes the first selected id, so match that.
        var ccHidden = wrap.querySelector('input[name^="cc_field_"]');
        if (ccHidden) return ccHidden.value || '';

        // date_range renders _from before _to, so the generic lookup below
        // already picks the same half the PHP does.
        var el = wrap.querySelector('select, textarea, input[type="text"], input[type="date"], input[type="number"]');
        return el ? (el.value || '') : '';
    }

    function evaluate(cond, ctx) {
        if (!cond || !cond.type) return true;

        if (cond.type === 'shared_account') {
            return !!ctx.isSharedAccount;
        }

        if (cond.type === 'field') {
            var actual = readValue(ctx.dynRoot, cond.field);
            var want   = cond.value == null ? '' : String(cond.value);
            switch (cond.op) {
                case 'filled':     return actual !== '' && actual !== '0';
                case 'empty':      return actual === '' || actual === '0';
                case 'not_equals': return actual !== want;
                case 'equals':
                default:           return actual === want;
            }
        }

        return true;
    }

    /**
     * Re-run the page's layout pass whenever anything inside the dynamic-field
     * area changes, so a field gated on another field appears as soon as its
     * trigger is answered. Delegated, because the fields it watches are shown
     * and hidden underneath it.
     */
    function watch(dynRoot, apply) {
        if (!dynRoot) return;
        ['change', 'input'].forEach(function (evt) {
            dynRoot.addEventListener(evt, function (e) {
                if (e.target && e.target.closest('.dynamic-field-wrap')) apply();
            });
        });
    }

    return { evaluate: evaluate, readValue: readValue, watch: watch };
})();
