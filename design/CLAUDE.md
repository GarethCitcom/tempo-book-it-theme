# Tempo Book It — Frontend Redesign (project decisions)

WordPress + WooCommerce plugin ("Tempo Book It", shortcode `[dsb_booking]`) for dance/theatre schools. First tenant: SJP Theatre Arts. Plugin will be sold to other schools. Bound design system: SJP Theatre Arts Design System (`_ds/…175e544c…/`) — always load its bundle + token CSS; compose with its Button/StatusBadge; unicode status glyphs (✓ ! × i •), no emoji, no icon library (numbered discs stand in for step "icons").

## Files
- `Tempo Booking Prototype.dc.html` — main interactive prototype (approved).
- `Tempo Teacher Register.dc.html` — teacher-only register prototype (approved).
- `Plugin vs Theme Map.dc.html` — responsibility map + tenant out-of-the-box mockups.
- `Desktop Layout Options.dc.html` — desktop exploration; option 1a (context rail) was chosen and built into the prototype.
- `Current Plugin Frontend.dc.html` — legacy baseline; don't restyle.

## Settled architecture decisions
- **Plugin owns** all booking-shaped UI: guided flow (student → class → package → review), checkout booking panel, hold countdowns, credit, My Account tabs (Bookings / Credit / Students), teacher register, status language. Scoped, prefixed CSS + `--dsb-*` tokens; self-contained look on any theme; templates overridable at `{theme}/dsb/`.
- **Theme owns** header/nav/footer/portal strip, page width, base font. Plugin exposes basket count/hold data via JS API for the theme's header.
- **Checkout**: plugin decorates the standard WooCommerce checkout (panel above the Checkout Block); never replaces the payment form/gateway.
- **My Account**: plugin registers custom Woo My Account endpoints alongside Woo's required ones (Account details, Payment methods…); never reimplements them.
- **Tenant theming**: settings page = 2 brand colours, logo, vocabulary (student/class/teacher swaps — copy must survive 40% longer labels), hold minutes. Demoed as Tweaks props on the prototype.
- **AJAX**: REST-driven step transitions with pushState + full-reload fallback; hold expiry is server-enforced, countdown is courtesy display.

## Approved UI decisions (booking prototype)
- Header: white bg, colour primary logo (46px desktop / 34px mobile), thick purple bottom border, orange portal strip below.
- Class types: colour keyed to TYPE (not class) via tinted pill on cards + week-tile left border; type filter is a dropdown (user-defined types scale); day filter stays chips.
- Desktop ≥1000px (option "1a"): 1140px shell, main column + 320px sticky context rail (Booking for card, "Your booking journey" steps card, action/CTA card replacing mobile sticky bottom bar); class cards 2-up; packages 3-up with term-dates card below (dates leave the singles card on desktop); WooCommerce payment block in rail on checkout; account tabs vertical left column. Mobile (<1000px) keeps the original stacked design + sticky bottom bar.
- Week view: Mon–Sun columns, click-and-drag pan (mouse) + native touch scroll, dashed "No classes" placeholders; browse aid only — booking continues through guided steps.
- Account type: parent (4-step flow, child switcher) vs adult student (3-step, second-person copy, no Students tab) — prototype prop `accountType`.
- Checkout credit: toggle + "Edit amount" custom input capped at available credit.
- No PLUGIN/THEME boundary badges in client-facing views.

## Approved UI decisions (teacher register — `Tempo Teacher Register.dc.html`)
- Teachers log in to registers only — no booking UI; theme chrome shows only "My classes" + "My account", portal strip reads "Teacher portal".
- Landing: day view with prev/next day navigation (chosen over today-only/week-strip; both remain as `dayViewStyle` tweak options) + "Jump to a date" date picker — desktop: in rail under "Today at a glance"; mobile: under the day nav. Teachers can look weeks ahead.
- Day class cards: type-colour left border, status badge (Not started / In progress x of n / ✓ Completed / "N booked so far" for future days). Past days locked ("registers lock at midnight — office amends"); future days open a read-only **booked list** (no marking).
- Register: statuses Present / Late / Absent as toggle chips (44px), unmarked = "• Expected"; "Mark all present, then flag exceptions" shortcut; completion banner (x of n + %).
- Advance-reported absences: pre-marked "× Absent · reported" with blue info line and reason — register completes without touching them; chips stay live in case the student turns up.
- Extras: headcount check (stepper, mismatch warning vs marked-in-room), walk-in/trial student add (flagged to office), optional "Notes for the office" textarea.
- Complete register: enabled only when all have a status; same-day reopen/amend; autosave copy "office sees updates live". Mobile: sticky bottom bar; desktop (1a rail): context card + register checklist (numbered discs) + action card.
- Protected profiles: 4-digit teacher PIN unlocks emergency/medical/collection details in popup; access window (`protectedMinutes`, default 5) shared across profiles with countdown; amber "!" avatar dot flags a medical note without revealing it. PIN set/changed on teacher My Account — requires account password; sign-in details stay in standard Woo account area.
