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

class domain_map {

	/**
	 * @var wpdb
	 */
	var $db;

	// The tables we need to map - empty for now as we will move to this later
	var $tables = array();

	// The main domain mapping tables
	var $dmtable;

	// The domain mapping options
	var $options;

	// For caching swapped urls later on
	var $swapped_url = array();

    /**
     * Text domain name used for translating strings
     *
     * @since 4.2.0
     * @param string Text_Domain
     */
    const Text_Domain = "domainmap";

	/**
	 * Options key set when rewrite rules are flushed
	 *
	 * @since 4.3.1
	 * @param string FLUSHED_REWRITE_RULES
	 */
	const FLUSHED_REWRITE_RULES = 'domainmap-flushed-rules-4_4_2_0';

    /**
     * Key to cache force ssl state
     *
     * @since 4.4.0.10
     * @param string  FORCE_SSL_KEY_PREFIX
     */
    const FORCE_SSL_KEY_PREFIX = 'dm_force_ssl_';

    private $_http;

	function __construct() {
		global $wpdb, $dm_cookie_style_printed, $dm_logout, $dm_authenticated;

		$dm_cookie_style_printed = false;
		$dm_logout = false;
		$dm_authenticated = false;

		$this->db = $wpdb;
		$this->dmtable = DOMAINMAP_TABLE_MAP;
        $this->_http = new CHttpRequest();
        $this->_http->init();
		// Set up the plugin
		add_action( 'init', array( $this, 'setup_plugin' ) );

		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ), 10 );

		// Add in the filters for domain mapping early on to get any information covered before the init action is hit
		$this->add_domain_mapping_filters();
		add_action('wp_ajax_update_excluded_pages_list', array($this, 'ajax_update_excluded_pages_list'));

		add_action("domainmap_plugin_activated", array($this, "flush_rewrite_rules"));
		add_action("domainmap_plugin_deactivated", array($this, "remove_rewrite_rule_flush_trace"));
	}

	function domain_mapping_admin_url( $admin_url, $path = '/', $_blog_id = false ) {
		global $blog_id;

		if ( !$_blog_id ) {
			$_blog_id = $blog_id;
		}


		switch ( $this->options['map_admindomain'] ) {
			case 'user':
				break;
			case 'mapped':
				break;
			case 'original':
				// get the mapped url using our filter
				$mapped_url = site_url( '/' );
				// remove the http and https parts of the url
				$mapped_url = str_replace( array( 'https://', 'http://' ), '', $mapped_url );
				// get the original url now with our filter removed
				$orig_url = trailingslashit( self::utils()->unswap_url( get_option( 'siteurl' ) ) );
				// remove the http and https parts of the original url
				$orig_url = str_replace( array( 'https://', 'http://' ), '', $orig_url );

				// Check if we are looking at the admin-ajax.php and if so, we want to leave the domain as mapped
				if ( $path != 'admin-ajax.php' && strpos($admin_url, "admin-ajax.php") === false ) {
					// swap the mapped url with the original one
					$admin_url = str_replace( $mapped_url, $orig_url, $admin_url );
					$admin_url = set_url_scheme($admin_url, self::utils()->get_admin_scheme( $admin_url ) );
				} else {
					if ( !is_admin() ) {
						// swap the original url with the mapped one
						$admin_url = str_replace( $orig_url, $mapped_url, $admin_url );
					}

				}
				break;
		}

         return $admin_url;

	}

	function add_domain_mapping_filters() {

		if ( defined( 'DOMAIN_MAPPING' ) ) {
			// filter the content with any original urls and change them to the mapped urls
			add_filter( 'the_content', array( &$this, 'domain_mapping_post_content' ) );
			// Jump in just before header output to change base_url - until a neater method can be found
			add_filter( 'print_head_scripts', array( &$this, 'reset_script_url' ), 1, 1);

			add_filter('authenticate', array( &$this, 'authenticate' ), 999, 3);

			add_filter( 'theme_root_uri', array( &$this, 'domain_mapping_post_content' ), 1 );
			add_filter( 'stylesheet_uri', array( &$this, 'domain_mapping_post_content' ), 1 );
			add_filter( 'stylesheet_directory', array( &$this, 'domain_mapping_post_content' ), 1 );
			add_filter( 'stylesheet_directory_uri', array( &$this, 'domain_mapping_post_content' ), 1 );
			add_filter( 'template_directory', array( &$this, 'domain_mapping_post_content' ), 1 );
			add_filter( 'template_directory_uri', array( &$this, 'domain_mapping_post_content' ), 1 );

			add_action( 'customize_preview_init', array( &$this, 'init_customizer_preview' ));
		} else {
			// We are assuming that we are on the original domain - so if we check if we are in the admin area, we need to only map those links that
			// point to the front end of the site
			if ( is_admin() ) {
				// filter the content with any original urls and change them to the mapped urls
				add_filter( 'the_content', array( &$this, 'domain_mapping_post_content' ) );
				add_filter( 'authenticate', array( &$this, 'authenticate' ), 999, 3 );
			}

			//Network Admin Notice for WHMCS
			if ( is_network_admin() ) {
				
				$this->show_whmcs_warning();
			}
		}


	}

	function setup_plugin() {
		$this->options = Domainmap_Plugin::instance()->get_options();

		$permitted = true;
		if ( function_exists( 'is_pro_site' ) && !empty( $this->options['map_supporteronly'] ) ) {
			// We have a pro-site option set and the pro-site plugin exists
			$levels = (array)get_site_option( 'psts_levels' );
			if ( !is_array( $this->options['map_supporteronly'] ) && !empty( $levels ) && $this->options['map_supporteronly'] == '1' ) {
				$keys = array_keys( $levels );
				$this->options['map_supporteronly'] = array( $keys[0] );
			}

			$permitted = false;
			// For each ProSite Level.
			foreach ( (array)$this->options['map_supporteronly'] as $level ) {
				// See if the current site is a DM enabled Pro Site.
				if( is_pro_site( get_current_blog_id(), $level ) ) {
					$permitted = true;
				}
			}
		}

		// Add the network admin settings
		if ( $permitted ) {
			add_action( 'wp_logout', array( $this, 'wp_logout' ), 10 );
		}
	}

	function authenticate($user) {
		global $dm_authenticated;

		if (!empty($user)) {
			$dm_authenticated = $user;
		}

		return $user;
	}

	function wp_logout() {
		global $dm_logout;

		$dm_logout = true;
	}

	function allowed_redirect_hosts( $allowed_hosts ) {
		if ( !empty( $_REQUEST['redirect_to'] ) ) {
			$redirect_url = parse_url( $_REQUEST['redirect_to'] );
			if ( isset( $redirect_url['host'] ) ) {
				$network_home_url = parse_url( network_home_url() );
				if ( $redirect_url['host'] != $network_home_url['host'] ) {
					$pos = strpos( $redirect_url['host'], '.' );
					if ( ($pos !== false) && (substr( $redirect_url['host'], $pos + 1 ) === $network_home_url['host']) ) {
						$allowed_hosts[] = $redirect_url['host'];
					}

					$bid = $this->db->get_var( "SELECT blog_id FROM {$this->dmtable} WHERE domain = '{$redirect_url['host']}' ORDER BY id LIMIT 1" );
					if ( $bid ) {
						$allowed_hosts[] = $redirect_url['host'];
					}
				}
			}
		} else {
			$domains 		= (array)$this->db->get_col( sprintf( "SELECT domain FROM %s WHERE blog_id = %d ORDER BY id ASC", DOMAINMAP_TABLE_MAP, $this->db->blogid ) );
			$original 		= $this->db->get_var( "SELECT domain FROM {$this->db->blogs} WHERE blog_id = " . intval( $this->db->blogid ) );
			$allowed_hosts 	= array_unique( array_merge( $allowed_hosts, $domains, array( $original ) ) );
		}

		return $allowed_hosts;
	}

	function reset_script_url( $return ) {
		global $wp_scripts;

		$wp_scripts->base_url = site_url();

		return $return;
	}

	function domain_mapping_post_content( $post_content ) {
		static $orig_urls = array();
		$blog_id = get_current_blog_id();

		if ( !isset( $orig_urls[$blog_id] ) ) {

            /**
             * Filter the original url
             *
             * @since 1.0.0
             * @param string $orig_url the original url
             */
            $orig_url = self::utils()->unswap_url( get_option( 'siteurl' ) );
			// switch the url to use the correct http or https and store the url in the cache
			$orig_urls[$blog_id] = is_ssl()
				? str_replace( "http://", "https://", $orig_url )
				: str_replace( "https://", "http://", $orig_url );

		} else {
			// we have a cached entry so just return that
			$orig_url = $orig_urls[$blog_id];
		}

        /**
         * Filter getting new mapped url
         *
         * @since 1.0.0
         * @param string $url
         */
        $url = apply_filters( 'pre_option_siteurl', 'NA' );
		if ( $url == 'NA' ) {
			// If we don't have a mapped url then just return the content unchanged
			return $post_content;
		}

        $orig_url = trailingslashit( $orig_url );
        $url = trailingslashit( $url );

		// replace all the original urls with the new ones and then return the content
		return str_replace( array( $orig_url,  self::utils()->swap_url_scheme( $orig_url ) ) , $url , $post_content );
	}

    /**
     * Retrieves option from db
     *
     * @since 4.2.0
     * @param $key string option name
     * @param bool $default string default value to return when option is not set or is empty
     * @return bool false if option not set or empty | mixed option value
     */
    protected function get_option( $key, $default = false ){
        return isset( $this->options[$key] ) && !empty( $this->options[$key] ) ? $this->options[$key] : $default;
    }


    /**
     * Return mapping dns config and status
     *
     * @since 4.2.0
     *
     * @param null $mapping
     * @return array
     */
    function get_dns_config( $mapping = null ) {
        if ( $mapping == null ) {
            $mapping = (object) array( 'domain' => 'www.example.com', 'active' => 1 );
        }

        $map_ipaddress = $this->get_option("map_ipaddress", __('IP not set by admin yet.', self::Text_Domain) );
        $no_www_domain = preg_replace('/^www\./', '', $mapping->domain);

        $records = array();
        if ( strpos( $map_ipaddress, ',' ) ) {
            // Multiple CNAME not supported, so assume A
            $_records = preg_split(',', $map_ipaddress);
            foreach ($_records as $record) {
                $records[] = array('host' => $mapping->domain, 'type' => 'A', 'target' => $record);
            }
        } else {
            if ( ip2long( $map_ipaddress ) > 0 ) {
                $rec_type = "A";
            } else {
                $rec_type = "CNAME";
            }
            $records[] = array('host' => $mapping->domain, 'type' => $rec_type, 'target' => $map_ipaddress);
        }
        return $records;
    }

	/**
	 * Updates excluded pages listing table with ajax
	 *
	 * @since 4.3.0
	 */
	function ajax_update_excluded_pages_list() {
		$wp_list_table = new Domainmap_Table_ExcludedPages_Listing();
		$wp_list_table->ajax_response();
	}

	/**
	 * Allow multiple domain mappings
	 * @return bool|mixed
	 */
	public static function allow_multiple(){
		if( defined("DOMAINMAPPING_ALLOWMULTI") ) return (bool) DOMAINMAPPING_ALLOWMULTI;
		return Domainmap_Plugin::instance()->get_option("map_allow_multiple", false);
	}

	/**
	 * Flushes rewrite rules on plugin activation
	 *
	 * @since 4.3.1
	 */
	function flush_rewrite_rules(){
		flush_rewrite_rules(true);
	}

	/**
	 * Removes trace of rewrite rule flush from db so that later on they can be flashed when the plugin gets activated again
	 *
	 * @since 4.3.1
	 */
	function remove_rewrite_rule_flush_trace(){
		global $wpdb;

		/**
		 * @param $wpdb WPDB
		 */
		$prefix = self::FLUSHED_REWRITE_RULES;

		$wpdb->query("DELETE FROM $wpdb->sitemeta WHERE `meta_key` LIKE '$prefix%'");
	}


	/**
	 * Function called after customier has loaded
	 * We call out function to load custom headers
	 */
	function init_customizer_preview(){
		add_filter( 'wp_headers', array($this, 'filter_iframe_security_headers' ), 10, 1);
	}
	
	
	/**
	 * Allow the customizer to load the mapped domain if accessed from the main site admin menu
	 * Only issue is the cross domain script dont work in the customier preview
	 *
	 * @param Array $headers
	 *
	 * @return Array $headers
	 */
	function filter_iframe_security_headers( $headers ){
		$customize_url 		= admin_url(); //Admin url
		$current_site_url 	= get_site_url(); //Current site url
		
		//parse each url
		$customize_site_url = parse_url( $customize_url );
		$current_site_url 	= parse_url( $current_site_url );

		//Get the host from each url to allow across the iframe
		$customize_site_url = $customize_site_url['host'];
		$current_site_url 	= $current_site_url['host'];
		
		//If the customizer domain and iframe domain are not the same, we add the 2 domains.
		//Firefox will not load if we ad the same domains
		if ( $customize_site_url != $current_site_url ) {
			//Add the host url for the customizr preview and the network admin site to allow iframe viewing incase customizer preview is a mapped domain
			$headers['X-Frame-Options'] 		= 'ALLOW-FROM ' . $customize_site_url .' '.$current_site_url ;
			$headers['Content-Security-Policy'] = 'frame-ancestors ' . $customize_site_url .' '.$current_site_url ;
		}
		return $headers;
	}

	/**
	 * WHMCS Admin warning notice
	 * Once the link is clicked the notice will no longer appear
	 *
	 * @since 4.4.2.5
	 */
	function show_whmcs_warning() {

		$show_notification = get_option( 'domainmapping_hide_notification', false );

		if ( !$show_notification ) {
			
			add_action( 'network_admin_notices', array( &$this, 'whmcs_warning' ) );
		}
	}
	
	/**
	 * WHMCS Admin warning notice
	 *
	 * @since 4.4.2.5
	 */
	function whmcs_warning() {
		$settings_url = add_query_arg( array(
			'page' 		=> 'domainmapping_options',
			'tab'  		=> 'reseller-options',
			'dismiss'  	=> 'true',
		), network_admin_url( 'settings.php', 'http' ) );
		
		$message      = sprintf( __( 'WHMCS is no longer supported in %s . Please check and update your <a href="%s">Reseller options</a>', 'domainmap' ), 'Domain Mapping' , $settings_url );
		$html_message = sprintf( '<div class="notice notice-warning">%s</div>', wpautop( $message ) );

		echo $html_message;
	}


	/**
	 * Returns instance of Domainmap_Utils
	 * @return Domainmap_Utils
	 */
	static function utils(){
		return new Domainmap_Utils();
	}
}
