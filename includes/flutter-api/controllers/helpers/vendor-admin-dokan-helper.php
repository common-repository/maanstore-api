<?php

class VendorAdminDokanHelper {

	public function sendError( $code, $message, $statusCode ) {
		return new WP_Error(
			$code,
			$message,
			[
				'status' => $statusCode,
			]
		);
	}

	protected function get_product_item( $id ) {
		if ( ! wc_get_product( $id ) ) {
			return $this->sendError( 'invalid_product', 'This product does not exist', 404 );
		}
		return wc_get_product( $id );
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

	protected function get_attribute_taxonomy_name( $slug, $product ) {
		$attributes = $product->get_attributes();

		if ( ! isset( $attributes[ $slug ] ) ) {
			return str_replace( 'pa_', '', $slug );
		}

		$attribute = $attributes[ $slug ];

		// Taxonomy attribute name.
		if ( $attribute->is_taxonomy() ) {
			$taxonomy = $attribute->get_taxonomy_object();
			return $taxonomy->attribute_label;
		}

		// Custom product attribute name.
		return $attribute->get_name();
	}

	protected function get_attribute_options( $product_id, $attribute ) {
		if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
			return wc_get_product_terms(
				$product_id,
				$attribute['name'],
				[
					'fields' => 'names',
				]
			);
		} elseif ( isset( $attribute['value'] ) ) {
			return array_map( 'trim', explode( '|', $attribute['value'] ) );
		}

		return [];
	}

	protected function get_attribute_slugs( $product_id, $attribute ) {
		if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
			return wc_get_product_terms(
				$product_id,
				$attribute['name'],
				[
					'fields' => 'slugs',
				]
			);
		} elseif ( isset( $attribute['value'] ) ) {
			return array_map( 'trim', explode( '|', $attribute['value'] ) );
		}

