<?php
/**
 * Plugin Name: Disable Users
 * Plugin URI:  http://wordpress.org/extend/disable-users
 * Description: This plugin provides the ability to disable specific user accounts.
 * Version:     1.0.2
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
 * @version    1.0.2
 * @package    JA_DisableUsers
 * @copyright  Copyright (c) 2013, Jared Atchison
 * @link       http://jaredatchison.com
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

final class ja_disable_users {

	static $instance;

	/**
	 * Initialize all the things
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		
		self::$instance =& $this;
		
		// Actions
		add_action( 'init',                     array( $this, 'load_textdomain'         )        );
		add_action( 'show_user_profile',        array( $this, 'use_profile_field'       )        );
		add_action( 'edit_user_profile',        array( $this, 'use_profile_field'       )        );
		add_action( 'personal_options_update',  array( $this, 'user_profile_field_save' )        );
		add_action( 'edit_user_profile_update', array( $this, 'user_profile_field_save' )        );
		add_action( 'wp_login',                 array( $this, 'user_login'              ), 10, 2 );
		add_filter( 'login_message',            array( $this, 'user_login_message'      )        );
	}

	/**
	 * Load the textdomain so we can support other languages
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ja_disable_users', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
	 * After login check to see if user account is disabled
	 *
	 * @since 1.0.0
	 * @param string $user_login
	 * @param object $user
	 */
	public function user_login( $user_login, $user ) {

		// Get user meta
		$disabled = get_user_meta( $user->ID, 'ja_disable_user', true );
		
		// Is the use logging in disabled?
		if ( $disabled == '1' ) {
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

}
new ja_disable_users();