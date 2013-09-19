<?php
// Compatibility mode
define( 'DM_COMPATIBILITY', 'yes' );

// domain mapping plugin to handle VHOST and non VHOST installation
global $wpdb;

// No if statement needed as the code was the same for both VHOST and non VHOST installations
if ( defined( 'DM_COMPATIBILITY' ) && DM_COMPATIBILITY == 'yes' ) {
	$wpdb->dmtable = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'domain_mapping';
} else {
	$wpdb->dmtable = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'domain_map';
}

if ( defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN_ERROR', true );
}

$wpdb->suppress_errors();

$using_domain = $wpdb->_escape( preg_replace( "/^www\./", "", $_SERVER['HTTP_HOST'] ) );

// Check for the domain with and without the www. prefix
$mapped_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = %s OR domain = %s LIMIT 1", $using_domain, 'www.' . $using_domain ) );

$wpdb->suppress_errors( false );

if ( !empty( $mapped_id ) ) {
	$current_blog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d LIMIT 1", $mapped_id ) );
	$current_blog->domain = $_SERVER['HTTP_HOST'];

	$blog_id = $mapped_id;
	$site_id = $current_blog->site_id;

	define( 'COOKIE_DOMAIN', $using_domain );

	$current_site = $wpdb->get_row( $wpdb->prepare( "SELECT * from {$wpdb->site} WHERE id = %d LIMIT 0, 1", $current_blog->site_id ) );
	// Add in the blog id
	$current_site->blog_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND path = %s", $current_site->domain, $current_site->path ) );
	$current_site = get_current_site_name( $current_site );

	$current_blog->path = $current_site->path;

	define( 'DOMAIN_MAPPING', 1 );

	// Added for belt and braces
	if ( !defined( 'WP_CONTENT_URL' ) ) {
		$protocol = is_ssl() ? 'https://' : 'http://';
		define( 'WP_CONTENT_URL', $protocol . $current_blog->domain . $current_blog->path . 'wp-content' ); // full url - WP_CONTENT_DIR is defined further up
	}
}