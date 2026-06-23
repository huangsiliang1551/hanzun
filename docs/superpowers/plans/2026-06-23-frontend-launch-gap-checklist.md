# Frontend Launch Gap Checklist

> **Current assessment date:** 2026-06-23
> **Scope:** public site only, local development only, no server sync

## Current Verdict

The public site is **not ready for launch yet**. The shared shell is mostly in place, but there are still blocking issues in multilingual content resolution and static page generation.

## What Is Already Stable

- Header / footer shared shell validation passes
- Footer contact shell validation passes
- Footer social contact validation passes
- Public branding validation passes
- Local preview chain is available:
  - `http://127.0.0.1:8080`
  - `http://127.0.0.1:8091`

## Current Blocking Failures

### P0: Must Fix Before Launch

- Public site runtime validation fails:
  - `navigation(en) must return translated menu and item names`
  - `homepage(en) must return translated section copy and CTA text`
  - `pageDetail must support lookup by slug`
- Static build output validation fails:
  - missing `zh/pages/cake-line-landing.html`
  - missing `en/pages/cake-line-landing.html`
- Site localization validation fails for the same generated detail pages

### Root Cause Direction

- Public content resolution is still contaminated by runtime storage / translation test data
- `backend/runtime/storage/pages.json` currently contains stale page data such as `slug = live-page`
- Public page lookup and page generation are not yet consistently reading the intended published source of truth
- English public navigation / homepage copy is not consistently resolving the correct translated records

## Rectification Checklist

### 1. Public Data Source Consistency

- Fix `PublicSiteService` public-read path so public pages do not pick stale runtime test records
- Fix `PageRepository` runtime-vs-database precedence for public reads
- Check translation read path for:
  - navigation menu
  - navigation items
  - homepage sections
  - pages
- Remove or isolate polluted runtime/public test data from real public rendering inputs

### 2. Static Page Generation Completeness

- Ensure custom page detail records like `cake-line-landing` can be found by slug
- Ensure static publisher outputs:
  - `/zh/pages/*.html`
  - `/en/pages/*.html`
- Rebuild full site and verify landing/detail pages are emitted for both languages

### 3. Multilingual Public Output Lock

- Lock public page language output to current site language
- Ensure English navigation uses actual English labels, not fallback or polluted test translations
- Ensure homepage section title and CTA render correct translated copy
- Keep AI reply language locked to page language, while allowing knowledge source metadata to remain Chinese if needed

### 4. Listing / Detail Template Quality

- Re-check product / solution / news / case list pages for consistent layout
- Re-check detail templates for broken encoding, garbled text, and empty blocks
- Confirm news and case “view” links point to detail pages, not back to listing pages
- Normalize card styles, spacing, and category/dropdown behavior across all four content types

### 5. Shared Shell Final Acceptance

- Re-check header on:
  - home
  - list pages
  - detail pages
- Re-check footer for:
  - company name
  - subtitle
  - copyright
  - hot products
  - hot solutions
  - full contact fields
- Re-check floating contact bar:
  - AI
  - email
  - phone
  - WhatsApp
  - address

### 6. AI Chat Launch Readiness

- Verify real `/api/ai/chat` request path on zh and en pages
- Verify session restore across refresh and page switch
- Verify inquiry creation prompt appears without interrupting chat
- Verify source metadata is attached to assistant message, not rendered as a fake extra bubble
- Verify local fallback reply stays in visitor language when model key is unavailable

### 7. Content / SEO / Launch Hygiene

- Check every generated page has correct title, description, canonical and hreflang behavior
- Re-check sitemap page behavior and `sitemap.xml`
- Check logo, media, and uploads URLs across public and admin usage
- Run representative visual regression on:
  - homepage
  - about/contact
  - one product detail
  - one solution detail
  - one news detail
  - one case detail

## Suggested Delivery Order

1. Fix public source-of-truth and slug lookup
2. Regenerate full site and make build validators green
3. Fix multilingual public output
4. Fix list/detail template quality and encoding issues
5. Re-run AI chat cross-page verification
6. Finish SEO and visual acceptance

## Acceptance Commands

Run after any public source or generator change:

```powershell
php .tmp-render-full.php
php backend/tests/validate-site-build-output-runtime.php
```

Core regression commands:

```powershell
node backend/tests/validate-public-site-runtime.js
php backend/tests/validate-site-localization-runtime.php
php backend/tests/validate-public-branding-runtime.php
php backend/tests/validate-footer-contact-shell-runtime.php
php backend/tests/validate-footer-contact-social-runtime.php
```

## Launch Distance Estimate

If we keep scope fixed and only finish the current public-site backlog:

- **P0 usable internal preview:** close
- **Public beta quality:** medium distance
- **Production launch quality:** still needs one focused full cleanup cycle

In practical terms, the site is **past the foundation stage, but not yet at launch-ready stage**.
