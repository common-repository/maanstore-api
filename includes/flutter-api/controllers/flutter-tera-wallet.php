<?php
require_once __DIR__ . '/flutter-base.php';

/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package Tera Wallet
 */

class FlutterTeraWallet extends FlutterBaseController {

	/**
	 * Endpoint namespace
	 *
	 * @var string
	 */
	protected $namespace = 'maan-tera-wallet';

	/**
	 * Register all routes releated with stores
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_flutter_tera_wallet_routes' ] );
	}

	public function register_flutter_tera_wallet_routes() {
		register_rest_route(
			$this->namespace,
			'/transactions',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_transactions' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/balance',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_balance' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/transfer',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'transfer' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/check_recharge',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'check_recharge' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/process_payment',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'process_payment' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/partial_payment',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'partial_payment' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/check_email',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'check_email' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);
	}

	private function getUserInfo( $user_id, &$cachedUsers = [] ) {
		if ( ! isset( $cachedUsers[ $user_id ] ) ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$cachedUsers[ $user_id ] = [
					'id'          => $user->ID,
					'username'    => $user->user_login,
					'nicename'    => $user->user_nicename,
					'email'       => $user->user_email,
					'displayname' => $user->display_name,
					'firstname'   => $user->user_firstname,
					'lastname'    => $user->last_name,
					'nickname'    => $user->nickname,
					'description' => $user->user_description,
				];
			}
		}
		return $cachedUsers[ $user_id ];
	}

	public function get_transactions( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$cookie = $request->get_header( 'User-Cookie' );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$page   = isset( $request['page'] ) ? $request['page'] : 0;
			$length = isset( $request['length'] ) ? $request['length'] : 10;
			$page   = $page * $length;
			$args   = [
				'limit'   => "$page, $length",
				'user_id' => $user_id,
			];
			$data   = get_wallet_transactions( $args );

			$cachedUsers = [];
			foreach ( $data as &$item ) {
				$item->user       = $this->getUserInfo( $item->user_id, $cachedUsers );
				$item->created_by = $this->getUserInfo( $item->created_by, $cachedUsers );
				unset( $item->user_id );
			}

			return $data;
		} else {
			return parent::sendError( 'no_permission', 'You need to add User-Cookie in header request', 400 );
		}
	}


	public function get_balance( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$cookie = $request->get_header( 'User-Cookie' );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$data = woo_wallet()->wallet->get_wallet_balance( $user_id, 'Edit' );
			return $data;
		} else {
			return parent::sendError( 'no_permission', 'You need to add User-Cookie in header request', 400 );
		}
	}

	public function transfer( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$cookie = $request->get_header( 'User-Cookie' );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			$json   = file_get_contents( 'php://input' );
			$params = json_decode( $json, true );
			$user   = get_user_by( 'email', $params['to'] );
			if ( ! $user ) {
				return parent::sendError( 'user_not_found', 'The user is not found', 400 );
			}
			wp_set_current_user( $user_id );
			$_POST['woo_wallet_transfer_user_id'] = $user->id;
			$_POST['woo_wallet_transfer_amount']  = $params['amount'];
			$_POST['woo_wallet_transfer_note']    = sanitize_text_field( $params['note'] );
			$_POST['woo_wallet_transfer']         = wp_create_nonce( 'woo_wallet_transfer' );

			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php';
			return Woo_Wallet_Frontend::instance()->do_wallet_transfer();
		} else {
			return parent::sendError( 'no_permission', 'You need to add User-Cookie in header request', 400 );
		}
	}

	public function check_recharge( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$json                      = file_get_contents( 'php://input' );
		$params                    = json_decode( $json, true );
		$_POST['woo_wallet_topup'] = wp_create_nonce( 'woo_wallet_topup' );
		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php';
		$wallet_product = get_wallet_rechargeable_product();

		$check = Woo_Wallet_Frontend::instance()->is_valid_wallet_recharge_amount( $params['amount'] );

		if ( $check['is_valid'] == false ) {
			return $check;
		}
		$api = new WC_REST_Products_Controller();
		$req = new WP_REST_Request( 'GET' );
		$req->set_query_params( [ 'id' => $wallet_product->id ] );
		$res = $api->get_item( $req );
		if ( is_wp_error( $res ) ) {
			return $res;
		} else {
			return $res->get_data();
		}
	}

	public function process_payment( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$json   = file_get_contents( 'php://input' );
		$params = json_decode( $json, true );
		$cookie = $request->get_header( 'User-Cookie' );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			wp_set_current_user( $user_id );
			$order = wc_get_order( $params['order_id'] );
			if ( ( $order->get_total( 'edit' ) > woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) && apply_filters( 'woo_wallet_disallow_negative_transaction', ( woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) <= 0 || $order->get_total( 'edit' ) > woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ), $order->get_total( 'edit' ), woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) ) {
				$error = sprintf( __( 'Your wallet balance is low. Please add %s to proceed with this transaction.', 'woo-wallet' ), $order->get_total( 'edit' ) - woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) );
				return parent::sendError( 'wallet_error', $error, 400 );
			}
			if ( $order->get_payment_method() == 'wallet' ) {
				$wallet_response = woo_wallet()->wallet->debit( get_current_user_id(), $order->get_total( 'edit' ), apply_filters( 'woo_wallet_order_payment_description', __( 'For order payment #', 'woo-wallet' ) . $order->get_order_number(), $order ) );
				// Reduce stock levels
				$order_id = $request['id'];
				wc_reduce_stock_levels( $order_id );

				if ( $wallet_response ) {
					$order->payment_complete( $wallet_response );
					do_action( 'woo_wallet_payment_processed', $order_id, $wallet_response );
				}
			} else {
				$order->payment_complete();
			}

			// Return thankyou redirect
			return [
				'result' => 'success',
			];
		} else {
			return parent::sendError( 'no_permission', 'You need to add User-Cookie in header request', 400 );
		}
	}

	public function partial_payment( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$json   = file_get_contents( 'php://input' );
		$params = json_decode( $json, true );
		$cookie = $request->get_header( 'User-Cookie' );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$order = wc_get_order( $params['order_id'] );
			if ( $order ) {
				if ( $order->get_customer_id() == $user_id ) {
					wp_set_current_user( $user_id );
					woo_wallet()->wallet->wallet_partial_payment( $params['order_id'] );
					return [
						'result' => 'success',
					];
				} else {
					return parent::sendError( 'no_permission', 'No Permission', 400 );
				}
			} else {
				return parent::sendError( 'not_found', 'Order not found', 400 );
			}
		} else {
			return parent::sendError( 'no_permission', 'You need to add User-Cookie in header request', 400 );
		}
	}

	public function check_email( $request ) {
		if ( ! is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
			return parent::sendError( 'invalid_plugin', 'You need to install TeraWallet plugin to use this api', 404 );
		}

		$json   = file_get_contents( 'php://input' );
		$params = json_decode( $json, true );
		$cookie = $request->get_header( 'User-Cookie' );
		if ( isset( $cookie ) && $cookie != null ) {
			$user_id = validateCookieLogin( $cookie );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			$user = get_user_by( 'email', $params['email'] );
			if ( $user ) {
				$avatar = get_user_meta( $user->ID, 'user_avatar', true );
				if ( ! isset( $avatar ) || $avatar == '' || is_bool( $avatar ) ) {
					$avatar = get_avatar_url( $user->ID );
				} else {
					$avatar = $avatar[0];
				}
				return [
					'id'          => $user->ID,
					'username'    => $user->user_login,
					'nicename'    => $user->user_nicename,
					'email'       => $user->user_email,
					'url'         => $user->user_url,
					'displayname' => $user->display_name,
					'firstname'   => $user->user_firstname,
					'lastname'    => $user->last_name,
					'nickname'    => $user->nickname,
					'description' => $user->user_description,
					'avatar'      => $avatar,
				];
			} else {
				return parent::sendError( 'not_found', 'The user is not found', 400 );
			}
		}

	}
}

new FlutterTeraWallet();
