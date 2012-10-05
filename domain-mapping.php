<?php
/*
Plugin Name: Domain Mapping plugin
Plugin URI: http://premium.wpmudev.org/project/domain-mapping
Description: A domain mapping plugin that can handle sub-directory installs and global logins
Version: 3.0.8.1
Author: Barry (Incsub)
Author URI: http://caffeinatedb.com
WDP ID: 99
Network: true
*/
/*  Copyright Incsub (http://incsub.com/)
    Based on an original by Donncha (http://ocaoimh.ie/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// UnComment out the line below to allow multiple domain mappings per blog
//define('DOMAINMAPPING_ALLOWMULTI', 'yes');

if ( !is_multisite() )
     exit( __('The domain mapping plugin is only compatible with WordPress Multisite.', 'domainmap') );

class domain_map {

	var $build = 6;

	var $db;

	var $dmt = '';

	var $mappings = array();

	function __construct() {

		global $wpdb, $dm_cookie_style_printed, $dm_logout, $dm_authenticated;
		
		$dm_cookie_style_printed = false;
		$dm_logout = false;
		$dm_authenticated = false;
		
		$this->db =& $wpdb;

		if(!empty($this->db->dmtable)) {
			$this->dmt = $this->db->dmtable;
		} else {
			if(defined('DM_COMPATIBILITY')) {
				if(!empty($this->db->base_prefix)) {
					$this->db->dmtable = $this->db->base_prefix . 'domain_mapping';
				} else {
					$this->db->dmtable = $this->db->prefix . 'domain_mapping';
				}
			} else {
				if(!empty($this->db->base_prefix)) {
					$this->db->dmtable = $this->db->base_prefix . 'domain_map';
				} else {
					$this->db->dmtable = $this->db->prefix . 'domain_map';
				}
			}
		}
		// Set up the plugin
		add_action('init', array(&$this, 'setup_plugin'));
		add_action('load-tools_page_domainmapping', array(&$this, 'add_admin_header'));

		add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

		// Add in the cross domain logins
		add_action( 'init', array(&$this, 'build_stylesheet_for_cookie'));
		
		// Add in menus
		add_action( 'manage_sites_custom_column', array(&$this, 'add_domain_mapping_field'), 1, 2 );
		
		add_filter( 'wpmu_blogs_columns', array(&$this, 'add_domain_mapping_column') );
		
                add_filter( 'login_url', array(&$this, 'domain_mapping_login_url'), 2, 100 );
                add_filter( 'logout_url', array(&$this, 'domain_mapping_login_url'), 2, 100 );
                add_filter( 'admin_url', array(&$this, 'domain_mapping_admin_url'), 3, 100 );
		
		add_filter( 'allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10);
		
		add_action( 'login_head', array(&$this, 'build_logout_cookie') );
	}
	
	function domain_map() {
		$this->__construct();
	}

	function load_textdomain() {
		$locale = apply_filters( 'domainmap_locale', get_locale() );
		$mofile = domainmap_dir( "languages/domainmap-$locale.mo" );
		if ( file_exists( $mofile ) )
			load_textdomain( 'domainmap', $mofile );
	}
	
	function shibboleth_session_initiator_url($initiator_url) {
		return $initiator_url;
	}

	function domain_mapping_login_url($login_url, $redirect='') {
		$logdom = get_site_option( 'map_logindomain', 'user' );
		
		switch($logdom) {
			case 'user':
				break;
			case 'mapped':
				break;
			case 'original':
				$mapped_url = site_url('/');
				$mapped_url = str_replace(array('https://', 'http://'), '', $mapped_url);
				remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
				$url = trailingslashit(get_option('siteurl'));
				$url = str_replace(array('https://', 'http://'), '', $url);
				$login_url = str_replace($mapped_url, $url, $login_url);
				add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
				break;
		}
		
		return $login_url;
	}
	
	function domain_mapping_admin_url($admin_url, $path = '/', $_blog_id = false) {
		global $blog_id;
		
		$logdom = get_site_option( 'map_admindomain', 'user' );
		
		if (!$_blog_id) {
			$_blog_id = $blog_id;
		}
		
		switch($logdom) {
			case 'user':
				break;
			case 'mapped':
				break;
			case 'original':
				$mapped_url = site_url('/');
				$mapped_url = str_replace(array('https://', 'http://'), '', $mapped_url);
				remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
				$url = trailingslashit(get_option('siteurl'));
				$url = str_replace(array('https://', 'http://'), '', $url);
				$admin_url = str_replace($mapped_url, $url, $admin_url);
				add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
				break;
		}
		
		return $admin_url;
	}

	function setup_plugin() {
		$this->handle_translation();
		
		if(isset($_POST['action']) && $_POST['action'] == 'updateoptions') {
			check_admin_referer('update-dmoptions');
			update_site_option('map_ipaddress', $_POST['map_ipaddress']);
			update_site_option('map_supporteronly', $_POST['map_supporteronly']);
			update_site_option('map_admindomain', $_POST['map_admindomain']);
			update_site_option('map_logindomain', $_POST['map_logindomain']);
		}
		
		if (is_admin()) {
			// We are in the admin area, so check for the redirects here
			$addom = get_site_option( 'map_admindomain', 'user' );
				
			switch($addom) {
				case 'user':
					break;
				case 'mapped':
					$this->redirect_to_mapped_domain();
					break;
				case 'original':
					if ( defined( 'DOMAIN_MAPPING' ) ) {
						// put in the code to send me to the original domain
						$this->redirect_to_orig_domain();
					}
					break;
			}
		} else {
			if (strpos(addslashes($_SERVER["SCRIPT_NAME"]),'/wp-login.php') !== false) {
				// We are in the login area, so check for the redirects here
				$logdom = get_site_option( 'map_logindomain', 'user' );
				
				switch($logdom) {
					case 'user':
						break;
					case 'mapped':
						$this->redirect_to_mapped_domain();
						break;
					case 'original':
						if ( defined( 'DOMAIN_MAPPING' ) ) {
							// put in the code to send me to the original domain
							$this->redirect_to_orig_domain();
						}
						break;
				}
			}
		}
		
		// Add the options page
		//add_action( 'wpmu_options', array(&$this, 'handle_domain_options'));
		//add_action( 'update_wpmu_options', array(&$this, 'update_domain_options'));
		add_action( 'network_admin_menu', array(&$this, 'add_admin_pages') );
		$sup = get_site_option( 'map_supporteronly', '0' );

		if(function_exists('is_pro_site') && $sup == '1') {
			// The supporter function exists and we are limiting domain mapping to supporters

			if(is_pro_site()) {
				// Add the management page
				add_action( 'admin_menu', array(&$this, 'add_page') );
				if ( defined( 'DOMAIN_MAPPING' ) ) {
					add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
					add_filter( 'pre_option_home', array(&$this, 'domain_mapping_home') );
					add_filter( 'the_content', array(&$this, 'domain_mapping_post_content') );
					// Jump in just before header output to change base_url - until a neater method can be found
					add_filter( 'print_head_scripts', array(&$this, 'reset_script_url'), 1, 1);
				}
				
				add_filter( 'plugins_url', array(&$this, 'swap_mapped_url'), 10, 3);
                                add_filter( 'content_url', array(&$this, 'swap_mapped_url'), 10, 2);
				add_filter( 'site_url', array(&$this, 'swap_mapped_url'), 10, 4);
				add_filter( 'home_url', array(&$this, 'swap_mapped_url'), 10, 3);
				
				add_filter( 'includes_url', array(&$this, 'unswap_mapped_url'), 10, 2);
				
				// Cross domain cookies
				//if ( defined('SHIBBOLETH_PLUGIN_REVISION') ) {
					//add_action( 'wp_login', array(&$this, 'build_cookie'), 100, 2 );
					add_filter( 'wp_redirect', array(&$this, 'wp_redirect'), 999, 2 );
				//}
				add_action( 'admin_head', array(&$this, 'build_cookie') );
				add_action( 'template_redirect', array(&$this, 'redirect_to_mapped_domain') );
			}
			add_action( 'delete_blog', array(&$this, 'delete_blog_domain_mapping'), 1, 2 );
		} else {
			// Add the management page
			add_action( 'admin_menu', array(&$this, 'add_page') );
			if ( defined( 'DOMAIN_MAPPING' ) ) {
				add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
				add_filter( 'pre_option_home', array(&$this, 'domain_mapping_home') );
				add_filter( 'the_content', array(&$this, 'domain_mapping_post_content') );
				// Jump in just before header output to change base_url - until a neater method can be found
				add_filter( 'print_head_scripts', array(&$this, 'reset_script_url'), 1, 1);
			}
			
			add_filter( 'plugins_url', array(&$this, 'swap_mapped_url'), 10, 3);
                        add_filter( 'content_url', array(&$this, 'swap_mapped_url'), 10, 2);
                        add_filter( 'site_url', array(&$this, 'swap_mapped_url'), 10, 4);
			add_filter( 'home_url', array(&$this, 'swap_mapped_url'), 10, 3);
			
			add_filter( 'includes_url', array(&$this, 'unswap_mapped_url'), 10, 2);
			// Cross domain cookies
			//if ( defined('SHIBBOLETH_PLUGIN_REVISION') ) {
				// add_action( 'wp_login', array(&$this, 'build_cookie'), 100, 2 );
				add_filter( 'wp_redirect', array(&$this, 'wp_redirect'), 999, 2 );
				add_filter('authenticate', array(&$this, 'authenticate'), 999, 3);
				add_action('wp_logout', array(&$this, 'wp_logout'), 10);
			//}
			add_action( 'admin_head', array(&$this, 'build_cookie') );
			add_action( 'template_redirect', array(&$this, 'redirect_to_mapped_domain') );
			add_action( 'delete_blog', array(&$this, 'delete_blog_domain_mapping'), 1, 2 );
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
	
	function wp_redirect($location) {
		global $dm_authenticated, $dm_logout, $dm_csc_building_urls;
		
		if ($dm_authenticated) {
			?>
			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
				<head>
					<meta name="robots" content="noindex,nofollow" />
					<title><?php _e('Authenticating...', 'domainmap'); ?></title>
					<?php
					if (!empty($dm_authenticated) && !empty($dm_authenticated->ID)) {
						$this->build_cookie('loging', $dm_authenticated, $location);
					} else {
						$this->build_cookie('logout');
					}
					
					if (count($dm_csc_building_urls) > 0) {
						$dm_csc_building_urls[] = rawurlencode($location);
						
						$location = rawurldecode(array_shift($dm_csc_building_urls));
						$location .= '&follow_through='.join(',', $dm_csc_building_urls);
					}
					?>
					<meta http-equiv="refresh" content="3;url=<?php echo $location; ?>" />
				</head>
				<body>
					<h1><?php _e('Please wait...', 'domainmap'); ?></h1>
					<p><?php echo sprintf(__('If it doesn\'t redirect in 5 seconds please click <a href="%s">here</a>.', 'domainmap'), $location); ?></p>
					<!-- Hej -->
				</body>
			</html>
			<?php
			header("HTTP/1.1 302 Found", true, 302);
			header("Location: {$location}", true, 302);
			define( 'DONOTCACHEPAGE', 1 ); // don't let wp-super-cache cache this page.
			// @ob_flush();
			exit();
		}
		
		if ($dm_logout) {
			?>
			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
				<head>
					<meta name="robots" content="noindex,nofollow" />
					<title><?php _e('Authenticating...', 'domainmap'); ?></title>
					<?php
					$this->build_cookie('logout');
					
					if (count($dm_csc_building_urls) > 0) {
						$dm_csc_building_urls[] = rawurlencode($location);
						
						$location = rawurldecode(array_shift($dm_csc_building_urls));
						$location .= '&follow_through='.join(',', $dm_csc_building_urls);
					}
					?>
					<meta http-equiv="refresh" content="3;url=<?php echo $location; ?>" />
				</head>
				<body>
					<h1><?php _e('Please wait...', 'domainmap'); ?></h1>
					<p><?php echo sprintf(__('If it doesn\'t redirect in 5 seconds please click <a href="%s">here</a>.', 'domainmap'), $location); ?></p>
					<!-- Hej dŒ -->
				</body>
			</html>
			<?php
			header("HTTP/1.1 302 Found", true, 302);
			header("Location: {$location}", true, 302);
			define( 'DONOTCACHEPAGE', 1 ); // don't let wp-super-cache cache this page.
			// @ob_flush();
			exit();
		}
		
		return $location;
	}

	function handle_translation() {
		$locale = get_locale();
		if(empty($locale)) $locale = "en_US";
		$mofile = WP_LANG_DIR . "/domainmap-$locale.mo";
		load_textdomain('domainmap', $mofile);
	}

	// Cookie functions
	function build_logout_cookie() {
		if(isset($_GET['loggedout'])) {
			// Log out CSS
			$this->build_cookie('logout');
		}
	}

	function build_cookie($action = 'login', $user = false, $redirect_to = false) {
		global $blog_id, $current_site, $dm_cookie_style_printed, $current_blog, $dm_logout, $dm_csc_building_urls;
		
		/**
		 * Cookie building order:
		 * - Main site url
		 * - Main site admin url
		 * - Unmapped site
		 * - Mapped site
		 * - Redirect to unampped site
		 * - Redirect to mapped site
		 */
		
		if (!is_array($dm_csc_building_urls)) {
			$dm_csc_building_urls = array();
		}
		if($action == '' || $action != 'logout') $action = 'login';

		$urls = array();
		
		// Main site url
		$network_url = parse_url(network_site_url());
		if (!isset($urls[$network_url['host']]))
			$urls[$network_url['host']] = 'http://' . $network_url['host'] . '/';
		
		// Main site admin url
		$network_admin_url = parse_url(network_admin_url());
		if (!isset($urls[$network_admin_url['host']]))
			$urls[$network_admin_url['host']] = 'http://' . $network_admin_url['host'] . $network_url['path'];
		
		// Unmapped site
		$domain = $this->db->get_row( "SELECT domain, path FROM {$this->db->blogs} WHERE blog_id = '{$blog_id}' LIMIT 1 /* domain mapping */", ARRAY_A);
		if (!isset($urls[$domain['domain']]))
			$urls[$domain['domain']] = 'http://' . $domain['domain'] . $domain['path'];
		
		// Mapped site
		$domains = $this->db->get_results( "SELECT domain FROM {$this->dmt} WHERE blog_id = '{$blog_id}' ORDER BY id /* domain mapping */", ARRAY_A);
		if($domains && is_array($domains)) {
			foreach ($domains as $domain) {
				if (!isset($urls[$domain['domain']]))
					$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
			}
		}
		
		// We are redirecting, lets pack some cookies for the journey. Nom Nom Nom
		if ($redirect_to) {
			$redirect_url = parse_url($redirect_to);
			
			$domain = $this->db->get_row( "SELECT blog_id, domain FROM {$this->dmt} WHERE domain = '{$redirect_url['host']}' OR domain LIKE '{$redirect_url['host']}/%' ORDER BY id LIMIT 1 /* domain mapping */", ARRAY_A);
			if ($domain) {
				// redirect to unmapped site
				$addom = get_site_option( 'map_admindomain', 'user' );
				if (!isset($urls[$domain['domain']]))
					$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
				
				// Other mapped sites
				$domains = $this->db->get_results( "SELECT domain FROM {$this->dmt} WHERE blog_id = '{$domain->blog_id}' ORDER BY id /* domain mapping */", ARRAY_A);
				if($domains && is_array($domains)) {
					foreach ($domains as $domain) {
						if (!isset($urls[$domain['domain']]))
							$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
					}
				}
				
				// redirect to mapped site
				$domain = $this->db->get_row( "SELECT domain, path FROM {$this->db->blogs} WHERE blog_id = '{$domain->blog_id}' LIMIT 1 /* domain mapping */", ARRAY_A);
				if ($domain) {
					if (!isset($urls[$domain['domain']]))
						$urls[$domain['domain']] = 'http://' . $domain['domain'] . $domain['path'];
				}
			} else {
				// redirect to unmapped site
				$domain = $this->db->get_row( "SELECT blog_id, domain, path FROM {$this->db->blogs} WHERE domain = '{$redirect_url['host']}' LIMIT 1 /* domain mapping */", ARRAY_A);
				if ($domains) {
					if (!isset($urls[$domain['domain']]))
						$urls[$domain['domain']] = 'http://' . $domain['domain'] . $domain['path'];
					
					// Other mapped sites
					$domains = $this->db->get_results( "SELECT domain FROM {$this->dmt} WHERE blog_id = '{$domain->blog_id}' ORDER BY id /* domain mapping */", ARRAY_A);
					if($domains && is_array($domains)) {
						foreach ($domains as $domain) {
							if (!isset($urls[$domain['domain']]))
								$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
						}
					}
				
					// redirect to mapped site
					$domain = $this->db->get_row( "SELECT blog_id, domain FROM {$this->dmt} WHERE blog_id = '{$domains->blog_id}' LIMIT 1 /* domain mapping */", ARRAY_A);
					if ($domain) {
						if (!isset($urls[$domain['domain']]))
							$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
					}
				}
			}
		}
		
		$_ssl = false;
		if ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) {
			$_ssl = true;
		}
		
		if(count($urls) > 0) {
			$key = get_user_meta($user->ID, 'cross_domain', true);
			if($key == 'none') $key = array();
			foreach ($urls as $url) {
				$parsed_url = parse_url($url);
				
				if (!isset($parsed_url['host']) || empty($parsed_url['host'])) {
					continue;
				}
				
				$hash = md5( AUTH_KEY . microtime() . 'COOKIEMONSTER' . $url );
				
				if (!$user) {
					$user = wp_get_current_user();
				}
				$key[$hash] = array ( 	"domain" 	=> $url,
										"hash"		=> $hash,
										"user_id"	=> $user->ID,
										"action"	=> $action
										);
				
				if ( is_admin() ) {
					if ( ( ($_ssl && preg_match('/https:\/\//', $url) > 0) || (!$_ssl && preg_match('/http:\/\//', $url) > 0) ) ) {
						$dm_cookie_style_printed = true;
						echo '<link rel="stylesheet" href="' . $url . $hash . '?action='.$action.'&uid='.$user->ID.'&build=' . date("Ymd", strtotime('-24 days') ) . '" type="text/css" media="screen" />';
					} else if ($_ssl) {
						$url = preg_replace( '/http:\/\//', 'https://', $url );
						echo '<link rel="stylesheet" href="' . $url . $hash . '?action='.$action.'&uid='.$user->ID.'&build=' . date("Ymd", strtotime('-24 days') ) . '" type="text/css" media="screen" />';
					} else {
						$url = preg_replace( '/https:\/\//', 'http://', $url );
						echo '<link rel="stylesheet" href="' . $url . $hash . '?action='.$action.'&uid='.$user->ID.'&build=' . date("Ymd", strtotime('-24 days') ) . '" type="text/css" media="screen" />';
					}
				}
				
				$dm_csc_building_urls[] = rawurlencode( $url . $hash . '?action='.$action.'&uid='.$user->ID.'&build=' . date("Ymd", strtotime('-24 days') ) );
			}
			update_user_meta($user->ID, 'cross_domain', $key);
		}
	}
	
	function allowed_redirect_hosts($allowed_hosts) {
		global $blog_id;
		
		if (empty($_REQUEST['redirect_to'])) {
			return $allowed_hosts;
		}
		
		$redirect_url = parse_url($_REQUEST['redirect_to']);
		$network_home_url = parse_url(network_home_url());
		if ($redirect_url['host'] === $network_home_url['host']) {
			return $allowed_hosts;
		}
		
		$pos = strpos($redirect_url['host'], '.');
		if (($pos !== false) && (substr($redirect_url['host'], $pos + 1) === $network_home_url['host'])) {
			$allowed_hosts[] = $redirect_url['host'];
		}
		
		$bid = $this->db->get_var( "SELECT blog_id FROM {$this->dmt} WHERE domain = '{$redirect_url['host']}' ORDER BY id LIMIT 1 /* domain mapping */");
		if ($bid) {
			$allowed_hosts[] = $redirect_url['host'];
		}
		
		return $allowed_hosts;
	}

	function build_stylesheet_for_cookie() {
		if( isset($_GET['build']) && isset($_GET['uid']) ) {
			if ( addslashes($_GET['build']) == date("Ymd", strtotime('-24 days') ) ) {
				// We have a stylesheet with a build and a matching date - so grab the hash
				$url = parse_url($_SERVER[ 'REQUEST_URI' ], PHP_URL_PATH);
				$hash = str_replace('','', basename($url));
				$key = get_user_meta($_GET['uid'], 'cross_domain', true);
				
				if (array_key_exists($hash, (array) $key)) {
					if(!is_user_logged_in() ) {
						// Set the cookies
						switch($key[$hash]['action']) {
							case 'login':	wp_set_auth_cookie($key[$hash]['user_id']);
											break;
							default:
											break;
						}
					} else {
						// Set the cookies
						switch($key[$hash]['action']) {
							case 'logout':
								wp_clear_auth_cookie();
								break;
							default:
								break;
						}
					}
					unset($key[$hash]);
					update_user_meta($_GET['uid'], 'cross_domain', (array) $key);
				}
			}
			define( 'DONOTCACHEPAGE', 1 ); // don't let wp-super-cache cache this page.
			if (!isset($_REQUEST['follow_through'])) {
				header("Content-type: text/css");
				echo "/* Sometimes me think what is love, and then me think love is what last cookie is for. Me give up the last cookie for you. */";
			} else {
			?>
				<!DOCTYPE html>
				<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
					<head>
						<meta name="robots" content="noindex,nofollow" />
						<title><?php _e('Authenticating...', 'domainmap'); ?></title>
						<?php
						$follow_through = preg_split('/,/', $_REQUEST['follow_through']);
						if (count($follow_through) > 0) {
							$location = rawurldecode(array_pop($follow_through));
						} else {
							$location = site_url();
						}
						if (count($follow_through) > 0) {
							foreach ($follow_through as $dm_csc_style_location) {
								echo '<link rel="stylesheet" href="' . rawurldecode($dm_csc_style_location) . '" type="text/css" media="screen" />';
							}
						}
						?>
					</head>
					<body>
						<h1><?php _e('Please wait...', 'domainmap'); ?></h1>
						<p><?php echo sprintf(__('If it doesn\'t redirect in 5 seconds please click <a href="%s">here</a>.', 'domainmap'), $location); ?></p>
						<script type="text/javascript">
							window.location = '<?php echo $location; ?>';
						</script>
						<!-- Flytta pŒ -->
					</body>
				</html>
				<?php
			}
			exit();
		}
	}

	function add_admin_header() {

	}

	function add_admin_pages() {
		add_submenu_page('settings.php', __('Domain Mapping','domainmap'), __('Domain Mapping','domainmap'), 'manage_options', "domainmapping_options", array(&$this,'handle_options_page'));
	}

	function add_page() {
		add_management_page( __('Domain Mapping', 'domainmap'), __('Domain Mapping', 'domainmap'), 'manage_options', 'domainmapping', array(&$this, 'handle_domain_page') );
	}

	function handle_dash_page() {

		require_once('classes/class.domains.php');
		$wp_domains_table = new WP_MS_Domains_List_Table();

		?>
			<div class="wrap">
			<?php screen_icon('ms-admin'); ?>
			<h2><?php _e('Domain Mapping', 'domainmap') ?>
			<?php echo $msg; ?>
			</h2>

			<form action="" method="get" id="ms-search">
			<?php $wp_domains_table->search_box( __( 'Search Sites' ), 'site' ); ?>
			<input type="hidden" name="action" value="blogs" />
			</form>

			<form id="form-site-list" action="edit.php?action=allblogs" method="post">
				<?php $wp_domains_table->display(); ?>
			</form>
			</div>
		<?php
	}

	function handle_options_page() {


		if(isset($_POST['action']) && $_POST['action'] == 'updateoptions') {

			check_admin_referer('update-dmoptions');

			update_site_option('map_ipaddress', $_POST['map_ipaddress']);
			update_site_option('map_supporteronly', $_POST['map_supporteronly']);

			update_site_option('map_admindomain', $_POST['map_admindomain']);
			update_site_option('map_logindomain', $_POST['map_logindomain']);
			
			$msg = 1;
		}

		$messages = array();
		$messages[1] = __('Options updated.','domainmap');

		?>
		<div class="wrap">
		<?php screen_icon('ms-admin'); ?>
		<h2><?php _e('Domain mapping Options', 'domainmap') ?>
		</h2>

			<?php
			if ( !empty($msg) ) {
				echo '<div id="message" class="updated fade"><p>' . $messages[(int) $msg] . '</p></div>';
				$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
			}
			?>

		<form action="" method="post" id="">

		<?php
		wp_nonce_field('update-dmoptions');
		echo '<h3>' . __( 'Domain mapping Configuration' ) . '</h3>';

		if ( !file_exists( ABSPATH . '/wp-content/sunrise.php' ) ) {
			echo "<p><strong>" . __("Please copy the sunrise.php to ", 'domainmap') . ABSPATH . __("/wp-content/sunrise.php and uncomment the SUNRISE setting in the ", 'domainmap') . ABSPATH . __("wp-config.php file", 'domainmap') . "</strong></p>";
		}

		if ( !defined( 'SUNRISE' ) ) {
			echo "<p><strong>" . __("Please uncomment the line <em>//define( 'SUNRISE', 'on' );</em> in the ", 'domainmap') . ABSPATH . __("wp-config.php file.", 'domainmap') . "</strong></p>";
		}

		echo "<p>" . __( "Enter the IP address users need to point their DNS A records at. If you don't know what it is, ping this blog to get the IP address.", 'domainmap' ) . "</p>";
		echo "<p>" . __( "If you have more than one IP address, separate them with a comma. This message is displayed on the Domain mapping page for your users.", 'domainmap' ) . "</p>";
		_e( "Server IP Address: ", 'domainmap' );
		echo "<input type='text' name='map_ipaddress' value='" . get_site_option( 'map_ipaddress' ) . "' />";

		if(function_exists('is_pro_site')) {
			$sup = get_site_option( 'map_supporteronly', '0' );
			echo '<p>' . __('Make this functionality only available to Pro Sites', 'domainmap') . '</p>';
			_e("Pro Sites Only: ", 'domainmap');
			echo "<select name='map_supporteronly'>";
			echo "<option value='0'";
			if($sup == 0) echo " selected='selected'";
			echo ">" . __('No', 'domainmap') . "</option>";
			echo "<option value='1'";
			if($sup == 1) echo " selected='selected'";
			echo ">" . __('Yes', 'domainmap') . "</option>";
			echo "</select>";
		}

		echo '<h4>' . __( 'Administration mapping', 'domainmap' ) . '</h4>';

		echo "<p>" . __( "The settings below allow you to control how the domain mapping plugin operates with the administration area.", 'domainmap' ) . "</p>";

		$addom = get_site_option( 'map_admindomain', 'user' );
		echo '<p>';
		echo __('The domain used for the administration area should be the', 'domainmap') . '&nbsp;';
		echo "<select name='map_admindomain'>";
		echo "<option value='user'";
		if($addom == 'user') echo " selected='selected'";
		echo ">" . __('domain entered by the user', 'domainmap') . "</option>";
		echo "<option value='mapped'";
		if($addom == 'mapped') echo " selected='selected'";
		echo ">" . __('mapped domain', 'domainmap') . "</option>";
		echo "<option value='original'";
		if($addom == 'original') echo " selected='selected'";
		echo ">" . __('original domain', 'domainmap') . "</option>";
		echo "</select>";
		echo '</p>';
		
		echo '<h4>' . __( 'Login mapping', 'domainmap' ) . '</h4>';

		echo "<p>" . __( "The settings below allow you to control how the domain mapping plugin operates with the login area.", 'domainmap' ) . "</p>";

		$logdom = get_site_option( 'map_logindomain', 'user' );
		echo '<p>';
		echo __('The domain used for the login area should be the', 'domainmap') . '&nbsp;';
		echo "<select name='map_logindomain'>";
		echo "<option value='user'";
		if($logdom == 'user') echo " selected='selected'";
		echo ">" . __('domain entered by the user', 'domainmap') . "</option>";
		echo "<option value='mapped'";
		if($logdom == 'mapped') echo " selected='selected'";
		echo ">" . __('mapped domain', 'domainmap') . "</option>";
		echo "<option value='original'";
		if($logdom == 'original') echo " selected='selected'";
		echo ">" . __('original domain', 'domainmap') . "</option>";
		echo "</select>";
		echo '</p>';

		?>
			<input type='hidden' name='action' value='updateoptions' />
			<p class="submit"><input type="submit" value="Save Changes" class="button-primary" id="submit" name="submit"></p>
			</form>
			</div>
		<?php
	}

	function update_domain_options() {

		update_site_option('map_ipaddress', $_POST['map_ipaddress']);
		update_site_option('map_supporteronly', $_POST['map_supporteronly']);

		update_site_option('map_admindomain', $_POST['map_admindomain']);
		update_site_option('map_logindomain', $_POST['map_logindomain']);
	}

	function handle_domain_options() {
		echo '<h3>' . __( 'Domain mapping Configuration' ) . '</h3>';

		if ( !file_exists( ABSPATH . '/wp-content/sunrise.php' ) ) {
			echo "<p><strong>" . __("Please copy the sunrise.php to ", 'domainmap') . ABSPATH . __("/wp-content/sunrise.php and uncomment the SUNRISE setting in the ", 'domainmap') . ABSPATH . __("wp-config.php file", 'domainmap') . "</strong></p>";
		}

		if ( !defined( 'SUNRISE' ) ) {
			echo "<p><strong>" . __("Please uncomment the line <em>//define( 'SUNRISE', 'on' );</em> in the ", 'domainmap') . ABSPATH . __("wp-config.php file.", 'domainmap') . "</strong></p>";
		}

		echo "<p>" . __( "Enter the IP address users need to point their DNS A records at. If you don't know what it is, ping this blog to get the IP address.", 'domainmap' ) . "</p>";
		echo "<p>" . __( "If you have more than one IP address, separate them with a comma. This message is displayed on the Domain mapping page for your users.", 'domainmap' ) . "</p>";
		_e( "Server IP Address: ", 'domainmap' );
		echo "<input type='text' name='map_ipaddress' value='" . get_site_option( 'map_ipaddress' ) . "' />";

		if(function_exists('is_pro_site')) {
			$sup = get_site_option( 'map_supporteronly', '0' );
			echo '<p>' . __('Make this functionality only available to Supporters', 'domainmap') . '</p>';
			_e("Supporters Only: ", 'domainmap');
			echo "<select name='map_supporteronly'>";
			echo "<option value='0'";
			if($sup == 0) echo " selected='selected'";
			echo ">" . __('No', 'domainmap') . "</option>";
			echo "<option value='1'";
			if($sup == 1) echo " selected='selected'";
			echo ">" . __('Yes', 'domainmap') . "</option>";
			echo "</select>";
		}

		echo '<h4>' . __( 'Administration mapping', 'domainmap' ) . '</h4>';

		echo "<p>" . __( "The settings below allow you to control how the domain mapping plugin operates with the administration area.", 'domainmap' ) . "</p>";

		$addom = get_site_option( 'map_admindomain', 'user' );
		echo '<p>';
		echo __('The domain used for the administration area should be the', 'domainmap') . '&nbsp;';
		echo "<select name='map_admindomain'>";
		echo "<option value='user'";
		if($addom == 'user') echo " selected='selected'";
		echo ">" . __('domain entered by the user', 'domainmap') . "</option>";
		echo "<option value='mapped'";
		if($addom == 'mapped') echo " selected='selected'";
		echo ">" . __('mapped domain', 'domainmap') . "</option>";
		echo "<option value='original'";
		if($addom == 'original') echo " selected='selected'";
		echo ">" . __('original domain', 'domainmap') . "</option>";
		echo "</select>";
		echo '</p>';

		/*
		echo '<p>';
		$ssl = get_site_option( 'map_adminssl', '0' );
		_e("Enforce SSL for the administration area: ", 'domainmap');
		echo "<select name='map_adminssl'>";
		echo "<option value='0'";
		if($ssl == 0) echo " selected='selected'";
		echo ">" . __('No', 'domainmap') . "</option>";
		echo "<option value='1'";
		if($ssl == 1) echo " selected='selected'";
		echo ">" . __('Yes', 'domainmap') . "</option>";
		echo "</select>";
		echo '</p>';
		*/


	}

	function handle_domain_page() {

		global $current_site;

		$this->db->dmtable = $this->db->base_prefix . 'domain_map';

		if ( is_super_admin() ) {
			if($this->db->get_var("SHOW TABLES LIKE '{$this->dmt}'") != $this->dmt) {
				$this->db->query( "CREATE TABLE IF NOT EXISTS `{$this->dmt}` (
					`id` bigint(20) NOT NULL auto_increment,
					`blog_id` bigint(20) NOT NULL,
					`domain` varchar(255) NOT NULL,
					`active` tinyint(4) default '1',
					PRIMARY KEY  (`id`),
					KEY `blog_id` (`blog_id`,`domain`,`active`)
				);" );
			}
		}


		if ( !empty( $_POST[ 'action' ] ) ) {
			$domain = $this->db->escape( preg_replace( "/^www\./", "", $_POST[ 'domain' ] ) );
			check_admin_referer( 'domain_mapping' );
			switch( $_POST[ 'action' ] ) {
				case "add":
					if( null == $this->db->get_row( $this->db->prepare("SELECT blog_id FROM {$this->db->blogs} WHERE domain = %s AND path = '/' /* domain mapping */", strtolower($domain)) ) && null == $this->db->get_row( $this->db->prepare("SELECT blog_id FROM {$this->dmt} WHERE domain = %s /* domain mapping */", strtolower($domain) ) ) ) {
						$this->db->query( $this->db->prepare( "INSERT INTO {$this->dmt} ( `id` , `blog_id` , `domain` , `active` ) VALUES ( NULL, %d, %s, '1') /* domain mapping */", $this->db->blogid, strtolower($domain)) );
					}
				break;
				case "delete":
					$this->db->query( $this->db->prepare("DELETE FROM {$this->dmt} WHERE domain = %s /* domain mapping */", strtolower($domain) ) );
				break;
			}
		}

		//testing

		echo "<div class='wrap'><div class='icon32' id='icon-tools'><br/></div><h2>" . __('Domain Mapping', 'domainmap') . "</h2>";

		echo "<p>" . __( 'If your domain name includes a sub-domain such as "blog" then you can add a CNAME for that hostname in your DNS pointing at this blog URL.', 'domainmap' ) . "</p>";
		$map_ipaddress = get_site_option( 'map_ipaddress', __('IP not set by admin yet.', 'domainmap') );
		if ( strpos( $map_ipaddress, ',' ) ) {
			echo "<p>" . __( 'If you want to redirect a domain you will need to add multiple DNS "A" records pointing at the IP addresses of this server: ', 'domainmap' ) . "<strong>" . $map_ipaddress . "</strong></p>";
		} else {
			echo "<p>" . __( 'If you want to redirect a domain you will need to add a DNS "A" record pointing at the IP address of this server: ', 'domainmap' ) . "<strong>" . $map_ipaddress . "</strong></p>";
		}

		?>
		<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
			<thead>
				<tr>
					<th scope="col" class="check-column"></th>
					<th scope="col" width="45%"><?php _e('Custom Domain','domainmap'); ?></th>
					<th scope="col" width="45%"><?php _e('Original Blog Address','domainmap'); ?></th>
					<th scope="col"><?php _e('Actions'); ?></th>
				</tr>
			</thead>
			<tbody id="the-list">

		<?php
		$domains = $this->db->get_results( $this->db->prepare("SELECT * FROM {$this->dmt} WHERE blog_id = %d",$this->db->blogid) );
		if ( is_array( $domains ) && !empty( $domains ) ) {
			foreach( $domains as $details ) { ?>

				<tr  class=''>
					<th scope="row" class="check-column">&nbsp;

					</th>
					<td>
						<a href='http://<?php echo $details->domain . $current_site->path; ?>'>http://<?php echo $details->domain . $current_site->path; ?></a>
					</td>
					<td>
					<?php
					$orig = $this->db->get_row( $this->db->prepare("SELECT * FROM {$this->db->blogs} WHERE blog_id = %d",$this->db->blogid) );
					?>
					<a href='http://<?php echo $orig->domain . $orig->path; ?>'>http://<?php echo $orig->domain . $orig->path; ?></a>
					</td>
					<td>
					<?php
					echo '<form method="POST">';
					echo '<input type="hidden" name="action" value="delete" />';
					echo "<input type='hidden' name='domain' value='{$details->domain}' />";
					echo "<input type='submit' value='" . __('Delete','domainmap') . "' class='button' />";
					wp_nonce_field( 'domain_mapping' );
					echo "</form>";
					?>
					</td>
				</tr>
			<?php
			}
		}

		reset($domains);

		if(empty($domains) || defined('DOMAINMAPPING_ALLOWMULTI')) {
		?>
		<form method="POST">
		<tr  class=''>
			<th scope="row" class="check-column">&nbsp;

			</th>
			<td>
				http://<input type='text' name='domain' value='' />/
			</td>
			<td>
			<?php
			$orig = $this->db->get_row( $this->db->prepare("SELECT * FROM {$this->db->blogs} WHERE blog_id = %d",$this->db->blogid) );
			?>
			<a href='http://<?php echo $orig->domain . $orig->path; ?>'>http://<?php echo $orig->domain . $orig->path; ?></a>
			</td>
			<td>
			<?php
			echo '<input type="hidden" name="action" value="add" />';
			wp_nonce_field( 'domain_mapping' );
			echo "<input type='submit' value='Add' class='button' />";
			?>
			</td>
		</tr>
		</form>
					<?php } ?>
		</tbody>
		</table>
		</div>
		<?php
	}

	function reset_script_url($return) {

		global $wp_scripts;

		$wp_scripts->base_url = site_url();

		return $return;
	}

	function swap_mapped_url($url, $path, $plugin = false, $bid = null) {
		global $current_blog, $current_site, $mapped_id, $current_blog;
		// To reduce the number of database queries, save the results the first time we encounter each blog ID.
		static $swapped_url = array();
		
		if ($plugin == 'relative') {
			return "{$current_blog->path}{$path}";
		}
		
		if ($plugin == 'login_post' || $plugin == 'login') {
			return $this->domain_mapping_login_url($url);
		}
		
		if ($plugin == 'admin') {
			return $this->domain_mapping_admin_url($url);
		}
		if ( !isset( $swapped_url[ $this->db->blogid ] ) ) {
			$s = $this->db->suppress_errors();
			$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );
			//$olddomain = str_replace($path, '', $url);
			$olddomain = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name='siteurl' LIMIT 1 /* domain mapping */" ) );
			if ( empty( $domain ) ) {
				$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
			}
			$this->db->suppress_errors( $s );
			$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
			if ( $domain ) {
				$innerurl = trailingslashit( $protocol . $domain . $current_site->path );
				$newurl = str_replace($olddomain, $innerurl, $url);
				$swapped_url[ $this->db->blogid ] = array($olddomain, $innerurl);
				$url = $newurl;
			} else {
				$swapped_url[ $this->db->blogid ] = false;
			}
		} elseif ( $swapped_url[ $this->db->blogid ] !== FALSE) {
			$olddomain = $swapped_url[ $this->db->blogid ][0];
			$url = str_replace($olddomain, $swapped_url[ $this->db->blogid ][1], $url);
		}
		return $url;
	}
	
	function unswap_mapped_url($url, $path) {
		global $current_blog, $current_site, $mapped_id;
		// To reduce the number of database queries, save the results the first time we encounter each blog ID.
		static $swapped_url = array();
		
		if ( !isset( $swapped_url[ $this->db->blogid ] ) ) {
			$s = $this->db->suppress_errors();
			$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );
			//$olddomain = str_replace($path, '', $url);
			$olddomain = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name='siteurl' LIMIT 1 /* domain mapping */" ) );
			if ( empty( $domain ) ) {
				$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
			}
			$this->db->suppress_errors( $s );
			$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
			if ( $domain ) {
				$innerurl = trailingslashit( $protocol . $domain . $current_site->path );
				$newurl = str_replace($innerurl, $olddomain, $url);
				$swapped_url[ $this->db->blogid ] = array($olddomain, $innerurl);
				$url = $newurl;
			} else {
				$swapped_url[ $this->db->blogid ] = false;
			}
		} elseif ( $swapped_url[ $this->db->blogid ] !== FALSE) {
			$olddomain = $swapped_url[ $this->db->blogid ][0];
			$url = str_replace($swapped_url[ $this->db->blogid ][1], $olddomain, $url);
		}
		if ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) {
			$url = str_replace('http://', 'https://', $url);
		}
		return $url;
	}

	function domain_mapping_siteurl( $setting ) {
		global $current_blog, $current_site, $mapped_id;

		// To reduce the number of database queries, save the results the first time we encounter each blog ID.
		static $return_url = array();

		if ( !isset( $return_url[ $this->db->blogid ] ) ) {
			$s = $this->db->suppress_errors();

			$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );

			if ( empty( $domain ) ) {
				$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
			}

			$this->db->suppress_errors( $s );
			$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
			if ( $domain ) {
				$return_url[ $this->db->blogid ] = untrailingslashit( $protocol . $domain . $current_site->path );
				$setting = $return_url[ $this->db->blogid ];
			} else {
				$return_url[ $this->db->blogid ] = false;
			}
		} elseif ( $return_url[ $this->db->blogid ] !== FALSE) {
			$setting = $return_url[ $this->db->blogid ];
		}

		return $setting;
	}

	function domain_mapping_home( $setting ) {
		global $current_blog, $current_site, $mapped_id;

		// To reduce the number of database queries, save the results the first time we encounter each blog ID.
		static $return_home = array();

		if ( !isset( $return_home[ $this->db->blogid ] ) ) {
			$s = $this->db->suppress_errors();

			$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );

			if ( empty( $domain ) ) {
				$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmt} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
			}

			$this->db->suppress_errors( $s );
			$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
			if ( $domain ) {
				$return_home[ $this->db->blogid ] = untrailingslashit( $protocol . $domain . $current_site->path );
				$setting = $return_home[ $this->db->blogid ];
			} else {
				$return_home[ $this->db->blogid ] = false;
			}
		} elseif ( $return_home[ $this->db->blogid ] !== FALSE) {
			$setting = $return_home[ $this->db->blogid ];
		}

		return $setting;
	}

	function domain_mapping_post_content( $post_content ) {

		static $orig_urls = array();
		if ( ! isset( $orig_urls[ $this->db->blogid ] ) ) {
			remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
			$orig_url = get_option( 'siteurl' );
			if ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) {
				$orig_url = str_replace( "http://", "https://", $orig_url );
			} else {
				$orig_url = str_replace( "https://", "http://", $orig_url );
			}
			$orig_urls[ $this->db->blogid ] = $orig_url;
			add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
		} else {
			$orig_url = $orig_urls[ $this->db->blogid ];
		}
		$url = $this->domain_mapping_siteurl( 'NA' );
		if ( $url == 'NA' )
			return $post_content;
		return str_replace( trailingslashit($orig_url), trailingslashit($url), $post_content );
	}

	function redirect_to_mapped_domain() {
		global $current_blog, $current_site;

		$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) )  ? 'https://' : 'http://';
		$url = $this->domain_mapping_siteurl( false );
		if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_site->path ) ) {
			// strip out any subdirectory blog names
			$request = str_replace("/a" . $current_blog->path, "/", "/a" . $_SERVER[ 'REQUEST_URI' ]);
			if($request != $_SERVER[ 'REQUEST_URI' ]) {
				Header( "Location: " . $url . $request,  301);
			} else {
				Header( "Location: " . $url . $_SERVER[ 'REQUEST_URI' ], 301 );
			}
			exit;
		}
	}

	function redirect_to_orig_domain() {
		global $current_blog, $current_site;

		// don't redirect AJAX requests
		if ( defined( 'DOING_AJAX' ) )
			return;
		
		$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) )  ? 'https://' : 'http://';
		remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
		$url = get_option( 'siteurl' );
		if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_site->path ) ) {
			// strip out any subdirectory blog names
			$request = str_replace("/a" . $current_blog->path, "/", "/a" . $_SERVER[ 'REQUEST_URI' ]);
			if($request != $_SERVER[ 'REQUEST_URI' ]) {
				Header( "Location: " . $url . $request,  301);
			} else {
				Header( "Location: " . $url . $_SERVER[ 'REQUEST_URI' ], 301 );
			}
			exit;
		}
		add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
	}

	function delete_blog_domain_mapping( $blog_id, $drop ) {

		if ( $blog_id && $drop ) {
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->dmt} WHERE blog_id  = %d /* domain mapping */", $blog_id ) );
		}
	}

	function add_domain_mapping_column( $columns ) {

		$first_array = array_splice ($columns, 0, 2);
		$columns = array_merge ($first_array, array('domainmap' => __('Custom Domain')), $columns);

		return $columns;
	}

	function build_domain_mapping_cache() {

		global $current_site;

		if(empty($this->mappings)) {

			$mappings = $this->db->get_results( "SELECT blog_id, domain FROM {$this->dmt} /* domain mapping */" );
			foreach($mappings as $map) {
				if($current_site->path == '/') {
					$this->mappings[$map->blog_id][] = "<a href='http://" . $map->domain . $current_site->path . "'>" . $map->domain . "</a>";
				} else {
					$this->mappings[$map->blog_id][] = "<a href='http://" . $map->domain . $current_site->path . "'>" . $map->domain . $current_site->path . "</a>";
				}
			}

		}

	}

	function add_domain_mapping_field( $column, $blog_id ) {

		$this->build_domain_mapping_cache();

		if ( $column == 'domainmap' ) {
			if(isset($this->mappings[$blog_id])) {
				echo implode("<br/>", $this->mappings[$blog_id]);
			}
		}
	}

	//// New function added to handle mapping the domain for the thickbox plugin in the admin section
	function load_tb_fix()
	{
		?>
		<script type="text/javascript">
			'tb_pathToImage = "<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>";
			'tb_closeImage = "<?php echo includes_url('js/thickbox/tb-close.png'); ?>";
		</script>

		<?php
}

} //end class domain_map


function set_domainmap_dir($base) {

	global $domainmap_dir;

	if(defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename($base))) {
		$domainmap_dir = trailingslashit(WPMU_PLUGIN_URL);
	} elseif(defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/domain-mapping/' . basename($base))) {
		$domainmap_dir = trailingslashit(WP_PLUGIN_DIR . '/domain-mapping');
	} else {
		$domainmap_dir = trailingslashit(WP_PLUGIN_DIR . '/domain-mapping');
	}


}

function domainmap_dir($extended) {

	global $domainmap_dir;

	return $domainmap_dir . $extended;


}

include_once('dash-notice/wpmudev-dash-notification.php');


// Set up my location
set_domainmap_dir(__FILE__);

global $dm_map;

$dm_map =& new domain_map();


