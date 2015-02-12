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
 * Excluded pages table.
 *
 * @category Domainmap
 * @package Table
 * @subpackage MappedDomains
 *
 * @since 4.3.0
 */

class Domainmap_Table_ExcludedPages_Listing extends Domainmap_Table {

	/**
	 * Excluded pages' id in array format
	 *
	 * @since 4.3.0
	 * @var array
	 */
	private $_excluded_pages_array;

	/*
	 * SSL forced pages' id in array format
	 *
	 * $since 4.3.0
	 * @var array
	 */
	private $_ssl_forced_pages_array;

	function __construct( $args = array()  ){
		parent::__construct( array_merge( array(
			'search_box_label' => __( 'Search pages', domain_map::Text_Domain ),
			'single'           => 'Excluded page',
			'plural'           => 'Excluded pages',
			'ajax'             => true,
			'search_box'       => true
		), $args ) );

		$this->_excluded_pages_array = Domainmap_Module_Mapping::get_excluded_pages(true);
		$this->_ssl_forced_pages_array = Domainmap_Module_Mapping::get_ssl_forced_pages(true);
	}


	/**
	 * Returns table columns.
	 *
	 * @since 4.3.0
	 *
	 * @return array The array of table columns to display.
	 */
	public function get_columns() {
		$cols =  array(
			'exclude'    => __( 'Exclude', domain_map::Text_Domain ),
			'force_ssl'    => __( 'Force ssl', domain_map::Text_Domain ),
			'title'    => __( 'Title', domain_map::Text_Domain )
		);

		return $cols;
	}


