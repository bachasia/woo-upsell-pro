---
phase: 2
title: "Campaign System + REST API"
status: pending
effort: 5h
depends_on: [1]
blocks: [7, 8]
---

# Phase 02: Campaign System + REST API

## Context Links
- [WooCommerce Architecture Research](../reports/researcher-woocommerce-architecture-260315-2151.md)
- [Admin UI Research](../reports/researcher-admin-ui-260315-2151.md)

## Overview
**Priority:** P1
Campaign CRUD logic + REST endpoints consumed by React admin. Campaign Manager handles product suggestion algorithm.

## Requirements
- Campaign CRUD via CampaignManager class
- REST API with full CRUD (5 endpoints)
- Product suggestion algorithm (auto: category/cross-sell; manual: per-campaign)
- Permission checks on all endpoints (`manage_woocommerce`)
- Nonce verification

## Architecture
```
REST Controller --> CampaignManager --> WP_Query (CPT wup_campaign)
                                    --> Product suggestion algo
```

**Campaign Data Model (post meta):**
```
_wup_campaign_type: popup | cart_upsell | bmsm | email_coupon
_wup_campaign_status: active | paused | draft
_wup_campaign_rules: { target_products: [], categories: [], tags: [], conditions: {} }
_wup_campaign_products: [product_ids]  // manual product selection
_wup_campaign_discount_tiers: [{ qty: int, discount: float, type: percent|fixed }]
_wup_campaign_settings: { popup_delay: int, auto_dismiss: int, ... }
```

## Related Code Files
**Create:**
- `includes/campaigns/class-wup-campaign-manager.php`
- `includes/api/class-wup-rest-controller.php`

**Modify:**
- `includes/campaigns/class-wup-campaign-cpt.php` (if meta registration needs updates)
- `woo-upsell-pro.php` (register REST routes in loader)

## Implementation Steps

### 1. `class-wup-campaign-manager.php`
- Namespace: `WooUpsellPro\Campaigns`
- Methods:
  - `get_campaigns(array $args = []): array` -- WP_Query wrapper, returns formatted campaigns
  - `get_campaign(int $id): ?array` -- single campaign with all meta
  - `create_campaign(array $data): int|WP_Error` -- wp_insert_post + update_post_meta
  - `update_campaign(int $id, array $data): bool|WP_Error` -- wp_update_post + meta
  - `delete_campaign(int $id): bool` -- wp_delete_post(force=true)
  - `get_active_campaigns(string $type): array` -- active campaigns filtered by type
  - `get_suggested_products(int $campaign_id, array $cart_items = []): array` -- product suggestions

### 2. Product Suggestion Algorithm
```
get_suggested_products($campaign_id, $cart_items):
  1. If campaign has manual products -> return those (filtered by in-stock)
  2. Else auto mode:
     a. Collect categories/tags from cart items
     b. Query cross-sells of cart products
     c. Query products in same categories (exclude already in cart)
     d. Merge, deduplicate, limit to 3
     e. Sort by: cross-sells first, then by popularity (total_sales meta)
  3. Return product data: id, name, price, image_url, permalink
```

### 3. `class-wup-rest-controller.php`
- Namespace: `WooUpsellPro\Api`
- Extend `WP_REST_Controller`
- Base: `wup/v1`
- Register routes in `register_routes()`:

| Method | Endpoint | Callback | Permission |
|--------|----------|----------|------------|
| GET | `/campaigns` | `get_campaigns` | `manage_woocommerce` |
| POST | `/campaigns` | `create_campaign` | `manage_woocommerce` |
| GET | `/campaigns/(?P<id>\d+)` | `get_campaign` | `manage_woocommerce` |
| PUT | `/campaigns/(?P<id>\d+)` | `update_campaign` | `manage_woocommerce` |
| DELETE | `/campaigns/(?P<id>\d+)` | `delete_campaign` | `manage_woocommerce` |
| GET | `/products` | `search_products` | `manage_woocommerce` |
| GET | `/settings` | `get_settings` | `manage_woocommerce` |
| POST | `/settings` | `update_settings` | `manage_woocommerce` |

### 4. Endpoint Details

**GET /campaigns** -- list all campaigns
- Query params: `type`, `status`, `per_page`, `page`
- Response: `{ campaigns: [...], total: int, pages: int }`

**POST /campaigns** -- create
- Body: `{ title, type, rules, products, discount_tiers, settings }`
- Sanitize all inputs, validate type enum
- Return created campaign object

**GET /campaigns/{id}** -- single
- Return full campaign with all meta

**PUT /campaigns/{id}** -- update
- Partial update supported
- Return updated campaign

**DELETE /campaigns/{id}** -- delete
- Force delete (bypass trash)
- Return `{ deleted: true }`

**GET /products** -- product search for campaign builder
- Query params: `search`, `category`, `per_page`
- Return: `[{ id, name, price, image, sku }]`
- Uses `wc_get_products()` or `WC_Product_Query`

**GET/POST /settings** -- plugin settings CRUD
- Read/write `wup_settings` option
- Sanitize all values

### 5. Request Validation & Sanitization
- Use `sanitize_text_field()`, `absint()`, `wp_kses_post()`
- Validate campaign type against allowed values
- Validate discount tier structure (qty > 0, discount > 0)
- Return `WP_Error` with appropriate HTTP codes on failure

### 6. Register in Loader
- Hook `rest_api_init` -> `$rest_controller->register_routes()`

## Todo
- [ ] Implement CampaignManager with CRUD methods
- [ ] Implement product suggestion algorithm
- [ ] Create REST controller with all endpoints
- [ ] Add request validation/sanitization
- [ ] Add settings endpoints
- [ ] Register routes via Loader
- [ ] Test endpoints with curl/Postman

## Success Criteria
- All REST endpoints return correct responses
- Permission denied for non-admin users (403)
- Campaign CRUD works end-to-end
- Product search returns WC products
- Settings save/load correctly

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| REST route conflicts | 404s | Use unique `wup/v1` namespace |
| Large product catalog slow search | UX lag | Limit results, add search index |
| Meta data corruption | Bad campaigns | Validate all input strictly |
