# Need help page — implementation spec

Companion to `PROMPT.md`. Design source: `Tempo Need Help.dc.html` (runs standalone in a browser). Screens in `screens/`.

## 1. Page anatomy

```
Theme chrome (already built — header + orange portal strip)
└─ 800px centred column, 28px top pad desktop / 20px mobile
   ├─ Eyebrow "HELP & SUPPORT" + H1 "Need a hand?" + one-line intro
   ├─ Two avenue cards (grid, 1fr 1fr ≥1000px, stacked below)
   └─ Active avenue section (school by default; card click swaps it)
```

Active card: 2px inset ring in brand orange. Inactive: 1px `--dsb-border`. Hover: orange ring. Cards are buttons, min 44px targets throughout.

Each card: Poppins 700 17px purple title, 12px description, three quoted example questions in muted 12px, orange "Ask the school ›" / "Get booking support ›" footer link pinned with `margin-top: auto`.

Card copy:
- School: "Classes and your child" / "Questions for the SJP office about classes, terms and what to bring." Examples: "What should my child wear?", "When does the new term start?", "Can we switch to the Thursday class?"
- Tech: "Something not working?" / "Trouble with booking, paying or logging in on this website." Examples: "My payment didn't go through", "My basket emptied itself", "The page shows an error"

(Note: the tech card description still says "logging in" as a symptom people search for, but the page body deliberately has no login help — logged-in-only page.)

## 2. School avenue

- H2 "Ask the school" (Poppins 700 19px purple).
- Email card: `--color-bg-brand-subtle` tinted panel, label "Email the office", the address as a large orange `mailto:` link (`word-break: break-all`), then the response note (toggleable setting): "We usually reply within one working day. For anything urgent on a class day, speak to your teacher at the studio."
- "Common questions" label + accordion. Each item: white card, 1px inset border, 12px radius; question row (600 13px, 44px min height) with a 22px circle chevron `⌄` that rotates 180° when open (150ms). One item open at a time; clicking the open item closes it; first item open on load. Answer: 13px/19px body, `0 14px 14px` padding.
- Footer line: "Can't see your question? **Email the office** and we'll help." (mailto link.)

Default FAQ content (5 items) — copy verbatim from the design source `faqData` array in `Tempo Need Help.dc.html`.

## 3. Technical avenue

- H2 "Booking system support".
- "Try these first" — 4 cards, each: 24px purple numbered disc (Poppins 700 12px white), bold 13px title, muted 12px body. Content verbatim from the design's `fixes` array: payment failed / basket emptied (hold expiry) / page stuck / not getting confirmation emails.
- "Still stuck? Email support" + the form card:
  - Your name, Email for our reply (side by side ≥1000px), both prefilled from the logged-in user.
  - "What went wrong?" textarea, placeholder "Tell us what you were trying to do and what happened instead".
  - Helper small print: "Your account email and the page you were on are included automatically, so support can find your booking without asking." **The implementation must actually do this** (user email + referring URL in the email body).
  - "Send to support" — orange pill button, hover purple, 44px min height.
- Sent state replaces the form: green `--dsb-success-bg` panel, ✓ disc, "Message sent", "Support will reply to {email}. Most messages get an answer within one working day."
- Vendor small print (toggleable): "Booking system support is provided by Tempo, support@tempo-book-it.com. The SJP office can't fix technical problems, so this route is faster."

## 4. Tokens and type

Same system as the rest of the plugin: Montserrat body, Poppins headings, `--dsb-*` tokens falling back to theme tokens. Page bg `--color-bg-subtle`, cards `--color-bg-surface`, purple `--color-brand-secondary`, orange `--color-brand-primary`, tinted panel `--color-bg-brand-subtle`, success pair `--color-status-success-bg/fg`. H1 25px/32px, H2 19px/26px, body 13px/19px, small print 11px/16px.

## 5. Settings (tenant-editable)

Extend the existing plugin settings page — one "Help page" section:

| Setting | Default |
|---|---|
| Office email | sjptheatrearts@yahoo.co.uk |
| Support email | support@tempo-book-it.com |
| Show response-time note | on |
| Vendor attribution ("Small print" / "Hidden") | Small print |
| FAQ items (question + answer, repeatable, orderable) | the 5 SJP defaults |
| Quick fixes (title + body, repeatable, orderable) | the 4 defaults |

Vocabulary swaps from the existing tenant settings apply across all defaults (e.g. "your child" → "you" for an adult-student vocabulary).

## 6. QA checklist

- [ ] Logged-out user hitting the URL gets the same redirect the rest of the portal uses.
- [ ] Card click swaps the section, no scroll jump, active ring moves.
- [ ] Accordion: one open at a time, first open on load, chevron rotates, 44px rows.
- [ ] Form prefills name + email from the account; send delivers to the configured address with account email + referring page appended; sent panel shows the reply address.
- [ ] Both toggles (response note, vendor attribution) hide their lines.
- [ ] 375px: cards stacked, email address wraps (`word-break: break-all`), inputs stack, all targets ≥44px.
- [ ] Longer vocabulary (+40%) doesn't break card layout.
- [ ] Strings translatable; FAQ/fix content escaped on output.
