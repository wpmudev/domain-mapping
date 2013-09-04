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
 * Site admin page render class.
 *
 * @since 4.0.0
 * @category Domainmap
 * @package Render
 * @subpackage Page
 */
class Domainmap_Render_Page_Site extends Domainmap_Render {

	/**
	 * Renders site admin page.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _to_html() {
		$tabs = array(
			'mapping'  => array(
				'label'    => __( 'Map domain', 'domainmap' ),
				'callback' => '_handle_domain_mapping_page',
			),
		);

		if ( $this->reseller && $this->reseller->is_valid() ) {
			$tabs['purchase'] = array(
				'label'    => __( 'Purchase domain', 'domainmap' ),
				'callback' => '_handle_domain_purchase_page',
			);
		}

		$activetab = strtolower( trim( filter_input( INPUT_GET, 'tab', FILTER_DEFAULT ) ) );
		if ( !in_array( $activetab, array_keys( $tabs ) ) ) {
			$activetab = key( $tabs );
		}

		?><div id="domainmapping-content" class="wrap">
			<div class="icon32" id="icon-tools"><br/></div>
			<h2><?php _e( 'Domain Mapping', 'domainmap' ) ?></h2>

			<div class="domainmapping-tab-switch">
				<ul>
					<?php foreach ( $tabs as $tab => $info ) : ?>
					<li>
						<a<?php echo $tab == $activetab ? ' class="active"' : '' ?> href="<?php echo esc_url( add_query_arg( 'tab', $tab ) ) ?>">
							<?php echo $info['label'] ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
				<div class="domainmapping-clear"></div>
			</div>
			<?php call_user_func( array( $this, $tabs[$activetab]['callback'] ) ) ?>
		</div><?php
	}

	/**
	 * Handles doamin mapping tab.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _handle_domain_mapping_page() {
		$descriptions = array();
		$descriptions[] = __( 'If your domain name includes a sub-domain such as "blog" then you can add a CNAME for that hostname in your DNS pointing at this blog URL.', 'domainmap' );

		$map_ipaddress = isset( $this->map_ipaddress ) ? trim( $this->map_ipaddress ) : '';
		if ( !empty( $map_ipaddress ) ) {
			if ( strpos( $map_ipaddress, ',' ) ) {
				$descriptions[] = __( 'If you want to redirect a domain you will need to add multiple DNS "A" records pointing at the IP addresses of this server: ', 'domainmap' ) . "<strong>{$map_ipaddress}</strong>";
			} else {
				$descriptions[] = __( 'If you want to redirect a domain you will need to add a DNS "A" record pointing at the IP address of this server: ', 'domainmap' ) . "<strong>{$map_ipaddress}</strong>";
			}
		}

		$schema = is_ssl() ? 'https' : 'http';
		$form_class = count( $this->domains ) > 0 && !defined( 'DOMAINMAPPING_ALLOWMULTI' ) ? ' domainmapping-form-hidden' : '';

		?><div class="domainmapping-tab">
			<?php foreach ( $descriptions as $description ) : ?>
				<p class="domainmapping-info"><?php echo $description ?></p>
			<?php endforeach; ?>

			<div class="domainmapping-domains domainmapping-box">
				<h3>
					<?php _e( 'Domain(s) mapped to', 'domainmap' ) ?>
					<span class="domainmapping-origin"><?php echo $schema ?>://<?php echo esc_html( $this->origin->domain . $this->origin->path ) ?></span>
				</h3>
				<div class="domainmapping-domains-wrapper domainmapping-box-content<?php echo $form_class ?>">
					<div class="domainmapping-locker"></div>
					<ul class="domainmapping-domains-list">
						<li>
							<span class="domainmapping-mapped"><?php _e( 'Mapped domain', 'domainmap' ) ?></span>
							<span class="domainmapping-map-state"><?php _e( 'Health status', 'domainmap' ) ?></span>
							<span class="domainmapping-map-remove"><?php _e( 'Actions', 'domainmap' ) ?></span>
						</li>
						<?php foreach( $this->domains as $domain ) : ?>
							<?php self::render_mapping_row( $domain, $schema ) ?>
						<?php endforeach; ?>
						<li class="domainmapping-form">
							<form id="domainmapping-form-map-domain" action="<?php echo admin_url( 'admin-ajax.php' ) ?>" method="post">
								<?php wp_nonce_field( 'domainmapping_map_domain', 'nonce' ) ?>
								<input type="hidden" name="action" value="domainmapping_map_domain">
								<input type="text" class="domainmapping-input-prefix" readonly disabled value="<?php echo $schema ?>://">
								<div class="domainmapping-controls-wrapper">
									<input type="text" class="domainmapping-input-domain" autofocus name="domain">
								</div>
								<input type="text" class="domainmapping-input-sufix" readonly disabled value="/">
								<button type="submit" class="button button-primary domainmapping-button"><i class="icon-globe"></i> <?php _e( 'Map domain', 'domainmap' ) ?></button>
								<div class="domainmapping-clear"></div>
							</form>
						</li>
					</ul>
				</div>
			</div>
		</div><?php
	}

	/**
	 * Renders domain mapping row.
	 *
	 * @since 4.0.0
	 *
	 * @static
	 * @access public
	 * @global stdClass $current_site Current site object.
	 * @param string $domain The mapped domain name.
	 * @param string $schema The current schema.
	 */
	public static function render_mapping_row( $domain, $schema = false ) {
		global $current_site;

		if ( !$schema ) {
			$schema = is_ssl() ? 'https' : 'http';
		}

		$remove_link = add_query_arg( array(
			'action' => 'domainmapping_unmap_domain',
			'nonce'  => wp_create_nonce( 'domainmapping_unmap_domain' ),
			'domain' => $domain,
		), admin_url( 'admin-ajax.php' ) );

		?><li>
			<a class="domainmapping-mapped" href="<?php echo $schema ?>://<?php echo $domain, $current_site->path ?>" target="_blank" title="<?php _e( 'Go to this domain', 'domainmap' ) ?>">
				<?php echo $schema ?>://<?php echo $domain, $current_site->path ?>
			</a>
			<?php self::render_health_column( $domain ) ?>
			<a class="domainmapping-map-remove" href="<?php echo esc_url( $remove_link ) ?>" title="<?php _e( 'Remove the domain', 'domainmap' ) ?>"><i class="icon-remove"></i></a>
		</li><?php
	}

