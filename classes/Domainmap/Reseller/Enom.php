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

	const RESELLER_ID            = 'enom';
	const RESELLER_API_ENDPOINT  = 'https://resellertest.enom.com/interface.asp?';

	const COMMAND_CHECK        = 'Check';
	const COMMAND_GET_TLD_LIST = 'GetTLDList';
	const COMMAND_RETAIL_PRICE = 'PE_GetRetailPrice';
	const COMMAND_PURCHASE     = 'Purchase';
	const COMMAND_SET_HOSTS    = 'SetHosts';

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

		$response = wp_remote_get( self::RESELLER_API_ENDPOINT . http_build_query( $args ) );
		if ( !is_array( $response ) || !isset( $response['body'] ) ) {
			return false;
		}

		libxml_use_internal_errors( true );
		return simplexml_load_string( $response['body'] );
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
	private function _log_enom_request( $type, SimpleXMLElement $xml ) {
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
			self::GATEWAY_PROSITES => __( 'Pro Sites payment gateway', 'domainmap' ),
		);
	}

	/**
	 * Returns current reseller payment gateway.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param array $options The plugin options.
	 * @return string The current gateway key.
	 */
	private function _get_gateway( $options = null ) {
		if ( !$options ) {
			$options = Domainmap_Plugin::instance()->get_options();
			$options = isset( $options[self::RESELLER_ID] ) ? $options[self::RESELLER_ID] : array();
		}

		$gateways = $this->_get_gateways();
		$gateway = isset( $options['gateway'] ) && isset( $gateways[$options['gateway']] ) ? $options['gateway'] : self::GATEWAY_ENOM;

		if ( $gateway == self::GATEWAY_PROSITES && !function_exists( 'is_pro_site' ) ) {
			$gateway = self::GATEWAY_ENOM;
		}

		return $gateway;
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

		$uid = isset( $options['uid'] ) ? $options['uid'] : '';
		$pwd = isset( $options['pwd'] ) ? str_shuffle( $options['pwd'] ) : '';
		$pwd_hash = sha1( $pwd );

		$gateways = $this->_get_gateways();
		$gateway = $this->_get_gateway( $options );

		?><div id="domainmapping-enom-header">
			<div id="domainmapping-enom-logo"></div>
		</div>

		<?php if ( isset( $options['valid'] ) && $options['valid'] == false ) : ?>
		<div class="domainmapping-info domainmapping-info-error">
			<?php _e( 'Looks like your credentials are invalid. Please, enter valid credentials and resave the form.', 'domainmap' ) ?>
		</div>
		<?php endif; ?>

		<?php if ( $gateway == self::GATEWAY_ENOM ) : ?>
		<div class="domainmapping-info domainmapping-info-warning"><?php
			_e( 'You use eNom credit card processing service. Pay attention that this service is available only to resellers who have entered into a credit card processing agreement with eNom.', 'domainmap' )
		?></div>
		<?php endif; ?>

		<div class="domainmapping-info"><?php
			printf(
				__( 'Keep in mind that to start using eNom API you have to add your server IP address in the live environment. Go to %s, click "Launch the Support Center" button and submit a new ticket. In the new ticket set "Add IP" subject, type the IP address(es) you wish to add and select API category.', 'domainmap' ),
				'<a href="http://www.enom.com/help/" target="_blank">eNom Help Center</a>'
			)
		?></div>

		<div>
			<h4><?php _e( 'Enter your account id and password:', 'domainmap' ) ?></h4>
			<div>
				<label for="enom-uid" class="domainmapping-label"><?php _e( 'Account id:', 'domainmap' ) ?></label>
				<input type="text" id="enom-uid" class="regular-text" name="map_reseller_enom_uid" value="<?php echo esc_attr( $uid ) ?>" autocomplete="off">
			</div>
			<div>
				<label for="enom-pwd" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?></label>
				<input type="password" id="enom-pwd" class="regular-text" name="map_reseller_enom_pwd" value="<?php echo esc_attr( $pwd ) ?>" autocomplete="off">
				<input type="hidden" name="map_reseller_enom_pwd_hash" value="<?php echo $pwd_hash ?>">
			</div>

			<?php if ( function_exists( 'is_pro_site' ) ) : ?>
			<h4><?php _e( 'Select payment gateway:', 'domainmap' ) ?></h4>
			<ul>
				<?php foreach ( $gateways as $key => $label ) : ?>
				<li>
					<label>
						<input type="radio" class="domainmapping-radio" name="map_reseller_enom_payment" value="<?php echo esc_attr( $key ) ?>"<?php checked( $key, $gateway )  ?>>
						<?php echo esc_html( $label ) ?>
					</label>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div><?php
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
		) );

		$this->_log_enom_request( self::REQUEST_PURCHASE_DOMAIN, $response );

		if ( $response && isset( $response->RRPCode ) && $response->RRPCode == 200 ) {
			$options = Domainmap_Plugin::instance()->get_options();
			if ( !empty( $options['map_ipaddress'] ) ) {
				$args = array(
					'sld' => $sld,
					'tld' => $tld,
				);

				$i = 0;
				foreach ( explode( ',', $options['map_ipaddress'] ) as $ip ) {
					if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
						$i++;
						$args["HostName{$i}"] = "@";
						$args["RecordType{$i}"] = "A";
						$args["Address{$i}"] = $ip;
					}
				}

				$response = $this->_exec_command( self::COMMAND_SET_HOSTS, $args );
				$this->_log_enom_request( self::REQUEST_SET_DNS_RECORDS, $response );
			}

			return "{$sld}.{$tld}";
		}

		return false;
	}

}