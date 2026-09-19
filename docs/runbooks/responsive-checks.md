# Responsive checks

How to prove the app still works on a tablet and a phone after a front-end
change. The harness lives in the session scratchpad, not the repo, because it
carries a throwaway login; recreate it from these notes when needed.

## What is checked

Four viewports, chosen for what a dealer actually holds:

| Viewport | Size | Why |
|---|---|---|
| iPad landscape | 1180 × 820 | The boat-show case: tablet on a stand, dealer and buyer both looking |
| iPad portrait | 820 × 1180 | Same tablet turned; the sidebar becomes off-canvas |
| iPhone | 390 × 844 | Read a quote, check a client, send a follow-up |
| Desktop | 1440 × 900 | Regression guard for the everyday experience |

Eleven tenant pages per viewport: dashboard, quotes list, quote builder, quote
detail, clients list, client form, catalogue, engines, team, company settings,
profile.

The pass/fail signal is `document.scrollWidth > clientWidth` — a page wider than
its viewport is what "the layout breaks" means in practice. Screenshots are
captured alongside, because the measurement alone misses plenty: it said zero
problems on the day the header search was wrapping to three lines across the
page title, and it blamed a table for an overflow that was really a filter row.
**Look at the pictures.**

## Two traps in the harness itself

- **`getBoundingClientRect` ignores ancestor clipping.** A wide table inside an
  `overflow-x-auto` card still reports a box past the viewport edge, so it turns
  up as the top "offender" while being entirely innocent. Trust `scrollWidth`
  for whether there is a problem; treat the offender list as a hint only.
- **Playwright's `isMobile` flag breaks bottom-fixed elements.** Under mobile
  emulation the layout viewport is taller than the captured area (916 vs 844),
  so anything pinned to the bottom lands outside the screenshot and cannot be
  clicked. That is the harness, not Safari. Capture the phone without the flag.

## Running it

```
npm i playwright
node shoot.mjs before      # baseline
# …deploy…
node shoot.mjs after       # compare
node interactive.mjs       # PDF preview modal + summary sheet open/closed
```

A tenant login is needed to reach the builder. Create a throwaway user in a test
dealership, and delete it when finished — it is a real login on production.

## Before changing any front-end code

Deploys build the CSS (`deploy.sh`), so any Tailwind class is available. That
was not true before 19 Sep 2026: the bundle was frozen at 19 June and classes
added after that silently did nothing. If a style mysteriously has no effect,
check the deployed bundle contains the class before rewriting the markup.
