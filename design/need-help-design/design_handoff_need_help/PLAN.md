# Need help page — implementation plan (awaiting sign-off)

Two repos: **plugin** `wp-content/plugins/tempo-book-it` (branch off `main`, working branch `claude/need-help-page`) and **theme** `wp-content/themes/tempo-book-it-theme` (branch off `development`). Plugin ships the page, settings, email and JS; theme adds one link + one bridge function.

## 1. Page registration — reuse the auto-created-page mechanism

Same shape as `[dsb_booking]` / `[dsb_register]`:

| Piece | Where | What |
|---|---|---|
| Shortcode | new `includes/Frontend/HelpPage.php` | `[dsb_help]`; `init()` registers shortcode + no-JS POST handler; `render()` mirrors `RegisterPage::render()`: licence gate → `LockedNotice::frontend_html()`; logged-out → same in-page "Please log in" notice with `wp_login_url($base_url)` as `FlowScreens.php:72-81` (on this site the theme's members-only `template_redirect` gate fires first, so a logged-out visitor is redirected to `/sign-in/?redirect_to=…` — QA item 1 satisfied by the existing rule); backfill option like `RegisterPage:75-79` |
| Auto page | `Services/PageSetupService::install()` | third `install_page( 'dsb_help_page_id', 'Need help?', 'need-help', '[dsb_help]' )` — created on activation **and** by `maybe_upgrade`-style call on `plugins_loaded` when the option is missing (existing live installs never re-activate) |
| URL | `template-functions.php` | `dsb_help_page_url()` via `PageLocator::url('dsb_help_page_id','dsb_help')`, next to `dsb_booking_page_url()`; add to `DataEraser:57-58`, Settings → General page dropdown (`SettingsPage:322/340` + save `:53-59`), dashboard `page_check` |
| Templates | `templates/help/page.php` (+ `partials/faq.php`, `partials/fixes.php`, `partials/form.php`, `partials/sent.php`) via `TemplateLoader::render()` → overridable at `{theme}/dsb/help/…` |
| Assets | `Assets::enqueue_help()` mirroring `enqueue_register()`: shared `enqueue_styles()` stack + `dsb-help.css` (dep `dsb-booking-ui`) + `dsb-help.js` with `dsbHelpConfig` {restUrl, nonce, i18n, vocab} |
| Root markup | `<div class="dsb-root alignwide" data-dsb-help-root><div class="dsb-help" data-dsb-help-app>…` — new scope `.dsb-help`, added to the scope list in the `dsb-booking.css` header comment; own border-box reset like `.dsb-app` |

Page slug: **`/need-help/`**, page title "Need help?" (translatable). Not a My Account tab (per PROMPT).

## 2. "Need help?" link — where and how

**Portal strip, right-hand cluster**: `Becky Smith · Need help? · Log out`. Reasoning: it's a portal-level utility, present on every logged-in page (the strip already bails when logged out, matching the page's access rule), and it stays visible for teachers too (they may hit technical problems in the register). Header nav stays as-is.

Mechanism (no hardcoded URL in the theme):
- Plugin: `dsb_help_page_url()` (PHP) and `helpUrl` added to `header_state()`/`getState()` (JS) so any theme can consume it either way; documented in `docs/13` §6.
- Theme: `tempo_help_url()` in `functions.php` — byte-for-byte the `tempo_book_url()` shape (`function_exists('dsb_help_page_url')` → filter `tempo_book_it_help_url`, else `''`). `blocks/portal-strip/render.php` prints the link **only when the URL is non-empty**, using the same padding/negative-margin hit-area trick as Log out (`chrome.css:356-366`), plus an `aria-current` when on the page. Filter `tempo_book_it_portal_strip_links` (array of `{label,url}`) so a child theme can add/remove strip links — that is the "filter, not hardcoded markup" seam.
- Old plugin + new theme = no link, nothing breaks; new plugin + old theme = page exists, reachable by URL/menu.

## 3. Content storage — `dsb_settings` keys, no CPT

Everything into the existing single `dsb_settings` option (`SettingsService::defaults()` — new keys need no migration). New Settings section **"Help page"** (`help`) in `SettingsPage::render()` between `account` and `first_time`:

| Key | Type / sanitiser | Default |
|---|---|---|
| `help_office_email` | `sanitize_email` | `sjptheatrearts@yahoo.co.uk` |
| `help_support_email` | `sanitize_email` | `support@tempo-book-it.com` |
| `help_show_response_note` | checkbox | `1` |
| `help_response_note` | textarea | "We usually reply within one working day…" |
| `help_vendor_attribution` | enum `small_print`/`hidden` | `small_print` |
| `help_faq` | repeatable rows `[ {q,a}, … ]` | 5 SJP items from the design |
| `help_fixes` | repeatable rows `[ {t,d}, … ]` | 4 items from the design |

