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

			$need_update = false;
			$need_update |= !file_exists( $dest );
			$need_update |= !defined( 'DOMAINMAPPING_SUNRISE_VERSION' ) || version_compare( DOMAINMAPPING_SUNRISE_VERSION, Domainmap_Plugin::SUNRISE, '<' );

			if ( $need_update && is_writable( WP_CONTENT_DIR ) && is_readable( $source ) ) {
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
	 * Builds alter table script for provided table.
	 *
	 * @since 4.0.3
	 *
	 * @access private
	 * @param string $name The name of a table.
	 * @param array $alters The array  of alters.
	 * @return string The sql script to alter a table.
	 */
	private function _alter_table( $name, array $alters ) {
		return sprintf( 'ALTER TABLE `%s` %s', $name, implode( ', ', $alters ) );
	}

	/**
	 * Performs upgrade plugin environment to up to date version.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _upgrade() {
		$filter = 'domainmaping_database_upgrade';
		$option = 'domainmaping_database_version';

		// fetch current database version
		$db_version = get_site_option( $option );
		if ( $db_version === false ) {
			$db_version = '0.0.0';
			update_site_option( $option, $db_version );
		}

		// check if current version is equal to database version, then there is nothing to upgrade
		if ( version_compare( $db_version, Domainmap_Plugin::VERSION, '=' ) ) {
			return;
		}

		// add upgrade functions
		$this->_add_filter( $filter, 'setup_database', 1 );
		$this->_add_filter( $filter, 'upgrade_to_4_0_3', 10 );
		$this->_add_filter( $filter, 'upgrade_to_4_2', 10 );
		$this->_add_filter( $filter, 'upgrade_to_4_4_0_8', 10 );


        /**
         * Filter version number
         *
         * @since 4.0.0
         * @param string $db_version plugin version number
         */
        $db_version = apply_filters( $filter, $db_version );
        // upgrade database version to current plugin version
		$db_version = version_compare( $db_version, Domainmap_Plugin::VERSION, '>=' )
			? $db_version
			: Domainmap_Plugin::VERSION;

		update_site_option( $option, $db_version );
	}

	/**
	 * Creates tables if they are not exists.
	 *
	 * @since 4.0.2
	 *
	 * @access public
	 * @param string $current_version The current plugin version.
	 * @return string Unchanged version.
	 */
	public function setup_database( $current_version ) {
		// check if old table exists
		$exists = false;
		$old_table = ( isset( $this->_wpdb->base_prefix ) ? $this->_wpdb->base_prefix : $this->_wpdb->prefix ) . 'domain_map';
		if ( is_a( $this->_wpdb, 'm_wpdb' ) && isset( $this->_wpdb->dbhglobal ) ) {
			// multi db is used, so we need to use bare functions to escape m_wpdb compatibility issues
			$result = @mysqli_query( 'SHOW TABLES', $this->_wpdb->dbhglobal );
			if ( $result ) {
				while ( ( $row = @mysqli_fetch_array( $result, MYSQLI_NUM ) ) ) {
					if ( $row[0] == $old_table ) {
						$exists = true;
						break;
					}
				}
				@mysqli_free_result( $result );
			}
		} else {
			// standard wpdb is used
			$exists = in_array( $old_table, $this->_wpdb->get_col( 'SHOW TABLES' ) );
		}

		// if old table exists, rename it
		if ( $exists ) {
			$this->_wpdb->query( sprintf( 'RENAME TABLE %s TO %s', $old_table, DOMAINMAP_TABLE_MAP ) );
		}

		// create tables if not exists
		$this->_exec_queries( array(
			$this->_create_table( DOMAINMAP_TABLE_MAP, array(
				'`id` BIGINT NOT NULL AUTO_INCREMENT',
				'`blog_id` BIGINT NOT NULL',
				'`domain` VARCHAR(191) NOT NULL',
				'`active` TINYINT DEFAULT 1',
				'PRIMARY KEY (`id`)',
				'KEY `blog_id` (`blog_id`, `domain`, `active`)',
			) ),

			$this->_create_table( DOMAINMAP_TABLE_RESELLER_LOG, array(
				'`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
				'`user_id` BIGINT UNSIGNED NOT NULL',
				'`provider` VARCHAR(191) NOT NULL',
				'`requested_at` DATETIME NOT NULL',
				'`type` TINYINT UNSIGNED NOT NULL',
				'`valid` TINYINT UNSIGNED NOT NULL',
				'`errors` TEXT NOT NULL',
				'`response` TEXT NOT NULL',
				'PRIMARY KEY (`id`)',
				'KEY `idx_reseller_log` (`provider`, `valid`)',
			) ),
		) );

		return $current_version;
	}

	/**
	 * Upgrades database to version 4.0.3
	 *
	 * @since 4.0.3
	 *
	 * @access public
	 * @param string $current_version The current plugin version.
	 * @return string Upgraded version if the current version is less, otherwise current version.
	 */
	public function upgrade_to_4_0_3( $current_version ) {
		$this_version = '4.0.3';
		if ( version_compare( $current_version, $this_version, '>=' ) ) {
			return $current_version;
		}

		$this->_exec_queries( array(
			$this->_alter_table( DOMAINMAP_TABLE_MAP, array(
				'CHANGE COLUMN `active` `active` TINYINT(4) UNSIGNED NOT NULL DEFAULT 1',
				'ADD COLUMN `is_primary` TINYINT UNSIGNED NOT NULL DEFAULT 0  AFTER `blog_id`',
			) ),
		) );

		return $this_version;
	}


    /**
     * Upgrades database to version 4.2
     *
     * @since 4.2
     *
     * @param string $current_version The current plugin version.
     * @return string Upgraded version if the current version is less, otherwise current version.
     */
    public function upgrade_to_4_2( $current_version ) {
        $this_version = '4.2';
        if ( version_compare( $current_version, $this_version, '>=' ) ) {
            return $current_version;
        }

        $this->_exec_queries( array(
            $this->_alter_table( DOMAINMAP_TABLE_MAP, array(
                'ADD COLUMN `scheme` TINYINT UNSIGNED NOT NULL DEFAULT 2  AFTER `active`',
            ) ),
        ) );

        return $this_version;
    }

    /**
     * Upgrades database to version 4.4.0.8
     *
     * Changes domain column's length to 191 to the max length in InnoDB for utf8mb4
     *
     * @since 4.4.0.8
     *
     * @access public
     * @param string $current_version The current plugin version.
     * @return string Upgraded version if the current version is less, otherwise current version.
     */
    public function upgrade_to_4_4_0_8( $current_version ) {
        $this_version = '4.4.0.8';

        if ( version_compare( $current_version, $this_version, '>=' ) ) {
            return $current_version;
        }

        $this->_exec_queries( array(
            $this->_alter_table( DOMAINMAP_TABLE_MAP, array(
                'MODIFY COLUMN `domain` VARCHAR(191) NOT NULL',
            ) ),
        ) );

        $this->_exec_queries( array(
            $this->_alter_table( DOMAINMAP_TABLE_RESELLER_LOG, array(
                'MODIFY COLUMN `provider` VARCHAR(191) NOT NULL',
            ) ),
        ) );

        return $this_version;
    }
}