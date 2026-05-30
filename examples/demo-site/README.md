# Statflow Demo Site — `examples/demo-site/`

A small, realistic, multi-page static website that serves two purposes:

1. **End-to-end test fixture** — a deterministic set of pages, elements, and interactions that the Statflow e2e harness instruments and asserts against.
2. **Integration example** — a realistic reference implementation showing how to instrument a real product site with the Statflow tracker.

The fictional product depicted is **Lumen Analytics** — a made-up company used solely for realistic placeholder content. It is not affiliated with any real product.

---

## Serving the site

No build step. Serve the directory with any static file server:

```bash
# Python
python3 -m http.server 8080 --directory examples/demo-site

# Node (npx)
npx serve examples/demo-site

# Caddy
caddy file-server --root examples/demo-site --listen :8080
```

---

## Page map

| File | Route | Purpose |
|------|-------|---------|
| `index.html` | `/` | Home — hero, features, stats strip, testimonials, CTA |
| `pricing.html` | `/pricing.html` | Pricing cards (3 tiers), billing toggle, FAQ accordion |
| `blog.html` | `/blog.html` | Post list with filter tabs, newsletter form, pagination |
| `blog-post.html` | `/blog-post.html` | Single article with breadcrumb, related posts, share/bookmark buttons |
| `contact.html` | `/contact.html` | Contact form (name, email, company, reason, message) |
| `404.html` | `/404.html` | Custom 404 page with navigation shortcuts |

All pages share:

- A sticky header with logo + main nav + action buttons
- A footer with four link columns
- The `<!-- STATFLOW_TRACKER -->` injection point in `<head>`
- `assets/css/styles.css` — single stylesheet, no external dependencies
- `assets/js/main.js` — shared vanilla JS (mobile nav, form, FAQ, pricing toggle, scroll reveal)

---

## Tracker injection convention

Every page `<head>` contains exactly this comment marker:

```html
<!-- STATFLOW_TRACKER -->
```

The e2e harness replaces this marker at test time with the real tracker `<script>` tag, for example:

```html
<script
  defer
  src="http://localhost:3000/tracker.js"
  data-site-id="demo-site-e2e"
></script>
```

Do **not** add a real tracker script to these files — the placeholder is intentional.

---

## Stable e2e selectors

All interactive elements carry both a stable `id` and a `data-testid` attribute. The `data-action` attribute identifies the semantic intent of a click for event-name assertions.

### Global (present on every page)

| Selector | Element | Notes |
|----------|---------|-------|
| `#site-header` / `[data-testid="site-header"]` — via `#site-header` | Sticky header wrapper | |
| `#logo-home` `[data-testid="site-logo"]` | Logo link → `index.html` | |
| `#nav-toggle` | Mobile hamburger button | `aria-expanded` toggled by JS |
| `#site-nav` | `<nav>` wrapper | Gets `.is-open` on mobile |
| `#nav-home` `[data-testid="nav-home"]` | Nav → Home | |
| `#nav-pricing` `[data-testid="nav-pricing"]` | Nav → Pricing | |
| `#nav-blog` `[data-testid="nav-blog"]` | Nav → Blog | |
| `#nav-contact` `[data-testid="nav-contact"]` | Nav → Contact | |
| `#header-btn-login` `[data-testid="header-btn-login"]` | "Sign in" ghost button | `data-action="login"` |
| `#header-btn-cta` `[data-testid="header-btn-cta"]` | "Get started free" primary button | `data-action="start-trial"` |
| `#site-footer` | Footer wrapper | |
| `#footer-logo` `[data-testid="footer-logo"]` | Footer logo → `index.html` | |
| `#footer-copyright` `[data-testid="footer-copyright"]` | Copyright line | |
| `#footer-link-pricing` `[data-testid="footer-link-pricing"]` | Footer → Pricing | |
| `#footer-link-blog` `[data-testid="footer-link-blog"]` | Footer → Blog | |
| `#footer-link-contact` `[data-testid="footer-link-contact"]` | Footer → Contact | |
| `#footer-legal-privacy` `[data-testid="footer-legal-privacy"]` | Footer → Privacy policy | |

