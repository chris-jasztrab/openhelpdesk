<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Tests\Support\TestCase;

/**
 * The generated service worker (/sw.js) injects the branding app name. It must
 * embed it as an escaped JSON literal so a hostile branding string can never
 * break out of its JS string and execute at the (root-scope) worker context.
 */
class PwaServiceWorkerTest extends TestCase
{
    public function test_service_worker_escapes_branding_app_name(): void
    {
        $orig = \getSetting('branding_app_name', 'OpenHelpDesk');

        // A payload that would break out of a naive '{$appName}' interpolation:
        // a double-quote, a comment-terminator, and a newline.
        \setSetting('branding_app_name', "\";})();alert(1);//\n*/ pwn");

        try {
            $r    = $this->get($this->guestClient(), '/sw.js');
            $body = (string) $r->getBody();

            $this->assertSame(200, $r->getStatusCode());
            $this->assertStringContainsString('javascript', $r->getHeaderLine('Content-Type'));

            // The app name must appear only as an escaped JSON string literal:
            // the embedded double-quote is backslash-escaped...
            $this->assertStringContainsString('const APP_NAME = "', $body);
            $this->assertStringContainsString('\\";', $body,
                ' — the embedded double-quote must be backslash-escaped');

            // ...and the newline must be escaped, not allowed to break the app
            // name across lines (which would surface the payload tail as code).
            $this->assertDoesNotMatchRegularExpression('/^\*\/ pwn/m', $body,
                ' — the newline in the app name must be escaped, not break the line');
        } finally {
            \setSetting('branding_app_name', $orig);
        }
    }
}
