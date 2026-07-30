# Task: replace WooCommerce's default notices with the SJP notice system

You are working on two codebases that ship together:

- **Theme** — the Tempo Book It Theme (a WordPress block theme). Owns page chrome and, from now on, **how a notice looks and where it appears**.
- **Plugin** — Tempo Book It (`[dsb_booking]`, booking/holds/credit/My Account). Owns **what a notice says and how urgent it is**.

Read `README.md` in this folder before writing code. It carries the full visual spec, DOM structures, token values and QA list. The design source of truth is `Woo Notice Options.dc.html` (open it in a browser, it runs standalone) and the PNGs in `screens/`.

## Plan before you code

Do not start editing. First:

1. Inventory every notice the plugin currently produces. Grep the plugin for `wc_add_notice`, `wc_print_notice`, `wc_add_wp_error_notices`, `WC_Blocks` notice contexts, and any `woocommerce_add_error` / `add_notice` wrappers of your own. Produce a table: file, line, type (`success` / `error` / `notice`), the message, and where the user is when it fires.
2. Map each one to a channel using the routing rules below.
3. Show me the table and the plan. Wait for sign-off, then implement.

## Hard scope rules

- **Do not add new notices.** This job replaces the presentation of notices that already exist. If a mapping exercise makes you want a new message, put it in a "suggestions" list at the end instead of writing it.
- **Do not change notice copy** in this pass, except where the README's channel requirements demand a short title for a toast or popup (those are additive, the body text stays).
- **Do not touch the payment form, the Checkout Block, or Woo's own required My Account endpoints.** The notice layer sits around them.
- No new build step, no npm dependency, no React. Vanilla CSS + one small vanilla JS module in the theme.
- Everything degrades: with JS off, or with the theme replaced, every notice must still render inline and readable.

## The three channels

| Channel              | What it means to a parent         | Behaviour                                                                             |
| -------------------- | --------------------------------- | ------------------------------------------------------------------------------------- |
| **inline** (default) | "Here is the state of this page." | Stays in the page flow where Woo put it. Restyled only.                               |
| **toast**            | "Noted, carry on."                | Slides in top right over the page, stacks, times out after 5s, never blocks anything. |
| **popup**            | "Stop and read this."             | Centred dialog over a dimmed backdrop, focus trapped, dismissed only by the parent.   |

The line between them: **would the parent's next action be wrong if they missed this?** If yes it is a popup. If the message only confirms something they just did, it is a toast. Everything else is inline.

## Routing rules the plugin must follow

**popup** — blocking, changes what happens next:

- A hold ran out and places were released (the parent is about to pay for something they no longer have).
- A class filled up between selection and checkout.
- Payment succeeded but the booking could not be completed, or any partial-failure state involving money.
- An account action that cannot be undone and needs acknowledgement (a booking cancelled by the office while the parent was on the page).

**toast** — passing confirmations, the page already shows the result:

- Added to basket / removed from basket.
- Credit applied or removed at checkout.
- Hold extended, or a courtesy "hold ending soon" warning.
- Student profile or photo permission saved.
- Anything fired by the plugin's own AJAX/REST step transitions where the page updates in place.

**inline** — everything else, and always for:

- Form validation on checkout, account details, student profile. Never a popup, never a toast. The parent needs to see the message next to the field they are fixing.
- Empty states Woo produces ("No order has been made yet").
- Anything that arrives on a fresh page load with no user action behind it.
- Anything you are unsure about. Inline is the safe default.

**Never**: a toast as the only carrier of an error that requires an action. If an error is worth a toast it is worth being inline too, so use inline (or popup) instead.

## How the two sides talk

The plugin declares intent, the theme decides presentation. Use the wrapper described in the README (`dsb_notice()` writes a `<span class="dsb-notice" data-dsb-channel="toast" data-dsb-key="…" data-dsb-title="…">` around the message, so the marker travels through both classic notices and the Blocks notice banner without patching Woo internals).

- Theme JS reads `data-dsb-channel` off any rendered notice and relocates it.
- **No marker means inline.** Woo's own notices, and any plugin notice you have not classified yet, therefore keep working with zero changes.
- Theme filter/override: a single `dsb_notice_channel` PHP filter (key, default channel) so a tenant can demote everything to inline without touching the plugin.

## Deliverables

1. Theme: `assets/css/dsb-notices.css` (or the theme's existing convention) restyling `.wc-block-components-notice-banner` **and** the classic `.woocommerce-message` / `.woocommerce-error` / `.woocommerce-info` markup, since My Account and checkout mix both.
2. Theme: `assets/js/dsb-notices.js` — the controller that promotes marked notices to toast or popup, plus the toast region and dialog markup it creates. Enqueue on Woo pages only.
3. Plugin: `dsb_notice()` helper + the `dsb_notice_channel` filter, and the call sites updated per the signed-off table.
4. A short `NOTICES.md` in the plugin documenting the three channels and the routing rules above, written for whoever adds the next notice.
5. The suggestions list of notices that seem missing (do not implement them).

## Acceptance

- Every notice in your table renders in its assigned channel, on desktop and at 375px.
- Checkout validation errors are still inline, still adjacent to the form, still announced.
- JS disabled: all notices inline and legible.
- Keyboard: popup traps focus, Escape closes it, focus returns to where it was. Toasts are reachable but never steal focus.
- `prefers-reduced-motion`: no slide or scale, opacity only.
- No layout shift on the booking flow when a toast fires.
