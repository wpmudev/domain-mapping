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
 * Abstract reseller class implements all routine stuff required for reseller API works.
 *
 * @category Domainmap
 * @package Reseller
 *
 * @since 4.0.0
 * @abstract
 */
abstract class Domainmap_Reseller {

	const CARD_TYPE_VISA             = 'Visa';
	const CARD_TYPE_AMERICAN_EXPRESS = 'AmEx';
	const CARD_TYPE_MASTERCARD       = 'Mastercard';
	const CARD_TYPE_DISCOVER         = 'Discover';

	const REQUEST_VALIDATE_CREDENTIALS = 1;
	const REQUEST_CHECK_DOMAIN         = 2;
	const REQUEST_GET_TLD_LIST         = 3;
	const REQUEST_GET_RETAIL_PRICE     = 4;
	const REQUEST_PURCHASE_DOMAIN      = 5;
	const REQUEST_SET_DNS_RECORDS      = 6;
	const REQUEST_GET_EXT_ATTRIBUTES   = 7;

	const LOG_LEVEL_DISABLED = 0;
	const LOG_LEVEL_ERRORS   = 1;
	const LOG_LEVEL_ALL      = 2;


	/**
	 * Last errors returned by reseller API.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @var WP_Error
	 */
	protected $_last_errors;

	/**
	 * Whether to cache tlds
	 *
	 * @since 4.2.0
	 *
	 * @access protected
	 * @var bool
	 */
	protected $cache_tlds = true;

	/**
	 * Returns last errors returned by reseller API.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return WP_Error The error object or NULL if no errors appears.
	 */
	public function get_last_errors() {
		return $this->_last_errors;
	}

	/**
	 * Returns reseller internal id.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 */
	public abstract function get_reseller_id();

	/**
	 * Returns reseller title.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @return string The title of reseller provider.
	 */
	public abstract function get_title();

	/**
	 * Renders reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 */
	public abstract function render_options();

	/**
	 * Saves reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @param array $options The array of plugin options.
	 */
	public abstract function save_options( &$options );

	/**
	 * Determines whether reseller API connected properly or not.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @return boolean TRUE if API connected properly, otherwise FALSE.
	 */
	public abstract function is_valid();

	/**
	 * Fetches and returns TLD list accepted by reseller.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 * @return array The array of TLD accepted by reseller.
	 */
	protected abstract function _get_tld_list();

	/**
	 * Returns TLD list accepted by reseller.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of TLD accepted by reseller.
	 */
	public function get_tld_list() {
		$transient = 'reseller-' . $this->get_reseller_id() . '-tlds';
		if( $this->cache_tlds ){
			$tlds = get_site_transient( $transient );
			if ( is_array( $tlds ) && !empty( $tlds ) ) {
				return $tlds;
			}
		}

		$tlds = $this->_get_tld_list();
		sort( $tlds, SORT_STRING );
		set_site_transient( $transient, $tlds, DAY_IN_SECONDS );

		return $tlds;
	}

	/**
	 * Checks domain availability.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 * @param string $tld The top level domain.
	 * @param string $sld The second level domain.
	 * @return boolean TRUE if domain is available to puchase, otherwise FALSE.
	 */
	protected abstract function _check_domain( $tld, $sld );

	/**
	 * Check whether a domain is available for purchasing.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $tld The top level domain.
	 * @param string $sld The second level domain.
	 * @return boolean TRUE if domain is available to puchase, otherwise FALSE.
	 */
	public function check_domain( $tld, $sld ) {
		$transient = sprintf( 'reseller-%s-check-%s.%s', $this->get_reseller_id(), $sld, $tld );
		$available = get_site_transient( $transient );
		if ( $available !== false ) {
			return $available;
		}

		$available = $this->_check_domain( $tld, $sld );
		set_site_transient( $transient, $available ? 1 : 0, HOUR_IN_SECONDS );

		return $available;
	}

