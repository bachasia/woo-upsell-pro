<?php
/**
 * Buy More Save More feature — quantity-based discount tiers.
 *
 * Renders a tier table on product and cart pages.
 * Applies negative fee discount via woocommerce_cart_calculate_fees.
 *
 * @package WooUpsellPro\Features
 */

declare(strict_types=1);

namespace WooUpsellPro\Features;

use WooUpsellPro\WUP_Loader;
use WooUpsellPro\Helpers\WUP_Utils;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WUP_Buy_More_Save_More
 */
class WUP_Buy_More_Save_More
{
    /**
     * Default tiers used when no campaign or setting override is present.
     *
     * @var array<int, array{qty: int, discount: int, type: string}>
     */
    private array $default_tiers = [
        ['qty' => 2,  'discount' => 5,  'type' => 'percent'],
        ['qty' => 5,  'discount' => 10, 'type' => 'percent'],
        ['qty' => 10, 'discount' => 20, 'type' => 'percent'],
    ];

    /**
     * Register all hooks via the Loader.
     *
     * @param WUP_Loader $loader Hook registration loader.
     */
    public function register_hooks(WUP_Loader $loader): void
    {
        // Product page: render above qty input.
        $loader->add_action('woocommerce_before_add_to_cart_quantity', $this, 'display_tier_table_product');

        // Cart page: render before cart table.
        $loader->add_action('woocommerce_before_cart_table', $this, 'display_tier_table_cart');

        // Apply discount as a negative cart fee.
        $loader->add_action('woocommerce_cart_calculate_fees', $this, 'apply_cart_discount', 20);
    }

    /**
     * Render tier table on the product page.
     */
    public function display_tier_table_product(): void
    {
        if (! WUP_Utils::is_feature_enabled('buy_more_save_more')) {
            return;
        }

        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $product_id = (int) $product->get_id();
        $tiers      = $this->get_tiers_for_product($product_id);

        if (empty($tiers)) {
            return;
        }

        $current_qty   = 1;
        $product_price = (float) $product->get_price();

        // Mark active tier.
        $tiers = $this->mark_active_tier($tiers, $current_qty);

        // Localize tier data for JS dynamic highlighting.
        wp_localize_script('wup-tier-table', 'wupTierData', [
            'tiers'      => $tiers,
            'price'      => $product_price,
            'productId'  => $product_id,
        ]);

        $context = 'product';
        $this->load_template($tiers, $product_id, $context);
    }

    /**
     * Render tier table on the cart page (per product with applicable tiers).
     */
    public function display_tier_table_cart(): void
    {
        if (! WUP_Utils::is_feature_enabled('buy_more_save_more')) {
            return;
        }

        $cart = WC()->cart;

        if (! $cart || $cart->is_empty()) {
            return;
        }

        $rendered_products = [];

        foreach ($cart->get_cart() as $cart_item) {
            $product_id = (int) ($cart_item['variation_id'] ?: $cart_item['product_id']);

            // Show once per unique product.
            if (in_array($product_id, $rendered_products, true)) {
                continue;
            }

            $tiers = $this->get_tiers_for_product($product_id);

            if (empty($tiers)) {
                continue;
            }

            $qty   = (int) $cart_item['quantity'];
            $tiers = $this->mark_active_tier($tiers, $qty);

            $rendered_products[] = $product_id;
            $context             = 'cart';
            $this->load_template($tiers, $product_id, $context);
        }
    }

