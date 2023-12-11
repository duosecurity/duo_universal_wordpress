<?php
/**
 * Handle settings for plugin
 *
 * This class handles the sanitization, validation and display
 * of the various settings associated with this plugin.
 *
 * @link https://duo.com/docs/wordpress
 *
 * @package Duo Universal
 * @since 1.0.0
 */

namespace Duo\DuoUniversalWordpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once 'class-duouniversal-utilities.php';
const SECRET_PLACEHOLDER = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

class DuoUniversal_Settings {
	public function __construct(
		$duo_utils
	) {
		$this->duo_utils = $duo_utils;
	}
	function duo_settings_page() {
		$this->duo_utils->duo_debug_log( 'Displaying duo setting page' );
		?>
	<div class="wrap">
		<h2>Duo Universal Authentication</h2>
		<?php if ( is_multisite() ) { ?>
			<form action="ms-options.php" method="post">
		<?php } else { ?>
			<form action="options.php" method="post">
		<?php } ?>
			<?php \settings_fields( 'duo_universal_settings' ); ?>
			<?php \do_settings_sections( 'duo_universal_settings' ); ?>
			<p class="submit">
				<input name="Submit" type="submit" class="button primary-button" value="<?php \esc_attr_e( 'Save Changes' ); ?>" />
			</p>
		</form>
	</div>
		<?php
	}

	function duo_settings_client_id() {
		$client_id = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_id' ) );
		echo "<input id='duoup_client_id' name='duoup_client_id' size='40' type='text' value='" . \esc_attr( $client_id ) . "' />";
	}

	function duoup_client_id_validate( $client_id ) {
		$client_id = sanitize_text_field( $client_id );
		if ( strlen( $client_id ) !== 20 ) {
			\add_settings_error( 'duoup_client_id', '', 'Client ID is not valid' );
			$current_id = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_id' ) );
			if ( $current_id ) {
				return $current_id;
			}
			return '';
		} else {
			return $client_id;
		}
	}

	function duo_settings_client_secret() {
		$client_secret = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_secret' ) );
		if ( $client_secret ) {
			$value = SECRET_PLACEHOLDER;
		} else {
			$value = '';
		}
		echo "<input id='duoup_client_secret' name='duoup_client_secret' size='40' type='password' value='" . \esc_attr( $value ) . "' autocomplete='off' />";
	}

	function duoup_client_secret_validate( $client_secret ) {
		$client_secret  = sanitize_text_field( $client_secret );
		$current_secret = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_secret' ) );
		if ( strlen( $client_secret ) !== 40 ) {
			\add_settings_error( 'duoup_client_secret', '', 'Client secret is not valid' );
			if ( $current_secret ) {
				return $current_secret;
			} else {
				return '';
			}
		} elseif ( SECRET_PLACEHOLDER === $client_secret ) {
				return $current_secret;
		} else {
			return $client_secret;
		}
	}

	function duo_settings_host() {
		$host = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_api_host' ) );
		echo "<input id='duoup_api_host' name='duoup_api_host' size='40' type='text' value='" . \esc_attr( $host ) . "' />";
	}

	function duoup_api_host_validate( $host ) {
		$host = sanitize_text_field( $host );
		if ( ! preg_match( '/^api-[a-zA-Z\d\.-]*/', $host ) || str_starts_with( $host, 'api-api-' ) ) {
			\add_settings_error( 'duoup_api_host', '', 'Host is not valid' );
			$current_host = \esc_attr( $this->duo_utils->duo_get_option( 'duo_host' ) );
			if ( $current_host ) {
				return $current_host;
			}
			return '';
		}

		return $host;
	}

	function duo_settings_failmode() {
		$failmode = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_failmode', 'open' ) );
		echo '<select id="duoup_failmode" name="duoup_failmode" />';
		if ( 'open' === $failmode ) {
			echo '<option value="open" selected>Open</option>';
			echo '<option value="closed">Closed</option';
		} else {
			echo '<option value="open">Open</option>';
			echo '<option value="closed" selected>Closed</option';
		}
		echo '</select>';
	}

	function duoup_failmode_validate( $failmode ) {
		$failmode = sanitize_text_field( $failmode );
		if ( ! in_array( $failmode, array( 'open', 'closed' ), true ) ) {
			add_settings_error( 'duoup_failmode', '', 'Failmode value is not valid' );
			$current_failmode = $this->duo_utils->duo_get_option( 'duoup_failmode', 'open' );
			return $current_failmode;
		}
		return $failmode;
	}

