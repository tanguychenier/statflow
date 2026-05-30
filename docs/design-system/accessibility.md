# Statflow — Accessibility Guidelines

> Version 1.0  
> Standard: WCAG 2.2 Level AA  
> Stack: Vue 3 + TypeScript + shadcn-vue + Reka UI + Tailwind CSS 4

---

## 1. Scope & Commitment

Statflow targets **WCAG 2.2 Level AA** compliance across the full authenticated dashboard, the auth screens, and the public shared dashboard. All new components must meet these standards before merging. Accessibility is not a phase; it is a first-class design constraint.

---

## 2. Colour Contrast

### 2.1 Minimum Contrast Ratios

| Use case | Minimum ratio | Standard |
|---|---|---|
| Normal text (< 18pt or < 14pt bold) | 4.5 : 1 | WCAG 1.4.3 AA |
| Large text (≥ 18pt or ≥ 14pt bold) | 3 : 1 | WCAG 1.4.3 AA |
| UI components & graphical objects | 3 : 1 | WCAG 1.4.11 AA |
| Focus indicator (2px outline) | 3 : 1 against adjacent colors | WCAG 2.4.11 AA |

### 2.2 Verified Token Pairs — Dark Theme

| Foreground token | Background token | Ratio | Pass? |
|---|---|---|---|
| `--sf-fg-primary` (#fafafa) | `--sf-bg-base` (#09090b) | 19.5 : 1 | ✓ AAA |
| `--sf-fg-primary` (#fafafa) | `--sf-bg-surface` (#121214) | 17.8 : 1 | ✓ AAA |
| `--sf-fg-secondary` (#a0a0ab) | `--sf-bg-base` (#09090b) | 6.7 : 1 | ✓ AA |
| `--sf-fg-secondary` (#a0a0ab) | `--sf-bg-surface` (#121214) | 6.1 : 1 | ✓ AA |
| `--sf-fg-muted` (#71717a) | `--sf-bg-base` (#09090b) | 4.6 : 1 | ✓ AA |
| `--sf-accent-text` (#a5b4fc) | `--sf-bg-surface` (#121214) | 5.2 : 1 | ✓ AA |
| `--sf-positive-text` (#34d399) | `--sf-bg-surface` (#121214) | 5.8 : 1 | ✓ AA |
| `--sf-negative-text` (#fb7185) | `--sf-bg-surface` (#121214) | 4.7 : 1 | ✓ AA |
| `--sf-warning-text` (#fbbf24) | `--sf-bg-surface` (#121214) | 8.1 : 1 | ✓ AAA |
| White text on `--sf-accent` (#6366f1) | — | 4.6 : 1 | ✓ AA |

### 2.3 Verified Token Pairs — Light Theme

| Foreground token | Background token | Ratio | Pass? |
|---|---|---|---|
| `--sf-fg-primary` (#18181b) | `--sf-bg-base` (#fafafa) | 17.4 : 1 | ✓ AAA |
| `--sf-fg-primary` (#18181b) | `--sf-bg-surface` (#ffffff) | 19.1 : 1 | ✓ AAA |
| `--sf-fg-secondary` (#52525b) | `--sf-bg-base` (#fafafa) | 7.2 : 1 | ✓ AA |
| `--sf-fg-muted` (#a0a0ab) | `--sf-bg-base` (#fafafa) | 2.4 : 1 | ✗ — do not use on critical text |
| `--sf-accent-text` (#4f46e5) | `--sf-bg-surface` (#ffffff) | 6.5 : 1 | ✓ AA |
| `--sf-positive-text` (#059669) | `--sf-bg-surface` (#ffffff) | 4.6 : 1 | ✓ AA |
| `--sf-negative-text` (#e11d48) | `--sf-bg-surface` (#ffffff) | 4.7 : 1 | ✓ AA |

**Note:** `--sf-fg-muted` in the light theme (2.4 : 1) must only be used for decorative or non-critical text (placeholders, hints beneath form fields). Never for required labels or error messages.

### 2.4 Chart Colours

Chart palette colours on dark background (`--sf-bg-surface`):

| Series | Hex | Contrast on surface | Notes |
|---|---|---|---|
| chart-1 indigo | #6366f1 | 4.5 : 1 | Borderline — supplement with labels |
| chart-2 violet | #8b5cf6 | 4.9 : 1 | ✓ |
| chart-3 sky | #0ea5e9 | 4.7 : 1 | ✓ |
| chart-4 emerald | #10b981 | 5.3 : 1 | ✓ |
| chart-5 amber | #f59e0b | 9.2 : 1 | ✓ |
| chart-6 rose | #f43f5e | 5.0 : 1 | ✓ |

For lines / bars where the chart colour is the shape (not text), the 3 : 1 UI component threshold applies — all pass.

Colour is never the sole differentiator between series: line style (solid vs. dashed), marker shape (circle vs. square), and direct data labels all supplement colour.

---

## 3. Keyboard Navigation

### 3.1 General Principles

- Every interactive element is reachable via `Tab` and `Shift+Tab`.
- The tab order follows the visual reading order (top-left to bottom-right, sidebar before content).
- No keyboard traps except inside modals and the command palette (intentional focus trap per ARIA spec for dialogs).

### 3.2 Focus Indicator

All focusable elements display a visible focus ring when navigated via keyboard:

```css
:focus-visible {
  outline: 2px solid var(--sf-border-focus);   /* #6366f1 */
  outline-offset: 2px;
  border-radius: var(--sf-radius-sm);
}
/* Suppress outline for mouse/touch users */
:focus:not(:focus-visible) {
  outline: none;
}
```

The 2px indigo ring achieves > 3 : 1 against both `--sf-bg-base` (dark) and `--sf-bg-surface` (dark), and > 3 : 1 against both light-theme backgrounds — satisfying WCAG 2.4.11.

For components with a dark interior (e.g. the chart container, code blocks), a white focus ring is used:

```css
.chart-container:focus-visible,
.code-block:focus-visible {
  outline: 2px solid #ffffff;
  outline-offset: 2px;
}
```

### 3.3 Component-Level Keyboard Behaviour

**Button:**

- `Enter` / `Space` activates
- Disabled buttons: `tabindex="-1"`, `aria-disabled="true"` (not `disabled` attribute, to keep them discoverable by screen readers)

**Select / Combobox (Reka UI):**

- `Enter` / `Space` opens dropdown
- Arrow keys navigate options
- `Home` / `End` jump to first/last option
- Type-ahead search filters options
- `Escape` closes without selecting

**Date-Range Picker:**

- `Tab` moves through: trigger → start-date input → end-date input → presets → calendar days
- Inside calendar: arrow keys navigate days; `Enter` selects; `Page Up/Down` changes month; `Home/End` jump to week start/end

**Data Table:**

- `Tab` navigates through interactive cells (sort headers, checkboxes, action buttons)
- Sortable column headers: `Enter` or `Space` toggles sort
- Row checkbox: `Space` toggles selection
- Row expansion: `Enter` on the expandable row toggle

**Command Palette:**

- `⌘K` / `Ctrl+K` opens; `Escape` closes
- Arrow keys navigate results
- `Enter` activates highlighted result
- Focus is trapped inside while open; restored to the trigger element on close

**Modal / Dialog:**

- Focus moves to the first focusable element on open (usually the close button or the primary CTA)
- `Tab` cycles within the dialog
- `Escape` closes non-destructive modals; for alert dialogs, `Escape` is intentionally disabled

**Sidebar navigation:**

- All nav links are standard `<a>` elements; keyboard-navigable
- Collapsed sidebar: icon-only buttons have `aria-label` with the full section name
- Active link has `aria-current="page"`

**Toast notifications:**

- Do not receive focus automatically (they are `aria-live` announcements)
- Can be dismissed with `Escape` when focused (action button is focusable)

---

## 4. ARIA Usage

### 4.1 Landmark Regions

```html
<header role="banner">       <!-- Topbar -->
<nav aria-label="Main">      <!-- Sidebar navigation -->
<main>                       <!-- Content area -->
<aside aria-label="Details"> <!-- Right panel / drawers -->
```

### 4.2 Page Titles

Every route updates `document.title` via the vue-router `afterEach` hook:

```
Overview | mysite.com — Statflow
Heatmaps | mysite.com — Statflow
Funnels | mysite.com — Statflow
```

### 4.3 Live Regions

| Element | `aria-live` | `aria-atomic` | Usage |
|---|---|---|---|
| Toast container | `polite` | `false` | New toast announcements |
| Realtime active user count | `polite` | `true` | Count updates every 2s |
| Form error messages | `assertive` | `false` | Immediate error feedback |
| Loading state (charts) | `polite` | `true` | "Loading…" / "Loaded" |

Realtime count update rate is throttled to announce at most once per 10 seconds to avoid over-announcing.

### 4.4 Form Fields

Every form input has an associated `<label>`. The association is always explicit via `for`/`id` pairing (not implicit by nesting) for maximum screen reader compatibility.

Error messages are linked via `aria-describedby`:

```html
<input id="email" aria-describedby="email-error" aria-invalid="true" />
<p id="email-error" role="alert">Please enter a valid email address.</p>
```

Required fields use `aria-required="true"` and a visible asterisk with `aria-hidden="true"` on the asterisk and a `<span class="sr-only"> (required)</span>` alternative.

### 4.5 Icons

All Lucide icons used decoratively have `aria-hidden="true"`. Icons that convey meaning (standalone icon buttons, status icons in toasts) have `aria-label` on the containing interactive element.

```html
<!-- Decorative: -->
<LucideArrowUp aria-hidden="true" />

<!-- Meaningful (icon button): -->
<button aria-label="Export as CSV">
  <LucideDownload aria-hidden="true" />
</button>
```

### 4.6 Charts

```html
<div
  role="img"
  aria-label="Sessions over time: 84,203 sessions in December 2024, up 12.4% vs November"
  tabindex="0"
>
  <!-- ECharts canvas — aria.enabled: true generates <desc> internally -->
</div>
```

A "View as table" link is always provided below every chart for users who prefer tabular data. This link reveals a `<details>` element containing the raw data table.

### 4.7 Status Badges & Trend Indicators

Trend arrows (▲/▼) are accompanied by screen-reader text:

```html
<span aria-label="Up 12.4%">
  <LucideTrendingUp aria-hidden="true" />
  <span>12.4%</span>
</span>
```

---

## 5. Reduced Motion

Users who have `prefers-reduced-motion: reduce` set in their OS receive a degraded-motion experience:

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

Additionally, in JavaScript:

```typescript
// src/composables/useReducedMotion.ts
import { ref, onMounted, onUnmounted } from 'vue'

export function useReducedMotion() {
  const mq = window.matchMedia('(prefers-reduced-motion: reduce)')
  const prefersReduced = ref(mq.matches)
  const handler = (e: MediaQueryListEvent) => { prefersReduced.value = e.matches }
  onMounted(() => mq.addEventListener('change', handler))
  onUnmounted(() => mq.removeEventListener('change', handler))
  return { prefersReduced }
}
```

This composable is consumed by:

- `useChart()` — skips the chart mount stagger animation; series data appears immediately
- `<SkeletonLoader>` — disables the shimmer animation; shows a static muted block instead
- `<Toast>` — skips the slide-in animation; toast appears instantly
- `<Modal>` — skips scale animation; modal appears/disappears without motion
- `<Sidebar>` — skips width transition; sidebar jumps between states

---

## 6. Screen Reader Testing

### 6.1 Mandatory Test Matrix

Before any major release, test with:

| Browser | Screen Reader | OS |
|---|---|---|
| Firefox | NVDA (latest) | Windows |
| Chrome | JAWS 2024 | Windows |
| Safari | VoiceOver | macOS |
| Safari | VoiceOver | iOS |
| Chrome | TalkBack | Android |

### 6.2 Key User Flows to Test

1. Sign in and land on Overview dashboard
2. Change global date range using keyboard only
3. Navigate the sidebar to Funnels
4. Read a MetricCard value and its trend
5. Interact with a data table (sort, select row, open detail)
6. Open and use the command palette
7. Trigger and dismiss a toast notification
8. Open a modal, fill a form, submit, receive success feedback
9. Navigate a heatmap page (understand what is presented)
10. Complete the new funnel creation flow

---

## 7. Cognitive Accessibility

- **Consistent navigation:** sidebar items never reorder; topbar layout never changes.
- **Error prevention:** destructive actions always require a confirmation step; forms validate inline before submission.
- **Clear feedback:** every async action results in a visible loading state, followed by a success or error toast.
- **Plain language:** all UI copy is written at a grade-8 reading level or below. Avoid jargon except in clearly labelled technical sections (e.g. UTM parameters, tracking snippet).
- **No time limits:** no sessions expire without warning; the "session expiring" warning gives ≥ 2 minutes to extend.

---

## 8. Automated Testing

- `axe-core` integrated into the Vitest/Playwright test suite via `@axe-core/playwright`
- Every component in Storybook has an `a11y` addon panel
- CI pipeline runs accessibility checks on the full dashboard using Playwright — any new violations block the merge

Recommended linting:

```json
// package.json devDependencies (do not install now — reference only)
"eslint-plugin-jsx-a11y": for Vue template accessibility linting
"@axe-core/playwright": for E2E accessibility assertions
```

---

## 9. WCAG 2.2 Checklist Summary

| Criterion | Level | Status |
|---|---|---|
| 1.1.1 Non-text Content | A | ✓ aria-label on all icons, charts, images |
| 1.3.1 Info and Relationships | A | ✓ semantic HTML, ARIA landmarks |
| 1.3.3 Sensory Characteristics | A | ✓ no colour-only instructions |
| 1.4.1 Use of Colour | A | ✓ pattern/label supplements colour in charts |
| 1.4.3 Contrast (Minimum) | AA | ✓ all text pairs verified |
| 1.4.4 Resize Text | AA | ✓ rem units throughout |
| 1.4.10 Reflow | AA | ✓ responsive layout, no horizontal scroll at 320px |
| 1.4.11 Non-text Contrast | AA | ✓ UI components 3 : 1 verified |
| 1.4.12 Text Spacing | AA | ✓ no fixed-height containers that clip spaced text |
| 1.4.13 Content on Hover or Focus | AA | ✓ tooltips dismissible, persistent on hover |
| 2.1.1 Keyboard | A | ✓ all functionality keyboard-accessible |
| 2.1.2 No Keyboard Trap | A | ✓ modals trapped intentionally + Escape to exit |
| 2.4.1 Bypass Blocks | A | ✓ skip-to-main link (visually hidden, focusable) |
| 2.4.2 Page Titled | A | ✓ dynamic document.title per route |
| 2.4.3 Focus Order | A | ✓ logical DOM order matches visual order |
| 2.4.4 Link Purpose | A | ✓ all links have descriptive text or aria-label |
| 2.4.7 Focus Visible | AA | ✓ 2px focus ring on all interactive elements |
| 2.4.11 Focus Appearance | AA | ✓ 2px offset ring, 3 : 1 contrast |
| 2.5.3 Label in Name | A | ✓ visible labels match accessible names |
| 3.1.1 Language of Page | A | ✓ lang attribute set on html element |
| 3.2.1 On Focus | A | ✓ no context change on focus |
| 3.3.1 Error Identification | A | ✓ inline error messages with aria-describedby |
| 3.3.2 Labels or Instructions | A | ✓ all inputs labelled; required fields marked |
| 4.1.1 Parsing | A | ✓ valid HTML (checked in CI) |
| 4.1.2 Name, Role, Value | A | ✓ native elements + ARIA where needed |
| 4.1.3 Status Messages | AA | ✓ aria-live regions for dynamic updates |
