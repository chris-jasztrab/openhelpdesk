/**
 * Similar past tickets — async fetch + render for the ticket view panel.
 *
 * Shared by the agent and admin ticket views. Each view renders a
 * #similarTicketsCard carrying:
 *   data-ticket-id     — the ticket being worked
 *   data-similar-url   — the JSON endpoint to fetch (agent vs admin prefix)
 *   data-status-labels — JSON map of raw status -> human label
 *
 * The endpoint caches per ticket, so the LLM rerank never runs more than
 * once per ticket unless the refresh button forces it (?refresh=1).
 */
(function () {
    var card = document.getElementById('similarTicketsCard');
    if (!card) { return; }

    var body    = document.getElementById('similarTicketsBody');
    var baseUrl = card.dataset.similarUrl || '';
    if (!body || !baseUrl) { return; }

    var labels = {};
    try { labels = JSON.parse(card.dataset.statusLabels || '{}'); } catch (_) {}

    // Keep match links in the same section the ticket was opened from
    // (/agent or /admin), derived from the endpoint URL.
    var ticketBase = baseUrl.replace(/\/tickets\/\d+\/similar$/, '/tickets/');
    if (ticketBase === baseUrl) { ticketBase = '/agent/tickets/'; }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render(matches) {
        if (!matches || !matches.length) {
            body.innerHTML = '<div class="text-muted small">No similar past tickets found.</div>';
            return;
        }
        var html = '<div class="list-group list-group-flush">';
        matches.forEach(function (m) {
            var status = labels[m.status] || m.status || '';
            var when   = (m.created_at || '').slice(0, 10);
            var pct    = Math.round((parseFloat(m.relevance) || 0) * 100);
            html += '<a href="' + ticketBase + encodeURIComponent(m.ticket_id) + '" '
                 +  'class="list-group-item list-group-item-action px-0">'
                 +    '<div class="d-flex justify-content-between align-items-start gap-2">'
                 +      '<span class="fw-semibold">#' + esc(m.ticket_id) + ' · ' + esc(m.subject) + '</span>'
                 +      '<span class="badge bg-primary bg-opacity-10 text-primary flex-shrink-0">' + pct + '%</span>'
                 +    '</div>'
                 +    (m.reasoning ? '<div class="small text-muted mt-1">' + esc(m.reasoning) + '</div>' : '')
                 +    '<div class="small text-muted mt-1">'
                 +      (status ? '<span class="me-2">' + esc(status) + '</span>' : '')
                 +      (when ? '<span>' + esc(when) + '</span>' : '')
                 +    '</div>'
                 +  '</a>';
        });
        html += '</div>';
        body.innerHTML = html;
    }

    function load(force) {
        body.innerHTML = '<div class="text-muted small d-flex align-items-center gap-2">'
            + '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
            + (force ? 'Re-running search…' : 'Searching ticket history…') + '</div>';
        var url = baseUrl + (force ? '?refresh=1' : '');
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.enabled) {
                    body.innerHTML = '<div class="text-muted small">Similar-ticket search is unavailable.</div>';
                    return;
                }
                render(data.matches);
            })
            .catch(function () {
                body.innerHTML = '<div class="text-danger small">Could not load similar tickets.</div>';
            });
    }

    var refreshBtn = document.getElementById('similarRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { load(true); });
    }
    load(false);
})();
