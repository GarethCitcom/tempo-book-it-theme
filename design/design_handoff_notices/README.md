# Tempo notice system — implementation spec

Companion to `PROMPT.md`. Design source: `Woo Notice Options.dc.html` (opens standalone in a browser). Screens in `screens/`.

Three channels, one shared visual language: white surface, status colour used as a keyline and a glyph disc, never as a full-bleed fill. Note: SJP Theatre Arts is the tenant - their branding has been used for this build.

---

## 1. Tokens

Plugin-side tokens are `--dsb-*` with theme tokens as the fallback chain, so the plugin looks right on any theme:

```css
:root {
  --dsb-surface: var(--color-bg-surface, #ffffff);
  --dsb-border: var(--color-border-default, #e5e7eb);
  --dsb-text: var(--color-text-primary, #1f2937);
  --dsb-text-muted: var(--color-text-muted, #6b7280);
  --dsb-brand: var(--color-brand-primary, #f36f21);
  --dsb-brand-alt: var(--color-brand-secondary, #3d0b5e);

  --dsb-success-bg: var(--color-status-success-bg, #ecfdf5);
  --dsb-success-fg: var(--color-status-success-fg, #047857);
  --dsb-danger-bg: var(--color-status-danger-bg, #fef2f2);
  --dsb-danger-fg: var(--color-status-danger-fg, #b91c1c);
  --dsb-warning-bg: var(--color-status-warning-bg, #fffbeb);
  --dsb-warning-fg: var(--color-status-warning-fg, #92400e);
  --dsb-info-bg: var(--color-status-info-bg, #eff6ff);
  --dsb-info-fg: var(--color-status-info-fg, #1d4ed8);
}
```

Status glyphs are unicode, never an icon font or emoji: `✓` success, `!` error and warning, `i` info (italic).

Type: Montserrat (theme inherits). Body 14px/1.5, toast body 12.5px/1.5, titles 700–800 weight in `--dsb-brand-alt` (purple) except inline titles, which take the status foreground colour.

---

## 2. Inline (default) — screens/01-inline.png

Replaces the current black keyline + tinted fill.

```
┌─────────────────────────────────────────────────┐
│▌ (✓) Saved                                  ×   │   ▌ = 4px status keyline, left
│     Payment method successfully added.          │
└─────────────────────────────────────────────────┘
```

- Container: `background: var(--dsb-surface)`, `border: 1px solid var(--dsb-border)`, `border-left: 4px solid <status-fg>`, `border-radius: 10px`, `padding: 13px 14px`, `display: flex; gap: 12px`.
- Glyph disc: 22px circle, `background: <status-bg>`, `color: <status-fg>`, 12px/800, `margin-top: 1px`, aligned to the first text line.
- Title (optional per notice, omit for single-line notices): 14px/700 in `<status-fg>`.
- Body: 14px/1.5 in `--dsb-text`.
- Dismiss `×`: 17px, `--dsb-text-muted`, hover fills `--color-bg-subtle`. Only on non-actionable notices; error notices tied to a form keep no dismiss.
- Action link (e.g. "Browse classes ›"): 13px/700 in `--dsb-brand`, right-aligned, `align-items: center` on the container when there is no title.
- Multiple notices stack with `gap: 12px`.

CSS targets — restyle **both** notice systems, they coexist:

- Blocks: `.wc-block-components-notice-banner`, `.wc-block-components-notice-banner__content`, and its SVG icon (hide the SVG, render the disc with a pseudo-element or a wrapper span).
- Classic: `.woocommerce-message`, `.woocommerce-error`, `.woocommerce-info` (My Account, add-payment-method, and most plugin notices).

Keep Woo's `role="alert"` / `aria-live` attributes intact. Do not remove the element from the DOM order.

---

## 3. Toast — screens/03-toast.png

For passing confirmations. Fired by relocating a marked notice, not by generating new content.