### `index.html` — Home

| Selector | Element | Notes |
|----------|---------|-------|
| `#hero` `[data-testid="hero-section"]` | Hero section | |
| `#hero-title` | H1 | |
| `#hero-btn-primary` `[data-testid="hero-btn-primary"]` | "Start for free" CTA | `data-action="hero-cta-primary"` |
| `#hero-btn-secondary` `[data-testid="hero-btn-secondary"]` | "Read the docs" | `data-action="hero-cta-secondary"` |
| `#features` `[data-testid="features-section"]` | Features section | |
| `#feature-pageviews` `[data-testid="feature-card-pageviews"]` | Feature card | `data-reveal` scroll reveal |
| `#feature-heatmaps` `[data-testid="feature-card-heatmaps"]` | Feature card | |
| `#feature-journeys` `[data-testid="feature-card-journeys"]` | Feature card | |
| `#feature-funnels` `[data-testid="feature-card-funnels"]` | Feature card | |
| `#feature-events` `[data-testid="feature-card-events"]` | Feature card | |
| `#feature-api` `[data-testid="feature-card-api"]` | Feature card | |
| `#stats-strip` `[data-testid="stats-strip"]` | Metric strip | |
| `#stat-events` | "10B+ events" stat | |
| `#stat-latency` | "<1ms overhead" stat | |
| `#stat-uptime` | "99.99% SLA" stat | |
| `#stat-stars` | "12k★ GitHub" stat | |
| `#testimonials` `[data-testid="testimonials-section"]` | Testimonials section | |
| `#testimonial-1` `[data-testid="testimonial-1"]` | First testimonial card | |
| `#testimonial-2` `[data-testid="testimonial-2"]` | Second testimonial card | |
| `#testimonial-3` `[data-testid="testimonial-3"]` | Third testimonial card | |
| `#cta-home` `[data-testid="cta-banner-home"]` | Bottom CTA banner | |
| `#cta-home-btn-primary` `[data-testid="cta-home-btn-primary"]` | "View pricing" | `data-action="cta-home-primary"` |
| `#cta-home-btn-secondary` `[data-testid="cta-home-btn-secondary"]` | "Talk to us" | `data-action="cta-home-secondary"` |

### `pricing.html` — Pricing

| Selector | Element | Notes |
|----------|---------|-------|
| `#billing-toggle` `[data-testid="billing-toggle"]` | Monthly/annual checkbox | Updating prices via `data-price-monthly` / `data-price-annual` attributes |
| `#label-monthly` | "Monthly" label | opacity reduced when annual active |
| `#label-annual` | "Annual" label | |
| `#pricing-grid` `[data-testid="pricing-section"]` | Cards container | |
| `#pricing-card-starter` `[data-testid="pricing-card-starter"]` | Starter plan card | `data-plan="starter"` |
| `#pricing-card-growth` `[data-testid="pricing-card-growth"]` | Growth plan card (featured) | `data-plan="growth"` |
| `#pricing-card-enterprise` `[data-testid="pricing-card-enterprise"]` | Enterprise plan card | `data-plan="enterprise"` |
| `#pricing-btn-starter` `[data-testid="pricing-btn-starter"]` | Starter CTA | `data-action="select-plan"` `data-plan="starter"` |
| `#pricing-btn-growth` `[data-testid="pricing-btn-growth"]` | Growth CTA | `data-action="select-plan"` `data-plan="growth"` |
| `#pricing-btn-enterprise` `[data-testid="pricing-btn-enterprise"]` | Enterprise CTA | `data-action="select-plan"` `data-plan="enterprise"` |
| `#faq-1` … `#faq-5` `[data-testid="faq-item-N"]` | FAQ `<details>` elements | Toggle open/closed |
| `[data-testid="faq-trigger-N"]` | FAQ `<summary>` trigger | |
| `#cta-pricing-btn-primary` | "Get started for free" | `data-action="cta-pricing-primary"` |
| `#cta-pricing-btn-secondary` | "Talk to sales" | `data-action="cta-pricing-secondary"` |