	/**
	 * Returns sortable columns
	 *
	 * @since 4.3.0
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'title'=> "title"
		);
	}

	/**
	 * Fetches records from database.
	 *
	 * @since 4.3.0
	 *
	 * @global wpdb $wpdb The database connection.
	 */
	public function prepare_items() {

		parent::prepare_items();

		$per_page = 15;

		$search_term = "";
		if ( isset( $_REQUEST['s'] ) && !empty( $_REQUEST['s'] )) {
			$search_term = $_REQUEST['s'];
		}


		$query = new WP_Query(array(
			"post_type" => "page",
			"posts_per_page" => $per_page,
			"s" => $search_term,
			"orderby" => "title",
			"order" =>  isset( $_REQUEST['order'] ) ? $_REQUEST['order']  : "ASC",
			"paged" => isset( $_REQUEST['paged'] ) ? $_REQUEST['paged']  : 1,
		));

		$this->items = $query->get_posts();
		$total_items = $query->found_posts;
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => $query->max_num_pages,
			'orderby'	=> 'title',
			'order'		=> isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'asc'
		) );

	}

	/**
	 * Renders exclude columns
	 *
	 * @since 4.3.0
	 * @param $page WP_Post
	 */
	public function column_exclude( $page ) {
		$is_excluded = in_array( $page->ID, $this->_excluded_pages_array );
		?>
		<input <?php checked($is_excluded, true); ?> type="checkbox" data-id="<?php echo $page->ID ?>" class="dm_excluded_page_checkbox" value="<?php echo $page->ID ?>"/>
		<?php
	}

	/**
	 * Renders exclude columns
	 *
	 * @since 4.3.0
	 * @param $page WP_Post
	 */
	public function column_force_ssl( $page ) {
		$is_excluded = in_array( $page->ID, $this->_ssl_forced_pages_array );
		?>
		<input <?php checked($is_excluded, true); ?> type="checkbox" data-id="<?php echo $page->ID ?>" class="dm_ssl_forced_page_checkbox" value="<?php echo $page->ID ?>"/>
		<?php
	}

	/**
	 * Renders title columns
	 * @since 4.3.0
	 * @param $page WP_Post
	 */
	public function column_title( $page ) {
		$url = get_permalink( $page->ID );
		printf( '<a target="_blank" href="%1$s">%2$s</a>',  $url , $page->post_title  );
	}




	/**
	 * Renders single row
	 *
	 * @since 4.3.0
	 *
	 * @param WP_Post $page
	 */
	public function single_row( $page ) {
		echo '<tr class="domainmapping-exluded-page-'. $page->ID. '">';
		$this->single_row_columns( $page );
		echo '</tr>';
	}

	/**
	 * Returns associative array with the list of bulk actions available on this table.
	 *
	 * @since 4.3.0
	 *
	 * @access protected
	 * @return array The associative array of bulk actions.
	 */
	public function get_bulk_actions() {
		return array();
	}

	/**
	 * Adds footer or header to the table
	 *
	 * @since 4.3.0
	 * @param string $which
	 */
	public function extra_tablenav( $which ) {

		$s = filter_input( INPUT_GET, 's' );

		/**
		 * For ajax calls
		 */
		wp_nonce_field( 'excluded-pages-nonce', '_excluded_pages_nonce' );
		?>

		<?php if ( 'top' == $which ):?>

				<form  method="get" id="dm_excluded_pages_search_form">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<input type="hidden" name="order" value="<?php echo isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : "" ?>"/>
					<input type="hidden" name="orderby" value="<?php echo isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : "" ?>"/>
					<div class="search-box">

						<input type="text" id="dm_excluded_pages_search_s" name="s" value="<?php echo $s ?>" placeholder="Search for page"/>
						<?php submit_button( __( 'Search pages', domain_map::Text_Domain ), 'button', false, false, array( 'id' => 'dm-search-for-exluded-pages' ) ); 		?>

					</div>
				</form>
			<span class="spinner" id="dm_excluded_pages_search_spinner"></span>
			<div class="displaying-num dm_excluded_pages_label"><span><?php echo count( $this->_excluded_pages_array ); ?></span> <?php _e("excluded", domain_map::Text_Domain); ?></div>
			&nbsp;
			<div class="displaying-num dm_ssl_forced_pages_label"><span><?php echo count( $this->_ssl_forced_pages_array ); ?></span> <?php _e("ssl forced", domain_map::Text_Domain); ?></div>
		<?php endif;?>
		<?php if ( 'bottom' == $which ):?>
			<form  method="post">
				<input type="hidden" name="page" value="domainmapping"/>
				<input type="hidden" name="paged" value="<?php echo isset( $_REQUEST['paged'] ) ? $_REQUEST['paged'] : "" ?>"/>
				<?php wp_nonce_field("save-exluded-pages", "_save-exluded-pages"); ?>
			<input type="hidden" name="dm_excluded_pages" id="dm_exluded_pages_hidden_field" value="<?php echo Domainmap_Module_Mapping::get_excluded_pages(); ?>"/>
			<input type="hidden" name="dm_ssl_forced_pages" id="dm_ssl_forced_pages_hidden_field" value="<?php echo Domainmap_Module_Mapping::get_ssl_forced_pages(); ?>"/>
			<?php submit_button( __( 'Save excluded pages', domain_map::Text_Domain ), 'primary', "dm-save-exluded-pages", false, array( 'id' => 'save-exluded-pages' ) ); 		?>
			</form>
		<?php endif;?>


	<?php
	}

	/**
	 * Handle an incoming ajax request (called from admin-ajax.php)
	 *
	 * @since 4.3.0
	 * @access public
	 */
	function ajax_response() {

		check_ajax_referer( 'excluded-pages-nonce', '_excluded_pages_nonce' );
		$this->prepare_items();
		ob_start();
		if ( ! empty( $_REQUEST['no_placeholder'] ) )
			$this->display_rows();
		else
			$this->display_rows_or_placeholder();
		$rows = ob_get_clean();
		ob_start();
		$this->print_column_headers();
		$headers = ob_get_clean();
		ob_start();
		$this->pagination('top');
		$pagination_top = ob_get_clean();
		ob_start();
		$this->pagination('bottom');
		$pagination_bottom = ob_get_clean();
		$response = array( 'rows' => $rows );
		$response['pagination']['top'] = $pagination_top;
		$response['pagination']['bottom'] = $pagination_bottom;
		$response['column_headers'] = $headers;
		if ( isset( $total_items ) )
			$response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );
		if ( isset( $total_pages ) ) {
			$response['total_pages'] = $total_pages;
			$response['total_pages_i18n'] = number_format_i18n( $total_pages );
		}
		die( json_encode( $response ) );
	}



}


