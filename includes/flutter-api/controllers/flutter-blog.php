<?php
require_once __DIR__ . '/flutter-base.php';
require_once __DIR__ . '/helpers/blog-helper.php';
/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package home
 */

class FlutterBlog extends FlutterBaseController {

	 /**
	 * Endpoint namespace
	 *
	 * @var string
	 */
	protected $namespace = 'maan-blog';


	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_flutter_blog_routes' ] );
	}

	public function register_flutter_blog_routes() {
		register_rest_route(
			$this->namespace,
			'/blog/dynamic',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_blog_from_dynamic_link' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/blog/create',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_blog' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/blog/comment',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_comment' ],
					'permission_callback' => function () {
						return parent::checkApiPermission();
					},
				],
			]
		);
	}

	function get_blog_from_dynamic_link( $request ) {
		$helper = new FlutterBlogHelper();
		return $helper->get_blog_from_dynamic_link( $request );
	}

	function create_blog( $request ) {
		$helper = new FlutterBlogHelper();
		return $helper->create_blog( $request );
	}

	function create_comment( $request ) {
		$helper = new FlutterBlogHelper();
		return $helper->create_comment( $request );
	}
}

new FlutterBlog();
