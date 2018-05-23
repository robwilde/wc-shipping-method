<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */

use PixelStorm\PepTransport\Frontend\Pep_Api_Request;
use PixelStorm\PepTransport\Includes\WP_Logging;

/**
 * Plugin Name: PEP Transport WC Shipping
 * Plugin URI: https://Pixelstorm.com.au
 * Description: PEP Transport API Custom Shipping Method for WooCommerce
 * Version: 2.0.0
 * Author: Robert Wilde
 * Author URI: http://Pixelstorm.com.au
 * Text Domain: pep-transport
 */


if ( ! defined( 'WPINC' ) ) {

	die;

}

/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {


	add_action( 'woocommerce_shipping_init', 'PEP_WC_Shipping_Method' );
	/**
	 * class extending the WC_Shipping_Method
	 */
	function pep_wc_shipping_method() {
		if ( ! class_exists( 'PEP_WC_Shipping_Method' ) ) {
			class PEP_WC_Shipping_Method extends WC_Shipping_Method {
				/** @noinspection MagicMethodsValidityInspection */
				/** @noinspection PhpMissingParentConstructorInspection */
				private $rate_cache;
				private $pep_services;
				private $found_rates;
				private $origin;
				private $ordered_services;

				public $cutoff;

				/** @noinspection MagicMethodsValidityInspection */
				/** @noinspection PhpMissingParentConstructorInspection */
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'pep_transport_api';
					$this->method_title       = __( 'PEP Shipping', 'pep-shipping' );
					$this->method_description = __( 'Custom Shipping Method for PEP Transport', 'pep-shipping' );

					$this->init();

					$this->enabled      = $this->settings['enabled'] ?? 'yes';
					$this->title        = $this->settings['title'] ?? __( 'PEP Shipping', 'pep-shipping' );
					$this->origin       = $this->get_option( 'origin' );
					$this->cutoff       = $this->get_option( 'sameday_cutoff' );
					$this->pep_services = $this->get_option( 'pep_services' );
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				public function init() {
					// Load the settings API
					$this->init_form_fields();
					$this->init_settings();

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'clear_transients' ) );
				}


				/**
				 * environment_check function.
				 *
				 * @access public
				 * @return void
				 */
				private function environment_check() {
					global $woocommerce;

					if ( get_woocommerce_currency() !== 'AUD' ) {
						echo '<div class="error"><p>' . __( 'PEP Transport requires that the currency is set to Australian Dollars.', 'pep_transport' ) . '</p></div>';
					} elseif ( $woocommerce->countries->get_base_country() !== 'AU' ) {
						echo '<div class="error"><p>' . __( 'PEP Transport requires that the base country/region is set to Australia.', 'pep_transport' ) . '</p></div>';
					} elseif ( ! $this->origin && $this->enabled === 'yes' ) {
						echo '<div class="error"><p>' . __( 'PEP Transport is enabled, but the origin postcode has not been set.', 'pep_transport' ) . '</p></div>';
					}

				}


				/**
				 * admin_options function.
				 *
				 * @access public
				 * @return void
				 */
				public function admin_options() {
					// Check users environment supports this method
					$this->environment_check();
					// Show settings
					parent::admin_options();
				}


				/**
				 * Define settings field for this shipping
				 * @return void
				 */
				public function init_form_fields() {

					$this->form_fields = [
						'enabled'        => [
							'title'   => __( 'Realtime Rates', 'pep_transport' ),
							'type'    => 'checkbox',
							'label'   => __( 'Enable', 'pep_transport' ),
							'default' => 'no'
						],
						'title'          => [
							'title'       => __( 'Method Title', 'pep_transport' ),
							'type'        => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'pep_transport' ),
							'default'     => $this->method_title,
							'desc_tip'    => true
						],
						'origin'         => [
							'title'       => __( 'Origin Postcode', 'pep_transport' ),
							'type'        => 'text',
							'description' => __( 'Enter the postcode for the <strong>sender</strong>.', 'pep_transport' ),
							'default'     => '6000',
							'desc_tip'    => true
						],
						'sameday_cutoff' => [
							'title'       => __( 'Same Day Cutoff', 'pep_transport' ),
							'type'        => 'text',
							'description' => __( 'Enter the cutoff time for sameday delivery <strong>24 hour format</strong>.', 'pep_transport' ),
							'default'     => '14:00',
							'desc_tip'    => true
						],
						'pep_services'   => [ 'type' => 'service_codes' ],
					];

				}


				/**
				 * generate the services for the admin section to be displayed
				 *
				 * @access public
				 * @return string
				 */
				public function generate_service_codes_html(): string {
					ob_start();
					include __DIR__ . '/includes/PEP_Services.php';
					include __DIR__ . '/partial/wc-admin-html.php';

					return ob_get_clean();
				}


				/**
				 * validate_services_field function.
				 *
				 * @access public
				 *
				 * @param mixed $key
				 *
				 * @return array $services
				 */
				public function validate_service_codes_field( $key ): array {
					$services = [];

					foreach ( (array) $_POST['pep_transport_service'] as $code => $settings ) {

						$services[ $code ] = [
							'order'       => wc_clean( $settings['order'] ),
							'enabled'     => isset( $settings['enabled'] ) ? true : false,
							'name'        => wc_clean( $settings['name'] ),
							'description' => wc_clean( $settings['description'] ),
							'variables'   => $settings['variables']
						];

					}

					return $services;
				}


				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 *
				 * @param mixed $package
				 *
				 * @return void
				 */
				public function calculate_shipping( $package = [] ) {

					$this->rate_cache  = get_transient( 'pep_transport_quotes' );
					$_shipping_details = wc()->customer->get_shipping();

					//	$chosen_methods = wc()->session->get( 'chosen_shipping_methods' );

					$_checkout_data = $_POST['post_data'] ?? null;

					$_name_fields = array();
					if ( $_checkout_data ) {

						$_pairs = explode( '&', $_checkout_data );
						$_count = 0;
						foreach ( $_pairs as $value ) {
							$_key_value = explode( '=', $value );

							if ( $_count === 2 ) {
								break;
							}

							if ( 'billing_first_name' === $_key_value[0] ) {
								$_name_fields['first_name'] = $_key_value[1];
								$_count ++;
							}

							if ( 'billing_last_name' === $_key_value[0] ) {
								$_name_fields['last_name'] = $_key_value[1];
								$_count ++;
							}
						}

						if ( ! empty( $_name_fields['first_name'] ) && ! empty( $_name_fields['last_name'] ) ) {

							$_customer_details['address2'] = wc()->customer->get_shipping_address_2();

							$_shipping_details['name']     = $_name_fields['first_name'] . ' ' . $_name_fields['last_name'];
							$_shipping_details['suburb']   = $_shipping_details['city'];
							$_shipping_details['address1'] = $_shipping_details['address_1'];

							$_pep_api = new Pep_Api_Request( $_shipping_details, $package );

							$service_codes = $this->filter_services( $_pep_api->get_measurements() );

							if ( empty( $service_codes ) ) {
								$message = 'No PEP Services available';
								wc_add_notice( $message, 'error' );
							}

							foreach ( $service_codes as $code => $values ) {

								$_rate = new stdClass();

								$_rate->code = (string) $code;
								$_rate->name = (string) $values['name'];
								$_rate->cost = null;

								$_response = $_pep_api->run_query( $_rate->code );

								// adding in for logging
								$_response['MANIFEST']['CONSIGNMENT']['SERVICE_CODE'] = $_rate->code;
								$this->log_response( $_response );

								if ( 'SUCCESS' === $_response['MANIFEST']['CONSIGNMENT']['STATUS'] ) {

									$_rate->cost = $_response['MANIFEST']['CONSIGNMENT']['COST'];

									// logging the response to the plugin settings page
									$this->found_rates[ $_rate->code ] = $this->prepare_rate( $_rate, $_response );

								}
							}

							set_transient( 'pep_transport_quotes', $this->rate_cache, HOUR_IN_SECONDS / 4 );

							// Register the rate
							foreach ( (array) $this->found_rates as $key => $rate ) {
								$this->add_rate( $rate );
							}

						}

					}

				}


				/**
				 * using measurements of the checkout to get correct service
				 *
				 * @param $measurements
				 *
				 * @return array
				 */
				private function filter_services( $measurements ) {

					// complete list of service codes to be filtered down
					// will return 3 service codes for (next-day/same-day/VIP)
					$filtered     = [];
					$_services    = $this->pep_services;
					$_totalWeight = $measurements['cart_weight'];
					unset( $measurements['cart_weight'] );

					// remove the services that do not match the service code variables
					$checkedWeightServices = array_filter( $_services, function ( $service ) use ( $_totalWeight ) {
						if ( ! $service['enabled'] ) {
							return false;
						}

						$variables = explode( ',', $service['variables'] );

						return $_totalWeight >= $variables[0] && $_totalWeight <= $variables[1];
					} );

					// loop through measurements and find any products that match the checkedWeight Services
					$productLengths = array_map( function ( $product ) {
						return $product['length'];
					}, $measurements );


					// @TODO look at changing this a array_map with an array_filter callback
					// loop through the lengths of each item and check if there are any matches to the services with specific size requirement
					foreach ( $productLengths as $length ) {
						$filtered = array_filter( $checkedWeightServices, function ( $service ) use ( $length ) {
							$variables = explode( ',', $service['variables'] );
							if ( empty( $variables[2] ) && empty( $variables[3] ) ) {
								return true;
							}

							return $length >= $variables[2] && $length <= $variables[3];
						} );
					}


					return $filtered;
				}


				/**
				 * prepare the rate to be returned to the checkout
				 *
				 * @access private
				 *
				 * @param  $_rate
				 * @param $_response
				 *
				 * @return array
				 */
				private function prepare_rate( $_rate, $_response ): array {

					$rate_code = $_rate->code;
					$rate_id   = "{$this->id}_{$_rate->name}";
					$rate_cost = $_rate->cost;
					$rate_name = $_rate->name;
//					$rate_name = $this->title;

					// Enabled check
					if ( isset( $this->pep_services[ $rate_code ] ) && empty( $this->pep_services[ $rate_code ]['enabled'] ) ) {
						return [];
					}

					// Name adjustment
					$rate_name = ! empty( $this->pep_services[ $rate_code ]['name'] ) ? $this->pep_services[ $rate_code ]['name'] : $rate_name;

					// Merging
					$packages = 1;
					if ( isset( $this->found_rates[ $rate_id ] ) ) {
						$rate_cost += $this->found_rates[ $rate_id ]['cost'];
						$packages  = 1 + $this->found_rates[ $rate_id ]['packages'];
					}

					// Sort
					$sort = isset( $this->pep_services[ $rate_code ]['order'] ) ? (int) $this->pep_services[ $rate_code ]['order'] : 999;

					return array(
						'id'        => $rate_id,
						'label'     => $rate_name,
						'cost'      => $rate_cost,
						'sort'      => $sort,
						'packages'  => $packages,
						'calc_tax'  => 'per_item',
						'meta_data' => array(
							'service_code' => $rate_code,
							'reference'    => $_response['MANIFEST']['CONSIGNMENT']['REFERENCE'],
							'consignment'  => $_response['MANIFEST']['CONSIGNMENT']['CONSIGNMENTNUMBER'],
						),
					);
				}


				/**
				 * logging class functions when required
				 *
				 * @param $_response
				 */
				private function log_response( $_response ) {

					//  output print for post content
					//	$content = print_r($_response['MANIFEST']['CONSIGNMENT'] , true);

					// logging the event
					$log_data = array(
						'post_title'   => 'HubSystem_Quote',
						'post_content' => serialize( $_response['MANIFEST']['CONSIGNMENT'] ),
						'post_parent'  => '',
						'log_type'     => 'event',
					);

					$log_meta = array(
						'user_id'     => get_current_user_id(),
						'customer_ip' => $_SERVER['SERVER_ADDR'],
						'request_uri' => $_SERVER['REQUEST_URI'],
					);

					WP_Logging::insert_log( $log_data, $log_meta );
				}


				/**
				 * clear any stored settings from WP Transients
				 *
				 * @access public
				 * @return void
				 */
				public function clear_transients() {
					delete_transient( 'pep_transport_quotes' );
				}

			} // class PEP_WC_Shipping_Method
		}
	}

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pep_plugin_action_links' );
	/**
	 * add in links to plugin functions
	 *
	 * @param $links
	 *
	 * @return array
	 */
	function pep_plugin_action_links( $links ) {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=pep_transport_shipping' ) . '">' . __( 'Settings',
				'pep_transport' ) . '</a>',
			'<a href="https://peptransport.com.au/cgi-bin/pepAccount" target="_blank">' . __( 'PEP Client Page', 'pep_transport' ) . '</a>',
		];

		return array_merge( $plugin_links, $links );
	}


	add_filter( 'woocommerce_shipping_methods', 'add_pep_shipping_method' );
	/**
	 * add in custom shipping method
	 *
	 * @param $methods
	 *
	 * @return array
	 */
	function add_pep_shipping_method( $methods ) {
		foreach ( [ 'next-day', 'same-day', 'vip' ] as $rate_option ) {
			$methods['pep_transport_api_' . $rate_option] = 'PEP_WC_Shipping_Method';
		}

		return $methods;
	}


	add_filter( 'woocommerce_shipping_methods', 'add_shipping_method' );
	function add_shipping_method( $methods ) {
		foreach ( [ 'next-day', 'same-day', 'overnight' ] as $rate_option ) {
			$methods['pep_transport_api_' . $rate_option] = 'PEP_WC_Shipping_Method';
		}

		return $methods;
	}


	add_action( 'woocommerce_review_order_before_cart_contents', 'pep_validate_order', 10 );
	add_action( 'woocommerce_after_checkout_validation', 'pep_validate_order', 10 );
	/**
	 * validate the order has the correct shipping information
	 *
	 * @param $posted
	 */
	function pep_validate_order( $posted ) {

		$packages = WC()->shipping->get_packages();

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $chosen_methods ) && in_array( 'pep', $chosen_methods, true ) ) {

			foreach ( $packages as $i => $package ) {

				if ( $chosen_methods[ $i ] !== 'pep' ) {
					continue;
				}

				$PEP_Shipping_Method = new PEP_WC_Shipping_Method();

				$sameday_cutoff = $PEP_Shipping_Method->cutoff;

				if ( time() >= strtotime( $sameday_cutoff ) ) {

					$message = sprintf( __( 'Sorry, it\'s  %s and past the %s Same day delivery cutoff', 'pep' ), date( 'H:i' ), $sameday_cutoff, $PEP_Shipping_Method->title );

					$messageType = 'error';

					if ( ! wc_has_notice( $message, $messageType ) ) {
						wc_add_notice( $message, $messageType );
					}
				}
			}
		}
	}


	add_filter( 'admin_enqueue_scripts', 'pep_transport_scripts' );
	function pep_transport_scripts() {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

}