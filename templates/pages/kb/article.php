<?php
$layout    = 'public';
$pageTitle = $article['title'] . ' – Help Center';
$breadcrumbs = [
    ['label' => 'Help Center',       'url' => '/kb'],
    ['label' => $article['category_name'], 'url' => '/kb/' . $article['category_slug']],
    ['label' => $article['folder_name'],   'url' => '/kb/' . $article['category_slug'] . '/' . $article['folder_slug']],
    ['label' => $article['title']],
];
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><?= e($article['title']) ?></h2>
        <?php if ($article['published_at']): ?>
            <div class="text-muted small">Published <?= date('M j, Y', strtotime($article['published_at'])) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::check() && Auth::isStaff()): ?>
            <a href="/admin/kb/articles/<?= (int) $article['id'] ?>/edit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Edit Article
            </a>
        <?php endif; ?>
        <a href="/kb/<?= e($article['category_slug']) ?>/<?= e($article['folder_slug']) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="kb-article-content">
            <?= $article['body_html'] ?>
        </div>
    </div>
</div>

<!-- Feedback widget -->
<div class="card border-0 shadow-sm" id="feedback-card">
    <div class="card-body p-4 text-center">
        <p class="fw-semibold mb-3" id="feedback-question">Was this article helpful?</p>
        <div class="d-flex justify-content-center gap-3" id="feedback-buttons">
            <button type="button" class="btn btn-outline-success px-4"
                    id="btn-helpful"
                    onclick="sendFeedback(1)"
                    <?= ($feedback['my_vote'] !== null) ? 'disabled' : '' ?>>
                <i class="bi bi-hand-thumbs-up me-1"></i>
                Yes <span class="badge bg-success ms-1" id="helpful-count"><?= $feedback['helpful'] ?></span>
            </button>
            <button type="button" class="btn btn-outline-danger px-4"
                    id="btn-not-helpful"
                    onclick="sendFeedback(-1)"
                    <?= ($feedback['my_vote'] !== null) ? 'disabled' : '' ?>>
                <i class="bi bi-hand-thumbs-down me-1"></i>
                No <span class="badge bg-danger ms-1" id="not-helpful-count"><?= $feedback['not_helpful'] ?></span>
            </button>
        </div>
        <?php if ($feedback['my_vote'] !== null): ?>
            <p class="text-muted small mt-2 mb-0" id="feedback-thanks">Thanks for your feedback!</p>
        <?php else: ?>
            <p class="text-muted small mt-2 mb-0" id="feedback-thanks" style="display:none;">Thanks for your feedback!</p>
        <?php endif; ?>
    </div>
</div>

<style>
.kb-article-content h1 { font-size: 1.75rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: .75rem; }
.kb-article-content h2 { font-size: 1.4rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: .5rem; }
.kb-article-content h3 { font-size: 1.15rem; font-weight: 600; margin-top: 1rem; margin-bottom: .5rem; }
.kb-article-content p { margin-bottom: .75rem; line-height: 1.7; }
.kb-article-content ul, .kb-article-content ol { margin-bottom: .75rem; padding-left: 1.5rem; }
.kb-article-content pre { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: .5rem; padding: 1rem; overflow-x: auto; }
.kb-article-content code { font-size: .875em; }
.kb-article-content pre code { background: none; padding: 0; }
.kb-article-content code:not(pre code) { background: #f1f5f9; padding: .15em .35em; border-radius: .25rem; }
.kb-article-content blockquote { border-left: 4px solid #e2e8f0; padding-left: 1rem; color: #64748b; margin-bottom: .75rem; }
.kb-article-content img { max-width: 100%; height: auto; border-radius: .5rem; }
.kb-article-content table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.kb-article-content th, .kb-article-content td { border: 1px solid #e2e8f0; padding: .5rem .75rem; }
.kb-article-content th { background: #f8fafc; font-weight: 600; }
</style>

<script>
function sendFeedback(rating) {
    var btnH  = document.getElementById('btn-helpful');
    var btnN  = document.getElementById('btn-not-helpful');
    btnH.disabled = true;
    btnN.disabled = true;

    var form = new URLSearchParams();
    form.append('rating', rating);

    fetch('/kb/articles/<?= e($article['slug']) ?>/feedback', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: form.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.status === 'ok' || data.status === 'already_voted') {
            document.getElementById('helpful-count').textContent = data.helpful;
            document.getElementById('not-helpful-count').textContent = data.not_helpful;
            document.getElementById('feedback-thanks').style.display = '';
        } else {
            btnH.disabled = false;
            btnN.disabled = false;
        }
    })
    .catch(function() {
        btnH.disabled = false;
        btnN.disabled = false;
    });
}
</script>
