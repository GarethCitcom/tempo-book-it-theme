# Tempo Book It Theme — project memory

Companion theme for the Tempo Book It plugin
(github.com/GarethCitcom/tempo-book-it). The plugin repo is
the source of truth for the integration contract: its
docs/13-developer-reference.md §6/6a and design/Plugin vs Theme Map.
Add that repo to the session when building against it.

## Responsibility split (settled — don't re-litigate)

- THEME owns: header/nav/footer, the orange "Booking portal" strip, page
  width, base font, page BACKGROUND (#F1F3F5 behind booking surfaces —
  the plugin deliberately doesn't paint it; tenants may recolour it via
  the plugin's "Body background colour" setting, unlocked by our
  `add_theme_support( 'dsb-body-background' )`), login/registration.
- PLUGIN owns: all booking-shaped UI, checkout decoration, My Account
  tabs, teacher register. Never restyle .dsb-\* internals from the theme;
  template overrides at {theme}/dsb/ only as a last resort.

## Integration surface the theme may use (nothing else)

- Header pill: dsb_basket_pill(), or roll your own from
  [data-dsb-basket] / [data-dsb-basket-count] / [data-dsb-basket-clock].
- JS: window.dsbBooking — getState() {basketCount, soonestExpires,
  student, isTeacher, helpUrl}, refresh(), on(cb) (dsb:state events).
- PHP: dsb_logo_url(), dsb_login_panel_image_url(),
  dsb_brand_colour('primary'|'secondary'),
  dsb_vocab('class'|'classes'|'student'|…),
  dsb_business_type('singular'|'plural'), dsb_business_name(),
  dsb_registration_available(), dsb_register_member(),
  dsb_booking()/dsb_register(), dsb_help_page_url() ('' = no page)
  template functions.
- CSS: --dsb-\* tokens printed on :root (brand colours included).

## Header spec (approved prototype)

White bg, tenant logo 46px desktop / 34px mobile, 5px bottom border in
brand secondary, orange portal strip below ("Booking portal" / user ·
Need help? · Log out), nav: Book classes · My account · Need help? ·
basket pill. Shell 1140px. "Need help?" is the plugin's [dsb_help] page:
tempo_help_url() wraps dsb_help_page_url(); the strip takes its links from
tempo_portal_strip_links() (filter tempo_book_it_portal_strip_links) and
the header menu gets the item appended by tempo_book_it_nav_append_help()
unless the school's own menu already links there (filter
tempo_book_it_help_in_header_nav to drop it). Both hide when the URL is ''.
The plugin's surfaces use alignwide — set theme.json wideSize ≈ 1140px.
