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

- **`main`** — what tenants install: the files WordPress loads, and
  nothing else. No design sources, no documentation (this README
  included), no project-memory file, no Playground blueprint.
- **`development`** — where the work happens, and the only place the
  project is documented. Everything on `main` plus `design/` (prototypes
  and design-system tokens), `README.md`, `dsb/README.md`, `CLAUDE.md`
  (the responsibility split between theme and plugin) and
  `blueprint.json` (the Playground preview below).

Develop on `development`, then merge it into `main` to release. Git keeps
the development-only paths deleted on `main` on its own: `development`
never touches those files after the split, so a merge has nothing to
reintroduce. If one of them is ever edited on `development`, the merge
raises a modify/delete conflict — resolve it by deleting on `main`.

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
- `screenshot.png` — 1200×900 brand card for Appearance → Themes: the
  Tempo Book It logo on the brand navy, with the product greens and the
  theme's bundled Poppins/Montserrat faces.
- `design/` — design sources (prototypes, tokens). On the `development`
  branch only; never shipped to tenants.
