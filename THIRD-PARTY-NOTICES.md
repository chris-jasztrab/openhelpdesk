# Third-Party Notices

OpenHelpDesk (LocalDesk) is distributed under the [MIT License](LICENSE).

It incorporates and depends on the open-source projects listed below. Each
remains the property of its respective authors and is governed by its own
license. This file is provided for attribution and license-compliance purposes.

> **Note on CKEditor 5.** A build of CKEditor 5 is **bundled** in this
> repository at `public/assets/vendor/ckeditor5/` and is licensed
> **GPL-2.0-or-later** (or commercially) by CKSource — separately from this
> project's MIT license. Distributing this repository therefore also
> distributes that GPL-licensed build; the GPL governs CKEditor itself, not
> the surrounding MIT-licensed application code (the app only loads the
> editor in the browser via `<script>`; it does not link against or derive
> from it). If the GPL dependency doesn't suit your distribution needs,
> obtain a commercial CKEditor license or swap in a permissively-licensed
> editor (e.g. Quill, TipTap, Trix).
> See the [CKEditor licensing page](https://ckeditor.com/legal/ckeditor-oss-license/).

---

## Front-end libraries (bundled, self-hosted)

Since v2.132.6 all front-end libraries are **bundled** in this repository under
[`public/assets/vendor/`](public/assets/vendor/) and served from the
application itself — the Content-Security-Policy in
[`src/bootstrap.php`](src/bootstrap.php) allows no third-party hosts. Versions
below are taken from each bundled file's banner.

| Project | Version | License | Purpose |
|---|---|---|---|
| [Bootstrap](https://github.com/twbs/bootstrap) | 5.3.3 | MIT | UI framework / CSS |
| [Bootstrap Icons](https://github.com/twbs/icons) | 1.11.3 (incl. woff/woff2 fonts) | MIT | Icon set |
| [CKEditor 5](https://github.com/ckeditor/ckeditor5) | 43.3.1 | **GPL-2.0-or-later** (or commercial) | Rich-text editor |
| [Chart.js](https://github.com/chartjs/Chart.js) | 4.5.1 | MIT | Report charts |
| [Mermaid](https://github.com/mermaid-js/mermaid) | 10.9.6 | MIT | Diagrams in docs |
| [driver.js](https://github.com/kamranahmedse/driver.js) | 1.3.4 | MIT | Onboarding tour |
| [Sortable.js](https://github.com/SortableJS/Sortable) | 1.15.0 | MIT | Drag-and-drop reordering |

Minified bundles may embed their own upstream dependencies (e.g. Mermaid
bundles DOMPurify); those remain under their respective upstream licenses as
part of the bundle.

> **Exception — the web installer.** The standalone installer page
> ([`public/install/index.php`](public/install/index.php)) still loads
> Bootstrap 5.3.2 and Bootstrap Icons 1.11.3 from `cdn.jsdelivr.net` at page
> load; those copies are not bundled. This affects only the one-time setup
> wizard, which is deleted after installation.

---

## PHP runtime dependencies (Composer `require`)

Installed under `vendor/`. Versions reflect the committed `composer.lock`.

| Project | Version | License | Purpose |
|---|---|---|---|
| [league/commonmark](https://github.com/thephpleague/commonmark) | 2.8.0 | BSD-3-Clause | Markdown → HTML |
| [phpmailer/phpmailer](https://github.com/PHPMailer/PHPMailer) | 6.12.0 | **LGPL-2.1-only** | Email (SMTP) |

### Transitive runtime dependencies

| Project | Version | License | Pulled in by |
|---|---|---|---|
| [league/config](https://github.com/thephpleague/config) | 1.2.0 | BSD-3-Clause | league/commonmark |
| [dflydev/dot-access-data](https://github.com/dflydev/dflydev-dot-access-data) | 3.0.3 | MIT | league/config |
| [nette/schema](https://github.com/nette/schema) | 1.3.5 | BSD-3-Clause | league/config |
| [nette/utils](https://github.com/nette/utils) | 4.1.3 | BSD-3-Clause | nette/schema |
| [psr/event-dispatcher](https://github.com/php-fig/event-dispatcher) | 1.0.0 | MIT | league/commonmark |
| [symfony/deprecation-contracts](https://github.com/symfony/deprecation-contracts) | 3.6.0 | MIT | league/commonmark |
| [symfony/polyfill-php80](https://github.com/symfony/polyfill-php80) | 1.33.0 | MIT | nette/utils |

> `nette/schema` and `nette/utils` are tri-licensed (BSD-3-Clause OR GPL-2.0-only
> OR GPL-3.0-only); BSD-3-Clause is selected here.

---

## PHP development dependencies (Composer `require-dev`)

Used for testing only. **Not shipped** to end users and not required to run the
application. Exclude `vendor/` dev packages from production deployments
(`composer install --no-dev`).

| Project | Version | License | Purpose |
|---|---|---|---|
| [phpunit/phpunit](https://github.com/sebastianbergmann/phpunit) | 10.5.63 | BSD-3-Clause | Test framework |
| [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) | 7.10.0 | MIT | HTTP client (tests) |
| [guzzlehttp/psr7](https://github.com/guzzle/psr7) | 2.8.0 | MIT | PSR-7 implementation |
| [guzzlehttp/promises](https://github.com/guzzle/promises) | 2.3.0 | MIT | Promises (Guzzle) |
| [psr/http-client](https://github.com/php-fig/http-client) | 1.0.3 | MIT | PSR-18 interface |
| [psr/http-factory](https://github.com/php-fig/http-factory) | 1.1.0 | MIT | PSR-17 interface |
| [psr/http-message](https://github.com/php-fig/http-message) | 2.0 | MIT | PSR-7 interface |
| [ralouphie/getallheaders](https://github.com/ralouphie/getallheaders) | 3.0.3 | MIT | Header polyfill |
| [nikic/php-parser](https://github.com/nikic/PHP-Parser) | 5.7.0 | BSD-3-Clause | PHP parsing (coverage) |
| [myclabs/deep-copy](https://github.com/myclabs/DeepCopy) | 1.13.4 | MIT | Object cloning (mocks) |
| [phar-io/manifest](https://github.com/phar-io/manifest) | 2.0.4 | BSD-3-Clause | PHAR verification |
| [phar-io/version](https://github.com/phar-io/version) | 3.2.1 | BSD-3-Clause | Version constraints |
| [theseer/tokenizer](https://github.com/theseer/tokenizer) | 1.3.1 | BSD-3-Clause | Token → XML (coverage) |
| phpunit/php-code-coverage | 10.1.16 | BSD-3-Clause | Code coverage |
| phpunit/php-file-iterator | 4.1.0 | BSD-3-Clause | File iteration |
| phpunit/php-invoker | 4.0.0 | BSD-3-Clause | Timeout invocation |
| phpunit/php-text-template | 3.0.1 | BSD-3-Clause | Templating |
| phpunit/php-timer | 6.0.0 | BSD-3-Clause | Timing |
| sebastian/* (cli-parser, code-unit, code-unit-reverse-lookup, comparator, complexity, diff, environment, exporter, global-state, lines-of-code, object-enumerator, object-reflector, recursion-context, type, version) | various | BSD-3-Clause | PHPUnit support libraries |

---

*Generated from `composer.lock` and the bundled assets under
`public/assets/vendor/`. To regenerate the PHP portion, run `composer licenses`.*
