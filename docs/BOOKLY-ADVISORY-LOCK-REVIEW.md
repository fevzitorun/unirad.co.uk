# Advisory-lock booking patch — 2026-10-21

**Review branch only. NOT LIVE, NOT INTEGRATION-TESTED.**

The Bookly write flow now obtains a MySQL named advisory lock `uqb_staff_<staff-id>` before overlap-check and insert, and releases it in `finally`. A lock timeout fails closed and records an order note. The lock covers the overlap check, insertion, order ownership metadata write and customer association preparation up to the customer-link section; it is held on the WordPress DB connection.

**Important limits:** This serialises only writers that use the same named lock. Bookly-native, manual, third-party, or other integrations can still write overlapping appointments without acquiring it. The WordPress DB adapter may use pooled/proxied connections; MySQL GET_LOCK behaviour and timeout must be verified against the deployed DB. This is not a transaction and does not make Bookly's tables inherently race-safe.

The order's single ownership marker is insufficient for multi-item booking. Standard WooCommerce emails may still misstate booking status. Refunds and manually moved bookings need robust row-level provenance. No guarantee of production correctness.

Before merge: php -l both copies; isolated WordPress/Bookly/WooCommerce clone with matching DB; synthetic two-worker concurrency and cross-writer tests; interval, DST and refund scenarios; verify lock release under exception/continue; test email and payment reconciliation. Clinical and privacy gates remain HOLD.
