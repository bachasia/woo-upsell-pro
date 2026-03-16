<?php
/**
 * WC Settings Page Extension
 *
 * Adds an "Upsell Pro" tab to WooCommerce > Settings with sections
 * for each feature. Feature toggles are stored as individual wp_options
 * via the standard WC settings API.
 *
 * @package WooUpsellPro\Admin
 */

namespace WooUpsellPro\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WUP_Settings_Page
 *
 * Extends WC_Settings_Page for a native WC settings tab experience.
 */
class WUP_Settings_Page extends \WC_Settings_Page {

    /**
     * Constructor — set tab ID and label, register with WC.
     */
    public function __construct() {
        $this->id    = 'wup';
        $this->label = __('Upsell Pro', 'woo-upsell-pro');

        parent::__construct();
    }

    /**
     * Return all sections for this tab.
     *
     * @return array<string, string> Section ID => label.
     */
    public function get_sections(): array {
        return apply_filters(
            'wup_settings_sections',
            [
                ''            => __('General', 'woo-upsell-pro'),
                'popup'       => __('Add-to-Cart Popup', 'woo-upsell-pro'),
                'cart_upsell' => __('Cart Upsell Widget', 'woo-upsell-pro'),
                'bmsm'        => __('Buy More Save More', 'woo-upsell-pro'),
                'email_coupon' => __('Post-Purchase Email', 'woo-upsell-pro'),
            ]
        );
    }

    /**
     * Return settings fields for the current section.
     *
     * @return array WC settings field definitions.
     */
    public function get_settings(): array {
        $section = $this->get_current_section();

        return apply_filters(
            "wup_settings_{$section}",
            $this->get_settings_for_section($section)
        );
    }

    /**
     * Return field definitions for a given section ID.
     *
     * @param string $section Section ID.
     * @return array WC settings fields.
     */
    private function get_settings_for_section(string $section): array {
        switch ($section) {
            case 'popup':
                return $this->popup_fields();
            case 'cart_upsell':
                return $this->cart_upsell_fields();
            case 'bmsm':
                return $this->bmsm_fields();
            case 'email_coupon':
                return $this->email_coupon_fields();
            default:
                return $this->general_fields();
        }
    }

    /**
     * General section — feature enable/disable toggles.
     *
     * @return array
     */
    private function general_fields(): array {
        return [
            [
                'title' => __('Feature Toggles', 'woo-upsell-pro'),
                'type'  => 'title',
                'id'    => 'wup_general_section_start',
            ],
            [
                'title'   => __('Enable Add-to-Cart Popup', 'woo-upsell-pro'),
                'type'    => 'checkbox',
                'id'      => 'wup_enable_popup',
                'default' => 'no',
            ],
            [
                'title'   => __('Enable Cart Upsell Widget', 'woo-upsell-pro'),
                'type'    => 'checkbox',
                'id'      => 'wup_enable_cart_upsell',
                'default' => 'no',
            ],
            [
                'title'   => __('Enable Buy More Save More', 'woo-upsell-pro'),
                'type'    => 'checkbox',
                'id'      => 'wup_enable_bmsm',
                'default' => 'no',
            ],
            [
                'title'   => __('Enable Post-Purchase Coupon Email', 'woo-upsell-pro'),
                'type'    => 'checkbox',
                'id'      => 'wup_enable_email_coupon',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wup_general_section_end',
            ],
        ];
    }

    /**
     * Popup section fields.
     *
     * @return array
     */
    private function popup_fields(): array {
        return [
            [
                'title' => __('Add-to-Cart Popup Settings', 'woo-upsell-pro'),
                'type'  => 'title',
                'id'    => 'wup_popup_section_start',
            ],
            [
                'title'             => __('Popup Heading', 'woo-upsell-pro'),
                'type'              => 'text',
                'id'                => 'wup_popup_heading',
                'default'           => __('Customers also bought', 'woo-upsell-pro'),
                'desc_tip'          => true,
                'description'       => __('Heading displayed at top of popup.', 'woo-upsell-pro'),
            ],
            [
                'title'             => __('Auto-Dismiss (seconds)', 'woo-upsell-pro'),
                'type'              => 'number',
                'id'                => 'wup_popup_auto_dismiss',
                'default'           => '0',
                'custom_attributes' => ['min' => '0', 'step' => '1'],
                'desc_tip'          => true,
                'description'       => __('Set to 0 to disable auto-dismiss.', 'woo-upsell-pro'),
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wup_popup_section_end',
            ],
        ];
    }

