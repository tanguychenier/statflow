# Statflow — Design Tokens

> Version 1.0 · Tailwind CSS 4 + CSS custom properties  
> Tokens are expressed as CSS variables and mapped to Tailwind 4 theme config via `@theme`.

---

## 1. Philosophy

Statflow's visual language is **data-dense but calm**: near-black canvases, precisely chosen accent hues, generous whitespace at the macro level, compact information density at the micro level. The aesthetic is closer to Linear/Vercel than to Google Analytics or Grafana. Two themes are supported from day one — `light` and `dark` — with `dark` as the default.

All tokens follow the naming convention:

```
--sf-{category}-{scale-or-role}
```

---

## 2. Color Palette

### 2.1 Primitive Palette (raw hue ramps, not for direct use in components)

These are the raw colour ramps from which semantic tokens are derived. They are defined as CSS variables on `:root` and are not exposed via Tailwind class names directly.

```css
:root {
  /* --- Neutral (Zinc-based, slightly warm) --- */
  --sf-neutral-0:   #ffffff;
  --sf-neutral-50:  #fafafa;
  --sf-neutral-100: #f4f4f5;
  --sf-neutral-200: #e4e4e7;
  --sf-neutral-300: #d1d1d6;
  --sf-neutral-400: #a0a0ab;
  --sf-neutral-500: #71717a;
  --sf-neutral-600: #52525b;
  --sf-neutral-700: #3f3f46;
  --sf-neutral-800: #27272a;
  --sf-neutral-850: #1c1c1f;
  --sf-neutral-900: #18181b;
  --sf-neutral-925: #121214;
  --sf-neutral-950: #09090b;

  /* --- Indigo (primary brand) --- */
  --sf-indigo-50:  #eef2ff;
  --sf-indigo-100: #e0e7ff;
  --sf-indigo-200: #c7d2fe;
  --sf-indigo-300: #a5b4fc;
  --sf-indigo-400: #818cf8;
  --sf-indigo-500: #6366f1;
  --sf-indigo-600: #4f46e5;
  --sf-indigo-700: #4338ca;
  --sf-indigo-800: #3730a3;
  --sf-indigo-900: #312e81;

  /* --- Violet (secondary accent) --- */
  --sf-violet-400: #a78bfa;
  --sf-violet-500: #8b5cf6;
  --sf-violet-600: #7c3aed;

  /* --- Emerald (positive / up-trend) --- */
  --sf-emerald-400: #34d399;
  --sf-emerald-500: #10b981;
  --sf-emerald-600: #059669;

  /* --- Rose (negative / down-trend / danger) --- */
  --sf-rose-400: #fb7185;
  --sf-rose-500: #f43f5e;
  --sf-rose-600: #e11d48;

  /* --- Amber (warning) --- */
  --sf-amber-400: #fbbf24;
  --sf-amber-500: #f59e0b;
  --sf-amber-600: #d97706;

  /* --- Sky (info / links) --- */
  --sf-sky-400: #38bdf8;
  --sf-sky-500: #0ea5e9;
  --sf-sky-600: #0284c7;

  /* --- Chart palette (ordered, accessible) --- */
  --sf-chart-1: #6366f1;  /* indigo   */
  --sf-chart-2: #8b5cf6;  /* violet   */
  --sf-chart-3: #0ea5e9;  /* sky      */
  --sf-chart-4: #10b981;  /* emerald  */
  --sf-chart-5: #f59e0b;  /* amber    */
  --sf-chart-6: #f43f5e;  /* rose     */
  --sf-chart-7: #a78bfa;  /* lavender */
  --sf-chart-8: #34d399;  /* mint     */
}
```

### 2.2 Semantic Tokens — Dark Theme (default)

