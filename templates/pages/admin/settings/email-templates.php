<?php
$layout       = 'app';
$pageTitle    = 'Email Templates';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Email Templates'],
];

// Token definitions per template
$tokenSets = [
    'ticket_created' => [
        ['token' => '{{first_name}}',   'desc' => 'Recipient\'s first name'],
        ['token' => '{{last_name}}',    'desc' => 'Recipient\'s last name'],
        ['token' => '{{user_name}}',    'desc' => 'Recipient\'s full name (first + last)'],
        ['token' => '{{ticket_id}}',    'desc' => 'Ticket number (e.g. 42)'],
        ['token' => '{{subject}}',      'desc' => 'Ticket subject line'],
        ['token' => '{{type}}',         'desc' => 'Ticket type (if set)'],
        ['token' => '{{location}}',     'desc' => 'Location (if set)'],
        ['token' => '{{priority}}',     'desc' => 'Priority name (if set)'],
    ],
    'ticket_updated' => [
        ['token' => '{{first_name}}',   'desc' => 'Recipient\'s first name'],
        ['token' => '{{last_name}}',    'desc' => 'Recipient\'s last name'],
        ['token' => '{{user_name}}',    'desc' => 'Recipient\'s full name (first + last)'],
        ['token' => '{{ticket_id}}',    'desc' => 'Ticket number'],
        ['token' => '{{subject}}',      'desc' => 'Ticket subject line'],
        ['token' => '{{message}}',      'desc' => 'The new comment text'],
        ['token' => '{{author}}',       'desc' => 'Name of the person who commented'],
    ],
    'ticket_merged' => [
        ['token' => '{{first_name}}',       'desc' => 'Recipient\'s first name'],
        ['token' => '{{last_name}}',        'desc' => 'Recipient\'s last name'],
        ['token' => '{{user_name}}',        'desc' => 'Recipient\'s full name (first + last)'],
        ['token' => '{{source_ticket_id}}', 'desc' => 'Merged (closed) ticket number'],
        ['token' => '{{source_subject}}',   'desc' => 'Merged ticket subject'],
        ['token' => '{{target_ticket_id}}', 'desc' => 'Master ticket number'],
        ['token' => '{{target_subject}}',   'desc' => 'Master ticket subject'],
    ],
    'csat_survey' => [
        ['token' => '{{first_name}}',   'desc' => 'Recipient\'s first name'],
        ['token' => '{{last_name}}',    'desc' => 'Recipient\'s last name'],
        ['token' => '{{user_name}}',    'desc' => 'Recipient\'s full name (first + last)'],
        ['token' => '{{ticket_id}}',    'desc' => 'Ticket number'],
        ['token' => '{{subject}}',      'desc' => 'Ticket subject line'],
    ],
    'ticket_reminder' => [
        ['token' => '{{first_name}}',   'desc' => 'Recipient\'s first name'],
        ['token' => '{{last_name}}',    'desc' => 'Recipient\'s last name'],
        ['token' => '{{user_name}}',    'desc' => 'Recipient\'s full name (first + last)'],
        ['token' => '{{ticket_id}}',    'desc' => 'Ticket number'],
        ['token' => '{{subject}}',      'desc' => 'Ticket subject line'],
    ],
    'group_alerts' => [
        ['token' => '{{first_name}}',   'desc' => 'Recipient\'s first name'],
        ['token' => '{{last_name}}',    'desc' => 'Recipient\'s last name'],
        ['token' => '{{user_name}}',    'desc' => 'Recipient\'s full name (first + last)'],
        ['token' => '{{ticket_id}}',    'desc' => 'Ticket number'],
        ['token' => '{{subject}}',      'desc' => 'Ticket subject line'],
        ['token' => '{{type}}',         'desc' => 'Ticket type (if set)'],
        ['token' => '{{location}}',     'desc' => 'Location (if set)'],
        ['token' => '{{priority}}',     'desc' => 'Priority name (if set)'],
        ['token' => '{{submitter}}',    'desc' => 'Full name of the person who submitted the ticket'],
    ],
];

