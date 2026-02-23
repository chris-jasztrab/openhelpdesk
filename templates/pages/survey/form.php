<?php
$layout    = 'auth';
$pageTitle = 'Rate Your Experience';
?>
<div class="container" style="max-width:520px; width:100%; padding:24px 16px;">
    <div class="card border-0 shadow-lg" style="border-radius:12px; overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#1e1b4b,#312e81); padding:20px 28px;">
            <h1 style="margin:0; color:#fff; font-size:20px; font-weight:700;">
                <?= e($appName) ?>
            </h1>
        </div>

        <div class="card-body p-4">

            <?php if ($alreadyDone): ?>

                <!-- Already submitted -->
                <div class="text-center py-3">
                    <div style="font-size:48px; margin-bottom:12px;">✅</div>
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
                    <!-- Hidden rating input, updated by JS -->
                    <input type="hidden" name="rating" id="ratingInput" value="<?= $preselect > 0 ? $preselect : '' ?>">

                    <!-- Star selector -->
                    <div class="d-flex gap-2 mb-4" id="starRow">
                        <?php
                        $labels = ['1' => 'Poor', '2' => 'Fair', '3' => 'Good', '4' => 'Great', '5' => 'Excellent'];
                        for ($i = 1; $i <= 5; $i++):
                            $active = $preselect === $i;
                        ?>
                        <button type="button"
                                class="btn star-btn flex-fill <?= $active ? 'star-active' : '' ?>"
                                data-value="<?= $i ?>"
                                style="border:2px solid <?= $active ? e($brandColor) : '#e2e8f0' ?>;
                                       background:<?= $active ? e($brandColor) : '#f8fafc' ?>;
                                       color:<?= $active ? '#fff' : '#475569' ?>;
                                       border-radius:10px; padding:10px 4px; font-size:.8rem;
                                       transition:all .15s;">
                            <div style="font-size:22px; line-height:1; margin-bottom:4px;"><?= $i ?>★</div>
                            <div style="font-size:11px;"><?= $labels[(string) $i] ?></div>
                        </button>
                        <?php endfor; ?>
                    </div>

                    <!-- Optional comment -->
                    <div class="mb-4">
                        <label for="comment" class="form-label fw-semibold" style="font-size:.875rem;">
                            Additional comments <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <textarea id="comment" name="comment" class="form-control" rows="3"
                                  placeholder="Tell us more about your experience…"
                                  maxlength="2000" style="font-size:.875rem; resize:vertical;"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn w-100 text-white fw-semibold"
                            style="background:<?= e($brandColor) ?>; border-color:<?= e($brandColor) ?>; padding:10px;">
                        <i class="bi bi-send me-1"></i>Submit Rating
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:.75rem; color:rgba(255,255,255,.6);">
        Powered by <?= e($appName) ?>
    </p>
</div>

<script>
(function () {
    var brandColor = <?= json_encode($brandColor) ?>;
    var btns = document.querySelectorAll('.star-btn');
    var input = document.getElementById('ratingInput');

    function selectStar(val) {
        btns.forEach(function (b) {
            var active = parseInt(b.dataset.value, 10) === val;
            b.style.border = '2px solid ' + (active ? brandColor : '#e2e8f0');
            b.style.background = active ? brandColor : '#f8fafc';
            b.style.color = active ? '#fff' : '#475569';
        });
        input.value = val;
    }

    btns.forEach(function (b) {
        b.addEventListener('click', function () {
            selectStar(parseInt(b.dataset.value, 10));
        });
    });
})();
</script>
