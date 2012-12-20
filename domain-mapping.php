<?php
/*
Plugin Name: Domain Mapping plugin
Plugin URI: http://premium.wpmudev.org/project/domain-mapping
Description: A domain mapping plugin that can handle sub-directory installs and global logins
Version: 3.1.7
Author: Barry (Incsub)
Author URI: http://caffeinatedb.com
WDP ID: 99
Network: true
*/
/*  Copyright Incsub (http://incsub.com/)
    Based on an original by Donncha (http://ocaoimh.ie/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// UnComment out the line below to allow multiple domain mappings per blog
//define('DOMAINMAPPING_ALLOWMULTI', 'yes');

if ( !is_multisite() ) {
	exit( __('The domain mapping plugin is only compatible with WordPress Multisite.', 'domainmap') );
} else {
	global $dm_map;

	// Include the configuration file
	require_once('includes/config.php');
	// Add in the global functions
	require_once('includes/functions.php');
	// Main domain mapping class
	require_once('classes/class.domainmap.php');
	// Load the WPMUDEV dashboard notification library
	include_once('external/wpmudev-dash-notification.php');

	// Set up my location
	set_domainmap_dir(__FILE__);

	$dm_map =& new domain_map();
}