	function duo_settings_roles() {
		$wp_roles = $this->duo_utils->duo_get_roles();
		$roles    = $wp_roles->get_names();
		$newroles = array();
		foreach ( $roles as $key => $role ) {
			$newroles[ \before_last_bar( $key ) ] = \before_last_bar( $role );
		}

		$selected = $this->duo_utils->duo_get_option( 'duoup_roles', $newroles );

		foreach ( $wp_roles->get_names() as $key => $role ) {
			// create checkbox for each role
			echo ( '' .
			"<input id='duoup_roles' " .
				"name='duoup_roles[" . \esc_attr( $key ) . "]' " .
				"type='checkbox' " .
				"value='" . \esc_attr( $role ) . "' " .
				( in_array( $role, $selected, true ) ? 'checked' : '' ) .
			'/>' .
			\esc_html( $role ) .
			'<br />' );
		}
	}

	function duoup_roles_validate( $options ) {
		// return empty array
		if ( ! is_array( $options ) || empty( $options ) || ( false === $options ) ) {
			return array();
		}
		$wp_roles = $this->duo_utils->duo_get_roles();

		$valid_roles = $wp_roles->get_names();
		// otherwise validate each role and then return the array
		foreach ( $options as $opt => $value ) {
			if ( ! array_key_exists( $opt, $valid_roles ) ) {
				unset( $options[ $opt ] );
			} else {
				$options[ $opt ] = sanitize_text_field( $value );
			}
		}
		return $options;
	}

	function duo_settings_text() {
		echo '<p>To use this plugin you must have an account with Duo Security.</p>';
		echo "<p>See the <a target='_blank' href='https://www.duosecurity.com/docs/wordpress'>Duo for WordPress guide</a> to enable Duo two-factor authentication for your WordPress logins.</p>";
		echo '<p>You can retrieve your Client ID, Client Secret, and API hostname by logging in to the Duo Admin Panel.</p>';
		echo '<p>Note: After enabling the plugin, you will be immediately prompted for second factor authentication.</p>';
	}

	function duo_settings_xmlrpc() {
		$val = '';
		if ( $this->duo_utils->duo_get_option( 'duoup_xmlrpc', 'off' ) === 'off' ) {
			$val = 'checked';
		}
		echo "<input id='duoup_xmlrpc' name='duoup_xmlrpc' type='checkbox' value='off' " . \esc_attr( $val ) . ' /> Yes<br />';
		echo 'Using XML-RPC bypasses two-factor authentication and makes your website less secure. We recommend only using the WordPress web interface for managing your WordPress website.';
	}

	function duoup_xmlrpc_validate( $option ) {
		$option = sanitize_text_field( $option );
		if ( 'off' === $option ) {
			return $option;
		}
		return 'on';
	}

