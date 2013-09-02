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
 * The module responsible for handling AJAX requests sent at domain purchase page.
 *
 * @category Domainmap
 * @package Module
 * @subpackage Ajax
 *
 * @since 4.0.0
 */
class Domainmap_Module_Ajax_Purchase extends Domainmap_Module_Ajax {

	const NAME = __CLASS__;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the Domainap_Plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		// add ajax actions
		$this->_add_ajax_action( 'domainmapping_check_domain', 'check_domain' );
		$this->_add_ajax_action( 'domainmapping_purchase_domain', 'purchase_domain' );
		$this->_add_ajax_action( 'domainmapping_get_purchase_form', 'get_purchase_form' );
	}

	/**
	 * Builds and returns user based transient name.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $transient Transient name.
	 * @return string User based transient name.
	 */
	private function _get_transient_name( $transient ) {
		return sprintf( 'domainmap-%s-%s', get_current_user_id(), $transient );
	}

	/**
	 * Checks the domain availability and returns it's price.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function check_domain() {
		self::_check_premissions( 'domainmapping_check_domain' );

		$sld = strtolower( trim( filter_input( INPUT_POST, 'sld' ) ) );
		$tld = strtolower( trim( filter_input( INPUT_POST, 'tld' ) ) );

		$message = false;
		$domain = "{$sld}.{$tld}";
		if ( self::_validate_domain_name( $domain ) ) {
			$reseller = $this->_plugin->get_reseller();

			$price = false;
			$available = $reseller->check_domain( $tld, $sld );
			if ( $available ) {
				$price = '$' . number_format( floatval( $reseller->get_tld_price( $tld ) ), 2 );
			}

			set_transient( $this->_get_transient_name( 'checkdomain' ), array(
				'domain' => $domain,
				'price'  => $price,
				'sld'    => $sld,
				'tld'    => $tld,
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'available' => $available,
				'html'      => $available
					? sprintf(
						'<div class="domainmapping-info domainmapping-info-success"><b>%s</b> %s <b>%s</b> %s.<br><a class="domainmapping-purchase-link" href="%s"><b>%s</b></a></div>',
						strtoupper( $domain ),
						__( 'is available to purchase for', 'domainmap' ),
						$price,
						__( 'per year', 'domainmap' ),
						add_query_arg( array(
							'action' => 'domainmapping_get_purchase_form',
							'nonce'  => wp_create_nonce( 'domainmapping_get_purchase_form' ),
							'tld'    => $tld,
						), admin_url( 'admin-ajax.php' ) ),
						__( 'Purchase this domain.', 'domainmap' )
					)

					: sprintf(
						'<div class="domainmapping-info domainmapping-info-error"><b>%s</b> %s.</div>',
						$domain,
						__( 'is not available to purchase', 'domainmap' )
					),
			) );
		} else {
			$message = __( 'Domain name is invalid.', 'domainmap' );
		}

		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * Returns HTML for purchase form.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @global WP_User $current_user The current user object.
	 */
	public function get_purchase_form() {
		global $current_user;

		self::_check_premissions( 'domainmapping_get_purchase_form' );

		$reseller = $this->_plugin->get_reseller();
		$info = get_transient( $this->_get_transient_name( 'checkdomain' ) );
		if ( !$info || !$reseller ) {
			wp_send_json_error();
		}

		get_currentuserinfo();
		$cardholder = trim( $current_user->user_firstname . ' ' . $current_user->user_lastname );
		if ( empty( $cardholder ) ) {
			$cardholder = __( 'Your name', 'domainmap' );
		}

		$render = new Domainmap_Render_Page_Purchase( $info );
		$render->cardtypes = $reseller->get_card_types();
		$render->cardholder = $cardholder;
		$render->countries = $this->_plugin->get_countries();
		wp_send_json_success( array( 'html' => $render->to_html() ) );
	}

	/**
	 * Purchases a domain name and sets up DNS A record.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function purchase_domain() {
		$message = false;

		wp_send_json_error( array(
			'message' => $message,
		) );
	}

}