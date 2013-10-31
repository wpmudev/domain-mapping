<?php
/*
Plugin Name: Domain Mapping plugin
Plugin URI: http://premium.wpmudev.org/project/domain-mapping
Description: A domain mapping plugin that can handle sub-directory installs and global logins
Version: 4.0.3
Author: Incsub
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
	$namespaces = array( 'Domainmap', 'WPMUDEV' );
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
 * Instantiates the plugin and setup all modules.
 *
 * @since 4.0.0
 *
 * @global wpdb $wpdb The instance of database connection.
 * @global domain_map $dm_map The instance of domain_map class.
 */
function domainmap_launch() {
	global $wpdb, $dm_map;

	// setup environment
	define( 'DOMAINMAP_BASEFILE', __FILE__ );
	define( 'DOMAINMAP_ABSURL',   plugins_url( '/', __FILE__ ) );
	define( 'DOMAINMAP_ABSPATH',  dirname( __FILE__ ) );

	if( !defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) ) {
		define( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN', false );
	}

	$prefix = isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix;
	define( 'DOMAINMAP_TABLE_MAP',          "{$prefix}domain_mapping" );
	define( 'DOMAINMAP_TABLE_RESELLER_LOG', "{$prefix}domain_mapping_reseller_log" );

	// MultiDB compatibility, register global tables
	if ( defined( 'MULTI_DB_VERSION' ) && function_exists( 'add_global_table' ) ) {
		add_global_table( 'domain_mapping' );
		add_global_table( 'domain_mapping_reseller_log' );
	}

	// set up the plugin core class
	$dm_map = new domain_map();

	// instantiate the plugin
	$plugin = Domainmap_Plugin::instance();

	// set general modules
	$plugin->set_module( Domainmap_Module_System::NAME );
	$plugin->set_module( Domainmap_Module_Setup::NAME );

	$sunrise = defined( 'SUNRISE' ) && filter_var( SUNRISE, FILTER_VALIDATE_BOOLEAN );
	if ( $sunrise && defined( 'DOMAINMAPPING_USE_CDSSO' ) && $plugin->get_option( 'map_crossautologin' ) ) {
		$plugin->set_module( Domainmap_Module_Cdsso::NAME );
	}

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		// suppresses errors rendering to prevent unexpected issues
		set_error_handler( '__return_true' );
		set_exception_handler( '__return_true' );

		// set ajax modules
		$plugin->set_module( Domainmap_Module_Ajax_Map::NAME );
		$plugin->set_module( Domainmap_Module_Ajax_Purchase::NAME );
	} else {
		if ( is_admin() ) {
			// set admin modules
			$plugin->set_module( Domainmap_Module_Pages::NAME );
			$plugin->set_module( Domainmap_Module_Admin::NAME );
		} else {
			// set front end modules
			$plugin->set_module( Domainmap_Module_Mapping::NAME );
		}
	}
}

// register autoloader function
spl_autoload_register( 'domainmap_autoloader' );

// launch the plugin
domainmap_launch();