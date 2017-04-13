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
 * The module responsible for handling AJAX requests sent at domain mapping page.
 *
 * @category Domainmap
 * @package Module
 * @subpackage Ajax
 *
 * @since 4.0.0
 */
class Domainmap_Module_Ajax_Map extends Domainmap_Module_Ajax {

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
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_MAP_DOMAIN, 'map_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_UNMAP_DOMAIN, 'unmap_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_HEALTH_CHECK, 'check_health_status', true, true );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_HEARTBEAT_CHECK, 'check_heartbeat', true, true );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_SELECT_PRIMARY_DOMAIN, 'select_primary_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_DESELECT_PRIMARY_DOMAIN, 'deselect_primary_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_CHANGE_FRONTEND_REDIRECT, 'change_frontend_mapping' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_TOGGLE_SCHEME, 'toggle_scheme' );

		// add wpengine compatibility
		if ( !has_action( 'domainmapping_added_domain' ) ) {
			$this->_add_action( 'domainmapping_added_domain', 'add_domain_to_wpengine' );
		}

		if ( !has_action( 'domainmapping_deleted_domain' ) ) {
			$this->_add_action( 'domainmapping_deleted_domain', 'remove_domain_from_wpengine' );
		}
	}

	/**
	 * Returns count of mapped domains for current blog.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @return int The count of already mapped domains.
	 */
	private function _get_domains_count() {
		return $this->_wpdb->get_var( 'SELECT COUNT(*) FROM ' . DOMAINMAP_TABLE_MAP . ' WHERE blog_id = ' . intval( $this->_wpdb->blogid ) );
	}

	/**
	 * Locates WPEngine API and loads it.
	 *
	 * @since 4.0.4
	 *
	 * @access private
	 * @return boolean TRUE if WPE_API has been located, otherwise FALSE.
	 */
	private function _locate_wpengine_api() {
		// if WPE_API doesn't exist, then try to locate it
		if ( !class_exists( 'WPE_API' ) ) {
			// if WPEngine is not defined, then return
			if ( !defined( 'WPE_PLUGIN_DIR' ) || !is_readable( WPE_PLUGIN_DIR . '/class-wpeapi.php' ) ) {
				return false;
			}

			include_once WPE_PLUGIN_DIR . '/class-wpeapi.php';
			// chec whether class has been loaded
			if ( !class_exists( 'WPE_API' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds domain to WPEngine domains list when this domain is mapped to a blog.
	 *
	 * @since 4.0.4
	 * @action domainmapping_added_domain
	 *
	 * @access public
	 * @param string $domain The domain name to add.
	 */
	public function add_domain_to_wpengine( $domain ) {
		// return if we can't locate WPEngine API class
		if ( !$this->_locate_wpengine_api() ) {
			return;
		}

		// add domain to WPEngine
		$api = new WPE_API();

		// set the method and domain
		$api->set_arg( 'method', 'domain' );
		$api->set_arg( 'domain', $domain );

		// do the api request
		$api->get();
	}

	/**
	 * Removes domain from WPEngine domains list when this domain is unmapped
	 * from a blog.
	 *
	 * @since 4.0.4
	 * @action domainmapping_deleted_domain
	 *
	 * @access public
	 * @param string $domain The domain name to remove.
	 */
	public function remove_domain_from_wpengine( $domain ) {
		// return if we can't locate WPEngine API class
		if ( !$this->_locate_wpengine_api() ) {
			return;
		}

		// add domain to WPEngine
		$api = new WPE_API();

		// set the method and domain
		$api->set_arg( 'method', 'domain-remove' );
		$api->set_arg( 'domain', $domain );

		// do the api request
		$api->get();
	}

	/**
	 * Maps new domain.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function map_domain() {
		global $blog_id;
		self::_check_premissions( Domainmap_Plugin::ACTION_MAP_DOMAIN );

		$message = $hide_form = false;
		$domain = strtolower( trim( filter_input( INPUT_POST, 'domain' ) ) );
		$scheme = strtolower( trim( filter_input( INPUT_POST, 'scheme' ) ) );
        $domain = Domainmap_Punycode::encode( $domain );
		$is_valid = $this->_validate_domain_name( $domain, true );
		if ( $is_valid ) {

			// check if mapped domains are 0 or multi domains are enabled
			$count = $this->_get_domains_count();
			$allowmulti = domain_map::allow_multiple();
			if ( $count == 0 || $allowmulti ) {

				// check if domain has not been mapped
				$blog = $this->_wpdb->get_row( $this->_wpdb->prepare( "SELECT blog_id FROM {$this->_wpdb->blogs} WHERE domain = %s AND path = '/'", $domain ) );
				$map = $this->_wpdb->get_row( $this->_wpdb->prepare( "SELECT blog_id FROM " . DOMAINMAP_TABLE_MAP . " WHERE domain = %s", $domain ) );

				if ( is_null( $blog ) && is_null( $map ) ) {
					$added = $this->_wpdb->insert( DOMAINMAP_TABLE_MAP, array(
						'blog_id' => (int) $blog_id,
						'domain'  => $domain,
						'active'  => 1,
                        "scheme" => $scheme,
					), array( '%d', '%s', '%d', '%d') );

                    if( !$added ){
                        $message = $this->_wpdb->last_error;
                    }else{
                        if ( $this->_plugin->get_option( 'map_verifydomain', true ) == false || $this->_validate_health_status( $domain ) ) {
                            // fire the action when a new domain is added
                            do_action( 'domainmapping_added_domain', $domain, $blog_id );

                            // send success response
                            ob_start();
                            $row = array( 'domain' => $domain, 'is_primary' => 0 );
                            Domainmap_Render_Site_Map::render_mapping_row( (object)$row );
                            wp_send_json_success( array(
                                'html'      => ob_get_clean(),
                                'hide_form' => !$allowmulti,
                            ) );
                        } else {
                            $this->_wpdb->delete( DOMAINMAP_TABLE_MAP, array( 'domain' => $domain ), array( '%s' ) );
                            $message = sprintf(
                                '<b>%s</b><br><small>%s</small>',
                                __( 'Domain name is unavailable to access.', 'domainmap' ),
                                __( 'We can’t access your new domain. Mapping a new domains can take as little as 15 minutes to resolve but in some cases can take up to 72 hours, so please wait if you just bought it. If it is an existing domain and has already been fully propagated, check your DNS records are configured correctly.', 'domainmap' )
                            );
                        }
                    }


				} else {
					$message = __( 'Domain is already mapped.', 'domainmap' );
				}
			} else {
				$message = __( 'Multiple domains are not allowed.', 'domainmap' );
				$hide_form = true;
			}
		} else {
			if( $is_valid === false ){
				$message = __( 'Domain name is invalid.', 'domainmap' );
			}else{
				$message = __( 'Domain name is prohibited.', 'domainmap' );
			}
		}


        // Set transient for scheme
        set_transient( domain_map::FORCE_SSL_KEY_PREFIX . $domain, (int) $scheme );

        // Send response json
		wp_send_json_error( array(
			'message'   => $message,
			'hide_form' => $hide_form,
		) );
	}

	/**
	 * Unmaps domain.
	 *
	 * @since 4.0.0
	 * @uses check_admin_referer() To avoid security exploits.
	 * @uses current_user_can() To check user permissions.
	 *
	 * @access public
	 */
	public function unmap_domain() {
		global $blog_id;
		self::_check_premissions( Domainmap_Plugin::ACTION_UNMAP_DOMAIN );

		$show_form = false;
		$domain = strtolower( trim( filter_input( INPUT_GET, 'domain' ) ) );
        $success = false;
		//We need to be able to also delete domains that have been saved wrongly.
		//Sometimes somethings can go wrong
		//if ( self::utils()->is_domain( $domain ) ) {
            $success = $this->delete_mapped_domain( $domain );

			// check if we need to show form
			$show_form = $this->_get_domains_count() == 0 || domain_map::allow_multiple();


            /**
             * Fires the action when a domain is removed
             *
             * @since 4.0.0
             * @param string $domain deleted domain name
             * @param int $blog_id
             */
            do_action( 'domainmapping_deleted_domain', $domain, $blog_id);
		//}

        if( $success ){
            wp_send_json_success( array( 'show_form' => $show_form ) );
        }else{
            wp_send_json_error( array( 'show_form' => $show_form ) );
        }
	}

	/**
	 * Checks domain health status.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function check_health_status() {
		self::_check_premissions( Domainmap_Plugin::ACTION_HEALTH_CHECK );
		$domain = strtolower( trim( filter_input( INPUT_GET, 'domain' ) ) );
		if ( !$this->_validate_domain_name( $domain ) ) {
			wp_send_json_error();
		}
		$this->set_valid_transient( $domain );
		ob_start();
		Domainmap_Render_Site_Map::render_health_column( $domain );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Checks heartbeat of the domain.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function check_heartbeat() {
		echo filter_input( INPUT_GET, 'check' );
		exit;
	}

	/**
	 * Selects primary domain for current blog.
	 *
	 * @since 4.0.3
	 *
	 * @access public
	 */
	public function select_primary_domain() {
		global $blog_id;

		self::_check_premissions( Domainmap_Plugin::ACTION_SELECT_PRIMARY_DOMAIN );

		if ( domain_map::allow_multiple() ) {
			// unset all domains
            $domain = filter_input( INPUT_GET, 'domain' );

            $blog_id = $blog_id == 1 ? (int) $this->_wpdb->get_var( $this->_wpdb->prepare( "SELECT `blog_id` FROM " . DOMAINMAP_TABLE_MAP .  " WHERE `domain` = %s", $domain ) ) : $blog_id;

          if( is_numeric($blog_id) && $blog_id !== 0 )
          {
            $res = $this->_wpdb->update(
                DOMAINMAP_TABLE_MAP,
                array( 'is_primary' => 0 ),
                array( 'blog_id' => $blog_id, 'is_primary' => 1 ),
                array( '%d' ),
                array( '%d', '%d' )
            );

            // set primary domain
            $this->_wpdb->update(
                DOMAINMAP_TABLE_MAP,
                array( 'is_primary' => 1 ),
                array( 'domain' => $domain ),
                array( '%d' ),
                array( '%s' )
            );
          }

		}

		wp_send_json_success();
		exit;
	}

	/**
	 * Deselects primary domain for current blog.
	 *
	 * @since 4.0.3
	 *
	 * @access public
	 */
	public function deselect_primary_domain() {
		global $blog_id;
		self::_check_premissions( Domainmap_Plugin::ACTION_DESELECT_PRIMARY_DOMAIN );

		if ( domain_map::allow_multiple() ) {
          $domain = filter_input( INPUT_GET, 'domain' );
          $blog_id = $blog_id == 1 ? (int) $this->_wpdb->get_var( $this->_wpdb->prepare( "SELECT `blog_id` FROM " . DOMAINMAP_TABLE_MAP .  " WHERE `domain` = %s", $domain ) ) : (int)  $blog_id;

          // deselect primary domains
			$this->_wpdb->update(
				DOMAINMAP_TABLE_MAP,
				array( 'is_primary' => 0 ),
				array( 'blog_id' => $blog_id, 'is_primary' => 1, 'domain' => $domain),
				array( '%d' ),
				array( '%d', '%d', '%s' )
			);
		}

		wp_send_json_success();
		exit;
	}

	/**
	 * Changes front end mapping for current blog.
	 *
	 * @since 4.1.2
	 *
	 * @access public
	 */
	public function change_frontend_mapping() {
		self::_check_premissions( Domainmap_Plugin::ACTION_CHANGE_FRONTEND_REDIRECT );

		$mapping = strtolower( filter_input( INPUT_POST, 'mapping' ) );
		if ( !in_array( $mapping, array( 'user', 'mapped', 'original' ) ) ) {
			wp_send_json_error();
		}

		update_option( 'domainmap_frontend_mapping', $mapping );
		wp_send_json_success();
	}


   function toggle_scheme(){
     self::_check_premissions( Domainmap_Plugin::ACTION_TOGGLE_SCHEME );
     $domain  = $_GET['domain'];
     $result = false;

     $current_scheme = (int) $this->_wpdb->get_var( $this->_wpdb->prepare( "SELECT `scheme` FROM " . DOMAINMAP_TABLE_MAP .  " WHERE `domain` = %s", $domain ) );
     if( !is_null( $current_scheme ) ){
	    $new_schema = ( $current_scheme + 1 ) % 3;
       $result = $this->_wpdb->update( DOMAINMAP_TABLE_MAP, array(
           "scheme" => $new_schema ,
       ), array(
           "domain" => $domain
       )  );
     }

     if( $result ){
		$transient_key = self::FORCE_SSL_KEY_PREFIX . $domain;
		set_transient($transient_key, $new_schema, 30 * MINUTE_IN_SECONDS);
		wp_send_json_success(array( "schema" => $new_schema ));
     }else{
		wp_send_json_error();
     }
   }


}