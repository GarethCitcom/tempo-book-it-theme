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

## Preview in Playground (no plugin needed)

```bash
npx @wp-playground/cli server \
  --blueprint=blueprint.json \
  --mount=.:/wordpress/wp-content/themes/tempo-studio-manager
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
| `tempo_studio_manager_teacher_roles` | `['teacher']` | Roles that get the teacher chrome |
| `tempo_studio_manager_book_url` | auto-detected, else `/book-classes/` | Page hosting `[dsb_booking]` |
| `tempo_studio_manager_my_classes_url` | auto-detected, else `/my-classes/` | Page hosting `[dsb_register]` |
| `tempo_studio_manager_public_paths` | `[]` | Path prefixes exempt from the login gate |
| `tempo_studio_manager_login_path` | `/sign-in/` | Public path for the theme-owned authentication screen |

## Structure

- `theme.json` — design-system tokens (colours, type scale, spacing,
  radii, shadows) mapped verbatim from `design/_ds/…/fig-tokens.css`;
  `wideSize: 1140px` (the plugin's desktop layouts assume it).
- `blocks/` — three tiny PHP-rendered blocks (no build step):
  `tempo/logo`, `tempo/header-nav` (role-aware links + plugin pill),
  `tempo/portal-strip`. `assets/js/editor-stub.js` gives them Site Editor
  placeholders.
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
- `design/` — design sources (prototypes, tokens); not shipped to production.
