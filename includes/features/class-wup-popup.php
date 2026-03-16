<?php
/**
 * Add-to-Cart Popup feature.
 *
 * Renders popup container in wp_footer.
 * Provides upsell product suggestion helper used by the REST endpoint.
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
 * Class WUP_Popup
 */
class WUP_Popup
{
    /**
     * Register hooks via the Loader.
     *
     * @param WUP_Loader $loader Hook registration loader.
     */
    public function register_hooks(WUP_Loader $loader): void
    {
        // Inject popup markup into page footer.
        $loader->add_action('wp_footer', $this, 'render_popup_container', 100);
    }

    /**
     * Output the popup.php template in the page footer.
     * Template is always present in DOM; JS controls visibility.
     */
    public function render_popup_container(): void
    {
        if (! WUP_Utils::is_feature_enabled('popup')) {
            return;
        }

        $template = WUP_PLUGIN_DIR . 'templates/popup.php';

        if (! file_exists($template)) {
            return;
        }

        include $template;
    }

    /**
     * Get a single upsell product suggestion for a given product.
     *
     * Delegates to campaign manager when available; falls back to
     * WooCommerce upsell / cross-sell products from the source product.
     *
     * @param int $product_id Source WC product ID.
     * @return array{id: int, name: string, price: string, price_html: string, image_url: string, permalink: string, add_to_cart_url: string}|null
     */
    public function get_upsell_for_product(int $product_id): ?array
    {
        // Try campaign-managed suggestions first.
        $campaign_suggestions = $this->get_campaign_suggestions($product_id);
        if (! empty($campaign_suggestions)) {
            return $campaign_suggestions[0];
        }

        // Fall back to WC-native upsells / cross-sells.
        $source = wc_get_product($product_id);
        if (! $source instanceof \WC_Product) {
            return null;
        }

        $candidate_ids = array_merge(
            $source->get_upsell_ids(),
            $source->get_cross_sell_ids()
        );

        // Exclude the source product itself.
        $candidate_ids = array_filter(
            $candidate_ids,
            static fn (int $id) => $id !== $product_id
        );

        foreach ($candidate_ids as $candidate_id) {
            $product_data = $this->build_product_data((int) $candidate_id);
            if ($product_data !== null) {
                return $product_data;
            }
        }

        return null;
    }

    /**
     * Prepare localized data for popup.js.
     *
     * @param int $product_id Product just added to cart.
     * @return array<string, mixed>
     */
    public function prepare_popup_data(int $product_id): array
    {
        return [
            'rest_url'            => esc_url_raw(rest_url('wup/v1/')),
            'nonce'               => wp_create_nonce('wp_rest'),
            'store_api_nonce'     => wp_create_nonce('wc_store_api'),
            'auto_dismiss_seconds' => 5,
            'product_id'          => $product_id,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Get popup upsell suggestions from an active popup campaign.
     *
     * @param int $product_id Source product ID.
     * @return array<int, array<string, mixed>>
     */
    private function get_campaign_suggestions(int $product_id): array
    {
        $campaigns = get_posts([
            'post_type'      => 'wup_campaign',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_wup_campaign_type',
                    'value' => 'popup',
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

        $campaign_id       = (int) $campaigns[0];
        $campaign_products = get_post_meta($campaign_id, '_wup_campaign_products', true);

        if (empty($campaign_products) || ! is_array($campaign_products)) {
            return [];
        }

        $results = [];
        foreach ($campaign_products as $cp_id) {
            if ((int) $cp_id === $product_id) {
                continue;
            }

            $data = $this->build_product_data((int) $cp_id);
            if ($data !== null) {
                $results[] = $data;
            }

            if (count($results) >= 1) {
                break;
            }
        }

        return $results;
    }

    /**
     * Build a normalised product data array from a WC product ID.
     *
     * @param int $product_id WC product ID.
     * @return array{id: int, name: string, price: string, price_html: string, image_url: string, permalink: string, add_to_cart_url: string}|null
     */
    private function build_product_data(int $product_id): ?array
    {
        $product = wc_get_product($product_id);

        if (! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
            return null;
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id
            ? (string) wp_get_attachment_image_url($image_id, 'thumbnail')
            : (string) wc_placeholder_img_src('thumbnail');

        return [
            'id'             => $product->get_id(),
            'name'           => $product->get_name(),
            'price'          => (string) $product->get_price(),
            'price_html'     => $product->get_price_html(),
            'image_url'      => $image_url,
            'permalink'      => (string) get_permalink($product->get_id()),
            'add_to_cart_url' => (string) $product->add_to_cart_url(),
        ];
    }
}
