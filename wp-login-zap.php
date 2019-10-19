<?php
/**
 * Plugin Name: WP Login Zap
 * Description: Sends user login data to Zapier when user logs in.
 * Version: 1.0.1
 * Author: Sanjeev Aryal
 * Author URI: http://www.sanjeebaryal.com.np
 * Text Domain: wp-login-zap
 *
 * @package    WP Login Zap
 * @author     Sanjeev Aryal
 * @since      1.0.0
 * @license    GPL-3.0+
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'admin_menu', 'wplz_register_setting_menu' );
add_action( 'admin_init', 'wplz_save_settings' );
add_action( 'wp_login', 'wplz_login', 10, 2 );

/**
 * Add WP Login To Zapier Submenu
 *
 * @since  1.0.0
 */
function wplz_register_setting_menu() {
	add_options_page( 'WP Login Zap', 'WP Login Zap', 'manage_options', 'wp-login-zap', 'wplz_settings_page' );
}

/**
 * Settings page for WP Login To Zapier
 *
 * @since 1.0.0
 */
function wplz_settings_page() {

	$webhook_url = get_option( 'webhook_url' );
	?>
		<h2 class="wp-heading-inline"><?php esc_html_e( 'WP Login Zap Settings', 'wp-login-zap' ); ?></h2>
		<form method="post">
			<table class="form-table">
					<tr valign="top">
						   <th scope="row"><?php echo esc_html__( 'Webhook URL', 'wp-login-zap' ); ?></th>
							<td><input style="width:35%" type="url" name="webhook_url" value ="<?php echo esc_url( $webhook_url ); ?>" class="wp-login-zap-webhook-url" /><br/>
							</td>
					</tr>
					<?php do_action( 'wp_login_zap_settings' ); ?>
					<?php wp_nonce_field( 'wp_login_zap_settings', 'wp_login_zap_settings_nonce' ); ?>

			</table>
				<?php submit_button(); ?>
		</form>
	<?php
}

/**
 * Save Settings.
 *
 * @since 1.0.0
 */
function wplz_save_settings() {

	if ( isset( $_POST['wp_login_zap_settings_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['wp_login_zap_settings_nonce'], 'wp_login_zap_settings' )
			) {
			   print 'Nonce Failed!';
			   exit;
		} else {
			$webhook_url = isset( $_POST['webhook_url'] ) ? esc_url_raw( $_POST['webhook_url'] ) : '';
			$message     = esc_html__( 'Done!', 'wp-login-zap' );
			$class       = 'notice-success';

			update_option( 'webhook_url', $webhook_url );

			if ( filter_var( $webhook_url, FILTER_VALIDATE_URL ) === false ) {
				$message = esc_html__( 'Not a valid webhook URL.' );
				$class   = 'error';
			}

			add_action(
				'admin_notices',
				function() use ( $message, $class ) {
					?>
					<div class="notice <?php echo $class; ?> is-dismissible">
						<p><?php echo $message; ?></p>
					</div>
					<?php
				}
			);
		}
	}
}

/**
 * Send data to Zapier.
 *
 * @param array $data Login data to send to Zapier.
 *
 * @since  1.0.0
 */
function wplz_send_data_to_zapier( array $data ) {

	$webhook_url = get_option( 'webhook_url' );

	if ( empty( $webhook_url ) ) {
		return;
	}

	$headers = array( 'Accept: application/json', 'Content-Type: application/json' );
	$args    = apply_filters(
		'wp_login_zap_arguments',
		array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => json_encode( $data ),
		)
	);

	$result = wp_remote_post( esc_url_raw( $webhook_url ), $args );

	if ( is_wp_error( $result ) ) {
		error_log( print_r( $result->get_error_message(), true ) );
	}

	do_action( 'wp_login_zap_data_sent', $result, $webhook_url );
}

/**
 * Action when user logs in.
 *
 * @param  $user_login Username.
 *
 * @param  $user User Object.
 *
 * @since  1.0.0
 */