### `blog.html` — Blog list

| Selector | Element | Notes |
|----------|---------|-------|
| `#blog-filters` `[data-testid="blog-filters"]` | Filter button group | |
| `#filter-all` `[data-testid="filter-all"]` | "All" filter | `data-filter="all"` |
| `#filter-analytics` `[data-testid="filter-analytics"]` | Analytics filter | `data-filter="analytics"` |
| `#filter-privacy` `[data-testid="filter-privacy"]` | Privacy filter | `data-filter="privacy"` |
| `#filter-product` `[data-testid="filter-product"]` | Product filter | `data-filter="product"` |
| `#filter-guide` `[data-testid="filter-guide"]` | Guide filter | `data-filter="guide"` |
| `#blog-post-featured` `[data-testid="blog-post-featured"]` | Featured post card | `data-category="analytics"` |
| `#blog-link-featured` `[data-testid="blog-link-featured"]` | Featured post link | `data-post-id="how-cookieless-analytics-works"` |
| `#blog-btn-featured` `[data-testid="blog-btn-featured"]` | "Read article" button | `data-action="read-post"` |
| `#blog-post-1` … `#blog-post-6` `[data-testid="blog-post-N"]` | Post cards | `data-category` attribute |
| `#blog-link-1` … `#blog-link-6` `[data-testid="blog-link-N"]` | Post title links | `data-post-id` attribute |
| `#blog-pagination` `[data-testid="blog-pagination"]` | Pagination nav | |
| `#pagination-prev` `[data-testid="pagination-prev"]` | Previous page button | `disabled` on page 1 |
| `#pagination-next` `[data-testid="pagination-next"]` | Next page button | |
| `#newsletter-form` `[data-testid="newsletter-form"]` | Newsletter form | `preventDefault` — never sends |
| `#newsletter-email` `[data-testid="newsletter-email"]` | Email input | |
| `#newsletter-submit` `[data-testid="newsletter-submit"]` | Subscribe button | `data-action="newsletter-subscribe"` |

### `blog-post.html` — Article

| Selector | Element | Notes |
|----------|---------|-------|
| `#article-hero` `[data-testid="article-hero"]` | Article header | |
| `#article-title` | H1 | |
| `#article-body` `[data-testid="article-body"]` | Article content | `data-post-id="how-cookieless-analytics-works"` |
| `#breadcrumb-home` `[data-testid="breadcrumb-home"]` | Breadcrumb → Home | |
| `#breadcrumb-blog` `[data-testid="breadcrumb-blog"]` | Breadcrumb → Blog | |
| `#article-btn-share` `[data-testid="article-btn-share"]` | Share button | `data-action="share-article"` |
| `#article-btn-bookmark` `[data-testid="article-btn-bookmark"]` | Bookmark button | `data-action="bookmark-article"` |
| `#article-tag-analytics` `[data-testid="article-tag-analytics"]` | Tag link | `data-tag="analytics"` |
| `#article-tag-privacy` `[data-testid="article-tag-privacy"]` | Tag link | `data-tag="privacy"` |
| `#article-tag-clickhouse` `[data-testid="article-tag-clickhouse"]` | Tag link | `data-tag="clickhouse"` |
| `#related-post-1` … `#related-post-3` `[data-testid="related-post-N"]` | Related post cards | |
| `#cta-post-btn-primary` `[data-testid="cta-post-btn-primary"]` | "Start for free" CTA | `data-action="cta-post-primary"` |

### `contact.html` — Contact form

