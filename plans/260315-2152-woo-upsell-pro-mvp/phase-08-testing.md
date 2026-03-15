---
phase: 8
title: "Testing & QA"
status: pending
effort: 3h
depends_on: [3, 4, 5, 6, 7]
blocks: []
---

# Phase 08: Testing & QA

## Context Links
- [All Phase Files](plan.md)

## Overview
**Priority:** P1
PHPUnit tests for business logic + manual QA checklist. Focus on discount calculation, coupon generation, campaign CRUD, and HPOS compatibility.

## Requirements
- PHPUnit setup with WP/WC test framework
- Unit tests for critical business logic
- Manual QA checklist for UI features
- HPOS compatibility verification
- Mobile responsiveness verification

## Architecture
```
tests/
├── phpunit.xml
├── bootstrap.php
└── unit/
    ├── test-campaign-manager.php
    ├── test-buy-more-save-more.php
    ├── test-email-coupon.php
    └── test-rest-controller.php
```

## Related Code Files
**Create:**
- `tests/phpunit.xml`
- `tests/bootstrap.php`
- `tests/unit/test-campaign-manager.php`
- `tests/unit/test-buy-more-save-more.php`
- `tests/unit/test-email-coupon.php`
- `tests/unit/test-rest-controller.php`

## Implementation Steps

### 1. PHPUnit Setup
- `phpunit.xml`: configure test suite, bootstrap, WP test lib path
- `bootstrap.php`: load WP test framework, activate plugin, load WC
- Add `phpunit` to composer require-dev: `yoast/phpunit-polyfills`

### 2. `test-campaign-manager.php`
Test cases:
- [ ] `test_create_campaign()`: create + verify post exists with correct meta
- [ ] `test_get_campaigns()`: create multiple, verify list returns all
- [ ] `test_get_campaign_by_id()`: single campaign returns correct data
- [ ] `test_update_campaign()`: modify meta, verify changes persisted
- [ ] `test_delete_campaign()`: delete, verify post gone
- [ ] `test_get_active_campaigns_by_type()`: filter by type + active status
- [ ] `test_get_suggested_products_manual()`: manual product list returned
- [ ] `test_get_suggested_products_auto()`: auto-selection returns products

### 3. `test-buy-more-save-more.php`
Test cases:
- [ ] `test_get_active_tier_single()`: qty=3, tiers [2,5,10], expect tier 2
- [ ] `test_get_active_tier_exact()`: qty=5, expect tier 5
- [ ] `test_get_active_tier_none()`: qty=1, expect null (no tier)
- [ ] `test_get_active_tier_highest()`: qty=15, expect tier 10
- [ ] `test_calculate_discount_percent()`: 10% on $50 = $5
- [ ] `test_calculate_discount_fixed()`: $3 fixed = $3
- [ ] `test_calculate_discount_zero_qty()`: qty 0, expect 0 discount
- [ ] `test_tiers_sorted_ascending()`: tiers returned in qty order
- [ ] `test_discount_applied_as_negative_fee()`: mock cart, verify fee added

### 4. `test-email-coupon.php`
Test cases:
- [ ] `test_generate_coupon_code_format()`: starts with 'wup-', 12 chars total
- [ ] `test_generate_coupon_code_unique()`: 100 codes, all unique
- [ ] `test_create_coupon_with_settings()`: verify WC_Coupon created with correct params
- [ ] `test_coupon_has_wup_source_meta()`: `_wup_source` = 'email_coupon'
- [ ] `test_duplicate_prevention()`: second call for same order_id does nothing
- [ ] `test_coupon_expiry_set_correctly()`: 30 days from now

### 5. `test-rest-controller.php`
Test cases:
- [ ] `test_get_campaigns_unauthorized()`: non-admin gets 403
- [ ] `test_get_campaigns_authorized()`: admin gets 200 + list
- [ ] `test_create_campaign_valid()`: POST returns 201 + campaign object
- [ ] `test_create_campaign_invalid_type()`: bad type returns 400
- [ ] `test_update_campaign()`: PUT returns updated object
- [ ] `test_delete_campaign()`: DELETE returns 200 + `{ deleted: true }`
- [ ] `test_search_products()`: returns WC product results

### 6. Manual QA Checklist

**Add-to-Cart Popup:**
- [ ] AJAX add-to-cart triggers popup (archive page)
- [ ] Non-AJAX add-to-cart triggers popup (single product)
- [ ] Upsell product displays in popup
- [ ] "View Cart" navigates to cart
- [ ] "Continue Shopping" dismisses popup
- [ ] Auto-dismiss after 5s works
- [ ] Progress bar animates
- [ ] Mobile bottom-sheet layout correct
- [ ] ESC key closes popup
- [ ] Focus trap works

**Cart Upsell Widget:**
- [ ] Widget appears below cart totals
- [ ] Shows 2-3 relevant products
- [ ] Products in cart are excluded
- [ ] "+ Add" adds to cart via AJAX
- [ ] Cart totals update after add
- [ ] Button shows loading -> success states
- [ ] Empty state: widget hidden when no suggestions
- [ ] Responsive grid (3/2/1 cols)

**Buy More Save More:**
- [ ] Tier table shows on product page
- [ ] Active tier highlights on qty change
- [ ] Tier table shows on cart page
- [ ] Discount applied as negative fee in cart
- [ ] Discount amount correct for each tier
- [ ] Multiple products with tiers each discounted correctly
- [ ] Mobile table responsive

**Post-Purchase Coupon Email:**
- [ ] Email sent after order -> processing
- [ ] Coupon code unique and valid
- [ ] Coupon applies at checkout with correct discount
- [ ] Email template renders properly
- [ ] No duplicate emails for same order
- [ ] Coupon expires correctly

**Admin UI:**
- [ ] WC Settings tab "Upsell Pro" visible
- [ ] Feature toggles save/load
- [ ] Campaign list loads
- [ ] Create campaign works
- [ ] Edit campaign works
- [ ] Delete campaign works
- [ ] Product picker search works
- [ ] BMSM tier configuration works
- [ ] Settings page saves all values

**HPOS Compatibility:**
- [ ] Plugin declares HPOS compatibility
- [ ] Order meta operations use HPOS-compatible methods
- [ ] No direct `wp_postmeta` queries for orders

### 7. Cross-Browser Testing (manual)
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## Todo
- [ ] Set up PHPUnit with WP test framework
- [ ] Write CampaignManager tests
- [ ] Write BuyMoreSaveMore calculation tests
- [ ] Write EmailCoupon tests
- [ ] Write REST controller tests
- [ ] Execute manual QA checklist
- [ ] Verify HPOS compatibility
- [ ] Cross-browser test

## Success Criteria
- All PHPUnit tests pass
- All manual QA items checked
- No PHP errors/warnings in error log
- HPOS compatibility verified
- Mobile layouts work on iOS + Android

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| WP test framework setup complexity | Delays testing | Use wp-env or local Docker setup |
| Flaky AJAX tests | False negatives | Focus unit tests on logic, manual test UI |
| Missing edge cases | Bugs in production | Prioritize discount calc + coupon tests |