    /**
     * Apply negative cart fee discount based on active tier.
     *
     * @param \WC_Cart $cart Current cart instance.
     */
    public function apply_cart_discount(\WC_Cart $cart): void
    {
        if (! WUP_Utils::is_feature_enabled('buy_more_save_more')) {
            return;
        }

        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $product_id  = (int) ($cart_item['variation_id'] ?: $cart_item['product_id']);
            $product     = $cart_item['data'] ?? null;
            $tiers       = $this->get_tiers_for_product($product_id);

            if (empty($tiers) || ! $product instanceof \WC_Product) {
                continue;
            }

            $qty         = (int) $cart_item['quantity'];
            $active_tier = $this->get_active_tier($tiers, $qty);

            if ($active_tier === null) {
                continue;
            }

            $unit_price = (float) $product->get_price();
            $discount   = $this->calculate_discount($unit_price * $qty, $active_tier);

            if ($discount <= 0) {
                continue;
            }

            $label = sprintf(
                /* translators: 1: product name, 2: discount percent */
                __('Bulk discount: %1$s (%2$d%% off)', 'woo-upsell-pro'),
                $product->get_name(),
                (int) $active_tier['discount']
            );

            $cart->add_fee($label, -$discount, false);
        }
    }

    /**
     * Get applicable tiers for a product.
     * Priority: campaign-specific tiers > global default tiers.
     *
     * @param int $product_id WC product ID.
     * @return array<int, array{qty: int, discount: int, type: string}>
     */
    public function get_tiers_for_product(int $product_id): array
    {
        // Check for campaign-specific tiers stored in wup_campaign post meta.
        $campaign_tiers = $this->get_campaign_tiers_for_product($product_id);

        if (! empty($campaign_tiers)) {
            return $campaign_tiers;
        }

        // Fall back to global settings tiers.
        $settings = get_option('wup_settings', []);
        $global_tiers = $settings['bmsm_tiers'] ?? [];

        if (! empty($global_tiers) && is_array($global_tiers)) {
            return $global_tiers;
        }

        return $this->default_tiers;
    }

    /**
     * Find the highest-matching tier for a given quantity.
     *
     * @param array<int, array{qty: int, discount: int, type: string}> $tiers Tiers list.
     * @param int                                                       $qty   Current quantity.
     * @return array{qty: int, discount: int, type: string}|null
     */
    public function get_active_tier(array $tiers, int $qty): ?array
    {
        $active = null;

        // Sort ascending by qty to find highest matching.
        usort($tiers, static fn ($a, $b) => $a['qty'] <=> $b['qty']);

        foreach ($tiers as $tier) {
            if ($qty >= $tier['qty']) {
                $active = $tier;
            }
        }

        return $active;
    }

    /**
     * Calculate discount amount for a given subtotal and tier.
     *
     * @param float                                  $subtotal Cart line subtotal.
     * @param array{qty: int, discount: int, type: string} $tier Tier definition.
     * @return float
     */
    public function calculate_discount(float $subtotal, array $tier): float
    {
        if ($tier['type'] === 'percent') {
            return round($subtotal * ($tier['discount'] / 100), 2);
        }

        // Fixed discount type.
        return min((float) $tier['discount'], $subtotal);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Mark tiers with active flag based on current quantity.
     *
     * @param array<int, array{qty: int, discount: int, type: string}> $tiers Tiers.
     * @param int                                                       $qty   Current qty.
     * @return array<int, array<string, mixed>>
     */
    private function mark_active_tier(array $tiers, int $qty): array
    {
        usort($tiers, static fn ($a, $b) => $a['qty'] <=> $b['qty']);

        $active_idx = -1;
        foreach ($tiers as $i => $tier) {
            if ($qty >= $tier['qty']) {
                $active_idx = $i;
            }
        }

        foreach ($tiers as $i => &$tier) {
            $tier['active'] = ($i === $active_idx);
        }
        unset($tier);

        return $tiers;
    }

    /**
     * Retrieve campaign-specific BMSM tiers for a product.
     *
     * @param int $product_id WC product ID.
     * @return array<int, array{qty: int, discount: int, type: string}>
     */
    private function get_campaign_tiers_for_product(int $product_id): array
    {
        // Query active bmsm campaigns that target this product.
        $campaigns = get_posts([
            'post_type'      => 'wup_campaign',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_wup_campaign_type',
                    'value' => 'bmsm',
                ],
                [
                    'key'   => '_wup_campaign_status',
                    'value' => 'active',
                ],
            ],
            'fields' => 'ids',
        ]);

        if (empty($campaigns)) {
            return [];
        }

        $campaign_id = (int) $campaigns[0];

        // Check if this product is targeted (empty = all products).
        $targeted_products = get_post_meta($campaign_id, '_wup_campaign_products', true);
        if (! empty($targeted_products) && is_array($targeted_products)) {
            if (! in_array($product_id, array_map('intval', $targeted_products), true)) {
                return [];
            }
        }

        $tiers = get_post_meta($campaign_id, '_wup_campaign_discount_tiers', true);

        if (! empty($tiers) && is_array($tiers)) {
            return $tiers;
        }

        return [];
    }

    /**
     * Load tier-table.php template.
     *
     * @param array<int, array<string, mixed>> $tiers      Tier data (with active flag).
     * @param int                              $product_id WC product ID.
     * @param string                           $context    'product' or 'cart'.
     */
    private function load_template(array $tiers, int $product_id, string $context): void
    {
        $template = WUP_PLUGIN_DIR . 'templates/tier-table.php';

        if (! file_exists($template)) {
            return;
        }

        include $template;
    }
}
