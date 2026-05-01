<?php

/**
 * Searchable index of individual admin settings.
 *
 * Powers the Chrome-style settings search in the admin sidebar:
 * each entry is one configurable field/toggle, with the URL + anchor
 * needed to deep-link straight to it (the destination page flashes
 * a highlight on the matched element on arrival).
 *
 * To add a setting:
 *   1. Make sure the input has a stable `id` attribute on the page.
 *   2. Add an entry below with that id as `anchor`.
 *   3. Leave `anchor` empty if there's no usable id — the search will
 *      still link to the page, just without scroll-to-field.
 *
 * Fields:
 *   label       — what the user sees on the page (form label / heading)
 *   description — short hint text (≤80 chars)
 *   group       — sidebar group header (Email, Scheduling, …)
 *   page_label  — sidebar item label
 *   page_url    — page URL
 *   section     — card / fieldset heading the field lives under
 *   anchor      — HTML id attribute on the input or section
 *   keywords    — extra synonyms that should match (space-separated)
 */

return [

    // =======================================================================
    // Email / SMTP  (/admin/settings)
    // =======================================================================
    ['label' => 'SMTP Host',                    'description' => 'Server hostname for outgoing mail',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'smtp_host',
     'keywords' => 'mail server outgoing relay'],

    ['label' => 'SMTP Port',                    'description' => 'Outgoing mail port (typically 587 or 465)',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'smtp_port',
     'keywords' => '587 465 25 outgoing port'],

    ['label' => 'SMTP Encryption',              'description' => 'TLS, SSL, or none for outgoing mail',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'smtp_encryption',
     'keywords' => 'tls ssl starttls security'],

    ['label' => 'SMTP Username',                'description' => 'Login username for outgoing mail server',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'smtp_username',
     'keywords' => 'auth login credentials'],

    ['label' => 'SMTP Password',                'description' => 'Login password for outgoing mail server',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'smtp_password',
     'keywords' => 'auth login credentials secret'],

    ['label' => 'Mail From Address',            'description' => 'Default From address on outgoing emails',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'mail_from_address',
     'keywords' => 'from sender noreply envelope'],

    ['label' => 'Mail From Name',               'description' => 'Display name shown beside the From address',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'mail_from_name',
     'keywords' => 'from sender display'],

    ['label' => 'SMTP Debug Logging',           'description' => 'Log every outgoing mail attempt to storage/logs/smtp.log',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email / SMTP Configuration', 'anchor' => 'smtp_debug',
     'keywords' => 'log debug troubleshoot diagnose'],

    ['label' => 'Send Test Email',              'description' => 'Verify your SMTP configuration with a test send',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Test Email', 'anchor' => '',
     'keywords' => 'test verify smtp check'],

    // ---- Email-to-Ticket ----
    ['label' => 'Enable Email-to-Ticket',       'description' => 'Auto-create tickets from inbound emails',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email-to-Ticket', 'anchor' => 'email_to_ticket_enabled',
     'keywords' => 'inbound auto-create new ticket from email'],

    ['label' => 'Auto-create user accounts',    'description' => 'Create portal accounts for unknown email senders',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email-to-Ticket', 'anchor' => 'email_to_ticket_auto_create_users',
     'keywords' => 'auto register portal users new'],

    ['label' => 'Default Ticket Type (email)',  'description' => 'Type applied to tickets created via email',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email-to-Ticket', 'anchor' => 'email_to_ticket_default_type_id',
     'keywords' => 'inbound type category'],

    ['label' => 'Default Priority (email)',     'description' => 'Priority applied to tickets created via email',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Email-to-Ticket', 'anchor' => 'email_to_ticket_default_priority_id',
     'keywords' => 'inbound priority urgency'],

    // ---- Inbound Mail / Reply Processing (Microsoft Graph) ----
    ['label' => 'Enable reply-by-email',        'description' => 'Process replies to ticket emails as comments',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_enabled',
     'keywords' => 'reply graph azure m365 inbound replies'],

    ['label' => 'Reply-To Address',             'description' => 'Address users reply to (mailbox below)',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_reply_to',
     'keywords' => 'reply-to inbound graph'],

    ['label' => 'Mailbox Address (Graph)',      'description' => 'Microsoft 365 mailbox the cron polls',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_mailbox',
     'keywords' => 'mailbox inbox m365 graph'],

    ['label' => 'Azure Tenant ID',              'description' => 'Found in Azure Portal → Azure AD → Overview',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_tenant_id',
     'keywords' => 'azure entra directory id'],

    ['label' => 'Azure Client ID (Graph)',      'description' => 'Application (Client) ID from App Registration',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_client_id',
     'keywords' => 'azure app registration application id'],

    ['label' => 'Azure Client Secret (Graph)',  'description' => 'Secret from Certificates & Secrets',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_client_secret',
     'keywords' => 'azure secret password app registration'],

    ['label' => 'App Secret Expiry Date',       'description' => 'Reminder date for rotating the Azure secret',
     'group' => 'Email', 'page_label' => 'Email / SMTP', 'page_url' => '/admin/settings',
     'section' => 'Inbound Mail — Reply Processing', 'anchor' => 'graph_secret_expires_at',
     'keywords' => 'expire rotation rotate reminder secret'],

    // =======================================================================
    // Email Templates  (/admin/settings/email-templates)
    // =======================================================================
    ['label' => 'Email Subject',                'description' => 'Subject line per template (supports {{tokens}})',
     'group' => 'Email', 'page_label' => 'Email Templates', 'page_url' => '/admin/settings/email-templates',
     'section' => 'Email Templates', 'anchor' => '',
     'keywords' => 'subject token placeholder template'],

    ['label' => 'Intro Message',                'description' => 'Opening paragraph of each email template',
     'group' => 'Email', 'page_label' => 'Email Templates', 'page_url' => '/admin/settings/email-templates',
     'section' => 'Email Templates', 'anchor' => '',
     'keywords' => 'intro greeting opening body'],

    ['label' => 'Button Label',                 'description' => 'Call-to-action button text per template',
     'group' => 'Email', 'page_label' => 'Email Templates', 'page_url' => '/admin/settings/email-templates',
     'section' => 'Email Templates', 'anchor' => '',
     'keywords' => 'button cta link template'],

    ['label' => 'Email Footer Text',            'description' => 'Text appended at the bottom of every email',
     'group' => 'Email', 'page_label' => 'Email Templates', 'page_url' => '/admin/settings/email-templates',
     'section' => 'Shared Footer', 'anchor' => 'footer_text',
     'keywords' => 'footer signature legal disclaimer'],

    // =======================================================================
    // Email Notifications  (/admin/settings/email-notifications)
    // =======================================================================
    ['label' => 'Notify: New Ticket Created',   'description' => 'Notify group members when a ticket is created',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Agent Notifications', 'anchor' => 'agent_new_ticket',
     'keywords' => 'agent notify new ticket created'],

    ['label' => 'Notify: Assigned to Group',    'description' => 'Notify group when a ticket is routed to them',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Agent Notifications', 'anchor' => 'agent_assigned_group',
     'keywords' => 'agent group assignment route'],

    ['label' => 'Notify: Assigned to Agent',    'description' => 'Notify agent when assigned a ticket directly',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Agent Notifications', 'anchor' => 'agent_assigned_agent',
     'keywords' => 'agent assignment'],

    ['label' => 'Notify: Requester Replies',    'description' => 'Notify agent of new customer comments',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Agent Notifications', 'anchor' => 'agent_requester_reply',
     'keywords' => 'agent reply customer comment'],

    ['label' => 'Notify: Note Added',           'description' => 'Notify agent of internal notes on their tickets',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Agent Notifications', 'anchor' => 'agent_note_added',
     'keywords' => 'agent internal note'],

    ['label' => 'Notify: Stale Ticket',         'description' => 'Notify agent when a ticket goes stale',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Agent Notifications', 'anchor' => 'ticket_stale_agent',
     'keywords' => 'stale aging idle inactivity'],

    ['label' => 'Requester: New Ticket Confirmation', 'description' => 'Send confirmation email to the ticket creator',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Ticket Requester Notifications', 'anchor' => 'requester_new_ticket',
     'keywords' => 'requester customer confirmation receipt'],

    ['label' => 'Requester: Agent Assigned',    'description' => 'Tell requester which agent is handling their ticket',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Ticket Requester Notifications', 'anchor' => 'requester_ticket_assigned',
     'keywords' => 'requester customer assignment'],

    ['label' => 'Requester: Agent Replied',     'description' => 'Notify requester when agent replies',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Ticket Requester Notifications', 'anchor' => 'requester_agent_comment',
     'keywords' => 'requester reply customer comment'],

    ['label' => 'Requester: Ticket Resolved',   'description' => 'Notify requester when ticket marked resolved',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Ticket Requester Notifications', 'anchor' => 'requester_ticket_resolved',
     'keywords' => 'requester resolved closed customer'],

    ['label' => 'Requester: Ticket Closed',     'description' => 'Notify requester when ticket marked closed',
     'group' => 'Email', 'page_label' => 'Email Notifications', 'page_url' => '/admin/settings/email-notifications',
     'section' => 'Ticket Requester Notifications', 'anchor' => 'requester_ticket_closed',
     'keywords' => 'requester closed final customer'],

    // =======================================================================
    // Business Hours  (/admin/settings/business-hours)
    // =======================================================================
    ['label' => 'Timezone',                     'description' => 'Organization timezone for SLA calculations',
     'group' => 'Scheduling', 'page_label' => 'Business Hours', 'page_url' => '/admin/settings/business-hours',
     'section' => 'Business Hours', 'anchor' => 'timezone',
     'keywords' => 'timezone tz region locale'],

    ['label' => 'Weekly Schedule',              'description' => 'Per-day open/close times and active days',
     'group' => 'Scheduling', 'page_label' => 'Business Hours', 'page_url' => '/admin/settings/business-hours',
     'section' => 'Weekly Schedule', 'anchor' => '',
     'keywords' => 'monday tuesday wednesday thursday friday saturday sunday hours open close shift'],

    // =======================================================================
    // Holidays  (/admin/settings/holidays)
    // =======================================================================
    ['label' => 'Add Holiday Date',             'description' => 'Specific date the organization is closed',
     'group' => 'Scheduling', 'page_label' => 'Holidays', 'page_url' => '/admin/settings/holidays',
     'section' => 'Add Holiday or Closed Day', 'anchor' => 'holiday_date',
     'keywords' => 'closed date day vacation'],

    ['label' => 'Holiday Name',                 'description' => 'Label for this closed day',
     'group' => 'Scheduling', 'page_label' => 'Holidays', 'page_url' => '/admin/settings/holidays',
     'section' => 'Add Holiday or Closed Day', 'anchor' => 'holiday_name',
     'keywords' => 'name label closed christmas thanksgiving'],

    ['label' => 'Exclude from SLA',             'description' => 'Skip this date in SLA timer calculations',
     'group' => 'Scheduling', 'page_label' => 'Holidays', 'page_url' => '/admin/settings/holidays',
     'section' => 'Add Holiday or Closed Day', 'anchor' => 'exclude_from_sla',
     'keywords' => 'sla exclude pause'],

    ['label' => 'Auto-Populate Holidays',       'description' => 'Add country/year holidays in bulk',
     'group' => 'Scheduling', 'page_label' => 'Holidays', 'page_url' => '/admin/settings/holidays',
     'section' => 'Auto-Populate Holidays', 'anchor' => 'auto_country',
     'keywords' => 'auto populate bulk country canada usa'],

    // =======================================================================
    // SLA Policies  (/admin/settings/sla-policies)
    // =======================================================================
    ['label' => 'First Response Target',        'description' => 'Per type/priority — minutes to first agent reply',
     'group' => 'Scheduling', 'page_label' => 'SLA Policies', 'page_url' => '/admin/settings/sla-policies',
     'section' => 'SLA Policies', 'anchor' => '',
     'keywords' => 'first response sla deadline target minutes'],

    ['label' => 'Resolution Target',            'description' => 'Per type/priority — minutes to ticket resolution',
     'group' => 'Scheduling', 'page_label' => 'SLA Policies', 'page_url' => '/admin/settings/sla-policies',
     'section' => 'SLA Policies', 'anchor' => '',
     'keywords' => 'resolution sla deadline target minutes resolve'],

    // =======================================================================
    // SSO  (/admin/settings/sso)
    // =======================================================================
    ['label' => 'Enable Microsoft 365 SSO',     'description' => 'Allow login via Microsoft 365 / Azure AD',
     'group' => 'Security', 'page_label' => 'SSO / Microsoft 365', 'page_url' => '/admin/settings/sso',
     'section' => 'Single Sign-On', 'anchor' => 'sso_enabled',
     'keywords' => 'sso saml oauth single sign on m365 azure entra login'],

    ['label' => 'SSO Tenant ID',                'description' => 'Azure Directory (Tenant) ID',
     'group' => 'Security', 'page_label' => 'SSO / Microsoft 365', 'page_url' => '/admin/settings/sso',
     'section' => 'Azure AD Credentials', 'anchor' => 'sso_tenant_id',
     'keywords' => 'sso azure entra directory tenant id'],

    ['label' => 'SSO Client ID',                'description' => 'Azure Application (Client) ID',
     'group' => 'Security', 'page_label' => 'SSO / Microsoft 365', 'page_url' => '/admin/settings/sso',
     'section' => 'Azure AD Credentials', 'anchor' => 'sso_client_id',
     'keywords' => 'sso azure application client id'],

    ['label' => 'SSO Client Secret',            'description' => 'Azure secret value (Certificates & Secrets)',
     'group' => 'Security', 'page_label' => 'SSO / Microsoft 365', 'page_url' => '/admin/settings/sso',
     'section' => 'Azure AD Credentials', 'anchor' => 'sso_client_secret',
     'keywords' => 'sso azure secret password'],

    ['label' => 'Location Prompt Behaviour',    'description' => 'Whether to prompt for location at login',
     'group' => 'Security', 'page_label' => 'SSO / Microsoft 365', 'page_url' => '/admin/settings/sso',
     'section' => 'Location Prompt Behaviour', 'anchor' => 'lp_sso',
     'keywords' => 'location branch prompt sso'],

    ['label' => 'SSO Debug Logging',            'description' => 'Log SSO attempts to storage/logs/sso-debug.log',
     'group' => 'Security', 'page_label' => 'SSO / Microsoft 365', 'page_url' => '/admin/settings/sso',
     'section' => 'Debug Logging', 'anchor' => 'sso_debug',
     'keywords' => 'log debug troubleshoot sso'],

    // =======================================================================
    // Branding  (/admin/settings/branding)
    // =======================================================================
    ['label' => 'Fallback Navbar Icon',         'description' => 'Bootstrap Icon class shown when no logo uploaded',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Logo', 'anchor' => 'navbarIconInput',
     'keywords' => 'icon navbar fallback brand bi-'],

    ['label' => 'Upload New Logo',              'description' => 'JPG, PNG, GIF, WEBP, or SVG (max 2 MB)',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Logo', 'anchor' => 'logo',
     'keywords' => 'logo image upload brand'],

    ['label' => 'Application Name',             'description' => 'App name shown in navbar, titles, login, emails',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Application Name', 'anchor' => 'appName',
     'keywords' => 'name title brand product'],

    ['label' => 'Primary Color',                'description' => 'Hex color for buttons, links, active states',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Color Scheme', 'anchor' => 'primaryColor',
     'keywords' => 'color hex primary brand button link'],

    ['label' => 'Primary Hover Color',          'description' => 'Color for button hover/focus states',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Color Scheme', 'anchor' => 'primaryHover',
     'keywords' => 'hover color button focus'],

    ['label' => 'Navbar Gradient Start',        'description' => 'Left side of the navbar gradient',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Color Scheme', 'anchor' => 'navbarStart',
     'keywords' => 'navbar gradient color left'],

    ['label' => 'Navbar Gradient End',          'description' => 'Right side of the navbar gradient',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Color Scheme', 'anchor' => 'navbarEnd',
     'keywords' => 'navbar gradient color right'],

    ['label' => 'Internal Note Background',     'description' => 'Row color for internal agent notes in timeline',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Timeline Colors', 'anchor' => 'timelineNoteBg',
     'keywords' => 'internal note timeline color background'],

    ['label' => 'Internal Note Accent',         'description' => 'Border/icon color for internal notes in timeline',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Timeline Colors', 'anchor' => 'timelineNoteAccent',
     'keywords' => 'internal note timeline accent border'],

    ['label' => 'System Entry Background',      'description' => 'Row color for automated system entries in timeline',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Timeline Colors', 'anchor' => 'timelineSystemBg',
     'keywords' => 'system timeline color background automated'],

    ['label' => 'System Entry Accent',          'description' => 'Border color for automated system entries',
     'group' => 'Customization', 'page_label' => 'Branding', 'page_url' => '/admin/settings/branding',
     'section' => 'Timeline Colors', 'anchor' => 'timelineSystemAccent',
     'keywords' => 'system timeline accent border'],

    // =======================================================================
    // Labels  (/admin/settings/labels)
    // =======================================================================
    ['label' => 'Upload labels.json',           'description' => 'JSON file overriding app terminology',
     'group' => 'Customization', 'page_label' => 'Labels', 'page_url' => '/admin/settings/labels',
     'section' => 'Label Customisation', 'anchor' => 'labels_file',
     'keywords' => 'labels json terminology rename i18n'],

    // =======================================================================
    // Tags  (/admin/settings/tags)
    // =======================================================================
    ['label' => 'Enable Tags on Tickets',       'description' => 'Show tag input on ticket forms',
     'group' => 'Customization', 'page_label' => 'Tags', 'page_url' => '/admin/settings/tags',
     'section' => 'Tags', 'anchor' => 'tags_enabled',
     'keywords' => 'tags keyword marker enable'],

    // =======================================================================
    // Canned Responses  (/admin/settings/canned-responses)
    // =======================================================================
    ['label' => 'Manage Canned Responses',      'description' => 'Create, edit, delete reply macros / snippets',
     'group' => 'Customization', 'page_label' => 'Canned Responses', 'page_url' => '/admin/settings/canned-responses',
     'section' => 'Canned Responses', 'anchor' => '',
     'keywords' => 'canned response macro snippet template reply boilerplate'],

    // =======================================================================
    // CSAT  (/admin/settings/csat)
    // =======================================================================
    ['label' => 'Enable CSAT Surveys',          'description' => 'Auto-send rating emails after ticket completion',
     'group' => 'Customization', 'page_label' => 'CSAT Surveys', 'page_url' => '/admin/settings/csat',
     'section' => 'Customer Satisfaction Surveys', 'anchor' => 'csat_enabled',
     'keywords' => 'csat satisfaction rating feedback survey'],

    ['label' => 'CSAT Trigger Status',          'description' => 'Send survey when ticket reaches Resolved or Closed',
     'group' => 'Customization', 'page_label' => 'CSAT Surveys', 'page_url' => '/admin/settings/csat',
     'section' => 'Customer Satisfaction Surveys', 'anchor' => 'csat_trigger_status',
     'keywords' => 'csat trigger resolved closed status'],

    ['label' => 'Send Test CSAT Survey',        'description' => 'Send a test rating email to verify config',
     'group' => 'Customization', 'page_label' => 'CSAT Surveys', 'page_url' => '/admin/settings/csat',
     'section' => 'Send a Test Survey', 'anchor' => 'test_email',
     'keywords' => 'csat test survey verify'],

    // =======================================================================
    // AI  (/admin/settings/ai)
    // =======================================================================
    ['label' => 'Enable AI Classification',     'description' => 'Use an LLM to detect required agent skills',
     'group' => 'Automation', 'page_label' => 'AI Classification', 'page_url' => '/admin/settings/ai',
     'section' => 'AI Classification', 'anchor' => 'ai_enabled',
     'keywords' => 'ai llm classification routing auto-tag claude gpt'],

    ['label' => 'AI Provider',                  'description' => 'Choose Anthropic or OpenAI',
     'group' => 'Automation', 'page_label' => 'AI Classification', 'page_url' => '/admin/settings/ai',
     'section' => 'Provider', 'anchor' => 'prov_anthropic',
     'keywords' => 'ai provider anthropic openai claude gpt'],

    ['label' => 'Anthropic API Key',            'description' => 'API key from console.anthropic.com',
     'group' => 'Automation', 'page_label' => 'AI Classification', 'page_url' => '/admin/settings/ai',
     'section' => 'Anthropic Credentials', 'anchor' => 'ai_anthropic_api_key',
     'keywords' => 'anthropic api key claude credential'],

    ['label' => 'Anthropic Model',              'description' => 'Claude model: Haiku, Sonnet, or Opus',
     'group' => 'Automation', 'page_label' => 'AI Classification', 'page_url' => '/admin/settings/ai',
     'section' => 'Anthropic Credentials', 'anchor' => 'ai_anthropic_model',
     'keywords' => 'anthropic model claude haiku sonnet opus'],

    ['label' => 'OpenAI API Key',               'description' => 'API key from platform.openai.com',
     'group' => 'Automation', 'page_label' => 'AI Classification', 'page_url' => '/admin/settings/ai',
     'section' => 'OpenAI Credentials', 'anchor' => 'ai_openai_api_key',
     'keywords' => 'openai api key gpt credential'],

    ['label' => 'OpenAI Model',                 'description' => 'GPT model: 4o, 4o-mini, or 4-turbo',
     'group' => 'Automation', 'page_label' => 'AI Classification', 'page_url' => '/admin/settings/ai',
     'section' => 'OpenAI Credentials', 'anchor' => 'ai_openai_model',
     'keywords' => 'openai model gpt 4o turbo'],

    // =======================================================================
    // Stale Tickets  (/admin/settings/stale-tickets)
    // =======================================================================
    ['label' => 'Stale Threshold (hours)',      'description' => 'Hours of inactivity before a ticket is stale',
     'group' => 'Automation', 'page_label' => 'Stale Tickets', 'page_url' => '/admin/settings/stale-tickets',
     'section' => 'Global Thresholds', 'anchor' => 'stale_threshold_hours',
     'keywords' => 'stale threshold idle inactive aging'],

    ['label' => 'Re-notify After (hours)',      'description' => 'Minimum gap between stale notifications',
     'group' => 'Automation', 'page_label' => 'Stale Tickets', 'page_url' => '/admin/settings/stale-tickets',
     'section' => 'Global Thresholds', 'anchor' => 'stale_recheck_hours',
     'keywords' => 'stale renotify recheck'],

    ['label' => 'Notify Assigned Agent (stale)', 'description' => 'Send stale alert to the assigned agent',
     'group' => 'Automation', 'page_label' => 'Stale Tickets', 'page_url' => '/admin/settings/stale-tickets',
     'section' => 'Email notifications', 'anchor' => 'notify_agent',
     'keywords' => 'stale notify agent'],

    ['label' => 'Notify Requester (stale)',     'description' => 'Reassure requester that ticket is not forgotten',
     'group' => 'Automation', 'page_label' => 'Stale Tickets', 'page_url' => '/admin/settings/stale-tickets',
     'section' => 'Email notifications', 'anchor' => 'notify_requester',
     'keywords' => 'stale notify requester customer'],

    // =======================================================================
    // Escalations + Paths
    // =======================================================================
    ['label' => 'Escalation Rules',             'description' => 'Create rules for time-based ticket escalation',
     'group' => 'Automation', 'page_label' => 'Escalation Rules', 'page_url' => '/admin/settings/escalations',
     'section' => 'Escalation Rules', 'anchor' => '',
     'keywords' => 'escalate rule deadline overdue trigger'],

    ['label' => 'Escalation Paths',             'description' => 'Tiered handoff paths per ticket type',
     'group' => 'Automation', 'page_label' => 'Escalation Paths', 'page_url' => '/admin/settings/escalation-paths',
     'section' => 'Escalation Paths', 'anchor' => '',
     'keywords' => 'escalation path tier handoff route'],

    // =======================================================================
    // Scheduled Reports + Cron Jobs
    // =======================================================================
    ['label' => 'Scheduled Reports',            'description' => 'Recurring reports emailed on a schedule',
     'group' => 'Automation', 'page_label' => 'Scheduled Reports', 'page_url' => '/admin/settings/scheduled-reports',
     'section' => 'Scheduled Reports', 'anchor' => '',
     'keywords' => 'scheduled report email recurring digest weekly monthly'],

    ['label' => 'Cron Jobs',                    'description' => 'Required server cron schedule entries',
     'group' => 'Automation', 'page_label' => 'Cron Jobs', 'page_url' => '/admin/settings/cron-jobs',
     'section' => 'Cron Jobs', 'anchor' => '',
     'keywords' => 'cron schedule background task job crontab'],

    // =======================================================================
    // Data — Imports + Backup
    // =======================================================================
    ['label' => 'Import Tickets (CSV)',         'description' => 'CSV export from previous ticketing system',
     'group' => 'Data', 'page_label' => 'Import Tickets', 'page_url' => '/admin/settings/import',
     'section' => 'Import Tickets from CSV', 'anchor' => 'csv_file',
     'keywords' => 'import csv migrate upload tickets'],

    ['label' => 'Import Users (CSV)',           'description' => 'CSV with email, name, role, location',
     'group' => 'Data', 'page_label' => 'Import Users', 'page_url' => '/admin/settings/import-users',
     'section' => 'Import Users from CSV', 'anchor' => 'csv_file',
     'keywords' => 'import csv users contacts portal'],

    ['label' => 'Import Knowledge Base (CSV)',  'description' => 'CSV with title, body_markdown, category, status',
     'group' => 'Data', 'page_label' => 'Import KB', 'page_url' => '/admin/settings/import-kb',
     'section' => 'Import Knowledge Base Articles', 'anchor' => 'csv_file',
     'keywords' => 'import csv kb knowledge base articles'],

    ['label' => 'Create Backup Now',            'description' => 'Generate .zip of database and website files',
     'group' => 'Data', 'page_label' => 'Backup', 'page_url' => '/admin/settings/backup',
     'section' => 'Create Backup', 'anchor' => '',
     'keywords' => 'backup snapshot zip export database'],

    // =======================================================================
    // System — Danger Zone
    // =======================================================================
    ['label' => 'Delete All Tickets',           'description' => 'Permanently remove every ticket',
     'group' => 'System', 'page_label' => 'Danger Zone', 'page_url' => '/admin/settings/danger-zone',
     'section' => 'Danger Zone', 'anchor' => '',
     'keywords' => 'delete tickets purge wipe destructive'],

    ['label' => 'Reset Everything',             'description' => 'Wipe all data; restart from setup wizard',
     'group' => 'System', 'page_label' => 'Danger Zone', 'page_url' => '/admin/settings/danger-zone',
     'section' => 'Danger Zone', 'anchor' => 'resetConfirmInput',
     'keywords' => 'reset wipe purge factory destructive'],

    // =======================================================================
    // Organization  (/admin/settings/organization)
    // =======================================================================
    ['label' => 'Organization Type',            'description' => 'Sector / industry — library, education, government, etc.',
     'group' => 'Organization', 'page_label' => 'Organization Type', 'page_url' => '/admin/settings/organization',
     'section' => 'Organization', 'anchor' => 'organization_type',
     'keywords' => 'organization type sector industry library public academic education school k12 college university government federal state municipal healthcare hospital clinic corporate enterprise small business manufacturing retail financial banking legal hospitality technology non-profit charity religious museum association'],

];
