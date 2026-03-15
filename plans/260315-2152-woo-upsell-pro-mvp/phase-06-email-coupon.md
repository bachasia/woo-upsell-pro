---
phase: 6
title: "Post-Purchase Coupon Email"
status: pending
effort: 4h
depends_on: [1]
blocks: [8]
---

# Phase 06: Post-Purchase Coupon Email

## Context Links
- [WC Architecture Research](../reports/researcher-woocommerce-architecture-260315-2151.md) -- Email hooks

## Overview
**Priority:** P2
After order status changes to processing, generate unique WC coupon and send branded email to customer. Configurable discount %, expiry, min order.

## Requirements
- Trigger on `woocommerce_order_status_processing`
- Generate unique coupon code (prefix + random string)
- Create WC coupon programmatically with configurable params
- Send HTML email via WC email system (extend WC_Email)
- Email template: branded, shows coupon code + details + CTA
- Settings: discount type/amount, expiry days, min order, usage limit
- Track coupon source via meta (`_wup_source: email_coupon`)
- One coupon per order (prevent duplicates)

## Architecture
```
Order status -> processing
  --> woocommerce_order_status_processing hook
  --> WUP_Email_Coupon::handle_order_processing($order_id)
  --> Check: feature enabled + not already sent for this order
  --> Generate coupon code
  --> Create WC_Coupon object
  --> Queue WC email (WUP_Email_Coupon_Notification)
  --> Mark order meta: _wup_coupon_sent = true
```

## Related Code Files
**Create:**
- `includes/features/class-wup-email-coupon.php`
- `includes/features/class-wup-email-coupon-notification.php` (extends WC_Email)
- `templates/email-coupon.php`

**Modify:**
- `includes/class-wup-loader.php` -- register hooks

## Implementation Steps

### 1. `class-wup-email-coupon.php`
- Namespace: `WooUpsellPro\Features`
- Methods:
  - `register_hooks(WUP_Loader $loader)`: add order status hook + email class filter
  - `handle_order_processing(int $order_id)`: main handler
  - `generate_coupon_code(): string`: `'wup-' . wp_generate_password(8, false, false)`
  - `create_coupon(string $code, array $settings): int`: create WC_Coupon
  - `register_email_class(array $emails): array`: add our email to WC email list
  - `has_coupon_been_sent(int $order_id): bool`: check order meta

### 2. Order Processing Handler
```php
handle_order_processing($order_id):
  1. Check feature enabled: Utils::is_feature_enabled('email_coupon')
  2. Get order: wc_get_order($order_id)
  3. Guard: if !$order || has_coupon_been_sent($order_id) -> return
  4. Get settings from wup_settings
  5. Generate unique coupon code
  6. Create coupon with settings
  7. Trigger email: do_action('wup_send_coupon_email', $order_id, $coupon_code)
  8. Mark sent: $order->update_meta_data('_wup_coupon_sent', 'yes')
  9. Store coupon code: $order->update_meta_data('_wup_coupon_code', $code)
  10. $order->save()
```

### 3. Coupon Creation
```php
create_coupon($code, $settings):
  $coupon = new WC_Coupon();
  $coupon->set_code($code);
  $coupon->set_discount_type($settings['type']); // 'percent' or 'fixed_cart'
  $coupon->set_amount($settings['amount']); // e.g., 10
  $coupon->set_date_expires(strtotime("+{$settings['expiry_days']} days"));
  $coupon->set_minimum_amount($settings['min_order'] ?? 0);
  $coupon->set_usage_limit(1); // single use
  $coupon->set_usage_limit_per_user(1);
  $coupon->set_individual_use(true);
  $coupon->update_meta_data('_wup_source', 'email_coupon');
  $coupon->save();
  return $coupon->get_id();
```

### 4. `class-wup-email-coupon-notification.php`
- Extend `WC_Email`
- Constructor: set `$this->id`, `$this->title`, `$this->description`
  - `$this->template_base = WUP_PLUGIN_DIR . 'templates/'`
  - `$this->template_html = 'email-coupon.php'`
  - `$this->customer_email = true`
- Hook trigger: `wup_send_coupon_email`
- `trigger($order_id, $coupon_code)`:
  - Load order, set recipient to billing email
  - Store coupon_code and order for template
  - Call `$this->send()`
- `get_content_html()`: load template with coupon data

### 5. Register Email Class
- Filter: `woocommerce_email_classes`
- Add `WUP_Email_Coupon_Notification` to email array
- This makes it appear in WC > Settings > Emails

### 6. `templates/email-coupon.php`
```php
<?php do_action('woocommerce_email_header', $email_heading, $email); ?>

<p><?= sprintf(__('Thank you for your order, %s!', 'woo-upsell-pro'), $order->get_billing_first_name()) ?></p>
<p><?= __('As a token of appreciation, here\'s an exclusive discount for your next purchase:', 'woo-upsell-pro') ?></p>

<div style="text-align:center; margin:24px 0; padding:16px; background:#f7f7f7; border-radius:8px;">
  <p style="font-size:12px; margin:0 0 8px;"><?= __('Your Coupon Code', 'woo-upsell-pro') ?></p>
  <p style="font-size:24px; font-weight:bold; letter-spacing:2px; margin:0; color:#7f54b3;">
    <?= esc_html($coupon_code) ?>
  </p>
  <p style="font-size:13px; margin:8px 0 0; color:#666;">
    <?= sprintf(__('%s off your next order', 'woo-upsell-pro'), $discount_text) ?>
    <?php if ($min_order > 0): ?>
      | <?= sprintf(__('Min. order: %s', 'woo-upsell-pro'), wc_price($min_order)) ?>
    <?php endif; ?>
    | <?= sprintf(__('Expires: %s', 'woo-upsell-pro'), $expiry_date) ?>
  </p>
</div>

<p style="text-align:center;">
  <a href="<?= esc_url(wc_get_page_permalink('shop')) ?>"
     style="display:inline-block; padding:12px 24px; background:#7f54b3; color:#fff; text-decoration:none; border-radius:4px;">
    <?= __('Shop Now', 'woo-upsell-pro') ?>
  </a>
</p>

<?php do_action('woocommerce_email_footer', $email); ?>
```

### 7. Settings (stored in wup_settings)
```php
'email_coupon' => [
    'enabled' => true,
    'discount_type' => 'percent',  // percent | fixed_cart
    'discount_amount' => 10,
    'expiry_days' => 30,
    'min_order_amount' => 0,
    'email_heading' => 'Thank you! Here\'s a gift.',
]
```

## Todo
- [ ] Implement WUP_Email_Coupon handler class
- [ ] Implement coupon generation and creation
- [ ] Create WC_Email extension class
- [ ] Register email class with WC
- [ ] Create email-coupon.php template
- [ ] Add duplicate prevention (order meta check)
- [ ] Add `_wup_source` meta to coupons for tracking
- [ ] Test email delivery
- [ ] Test coupon applies correctly at checkout

## Success Criteria
- Email sent after order status -> processing
- Unique coupon code generated per order
- Coupon works at checkout with correct discount
- Email renders properly in major email clients
- No duplicate emails/coupons for same order
- Email visible in WC > Settings > Emails for customization

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| Email deliverability | User never sees coupon | Use WC email system (inherits store SMTP) |
| Coupon code collision | Duplicate codes | 8-char random + prefix makes collision negligible |
| Order status re-trigger | Multiple emails | Guard with `_wup_coupon_sent` order meta |
