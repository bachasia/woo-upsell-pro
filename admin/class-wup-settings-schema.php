<?php
/**
 * WUP_Settings_Schema — Full settings field definitions.
 *
 * Used as a trait by WUP_Settings_Page.
 * Each field: id, name, type, default, tab, desc?, options?, css?
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WUP_Settings_Schema {

	/** @return array<int,array> Full schema for all tabs. */
	public function get_schema(): array {
		return array_merge(
			$this->schema_bundle(),
			$this->schema_popup(),
			$this->schema_sidecart(),
			$this->schema_bmsm(),
			$this->schema_cart(),
			$this->schema_coupons(),
			$this->schema_announcement(),
			$this->schema_sales_popup(),
			$this->schema_advanced()
		);
	}

	// ── FBT Bundle ──────────────────────────────────────────────────────────────

	private function schema_bundle(): array {
		return [
			[ 'id' => 'wup_upsell_bundle_enable',               'name' => 'Enable FBT Bundle',         'type' => 'checkbox', 'default' => 'no',      'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_upsell_bundle_position',             'name' => 'Position',                   'type' => 'select',   'default' => 'below_add_to_cart', 'tab' => 'wup-bundle',
				'options' => [ 'below_add_to_cart' => 'Below Add Cart Form', 'below_images' => 'Below Product Images', 'below_summary' => 'Below Product Summary', 'inside_summary' => 'Inside Product Summary', 'shortcode_only' => 'Shortcode Only' ] ],
			[ 'id' => 'wup_upsell_bundle_priority',             'name' => 'Position Priority',         'type' => 'number',   'default' => 30,         'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_upsell_bundle_heading',              'name' => 'Section Heading',           'type' => 'text',     'default' => 'Frequently Bought Together', 'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_upsell_bundle_layout',               'name' => 'Layout',                    'type' => 'select',   'default' => '1',        'tab' => 'wup-bundle',
				'options' => [ '1' => 'Layout 1', '2' => 'Layout 2', '3' => 'Layout 3', '4' => 'Layout 4' ] ],
			[ 'id' => 'wup_upsell_bundle_source',               'name' => 'Product Source',            'type' => 'select',   'default' => 'related',  'tab' => 'wup-bundle',
				'options' => [ 'related' => 'Related', 'cross_sell' => 'Cross-sells', 'upsell' => 'Upsells', 'categories' => 'By Category', 'tags' => 'By Tag' ] ],
			[ 'id' => 'wup_upsell_bundle_limit',                'name' => 'Max Related Products',     'type' => 'number',   'default' => 2,          'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_upsell_bundle_categories',           'name' => 'Source Categories',        'type' => 'text',     'default' => '',         'tab' => 'wup-bundle', 'desc' => 'Comma-separated category IDs' ],
			[ 'id' => 'wup_upsell_bundle_prefix',               'name' => 'Bundle Label Prefix',      'type' => 'text',     'default' => '[FBT]',    'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_upsell_bundle_hide_options_when',    'name' => 'Show Select Options When', 'type' => 'select',   'default' => '2',        'tab' => 'wup-bundle',
				'options' => [ '2' => 'Products with at least 2 options or more', '3' => 'Products with at least 3 options or more', '4' => 'Products with at least 4 options or more', '5' => 'Products with at least 5 options or more', '6' => 'Products with at least 6 options or more' ] ],
			[ 'id' => 'wup_upsell_bundle_add_action_label',     'name' => 'Button Label',             'type' => 'text',     'default' => 'Add All To Cart', 'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_upsell_bundle_discount_amount',      'name' => 'Bundle Discount %',        'type' => 'number',   'default' => 0,          'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_fbt_badges_enable',                  'name' => 'Enable FBT Badges',        'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_fbt_badges_text',                    'name' => 'Badge Text',               'type' => 'text',     'default' => 'Bundle & Save', 'tab' => 'wup-bundle' ],
			[ 'id' => 'wup_fbt_badges_bgcolor',                 'name' => 'Badge Background',         'type' => 'color',    'default' => '#ff5722',  'tab' => 'wup-bundle',
				'css' => ':root|--wup-fbt-badge-bg-color' ],
		];
	}

	// ── Post-ATC Popup ───────────────────────────────────────────────────────────

	private function schema_popup(): array {
		return [
			[ 'id' => 'wup_upsell_popup_enable',                'name' => 'Enable Popup',             'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-popup' ],
			[ 'id' => 'wup_upsell_popup_source',                'name' => 'Product Source',           'type' => 'select',   'default' => 'related',   'tab' => 'wup-popup',
				'options' => [ 'related' => 'Related', 'cross_sell' => 'Cross-sells', 'upsell' => 'Upsells', 'categories' => 'By Category' ] ],
			[ 'id' => 'wup_upsell_popup_categories',            'name' => 'Source Categories',       'type' => 'text',     'default' => '',          'tab' => 'wup-popup' ],
			[ 'id' => 'wup_upsell_popup_limit',                 'name' => 'Max Products',            'type' => 'number',   'default' => 3,           'tab' => 'wup-popup' ],
			[ 'id' => 'wup_upsell_popup_heading_text',          'name' => 'Popup Heading',           'type' => 'text',     'default' => 'You might also like…', 'tab' => 'wup-popup' ],
			[ 'id' => 'wup_upsell_popup_add_action_label',      'name' => 'Add Button Label',        'type' => 'text',     'default' => 'Add To Cart', 'tab' => 'wup-popup' ],
			[ 'id' => 'wup_upsell_image_variants',              'name' => 'Show Image Variants',     'type' => 'checkbox', 'default' => 'no',        'tab' => 'wup-popup' ],
			[ 'id' => 'wup_upsell_add_action_color',            'name' => 'Add Button Color',        'type' => 'color',    'default' => '#333333',   'tab' => 'wup-popup',
				'css' => [ '.wup-popup-add-btn|background-color', '.wup-cs-atc|background-color' ] ],
			[ 'id' => 'wup_upsell_add_action_label_color',      'name' => 'Add Button Text Color',   'type' => 'color',    'default' => '#ffffff',   'tab' => 'wup-popup',
				'css' => [ '.wup-popup-add-btn|color', '.wup-cs-atc|color' ] ],
			[ 'id' => 'wup_upsell_checkout_button_color',       'name' => 'Checkout Button Color',   'type' => 'color',    'default' => '#333333',   'tab' => 'wup-popup',
				'css' => '.wup-popup-footer .button.checkout|background-color' ],
			[ 'id' => 'wup_upsell_checkout_button_text_color',  'name' => 'Checkout Button Text',    'type' => 'color',    'default' => '#ffffff',   'tab' => 'wup-popup',
				'css' => '.wup-popup-footer .button.checkout|color' ],
			[ 'id' => 'wup_upsell_viewcart_button_color',       'name' => 'View Cart Button Color',  'type' => 'color',    'default' => '#555555',   'tab' => 'wup-popup',
				'css' => '.wup-popup-footer .button.wc-forward|background-color' ],
			[ 'id' => 'wup_upsell_viewcart_button_text_color',  'name' => 'View Cart Button Text',   'type' => 'color',    'default' => '#ffffff',   'tab' => 'wup-popup',
				'css' => '.wup-popup-footer .button.wc-forward|color' ],
		];
	}

	// ── Side Cart ────────────────────────────────────────────────────────────────

	private function schema_sidecart(): array {
		return [
			[ 'id' => 'wup_upsell_sidecart_enable',             'name' => 'Enable Side Cart',        'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_upsell_sidecart_open_selector',      'name' => 'Open Trigger Selector',   'type' => 'text',     'default' => '.cart-contents', 'tab' => 'wup-sidecart', 'desc' => 'CSS selector that opens the side cart' ],
			[ 'id' => 'wup_upsell_sidecart_checkout_label',     'name' => 'Checkout Button Label',   'type' => 'text',     'default' => 'Checkout', 'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_upsell_sidecart_primary_color',      'name' => 'Primary Color',           'type' => 'color',    'default' => '#333333',  'tab' => 'wup-sidecart',
				'css' => ':root|--wup-sc-color-primary' ],
			[ 'id' => 'wup_upsell_sidecart_icon_enable',        'name' => 'Enable Cart Icon',        'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_upsell_sidecart_icon_bgcolor',       'name' => 'Icon Background',         'type' => 'color',    'default' => '#333333',  'tab' => 'wup-sidecart',
				'css' => '.wup-cart-icon|background-color' ],
			[ 'id' => 'wup_upsell_sidecart_icon_color',         'name' => 'Icon Color',              'type' => 'color',    'default' => '#ffffff',  'tab' => 'wup-sidecart',
				'css' => '.wup-cart-icon|color' ],
			[ 'id' => 'wup_upsell_sidecart_icon_position',      'name' => 'Icon Position',           'type' => 'select',   'default' => 'bottom_right', 'tab' => 'wup-sidecart',
				'options' => [ 'bottom_right' => 'Bottom Right', 'bottom_left' => 'Bottom Left', 'top_right' => 'Top Right', 'top_left' => 'Top Left' ] ],
			[ 'id' => 'wup_sidecart_fsg_enable',                'name' => 'Free Shipping Bar',       'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_sidecart_fsg_type',                  'name' => 'Shipping Goal Type',      'type' => 'select',   'default' => 'amount',   'tab' => 'wup-sidecart',
				'options' => [ 'amount' => 'Subtotal Amount', 'count' => 'Item Count' ] ],
			[ 'id' => 'wup_sidecart_fsg_amount',                'name' => 'Shipping Goal Value',     'type' => 'number',   'default' => 50,         'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_sidecart_fsg_msg_progress',          'name' => 'Progress Message',        'type' => 'text',     'default' => 'Add [amount] more for free shipping!', 'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_sidecart_fsg_msg_success',           'name' => 'Success Message',         'type' => 'text',     'default' => '🎉 You\'ve unlocked free shipping!', 'tab' => 'wup-sidecart' ],
			[ 'id' => 'wup_sidecart_fsg_color',                 'name' => 'Progress Bar Color',      'type' => 'color',    'default' => '#4caf50',  'tab' => 'wup-sidecart',
				'css' => '.wup-fsg-progress > span|background-color' ],
			[ 'id' => 'wup_sidecart_fsg_bg_color',              'name' => 'Progress Bar Background', 'type' => 'color',    'default' => '#e0e0e0',  'tab' => 'wup-sidecart',
				'css' => '.wup-fsg-progress|background' ],
		];
	}

	// ── BMSM ────────────────────────────────────────────────────────────────────

	private function schema_bmsm(): array {
		return [
			[ 'id' => 'wup_bmsm_enable',                        'name' => 'Enable BMSM',             'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_conditional',                   'name' => 'Tier Type',               'type' => 'select',   'default' => 'items',    'tab' => 'wup-bmsm',
				'options' => [ 'items' => 'By Item Count', 'amounts' => 'By Subtotal' ] ],
			[ 'id' => 'wup_buy_more_by_items',                  'name' => 'Tiers (by items)',        'type' => 'textarea', 'default' => '[{"min":2,"discount":5},{"min":3,"discount":10}]', 'tab' => 'wup-bmsm', 'desc' => 'JSON: [{min,discount}]' ],
			[ 'id' => 'wup_buy_more_by_amounts',                'name' => 'Tiers (by subtotal)',     'type' => 'textarea', 'default' => '[{"min":50,"discount":5},{"min":100,"discount":10}]', 'tab' => 'wup-bmsm', 'desc' => 'JSON: [{min,discount}]' ],
			[ 'id' => 'wup_bmsm_position',                      'name' => 'Position Hook',           'type' => 'select',   'default' => 'woocommerce_after_add_to_cart_form', 'tab' => 'wup-bmsm',
				'options' => [
					'woocommerce_after_add_to_cart_form'      => 'Below Add Cart Form',
					'woocommerce_after_single_product_thumbnail' => 'Below Product Images',
					'woocommerce_single_product_summary'      => 'Inside Product Summary',
					'woocommerce_after_single_product_summary'=> 'Below Product Summary',
					'shortcode_only'                          => 'Shortcode Only',
				] ],
			[ 'id' => 'wup_bmsm_priority',                      'name' => 'Position Priority',      'type' => 'number',   'default' => 35,         'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_style',                         'name' => 'Display Style',           'type' => 'select',   'default' => 'style1',   'tab' => 'wup-bmsm',
				'options' => [
					'style1' => 'Table (Default)',
					'style2' => 'Badge Row',
					'style3' => 'Flash Card',
					'style4' => 'Card Grid',
					'style5' => 'Radio List',
				] ],
			[ 'id' => 'wup_bmsm_heading',                       'name' => 'Heading Text',            'type' => 'text',     'default' => 'Buy More Save More', 'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_subtitle',                      'name' => 'Subtitle Text',           'type' => 'text',     'default' => '',         'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_categories',                    'name' => 'Eligible Categories',     'type' => 'text',     'default' => '',         'tab' => 'wup-bmsm', 'desc' => 'Comma-separated IDs (empty = all)' ],
			[ 'id' => 'wup_bmsm_add_cart_button',               'name' => 'Show "Buy X" Button',    'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_add_action_label',              'name' => 'Button Label',            'type' => 'text',     'default' => 'Buy {quantity}', 'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_congrats_items',                'name' => 'Congrats (items)',        'type' => 'text',     'default' => '🎉 You unlocked [discount_amount]% discount!', 'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_congrats_subtotal',             'name' => 'Congrats (subtotal)',     'type' => 'text',     'default' => '🎉 You unlocked [discount_amount]% discount!', 'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_remain_items',                  'name' => 'Remain (items)',          'type' => 'text',     'default' => 'Add [remain] more to save [discount_amount]%!', 'tab' => 'wup-bmsm' ],
			[ 'id' => 'wup_bmsm_remain_subtotal',               'name' => 'Remain (subtotal)',       'type' => 'text',     'default' => 'Spend [remain] more to save [discount_amount]%!', 'tab' => 'wup-bmsm' ],
		];
	}

	// ── Cart / Thank-you / Related ───────────────────────────────────────────────

	private function schema_cart(): array {
		return [
			[ 'id' => 'wup_cart_upsell_enable',                 'name' => 'Enable Cart Upsell',      'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-cart' ],
			[ 'id' => 'wup_cart_upsell_source',                 'name' => 'Cart Source',              'type' => 'select',   'default' => 'related',   'tab' => 'wup-cart',
				'options' => [ 'related' => 'Related', 'cross_sell' => 'Cross-sells', 'categories' => 'By Category' ] ],
			[ 'id' => 'wup_cart_upsell_categories',             'name' => 'Cart Categories',          'type' => 'text',     'default' => '',          'tab' => 'wup-cart' ],
			[ 'id' => 'wup_cart_upsell_limit',                  'name' => 'Cart Max Products',        'type' => 'number',   'default' => 4,           'tab' => 'wup-cart' ],
			[ 'id' => 'wup_cart_upsell_heading',                'name' => 'Cart Heading',             'type' => 'text',     'default' => 'You might also like', 'tab' => 'wup-cart' ],
			[ 'id' => 'wup_thankyou_upsell_enable',             'name' => 'Enable Thank-you Upsell',  'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-cart' ],
			[ 'id' => 'wup_thankyou_upsell_source',             'name' => 'Thank-you Source',         'type' => 'select',   'default' => 'related',   'tab' => 'wup-cart',
				'options' => [ 'related' => 'Related', 'cross_sell' => 'Cross-sells', 'categories' => 'By Category' ] ],
			[ 'id' => 'wup_thankyou_upsell_limit',              'name' => 'Thank-you Max Products',   'type' => 'number',   'default' => 4,           'tab' => 'wup-cart' ],
			[ 'id' => 'wup_thankyou_upsell_heading',            'name' => 'Thank-you Heading',        'type' => 'text',     'default' => 'You might also like', 'tab' => 'wup-cart' ],
			[ 'id' => 'wup_related_enable',                     'name' => 'Enable Related Products',  'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-cart' ],
			[ 'id' => 'wup_related_source',                     'name' => 'Related Source',           'type' => 'select',   'default' => 'related',   'tab' => 'wup-cart',
				'options' => [ 'related' => 'Related', 'upsell' => 'Upsells', 'categories' => 'By Category' ] ],
			[ 'id' => 'wup_related_limit',                      'name' => 'Related Max Products',     'type' => 'number',   'default' => 4,           'tab' => 'wup-cart' ],
			[ 'id' => 'wup_related_position',                   'name' => 'Related Position Hook',    'type' => 'select',   'default' => 'woocommerce_after_single_product', 'tab' => 'wup-cart',
				'options' => [ 'woocommerce_after_single_product' => 'After Product', 'woocommerce_after_single_product_summary' => 'After Summary' ] ],
			[ 'id' => 'wup_related_priority',                   'name' => 'Related Priority',         'type' => 'number',   'default' => 20,          'tab' => 'wup-cart' ],
		];
	}

	// ── Coupons ──────────────────────────────────────────────────────────────────

	private function schema_coupons(): array {
		return [
			[ 'id' => 'wup_coupon_enable',                      'name' => 'Enable Email Coupon',     'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-coupon' ],
			[ 'id' => 'wup_coupon_amount',                      'name' => 'Discount %',              'type' => 'number',   'default' => 15,         'tab' => 'wup-coupon' ],
			[ 'id' => 'wup_coupon_code',                        'name' => 'Fixed Code (optional)',   'type' => 'text',     'default' => '',         'tab' => 'wup-coupon', 'desc' => 'Leave empty to auto-generate' ],
			[ 'id' => 'wup_coupon_email_subject',               'name' => 'Email Subject',           'type' => 'text',     'default' => 'Congrats! You unlocked special discount on {{site.name}}!', 'tab' => 'wup-coupon' ],
			[ 'id' => 'wup_coupon_email_content',               'name' => 'Email Body',              'type' => 'textarea', 'default' => '',         'tab' => 'wup-coupon', 'desc' => 'Tokens: {{customer.name}}, {{site.name}}, {{discount.amount}}, {{discount.code}}' ],
			[ 'id' => 'wup_advanced_coupons_one',               'name' => 'One Coupon per Customer', 'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-coupon' ],
		];
	}

	// ── Announcements ────────────────────────────────────────────────────────────

	private function schema_announcement(): array {
		return [
			[ 'id' => 'wup_upsell_announcement_topbar',         'name' => 'Enable Topbar',           'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_topbar_text',    'name' => 'Topbar Text',             'type' => 'text',     'default' => '',         'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_topbar_bgcolor', 'name' => 'Topbar Background',       'type' => 'color',    'default' => '#333333',  'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_topbar_color',   'name' => 'Topbar Text Color',       'type' => 'color',    'default' => '#ffffff',  'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_topbar_fontsize','name' => 'Topbar Font Size',        'type' => 'text',     'default' => '0.9em',    'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_product',        'name' => 'Enable Product Bar',      'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_product_text',   'name' => 'Product Bar Text',        'type' => 'text',     'default' => '',         'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_product_bgcolor','name' => 'Product Bar Background',  'type' => 'color',    'default' => '#f5f5f5',  'tab' => 'wup-announcement' ],
			[ 'id' => 'wup_upsell_announcement_product_color',  'name' => 'Product Bar Text Color',  'type' => 'color',    'default' => '#333333',  'tab' => 'wup-announcement' ],
		];
	}

	// ── Sales Popup ──────────────────────────────────────────────────────────────

	private function schema_sales_popup(): array {
		return [
			[ 'id' => 'wup_popup_enable',                       'name' => 'Enable Sales Popup',      'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-sales-popup' ],
			[ 'id' => 'wup_popup_template',                     'name' => 'Template',                'type' => 'select',   'default' => 'modern',   'tab' => 'wup-sales-popup',
				'options' => [ 'modern' => 'Modern', 'minimal' => 'Minimal', 'dark' => 'Dark' ] ],
			[ 'id' => 'wup_popup_position',                     'name' => 'Desktop Position',        'type' => 'select',   'default' => 'bottom_left', 'tab' => 'wup-sales-popup',
				'options' => [ 'bottom_left' => 'Bottom Left', 'bottom_right' => 'Bottom Right', 'top_left' => 'Top Left', 'top_right' => 'Top Right' ] ],
			[ 'id' => 'wup_popup_mobile',                       'name' => 'Mobile Position',         'type' => 'select',   'default' => 'mobile-bottom_center', 'tab' => 'wup-sales-popup',
				'options' => [ 'mobile-bottom_center' => 'Bottom Center', 'mobile-top_center' => 'Top Center', 'mobile-hidden' => 'Hidden' ] ],
			[ 'id' => 'wup_popup_source',                       'name' => 'Product Source',          'type' => 'select',   'default' => 'smart_random', 'tab' => 'wup-sales-popup',
				'options' => [ 'smart_random' => 'Smart Random', 'smart_selected' => 'Selected Products' ] ],
			[ 'id' => 'wup_popup_products',                     'name' => 'Selected Products',       'type' => 'text',     'default' => '',         'tab' => 'wup-sales-popup', 'desc' => 'Comma-separated product IDs' ],
			[ 'id' => 'wup_popup_pages',                        'name' => 'Show On Pages',           'type' => 'select',   'default' => 'all',      'tab' => 'wup-sales-popup',
				'options' => [ 'all' => 'All Pages', 'home_only' => 'Home Only', 'product_cart' => 'Product & Cart' ] ],
			[ 'id' => 'wup_popup_loop_time',                    'name' => 'Loop Interval (sec)',     'type' => 'number',   'default' => 5,          'tab' => 'wup-sales-popup' ],
			[ 'id' => 'wup_popup_display_time',                 'name' => 'Display Duration (sec)',  'type' => 'number',   'default' => 4,          'tab' => 'wup-sales-popup' ],
			[ 'id' => 'wup_popup_msg_template',                 'name' => 'Message Template',        'type' => 'text',     'default' => '{{name}} from {{city}} just bought {{product}} {{time}} ago', 'tab' => 'wup-sales-popup' ],
			[ 'id' => 'wup_popup_names',                        'name' => 'Customer Names',          'type' => 'textarea', 'default' => "Emma\nLiam\nOlivia\nNoah\nAva", 'tab' => 'wup-sales-popup', 'desc' => 'One per line' ],
			[ 'id' => 'wup_popup_cities',                       'name' => 'Cities',                  'type' => 'textarea', 'default' => "New York\nLos Angeles\nChicago\nHouston\nPhoenix", 'tab' => 'wup-sales-popup', 'desc' => 'One per line' ],
		];
	}

	// ── Advanced ─────────────────────────────────────────────────────────────────

	private function schema_advanced(): array {
		return [
			[ 'id' => 'wup_fomo_stock_enable',                  'name' => 'Enable FOMO Stock',       'type' => 'checkbox', 'default' => 'no',       'tab' => 'wup-advanced' ],
			[ 'id' => 'wup_fomo_stock_msg',                     'name' => 'Stock Message',           'type' => 'text',     'default' => 'Only [stock] stock left!', 'tab' => 'wup-advanced' ],
			[ 'id' => 'wup_fomo_stock_min',                     'name' => 'Stock Min (lower bound)', 'type' => 'number',   'default' => 5,          'tab' => 'wup-advanced' ],
			[ 'id' => 'wup_fomo_stock_max',                     'name' => 'Stock Max (upper bound)', 'type' => 'number',   'default' => 10,         'tab' => 'wup-advanced' ],
			[ 'id' => 'wup_fomo_stock_color',                   'name' => 'Stock Notice Color',      'type' => 'color',    'default' => '#ff9900',  'tab' => 'wup-advanced',
				'css' => '.wup-fomo-stock|color' ],
		];
	}
}
