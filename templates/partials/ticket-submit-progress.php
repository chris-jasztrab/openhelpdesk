<?php
/**
 * Shared "we're working on it" cycling status for the submit button on
 * every ticket-create flow (portal, agent, floor). Replaces the static
 * "Checking for duplicates…" label with a rotating list of playful
 * phrases so the requester knows the click registered and something is
 * still happening — without lying about what step the server is on.
 *
 * Defines window.startTicketSubmitProgress(btn) returning a stopper:
 *   const progress = window.startTicketSubmitProgress(submitBtn);
 *   ...
 *   progress.stop();   // restores original label + re-enables button
 *
 * Phrases cycle every ~1.8s in a shuffled order, starting with
 * "Checking for duplicates…" so the first frame still tells the user
 * what we're actually doing. Fast responses (<1.8s) only ever see that
 * first frame, so we never lie about what's happening for slow steps.
 */
?>
<script>
window.startTicketSubmitProgress = (function () {
    const PHRASES = [
        'Phoning a friend…',
        'Flashing the bat signal…',
        'Putting your ticket in the paper shredder…',
        'Waking the help desk gnomes…',
        'Bribing the printer with cookies…',
        'Consulting the magic 8-ball…',
        'Sharpening every pencil in the building…',
        'Untangling the cables…',
        'Decoding ancient library scrolls…',
        'Polishing the crystal ball…',
        'Negotiating with the wifi router…',
        'Re-inking the stamp pad…',
        'Looking under the rug…'
    ];

    function shuffled(arr) {
        const a = arr.slice();
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            const t = a[i]; a[i] = a[j]; a[j] = t;
        }
        return a;
    }

    return function startTicketSubmitProgress(btn) {
        if (!btn) { return { stop: function () {} }; }

        // If already cycling on this button, stop the previous run so the
        // caller's stop() is the only one that controls visibility.
        if (btn._submitProgressStop) { btn._submitProgressStop(); }

        const origLabel = btn.innerHTML;
        const sequence  = ['Checking for duplicates…'].concat(shuffled(PHRASES));
        let i = 0;

        function show(idx) {
            btn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
              + sequence[idx % sequence.length];
        }

        btn.disabled = true;
        show(0);

        const timer = setInterval(function () {
            i++;
            show(i);
        }, 1800);

        const stop = function () {
            clearInterval(timer);
            btn.disabled    = false;
            btn.innerHTML   = origLabel;
            btn._submitProgressStop = null;
        };
        btn._submitProgressStop = stop;

        return { stop: stop };
    };
})();
</script>
