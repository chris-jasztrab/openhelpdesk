<?php
$layout    = 'auth';
$pageTitle = 'Thank You';

$rating = (int) $survey['rating'];
$emojiData = [
    1 => ['face' => '😣', 'label' => 'Terrible',  'color' => '#dc2626', 'bg' => '#fee2e2'],
    2 => ['face' => '😕', 'label' => 'Poor',      'color' => '#ea580c', 'bg' => '#fff7ed'],
    3 => ['face' => '😐', 'label' => 'Okay',      'color' => '#ca8a04', 'bg' => '#fefce8'],
    4 => ['face' => '😊', 'label' => 'Good',      'color' => '#16a34a', 'bg' => '#f0fdf4'],
    5 => ['face' => '😄', 'label' => 'Excellent', 'color' => '#2563eb', 'bg' => '#eff6ff'],
];
$em = $emojiData[$rating] ?? $emojiData[3];
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
.thanks-emoji {
    animation: pop-in .5s cubic-bezier(.34,1.56,.64,1) forwards, float 3s ease-in-out 0.5s infinite;
    display: inline-block;
}
@keyframes confetti-fall {
    0%   { transform: translateY(-20px) rotate(0deg); opacity: 1; }
    100% { transform: translateY(60px) rotate(360deg); opacity: 0; }
}
.confetti-piece {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 2px;
    animation: confetti-fall 1.5s ease-in forwards;
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

        <div class="card-body p-4 text-center" style="position:relative; overflow:hidden;">

            <!-- Confetti container -->
            <div id="confetti-box" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;"></div>

            <!-- Big animated emoji -->
            <div style="font-size:72px; margin-bottom:8px; position:relative; z-index:1;">
                <span class="thanks-emoji"><?= $em['face'] ?></span>
            </div>

            <!-- Rating badge -->
            <div style="display:inline-block; background:<?= $em['bg'] ?>; border-radius:999px; padding:6px 18px; margin-bottom:16px;">
                <span style="font-weight:700; color:<?= $em['color'] ?>; font-size:.9rem;"><?= htmlspecialchars($em['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <h3 class="fw-bold mb-2">Thank you for your feedback!</h3>
            <p class="text-muted mb-1" style="font-size:.875rem;">
                You rated ticket <strong>#<?= (int) $survey['ticket_id'] ?></strong>
            </p>
            <p class="text-muted mb-0" style="font-size:.875rem;">
                Your feedback helps us keep improving our support. 💙
            </p>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color:rgba(255,255,255,.6);">
        Powered by <?= e($appName) ?>
    </p>
</div>

<script>
(function () {
    var colors = ['#7c3aed','#4f46e5','#06b6d4','#f59e0b','#10b981','#ef4444','#ec4899'];
    var box    = document.getElementById('confetti-box');
    for (var i = 0; i < 22; i++) {
        (function (i) {
            setTimeout(function () {
                var el = document.createElement('div');
                el.className = 'confetti-piece';
                el.style.left            = Math.random() * 100 + '%';
                el.style.top             = Math.random() * 30 + '%';
                el.style.background      = colors[Math.floor(Math.random() * colors.length)];
                el.style.animationDelay  = (Math.random() * 0.6) + 's';
                el.style.animationDuration = (1.2 + Math.random() * 0.8) + 's';
                box.appendChild(el);
            }, i * 40);
        })(i);
    }
})();
</script>
