---
name: Frontend Design
description: >-
  Build or change UI in the frontend/ React SPA (components, pages, styles,
  dialogs, forms, charts). Enforces the app's existing look and feel, plain-CSS
  design tokens, no new dependencies, accessibility, and mobile-first responsive
  behavior. Use for any task that touches frontend/ markup, styling, or layout.
---

# Frontend design (job-track)

Follow this whenever you create or modify UI in `frontend/`. The goal is a UI
that looks and behaves like the rest of the app: quiet, utilitarian, consistent.

## 1. Consistency with the app

- **Design tokens first.** Never hard-code colors, radii, or shadows. Use the
  CSS custom properties from `frontend/src/styles.css`:
  `--color-bg`, `--color-surface`, `--color-border`, `--color-text`,
  `--color-text-muted`, `--color-primary`, `--color-primary-hover`,
  `--color-danger`, `--color-danger-bg`, `--radius`, `--shadow-sm`, `--shadow-md`.
  If a value is genuinely missing, add a token to `:root` and use it.
- **Reuse before writing.** `frontend/src/styles.css` has established classes for
  cards, buttons, form fields, tables, badges, modals, and page layout. Search
  it for a matching class before inventing a new one. New classes go into
  `styles.css` and follow its naming (lowercase, hyphenated, e.g.
  `.report-stats`, `.modal-close`).
- **Reuse components.** `Modal` (dialog chrome + Escape handling),
  `ErrorBanner`, `RichTextEditor`, `WeeklyBarChart` pattern. Don't rebuild a
  dialog or error display inline in a page.
- **No component library, no new dependencies.** Plain CSS + existing deps
  (React, react-router, Tiptap, marked) cover everything. If you believe a
  package is truly required, stop and ask the user before installing.
- Charts are dependency-free CSS/SVG (see `WeeklyBarChart.tsx`); keep new
  visualizations in the same style, with pure helpers extracted to a `*.ts`
  module for unit testing.

## 2. Accessibility (non-negotiable)

- Semantic HTML: real `<button>` (never a clickable `<div>`), `<label>` for
  every input, headings in order, `<table>` for tabular data.
- Dialogs: `role="dialog"`, `aria-modal="true"`, an accessible name, an
  Escape-key close handler, and a visible close button with `aria-label`
  (follow `Modal.tsx`).
- Icon-only buttons need `aria-label`. Color is never the only carrier of
  meaning (add text or an icon).
- Keyboard: everything reachable and operable by keyboard alone; visible focus
  (keep default outlines or make an equivalent custom one); hit targets big
  enough on touch (≥ ~44px).
- Keep text contrast readable against `--color-bg`/`--color-surface`; use
  `--color-text-muted` only for secondary text, not body copy.
- Prefer simpler markup that a screen reader reads naturally over adding lots
  of ARIA.

## 3. Responsive & mobile (non-negotiable)

The app is used in a smartphone browser. Test the layout mentally at ~360px
width, not just desktop.

- **Avoid horizontal overflow.** It is the most common mobile bug:
  - In grids use `minmax(0, 1fr)` instead of `1fr` so long content can shrink.
  - Give flex children `min-width: 0` when they contain text that must wrap or
    truncate.
  - Long words/URLs/emails: `overflow-wrap: anywhere` or truncation with
    `text-overflow: ellipsis` (a `title` attribute for the full value).
  - No fixed widths wider than ~340px; use `max-width: 100%`, `min(x, 100%)`.
  - Check tables and code blocks for scroll containment (`overflow-x: auto`
    on a wrapper) rather than letting them blow out the page.
- **Use the existing breakpoints** rather than inventing new ones:
  `799px` (nav switches to the slide-in mobile menu), `700px` and `420px`
  (multi-column grids collapse). Extend these blocks for new page-level
  layouts that need to stack on small screens.
- Design mobile-first: single column by default, add columns at wider
  breakpoints. Touch targets must not rely on hover (`:hover` is a
  progressive enhancement only).
- Dialogs and page chrome must not require horizontal scrolling on a phone;
  modals should be effectively full-width minus small margins at 360px.

## 4. Validate the change

- Run `npm run check` in `frontend/` (Prettier, ESLint, Vitest + coverage,
  build). New UI logic belongs in co-located `*.test.tsx` files; extract pure
  helpers into `*.ts` modules so they are testable without rendering.
- Verify visually with the Vite dev server on port 5173 (proxies `/api` to
  the Symfony backend on 8000) and resize to phone width (or device toolbar)
  to confirm no horizontal overflow at 360px.