```css
[data-theme="dark"],
:root {
  /* Backgrounds */
  --sf-bg-base:       var(--sf-neutral-950);   /* #09090b — page canvas        */
  --sf-bg-surface:    var(--sf-neutral-925);   /* #121214 — card / panel bg    */
  --sf-bg-overlay:    var(--sf-neutral-900);   /* #18181b — dropdown / popover */
  --sf-bg-subtle:     var(--sf-neutral-850);   /* #1c1c1f — hover / stripe     */
  --sf-bg-muted:      var(--sf-neutral-800);   /* #27272a — disabled / dimmed  */

  /* Borders */
  --sf-border:        var(--sf-neutral-800);   /* default border               */
  --sf-border-strong: var(--sf-neutral-700);   /* emphasis border              */
  --sf-border-focus:  var(--sf-indigo-500);    /* focus ring                   */

  /* Foreground / text */
  --sf-fg-primary:    var(--sf-neutral-50);    /* primary text                 */
  --sf-fg-secondary:  var(--sf-neutral-400);   /* secondary / label text       */
  --sf-fg-muted:      var(--sf-neutral-600);   /* placeholder / hint text      */
  --sf-fg-disabled:   var(--sf-neutral-700);   /* disabled text                */
  --sf-fg-inverse:    var(--sf-neutral-950);   /* text on bright surfaces      */

  /* Brand / Interactive */
  --sf-accent:        var(--sf-indigo-500);    /* primary CTA                  */
  --sf-accent-hover:  var(--sf-indigo-400);    /* CTA hover                    */
  --sf-accent-subtle: rgba(99,102,241,0.12);   /* tinted bg for active states  */
  --sf-accent-text:   var(--sf-indigo-300);    /* accent text on dark bg       */

  /* Status */
  --sf-positive:      var(--sf-emerald-500);
  --sf-positive-text: var(--sf-emerald-400);
  --sf-positive-bg:   rgba(16,185,129,0.10);
  --sf-negative:      var(--sf-rose-500);
  --sf-negative-text: var(--sf-rose-400);
  --sf-negative-bg:   rgba(244,63,94,0.10);
  --sf-warning:       var(--sf-amber-500);
  --sf-warning-text:  var(--sf-amber-400);
  --sf-warning-bg:    rgba(245,158,11,0.10);
  --sf-info:          var(--sf-sky-500);
  --sf-info-text:     var(--sf-sky-400);
  --sf-info-bg:       rgba(14,165,233,0.10);

  /* Elevation (box-shadow stacks) */
  --sf-shadow-sm:  0 1px 2px 0 rgba(0,0,0,0.40);
  --sf-shadow-md:  0 4px 12px 0 rgba(0,0,0,0.50), 0 1px 3px 0 rgba(0,0,0,0.40);
  --sf-shadow-lg:  0 8px 24px 0 rgba(0,0,0,0.60), 0 2px 6px  0 rgba(0,0,0,0.40);
  --sf-shadow-xl:  0 20px 40px 0 rgba(0,0,0,0.70), 0 4px 12px 0 rgba(0,0,0,0.50);

  /* Glow accents (used sparingly on active nav items, chart highlights) */
  --sf-glow-accent: 0 0 12px rgba(99,102,241,0.35);
}
```

### 2.3 Semantic Tokens — Light Theme

