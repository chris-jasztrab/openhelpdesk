<!-- Muted ("soft chip") badge styles ------------------------------------------
     Tones down every label/chip in the app — status, priority, type, tag, and
     any other badge whose colour is set inline — without losing the colour's
     identity. Used to be vivid, saturated swatches with white text; now a
     light tint of the original colour with dark text (or the inverse in dark
     mode). Loaded site-wide from the app and base layouts.

     How the inline-style override works: the inline `style="background:#xxx"`
     compiles to `background-color: #xxx; background-image: none; ...`. The
     !important `background-image` declaration below survives the inline
     shorthand (which is not marked !important) and paints a translucent
     white overlay on top of the original colour, so a deep red becomes a
     pale pink while still reading as "red". An empty-text swatch can opt
     out by adding the `.badge-vivid` class.
-->
<style>
    /* Bootstrap-themed status badges ------------------------------------- */
    .badge.bg-primary   { background-color: #dbeafe !important; color: #1e3a8a !important; }
    .badge.bg-secondary { background-color: #e2e8f0 !important; color: #334155 !important; }
    .badge.bg-success   { background-color: #dcfce7 !important; color: #14532d !important; }
    .badge.bg-info      { background-color: #cffafe !important; color: #155e75 !important; }
    .badge.bg-warning   { background-color: #fef3c7 !important; color: #78350f !important; }
    .badge.bg-danger    { background-color: #fee2e2 !important; color: #7f1d1d !important; }
    .badge.bg-dark      { background-color: #cbd5e1 !important; color: #0f172a !important; }
    /* .bg-light is already pale; leave it. */

    /* Inline-styled badges (priority, type, tag, ...) -------------------- */
    .badge[style*="background"]:not(.badge-vivid) {
        background-image: linear-gradient(rgba(255,255,255,.82), rgba(255,255,255,.82)) !important;
        color: #1e293b !important;
        font-weight: 600;
    }

    /* Dark mode --------------------------------------------------------- */
    [data-bs-theme="dark"] .badge.bg-primary   { background-color: rgba(59,130,246,.18) !important; color: #93c5fd !important; }
    [data-bs-theme="dark"] .badge.bg-secondary { background-color: rgba(148,163,184,.18) !important; color: #cbd5e1 !important; }
    [data-bs-theme="dark"] .badge.bg-success   { background-color: rgba(34,197,94,.18)  !important; color: #86efac !important; }
    [data-bs-theme="dark"] .badge.bg-info      { background-color: rgba(14,165,233,.18) !important; color: #7dd3fc !important; }
    [data-bs-theme="dark"] .badge.bg-warning   { background-color: rgba(245,158,11,.18) !important; color: #fcd34d !important; }
    [data-bs-theme="dark"] .badge.bg-danger    { background-color: rgba(239,68,68,.18)  !important; color: #fca5a5 !important; }
    [data-bs-theme="dark"] .badge.bg-dark      { background-color: rgba(100,116,139,.22)!important; color: #cbd5e1 !important; }

    /* In dark mode, wash inline backgrounds toward black instead of white
       so a saturated colour reads as a muted dark chip with light text. */
    [data-bs-theme="dark"] .badge[style*="background"]:not(.badge-vivid) {
        background-image: linear-gradient(rgba(0,0,0,.55), rgba(0,0,0,.55)) !important;
        color: #e2e8f0 !important;
    }
</style>
