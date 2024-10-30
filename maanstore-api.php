<?php
/**
 * Plugin Name: MaanStore API
 * Plugin URI: https://wordpress.org/plugins/maanstore-api/
 * Description: The MaanStore API Plugin which is used for REST API configurations of mobile apps created by MaanTheme
 * Version: 1.0.1
 * Author: MaanTheme
 * Author URI: https://profiles.wordpress.org/maantheme/
 * Text Domain: maanstore-api
 */

defined( 'ABSPATH' ) || wp_die( 'Can\'t access directly!' );

// Media Upload.
require plugin_dir_path( __FILE__ ) . 'includes/api-handlers/media-upload.php';

// Flutter API.
require plugin_dir_path( __FILE__ ) . 'includes/flutter-api/maan-flutter-config.php';