Repeater UI: no add/remove-row JS repeater exists in the plugin, so I'll add a small one (`admin-tempo.js`, `[data-dsb-repeater]`: add row / remove row / ↑↓ reorder buttons, rows are `help_faq[n][q]` / `help_faq[n][a]` inputs, saved in submitted order → order = display order). Sanitised in `SettingsService::update()` as a whitelist walk (`sanitize_text_field` question/title, `sanitize_textarea_field` answer/body, drop empty rows, cap 30). Readers `SettingsService::help_faq()` / `help_fixes()` return defaults when the stored list is empty; a "Restore defaults" button per list.

Defaults are stored as English source strings and pass through vocab at render time: `Vocab`-substituted via `Templates::substitute()`-style tokens (`{student}`, `{class}`, `{classes}`, `{teacher}`, `{term}`) so "your child" → "you" swaps happen for adult-student vocab. Defaults use tokens (`"What should my {student} wear to class?"`); tenants may type tokens too. Also add `dsb_help_faq` / `dsb_help_fixes` filters (theme/addon override) — cheap, no new mechanism.

Design copy that is hardcoded page chrome (eyebrow, H1, intro, card titles/descriptions/examples, section headings, form labels, sent panel, vendor small print) = translatable strings in the template with `sprintf(__(), Vocab::…)`. Vendor line uses `help_support_email`.

## 4. Support form — send path

- Form posts to `admin_url('admin-post.php')` with `action=dsb_help_request` + nonce (`AccountActions::guard()`/`finish()` shape). Handler: `auth_redirect()` → `check_admin_referer` → sanitise (name, reply email `is_email`, message required, honeypot) → send → redirect back to the page with `?dsb_sent=1#dsb-help-tech` (sent panel replaces the form, tech avenue pre-selected; no ticket system).
- Enhanced path: `POST dsbook/v1/help/request` (`RestApi` controller, `permission_callback` = logged-in, same sanitiser shared with the admin-post handler) — `dsb-help.js` intercepts submit, `restFetch`, swaps in the sent panel; on network/5xx error falls back to native `form.submit()` (pattern at `dsb-booking.js:1226-1245`).
- Email: `wp_mail()` + `Mailer::record('help_request', …)` per the `StudentProfileDocument` precedent (this message is *from* a member *to* the vendor — the WC-branded member template registry is the wrong fit). Body: name, reply email, **account email (`wp_get_current_user()->user_email`)**, user ID/display name, **referring page (`wp_get_referer()` sanitised, or the page URL)**, site URL, plugin version, browser UA, message. `Reply-To:` = reply email. Recipient = `help_support_email`. Rate-limit: 5 per user per hour (transient) → friendly notice.
- Success copy "Support will reply to {email}" uses the submitted reply address.

## 5. Front-end behaviour

- Avenue cards are `<button>`s (44px), `aria-pressed`, swap `[data-dsb-avenue]` panels client-side; no-JS: both panels rendered, cards are `<a href="#dsb-help-school">`/`#…-tech` links, JS hides the inactive one. Default = school, or tech when `?dsb_sent=1` / `#dsb-help-tech`.
- FAQ accordion: `<button aria-expanded>` rows + `hidden` answers, one open, first open on load, `⌄` glyph rotates 150ms. Numbered discs are styled spans. No emoji/icon libs.
- CSS: `.dsb-help` scoped, `--dsb-*` tokens only (`--dsb-color-bg-brand-subtle`, `--dsb-color-status-success-bg/fg`, `--dsb-radius-*`, `--dsb-size-control-min`, `--dsb-type-*`); breakpoint 1000px for the 1fr 1fr grids; `word-break: break-all` on the email; card footer link `margin-top:auto`. Column width 800px max inside the theme's `alignwide` shell.

## 6. Housekeeping

- Plugin: `docs/13` §6 (new template fn + JS `helpUrl`), `docs/04` or new `docs/19-help-page.md` (settings), `readme.txt` changelog, `includes/Frontend/CLAUDE.md` one paragraph, `Privacy/PersonalData` untouched (nothing stored). Lint with `php -l`.
- Theme: `CLAUDE.md` integration-surface list gains `dsb_help_page_url()`/`tempo_help_url()`; `chrome.css` strip link style; version bump to be confirmed with you (theme has self-hosted updates).
- Not touched: existing flows/templates/CSS, checkout, My Account tabs.

## Open points for you

1. Strip position (right cluster, before "Log out") — OK, or would you rather it in the header nav / as a WP menu item?
2. `dsb_settings` + custom repeater vs textarea-with-`---` separator: I recommend the repeater (README §5 asks for orderable rows); say if you'd rather I keep it to a textarea.
3. Slug `need-help` and shortcode `[dsb_help]` — OK?
4. Plugin working branch name `claude/need-help-page`; theme branch off `development` — OK?