$defaults = [
    'ticket_created' => [
        'subject' => '[Ticket #{{ticket_id}}] {{subject}}',
        'intro'   => 'Your ticket has been created and our team will review it shortly.',
        'button'  => 'View Ticket',
    ],
    'ticket_updated' => [
        'subject' => '[Ticket #{{ticket_id}}] Update: {{subject}}',
        'intro'   => 'Your ticket has been updated.',
        'button'  => 'View Ticket',
    ],
    'ticket_merged' => [
        'subject' => '[Ticket #{{source_ticket_id}}] Your ticket has been merged',
        'intro'   => 'Ticket #{{source_ticket_id}} has been consolidated with a related ticket. You can view updates and add comments on the master ticket.',
        'button'  => 'View Master Ticket',
    ],
    'csat_survey' => [
        'subject' => 'How did we do? — [Ticket #{{ticket_id}}] {{subject}}',
        'intro'   => 'Your ticket has been resolved. We\'d love to hear how we did — it only takes one click!',
    ],
    'ticket_reminder' => [
        'subject' => 'Following up on your ticket [#{{ticket_id}}] {{subject}}',
        'intro'   => 'We\'re still waiting to hear back from you on your support ticket. Please reply with an update so we can continue helping you.',
        'button'  => 'View & Reply',
    ],
    'group_alerts' => [
        'subject' => '[Ticket #{{ticket_id}}] New Ticket: {{subject}}',
        'intro'   => 'A new support ticket has been submitted.',
        'button'  => 'View Ticket',
    ],
];

$defaultFooter = 'This is an automated message from LocalDesk. Please do not reply directly to this email.';

$tabs = [
    'ticket_created'  => ['label' => 'Ticket Created',    'icon' => 'bi-ticket-perforated'],
    'ticket_updated'  => ['label' => 'Ticket Updated',    'icon' => 'bi-chat-left-text'],
    'ticket_merged'   => ['label' => 'Ticket Merged',     'icon' => 'bi-diagram-2'],
    'csat_survey'     => ['label' => 'CSAT Survey',       'icon' => 'bi-star'],
    'ticket_reminder' => ['label' => 'Customer Reminder', 'icon' => 'bi-clock-history'],
    'group_alerts'    => ['label' => 'Group Alerts',      'icon' => 'bi-people'],
];

$activeTab = $_GET['tab'] ?? 'ticket_created';
if (!array_key_exists($activeTab, $tabs) && $activeTab !== 'shared') {
    $activeTab = 'ticket_created';
}
$groups ??= [];
?>

<div class="mb-4">
    <h2 class="fw-bold mb-0">Email Templates</h2>
    <p class="text-muted mt-1 mb-0">Customise the subject, intro message, and button label for each automated email. Use <code>{{tokens}}</code> to insert dynamic content.</p>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<!-- Template tabs -->
