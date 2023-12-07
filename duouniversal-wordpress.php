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
 * Version: 1.0.0
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
	exit; // Exit if accessed directly
}

defined( 'ABSPATH' ) || die( 'Direct Access Denied' );

require_once 'class-duouniversal-settings.php';
require_once 'class-duouniversal-utilities.php';
require_once 'vendor/autoload.php';
require_once 'class-duouniversal-wordpressplugin.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversalWordpress;

$GLOBALS['duoup_debug'] = false;

$utils = new Duo\DuoUniversalWordpress\DuoUniversal_Utilities();

if ( $utils->duo_auth_enabled() ) {
	try {
		$duo_client = new Client(
			$utils->duo_get_option( 'duoup_client_id' ),
			$utils->duo_get_option( 'duoup_client_secret' ),
			$utils->duo_get_option( 'duoup_api_host' ),
			'',
		);
	} catch ( Exception $e ) {
		$utils->duo_debug_log( $e->getMessage() );
		$duo_client = null;
	}
} else {
	$duo_client = null;
}

$duoup_plugin = new DuoUniversal_WordpressPlugin(
	$utils,
	$duo_client
);

$settings = new DuoUniversal_Settings(
	$utils
);

if ( ! \is_multisite() ) {
	$plugin_name = plugin_basename( __FILE__ );
	add_filter( 'plugin_action_links_' . $plugin_name, array( $settings, 'duo_add_link' ), 10, 2 );
}


/*-------------XML-RPC Features-----------------*/

if ( $duoup_plugin->duo_utils->duo_get_option( 'duoup_xmlrpc', 'off' ) === 'off' ) {
	\add_filter( 'xmlrpc_enabled', '__return_false' );
}

/*-------------Register WordPress Hooks-------------*/

\add_action( 'init', array( $duoup_plugin, 'duo_verify_auth' ), 10 );

\add_action( 'clear_auth_cookie', array( $duoup_plugin, 'clear_current_user_auth' ), 10 );

\add_filter( 'authenticate', array( $duoup_plugin, 'duo_authenticate_user' ), 10, 3 );

// add single-site submenu option.
\add_action( 'admin_menu', array( $settings, 'duo_add_page' ) );
\add_action( 'admin_init', array( $settings, 'duo_admin_init' ) );

// Custom fields in multi-site network settings.
\add_action( 'wpmu_options', array( $settings, 'duo_mu_options' ) );
\add_action( 'update_wpmu_options', array( $settings, 'duo_update_mu_options' ) );
