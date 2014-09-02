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
 * WHMCS reseller API class.
 *
 * @category Domainmap
 * @package Reseller
 *
 * @since 4.2.0
 */
class Domainmap_Reseller_WHMCS extends Domainmap_Reseller {

	const RESELLER_ID = 'whmcs';


	const ENVIRONMENT_PRODUCTION = 'prod';
	const ENVIRONMENT_TEST       = 'test';

	const COMMAND_CHECK_LOGIN_CREDS  = 'getclients';
	const COMMAND_VALIDATE_LOGIN     = 'validatelogin';
	const COMMAND_ADD_ORDER          = 'addorder';
	const COMMAND_CHECK              = 'domainwhois';
	const COMMAND_GET_GATEWAYS       = 'getpaymentmethods';
	const COMMAND_REGISTER_CLIENT       = 'addclient';

	const COMMAND_RETAIL_PRICE       = 'PE_GetRetailPrice';
	const COMMAND_PURCHASE           = 'Purchase';
	const COMMAND_SET_HOSTS          = 'SetHosts';
	const COMMAND_GET_EXT_ATTRIBUTES = 'GetExtAttributes';
	const COMMAND_REGISTER_ACCOUNT   = 'CreateAccount';

	const GATEWAY_PAYPAL     = 'paypal';
	const GATEWAY_PROSITES = 'prosites';

    const ACTION_CHECK_CLIENT_LOGIN      = 'dm_whmcs_validate_client_login';
    const ACTION_ORDER_DOMAIN            = 'dm_whmcs_order_domain';
    const ACTION_REGISTER_CLIENT         = 'dm_whmcs_register_client';

    protected $cache_tlds = false;
    /**
	 * Returns reseller internal id.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 */
	public function get_reseller_id() {
		return self::RESELLER_ID;
	}



    /**
     * Executes remote command and returns response of execution.
     *
     * @since 4.2.0
     *
     * @access private
     * @param string $command The command name.
     * @param array $args Additional optional arguments.
     * @param string $endpoint The concrete endpoint to use for the request.
     * @return SimpleXMLElement Returns simplexml object on success, otherwise FALSE.
     */