| Selector | Element | Notes |
|----------|---------|-------|
| `#contact-form` `[data-testid="contact-form"]` | The form element | `preventDefault` — never sends |
| `#contact-name` `[data-testid="contact-field-name"]` | Name input | `required` |
| `#contact-email` `[data-testid="contact-field-email"]` | Email input | `required` |
| `#contact-company` `[data-testid="contact-field-company"]` | Company input | optional |
| `#contact-reason` `[data-testid="contact-field-reason"]` | Reason `<select>` | `required`; options: `general`, `sales`, `bug`, `feature`, `partnership` |
| `#contact-message` `[data-testid="contact-field-message"]` | Message `<textarea>` | `required` |
| `#contact-btn-submit` `[data-testid="contact-btn-submit"]` | Submit button | `data-action="submit-contact"` |
| `#form-feedback` `[data-testid="form-feedback"]` | Success banner | Hidden until valid submit; gets `.is-visible` class |
| `#contact-shortcut-pricing` `[data-testid="contact-shortcut-pricing"]` | Quick-link → Pricing | |
| `#contact-shortcut-docs` `[data-testid="contact-shortcut-docs"]` | Quick-link → Docs | |
| `#contact-item-email` `[data-testid="contact-item-email"]` | Email contact info item | |

### `404.html` — Error page

| Selector | Element | Notes |
|----------|---------|-------|
| `#error-page-404` `[data-testid="error-page-404"]` | Error page wrapper | |
| `#error-title` | H1 "Page not found" | |
| `#error-btn-home` `[data-testid="error-btn-home"]` | → Home | `data-action="error-go-home"` |
| `#error-btn-blog` `[data-testid="error-btn-blog"]` | → Blog | `data-action="error-go-blog"` |
| `#error-btn-contact` `[data-testid="error-btn-contact"]` | → Contact | `data-action="error-go-contact"` |
| `#error-link-pricing` `[data-testid="error-link-pricing"]` | Suggestion → Pricing | |
| `#error-link-blog` `[data-testid="error-link-blog"]` | Suggestion → Blog | |
| `#error-link-docs` `[data-testid="error-link-docs"]` | Suggestion → Docs | |
| `#error-link-contact` `[data-testid="error-link-contact"]` | Suggestion → Contact | |

---

## Common `data-action` values (for custom-event assertions)

| `data-action` value | Trigger | Page |
|---------------------|---------|------|
| `hero-cta-primary` | Hero "Start for free" | index |
| `hero-cta-secondary` | Hero "Read the docs" | index |
| `cta-home-primary` | Bottom CTA "View pricing" | index |
| `cta-home-secondary` | Bottom CTA "Talk to us" | index |
| `start-trial` | Header "Get started free" | all |
| `login` | Header "Sign in" | all |
| `select-plan` | Pricing plan CTA | pricing |
| `cta-pricing-primary` | Pricing bottom CTA | pricing |
| `cta-pricing-secondary` | Pricing "Talk to sales" | pricing |
| `read-post` | Blog "Read article" button | blog |
| `newsletter-subscribe` | Newsletter submit | blog |
| `share-article` | Article share | blog-post |
| `bookmark-article` | Article bookmark | blog-post |
| `cta-post-primary` | Post "Start for free" | blog-post |
| `submit-contact` | Contact form submit | contact |
| `error-go-home` | 404 → Home | 404 |
| `error-go-blog` | 404 → Blog | 404 |

---

## File tree

```
examples/demo-site/
├── index.html          ← Home page
├── pricing.html        ← Pricing page
├── blog.html           ← Blog post list
├── blog-post.html      ← Single blog article
├── contact.html        ← Contact form
├── 404.html            ← Custom 404
├── assets/
│   ├── css/
│   │   └── styles.css  ← Single shared stylesheet
│   └── js/
│       └── main.js     ← Shared vanilla JS
└── README.md           ← This file
```
