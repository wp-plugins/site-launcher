<?php
/*
 Plugin Name: Site Launcher
 Plugin URI: http://www.wickedcleverlabs.com/site-launcher
 Description: Lets you set a date to launch or suspend your site automatically. Lets you choose which admins have access to the plugin settings. Generates nicely customizable "coming soon" and 
"site suspended" pages. This plugin is based on the underConstruction plugin by <a href="http://masseltech.com/" target="_blank">Jeremy Massel</a>. If all you need is a "Coming Soon" page, <a 
href="https://wordpress.org/plugins/underconstruction/" target="_blank">underConstruction</a> is highly recommended.<br />. A complete description along with screenshots and usage instructions is 
<a href="http://www.wickedcleverlabs.com/site-launcher/" target="_blank">here</a>.
 Version: 0.7.4
 Author: Saill White
 Author URI: http://www.wickedcleverlabs.com/
 Text Domain: site-launcher
 */

/*
 This file is part of Site Launcher.
 Site Launcher is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, 
 (at your option) any later version.
 Site Launcher is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with Site Launcher.  If not, see <http://www.gnu.org/licenses/>.
 */

class Site_Launcher
{
	var $installed_folder = "";
	var $main_options_page = "site_launcher_main_options";

	function __construct()
	{
		$this->installed_folder = basename( dirname(__FILE__) );
		// add scripts and styles
		add_action( 'admin_print_styles', array($this, 'load_admin_styles' ) );
		//add_action( 'admin_print_scripts', array($this, 'output_admin_scripts' ), PHP_INT_MAX);
		wp_enqueue_script('scriptaculous');
		wp_register_script( 'site-launcher-js', WP_PLUGIN_URL.'/'.$this->installed_folder.'/site-launcher.dev.js' );
		wp_enqueue_script( 'site-launcher-js' );
		// color picker
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'site-launcher-color-picker-js', WP_PLUGIN_URL.'/'.$this->installed_folder.'/site-launcher-color-picker.js', array( 'wp-color-picker' ), false, true );
		
		add_action( 'template_redirect', array( $this, 'override_wp' ) );

		//add_action( 'plugins_loaded', array($this, 'site_launcher_init_translation' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
		
