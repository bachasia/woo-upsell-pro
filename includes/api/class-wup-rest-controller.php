<?php

namespace WooUpsellPro\Api;

use WooUpsellPro\Campaigns\WUP_Campaign_CPT;
use WooUpsellPro\Campaigns\WUP_Campaign_Manager;
use WooUpsellPro\Features\WUP_Popup;

if (! defined('ABSPATH')) {
    exit;
}

class WUP_Rest_Controller extends \WP_REST_Controller {
    private WUP_Campaign_Manager $campaign_manager;

    public function __construct(WUP_Campaign_Manager $campaign_manager) {
        $this->campaign_manager = $campaign_manager;
        $this->namespace = 'wup/v1';
        $this->rest_base = 'campaigns';
    }

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/campaigns',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_campaigns'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
                [
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'create_campaign'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/campaigns/(?P<id>\d+)',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_campaign'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
                [
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_campaign'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
                [
                    'methods' => \WP_REST_Server::DELETABLE,
                    'callback' => [$this, 'delete_campaign'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/products',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'search_products'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/products/suggest',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'suggest_product'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/settings',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_settings'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
                [
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'update_settings'],
                    'permission_callback' => [$this, 'admin_permissions_check'],
                ],
            ]
        );
    }

    public function admin_permissions_check(): bool|\WP_Error {
        if (! current_user_can('manage_woocommerce')) {
            return new \WP_Error('forbidden', __('Insufficient permissions.', 'woo-upsell-pro'), ['status' => 403]);
        }

        return true;
    }

    public function get_campaigns(\WP_REST_Request $request): \WP_REST_Response {
        $data = $this->campaign_manager->get_campaigns([
            'type' => sanitize_key((string) $request->get_param('type')),
            'status' => sanitize_key((string) $request->get_param('status')),
            'per_page' => absint((int) $request->get_param('per_page') ?: 20),
            'page' => absint((int) $request->get_param('page') ?: 1),
        ]);

        return new \WP_REST_Response($data, 200);
    }

    public function get_campaign(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $id = absint((int) $request['id']);
        $campaign = $this->campaign_manager->get_campaign($id);

        if (! $campaign) {
            return new \WP_Error('not_found', __('Campaign not found.', 'woo-upsell-pro'), ['status' => 404]);
        }

        return new \WP_REST_Response($campaign, 200);
    }

    public function create_campaign(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $payload = $this->sanitize_campaign_payload($request->get_json_params() ?: []);

        if (empty($payload['title'])) {
            return new \WP_Error('invalid_title', __('Campaign title is required.', 'woo-upsell-pro'), ['status' => 400]);
        }

        if (empty($payload['type']) || ! in_array($payload['type'], WUP_Campaign_CPT::TYPES, true)) {
            return new \WP_Error('invalid_type', __('Invalid campaign type.', 'woo-upsell-pro'), ['status' => 400]);
        }

        $created = $this->campaign_manager->create_campaign($payload);

        if (is_wp_error($created)) {
            return $created;
        }

        $campaign = $this->campaign_manager->get_campaign((int) $created);
        return new \WP_REST_Response($campaign, 201);
    }

    public function update_campaign(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $id = absint((int) $request['id']);
        $payload = $this->sanitize_campaign_payload($request->get_json_params() ?: []);

        if (isset($payload['type']) && ! in_array($payload['type'], WUP_Campaign_CPT::TYPES, true)) {
            return new \WP_Error('invalid_type', __('Invalid campaign type.', 'woo-upsell-pro'), ['status' => 400]);
        }

        $updated = $this->campaign_manager->update_campaign($id, $payload);

        if (is_wp_error($updated)) {
            return $updated;
        }

        $campaign = $this->campaign_manager->get_campaign($id);
        return new \WP_REST_Response($campaign, 200);
    }

    public function delete_campaign(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $id = absint((int) $request['id']);
        $deleted = $this->campaign_manager->delete_campaign($id);

        if (! $deleted) {
            return new \WP_Error('not_found', __('Campaign not found.', 'woo-upsell-pro'), ['status' => 404]);
        }

        return new \WP_REST_Response(['deleted' => true], 200);
    }

    public function search_products(\WP_REST_Request $request): \WP_REST_Response {
        $search = sanitize_text_field((string) $request->get_param('search'));
        $category = sanitize_text_field((string) $request->get_param('category'));
        $per_page = absint((int) $request->get_param('per_page') ?: 20);

        $args = [
            'status' => 'publish',
            'limit' => max(1, min(50, $per_page)),
            'return' => 'objects',
        ];

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
        }

        if ($category !== '') {
            $args['category'] = [$category];
        }

        $products = wc_get_products($args);
        $data = array_map(
            static function (\WC_Product $product): array {
                return [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'price_html' => $product->get_price_html(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src(),
                    'sku' => $product->get_sku(),
                ];
            },
            $products
        );

        return new \WP_REST_Response($data, 200);
    }

    public function suggest_product(\WP_REST_Request $request): \WP_REST_Response {
        $product_id = absint((int) $request->get_param('product_id'));

        if ($product_id < 1) {
            return new \WP_REST_Response(null, 200);
        }

        $popup = new WUP_Popup();
        $suggested = $popup->get_upsell_for_product($product_id);

        return new \WP_REST_Response($suggested ?: null, 200);
    }

