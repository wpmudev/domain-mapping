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
 * The module responsible for admin pages.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_Pages extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * Admin page handle.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var string
	 */
	private $_admin_page;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of Domainmap_Plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->_add_action( 'admin_menu', 'add_site_options_page' );
		$this->_add_action( 'network_admin_menu', 'add_network_options_page' );
		$this->_add_action( 'admin_enqueue_scripts', 'enqueue_scripts' );
	}

	/**
	 * Register admin page WPMUDev Dashboard notiecs array.
	 *
	 * @since 4.1.1
	 *
	 * @access private
	 * @global array $wpmudev_notices WPMUDev dashboard notices array.
	 */
	private function _register_wpmudev_notices() {
		global $wpmudev_notices;

		$wpmudev_notices[] = array(
			'id'      => 99,
			'name'    => 'Domain Mapping plugin',
			'screens' => array( is_network_admin() ? "{$this->_admin_page}-network" : $this->_admin_page ),
		);
	}

	/**
	 * Registers site options page in admin menu.
	 *
	 * @since 4.0.0
	 * @action admin_menu
	 *
	 * @access public
	 */
	public function add_site_options_page() {
		global $blog_id;
		if ( $blog_id > 1 && $this->_plugin->is_site_permitted() ) {
			$title = __( 'Domain Mapping', 'domainmap' );
			$this->_admin_page = add_management_page( $title, $title, 'manage_options', 'domainmapping', array( $this, 'render_site_options_page' ) );
			$this->_register_wpmudev_notices();
		}
	}

	/**
	 * Renders network options page.
	 *
	 * @since 4.0.0
	 * @callback add_management_page()
	 *
	 * @access public
	 */
	public function render_site_options_page() {
		global $blog_id;
		$reseller = $this->_plugin->get_reseller();
		$tabs = array( 'mapping' => __( 'Map domain', 'domainmap' ) );

		if ( $reseller && $reseller->is_valid() && count( $reseller->get_tld_list() ) ) {
			$tabs['purchase'] = __( 'Purchase domain', 'domainmap' );
		}

		$activetab = strtolower( trim( filter_input( INPUT_GET, 'tab', FILTER_DEFAULT ) ) );
		if ( !in_array( $activetab, array_keys( $tabs ) ) ) {
			$activetab = key( $tabs );
		}

		$page = null;
		$options = $this->_plugin->get_options();
		if ( $activetab == 'purchase' ) {
			$page = new Domainmap_Render_Site_Purchase( $tabs, $activetab, $options );
			$page->reseller = $reseller;
		} else {
			// fetch unchanged domain name from database, because get_option function could return mapped domain name
			$basedomain = parse_url( $this->_wpdb->get_var( "SELECT option_value FROM {$this->_wpdb->options} WHERE option_name = 'siteurl'" ), PHP_URL_HOST );

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
				$dns = @dns_get_record( $basedomain, DNS_A );
				if ( is_array( $dns ) ) {
					$ips = wp_list_pluck( $dns, 'ip' );
				}
			}

			// prepare template
			$page = new Domainmap_Render_Site_Map( $tabs, $activetab, $options );
			$page->origin = $this->_wpdb->get_row( "SELECT * FROM {$this->_wpdb->blogs} WHERE blog_id = " . intval( $blog_id ) );
			$page->domains = (array)$this->_wpdb->get_results( sprintf( "SELECT domain, is_primary FROM %s WHERE blog_id = %d ORDER BY id ASC", DOMAINMAP_TABLE_MAP, (int) $blog_id ) );
			$page->ips = $ips;
		}

		if ( $page ) {
			$page->render();
		}
	}

	/**
	 * Registers network options page in admin menu.
	 *
	 * @since 4.0.0
	 * @action network_admin_menu
	 *
	 * @access public
	 */
	public function add_network_options_page() {
		$title = __( 'Domain Mapping', 'domainmap' );
		$this->_admin_page = add_submenu_page( 'settings.php', $title, $title, 'manage_network_options', 'domainmapping_options', array( $this, 'render_network_options_page' ) );
		$this->_register_wpmudev_notices();
	}

	/**
	 * Updates network options.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $nonce_action The nonce action param.
	 */
	private function _update_network_options( $nonce_action ) {
		// if request method is post, then save options
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			// check referer
			check_admin_referer( $nonce_action );

			// Update the domain mapping settings
			$options = $this->_plugin->get_options();

			// parse IP addresses
			$ips = array();
			foreach ( explode( ',', filter_input( INPUT_POST, 'map_ipaddress' ) ) as $ip ) {
				$ip = filter_var( trim( $ip ), FILTER_VALIDATE_IP );
				if ( $ip ) {
					$ips[] = $ip;
				}
			}

			// parse supported levels
			$supporters = array();
			if ( isset( $_POST['map_supporteronly'] ) ) {
				$supporters = array_filter( array_map( 'intval', (array)$_POST['map_supporteronly'] ) );
			}

			$options['map_ipaddress'] = implode( ', ', array_unique( $ips ) );
			$options['map_supporteronly'] = $supporters;
			$options['map_admindomain'] = filter_input( INPUT_POST, 'map_admindomain' );
			$options['map_logindomain'] = filter_input( INPUT_POST, 'map_logindomain' );
			$options['map_crossautologin'] = filter_input( INPUT_POST, 'map_crossautologin', FILTER_VALIDATE_BOOLEAN );
			$options['map_crossautologin_infooter'] = filter_input( INPUT_POST, 'map_crossautologin_infooter', FILTER_VALIDATE_BOOLEAN );
			$options['map_crossautologin_async'] = filter_input( INPUT_POST, 'map_crossautologin_async', FILTER_VALIDATE_BOOLEAN );
			$options['map_verifydomain'] = filter_input( INPUT_POST, 'map_verifydomain', FILTER_VALIDATE_BOOLEAN );
			$options['map_check_domain_health'] = filter_input( INPUT_POST, 'map_check_domain_health', FILTER_VALIDATE_BOOLEAN );
			$options['map_force_admin_ssl'] = $this->server_supports_ssl() ?  filter_input( INPUT_POST, 'map_force_admin_ssl', FILTER_VALIDATE_BOOLEAN ) : 0;
			$options['map_force_frontend_ssl'] = filter_input( INPUT_POST, 'map_force_frontend_ssl', FILTER_VALIDATE_INT );
			$options['map_instructions'] = current_user_can('unfiltered_html') ? filter_input( INPUT_POST, 'map_instructions' ) : wp_kses_post( filter_input( INPUT_POST, 'map_instructions' ) );
			$options['map_disallow_subdomain'] = filter_input( INPUT_POST, 'dm_disallow_subdomain', FILTER_VALIDATE_BOOLEAN );
			$options['map_prohibited_domains'] = filter_input( INPUT_POST, 'dm_prohibited_domains', FILTER_SANITIZE_STRING );
			$options['map_allow_excluded_urls'] = filter_input( INPUT_POST, 'map_allow_excluded_urls', FILTER_VALIDATE_INT );
			$options['map_allow_excluded_pages'] = filter_input( INPUT_POST, 'map_allow_excluded_pages', FILTER_VALIDATE_INT );
			$options['map_allow_forced_urls'] = filter_input( INPUT_POST, 'map_allow_forced_urls', FILTER_VALIDATE_INT );
			$options['map_allow_forced_pages'] = filter_input( INPUT_POST, 'map_allow_forced_pages', FILTER_VALIDATE_INT );
			$options['map_allow_multiple'] = filter_input( INPUT_POST, 'map_allow_multiple', FILTER_VALIDATE_BOOLEAN );

			// update options
			update_site_option( 'domain_mapping', $options );

			// if noheader argument is passed, then redirect back to options page
			if ( filter_input( INPUT_GET, 'noheader', FILTER_VALIDATE_BOOLEAN ) ) {
				wp_safe_redirect( esc_url_raw( add_query_arg( array( 'noheader' => false, 'saved' => 'true' ) ) ) );
				exit;
			}
		}
	}

	/**
	 * Updates reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $nonce_action The nonce action param.
	 */
	private function _update_reseller_options( $nonce_action ) {
		// if request method is post, then save options
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			// check referer
			check_admin_referer( $nonce_action );

			// Update the domain mapping settings
			$options = $this->_plugin->get_options();

			// save reseller options
			$options['map_reseller'] = '';
			$resellers = $this->_plugin->get_resellers();
			$reseller = filter_input( INPUT_POST, 'map_reseller' );
			if ( isset( $resellers[$reseller] ) ) {
				$options['map_reseller'] = $reseller;
				$resellers[$reseller]->save_options( $options );
			}
			// save reseller API requests log level
			$options['map_reseller_log'] = filter_input( INPUT_POST, 'map_reseller_log', FILTER_VALIDATE_INT, array(
				'options' => array(
					'min_range' => Domainmap_Reseller::LOG_LEVEL_DISABLED,
					'max_range' => Domainmap_Reseller::LOG_LEVEL_ALL,
					'default'   => Domainmap_Reseller::LOG_LEVEL_DISABLED,
				),
			) );

			// update options
			update_site_option( 'domain_mapping', $options );

			// if noheader argument is passed, then redirect back to options page
			if ( filter_input( INPUT_GET, 'noheader', FILTER_VALIDATE_BOOLEAN ) ) {
				wp_safe_redirect( esc_url_raw( add_query_arg( array( 'noheader' => false, 'saved' => 'true' ) ) ) );
				exit;
			}
		}
	}

	/**
	 * Handles table log actions.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $nonce_action The nonce action param.
	 */
	private function _handle_log_actions( $nonce_action ) {
		$redirect = wp_get_referer();
		$nonce = filter_input( INPUT_GET, 'nonce' );
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			$nonce = filter_input( INPUT_POST, '_wpnonce' );
		}

		$table = new Domainmap_Table_Reseller_Log();
		switch ( $table->current_action() ) {
			case 'reseller-log-view':
				$item = filter_input( INPUT_GET, 'items', FILTER_VALIDATE_INT );
				if ( wp_verify_nonce( $nonce, $nonce_action ) && $item ) {
					$log = $this->_wpdb->get_row( 'SELECT * FROM ' . DOMAINMAP_TABLE_RESELLER_LOG . ' WHERE id = ' . $item );
					if ( !$log ) {
						status_header( 404 );
					} else {
						@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
						@header( sprintf(
							'Content-Disposition: inline; filename="%s-%s-%d-%s-request.json"',
							parse_url( home_url(), PHP_URL_HOST ),
							$log->provider,
							$log->id,
							preg_replace( '/\D+/', '', $log->requested_at )
						) );

						echo $log->response;
					}
					exit;
				}
				break;

			case 'reseller-log-delete':
				$items = isset( $_REQUEST['items'] ) ? (array)$_REQUEST['items'] : array();
				$items = array_filter( array_map( 'intval', $items ) );

				if ( wp_verify_nonce( $nonce, $nonce_action ) && !empty( $items ) ) {
					$this->_wpdb->query( 'DELETE FROM ' . DOMAINMAP_TABLE_RESELLER_LOG . ' WHERE id IN (' . implode( ', ', $items ) . ')' );

					$redirect = esc_url_raw( add_query_arg( 'deleted', 'true', $redirect ) );
				}
				break;
		}


		// if noheader argument is passed, then redirect back to options page
		if ( filter_input( INPUT_GET, 'noheader', FILTER_VALIDATE_BOOLEAN ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'type', isset( $_REQUEST['type'] ) ? $_REQUEST['type'] : false, $redirect ) ) );
			exit;
		}
	}

	/**
	 * Processes POST request sent to network options page.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $activetab The active tab.
	 * @param string $nonce_action The nonce action param.
	 */
	private function _save_network_options_page( $activetab, $nonce_action ) {
		// update options
		switch ( $activetab ) {
			case 'general-options':
				$this->_update_network_options( $nonce_action );
				break;
			case 'reseller-options':
				$this->_update_reseller_options( $nonce_action );
				break;
			case 'reseller-api-log':
				$this->_handle_log_actions( $nonce_action );
				break;

		}

		// if noheader argument is passed, then redirect back to options page
		if ( filter_input( INPUT_GET, 'noheader', FILTER_VALIDATE_BOOLEAN ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}
	}

	/**
	 * Renders network options page.
	 *
	 * @since 4.0.0
	 * @callback add_submenu_page()
	 *
	 * @access public
	 */
	public function render_network_options_page() {
		$options = $this->_plugin->get_options();

		$tabs = array(
			'general-options'  => __( 'Mapping options', 'domainmap' ),
			'reseller-options' => __( 'Reseller options', 'domainmap' ),
//			'reseller-api-log' => __( 'API Log', 'domainmap' ),
            'mapped-domains' => __( 'Mapped Domains', 'domainmap' ),
		);

		$reseller = $this->_plugin->get_reseller();
		if ( isset( $options['map_reseller_log'] ) && $options['map_reseller_log'] && !is_null( $reseller ) ) {
			$tabs['reseller-api-log'] = __( 'Reseller API log', 'domainmap' );
		}

		$activetab = strtolower( trim( filter_input( INPUT_GET, 'tab', FILTER_DEFAULT ) ) );
		if ( !in_array( $activetab, array_keys( $tabs ) ) ) {
			$activetab = key( $tabs );
		}

		$nonce_action = "domainmapping-{$activetab}";
		$this->_save_network_options_page( $activetab, $nonce_action );

		// render page
		$page = null;
		switch ( $activetab ) {
			default:
			case 'general-options':
				$page = new Domainmap_Render_Network_Options( $tabs, $activetab, $nonce_action, $options );
				// fetch unchanged domain name from database, because get_option function could return mapped domain name
				$page->basedomain = $this->_wpdb->get_var( "SELECT option_value FROM {$this->_wpdb->options} WHERE option_name = 'siteurl'" );
				break;
			case 'reseller-options':
				$page = new Domainmap_Render_Network_Resellers( $tabs, $activetab, $nonce_action, $options );
				$page->resellers = $this->_plugin->get_resellers();
				break;
			case 'reseller-api-log':
				$page = new Domainmap_Render_Network_Log( $tabs, $activetab, $nonce_action, $options );
				$page->table = new Domainmap_Table_Reseller_Log( array(
					'reseller'     => $reseller->get_reseller_id(),
					'nonce_action' => $nonce_action,
					'actions'      => array(
						'reseller-log-delete' => __( 'Delete', 'domainmap' ),
					),
				) );
				break;
            case 'mapped-domains':
                $page = new Domainmap_Render_Network_MappedDomains( $tabs, $activetab, $nonce_action, $options );
                $page->table = new Domainmap_Table_MappedDomains_Listing( array(
                    'nonce_action' => $nonce_action,
                ) );
                break;
		}

		if ( $page ) {
			$page->render();
		}
	}

	/**
	 * Enqueues appropriate scripts and styles for specific admin pages.
	 *
	 * @since 3.3
	 * @action admin_enqueue_scripts
	 * @uses wp_enqueue_script() To enqueue javascript files.
	 * @uses wp_enqueue_style() To enqueue CSS files.
	 *
	 * @access public
	 * @global WP_Styles $wp_styles The styles queue class object.
	 * @param string $page The page handle.
	 */
	public function enqueue_scripts( $page ) {
		// if we are not at the site admin page, then exit
		if ( $page != $this->_admin_page ) {
			return;
		}

		// enqueue scripts
        wp_enqueue_script( 'jquery-effects-core' );
        wp_enqueue_script( 'jquery-effects-highlight' );
		wp_enqueue_script( 'domainmapping-admin' );


		// enqueue styles
		wp_enqueue_style( 'domainmapping-admin' );
	}



}