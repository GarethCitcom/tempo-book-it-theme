# Task: add a "Need help" page to Tempo Studio Manager

The plugin and theme are already built and live. This is **one additional page** — do not refactor, restyle, or touch existing flows, templates, or CSS beyond what this page needs.

Read `README.md` in this folder for the full spec, then open `Tempo Need Help.dc.html` in a browser — it is the working design (interactive: avenue cards switch the section below, FAQ accordion opens, support form has a sent state). Screenshots in `screens/`.

## What the page is

A standalone portal page (not a My Account tab) that routes logged-in users to one of two help avenues:

1. **School help** — questions for the SJP office (classes, terms, uniform, moving days). Email link to the office + an FAQ accordion. Content is tenant-owned.
2. **Technical help** — problems with the booking system itself (payments, holds, basket, emails). A "try these first" quick-fixes list + an email support form to the plugin vendor. Tempo appears only as small print.

The page is **plugin-owned** (it's booking-shaped UI), rendered inside the theme's chrome like every other plugin page. Template overridable at `{theme}/dsb/` per the existing convention.

## Plan before you code

1. Find how existing plugin pages register a front-end page/endpoint and reuse that mechanism. Do not invent a new one.
2. Propose: the page slug, where the "Need help?" link goes in the portal strip (theme owns the strip — it should consume the same JS/data API the basket count uses, or a filter, not hardcoded markup), and how FAQ/quick-fix content is stored (see README §5). Show me the plan, wait for sign-off, then build.

## Hard scope rules

- Logged-in users only, same access rule as the rest of the portal. **No login-help content anywhere on the page** — a user reading it is logged in by definition (we removed "can't log in" from the design deliberately; login help belongs on the login screen).
- Both email addresses, all FAQ items, and all quick fixes are **tenant-editable settings**, not hardcoded strings. Defaults ship with the SJP content from the README.
- The support form emails the configured support address. No ticket system, no new DB tables beyond an option/CPT for the editable content.
- Vocabulary swaps (student/class/teacher) from the existing settings must apply to this page's copy, and the copy must survive 40% longer labels.
- Scoped, prefixed CSS with `--dsb-*` tokens, same as the rest of the plugin. No emoji, no icon library; unicode glyphs only (the chevron is `⌄`, the numbered discs are styled spans).

## Acceptance

- Page renders inside any theme with the plugin's self-contained look; matches the design at ≥1000px (two cards side by side) and below (stacked, 44px hit targets).
- Avenue switch, accordion, and form work without a full page reload where the rest of the plugin uses AJAX; degrade to full reload otherwise.
- Form sends: to the configured address, includes the user's account email and the referring page automatically (the design promises this in the helper text), honours the vendor small-print toggle.
- Sent state replaces the form (success panel, no redirect).
- All strings translatable, all settings escape output.