    public function get_settings(): \WP_REST_Response {
        $settings = get_option('wup_settings', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        return new \WP_REST_Response($settings, 200);
    }

    public function update_settings(\WP_REST_Request $request): \WP_REST_Response {
        $payload = $request->get_json_params() ?: [];
        $sanitized = $this->sanitize_settings_payload($payload);

        update_option('wup_settings', $sanitized, false);

        return new \WP_REST_Response($sanitized, 200);
    }

    private function sanitize_campaign_payload(array $payload): array {
        $sanitized = [];

        if (array_key_exists('title', $payload)) {
            $sanitized['title'] = sanitize_text_field((string) $payload['title']);
        }

        if (array_key_exists('type', $payload)) {
            $sanitized['type'] = sanitize_key((string) $payload['type']);
        }

        if (array_key_exists('status', $payload)) {
            $sanitized['status'] = sanitize_key((string) $payload['status']);
        }

        if (array_key_exists('rules', $payload) && is_array($payload['rules'])) {
            $sanitized['rules'] = $this->sanitize_recursive($payload['rules']);
        }

        if (array_key_exists('products', $payload) && is_array($payload['products'])) {
            $sanitized['products'] = array_values(array_filter(array_map('absint', $payload['products'])));
        }

        if (array_key_exists('discount_tiers', $payload) && is_array($payload['discount_tiers'])) {
            $tiers = [];

            foreach ($payload['discount_tiers'] as $tier) {
                if (! is_array($tier)) {
                    continue;
                }

                $qty = absint((int) ($tier['qty'] ?? 0));
                $discount = (float) ($tier['discount'] ?? 0);
                $type = sanitize_key((string) ($tier['type'] ?? 'percent'));

                if ($qty < 1 || $discount <= 0 || ! in_array($type, ['percent', 'fixed'], true)) {
                    continue;
                }

                $tiers[] = [
                    'qty' => $qty,
                    'discount' => $discount,
                    'type' => $type,
                ];
            }

            $sanitized['discount_tiers'] = $tiers;
        }

        if (array_key_exists('settings', $payload) && is_array($payload['settings'])) {
            $sanitized['settings'] = $this->sanitize_recursive($payload['settings']);
        }

        return $sanitized;
    }

    private function sanitize_settings_payload(array $payload): array {
        $defaults = [
            'enable_popup' => true,
            'enable_cart_upsell' => true,
            'enable_bmsm' => true,
            'enable_email_coupon' => true,
            'popup_auto_dismiss' => 5,
            'popup_heading' => __('Customers also bought', 'woo-upsell-pro'),
            'cart_upsell_heading' => __('You might also like', 'woo-upsell-pro'),
            'cart_upsell_max_products' => 3,
            'bmsm_tiers' => [],
            'email_coupon' => [
                'enabled' => true,
                'discount_type' => 'percent',
                'discount_amount' => 10,
                'expiry_days' => 30,
                'min_order_amount' => 0,
                'email_heading' => __('Thank you! Here\'s a gift.', 'woo-upsell-pro'),
            ],
        ];

        $merged = array_replace_recursive($defaults, is_array($payload) ? $payload : []);

        $merged['enable_popup'] = (bool) $merged['enable_popup'];
        $merged['enable_cart_upsell'] = (bool) $merged['enable_cart_upsell'];
        $merged['enable_bmsm'] = (bool) $merged['enable_bmsm'];
        $merged['enable_email_coupon'] = (bool) $merged['enable_email_coupon'];
        $merged['popup_auto_dismiss'] = max(0, absint((int) $merged['popup_auto_dismiss']));
        $merged['popup_heading'] = sanitize_text_field((string) $merged['popup_heading']);
        $merged['cart_upsell_heading'] = sanitize_text_field((string) $merged['cart_upsell_heading']);
        $merged['cart_upsell_max_products'] = max(1, min(6, absint((int) $merged['cart_upsell_max_products'])));

        $tiers = [];
        if (is_array($merged['bmsm_tiers'])) {
            foreach ($merged['bmsm_tiers'] as $tier) {
                if (! is_array($tier)) {
                    continue;
                }

                $qty = absint((int) ($tier['qty'] ?? 0));
                $discount = (float) ($tier['discount'] ?? 0);
                $type = sanitize_key((string) ($tier['type'] ?? 'percent'));

                if ($qty < 1 || $discount <= 0 || ! in_array($type, ['percent', 'fixed'], true)) {
                    continue;
                }

                $tiers[] = ['qty' => $qty, 'discount' => $discount, 'type' => $type];
            }
        }
        $merged['bmsm_tiers'] = $tiers;

        if (! is_array($merged['email_coupon'])) {
            $merged['email_coupon'] = $defaults['email_coupon'];
        }

        $merged['email_coupon']['enabled'] = (bool) ($merged['email_coupon']['enabled'] ?? true);
        $merged['email_coupon']['discount_type'] = in_array(($merged['email_coupon']['discount_type'] ?? 'percent'), ['percent', 'fixed_cart'], true)
            ? $merged['email_coupon']['discount_type']
            : 'percent';
        $merged['email_coupon']['discount_amount'] = max(0, (float) ($merged['email_coupon']['discount_amount'] ?? 10));
        $merged['email_coupon']['expiry_days'] = max(1, absint((int) ($merged['email_coupon']['expiry_days'] ?? 30)));
        $merged['email_coupon']['min_order_amount'] = max(0, (float) ($merged['email_coupon']['min_order_amount'] ?? 0));
        $merged['email_coupon']['email_heading'] = sanitize_text_field((string) ($merged['email_coupon']['email_heading'] ?? ''));

        return $merged;
    }

    private function sanitize_recursive(mixed $value): mixed {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $safe_key = is_string($key) ? sanitize_key($key) : $key;
                $result[$safe_key] = $this->sanitize_recursive($item);
            }

            return $result;
        }

        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return sanitize_text_field((string) $value);
    }
}