    /**
     * Cart upsell widget section fields.
     *
     * @return array
     */
    private function cart_upsell_fields(): array {
        return [
            [
                'title' => __('Cart Upsell Widget Settings', 'woo-upsell-pro'),
                'type'  => 'title',
                'id'    => 'wup_cart_upsell_section_start',
            ],
            [
                'title'       => __('Widget Heading', 'woo-upsell-pro'),
                'type'        => 'text',
                'id'          => 'wup_cart_upsell_heading',
                'default'     => __('You might also like', 'woo-upsell-pro'),
                'desc_tip'    => true,
                'description' => __('Heading shown above the cart upsell products.', 'woo-upsell-pro'),
            ],
            [
                'title'             => __('Max Products to Show', 'woo-upsell-pro'),
                'type'              => 'number',
                'id'                => 'wup_cart_upsell_max_products',
                'default'           => '3',
                'custom_attributes' => ['min' => '1', 'max' => '6', 'step' => '1'],
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wup_cart_upsell_section_end',
            ],
        ];
    }

    /**
     * Buy More Save More section fields.
     *
     * @return array
     */
    private function bmsm_fields(): array {
        return [
            [
                'title' => __('Buy More Save More Settings', 'woo-upsell-pro'),
                'type'  => 'title',
                'id'    => 'wup_bmsm_section_start',
                'desc'  => __('Default discount tiers. These can be overridden per-campaign.', 'woo-upsell-pro'),
            ],
            [
                'title'   => __('Allow Per-Product Override', 'woo-upsell-pro'),
                'type'    => 'checkbox',
                'id'      => 'wup_bmsm_per_product_override',
                'default' => 'no',
            ],
            [
                'title'       => __('Promotional Banner Text', 'woo-upsell-pro'),
                'type'        => 'text',
                'id'          => 'wup_bmsm_banner_text',
                'default'     => __('Buy more, save more!', 'woo-upsell-pro'),
                'desc_tip'    => true,
                'description' => __('Text shown above the cart table for BMSM promotions.', 'woo-upsell-pro'),
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wup_bmsm_section_end',
            ],
        ];
    }

    /**
     * Email coupon section fields.
     *
     * @return array
     */
    private function email_coupon_fields(): array {
        return [
            [
                'title' => __('Post-Purchase Coupon Email Settings', 'woo-upsell-pro'),
                'type'  => 'title',
                'id'    => 'wup_email_coupon_section_start',
            ],
            [
                'title'   => __('Discount Type', 'woo-upsell-pro'),
                'type'    => 'select',
                'id'      => 'wup_email_coupon_discount_type',
                'default' => 'percent',
                'options' => [
                    'percent'    => __('Percentage discount', 'woo-upsell-pro'),
                    'fixed_cart' => __('Fixed cart discount', 'woo-upsell-pro'),
                ],
            ],
            [
                'title'             => __('Discount Amount', 'woo-upsell-pro'),
                'type'              => 'number',
                'id'                => 'wup_email_coupon_discount_amount',
                'default'           => '10',
                'custom_attributes' => ['min' => '0', 'step' => '0.01'],
                'desc_tip'          => true,
                'description'       => __('Enter % or fixed amount depending on discount type.', 'woo-upsell-pro'),
            ],
            [
                'title'             => __('Coupon Expiry (days)', 'woo-upsell-pro'),
                'type'              => 'number',
                'id'                => 'wup_email_coupon_expiry_days',
                'default'           => '30',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
            ],
            [
                'title'             => __('Minimum Order Amount', 'woo-upsell-pro'),
                'type'              => 'number',
                'id'                => 'wup_email_coupon_min_order',
                'default'           => '0',
                'custom_attributes' => ['min' => '0', 'step' => '0.01'],
                'desc_tip'          => true,
                'description'       => __('Set to 0 for no minimum.', 'woo-upsell-pro'),
            ],
            [
                'title'       => __('Email Heading', 'woo-upsell-pro'),
                'type'        => 'text',
                'id'          => 'wup_email_coupon_heading',
                'default'     => __("Thank you! Here's a gift.", 'woo-upsell-pro'),
                'desc_tip'    => true,
                'description' => __('Heading shown at top of the coupon email.', 'woo-upsell-pro'),
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wup_email_coupon_section_end',
            ],
        ];
    }

    /**
     * Get the current section from query string.
     *
     * @return string
     */
    private function get_current_section(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['section']) ? sanitize_key($_GET['section']) : '';
    }
}
