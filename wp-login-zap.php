<?php
/**
 * Plugin Name: WP Login Zap
 * Description: Sends user login data to Zapier when user logs in.
 * Version: 1.0.0
 * Author: Sanjeev Aryal
 * Author URI: http://www.sanjeebaryal.com.np
 * Text Domain: wp-login-zap
 */
defined( 'ABSPATH' ) or die( "No script kiddies please!" );

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
			           	<th scope="row"><?php echo esc_html__( 'Webhook URL', 'wp-login-zap' );?></th>
			        		<td><input style="width:35%" type="text" name="webhook_url" value ="<?php echo esc_html( $webhook_url ); ?>" class="wp-login-zap-webhook-url" /><br/>
			        		</td>
			        </tr>
			        <?php do_action( 'wp_login_zap_settings' );?>
		            <?php wp_nonce_field( 'wp_login_zap_settings', 'wp_login_zap_settings_nonce' );?>

			</table>
			    <?php submit_button(); ?>
		</form>
	<?php
}

/**
 * Save Settings.
 * s
 * @since 1.0.0
 */
function wplz_save_settings() {

	if( isset( $_POST['wp_login_zap_settings_nonce'] ) ) {
		if( ! wp_verify_nonce( $_POST['wp_login_zap_settings_nonce'], 'wp_login_zap_settings' )
			) {
			   print 'Nonce Failed!';
			   exit;
		} else {
			$webhook_url = isset( $_POST['webhook_url'] ) ? $_POST['webhook_url'] : '';
			$message     = esc_html__( 'Done!', 'wp-login-zap');
			$class       = 'notice-success';

			update_option( 'webhook_url', $webhook_url );

			if ( filter_var( $webhook_url, FILTER_VALIDATE_URL ) === FALSE) {
				$message = esc_html__( 'Not a valid webhook URL.' );
				$class   = 'error';
			}

			add_action( 'admin_notices', function() use ( $message, $class ) {
			    ?>
				    <div class="notice <?php echo $class;?> is-dismissible">
				        <p><?php echo $message; ?></p>
				    </div>
				<?php
			});
		}
	}
}

/**
 * Send data to Zapier.
 *
 * @since  1.0.0
 */
function wplz_send_data_to_zapier( array $data ) {

	$webhook_url = get_option( 'webhook_url' );
    $headers 	 = array( 'Accept: application/json', 'Content-Type: application/json' );
	$args 		 = apply_filters( 'wp_login_zap_arguments', array(
					'method'  => 'POST',
				    'headers' => $headers,
  	     	        'body'    => json_encode( $data ),
				));

	$result  = wp_remote_post( $webhook_url, $args );

    if ( is_wp_error( $result ) ) {
        error_log( print_r( $result->get_error_message() ) );
    }

    do_action( 'wp_login_zap_data_sent', $result, $webhook_url );
}

/**
 * Action when user logs in.
 * 
 * @param  string $user_login Username
 * 
 * @param  obj $user
 *
 * @since  1.0.0
 */
function wplz_login( $user_login, $user ) {
	$data = apply_filters( 'wp_login_zap_data_sending', array(
		'ID' => $user->ID,
		'Username' => $user_login,
		'Email' => $user->user_email,
		apply_filters( 'wp_login_zap_logged_in_time', 'Current Time' )  => current_time( 'mysql' )
	));

	wplz_send_data_to_zapier( $data );	
}