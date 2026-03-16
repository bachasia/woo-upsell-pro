<?php
/**
 * Cart Upsell Widget feature.
 *
 * Renders a product suggestion grid below cart collaterals.
 * Excludes products already in the cart.
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
 * Class WUP_Cart_Upsell
 */
class WUP_Cart_Upsell
{
    private const DEFAULT_MAX_PRODUCTS = 3;

    /**
     * Register hooks via the Loader.
     *
     * @param WUP_Loader $loader Hook registration loader.
     */
    public function register_hooks(WUP_Loader $loader): void
    {
        // Render widget after cart totals / collaterals area.
        $loader->add_action('woocommerce_cart_collaterals', $this, 'render_widget', 20);
    }

    /**
     * Gather upsell products and render the cart-upsell.php template.
     */
    public function render_widget(): void
    {
        if (! WUP_Utils::is_feature_enabled('cart_upsell')) {
            return;
        }

        $products = $this->get_upsell_products();

        if (empty($products)) {
            return;
        }

        $settings = get_option('wup_settings', []);
        $heading  = ! empty($settings['cart_upsell_heading'])
            ? (string) $settings['cart_upsell_heading']
            : __('You might also like', 'woo-upsell-pro');

        $template = WUP_PLUGIN_DIR . 'templates/cart-upsell.php';

        if (! file_exists($template)) {
            return;
        }

        include $template;
    }

    /**
     * Extract all product IDs currently in the cart (including variation parents).
     *
     * @return int[]
     */
    public function get_cart_product_ids(): array
    {
        $cart = WC()->cart;

        if (! $cart) {
            return [];
        }

        $ids = [];
        foreach ($cart->get_cart() as $item) {
            $ids[] = (int) $item['product_id'];
            if (! empty($item['variation_id'])) {
                $ids[] = (int) $item['variation_id'];
            }
        }

        return array_unique($ids);
    }

    /**
     * Get upsell product data, excluding items already in cart.
     *
     * Priority: active cart_upsell campaign products → cross-sells → same-category.
     *
     * @return array<int, array{id: int, name: string, price_html: string, image_url: string, permalink: string}>
     */
    public function get_upsell_products(): array
    {
        $in_cart = $this->get_cart_product_ids();

        // 1. Try campaign-managed products first.
        $candidates = $this->get_campaign_product_ids($in_cart);

        // 2. Fall back to cross-sells from cart items.
        if (empty($candidates)) {
            $candidates = $this->get_cross_sell_ids($in_cart);
        }

        // 3. Fall back to same-category products.
        if (empty($candidates)) {
            $candidates = $this->get_same_category_ids($in_cart);
        }

        if (empty($candidates)) {
            return [];
        }

        $results = [];
        foreach ($candidates as $product_id) {
            $data = $this->build_product_data((int) $product_id);
            if ($data !== null) {
                $results[] = $data;
            }

            if (count($results) >= $this->get_max_products()) {
                break;
            }
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Get product IDs from an active cart_upsell campaign, excluding cart items.
     *
     * @param int[] $exclude Product IDs to exclude.
     * @return int[]
     */
    private function get_campaign_product_ids(array $exclude): array
    {
        $campaigns = get_posts([
            'post_type'      => 'wup_campaign',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_wup_campaign_type',
                    'value' => 'cart_upsell',
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

        $products = get_post_meta((int) $campaigns[0], '_wup_campaign_products', true);

        if (empty($products) || ! is_array($products)) {
            return [];
        }

        return array_values(array_diff(array_map('intval', $products), $exclude));
    }

    /**
     * Collect cross-sell IDs from all cart products, excluding cart items.
     *
     * @param int[] $exclude Product IDs to exclude.
     * @return int[]
     */
    private function get_cross_sell_ids(array $exclude): array
    {
        $cart = WC()->cart;

        if (! $cart) {
            return [];
        }

        $cross_sell_ids = [];
        foreach ($cart->get_cart() as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product instanceof \WC_Product) {
                $cross_sell_ids = array_merge($cross_sell_ids, $product->get_cross_sell_ids());
            }
        }

        $cross_sell_ids = array_unique($cross_sell_ids);
        return array_values(array_diff(array_map('intval', $cross_sell_ids), $exclude));
    }

    /**
     * Find products in the same categories as cart items, sorted by total_sales.
     *
     * @param int[] $exclude Product IDs to exclude.
     * @return int[]
     */
    private function get_same_category_ids(array $exclude): array
    {
        $cart = WC()->cart;

        if (! $cart) {
            return [];
        }

        $category_ids = [];
        foreach ($cart->get_cart() as $item) {
            $terms = get_the_terms((int) $item['product_id'], 'product_cat');
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    $category_ids[] = (int) $term->term_id;
                }
            }
        }

        $category_ids = array_unique($category_ids);

        if (empty($category_ids)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $this->get_max_products() + count($exclude),
            'post__not_in'   => $exclude,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category_ids,
                ],
            ],
            'meta_key'       => 'total_sales',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        return array_map('intval', $query->posts);
    }

    private function get_max_products(): int
    {
        $max = (int) WUP_Utils::get_setting('cart_upsell_max_products', self::DEFAULT_MAX_PRODUCTS);

        return max(1, min(6, $max));
    }

    /**
     * Build normalised product data array for template rendering.
     *
     * @param int $product_id WC product ID.
     * @return array{id: int, name: string, price_html: string, image_url: string, permalink: string}|null
     */
    private function build_product_data(int $product_id): ?array
    {
        $product = wc_get_product($product_id);

        if (
            ! $product instanceof \WC_Product
            || ! $product->is_purchasable()
            || ! $product->is_in_stock()
            || ! $product->is_visible()
        ) {
            return null;
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id
            ? (string) wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail')
            : (string) wc_placeholder_img_src('woocommerce_thumbnail');

        return [
            'id'         => $product->get_id(),
            'name'       => $product->get_name(),
            'price_html' => $product->get_price_html(),
            'image_url'  => $image_url,
            'permalink'  => (string) get_permalink($product->get_id()),
        ];
    }
}
