<?php
class FlutterBaseController {
	/**
	 * Check permissions for the posts.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function sendError( $code, $message, $statusCode ) {
		return new WP_Error( $code, $message, [ 'status' => $statusCode ] );
	}

    //TODO: Remove this permission.
	public function checkApiPermission() {
		return get_option( 'maanstore_purchase_code' ) === '1' || get_option( 'maanstore_purchase_code' ) === true;
	}
}
