<?php
require_once __DIR__ . '/flutter-base.php';

/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package Midtrans
 */

class FlutterMidtrans extends FlutterBaseController {

	/**
	 * Endpoint namespace
	 *
	 * @var string
	 */
	protected $namespace = 'maan-midtrans';

	/**
	 * Register all routes releated with stores
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_flutter_midtrans_routes' ] );
	}

	public function register_flutter_midtrans_routes() {
		register_rest_route(
			$this->namespace,
			'/generate_snap_token',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'generate_snap_token' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/payment_success',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'payment_success' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

	}

	public function generate_snap_token( $request ) {
		if ( ! is_plugin_active( 'midtrans-woocommerce/midtrans-gateway.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install Midtrans WooCommerce Payment Gateway plugin to use this api', 404 );
		}
		$json = file_get_contents( 'php://input' );
		$body = json_decode( $json, true );

		$params = [
			'transaction_details' => [
				'order_id'     => sanitize_text_field( $body['order_id'] ),
				'gross_amount' => sanitize_text_field( $body['amount'] ),
			],
		];
		require_once ABSPATH . 'wp-content/plugins/midtrans-woocommerce/midtrans-gateway.php';
		$order        = wc_get_order( sanitize_text_field( $body['order_id'] ) );
		$snapResponse = WC_Midtrans_API::createSnapTransactionHandleDuplicate( $order, $params, 'midtrans' );
		return $snapResponse;
	}

	public function payment_success( $request ) {
		$json = file_get_contents( 'php://input' );
		$body = json_decode( $json, true );

		$order = wc_get_order( sanitize_text_field( $body['order_id'] ) );
		$order->payment_complete();
		$order->add_order_note( 'Midtrans payment successful.<br/>Transaction ID: ' . sanitize_text_field( $body['transaction_id'] ) );
		return true;
	}
}

new FlutterMidtrans();