		return [];
	}


	   /// GET FUNCTIONS
	public function get_vendor_profile( $user_id ) {
		$vendor_data = get_user_meta( $user_id, 'dokan_profile_settings', true );
		if ( is_string( $vendor_data ) ) {
			$vendor_data = [];
		}
		$user                       = get_userdata( $user_id );
		$vendor_data['store_email'] = $user->user_email;
		$vendor_data['logo']        = wp_get_attachment_image_src(
			$vendor_data['gravatar']
		)[0];
		$vendor_data['banner']      = wp_get_attachment_image_src(
			$vendor_data['banner']
		)[0];
		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => $vendor_data,
			],
			200
		);
	}

	public function update_vendor_profile( $request, $user_id ) {
		$data        = json_decode( $request, true );
		$vendor_data = get_user_meta( $user_id, 'dokan_profile_settings', true );
		if ( is_string( $vendor_data ) ) {
			$vendor_data = [];
		}

		$store_name     = sanitize_text_field( $data['store_name'] );
		$store_nicename = sanitize_text_field( $data['store_slug'] );
		$store_location = sanitize_text_field( $data['store_location'] );
		$store_lat      = sanitize_text_field( $data['store_lat'] );
		$store_lng      = sanitize_text_field( $data['store_lng'] );
		$phone          = sanitize_text_field( $data['phone'] );
		$store_email    = sanitize_text_field( $data['store_email'] );

		if ( ! empty( $store_name ) ) {
			 $vendor_data['store_name'] = $store_name;
		}
		if ( ! empty( $phone ) ) {
			 $vendor_data['phone'] = $phone;
		}
		if ( ! empty( $data['address'] ) ) {
			 $vendor_data['address'] = $data['address'];
		}
		if ( ! empty( $store_lat ) && ! empty( $store_lng ) ) {
			 $vendor_data['location'] = $store_lat . ',' . $store_lng;
		}
		if ( ! empty( $store_location ) ) {
			 $vendor_data['find_address'] = $store_location;
		}
		if ( ! empty( $store_email ) ) {
			 $args = [
				 'ID'         => $user_id,
				 'user_email' => esc_attr( $store_email ),
			 ];
			 wp_update_user( $args );
		}

		$count = 0;

		if ( isset( $data['store_time'] ) ) {
			 $vendor_data['dokan_store_time'] = $data['store_time'];
		}
		if ( isset( $data['dokan_store_open_notice'] ) ) {
			 $vendor_data['dokan_store_open_notice'] = $data['dokan_store_open_notice'];
		}
		if ( isset( $data['dokan_store_close_notice'] ) ) {
			  $vendor_data['dokan_store_close_notice'] = $data['dokan_store_close_notice'];
		}
		if ( isset( $data['dokan_store_time_enabled'] ) ) {
			   $vendor_data['dokan_store_time_enabled'] = $data['dokan_store_time_enabled'];
		}

		if ( isset( $data['logo'] ) ) {
			$img_id                  = upload_image_from_mobile(
				$data['logo'],
				$count,
				$user_id
			);
			$count                   = $count + 1;
			$vendor_data['gravatar'] = $img_id;
		}

		if ( isset( $data['banner'] ) && isset( $data['banner_type'] ) ) {
			$img_id                 = upload_image_from_mobile(
				$data['banner'],
				$count,
				$user_id
			);
			 $vendor_data['banner'] = $img_id;
			 $count++;
		}

		if ( isset( $store_lng ) && isset( $store_lat ) && isset( $store_location ) ) {
			update_user_meta( $user_id, 'dokan_geo_latitude', $store_lat );
			update_user_meta( $user_id, 'dokan_geo_longitude', $store_lng );
			update_user_meta( $user_id, 'dokan_geo_address', $store_location );
		}

			wp_update_user(
				[
					'ID'            => $user_id,
					'user_nicename' => $store_nicename,
				]
			);

		 update_user_meta( $user_id, 'dokan_profile_settings', $vendor_data );
		 update_user_meta( $user_id, 'dokan_store_name', $store_name );

		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => 1,
			],
			200
		);
	}

	/// GET FUNCTIONS
	public function flutter_get_products( $request, $user_id ) {
		global $woocommerce, $wpdb;
		$page  = isset( $request['page'] ) ? sanitize_text_field( $request['page'] ) : 1;
		$limit = isset( $request['per_page'] ) ? sanitize_text_field( $request['per_page'] ) : 10;
		if ( ! is_numeric( $page ) ) {
			$page = 1;
		}
		if ( ! is_numeric( $limit ) ) {
			$limit = 10;
		}
		if ( $page >= 1 ) {
			$page = ( $page - 1 ) * $limit;
		}

		if ( $user_id ) {
			$vendor_id = absint( $user_id );
		}

		$table_name = $wpdb->prefix . 'posts';
		$sql        = "SELECT * FROM `$table_name` WHERE `$table_name`.`post_author` = $vendor_id AND `$table_name`.`post_type` = 'product'";

		if ( isset( $request['search'] ) ) {
			$search = sanitize_text_field( $request['search'] );
			$search = "%$search%";
			$sql   .= " AND (`$table_name`.`post_content` LIKE '$search' OR `$table_name`.`post_title` LIKE '$search' OR `$table_name`.`post_excerpt` LIKE '$search')";
		}
		$sql .= " ORDER BY `ID` DESC LIMIT $limit OFFSET $page";

		$item = $wpdb->get_results( $sql );

		$products_arr = [];
		foreach ( $item as $pro ) {
			$product = wc_get_product( $pro->ID );

			$p         = $product->get_data();
			$image_arr = [];
			foreach ( array_filter( $p['gallery_image_ids'] ) as $img ) {
				$image = wp_get_attachment_image_src( $img, 'full' );
				if ( ! is_null( $image[0] ) ) {
					$image_arr[] = $image[0];
				}
			}

			$image = wp_get_attachment_image_src( $p['image_id'], 'full' );
			if ( ! is_null( $image[0] ) ) {
				$p['featured_image'] = $image[0];
			}
			$p['user_id'] = $user_id;

			$p['images']       = $image_arr;
			$p['category_ids'] = [];
			$category_ids      = wp_get_post_terms( $p['id'], 'product_cat' );
			foreach ( $category_ids as $cat ) {
				if ( $cat->slug != 'uncategorized' ) {
					$p['category_ids'][] = $cat->term_id;
				}
			}
			$p['type']    = $product->get_type();
			$p['on_sale'] = $product->is_on_sale();
			$p['tags']    = wp_get_post_terms( $product->get_id(), 'product_tag' );

			$attributes = [];

			foreach ( $product->get_attributes() as $attribute ) {
				$attributes[] = [
					'id'        => $attribute['is_taxonomy'] ? wc_attribute_taxonomy_id_by_name( $attribute['name'] ) : 0,
					'name'      => 0 === strpos( $attribute['name'], 'pa_' ) ? get_taxonomy( $attribute['name'] )
						->labels->singular_name : $attribute['name'],
					'position'  => (int) $attribute['position'],
					'visible'   => (bool) $attribute['is_visible'],
					'variation' => (bool) $attribute['is_variation'],
					'options'   => $this->get_attribute_options( $product->get_id(), $attribute ),
					'slugs'     => $this->get_attribute_slugs( $product->get_id(), $attribute ),
					'default'   => 0 === strpos( $attribute['name'], 'pa_' ),
					'slug'      => $attribute['name'],
				];
			}
			$p['attributesData'] = $attributes;
			if ( $product->get_type() == 'variable' ) {
				$result = [];
				$query  = [
					'post_parent'    => $product->get_id(),
					'post_status'    => [ 'publish', 'private' ],
					'post_type'      => [ 'product_variation' ],
					'posts_per_page' => -1,
				];

				$wc_query = new WP_Query( $query );
				while ( $wc_query->have_posts() ) :
					$wc_query->next_post();
					$result[] = $wc_query->post;
				endwhile;

				foreach ( $result as $variation ) {
					$p_varation                             = new WC_Product_Variation( $variation->ID );
					$dataVariation                          = [];
					$dataVariation['variation_id']          = $p_varation->get_id();
					$dataVariation['max_qty']               = $p_varation->get_stock_quantity();
					$dataVariation['variation_is_active']   = $p_varation->get_status() == 'publish';
					$dataVariation['display_price']         = $p_varation->get_sale_price();
					$dataVariation['display_regular_price'] = $p_varation->get_regular_price();
					$dataVariation['slugs']                 = $p_varation->get_attributes();
					$dataVariation['manage_stock']          = $p_varation->get_manage_stock();
					$dataVariation['stock_status']          = $p_varation->get_stock_status();
					$attributes                             = $p_varation->get_attributes();
					$dataVariation['attributes']            = [];
					foreach ( $dataVariation['slugs'] as $key => $value ) {
						foreach ( $p['attributesData'] as $item ) {
							if ( $item['slug'] === $key ) {
								for ( $i = 0; $i < count( $item['slugs'] ); $i++ ) {
									if ( $value === $item['slugs'][ $i ] ) {
										$dataVariation['attributes'][ $key ] = $item['options'][ $i ];
										break;
									}
								}
								break;
							}
						}
					}
					$p['variable_products'][] = $dataVariation;
				}
			}
			$products_arr[] = $p;
		}
		return apply_filters( 'flutter_get_products', $products_arr, $request, $user_id );
	}

	public function flutter_get_orders( $request, $user_id ) {
		$api      = new WC_REST_Orders_V1_Controller();
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

		$results = [];
		if ( is_plugin_active( 'dokan-lite/dokan.php' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'dokan_orders';
			$sql        = 'SELECT * FROM ' . $table_name . " WHERE seller_id = $user_id";

			if ( isset( $request['status'] ) ) {
				$status = sanitize_text_field( $request['status'] );
				$sql   .= " AND order_status = 'wc-$status'";
			}
			if ( isset( $request['search'] ) ) {
				$search = sanitize_text_field( $request['search'] );
				$sql   .= " AND order_id LIKE '$search%'";
			}
			$sql  .= " GROUP BY $table_name.`order_id` ORDER BY $table_name.`order_id` DESC LIMIT $per_page OFFSET $page";
			$items = $wpdb->get_results( $sql );

			foreach ( $items as $item ) {
				$order = wc_get_order( $item->order_id );
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
				$results[] = $order;
			}
		}
		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => $results,
			],
			200
		);
	}

	public function flutter_get_sale_stats( $user_id ) {
		$id                                       = $user_id;
		$price_decimal                            = get_option( 'woocommerce_price_num_decimals', 2 );
		$sales_stats['gross_sales']['last_month'] = round( $this->wcfm_get_gross_sales_by_vendor( $id, 'last_month' ), $price_decimal );
		$sales_stats['gross_sales']['month']      = round( $this->wcfm_get_gross_sales_by_vendor( $id, 'month' ), $price_decimal );
		$sales_stats['gross_sales']['year']       = round( $this->wcfm_get_gross_sales_by_vendor( $id, 'year' ), $price_decimal );
		$sales_stats['gross_sales']['week_1']     = round( $this->wcfm_get_gross_sales_by_vendor( $id, '7day' ), $price_decimal );
		$sales_stats['gross_sales']['week_2']     = round( $this->wcfm_get_gross_sales_by_vendor( $id, '14day' ), $price_decimal );
		$sales_stats['gross_sales']['week_3']     = round( $this->wcfm_get_gross_sales_by_vendor( $id, '21day' ), $price_decimal );
		$sales_stats['gross_sales']['week_4']     = round( $this->wcfm_get_gross_sales_by_vendor( $id, '28day' ), $price_decimal );
		$sales_stats['gross_sales']['week_5']     = round( $this->wcfm_get_gross_sales_by_vendor( $id, '35day' ), $price_decimal );
		$sales_stats['gross_sales']['all']        = round( $this->wcfm_get_gross_sales_by_vendor( $id, 'all' ), $price_decimal );
		if ( $sales_stats['gross_sales']['last_month'] != 0 ) {
			$profit_percentage = round( $sales_stats['gross_sales']['month'] - $sales_stats['gross_sales']['last_month'], 2 );
			$profit_percentage = round( $profit_percentage / $sales_stats['gross_sales']['last_month'] * 100 / 100, 2 );
		} else {
			$profit_percentage = round( $sales_stats['gross_sales']['month'] - $sales_stats['gross_sales']['last_month'], 2 );
			$profit_percentage = round( $profit_percentage / 1 * 100 / 100, 2 );
		}
		$sales_stats['gross_sales']['profit_percentage'] = $profit_percentage;
		$sales_stats['earnings']['last_month']           = round( $this->wcfm_get_commission_by_vendor( $id, 'last_month' ), $price_decimal );
		$sales_stats['earnings']['month']                = round( $this->wcfm_get_commission_by_vendor( $id, 'month' ), $price_decimal );
		$sales_stats['earnings']['year']                 = round( $this->wcfm_get_commission_by_vendor( $id, 'year' ), $price_decimal );
		$sales_stats['earnings']['week_1']               = round( $this->wcfm_get_commission_by_vendor( $id, '7day' ), $price_decimal );
		$sales_stats['earnings']['week_2']               = round( $this->wcfm_get_commission_by_vendor( $id, '14day' ), $price_decimal );
		$sales_stats['earnings']['week_3']               = round( $this->wcfm_get_commission_by_vendor( $id, '21day' ), $price_decimal );
		$sales_stats['earnings']['week_4']               = round( $this->wcfm_get_commission_by_vendor( $id, '28day' ), $price_decimal );
		$sales_stats['earnings']['week_5']               = round( $this->wcfm_get_commission_by_vendor( $id, '35day' ), $price_decimal );
		$sales_stats['earnings']['all']                  = round( $this->wcfm_get_commission_by_vendor( $id, 'all' ), $price_decimal );
		if ( $sales_stats['earnings']['last_month'] != 0 ) {
			$profit_percentage = round( $sales_stats['earnings']['month'] - $sales_stats['earnings']['last_month'], 2 );
			$profit_percentage = round( $profit_percentage / $sales_stats['earnings']['last_month'] * 100 / 100, 2 );
		} else {
			$profit_percentage = round( $sales_stats['earnings']['month'] - $sales_stats['earnings']['last_month'], 2 );
			$profit_percentage = round( $profit_percentage / 1 * 100 / 100, 2 );
		}
		$sales_stats['earnings']['profit_percentage'] = $profit_percentage;

		$sales_stats['currency'] = get_woocommerce_currency();

		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => $sales_stats,
			],
			200
		);
	}

	public function flutter_update_order_status( $request, $user_id ) {
		global $WCFM;

		$order_id     = sanitize_text_field( $request['order_id'] );
		$order_status = sanitize_text_field( $request['order_status'] );

		if ( ! dokan_is_seller_has_order( $user_id, $order_id ) || ! is_numeric( $order_id ) ) {
			return new WP_REST_Response(
				[
					'status'   => 'success',
					'response' => [],
				],
				200
			);
		}

		$order = wc_get_order( $order_id );
		$order->update_status( $order_status, '', true );

		$note = sanitize_text_field( $request['customer_note'] );
		if ( ! empty( $note ) ) {
			$order->add_order_note( $note, true, true );
		}

		do_action( 'woocommerce_order_edit_status', $order_id, $order_status );

		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => $order->get_data(),
			],
			200
		);
	}

	public function format_collection_response( $response, $request, $total_items ) {
		// Store pagination values for headers then unset for count query.
		$per_page  = (int) ( ! empty( $request['per_page'] ) ? $request['per_page'] : 20 );
		$page      = (int) ( ! empty( $request['page'] ) ? $request['page'] : 1 );
		$max_pages = ceil( $total_items / $per_page );

		if ( function_exists( 'dokan_get_seller_status_count' ) && current_user_can( 'manage_woocommerce' ) ) {
			$counts = dokan_get_seller_status_count();
			$response->header( 'X-Status-Pending', (int) $counts['inactive'] );
			$response->header( 'X-Status-Approved', (int) $counts['active'] );
			$response->header( 'X-Status-All', (int) $counts['total'] );
		}

		$response->header( 'X-WP-Total', (int) $total_items );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		if ( $total_items === 0 ) {
			return $response;
		}

		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->base ) ) );

		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}

		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	public function prepare_reviews_for_response( $item, $request, $additional_fields = [] ) {
		if ( dokan()->is_pro_exists() && dokan_pro()
				->module
				->is_active( 'store_reviews' ) ) {
			$user          = get_user_by( 'id', $item->post_author );
			$user_gravatar = get_avatar_url( $user->user_email );

			$data = [
				'id'         => (int) $item->ID,
				'author'     => [
					'id'     => $user->ID,
					'name'   => $user->user_login,
					'email'  => $user->user_email,
					'url'    => $user->user_url,
					'avatar' => $user_gravatar,
				],
				'title'      => $item->post_title,
				'content'    => $item->post_content,
				'permalink'  => null,
				'product_id' => null,
				'approved'   => true,
				'date'       => mysql_to_rfc3339( $item->post_date ),
				'rating'     => intval( get_post_meta( $item->ID, 'rating', true ) ),
			];
		} else {
			$comment_author_img_url = get_avatar_url( $item->comment_author_email );
			$data                   = [
				'id'         => (int) $item->comment_ID,
				'author'     => [
					'id'     => $item->user_id,
					'name'   => $item->comment_author,
					'email'  => $item->comment_author_email,
					'url'    => $item->comment_author_url,
					'avatar' => $comment_author_img_url,
				],
				'title'      => null,
				'content'    => $item->comment_content,
				'permalink'  => get_comment_link( $item ),
				'product_id' => $item->comment_post_ID,
				'approved'   => (bool) $item->comment_approved,
				'date'       => mysql_to_rfc3339( $item->comment_date ),
				'rating'     => intval( get_comment_meta( $item->comment_ID, 'rating', true ) ),
			];
		}

		$data = array_merge( $data, $additional_fields );

		return $data;
	}

	public function flutter_get_reviews( $request, $user_id ) {
		$store_id           = $user_id;
		$params['per_page'] = 10;
		$params['page']     = 1;

		$status_filter = '';
		if ( isset( $request['status_type'] ) && ( $request['status_type'] != '' ) ) {
			$status_filter = sanitize_text_field( $request['status_type'] );
		}

		if ( dokan()->is_pro_exists() ) {
			if ( dokan_pro()
				->module
				->is_active( 'store_reviews' ) ) {
				if ( $status_filter == 'pending' ) {
					return new WP_REST_Response(
						[
							'status'   => 'success',
							'response' => [],
						],
						200
					);
				}
				$args = [
					'post_type'   => 'dokan_store_reviews',
					'meta_key'    => 'store_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => $store_id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_status' => 'publish',
				'posts_per_page'  => (int) $request['per_page'],
				'paged'           => (int) $request['page'],
				'author__not_in'  => [ $store_id ],
				];

				$query = new WP_Query( $args );

				if ( empty( $query->posts ) ) {
					return new WP_Error( 'no_reviews_found', __( 'No reviews found', 'dokan-lite' ), [ 'status' => 404 ] );
				}

				$data = [];

				foreach ( $query->posts as $post ) {
					$data[] = $this->prepare_reviews_for_response( $post, $request );
				}

				$total_count = $query->found_posts;
			} else {
				$dokan_template_reviews = dokan_pro()->review;
				$post_type              = 'product';
				$limit                  = (int) $params['per_page'];
				$paged                  = (int) ( $params['page'] - 1 ) * $params['per_page'];
				$status                 = '1';
				$comments               = $dokan_template_reviews->comment_query( $store_id, $post_type, $limit, $status, $paged );

				if ( empty( $comments ) ) {
					return new WP_Error( 'no_reviews_found', __( 'No reviews found', 'dokan-lite' ), [ 'status' => 404 ] );
				}

				$data = [];

				foreach ( $comments as $comment ) {
					$data[] = $this->prepare_reviews_for_response( $comment, $request );
				}

				$total_count = 0;
			}
		} else {
			return new WP_REST_Response(
				[
					'status'   => 'success',
					'response' => [],
				],
				200
			);
		}

		$response = rest_ensure_response( $data );
		$response = $this->format_collection_response( $response, $request, $total_count );

		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => $response->data,
			],
			200
		);
	}

	// Update review status
	function flutter_update_review( $request ) {

	}

	/* GET WCFM SALE STATS FUNCTIONS. CUSTOM BY TOAN 04/11/2020 */

	function wcfm_query_time_range_filter( $sql, $time, $interval = '7day', $start_date = '', $end_date = '', $table_handler = 'commission' ) {
		switch ( $interval ) {
			case 'year':
				$sql .= " AND YEAR( {$table_handler}.{$time} ) = YEAR( CURDATE() )";
				break;
			case 'last_month':
				$sql .= " AND MONTH( {$table_handler}.{$time} ) = MONTH( NOW() ) - 1 AND YEAR( {$table_handler}.{$time} ) = YEAR( CURDATE() )";
				break;
			case 'month':
				$sql .= " AND MONTH( {$table_handler}.{$time} ) = MONTH( NOW() ) AND YEAR( {$table_handler}.{$time} ) = YEAR( CURDATE() )";
				break;
			case 'all':
				break;
			case '7day':
				$sql .= " AND DATE( {$table_handler}.{$time} ) BETWEEN DATE_SUB( NOW(), INTERVAL 7 DAY ) AND NOW()";
				break;
			case '14day':
				$sql .= " AND DATE( {$table_handler}.{$time} ) BETWEEN DATE_SUB( NOW(), INTERVAL 14 DAY ) AND DATE_SUB( NOW(), INTERVAL 7 DAY )";
				break;
			case '21day':
				$sql .= " AND DATE( {$table_handler}.{$time} ) BETWEEN DATE_SUB( NOW(), INTERVAL 21 DAY ) AND DATE_SUB( NOW(), INTERVAL 14 DAY )";
				break;
			case '28day':
				$sql .= " AND DATE( {$table_handler}.{$time} ) BETWEEN DATE_SUB( NOW(), INTERVAL 28 DAY ) AND DATE_SUB( NOW(), INTERVAL 21 DAY )";
				break;
			case '35day':
				$sql .= " AND DATE( {$table_handler}.{$time} ) BETWEEN DATE_SUB( NOW(), INTERVAL 35 DAY ) AND DATE_SUB( NOW(), INTERVAL 28 DAY )";
				break;
			case 'default':
		}

		return $sql;
	}

	function wcfm_get_gross_sales_by_vendor( $vendor_id = '', $interval = '7day', $is_paid = false, $order_id = 0, $filter_date_form = '', $filter_date_to = '' ) {
		global $woocommerce, $wpdb;

		if ( $vendor_id ) {
			$vendor_id = absint( $vendor_id );
		}

		$gross_sales = 0;
		$table_1     = "{$wpdb->prefix}posts";
		$table_2     = "{$wpdb->prefix}dokan_orders";

		$sql    = "SELECT {$table_2}.order_total FROM {$table_2} INNER JOIN {$table_1} ON {$table_1}.ID = {$table_2}.order_id WHERE {$table_1}.post_type = 'shop_order'";
		$sql   .= " AND {$table_1}.post_status = 'wc-completed' AND {$table_2}.seller_id = {$vendor_id}";
		$sql    = $this->wcfm_query_time_range_filter( $sql, 'post_date', $interval, '', '', "{$wpdb->prefix}posts" );
		$result = $wpdb->get_results( $sql );

		foreach ( $result as $order_id ) {
			$gross_sales += $order_id->order_total;
		}

		if ( ! $gross_sales ) {
			$gross_sales = 0;
		}

		return $gross_sales;
	}

	/**
	 * Total commission paid by Admin
	 */
	function wcfm_get_commission_by_vendor( $vendor_id = '', $interval = '7day', $is_paid = false, $order_id = 0, $filter_date_form = '', $filter_date_to = '' ) {
		global $woocommerce, $wpdb;

		if ( $vendor_id ) {
			$vendor_id = absint( $vendor_id );
		}

		$commission = 0;

		$table_1 = "{$wpdb->prefix}posts";
		$table_2 = "{$wpdb->prefix}dokan_orders";

		$sql    = "SELECT {$table_2}.net_amount FROM {$table_2} INNER JOIN {$table_1} ON {$table_1}.ID = {$table_2}.order_id WHERE {$table_1}.post_type = 'shop_order'";
		$sql   .= " AND {$table_1}.post_status = 'wc-completed' AND {$table_2}.seller_id = {$vendor_id}";
		$sql    = $this->wcfm_query_time_range_filter( $sql, 'post_date', $interval, '', '', "{$wpdb->prefix}posts" );
		$result = $wpdb->get_results( $sql );

		foreach ( $result as $order_id ) {
			$commission += $order_id->net_amount;
		}

		if ( ! $commission ) {
			$commission = 0;
		}

		return $commission;
	}

	/* GET WCFM SALE STATS FUNCTIONS. CUSTOM BY TOAN 04/11/2020 */

	/* GET NOTIFICATIONS */
	function get_notification_by_vendor( $request, $user_id ) {
		global $WCFM, $wpdb;
		$wcfm_messages = [];
		if ( isset( $request['per_page'] ) && $request['per_page'] ) {
			$limit  = sanitize_text_field( $request['per_page'] );
			$offset = sanitize_text_field( $request['page'] );
			if ( ! is_numeric( $offset ) ) {
				$offset = 1;
			}
			if ( ! is_numeric( $limit ) ) {
				$limit = 10;
			}
			$offset     = ( $offset - 1 ) * $limit;
			$message_to = apply_filters( 'wcfm_message_author', $user_id );

			$sql                   = 'SELECT wcfm_messages.* FROM ' . $wpdb->prefix . 'wcfm_messages AS wcfm_messages';
			$vendor_filter         = " WHERE ( `author_id` = {$message_to} OR `message_to` = -1 OR `message_to` = {$message_to} )";
			$sql                  .= $vendor_filter;
			$message_status_filter = " AND NOT EXISTS (SELECT * FROM {$wpdb->prefix}wcfm_messages_modifier as wcfm_messages_modifier_2 WHERE wcfm_messages.ID = wcfm_messages_modifier_2.message AND wcfm_messages_modifier_2.read_by={$message_to})";
			$sql                  .= $message_status_filter;
			$sql                  .= ' ORDER BY wcfm_messages.`ID` DESC';
			$sql                  .= " LIMIT $limit";
			$sql                  .= " OFFSET $offset";
			$wcfm_messages         = $wpdb->get_results( $sql );

			foreach ( $wcfm_messages as $wcfm_message ) {
				unset( $wcfm_message->author_id, $wcfm_message->reply_to, $wcfm_message->author_is_admin, $wcfm_message->author_is_vendor, $wcfm_message->author_is_customer, $wcfm_message->is_notice, $wcfm_message->is_direct_message, $wcfm_message->is_pined, $wcfm_message->message_to );
				$wcfm_message->message = strip_tags( $wcfm_message->message );
			}
		}
		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => $wcfm_messages,
			],
			200
		);
	}

	/// CREATE ///
	public function vendor_admin_create_product( $request, $user_id ) {
		$user     = get_userdata( $user_id );
		$isSeller = in_array( 'seller', $user->roles );

		$requestStatus = 'draft';
		if ( isset( $request['status'] ) ) {
			$requestStatus = sanitize_text_field( $request['status'] );
		}

		$name                  = sanitize_text_field( $request['name'] );
		$description           = sanitize_text_field( $request['description'] );
		$short_description     = sanitize_text_field( $request['short_description'] );
		$featured_image        = sanitize_text_field( $request['featuredImage'] );
		$product_images        = sanitize_text_field( $request['images'] );
		$type                  = sanitize_text_field( $request['type'] );
		$tags                  = sanitize_text_field( $request['tags'] );
		$featured              = sanitize_text_field( $request['featured'] );
		$regular_price         = sanitize_text_field( $request['regular_price'] );
		$sale_price            = sanitize_text_field( $request['sale_price'] );
		$date_on_sale_from     = sanitize_text_field( $request['date_on_sale_from'] );
		$date_on_sale_from_gmt = sanitize_text_field( $request['date_on_sale_from_gmt'] );
		$date_on_sale_to       = sanitize_text_field( $request['date_on_sale_to'] );
		$date_on_sale_to_gmt   = sanitize_text_field( $request['date_on_sale_to_gmt'] );
		$in_stock              = sanitize_text_field( $request['in_stock'] );
		$stock_quantity        = sanitize_text_field( $request['stock_quantity'] );
		$manage_stock          = sanitize_text_field( $request['manage_stock'] );
		$backorders            = sanitize_text_field( $request['backorders'] );
		$categories            = sanitize_text_field( $request['categories'] );
		$productAttributes     = sanitize_text_field( $request['productAttributes'] );
		$variations            = sanitize_text_field( $request['variations'] );
		$inventory_delta       = sanitize_text_field( $request['inventory_delta'] );

		$count = 1;

		if ( $isSeller ) {
			$args = [
				'post_author'  => $user_id,
				'post_content' => $description,
				'post_status'  => $requestStatus, // (Draft | Pending | Publish)
				'post_title'   => $name,
				'post_parent'  => '',
				'post_type'    => 'product',
			];
			// Create a simple WooCommerce product
			$post_id = wp_insert_post( $args );
			$product = wc_get_product( $post_id );

			if ( $product->get_type() != $type ) {
				// Get the correct product classname from the new product type
				$product_classname = WC_Product_Factory::get_product_classname( $product->get_id(), $type );

				// Get the new product object from the correct classname
				$product = new $product_classname( $product->get_id() );
				$product->save();

			}

			if ( isset( $featured_image ) ) {
				if ( ! empty( $featured_image ) ) {
					if ( $this->http_check( $featured_image ) ) {
						$featured_image_id = $this->find_image_id( $featured_image );
						$product->set_image_id( $featured_image_id );
					} else {
						$featured_image_id = upload_image_from_mobile( $featured_image, $count, $user_id );
						$product->set_image_id( $featured_image_id );
						$count = $count + 1;
					}
				} else {
					$product->set_image_id( '' );
				}
			}

			if ( isset( $product_images ) ) {
				$product_images_array = array_filter( explode( ',', $product_images ) );
				$img_array            = [];

				foreach ( $product_images_array as $p_img ) {
					if ( ! empty( $p_img ) ) {
						if ( $this->http_check( $p_img ) ) {
							$img_id = $this->find_image_id( $p_img );
							array_push( $img_array, $img_id );
						} else {
							$img_id = upload_image_from_mobile( $p_img, $count, $user_id );
							array_push( $img_array, $img_id );
							$count = $count + 1;
						}
					}
				}
				$product->set_gallery_image_ids( $img_array );
			}

			if ( isset( $tags ) ) {
				$tags = array_filter( explode( ',', $tags ) );
				wp_set_object_terms( $post_id, $tags, 'product_tag' );
			}

			/// Set attributes to product
			if ( isset( $product ) && ! is_wp_error( $product ) ) {
				if ( isset( $name ) ) {
					$product->set_name( wp_filter_post_kses( $name ) );
				}
				// Featured Product.
				if ( isset( $featured ) ) {
					$product->set_featured( $featured );
				}
				// SKU.
				if ( isset( $request['sku'] ) ) {
					$product->set_sku( wc_clean( $request['sku'] ) );
				}

				// Sales and prices.
				if ( in_array(
					$product->get_type(),
					[
						'variable',
						'grouped',
					],
					true
				) ) {
					$product->set_regular_price( '' );
					$product->set_sale_price( '' );
					$product->set_date_on_sale_to( '' );
					$product->set_date_on_sale_from( '' );
					$product->set_price( '' );
				} else {
					// Regular Price.
					if ( isset( $regular_price ) ) {
						$product->set_regular_price( $regular_price );
					}
					// Sale Price.
					if ( isset( $sale_price ) && ! empty( $sale_price ) ) {
						$product->set_sale_price( $sale_price );
					}
					if ( isset( $date_on_sale_from ) ) {
						$product->set_date_on_sale_from( $date_on_sale_from );
					}
					if ( isset( $date_on_sale_from_gmt ) ) {
						$product->set_date_on_sale_from( $date_on_sale_from_gmt ? strtotime( $date_on_sale_from_gmt ) : null );
					}

					if ( isset( $date_on_sale_to ) ) {
						$product->set_date_on_sale_to( $date_on_sale_to );
					}

					if ( isset( $date_on_sale_to_gmt ) ) {
						$product->set_date_on_sale_to( $date_on_sale_to_gmt ? strtotime( $date_on_sale_to_gmt ) : null );
					}
				}

				// Description
				if ( isset( $description ) ) {
					$product->set_description( $description );
				}
				if ( isset( $short_description ) ) {
					$product->set_description( $short_description );
				}

				// Stock status.
				if ( isset( $in_stock ) && is_bool( $in_stock ) ) {
					$stock_status = true === $in_stock ? 'instock' : 'outofstock';
				} else {
					$stock_status = $product->get_stock_status();
				}

				// Stock data.
				if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
					// Manage stock.
					if ( isset( $manage_stock ) ) {
						$product->set_manage_stock( $manage_stock );
					}

					// Backorders.
					if ( isset( $backorders ) ) {
						$product->set_backorders( $backorders );
					}

					if ( $product->is_type( 'grouped' ) ) {
						$product->set_manage_stock( 'no' );
						$product->set_backorders( 'no' );
						$product->set_stock_quantity( '' );
						$product->set_stock_status( $stock_status );
					} elseif ( $product->is_type( 'external' ) ) {
						$product->set_manage_stock( 'no' );
						$product->set_backorders( 'no' );
						$product->set_stock_quantity( '' );
						$product->set_stock_status( 'instock' );
					} elseif ( $product->get_manage_stock() ) {
						// Stock status is always determined by children so sync later.
						if ( ! $product->is_type( 'variable' ) ) {
							$product->set_stock_status( $stock_status );
						}

						// Stock quantity.
						if ( isset( $stock_quantity ) ) {
							$product->set_stock_quantity( wc_stock_amount( $stock_quantity ) );
						} elseif ( isset( $inventory_delta ) ) {
							$stock_quantity  = wc_stock_amount( $product->get_stock_quantity() );
							$stock_quantity += wc_stock_amount( $inventory_delta );
							$product->set_stock_quantity( wc_stock_amount( $stock_quantity ) );
						}
					} else {
						// Don't manage stock.
						$product->set_manage_stock( 'no' );
						$product->set_stock_quantity( '' );
						$product->set_stock_status( $stock_status );
					}
				} elseif ( ! $product->is_type( 'variable' ) ) {
					$product->set_stock_status( $stock_status );
				}

				//Assign categories
				if ( isset( $categories ) ) {
					$categories = array_filter( explode( ',', $categories ) );
					if ( ! empty( $categories ) ) {
						$categoryArray = [];
						foreach ( $categories as $index ) {
							$categoryArray[] = absint( $index );
						}
						$product->set_category_ids( $categoryArray );
					}
				}

				//Description
				$product->set_short_description( $short_description );
				$product->set_description( $description );
				$attribute_json = json_decode( $productAttributes, true );
				$pro_attributes = [];
				foreach ( $attribute_json as $key => $value ) {
					if ( $value['isActive'] ) {
						$attribute_name = strtolower( $value['slug'] );
						if ( $value['default'] ) {
							$attribute_name = strtolower(
								'pa_' . $value['slug']
							);
						}
						$attribute_id = wc_attribute_taxonomy_id_by_name(
							$attribute_name
						);
						$attribute    = new WC_Product_Attribute();
						$attribute->set_id( $attribute_id );
						$attribute->set_name( wc_clean( $attribute_name ) );
						$options = $value['options'];
						$attribute->set_options( $options );
						$attribute->set_visible( $value['visible'] );
						$attribute->set_variation( $value['variation'] );

						$pro_attributes[] = $attribute;
					}
				}

				$product->set_props(
					[
						'attributes' => $pro_attributes,
					]
				);
				if ( is_wp_error( $product ) ) {
					return $this->sendError( 'request_failed', 'Bad data', 400 );
				}

				$product->save();

				if ( $product->get_type() == 'variable' ) {

					$variations_arr = json_decode( $variations, true );
					foreach ( $variations_arr as $variation ) {
						if ( $variation['variation_id'] != -1 ) {
							foreach ( $variation['slugs'] as $key => $value ) {
								$variationAttrArr[ $key ] = strtolower( strval( $value ) );
							}
							$variationProduct = new WC_Product_Variation( $variation['variation_id'] );
							$variationProduct->set_regular_price( $variation['display_regular_price'] );
							$variationProduct->set_sale_price( $variation['display_price'] );
							$variationProduct->set_stock_quantity( $variation['max_qty'] );
							$variationProduct->set_attributes( $variationAttrArr );
							$variationProduct->set_manage_stock( boolval( $variation['manage_stock'] ) );
							$variationProduct->set_status( $variation['variation_is_active'] ? 'publish' : 'private' );
							$variationProduct->set_stock_status( $variation['stock_status'] );
							$variationProduct->save();
						} else {
							// Creating the product variation
							$variation_post = [
								'post_title'  => $product->get_title(),
								'post_name'   => 'product-' . $product->get_id() . '-variation',
								'post_status' => 'publish',
								'post_parent' => $product->get_id(),
								'post_type'   => 'product_variation',
								'guid'        => $product->get_permalink(),
							];
							$variation_id   = wp_insert_post( $variation_post );

							foreach ( $variation['slugs'] as $key => $value ) {
								$variationAttrArr[ $key ] = strtolower( strval( $value ) );
							}
							$variationProduct = new WC_Product_Variation( $variation_id );
							$variationProduct->set_regular_price( $variation['display_regular_price'] );
							$variationProduct->set_sale_price( $variation['display_price'] );
							$variationProduct->set_stock_quantity( $variation['max_qty'] );
							$variationProduct->set_attributes( $variationAttrArr );
							$variationProduct->set_manage_stock( boolval( $variation['manage_stock'] ) );
							$variationProduct->set_status( $variation['variation_is_active'] ? 'publish' : 'private' );
							$variationProduct->set_stock_status( $variation['stock_status'] );
							$variationProduct->save();
						}
						$variable_ids[] = $variationProduct->get_id();
					}
				}

				wp_update_post(
					[
						'ID'          => $product->get_id(),
						'post_author' => $user_id,
					]
				);
				//print_r($product);
				$image_arr = [];
				$p         = $product->get_data();
				foreach ( array_filter( $p['gallery_image_ids'] ) as $img ) {
					$image = wp_get_attachment_image_src( $img, 'full' );

					if ( ! is_null( $image[0] ) ) {
						$image_arr[] = $image[0];
					}
				}
				$p['description']       = strip_tags( $p['description'] );
				$p['short_description'] = strip_tags( $p['short_description'] );
				$p['images']            = $image_arr;
				$image                  = wp_get_attachment_image_src( $p['image_id'], 'full' );
				if ( ! is_null( $image[0] ) ) {
					$p['featured_image'] = $image[0];
				}
				$p['type']    = $product->get_type();
				$p['on_sale'] = $product->is_on_sale();
				if ( $product->get_type() == 'variable' ) {
					$query = [
						'post_parent'    => $product->get_id(),
						'post_status'    => [ 'publish', 'private' ],
						'post_type'      => [ 'product_variation' ],
						'posts_per_page' => -1,
					];

					$wc_query = new WP_Query( $query );
					while ( $wc_query->have_posts() ) {
						$wc_query->next_post();
						$result[] = $wc_query->post;
					}

					foreach ( $result as $variation ) {
						$p_varation                             = new WC_Product_Variation( $variation->ID );
						$dataVariation                          = [];
						$dataVariation['variation_id']          = $p_varation->get_id();
						$dataVariation['max_qty']               = $p_varation->get_stock_quantity();
						$dataVariation['variation_is_active']   = $p_varation->get_status() == 'publish';
						$dataVariation['display_price']         = $p_varation->get_sale_price();
						$dataVariation['display_regular_price'] = $p_varation->get_regular_price();
						$dataVariation['attributes']            = $p_varation->get_attributes();
						$dataVariation['manage_stock']          = $p_varation->get_manage_stock();
						$dataVariation['stock_status']          = $p_varation->get_stock_status();
						$p['variable_products'][]               = $dataVariation;
					}
				}
				return new WP_REST_Response(
					[
						'status'   => 'success',
						'response' => $p,
					],
					200
				);
			}
		} else {
			return $this->sendError( 'invalid_role', 'You must be seller to create product', 401 );
		}
	}

	/// UPDATE ///
	public function vendor_admin_update_product( $request, $user_id ) {

		$id = isset( $request['id'] ) ? $request['id'] : 0;
		if ( isset( $id ) && is_numeric( $id ) ) {
			$product = $this->get_product_item( $id );
		} else {
			return $this->sendError( 'request_failed', 'Invalid data', 400 );
		}

		/// Validate requested user_id and product_id
		$post_obj = get_post( $product->get_id() );

		$author_id = $post_obj->post_author;

		if ( $user_id != $author_id ) {
			return $this->sendError( 'unauthorized', 'You are not allow to do this', 401 );
		}

		$name                  = sanitize_text_field( $request['name'] );
		$description           = sanitize_text_field( $request['description'] );
		$short_description     = sanitize_text_field( $request['short_description'] );
		$featured_image        = sanitize_text_field( $request['featuredImage'] );
		$product_images        = sanitize_text_field( $request['images'] );
		$type                  = sanitize_text_field( $request['type'] );
		$tags                  = sanitize_text_field( $request['tags'] );
		$featured              = sanitize_text_field( $request['featured'] );
		$regular_price         = sanitize_text_field( $request['regular_price'] );
		$sale_price            = sanitize_text_field( $request['sale_price'] );
		$date_on_sale_from     = sanitize_text_field( $request['date_on_sale_from'] );
		$date_on_sale_from_gmt = sanitize_text_field( $request['date_on_sale_from_gmt'] );
		$date_on_sale_to       = sanitize_text_field( $request['date_on_sale_to'] );
		$date_on_sale_to_gmt   = sanitize_text_field( $request['date_on_sale_to_gmt'] );
		$in_stock              = sanitize_text_field( $request['in_stock'] );
		$stock_quantity        = sanitize_text_field( $request['stock_quantity'] );
		$manage_stock          = sanitize_text_field( $request['manage_stock'] );
		$backorders            = sanitize_text_field( $request['backorders'] );
		$categories            = sanitize_text_field( $request['categories'] );
		$productAttributes     = sanitize_text_field( $request['productAttributes'] );
		$variations            = sanitize_text_field( $request['variations'] );
		$inventory_delta       = sanitize_text_field( $request['inventory_delta'] );
		$status                = sanitize_text_field( $request['status'] );
		$stock_status          = sanitize_text_field( $request['stock_status'] );

		$count = 1;
		if ( $product->get_type() != $type ) {
			// Get the correct product classname from the new product type
			$product_classname = WC_Product_Factory::get_product_classname( $product->get_id(), $type );

			// Get the new product object from the correct classname
			$product = new $product_classname( $product->get_id() );
			$product->save();
		}

		if ( isset( $tags ) ) {
			$tags = array_filter( explode( ',', $tags ) );
			wp_set_object_terms( $product->get_id(), $tags, 'product_tag' );
		}

		if ( isset( $featured_image ) ) {
			if ( ! empty( $featured_image ) ) {
				if ( $this->http_check( $featured_image ) ) {
					$featured_image_id = $this->find_image_id( $featured_image );
					$product->set_image_id( $featured_image_id );
				} else {
					$featured_image_id = upload_image_from_mobile( $featured_image, $count, $user_id );
					$product->set_image_id( $featured_image_id );
					$count = $count + 1;
				}
			} else {
				$product->set_image_id( '' );
			}
		}

		if ( isset( $product_images ) ) {
			$product_images_array = array_filter( explode( ',', $product_images ) );
			$img_array            = [];

			foreach ( $product_images_array as $p_img ) {
				if ( ! empty( $p_img ) ) {
					if ( $this->http_check( $p_img ) ) {
						$img_id = $this->find_image_id( $p_img );
						array_push( $img_array, $img_id );
					} else {
						$img_id = upload_image_from_mobile( $p_img, $count, $user_id );
						array_push( $img_array, $img_id );
						$count = $count + 1;
					}
				}
			}
			$product->set_gallery_image_ids( $img_array );
		}

		/// Set attributes to product
		if ( isset( $product ) && ! is_wp_error( $product ) ) {
			if ( isset( $name ) ) {
				$product->set_name( wp_filter_post_kses( $name ) );
			}
			// Featured Product.
			if ( isset( $featured ) ) {
				$product->set_featured( $featured );
			}
			// SKU.
			if ( isset( $request['sku'] ) ) {
				$product->set_sku( wc_clean( $request['sku'] ) );
			}

			// Sales and prices.
			$product->set_status( $status );

			if ( in_array(
				$product->get_type(),
				[
					'variable',
					'grouped',
				],
				true
			) ) {
				$product->set_regular_price( '' );
				$product->set_sale_price( '' );
				$product->set_date_on_sale_to( '' );
				$product->set_date_on_sale_from( '' );
				$product->set_price( '' );
			} else {
				// Regular Price.
				if ( isset( $regular_price ) ) {
					$product->set_regular_price( $regular_price );
				}
				// Sale Price.
				if ( isset( $sale_price ) && ! empty( $sale_price ) ) {
					$product->set_sale_price( $sale_price );
				}
				if ( isset( $date_on_sale_from ) ) {
					$product->set_date_on_sale_from( $date_on_sale_from );
				}
				if ( isset( $date_on_sale_from_gmt ) ) {
					$product->set_date_on_sale_from( $date_on_sale_from_gmt ? strtotime( $date_on_sale_from_gmt ) : null );
				}

				if ( isset( $date_on_sale_to ) ) {
					$product->set_date_on_sale_to( $date_on_sale_to );
				}

				if ( isset( $date_on_sale_to_gmt ) ) {
					$product->set_date_on_sale_to( $date_on_sale_to_gmt ? strtotime( $date_on_sale_to_gmt ) : null );
				}
			}

			// Description
			if ( isset( $description ) ) {

				$product->set_description( strip_tags( $description ) );
			}
			if ( isset( $short_description ) ) {
				$product->set_short_description( strip_tags( $short_description ) );
			}

			// // Stock status.
			// if (isset($in_stock)) {
			//     $stock_status = true === $in_stock ? 'instock' : 'outofstock';
			// } else {
			//     $stock_status = $product->get_stock_status();
			// }

			// Stock data.
			if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
				// Manage stock.
				if ( isset( $manage_stock ) ) {
					$product->set_manage_stock( $manage_stock );
				}

				// Backorders.
				if ( isset( $backorders ) ) {
					$product->set_backorders( $backorders );
				}

				if ( $product->is_type( 'grouped' ) ) {
					$product->set_manage_stock( 'no' );
					$product->set_backorders( 'no' );
					$product->set_stock_quantity( '' );
					$product->set_stock_status( $stock_status );
				} elseif ( $product->is_type( 'external' ) ) {
					$product->set_manage_stock( 'no' );
					$product->set_backorders( 'no' );
					$product->set_stock_quantity( '' );
					$product->set_stock_status( 'instock' );
				} elseif ( $product->get_manage_stock() ) {
					// Stock status is always determined by children so sync later.
					if ( ! $product->is_type( 'variable' ) ) {
						$product->set_stock_status( $stock_status );
					}

					// Stock quantity.
					if ( isset( $stock_quantity ) ) {
						$product->set_stock_quantity( wc_stock_amount( $stock_quantity ) );
					} elseif ( isset( $request['inventory_delta'] ) ) {
						$stock_quantity  = wc_stock_amount( $product->get_stock_quantity() );
						$stock_quantity += wc_stock_amount( $inventory_delta );
						$product->set_stock_quantity( wc_stock_amount( $stock_quantity ) );
					}
				} else {
					// Don't manage stock.
					$product->set_manage_stock( 'no' );
					$product->set_stock_quantity( '' );
					$product->set_stock_status( $stock_status );
				}
			} elseif ( ! $product->is_type( 'variable' ) ) {
				$product->set_stock_status( $stock_status );
			}

			//Assign categories
			if ( isset( $categories ) ) {
				$categories = array_filter( explode( ',', $categories ) );
				if ( ! empty( $categories ) ) {
					$categoryArray = [];
					foreach ( $categories as $index ) {
						$categoryArray[] = absint( $index );
					}
					$product->set_category_ids( $categoryArray );
				} else {
					$product->set_category_ids( [] );
				}
			}

			//Description
			$product->set_short_description( $short_description );
			$product->set_description( $description );
			if ( is_wp_error( $product ) ) {
				return $this->sendError( 'request_failed', 'Bad data', 400 );
			}

			$attribute_json = json_decode( $productAttributes, true );
			$pro_attributes = [];
			foreach ( $attribute_json as $key => $value ) {
				if ( $value['isActive'] ) {
					$attribute_name = strtolower( $value['slug'] );
					if ( $value['default'] ) {
						$attribute_name = strtolower( 'pa_' . $value['slug'] );
					}
					$attribute_id = wc_attribute_taxonomy_id_by_name(
						$attribute_name
					);
					$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );
					$attribute    = new WC_Product_Attribute();
					$attribute->set_id( $attribute_id );
					$attribute->set_name( wc_clean( $attribute_name ) );
					$options = $value['options'];
					$attribute->set_options( $options );
					$attribute->set_visible( $value['visible'] );
					$attribute->set_variation( $value['variation'] );
					$pro_attributes[] = $attribute;
				}
			}

			$product->set_props(
				[
					'attributes' => $pro_attributes,
				]
			);
			$product->save();

			if ( $product->is_type( 'variable' ) ) {

				$variations_arr = json_decode( $variations, true );
				foreach ( $variations_arr as $variation ) {
					if ( $variation['variation_id'] != -1 ) {
						foreach ( $variation['slugs'] as $key => $value ) {
							$variationAttrArr[ $key ] = strtolower( strval( $value ) );
						}
						$variationProduct = new WC_Product_Variation( $variation['variation_id'] );
						$variationProduct->set_regular_price( $variation['display_regular_price'] );
						$variationProduct->set_sale_price( $variation['display_price'] );
						$variationProduct->set_stock_quantity( $variation['max_qty'] );
						$variationProduct->set_attributes( $variationAttrArr );
						$variationProduct->set_manage_stock( boolval( $variation['manage_stock'] ) );
						$variationProduct->set_status( $variation['variation_is_active'] ? 'publish' : 'private' );
						$variationProduct->set_stock_status( $variation['stock_status'] );
						$variationProduct->save();
					} else {
						// Creating the product variation
						$variation_post = [
							'post_title'  => $product->get_title(),
							'post_name'   => 'product-' . $product->get_id() . '-variation',
							'post_status' => 'publish',
							'post_parent' => $product->get_id(),
							'post_type'   => 'product_variation',
							'guid'        => $product->get_permalink(),
						];
						$variation_id   = wp_insert_post( $variation_post );

						foreach ( $variation['slugs'] as $key => $value ) {
							$variationAttrArr[ $key ] = strtolower( strval( $value ) );
						}
						$variationProduct = new WC_Product_Variation( $variation_id );
						$variationProduct->set_regular_price( $variation['display_regular_price'] );
						$variationProduct->set_sale_price( $variation['display_price'] );
						$variationProduct->set_stock_quantity( $variation['max_qty'] );
						$variationProduct->set_attributes( $variationAttrArr );
						$variationProduct->set_manage_stock( boolval( $variation['manage_stock'] ) );
						$variationProduct->set_status( $variation['variation_is_active'] ? 'publish' : 'private' );
						$variationProduct->set_stock_status( $variation['stock_status'] );
						$variationProduct->save();
					}
				}
			}

			wp_update_post(
				[
					'ID'          => $product->get_id(),
					'post_author' => $user_id,
				]
			);
			//print_r($product);
			$image_arr = [];
			$p         = $product->get_data();

			foreach ( array_filter( $p['gallery_image_ids'] ) as $img ) {
				$image = wp_get_attachment_image_src( $img, 'full' );

				if ( ! is_null( $image[0] ) ) {
					$image_arr[] = $image[0];
				}
			}
			$p['description']       = strip_tags( $p['description'] );
			$p['short_description'] = strip_tags( $p['short_description'] );
			$p['images']            = $image_arr;
			$image                  = wp_get_attachment_image_src( $p['image_id'], 'full' );
			if ( ! is_null( $image[0] ) ) {
				$p['featured_image'] = $image[0];
			}
			$p['type']    = $product->get_type();
			$p['on_sale'] = $product->is_on_sale();
			$attributes   = [];
			foreach ( $product->get_attributes() as $attribute ) {
				$attributes[] = [
					'id'        => $attribute['is_taxonomy'] ? wc_attribute_taxonomy_id_by_name( $attribute['name'] ) : 0,
					'name'      => $this->get_attribute_taxonomy_name( $attribute['name'], $product ),
					'position'  => (int) $attribute['position'],
					'visible'   => (bool) $attribute['is_visible'],
					'variation' => (bool) $attribute['is_variation'],
					'options'   => $this->get_attribute_options( $product->get_id(), $attribute ),
					'default'   => 0 === strpos( $attribute['name'], 'pa_' ),
				];
			}

			$p['attributesData'] = $attributes;
			if ( $product->is_type( 'variable' ) ) {
				$query = [
					'post_parent'    => $product->get_id(),
					'post_status'    => [ 'publish', 'private' ],
					'post_type'      => [ 'product_variation' ],
					'posts_per_page' => -1,
				];

				$wc_query = new WP_Query( $query );
				while ( $wc_query->have_posts() ) {
					$wc_query->next_post();
					$result[] = $wc_query->post;
				}

				foreach ( $result as $variation ) {
					$p_varation                             = new WC_Product_Variation( $variation->ID );
					$dataVariation                          = [];
					$dataVariation['variation_id']          = $p_varation->get_id();
					$dataVariation['max_qty']               = $p_varation->get_stock_quantity();
					$dataVariation['variation_is_active']   = $p_varation->get_status() == 'publish';
					$dataVariation['display_price']         = $p_varation->get_sale_price();
					$dataVariation['display_regular_price'] = $p_varation->get_regular_price();
					$dataVariation['attributes']            = $p_varation->get_attributes();
					$dataVariation['manage_stock']          = $p_varation->get_manage_stock();
					$dataVariation['stock_status']          = $p_varation->get_stock_status();
					$p['variable_products'][]               = $dataVariation;
				}
			}
			return new WP_REST_Response(
				[
					'status'   => 'success',
					'response' => $p,
				],
				200
			);
		}
	}

	/// DELETE ///
	public function vendor_admin_delete_product( $request, $user_id ) {
		/// Validate product ID
		$id = isset( $request['id'] ) ? $request['id'] : 0;
		if ( isset( $request['id'] ) && is_numeric( $id ) ) {
			$product = $this->get_product_item( $id );
		} else {
			return $this->sendError( 'request_failed', 'Invalid data', 400 );
		}
		/// Validate requested user_id and product_id
		$post_obj  = get_post( $product->get_id() );
		$author_id = $post_obj->post_author;
		if ( $user_id != $author_id ) {
			return $this->sendError( 'unauthorized', 'You are not allow to do this', 401 );
		}
		wp_delete_post( $product->get_id() );
		return new WP_REST_Response(
			[
				'status'   => 'success',
				'response' => '',
			],
			200
		);
	}
}