    /**
     * Executes remote command and returns response of execution.
     *
     * @param $command
     * @param array $arguments
     * @return WP_Error | stdClass
     */
    public static function exec_command( $command, $arguments = array() ){
        $options = Domainmap_Plugin::instance()->get_options();

        $args = array();
        $args['username']       = isset( $options[self::RESELLER_ID]['uid'] ) ?  $options[self::RESELLER_ID]['uid'] : false;
        $args['password']       = isset( $options[self::RESELLER_ID]['pwd'] ) ?  md5( $options[self::RESELLER_ID]['pwd'] ) : false;
        $api_url                = isset( $options[self::RESELLER_ID]['api'] ) ?  $options[self::RESELLER_ID]['api']  : false;
        $args['responsetype']   = "json";
        $args['action']         = $command;
        $args                   = array_merge( $args, $arguments );

        if( !($args['username'] && $args['password'] && $api_url)  ) return;

        $response = wp_remote_post($api_url, array(
            'timeout' => 3,
            'body' => $args
        ));

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ) );

        $error = new WP_Error();
        if ( $response_code  === 200 ){
           if( isset( $response_body->result ) && $response_body->result === "success" ){
               return $response_body;
           }elseif( isset( $response_body->result ) && $response_body->result === "error"  ){
               $error->add( $response_code, strip_tags( $response_body->message ) );
               return $error;
           }
        }else{
            $error->add( $response_code, isset( $response_body->message ) ? $response_body->message : __("Unknown", domain_map::Text_Domain) );
            return $error;
        }
    }

    private function _exec_command( $command, $arguments = array() ) {
        return self::exec_command( $command, $arguments );
    }


	/**
	 * Saves reseller options.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 * @param array $options The array of plugin options.
	 */
	public function save_options( &$options ) {
		if ( !isset( $options[self::RESELLER_ID] ) || !is_array( $options[self::RESELLER_ID] ) ) {
			$options[self::RESELLER_ID] = array();
		}

        // api url
        $api = trim( filter_input( INPUT_POST, 'map_reseller_whmcs_api' ) );
        $options[self::RESELLER_ID]['api'] = $api;

		// user name
		$uid = trim( filter_input( INPUT_POST, 'map_reseller_whmcs_uid' ) );
		$need_health_check = !isset( $options[self::RESELLER_ID]['uid'] ) || $options[self::RESELLER_ID]['uid'] != $uid;
		$options[self::RESELLER_ID]['uid'] = $uid;

		// password
		$pwd = filter_input( INPUT_POST, 'map_reseller_whmcs_pwd' );
		$pwd_hash = filter_input( INPUT_POST, 'map_reseller_whmcs_pwd_hash' );
		if ( $pwd_hash !== md5( $pwd ) ) {
			$options[self::RESELLER_ID]['pwd'] = $pwd;
			$need_health_check = true;
		}else{
            $need_health_check = !isset( $options[self::RESELLER_ID]['pwd'] ) || $options[self::RESELLER_ID]['pwd'] != $pwd;
        }

        /**
         * Tlds
         */
        $tlds = $_POST["dm_whmcs_tld"];
        $options[self::RESELLER_ID]['tlds'] = $tlds;

		// payment gateway
		$gateway = filter_input( INPUT_POST, 'map_reseller_whmcs_payment' );
		if ( $gateway !== false ) {
			$gateways = $this->_get_gateways();
			if ( isset( $gateways[$gateway] ) ) {
				$options[self::RESELLER_ID]['gateway'] = $gateway;
			}
		}

        // client registration
        $options[self::RESELLER_ID]['enable_registration'] = (bool) filter_input( INPUT_POST, 'map_reseller_whmcs_client_registration' );

		// validate credentials
		$options[self::RESELLER_ID]['valid'] = $need_health_check || ( isset( $options[self::RESELLER_ID]['valid'] ) && $options[self::RESELLER_ID]['valid'] === false )
			? $this->_validate_credentials()
			: true;
	}

	/**
	 * Validates API credentials.
	 *
	 * @sicne 4.2.0
	 *
	 * @access private
	 * @param string $uid The user id.
	 * @param string $pwd The user password.
	 * @param string $environment Current environment.
	 * @return boolean TRUE if API credentials are valid, otherwise FALSE.
	 */
	private function _validate_credentials() {
		$json = $this->_exec_command( self::COMMAND_CHECK_LOGIN_CREDS );
        $this->_log_whmcs_request(self::REQUEST_VALIDATE_CREDENTIALS, $json);
        $valid = is_object( $json ) && !is_wp_error( $json ) ? $json->result === 'success' : false;

        $transient = 'whmcs_errors_' . get_current_user_id();
        if ( !$valid ) {
            set_site_transient( $transient, $this->get_last_errors() );
        } else {
            delete_site_transient( $transient );
        }

        return $valid;
	}

	/**
	 * Returns the array of payments gateways supported by reseller.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 * @global ProSites $psts The instance of ProSites plugin class.
	 * @return array The associative array of payment gateways.
	 */
	private function _get_gateways() {

        $transient = "dm-whmcs-gateways";
        $gateways = get_site_transient( $transient );
        if( $gateways !== false ){
            return $gateways;
        }

        $gateways = array();
        $options = Domainmap_Plugin::instance()->get_options();
        if( $options[self::RESELLER_ID]['valid'] ){
            $object = $this->exec_command( self::COMMAND_GET_GATEWAYS );
            if( !is_wp_error( $object ) ){
                foreach( $object->paymentmethods->paymentmethod as $method ) {
                    $gateways[$method->module] = $method->displayname;
                }
                set_site_transient( $transient, $gateways, DAY_IN_SECONDS );
            }
        }

		return $gateways;
	}

	/**
	 * Returns current reseller payment gateway.
	 *
	 * @since 4.2.0
	 *
	 * @param array $options The plugin options.
	 * @return string The current gateway key.
	 */
	public function get_gateway( $options = null, $name = false ) {

		// if no options were passed, take it from the plugin instance
		if ( !$options ) {
			$options = Domainmap_Plugin::instance()->get_options();
			$options = isset( $options[self::RESELLER_ID] ) ? $options[self::RESELLER_ID] : array();
		}
		// fetch gateway information
		$gateways = $this->_get_gateways();

		$gateway = isset( $options['gateway'] ) && isset( $gateways[$options['gateway']] )
			? $options['gateway']
			: self::GATEWAY_PAYPAL;
        if( $name ){
          return isset( $gateways[$gateway] ) ? $gateways[$gateway] : $gateway;
        }
		return $gateway;
	}


	/**
	 * Returns current environment.
	 *
	 * @since 4.2.0
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
	 * @since 4.2.0
	 *
	 * @access public
	 * @return string The title of reseller provider.
	 */
	public function get_title() {
		return 'WHMCS';
	}

	/**
	 * Renders reseller options.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 */
	public function render_options() {
		$options = Domainmap_Plugin::instance()->get_options();
		$options = isset( $options[self::RESELLER_ID] ) ? $options[self::RESELLER_ID] : array();



		$template = new Domainmap_Render_Reseller_WHMCS_Settings( $options );

		$template->gateways = $this->_get_gateways();
		$template->gateway = $this->get_gateway( $options );
		$template->errors = get_site_transient( 'whmcs_errors_' . get_current_user_id() );

		$template->render();
	}

	/**
	 * Determines whether reseller API connected properly or not.
	 *
	 * @since 4.2.0
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
	 * @since 4.2.0
	 *
	 * @access protected
	 * @return array The array of TLD accepted by reseller.
	 */
	protected function _get_tld_list() {
        $tlds = self::get_domain_pricing();
        $tlds = array_map( array($this, "_callback_extract_tld" ), $tlds);
        return $tlds;
	}

    /**
     * Callback to extract tld from domain pricing records
     *
     * @since 4.2.0
     * @param $item
     * @return mixed
     */
    private function _callback_extract_tld( $item ){
        return str_replace( ".", "",  $item['tld'] );
    }

	/**
	 * Checks domain availability.
	 *
	 * @since 4.2.0
	 *
	 * @access protected
	 * @param string $tld The top level domain.
	 * @param string $sld The second level domain.
	 * @return boolean TRUE if domain is available to puchase, otherwise FALSE.
	 */
	protected function _check_domain( $tld, $sld ) {
		$json = $this->_exec_command( self::COMMAND_CHECK,  array(
			'domain' => $sld . "." . $tld,
		) );

		$this->_log_whmcs_request( self::REQUEST_CHECK_DOMAIN, $json );

        return isset( $json->status ) ? $json->status === "available" : false;
	}

	/**
	 * Fetches and returns TLD price.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 * @param string $tld The top level domain.
	 * @param int $period Domain registration period.
	 * @return float The price for the TLD.
	 */
	protected function _get_tld_price( $tld, $period ) {
		$pricing = self::get_domain_pricing();
        foreach( $pricing as $option ){
            if( $option['tld'] === "." . $tld ){
                return $option['price'][$period - 1];
            }
        }
	}

	/**
	 * Purchases a domain name.
	 *
	 * @since 4.2.0
	 *
	 * @access protected
	 * @return string|boolean The domain name if purchased successfully, otherwise FALSE.
	 */
	public function purchase() {
		$sld = trim( filter_input( INPUT_POST, 'sld' ) );
		$tld = trim( filter_input( INPUT_POST, 'tld' ) );
		$expiry = array_map( 'trim', explode( '/', filter_input( INPUT_POST, 'card_expiration' ), 2 ) );

		$billing_phone = '+' . preg_replace( '/[^0-9\.]/', '', filter_input( INPUT_POST, 'billing_phone' ) );
		$registrant_phone = '+' . preg_replace( '/[^0-9\.]/', '', filter_input( INPUT_POST, 'registrant_phone' ) );
		$registrant_fax = '+' . preg_replace( '/[^0-9\.]/', '', filter_input( INPUT_POST, 'registrant_fax' ) );

		$response = $this->_exec_command( self::COMMAND_PURCHASE, array(
			'sld'                        => $sld,
			'tld'                        => $tld,
			'UseDNS'                     => 'default',
			'ChargeAmount'               => $this->get_tld_price( $tld ),
			'EndUserIP'                  => self::_get_remote_ip(),
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

		$this->_log_whmcs_request( self::REQUEST_PURCHASE_DOMAIN, $response );

		if ( $response && isset( $response->RRPCode ) && $response->RRPCode == 200 ) {
			$this->_populate_dns_records( $tld, $sld );
			return "{$sld}.{$tld}";
		}

		return false;
	}

	/**
	 * Populates either DNS A or CNAME records for purchased domain.
	 *
	 * @since 4.2.0
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
			// fetch unchanged domain name from database, because get_option function could return mapped domain name
			$basedomain = parse_url( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" ), PHP_URL_HOST );
			// fetch domain DNS A records
			$dns = @dns_get_record( $basedomain, DNS_A );
			if ( is_array( $dns ) ) {
				$ips = wp_list_pluck( $dns, 'ip' );
			}
		}

		// if we have an ip address to populate DNS record, then try to detect if we use shared or dedicated hosting
		$dedicated = false;
		if ( !empty( $ips ) ) {
			$check = sha1( time() );

			switch_to_blog( 1 );
			$ajax_url = admin_url( 'admin-ajax.php' );
			$ajax_url = str_replace( parse_url( $ajax_url, PHP_URL_HOST ), current( $ips ), $ajax_url );
			restore_current_blog();

			$response = wp_remote_request( add_query_arg( array(
				'action' => Domainmap_Plugin::ACTION_HEARTBEAT_CHECK,
				'check'  => $check,
			), $ajax_url ) );

			$dedicated = !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 && wp_remote_retrieve_body( $response ) == $check;
		}

		// populate request arguments
		if ( !empty( $ips ) && $dedicated ) {
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
		} else {
			// network is hosted on shared hosting and we can use DNS CNAME records for it
			$origin = $wpdb->get_row( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = " . intval( $wpdb->blogid ) );

			$args['HostName1'] = "{$sld}.{$tld}";
			$args['RecordType1'] = 'CNAME';
			$args['Address1'] = "{$origin->domain}.";

			$args['HostName2'] = "www.{$sld}.{$tld}";
			$args['RecordType2'] = 'CNAME';
			$args['Address2'] = "{$origin->domain}.";
		}

		// setup DNS records if it has been populated
		if ( !empty( $args ) ) {
			$args['sld'] = $sld;
			$args['tld'] = $tld;

			$response = $this->_exec_command( self::COMMAND_SET_HOSTS, $args );
			$this->_log_whmcs_request( self::REQUEST_SET_DNS_RECORDS, $response );
		}
	}

	/**
	 * Renders purchase form.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 * @global WP_User $current_user The current user object.
	 * @param array $domain_info The information about a domain to purchase.
	 */
	public function render_purchase_form( $domain_info ) {
		global $current_user;

		get_currentuserinfo();
		$cardholder = trim( $current_user->user_firstname . ' ' . $current_user->user_lastname );
		if ( empty( $cardholder ) ) {
			$cardholder = __( 'Your name', 'domainmap' );
		}
        ob_start();
		$render = new Domainmap_Render_Reseller_WHMCS_Purchase( $domain_info );
		$render->errors = $this->get_last_errors();
		$render->render();
        return ob_get_clean();
	}


	/**
	 * Returns domain available response HTML with a link on purchase form or paypal checkout.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin class.
	 * @param string $sld The actual SLD.
	 * @param string $tld The actual TLD.
	 * @param string $purchase_link The purchase URL.
	 * @return string Response HTML.
	 */
	public function get_domain_available_response( $sld, $tld, $purchase_link = false ) {
        ob_start();

        printf(
            '<div class="domainmapping-info domainmapping-info-success"><b>%s</b> %s <b>$%s</b> %s.<div class="domainmapping-clear"></div>',
            strtoupper( "{$sld}.{$tld}" ),
            __( 'is available to purchase for', 'domainmap' ),
            $this->get_tld_price( $tld ),
            __( 'per year', 'domainmap' )
        );
        $register_link = add_query_arg( array(
            'action'   => Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM,
            'nonce'    => wp_create_nonce( Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM ),
            'reseller' => self::encode_reseller_class( __CLASS__ ),
        ), admin_url( 'admin-ajax.php' ) );
        ?>
        <br/>
        <p><?php _e("Login to WHMCS with your client details: ", domain_map::Text_Domain); ?></p>
                <form action="" method="post" id="dm_whmcs_client_login">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="dm_client_email"><?php _e("Email: ", domain_map::Text_Domain); ?></label></th>
                            <td>
                                <input type="email" id="dm_client_email" name="dm_client_email" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="dm_client_pass"><?php _e("Password: ", domain_map::Text_Domain); ?></label></th>
                            <td>
                                <input type="password" id="dm_client_pass" name="dm_client_pass" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button class="button-primary button"><?php _e("Submit", domain_map::Text_Domain); ?></button>
                    </p>
                    <?php if( $this->allow_registration() ): ?>
                    <p>
                        <strong>
                        <?php
                            printf( __("Or <a href='%s' id='dm_whmcs_register_client'>click to signup as a new client</a>", domain_map::Text_Domain), $register_link );
                        ?>
                        </strong>
                    </p>
                    <?php endif; ?>
                </form>
        </div>
        <?php
        return ob_get_clean();
	}

	/**
	 * Executes SetExpressCheckout command of PayPal API and returns response.
	 *
	 * @since 4.2.0
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
			'action' => Domainmap_Plugin::ACTION_PAYPAL_DO_EXPRESS_CHECKOUT,
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
	 * @since 4.2.0
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
	 * @since 4.2.0
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
	 * @since 4.2.0
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

		$this->_log_whmcs_request( self::REQUEST_PURCHASE_DOMAIN, $response );

		if ( $response && isset( $response->RRPCode ) && $response->RRPCode == 200 ) {
			$this->_populate_dns_records( $tld, $sld );
			return "{$sld}.{$tld}";
		}

		return false;
	}

	/**
	 * Determines whether reseller supports accounts registration.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @return boolean TRUE if reseller supports account registration, otherwise FALSE.
	 */
	public function support_account_registration() {
		return true;
	}

	/**
	 * Renders registration form.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @global WP_User $current_user The current user object.
	 */
	public function render_registration_form() {
		global $current_user;

		get_currentuserinfo();
		$cardholder = trim( $current_user->user_firstname . ' ' . $current_user->user_lastname );
		if ( empty( $cardholder ) ) {
			$cardholder = __( 'Your name', 'domainmap' );
		}

		$template = new Domainmap_Render_Reseller_WHMCS_Register();

		$template->errors = $this->get_last_errors();
		$template->countries = Domainmap_Plugin::instance()->get_countries();

		$template->render();
	}

	/**
	 * Registers new account.
	 *
	 * @since 4.2.0
	 *
	 * @access public
	 * @return boolean TRUE if account registered successfully, otherwise FALSE.
	 */
	public function register_account() {

	}

    /**
     * Orders domain name by making api call to WHMCS endpoint
     *
     * @since 4.2
     *
     * @param $client_id
     * @param $domain
     */
    public static function order_domain( $client_id, $domain ){
        $options = Domainmap_Plugin::instance()->get_options();
        $paymentmethod = isset( $options[Domainmap_Reseller_WHMCS::RESELLER_ID] ) ? $options[Domainmap_Reseller_WHMCS::RESELLER_ID] : $options['gateway'];
        $nameserver1 = "";
        $nameserver2 = "";
        $args = array(
            "clientid" => $client_id,
            "domaintype" => "register",
            "domain" => $domain,
            "regperiod" => "1",
            "dnsmanagement" => "on",
            "idprotection" => "on",
            "nameserver1" => $nameserver1,
            "nameserver2" => $nameserver2,
//            "promocode" => $promocode,
            "paymentmethod" => $paymentmethod
        );

        Domainmap_Reseller_WHMCS::exec_command( Domainmap_Reseller_WHMCS::COMMAND_ADD_ORDER  , $args );
    }

    /**
     * Retrieves domain pricing
     *
     * @since 4.2.0
     * @return array
     */
    public static function get_domain_pricing(){
        $options =  Domainmap_Plugin::instance()->get_options();
        $options = $options[Domainmap_Reseller_WHMCS::RESELLER_ID];
        return isset( $options['tlds'] ) ? $options['tlds'] : array();
    }

    /**
     * Retrieves if client registration is allowed
     *
     * @since 4.2.0
     *
     * @return bool
     */
    public function allow_registration(){
        $options =  Domainmap_Plugin::instance()->get_options();
        $options = $options[Domainmap_Reseller_WHMCS::RESELLER_ID];
        return $options['enable_registration'];
    }

    /**
     * Logs request to reseller API.
     *
     * @since 4.2.0
     *
     * @access protected
     * @param int $type The request type.
     * @param SimpleXMLElement $response The response information, received on request.
     */
    private function _log_whmcs_request( $type, $object ) {
        if ( !is_object( $object ) ) {
            return;
        }

        $valid = false;
        $errors = array();

        if ( is_wp_error( $object ) ) {
            $errors = $object->get_error_messages();
        } elseif ( is_object( $object ) && !is_wp_error( $object ) ) {
                $valid = $object->result === "success";
            if ( !$valid ) {
                $errors = $object->message;
            }
        } else {
            $errors[] = __( 'Unexpected error appears during request processing. Please, try again later.', 'domainmap' );
            if ( filter_input( INPUT_GET, 'debug' ) ) {
                $errors[] = $object;
            }
        }

        $this->_log_request( $type, $valid, $errors, $object );
    }
}