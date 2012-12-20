<?php
if( !class_exists('domain_map')) {

	class domain_map {

		var $build = 7;

		var $db;

		// The tables we need to map - empty for now as we will move to this later
		var $tables = array();

		// The main domain mapping tables
		var $dmtable;

		var $mappings = array();

		// The domain mapping options
		var $options;

		// For caching swapped urls later on
		var $swapped_url = array();

		function __construct() {

			global $wpdb, $dm_cookie_style_printed, $dm_logout, $dm_authenticated;

			$dm_cookie_style_printed = false;
			$dm_logout = false;
			$dm_authenticated = false;

			$this->db =& $wpdb;

			if(defined('DM_COMPATIBILITY') && DM_COMPATIBILITY == 'yes') {
				if(!empty($this->db->base_prefix)) {
					$this->dmtable = $this->db->base_prefix . 'domain_mapping';
				} else {
					$this->dmtable = $this->db->prefix . 'domain_mapping';
				}
			} else {
				if(!empty($this->db->base_prefix)) {
					$this->dmtable = $this->db->base_prefix . 'domain_map';
				} else {
					$this->dmtable = $this->db->prefix . 'domain_map';
				}
			}

			$version = get_site_option('domainmapping_version', false);
			if($version === false || $version < $this->build) {
				update_site_option('domainmapping_version', $this->build);
				$this->install( $version );
			}

			// Set up the plugin
			add_action( 'init', array(&$this, 'setup_plugin'));
			// Add any header css or js that we need for the admin page
			add_action( 'load-tools_page_domainmapping', array(&$this, 'add_admin_header'));
			// Add any header css or js for the network side
			add_action('load-settings_page_domainmapping_options', array(&$this, 'add_network_admin_header'));
			// Translate the plugin
			add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

			// Add in the cross domain logins
			add_action( 'init', array(&$this, 'build_stylesheet_for_cookie'));

			// Add in column header to the Site table
			add_filter( 'wpmu_blogs_columns', array(&$this, 'add_site_column_header') );
			// Add in the column data to the Sites table
			add_action( 'manage_sites_custom_column', array(&$this, 'add_sites_column_data'), 1, 2 );

			add_filter( 'allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10);

			add_action( 'login_head', array(&$this, 'build_logout_cookie') );

			// Add in the filters for domain mapping early on to get any information covered before the init action is hit
			$this->add_domain_mapping_filters();
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

		function install( $version ) {
			// Just the single table creating function for now - will get more complex later
			$this->db->query( "CREATE TABLE IF NOT EXISTS `{$this->dmtable}` (
				`id` bigint(20) NOT NULL auto_increment,
				`blog_id` bigint(20) NOT NULL,
				`domain` varchar(255) NOT NULL,
				`active` tinyint(4) default '1',
				PRIMARY KEY  (`id`),
				KEY `blog_id` (`blog_id`,`domain`,`active`)
			);" );

		}

		function add_admin_header() {

		}

		function add_network_admin_page() {
			add_submenu_page('settings.php', __('Domain Mapping','domainmap'), __('Domain Mapping','domainmap'), 'manage_options', "domainmapping_options", array(&$this,'handle_options_page'));
		}

		function add_site_admin_page() {
			add_management_page( __('Domain Mapping', 'domainmap'), __('Domain Mapping', 'domainmap'), 'manage_options', 'domainmapping', array(&$this, 'handle_domain_page') );
		}

		/** Sites columns functions **/

		function add_site_column_header( $columns ) {

			$first_array = array_splice ($columns, 0, 2);
			$columns = array_merge ($first_array, array('domainmap' => __('Mapped Domain', 'domainmap')), $columns);

			return $columns;
		}

		function build_domain_mapping_cache() {

			global $current_site;

			if(empty($this->mappings)) {

				$mappings = $this->db->get_results( "SELECT blog_id, domain FROM {$this->dmtable} /* domain mapping */" );
				foreach($mappings as $map) {
					if($current_site->path == '/') {
						$this->mappings[$map->blog_id][] = "<a href='http://" . $map->domain . $current_site->path . "'>" . $map->domain . "</a>";
					} else {
						$this->mappings[$map->blog_id][] = "<a href='http://" . $map->domain . $current_site->path . "'>" . $map->domain . $current_site->path . "</a>";
					}
				}

			}

		}

		function add_sites_column_data( $column, $blog_id ) {

			$this->build_domain_mapping_cache();

			if ( $column == 'domainmap' ) {
				if(isset($this->mappings[$blog_id])) {
					echo implode("<br/>", $this->mappings[$blog_id]);
				}
			}
		}



		function shibboleth_session_initiator_url($initiator_url) {
			return $initiator_url;
		}

		function domain_mapping_login_url($login_url, $redirect='') {

			switch($this->options['map_logindomain']) {
				case 'user':
					break;
				case 'mapped':
					break;
				case 'original':
					// Get the mapped url using our filter
					$mapped_url = site_url('/');
					// remove the http and https parts of the url
					$mapped_url = str_replace(array('https://', 'http://'), '', $mapped_url);
					// remove the filter we added to swap the url
					remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
					// get the original url now with our filter removed
					$url = trailingslashit( get_option('siteurl') );
					// again remove the http and https parts of the url
					$url = str_replace(array('https://', 'http://'), '', $url);
					// replace the mapped url with the original one
					$login_url = str_replace($mapped_url, $url, $login_url);
					// put our filter back in place
					add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );

					break;
			}

			return $login_url;
		}

		function domain_mapping_admin_url($admin_url, $path = '/', $_blog_id = false) {
			global $blog_id;

			if (!$_blog_id) {
				$_blog_id = $blog_id;
			}

			switch($this->options['map_admindomain']) {
				case 'user':
					break;
				case 'mapped':
					break;
				case 'original':
					// get the mapped url using our filter
					$mapped_url = site_url('/');
					// remove the http and https parts of the url
					$mapped_url = str_replace(array('https://', 'http://'), '', $mapped_url);
					// remove the filter we added to swap the url
					remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
					// get the original url now with our filter removed
					$url = trailingslashit( get_option('siteurl') );
					// remove the http and https parts of the original url
					$url = str_replace(array('https://', 'http://'), '', $url);
					// swap the mapped url with the original one
					$admin_url = str_replace($mapped_url, $url, $admin_url);
					// put our filter back in place
					add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );

					break;
			}

			return $admin_url;
		}

		function domain_mapping_mappedurl( $setting ) {
			global $current_blog, $current_site, $mapped_id;

			// To reduce the number of database queries, save the results the first time we encounter each blog ID.
			static $return_url = array();

			if ( !isset( $return_url[ $this->db->blogid ] ) ) {
				$s = $this->db->suppress_errors();

				$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );

				if ( empty( $domain ) ) {
					$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
				}

				$this->db->suppress_errors( $s );
				$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
				if ( !empty($domain) ) {
					$return_url[ $this->db->blogid ] = untrailingslashit( $protocol . $domain . $current_site->path );
					$setting = $return_url[ $this->db->blogid ];
				} else {
					$return_url[ $this->db->blogid ] = false;
				}
			} elseif ( $return_url[ $this->db->blogid ] !== false) {
				$setting = $return_url[ $this->db->blogid ];
			}

			return $setting;
		}

		function add_domain_mapping_filters() {

			if ( defined( 'DOMAIN_MAPPING' ) ) {
				// replace the siteurl with the mapped domain
				add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
				// replace the hom url with the mapped url
				add_filter( 'pre_option_home', array(&$this, 'domain_mapping_mappedurl') );
				// filter the content with any original urls and change them to the mapped urls
				add_filter( 'the_content', array(&$this, 'domain_mapping_post_content') );
				// Jump in just before header output to change base_url - until a neater method can be found
				add_filter( 'print_head_scripts', array(&$this, 'reset_script_url'), 1, 1);

				add_filter( 'home_url', array(&$this, 'swap_mapped_url'), 10, 4);
				add_filter( 'site_url', array(&$this, 'swap_mapped_url'), 10, 4);
				add_filter( 'includes_url', array(&$this, 'swap_mapped_url'), 10, 2);
				add_filter( 'content_url', array(&$this, 'swap_mapped_url'), 10, 2);
				add_filter( 'plugins_url', array(&$this, 'swap_mapped_url'), 10, 3);

				add_filter( 'wp_redirect', array(&$this, 'wp_redirect'), 999, 2 );

				add_filter('authenticate', array(&$this, 'authenticate'), 999, 3);

				add_filter( 'login_url', array(&$this, 'domain_mapping_login_url'), 2, 100 );
	            add_filter( 'logout_url', array(&$this, 'domain_mapping_login_url'), 2, 100 );
	            add_filter( 'admin_url', array(&$this, 'domain_mapping_admin_url'), 3, 100 );

				add_filter( 'theme_root_uri', array(&$this, 'domain_mapping_post_content'), 1 );
				add_filter( 'stylesheet_uri', array(&$this, 'domain_mapping_post_content'), 1 );
				add_filter( 'stylesheet_directory', array(&$this, 'domain_mapping_post_content'), 1 );
				add_filter( 'stylesheet_directory_uri', array(&$this, 'domain_mapping_post_content'), 1 );
				add_filter( 'template_directory', array(&$this, 'domain_mapping_post_content'), 1 );
				add_filter( 'template_directory_uri', array(&$this, 'domain_mapping_post_content'), 1 );
			} else {
				// We are assuming that we are on the original domain - so if we check if we are in the admin area, we need to only map those links that
				// point to the front end of the site
				if(is_admin()) {
					// replace the hom url with the mapped url
					add_filter( 'pre_option_home', array(&$this, 'domain_mapping_mappedurl') );
					// filter the content with any original urls and change them to the mapped urls
					add_filter( 'the_content', array(&$this, 'domain_mapping_post_content') );
					add_filter( 'home_url', array(&$this, 'swap_mapped_url'), 10, 4);
					add_filter( 'wp_redirect', array(&$this, 'wp_redirect'), 999, 2 );
					add_filter( 'authenticate', array(&$this, 'authenticate'), 999, 3);
				}
			}

		}

		function setup_plugin() {

			$this->options = get_site_option('domain_mapping', array());
			if(empty($this->options)) {
				$this->options['map_ipaddress'] = get_site_option('map_ipaddress');
				$this->options['map_supporteronly'] = get_site_option('map_supporteronly', '0');
				$this->options['map_admindomain'] = get_site_option('map_admindomain', 'user');
				$this->options['map_logindomain'] = get_site_option('map_logindomain', 'user');

				update_site_option('domain_mapping', $this->options);
			}

			if (is_admin()) {
				// We are in the admin area, so check for the redirects here
				switch($this->options['map_admindomain']) {
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
					switch($this->options['map_logindomain']) {
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

			// Add the network admin settings
			add_action( 'network_admin_menu', array(&$this, 'add_network_admin_page') );

			if(function_exists('is_pro_site') && $this->options['map_supporteronly'] == '1') {
				// The supporter function exists and we are limiting domain mapping to supporters

				if(is_pro_site()) {
					// Add the management page
					add_action( 'admin_menu', array(&$this, 'add_site_admin_page') );

					add_action('wp_logout', array(&$this, 'wp_logout'), 10);
					add_action( 'admin_head', array(&$this, 'build_cookie') );
					add_action( 'template_redirect', array(&$this, 'redirect_to_mapped_domain') );
				}

			} else {
				// Add the management page

				add_action( 'admin_menu', array(&$this, 'add_site_admin_page') );

				add_action('wp_logout', array(&$this, 'wp_logout'), 10);
				add_action( 'admin_head', array(&$this, 'build_cookie') );
				add_action( 'template_redirect', array(&$this, 'redirect_to_mapped_domain') );
			}

			add_action( 'delete_blog', array(&$this, 'delete_blog_domain_mapping'), 1, 2 );
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
				define( 'DONOTCACHEPAGE', 1 ); // don't let wp-super-cache cache this page.
				header("HTTP/1.1 301 Moved Permanently", true, 301);
				header("Location: {$location}", true, 301);
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
				// @ob_flush();
				exit();
			}

			if ($dm_logout) {
				define( 'DONOTCACHEPAGE', 1 ); // don't let wp-super-cache cache this page.
				header("HTTP/1.1 301 Moved Permanently", true, 301);
				header("Location: {$location}", true, 301);
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
						<!-- Hej då -->
					</body>
				</html>
				<?php
				// @ob_flush();
				exit();
			}

			return $location;
		}

		// Cookie functions
		function build_logout_cookie() {
			if(isset($_GET['loggedout'])) {
				// Log out CSS
				$this->build_cookie('logout');
			}
		}

		function build_cookie($action = 'login', $user = false, $redirect_to = false) {
			global $blog_id, $current_site, $dm_cookie_style_printed, $current_blog, $dm_logout, $dm_csc_building_urls, $user;

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
			$domains = $this->db->get_results( "SELECT domain FROM {$this->dmtable} WHERE blog_id = '{$blog_id}' ORDER BY id /* domain mapping */", ARRAY_A);
			if($domains && is_array($domains)) {
				foreach ($domains as $domain) {
					if (!isset($urls[$domain['domain']]))
						$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
				}
			}

			// We are redirecting, lets pack some cookies for the journey. Nom Nom Nom
			if ($redirect_to) {
				$redirect_url = parse_url($redirect_to);

				$domain = $this->db->get_row( "SELECT blog_id, domain FROM {$this->dmtable} WHERE domain = '{$redirect_url['host']}' OR domain LIKE '{$redirect_url['host']}/%' ORDER BY id LIMIT 1 /* domain mapping */", ARRAY_A);
				if ($domain) {
					// redirect to unmapped site
					$addom = get_site_option( 'map_admindomain', 'user' );
					if (!isset($urls[$domain['domain']]))
						$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';

					// Other mapped sites
					$domains = $this->db->get_results( "SELECT domain FROM {$this->dmtable} WHERE blog_id = '{$domain->blog_id}' ORDER BY id /* domain mapping */", ARRAY_A);
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
						$domains = $this->db->get_results( "SELECT domain FROM {$this->dmtable} WHERE blog_id = '{$domain->blog_id}' ORDER BY id /* domain mapping */", ARRAY_A);
						if($domains && is_array($domains)) {
							foreach ($domains as $domain) {
								if (!isset($urls[$domain['domain']]))
									$urls[$domain['domain']] = 'http://' . $domain['domain'] . '/';
							}
						}

						// redirect to mapped site
						$domain = $this->db->get_row( "SELECT blog_id, domain FROM {$this->dmtable} WHERE blog_id = '{$domains->blog_id}' LIMIT 1 /* domain mapping */", ARRAY_A);
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
				if (!$user) {
					$user = wp_get_current_user();
				}

				if(!is_wp_error( $user )) {
					$key = get_user_meta($user->ID, 'cross_domain', true);
					if($key == 'none') $key = array();
					foreach ($urls as $url) {
						$parsed_url = parse_url($url);

						if (!isset($parsed_url['host']) || empty($parsed_url['host'])) {
							continue;
						}

						$hash = md5( AUTH_KEY . microtime() . 'COOKIEMONSTER' . $url );

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

			$bid = $this->db->get_var( "SELECT blog_id FROM {$this->dmtable} WHERE domain = '{$redirect_url['host']}' ORDER BY id LIMIT 1 /* domain mapping */");
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
							<!-- Flytta på -->
						</body>
					</html>
					<?php
				}
				exit();
			}
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

		function add_network_admin_header() {

			if(isset($_POST['action']) && $_POST['action'] == 'updateoptions') {
				check_admin_referer('update-dmoptions');

				// Update the domain mapping settings
				$this->options = get_site_option('domain_mapping', array());

				$this->options['map_ipaddress'] = $_POST['map_ipaddress'];
				$this->options['map_supporteronly'] = (isset($_POST['map_supporteronly'])) ? $_POST['map_supporteronly'] : '';
				$this->options['map_admindomain'] = $_POST['map_admindomain'];
				$this->options['map_logindomain'] = $_POST['map_logindomain'];

				update_site_option('domain_mapping', $this->options);

				wp_safe_redirect( add_query_arg( array( 'msg' => 1 ), wp_get_referer() ) );
				exit;
			}

		}

		function handle_options_page() {

			$messages = array();
			$messages[1] = __('Options updated.','domainmap');

			?>
			<div class="wrap">
			<?php screen_icon('ms-admin'); ?>
			<h2><?php _e('Domain mapping Options', 'domainmap') ?>
			</h2>

				<?php
				if ( isset($_GET['msg']) ) {
					echo '<div id="message" class="updated fade"><p>' . $messages[(int) $_GET['msg']] . '</p></div>';
					$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
				}
				?>

			<form action="" method="post" id="">

			<?php
			wp_nonce_field('update-dmoptions');
			echo '<h3>' . __( 'Domain mapping Configuration', 'domainmap' ) . '</h3>';

			if ( !file_exists( ABSPATH . '/wp-content/sunrise.php' ) ) {
				echo "<p><strong>" . __("Please copy the sunrise.php to ", 'domainmap') . ABSPATH . __("/wp-content/sunrise.php and uncomment the SUNRISE setting in the ", 'domainmap') . ABSPATH . __("wp-config.php file", 'domainmap') . "</strong></p>";
			}

			if ( !defined( 'SUNRISE' ) ) {
				echo "<p><strong>" . __("Please uncomment the line <em>//define( 'SUNRISE', 'on' );</em> in the ", 'domainmap') . ABSPATH . __("wp-config.php file.", 'domainmap') . "</strong></p>";
			}

			echo "<p>" . __( "Enter the IP address users need to point their DNS A records at. If you don't know what it is, ping this blog to get the IP address.", 'domainmap' ) . "</p>";
			echo "<p>" . __( "If you have more than one IP address, separate them with a comma. This message is displayed on the Domain mapping page for your users.", 'domainmap' ) . "</p>";
			_e( "Server IP Address: ", 'domainmap' );
			echo "<input type='text' name='map_ipaddress' value='" . $this->options['map_ipaddress'] . "' />";

			if(function_exists('is_pro_site')) {
				echo '<p>' . __('Make this functionality only available to Pro Sites', 'domainmap') . '</p>';
				_e("Pro Sites Only: ", 'domainmap');
				?>
				<select name='map_supporteronly'>
					<option value='0' <?php selected('0', $this->options['map_supporteronly']); ?>><?php _e('No', 'domainmap'); ?></option>
					<option value='1' <?php selected('1', $this->options['map_supporteronly']); ?>><?php _e('Yes', 'domainmap'); ?></option>
				</select>
				<?php
			}

			echo '<h4>' . __( 'Administration mapping', 'domainmap' ) . '</h4>';

			echo "<p>" . __( "The settings below allow you to control how the domain mapping plugin operates with the administration area.", 'domainmap' ) . "</p>";

			echo '<p>';
			echo __('The domain used for the administration area should be the', 'domainmap') . '&nbsp;';
			?>
			<select name='map_admindomain'>
				<option value='user' <?php selected('user', $this->options['map_admindomain']); ?>><?php _e('domain entered by the user', 'domainmap'); ?></option>
				<option value='mapped' <?php selected('mapped', $this->options['map_admindomain']); ?>><?php _e('mapped domain', 'domainmap'); ?></option>
				<option value='original' <?php selected('original', $this->options['map_admindomain']); ?>><?php _e('original domain', 'domainmap'); ?></option>
			</select>
			<?php
			echo '</p>';

			echo '<h4>' . __( 'Login mapping', 'domainmap' ) . '</h4>';

			echo "<p>" . __( "The settings below allow you to control how the domain mapping plugin operates with the login area.", 'domainmap' ) . "</p>";

			$logdom = get_site_option( 'map_logindomain', 'user' );
			echo '<p>';
			echo __('The domain used for the login area should be the', 'domainmap') . '&nbsp;';
			?>
			<select name='map_logindomain'>
				<option value='user' <?php selected('user', $this->options['map_logindomain']); ?>><?php _e('domain entered by the user', 'domainmap'); ?></option>
				<option value='mapped' <?php selected('mapped', $this->options['map_logindomain']); ?>><?php _e('mapped domain', 'domainmap'); ?></option>
				<option value='original' <?php selected('original', $this->options['map_logindomain']); ?>><?php _e('original domain', 'domainmap'); ?></option>
			</select>
			<?php
			echo '</p>';

			echo '<h4>' . __( 'Domain Mapping Table', 'domainmap' ) . '</h4>';



			if( $this->check_for_table() ) {
				echo "<p>" . __( "The domain mapping table is called <strong>", 'domainmap' ) . $this->dmtable . __('</strong> and exists in your database.','domainmap') . "</p>";
			} else {
				echo "<p>" . __( "The domain mapping table should be called <strong>", 'domainmap' ) . $this->dmtable . __('</strong> but does not seem to exist in your database and cannot be created.','domainmap') . "</p>";
				echo "<p>" . __( "To create the database table you need to run the following SQL.",'domainmap') . "</p>";
				echo "<textarea class='code' rows='10' cols='100' readonly='readonly'>";
				echo "CREATE TABLE IF NOT EXISTS `{$this->dmtable}` (
`id` bigint(20) NOT NULL auto_increment,
`blog_id` bigint(20) NOT NULL,
`domain` varchar(255) NOT NULL,
`active` tinyint(4) default '1',
PRIMARY KEY  (`id`),
KEY `blog_id` (`blog_id`,`domain`,`active`)
);";
				echo "</textarea>";
			}

			?>
				<input type='hidden' name='action' value='updateoptions' />
				<p class="submit"><input type="submit" value="<?php _e('Save Changes','domainmap'); ?>" class="button-primary" id="submit" name="submit"></p>
				</form>
				</div>
			<?php
		}

		function check_for_table( $trytocreate = true ) {

			$sql = "SHOW TABLES LIKE '{$this->dmtable}'";

			$table = $this->db->get_var( $sql );
			if( empty( $table ) ) {
				// We don't have the table so we should check if we should create it.
				if($trytocreate) {
					$this->db->query( "CREATE TABLE IF NOT EXISTS `{$this->dmtable}` (
						`id` bigint(20) NOT NULL auto_increment,
						`blog_id` bigint(20) NOT NULL,
						`domain` varchar(255) NOT NULL,
						`active` tinyint(4) default '1',
						PRIMARY KEY  (`id`),
						KEY `blog_id` (`blog_id`,`domain`,`active`)
					);" );

					// Do another check to see if it was created
					$sql = "SHOW TABLES LIKE '{$this->dmtable}'";
					$table = $this->db->get_var( $sql );
					if( empty( $table ) ) {
						return false;
					} else {
						return true;
					}
				}
			} else {
				return true;
			}

			return true;

		}

		function handle_domain_page() {

			global $current_site;

			if ( !empty( $_POST[ 'action' ] ) ) {
				$domain = $this->db->escape( preg_replace( "/^www\./", "", $_POST[ 'domain' ] ) );
				check_admin_referer( 'domain_mapping' );
				switch( $_POST[ 'action' ] ) {
					case "add":
						if( null == $this->db->get_row( $this->db->prepare("SELECT blog_id FROM {$this->db->blogs} WHERE domain = %s AND path = '/' /* domain mapping */", strtolower($domain)) ) && null == $this->db->get_row( $this->db->prepare("SELECT blog_id FROM {$this->dmtable} WHERE domain = %s /* domain mapping */", strtolower($domain) ) ) ) {
							$this->db->query( $this->db->prepare( "INSERT INTO {$this->dmtable} ( `id` , `blog_id` , `domain` , `active` ) VALUES ( NULL, %d, %s, '1') /* domain mapping */", $this->db->blogid, strtolower($domain)) );
							// fire the action when a new domain is added
							do_action( 'domainmapping_added_domain', strtolower($domain), $this->db->blogid );
						}
					break;
					case "delete":
						$this->db->query( $this->db->prepare("DELETE FROM {$this->dmtable} WHERE domain = %s /* domain mapping */", strtolower($domain) ) );
						// fire the action when a domain is removed
						do_action( 'domainmapping_deleted_domain', strtolower($domain), $this->db->blogid );
					break;
				}
			}

			//testing

			echo "<div class='wrap'><div class='icon32' id='icon-tools'><br/></div><h2>" . __('Domain Mapping', 'domainmap') . "</h2>";

			echo "<p>" . __( 'If your domain name includes a sub-domain such as "blog" then you can add a CNAME for that hostname in your DNS pointing at this blog URL.', 'domainmap' ) . "</p>";
			$map_ipaddress = $this->options['map_ipaddress'];
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
			$domains = $this->db->get_results( $this->db->prepare("SELECT * FROM {$this->dmtable} WHERE blog_id = %d",$this->db->blogid) );
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

		function swap_mapped_url($url, $path = '', $plugin = false, $bid = null) {

			// This function swaps the url to the mapped one

			global $current_blog, $current_site, $mapped_id, $current_blog;

			if ($plugin == 'relative') {
				return "{$current_blog->path}{$path}";
			}

			if ($plugin == 'login_post' || $plugin == 'login') {
				return $this->domain_mapping_login_url($url);
			}

			if ($plugin == 'admin') {
				return $this->domain_mapping_admin_url($url);
			}

			if ( !isset( $this->swapped_url[ $this->db->blogid ] ) ) {

				$s = $this->db->suppress_errors();

				// Get the mapped domain
				$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );
				if ( empty( $newdomain ) ) {
					$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
				}
				// We have to grab the old domain this way because we are filtering the options table and using get_option would return the mapped one
				$olddomain = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name='siteurl' LIMIT %d /* domain mapping */", 1 ) );

				$this->db->suppress_errors( $s );
				$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';

				if ( !empty($newdomain) ) {
					// Get the domain and path we want to swap to
					$innerurl = trailingslashit( $protocol . $newdomain . $current_site->path );
					// replace any occurance of the old domain with the new one
					$newurl = str_replace($olddomain, $innerurl, $url);
					// store the olddomain and the new one in a cache
					$this->swapped_url[ $this->db->blogid ] = array( 'olddomain' => $olddomain, 'newdomain' => $innerurl);
					// get ready to return our new url
					$url = $newurl;
				} else {
					// we can't find a map for this domain so record a false in the cache
					$this->swapped_url[ $this->db->blogid ] = false;
				}
			} elseif ( $this->swapped_url[ $this->db->blogid ] !== false) {
				// get the information from the cache for the old domain
				$olddomain = $this->swapped_url[ $this->db->blogid ]['olddomain'];
				// replace the old domain with the new one and set the url for returning
				$url = str_replace($olddomain, $this->swapped_url[ $this->db->blogid ]['newdomain'], $url);
			}
			return $url;
		}

		function unswap_mapped_url($url, $path = '') {
			global $current_blog, $current_site, $mapped_id;


			if ( !isset( $this->swapped_url[ $this->db->blogid ] ) ) {

				$s = $this->db->suppress_errors();
				// Try to get the mapped domain from the domain table
				$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $this->db->blogid ) );
				if ( empty( $newdomain ) ) {
					$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
				}
				// We have to grab the old domain this way because we are filtering the options table and using get_option would return the mapped one
				$olddomain = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name='siteurl' LIMIT %d /* domain mapping */", 1 ) );

				$this->db->suppress_errors( $s );

				$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';

				if ( !empty($newdomain) ) {
					// Work out the mapped domain
					$innerurl = trailingslashit( $protocol . $newdomain . $current_site->path );
					// swap any mapped domains with the original domain in the passed url
					$newurl = str_replace($innerurl, $olddomain, $url);
					// Cache the information for later use
					$this->swapped_url[ $this->db->blogid ] = array( 'olddomain' => $olddomain, 'newdomain' => $innerurl);
					// Return the new url
					$url = $newurl;
				} else {
					// No mapped domain so set the cache to false
					$this->swapped_url[ $this->db->blogid ] = false;
				}
			} elseif ( $this->swapped_url[ $this->db->blogid ] !== false) {
				// get the information from the cache for the old domain
				$olddomain = $this->swapped_url[ $this->db->blogid ]['olddomain'];
				// replace the new domain with the old one
				$url = str_replace($this->swapped_url[ $this->db->blogid ]['newdomain'], $olddomain, $url);
			}
			// Update the protocal if we need to
			if ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) {
				$url = str_replace('http://', 'https://', $url);
			}
			return $url;
		}

		function domain_mapping_post_content( $post_content ) {

			static $orig_urls = array();
			if ( ! isset( $orig_urls[ $this->db->blogid ] ) ) {
				// remove the filter from the site url so that we can get the original url
				remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
				// get the original url
				$orig_url = get_option( 'siteurl' );
				// switch the url to use the correct http or https
				if ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) {
					$orig_url = str_replace( "http://", "https://", $orig_url );
				} else {
					$orig_url = str_replace( "https://", "http://", $orig_url );
				}
				// store the url in the cache
				$orig_urls[ $this->db->blogid ] = $orig_url;
				// put our filter back in place
				add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
			} else {
				// we have a cached entry so just return that
				$orig_url = $orig_urls[ $this->db->blogid ];
			}
			// Get the new mapped url
			$url = $this->domain_mapping_mappedurl( 'NA' );
			if ( $url == 'NA' ) {
				// If we don't have a mapped url then just return the content unchanged
				return $post_content;
			} else {
				// replace all the original urls with the new ones and then return the content
				return str_replace( trailingslashit($orig_url), trailingslashit($url), $post_content );
			}

		}

		function redirect_to_mapped_domain() {
			global $current_blog, $current_site;

			$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) )  ? 'https://' : 'http://';
			$url = $this->domain_mapping_mappedurl( false );
			if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_site->path ) ) {
				// strip out any subdirectory blog names
				$request = str_replace("/a" . $current_blog->path, "/", "/a" . $_SERVER[ 'REQUEST_URI' ]);
				if($request != $_SERVER[ 'REQUEST_URI' ]) {
					header("HTTP/1.1 301 Moved Permanently", true, 301);
					header( "Location: " . $url . $request, true, 301);
				} else {
					header("HTTP/1.1 301 Moved Permanently", true, 301);
					header( "Location: " . $url . $_SERVER[ 'REQUEST_URI' ], true, 301 );
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
			remove_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
			$url = get_option( 'siteurl' );
			if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_site->path ) ) {
				// strip out any subdirectory blog names
				$request = str_replace("/a" . $current_blog->path, "/", "/a" . $_SERVER[ 'REQUEST_URI' ]);
				if($request != $_SERVER[ 'REQUEST_URI' ]) {
					header("HTTP/1.1 301 Moved Permanently", true, 301);
					header( "Location: " . $url . $request,  true, 301);
				} else {
					header("HTTP/1.1 301 Moved Permanently", true, 301);
					header( "Location: " . $url . $_SERVER[ 'REQUEST_URI' ], true, 301 );
				}
				exit;
			}
			add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );
		}

		function delete_blog_domain_mapping( $blog_id, $drop ) {

			if ( $blog_id && $drop ) {
				$this->db->query( $this->db->prepare( "DELETE FROM {$this->dmtable} WHERE blog_id  = %d /* domain mapping */", $blog_id ) );
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


}


?>