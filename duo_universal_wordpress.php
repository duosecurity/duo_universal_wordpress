<?php
defined('ABSPATH') or die('Direct Access Denied');

/*
Plugin Name: Duo Universal
Plugin URI: http://wordpress.org/extend/plugins/duo-universal-wordpress/
Description: This plugin enables Duo universal authentication for WordPress logins.
Version: 1.0.0
Author: Duo Security
Author URI: http://www.duosecurity.com
License: Apache-2.0
*/

/*
Copyright (c) 2022 Cisco Systems, Inc. and/or its affiliates
All rights reserved.
*/

require_once 'duo_universal_settings.php';
require_once 'duo_universal_utilities.php';
require_once 'duo_universal_wordpress_helper.php';
require_once 'vendor/autoload.php';
require_once 'duo_universal_authentication.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversalWordpress;

$GLOBALS['DuoDebug'] = false;

$helper = new Duo\DuoUniversalWordpress\DuoUniversalWordpressHelper();
$utils = new Duo\DuoUniversalWordpress\DuoUniversalUtilities($helper);

if ($utils->duo_auth_enabled()) {
    try {
        $duo_client = new Client(
            $utils->duo_get_option('duo_client_id'),
            $utils->duo_get_option('duo_client_secret'),
            $utils->duo_get_option('duo_host'),
            "",
        );
    } catch (Exception $e) {
        $utils->duo_debug_log($e->getMessage());
        $duo_client = null;
    }
} else {
    $duo_client = null;
}

$plugin = new DuoUniversalWordpressPlugin(
    $utils,
    $duo_client
);

$settings = new Duo\DuoUniversalWordpress\DuoUniversalSettings(
    $utils
);

if (!$settings->wordpress_helper->is_multisite()) {
    $plugin_name = plugin_basename(__FILE__);
    add_filter('plugin_action_links_'.$plugin_name, array($settings, 'duo_add_link'), 10, 2);
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
