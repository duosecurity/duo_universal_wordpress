<?php
namespace Duo\DuoUniversalWordpress;

require_once 'duouniversal-helperinterface.php';
class DuoUniversal_WordpressHelper implements DuoUniversal_WordpressHelperInterface {

	public function set_transient( $name, $value, $expiration ) {
		return set_transient( $name, $value, $expiration );
	}
	public function get_transient( $name ) {
		return get_transient( $name );
	}
	public function delete_transient( $name ) {
		return delete_transient( $name );
	}
	public function wp_get_current_user() {
		return wp_get_current_user();
	}
	public function wp_logout() {
		return wp_logout();
	}
	public function wp_redirect( $url ) {
		return wp_redirect( $url );
	}
	public function WP_Error( $code, $message = '', $data = '' ) {
		return new \WP_Error( $code, $message, $data );
	}
	public function WP_User( $id, $name = '', $site_id = '' ) {
		return new \WP_User( $id, $name, $site_id );
	}
	public function remove_action( $hook_name, $callback, $priority ) {
		return remove_action( $hook_name, $callback, $priority );
	}
	public function wp_authenticate_username_password( $user, $username, $password ) {
		return wp_authenticate_username_password( $user, $username, $password );
	}
	public function wp_authenticate_email_password( $user, $email, $password ) {
		return wp_authenticate_email_password( $user, $email, $password );
	}
	public function is_multisite() {
		return is_multisite();
	}
	public function get_current_site() {
		return get_current_site();
	}
	public function is_user_logged_in() {
		return is_user_logged_in();
	}
	public function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}
	public function apply_filters( $hook_name, $value, $args ) {
		return apply_filters( $hook_name, $value, $args );
	}
	public function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook_name, $callback, $priority, $accepted_args );
	}
	public function get_option( $name, $default_value ) {
		return get_option( $name, $default_value );
	}
	public function get_site_option( $name, $default_value ) {
		return get_site_option( $name, $default_value );
	}
	public function WP_Roles() {
		return WP_Roles();
	}
	public function settings_fields( $setting ) {
		return settings_fields( $setting );
	}
	public function do_settings_sections( $setting ) {
		return do_settings_sections( $setting );
	}
	public function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		return add_settings_error( $setting, $code, $message, $type );
	}
	public function before_last_bar( $s ) {
		return before_last_bar( $s );
	}
	public function plugin_basename( $file ) {
		return plugin_basename( $file );
	}
	public function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		return add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $position );
	}
	public function add_site_option( $option, $value ) {
		return add_site_option( $option, $value );
	}
	public function add_settings_field( $id, $title, $callback, $page, $section, $args = array() ) {
		return add_settings_field( $id, $title, $callback, $page, $section, $args );
	}
	public function add_settings_section( $id, $title, $callback, $page, $args = array() ) {
		return add_settings_section( $id, $title, $callback, $page, $args );
	}
	public function register_setting( $option_group, $option_name, $args = array() ) {
		return register_setting( $option_group, $option_name, $args );
	}
	public function update_site_option( $option, $value ) {
		return update_site_option( $option, $value );
	}
	public function esc_attr_e( ?string $text, ?string $domain = 'default' ) {
		return esc_attr_e( $text, $domain );
	}
	public function esc_attr( ?string $text ) {
		return esc_attr( $text );
	}
	public function esc_html( $text ) {
		return esc_html( $text );
	}
	public function translate( $text, $domain = 'default' ) {
		return __( $text, $domain );
	}
	public function sanitize_url( $url, $protocols = null ) {
		return sanitize_url( $url, $protocols );
	}
	public function sanitize_text_field( $str ) {
		return sanitize_text_field( $str );
	}
}
