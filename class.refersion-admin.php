<?php
/*

Copyright 2017 Refersion, Inc. (email : helpme@refersion.com)

This file is part of Refersion for WooCommerce.

Refersion for WooCommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Refersion for WooCommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Refersion for WooCommerce. If not, see <http://www.gnu.org/licenses/>.

*/

class Refersion_Admin
{

	/**
	* Holds the values to be used in the fields callbacks
	*/
	private $options;
	private $menu_id;
	private $menu_name = 'refersion-navigation';

	/**
	* Start up
	*/
	public function __construct() { 

		if ( is_admin() ) {
			$options = get_option( 'refersion_settings' );
			add_action( 'admin_menu', array( 'Refersion_Admin', 'add_plugin_page' ) );
			add_action( 'admin_init', array( 'Refersion_Admin', 'page_init' ) );   
		}

	}

	/**
	* Display message upon plug-in activation
	*/
	public static function activation_message() {

		if ( !is_array( get_option( 'refersion_settings' ) ) ) {

			$message = __( 'Refersion for WooCommerce is almost ready.', 'refersion-for-woocommerce' );
			$link = sprintf( __( '<a href="%1$s">Click here to configure the plugin</a>.', 'refersion-setting-admin' ), 'admin.php?page=refersion-setting-admin' );
			echo sprintf( '<div id="refersion-message-warning" class="updated fade"><p><strong>%1$s</strong> %2$s</p></div>', $message, $link );

		}

	}
  
	/**
	* Add options page
	*/
	public static function add_plugin_page() {

		// This page will be under the "WooCommerce" menu
		add_submenu_page(
			'woocommerce',
			'Refersion',
			'Refersion',
			'manage_options',
			'refersion-setting-admin',
			array( 'Refersion_Admin', 'create_admin_page' )
		);

	}

	/**
	* Add Settings link to Plugins page
	*/
	public static function add_plugins_settings($links) {
		$url = get_admin_url()."admin.php?page=refersion-setting-admin";
		$settings_link = '<a href="'.$url.'">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	* Options page callback
	*/
	public static function create_admin_page() {

		global $refersion_settings_page;

		// Does the user have permission to do this?
		if ( ! current_user_can('manage_options') ) {
			return;
		}

		// Success message after updated
		if (isset($_GET['settings-updated'])) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			_e ( ' Settings saved. ');
			echo '</p></div>';
		}

		// Set class property
		$refersion_settings_page->options = get_option( 'refersion_settings' );
?>

		<div class="wrap">

			<div>

				<img src="<?php echo plugins_url( 'refersion_logo.png', __FILE__ ); ?>" alt="Refersion" />
				
				<p>
					<?php _e( 'In order to automatically setup Refersion tracking on your WooCommerce shop, the following settings must be filled out. For help, visit our <a href="https://support.refersion.com/" target="_blank">Knowledge Base</a>.', 'refersion-for-woocommerce' ); ?>
				</p>

				<p>
					<?php _e( 'This plugin requires a <a href="https://www.refersion.com" target="_blank">Refersion</a> account. If you do not already have an account, you can <a href="https://www.refersion.com/pricing" target="_blank">sign up</a> right now.', 'refersion-for-woocommerce' ); ?>
				</p>

			</div>

			<div>

				<form method="post" action="options.php">

					<?php
						
						settings_fields( 'refersion_option_group' );   
						do_settings_sections( 'refersion-setting-admin' );

						submit_button(); 
					?>

				</form>

			</div>

		</div>	

<?php
	}

