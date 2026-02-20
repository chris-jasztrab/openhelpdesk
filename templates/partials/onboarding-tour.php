<!-- Onboarding Tour Modal -->
<div class="modal fade" id="onboardingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="onboardingModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width:580px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

            <!-- Gradient header strip -->
            <div style="height:6px; background:linear-gradient(90deg, var(--ld-primary) 0%, #7c3aed 100%);"></div>

            <div class="modal-body p-0">
                <!-- Step panel container -->
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
                                Your help desk is installed and ready to go.
                                This quick tour will show you where everything lives so you can get your team up and running fast.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2: Tickets -->
                    <div class="tour-step d-none" data-step="2">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#eff6ff;font-size:1.5rem;color:var(--ld-primary);">
                                    <i class="bi bi-ticket-detailed-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Tickets</h5>
                            </div>
                            <p class="text-muted mb-3">
                                The <strong>Tickets</strong> section is your central workspace. Here you can:
                            </p>
                            <ul class="text-muted mb-0" style="line-height:1.9;">
                                <li>View, filter and search all incoming tickets</li>
                                <li>Assign tickets to agents or groups</li>
                                <li>Change status, priority and ticket type</li>
                                <li>Add comments and internal notes</li>
                                <li>Merge duplicate tickets</li>
                                <li>Track the full timeline of every ticket event</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Step 3: Users & Agents -->
                    <div class="tour-step d-none" data-step="3">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#f0fdf4;font-size:1.5rem;color:#16a34a;">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Users &amp; Agents</h5>
                            </div>
                            <p class="text-muted mb-3">There are three roles in LocalDesk:</p>
                            <div class="d-flex flex-column gap-2 mb-3">
                                <div class="border rounded p-2 d-flex gap-2 align-items-center">
                                    <span class="badge" style="background:var(--ld-primary);">Admin</span>
                                    <span class="text-muted small">Full access — manage settings, users, tickets and reports.</span>
                                </div>
                                <div class="border rounded p-2 d-flex gap-2 align-items-center">
                                    <span class="badge bg-success">Agent</span>
                                    <span class="text-muted small">Handle tickets, add notes and manage assigned work.</span>
                                </div>
                                <div class="border rounded p-2 d-flex gap-2 align-items-center">
                                    <span class="badge bg-secondary">User</span>
                                    <span class="text-muted small">Submit and track their own tickets via the portal.</span>
                                </div>
                            </div>
                            <p class="text-muted mb-0 small">
                                Go to <strong>Admin → Users</strong> to invite your team or import users from a CSV.
                            </p>
                        </div>
                    </div>

                    <!-- Step 4: Settings -->
                    <div class="tour-step d-none" data-step="4">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#fef9c3;font-size:1.5rem;color:#ca8a04;">
                                    <i class="bi bi-sliders"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Settings</h5>
                            </div>
                            <p class="text-muted mb-2">Configure LocalDesk to match your organisation:</p>
                            <div class="row g-2">
                                <?php foreach ([
                                    ['bi-envelope',   'Email / SMTP',    'Connect your mail server'],
                                    ['bi-pencil-square','Email Templates','Customise notification emails'],
                                    ['bi-stopwatch',  'SLA Policies',    'Set response time targets'],
                                    ['bi-lightning',  'Automations',     'Auto-assign and auto-respond'],
                                    ['bi-palette',    'Branding',        'Logo, colours & timeline styles'],
                                    ['bi-clock',      'Business Hours',  'Define when SLA timers run'],
                                ] as [$icon, $label, $desc]): ?>
                                <div class="col-6">
                                    <div class="border rounded p-2 small">
                                        <i class="bi <?= $icon ?> me-1 text-muted"></i>
                                        <strong><?= $label ?></strong>
                                        <div class="text-muted" style="font-size:.8rem;"><?= $desc ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Portal & Knowledge Base -->
                    <div class="tour-step d-none" data-step="5">
                        <div class="px-5 py-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:56px;height:56px;background:#fdf4ff;font-size:1.5rem;color:#9333ea;">
                                    <i class="bi bi-globe2"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Portal &amp; Knowledge Base</h5>
                            </div>
                            <p class="text-muted mb-3">
                                Your end users interact with LocalDesk through the <strong>Portal</strong> at
                                <code>/portal</code>. From there they can:
                            </p>
                            <ul class="text-muted mb-3" style="line-height:1.9;">
                                <li>Submit new tickets and track their status</li>
                                <li>Reply to agent comments</li>
                                <li>Browse the Knowledge Base for self-service answers</li>
                            </ul>
                            <p class="text-muted mb-0 small">
                                Publish Knowledge Base articles under <strong>Admin → Knowledge Base</strong>
                                to reduce repetitive support requests.
                            </p>
                        </div>
                    </div>

                    <!-- Step 6: Done / Docs -->
                    <div class="tour-step d-none" data-step="6">
                        <div class="text-center px-5 py-4">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                 style="width:72px;height:72px;background:#f0fdf4;font-size:2rem;color:#16a34a;">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <h4 class="fw-bold mb-2">You're all set!</h4>
                            <p class="text-muted mb-4">
                                Here are a few good first steps to get LocalDesk fully configured for your team.
                            </p>
                            <div class="d-flex flex-column gap-2 text-start">
                                <a href="/admin/settings" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-envelope"></i> Configure SMTP email
                                </a>
                                <a href="/admin/settings/sla-policies" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-stopwatch"></i> Set up SLA policies
                                </a>
                                <a href="/admin/users/create" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-person-plus"></i> Add your first agent
                                </a>
                                <a href="/admin/docs" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <i class="bi bi-question-circle"></i> Browse the documentation
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
                         style="width:8px;height:8px;background:<?= $i === 1 ? 'var(--ld-primary)' : '#cbd5e1' ?>;
                                transition:background .2s;"
                         data-dot="<?= $i ?>"></div>
                    <?php endfor; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="tourPrev" style="display:none!important;">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                    <button type="button" class="btn btn-sm text-white" id="tourNext"
                            style="background:var(--ld-primary);">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <!-- Dismiss form (hidden until last step) -->
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
(function () {
    const totalSteps = 6;
    let current = 1;

    const modal     = new bootstrap.Modal(document.getElementById('onboardingModal'), { backdrop: 'static' });
    const prevBtn   = document.getElementById('tourPrev');
    const nextBtn   = document.getElementById('tourNext');
    const dismissF  = document.getElementById('tourDismissForm');
    const dots      = document.querySelectorAll('.tour-dot');

    modal.show();

    function goTo(n) {
        document.querySelector(`.tour-step[data-step="${current}"]`).classList.add('d-none');
        current = n;
        document.querySelector(`.tour-step[data-step="${current}"]`).classList.remove('d-none');

        // Update dots
        dots.forEach(d => {
            d.style.background = parseInt(d.dataset.dot) === current ? 'var(--ld-primary)' : '#cbd5e1';
        });

        // Prev button
        prevBtn.style.setProperty('display', current > 1 ? '' : 'none', 'important');

        // Next / dismiss toggle
        if (current === totalSteps) {
            nextBtn.style.display = 'none';
            dismissF.classList.remove('d-none');
        } else {
            nextBtn.style.display = '';
            dismissF.classList.add('d-none');
        }
    }

    nextBtn.addEventListener('click', () => { if (current < totalSteps) goTo(current + 1); });
    prevBtn.addEventListener('click', () => { if (current > 1) goTo(current - 1); });
})();
</script>