- Region: `position: fixed`, top right, `top: 88px` (clears theme header + portal strip; use the theme's header height variable if it exists), `right: 16px`, `z-index: 9000`, `display: flex; flex-direction: column; gap: 10px`, `pointer-events: none` on the region, `auto` on each toast.
- Toast: 300px wide (`max-width: calc(100vw - 32px)`), `background: var(--dsb-surface)`, `border-radius: 12px`, `box-shadow: 0 12px 32px rgba(17,17,26,0.18)`, `overflow: hidden`.
- Inside: 22px glyph disc, title 13px/800 purple, body 12.5px/1.5 muted, dismiss `×`, all in a `13px 14px` flex row with `gap: 10px`.
- Progress bar: 3px full-width strip in `<status-fg>` at the bottom, `transform-origin: left`, `animation: dsbBar 5s linear both` (`scaleX(1) → scaleX(0)`).
- Entry: `translateX(24px)` + opacity, 200ms `cubic-bezier(0.2,0.8,0.2,1)`. Exit: reverse, 160ms.
- Lifetime 5s. Pause the timer and the bar on hover and on focus within.
- Max 4 on screen, oldest removed first. Dedupe by `data-dsb-key`: an identical key still on screen resets its timer instead of stacking.
- Mobile (<600px): full width, `left: 16px; right: 16px`, still top-anchored.
- Region is `aria-live="polite" aria-atomic="false"`. Never move focus to a toast.

Mobile placement note: the booking flow has a sticky bottom bar below 1000px, so toasts stay top-anchored on mobile rather than moving to the bottom.

---

## 4. Popup — screens/02-popup.png

For blocking states only.

- Backdrop: `rgba(35, 10, 55, 0.55)` (brand purple, not neutral black), fades in 160ms, `z-index: 9500`.
- Dialog: `max-width: 420px`, `width: calc(100% - 48px)`, `background: var(--dsb-surface)`, `border-radius: 16px`, `padding: 24px`, `box-shadow: 0 24px 60px rgba(17,17,26,0.28)`, entry `dsbPop` 180ms (`translateY(10px) scale(0.97)` → none).
- Header row: 40px glyph disc + title 18px/800 in purple, `gap: 12px`.
- Body: 14px/1.6 muted, `text-wrap: pretty`.
- Buttons, right-aligned, `gap: 10px`, min-height 44px, pill radius:
  - Secondary "Not now" — 1px border `--dsb-border`, muted label, hover border/label purple.
  - Primary — orange fill `--dsb-brand`, white 13px/800 label, hover purple. The label is a verb tied to the recovery path ("Add it again", "View bookings", "Join waiting list"), not "OK".
- `role="alertdialog"`, `aria-labelledby` / `aria-describedby` wired to the title and body. Focus moves to the dialog on open, is trapped, returns to the triggering element (or `document.body` when the notice arrived on page load) on close.
- Escape and backdrop click both close, and closing must leave the same notice available inline underneath, so nothing is lost by dismissing.
- One popup at a time. If a second arrives, queue it.
- The primary button may carry a URL (recovery path) — this is a link styled as a button, not a new notice.

---

## 5. Plugin → theme contract

`dsb_notice( string $message, string $type = 'notice', array $args = [] )` wraps the message:

```php
$args = [
  'channel' => 'toast',            // inline | toast | popup, default inline
  'key'     => 'credit_applied',   // stable, used for dedupe and for the filter
  'title'   => 'Credit applied',   // required for toast and popup, ignored inline unless set
  'cta'     => [ 'label' => 'Add it again', 'url' => $url ], // popup only, optional
];
```

It emits, then hands to `wc_add_notice()`:

```html
<span
  class="dsb-notice"
  data-dsb-channel="toast"
  data-dsb-key="credit_applied"
  data-dsb-title="Credit applied"
  >£15.00 of your credit has come off this booking.</span
>
```

The channel passes through `apply_filters( 'dsb_notice_channel', $channel, $key, $type )` before it is written, so a tenant can force `inline` globally.

Theme controller:

1. On DOM ready, and again on `wc-blocks_added_to_cart` / the plugin's own step-transition event, scan for `.dsb-notice[data-dsb-channel]` inside any Woo notice container.
2. `toast` → build the toast from the marker's text/title/type, then remove the host notice element from the flow.
3. `popup` → same, into the dialog, but **leave the inline notice in place** underneath (visually hidden is not enough, keep it rendered) so dismissing the dialog does not destroy the message.
4. `inline` or no marker → leave alone, CSS handles it.
5. If the controller has not run (JS off, script error), everything stays inline and styled. That is the whole fallback story.

Type mapping: Woo `success` → success, `error` → danger, `notice`/`info` → info. Warning is plugin-only, declared with `data-dsb-type="warning"` when the wrapper is used.

---

## 6. QA checklist

- [ ] Add payment method → success renders inline restyled (classic markup).
- [ ] Empty Orders tab → info renders inline with the action link.
- [ ] Checkout validation error → inline, next to the form, focus lands on the first invalid field, no popup.
- [ ] Hold expiry on checkout → popup, purple backdrop, recovery CTA works, message still present inline after dismissal.
- [ ] Add to basket via the guided flow → single toast, no layout shift, gone in 5s.
- [ ] Three toasts in quick succession → stacked, max 4, same-key repeats reset rather than duplicate.
- [ ] Escape closes popup, focus returns, second queued popup opens after.
- [ ] 375px: toast full width and readable, popup fits with 24px gutters, sticky bottom bar unobstructed.
- [ ] `prefers-reduced-motion: reduce` → opacity only, progress bar jumps rather than animates.
- [ ] JS disabled: every notice inline, styled, no orphan empty containers.
- [ ] Screen reader: toast announced politely, popup announced as an alert dialog, inline unchanged from Woo's default semantics.
