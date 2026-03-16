<?php
$layout    = 'auth';
$pageTitle = 'Ticket Reopened';
?>
<style>
@keyframes pop-in {
    0%   { transform: scale(0.3) rotate(-10deg); opacity: 0; }
    70%  { transform: scale(1.2) rotate(3deg); }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-10px); }
}
.reopen-emoji {
    animation: pop-in .5s cubic-bezier(.34,1.56,.64,1) forwards, float 3s ease-in-out 0.5s infinite;
    display: inline-block;
}
</style>

<div class="container" style="max-width:480px; width:100%; padding:24px 16px;">
    <div class="card border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5,#06b6d4); padding:22px 28px;">
            <h1 style="margin:0; color:#fff; font-size:20px; font-weight:700;">
                <?= e($appName) ?>
            </h1>
        </div>

        <div class="card-body p-4 text-center">

            <div style="font-size:72px; margin-bottom:8px;">
                <span class="reopen-emoji">🔓</span>
            </div>

            <div style="display:inline-block; background:#fee2e2; border-radius:999px; padding:6px 18px; margin-bottom:16px;">
                <span style="font-weight:700; color:#dc2626; font-size:.9rem;">Ticket Reopened</span>
            </div>

            <h3 class="fw-bold mb-2">We're on it!</h3>
            <p class="text-muted mb-1" style="font-size:.9rem;">
                Ticket <strong>#<?= (int) $ticketId ?></strong> has been reopened and our team has been notified.
            </p>
            <p class="text-muted mb-0" style="font-size:.875rem;">
                We're sorry the issue wasn't fully resolved. Someone will follow up with you shortly. 💙
            </p>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color:rgba(255,255,255,.6);">
        Powered by <?= e($appName) ?>
    </p>
</div>
