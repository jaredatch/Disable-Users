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
		add_action( 'init',                       array( $this, 'load_textdomain'             )        );
		add_action( 'rest_api_init',              array( $this, 'register_rest_endpoints'     )        );
		add_action( 'show_user_profile',          array( $this, 'use_profile_field'           )        );
		add_action( 'edit_user_profile',          array( $this, 'use_profile_field'           )        );
		add_action( 'personal_options_update',    array( $this, 'user_profile_field_save'     )        );
		add_action( 'edit_user_profile_update',   array( $this, 'user_profile_field_save'     )        );
		add_action( 'wp_login',                   array( $this, 'user_login'                  ), 10, 2 );
		add_action( 'manage_users_custom_column', array( $this, 'manage_users_column_content' ), 10, 3 );
		add_action( 'admin_footer-users.php',	  array( $this, 'manage_users_css'            )        );

		// Filters
		add_filter( 'login_message',              array( $this, 'user_login_message'          )        );
		add_filter( 'manage_users_columns',       array( $this, 'manage_users_columns'	      )        );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_api_access'             )        );
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
	 * @todo  ADD @since VERSION ON DEPLOYMENT
	 * @param int $user_id The user ID to check
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
			if ( get_the_author_meta( 'ja_disable_user', $user_ID )	== 1 ) {
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

	/**
	 * Register endpoints to allow for the disabling and enabling of users
     * via the WordPress REST API.
     *
     * @todo add @since on merge.
	 */
	public function register_rest_endpoints() {

		register_rest_route( 'wp/v2', '/users/(?P<id>\d+)/enable', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_enable_user'),
			'permission_callback' => function () {
				return current_user_can( 'edit_users' );
			},
		) );

		register_rest_route( 'wp/v2', '/users/(?P<id>\d+)/disable', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_disable_user'),
			'permission_callback' => function () {
				return current_user_can( 'edit_users' );
			},
		) );

	}

	/**
     * Check if the current user can modify user accounts.
     * Required for the permission check in the register_rest_endpoints();
     *
	 * @todo add @since on merge.
	 * @return bool
	 */
	public function can_edit_users() {

	    global $current_user;

	    print_r( $current_user );

        return current_user_can( 'edit_users' );
    }

	/**
     * REST Endpoint Handler
     *
     * Enable a user account
     *
	 * @todo add @since on merge.
	 * @param WP_REST_Request $request The REST API Request
     * @return null|WP_Error Returns HTTP Status Code 204 on Success, or a WP_Error on Failure
     *
	 */
	public function rest_enable_user( WP_REST_Request $request ) {

		$user_id = $request->get_param( 'id' );
		return $this->handle_rest_request( $user_id, '0' );
	}

	/**
	 * REST Endpoint Handler
	 *
	 * Disable a user account
	 *
	 * @todo add @since on merge.
	 * @param WP_REST_Request $request The REST API Request
	 * @return null|WP_Error Returns HTTP Status Code 204 on Success, or a WP_Error on Failure
	 *
	 */
	public function rest_disable_user( WP_REST_Request $request ) {

		$user_id = $request->get_param( 'id' );
		return $this->handle_rest_request( $user_id, '1' );
	}

	/**
	 * REST Endpoint Handler
	 *
	 * Enable a user account
	 *
	 * @todo add @since on merge.
	 * @param int $user_id The User ID to be modified
     * @param int $disabled 1 to enable the user account, 0 to disable it
	 * @return null|WP_Error Returns HTTP Status Code 204 on Success, or a WP_Error on Failure
	 *
	 */
	private function handle_rest_request( $user_id, $disabled ) {

		// Double check that the user account exists
		$user = get_user_by( 'id', $user_id );

		// If the user does not exist, return a 404 error
		if ( null == $user ) {
			return new WP_Error('ja_disable_users_user_not_found', 'The requested user does not exist', array( 'status' => 404 ) );
		}

		update_user_meta( $user_id, 'ja_disable_user', $disabled );

		status_header( 204 );
		exit;
    }

	/**
	 * Returning an authentication error if a user who is logged in is also disabled.
	 *
	 * @since 1.1.0
	 * @param WP_Error|null|bool
	 * @return mixed
	 */
	function rest_api_access( $access ) {

		if ( is_user_logged_in() && $this->is_user_disabled( get_current_user_id() ) ) {

			return new WP_Error( 'rest_cannot_access', __( 'User disabled.', 'disable-users' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return $access;
	}
}
new ja_disable_users();
