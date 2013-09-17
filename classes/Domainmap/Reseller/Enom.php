<?php

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
// | Based on an original by Donncha (http://ocaoimh.ie/)                 |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

/**
 * eNom reseller API class.
 *
 * @category Domainmap
 * @package Reseller
 *
 * @since 4.0.0
 */
class Domainmap_Reseller_Enom extends Domainmap_Reseller {

	const RESELLER_ID = 'enom';

	const ENDPOINT_PRODUCTION  = 'https://reseller.enom.com/interface.asp?';
	const ENDPOINT_TEST        = 'https://resellertest.enom.com/interface.asp?';

	const ENVIRONMENT_PRODUCTION = 'prod';
	const ENVIRONMENT_TEST       = 'test';

	const COMMAND_CHECK              = 'Check';
	const COMMAND_GET_TLD_LIST       = 'GetTLDList';
	const COMMAND_RETAIL_PRICE       = 'PE_GetRetailPrice';
	const COMMAND_PURCHASE           = 'Purchase';
	const COMMAND_SET_HOSTS          = 'SetHosts';
	const COMMAND_GET_EXT_ATTRIBUTES = 'GetExtAttributes';

	const GATEWAY_ENOM     = 'enom';
	const GATEWAY_PROSITES = 'prosites';

	/**
	 * Returns reseller internal id.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function get_reseller_id() {
		return self::RESELLER_ID;
	}

	/**
	 * Executes remote command and returns response of execution.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $command The command name.
	 * @param array $args Additional optional arguments.
	 * @return SimpleXMLElement Returns simplexml object on success, otherwise FALSE.
	 */
	private function _exec_command( $command, $args = array() ) {
		if ( !isset( $args['uid'] ) || !isset( $args['pw'] ) ) {
			$options = Domainmap_Plugin::instance()->get_options();

			if ( !isset( $args['uid'] ) ) {
				$args['uid'] = isset( $options['enom']['uid'] ) ? $options['enom']['uid'] : '';
			}

			if ( !isset( $args['pw'] ) ) {
				$args['pw'] = isset( $options['enom']['pwd'] ) ? $options['enom']['pwd'] : '';
			}
		}

		if ( !isset( $args['responsetype'] ) ) {
			$args['responsetype'] = 'xml';
		}

		$args['command'] = $command;

		$endpoint = $this->_get_environment() == self::ENVIRONMENT_PRODUCTION
			? self::ENDPOINT_PRODUCTION
			: self::ENDPOINT_TEST;
		$response = wp_remote_get( $endpoint . http_build_query( $args ) );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			return false;
		}

