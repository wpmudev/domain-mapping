<?php
if ( !class_exists( 'domain_map', false ) ) :
	class domain_map {

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

			$this->db = $wpdb;
			$this->dmtable = DOMAINMAP_TABLE_MAP;

			// Set up the plugin
			add_action( 'init', array( $this, 'setup_plugin' ) );
			// Add in the cross domain logins
			add_action( 'init', array(&$this, 'build_stylesheet_for_cookie'));

			// Add any header css or js that we need for the admin page
			add_action( 'load-tools_page_domainmapping', array(&$this, 'add_admin_header'));

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

		function add_admin_header() {

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
					// put our filter back in place
					add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );

					// replace the mapped url with the original one
					$login_url = str_replace($mapped_url, $url, $login_url);

					/*
					if( !isset($_POST['postpass']) ) {

					} else {
						// keep the mapped url as we need to just process and return
						$login_url = str_replace($url, $mapped_url, $login_url);
					}
					*/

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
					// put our filter back in place
					add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_mappedurl') );

					// Check if we are looking at the admin-ajax.php and if so, we want to leave the domain as mapped
					if( $path != 'admin-ajax.php' ) {
						// swap the mapped url with the original one
						$admin_url = str_replace($mapped_url, $url, $admin_url);
					} else {
						if( !is_admin() ) {
							// swap the original url with the mapped one
							$admin_url = str_replace($url, $mapped_url, $admin_url);
						}
					}

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

				$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", $_SERVER[ 'HTTP_HOST' ], $this->db->blogid ) );

				if ( empty( $domain ) ) {
					$domain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
				}

				$this->db->suppress_errors( $s );
				if( defined('DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN') && DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN == true ) {
					$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
				} else {
					$protocol = 'http://';
				}

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
				// replace the home url with the mapped url
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
			$this->options = Domainmap_Plugin::instance()->get_options();

			if ( is_admin() ) {
				// We are in the admin area, so check for the redirects here
				switch( $this->options['map_admindomain'] ) {
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
				if ( strpos( addslashes( $_SERVER["SCRIPT_NAME"] ), '/wp-login.php' ) !== false ) {
					// We are in the login area, so check for the redirects here
					switch( $this->options['map_logindomain'] ) {
						case 'mapped':
							$this->redirect_to_mapped_domain();
							break;
						case 'original':
							if ( defined( 'DOMAIN_MAPPING' ) && empty( $_POST ) ) {
								// put in the code to send me to the original domain unless we are submitting to the form
								$this->redirect_to_orig_domain();
							}
							break;
					}
				}
			}

			$permitted = true;
			if ( function_exists( 'is_pro_site' ) && !empty( $this->options['map_supporteronly'] ) ) {
				// We have a pro-site option set and the pro-site plugin exists
				$levels = (array)get_site_option( 'psts_levels' );
				if( !is_array( $this->options['map_supporteronly'] ) && !empty( $levels ) && $this->options['map_supporteronly'] == '1' ) {
					$keys = array_keys( $levels );
					$this->options['map_supporteronly'] = array( $keys[0] );
				}

				$permitted = false;
				foreach ( (array)$this->options['map_supporteronly'] as $level ) {
					if( is_pro_site( false, $level ) ) {
						$permitted = true;
					}
				}
			}

			// Add the network admin settings
			add_action( 'delete_blog', array( $this, 'delete_blog_domain_mapping' ), 1, 2 );
			if ( $permitted ) {
				add_action( 'wp_logout', array( $this, 'wp_logout' ), 10 );
				add_action( 'admin_head', array( $this, 'build_cookie' ) );
				add_action( 'template_redirect', array( $this, 'redirect_to_mapped_domain' ) );
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
							$this->build_cookie('login', $dm_authenticated, $location);
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
							$_ssl = is_ssl();
							if ( ( ($_ssl && preg_match('#https://#', $url) > 0) || (!$_ssl && preg_match('#http://#', $url) > 0) ) ) {
								$dm_cookie_style_printed = true;
								echo '<link rel="stylesheet" href="' . $url . $hash . '?action='.$action.'&uid='.$user->ID.'&build=' . date("Ymd", strtotime('-24 days') ) . '" type="text/css" media="screen" />';
							} else if ($_ssl) {
								$url = preg_replace( '#http://#', 'https://', $url );
								echo '<link rel="stylesheet" href="' . $url . $hash . '?action='.$action.'&uid='.$user->ID.'&build=' . date("Ymd", strtotime('-24 days') ) . '" type="text/css" media="screen" />';
							} else {
								$url = preg_replace( '#https://#', 'http://', $url );
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
			if ( isset($redirect_url['host']) && $redirect_url['host'] === $network_home_url['host']) {
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
								case 'login':
									wp_set_auth_cookie($key[$hash]['user_id']);
									break;
							}
						} else {
							// Set the cookies
							switch($key[$hash]['action']) {
								case 'logout':
									wp_clear_auth_cookie();
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
				$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", $_SERVER[ 'HTTP_HOST' ], $this->db->blogid ) );
				if ( empty( $newdomain ) ) {
					$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
				}
				// We have to grab the old domain this way because we are filtering the options table and using get_option would return the mapped one
				$olddomain = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name='siteurl' LIMIT %d /* domain mapping */", 1 ) );

				$this->db->suppress_errors( $s );
				if( defined('DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN') && DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN == true ) {
					$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
				} else {
					$protocol = 'http://';
				}
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
				$olddomain = untrailingslashit( $this->swapped_url[ $this->db->blogid ]['olddomain'] );
				// replace the old domain with the new one and set the url for returning
				$url = str_replace($olddomain, untrailingslashit( $this->swapped_url[ $this->db->blogid ]['newdomain'] ), $url);
			}
			return $url;
		}

		function unswap_mapped_url($url, $path = '') {
			global $current_blog, $current_site, $mapped_id;


			if ( !isset( $this->swapped_url[ $this->db->blogid ] ) ) {

				$s = $this->db->suppress_errors();
				// Try to get the mapped domain from the domain table
				$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1 /* domain mapping */", $_SERVER[ 'HTTP_HOST' ], $this->db->blogid ) );
				if ( empty( $newdomain ) ) {
					$newdomain = $this->db->get_var( $this->db->prepare( "SELECT domain FROM {$this->dmtable} WHERE blog_id = %d /* domain mapping */", $this->db->blogid ) );
				}
				// We have to grab the old domain this way because we are filtering the options table and using get_option would return the mapped one
				$olddomain = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name='siteurl' LIMIT %d /* domain mapping */", 1 ) );

				$this->db->suppress_errors( $s );

				if( defined('DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN') && DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN == true ) {
					$protocol = ( ( isset( $_SERVER[ 'HTTPS' ] ) && 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) || ( isset( $_SERVER[ 'SERVER_PORT' ] ) && '443' == $_SERVER[ 'SERVER_PORT' ] ) ) ? 'https://' : 'http://';
				} else {
					$protocol = 'http://';
				}

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
				$olddomain = untrailingslashit( $this->swapped_url[ $this->db->blogid ]['olddomain'] );
				// replace the new domain with the old one
				$url = str_replace( untrailingslashit( $this->swapped_url[ $this->db->blogid ]['newdomain'] ), $olddomain, $url);
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
		function load_tb_fix() {
			?><script type="text/javascript">
				'tb_pathToImage = "<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>";
				'tb_closeImage = "<?php echo includes_url('js/thickbox/tb-close.png'); ?>";
			</script><?php
		}

	} //end class domain_map
endif;