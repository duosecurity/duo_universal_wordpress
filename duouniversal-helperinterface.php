<?php
/**
 * Interface for WordPress wrapper
 *
 * Gives a common interface for WordPress implementations and adapters
 * as well as serving as a list of all WordPress functions used by this plugin.
 *
 * @link https://duo.com/docs/wordpress
 *
 * @package Duo Universal
 * @since 1.0.0
 */

namespace Duo\DuoUniversalWordpress;

interface DuoUniversal_WordpressHelperInterface {

	public function set_transient( $name, $value, $expiration );
	public function get_transient( $name );
	public function delete_transient( $name );
	public function wp_get_current_user();
	public function wp_logout();
	public function wp_redirect( $url );
	public function WP_Error( $code, $message = '', $data = '' );
	public function WP_User( $id, $name = '', $site_id = '' );
	public function remove_action( $hook_name, $callback, $priority );
	public function wp_authenticate_username_password( $user, $username, $password );
	public function wp_authenticate_email_password( $user, $email, $password );
	public function is_multisite();
	public function get_current_site();
	public function is_user_logged_in();
	public function add_filter( $hook_name, $callbakc, $priority = 10, $accepted_args = 1 );
	public function apply_filters( $hook_name, $value, $args );
	public function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 );
	public function WP_Roles();
	public function get_option( $key, $default_value );
	public function get_site_option( $key, $default_value );
	public function settings_fields( $setting );
	public function do_settings_sections( $setting );
	public function add_settings_error( $setting, $code, $message, $type = 'error' );
	public function before_last_bar( $s );
	public function plugin_basename( $file );
	public function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null );
	public function add_site_option( $option, $value );
	public function add_settings_field( $id, $title, $callback, $page, $section, $args = array() );
	public function add_settings_section( $id, $title, $callback, $page, $args = array() );
	public function register_setting( $option_group, $option_name, $args = array() );
	public function update_site_option( $option, $value );
	public function esc_attr_e( ?string $text, ?string $domain = 'default' );
	public function esc_attr( ?string $text );
	public function esc_html( $text );
	public function translate( $text, $domain = 'default' );
	public function sanitize_url( $url, $protocols = null );
	public function sanitize_text_field( $str );
}