		libxml_use_internal_errors( true );
		return simplexml_load_string( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Logs request to reseller API.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @param int $type The request type.
	 * @param SimpleXMLElement $response The response information, received on request.
	 */
	private function _log_enom_request( $type, $xml ) {
		if ( !is_object( $xml ) ) {
			return;
		}

		$valid = !isset( $xml->ErrCount ) || $xml->ErrCount == 0;
		$errors = array();
		if ( !$valid && isset( $xml->errors ) ) {
			$errors = json_decode( json_encode( $xml->errors ), true );
		}

		$this->_log_request( $type, $valid, $errors, $xml );
	}

	/**
	 * Saves reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $options The array of plugin options.
	 */
	public function save_options( &$options ) {
		if ( !isset( $options[self::RESELLER_ID] ) || !is_array( $options[self::RESELLER_ID] ) ) {
			$options[self::RESELLER_ID] = array();
		}

		// user name
		$uid = trim( filter_input( INPUT_POST, 'map_reseller_enom_uid' ) );
		$need_health_check = !isset( $options[self::RESELLER_ID]['uid'] ) || $options[self::RESELLER_ID]['uid'] != $uid;
		$options[self::RESELLER_ID]['uid'] = $uid;

		// password
		$pwd = filter_input( INPUT_POST, 'map_reseller_enom_pwd' );
		$pwd_hash = filter_input( INPUT_POST, 'map_reseller_enom_pwd_hash' );
		if ( $pwd_hash != sha1( $pwd ) ) {
			$options[self::RESELLER_ID]['pwd'] = $pwd;
			$need_health_check = true;
		}

		// payment gateway
		$gateway = filter_input( INPUT_POST, 'map_reseller_enom_payment' );
		if ( $gateway !== false ) {
			$gateways = $this->_get_gateways();
			if ( isset( $gateways[$gateway] ) ) {
				$options[self::RESELLER_ID]['gateway'] = $gateway;
			}
		}

		// environment
		$environment = filter_input( INPUT_POST, 'map_reseller_enom_environment' );
		if ( in_array( $environment, array( self::ENVIRONMENT_PRODUCTION, self::ENVIRONMENT_TEST ) ) ) {
			$options[self::RESELLER_ID]['environment'] = $environment;
		}

		// validate credentials
		$options[self::RESELLER_ID]['valid'] = $need_health_check || ( isset( $options[self::RESELLER_ID]['valid'] ) && $options[self::RESELLER_ID]['valid'] == false )
			? $this->_validate_credentials( $options[self::RESELLER_ID]['uid'], $options[self::RESELLER_ID]['pwd'] )
			: true;
	}

	/**
	 * Validates API credentials.
	 *
	 * @sicne 4.0.0
	 *
	 * @access private
	 * @param string $uid The user id.
	 * @param string $pwd The user password.
	 * @return boolean TRUE if API credentials are valid, otherwise FALSE.
	 */
	private function _validate_credentials( $uid, $pwd ) {
		$xml = $this->_exec_command( self::COMMAND_CHECK, array(
			'uid' => $uid,
			'pw'  => $pwd,
			'sld' => 'example',
			'tld' => 'com',
		) );

		$this->_log_enom_request( self::REQUEST_VALIDATE_CREDENTIALS, $xml );

		return !isset( $xml->ErrCount ) || $xml->ErrCount == 0;
	}

	/**
	 * Returns the array of payments gateways supported by reseller.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @return array The associative array of payment gateways.
	 */
	private function _get_gateways() {
		return array(
			self::GATEWAY_ENOM     => __( 'eNom credit card processing services', 'domainmap' ),
			self::GATEWAY_PROSITES => __( 'Pro Sites PayPal payment gateway', 'domainmap' ),
		);
	}

	/**
	 * Returns current reseller payment gateway.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @global ProSites $psts The instance of ProSites plugin class.
	 * @param array $options The plugin options.
	 * @return string The current gateway key.
	 */
	private function _get_gateway( $options = null ) {
		global $psts;

		// if no options were passed, take it from the plugin instance
		if ( !$options ) {
			$options = Domainmap_Plugin::instance()->get_options();
			$options = isset( $options[self::RESELLER_ID] ) ? $options[self::RESELLER_ID] : array();
		}

		// fetch gateway information
		$gateways = $this->_get_gateways();
		$gateway = isset( $options['gateway'] ) && isset( $gateways[$options['gateway']] )
			? $options['gateway']
			: self::GATEWAY_ENOM;

		// if paypal gateway is selected, then check if it is available
		if ( $gateway == self::GATEWAY_PROSITES ) {
			$paypal_class = 'ProSites_Gateway_PayPalExpressPro';
			$pro_gateways = $psts->get_setting( 'gateways_enabled' ) ;

			// if prosites is not activated or paypal gateway is not activated, then use eNom gateway
			if ( !$psts || !in_array( $paypal_class, (array)$pro_gateways ) || !class_exists( $paypal_class ) ) {
				$gateway = self::GATEWAY_ENOM;
			}
		}

		return $gateway;
	}

	/**
	 * Returns current environment.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param array $options The plugin options.
	 * @return string The current environment.
	 */
	private function _get_environment( $options = null ) {
		// if no options were passed, take it from the plugin instance
		if ( !$options ) {
			$options = Domainmap_Plugin::instance()->get_options();
			$options = isset( $options[self::RESELLER_ID] ) ? $options[self::RESELLER_ID] : array();
		}

		return isset( $options['environment'] ) ? $options['environment'] : self::ENVIRONMENT_TEST;
	}

	/**
	 * Returns reseller title.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return string The title of reseller provider.
	 */
	public function get_title() {
		return 'eNom';
	}

	/**
	 * Renders reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function render_options() {
		$options = Domainmap_Plugin::instance()->get_options();
		$options = isset( $options[self::RESELLER_ID] ) ? $options[self::RESELLER_ID] : array();

		$render = new Domainmap_Render_Reseller_Enom_Settings( $options );
		$render->gateways = $this->_get_gateways();
		$render->gateway = $this->_get_gateway( $options );
		$render->environment = $this->_get_environment( $options );
		$render->render();
	}

	/**
	 * Determines whether reseller API connected properly or not.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return boolean TRUE if API connected properly, otherwise FALSE.
	 */
	public function is_valid() {
		$options = Domainmap_Plugin::instance()->get_options();
		return !isset( $options[self::RESELLER_ID]['valid'] ) || $options[self::RESELLER_ID]['valid'] == true;
	}

	/**
	 * Returns TLD list accepted by reseller.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @return array The array of TLD accepted by reseller.
	 */
	protected function _get_tld_list() {
		$xml = $this->_exec_command( self::COMMAND_GET_TLD_LIST );
		$this->_log_enom_request( self::REQUEST_GET_TLD_LIST, $xml );

		$tlds = array();
		if ( $xml && isset( $xml->tldlist->tld ) ) {
			$tldlist = json_decode( json_encode( $xml->tldlist ), true );
			foreach ( $tldlist['tld'] as $tld ) {
				$tlds[] = $tld['tld'];
			}
		}

		return array_filter( $tlds );
	}

	/**
	 * Checks domain availability.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @param string $tld The top level domain.
	 * @param string $sld The second level domain.
	 * @return boolean TRUE if domain is available to puchase, otherwise FALSE.
	 */
	protected function _check_domain( $tld, $sld ) {
		$xml = $this->_exec_command( self::COMMAND_CHECK, array(
			'tld' => $tld,
			'sld' => $sld,
		) );

		$this->_log_enom_request( self::REQUEST_CHECK_DOMAIN, $xml );

		return $xml && isset( $xml->RRPCode ) && $xml->RRPCode == 210;
	}

	/**
	 * Fetches and returns TLD price.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $tld The top level domain.
	 * @return float The price for the TLD.
	 */
	protected function _get_tld_price( $tld ) {
		$xml = $this->_exec_command( self::COMMAND_RETAIL_PRICE, array(
			'tld'         => $tld,
			'ProductType' => 10,
		) );

		$this->_log_enom_request( self::REQUEST_GET_RETAIL_PRICE, $xml );

		if ( $xml && isset( $xml->productprice->price ) ) {
			return floatval( $xml->productprice->price );
		}

		return false;
	}

	/**
	 * Purchases a domain name.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @return string|boolean The domain name if purchased successfully, otherwise FALSE.
	 */
	public function purchase() {
		$sld = trim( filter_input( INPUT_POST, 'sld' ) );
		$tld = trim( filter_input( INPUT_POST, 'tld' ) );
		$expiry = array_map( 'trim', explode( '/', filter_input( INPUT_POST, 'card_expiration' ), 2 ) );

		$billing_phone = '+' . preg_replace( '/[^0-9]/', '', filter_input( INPUT_POST, 'billing_phone' ) );
		$registrant_phone = '+' . preg_replace( '/[^0-9]/', '', filter_input( INPUT_POST, 'registrant_phone' ) );
		$registrant_fax = '+' . preg_replace( '/[^0-9]/', '', filter_input( INPUT_POST, 'registrant_fax' ) );

		$response = $this->_exec_command( self::COMMAND_PURCHASE, array(
			'sld'                        => $sld,
			'tld'                        => $tld,
			'UseDNS'                     => 'default',
			'ChargeAmount'               => $this->get_tld_price( $tld ),
			'EndUserIP'                  => $_SERVER['REMOTE_ADDR'],
			'CardType'                   => filter_input( INPUT_POST, 'card_type' ),
			'CCName'                     => filter_input( INPUT_POST, 'card_cardholder' ),
			'CreditCardNumber'           => preg_replace( '/[^0-9]/', '', filter_input( INPUT_POST, 'card_number' ) ),
			'CreditCardExpMonth'         => $expiry[0],
			'CreditCardExpYear'          => isset( $expiry[1] ) ? "20{$expiry[1]}" : '',
			'CVV2'                       => filter_input( INPUT_POST, 'card_cvv2' ),
			'CCAddress'                  => filter_input( INPUT_POST, 'billing_address' ),
			'CCCity'                     => filter_input( INPUT_POST, 'billing_city' ),
			'CCStateProvince'            => filter_input( INPUT_POST, 'billing_state' ),
			'CCZip'                      => filter_input( INPUT_POST, 'billing_zip' ),
			'CCPhone'                    => $billing_phone,
			'CCCountry'                  => filter_input( INPUT_POST, 'billing_country' ),
			'RegistrantFirstName'        => filter_input( INPUT_POST, 'registrant_first_name' ),
			'RegistrantLastName'         => filter_input( INPUT_POST, 'registrant_last_name' ),
			'RegistrantOrganizationName' => filter_input( INPUT_POST, 'registrant_organization' ),
			'RegistrantJobTitle'         => filter_input( INPUT_POST, 'registrant_job_title' ),
			'RegistrantAddress1'         => filter_input( INPUT_POST, 'registrant_address1' ),
			'RegistrantAddress2'         => filter_input( INPUT_POST, 'registrant_address2' ),
			'RegistrantCity'             => filter_input( INPUT_POST, 'registrant_city' ),
			'RegistrantStateProvince'    => filter_input( INPUT_POST, 'registrant_state' ),
			'RegistrantPostalCode'       => filter_input( INPUT_POST, 'registrant_zip' ),
			'RegistrantCountry'          => filter_input( INPUT_POST, 'registrant_country' ),
			'RegistrantEmailAddress'     => filter_input( INPUT_POST, 'registrant_email' ),
			'RegistrantPhone'            => $registrant_phone,
			'RegistrantFax'              => $registrant_fax,
		) + ( isset( $_POST['ExtendedAttributes'] ) ? (array)$_POST['ExtendedAttributes'] : array() ) );

		$this->_log_enom_request( self::REQUEST_PURCHASE_DOMAIN, $response );

		if ( $response && isset( $response->RRPCode ) && $response->RRPCode == 200 ) {
			$this->_populate_dns_records( $tld, $sld );
			return "{$sld}.{$tld}";
		}

		return false;
	}

	/**
	 * Populates either DNS A or CNAME records for purchased domain.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @global wpdb $wpdb The database connection.
	 * @param string $tld The TLD name.
	 * @param string $sld The SLD name.
	 */
	private function _populate_dns_records( $tld, $sld ) {
		global $wpdb;

		$ips = $args = array();
		$options = Domainmap_Plugin::instance()->get_options();

		// fetch unchanged domain name from database, because get_option function could return mapped domain name
		$basedomain = parse_url( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" ), PHP_URL_HOST );

		// if server ip addresses are provided, use it to populate DNS records
		if ( !empty( $options['map_ipaddress'] ) ) {
			foreach ( explode( ',', trim( $options['map_ipaddress'] ) ) as $ip ) {
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$ips[] = $ip;
				}
			}
		}

		// looks like server ip addresses are not set, then try to read it automatically
		if ( empty( $ips ) && function_exists( 'dns_get_record' ) ) {
			$ips = wp_list_pluck( dns_get_record( $basedomain, DNS_A ), 'ip' );
		}

		// if we have an ip address to populate DNS record, then try to detect if we use shared or dedicated hosting
		$dedicated = false;
		if ( !empty( $ips ) ) {
			$check = sha1( time() );
			$ajax_url = admin_url( 'admin-ajax.php' );
			$ajax_url = str_replace( parse_url( $ajax_url, PHP_URL_HOST ), current( $ips ), $ajax_url );

			$response = wp_remote_request( add_query_arg( array(
				'action' => 'domainmapping_heartbeat_check',
				'check'  => $check,
			), $ajax_url ) );

			$dedicated = !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 && wp_remote_retrieve_body( $response ) == $check;
		}

		// populate request arguments
		if ( !empty( $ips ) ) {
			if ( defined( 'SUBDOMAIN_INSTALL' ) && !$dedicated ) {
				if ( SUBDOMAIN_INSTALL ) {
					// network is hosted on shared hosting and uses subdomains for sites
					// we can use DNS CNAME records for it
					$args['HostName1'] = "{$sld}.{$tld}";
					$args['RecordType1'] = 'CNAME';
					$args['Address1'] = "{$basedomain}.";

					$args['HostName2'] = "www.{$sld}.{$tld}";
					$args['RecordType2'] = 'CNAME';
					$args['Address2'] = "{$basedomain}.";
				} else {
					// network is hosted on shared hosting and uses subfolders for sites
					// neither DNS A record nor DNS CNAME record won't work in this case
					return;
				}
			} else {
				// network is hosted on dedicated hosting and we can use DNS A records
				$i = 0;
				foreach ( $ips as $ip ) {
					if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
						$i++;
						$args["HostName{$i}"] = '@';
						$args["RecordType{$i}"] = 'A';
						$args["Address{$i}"] = $ip;
					}
				}
			}
		}

		// setup DNS records if it has been populated
		if ( !empty( $args ) ) {
			$args['sld'] = $sld;
			$args['tld'] = $tld;

			$response = $this->_exec_command( self::COMMAND_SET_HOSTS, $args );
			$this->_log_enom_request( self::REQUEST_SET_DNS_RECORDS, $response );
		}
	}