function wplz_login( $user_login, $user ) {
	$data = apply_filters(
		'wp_login_zap_data_sending',
		array(
			esc_html__( 'ID', 'wp-login-zap' )       => $user->ID,
			esc_html__( 'Username', 'wp-login-zap' ) => $user_login,
			esc_html__( 'Email', 'wp-login-zap' )    => $user->user_email,
			apply_filters( 'wp_login_zap_logged_in_time', esc_html__( 'Logged-in Time', 'wp-login-zap' ) ) => current_time( 'mysql' ),
			esc_html__( 'User IP Address', 'wp-login-zap' ) => wplz_get_ip_address(),
			esc_html__( 'Browser', 'wp-login-zap' )  => wplz_get_browser(),
		)
	);

	wplz_send_data_to_zapier( $data );
}

/**
 * Get User IP Address.
 *
 * @see  https://stackoverflow.com/a/2031935/5608921
 *
 * @since  1.0.1
 *
 * @return string IP Address
 */
function wplz_get_ip_address() {

	$ip = '';

	foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
		if ( array_key_exists( $key, $_SERVER ) === true ) {
			foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
				$ip = trim( $ip ); // just to be safe

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
					return $ip;
				}
			}
		}
	}

	return $ip;
}

/**
 * Get User Browser Information.
 *
 * @see  https://www.php.net/manual/en/function.get-browser.php#101125
 *
 * @since  1.0.1
 *
 * @return string
 */
function wplz_get_browser() {

	$u_agent  = $_SERVER['HTTP_USER_AGENT'];
	$bname    = 'Unknown';
	$platform = 'Unknown';
	$Version  = '';

	// First get the platform?
	if ( preg_match( '/linux/i', $u_agent ) ) {
		$platform = 'linux';
	} elseif ( preg_match( '/macintosh|mac os x/i', $u_agent ) ) {
		$platform = 'mac';
	} elseif ( preg_match( '/windows|win32/i', $u_agent ) ) {
		$platform = 'windows';
	}

	// Next get the name of the useragent yes seperately and for good reason
	if ( preg_match( '/MSIE/i', $u_agent ) && ! preg_match( '/Opera/i', $u_agent ) ) {
		$bname = 'Internet Explorer';
		$ub    = 'MSIE';
	} elseif ( preg_match( '/Firefox/i', $u_agent ) ) {
		$bname = 'Mozilla Firefox';
		$ub    = 'Firefox';
	} elseif ( preg_match( '/Chrome/i', $u_agent ) ) {
		$bname = 'Google Chrome';
		$ub    = 'Chrome';
	} elseif ( preg_match( '/Safari/i', $u_agent ) ) {
		$bname = 'Apple Safari';
		$ub    = 'Safari';
	} elseif ( preg_match( '/Opera/i', $u_agent ) ) {
		$bname = 'Opera';
		$ub    = 'Opera';
	} elseif ( preg_match( '/Netscape/i', $u_agent ) ) {
		$bname = 'Netscape';
		$ub    = 'Netscape';
	}

	// finally get the correct version number
	$known   = array( 'Version', $ub, 'other' );
	$pattern = '#(?<browser>' . join( '|', $known ) .
	')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	if ( ! preg_match_all( $pattern, $u_agent, $matches ) ) {
		// we have no matching number just continue
	}

	// see how many we have
	$i = count( $matches['browser'] );
	if ( $i != 1 ) {
		// we will have two since we are not using 'other' argument yet
		// see if version is before or after the name
		if ( strripos( $u_agent, 'Version' ) < strripos( $u_agent, $ub ) ) {
			$version = $matches['version'][0];
		} else {
			$version = $matches['version'][1];
		}
	} else {
		$version = $matches['version'][0];
	}

	// check if we have a number
	if ( $version == null || $version == '' ) {
		$version = '?';}

	$browser = 'Browser: ' . $bname . ' ' . $version . ' on ' . $platform . '';

	return $browser;
}