	/**
	* Register and add settings
	*/
	public static function page_init() {

		register_setting(
			'refersion_option_group',
			'refersion_settings',
			array( 'Refersion_Admin', 'sanitize' )
		);

		add_settings_section(
			'setting_section_id',
			'Configuration',
			array( 'Refersion_Admin', 'print_section_info' ),
			'refersion-setting-admin'
		);

		add_settings_field(
			'refersion_status', 
			'Refersion tracking enabled?', 
			array( 'Refersion_Admin', 'refersion_status_callback' ), 
			'refersion-setting-admin', 
			'setting_section_id'
		);

		add_settings_field(
			'refersion_public_api_key',
			'Your Refersion public API key',
			array( 'Refersion_Admin', 'refersion_public_api_key_callback' ),
			'refersion-setting-admin',
			'setting_section_id'
		);
		
		add_settings_field(
			'refersion_secret_api_key',
			'Your Refersion secret API key',
			array( 'Refersion_Admin', 'refersion_secret_api_key_callback' ),
			'refersion-setting-admin',
			'setting_section_id'
		);

		add_settings_field(
			'refersion_subdomain',
			'Your Refersion Subdomain (optional and for advanced use only)',
			array( 'Refersion_Admin', 'refersion_subdomain_callback' ),
			'refersion-setting-admin',
			'setting_section_id'
		);

	}

	/**
	* Sanitize each setting field as needed
	*
	* @param array $input Contains all settings fields as array keys
	*/
	public static function sanitize( $input ) {

		$new_input = array();

		if( !empty( $input['refersion_public_api_key'] ) ) {
			$new_input['refersion_public_api_key'] = trim($input['refersion_public_api_key']);
		}

		if( !empty( $input['refersion_secret_api_key'] ) ) {
			$new_input['refersion_secret_api_key'] = trim($input['refersion_secret_api_key']);
		}

		if( !empty( $input['refersion_subdomain'] ) ) {
			$new_input['refersion_subdomain'] = trim($input['refersion_subdomain']);
		}

		if( !empty( $input['refersion_status'] ) && in_array( (int) $input['refersion_status'] , array(0, 1), TRUE )  ) {
			$new_input['refersion_status'] = $input['refersion_status'];
		}

		return $new_input;

	}

	/** 
	* A heading
	*/
	public static function print_section_info() {
		print 'Enter your settings below:';
	}

	/** 
	* Public key field
	*/
	public static function refersion_public_api_key_callback() {

		global $refersion_settings_page;

		printf(
			'<input type="text" id="refersion_public_api_key" name="refersion_settings[refersion_public_api_key]" value="%s" style="width:300px;" />',
			isset( $refersion_settings_page->options['refersion_public_api_key'] ) ? esc_attr( $refersion_settings_page->options['refersion_public_api_key']) : ''
		);

	}

	/** 
	* Secret key field
	*/
	public static function refersion_secret_api_key_callback() {

		global $refersion_settings_page;

		printf(
			'<input type="text" id="refersion_secret_api_key" name="refersion_settings[refersion_secret_api_key]" value="%s" style="width:300px;" />',
			isset( $refersion_settings_page->options['refersion_secret_api_key'] ) ? esc_attr( $refersion_settings_page->options['refersion_secret_api_key']) : ''
		);

	}

	/** 
	* Subdomain
	*/
	public static function refersion_subdomain_callback() {

		global $refersion_settings_page;

		printf(
			'<input type="text" id="refersion_subdomain" name="refersion_settings[refersion_subdomain]" value="%s" style="width:300px;" />',
			isset( $refersion_settings_page->options['refersion_subdomain'] ) ? esc_attr( $refersion_settings_page->options['refersion_subdomain']) : ''
		);

	}

	/** 
	* Enabled field
	*/
	public static function refersion_status_callback()  {

		global $refersion_settings_page;

		$a = 'selected=selected';
		$b = '';  

		if(isset( $refersion_settings_page->options['refersion_status'] )){
		
			if( $refersion_settings_page->options['refersion_status'] == 1 ){
				$a = ''; 
				$b = 'selected=selected';
			}

		}

		echo '<select id="secret_api_key" name="refersion_settings[refersion_status]"><option value="0" '.$a.'>No, turn off Refersion reporting</option><option value="1" '.$b.'>Yes, send orders to Refersion</option></select> '; 
	
	}

}