	/**
	 * Returns purchase form html.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @global WP_User $current_user The current user object.
	 * @param array $domain_info The information about a domain to purchase.
	 * @return string The purchase form html.
	 */
	public function get_purchase_form_html( $domain_info ) {
		global $current_user;

		get_currentuserinfo();
		$cardholder = trim( $current_user->user_firstname . ' ' . $current_user->user_lastname );
		if ( empty( $cardholder ) ) {
			$cardholder = __( 'Your name', 'domainmap' );
		}

		$render = new Domainmap_Render_Reseller_Enom_Purchase( $domain_info );
		$render->cardtypes = $this->get_card_types();
		$render->cardholder = $cardholder;
		$render->countries = Domainmap_Plugin::instance()->get_countries();
		$render->ext_attributes = $this->_get_extended_attributes( $domain_info['tld'] );

		return $render->to_html();
	}

	/**
	 * Returns extended attributes for specific TLD.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $tld The TLD name.
	 */
	private function _get_extended_attributes( $tld ) {
		$transient = "domainmap-ext-attributes-{$tld}";
		$attributes = get_transient( $transient );
		if ( $attributes !== false ) {
			return $attributes;
		}

		$attributes = array();
		$response = $this->_exec_command( self::COMMAND_GET_EXT_ATTRIBUTES, array( 'TLD' => $tld ) );
		$this->_log_enom_request( self::REQUEST_GET_EXT_ATTRIBUTES, $response );

		$response = json_decode( json_encode( $response ), true );
		if ( !empty( $response['Attributes']['Attribute'] ) && is_array( $response['Attributes']['Attribute'] ) ) {
			foreach ( $response['Attributes']['Attribute'] as $attribute ) {
				if ( $attribute['Application'] == 2 ) {
					$attribute['Options'] = $attribute['Options']['Option'];
					$attributes[] = $attribute;
				}
			}
		}

		set_transient( $transient, $attributes, DAY_IN_SECONDS );

		return $attributes;
	}

