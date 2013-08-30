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
				$price = floatval( $reseller->get_tld_price( $tld ) );
			}

			wp_send_json_success( array(
				'available' => $available,
				'html'      => $available
					? sprintf(
						'<div class="domainmapping-info domainmapping-info-success"><b>%s</b> %s <b>$%s</b>.<br><a href="javascript:;"><b>%s</b></a></div>',
						strtoupper( $domain ),
						__( 'is available to purchase for', 'domainmap' ),
						number_format( $price, 2 ),
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

}