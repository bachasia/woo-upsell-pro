# Project Roadmap

_Last updated: 2026-03-16_

## Current Status

- Project: **Woo Upsell Pro**
- Objective: Clone `salesgen-upsell` feature set under `wup_` namespace
- Overall Progress: **100%**
- Current Phase: **Completed (Phases 00–09)**

## Phase Progress

| Phase | Scope | Status |
|---|---|---|
| 00 | Foundation/bootstrap/WC guard | Completed |
| 01 | Product source + variation resolver | Completed |
| 02 | FBT bundle + product fields + transient support | Completed |
| 03 | Popup + side cart | Completed |
| 04 | Cart upsell + thank-you upsell + related | Completed |
| 05 | Buy More Save More + coupon engine | Completed |
| 06 | Announcement bars + sales popup | Completed |
| 07 | Email coupon + FOMO stock counter | Completed |
| 08 | Admin settings page + schema + dynamic CSS wiring | Completed |
| 09 | QA/security hardening + compatibility | Completed |

## Recently Completed Work

### Phase 07
- Added order-triggered email coupon flow.
- Added FOMO stock notice rendering with configurable message, range, and color.

### Phase 08
- Rewrote settings page as native PHP tabbed renderer.
- Added broad settings schema coverage (9 tabs, 100+ field definitions in current schema).
- Wired dynamic CSS schema to `WUP_Assets`.

### Phase 09
- Declared WooCommerce HPOS compatibility.
- Added sanitize callbacks in settings registration.
- Added admin capability check for cache/transient clearing actions.
- Scoped BMSM asset enqueue to relevant storefront contexts.

## Near-Term Follow-Up (Post-Parity)

1. **Settings key consistency pass**
   - Verify schema IDs align with runtime option reads across all feature classes.
2. **End-to-end QA pass in real WooCommerce environments**
   - Desktop + mobile matrix validation.
3. **Release hardening**
   - Versioning, changelog discipline, and smoke test checklist before tags.

## Success Criteria (Current)

- Phase parity delivered for 00–09.
- No blocking bootstrap/runtime compatibility issues identified from implementation review.
- Security guardrails present on critical admin operations.
