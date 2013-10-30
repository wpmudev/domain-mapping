<?php

define( 'DOMAINMAPPING_SUNRISE_VERSION', '1.0.1' );

// domain mapping plugin to handle VHOST and non VHOST installation
global $wpdb;
$wpdb->dmtable = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'domain_mapping';

if ( defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN_ERROR', true );
}

$using_domain = $_SERVER['HTTP_HOST'];
if ( filter_var( $using_domain, FILTER_VALIDATE_IP ) ) {
	$mapped_id = 1;
} else {
	$s_e = $wpdb->suppress_errors();

	// Check for the domain with and without the www. prefix
	$using_domain = preg_replace( '/^www\./', '', $using_domain );
	$mapped_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = %s OR domain = %s LIMIT 1", $using_domain, "www.{$using_domain}" ) );

	$wpdb->suppress_errors( $s_e );
}

if ( !empty( $mapped_id ) ) {
	$current_blog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d LIMIT 1", $mapped_id ) );
	$current_blog->domain = $_SERVER['HTTP_HOST'];

	$blog_id = $mapped_id;
	$site_id = $current_blog->site_id;

	define( 'COOKIE_DOMAIN', $using_domain );

	$current_site = $wpdb->get_row( $wpdb->prepare( "SELECT * from {$wpdb->site} WHERE id = %d LIMIT 1", $current_blog->site_id ) );
	$current_site->blog_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND path = %s", $current_site->domain, $current_site->path ) );

	$current_site = get_current_site_name( $current_site );
	$current_blog->path = $current_site->path;

	define( 'DOMAIN_MAPPING', 1 );

	// Added for belt and braces
	if ( !defined( 'WP_CONTENT_URL' ) ) {
		// full url - WP_CONTENT_DIR is defined further up
		define( 'WP_CONTENT_URL', ( is_ssl() ? 'https://' : 'http://' ) . $current_blog->domain . $current_blog->path . 'wp-content' );
	}
}

// clean up temporary variables
unset( $s_e, $using_domain, $mapped_id );