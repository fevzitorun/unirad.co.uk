# Booking interval overlap review — 2026-10-20

Review branch only; **NOT DEPLOYED OR TESTED IN A WORDPRESS CLONE**.

The Bookly write path now checks whether any existing appointment for the configured staff overlaps the requested half-open interval: `existing.start_date < requested.end_date AND existing.end_date > requested.start_date`. This prevents obvious staggered-time conflicts (e.g. 10:00–11:00 vs 10:30–11:30) when the conflicting row is already committed before the check. Existing rows are not overwritten. Idempotent retry is permitted only when both stored order markers match the conflicting appointment ID.

**Critical remaining risk:** a read-then-insert race remains: two concurrent requests can both observe an empty interval. The code does not yet lock the staff resource or establish a unique reservation constraint. This patch must not be represented as concurrency-safe.

Other unresolved blockers: per-item ownership for multi-item orders, safe deletion of linked customer records, DB-level ownership verification, post-payment failure reconciliation, standard WooCommerce transactional emails, live plugin inventory and clinical safety questionnaire source.

Acceptance test plan (not run): staggered overlap, identical start, adjacent non-overlap, overnight intervals, timezone/DST, concurrent two-process checkout, webhook retries, reschedule, refund and cancellation. Use isolated clone and synthetic patients only. Release gate HOLD.
