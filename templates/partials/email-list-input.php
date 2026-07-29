<?php
/**
 * Reusable "list of email addresses" field.
 *
 * Renders a chip editor on top of a hidden input, so what actually posts is
 * still the newline-separated list the routes already parse — no server-side
 * change needed to adopt it.
 *
 * Typing is forgiving on purpose: Enter, comma, semicolon, space or Tab each
 * finish an address, pasting a list from a spreadsheet or an Outlook To: line
 * splits it into chips, and "Add me" fills in your own address (the common
 * case). Malformed addresses turn red and block submit rather than being
 * silently dropped, which is what a plain textarea did.
 *
 * Expects (all optional except the value):
 *   string $emailListValue    — current addresses, newline-separated
 *   string $emailListName     — form field name (default 'recipients')
 *   string $emailListId       — DOM id prefix, must be unique per page
 *   string $emailListLabel    — visible label ('' renders no label)
 *   string $emailListHelp     — help text under the field
 */

$elName  = $emailListName  ?? 'recipients';
$elId    = $emailListId    ?? 'emailList';
$elValue = (string) ($emailListValue ?? '');
$elLabel = $emailListLabel ?? 'Recipients';
$elHelp  = $emailListHelp  ?? 'Type an address and press Enter, or paste a whole list.';
$elSelf  = (string) (Auth::user()['email'] ?? '');

// The widget's CSS/JS is identical for every instance — emit it once per page.
$elFirst = !isset($GLOBALS['__emailListAssetsDone']);
$GLOBALS['__emailListAssetsDone'] = true;
?>
<?php if ($elLabel !== ''): ?>
<label class="form-label fw-semibold small" for="<?= e($elId) ?>Entry">
    <?= e($elLabel) ?> <span class="text-danger">*</span>
</label>
<?php endif; ?>

<div class="email-list" data-email-list data-self-email="<?= e($elSelf) ?>">
    <input type="hidden" name="<?= e($elName) ?>" value="<?= e($elValue) ?>" data-email-list-value>

    <div class="form-control email-list-box d-flex flex-wrap align-items-center gap-1" data-email-list-box>
        <span class="email-list-chips d-contents" data-email-list-chips></span>
        <input type="text" class="email-list-entry flex-grow-1" id="<?= e($elId) ?>Entry"
               data-email-list-entry autocomplete="off" spellcheck="false"
               inputmode="email" placeholder="name@example.com"
               aria-describedby="<?= e($elId) ?>Help">
    </div>

    <div class="d-flex align-items-center justify-content-between gap-2 mt-1 flex-wrap">
        <div class="form-text m-0" id="<?= e($elId) ?>Help"><?= e($elHelp) ?></div>
        <?php if ($elSelf !== ''): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-email-list-add-self>
            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add me
        </button>
        <?php endif; ?>
    </div>

    <div class="invalid-feedback d-block small" data-email-list-error role="alert" aria-live="polite"></div>
</div>

