<?php
/**
 * Plugin Name: Disable Users
 * Plugin URI:  http://wordpress.org/extend/disable-users
 * Description: This plugin provides the ability to disable specific user accounts.
 * Version:     1.2.0
 * Author:      Jared Atchison, Stephen Schrauger
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
 * @author     Jared Atchison, Stephen Schrauger
 * @version    1.2.0
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

		// Plugin Settings
		// Register the 'settings' page
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Add a link from the plugin page to this plugin's settings page
		add_filter( 'plugin_row_meta', array( $this, 'plugin_action_links' ), 10, 2 );

		// Modify the All Users query to hide disabled users by default (if preference set)
		add_filter('pre_user_query', array($this, 'user_query_exceptions' ) );

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


		if (esc_attr( get_option( 'ja-disable-users-setting-hide-disabled' ))) {
			$title = __("Click to show disabled accounts.");
		} else {
			$title = __("Click to hide disabled accounts.");
		}
		$toggle_link = '<a href="?toggle_disabled=1" title="' . $title . '">*</a>';
		$defaults['ja_user_disabled'] = __( 'Disabled' . $toggle_link, 'ja_disable_users' );
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
	 * Tells WordPress about a new page and what function to call to create it
	 *
	 * @since 1.1.0
	 */
	public function add_plugin_page() {
		// This page will be under "Settings" menu. add_options_page is merely a WP wrapper for add_submenu_page specifying the 'options-general' menu as parent
		add_options_page(
			"Disable Users Settings", // page title
			"Disable Users Settings", // menu title
			"manage_options", // user capability required to edit these settings
			"ja-disable-users-settings", // new page slug
			array(
				$this,
				'create_settings_page'
			) // since we are putting settings on our own page, we also have to define how to print out the settings
		);
	}

	/**
	 * Adds a link to this plugin's setting page directly on the WordPress plugin list page
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public function plugin_action_links( $links, $file ) {
		if ( strpos( __FILE__, $file ) !== false ) {
			$links = array_merge(
				$links,
				array(
					'settings' => '<a href="' . admin_url( 'options-general.php?page=ja-disable-users-settings' ) . '">' . __( 'Settings', 'ja-disable-users-settings') . '</a>'
				)
			);
		}

		return $links;
	}

	/**
	 * Tells WordPress how to output the page
	 *
	 * @since 1.2.0
	 */
	public function create_settings_page() {
		?>
		<div class="wrap" >

			<h2 >Disable Users - Settings</h2 >

			<form method="post" action="options.php" >
				<?php
				settings_fields( 'ja-disable-users-settings-group' );
				do_settings_sections( 'ja-disable-users-settings' );
				submit_button();
				?>
			</form >
		</div >
		<?php
	}

	/**
	 * Adds settings to settings page for this plugin.
	 * @since 1.2.0
	 */
	public function admin_init() {


		add_settings_section(
			'ja-disable-users-settings-section', // unique name of section
			'Disable Users - Settings', // start of section text shown to user
			'', // Extra text after section title
			'ja-disable-users-settings' // what page this section is on
		);

		$this->add_setting('ja-disable-users-setting-hide-disabled', __("Hide disabled users by default on All Users")); // adds a checkbox that lets users view All Users minus any disabled users
	}

	/**
	 * Adds a setting
	 *
	 * @param $setting_name
	 * @param $label
	 */
	public function add_setting( $setting_id, $label ) {
		// add setting, and register it
		add_settings_field(
			$setting_id,  // Unique ID used to identify the field
			$label,  // The label to the left of the option.
			array($this, 'settings_input_checkbox'),   // The function responsible for rendering checkboxes
			'ja-disable-users-settings',                         // The page on which this option will be displayed
			'ja-disable-users-settings-section',         // The name of the section to which this field belongs
			array(   // The array of arguments to pass to the callback. These 3 are referenced in setting_input_checkbox.
			         'id'      => $setting_id,
			         'label'   => $label,
			         'value'   => esc_attr( get_option( $setting_id ))
			)
		);
		register_setting(
			'ja-disable-users-settings-group',
			$setting_id
		//array( $this, 'sanitize' ) // sanitize function
		);

	}

	/*
	**
	* Creates the HTML code that is printed for each setting input
	*
	* @param $args
	*/
	public function settings_input_checkbox( $args ) {
		// Note the ID and the name attribute of the element should match that of the ID in the call to add_settings_field.
		// Because we only call register_setting once, all the options are stored in an array in the database. So we
		// have to name our inputs with the name of an array. ex <input type="text" id=option_key name="option_group_name[option_key]" />.
		// WordPress will automatically serialize the inputs that are in this array form and store it under
		// the option_group_name field. Then get_option will automatically unserialize and grab the value already set and pass it in via the $args as the 'value' parameter.
		if ($args[ 'value' ]) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}

		$html = '';

		// create a hidden variable with the same name and no value. if the box is unchecked, the hidden value will be POSTed.
		// If the value is checked, only the checkbox will be sent.
		// This way, we don't have to uncheck everything server-side and then re-check the POSTed values.
		// This is particularly useful to prevent preferences from being deleted if a post type is removed from a theme's code.
		// If we just unchecked everything, old post types would lose their preferences; if they are later reactivated, the preference
		// would be gone. This way, the preference persists.
		$html .= '<input type="hidden"   id="' . $args[ 'id' ] . '" name="' . $args[ 'id' ] . '" value=""/>';
		$html .= '<input type="checkbox" id="' . $args[ 'id' ] . '" name="' . $args[ 'id' ] . '" value="' . ( $args[ 'id' ] ) . '" ' . $checked . '/>';

		// Here, we will take the first argument of the array and add it to a label next to the input
		$html .= '<label for="' . $args[ 'id' ] . '"> ' . $args[ 'label' ] . '</label>';
		echo $html;
	}

	public function user_query_exceptions($query_obj){
		// if preference is set to hide disabled users by default, then alter the All Users query to prevent disabled users from showing up
		global $wpdb;
		$str_hide_query = " AND ID NOT IN (SELECT user_id from $wpdb->usermeta WHERE meta_key = 'ja_disable_user' AND meta_value = '1')";

		if (esc_attr( get_option( 'ja-disable-users-setting-hide-disabled' ))) {
			// preference set to hide disabled users by default. now check if they are manually showing them.
			if ( empty( $_REQUEST[ 'toggle_disabled' ] ) ) {
				// 'show_disabled' not specified, so exclude disabled users
				// search for ja_disable_user=true. if ID is listed, they are disabled.
				$query_obj->query_where .= $str_hide_query;
			}
		} else {
			// preference set to show disabled users by default. now check if they are manually hiding them.
			if ( !empty( $_REQUEST[ 'toggle_disabled' ] ) ) {
				// 'hide_disabled' specified, so exclude disabled users
				// search for ja_disable_user=true. if ID is listed, they are disabled.
				$query_obj->query_where .= $str_hide_query;
			}
		}
		return $query_obj;

	}
}
new ja_disable_users();