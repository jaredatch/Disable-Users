<?php
/**
 * Plugin Name: Disable Users
 * Plugin URI:  http://wordpress.org/extend/disable-users
 * Description: This plugin provides the ability to disable specific user accounts.
 * Version:     2.0
 * Author:      Jared Atchison, khromov
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
 * @version    2.0
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
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'show_user_profile', array( $this, 'use_profile_field' ) );
		add_action( 'edit_user_profile', array( $this, 'use_profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'user_profile_field_save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_profile_field_save' ) );
		add_action( 'manage_users_custom_column', array( $this, 'manage_users_column_content' ), 10, 3 );
		add_action( 'admin_footer-users.php', array( $this, 'manage_users_css' ) );
		add_action( 'admin_post_ja_disable_user', array( $this, 'toggle_user' ) );
		add_action( 'admin_post_ja_enable_user', array( $this, 'toggle_user' ) );

		// Filters
		add_filter( 'manage_users_columns', array( $this, 'manage_users_columns' ) );
		add_filter( 'wpmu_users_columns', array( $this, 'manage_users_columns' ) );
		add_filter( 'authenticate', array( $this, 'user_login' ), 1000, 3 );

	}

	/**
	 * Gets the capability associated with banning a user
	 * @return string
	 */
	function get_edit_cap() {
		return is_multisite() ? 'manage_network_users' : 'edit_users';
	}

	/**
	 * Toggles the users disabled status
	 *
	 * @since 1.1.0
	 */
	function toggle_user() {
		$nonce_name = ( isset( $_GET['action'] ) && $_GET['action'] === 'ja_disable_user' ) ? 'ja_disable_user_' : 'ja_enable_user_';
		if ( current_user_can( $this->get_edit_cap() ) && isset( $_GET['ja_user_id'] ) && isset( $_GET['ja_nonce'] ) && wp_verify_nonce( $_GET['ja_nonce'], $nonce_name . $_GET['ja_user_id'] ) ) {

			//Don't disable super admins
			if ( is_multisite() && is_super_admin( (int) $_GET['ja_user_id'] ) ) {
				wp_die( __( 'Super admins can not be disabled.', 'ja_disable_users' ) );
			}

			update_user_meta( (int) $_GET['ja_user_id'], 'ja_disable_user', ( $nonce_name === 'ja_disable_user_' ? true : false ) );

			//Log out user - https://wordpress.stackexchange.com/questions/184161/destroy-user-sessions-based-on-user-id
			$sessions = WP_Session_Tokens::get_instance( (int) $_GET['ja_user_id'] );
			$sessions->destroy_all();

			//Redirect back
			if ( isset( $_GET['ja_return_url'] ) ) {
				wp_safe_redirect( $_GET['ja_return_url'] );
				exit;
			} else {
				wp_die( __( 'The user has been updated.', 'ja_disable_users' ) );
			}
		} else {
			wp_die( __( 'You are not allowed to perform this action, or your nonce expired.', 'ja_disable_users' ) );
		}
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
	 *
	 * @param object $user
	 */
	public function use_profile_field( $user ) {

		//Super admins can not be banned
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return;
		}

		// Only show this option to users who can delete other users
		if ( ! current_user_can( $this->get_edit_cap() ) ) {
			return;
		}
		?>
        <table class="form-table">
            <tbody>
            <tr>
                <th>
                    <label for="ja_disable_user"><?php _e( 'Disable User Account', 'ja_disable_users' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" name="ja_disable_user" id="ja_disable_user"
                           value="1" <?php checked( 1, get_the_author_meta( 'ja_disable_user', $user->ID ) ); ?> />
                    <span class="description"><?php _e( 'If checked, the user will not be able to login with this account.', 'ja_disable_users' ); ?></span>
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
	 *
	 * @param int $user_id
	 */
	public function user_profile_field_save( $user_id ) {

		//Don't disable super admins
		if ( is_multisite() && is_super_admin( $user_id ) ) {
			return;
		}

		// Only worry about saving this field if the user has access
		if ( ! current_user_can( $this->get_edit_cap() ) ) {
			return;
		}

		if ( ! isset( $_POST['ja_disable_user'] ) ) {
			$disabled = false;
		} else {
			$disabled = (int) $_POST['ja_disable_user'] ? true : false;
		}

		update_user_meta( $user_id, 'ja_disable_user', $disabled );
	}

	/**
	 * @param $user
	 * @param $username
	 * @param $password
	 *
	 * @return mixed
	 */
	public function user_login( $user, $username, $password ) {

		//If this is a valid user, check if the user is disabled before logging in
		if ( is_a( $user, 'WP_User' ) ) {
			$disabled = get_user_meta( $user->ID, 'ja_disable_user', true );

			// Is the use logging in disabled?
			if ( $disabled ) {
				return new WP_Error( 'ja_user_disabled', apply_filters( 'js_user_disabled_message', __( '<strong>ERROR</strong>: Account disabled.', 'ja_disable_users' ) ) );
			}
		}

		//Pass on any existing errors
		return $user;
	}

	/**
	 * Add custom disabled column to users list
	 *
	 * @since 1.0.3
	 *
	 * @param array $defaults
	 *
	 * @return array
	 */
	public function manage_users_columns( $defaults ) {

		$defaults['ja_user_disabled'] = __( 'User status', 'ja_disable_users' );

		return $defaults;
	}

	/**
	 * Set content of disabled users column
	 *
	 * @since 1.0.3
	 *
	 * @param empty $empty
	 * @param string $column_name
	 * @param int $user_ID
	 *
	 * @return string
	 */
	public function manage_users_column_content( $empty, $column_name, $user_ID ) {

		if ( $column_name == 'ja_user_disabled' ) {

			//Super admins can't be disabled
			if ( is_super_admin( $user_ID ) ) {
				return '<span class="ja-user-enabled">&#x2714;</span>';
			}

			$user_disabled = get_the_author_meta( 'ja_disable_user', $user_ID );
			$nonce         = $user_disabled ? wp_create_nonce( 'ja_enable_user_' . $user_ID ) : wp_create_nonce( 'ja_disable_user_' . $user_ID );
			$return_url    = urlencode_deep( ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] );

			if ( $user_disabled ) {
				$link_url = admin_url( "admin-post.php?action=ja_enable_user&ja_user_id={$user_ID}&ja_nonce={$nonce}&ja_return_url={$return_url}&message=1" );

				return '<span class="ja-user-disabled">&#x2718;</span><br><a href="' . esc_attr__( $link_url ) . '">' . __( 'Enable', 'ja_disable_users' ) . '</a>';
			} else {
				$link_url = admin_url( "admin-post.php?action=ja_disable_user&ja_user_id={$user_ID}&ja_nonce={$nonce}&ja_return_url={$return_url}&message=1" );

				return '<span class="ja-user-enabled">&#x2714;</span> <br><a href="' . esc_attr__( $link_url ) . '">' . __( 'Disable', 'ja_disable_users' ) . '</a>';
			}
		}

		return $empty;
	}

	/**
	 * Add basic styles
	 *
	 * @since 1.0.3
	 */
	public function manage_users_css() {
		?>
        <style type="text/css">
            .column-ja_user_disabled {
                width: 80px;
            }

            span.ja-user-enabled {
                font-size: 30px;
                color: green;
            }

            span.ja-user-disabled {
                font-size: 30px;
                color: red;
            }
        </style>
		<?php
	}
}

new ja_disable_users();