```css
[data-theme="light"] {
  /* Backgrounds */
  --sf-bg-base:       var(--sf-neutral-50);
  --sf-bg-surface:    var(--sf-neutral-0);
  --sf-bg-overlay:    var(--sf-neutral-0);
  --sf-bg-subtle:     var(--sf-neutral-100);
  --sf-bg-muted:      var(--sf-neutral-200);

  /* Borders */
  --sf-border:        var(--sf-neutral-200);
  --sf-border-strong: var(--sf-neutral-300);
  --sf-border-focus:  var(--sf-indigo-500);

  /* Foreground / text */
  --sf-fg-primary:    var(--sf-neutral-900);
  --sf-fg-secondary:  var(--sf-neutral-600);
  --sf-fg-muted:      var(--sf-neutral-400);
  --sf-fg-disabled:   var(--sf-neutral-300);
  --sf-fg-inverse:    var(--sf-neutral-50);

  /* Brand / Interactive */
  --sf-accent:        var(--sf-indigo-600);
  --sf-accent-hover:  var(--sf-indigo-700);
  --sf-accent-subtle: rgba(79,70,229,0.08);
  --sf-accent-text:   var(--sf-indigo-600);

  /* Status */
  --sf-positive:      var(--sf-emerald-600);
  --sf-positive-text: var(--sf-emerald-600);
  --sf-positive-bg:   rgba(5,150,105,0.08);
  --sf-negative:      var(--sf-rose-600);
  --sf-negative-text: var(--sf-rose-600);
  --sf-negative-bg:   rgba(225,29,72,0.08);
  --sf-warning:       var(--sf-amber-600);
  --sf-warning-text:  var(--sf-amber-600);
  --sf-warning-bg:    rgba(217,119,6,0.08);
  --sf-info:          var(--sf-sky-600);
  --sf-info-text:     var(--sf-sky-600);
  --sf-info-bg:       rgba(2,132,199,0.08);

  /* Elevation */
  --sf-shadow-sm:  0 1px 2px 0 rgba(0,0,0,0.06), 0 1px 3px 0 rgba(0,0,0,0.04);
  --sf-shadow-md:  0 4px 12px 0 rgba(0,0,0,0.08), 0 1px 3px 0 rgba(0,0,0,0.06);
  --sf-shadow-lg:  0 8px 24px 0 rgba(0,0,0,0.10), 0 2px 6px  0 rgba(0,0,0,0.06);
  --sf-shadow-xl:  0 20px 40px 0 rgba(0,0,0,0.12), 0 4px 12px 0 rgba(0,0,0,0.08);

  --sf-glow-accent: 0 0 12px rgba(79,70,229,0.20);
}
```

---

## 3. Typography Scale

**Base font stack:**

```
Inter, "Inter Variable", ui-sans-serif, system-ui, -apple-system, sans-serif
```

**Mono font stack:**

```
"JetBrains Mono", "JetBrains Mono Variable", "Fira Code", ui-monospace, monospace
```

Both fonts are loaded via `@fontsource` packages and subset to Latin + Latin-Extended.

### 3.1 CSS Variables

```css
:root {
  /* Font families */
  --sf-font-sans: Inter, "Inter Variable", ui-sans-serif, system-ui, -apple-system, sans-serif;
  --sf-font-mono: "JetBrains Mono", "JetBrains Mono Variable", "Fira Code", ui-monospace, monospace;

  /* Font sizes (rem, base 16px) */
  --sf-text-2xs:  0.625rem;   /*  10px */
  --sf-text-xs:   0.75rem;    /*  12px */
  --sf-text-sm:   0.875rem;   /*  14px */
  --sf-text-base: 1rem;       /*  16px */
  --sf-text-md:   1.0625rem;  /*  17px — slightly larger than base for readability */
  --sf-text-lg:   1.125rem;   /*  18px */
  --sf-text-xl:   1.25rem;    /*  20px */
  --sf-text-2xl:  1.5rem;     /*  24px */
  --sf-text-3xl:  1.875rem;   /*  30px */
  --sf-text-4xl:  2.25rem;    /*  36px */
  --sf-text-5xl:  3rem;       /*  48px */

  /* Line heights */
  --sf-leading-tight:   1.25;
  --sf-leading-snug:    1.375;
  --sf-leading-normal:  1.5;
  --sf-leading-relaxed: 1.625;

  /* Font weights */
  --sf-weight-regular:   400;
  --sf-weight-medium:    500;
  --sf-weight-semibold:  600;
  --sf-weight-bold:      700;

  /* Letter spacing */
  --sf-tracking-tight:  -0.025em;
  --sf-tracking-normal:  0em;
  --sf-tracking-wide:    0.025em;
  --sf-tracking-wider:   0.05em;
  --sf-tracking-widest:  0.1em;
}
```