<ul class="nav nav-tabs mb-0" id="emailTabs">
    <?php foreach ($tabs as $tKey => $tInfo): ?>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === $tKey ? 'active' : '' ?>"
           href="?tab=<?= $tKey ?>">
            <i class="bi <?= $tInfo['icon'] ?> me-1"></i><?= e($tInfo['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'shared' ? 'active' : '' ?>"
           href="?tab=shared">
            <i class="bi bi-layout-text-window me-1"></i>Shared Footer
        </a>
    </li>
</ul>

<?php if ($activeTab === 'shared'): ?>

<!-- Shared footer -->
<div class="card border-0 shadow-sm border-top-0" style="border-radius:0 0 .5rem .5rem;">
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            This footer text appears at the bottom of every outgoing ticket email.
        </p>
        <form method="POST" action="/admin/settings/email-templates">
            <?= csrfField() ?>
            <input type="hidden" name="tab" value="shared">

            <div class="mb-4" style="max-width:640px;">
                <label class="form-label fw-semibold">Footer Text</label>
                <textarea class="form-control font-monospace" name="email_footer_text" rows="3"
                    placeholder="<?= e($defaultFooter) ?>"><?= e($tplValues['email_footer_text'] ?? '') ?></textarea>
                <div class="form-text">Leave blank to use the default: <em><?= e($defaultFooter) ?></em></div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Footer
            </button>
            <?php if (!empty($tplValues['email_footer_text'])): ?>
            <button type="submit" name="reset_footer" value="1" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default
            </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'group_alerts'): ?>

<!-- Group Alerts tab -->
<div class="card border-0 shadow-sm border-top-0" style="border-radius:0 0 .5rem .5rem;">
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            Customize the email sent to group members when a new ticket is created.
            To control which groups receive these alerts, edit the group under <a href="/admin/groups">Settings → Groups</a>.
        </p>

        <form method="POST" action="/admin/settings/email-templates">
            <?= csrfField() ?>
            <input type="hidden" name="tab" value="group_alerts">

            <div class="row g-4">
                <!-- Editor column -->
                <div class="col-lg-8">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Line</label>
                        <input type="text" class="form-control font-monospace"
                               name="email_subject_group_alerts"
                               value="<?= e($tplValues['email_subject_group_alerts'] ?? '') ?>"
                               placeholder="<?= e($defaults['group_alerts']['subject']) ?>">
                        <div class="form-text">Leave blank to use the default shown above as placeholder.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Intro Message</label>
                        <textarea id="intro-editor" class="form-control" name="email_intro_group_alerts"
                                  rows="3"
                                  placeholder="<?= e($defaults['group_alerts']['intro']) ?>"><?= e($tplValues['email_intro_group_alerts'] ?? '') ?></textarea>
                        <div class="form-text">Appears as the subtitle paragraph below the ticket heading. Leave blank to use the default.</div>
                    </div>

                    <div class="mb-4" style="max-width:300px;">
                        <label class="form-label fw-semibold">Button Label</label>
                        <input type="text" class="form-control"
                               name="email_button_group_alerts"
                               value="<?= e($tplValues['email_button_group_alerts'] ?? '') ?>"
                               placeholder="<?= e($defaults['group_alerts']['button']) ?>">
                        <div class="form-text">Leave blank to use the default.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                            <i class="bi bi-check-lg me-1"></i>Save
                        </button>
                        <?php
                        $hasCustom = !empty($tplValues['email_subject_group_alerts'])
                                  || !empty($tplValues['email_intro_group_alerts'])
                                  || !empty($tplValues['email_button_group_alerts']);
                        ?>
                        <?php if ($hasCustom): ?>
                        <button type="submit" name="reset_template" value="group_alerts"
                                class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Template to Defaults
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Token legend column -->
                <div class="col-lg-4">
                    <div class="bg-light border rounded p-3">
                        <h6 class="fw-semibold mb-2"><i class="bi bi-braces me-1"></i>Available Tokens</h6>
                        <p class="text-muted small mb-3">Click a token to copy it. Tokens work in the Subject and Intro fields.</p>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($tokenSets['group_alerts'] as $tok): ?>
                            <div class="d-flex align-items-start gap-2">
                                <code class="token-chip bg-white border rounded px-2 py-1 small flex-shrink-0"
                                      style="cursor:pointer; user-select:all;"
                                      title="Click to copy"
                                      onclick="copyToken(this)"><?= e($tok['token']) ?></code>
                                <span class="text-muted small pt-1"><?= e($tok['desc']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Subject preview -->
                    <?php
                    $gaSubjectTpl = $tplValues['email_subject_group_alerts'] ?? $defaults['group_alerts']['subject'];
                    $gaPreview = $gaSubjectTpl;
                    foreach (['ticket_id' => '42', 'subject' => 'Printer not working', 'submitter' => 'Jordan Lee'] as $k => $v) {
                        $gaPreview = str_replace('{{' . $k . '}}', $v, $gaPreview);
                    }
                    ?>
                    <div class="mt-3 bg-white border rounded p-3">
                        <div class="text-muted small fw-semibold mb-1">Subject preview</div>
                        <div class="small text-break" style="font-family:monospace;"><?= e($gaPreview) ?></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Per-template editor -->
<div class="card border-0 shadow-sm border-top-0" style="border-radius:0 0 .5rem .5rem;">
    <div class="card-body p-4">

        <div class="row g-4">
            <!-- Editor column -->
            <div class="col-lg-8">
                <form method="POST" action="/admin/settings/email-templates">
                    <?= csrfField() ?>
                    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Line</label>
                        <input type="text" class="form-control font-monospace"
                               name="email_subject_<?= e($activeTab) ?>"
                               value="<?= e($tplValues["email_subject_{$activeTab}"] ?? '') ?>"
                               placeholder="<?= e($defaults[$activeTab]['subject']) ?>">
                        <div class="form-text">Leave blank to use the default shown above as placeholder.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Intro Message</label>
                        <textarea id="intro-editor" class="form-control" name="email_intro_<?= e($activeTab) ?>"
                                  rows="3"
                                  placeholder="<?= e($defaults[$activeTab]['intro']) ?>"><?= e($tplValues["email_intro_{$activeTab}"] ?? '') ?></textarea>
                        <div class="form-text">
                            Appears as the subtitle paragraph below the ticket heading.
                            Leave blank to use the default.
                        </div>
                    </div>

                    <?php if ($activeTab !== 'csat_survey'): ?>
                    <div class="mb-4" style="max-width:300px;">
                        <label class="form-label fw-semibold">Button Label</label>
                        <input type="text" class="form-control"
                               name="email_button_<?= e($activeTab) ?>"
                               value="<?= e($tplValues["email_button_{$activeTab}"] ?? '') ?>"
                               placeholder="<?= e($defaults[$activeTab]['button']) ?>">
                        <div class="form-text">Leave blank to use the default.</div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info small mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        The CSAT survey email uses <strong>star rating buttons</strong> (1–5) instead of a single call-to-action button, so there is no button label to customise.
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                            <i class="bi bi-check-lg me-1"></i>Save Template
                        </button>
                        <?php
                        $hasCustom = !empty($tplValues["email_subject_{$activeTab}"])
                                  || !empty($tplValues["email_intro_{$activeTab}"])
                                  || ($activeTab !== 'csat_survey' && !empty($tplValues["email_button_{$activeTab}"]));
                        ?>
                        <?php if ($hasCustom): ?>
                        <button type="submit" name="reset_template" value="<?= e($activeTab) ?>"
                                class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Defaults
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Token legend column -->
            <div class="col-lg-4">
                <div class="bg-light border rounded p-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-braces me-1"></i>Available Tokens</h6>
                    <p class="text-muted small mb-3">Click a token to copy it. Tokens work in the Subject and Intro fields.</p>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($tokenSets[$activeTab] as $tok): ?>
                        <div class="d-flex align-items-start gap-2">
                            <code class="token-chip bg-white border rounded px-2 py-1 small flex-shrink-0"
                                  style="cursor:pointer; user-select:all;"
                                  title="Click to copy"
                                  onclick="copyToken(this)"><?= e($tok['token']) ?></code>
                            <span class="text-muted small pt-1"><?= e($tok['desc']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Tokens in the <strong>Intro</strong> field are automatically HTML-escaped, so
                            they display safely even if a ticket subject contains special characters.
                        </p>
                    </div>
                </div>

                <!-- Live preview of subject -->
                <?php
                $subjectTpl = $tplValues["email_subject_{$activeTab}"] ?? $defaults[$activeTab]['subject'];
                $previewTokens = [
                    'ticket_created'  => ['first_name' => 'Alex', 'last_name' => 'Johnson', 'user_name' => 'Alex Johnson', 'ticket_id' => '42', 'subject' => 'Printer not working', 'type' => 'Hardware', 'location' => 'Main Branch', 'priority' => 'High'],
                    'ticket_updated'  => ['first_name' => 'Alex', 'last_name' => 'Johnson', 'user_name' => 'Alex Johnson', 'ticket_id' => '42', 'subject' => 'Printer not working', 'message' => 'We are looking into this.', 'author' => 'Jane Smith'],
                    'ticket_merged'   => ['first_name' => 'Alex', 'last_name' => 'Johnson', 'user_name' => 'Alex Johnson', 'source_ticket_id' => '42', 'source_subject' => 'Printer not working', 'target_ticket_id' => '38', 'target_subject' => 'Office printer issues'],
                    'csat_survey'     => ['first_name' => 'Alex', 'last_name' => 'Johnson', 'user_name' => 'Alex Johnson', 'ticket_id' => '42', 'subject' => 'Printer not working'],
                    'ticket_reminder' => ['first_name' => 'Alex', 'last_name' => 'Johnson', 'user_name' => 'Alex Johnson', 'ticket_id' => '42', 'subject' => 'Printer not working'],
                    'group_alerts'    => ['first_name' => 'Alex', 'last_name' => 'Johnson', 'user_name' => 'Alex Johnson', 'ticket_id' => '42', 'subject' => 'Printer not working', 'type' => 'Hardware', 'location' => 'Main Branch', 'priority' => 'High', 'submitter' => 'Jordan Lee'],
                ];
                $preview = $subjectTpl;
                foreach (($previewTokens[$activeTab] ?? []) as $k => $v) {
                    $preview = str_replace('{{' . $k . '}}', $v, $preview);
                }
                ?>
                <div class="mt-3 bg-white border rounded p-3">
                    <div class="text-muted small fw-semibold mb-1">Subject preview</div>
                    <div class="small text-break" style="font-family:monospace;"><?= e($preview) ?></div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php endif; ?>

<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.css">
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
function copyToken(el) {
    const text = el.textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const orig = el.style.background;
        el.style.background = '#d1fae5';
        setTimeout(() => { el.style.background = orig; }, 800);
    });
}

(function () {
    var ta = document.getElementById('intro-editor');
    if (!ta) return;

    ClassicEditor.create(ta, {
        placeholder: ta.getAttribute('placeholder') || '',
        toolbar: {
            items: ['bold', 'italic', '|', 'link', '|', 'bulletedList', 'numberedList']
        },
        link: {
            defaultProtocol: 'https://'
        }
    }).then(function (editor) {
        var form = ta.form || ta.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                ta.value = editor.getData();
            });
        }
    }).catch(console.error);
})();
</script>
<style>
.ck-editor__editable { min-height: 7.5em; }
</style>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
