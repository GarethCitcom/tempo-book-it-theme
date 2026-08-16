# Tempo Book It Theme — companion block theme

WordPress block theme providing the site chrome around the
[Tempo Book It](https://github.com/GarethCitcom/tempo-book-it)
booking plugin. The plugin owns all booking-shaped UI; this theme owns the
header, orange portal strip, footer, 1140px shell, base typography, page
background and login. It contains **no booking UI**.

## Requirements

- WordPress **6.6+** (theme.json v3 — note: v3 shipped in 6.6, so that is the
  floor even though the wider project targets 6.5+)
- PHP 7.4+
- WooCommerce (checkout / My Account pages; the plugin decorates them)

## Branches

- **`development`** — where the work happens. Branch from it for a change,
  merge back into it when the change is done.
- **`main`** — production: what tenants install and what everyone
  downloads. It only ever receives merges from `development`, so every
  commit on it is a state that was fit to release.

Both branches hold the same files. What keeps a *download* to the theme
alone is `.gitattributes`, which marks `design/`, `README.md`,
`CLAUDE.md`, `blueprint.json`, `dsb/` and `.github/` as `export-ignore`:
a zip built by `git archive` — which is what the release workflow and
GitHub's own "Download ZIP" both use — contains only the files WordPress
loads, whichever branch it was built from.

That is worth stating plainly, because the two were once kept apart by
hand as well: development-only paths were deleted from `main` on every
merge. It was never needed — `export-ignore` had already made the
download tidy — and the manual step was fragile enough that `main` ended
up with no README, leaving the repository's front page blank. One shape,
one mechanism.

## Updates

Sites do not get theme updates from wordpress.org — the theme is not
hosted there. `inc/updates.php` supplies them instead, and it works the
same whether the theme was installed by the plugin or by hand, because
the mechanism travels inside the theme.

- `Update URI` in `style.css` takes the theme out of the wordpress.org
  update check entirely. Without it, core could one day offer an
  unrelated theme that happens to share the slug, and overwrite this one.
- On each update check the theme fetches a manifest published as a release
  asset, at
  `releases/latest/download/update.json` — a URL that always resolves to
  the newest release. Nothing successful is stored between requests.
  WordPress already decides how often a check happens (twice daily on
  cron, hourly on the themes screen, every minute on Dashboard →
  Updates), so one request per check is the right cost, and a stored
  answer can only ever make a check report something the network would
  not have. A *failed* fetch is remembered for fifteen minutes, so a site
  with no outbound access is not made to wait on a timeout every time;
  "Check again" retries it immediately.
- If the manifest names a higher version than `style.css`, WordPress
  shows the usual update notice on Appearance → Themes and Dashboard →
  Updates, with one-click update and auto-update support. Anything
  else — no release yet, no outbound network, malformed JSON, a package
  URL that is not HTTPS on a GitHub host — is silent: no notice, no
  error.
- `tempo_book_it_update_manifest_url` and
  `tempo_book_it_update_package_hosts` repoint the channel, should the
  plugin or a tenant mirror ever take it over.

When a site is not seeing a release, **Tools → Site Health → Info → Tempo
Book It theme updates** says which step failed: whether WordPress read
the `Update URI` header, whether the theme's check is hooked at all,
whether this server can fetch the manifest right now, what the last check
concluded, and whether the theme appears in the WordPress update list.
Silence in the admin has one cause per line there.

### Cutting a release

1. On `development`, bump `Version:` in `style.css`. This is the number
   every site compares against, so nothing ships without it. Bumping it
   here rather than on `main` keeps `main` a branch that is only ever
   merged into.
2. Merge `development` into `main`.
3. Either tag the merge commit `vX.Y.Z` and push the tag, or run
   **Actions → Release → Run workflow** against `main` — the manual route
   derives the tag from the header and creates it, so there is nothing to
   type.

`.github/workflows/release.yml` does the rest: it refuses a tag that
disagrees with the header, refuses to republish a version that already
has a release, builds `tempo-book-it-theme.zip` (unpacking to
a folder named exactly `tempo-book-it-theme/`, which is what lets
WordPress update in place — GitHub's own source zip unpacks to a
versioned folder and cannot), writes `update.json` from the theme
headers, and publishes both as release assets.

Point manual installers at that release asset rather than "Download ZIP",
so their theme folder is named correctly from the start.

## Preview in Playground (no plugin needed)

Run this from a `development` checkout — `blueprint.json` is not on `main`.

```bash
npx @wp-playground/cli server \
  --blueprint=blueprint.json \
  --mount=.:/wordpress/wp-content/themes/tempo-book-it-theme
```

The blueprint installs WooCommerce, activates the theme, creates demo pages
(front page, Book classes, My classes), adds a `teacher` demo user
(`teacher` / `password`) and drops in a Playground-only mu-plugin that stubs
the plugin's basket pill and `window.dsbBooking` API so the live pill and
hold countdown are visible. Log in as `admin` / `password` for the customer
chrome, or `teacher` / `password` for the teacher chrome.

## How it fits the plugin (integration contract)

- **PHP bridge** (all `function_exists`-guarded, theme works standalone):
  `dsb_basket_pill()`, `dsb_logo_url()`, `dsb_vocab()` — wrapped by
  `tempo_logo_url()`, `tempo_vocab()` etc. in `functions.php`.
- **Brand colours**: the plugin's branding settings (`dsb_brand_colour()`)
  are fed into the theme.json palette at runtime
  (`wp_theme_json_data_theme`), with light tints computed from them — so
  the header border, portal strip, headings, links, buttons and the login
  screen all follow the plugin's two brand colours automatically. The
  theme.json values are only the fallback when the plugin is absent.
- **Login panel image**: Settings → Branding can supply an optional
  full-bleed image for the right side of `/sign-in/`. The built-in studio
  illustration remains the fallback when no image is selected.
- **Nav URLs**: "Book classes" / "My classes" links auto-detect the pages
  hosting `[dsb_booking]` / `[dsb_register]` (cached daily, flushed on
  page save); the filters below still override.
- **Need help?**: the plugin's `[dsb_help]` page (`dsb_help_page_url()`,
  wrapped by `tempo_help_url()`) is linked from the portal strip and
  appended to the header menu — both only while the plugin offers the page,
  and the menu item steps aside when a school's own menu already links
  there. Filters: `tempo_book_it_help_url`, `tempo_book_it_portal_strip_links`
  (the strip's link list), `tempo_book_it_help_in_header_nav`.
- **JS**: the plugin's `dsb-booking.js` installs `window.dsbBooking` and
  auto-fills `[data-dsb-basket]` / `[data-dsb-basket-count]` /
  `[data-dsb-basket-clock]` — the theme ships **no basket JS of its own**.
- **Roles**: the plugin registers `teacher`; teachers get "Teacher portal" +
  a collapsed nav (My classes · My account).
- **WooCommerce account pages**: the plugin keeps ownership of its custom
  Bookings / Credit / Students tabs and account settings. The theme only
  presents WooCommerce's required Account details, Payment methods, Orders
  and single-order screens, using Woo's forms, endpoint hooks, gateway actions
  and order templates rather than replacing their behaviour.
- **Template overrides**: `dsb/` is the plugin's override location — see
  `dsb/README.md`. Last resort only; never restyle `.dsb-*` internals.
  The directory is documentation, not machinery: it ships empty here and
  not at all on `main`, and the plugin's loader simply finds no override
  until someone creates the path.

## Members-only

The whole front end requires login (`template_redirect` gate in
`functions.php`); logged-out visitors are redirected to the theme-owned
`/sign-in/` experience. Sign-in, password recovery and password reset all
use WordPress core authentication behind the custom route. The privacy policy
page stays public.

The same route provides public registration when the companion plugin exposes
`dsb_registration_available()`. An independent student receives one customer
account. A parent/guardian submission creates a parent account plus a linked,
parent-managed student account. The real login account receives a secure
set-password email only after the complete operation succeeds.

### Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `tempo_book_it_teacher_roles` | `['teacher']` | Roles that get the teacher chrome |
| `tempo_book_it_admin_roles` | `['administrator']` | Roles that get the school-admin header menu (`manage_options` also qualifies) |
| `tempo_book_it_book_url` | auto-detected, else `/book-classes/` | Page hosting `[dsb_booking]` |
| `tempo_book_it_my_classes_url` | auto-detected, else `/my-classes/` | Page hosting `[dsb_register]` |
| `tempo_book_it_public_paths` | `[]` | Path prefixes exempt from the login gate |
| `tempo_book_it_login_path` | `/sign-in/` | Public path for the theme-owned authentication screen |

## Structure

- `theme.json` — design-system tokens (colours, type scale, spacing,
  radii, shadows) mapped verbatim from `design/_ds/…/fig-tokens.css`;
  `wideSize: 1140px` (the plugin's desktop layouts assume it).
- `blocks/` — four tiny PHP-rendered blocks (no build step):
  `tempo/logo`, `tempo/header-nav` (role-aware links + plugin pill),
  `tempo/classic-nav` (the registered header menu), `tempo/portal-strip`.
  `assets/js/editor-stub.js` gives them Site Editor placeholders.
- `inc/nav-menu.php` — classic header menus: registers three role-scoped
  menu locations (edited under Appearance → Menus) — `header` (students &
  parents), `header-teacher` (teachers), `header-admin` (school admins) —
  each visible only to its own audience. Assigning a menu to a role's
  location hands that role's header links entirely to the menu: the
  pinned `tempo/header-nav` block (basket pill + Book classes / My
  classes) stops rendering for that role. Until then the pinned links
  stay and the menu area falls back to a bare "My account" link. Also
  strips WooCommerce's auto-hooked account/mini-cart icon blocks from any
  Navigation block, and provides the display settings in **Customize →
  Header navigation**: show icons & text on links (each item picks a
  built-in icon or uploads its own image on the Menus screen), and mobile
  behaviour — collapse behind a menu button, or keep links inline as
  icons only.
- `parts/`, `templates/`, `patterns/` — chrome parts and page templates.
- `inc/woocommerce-account.php`, `inc/woocommerce-checkout.php`,
  `assets/css/woocommerce-account.css`, `assets/css/woocommerce-checkout.css`,
  `assets/js/woocommerce-checkout-boot.js`,
  `assets/js/woocommerce-checkout.js`, `assets/css/woocommerce-order-received.css` and
  `woocommerce/myaccount/view-order.php` — scoped presentation for Woo's
  native account, checkout and order-confirmation endpoints; no payment,
  account, coupon or order logic.
- `assets/fonts/` — Poppins 600/700 + Montserrat variable, bundled locally
  (no Google Fonts requests).
- `inc/updates.php` — the update channel described above; no other file
  in the theme knows about it.
- `.github/workflows/release.yml`, `.gitattributes` — the release build,
  and the rules that keep it (and every other archive) to theme files
  only. Neither ends up in the zip a tenant installs.
- `screenshot.png` — 1200×900 brand card for Appearance → Themes: the
  Tempo Book It logo on the brand navy, with the product greens and the
  theme's bundled Poppins/Montserrat faces.
- `design/` — design sources (prototypes, tokens). On the `development`
  branch only; never shipped to tenants.
