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
 * Renders map domain tab page.
 *
 * @category Domainmap
 * @package Render
 * @subpackage Site
 *
 * @since 4.0.0
 */
class Domainmap_Render_Site_Map extends Domainmap_Render_Site {

	/**
	 * Renders instructions how to configure DNS records.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_instructions() {
		if ( empty( $this->ips ) ) {
			return;
		}

		$descriptions = array();
		if ( defined( 'SUBDOMAIN_INSTALL' ) && !$this->dedicated ) {
			if ( SUBDOMAIN_INSTALL ) {
				$descriptions[] = __( 'You need to add a DNS CNAME record pointing at this server domain name: ', 'domainmap' ) . "<strong>{$this->origin->domain}.</strong>";
			} else {
				// network is hosted on shared hosting and uses subfolders for sites
				// neither DNS A record nor DNS CNAME record won't work in this case
			}
		} else {
			$ips = '<strong>' . implode( ', ', $this->ips ) . '</strong>';
			$descriptions[] = count( $this->ips ) > 1
				? __( 'You need to add multiple DNS "A" records pointing at the IP addresses of this server: ', 'domainmap' ) . $ips
				: __( 'You need to add a DNS "A" record pointing at the IP address of this server: ', 'domainmap' ) . $ips;
		}

		foreach ( $descriptions as $description ) :
			?><p class="domainmapping-info"><?php echo $description ?></p><?php
		endforeach;
	}

	/**
	 * Renders tab content.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_tab() {
		$schema = defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) && DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN && is_ssl() ? 'https' : 'http';
		$form_class = count( $this->domains ) > 0 && !defined( 'DOMAINMAPPING_ALLOWMULTI' ) ? ' domainmapping-form-hidden' : '';

		$this->_render_instructions();

		?><div class="domainmapping-domains domainmapping-box">
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
							<button type="submit" class="button button-primary"><i class="icon-globe"></i> <?php _e( 'Map domain', 'domainmap' ) ?></button>
							<div class="domainmapping-clear"></div>
						</form>
					</li>
				</ul>
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
			$schema = defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) && DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN && is_ssl() ? 'https' : 'http';;
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

}