### 3.2 Typographic Roles

| Role | Size token | Weight | Line-height | Tracking | Usage |
|---|---|---|---|---|---|
| `display-lg` | `--sf-text-4xl` | semibold | tight | tight | Hero numbers (KPI cards) |
| `display-md` | `--sf-text-3xl` | semibold | tight | tight | Section KPIs |
| `display-sm` | `--sf-text-2xl` | semibold | snug | tight | Large metric values |
| `heading-xl` | `--sf-text-xl` | semibold | snug | tight | Page titles |
| `heading-lg` | `--sf-text-lg` | semibold | snug | normal | Section headers |
| `heading-md` | `--sf-text-base` | semibold | normal | normal | Card titles |
| `heading-sm` | `--sf-text-sm` | semibold | normal | wide | Sub-section labels (caps optional) |
| `body-lg` | `--sf-text-md` | regular | relaxed | normal | Long-form copy |
| `body-base` | `--sf-text-base` | regular | normal | normal | Default UI text |
| `body-sm` | `--sf-text-sm` | regular | normal | normal | Secondary text, form labels |
| `caption` | `--sf-text-xs` | medium | normal | wide | Chart labels, table meta |
| `overline` | `--sf-text-2xs` | semibold | normal | widest | Badges, tag labels (uppercase) |
| `code` | `--sf-text-sm` | regular | normal | normal | Code snippets, tracking IDs |
| `code-sm` | `--sf-text-xs` | regular | normal | normal | Inline code |

---

## 4. Spacing Scale

Uses an 4px base unit. Tailwind 4 is configured with a custom scale rather than its default.

```css
:root {
  --sf-space-px:   1px;
  --sf-space-0-5:  0.125rem;  /*  2px */
  --sf-space-1:    0.25rem;   /*  4px */
  --sf-space-1-5:  0.375rem;  /*  6px */
  --sf-space-2:    0.5rem;    /*  8px */
  --sf-space-2-5:  0.625rem;  /* 10px */
  --sf-space-3:    0.75rem;   /* 12px */
  --sf-space-3-5:  0.875rem;  /* 14px */
  --sf-space-4:    1rem;      /* 16px */
  --sf-space-5:    1.25rem;   /* 20px */
  --sf-space-6:    1.5rem;    /* 24px */
  --sf-space-7:    1.75rem;   /* 28px */
  --sf-space-8:    2rem;      /* 32px */
  --sf-space-10:   2.5rem;    /* 40px */
  --sf-space-12:   3rem;      /* 48px */
  --sf-space-14:   3.5rem;    /* 56px */
  --sf-space-16:   4rem;      /* 64px */
  --sf-space-20:   5rem;      /* 80px */
  --sf-space-24:   6rem;      /* 96px */
  --sf-space-32:   8rem;      /* 128px */
}
```

**Layout constants:**

| Token | Value | Usage |
|---|---|---|
| `--sf-sidebar-width` | `220px` | Expanded sidebar |
| `--sf-sidebar-collapsed-width` | `56px` | Collapsed icon-only sidebar |
| `--sf-topbar-height` | `56px` | Top navigation bar |
| `--sf-content-max-width` | `1440px` | Maximum content container |
| `--sf-panel-width` | `320px` | Right-side detail panels |

---

## 5. Border Radii

```css
:root {
  --sf-radius-none:  0;
  --sf-radius-xs:    0.125rem;  /*  2px — inline badges */
  --sf-radius-sm:    0.25rem;   /*  4px — inputs, small chips */
  --sf-radius-md:    0.375rem;  /*  6px — buttons, dropdowns */
  --sf-radius-lg:    0.5rem;    /*  8px — cards, panels */
  --sf-radius-xl:    0.75rem;   /* 12px — modals, large cards */
  --sf-radius-2xl:   1rem;      /* 16px — feature cards */
  --sf-radius-full:  9999px;    /* pills, avatars, toggles */
}
```

