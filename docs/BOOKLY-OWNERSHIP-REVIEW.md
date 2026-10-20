# Bookly ownership hotfix — review branch only (2026-10-20)

**NOT DEPLOYED. DO NOT MERGE WITHOUT CLONE TESTS AND CLINIC APPROVAL.**

Two mirrored copies of Quick Booking V2 were patched together. When the staff/start slot is occupied, the code now refuses to overwrite the existing appointment unless the same order carries an ownership marker, in which case it treats the call as an idempotent retry. Newly inserted Bookly appointments store `_uqb_owned_bookly_appt_id` on the WooCommerce order. Cancellation/refund deletion requires the marker to match the existing link. Legacy orders with no marker are held for manual reconciliation instead of automatic deletion.

Limitations: the ownership marker is not yet independently authenticated against the Bookly row; database concurrency and overlapping intervals are not solved; existing email hooks may send confirmation even when a slot conflict blocks Bookly creation; multi-item bookings may overwrite order-level metadata; native Bookly entries and legacy records require migration logic. **This patch is a defensive review candidate, not a production-ready solution.**

Required tests: concurrent checkout, overlapping appointments, retries, legacy cancellations/refunds, manually moved bookings, multi-item orders, WooCommerce payment status and Brevo email ordering, plus clinical safety form/data controls. Use isolated WordPress clone and synthetic patients only.