		//ajax
		add_action( 'wp_ajax_site_launcher_get_ip_address', array( $this, 'get_ip_address' ) );

	}

	function site_launcher()
	{
		$this->__construct();
	}

	function get_main_options_page()
	{
		return $this->main_options_page;
	}
	
	function load_admin_styles()
	{
		wp_register_style( 'site-launcher-admin', WP_PLUGIN_URL .'/'.$this->installed_folder.'/site-launcher-admin.css' );
		wp_enqueue_style( 'site-launcher-admin' );
		
		$userID = get_current_user_id();
		$allowed_admins = get_option( 'site_launcher_allowed_admins' );
		// if plugin has been used and current user is not on the allowed admin list, hide this plugin
		if ( $allowed_admins !== false )
		{
			if ( ! in_array($userID, $allowed_admins ) )
			{
				wp_register_style( 'site-launcher-not-auth', WP_PLUGIN_URL .'/'.$this->installed_folder.'/site-launcher-not-auth.css' );
				wp_enqueue_style( 'site-launcher-not-auth' );
			}
		}
	}
	
	function output_admin_scripts()
	{
		//don't do this if all-in-one-event-calendar is installed, until we figure out why it's incompatible.
		$plugins = get_plugins();
		foreach ( $plugins as $plugin )
		{
			if ( trim( $plugin['Name'] ) == 'All-in-One Event Calendar by Timely' ) return;
		}
		
		$admin_js = ob_get_contents();
		ob_end_clean();
		$skipjs_count= 0;
	
		// Use WordPress built-in version of jQuery
		$jquery_url = includes_url( 'js/jquery/jquery.js' );
		$admin_js = implode( '', array(
			"<script type='text/javascript' src='{$jquery_url}'></script>\n",
			"<script type='text/javascript'>
			window.onerror = function(msg, url, line){
				if ( url.match(/\.js$|\.js\?/) ) {
					if ( window.location.search.length > 0) {
						if ( window.location.search.indexOf(url) == -1 )
							window.location.search += '&skipjs[{$skipjs_count}]='+url;
					}
					else {
						window.location.search = '?skipjs[{$skipjs_count}]='+url;
					}
				}
				return true;
			};</script>\n",
			$admin_js
		) );
		$admin_js .= "
		<script type=\"text/javascript\">
		/* <![CDATA[ */
		(function($){
			var default_color = '#ccc',
				header_background_fields,
				header_text_fields,
				header_background_suspended_fields,
				header_text_suspended_fields				
				;

			function pickBackgroundColor(color) {
				$( '#name' ).css( 'color', color);
				$( '#desc' ).css( 'color', color);
				$( '#background_color' ).val(color);
			}
			function pickTextColor(color) {
				$( '#name' ).css( 'color', color);
				$( '#desc' ).css( 'color', color);
				$( '#text_color' ).val(color);
			}
			function pickBackgroundColorSuspended(color) {
				$( '#name' ).css( 'color', color);
				$( '#desc' ).css( 'color', color);
				$( '#background_color_suspended' ).val(color);
			}
			function pickTextColorSuspended(color) {
				$( '#name' ).css( 'color', color);
				$( '#desc' ).css( 'color', color);
				$( '#text_color_suspended' ).val(color);
			}
			function toggle_background() {
				var checked = $( '#display-header-background' ).prop( 'checked' ),
					background_color;
				header_background_fields.toggle( checked );
				if ( ! checked )
					return;
				background_color = $( '#background_color' );
				if ( '' == background_color.val().replace( '#', '' ) ) {
					background_color.val( default_color );
					pickBackgroundColor( default_color );
				} else {
					pickBackgroundColor( background_color.val() );
				}
			}
			function toggle_text() {
				var checked = $( '#display-header-text' ).prop( 'checked' ),
					text_color;
				header_text_fields.toggle( checked );
				if ( ! checked )
					return;
				text_color = $( '#text_color' );
				if ( '' == text_color.val().replace( '#', '' ) ) {
					text_color.val( default_color );
					pickTextColor( default_color );
				} else {
					pickTextColor( text_color.val() );
				}
			}
			function toggle_background_suspended() {
				var checked = $( '#display-header-background-suspended' ).prop( 'checked' ),
					background_color_suspended;
				header_background_suspended_fields.toggle( checked );
				if ( ! checked )
					return;
				background_color_suspended = $( '#background_color_suspended' );
				if ( '' == background_color_suspended.val().replace( '#', '' ) ) {
					background_color_suspended.val( default_color );
					pickBackgroundColorSuspended( default_color );
				} else {
					pickBackgroundColorSuspended( background_color_suspended.val() );
				}
			}
			function toggle_text_suspended() {
				var checked = $( '#display-header-text-suspended' ).prop( 'checked' ),
					text_color;
				header_text_suspended_fields.toggle( checked );
				if ( ! checked )
					return;
				text_color_suspended = $( '#text_color_suspended' );
				if ( '' == text_color.val().replace( '#', '' ) ) {
					text_color_suspended.val( default_color );
					pickTextColorSuspended( default_color );
				} else {
					pickTextColorSuspended( text_color_suspended.val() );
				}
			}
			$(document).ready(function() {
				var background_color = $( '#background_color' );
				header_background_fields = $( '.displaying-header-background' );
				background_color.wpColorPicker({
					change: function( event, ui ) {
						pickBackgroundColor( background_color.wpColorPicker( 'color' ) );
					},
					clear: function() {
						pickBackgroundColor( '' );
					}
				});
				$( '#display-header-background' ).click( toggle_background );
				
				var text_color = $( '#text_color' );
				header_text_fields = $( '.displaying-header-text' );
				text_color.wpColorPicker({
					change: function( event, ui ) {
						pickTextColor( text_color.wpColorPicker( 'color' ) );
					},
					clear: function() {
						pickTextColor( '' );
					}
				});				
				$( '#display-header-text' ).click( toggle_text );

				var background_color_suspended = $( '#background_color_suspended' );
				header_background_suspended_fields = $( '.displaying-header-background-suspended' );
				background_color_suspended.wpColorPicker({
					change: function( event, ui ) {
						pickBackgroundColorSuspended( background_color_suspended.wpColorPicker( 'color' ) );
					},
					clear: function() {
						pickBackgroundColorSuspended( '' );
					}
				});
				$( '#display-header-background-suspended' ).click( toggle_background_suspended );
				
				var text_color_suspended = $( '#text_color_suspended' );
				header_text_suspended_fields = $( '.displaying-header-text-suspended' );
				text_color_suspended.wpColorPicker({
					change: function( event, ui ) {
						pickTextColorSuspended( text_color_suspended.wpColorPicker( 'color' ) );
					},
					clear: function() {
						pickTextColorSuspended( '' );
					}
				});
				$( '#display-header-text-suspended' ).click( toggle_text_suspended );
					});

		})(jQuery);
		/* ]]> */
		</script>";
		$admin_js .= "
		<script type=\"text/javascript\">
			WebFontConfig = {
			google: { families: [ 'Special+Elite::latin', 'Playfair+Display::latin', 'Griffy::latin', 'Indie+Flower::latin', 'Open+Sans::latin',  'Poiret+One::latin', 'Philosopher::latin', 'Orbitron::latin', 'Patua+One::latin', 'Limelight::latin'] }
			};
			(function() {
			var wf = document.createElement('script');
			wf.src = ('https:' == document.location.protocol ? 'https' : 'http') +
			'://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js';
			wf.type = 'text/javascript';
			wf.async = 'true';
			var s = document.getElementsByTagName('script')[0];
			s.parentNode.insertBefore(wf, s);
		})(); </script>";
		
		echo $admin_js;

	}
	
	function init_translation()
	{
		load_plugin_textdomain( 'site-launcher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	
	
	function plugin_links($links, $file)
	{
		if ( $file == basename( dirname(__FILE__) ).'/'.basename(__FILE__) && function_exists("admin_url") )
		{
			//add settings 
			$manage_link = '<a href="'.admin_url( 'options-general.php?page='.$this->get_main_options_page() ).'">'.__( 'Settings' ).'</a>';
			array_unshift( $links, $manage_link );
		}
		return $links;
	}

	function show_admin()
	{
		require_once ( 'site-launcher-admin.php' );
	}

	function admin_menu()
	{
		$userID = get_current_user_id();
		$allowed_admins = get_option( 'site_launcher_allowed_admins' );
		// if plugin has not yet been used - assumed current user is installing it.
		if ( $allowed_admins === false )
		{
			/* Register our plugin page */
			$page = add_options_page( 'Site Launcher Settings', 'Site Launcher', 'activate_plugins', $this->main_options_page, array($this, 'show_admin' ) );

			/* Using registered $page handle to hook script load */
			add_action( 'admin_print_scripts-'.$page, array($this, 'enqueue_scripts' ) );
		}
		
		elseif ( ( is_user_logged_in() && in_array( $userID, $allowed_admins ) ) )
		{
			/* Register our plugin page */
			$page = add_options_page( 'Site Launcher Settings', 'Site Launcher', 'activate_plugins', $this->main_options_page, array($this, 'show_admin' ) );

			/* Using registered $page handle to hook script load */
			add_action( 'admin_print_scripts-'.$page, array($this, 'enqueue_scripts' ) );
		}
	}

	function enqueue_scripts()
	{
		/*
		 * Enqueue our scripts here
		 */
	
		wp_enqueue_script( 'site-launcher-js' );
		
	}
	

	function override_wp()
	{	
		if ( $this->get_plugin_mode() != 'live' && $this->get_plugin_mode() != 'site_scheduled_for_suspension' )
		{
			if ( ! is_user_logged_in() || ! current_user_can( 'read' ) )
			{
				if ( $this->get_plugin_mode() == 'coming_soon' )
				{
					$ip_array = get_option( 'site_launcher_ip_whitelist' );
				}
				elseif ( $this->get_plugin_mode() == 'site_suspended' )
				{
					$ip_array = get_option( 'site_launcher_ip_whitelist_suspended' );
				}
				
				if( !is_array($ip_array) )
				{
					$ip_array = array();
				}
				
			
				// if user is not on whitelist 
				if( !in_array( $_SERVER['REMOTE_ADDR'], $ip_array ) ){

					//send a 503 - service unavailable code
					header( 'HTTP/1.1 503 Service Unavailable' );

					require_once ( 'site-launcher-display.php' );
					$options = get_option( 'site_launcher_display_options' );
					display_site_down_page( $options, $this->get_plugin_mode() );
					die();
				}
			}
		}
	}



	function activate()
	{
		if (get_option( 'site_launcher_archive' ) )
		{
			//get all the options back from the archive
			$options = get_option( 'site_launcher_archive' );

			//put them back where they belong
			update_option( 'site_launcher_mode', $options['site_launcher_mode']);
			update_option( 'site_launcher_allowed_admins', $options['site_launcher_allowed_admins']);
			update_option( 'site_launcher_display_options', $options['site_launcher_display_options']);
			update_option( 'site_launcher_ip_whitelist', $options['site_launcher_ip_whitelist']);
			update_option( 'site_launcher_ip_whitelist_suspended', $options['site_launcher_ip_whitelist_suspended']);
			update_option( 'site_launcher_launch_date', $options['site_launcher_launch_date']);
			update_option( 'site_launcher_suspend_date', $options['site_launcher_suspend_date']);
			update_option( 'site_launcher_users_to_demote', $options['site_launcher_users_to_demote']);
			update_option( 'site_launcher_users_have_been_demoted', $options['site_launcher_users_have_been_demoted']);
			delete_option( 'site_launcher_archive' );
		}
	}

	function deactivate()
	{
		
		//get all the options. store them in an array
		$options = array();
		$options['site_launcher_mode'] = get_option( 'site_launcher_mode' );
		$options['site_launcher_allowed_admins'] = get_option( 'site_launcher_allowed_admins' );
		$options['site_launcher_display_options'] = get_option( 'site_launcher_display_options' );
		$options['site_launcher_ip_whitelist'] = get_option( 'site_launcher_ip_whitelist' );
		$options['site_launcher_ip_whitelist_suspended'] = get_option( 'site_launcher_ip_whitelist_suspended' );
		$options['site_launcher_launch_date'] = get_option( 'site_launcher_launch_date' );
		$options['site_launcher_suspend_date'] = get_option( 'site_launcher_suspend_date' );
		$options['site_launcher_users_to_demote'] = get_option( 'site_launcher_users_to_demote' );
		$options['site_launcher_users_have_been_demoted'] = get_option( 'site_launcher_users_have_been_demoted' );
		//store the options all in one record, in case we ever reactivate the plugin
		update_option( 'site_launcher_archive', $options);

		//delete the separate ones
		delete_option( 'site_launcher_mode' );
		delete_option( 'site_launcher_allowed_admins' );
		delete_option( 'site_launcher_display_options' );
		delete_option( 'site_launcher_ip_whitelist' );
		delete_option( 'site_launcher_ip_whitelist_suspended' );
		delete_option( 'site_launcher_launch_date' );
		delete_option( 'site_launcher_suspend_date' );
		delete_option( 'site_launcher_users_to_demote' );
		delete_option( 'site_launcher_users_have_been_demoted' );
	}


	
	function get_display_option( $in_option_name )
	{
		$display_option = false;
		
		if ( get_option( 'site_launcher_display_options' ) !== false )
		{
			$options = get_option( 'site_launcher_display_options' );
			$display_option = stripslashes( $options[ $in_option_name ] );
		}
		
		return $display_option;
		
	}
	
	function get_allowed_admins()
	{
		// if option has never been set, update with current user id
		if ( get_option( 'site_launcher_allowed_admins' ) === false )
		{
			$userID = get_current_user_id();
			$allowed_admins = array( $userID );
			update_option( 'site_launcher_allowed_admins', $allowed_admins );
		}
		else
		{
			$allowed_admins = get_option( 'site_launcher_allowed_admins' );
		}
		return $allowed_admins;
	}
	
	function get_ip_address()
	{
		echo $_SERVER['REMOTE_ADDR'];
		die();
		
	}
	
	function get_status_message()
	{
		if ( $this->get_plugin_mode() == 'live' )
		{
			$message = _e( 'Website is LIVE!', 'site-launcher' );
		}
		elseif ( $this->get_plugin_mode() == 'coming_soon' )
		{
			if ( $this->get_site_launch_date() == 'never' )
			{
				$message = _e( 'Website is coming soon.', 'site-launcher' );
			}
			elseif ( $this->get_site_launch_date() == 'now' )
			{
				$message = _e( 'Website has been launched!', 'site-launcher' );
			}
			else
			{
				$message = _e( 'Website is scheduled for launch on: ', 'site-launcher' ).date ( 'l F jS  Y, \a\t g:i A', $this->get_site_launch_date().'.' );
			}
		}

		elseif ( $this->get_plugin_mode() == 'site_suspended' )
		{
			if ( $this->get_site_suspend_date() == 'now' )
			{
				$message = _e( 'Website has been suspended!', 'site-launcher' );
			}
		}
		elseif ( $this->get_plugin_mode() == 'site_scheduled_for_suspension' )
		{
			$message = _e( 'Website is scheduled to be suspended on: ', 'site-launcher' ).date ( 'l F jS  Y, \a\t g:i A', $this->get_site_suspend_date().'.' );
		}
		
		return $message;
	}

	// always check mode AFTER dates have been set
	function get_plugin_mode()
	{
		if ( get_option( 'site_launcher_mode' ) ) 
		{
			if  ( get_option( 'site_launcher_mode' ) == 'coming_soon' &&  ( $this->get_site_launch_date() !==  'now' ) )
			{
				$mode = 'coming_soon';
			}
			elseif ( ( get_option( 'site_launcher_mode' ) == 'site_suspended'  ||  get_option( 'site_launcher_mode' ) == 'site_scheduled_for_suspension' ) && ( $this->get_site_suspend_date() === 'now' ) )
			{
				$mode = 'site_suspended';
				$users_have_been_demoted = get_option( 'site_launcher_users_have_been_demoted' );
				if ( $users_have_been_demoted  === 'no' ) $this->demote_users();
			}
			elseif ( ( get_option( 'site_launcher_mode' ) == 'site_suspended'  ||  get_option( 'site_launcher_mode' ) == 'site_scheduled_for_suspension' ) && ( $this->get_site_suspend_date() !== 'now' ) )
			{
				$mode = 'site_scheduled_for_suspension';
			}
			else
			{
				$mode = 'live';
			}	
		}
		else	//if it's not set yet
		{
			$mode = 'live';
		}
		
		return $mode;
	}

	function get_site_launch_date()
	{
		// date is julian, check against current date and return false if launch date is in the past
		if ( get_option( 'site_launcher_launch_date' ) !== false )
		{	
			if ( is_numeric( get_option( 'site_launcher_launch_date' ) ) ) 
			{
				$current_time = current_time( 'timestamp' ); // use the WordPress blog time function
				if ( $current_time > get_option( 'site_launcher_launch_date' ) )
				{
					$launch_date = 'now';
				}
				else
				{
					$launch_date = get_option( 'site_launcher_launch_date' );
				}
			}
			elseif ( get_option( 'site_launcher_launch_date' ) == 'now' )
			{
				$launch_date = 'now'; // 'never' if we're in manual mode
			}
			else
			{
				$launch_date = 'never'; // 'never' if we're in manual mode
			}
		}
		else
		{
			$launch_date = 'now'; // default to live
		}
		
		return $launch_date;
	}
		
	function get_site_suspend_date()
	{
		// date is julian, check against current date and return false if suspend date is in the past
		if ( get_option( 'site_launcher_suspend_date' ) !== false )
		{	
			if ( is_numeric( get_option( 'site_launcher_suspend_date' ) ) )
			{
				$current_time = current_time( 'timestamp' ); // use the WordPress blog time function
				if ( $current_time > get_option( 'site_launcher_suspend_date' ))
				{
					$suspend_date = 'now';
				}
				else
				{
					$suspend_date = get_option( 'site_launcher_suspend_date' );
				}
			}
			else
			{
				$suspend_date = get_option( 'site_launcher_suspend_date' ); 
			}
		}
		else
		{
			$suspend_date = 'never'; // default to live
		}
		
		return $suspend_date;
	}
	
	function demote_users()
	{
		$user_id_role_strings = get_option( 'site_launcher_users_to_demote' );
		$role = get_role( 'subscriber' );
		$role->remove_cap( 'read' );
		if ( is_array( $user_id_role_strings ) )
		{
			foreach ( $user_id_role_strings as $id_role )
			{
				$bits = explode( '_', $id_role );
				$user_id = $bits[0];
				wp_update_user( array( 'ID' => $user_id, 'role' => 'subscriber' ) );
			}
			update_option( 'site_launcher_users_have_been_demoted', 'yes' );
		}
	}
		
}



function site_launcher_plugin_delete()
{
	delete_option( 'site_launcher_archive' );
}




global $site_launcher_plugin;
$site_launcher_plugin = new Site_Launcher();


register_uninstall_hook( __FILE__, 'site_launcher_plugin_delete' );

add_filter( 'plugin_action_links', array( $site_launcher_plugin,'plugin_links' ), 10, 2 );

?>
