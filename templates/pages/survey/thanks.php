<?php
$layout    = 'auth';
$pageTitle = 'Thank You';
?>
<div class="container" style="max-width:480px; width:100%; padding:24px 16px;">
    <div class="card border-0 shadow-lg" style="border-radius:12px; overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#1e1b4b,#312e81); padding:20px 28px;">
            <h1 style="margin:0; color:#fff; font-size:20px; font-weight:700;">
                <?= e($appName) ?>
            </h1>
        </div>

        <div class="card-body p-4 text-center">
            <div style="font-size:56px; margin-bottom:16px;">🎉</div>

            <h3 class="fw-bold mb-2">Thank you for your feedback!</h3>

            <?php
            $stars    = (int) $survey['rating'];
            $maxStars = 5;
            ?>
            <div style="font-size:28px; margin-bottom:8px; letter-spacing:2px;">
                <?= str_repeat('★', $stars) ?><span style="color:#cbd5e1;"><?= str_repeat('★', $maxStars - $stars) ?></span>
            </div>
            <p class="text-muted mb-1" style="font-size:.875rem;">
                You rated ticket <strong>#<?= (int) $survey['ticket_id'] ?></strong>
                <strong><?= $stars ?> out of <?= $maxStars ?> stars</strong>.
            </p>
            <p class="text-muted mb-0" style="font-size:.875rem;">
                Your feedback helps us keep improving our support.
            </p>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color:rgba(255,255,255,.6);">
        Powered by <?= e($appName) ?>
    </p>
</div>
