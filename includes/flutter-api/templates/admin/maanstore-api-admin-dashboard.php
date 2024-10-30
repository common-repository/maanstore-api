<?php require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'functions/index.php'; ?>

	<div class="wrap">
		<div class="thanks">
			<p style="font-size: 16px;">Thank you for installing Mstore API plugins.</p>
			<?php
			$verified = get_option( 'mstore_purchase_code' );
			?>
		</div>
	</div>

<?php
if ( isset( $verified ) && $verified == '1' ) {
	?>
	<div class="thanks">
		<p style="font-size: 16px;">This setting limit the number of product per category to use cache data in home
			screen</p>
	</div>
	<form action="" method="post">
		<?php
		$limit = get_option( 'mstore_limit_product' );
		?>
		<div class="form-group" style="margin-top:10px;margin-bottom:40px">
			<input type="number" value="<?php echo ( ! isset( $limit ) || $limit == false ) ? 10 : esc_attr( $limit ); ?>"
				   class="mstore-update-limit-product">
		</div>
	</form>

	<div class="thanks">
		<p style="font-size: 16px;">The server key firebase is used to push notification when order status changed.</p>
		<p style="font-size: 12px;">(Firebase project -> Project Settings -> Cloud Messaging -> Server key)</p>
	</div>
	<form action="" method="post">
		<?php
		$serverKey = get_option( 'maanstore_firebase_server_key' );
		?>
		<div class="form-group" style="margin-top:10px;margin-bottom:40px">
			<textarea class="maanstore-update-firebase-server-key mstore_input"
					style="height: 120px"><?php echo esc_attr( $serverKey ); ?></textarea>
		</div>
	</form>

	<div class="thanks">
		<p style="font-size: 16px;">New Order Message</p>
	</div>
	<form action="" method="post">
		<?php
		$newOrderTitle = get_option( 'mstore_new_order_title' );
		if ( ! isset( $newOrderTitle ) || $newOrderTitle == false ) {
			$newOrderTitle = 'new Order';
		}
		$newOrderMsg = get_option( 'mstore_new_order_message' );
		if ( ! isset( $newOrderMsg ) || $newOrderMsg == false ) {
			$newOrderMsg = 'Hi() {{name}}, Congratulations, you have received a new order() ! ';
		}
		?>
		<div class="form-group" style="margin-top:10px;">
			<input type="text" placeholder="Title" value="<?php echo esc_attr( $newOrderTitle ); ?>"
				   class="mstore-update-new-order-title mstore_input">
		</div>
		<div class="form-group" style="margin-top:10px;margin-bottom:40px">
			<textarea placeholder="Message" class="mstore-update-new-order-message mstore_input"
					style="height: 120px"><?php echo esc_attr( $newOrderMsg ); ?></textarea>
		</div>
	</form>

	<!-- Generate Token -->
	<form action="" method="post">
		<?php
		if ( isset( $_POST['but_generate'] ) ) {
			$user   = wp_get_current_user();
			$cookie = generateCookieByUserId( $user->ID );
			?>
				<div class="form-group" style="margin-top:10px;margin-bottom:10px">
					<textarea class="mstore_input" style="height: 150px"><?php echo esc_attr( $cookie ); ?></textarea>
				</div>
				<?php
		}
		?>
		<button type="submit" class="mstore_button" name='but_generate'>Generate Token</button>
	</form>
	<!-- ./Generate Token -->

	<div class="thanks">
		<p style="font-size: 16px;">Order Status Changed Message</p>
	</div>
	<form action="" method="post">
		<?php
		$statusOrderTitle = get_option( 'mstore_status_order_title' );

		if ( ! isset( $statusOrderTitle ) || $statusOrderTitle == false ) {
			$statusOrderTitle = 'Order Status Changed';
		}

		$statusOrderMsg = get_option( 'mstore_status_order_message' );

		if ( ! isset( $statusOrderMsg ) || $statusOrderMsg == false ) {
			$statusOrderMsg = 'Hi {{name}}, Your order: #{{orderId}} changed from {{prevStatus}} to {{nextStatus}}';
		}
		?>
		<div class="form-group" style="margin-top:10px;">
			<input type="text" placeholder="Title" value="<?php echo esc_attr( $statusOrderTitle ); ?>"
				class="mstore-update-status-order-title mstore_input">
		</div>
		<div class="form-group" style="margin-top:10px;margin-bottom:40px">
			<textarea placeholder="Message" class="mstore-update-status-order-message mstore_input"
					style="height: 120px"><?php echo esc_attr( $statusOrderMsg ); ?></textarea>
		</div>
	</form>

	<form action="" enctype="multipart/form-data" method="post">
		<button type="submit" class="mstore_button" name='but_submit'>Save</button>
	</form>
	<?php
}
?>
