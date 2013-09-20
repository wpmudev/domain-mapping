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
 * The module responsible for system tasks.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_System extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->_check_sunrise();
		$this->_upgrade();
	}

	/**
	 * Checks sunrise.php file availability.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _check_sunrise() {
		if ( defined( 'SUNRISE' ) ) {
			$dest = WP_CONTENT_DIR . '/sunrise.php';
			$source = DOMAINMAP_ABSPATH . '/sunrise.php';
			if ( !file_exists( $dest ) && is_writable( WP_CONTENT_DIR ) && is_readable( $source ) ) {
				@copy( $source, $dest );
			}
		}
	}

	/**
	 * Executes an array of sql queries.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param array $queries The arrayof queries to execute.
	 */
	private function _exec_queries( array $queries ) {
		foreach ( $queries as $query ) {
			$this->_wpdb->query( $query );
		}
	}

	/**
	 * Generates CREATE TABLE sql script for provided table name and columns list.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $name The name of a table.
	 * @param array $columns The array  of columns, indexes, constraints.
	 * @return string The sql script for table creation.
	 */
	private function _create_table( $name, array $columns ) {
		$charset = '';
		if ( !empty( $this->_wpdb->charset ) ) {
			$charset = " DEFAULT CHARACTER SET " . $this->_wpdb->charset;
		}

		$collate = '';
		if ( !empty( $this->_wpdb->collate ) ) {
			$collate .= " COLLATE " . $this->_wpdb->collate;
		}

		return sprintf( 'CREATE TABLE IF NOT EXISTS `%s` (%s)%s%s', $name, implode( ', ', $columns ), $charset, $collate );
	}

	/**
	 * Performs upgrade plugin evnironment to up to date version.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _upgrade() {
		$filter = 'domainmap_upgrade';
		$option = 'domainmap_version';

		// fetch current database version
		$db_version = get_site_option( $option );
		if ( $db_version === false || !preg_match( '/^\d+(\.\d+){2,3}$/', $db_version ) ) {
			$db_version = '0.0.0';
			update_option( $option, $db_version );
		}

		// check if current version is equal to database version, then there is nothing to upgrade
		if ( version_compare( $db_version, Domainmap_Plugin::VERSION, '=' ) ) {
			return;
		}

		// add upgrade functions
		$this->_add_filter( $filter, 'upgrade_to_4_0_0', 1 );

		// upgrade database version to current plugin version
		update_site_option( $option, apply_filters( $filter, $db_version ) );
	}

	/**
	 * Upgrades plugin environment to 4.0.0 version
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $current_version The current plugin version.
	 * @return string The upgraded version.
	 */
	public function upgrade_to_4_0_0( $current_version ) {
		$this_version = '4.0.0';
		if ( version_compare( $current_version, $this_version, '>=' ) ) {
			return $current_version;
		}

		$this->_exec_queries( array(
			$this->_create_table( DOMAINMAP_TABLE_MAP, array(
				'`id` BIGINT NOT NULL AUTO_INCREMENT',
				'`blog_id` BIGINT NOT NULL',
				'`domain` VARCHAR(255) NOT NULL',
				'`active` TINYINT DEFAULT 1',
				'PRIMARY KEY (`id`)',
				'KEY `blog_id` (`blog_id`, `domain`, `active`)',
			) ),

			$this->_create_table( DOMAINMAP_TABLE_RESELLER_LOG, array(
				'`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
				'`user_id` BIGINT UNSIGNED NOT NULL',
				'`provider` VARCHAR(255) NOT NULL',
				'`requested_at` DATETIME NOT NULL',
				'`type` TINYINT UNSIGNED NOT NULL',
				'`valid` TINYINT UNSIGNED NOT NULL',
				'`errors` TEXT NOT NULL',
				'`response` TEXT NOT NULL',
				'PRIMARY KEY (`id`)',
				'KEY `idx_reseller_log` (`provider`, `valid`)',
			) ),
		) );

		return $this_version;
	}

}