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
 * The module responsible for setup plugin environment.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_Setup extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->_add_action( 'init', 'register_scripts' );
		$this->_add_action( 'plugins_loaded', 'load_text_domain' );
		$this->_add_filter( 'domainmapping_resellers', 'setup_resellers' );

		// load the WPMUDEV dashboard notification library
		$notices = new WPMUDEV_Dashboard_Notice();
	}

	/**
	 * Loads plugin text domain.
	 *
	 * @since 4.0.0
	 * @action plugins_loaded
	 * @uses load_textdomain() To load translations for the plugin.
	 *
	 * @access public
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'domainmap', false, dirname( plugin_basename( DOMAINMAP_BASEFILE ) ) . '/languages/' );
	}

	/**
	 * Registers javascript and stylesheet files.
	 *
	 * @since 4.0.0
	 * @action init
	 * @uses plugins_url() To generate base URL of assets files.
	 * @uses wp_register_script() To register javascript files.
	 * @uses wp_localize_script()  To localize javascript files.
	 * @uses wp_register_style() To register CSS files.
	 *
	 * @access public
	 * @global type $wp_styles
	 */
	public function register_scripts() {
		global $wp_styles;

		$baseurl = plugins_url( '/', DOMAINMAP_BASEFILE );

		// enqueue scripts
		wp_register_script( 'jquery-payment', $baseurl . 'js/jquery.payment.js', array( 'jquery' ), '1.0.1', true );
		wp_register_script( 'domainmapping-admin', $baseurl . 'js/admin.js', array( 'jquery' ), Domainmap_Plugin::VERSION, true );
		wp_localize_script( 'domainmapping-admin', 'domainmapping', array(
			'button'  => array(
				'close' => __( 'OK', 'domainmap' ),
			),
			'message' => array(
				'unmap'             => __( 'You are about to unmap selected domain. Do you really want to proceed?', 'domainmap' ),
				'empty'             => __( 'Please, enter not empty domain name.', 'domainmap' ),
				'deselect'          => __( 'You are about to deselect your primary domain. Do you really want to proceed?', 'domainmap' ),
				'valid_selection'   => __( 'You are about to change your primary domain. Do you really want to proceed?', 'domainmap' ),
				'invalid_selection' => __( 'You are about to select invalid domain as primary. It could cause unexpected issues at your front end. Do you really want to proceed?', 'domainmap' ),

				'invalid' => array(
					'card_number' => __( 'Credit card number is invalid.', 'domainmap' ),
					'card_type'   => __( 'Credit card type is invalid.', 'domainmap' ),
					'card_expiry' => __( 'Credit card expiry date is invalid.', 'domainmap' ),
					'card_cvv'    => __( 'Credit card CVV2 code is invalid.', 'domainmap' ),
				),

				'purchase' => array(
					'success' => __( 'Domain name has been purchased successfully.', 'domainmap' ),
					'failed'  => __( 'Domain name purchase has failed.', 'domainmap' ),
				),
			),
		) );

		// enqueue styles
		wp_register_style( 'bootstrap-glyphs', $baseurl . 'css/bootstrap-glyphs.min.css', array(), '2.3.2' );
		wp_register_style( 'font-awesome', $baseurl . 'css/font-awesome.min.css', array(), '3.2.1' );
		wp_register_style( 'font-awesome-ie', $baseurl . 'css/font-awesome-ie7.min.css', array( 'font-awesome' ), '3.2.1' );
		wp_register_style( 'google-font-lato', '//fonts.googleapis.com/css?family=Lato:300,400,700,400italic', array(), Domainmap_Plugin::VERSION );
		wp_register_style( 'domainmapping-admin', $baseurl . 'css/admin.css', array( 'google-font-lato', 'buttons' ), Domainmap_Plugin::VERSION );

		$wp_styles->registered['font-awesome-ie']->add_data( 'conditional', 'IE 7' );
	}

	/**
	 * Setups resellers.
	 *
	 * @since 4.0.0
	 * @filter domainmapping_resellers
	 *
	 * @access public
	 * @param array $resellers The array of resellers.
	 * @return array Updated array of resellers.
	 */
	public function setup_resellers( $resellers ) {
		$resellers[] = new Domainmap_Reseller_Enom();
		return $resellers;
	}

}