<?php if ($elFirst): ?>
<style>
.email-list-box { height: auto; min-height: calc(1.5em + .75rem + 2px); cursor: text; }
.email-list-entry { border: 0; background: transparent; color: inherit; outline: none; min-width: 14ch; padding: 0; }
.email-list-chip { max-width: 100%; }
.email-list-chip .email-list-chip-text { overflow: hidden; text-overflow: ellipsis; }
.email-list .d-contents { display: contents; }
</style>
<script>
(function () {
    // Deliberately loose: catches typos like "a@b" or "a b@c.com" without
    // rejecting the valid-but-unusual addresses a stricter pattern would.
    var RE = /^[^\s@,;]+@[^\s@,;]+\.[^\s@,;]{2,}$/;

    function init(root) {
        if (root.dataset.emailListReady) { return; }
        root.dataset.emailListReady = '1';

        var hidden   = root.querySelector('[data-email-list-value]');
        var box      = root.querySelector('[data-email-list-box]');
        var chipsEl  = root.querySelector('[data-email-list-chips]');
        var entry    = root.querySelector('[data-email-list-entry]');
        var errorEl  = root.querySelector('[data-email-list-error]');
        var addSelf  = root.querySelector('[data-email-list-add-self]');
        var form     = root.closest('form');
        var emails   = [];

        function split(text) {
            return text.split(/[,;\s]+/).map(function (s) { return s.trim(); })
                       .filter(function (s) { return s.length > 0; });
        }

        function sync() {
            hidden.value = emails.join('\n');
        }

        function render() {
            chipsEl.innerHTML = '';
            emails.forEach(function (email, i) {
                var bad  = !RE.test(email);
                var chip = document.createElement('span');
                chip.className = 'email-list-chip badge rounded-pill d-inline-flex align-items-center gap-1 '
                               + (bad ? 'text-bg-danger' : 'text-bg-secondary');
                if (bad) { chip.title = 'This does not look like an email address'; }

                var label = document.createElement('span');
                label.className = 'email-list-chip-text';
                label.textContent = email;
                chip.appendChild(label);

                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'btn-close btn-close-white';
                x.style.fontSize = '.5rem';
                x.setAttribute('aria-label', 'Remove ' + email);
                x.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    emails.splice(i, 1);
                    sync(); render(); validate(); entry.focus();
                });
                chip.appendChild(x);

                chipsEl.appendChild(chip);
            });
        }

        function validate() {
            var bad = emails.filter(function (e) { return !RE.test(e); });
            if (bad.length) {
                errorEl.textContent = bad.length === 1
                    ? '"' + bad[0] + '" is not a valid email address.'
                    : bad.length + ' addresses are not valid email addresses.';
                return false;
            }
            errorEl.textContent = '';
            return true;
        }

        function add(text) {
            var added = false;
            split(text).forEach(function (candidate) {
                if (emails.indexOf(candidate) === -1) { emails.push(candidate); added = true; }
            });
            if (added) { sync(); render(); }
            validate();
            return added;
        }

        function commitEntry() {
            if (entry.value.trim() === '') { return; }
            add(entry.value);
            entry.value = '';
        }

        // What the server rendered. Kept because a hidden input can't be reset
        // to it: assigning .value on type=hidden also moves defaultValue, so
        // form.reset() has nothing to restore and is a no-op here.
        var initial = hidden.value;

        function load(text) {
            emails = split(text === undefined ? hidden.value : text);
            sync(); render(); errorEl.textContent = '';
        }

        entry.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ',' || ev.key === ';') {
                ev.preventDefault();       // Enter must not submit the form mid-typing
                commitEntry();
            } else if (ev.key === ' ' && entry.value.trim() !== '') {
                ev.preventDefault();
                commitEntry();
            } else if (ev.key === 'Tab' && entry.value.trim() !== '') {
                commitEntry();             // commit, but still move focus
            } else if (ev.key === 'Backspace' && entry.value === '' && emails.length) {
                emails.pop();
                sync(); render(); validate();
            }
        });

        entry.addEventListener('paste', function (ev) {
            var text = (ev.clipboardData || window.clipboardData).getData('text');
            if (!text) { return; }
            ev.preventDefault();
            add(text);
            entry.value = '';
        });

        entry.addEventListener('blur', commitEntry);

        box.addEventListener('click', function (ev) {
            if (ev.target === box || ev.target === chipsEl) { entry.focus(); }
        });

        if (addSelf) {
            addSelf.addEventListener('click', function () {
                add(root.dataset.selfEmail || '');
                entry.focus();
            });
        }

        if (form) {
            form.addEventListener('submit', function (ev) {
                commitEntry();
                if (!emails.length) {
                    ev.preventDefault();
                    errorEl.textContent = 'Add at least one recipient.';
                    entry.focus();
                    return;
                }
                if (!validate()) {
                    ev.preventDefault();
                    entry.focus();
                }
            });
            // Restore the list the page was rendered with — this is what makes
            // the reopened "Schedule report" modal start clean instead of
            // carrying the last attempt's recipients.
            form.addEventListener('reset', function () {
                setTimeout(function () {
                    entry.value = '';
                    load(initial);
                }, 0);
            });
        }

        load();
    }

    function initAll() {
        document.querySelectorAll('[data-email-list]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
<?php endif; ?>
<?php
// Don't leak this instance's settings into the next include on the same page.
unset($emailListValue, $emailListName, $emailListId, $emailListLabel, $emailListHelp,
      $elName, $elId, $elValue, $elLabel, $elHelp, $elSelf, $elFirst);
?>
