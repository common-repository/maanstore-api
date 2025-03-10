<?php

class CUSTOM_WC_REST_Orders_Controller extends WC_REST_Orders_Controller {


	/**
	 * Endpoint namespace
	 *
	 * @var string
	 */
	protected $namespace = 'maan-order';

	/**
	 * Register all routes releated with stores
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_flutter_woo_routes' ] );
	}

	public function register_flutter_woo_routes() {
		register_rest_route(
			$this->namespace,
			'/create',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_new_order' ],
					'permission_callback' => [ $this, 'custom_create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		//some reasons can't use PUT method
		register_rest_route(
			$this->namespace,
			'/update' . '/(?P<id>[\d]+)',
			[
				'args'   => [
					'id' => [
						'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'custom_create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/update' . '/(?P<id>[\d]+)',
			[
				'args'   => [
					'id' => [
						'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'custom_create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		//some reasons can't use DELETE method
		register_rest_route(
			$this->namespace,
			'/delete' . '/(?P<id>[\d]+)',
			[
				'args'   => [
					'id' => [
						'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'new_delete_pending_order' ],
					'permission_callback' => [ $this, 'custom_delete_item_permissions_check' ],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	function custom_create_item_permissions_check( $request ) {
		$cookie = $request->get_header( 'User-Cookie' );
		$json   = file_get_contents( 'php://input' );
		$params = json_decode( $json, true );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return false;
			}
			$params['customer_id'] = $user_id;
			wp_set_current_user( $user_id );
			$request->set_body_params( $params );
			return true;
		} else {
			$params['customer_id'] = 0;
			$request->set_body_params( $params );
			return true;
		}
	}

	function custom_delete_item_permissions_check( $request ) {
		$cookie = $request->get_header( 'User-Cookie' );
		$json   = file_get_contents( 'php://input' );
		$params = json_decode( $json, true );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return false;
			}
			$order = wc_get_order( $request['id'] );
			return $order->get_customer_id() == 0 || $order->get_customer_id() == $user_id;
		} else {
			return false;
		}
	}

	function create_new_order( $request ) {
		$params = $request->get_body_params();
		if ( isset( $params['fee_lines'] ) && count( $params['fee_lines'] ) > 0 ) {
			$fee_name = $params['fee_lines'][0]['name'];
			if ( $fee_name == 'Via Wallet' ) {
				if ( is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
					$balance = woo_wallet()->wallet->get_wallet_balance( $params['customer_id'], 'Edit' );
					$total   = $params['fee_lines'][0]['total'];
					if ( floatval( $balance ) < floatval( $total ) * ( -1 ) ) {
						return new WP_Error( 'invalid_wallet', 'The wallet is not enough to checkout', [ 'status' => 400 ] );
					}
				}
			}
		}
		if ( isset( $params['payment_method'] ) && $params['payment_method'] == 'wallet' && isset( $params['total'] ) ) {
			if ( is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
				$balance = woo_wallet()->wallet->get_wallet_balance( $params['customer_id'], 'Edit' );
				if ( floatval( $balance ) < floatval( $params['total'] ) ) {
					return new WP_Error( 'invalid_wallet', 'The wallet is not enough to checkout', [ 'status' => 400 ] );
				}
			}
		}

		$response = $this->create_item( $request );
		$data     = $response->get_data();

		// Send the customer invoice email.
		$order = wc_get_order( $data['id'] );
		if ( $order->get_payment_method() == 'cod' || $order->has_status( [ 'processing', 'completed' ] ) ) {
			WC()->payment_gateways();
			WC()->shipping();
			WC()->mailer()->customer_invoice( $order );
			WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order->get_id(), $order, true );
			add_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
			WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order->get_id(), $order, true );
		}

		//add order note if payment method is tap
		if ( isset( $params['payment_method'] ) && $params['payment_method'] == 'tap' && isset( $params['transaction_id'] ) ) {
			$order->payment_complete();
			$order->add_order_note( 'Tap payment successful.<br/>Tap ID: ' . $params['transaction_id'] );
		}

		return $response;
	}

	function new_delete_pending_order( $request ) {
		add_filter( 'woocommerce_rest_check_permissions', '__return_true' );
		$response = $this->delete_item( $request );
		remove_filter( 'woocommerce_rest_check_permissions', '__return_true' );
		return $response;
	}
}

new CUSTOM_WC_REST_Orders_Controller();