---

## 6. Shadows & Elevation

Elevation uses a three-layer model: **surface → raised → floating**. Dark theme uses deep black shadows; light theme uses soft grey shadows.

| Level | Token | Usage |
|---|---|---|
| 0 — flat | no shadow | Table rows, sidebars |
| 1 — surface | `--sf-shadow-sm` | Cards, form fields |
| 2 — raised | `--sf-shadow-md` | Dropdown menus, tooltips |
| 3 — floating | `--sf-shadow-lg` | Modals, popovers, command palette |
| 4 — overlay | `--sf-shadow-xl` | Full-screen overlays |

In addition to box-shadows, cards on the dark theme use a subtle `border: 1px solid var(--sf-border)` to create definition without heavy shadows.

---

## 7. Motion & Easing

```css
:root {
  /* Durations */
  --sf-duration-instant:  50ms;
  --sf-duration-fast:    100ms;
  --sf-duration-base:    150ms;
  --sf-duration-slow:    250ms;
  --sf-duration-slower:  350ms;
  --sf-duration-slowest: 500ms;

  /* Easing curves */
  --sf-ease-default:      cubic-bezier(0.16, 1, 0.3, 1);  /* snappy spring — primary */
  --sf-ease-in:           cubic-bezier(0.4, 0, 1, 1);     /* exit animations         */
  --sf-ease-out:          cubic-bezier(0, 0, 0.2, 1);     /* enter animations        */
  --sf-ease-in-out:       cubic-bezier(0.4, 0, 0.2, 1);   /* contextual transitions  */
  --sf-ease-bounce:       cubic-bezier(0.34, 1.56, 0.64, 1); /* playful spring        */
  --sf-ease-linear:       linear;                          /* progress bars, loaders  */
}
```

### 7.1 Motion Roles

| Interaction | Duration | Easing | Notes |
|---|---|---|---|
| Button press | 100ms | `ease-default` | Scale 0.97 on active |
| Dropdown open | 150ms | `ease-out` | Fade + translate-y-1 |
| Dropdown close | 100ms | `ease-in` | Fade out only |
| Modal enter | 250ms | `ease-default` | Scale 0.96→1 + fade |
| Modal exit | 150ms | `ease-in` | Scale 1→0.96 + fade |
| Sidebar collapse | 250ms | `ease-in-out` | Width transition |
| Toast enter | 350ms | `ease-bounce` | Slide from edge |
| Toast exit | 200ms | `ease-in` | Fade + translate |
| Skeleton shimmer | 1500ms | `ease-linear` | Infinite loop |
| Chart mount | 500ms | `ease-out` | Stagger per series |
| Page transition | 200ms | `ease-out` | Fade only — no slide |

### 7.2 Reduced Motion

When `prefers-reduced-motion: reduce` is set, all durations collapse to `0ms` or `50ms` (for state-clarity only). Motion-decorative animations (shimmer, chart mount stagger) are eliminated. See `accessibility.md` for full details.

---

## 8. Tailwind 4 Theme Config Mapping

Tailwind 4 uses `@theme` in CSS (not a JS config file). The mapping below shows how tokens wire in.

