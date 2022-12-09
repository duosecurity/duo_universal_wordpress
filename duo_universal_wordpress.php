<?php
defined('ABSPATH') or die('Direct Access Denied');

/*
Plugin Name: Duo Universal Two-Factor Authentication
Plugin URI: http://wordpress.org/extend/plugins/duo-wordpress/
Description: This plugin enables Duo two-factor authentication for WordPress logins.
Version: 2.5.7
Author: Duo Security
Author URI: http://www.duosecurity.com
License: GPL2
*/

/*
Copyright 2014 Duo Security <duo_web@duosecurity.com>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once 'duo_settings.php';
require_once 'utilities.php';
require_once 'duo_wordpress_helper.php';
require_once 'vendor/autoload.php';
require_once 'authentication.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversalWordpress;

$GLOBALS['DuoDebug'] = false;

$helper = new Duo\DuoUniversalWordpress\WordpressHelper();
$utils = new Duo\DuoUniversalWordpress\Utilities($helper);

if ($utils->duo_auth_enabled()) {
    $duo_client = new Client(
        $utils->duo_get_option('duo_client_id'),
        $utils->duo_get_option('duo_client_secret'),
        $utils->duo_get_option('duo_host'),
        "",
    );
} else {
    $duo_client = null;
}

$plugin = new DuoUniversalWordpressPlugin(
    $utils,
    $duo_client
);

$settings = new Duo\DuoUniversalWordpress\Settings(
    $utils
);

if (!$settings->wordpress_helper->is_multisite()) {
    add_filter('plugin_action_links', array($settings, 'duo_add_link'), 10, 2);
}


/*-------------XML-RPC Features-----------------*/

if($plugin->duo_utils->duo_get_option('duo_xmlrpc', 'off') == 'off') {
    $helper->add_filter('xmlrpc_enabled', '__return_false');
}

/*-------------Register WordPress Hooks-------------*/

$helper->add_action('init', array($plugin, 'duo_verify_auth'), 10);

$helper->add_action('clear_auth_cookie', array($plugin, 'clear_current_user_auth'), 10);

$helper->add_filter('authenticate', array($plugin, 'duo_authenticate_user'), 10, 3);

//add single-site submenu option
$helper->add_action('admin_menu', array($settings, 'duo_add_page'));
$helper->add_action('admin_init', array($settings, 'duo_admin_init'));

// Custom fields in network settings
$helper->add_action('wpmu_options', array($settings, 'duo_mu_options'));
$helper->add_action('update_wpmu_options', array($settings, 'duo_update_mu_options'));
?>