	/**
	 * Returns domain available response HTML with a link on purchase form or paypal checkout.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin class.
	 * @param string $sld The actual SLD.
	 * @param string $tld The actual TLD.
	 * @param string $purchase_link The purchase URL.
	 * @return string Response HTML.
	 */
	public function get_domain_available_response( $sld, $tld, $purchase_link = false ) {
		global $psts;

		$gateway = $this->_get_gateway();

		if ( $gateway == self::GATEWAY_PROSITES && $psts ) {
			$locale = apply_filters( 'domainmap_locale', get_locale() );
			if ( !preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $locale ) ) {
				$locale = 'en_US';
			}

			return parent::get_domain_available_response( $sld, $tld, sprintf( '
				<form class="domainmapping-paypal-form" action="%s">
					<input type="hidden" name="action" value="domainmapping_purchase_with_paypal">
					<input type="hidden" name="nonce" value="%s">
					<input type="hidden" name="sld" value="%s">
					<input type="hidden" name="tld" value="%s">
					<button type="submit" class="domainmapping-transparent-button"><img src="http://www.paypalobjects.com/%s/i/btn/btn_buynow_LG.gif" alt="%s"></button>
				</form>
				',
				admin_url( 'admin-ajax.php' ),
				wp_create_nonce( 'domainmapping_purchase_with_paypal' ),
				$sld,
				$tld,
				$locale,
				__( 'Purchase this domain with PayPal Express Checkout.', 'domainmap' )
			) );
		}