	/**
	 * Fetches and returns TLD price.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @param string $tld The top level domain.
	 * @return float The price for the TLD.
	 */
	protected abstract function _get_tld_price( $tld, $period );

	/**
	 * Returns TLD price.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $tld The top level domain.
	 * @return float The price for the TLD.
	 */
	public function get_tld_price( $tld, $period = 1 ) {
		$transient = sprintf( 'reseller-%s-%s-price', $this->get_reseller_id(), $tld );
		if( $this->cache_tlds ){
			$price = get_site_transient( $transient );
			if ( $price != false ) {
				return $price;
			}
		}
		$price = $this->_get_tld_price( $tld, $period );
		set_site_transient( $transient, $price, DAY_IN_SECONDS );

		return $price;
	}

	/**
	 * Returns array of supported card types.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of supported card types.
	 */
	public function get_card_types() {
		return array(
			self::CARD_TYPE_VISA,
			self::CARD_TYPE_MASTERCARD,
			self::CARD_TYPE_AMERICAN_EXPRESS,
			self::CARD_TYPE_DISCOVER,
		);
	}

	/**
	 * Purchases a domain name.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return string|boolean The domain name if purchased successfully, otherwise FALSE.
	 */
	public abstract function purchase();

	/**
	 * Logs request to reseller API.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @global wpdb $wpdb The current database connection.
	 * @param int $type The request type.
	 * @param boolean $valid Determines whether the request has been successfull or not.
	 * @param array|string $errors The error(s) received in the response.
	 * @param mixed $response The response information, received on request.
	 */
	protected function _log_request( $type, $valid, $errors, $response ) {
		global $wpdb;

		// sets last errors if request failed
		if ( !$valid ) {
			$this->_last_errors = new WP_Error();
			foreach ( (array)$errors as $code => $message ) {
				$this->_last_errors->add( $code, $message );
			}
		} else {
			$this->_last_errors = null;
		}

		// get logging level option
		$options = Domainmap_Plugin::instance()->get_options();
		$level = isset( $options['map_reseller_log'] )
			? (int)$options['map_reseller_log']
			: self::LOG_LEVEL_DISABLED;

		// don't log request if logging is disabled or request is valid,
		// but only errors should be logged
		if ( !$type || !$level || ( $valid && $level == self::LOG_LEVEL_ERRORS ) ) {
			return;
		}

		// save requests into the log
		$wpdb->insert( DOMAINMAP_TABLE_RESELLER_LOG, array(
			'user_id'      => get_current_user_id(),
			'provider'     => $this->get_reseller_id(),
			'requested_at' => current_time( 'mysql' ),
			'type'         => $type,
			'valid'        => $valid ? 1 : 0,
			'errors'       => is_array( $errors ) ? implode( PHP_EOL, $errors ) : $errors,
			'response'     => json_encode( $response ),
		), array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' ) );
	}

	/**
	 * Returns the associative array of request types and labels.
	 *
	 * @since 4.0.0
	 *
	 * @static
	 * @access public
	 * @return array The associative array of request types and labels.
	 */
	public static function get_request_types() {
		return array(
			self::REQUEST_VALIDATE_CREDENTIALS => __( 'Verify credentials', 'domainmap' ),
			self::REQUEST_CHECK_DOMAIN         => __( 'Check domain availability', 'domainmap' ),
			self::REQUEST_GET_TLD_LIST         => __( 'Get TLD list', 'domainmap' ),
			self::REQUEST_GET_RETAIL_PRICE     => __( 'Get retail price', 'domainmap' ),
			self::REQUEST_GET_EXT_ATTRIBUTES   => __( 'Get extended attributes', 'domainmap' ),
			self::REQUEST_PURCHASE_DOMAIN      => __( 'Purchase domain', 'domainmap' ),
			self::REQUEST_SET_DNS_RECORDS      => __( 'Set DNS record', 'domainmap' ),
		);
	}

	/**
	 * Renders purchase form.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @param array $domain_info The information about a domain to purchase.
	 * @return string The purchase form html.
	 */
	public abstract function render_purchase_form( $domain_info );

	/**
	 * Returns domain available response HTML with a link on purchase form.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @global wpdb $wpdb Current database connection.
	 * @param string $sld The actual SLD.
	 * @param string $tld The actual TLD.
	 * @param string $purchase_link The purchase URL.
	 * @return string Response HTML.
	 */
	public function get_domain_available_response( $sld, $tld, $purchase_link = false ) {
		global $wpdb, $blog_id;
		if ( !$purchase_link ) {
			$purchase_link = add_query_arg( array(
				'action'  => Domainmap_Plugin::ACTION_SHOW_PURCHASE_FORM,
				'nonce'   => wp_create_nonce( Domainmap_Plugin::ACTION_SHOW_PURCHASE_FORM ),
				'tld'     => $tld,
				'blog'    => $blog_id,
				'success' => urlencode( add_query_arg( 'page', 'domainmapping', admin_url( 'tools.php' ) ) ),
				'cancel'  => urlencode( site_url( add_query_arg( array(
					'sld' => $sld,
					'tld' => $tld,
				), wp_get_referer() ) ) ),
			), admin_url( '/admin-ajax.php' ) );

			$purchase_link = sprintf(
				'<a class="domainmapping-purchase-link" href="%s"><b>%s</b></a>',
				esc_url( $purchase_link ),
				__( 'Purchase this domain.', 'domainmap' )
			);
		}

		return sprintf(
			'<div class="domainmapping-info domainmapping-info-success"><b>%s</b> %s <b>%s</b> %s &nbsp;%s.<div class="domainmapping-clear"></div>%s</div>',
			strtoupper( "{$sld}.{$tld}" ),
			__( 'is available to purchase for', 'domainmap' ),
			$this->get_currency_symbol(),
			$this->get_tld_price( $tld ),
			__( 'per year', 'domainmap' ),
			$purchase_link
		);
	}

	/**
	 * Proceeds PayPal checkout.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function proceed_paypal_checkout() {}

	/**
	 * Completes PayPal checkout and purchase a domain.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return string|boolean Returns domain name on success, otherwise FALSE.
	 */
	public function complete_paypal_checkout() {
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
		return false;
	}

	/**
	 * Renders registration form.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 */
	public function render_registration_form() {}

	/**
	 * Determines whether registration form should be displayed on HTTPS page or not.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @return boolean TRUE if SSL is required, otherwise is FALSE.
	 */
	public function registration_over_ssl() {
		return false;
	}

	/**
	 * Registers new account.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @return boolean TRUE if account registered successfully, otherwise FALSE.
	 */
	public function regiser_account() {
		return false;
	}

	/**
	 * Encodes reseller class.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 * @access public
	 * @param string $class The class name of a reseller to encode.
	 * @return string Encoded class name.
	 */
	public static function encode_reseller_class( $class ) {
		return dechex( crc32( $class ) );
	}

	/**
	 * Returns user IP address.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 * @access protected
	 * @return string Remote IP address on success, otherwise FALSE.
	 */
	protected static function _get_remote_ip() {
		$flag = !WP_DEBUG ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : null;
		$keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		$remote_ip = false;
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( array_filter( array_map( 'trim', explode( ',', $_SERVER[$key] ) ) ) as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP, $flag ) !== false ) {
						$remote_ip = $ip;
						break;
					}
				}
			}
		}

		return $remote_ip;
	}


	/**
	 * Retrieves current currency symbol
	 *
	 * To provide compatibility to older php version, sub-class method is not directly called
	 *
	 * @since 4.3.1
	 * @return string
	 */
	public function get_currency_symbol(){
		return DM_Currencies::get_symbol( Domainmap_Plugin::instance()->get_reseller()->get_currency() );
	}
}