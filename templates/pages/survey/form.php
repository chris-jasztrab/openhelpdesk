<?php
$layout    = 'auth';
$pageTitle = 'Rate Your Experience';
?>
<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0) scale(1); }
    50%       { transform: translateY(-8px) scale(1.1); }
}
@keyframes wobble {
    0%, 100% { transform: rotate(0deg) scale(1); }
    25%       { transform: rotate(-10deg) scale(1.05); }
    75%       { transform: rotate(10deg) scale(1.05); }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.18); }
}
@keyframes spin-pop {
    0%   { transform: rotate(0deg) scale(1); }
    25%  { transform: rotate(-5deg) scale(1.12); }
    75%  { transform: rotate(5deg) scale(1.12); }
    100% { transform: rotate(0deg) scale(1); }
}
.anim-wobble { animation: wobble 2s ease-in-out infinite; }
.anim-bounce { animation: bounce 1.8s ease-in-out infinite 0.1s; }
.anim-pulse  { animation: pulse 2.2s ease-in-out infinite 0.2s; }
.anim-spin   { animation: spin-pop 2s ease-in-out infinite 0.15s; }

.emoji-btn {
    border: 3px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 14px;
    padding: 14px 6px 10px;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
    flex: 1;
    text-align: center;
    outline: none;
}
.emoji-btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,.12);
}
.emoji-btn.active {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,.15);
}
.emoji-face {
    font-size: 36px;
    line-height: 1;
    margin-bottom: 6px;
    display: block;
}
.emoji-label {
    font-size: 11px;
    font-weight: 700;
    display: block;
}
</style>

<div class="container" style="max-width:540px; width:100%; padding:24px 16px;">
    <div class="card border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5,#06b6d4); padding:22px 28px;">
            <h1 style="margin:0; color:#fff; font-size:20px; font-weight:700;">
                <?= e($appName) ?>
            </h1>
        </div>

        <div class="card-body p-4">

            <?php if ($alreadyDone): ?>

                <!-- Already submitted -->
                <div class="text-center py-3">
                    <div style="font-size:52px; margin-bottom:12px;">✅</div>
                    <h4 class="fw-bold mb-2">Already submitted</h4>
                    <p class="text-muted mb-0">You've already submitted your rating for this ticket. Thank you!</p>
                </div>

            <?php else: ?>

                <h4 class="fw-bold mb-1">How did we do?</h4>
                <p class="text-muted mb-1" style="font-size:.9rem;">
                    Ticket <strong>#<?= (int) $survey['ticket_id'] ?></strong> &mdash;
                    <?= e($survey['subject']) ?>
                </p>
                <p class="text-muted mb-4" style="font-size:.875rem;">
                    Your feedback helps us improve. It only takes a second!
                </p>

                <?php if ($error === 'rating'): ?>
                <div class="alert alert-danger py-2 mb-3" style="font-size:.875rem;">
                    <i class="bi bi-exclamation-circle me-1"></i>Please select a rating before submitting.
                </div>
                <?php endif; ?>

                <form method="POST" action="/survey/<?= e($survey['token']) ?>">
                    <input type="hidden" name="rating" id="ratingInput" value="<?= $preselect > 0 ? $preselect : '' ?>">

                    <!-- Emoji selector -->
                    <?php
                    $emojiData = [
                        1 => ['face' => '😣', 'label' => 'Terrible',  'bg' => '#fee2e2', 'border' => '#fca5a5', 'color' => '#dc2626', 'anim' => 'anim-wobble'],
                        2 => ['face' => '😕', 'label' => 'Poor',      'bg' => '#fff7ed', 'border' => '#fdba74', 'color' => '#ea580c', 'anim' => 'anim-bounce'],
                        3 => ['face' => '😐', 'label' => 'Okay',      'bg' => '#fefce8', 'border' => '#fde047', 'color' => '#ca8a04', 'anim' => 'anim-pulse'],
                        4 => ['face' => '😊', 'label' => 'Good',      'bg' => '#f0fdf4', 'border' => '#86efac', 'color' => '#16a34a', 'anim' => 'anim-spin'],
                        5 => ['face' => '😄', 'label' => 'Excellent', 'bg' => '#eff6ff', 'border' => '#93c5fd', 'color' => '#2563eb', 'anim' => 'anim-bounce'],
                    ];
                    ?>
                    <div class="d-flex gap-2 mb-4" id="emojiRow">
                        <?php foreach ($emojiData as $value => $em):
                            $active = $preselect === $value;
                        ?>
                        <button type="button"
                                class="emoji-btn <?= $active ? 'active' : '' ?>"
                                data-value="<?= $value ?>"
                                data-bg="<?= $em['bg'] ?>"
                                data-border="<?= $em['border'] ?>"
                                data-color="<?= $em['color'] ?>"
                                style="border-color:<?= $active ? $em['border'] : '#e2e8f0' ?>;
                                       background:<?= $active ? $em['bg'] : '#f8fafc' ?>;">
                            <span class="emoji-face <?= $em['anim'] ?>"><?= $em['face'] ?></span>
                            <span class="emoji-label" style="color:<?= $active ? $em['color'] : '#94a3b8' ?>;"><?= $em['label'] ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Optional comment -->
                    <div class="mb-4">
                        <label for="comment" class="form-label fw-semibold" style="font-size:.875rem;">
                            Want to share more? <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <textarea id="comment" name="comment" class="form-control" rows="3"
                                  placeholder="Tell us what we could do better, or what we did great…"
                                  maxlength="2000" style="font-size:.875rem; resize:vertical;"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn w-100 text-white fw-semibold"
                            style="background:linear-gradient(135deg,#7c3aed,#4f46e5); border:none; padding:12px; font-size:1rem; border-radius:10px;">
                        <i class="bi bi-send me-1"></i>Submit My Rating
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="/survey/<?= e($survey['token']) ?>/reopen"
                       style="font-size:.8rem; color:#94a3b8; text-decoration:none;">
                        Issue not resolved? <span style="text-decoration:underline;">Click here to reopen your ticket</span>
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color:rgba(255,255,255,.6);">
        Powered by <?= e($appName) ?>
    </p>
</div>

<script>
(function () {
    var btns  = document.querySelectorAll('.emoji-btn');
    var input = document.getElementById('ratingInput');

    function selectEmoji(val) {
        btns.forEach(function (b) {
            var active = parseInt(b.dataset.value, 10) === val;
            b.style.borderColor = active ? b.dataset.border : '#e2e8f0';
            b.style.background  = active ? b.dataset.bg    : '#f8fafc';
            b.querySelector('.emoji-label').style.color = active ? b.dataset.color : '#94a3b8';
            if (active) {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });
        input.value = val;
    }

    // Apply preselect
    var preselect = parseInt(input.value, 10);
    if (preselect >= 1 && preselect <= 5) {
        selectEmoji(preselect);
    }

    btns.forEach(function (b) {
        b.addEventListener('click', function () {
            selectEmoji(parseInt(b.dataset.value, 10));
        });
    });
})();
</script>
