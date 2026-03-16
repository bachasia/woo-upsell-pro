# Project Overview + Product Development Requirements (PDR)

_Last updated: 2026-03-16_

## Product Overview

Woo Upsell Pro is a WooCommerce upsell/cross-sell plugin designed to match the core commercial feature set of `salesgen-upsell`, while using project-owned code and `wup_` namespace conventions.

## Problem Statement

Merchants need bundled upsell mechanics (bundle, popup, side cart, BMSM, post-purchase couponing, urgency messaging) in one plugin, with centralized settings and theme-compatible rendering.

## Objectives

1. Deliver full feature parity with source reference plugin for Phases 00–09.
2. Provide secure WordPress-native settings and AJAX handling.
3. Keep architecture modular and maintainable across feature classes/templates.

## Functional Requirements

### Core
- WooCommerce dependency guard with clear admin notice path.
- HPOS compatibility declaration.

### Merchandising Features
- FBT bundle recommendations and add-all cart behavior.
- Add-to-cart popup upsell flow.
- Side cart with quantity updates, coupon actions, and fragments.
- Cart upsell, thank-you upsell, and related product blocks.
- BMSM tiered discount flow.
- Announcement and social-proof sales popups.
- Email coupon on completed order flow.
- FOMO stock urgency notice on product pages.

### Admin/Configuration
- Tabbed settings page under admin menu.
- Field schema-driven setting definitions and sanitization.
- Dynamic CSS generation from schema mapping metadata.

## Non-Functional Requirements

- PHP 8.1+ compatibility.
- WordPress 6.x and WooCommerce 8.x/9.x compatibility target.
- Security controls on all AJAX mutation paths (nonce and capability where needed).
- Low operational overhead via transient caching.

## Acceptance Criteria

- All phases 00–09 are marked complete and loaded by plugin bootstrap.
- Email coupon and FOMO behaviors are operational behind settings toggles.
- Admin settings save flow sanitizes and persists values correctly.
- Cache/transient clear operations enforce admin capability checks.
- Plugin can initialize cleanly with WooCommerce active and degrade gracefully without it.

## Constraints

- WordPress plugin architecture and WooCommerce hook lifecycle.
- Option-key namespace under `wup_`.
- Existing template and asset pipeline layout.

## Dependencies

- WordPress core APIs.
- WooCommerce classes and APIs (for example `WC_Coupon`, `wc_get_products`, and `wc_get_order`).

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Option key/schema mismatch | Misconfigured features | Add key consistency audit and regression checks |
| Theme variability for cart triggers/hooks | UX regressions | Keep selector/hook settings configurable |
| AJAX misuse or missing checks | Security defects | Enforce nonce + capability model, verify per endpoint |

## Versioned Requirement Notes

- **v1 (current):** Feature parity baseline delivered for phases 00–09.
- **vNext:** Stabilization, key consistency cleanup, full QA matrix evidence in CI/manual reports.
