# unirad Digital Platform — Migration Master Plan
Date: 2026-10-19 | Status: Sprint 0 / discovery | Owner: unirad project

## Decision record
- The live unirad.co.uk WordPress website remains production until explicit cutover approval.
- Build a separate private `unirad-web` Next.js/Vercel project; do not reuse `unirad-prime`.
- Connective Healthcare OS is an independent project, **not** a dependency, workstream, or deployment target of this project.
- Other projects may be consulted for patterns only; no automatic transfer of data, code, secrets, pricing, or clinical policies.
- Keep web delivery and clinical operations on separate timelines. No production DNS, plugin, patient, booking, payment, or data changes under this Sprint 0 approval.

## Two independent tracks
### A — Web (target: six weeks, contingent on audit)
W1: Audit WordPress plugin inventory, Bookly/WooCommerce and hosting, SEO URL inventory, GSC/GA4 12-month baseline, test-domain feasibility, acceptance thresholds.
W2: Create isolated Next.js/Vercel staging, design system and initial booking subdomain proof-of-concept using synthetic data/test mode.
W3: Home, scan service, location and pricing presentation; validate claims against approved current sources.
W4: Content/blog/FAQ migration, URL parity, canonical/schema/sitemap, local SEO and GEO.
W5: Booking bridge, consented callback, analytics, accessibility, mobile and end-to-end tests.
W6: Cutover rehearsal, redirects, SEO regression, rollback drill, GO/HOLD review. Publish only with separate explicit approval.

### B — Operations (independent schedule and gates)
Discover current RIS/PACS, reporting partner, booking, payment provider, telephone, email, patient workflows. Design group > legal entity > branch > resource/device > service and branch pricing. Legal-entity payment accounts; branch-level appointments. Separate secure clinical application and authentication, UK GDPR DPIA and Scotland/HIS specialist review, role-based access, audit logging, report *status* integration rather than replacement of radiology reporting. No patient data in marketing app. AI only for bounded, human-reviewed workflows until separately approved.

## Booking continuity — critical path
Candidate: preserve WordPress/Bookly/WooCommerce at `book.unirad.co.uk` after public website moves to Next.js. NOT APPROVED until tested in an isolated WordPress copy in W1–W2:
- Hostnames, WP site URLs, plugin licences, cookies/session, checkout redirects, email deliverability, webhook/callback endpoints, TLS and cross-origin flows.
- Synthetic appointment; payment provider test mode; confirmation, cancellation and refund-path simulation; reconciliation and callback idempotency.
- No real transactions or production changes. Alternative if blocked: retain WordPress on original site and hold cutover; do not rush replacement engine.

## SEO baseline and parity
Inventory all indexed and discovered URLs, metadata, canonical, structured data, hreflang if applicable, robots, XML sitemaps, images, internal links, backlink landing pages, GSC performance and GA4 conversions. Preserve URLs wherever possible; one-to-one 301 only when necessary. Staging noindex and access-controlled. No unsupported local branch pages or unapproved turnaround/pricing claims.

## GO / HOLD gates (thresholds to be signed off in W1)
- 100% of inventoried critical booking and payment paths pass in test mode.
- 100% of legacy URLs have verified retain/redirect disposition; no redirect loops or accidental noindex on production.
- Payment callbacks, emails, refunds/cancellations, analytics events and consent pass scripted tests.
- Accessibility WCAG AA target, mobile Core Web Vitals thresholds and conversion baseline agreed from real measurement.
- Rollback rehearsal succeeds, including DNS, booking continuity, WP hosting, data integrity and monitoring.
- DPIA, privacy notices, data processors and relevant HIS obligations reviewed by named clinic-side owner before handling patient data.
- No launch while any critical gate is HOLD.

## Cutover and rollback
Lower DNS TTL only in approved maintenance plan; freeze relevant WordPress content during cutover; keep recoverable WordPress backup and accessible legacy booking for weeks; monitor GSC, analytics, errors, payments and bookings; rollback on pre-agreed triggers. Changing DNS requires fresh explicit authorisation.

## Immediate Sprint 0 outputs
1. Current-state evidence inventory: repository roles versus live WordPress deployment; WP plugins, shortcodes, endpoints, hosting and backups.
2. Booking subdomain feasibility/test matrix, not a premature risk rating.
3. GSC/GA4 baseline requests and approved scan/pricing/report policy source list.
4. Architecture decision record for `unirad-web` and separate clinical operations.
5. Isolated staging setup only after repository/Vercel ownership and existing deployments are verified.

## Evidence log — 2026-10-19
- `fevzitorun/unirad.co.uk` README identifies a WordPress plugin suite; recent commits describe Bookly calendar custom titles and claustrophobia hold handling. This is repository evidence, **not** proof of active production deployment.
- `fevzitorun/unirad-plugins` README links unirad.co.uk; deployment relationship unverified.
- Existing Vercel `unirad-prime` is a distinct Next.js project with its own domain(s), and is out of scope.
- No GSC, GA4, RIS/PACS, Bookly export, live plugin inventory or approved pricing evidence yet obtained.

## Scope exclusions
No ScanBook integration. No Connective Healthcare OS dependency. No unirad-prime repurposing. No clinical advice by AI. No real patient data in tests. No new branch launch or public SEO pages until clinically operational and verified.