```css
/* in tailwind.css / main.css */
@import "tailwindcss";

@theme {
  /* Colors — expose semantic tokens as Tailwind color utilities */
  --color-bg-base:       var(--sf-bg-base);
  --color-bg-surface:    var(--sf-bg-surface);
  --color-bg-overlay:    var(--sf-bg-overlay);
  --color-bg-subtle:     var(--sf-bg-subtle);
  --color-bg-muted:      var(--sf-bg-muted);
  --color-border:        var(--sf-border);
  --color-border-strong: var(--sf-border-strong);
  --color-border-focus:  var(--sf-border-focus);
  --color-fg-primary:    var(--sf-fg-primary);
  --color-fg-secondary:  var(--sf-fg-secondary);
  --color-fg-muted:      var(--sf-fg-muted);
  --color-fg-disabled:   var(--sf-fg-disabled);
  --color-fg-inverse:    var(--sf-fg-inverse);
  --color-accent:        var(--sf-accent);
  --color-accent-hover:  var(--sf-accent-hover);
  --color-accent-subtle: var(--sf-accent-subtle);
  --color-accent-text:   var(--sf-accent-text);
  --color-positive:      var(--sf-positive);
  --color-positive-text: var(--sf-positive-text);
  --color-positive-bg:   var(--sf-positive-bg);
  --color-negative:      var(--sf-negative);
  --color-negative-text: var(--sf-negative-text);
  --color-negative-bg:   var(--sf-negative-bg);
  --color-warning:       var(--sf-warning);
  --color-warning-text:  var(--sf-warning-text);
  --color-warning-bg:    var(--sf-warning-bg);
  --color-info:          var(--sf-info);
  --color-info-text:     var(--sf-info-text);
  --color-info-bg:       var(--sf-info-bg);
  --color-chart-1:       var(--sf-chart-1);
  --color-chart-2:       var(--sf-chart-2);
  --color-chart-3:       var(--sf-chart-3);
  --color-chart-4:       var(--sf-chart-4);
  --color-chart-5:       var(--sf-chart-5);
  --color-chart-6:       var(--sf-chart-6);
  --color-chart-7:       var(--sf-chart-7);
  --color-chart-8:       var(--sf-chart-8);

  /* Typography */
  --font-family-sans: var(--sf-font-sans);
  --font-family-mono: var(--sf-font-mono);

  /* Spacing — override Tailwind defaults for consistency */
  --spacing-px:   1px;
  --spacing-0-5:  var(--sf-space-0-5);
  --spacing-1:    var(--sf-space-1);
  --spacing-2:    var(--sf-space-2);
  --spacing-3:    var(--sf-space-3);
  --spacing-4:    var(--sf-space-4);
  --spacing-5:    var(--sf-space-5);
  --spacing-6:    var(--sf-space-6);
  --spacing-8:    var(--sf-space-8);
  --spacing-10:   var(--sf-space-10);
  --spacing-12:   var(--sf-space-12);
  --spacing-16:   var(--sf-space-16);
  --spacing-20:   var(--sf-space-20);
  --spacing-24:   var(--sf-space-24);
  --spacing-32:   var(--sf-space-32);

  /* Border radius */
  --radius-none:  var(--sf-radius-none);
  --radius-xs:    var(--sf-radius-xs);
  --radius-sm:    var(--sf-radius-sm);
  --radius-md:    var(--sf-radius-md);
  --radius-lg:    var(--sf-radius-lg);
  --radius-xl:    var(--sf-radius-xl);
  --radius-2xl:   var(--sf-radius-2xl);
  --radius-full:  var(--sf-radius-full);

  /* Shadows */
  --shadow-sm:  var(--sf-shadow-sm);
  --shadow-md:  var(--sf-shadow-md);
  --shadow-lg:  var(--sf-shadow-lg);
  --shadow-xl:  var(--sf-shadow-xl);
}
```

---

## 9. Token Governance

- All design tokens live in `apps/frontend/src/assets/tokens.css` and are imported once in `main.ts`.
- The `data-theme` attribute is toggled on `<html>` by the `useTheme()` composable (backed by Pinia + `localStorage`).
- Tokens are **never** hard-coded inside component `<style>` blocks. Components always reference semantic tokens, never primitive palette tokens.
- Primitive palette tokens are prefixed `--sf-{hue}-{step}` and must not appear in component stylesheets.
- Color contrast ratios for all foreground/background semantic pairs are documented in `accessibility.md`.
