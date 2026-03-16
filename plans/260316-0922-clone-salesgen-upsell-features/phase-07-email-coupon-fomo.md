# Phase 07 — Email Coupon (Thank-you) + FOMO Stock Counter

**Status:** Todo | **Priority:** P1 | **Effort:** S
**Depends on:** Phase 00

---

## Part A — Email Coupon on Order

### Overview

Auto-generate a WC coupon when an order is placed and send a custom email to the customer with the coupon code. Configurable: discount amount, email subject, email body with template tags. Option to restrict one coupon per customer (one-time use).

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_coupon_enable` | `no` | Enable email coupon feature |
| `wup_coupon_amount` | `15` | Discount % |
| `wup_coupon_code` | `` | Fixed code (empty = auto-generate) |
| `wup_coupon_email_subject` | `Congrats! You unlocked special discount on {{site.name}}!` | Email subject |
| `wup_coupon_email_content` | (see below) | Email body |
| `wup_advanced_coupons_one` | `no` | One coupon per customer (usage_limit_per_user=1) |

**Default email content:**
```
Hi {{customer.name}},

Thank you for your order on our site {{site.name}}.

You just unlocked {{discount.amount}}% discount. Use code below to received {{discount.amount}}% OFF on next orders.

Code: {{discount.code}}

If you have any questions or concerns, please contact us by reply this email.

Happy a nice day!
```

**Template tags:**
| Tag | Value |
|-----|-------|
| `{{site.name}}` | `get_bloginfo('name')` |
| `{{customer.name}}` | Order billing first name |
| `{{discount.amount}}` | Coupon percent amount |
| `{{discount.code}}` | Generated/fixed coupon code |

### Files

- `includes/features/class-wup-email-coupon.php`
- `templates/email/coupon.php` (HTML email template)

### class-wup-email-coupon.php

```php
class WUP_Email_Coupon {
  public function __construct( array $options ) { ... }

  // Hook: woocommerce_thankyou (priority 10)
  public function on_order_complete( int $order_id ): void

  private function generate_coupon( int $order_id ): string  // returns coupon code
  private function coupon_exists( string $code ): bool
  private function send_email( WC_Order $order, string $code ): void
  private function render_email_body( array $tokens ): string
  private function already_sent( int $order_id ): bool  // prevents duplicate sends
}
```

### Coupon Generation

```php
// Auto-generate if wup_coupon_code is empty
$code = !empty($options['wup_coupon_code'])
    ? sanitize_title($options['wup_coupon_code'])
    : 'wup-' . strtolower(wp_generate_password(8, false));

// Create WC coupon programmatically
$coupon = new WC_Coupon();
$coupon->set_code( $code );
$coupon->set_discount_type( 'percent' );
$coupon->set_amount( $options['wup_coupon_amount'] );
$coupon->set_usage_limit( 1 );  // single use
if ( $options['wup_advanced_coupons_one'] === 'yes' ) {
    $coupon->set_usage_limit_per_user( 1 );
}
$coupon->save();
```

### Duplicate Prevention

Store sent flag on order meta `_wup_coupon_sent = 1` after successful send. Check before processing.

### Email Sending

Use `wp_mail()` with `Content-Type: text/html`. No custom WC email class needed (plain wp_mail is sufficient and simpler).

---

## Part B — FOMO Stock Counter

### Overview

Shows stock urgency message on product pages when product stock is within a configured min–max range. e.g. "Only 3 stock left!" displayed in a colored notice.

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_fomo_stock_enable` | `no` | Enable FOMO stock counter |
| `wup_fomo_stock_msg` | `Only [stock] stock left!` | Message template (`[stock]` token) |
| `wup_fomo_stock_min` | `5` | Show when stock ≤ this value |
| `wup_fomo_stock_max` | `10` | Show when stock ≥ this value (lower bound of display range) |
| `wup_fomo_stock_color` | `#ff9900` | Text/icon color |

**Display logic:** Show message when `$stock >= wup_fomo_stock_min && $stock <= wup_fomo_stock_max`. The `wup_fomo_stock_min` is the lower bound and `wup_fomo_stock_max` is the upper bound of the "show" range.

*(Note: salesgen defaults are min=5, max=10 — meaning show when stock between 5 and 10.)*

### Files

- `includes/features/class-wup-fomo-stock.php`

### class-wup-fomo-stock.php

```php
class WUP_Fomo_Stock {
  public function __construct( array $options ) { ... }

  // Hook: woocommerce_single_product_summary (priority 25)
  public function render_stock_notice(): void

  private function get_stock_qty(): ?int  // null if not managing stock
  private function should_show( int $qty ): bool
}
```

### Render output

```html
<div class="wup-fomo-stock" style="color: {wup_fomo_stock_color};">
  Only 3 stock left!
</div>
```

Color applied inline (dynamic CSS engine also handles `wup_fomo_stock_color` via css map in settings schema if desired, or just inline style).

Token `[stock]` replaced with actual `$product->get_stock_quantity()`.

---

## Implementation Steps

**Email Coupon:**
1. Create `class-wup-email-coupon.php`
2. Hook `woocommerce_thankyou` — check `_wup_coupon_sent` order meta guard
3. `generate_coupon()`: create WC_Coupon, set percent/amount/usage limits, save
4. `send_email()`: replace tokens in subject + body, `wp_mail()` with HTML content type
5. Set `_wup_coupon_sent = 1` on order meta after send
6. Create `templates/email/coupon.php` for HTML email body (optional — can be rendered in class)

**FOMO Stock:**
1. Create `class-wup-fomo-stock.php`
2. Hook `woocommerce_single_product_summary` only when `wup_fomo_stock_enable == yes`
3. `get_stock_qty()`: `$product->get_manage_stock() && $product->get_stock_quantity()`
4. `should_show()`: `$qty >= $min && $qty <= $max`
5. Render inline div with color style and `[stock]` token replaced
6. Only show on `is_product()` pages

## Todo

### Email Coupon
- [ ] `includes/features/class-wup-email-coupon.php`
- [ ] `templates/email/coupon.php` (HTML email template, optional)
- [ ] WC_Coupon programmatic creation (percent, usage_limit, usage_limit_per_user)
- [ ] Token replacement in subject + body
- [ ] `_wup_coupon_sent` order meta duplicate guard
- [ ] `wp_mail()` with HTML content type header

### FOMO Stock
- [ ] `includes/features/class-wup-fomo-stock.php`
- [ ] Stock range check (min/max display window)
- [ ] `[stock]` token replacement
- [ ] Inline color style from setting
- [ ] Only runs on single product pages
