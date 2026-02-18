<?php
$layout       = 'app';
$pageTitle    = 'Knowledge Base';
$sidebarItems = portalSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Portal', 'url' => '/portal'],
    ['label' => 'Knowledge Base'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Knowledge Base</h2>
    <div style="max-width:320px; width:100%;">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" id="kb-search" placeholder="Search articles..."
                   autocomplete="off">
        </div>
    </div>
</div>

<!-- Search results (hidden by default) -->
<div id="kb-search-results" class="mb-4" style="display:none;">
    <h5 class="fw-semibold mb-3">Search Results</h5>
    <div id="kb-search-list"></div>
</div>

<!-- Categories grid -->
<div id="kb-categories">
    <?php if (empty($categories)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-book" style="font-size:3rem;"></i>
            <p class="mt-2">No knowledge base articles available yet.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
            <div class="col-md-6 col-lg-4">
                <a href="/portal/kb/<?= e($cat['slug']) ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100" style="transition: transform .15s ease;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-2">
                                <div class="rounded-3 d-flex align-items-center justify-content-center me-3"
                                     style="width:44px; height:44px; background:#eef2ff;">
                                    <i class="bi bi-collection" style="font-size:1.25rem; color:var(--ld-primary);"></i>
                                </div>
                                <div>
                                    <h5 class="fw-semibold mb-0 text-dark"><?= e($cat['name']) ?></h5>
                                    <small class="text-muted"><?= (int) $cat['folder_count'] ?> folder<?= (int) $cat['folder_count'] !== 1 ? 's' : '' ?></small>
                                </div>
                            </div>
                            <?php if ($cat['description']): ?>
                                <p class="text-muted small mb-0"><?= e(mb_strimwidth($cat['description'], 0, 120, '...')) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var searchInput = document.getElementById('kb-search');
    var resultsBox  = document.getElementById('kb-search-results');
    var resultsList = document.getElementById('kb-search-list');
    var categories  = document.getElementById('kb-categories');
    var timer       = null;

    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 2) {
            resultsBox.style.display = 'none';
            categories.style.display = '';
            return;
        }
        timer = setTimeout(function() {
            fetch('/portal/kb/search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.length === 0) {
                        resultsList.innerHTML = '<p class="text-muted">No articles found.</p>';
                    } else {
                        var html = '<div class="list-group">';
                        data.forEach(function(a) {
                            html += '<a href="/portal/kb/articles/' + encodeURIComponent(a.slug) + '" class="list-group-item list-group-item-action">'
                                + '<div class="fw-semibold">' + escHtml(a.title) + '</div>'
                                + '<small class="text-muted">' + escHtml(a.category_name || '') + ' / ' + escHtml(a.folder_name || '') + '</small>'
                                + '</a>';
                        });
                        html += '</div>';
                        resultsList.innerHTML = html;
                    }
                    resultsBox.style.display = '';
                    categories.style.display = 'none';
                });
        }, 300);
    });

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
</script>
