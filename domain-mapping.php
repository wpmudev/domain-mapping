<?php
/*
Plugin Name: Domain Mapping
Plugin URI: https://premium.wpmudev.org/project/domain-mapping/
Description: The ultimate Multisite domain mapping plugin - sync cookies, sell domains with eNom, and integrate with Pro Sites.
Version: 4.1.4.2
Author: WPMU DEV
Author URI: http://premium.wpmudev.org
WDP ID: 99
Network: true
*/

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

// prevent non multisite usage or reloading the plugin, if it has been already loaded
if ( !is_multisite() || class_exists( 'Domainmap_Plugin', false ) ) {
   return;
}

// UnComment out the line below to allow multiple domain mappings per blog
//define('DOMAINMAPPING_ALLOWMULTI', 'yes');

// WPMUDev Dashboard Notices
//load dashboard notice
global $wpmudev_notices;
$wpmudev_notices[] = array( 'id'=> 99,'name'=> 'Domain Mapping', 'screens' => array( 'tools_page_domainmapping', 'settings_page_domainmapping_options-network' ) );
require_once 'extra/wpmudev-dash-notification.php';

// main domain mapping class
require_once 'classes/class.domainmap.php';

/**
 * Automatically loads classes for the plugin. Checks a namespace and loads only
 * approved classes.
 *
 * @since 4.0.0
 *
 * @param string $class The class name to autoload.
 * @return boolean Returns TRUE if the class is located. Otherwise FALSE.
 */
function domainmap_autoloader( $class ) {
	$basedir = dirname( __FILE__ );
	$namespaces = array( 'Domainmap' );
	foreach ( $namespaces as $namespace ) {
		if ( substr( $class, 0, strlen( $namespace ) ) == $namespace ) {
			$filename = $basedir . str_replace( '_', DIRECTORY_SEPARATOR, "_classes_{$class}.php" );
			if ( is_readable( $filename ) ) {
				require $filename;
				return true;
			}
		}
	}

	return false;
}

/**
 * Setups domain mapping constants.
 *
 * @since 4.1.2
 *
 * @global wpdb $wpdb The instance of database connection.
 */
function domainmap_setup_constants() {
	global $wpdb;

	// setup environment
	define( 'DOMAINMAP_BASEFILE', __FILE__ );
	define( 'DOMAINMAP_ABSURL',   plugins_url( '/', __FILE__ ) );
	define( 'DOMAINMAP_ABSPATH',  dirname( __FILE__ ) );

	if ( !defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) ) {
		define( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN', false );
	}

	if ( !defined( 'DOMAINMAPPING_ALLOWMULTI' ) ) {
		define( 'DOMAINMAPPING_ALLOWMULTI', false );
	}

	// setup db tables
	$prefix = isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix;
	define( 'DOMAINMAP_TABLE_MAP',          "{$prefix}domain_mapping" );
	define( 'DOMAINMAP_TABLE_RESELLER_LOG', "{$prefix}domain_mapping_reseller_log" );

	// MultiDB compatibility, register global tables
	if ( defined( 'MULTI_DB_VERSION' ) && function_exists( 'add_global_table' ) ) {
		add_global_table( 'domain_mapping' );
		add_global_table( 'domain_mapping_reseller_log' );
	}
}

/**
 * Instantiates the plugin and setup all modules.
 *
 * @since 4.0.0
 *
 * @global domain_map $dm_map The instance of domain_map class.
 */
function domainmap_launch() {
	global $dm_map;

	domainmap_setup_constants();

	// set up the plugin core class
	$dm_map = new domain_map();

	// instantiate the plugin
	$plugin = Domainmap_Plugin::instance();

	// set general modules
	$plugin->set_module( Domainmap_Module_System::NAME );
	$plugin->set_module( Domainmap_Module_Setup::NAME );
	$plugin->set_module( Domainmap_Module_Mapping::NAME );

	// CDSSO module
	if ( defined( 'SUNRISE' ) && filter_var( SUNRISE, FILTER_VALIDATE_BOOLEAN ) && $plugin->get_option( 'map_crossautologin' ) ) {
		$plugin->set_module( Domainmap_Module_Cdsso::NAME );
	}

	// conditional modules
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		// suppresses errors rendering to prevent unexpected issues
		set_error_handler( '__return_true' );
		set_exception_handler( '__return_true' );

		// set ajax modules
		$plugin->set_module( Domainmap_Module_Ajax_Map::NAME );
		$plugin->set_module( Domainmap_Module_Ajax_Purchase::NAME );
		$plugin->set_module( Domainmap_Module_Ajax_Register::NAME );
	} else {
		if ( is_admin() ) {
			// set admin modules
			$plugin->set_module( Domainmap_Module_Pages::NAME );
			$plugin->set_module( Domainmap_Module_Admin::NAME );
		}
	}
}

// register autoloader function
spl_autoload_register( 'domainmap_autoloader' );

// launch the plugin
domainmap_launch();

/*================== Global Functions =======================*/

/**
 * Retrieves respective site url with original domain for current site checking weather it's an ssl connection
 *
 * Returns the 'site_url' option or unswapped site url if it's and ssl connection with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 4.1.3
 *
 * @uses site_url()
 *
 * @param string $path Optional. Path relative to the site url.
 * @param string $scheme Optional. Scheme to give the site url context. See set_url_scheme().
 * @return string Site url link with optional path appended.
 */
function dm_site_url( $path = '', $scheme = null ){
    $current_site_url = site_url( $path, $scheme );
    return Domainmap_Module_Mapping::unswap_url( $current_site_url, false, (bool) $path );
}

/**
 * Retrieves respective home url with original domain for current site checking weather it's an ssl connection
 *
 * Returns the 'home' option or unswapped home url if it's and ssl connection with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 4.1.3
 *
 * @uses home_url()
 *
 * @param string $path Optional. Path relative to the site url.
 * @param string $scheme Optional. Scheme to give the site url context. See set_url_scheme().
 * @return string Site url link with optional path appended.
 */
function dm_home_url( $path = '', $scheme = null ){
    $current_home_url = home_url( $path, $scheme );
    return Domainmap_Module_Mapping::unswap_url( $current_home_url, false, (bool) $path );
}