<?php
$layout    = 'auth';
$pageTitle = 'Reopen Ticket';
?>
<div class="container" style="max-width:480px; width:100%; padding:24px 16px;">
    <div class="card border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5,#06b6d4); padding:22px 28px;">
            <h1 style="margin:0; color:#fff; font-size:20px; font-weight:700;">
                <?= e($appName) ?>
            </h1>
        </div>

        <div class="card-body p-4 text-center">
            <div style="font-size:64px; margin-bottom:8px;">🔓</div>
            <h3 class="fw-bold mb-2">Reopen this ticket?</h3>
            <p class="text-muted mb-4" style="font-size:.9rem;">
                Ticket <strong>#<?= (int) $ticketId ?></strong> will be reopened and our team notified.
            </p>
            <?php // A POST form (not a bare link) so that email-security scanners and
                  // link-preview/prefetch bots can't silently reopen the ticket just by
                  // fetching the URL. The 64-char token authorises the action. ?>
            <form method="POST" action="/survey/<?= e($token) ?>/reopen">
                <button type="submit" class="btn btn-lg text-white px-4"
                        style="background:linear-gradient(135deg,#7c3aed,#4f46e5); border:0;">
                    Yes, reopen it
                </button>
            </form>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color:rgba(255,255,255,.6);">
        Powered by <?= e($appName) ?>
    </p>
</div>
