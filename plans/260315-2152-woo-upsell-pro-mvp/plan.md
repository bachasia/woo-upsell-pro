---
title: "Woo Upsell Pro MVP Implementation"
description: "WooCommerce upsell plugin with popup, cart widget, tiered discounts, post-purchase email, campaign system, and React admin"
status: pending
priority: P1
effort: 32h
branch: main
tags: [woocommerce, plugin, upsell, mvp]
created: 2026-03-15
---

# Woo Upsell Pro -- MVP Implementation Plan

## Overview
WooCommerce upsell plugin delivering 6 features: add-to-cart popup, cart upsell widget, buy-more-save-more tiers, post-purchase coupon email, campaign system (CPT + REST), React admin UI.

## Tech Stack
- PHP 8.0+ / WP 6.4+ / WC 8.0+ / HPOS-compatible
- OOP + PSR-4 (`WooUpsellPro\`), prefix `wup_`, text domain `woo-upsell-pro`
- REST API (admin), WC Store API (frontend cart), @wordpress/scripts build
- No external PHP deps

## Phase Summary

| # | Phase | Effort | Status | Depends On |
|---|-------|--------|--------|------------|
| 01 | [Project Setup & Bootstrap](phase-01-project-setup.md) | 3h | pending | -- |
| 02 | [Campaign System + REST API](phase-02-campaign-system.md) | 5h | pending | 01 |
| 03 | [Buy More Save More](phase-03-buy-more-save-more.md) | 4h | pending | 01 |
| 04 | [Add-to-Cart Popup](phase-04-add-to-cart-popup.md) | 4h | pending | 01 |
| 05 | [Cart Upsell Widget](phase-05-cart-upsell-widget.md) | 4h | pending | 01 |
| 06 | [Post-Purchase Coupon Email](phase-06-email-coupon.md) | 4h | pending | 01 |
| 07 | [Admin UI (React)](phase-07-admin-ui.md) | 5h | pending | 02 |
| 08 | [Testing & QA](phase-08-testing.md) | 3h | pending | 03-07 |

## Dependency Graph
```
Phase 01 (setup)
  |
  +---> Phase 02 (campaigns) ---> Phase 07 (admin UI)
  +---> Phase 03 (BMSM)     ---|
  +---> Phase 04 (popup)    ---+--> Phase 08 (testing)
  +---> Phase 05 (cart)     ---|
  +---> Phase 06 (email)    ---|
```

Phases 03-06 can run in parallel after Phase 01.
Phase 07 requires Phase 02 (REST endpoints).
Phase 08 requires all feature phases complete.

## Key Architecture Decisions
1. Hook registration centralized in Loader class only
2. REST API exclusively -- no admin-ajax.php
3. WC Store API for frontend cart operations (HPOS-compatible)
4. Campaign data stored as CPT `wup_campaign` with post meta
5. Settings stored in `wp_options` (autoload=no)
6. Frontend JS: vanilla ES6+; Admin JS: React + @wordpress/components
7. SCSS with BEM naming, `.wup-` prefix

## Research Reports
- [WooCommerce Architecture](../reports/researcher-woocommerce-architecture-260315-2151.md)
- [Market Analysis](../reports/researcher-market-analysis-260315-2151.md)
- [Admin UI Research](../reports/researcher-admin-ui-260315-2151.md)
- [Tech Stack](../../docs/tech-stack.md)
- [Design Guidelines](../../docs/design-guidelines.md)
