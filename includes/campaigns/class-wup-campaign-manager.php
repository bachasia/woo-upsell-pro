<?php
/**
 * Campaign CRUD and product suggestion logic.
 *
 * @package WooUpsellPro\Campaigns
 */

namespace WooUpsellPro\Campaigns;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WUP_Campaign_Manager
 */
class WUP_Campaign_Manager {

    private const META_KEYS = [
        'type'           => '_wup_campaign_type',
        'status'         => '_wup_campaign_status',
        'rules'          => '_wup_campaign_rules',
        'products'       => '_wup_campaign_products',
        'discount_tiers' => '_wup_campaign_discount_tiers',
        'settings'       => '_wup_campaign_settings',
    ];

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    /**
     * List campaigns with optional filters.
     *
     * @param array{type?: string, status?: string, per_page?: int, page?: int} $args
     * @return array{campaigns: array, total: int, pages: int}
     */
    public function get_campaigns( array $args = [] ): array {
        $per_page = absint( $args['per_page'] ?? 20 );
        $page     = absint( $args['page'] ?? 1 );

        $query_args = [
            'post_type'      => WUP_Campaign_CPT::POST_TYPE,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [],
        ];

        if ( ! empty( $args['type'] ) ) {
            $query_args['meta_query'][] = [
                'key'   => '_wup_campaign_type',
                'value' => sanitize_key( $args['type'] ),
            ];
        }

        if ( ! empty( $args['status'] ) ) {
            $query_args['meta_query'][] = [
                'key'   => '_wup_campaign_status',
                'value' => sanitize_key( $args['status'] ),
            ];
        }

        $query = new \WP_Query( $query_args );

        $campaigns = array_map(
            fn( \WP_Post $post ) => $this->format_campaign( $post ),
            $query->posts
        );

        return [
            'campaigns' => $campaigns,
            'total'     => (int) $query->found_posts,
            'pages'     => (int) $query->max_num_pages,
        ];
    }

    /**
     * Get a single campaign by post ID.
     */
    public function get_campaign( int $id ): ?array {
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== WUP_Campaign_CPT::POST_TYPE ) {
            return null;
        }

