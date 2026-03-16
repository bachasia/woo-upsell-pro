<?php
/**
 * WUP_Email_Coupon — Auto-generate WC coupon on order and email it to customer.
 *
 * Hooks: woocommerce_thankyou (priority 10)
 * Guard: _wup_coupon_sent order meta prevents duplicate sends.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Email_Coupon' ) ) {

	class WUP_Email_Coupon {

		/** @var array<string,mixed> */
		private array $options;

		/** @var WUP_Email_Coupon|null */
		private static ?WUP_Email_Coupon $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->options = [
				'enable'         => wup_get_option( 'wup_coupon_enable', 'no' ),
				'amount'         => wup_get_option( 'wup_coupon_amount', 15 ),
				'code'           => wup_get_option( 'wup_coupon_code', '' ),
				'email_subject'  => wup_get_option( 'wup_coupon_email_subject', 'Congrats! You unlocked special discount on {{site.name}}!' ),
				'email_content'  => wup_get_option( 'wup_coupon_email_content', $this->default_email_content() ),
				'one_per_user'   => wup_get_option( 'wup_advanced_coupons_one', 'no' ),
			];

			if ( 'yes' === $this->options['enable'] ) {
				add_action( 'woocommerce_thankyou', [ $this, 'on_order_complete' ], 10, 1 );
			}
		}

		/** Hook: fire once per order. */
		public function on_order_complete( int $order_id ): void {
			if ( $this->already_sent( $order_id ) ) {
				return;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			$code = $this->generate_coupon( $order_id );
			if ( ! $code ) {
				return;
			}

			$this->send_email( $order, $code );
		}

		/** Generate WC coupon and return the code. Returns empty string on failure. */
		private function generate_coupon( int $order_id ): string {
			$fixed_code = sanitize_title( $this->options['code'] );

			if ( $fixed_code && $this->coupon_exists( $fixed_code ) ) {
				return $fixed_code;
			}

			$code = $fixed_code ?: ( 'wup-' . strtolower( wp_generate_password( 8, false ) ) );

			// Avoid duplicate code collision on auto-generate.
			while ( ! $fixed_code && $this->coupon_exists( $code ) ) {
				$code = 'wup-' . strtolower( wp_generate_password( 8, false ) );
			}

			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'percent' );
			$coupon->set_amount( (float) $this->options['amount'] );
			$coupon->set_usage_limit( 1 );

			if ( 'yes' === $this->options['one_per_user'] ) {
				$coupon->set_usage_limit_per_user( 1 );
			}

			$coupon->save();

			return $code;
		}

		/** Check whether a WC coupon post with this code already exists. */
		private function coupon_exists( string $code ): bool {
			return (bool) wc_get_coupon_id_by_code( $code );
		}

		/** Build HTML email, replace tokens, fire wp_mail. */
		private function send_email( WC_Order $order, string $code ): void {
			$billing_email = $order->get_billing_email();
			if ( ! $billing_email ) {
				return;
			}

			$tokens = [
				'{{site.name}}'       => get_bloginfo( 'name' ),
				'{{customer.name}}'   => $order->get_billing_first_name(),
				'{{discount.amount}}' => (string) $this->options['amount'],
				'{{discount.code}}'   => strtoupper( $code ),
			];

			$subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $this->options['email_subject'] );
			$body    = $this->render_email_body( $tokens );

			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

			$sent = wp_mail( $billing_email, $subject, $body, $headers );

			if ( $sent ) {
				$order->update_meta_data( '_wup_coupon_sent', 1 );
				$order->update_meta_data( '_wup_coupon_code', strtoupper( $code ) );
				$order->save();
			}
		}

		/** Replace tokens in email body and wrap in basic HTML. */
		private function render_email_body( array $tokens ): string {
			$raw  = str_replace( array_keys( $tokens ), array_values( $tokens ), $this->options['email_content'] );
			$html = nl2br( wp_kses_post( $raw ) );

			ob_start();
			include WUP_TEMPLATES_DIR . 'email/coupon.php';
			$output = ob_get_clean();

			if ( $output ) {
				return str_replace( '{{email_body}}', $html, $output );
			}

			// Fallback: inline HTML.
			return '<html><body style="font-family:sans-serif;max-width:600px;margin:auto;padding:24px;">'
				. $html . '</body></html>';
		}

		/** Returns true if coupon email was already sent for this order. */
		private function already_sent( int $order_id ): bool {
			return (bool) get_post_meta( $order_id, '_wup_coupon_sent', true );
		}

		private function default_email_content(): string {
			return "Hi {{customer.name}},\n\nThank you for your order on our site {{site.name}}.\n\n"
				. "You just unlocked {{discount.amount}}% discount. Use code below to received {{discount.amount}}% OFF on next orders.\n\n"
				. "Code: {{discount.code}}\n\n"
				. "If you have any questions or concerns, please contact us by reply this email.\n\nHappy a nice day!";
		}
	}
}
