<?php
/**
 * Plugin Name: Disable Users
 * Plugin URI:  http://wordpress.org/extend/disable-users
 * Description: This plugin provides the ability to disable specific user accounts.
 * Version:     1.0.5
 * Author:      Jared Atchison
 * Author URI:  http://jaredatchison.com 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author     Jared Atchison
 * @version    1.0.5
 * @package    JA_DisableUsers
 * @copyright  Copyright (c) 2015, Jared Atchison
 * @link       http://jaredatchison.com
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

final class ja_disable_users {

	/**
	 * Initialize all the things
	 *
	 * @since 1.0.0
	 */
	function __construct() {

		// Actions
		add_action( 'init',                             array( $this, 'load_textdomain'                 )        );
		add_action( 'show_user_profile',                array( $this, 'use_profile_field'               )        );
		add_action( 'edit_user_profile',                array( $this, 'use_profile_field'               )        );
		add_action( 'personal_options_update',          array( $this, 'user_profile_field_save'         )        );
		add_action( 'edit_user_profile_update',         array( $this, 'user_profile_field_save'         )        );
		add_action( 'wp_login',                         array( $this, 'user_login'                      ), 10, 2 );
		add_action( 'manage_users_custom_column',       array( $this, 'manage_users_column_content'     ), 10, 3 );
		add_action( 'admin_footer-users.php',	        array( $this, 'manage_users_css'                )        );

		// Filters
		add_filter( 'login_message',                    array( $this, 'user_login_message'              )        );
		add_filter( 'manage_users_columns',             array( $this, 'manage_users_columns'	        )        );
		add_filter( 'wp_mail',                          array( $this, 'do_not_email_disabled_users'     )        );
	}

	/**
	 * Load the textdomain so we can support other languages
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		$domain = 'ja_disable_users';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add the field to user profiles
	 *
	 * @since 1.0.0
	 * @param object $user
	 */
	public function use_profile_field( $user ) {

		// Only show this option to users who can delete other users
		if ( !current_user_can( 'edit_users' ) )
			return;
		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label for="ja_disable_user"><?php _e(' Disable User Account', 'ja_disable_users' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="ja_disable_user" id="ja_disable_user" value="1" <?php checked( 1, get_the_author_meta( 'ja_disable_user', $user->ID ) ); ?> />
						<span class="description"><?php _e( 'If checked, the user cannot login with this account.' , 'ja_disable_users' ); ?></span>
					</td>
				</tr>
			<tbody>
		</table>
		<?php
	}

	/**
	 * Saves the custom field to user meta
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function user_profile_field_save( $user_id ) {

		// Only worry about saving this field if the user has access
		if ( !current_user_can( 'edit_users' ) )
			return;

		if ( !isset( $_POST['ja_disable_user'] ) ) {
			$disabled = 0;
		} else {
			$disabled = $_POST['ja_disable_user'];
		}
	 
		update_user_meta( $user_id, 'ja_disable_user', $disabled );
	}

	/**
	 * Checks if a user is disabled
	 *
	 * @since 1.1.0
	 * @param int $user_id The user ID to check
	 * @param object $user
     * @return boolean true if disabled, false if enabled
	 */
	private function is_user_disabled( $user_id ) {

		// Get user meta
		$disabled = get_user_meta( $user_id, 'ja_disable_user', true );

		// Is the use logging in disabled?
		if ( $disabled == '1' ) {
		    return true;
		}

		return false;
    }

	/**
	 * After login check to see if user account is disabled
	 *
	 * @since 1.0.0
	 * @param string $user_login
	 * @param object $user
	 */
	public function user_login( $user_login, $user = null ) {

		if ( !$user ) {
			$user = get_user_by('login', $user_login);
		}
		if ( !$user ) {
			// not logged in - definitely not disabled
			return;
		}

		// Is the use logging in disabled?
		if ( $this->is_user_disabled( $user->ID ) ) {
			// Clear cookies, a.k.a log user out
			wp_clear_auth_cookie();

			// Build login URL and then redirect
			$login_url = site_url( 'wp-login.php', 'login' );
			$login_url = add_query_arg( 'disabled', '1', $login_url );
			wp_redirect( $login_url );
			exit;
		}
	}

	/**
	 * Show a notice to users who try to login and are disabled
	 *
	 * @since 1.0.0
	 * @param string $message
	 * @return string
	 */
	public function user_login_message( $message ) {

		// Show the error message if it seems to be a disabled user
		if ( isset( $_GET['disabled'] ) && $_GET['disabled'] == 1 ) 
			$message =  '<div id="login_error">' . apply_filters( 'ja_disable_users_notice', __( 'Account disabled', 'ja_disable_users' ) ) . '</div>';

		return $message;
	}

	/**
	 * Add custom disabled column to users list
	 *
	 * @since 1.0.3
	 * @param array $defaults
	 * @return array
	 */
	public function manage_users_columns( $defaults ) {

		$defaults['ja_user_disabled'] = __( 'Disabled', 'ja_disable_users' );
		return $defaults;
	}

	/**
	 * Set content of disabled users column
	 *
	 * @since 1.0.3
	 * @param empty $empty
	 * @param string $column_name
	 * @param int $user_ID
	 * @return string
	 */
	public function manage_users_column_content( $empty, $column_name, $user_ID ) {

		if ( $column_name == 'ja_user_disabled' ) {
			if ( $this->is_user_disabled( $user_ID ) ) {
				return __( 'Disabled', 'ja_disable_users' );
			}
		}
	}

	/**
	 * Specifiy the width of our custom column
	 *
	 * @since 1.0.3
 	 */
	public function manage_users_css() {
		echo '<style type="text/css">.column-ja_user_disabled { width: 80px; }</style>';
	}

	/***
     * Returns an array containing the email addresses of all disabled users.
     * If there are no disabled users, returns null.
     *
     * Using the 'ja_disable_users_disabled_email_addresses' filter you can modify the
     * the returned array.
     *
     * @since 1.1.0
	 * @return null|array
	 */
	private function get_disabled_users_email_addresses() {
        global $wpdb;
	    $sql = "SELECT user_email FROM {$wpdb->prefix}users u INNER JOIN {$wpdb->prefix}usermeta um ON u.ID = um.user_id AND um.meta_key = 'ja_disable_user' WHERE um.meta_value = '1'";

	    return apply_filters('ja_disable_users_disabled_email_addresses', $wpdb->get_col( $sql ) );
    }

	/**
     * Removes any disabled user's email addresses from the To: email header.
     * Works with comma separated values, or an array for to $args['to'] value.
     *
     * @since 1.1.0
	 * @param array $args A compacted array of wp_mail() arguments, including the "to" email,
	 *                    subject, message, headers, and attachments values.
	 *
	 * @return array The updated array of arguments
	 */
	public function do_not_email_disabled_users( $args ) {

	    if ( ! isset( $args['to'] ) ) {
	        return $args;
        }

        // Get an array of the users being sent to
        if ( is_array( $args['to'] ) ) {
	        $to = $args['to'];
        } else {
	        $to = array_map( 'trim', explode( ',', $args['to'] ) );
        }

	    // Get an array of disabled users
        $disabled_users = $this->get_disabled_users_email_addresses();

        // Update wp_mail() with the remaining email addresses
        $args['to'] = implode(',', array_diff( $to, $disabled_users ) );

        return $args;

    }
}
new ja_disable_users();