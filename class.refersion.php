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

class Refersion {

	/**
	* Grab the user's IP
	*/
	public static function refersion_get_client_ip() {

		$ipaddress = NULL;
		if (getenv('HTTP_CLIENT_IP')) $ipaddress = getenv('HTTP_CLIENT_IP');
		if (getenv('HTTP_X_FORWARDED_FOR')) $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		if (getenv('HTTP_X_FORWARDED')) $ipaddress = getenv('HTTP_X_FORWARDED');
		if (getenv('HTTP_FORWARDED_FOR')) $ipaddress = getenv('HTTP_FORWARDED_FOR');
		if (getenv('HTTP_FORWARDED')) $ipaddress = getenv('HTTP_FORWARDED');
		if (getenv('REMOTE_ADDR')) $ipaddress = getenv('REMOTE_ADDR');
				
		return $ipaddress;

	}

	/**
	* Generates a new cart_id for Refersion
	*/
	public static function refersion_generate_cart_id($length = REFERSION_CART_ID_LENGTH) {

		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;

	}

	/**
	* Check if Woocomerce already installed or not
	*/
	public static function check_woocomerce() {

		// Require parent plugin
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) AND current_user_can( 'activate_plugins' ) AND class_exists( 'WooCommerce' ) ) {
		
			// Stop activation redirect and show error
			wp_die('Sorry, but this plugin requires the Woocommerce to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
			
		}

	}

	/**
	* Create Refersion DB table upon plugin installation
	*/
	public static function refersion_activation_db() {

		global $wpdb;

		$table_name = REFERSION_WC_ORDERS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
				wc_order_id bigint(20) NOT NULL,
				created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				refersion_cart_id char(50) NOT NULL,
				refersion_sent_status enum('PENDING','SENT') DEFAULT 'PENDING',
				ip_address char(25) DEFAULT NULL,
				KEY refersion_sent_status (refersion_sent_status),
				KEY wc_order_id (wc_order_id),
				KEY refersion_cart_id (refersion_cart_id)
			) $charset_collate;
		";

		// Update using wp-admin function
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	}

	public static function refersion_wp_enqueue_scripts() {

		// Get option set in admin
		$options = get_option( 'refersion_settings' );

		// Only run if enabled
		if ($options['refersion_status'] AND strlen($options['refersion_public_api_key']) > 0) {

			// Initialize save point for frontend page output
			@ob_start();

				// Get subdomain, if any
				if ( ! empty($options['refersion_subdomain']) ) {
					$subdomain = $options['refersion_subdomain'];
				} else {
					$subdomain = 'www';
				}

			// Add tracking script
			wp_enqueue_script( 'refersion-wc-tracking', '//' . $subdomain . '.refersion.com/tracker/v3/'. $options['refersion_public_api_key'] . '.js', array(), FALSE, FALSE );

		}

	}

	public static function refersion_footer() {

		// Get option set in admin
		$options = get_option( 'refersion_settings' );

		// Only run if enabled
		if ($options['refersion_status'] AND strlen($options['refersion_public_api_key']) > 0) {

			// Append Javascript tracking library on every frontend page (to track clicks) at the end of the footer
			$footer = @ob_get_clean();

			echo $footer . '<!-- REFERSION TRACKING: BEGIN --><script>_refersion();</script><!-- REFERSION TRACKING: END -->';

		}

	}
	
	/**
	* Add cart_id into database after the order is complete
	*/
	public static function refersion_woocommerce_new_order($order_id) {

		// Get option set in admin
		$options = get_option( 'refersion_settings' );

		// Only run if enabled
		if ($options['refersion_status'] AND strlen($options['refersion_public_api_key']) > 0) {

			global $wpdb;

			// Generate a cart_id
			$refersion_cart_id = Refersion::refersion_generate_cart_id();

			// Insert the cart_id, user's IP address and WC order into the Refersion DB table
			$sql = "INSERT INTO `" . REFERSION_WC_ORDERS_TABLE . "` (`wc_order_id`,`refersion_cart_id`,`ip_address`) VALUES (%d, %s, %s)";
			$sql_prep = $wpdb->prepare($sql,array($order_id, $refersion_cart_id, Refersion::refersion_get_client_ip() ) );
			$wpdb->query($sql_prep);

		}

	}
	
	/**
	* Refersion JS code for the thank you page
	*/
	public static function refersion_woocommerce_thankyou($order_id) {

		// Get option set in admin
		$options = get_option( 'refersion_settings' );

		// Only run if enabled
		if ( $options['refersion_status'] AND strlen($options['refersion_public_api_key']) > 0 ) {

			global $wpdb;

			// Get the cart_id from the Refersion DB table
			$sql = "SELECT `refersion_cart_id` FROM `" . REFERSION_WC_ORDERS_TABLE . "` WHERE (`wc_order_id`  = %d AND `refersion_sent_status` = 'PENDING')";
			$refersion_cart_id = trim( @$wpdb->get_var( $wpdb->prepare( $sql, array($order_id) ) ) );

			if (strlen($refersion_cart_id) > 0) {

				// Use Wordpress script loader
				$rfsn_vars = array(
					"cti" => $refersion_cart_id
				);

				// Load the JS
				wp_register_script( "scripts", plugin_dir_url( __FILE__ ) . "/rfsn.js" );
				wp_enqueue_script( "scripts" );
				wp_localize_script( "scripts", "rfsn_vars", $rfsn_vars );

			}


		}

	}

	/**
	* Refersion webhook cURL call
	*/
	public static function refersion_curl_post($order_data) {
		
		$order_data_json = json_encode($order_data);
		
		// The URL that you are posting to
		$url = 'https://www.refersion.com/tracker/v3/webhook';
		
		// Start cURL
		$curl = curl_init($url);
		
		// Verify that our SSL is active (for added security)
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
		
		// Send as a POST
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		
		// The JSON data that you have already compiled
		curl_setopt($curl, CURLOPT_POSTFIELDS, $order_data_json);
		
		// Return the response
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		
		// Set headers to be JSON-friendly
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		    'Content-Type: application/json',
		    'Content-Length: ' . strlen($order_data_json))
		);
		
		// Seconds (5) before giving up
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
		
		// Execute post, capture response (if any) and status code
		$result = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		// Close connection
		curl_close($curl);

		return $result;

	}

	/**
	* Refersion Webhook to send order data
	*/
	public static function refersion_woocommerce_order_status_completed($order_id) {

		// Array to hold order value to be converted in json
		$order_data = array();

		// Get option set in admin
		$options = get_option( 'refersion_settings' );

		// Only run if Refersion tracking is enabled and both API keys are set
		if ($options['refersion_status'] AND strlen($options['refersion_public_api_key']) > 0 AND strlen($options['refersion_secret_api_key']) > 0) {

			global $wpdb;

			// Get API keys from shop admin
			$order_data['refersion_public_key'] = $options['refersion_public_api_key'];
			$order_data['refersion_secret_key'] = $options['refersion_secret_api_key'];	

			// Get cart_id and IP address from database
			$sql = "SELECT `refersion_cart_id`, `ip_address` FROM `" . REFERSION_WC_ORDERS_TABLE . "` WHERE (`wc_order_id`  = %d)";
			$results = $wpdb->get_results( $wpdb->prepare($sql, array($order_id) ), ARRAY_A);
			$cart_id = $results[0]["refersion_cart_id"];
			$ip_address = $results[0]["ip_address"];

			// Get order object
			$order = new WC_Order( $order_id );

			// Cart ID
			$order_data['cart_id'] = $cart_id;

			// Order ID
			$order_data['order_id'] = $order_id;

			// Order totals
			$orderGrandTotal = $order->get_total();
			$order_data['shipping'] = $order->get_total_shipping();
			$order_data['tax'] =  $order->get_total_tax();
			
			// Coupon codes - if multiple are used, send only the first one
			$discount = NULL;
			if( count($order->get_used_coupons()) > 0 ){
				$discount = $order->get_used_coupons();
				$discount = $discount[0];
			}
			$order_data['discount'] = abs($order->get_total_discount());
			$order_data['discount_code'] = $discount;

			// Currency code
			if ( WC()->version < '3.0.0' ) {
				$order_data['currency_code'] = $order-> get_order_currency();
			}

			if ( WC()->version >= '3.0.0' ) {
				$order_data['currency_code'] = $order-> get_currency();
			}

			// Detect if we have billing info otherwise shipping information will be used
			$first_name =  trim( @get_post_meta( $order_id, 'billing_first_name', true ) );
			$address_type = ( strlen($first_name) > 0 ) ? "_billing_" : "_shipping_";

			// Customer first and last name, default to shipping name
			if ( WC()->version < '3.0.0' ) {
				$order_data['customer']['first_name'] = get_post_meta( $order_id, $address_type . 'first_name', true );
				$order_data['customer']['last_name'] = get_post_meta( $order_id, $address_type . 'last_name', true );
			}

			if ( WC()->version >= '3.0.0' ) {
				$order_data['customer']['first_name'] = $order->get_billing_first_name();
				$order_data['customer']['last_name'] = $order->get_billing_last_name();
			}

			// Get email
			if ( WC()->version < '3.0.0' ) {
				$order_data['customer']['email'] = ( !empty($order->billing_email) ? $order->billing_email : NULL );
			}
			if ( WC()->version >= '3.0.0' ) {
				$order_data['customer']['email'] = ( !empty($order->get_billing_email()) ? $order->get_billing_email() : NULL );
			}

			// Other customer details
			$order_data['customer']['ip_address'] = $ip_address;

			// Get order line items
			$items = $order->get_items();

			if ( WC()->version < '3.0.0' ) {
	

				foreach ( $items as $item ) {

					// Figure out the SKU, is it a variation or not? 
					if ( ! empty($item['variation_id']) AND $item['variation_id'] > 0 ) {
						$product_id = $item['variation_id'];
					} else {
						$product_id = $item['product_id'];
					}

					// Get the SKU
					$product = new WC_Product($product_id);

					// Build line items
					$product_sku = $product->get_sku();
					$order_data['items'][$product_id]['sku'] = ( !empty( $product_sku ) ? $product_sku : 'N/A' );
					$order_data['items'][$product_id]['quantity'] = (int) $item['qty'];
					$order_data['items'][$product_id]['price'] = ( (float) $item['line_subtotal'] / (int) $item['qty'] );

					// Just in case
					unset($product_id, $product);

				}

			}

			if ( WC()->version >= '3.0.0' ) {

				foreach ( $items  as $item ) {

					$item_data = $item->get_product();
					$item_data = $item_data->get_data();

					$item_id = $item->get_variation_id();
					$item_quantity = $item->get_quantity();

					// Figure out the SKU, is it a variation or not? 
					if ( ! empty($item_id) AND $item_id > 0 ) {
						$product_id = $item_id;
					} else {
						$product_id = $item_data['id'];
					}

					// Build line items
					$order_data['items'][$product_id]['sku'] = ( !empty( $item_data['sku'] ) ? $item_data['sku'] : 'N/A' );
					$order_data['items'][$product_id]['quantity'] = (int) $item['qty'];
					$order_data['items'][$product_id]['price'] = ( (float) $item_data['price'] * (int) $item['qty'] );

					// Just in case
					unset($item_data, $item_id, $item_quantity);

				}

			}

			// Send order data via cURL if installed, otherwise use WP backend function
			if ( function_exists('curl_version') ) {

				// Send using cURL
				$refersion_response = Refersion::refersion_curl_post($order_data);

			} else {

				// Compile data for WP function to send to Refersion
				$post_data = array(
					'headers' => array(
						'Content-Type' => 'application/json'
					), 'body' => json_encode($order_data)
				);

				// Send order data to Refersion via WP
				$refersion_response = wp_remote_post( 'https://www.refersion.com/tracker/v3/webhook', $post_data );

			}

			// Update DB to say that the order was sent to Refersion
			$sql = "UPDATE `" . REFERSION_WC_ORDERS_TABLE . "` SET `refersion_sent_status` = 'SENT' WHERE `wc_order_id` = %d AND `refersion_sent_status` = 'PENDING'";
			$sql_prep = $wpdb->prepare($sql, array( $order_id ) );
			$wpdb->query($sql_prep);

			return $refersion_response;

		}

	}

}