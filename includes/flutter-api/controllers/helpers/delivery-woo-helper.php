<?php
class DeliveryWooHelper extends FlutterBaseController {

	public function sendError( $code, $message, $statusCode ) {
		return new WP_Error(
			$code,
			$message,
			array(
				'status' => $statusCode,
			)
		);
	}

	protected function find_image_id( $image ) {
		$image_id = attachment_url_to_postid( stripslashes( $image ) );
		return $image_id;
	}

	protected function http_check( $url ) {
		if ( ( ! ( substr( $url, 0, 7 ) == 'http://' ) ) && ( ! ( substr( $url, 0, 8 ) == 'https://' ) ) ) {
			return false;
		}
		return true;
	}


	/// GET FUNCTIONS
	public function get_delivery_profile( $user_id ) {
		$data['first_name'] = get_user_meta( $user_id, 'billing_first_name', true );
		$data['last_name']  = get_user_meta( $user_id, 'billing_last_name', true );
		$data['phone']      = get_user_meta( $user_id, 'billing_phone', true );

		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => $data,
			),
			200
		);
	}

	public function update_vendor_profile( $request, $user_id ) {
		$data        = json_decode( $request, true );
		$vendor_data = get_user_meta( $user_id, 'wcfmmp_profile_settings', true );
		if ( is_string( $vendor_data ) ) {
			$vendor_data = array();
		}
	}


	public function get_delivery_stat( $user_id ) {
		$delivered_count = 0;
		$pending_count   = 0;
		$total           = 0;

		if ( is_plugin_active( 'delivery-drivers-for-woocommerce/delivery-drivers-for-woocommerce.php' ) ) {
			global $wpdb;
			$table_1 = "{$wpdb->prefix}posts";
			$table_2 = "{$wpdb->prefix}postmeta";
			$sql     = "SELECT ID FROM {$table_1} INNER JOIN {$table_2} ON {$table_1}.ID = {$table_2}.post_id";
			$sql    .= " WHERE `{$table_2}`.`meta_key` = 'ddwc_driver_id' AND `{$table_2}`.`meta_value` = {$user_id}";
			$sql    .= " AND `{$table_1}`.`post_type` = 'shop_order'";

			$total         = count( $wpdb->get_results( $sql ) );
			$pending_sql   = $sql . " AND (`{$table_1}`.`post_status` = 'wc-driver-assigned' OR `{$table_1}`.`post_status` = 'wc-out-for-delivery' OR `{$table_1}`.`post_status` = 'wc-processing')";
			$delivered_sql = $sql . " AND `{$table_1}`.`post_status` = 'wc-completed'";
			$cancelled_sql = $sql . " AND `{$table_1}`.`post_status` = 'wc-cancelled'";

			$pending_count   = count( $wpdb->get_results( $pending_sql ) );
			$delivered_count = count( $wpdb->get_results( $delivered_sql ) );
			$cancelled_count = count( $wpdb->get_results( $cancelled_sql ) );
		}

		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => array(
					'delivered' => $delivered_count,
					'pending'   => $pending_count,
					'cancelled' => $cancelled_count,
					'total'     => $total,
				),
			),
			200
		);
	}

	public function get_delivery_order( $user_id, $request ) {
		$api = new WC_REST_Orders_V1_Controller();

		$order_id = $request['id'];
		if ( isset( $order_id ) ) {
			$order_id = sanitize_text_field( $order_id );
			if ( is_numeric( $order_id ) ) {
				if ( is_plugin_active( 'delivery-drivers-for-woocommerce/delivery-drivers-for-woocommerce.php' ) ) {
					$order = wc_get_order( $order_id );
					return new WP_REST_Response(
						array(
							'status'   => 'success',
							'response' => $order,
						),
						200
					);
				}
			}
		}
		return sendError( 'invalid_parameters', 'Invalid parameters', 400 );
	}


	public function get_delivery_stores( $user_id, $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcfm_delivery_orders';
		$sql        = "SELECT $table_name.`vendor_id` FROM `{$table_name}`";
		$sql       .= ' WHERE 1=1';
		$sql       .= " AND delivery_boy = {$user_id}";
		$sql       .= ' AND is_trashed = 0';
		$sql       .= " AND delivery_status = 'pending'";
		$sql       .= " GROUP BY $table_name.`vendor_id`";
		$items      = $wpdb->get_results( $sql );

		$vendor = new FlutterWCFMHelper();
		$stores = array();
		foreach ( $items as $item ) {
			$vendor_data = $vendor->flutter_get_wcfm_stores_by_id( $item->vendor_id );
			$stores[]    = $vendor_data->data;
		}
		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => $stores,
			),
			200
		);

	}

	public function get_delivery_orders( $user_id, $request ) {
		$api     = new WC_REST_Orders_V1_Controller();
		$results = array();
		if ( is_plugin_active( 'delivery-drivers-for-woocommerce/delivery-drivers-for-woocommerce.php' ) ) {
			$page     = 1;
			$per_page = 10;

			if ( isset( $request['page'] ) ) {
				$page = sanitize_text_field( $request['page'] );
				if ( ! is_numeric( $page ) ) {
					$page = 1;
				}
			}

			if ( isset( $request['per_page'] ) ) {
				$per_page = sanitize_text_field( $request['per_page'] );
				if ( ! is_numeric( $per_page ) ) {
					$per_page = 10;
				}
			}

			$page = ( $page - 1 ) * $per_page;
			global $wpdb;

			$table_1 = "{$wpdb->prefix}posts";
			$table_2 = "{$wpdb->prefix}postmeta";
			$sql     = "SELECT ID FROM {$table_1} INNER JOIN {$table_2} ON {$table_1}.ID = {$table_2}.post_id";
			$sql    .= " WHERE `{$table_2}`.`meta_key` = 'ddwc_driver_id' AND `{$table_2}`.`meta_value` = {$user_id}";

			if ( isset( $request['status'] ) && ! empty( $request['status'] ) ) {
				$status = sanitize_text_field( $request['status'] );

				if ( $status == 'assigned' ) {
					$sql .= " AND `{$table_1}`.`post_status` = 'wc-driver-assigned'";
				}

				if ( $status == 'out-for-delivery' ) {
					$sql .= " AND `{$table_1}`.`post_status` = 'wc-out-for-delivery'";
				}

				if ( $status == 'returned' ) {
					$sql .= " AND `{$table_1}`.`post_status` = 'wc-order-returned'";
				}

				if ( $status == 'delivered' ) {
					$sql .= " AND `{$table_1}`.`post_status` = 'wc-completed'";
				}

				if ( $status == 'cancelled' ) {
					$sql .= " AND `{$table_1}`.`post_status` = 'wc-cancelled'";
				}

				// order-returned, driver-assigned, completed, out-for-delivery, cancelled
			} else {
				$sql .= " AND (`{$table_1}`.`post_status` = 'wc-driver-assigned' OR `{$table_1}`.`post_status` = 'wc-out-for-delivery' OR `{$table_1}`.`post_status` = 'wc-completed' OR `{$table_1}`.`post_status` = 'wc-processing')";
			}
			if ( isset( $request['search'] ) ) {
				$order_search = sanitize_text_field( $request['search'] );
				$sql         .= " AND $table_1.`ID` LIKE '%{$order_search}%'";
			}

			$sql .= " AND `{$table_1}`.`post_type` = 'shop_order'";
			$sql .= " GROUP BY $table_1.`ID` ORDER BY $table_1.`ID` DESC LIMIT $per_page OFFSET $page";

			$items = $wpdb->get_results( $sql );

			foreach ( $items as $item ) {
				$order = wc_get_order( $item );
				if ( is_bool( $order ) ) {
					continue;
				}
				$response               = $api->prepare_item_for_response( $order, $request );
				$order                  = $response->get_data();
				$count                  = count( $order['line_items'] );
				$order['product_count'] = $count;
				for ( $i = 0; $i < $count; $i++ ) {
					$product_id = absint( $order['line_items'][ $i ]['product_id'] );
					$image      = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ) );
					if ( ! is_null( $image[0] ) ) {
						$order['line_items'][ $i ]['featured_image'] = $image[0];
					}
				}
				$order['delivery_status'] = 'delivered';
				if ( $order['status'] != 'completed' ) {
					$order['delivery_status'] = 'pending';
				}
				$results[] = $order;
			}
		}
		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => $results,
			),
			200
		);
	}


	function get_notification( $request, $user_id ) {
		global $WCFM, $wpdb;
		// include upgrade-functions for maybe_create_table;
		if ( ! function_exists( 'maybe_create_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$table_name = $wpdb->prefix . 'delivery_woo_notification';
		$sql        = 'CREATE TABLE ' . $table_name . '(
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                message text NOT NULL,
                order_id text NOT NULL,
                delivery_boy text NOT NULL,
                created datetime NOT NULL,
                UNIQUE KEY id (id)
                );';
		maybe_create_table( $table_name, $sql );
		$messages = array();
		if ( isset( $request['per_page'] ) && $request['per_page'] ) {
			$limit  = $request['per_page'];
			$offset = $request['page'];
			if ( isset( $offset ) ) {
				$offset = sanitize_text_field( $offset );
				if ( ! is_numeric( $offset ) ) {
					$offset = 1;
				}
			}
			if ( isset( $limit ) ) {
				$limit = sanitize_text_field( $limit );
				if ( ! is_numeric( $limit ) ) {
					$limit = 10;
				}
			}
			$offset   = ( $offset - 1 ) * $limit;
			$sql      = "SELECT * FROM $table_name WHERE `{$table_name}`.`delivery_boy` = $user_id";
			$sql     .= " ORDER BY `{$table_name}`.`id` DESC";
			$sql     .= " LIMIT $limit";
			$sql     .= " OFFSET $offset";
			$messages = $wpdb->get_results( $sql );
		}

		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => $messages,
			),
			200
		);
	}

	/**
	 * Update delivery profile information.
	 *
	 * @param http_request $request
	 * @param int $user_id
	 * @return response rest response or error.
	 */
	function update_delivery_profile( $request, $user_id ) {
		$is_pw_correct = true;
		$pass          = sanitize_text_field( $request['password'] );
		$new_pass      = sanitize_text_field( $request['new_password'] );
		$first_name    = sanitize_text_field( $request['first_name'] );
		$last_name     = sanitize_text_field( $request['last_name'] );
		$phone         = sanitize_text_field( $request['phone'] );
		$data          = array( 'ID' => $user_id );
		if ( isset( $params->display_name ) ) {
			$user_update['first_name'] = $params->first_name;
		}
		if ( isset( $params->display_name ) ) {
			$user_update['last_name'] = $params->last_name;
		}

		if ( isset( $first_name ) ) {
			$data['first_name'] = $first_name;
			update_user_meta( $user_id, 'billing_first_name', $first_name, '' );
			wp_update_user(
				array(
					'ID'         => $user_id,
					'first_name' => $first_name,
				)
			);
		}
		if ( isset( $last_name ) ) {
			$data['last_name'] = $last_name;
			update_user_meta( $user_id, 'billing_last_name', $last_name, '' );
			wp_update_user(
				array(
					'ID'        => $user_id,
					'last_name' => $last_name,
				)
			);
		}
		if ( isset( $phone ) ) {
			update_user_meta( $user_id, 'billing_phone', $phone, '' );
		}
		if ( ! empty( $data ) ) {
			wp_update_user( $data, $user_id );
		}
		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => 1,
			),
			200
		);
	}

	public function update_delivery_order( $order_id, $delivery_status ) {
		$status         = 'driver-assigned'; //Statuses are : driver-assigned, out-for-delivery, order-returned,completed
		$allowed_status = array( 'driver-assigned', 'out-for-delivery', 'order-returned', 'completed', 'cancelled' );

		if ( ! empty( $delivery_status ) && in_array( $delivery_status, $allowed_status ) ) {
			$status = $delivery_status;
		} else {
			return new WP_Error( 'invalid_delivery_status', 'Proper delivery status required', array( 'status' => 400 ) );
		}

		if ( $status != 'cancelled' ) {
			$order = wc_update_order(
				array(
					'order_id' => $order_id,
					'status'   => $status,
				)
			);
		} else {
			$order = wc_update_order(
				array(
					'order_id' => $order_id,
					'status'   => 'processing',
				)
			);

			$driver_id = get_post_meta( $order_id, 'ddwc_driver_id', true );

			if ( $driver_id ) {
				$driver_name = get_the_author_meta( 'display_name', $driver_id );
				$order_note  = 'Delivery Driver: ' . $driver_name . ' has cancelled this delivery request';

				$current_order = wc_get_order( $order_id );
				$current_order->add_order_note( $order_note );

				update_post_meta( $order_id, 'ddwc_driver_id', -1 );
			}
		}

		if ( is_wp_error( $order ) ) {
			return new WP_REST_Response(
				array(
					'status'   => 'success',
					'response' => -1,
					'message'  => $order,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'status'   => 'success',
				'response' => 1,
			),
			200
		);
	}


	function set_off_time( $user_id, $is_available ) {
		if ( is_plugin_active( 'delivery-drivers-for-woocommerce/delivery-drivers-for-woocommerce.php' ) ) {
			$new_value = 'on';
			$old_value = '';
			if ( $is_available !== 'true' ) {
				$new_value = '';
				$old_value = 'on';
			}
			// Update driver availability.
			update_user_meta( $user_id, 'ddwc_driver_availability', $new_value, $old_value );
			$meta_value = get_user_meta( $user_id, 'ddwc_driver_availability', true );
			return new WP_REST_Response(
				array(
					'status'   => 'success',
					'response' => $meta_value,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'status'   => 'unknown-error',
				'response' => '',
			),
			400
		);
	}

	function set_driver_note( $note, $order_id ) {

		$current_order = wc_get_order( $order_id );
		$driver_note   = 'Driver Note: ' . $note;

		if ( $current_order ) {
			$order_note_id = $current_order->add_order_note( $driver_note );

			$current_order->save();

			return new WP_REST_Response(
				array(
					'status'   => 'success',
					'response' => array(
						'note_id' => $order_note_id,
					),
				),
			);
		}

		return new WP_REST_Response(
			array(
				'status'   => 'Order not found . ',
				'response' => '',
			),
			400
		);
	}

	public function get_all_drivers_notes( $order_id ) {
		$current_order = wc_get_order( $order_id );

		if ( $current_order ) {
			$args = array(
				'post_id' => $order_id,
				'orderby' => 'comment_ID',
				'order'   => 'DESC',
				'approve' => 'approve',
				'type'    => 'order_note',
			);

			remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

			$notes = get_comments( $args );

			add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

			return new WP_REST_Response(
				array(
					'status'   => 'success',
					'response' => $notes,
				),
				200
			);
		}

			return new WP_REST_Response(
				array(
					'status'   => 'Order not found . ',
					'response' => '',
				),
				400
			);

	}
}

