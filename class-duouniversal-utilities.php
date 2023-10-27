<?php
namespace Duo\DuoUniversalWordpress;

class DuoUniversal_Utilities {

	public function __construct(
		$wordpress_helper
	) {
		$this->wordpress_helper = $wordpress_helper;
	}

	function xmlrpc_enabled() {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	function duo_get_roles() {
		global $wp_roles;
		// $wp_roles may not be initially set if WordPress < 3.3
		$wp_roles = isset( $wp_roles ) ? $wp_roles : $this->wordpress_helper->WP_Roles();
		return $wp_roles;
	}

	function duo_auth_enabled() {
		if ( $this->xmlrpc_enabled() ) {
			$this->duo_debug_log( 'Found an XMLRPC request. XMLRPC is allowed for this site. Skipping second factor' );
			return false; // allows the XML-RPC protocol for remote publishing
		}

		if ( $this->duo_get_option( 'duoup_client_id', '' ) === '' || $this->duo_get_option( 'duoup_client_secret', '' ) == ''
			|| $this->duo_get_option( 'duoup_api_host', '' ) === ''
		) {
			return false;
		}
		return true;
	}

	function duo_role_require_mfa( $user ) {
		$wp_roles  = $this->duo_get_roles();
		$all_roles = array();
		foreach ( $wp_roles->get_names() as $k => $r ) {
			$all_roles[ $k ] = $r;
		}

		$duoup_roles = $this->duo_get_option( 'duoup_roles', $all_roles );

		/*
		 * WordPress < 3.3 does not include the roles by default
		 * Create a User object to get roles info
		 * Don't use get_user_by()
		 */
		if ( ! isset( $user->roles ) ) {
			$user = $this->wordpress_helper->WP_User( 0, $user->user_login );
		}

		/*
		 * Mainly a workaround for multisite login:
		 * if a user logs in to a site different from the one
		 * they are a member of, login will work however
		 * it appears as if the user has no roles during authentication
		 * "fail closed" in this case and require duo auth
		 */
		if ( empty( $user->roles ) ) {
			return true;
		}

		foreach ( $user->roles as $role ) {
			if ( array_key_exists( $role, $duoup_roles ) ) {
				return true;
			}
		}
		return false;
	}

	function duo_get_uri() {
		// Workaround for IIS which may not set REQUEST_URI, or QUERY parameters.
		// sanitize_url can be used due to its special handling of relative
		// paths (for which protocols are not required/enforced), and REQUEST_URI
		// always includes the leading slash in the URI path.
		if ( ! isset( $_SERVER['REQUEST_URI'] )
			|| ( ! empty( $_SERVER['QUERY_STRING'] ) && ! strpos( $this->wordpress_helper->sanitize_url( $_SERVER['REQUEST_URI'] ), '?', 0 ) )
		) {
			$current_uri = $this->wordpress_helper->sanitize_url( substr( $_SERVER['PHP_SELF'], 1 ) );
			if ( isset( $_SERVER['QUERY_STRING'] ) ) {
				$current_uri = $this->wordpress_helper->sanitize_url( $current_uri . '?' . $_SERVER['QUERY_STRING'] );
			}

			return $current_uri;
		} else {
			return $this->wordpress_helper->sanitize_url( $_SERVER['REQUEST_URI'] );
		}
	}

	function duo_get_option( $key, $default = '' ) {
		if ( $this->wordpress_helper->is_multisite() ) {
			return $this->wordpress_helper->get_site_option( $key, $default );
		} else {
			return $this->wordpress_helper->get_option( $key, $default );
		}
	}

	function duo_debug_log( $message ) {
		global $duo_debug;
		if ( $duo_debug ) {
			error_log( 'Duo debug: ' . $message );
		}
	}
}
