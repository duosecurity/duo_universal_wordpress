<?php
/**
 * Duo Universal
 *
 * @package    Duo Universal
 * @author     Duo Security
 * @copyright  2023 Cisco Systems, Inc. and/or its affiliates
 * @license    Apache-2.0
 * @link https://duo.com/docs/wordpress
 *
 * Plugin Name: Duo Universal
 * Plugin URI: http://wordpress.org/extend/plugins/duo-universal/
 * Description: This plugin enables Duo universal authentication for WordPress logins.
 * Version: 1.2.1
 * Requires at least: 6.0.0
 * Requires PHP: 7.3.16
 * Author: Duo Security
 * Author URI: http://www.duosecurity.com
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: duo-universal
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once plugin_dir_path( __FILE__ ) . 'class-duouniversal-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'class-duouniversal-utilities.php';
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'class-duouniversal-wordpressplugin.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversalWordpress;

$utils = new Duo\DuoUniversalWordpress\DuoUniversal_Utilities();

if ( $utils->duo_auth_enabled() ) {
	try {
		$duo_client = new Client(
			$utils->duo_get_option( 'duoup_client_id' ),
			$utils->duo_get_option( 'duoup_client_secret' ),
			$utils->duo_get_option( 'duoup_api_host' ),
			'',
			true,
			null,
			$utils->duo_get_option( 'duoup_disable_ca_pinning', 'off' ) === 'on',
		);
		$utils->duo_debug_log( 'CA bundle version: ' . Client::CA_BUNDLE_VERSION );
		if ( $utils->duo_get_option( 'duoup_disable_ca_pinning', 'off' ) === 'on' ) {
			$utils->duo_debug_log( 'WARNING: CA pinning is disabled via configuration' );
		}
	} catch ( Exception $e ) {
		$utils->duo_debug_log( $e->getMessage() );
		$duo_client = null;
	}
} else {
	$duo_client = null;
}

$duoup_plugin = new Duo\DuoUniversalWordpress\DuoUniversal_WordpressPlugin(
	$utils,
	$duo_client
);

$settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings(
	$utils
);

/**
 * Extend User Agent string with WordPress-specific information
 */
function duo_extend_user_agent() {
	global $duo_client, $utils;

	if ( $duo_client && $utils && $utils->duo_auth_enabled() ) {
		try {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_data = get_plugin_data( __FILE__ );
			$plugin_version = $plugin_data['Version'];

			global $wp_version;

			$user_agent_extension = sprintf(
				'duo_universal_wordpress/%s (wordpress_version=%s)',
				$plugin_version,
				$wp_version
			);

			$duo_client->appendToUserAgent( $user_agent_extension );
		} catch ( Exception $e ) {
			$utils->duo_debug_log( $e->getMessage() );
		}
	}
}

if ( ! \is_multisite() ) {
	$plugin_name = plugin_basename( __FILE__ );
	add_filter( 'plugin_action_links_' . $plugin_name, array( $settings, 'duo_add_link' ), 10, 2 );
}


/*-------------XML-RPC Features-----------------*/

if ( $duoup_plugin->duo_utils->duo_get_option( 'duoup_xmlrpc', 'off' ) === 'off' ) {
	\add_filter( 'xmlrpc_enabled', '__return_false' );
}

/*-------------Register WordPress Hooks-------------*/

\add_action( 'init', 'duo_extend_user_agent' );

\add_action( 'init', array( $duoup_plugin, 'duo_verify_auth' ), 10 );

\add_action( 'clear_auth_cookie', array( $duoup_plugin, 'clear_current_user_auth' ), 10 );

\add_filter( 'authenticate', array( $duoup_plugin, 'duo_authenticate_user' ), 10, 3 );

// add single-site submenu option.
\add_action( 'admin_menu', array( $settings, 'duo_add_page' ) );
\add_action( 'admin_init', array( $settings, 'duo_admin_init' ) );

// Custom fields in multi-site network settings.
\add_action( 'wpmu_options', array( $settings, 'duo_mu_options' ) );
\add_action( 'update_wpmu_options', array( $settings, 'duo_update_mu_options' ) );
