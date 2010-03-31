<?php
/*
Plugin Name: VHOST and directory enabled Domain Mapping plugin
Plugin URI: http://premium.wpmudev.org/project/domain-mapping
Description: A domain mapping plugin that can handle sub-directory installs and global logins
Version: 2.1.1
Author: Barry Getty (Incsub)
Author URI: http://caffeinatedb.com
WDP ID: 99
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

class domain_map {

	var $build = 4;

	var $db;

	var $dmt = '';

	var $mappings = array();

	function __construct() {

		global $wpdb;

		$this->db =& $wpdb;

		if(!empty($this->db->dmtable)) {
			$this->dmt = $this->db->dmtable;
		} else {
			$this->dmt = $this->db->base_prefix . 'domain_map';
		}
		// Set up the plugin
		add_action('init', array(&$this, 'setup_plugin'));
		// Add in the cross domain logins
		add_action( 'init', array(&$this, 'build_stylesheet_for_cookie'));

		if(is_admin() || strpos(addslashes($_SERVER["SCRIPT_NAME"]),'/wp-login.php') !== false) {
			// We are in the admin area, so check for the redirects here
			$addom = get_site_option( 'map_admindomain', 'user' );

			switch($addom) {

				case 'user':		break;
				case 'mapped':		$this->redirect_to_mapped_domain();
									break;
				case 'original':	if ( defined( 'DOMAIN_MAPPING' ) ) {
										// put in the code to send me to the original domain
										$this->redirect_to_orig_domain();
									}
									break;


			}

		}

	}


	function domain_map() {
		$this->__construct();
	}

	function setup_plugin() {

		$this->handle_translation();
		// Add the options page
		add_action( 'wpmu_options', array(&$this, 'handle_domain_options'));
		add_action( 'update_wpmu_options', array(&$this, 'update_domain_options'));

		$sup = get_site_option( 'map_supporteronly', '0' );

		if(function_exists('is_supporter') && $sup == '1') {
			// The supporter function exists and we are limiting domain mapping to supporters

			if(is_supporter()) {
				// Add the management page
				add_action( 'admin_menu', array(&$this, 'add_page') );
				if ( defined( 'DOMAIN_MAPPING' ) ) {
					add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
					add_filter( 'pre_option_home', array(&$this, 'domain_mapping_home') );
					add_filter( 'the_content', array(&$this, 'domain_mapping_post_content') );

					add_filter( 'plugins_url', array(&$this, 'swap_mapped_url'), 10, 3);
					add_filter( 'content_url', array(&$this, 'swap_mapped_url'), 10, 2);
					add_filter( 'site_url', array(&$this, 'swap_mapped_url'), 10, 3);

					// Jump in just before header output to change base_url - until a neater method can be found
					add_filter( 'print_head_scripts', array(&$this, 'reset_script_url'), 1, 1);
				}

				// Cross domain cookies
				add_action( 'admin_head', array(&$this, 'build_cookie') );
				add_action( 'login_head', array(&$this, 'build_logout_cookie') );

				add_action( 'template_redirect', array(&$this, 'redirect_to_mapped_domain') );
			}

			add_action( 'delete_blog', array(&$this, 'delete_blog_domain_mapping'), 1, 2 );
			add_filter( 'wpmu_blogs_columns', array(&$this, 'add_domain_mapping_column') );
			add_action( 'manage_blogs_custom_column', array(&$this, 'add_domain_mapping_field'), 1, 3 );

		} else {
			// Add the management page
			add_action( 'admin_menu', array(&$this, 'add_page') );

			if ( defined( 'DOMAIN_MAPPING' ) ) {
				add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
				add_filter( 'pre_option_home', array(&$this, 'domain_mapping_home') );
				add_filter( 'the_content', array(&$this, 'domain_mapping_post_content') );

				add_filter( 'plugins_url', array(&$this, 'swap_mapped_url'), 10, 3);
				add_filter( 'content_url', array(&$this, 'swap_mapped_url'), 10, 2);
				add_filter( 'site_url', array(&$this, 'swap_mapped_url'), 10, 3);

				// Jump in just before header output to change base_url - until a neater method can be found
				add_filter( 'print_head_scripts', array(&$this, 'reset_script_url'), 1, 1);
			}

			// Cross domain cookies
			add_action( 'admin_head', array(&$this, 'build_cookie') );
			add_action( 'login_head', array(&$this, 'build_logout_cookie') );

			add_action( 'template_redirect', array(&$this, 'redirect_to_mapped_domain') );

			add_action( 'delete_blog', array(&$this, 'delete_blog_domain_mapping'), 1, 2 );

			add_filter( 'wpmu_blogs_columns', array(&$this, 'add_domain_mapping_column') );

			add_action( 'manage_blogs_custom_column', array(&$this, 'add_domain_mapping_field'), 1, 3 );

		}

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

	function build_cookie($action = 'login') {

		global $blog_id, $current_site;

		if($action == '') $action = 'login';

		$url = false;
		if ( defined( 'DOMAIN_MAPPING' ) ) {
			$domains = $this->db->get_row( "SELECT domain, path FROM {$this->db->blogs} WHERE blog_id = '{$blog_id}' LIMIT 1 /* domain mapping */");
			$url = 'http://' . $domains->domain . $domains->path;
		} else {
			$domain = $this->db->get_var( "SELECT domain FROM {$this->dmt} WHERE blog_id = '{$blog_id}' ORDER BY id LIMIT 1 /* domain mapping */");
			if($domain) {
				$url = 'http://' . $domain . '/';
			}
		}

		if($url) {
			$key = get_option('cross_domain', 'none');
			if($key == 'none') $key = array();

			$hash = md5( AUTH_KEY . time() . 'COOKIEMONSTER');

			$user = wp_get_current_user();

			$key[$hash] = array ( 	"domain" 	=> $url,
									"hash"		=> $hash,
									"user_id"	=> $user->ID,
									"action"	=> $action
									);

			update_option('cross_domain', $key);

			echo '<link rel="stylesheet" href="' . $url . $hash . '.css?build=' . date("Ymd", strtotime('-24 days') ) . '" type="text/css" media="screen" />';
		}


	}

	function build_stylesheet_for_cookie() {

		if( isset($_GET['build']) && addslashes($_GET['build']) == date("Ymd", strtotime('-24 days') ) ) {
			// We have a stylesheet with a build and a matching date - so grab the hash
			$url = parse_url($_SERVER[ 'REQUEST_URI' ], PHP_URL_PATH);
			$hash = str_replace('.css','', basename($url));

			$key = get_option('cross_domain');

			if(array_key_exists($hash, (array) $key)) {
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

						case 'logout':	wp_clear_auth_cookie();
										break;
						default:
										break;
					}
				}

		        header("Content-type: text/css");
				echo "/* Sometimes me think what is love, and then me think love is what last cookie is for. Me give up the last cookie for you. */";
				define( 'DONOTCACHEPAGE', 1 ); // don't let wp-super-cache cache this page.
				unset($key[$hash]);
				update_option('cross_domain', (array) $key);
				die();
			}
		}
	}


	function add_page() {
		add_management_page( __('Domain Mapping', 'domainmap'), __('Domain Mapping', 'domainmap'), 8, 'domainmapping', array(&$this, 'handle_domain_page') );

	}

	function update_domain_options() {

		update_site_option('map_ipaddress', $_POST['map_ipaddress']);
		update_site_option('map_supporteronly', $_POST['map_supporteronly']);

		update_site_option('map_admindomain', $_POST['map_admindomain']);

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

		if(function_exists('is_supporter')) {
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

		if ( is_site_admin() ) {
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
					if( null == $this->db->get_row( $this->db->prepare("SELECT blog_id FROM {$this->db->blogs} WHERE domain = %s /* domain mapping */", strtolower($domain)) ) && null == $this->db->get_row( $this->db->prepare("SELECT blog_id FROM {$this->dmt} WHERE domain = %s /* domain mapping */", strtolower($domain) ) ) ) {
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
		<?php
		}
		?>
		</tbody>
		</table>
		<?php

	}

	function reset_script_url($return) {

		global $wp_scripts;

		$wp_scripts->base_url = site_url();

		return $return;
	}


	function swap_mapped_url($url, $path, $plugin = false) {
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
			$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
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
			$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
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
			$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
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
			$orig_urls[ $this->db->blogid ] = $orig_url;
			add_filter( 'pre_option_siteurl', array(&$this, 'domain_mapping_siteurl') );
		} else {
			$orig_url = $orig_urls[ $this->db->blogid ];
		}
		$url = $this->domain_mapping_siteurl( 'NA' );
		if ( $url == 'NA' )
			return $post_content;
		return str_replace( $orig_url, $url, $post_content );
	}

	function redirect_to_mapped_domain() {
		global $current_blog, $current_site;

		$protocol = ( 'on' == strtolower($_SERVER['HTTPS']) ) ? 'https://' : 'http://';
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

		$protocol = ( 'on' == strtolower($_SERVER['HTTPS']) ) ? 'https://' : 'http://';
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
				$this->mappings[$map->blog_id][] = "<a href='http://" . $map->domain . $current_site->path . "'>" . $map->domain . $current_site->path . "</a>";
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


}

$dm_map =& new domain_map();


?>