	function duo_add_link( $links ) {
		$settings_link = '<a href="options-general.php?page=duo_universal_wordpress">' . \__( 'Settings', 'duo_universal_wordpress' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}


	function duo_add_page() {
		if ( ! is_multisite() ) {
			add_options_page( 'Duo Universal', 'Duo Universal', 'manage_options', 'duo_universal_wordpress', array( $this, 'duo_settings_page' ) );
		}
	}


	function duo_add_site_option( $option, $value = '' ) {
		// Add multisite option only if it doesn't exist already
		// With WordPress versions < 3.3, calling add_site_option will override old values
		if ( $this->duo_utils->duo_get_option( $option ) === false ) {
			\add_site_option( $option, $value );
		}
	}


	function duo_admin_init() {
		if ( is_multisite() ) {
			$wp_roles = $this->duo_utils->duo_get_roles();
			$roles    = $wp_roles->get_names();
			$allroles = array();
			foreach ( $roles as $key => $role ) {
				$allroles[ \before_last_bar( $key ) ] = \before_last_bar( $role );
			}

			$this->duo_add_site_option( 'duoup_client_id', '' );
			$this->duo_add_site_option( 'duoup_client_secret', '' );
			$this->duo_add_site_option( 'duoup_api_host', '' );
			$this->duo_add_site_option( 'duoup_failmode', '' );
			$this->duo_add_site_option( 'duoup_roles', $allroles );
			$this->duo_add_site_option( 'duoup_xmlrpc', 'off' );
		} else {
			\add_settings_section( 'duo_universal_settings', 'Main Settings', array( $this, 'duo_settings_text' ), 'duo_universal_settings' );
			\add_settings_field( 'duoup_client_id', 'Client ID', array( $this, 'duo_settings_client_id' ), 'duo_universal_settings', 'duo_universal_settings' );
			\add_settings_field( 'duoup_client_secret', 'Client Secret', array( $this, 'duo_settings_client_secret' ), 'duo_universal_settings', 'duo_universal_settings' );
			\add_settings_field( 'duoup_api_host', 'API hostname', array( $this, 'duo_settings_host' ), 'duo_universal_settings', 'duo_universal_settings' );
			\add_settings_field( 'duoup_failmode', 'Failmode', array( $this, 'duo_settings_failmode' ), 'duo_universal_settings', 'duo_universal_settings' );
			\add_settings_field( 'duoup_roles', 'Enable for roles:', array( $this, 'duo_settings_roles' ), 'duo_universal_settings', 'duo_universal_settings' );
			\add_settings_field( 'duoup_xmlrpc', 'Disable XML-RPC (recommended)', array( $this, 'duo_settings_xmlrpc' ), 'duo_universal_settings', 'duo_universal_settings' );

			\register_setting( 'duo_universal_settings', 'duoup_client_id', array( $this, 'duoup_client_id_validate' ) );
			\register_setting( 'duo_universal_settings', 'duoup_client_secret', array( $this, 'duoup_client_secret_validate' ) );
			\register_setting( 'duo_universal_settings', 'duoup_api_host', array( $this, 'duoup_api_host_validate' ) );
			\register_setting( 'duo_universal_settings', 'duoup_failmode', array( $this, 'duoup_failmode_validate' ) );
			\register_setting( 'duo_universal_settings', 'duoup_roles', array( $this, 'duoup_roles_validate' ) );
			\register_setting( 'duo_universal_settings', 'duoup_xmlrpc', array( $this, 'duoup_xmlrpc_validate' ) );
		}
	}

	function duo_mu_options() {
		$this->duo_utils->duo_debug_log( 'Displaying multisite settings' );

		?>
		<h3>Duo Security</h3>
		<table class="form-table">
			<?php $this->duo_settings_text(); ?></td></tr>
			<tr><th>Client ID</th><td><?php $this->duo_settings_client_id(); ?></td></tr>
			<tr><th>Client Secret</th><td><?php $this->duo_settings_client_secret(); ?></td></tr>
			<tr><th>API hostname</th><td><?php $this->duo_settings_host(); ?></td></tr>
			<tr><th>Failmode</th><td><?php $this->duo_settings_failmode(); ?></td></tr>
			<tr><th>Roles</th><td><?php $this->duo_settings_roles(); ?></td></tr>
			<tr><th>Disable XML-RPC</th><td><?php $this->duo_settings_xmlrpc(); ?></td></tr>
		</table>
		<?php
	}

	function duo_update_mu_options() {
		check_admin_referer( 'siteoptions' );

		if ( isset( $_POST['duoup_client_id'] ) ) {
			$client_id = $this->duoup_client_id_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_client_id'] ) ) );
			$result    = \update_site_option( 'duoup_client_id', $client_id );
		}

		if ( isset( $_POST['duoup_client_secret'] ) ) {
			$client_secret = $this->duoup_client_secret_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_client_secret'] ) ) );
			$result        = \update_site_option( 'duoup_client_secret', $client_secret );
		}

		if ( isset( $_POST['duoup_api_host'] ) ) {
			$host   = $this->duoup_api_host_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_api_host'] ) ) );
			$result = \update_site_option( 'duoup_api_host', $host );
		}

		if ( isset( $_POST['duoup_failmode'] ) ) {
			$failmode = $this->duoup_failmode_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_failmode'] ) ) );
			$result   = \update_site_option( 'duoup_failmode', $failmode );
		} else {
			$result = \update_site_option( 'duoup_failmode', 'open' );
		}

		if ( isset( $_POST['duoup_roles'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$roles  = $this->duoup_roles_validate( \wp_unslash( $_POST['duoup_roles'] ) );
			$result = \update_site_option( 'duoup_roles', $roles );
		} else {
			$result = \update_site_option( 'duoup_roles', array() );
		}

		if ( isset( $_POST['duoup_xmlrpc'] ) ) {
			$xmlrpc = $this->duoup_xmlrpc_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_xmlrpc'] ) ) );
			$result = \update_site_option( 'duoup_xmlrpc', $xmlrpc );
		} else {
			$result = \update_site_option( 'duoup_xmlrpc', 'on' );
		}
	}
}
