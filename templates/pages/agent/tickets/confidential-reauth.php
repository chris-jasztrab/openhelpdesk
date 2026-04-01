<?php
$layout       = 'app';
$pageTitle    = 'Confidential Ticket #' . $ticketId;
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets', 'url' => '/agent/tickets'],
    ['label' => '#' . $ticketId],
];
?>

<div class="row justify-content-center mt-5">
    <div class="col-lg-5 col-md-7">
        <div class="card border-warning shadow-sm">
            <div class="card-header bg-warning bg-opacity-10 text-center py-3">
                <i class="bi bi-shield-lock-fill text-warning" style="font-size:2.5rem;"></i>
                <h4 class="fw-bold mt-2 mb-0">Confidential Ticket</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">
                    This ticket belongs to a <strong>confidential</strong> ticket type. Only members of the assigned group can normally view it.
                </p>

                <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                    <div>
                        <strong>Security Notice:</strong> Your access will be recorded in the audit log and all members of the assigned group will be notified via email that you viewed this ticket.
                    </div>
                </div>

                <p class="fw-semibold mb-3">Please re-enter your password to continue:</p>

                <form method="POST" action="/agent/tickets/<?= (int) $ticketId ?>/confidential-auth">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               required autofocus placeholder="Enter your password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning text-dark fw-semibold flex-fill">
                            <i class="bi bi-unlock me-1"></i>Authenticate &amp; View Ticket
                        </button>
                        <a href="/agent/tickets" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