		return parent::get_domain_available_response( $sld, $tld, $purchase_link );
	}

	/**
	 * Executes SetExpressCheckout command of PayPal API and returns response.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @global ProSites $psts The instance of ProSites plugin class.
	 * @param float $amount The price of the domain.
	 * @param string $sld The second level domain.
	 * @param string $tld The first level domain.
	 * @return array The response array on success, otherwise FALSE.
	 */
	private function _set_express_checkout( $amount, $sld, $tld ) {
		global $psts;
		if ( !$psts ) {
			return false;
		}

		$returnurl = add_query_arg( array(
			'action' => 'domainmapping_do_express_checkout',
			'sld'    => $sld,
			'tld'    => $tld,
		), admin_url( 'admin-ajax.php' ) );

		$cancelurl = parse_url( add_query_arg( array(
			'token' => false,
			'sld'   => $sld,
			'tld'   => $tld,
		), wp_get_referer() ) );

		return $this->_call_paypal_api( 'SetExpressCheckout', array(
			'PAYMENTREQUEST_0_AMT'           => $amount,
			'PAYMENTREQUEST_0_ITEMAMT'       => $amount,
			'PAYMENTREQUEST_0_CURRENCYCODE'  => 'USD',
			'PAYMENTREQUEST_0_DESC'          => __( 'Payment for 1 year usage of the domain name.', 'domainmap' ),
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'L_PAYMENTREQUEST_0_NAME0'       => "{$sld}.{$tld}",
			'L_PAYMENTREQUEST_0_QTY0'        => 1,
			'L_PAYMENTREQUEST_0_AMT0'        => $amount,
			'LOCALECODE'                     => $psts->get_setting( 'pypl_site' ),
			'NOSHIPPING'                     => 1,
			'ALLOWNOTE'                      => 0,
			'RETURNURL'                      => $returnurl,
			'CANCELURL'                      => site_url( "{$cancelurl['path']}?{$cancelurl['query']}" ),
			'HDRIMG'                         => $psts->get_setting( 'pypl_header_img' ),
			'HDRBORDERCOLOR'                 => $psts->get_setting( 'pypl_header_border' ),
			'HDRBACKCOLOR'                   => $psts->get_setting( 'pypl_header_back' ),
			'PAYFLOWCOLOR'                   => $psts->get_setting( 'pypl_page_back' ),
		) );
	}

	/**
	 * Calls PayPal API and returns response array.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @global ProSites $psts The instance of ProSites plugin class.
	 * @param string $method The request command.
	 * @param array $args The array of request arguments.
	 * @return boolean|array The response array on success, otherwise FALSE.
	 */
	private function _call_paypal_api( $method, array $args ) {
		global $psts;
		if ( !$psts ) {
			return false;
		}

		$endpoint = $psts->get_setting( 'pypl_status' ) == 'live'
			? "https://api-3t.paypal.com/nvp"
			: "https://api-3t.sandbox.paypal.com/nvp";

		$response = wp_remote_post( $endpoint, array(
			'user-agent' => "Pro Sites: http://premium.wpmudev.org/project/pro-sites | PayPal Express/Pro Gateway",
			'sslverify'  => false,
			'timeout'    => 60,
			'body'       => http_build_query( array_merge( array(
				'VERSION'   => '63.0',
				'PWD'       => $psts->get_setting( 'pypl_api_pass' ),
				'USER'      => $psts->get_setting( 'pypl_api_user' ),
				'SIGNATURE' => $psts->get_setting( 'pypl_api_sig' ),
				'METHOD'    => $method,
			), $args ) ),
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			return false;
		}

		$nvp_response = array();
		parse_str( wp_remote_retrieve_body( $response ), $nvp_response );
		return $nvp_response;
	}

	/**
	 * Proceeds PayPal checkout.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin class.
	 */
	public function proceed_paypal_checkout() {
		global $psts;

		$tld = strtolower( trim( filter_input( INPUT_GET, 'tld' ) ) );
		$sld = strtolower( trim( filter_input( INPUT_GET, 'sld' ) ) );
		$response = $this->_set_express_checkout( $this->get_tld_price( $tld ), $sld, $tld );
		if ( !$response || !isset( $response['ACK'] ) || !isset( $response['TOKEN'] ) || ( $response['ACK'] != 'Success' && $response['ACK'] != 'SuccessWithWarning' ) ) {
			return;
		}

		$paypal_url = $psts->get_setting( 'pypl_status' ) == 'live'
			? "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token="
			: "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";

		wp_redirect( $paypal_url . urlencode( $response['TOKEN'] ) );
		exit;
	}

	/**
	 * Completes PayPal checkout and purchase a domain.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return string|boolean Returns domain name on success, otherwise FALSE.
	 */
	public function complete_paypal_checkout() {
		$token = filter_input( INPUT_GET, 'token' );
		if ( !$token ) {
			return false;
		}

		// receive checkout details
		$details = $this->_call_paypal_api( 'GetExpressCheckoutDetails', array( 'TOKEN' => $token ) );
		if ( !$details || !isset( $details['ACK'] ) || ( $details['ACK'] != 'Success' && $details['ACK'] != 'SuccessWithWarning' ) ) {
			return false;
		}

		// complete checkout
		$response = $this->_call_paypal_api( 'DoExpressCheckoutPayment', array(
			'PAYERID'                        => $details['PAYERID'],
			'TOKEN'                          => $details['TOKEN'],
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_CURRENCYCODE'  => $details['PAYMENTREQUEST_0_CURRENCYCODE'],
			'PAYMENTREQUEST_0_AMT'           => $details['PAYMENTREQUEST_0_AMT'],
			'PAYMENTREQUEST_0_ITEMAMT'       => $details['PAYMENTREQUEST_0_ITEMAMT'],
			'L_PAYMENTREQUEST_0_QTY0'        => $details['L_PAYMENTREQUEST_0_QTY0'],
			'L_PAYMENTREQUEST_0_AMT0'        => $details['L_PAYMENTREQUEST_0_AMT0'],
			'L_PAYMENTREQUEST_0_NAME0'       => $details['L_PAYMENTREQUEST_0_NAME0'],
			'L_PAYMENTREQUEST_0_NUMBER0'     => 0,
		) );

		if ( !$response || !isset( $response['ACK'] ) || ( $response['ACK'] != 'Success' && $response['ACK'] != 'SuccessWithWarning' ) ) {
			return false;
		}

		$sld = trim( filter_input( INPUT_GET, 'sld' ) );
		$tld = trim( filter_input( INPUT_GET, 'tld' ) );

		$response = $this->_exec_command( self::COMMAND_PURCHASE, array(
			'sld'           => $sld,
			'tld'           => $tld,
			'UseDNS'        => 'default',
			'UseCreditCard' => 'no',
			'EndUserIP'     => $_SERVER['REMOTE_ADDR'],
		) );

		$this->_log_enom_request( self::REQUEST_PURCHASE_DOMAIN, $response );

		if ( $response && isset( $response->RRPCode ) && $response->RRPCode == 200 ) {
			$this->_populate_dns_records( $tld, $sld );
			return "{$sld}.{$tld}";
		}

		return false;
	}

}