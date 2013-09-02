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
	const RESELLER_API_ENDPOINT  = 'https://resellertest.enom.com/interface.asp';

	const COMMAND_CHECK        = 'check';
	const COMMAND_GET_TLD_LIST = 'gettldlist';
	const COMMAND_RETAIL_PRICE = 'PE_GetRetailPrice';

	/**
	 * Returns reseller internal id.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _get_reseller_id() {
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

		$response = wp_remote_get( add_query_arg( $args, self::RESELLER_API_ENDPOINT ) );
		if ( !is_array( $response ) || !isset( $response['body'] ) ) {
			return false;
		}

		libxml_use_internal_errors( true );
		return simplexml_load_string( $response['body'] );
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

		$uid = trim( filter_input( INPUT_POST, 'map_reseller_enom_uid' ) );
		$need_health_check = !isset( $options[self::RESELLER_ID]['uid'] ) || $options[self::RESELLER_ID]['uid'] != $uid;
		$options[self::RESELLER_ID]['uid'] = $uid;

		$pwd = filter_input( INPUT_POST, 'map_reseller_enom_pwd' );
		$pwd_hash = filter_input( INPUT_POST, 'map_reseller_enom_pwd_hash' );
		if ( $pwd_hash != sha1( $pwd ) ) {
			$options[self::RESELLER_ID]['pwd'] = $pwd;
			$need_health_check = true;
		}

		$options[self::RESELLER_ID]['valid'] = $need_health_check || ( isset( $options[self::RESELLER_ID]['valid'] ) && $options[self::RESELLER_ID]['valid'] == false )
			? $this->_validateCredentials( $options[self::RESELLER_ID]['uid'], $options[self::RESELLER_ID]['pwd'] )
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
	private function _validateCredentials( $uid, $pwd ) {
		$xml = $this->_exec_command( self::COMMAND_CHECK, array(
			'uid' => $uid,
			'pw'  => $pwd,
			'sld' => 'example',
			'tld' => 'com',
		) );

		return isset( $xml->ErrCount ) && $xml->ErrCount == 0;
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

		?>
		<div id="domainmapping-enom-header">
			<div id="domainmapping-enom-logo"></div>
		</div>

		<?php if ( isset( $options['valid'] ) && $options['valid'] == false ) : ?>
		<div class="domainmapping-info domainmapping-info-error">
			<?php _e( 'Looks like your credentials are invalid. Please, enter valid credentials and resave the form.', 'domainmap' ) ?>
		</div>
		<?php endif; ?>

		<div class="domainmapping-info"><?php
			_e( 'Pay attention that if you want to use eNom credit card processing services, this service is available only to resellers who have entered into a credit card processing agreement with eNom.', 'domainmap' )
		?></div>

		<div class="domainmapping-info"><?php
			printf(
				__( 'Also keep in mind that to start using eNom API you have to add your server IP address in the live environment. Go to %s, click "Launch the Support Center" button and submit a new ticket. In the new ticket set "Add IP" subject, type the IP address(es) you wish to add and select API category.', 'domainmap' ),
				'<a href="http://www.enom.com/help/" target="_blank">eNom Help Center</a>'
			)
		?></div>

		<div>
			<p>
				<?php _e( 'In case to use eNom provider, please, enter your account id and password in the fields below.', 'domainmap' ) ?>
			</p>
			<div>
				<label for="enom-uid" class="domainmapping-label"><?php _e( 'Account id:', 'domainmap' ) ?></label>
				<input type="text" id="enom-uid" class="regular-text" name="map_reseller_enom_uid" value="<?php echo esc_attr( $uid ) ?>" autocomplete="off">
			</div>
			<div>
				<label for="enom-pwd" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?></label>
				<input type="password" id="enom-pwd" class="regular-text" name="map_reseller_enom_pwd" value="<?php echo esc_attr( $pwd ) ?>" autocomplete="off">
				<input type="hidden" name="map_reseller_enom_pwd_hash" value="<?php echo $pwd_hash ?>">
			</div>
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

		if ( $xml && isset( $xml->productprice->price ) ) {
			return floatval( $xml->productprice->price );
		}

		return false;
	}

}