        return $this->format_campaign( $post );
    }

    /**
     * Create a new campaign.
     *
     * @param array $data Campaign fields.
     * @return int|\WP_Error Post ID on success.
     */
    public function create_campaign( array $data ): int|\WP_Error {
        $validation = $this->validate_campaign_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $post_id = wp_insert_post(
            [
                'post_title'  => sanitize_text_field( $data['title'] ?? '' ),
                'post_type'   => WUP_Campaign_CPT::POST_TYPE,
                'post_status' => 'publish',
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $this->save_campaign_meta( $post_id, $data );

        return $post_id;
    }

    /**
     * Update an existing campaign (partial update supported).
     *
     * @return bool|\WP_Error
     */
    public function update_campaign( int $id, array $data ): bool|\WP_Error {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== WUP_Campaign_CPT::POST_TYPE ) {
            return new \WP_Error( 'not_found', __( 'Campaign not found.', 'woo-upsell-pro' ), [ 'status' => 404 ] );
        }

        $validation = $this->validate_campaign_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        if ( isset( $data['title'] ) ) {
            wp_update_post( [ 'ID' => $id, 'post_title' => sanitize_text_field( $data['title'] ) ] );
        }

        $this->save_campaign_meta( $id, $data );

        return true;
    }

    /**
     * Permanently delete a campaign.
     */
    public function delete_campaign( int $id ): bool {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== WUP_Campaign_CPT::POST_TYPE ) {
            return false;
        }

        return (bool) wp_delete_post( $id, true );
    }

    /**
     * Return active campaigns filtered by type.
     *
     * @param string $type Campaign type slug.
     * @return array
     */
    public function get_active_campaigns( string $type ): array {
        $result = $this->get_campaigns( [ 'type' => $type, 'status' => 'active', 'per_page' => 100 ] );
        return $result['campaigns'];
    }

    // ------------------------------------------------------------------
    // Product suggestion algorithm
    // ------------------------------------------------------------------

    /**
     * Suggest upsell products for a campaign given current cart items.
     *
     * Priority:
     *  1. Manual products on the campaign (filtered to in-stock).
     *  2. Auto: cross-sells of cart products, then same-category products.
     *     Deduped, cart items excluded, limited to 3, sorted by popularity.
     *
     * @param int   $campaign_id Campaign post ID.
     * @param int[] $cart_item_ids Product IDs currently in the cart.
     * @return array Product data arrays.
     */
    public function get_suggested_products( int $campaign_id, array $cart_item_ids = [] ): array {
        $campaign = $this->get_campaign( $campaign_id );
        if ( ! $campaign ) {
            return [];
        }

        $manual_ids = array_map( 'absint', $campaign['products'] ?? [] );

        if ( ! empty( $manual_ids ) ) {
            return $this->build_product_data( $manual_ids, $cart_item_ids, 3 );
        }

        // Auto mode.
        $cross_sell_ids  = $this->get_cross_sell_ids( $cart_item_ids );
        $category_ids    = $this->get_category_product_ids( $cart_item_ids );

        // Cross-sells first, then category — deduped.
        $candidates = array_unique( array_merge( $cross_sell_ids, $category_ids ) );

        return $this->build_product_data( $candidates, $cart_item_ids, 3, true );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function format_campaign( \WP_Post $post ): array {
        return [
            'id'             => $post->ID,
            'title'          => $post->post_title,
            'type'           => get_post_meta( $post->ID, '_wup_campaign_type', true ) ?: 'popup',
            'status'         => get_post_meta( $post->ID, '_wup_campaign_status', true ) ?: 'draft',
            'rules'          => get_post_meta( $post->ID, '_wup_campaign_rules', true ) ?: [],
            'products'       => get_post_meta( $post->ID, '_wup_campaign_products', true ) ?: [],
            'discount_tiers' => get_post_meta( $post->ID, '_wup_campaign_discount_tiers', true ) ?: [],
            'settings'       => get_post_meta( $post->ID, '_wup_campaign_settings', true ) ?: [],
            'created_at'     => $post->post_date,
            'updated_at'     => $post->post_modified,
        ];
    }

    private function save_campaign_meta( int $post_id, array $data ): void {
        $map = [
            'type'           => [ '_wup_campaign_type',           'sanitize_key' ],
            'status'         => [ '_wup_campaign_status',         'sanitize_key' ],
            'rules'          => [ '_wup_campaign_rules',          null ],
            'products'       => [ '_wup_campaign_products',       null ],
            'discount_tiers' => [ '_wup_campaign_discount_tiers', null ],
            'settings'       => [ '_wup_campaign_settings',       null ],
        ];

        foreach ( $map as $field => [ $meta_key, $sanitizer ] ) {
            if ( ! array_key_exists( $field, $data ) ) {
                continue;
            }

            $value = $data[ $field ];

            if ( $sanitizer && is_callable( $sanitizer ) ) {
                $value = $sanitizer( $value );
            } elseif ( is_array( $value ) ) {
                $value = $this->sanitize_recursive( $value );
            } elseif ( is_string( $value ) ) {
                $value = sanitize_text_field( $value );
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    private function validate_campaign_data( array $data ): bool|\WP_Error {
        if ( isset( $data['type'] ) && ! in_array( $data['type'], WUP_Campaign_CPT::TYPES, true ) ) {
            return new \WP_Error(
                'invalid_type',
                sprintf(
                    /* translators: %s: allowed types */
                    __( 'Invalid campaign type. Allowed: %s', 'woo-upsell-pro' ),
                    implode( ', ', WUP_Campaign_CPT::TYPES )
                ),
                [ 'status' => 400 ]
            );
        }

        if ( isset( $data['status'] ) && ! in_array( $data['status'], WUP_Campaign_CPT::STATUSES, true ) ) {
            return new \WP_Error(
                'invalid_status',
                __( 'Invalid campaign status.', 'woo-upsell-pro' ),
                [ 'status' => 400 ]
            );
        }

        if ( isset( $data['discount_tiers'] ) && is_array( $data['discount_tiers'] ) ) {
            foreach ( $data['discount_tiers'] as $tier ) {
                if ( empty( $tier['qty'] ) || (int) $tier['qty'] < 1 ) {
                    return new \WP_Error( 'invalid_tier', __( 'Tier qty must be >= 1.', 'woo-upsell-pro' ), [ 'status' => 400 ] );
                }
                if ( ! isset( $tier['discount'] ) || (float) $tier['discount'] <= 0 ) {
                    return new \WP_Error( 'invalid_tier', __( 'Tier discount must be > 0.', 'woo-upsell-pro' ), [ 'status' => 400 ] );
                }
            }
        }

        return true;
    }

    /**
     * Collect cross-sell product IDs from a set of products.
     *
     * @param int[] $product_ids
     * @return int[]
     */
    private function get_cross_sell_ids( array $product_ids ): array {
        $cross_sell_ids = [];

        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( $product ) {
                $cross_sell_ids = array_merge( $cross_sell_ids, $product->get_cross_sell_ids() );
            }
        }

        return array_unique( $cross_sell_ids );
    }

    /**
     * Find products in the same categories as given product IDs.
     *
     * @param int[] $product_ids
     * @return int[]
     */
    private function get_category_product_ids( array $product_ids ): array {
        if ( empty( $product_ids ) ) {
            return [];
        }

        $category_ids = [];
        foreach ( $product_ids as $pid ) {
            $terms = get_the_terms( $pid, 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $category_ids = array_merge( $category_ids, wp_list_pluck( $terms, 'term_id' ) );
            }
        }

        if ( empty( $category_ids ) ) {
            return [];
        }

        $query = new \WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array_unique( $category_ids ),
                ],
            ],
        ] );

        return $query->posts;
    }

    /**
     * Build formatted product data, excluding cart items, limited to $limit.
     *
     * @param int[]  $candidate_ids  Proposed product IDs.
     * @param int[]  $exclude_ids    Cart product IDs to skip.
     * @param int    $limit          Max products to return.
     * @param bool   $sort_by_sales  Sort by total_sales descending.
     * @return array
     */
    private function sanitize_recursive( mixed $value ): mixed {
        if ( is_array( $value ) ) {
            $result = [];

            foreach ( $value as $key => $item ) {
                $safe_key = is_string( $key ) ? sanitize_key( $key ) : $key;
                $result[ $safe_key ] = $this->sanitize_recursive( $item );
            }

            return $result;
        }

        if ( is_string( $value ) ) {
            return sanitize_text_field( $value );
        }

        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
            return $value;
        }

        return sanitize_text_field( (string) $value );
    }

    private function build_product_data(
        array $candidate_ids,
        array $exclude_ids,
        int $limit,
        bool $sort_by_sales = false
    ): array {
        $results = [];

        foreach ( $candidate_ids as $pid ) {
            if ( in_array( $pid, $exclude_ids, true ) ) {
                continue;
            }

            $product = wc_get_product( $pid );
            if ( ! $product || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
                continue;
            }

            $results[] = [
                'id'        => $product->get_id(),
                'name'      => $product->get_name(),
                'price'     => $product->get_price(),
                'image_url' => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src(),
                'permalink' => get_permalink( $product->get_id() ),
                'sales'     => (int) get_post_meta( $product->get_id(), 'total_sales', true ),
            ];

            if ( count( $results ) >= $limit && ! $sort_by_sales ) {
                break;
            }
        }

        if ( $sort_by_sales && count( $results ) > 1 ) {
            usort( $results, static fn( $a, $b ) => $b['sales'] <=> $a['sales'] );
        }

        return array_slice( $results, 0, $limit );
    }
}