	/**
	 * Renders health check status columnt.
	 *
	 * @since 4.0.0
	 *
	 * @static
	 * @access public
	 * @param string $domain The domain name.
	 */
	public static function render_health_column( $domain ) {
		$health_link = add_query_arg( array(
			'action' => 'domainmapping_check_health',
			'nonce'  => wp_create_nonce( 'domainmapping_check_health' ),
			'domain' => $domain,
		), admin_url( 'admin-ajax.php' ) );

		$health = get_transient( "domainmapping-{$domain}-health" );
		$health_message = __( 'need revalidate', 'domainmap' );
		$health_class = ' domainmapping-need-revalidate';
		if ( $health !== false ) {
			if ( $health ) {
				$health_class = ' domainmapping-valid-domain';
				$health_message = __( 'valid', 'domainmap' );
			} else {
				$health_class = ' domainmapping-invalid-domain';
				$health_message = __( 'invalid', 'domainmap' );
			}
		}

		?><a class="domainmapping-map-state<?php echo $health_class ?>" href="<?php echo $health_link ?>" title="<?php _e( 'Refres health status', 'domainmap' ) ?>"><?php
			echo $health_message
		?></a><?php
	}

	/**
	 * Handles domain purchase tab.
	 *
	 * @sicne 4.0.0
	 * @uses wp_enqueue_script() To enqueue already registered jQuery payment script.
	 *
	 * @access private
	 */
	private function _handle_domain_purchase_page() {
		wp_enqueue_script( 'jquery-payment' );
		$tlds = $this->reseller->get_tld_list();

		?><div class="domainmapping-tab">
			<p class="domainmapping-info"><?php
				_e( 'If you want to buy an unique domain name and map it to your site, then you can do it on this page. Check whether desired domain name is available, and if it is, just fill in payment details and purchase it. New domain will be bought and mapped to your site. All necessary DNS records will be setup automatically.', 'domainmap' )
			?></p>

			<div id="domainmapping-box-check-domain" class="domainmapping-box">
				<h3><?php _e( 'Step 1: Check domain availability', 'domainmap' ) ?></h3>
				<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
					<div class="domainmapping-locker"></div>
					<form id="domainmapping-check-domain-form" action="<?php echo admin_url( 'admin-ajax.php' ) ?>" method="post">
						<?php wp_nonce_field( 'domainmapping_check_domain', 'nonce' ) ?>
						<input type="hidden" name="action" value="domainmapping_check_domain">
						<input type="text" class="domainmapping-input-prefix" readonly disabled value="http://">
						<div class="domainmapping-controls-wrapper">
							<input type="text" class="domainmapping-input-domain" autofocus name="sld">
							<select name="tld" class="domainmapping-select-domain">
								<?php foreach ( $tlds as $tld ) : ?>
								<option<?php selected( $tld, 'com' ) ?> value="<?php echo esc_attr( $tld ) ?>">.<?php echo esc_html( $tld ) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<input type="text" class="domainmapping-input-sufix" readonly disabled value="">
						<button type="submit" class="button-primary button domainmapping-button"><i class="icon-search"></i> <?php _e( 'Check domain', 'domainmap' ) ?></button>
						<div class="domainmapping-clear"></div>
					</form>

					<div class="domainmapping-form-results"></div>
				</div>
			</div>
		</div><?php
	}

}