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
 * Reseller API log table.
 *
 * @category Domainmap
 * @package Table
 * @subpackage Reseller
 *
 * @since 4.0.0
 */
class Domainmap_Table_Reseller_Log extends Domainmap_Table {

	/**
	 * Returns errors column data to display.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $item The array of row data.
	 * @return string The errors.
	 */
	public function column_errors( $item ) {
		return implode( '<br>', array_map( 'esc_html', explode( PHP_EOL, $item['errors'] ) ) );
	}

	/**
	 * Returns valid column data to display.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $item The array of row data.
	 * @return string The valid status.
	 */
	public function column_valid( $item ) {
		return $item['valid'] == 0
			? '<span class="domainmapping-request-invalid">' . __( 'failed', 'domainmap' ) . '</span>'
			: '<span class="domainmapping-request-valid">' . __( 'success', 'domainmap' ) . '</span>';
	}

	/**
	 * Returns date and time when request was processed.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $item The array of row data.
	 * @return string The date and time when the request was processed.
	 */
	public function column_requested_at( $item ) {
		$timestamp = strtotime( $item['requested_at'] );
		if ( $timestamp === false ) {
			return '<i>' . __( 'unknown', 'domainmap' ) . '</i>';
		}

		$date_pattern = date( 'Y', $timestamp ) == date( 'Y' )
			? 'l, d M'
			: 'l, d M y';

		$time_pattern = 'H:i T';

		return '<b>' . date( $date_pattern, $timestamp ) . '</b><span>' . date( $time_pattern, $timestamp ) . '</span>';
	}

	/**
	 * Returns user name, who has triggered the request.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $item The array of row data.
	 * @return string The user name.
	 */
	public function column_user_name( $item ) {
		$nonce = wp_create_nonce( $this->_args['nonce_action'] );

		$actions = array(
			'view'   => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( add_query_arg( array(
					'nonce'                => $nonce,
					'noheader'             => 'true',
					'action'               => 'reseller-log-view',
					$this->_args['plural'] => $item['id'],
				) ) ),
				__( 'View Details', 'domainmap' )
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return showNotice.warn();">%s</a>',
				esc_url(add_query_arg( array(
					'nonce'                => $nonce,
					'noheader'             => 'true',
					'action'               => 'reseller-log-delete',
					$this->_args['plural'] => $item['id'],
				) ) ),
				__( 'Delete', 'domainmap' )
			),
		);

		return sprintf(
			'<a href="%s"><b>%s</b></a> %s',
			esc_url( add_query_arg( 's', $item['user_id'], network_admin_url( 'users.php' ) ) ),
			$item['user_name'],
			$this->row_actions( $actions )
		);
	}

	/**
	 * Returns request type, which has been triggered.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $item The array of row data.
	 * @return string The request type.
	 */
	public function column_action( $item ) {
		$types = Domainmap_Reseller::get_request_types();
		return isset( $types[$item['type']] )
			? $types[$item['type']]
			: '<i>' . __( 'unknown', 'domainmap' ) . '</i>';
	}

	/**
	 * Returns tabel columns.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of table columns to display.
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" class="cb_all">',
			'user_name'    => __( 'User', 'domainmap' ),
			'action'       => __( 'Action', 'domainmap' ),
			'valid'        => __( 'Status', 'doaminmap' ),
			'requested_at' => __( 'Requested At', 'domainmap' ),
			'errors'       => __( 'Errors', 'domainmap' ),
		);
	}

	/**
	 * Returns the associative array with the list of views available on this table.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @global wpdb $wpdb The database connection.
	 * @return array The array of views.
	 */
	public function get_views() {
		global $wpdb;

		$type = $this->_get_type_filter();
		$type = ( $type !== false ? ' AND type = ' . $type : '' );

		$valid = filter_input( INPUT_GET, 'valid', FILTER_VALIDATE_INT, array(
			'options' => array(
				'min_range' => 0,
				'max_range' => 1,
				'default'   => false,
			),
		) );

		$provider = esc_sql( $this->_args['reseller'] );
		return array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( array( 'valid' => false, 'paged' => false ) ) ),
				!isseT( $_GET['valid'] ) ? ' class="current"' : '',
				__( 'All', 'domainmap' ),
				intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . DOMAINMAP_TABLE_RESELLER_LOG . " WHERE provider = %s" . $type, $provider ) ) )
			),
			'valid' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( array( 'valid' => '1', 'paged' => false ) ) ),
				$valid > 0 ? ' class="current"' : '',
				__( 'Success', 'domainmap' ),
				intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . DOMAINMAP_TABLE_RESELLER_LOG . " WHERE provider = %s AND valid > 0" . $type, $provider ) ) )
			),
			'invalid' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( array( 'valid' => '0', 'paged' => false ) ) ),
				$valid === 0 ? ' class="current"' : '',
				__( 'Failed', 'domainmap' ),
				intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . DOMAINMAP_TABLE_RESELLER_LOG . " WHERE provider = %s AND valid = 0" . $type, $provider ) ) )
			),
		);
	}

	/**
	 * Returns type filter value.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @return int Request type filter value.
	 */
	private function _get_type_filter() {
		$types = array_keys( Domainmap_Reseller::get_request_types() );
		return filter_input( INPUT_GET, 'type', FILTER_VALIDATE_INT, array(
			'options' => array(
				'min_range' => min( $types ),
				'max_range' => max( $types ),
				'default'   => false,
			),
		) );
	}

	/**
	 * Fetches records from database.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @global wpdb $wpdb The database connection.
	 */
	public function prepare_items() {
		global $wpdb;

		parent::prepare_items();
		$per_page = 20;
		$offset = ( $this->get_pagenum() - 1 ) * $per_page;

		$type = $this->_get_type_filter();
		$type = $type !== false ? ' AND l.type = ' . $type : '';

		$valid = filter_input( INPUT_GET, 'valid', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0, 'max_range' => 1, 'default' => false ) ) );
		$valid = $valid !== false ? ' AND l.valid = ' . $valid : '';

		$this->items = $wpdb->get_results( $wpdb->prepare( "
			SELECT SQL_CALC_FOUND_ROWS l.id, l.user_id, l.requested_at, l.type, l.valid, l.errors, u.display_name AS user_name
			  FROM " . DOMAINMAP_TABLE_RESELLER_LOG . " AS l
			  LEFT JOIN {$wpdb->users} AS u ON u.ID = l.user_id
			 WHERE l.provider = %s{$type}{$valid}
			 ORDER BY l.id DESC
			 LIMIT {$per_page}
			OFFSET {$offset}
			",
			$this->_args['reseller']
		), ARRAY_A );

		$total_items = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param arra $item The current item.
	 */
	public function single_row( $item ) {
		echo '<tr class="domainmapping-log-item-', ( $item['valid'] ? 'valid' : 'invalid' ), '">';
			$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	public function extra_tablenav( $which ) {
		$current_type = filter_input( INPUT_GET, 'type' );

		?><div class="alignleft actions"><?php

		if ( 'top' == $which ) :
			?><select name="type">
				<option value=""><?php _e( 'Show all actions', 'domainmap' ) ?></option>
				<?php
					foreach ( Domainmap_Reseller::get_request_types() as $type => $label ) :
						printf( '<option%s value="%s">%s</option>',
							selected( $type, $current_type, false ),
							esc_attr( $type ),
							esc_html( $label )
						);
					endforeach;
				?>
			</select><?php

			submit_button( __( 'Filter' ), 'button', false, false, array( 'id' => 'post-query-submit' ) );
		endif;

		?></div><?php
	}

}