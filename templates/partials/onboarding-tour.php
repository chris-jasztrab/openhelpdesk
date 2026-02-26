<!-- Onboarding Tour Modal -->
<div class="modal fade" id="onboardingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="onboardingModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width:580px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

            <!-- Gradient header strip -->
            <div style="height:6px; background:linear-gradient(90deg, var(--ld-primary) 0%, #7c3aed 100%);"></div>

            <div class="modal-body p-0">
                <div id="tourSteps">

                    <!-- Step 1: Welcome -->
                    <div class="tour-step" data-step="1">
                        <div class="text-center px-5 py-4">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                 style="width:72px;height:72px;background:var(--ld-primary);font-size:2rem;color:#fff;">
                                <i class="bi bi-rocket-takeoff-fill"></i>
                            </div>
                            <h4 class="fw-bold mb-2">Welcome to LocalDesk!</h4>
                            <p class="text-muted mb-0">
                                Your help desk is installed and ready. This short tour covers the
                                <strong>essential setup steps</strong> to get your system fully operational.
                                It only takes a few minutes.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2: Locations -->
                    <div class="tour-step d-none" data-step="2">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#eff6ff;font-size:1.5rem;color:var(--ld-primary);">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Step 1 — Add <?= e(label('location.plural', 'Locations')) ?></h5>
                            </div>
                            <p class="text-muted mb-3">
                                <?= e(label('location.plural', 'Locations')) ?> represent the physical sites or offices your team supports
                                (e.g. different library branches, buildings, or departments).
                                Tickets can be tagged to a <?= e(label('location.singular', 'location')) ?> so you can filter,
                                assign, and report by site.
                            </p>
                            <div class="alert alert-info small py-2 mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                You can rename "<?= e(label('location.singular', 'Location')) ?>" to anything — Branch, Site, Campus — under
                                <strong>Settings → Labels</strong>.
                            </div>
                            <a href="/admin/locations/create" class="btn text-white w-100" style="background:var(--ld-primary);">
                                <i class="bi bi-plus-lg me-1"></i>Add your first <?= e(label('location.singular', 'Location')) ?>
                            </a>
                            <p class="text-muted small text-center mt-2 mb-0">You can add more anytime under <strong>Admin → Settings → <?= e(label('location.plural', 'Locations')) ?></strong>.</p>
                        </div>
                    </div>

                    <!-- Step 3: Ticket Types -->
                    <div class="tour-step d-none" data-step="3">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#fdf4ff;font-size:1.5rem;color:#9333ea;">
                                    <i class="bi bi-tags-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Step 2 — Ticket Types</h5>
                            </div>
                            <p class="text-muted mb-3">
                                Ticket types let you categorise what kind of request each ticket is —
                                for example: <em>Hardware</em>, <em>Software</em>, <em>Access Request</em>,
                                <em>Maintenance</em>. Adding types now makes filtering and reporting much more useful.
                            </p>
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <?php foreach (['Hardware', 'Software', 'Access Request', 'Network', 'Maintenance', 'General'] as $eg): ?>
                                <span class="badge bg-secondary py-1 px-2" style="font-size:.8rem;"><?= $eg ?></span>
                                <?php endforeach; ?>
                            </div>
                            <a href="/admin/types" class="btn text-white w-100" style="background:var(--ld-primary);">
                                <i class="bi bi-tags me-1"></i>Set Up Ticket Types
                            </a>
                            <p class="text-muted small text-center mt-2 mb-0">You can add or edit types anytime under <strong>Admin → Ticket Types</strong>.</p>
                        </div>
                    </div>

                    <!-- Step 4: Priorities -->
                    <div class="tour-step d-none" data-step="4">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#fff7ed;font-size:1.5rem;color:#ea580c;">
                                    <i class="bi bi-flag-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Step 3 — Priorities</h5>
                            </div>
                            <p class="text-muted mb-3">
                                Four default priorities have been created for you. You can rename them,
                                change their colours, or add new ones to match your team's workflow.
                            </p>
                            <div class="d-flex flex-column gap-2 mb-3">
                                <?php foreach ([
                                    ['Low',      '#198754'],
                                    ['Medium',   '#ffc107'],
                                    ['High',     '#fd7e14'],
                                    ['Critical', '#dc3545'],
                                ] as [$name, $color]): ?>
                                <div class="d-flex align-items-center gap-2 border rounded px-3 py-2">
                                    <span class="rounded-circle" style="width:12px;height:12px;background:<?= $color ?>;flex-shrink:0;"></span>
                                    <span class="fw-medium small"><?= $name ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="/admin/priorities" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-flag me-1"></i>Customise Priorities
                            </a>
                        </div>
                    </div>

                    <!-- Step 5: Email -->
                    <div class="tour-step d-none" data-step="5">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#fef9c3;font-size:1.5rem;color:#ca8a04;">
                                    <i class="bi bi-envelope-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Step 4 — Configure Email</h5>
                            </div>
                            <p class="text-muted mb-3">
                                LocalDesk sends email notifications to agents and users when tickets are created,
                                updated, or resolved. <strong>Without SMTP configured, no emails will be sent.</strong>
                            </p>
                            <ul class="text-muted small mb-3" style="line-height:1.9;">
                                <li>New ticket confirmation to the submitter</li>
                                <li>Agent assignment and reply notifications</li>
                                <li>Status change and resolution emails</li>
                                <li>CSAT survey emails on ticket close</li>
                            </ul>
                            <a href="/admin/settings" class="btn text-white w-100" style="background:var(--ld-primary);">
                                <i class="bi bi-envelope me-1"></i>Configure SMTP Settings
                            </a>
                        </div>
                    </div>

                    <!-- Step 6: Done -->
                    <div class="tour-step d-none" data-step="6">
                        <div class="text-center px-5 py-4">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                 style="width:72px;height:72px;background:#f0fdf4;font-size:2rem;color:#16a34a;">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <h4 class="fw-bold mb-2">You're all set!</h4>
                            <p class="text-muted mb-4">A few more things worth setting up when you have time:</p>
                            <div class="d-flex flex-column gap-2 text-start">
                                <a href="/admin/users/create" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-person-plus"></i> Add agents to your team
                                </a>
                                <a href="/admin/settings/sla-policies" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-stopwatch"></i> Set up SLA policies
                                </a>
                                <a href="/admin/settings/business-hours" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-clock"></i> Configure business hours
                                </a>
                                <a href="/admin/settings/cron-jobs" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-clock-history"></i> Set up background cron jobs
                                </a>
                            </div>
                        </div>
                    </div>

                </div><!-- /tourSteps -->
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 px-5 pb-4 pt-0 d-flex justify-content-between align-items-center">
                <!-- Step dots -->
                <div class="d-flex gap-1" id="tourDots">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="rounded-circle tour-dot"
                         style="width:8px;height:8px;background:<?= $i === 1 ? 'var(--ld-primary)' : '#cbd5e1' ?>;transition:background .2s;"
                         data-dot="<?= $i ?>"></div>
                    <?php endfor; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm invisible" id="tourPrev">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                    <button type="button" class="btn btn-sm text-white" id="tourNext"
                            style="background:var(--ld-primary);">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <form method="POST" action="/admin/onboarding/dismiss" id="tourDismissForm" class="d-none">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                            <i class="bi bi-check-lg me-1"></i>Get Started
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Defer until Bootstrap is ready (Bootstrap JS loads after page content)
document.addEventListener('DOMContentLoaded', function () {
    var autoShow = <?= ($autoShowTour ?? false) ? 'true' : 'false' ?>;
    var totalSteps = 6;
    var current = 1;

    var modalEl  = document.getElementById('onboardingModal');
    var modal    = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    var prevBtn  = document.getElementById('tourPrev');
    var nextBtn  = document.getElementById('tourNext');
    var dismissF = document.getElementById('tourDismissForm');
    var dots     = document.querySelectorAll('.tour-dot');

    function goTo(n) {
        document.querySelectorAll('.tour-step').forEach(function (s) { s.classList.add('d-none'); });
        current = n;
        document.querySelector('.tour-step[data-step="' + current + '"]').classList.remove('d-none');

        dots.forEach(function (d) {
            d.style.background = parseInt(d.dataset.dot) === current ? 'var(--ld-primary)' : '#cbd5e1';
        });

        prevBtn.classList.toggle('invisible', current === 1);

        if (current === totalSteps) {
            nextBtn.classList.add('d-none');
            dismissF.classList.remove('d-none');
        } else {
            nextBtn.classList.remove('d-none');
            dismissF.classList.add('d-none');
        }
    }

    modalEl.addEventListener('show.bs.modal', function () { goTo(1); });
    nextBtn.addEventListener('click', function () { if (current < totalSteps) goTo(current + 1); });
    prevBtn.addEventListener('click', function () { if (current > 1) goTo(current - 1); });

    if (autoShow) { modal.show(); }
});
</script>
