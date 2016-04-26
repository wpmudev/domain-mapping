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

		$this->_add_ajax_action( Domainmap_Plugin::ACTION_CHECK_DOMAIN_AVAILABILITY, 'check_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_PAYPAL_PURCHASE, 'purchase_with_paypal' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_PAYPAL_DO_EXPRESS_CHECKOUT, 'complete_paypal_checkout' );

		$this->_add_ajax_action( Domainmap_Plugin::ACTION_SHOW_PURCHASE_FORM, 'render_purchase_form' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_SHOW_PURCHASE_FORM, 'redirect_to_login_form', false, true );
		$this->_add_ajax_action( Domainmap_Reseller_WHMCS::ACTION_CHECK_CLIENT_LOGIN, 'whmcs_validate_client_login' );
		$this->_add_ajax_action( Domainmap_Reseller_WHMCS::ACTION_ORDER_DOMAIN, 'ajax_whmcs_order_domain' );
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
		self::_check_premissions( Domainmap_Plugin::ACTION_CHECK_DOMAIN_AVAILABILITY );

		$sld = strtolower( trim( filter_input( INPUT_POST, 'sld' ) ) );
		$tld = strtolower( trim( filter_input( INPUT_POST, 'tld' ) ) );

		$message = false;
		$domain = "{$sld}.{$tld}";
		$is_valid = $this->_validate_domain_name( $domain );
		if ( $is_valid ) {
			$reseller = $this->_plugin->get_reseller();

			$price = false;
			$available = $reseller->check_domain( $tld, $sld );
			if ( $available ) {
				$price = '$' . number_format( floatval( $reseller->get_tld_price( $tld ) ), 2 );
			}

			set_site_transient( $this->_get_transient_name( 'checkdomain' ), array(
				'domain' => $domain,
				'price'  => $price,
				'sld'    => $sld,
				'tld'    => $tld,
			), HOUR_IN_SECONDS );

			wp_send_json_success( array(
				'available' => $available,
				'html'      => $available
					? $reseller->get_domain_available_response( $sld, $tld )
					: sprintf( '<div class="domainmapping-info domainmapping-info-error"><b>%s</b> %s.</div>', $domain, __( 'is not available to purchase', 'domainmap' ) ),
			) );
		} else {
			if( $is_valid === false ){
				$message = __( 'Domain name is invalid.', 'domainmap' );
			}else{
				$message = __( 'Domain name is prohibited.', 'domainmap' );
			}
		}

		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * Checks SSL connection and user permissions before render or process
	 * purchase form.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 */
	private function _check_ssl_and_security() {
		// check if ssl connection is not used
		if ( !is_ssl() ) {
			// ssl connection is not used, so if you logged in then redirect him
			// to https page, otherwise redirect him to login page
			$user_id = get_current_user_id();
			if ( $user_id ) {
				// propagate SSL auth cookie
				wp_set_auth_cookie( $user_id, true, true );

				// redirect to https version of this page
				wp_redirect( esc_url_raw( add_query_arg( array_map( 'urlencode', $_GET ), admin_url( 'admin-ajax.php', 'https' ) ) ) );
				exit;
			} else {
				// redirect to login form
				$this->redirect_to_login_form();
			}
		}

		// check if user has permissions
		if ( !check_admin_referer( Domainmap_Plugin::ACTION_SHOW_PURCHASE_FORM, 'nonce' ) || !current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			exit;
		}
	}

	/**
	 * Renders purchase form.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function render_purchase_form() {
        $this->_check_ssl_and_security();
		$reseller = $this->_plugin->get_reseller();
		$info = get_site_transient( $this->_get_transient_name( 'checkdomain' ) );
		if ( !$info || !$reseller ) {
			status_header( 404 );
			exit;
		}

		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			$domain = $reseller->purchase();
			if ( $domain ) {
				$this->_map_domain( $domain, filter_input( INPUT_GET, 'blog', FILTER_VALIDATE_INT ) );
				wp_redirect( filter_input( INPUT_GET, 'success', FILTER_VALIDATE_URL, array( 'options' => array( 'default' => admin_url() ) ) ) );
				exit;
			}
		}

		define( 'IFRAME_REQUEST', true );

		// enqueue scripts
		wp_enqueue_script( 'jquery-payment' );
		wp_enqueue_script( 'domainmapping-admin' );

		// enqueue styles
		wp_enqueue_style( 'bootstrap-glyphs' );
		wp_enqueue_style( 'google-font-lato' );
		wp_enqueue_style( 'domainmapping-admin' );

		// render purchase form
		wp_iframe( array( $reseller, 'render_purchase_form' ), $info );
		exit;
	}

	/**
	 * Proceeds PayPal checkout.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function purchase_with_paypal() {
		$reseller = $this->_plugin->get_reseller();
		if ( $reseller ) {
			$reseller->proceed_paypal_checkout();
		}

		wp_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Maps already bought domain.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $domain The new domain name to map.
	 * @param int $blog_id The blog ID to map domain to.
	 */
	private function _map_domain( $domain, $blog_id = false ) {
		if ( !$blog_id ) {
			global $blog_id;
			$blog_id = intval( $blog_id );
		}

		// check if mapped domains are 0 or multi domains are enabled
		$count = $this->_wpdb->get_var( 'SELECT COUNT(*) FROM ' . DOMAINMAP_TABLE_MAP . ' WHERE blog_id = ' . $blog_id );
		$allowmulti = domain_map::allow_multiple();
		if ( $count == 0 || $allowmulti ) {

			// check if domain has not been mapped
			$blog = $this->_wpdb->get_row( $this->_wpdb->prepare( "SELECT blog_id FROM {$this->_wpdb->blogs} WHERE domain = %s AND path = '/'", $domain ) );
			$map = $this->_wpdb->get_row( $this->_wpdb->prepare( 'SELECT blog_id FROM ' . DOMAINMAP_TABLE_MAP . ' WHERE domain = %s', $domain ) );

			if( is_null( $blog ) && is_null( $map ) ) {
				$this->_wpdb->insert( DOMAINMAP_TABLE_MAP, array(
					'blog_id' => $blog_id,
					'domain'  => $domain,
					'active'  => 1,
				), array( '%d', '%s', '%d' ) );

                /**
                 * Fires the action when a new domain is added
                 *
                 * @since 4.0.0
                 * @param string $domain added domain
                 * $param int $blog_id
                 */
                do_action( 'domainmapping_added_domain', $domain, $blog_id );
			}
		}
	}

	/**
	 * Completes PayPal checkout and purcheses a domain name.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function complete_paypal_checkout() {
		$reseller = $this->_plugin->get_reseller();
		if ( $reseller ) {
			$domain = $reseller->complete_paypal_checkout();
			if ( $domain ) {
				$this->_map_domain( $domain );
			}
		}

		wp_redirect( esc_url_raw( add_query_arg( 'page', 'domainmapping', admin_url( 'tools.php' ) ) ) );
		exit;
	}

    public function whmcs_validate_client_login(){
        extract( $_POST['data'] );
        /**
         * @var $whmcs Domainmap_Reseller_WHMCS
         */
        $whmcs = $this->_plugin->get_reseller();
        if( !empty( $password2 ) && !empty( $email ) ){
            $object = Domainmap_Reseller_WHMCS::exec_command( Domainmap_Reseller_WHMCS::COMMAND_VALIDATE_LOGIN, array(
                "email" => $email,
                "password2" => $password2
            ) );
            /**
             * var $object WP_Error
             */
            $client_id_transient = $this->_get_transient_name( "whmcs_client_id" );
            if( !is_wp_error($object) ){
                set_site_transient( $client_id_transient, $object->userid, HOUR_IN_SECONDS  );
                wp_send_json_success( array(
                    "html" =>  $whmcs->render_purchase_form( array(
                        "tld" => $tld,
                        "sld" => $sld,
                        "domain" => $sld . "." . $tld
                    ) )
                ) );

            }else{
                wp_send_json_error( array(
                    "error" => $object->get_error_message(),
                ) );
            }
        }

        wp_die();
    }

    /**
     * Orders new domain via WHMCS API
     *
     * @since 4.2.0
     */
    function ajax_whmcs_order_domain(){
        /**
         * @var $whmcs Domainmap_Reseller_WHMCS
         */
        $whmcs = $this->_plugin->get_reseller();

        $client_id_transient = $this->_get_transient_name( "whmcs_client_id" );
        $client_id = get_site_transient( $client_id_transient );
        $data = $_POST['data'];

        $domain = isset( $data['sld'],  $data['tld'] ) ? $data['sld'] . "." . $data['tld'] : false;
        $period = isset( $data['period'] ) ? $data["period"] : "1";
        if( !$client_id ){
            wp_send_json_error( array(
                "expired" => true,
                "message" => __('Session Expired, please login again', domain_map::Text_Domain),
            ) );
        }

        if( !$domain ){
            wp_send_json_error( array(
                "expired" => false,
            ) );
        }



        $object = Domainmap_Reseller_WHMCS::exec_command( Domainmap_Reseller_WHMCS::COMMAND_ADD_ORDER, array_merge(array(
            "clientid" =>  $client_id,
            "domaintype" => "register",
            "domain" => $domain,
            "regperiod" => $period,
            "dnsmanagement" => "on",
            "idprotection" => "on",
            "paymentmethod" => $whmcs->get_gateway()
        ), $this->_get_current_domain_nameservers()) );
        $whmcs->log_whmcs_request(Domainmap_Reseller_WHMCS::COMMAND_ADD_ORDER, $object);
        if( !is_wp_error($object) ){
            $this->_map_domain( $domain);
            wp_send_json_success( array(
                "invoiceid" =>  $object->invoiceid,
                "orderid" => $object->orderid
            ) );
        }else{
            wp_send_json_error( array(
                "message" => __('Error ordering the domain', domain_map::Text_Domain),
            ) );
        }

    }

    /**
     * Retrieve name servers for current hostname
     *
     * @since 4.2.0
     *
     * @return array
     */
    private function _get_current_domain_nameservers(){
        global $current_site;
        $name_servers = array();
        $ns_query = dns_get_record($current_site->domain);
        if( is_array($ns_query) ){
            $i = 1;
            foreach($ns_query as $val){
                if( isset($val['target']) ){
                    $name_servers["nameserver" . $i] = $val['target'];
                }
                $i++;
            }
        }

        if( count($name_servers) === 0 ){
            $name_servers["nameserver1"] = "ns1." . $current_site->domain;
            $name_servers["nameserver2"] = "ns2." . $current_site->domain;
        }

        return $name_servers;
    }
}