<?php
/**
 * Check if Domain Mapping plugin is active.
 *
 * @return void
 */
function domainmapping_is_dm_active() {
    global $wpdb;

    // Get from cache.
    $dmmd_active_plugins = wp_cache_get( 'dmmd_active_plugins' );

    // If nothing in cache, get fresh list.
    if ( ! $dmmd_active_plugins ) {
        // Get active plugins.
        $dmmd_active_plugins = unserialize( $wpdb->get_var("SELECT `meta_value` FROM " . $wpdb->sitemeta ." WHERE `meta_key`='active_sitewide_plugins'") );
        // Set to cache.
        wp_cache_set( 'dm_active_plugins', $dmmd_active_plugins );
    }

    return is_array( $dmmd_active_plugins ) ? in_array( 'domain-mapping/domain-mapping.php', array_keys( $dmmd_active_plugins ) ) : false;
}

// If domain mapping is not active, bail.
if ( ! domainmapping_is_dm_active() ) {
    return;
}

// Define the current version of file.
define( 'DOMAINMAPPING_SUNRISE_VERSION', '1.0.3.1' );

global $wpdb;

// Domain mapping plugin to handle VHOST and non VHOST installation.

// DM database table.
$wpdb->dmtable = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'domain_mapping';

// If cookie domain is not set.
if ( defined( 'COOKIE_DOMAIN' ) ) {
    define( 'COOKIE_DOMAIN_ERROR', true );
}

// Get the current domain.
$using_domain = strtolower( preg_replace( "/^www\./", "", $_SERVER['HTTP_HOST'] ) );

// Set cookie domain.
define( 'COOKIE_DOMAIN', $_SERVER['HTTP_HOST'] );

$s_e = $wpdb->suppress_errors();

// Check for the domain with and without the www. prefix
$mapped = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtable} WHERE active=1 AND ( domain = %s OR domain = %s ) LIMIT 1", $using_domain, "www.{$using_domain}" ), OBJECT );

// Do not show db errors.
$wpdb->suppress_errors( $s_e );

// If not empty, continue.
if ( ! empty( $mapped ) ) {
    // Set to globals.
    $GLOBALS['dm_mapped'] = $mapped;

    // Get blog data.
    $current_blog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d LIMIT 1", $mapped->blog_id ) );
    // Set the domain to current domain.
    $current_blog->domain = $_SERVER['HTTP_HOST'];

    // Get the blog id and site id.
    $blog_id = $mapped->blog_id;
    $site_id = $current_blog->site_id;

    // Get the site.
    $current_site = $wpdb->get_row( $wpdb->prepare( "SELECT * from {$wpdb->site} WHERE id = %d LIMIT 1", $current_blog->site_id ) );
    // Set the blog id.
    $current_site->blog_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND path = %s", $current_site->domain, $current_site->path ) );

    global $wp_version;

    // The function get_current_site_name is deprecated as of 3.9.
    if ( version_compare( $wp_version, '3.9', '<' ) && function_exists( 'get_current_site_name' ) ) {
        $current_site = get_current_site_name( $current_site );
    }

    // Set site_name value.
    if ( ! isset( $current_site->site_name ) || empty( $current_site->site_name ) ) {
        // Set from cache.
        $current_site->site_name = wp_cache_get( $current_site->id . ':site_name', 'site-options' );
        // If not available, get now.
        if ( ! $current_site->site_name ) {
            // Get from db.
            $current_site->site_name = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = %d AND meta_key = 'site_name'", $current_site->id ) );
            // Use the domain name as fallback.
            if ( ! $current_site->site_name ) {
                $current_site->site_name = ucfirst( $current_site->domain );
            }

            // Set to cache.
            wp_cache_set( $current_site->id . ':site_name', $current_site->site_name, 'site-options' );
        }
    }

    // Set current path.
    $current_blog->path = $current_site->path;

    // Domain mapping flag.
    define( 'DOMAIN_MAPPING', 1 );

    // Added for belt and braces.
    if ( ! defined( 'WP_CONTENT_URL' ) ) {
        // Full url - WP_CONTENT_DIR is defined further up.
        define( 'WP_CONTENT_URL', ( is_ssl() ? 'https://' : 'http://' ) . $current_blog->domain . $current_blog->path . 'wp-content' );
    }
}

// Clean up temporary variables.
unset( $s_e, $using_domain );
