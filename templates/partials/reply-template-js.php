<?php
/**
 * Per-user standard reply templates (My Profile › Reply Templates).
 * Required by the agent AND admin ticket views; expects $ticket in scope.
 *
 * Defines window.ReplyTemplate:
 *   apply(mode)  — fill the reply editor with the user's template for that mode
 *                  (reply / forward / note), unless they've typed something.
 *   untouched()  — the editor holds only an unedited template; the draft
 *                  autosave and the submit handler treat that as empty.
 */
?>
<script>
window.ReplyTemplate = (function () {
    var templates = <?= json_encode((object) userReplyTemplates((int) Auth::id(), $ticket), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var pristine  = null; // editor HTML straight after a template went in

    function untouched() {
        var ed = window._replyEditor;
        return pristine !== null && !!ed && ed.getData() === pristine;
    }

    function apply(mode) {
        var ed = window._replyEditor;
        mode = mode || document.getElementById('replyForm').dataset.mode;
        if (!ed || !mode) return;
        if (ed.getData() !== '' && !untouched()) return; // real content — leave it alone
        var html = templates[mode] || '';
        ed.setData(html);
        pristine = html ? ed.getData() : null;
        if (!html) return;
        // Caret on the first blank line (between greeting and sign-off), else at the end.
        ed.model.change(function (writer) {
            var root = ed.model.document.getRoot();
            var target = root.getChild(root.childCount - 1), where = 'end';
            for (var i = 0; i < root.childCount; i++) {
                if (root.getChild(i).isEmpty) { target = root.getChild(i); where = 0; break; }
            }
            writer.setSelection(target, where);
        });
    }

    return { apply: apply, untouched: untouched };
})();
</script>
