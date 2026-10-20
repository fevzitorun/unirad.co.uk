# Synthetic booking acceptance matrix — 2026-10-20

Status: **NOT EXECUTED**. Review-branch defensive patch only. No clone, database or test payment provider was used.

| Case | Expected result |
|---|---|
| Empty slot, one synthetic order | One Bookly appointment, durable order ownership |
| Same order, repeated payment hook | No duplicate booking or email |
| Other order, identical start and staff | Existing row unchanged; second order flagged for staff review; no confirmation email |
| Overlapping intervals, different start | Conflict detected before insert (NOT YET IMPLEMENTED) |
| Two concurrent checkouts | Exactly one wins, loser safely fails (NOT YET IMPLEMENTED) |
| Refund order that owns appointment | Only its appointment removed, idempotently |
| Refund legacy order without ownership marker | No automatic deletion; manual reconciliation |
| Manually moved booking | Ownership persists independent of start time |
| Multiple scan items | Each item has a durable independent booking link (NOT YET IMPLEMENTED) |
| Paid order but Bookly insert fails | No booking confirmation, staff exception and payment reconciliation |
| Callback request | No claim of confirmed appointment |
| Positive/uncertain MRI safety answer | Clinician review; no automatic safety clearance |
| Brevo delivery retry | No duplicate message and no patient data in logs |
| Failed/declined payment | No misleading booking confirmation |

## Review branch guard
`send_patient_confirmation()` now withholds the custom patient confirmation unless the order has matching `_uqb_bookly_appt_id` and `_uqb_owned_bookly_appt_id` markers. This does not yet guarantee ownership at the database level, nor control WooCommerce's other standard emails. Requires testing with a live-equivalent isolated clone.

## Blocking issues
- No isolated WordPress/Bookly/WooCommerce clone available for actual execution.
- Need atomic slot locking and full interval overlap checks.
- Need ownership persisted per order item and verified against Bookly row.
- Need an explicit reconciliation state and safe payment/refund communications.
- Need installed plugin/version inventory and the separate safety questionnaire source.
- Need review of WooCommerce default transactional emails.

**Gate: HOLD.** No production deployment or live patient/payment actions.
