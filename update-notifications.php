<?php
/*
Plugin Name: Update Notifications
Plugin URI: 
Description:
Author: Andrew Billits
Version: 1.0.0
Author URI:
WDP ID: 119
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$update_notificiations_version = '1.0.0';

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$update_notificiations_server_url = 'http://premium.wpmudev.org/wdp-un.php';
$update_notificiations_enable_admin_notices = 'yes'; //Either 'yes' OR 'no'
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('admin_menu', 'update_notificiations_plug_pages');
add_action('admin_notices', 'update_notificiations_notice_output');
add_action('admin_footer', 'update_notificiations_check');
add_action('admin_head', 'update_notificiations_admin_header_refesh_local_projects');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function update_notificiations_get_id_plugin($plugin_file) {
	$fp = fopen($plugin_file, 'r');
	$plugin_data = fread( $fp, 8192 );
	fclose($fp);

	preg_match( '|WDP ID:(.*)$|mi', $plugin_data, $id );
	preg_match( '|Version:(.*)$|mi', $plugin_data, $version );

	if ( is_array( $id ) ) {
		$id = $id[1];
		$id = str_replace('.', '', $id);
		$id = str_replace(' ', '', $id);
		$id = trim($id);
	}

	if ( is_array( $version ) ) {
		$version = $version[1];
		$version = str_replace(' ', '', $version);
		$version = trim($version);
	}
	
	
	if ( is_numeric($id) && !empty($version) ) {
		$return['id'] = $id;
		$return['version'] = $version;
	}
	
	return $return;
}

function update_notificiations_get_projects() {
	$projects = array();

	//plugins directory
	//----------------------------------------------------------------------------------//
	$plugins_root = WP_PLUGIN_DIR;
	if( empty($plugins_root) ) {
		$plugins_root = ABSPATH . 'wp-content/plugins';
	}

	$plugins_dir = @opendir($plugins_root);
	$plugin_files = array();
	if ( $plugins_dir ) {
		while (($file = readdir( $plugins_dir ) ) !== false ) {
			if ( substr($file, 0, 1) == '.' )
				continue;
			if ( is_dir( $plugins_root.'/'.$file ) ) {
				$plugins_subdir = @ opendir( $plugins_root.'/'.$file );
				if ( $plugins_subdir ) {
					while (($subfile = readdir( $plugins_subdir ) ) !== false ) {
						if ( substr($subfile, 0, 1) == '.' )
							continue;
						if ( substr($subfile, -4) == '.php' )
							$plugin_files[] = "$file/$subfile";
					}
				}
			} else {
				if ( substr($file, -4) == '.php' )
					$plugin_files[] = $file;
			}
		}
	}
	@closedir( $plugins_dir );
	@closedir( $plugins_subdir );

	if ( $plugins_dir && !empty($plugin_files) ) {
		foreach ( $plugin_files as $plugin_file ) {
			if ( is_readable( "$plugins_root/$plugin_file" ) ) {
			
				$data = update_notificiations_get_id_plugin( "$plugins_root/$plugin_file" );
				
				if ( is_array( $data ) ) {
					$projects[$data['id']]['id'] = $data['id'];
					$projects[$data['id']]['version'] = $data['version'];
				}
			}
		}
	}
	//----------------------------------------------------------------------------------//

	//mu-plugins directory
	//----------------------------------------------------------------------------------//
	$mu_plugins_root = WPMU_PLUGIN_DIR;
	if( empty($mu_plugins_root) ) {
		$mu_plugins_root = ABSPATH . 'wp-content/mu-plugins';
	}

	$mu_plugins_dir = @opendir($mu_plugins_root);
	$mu_plugin_files = array();
	if ( $mu_plugins_dir ) {
		while (($file = readdir( $mu_plugins_dir ) ) !== false ) {
			if ( substr($file, 0, 1) == '.' )
				continue;
			if ( is_dir( $mu_plugins_root.'/'.$file ) ) {
				$mu_plugins_subdir = @ opendir( $mu_plugins_root.'/'.$file );
				if ( $mu_plugins_subdir ) {
					while (($subfile = readdir( $mu_plugins_subdir ) ) !== false ) {
						if ( substr($subfile, 0, 1) == '.' )
							continue;
						if ( substr($subfile, -4) == '.php' )
							$mu_plugin_files[] = "$file/$subfile";
					}
				}
			} else {
				if ( substr($file, -4) == '.php' )
					$mu_plugin_files[] = $file;
			}
		}
	}
	@closedir( $mu_plugins_dir );
	@closedir( $mu_plugins_subdir );

	if ( $mu_plugins_dir && !empty($mu_plugin_files) ) {
		foreach ( $mu_plugin_files as $mu_plugin_file ) {
			if ( is_readable( "$mu_plugins_root/$mu_plugin_file" ) ) {
			
				$id = update_notificiations_get_id_plugin( "$mu_plugins_root/$mu_plugin_file" );
				
				if ( !empty( $id ) ) {
					$projects[] = $id;
				}
			}
		}
	}
	//----------------------------------------------------------------------------------//

	//wp-content directory
	//----------------------------------------------------------------------------------//
	$content_plugins_root = WP_CONTENT_DIR;
	if( empty($content_plugins_root) ) {
		$content_plugins_root = ABSPATH . 'wp-content';
	}

	$content_plugins_dir = @opendir($content_plugins_root);
	$content_plugin_files = array();
	if ( $content_plugins_dir ) {
		while (($file = readdir( $content_plugins_dir ) ) !== false ) {
			if ( substr($file, 0, 1) == '.' )
				continue;
			if ( !is_dir( $content_plugins_root.'/'.$file ) ) {
				if ( substr($file, -4) == '.php' )
					$content_plugin_files[] = $file;
			}
		}
	}
	@closedir( $content_plugins_dir );
	@closedir( $content_plugins_subdir );

	if ( $content_plugins_dir && !empty($content_plugin_files) ) {
		foreach ( $content_plugin_files as $content_plugin_file ) {
			if ( is_readable( "$content_plugins_root/$content_plugin_file" ) ) {
			
				$id = update_notificiations_get_id_plugin( "$content_plugins_root/$content_plugin_file" );
				
				if ( !empty( $id ) ) {
					$projects[] = $id;
				}
			}
		}
	}
	//----------------------------------------------------------------------------------//

	//themes directory
	//----------------------------------------------------------------------------------//
	$themes_root = WP_CONTENT_DIR . '/themes';
	if( empty($themes_root) ) {
		$themes_root = ABSPATH . 'wp-content/themes';
	}

	$themes_dir = @opendir($themes_root);
	$themes_files = array();
	if ( $themes_dir ) {
		while (($file = readdir( $themes_dir ) ) !== false ) {
			if ( substr($file, 0, 1) == '.' )
				continue;
			if ( is_dir( $themes_root.'/'.$file ) ) {
				$themes_subdir = @ opendir( $themes_root.'/'.$file );
				if ( $themes_subdir ) {
					while (($subfile = readdir( $themes_subdir ) ) !== false ) {
						if ( substr($subfile, 0, 1) == '.' )
							continue;
						if ( substr($subfile, -4) == '.css' )
							$themes_files[] = "$file/$subfile";
					}
				}
			} else {
				if ( substr($file, -4) == '.css' )
					$themes_files[] = $file;
			}
		}
	}
	@closedir( $themes_dir );
	@closedir( $themes_subdir );

	if ( $themes_dir && !empty($themes_files) ) {
		foreach ( $themes_files as $themes_file ) {
			if ( is_readable( "$themes_root/$themes_file" ) ) {
			
				$id = update_notificiations_get_id_plugin( "$themes_root/$themes_file" );
				
				if ( !empty( $id ) ) {
					$projects[] = $id;
				}
			}
		}
	}
	//----------------------------------------------------------------------------------//

	$projects = array_unique($projects);

	return $projects;
}

function update_notificiations_admin_header_refesh_local_projects() {
	if ( is_site_admin() ) {
		update_notificiations_refesh_local_projects();
	}
}

function update_notificiations_refesh_local_projects() {
	$data = get_site_option('wdp_un_last_response');
	$now = time();
	if ( is_array( $data ) ) {
		$local_projects = update_notificiations_get_projects();
		
		$local_project_ids = array();
		
		foreach ($local_projects as $local_project) {
			$local_project_ids[] = $local_project['id'];
		}
		
		$current_local_projects = get_site_option('wdp_un_local_projects');
		
		$current_local_projects_md5 = md5(serialize($current_local_projects));
		$local_projects_md5 = md5(serialize($local_projects));
		
		if ( $current_local_projects_md5 != $local_projects_md5 ) {
			update_notificiations_process();
			update_site_option('wdp_un_last_run', $now);
			//refresh data
			unset( $data );
			$data = get_site_option('wdp_un_last_response');
		}
		
		update_site_option('wdp_un_local_projects', $local_projects);
		update_site_option('wdp_un_local_project_ids', $local_project_ids);
		
		$remote_projects = $data['latest_versions'];
		
		$count = 0;
		
		foreach ( $local_projects as $local_project ) {
			foreach ( $remote_projects as $remote_project ) {
				update_site_option('wdp_un_updates_available', 'no');
				if ( $remote_project['id'] == $local_project['id'] ) {
					//match
					$local_version = $local_project['version'];
					$local_version = trim($local_version, '.0');
					$local_version = trim($local_version, '.0');
					$remote_version = $remote_project['version'];
					$remote_version = trim($remote_version, '.0');
					$remote_version = trim($remote_version, '.0');
					if ( $local_version != $remote_version ) {
						$count = $count + 1;
						update_site_option('wdp_un_updates_available', 'yes');
					}
				}
			}
		}
		update_site_option('wdp_un_updates_available_count', $count);
	}
}

function update_notificiations_process() {
	global $wpdb, $current_site, $update_notificiations_version, $update_notificiations_server_url;

	$local_projects = update_notificiations_get_projects();
	
	$local_project_ids = array();
	
	foreach ($local_projects as $local_project) {
		$local_project_ids[] = $local_project['id'];
	}
	
	update_site_option('wdp_un_local_projects', $local_projects);
	update_site_option('wdp_un_local_project_ids', $local_project_ids);

	$url = $update_notificiations_server_url . '?action=check&un-version=' . $update_notificiations_version . '&domain=' . urlencode($current_site->domain) . '&path=' . urlencode($current_site->path) . '&p=' . implode('.', $local_project_ids);

	$options = array(
		'timeout' => 3,
		'user-agent' => 'UN Client/' . $update_notificiations_version
	);

	$response = wp_remote_get($url, $options);
	$data = $response['body'];
	if ( $data != 'error' ) {
		$data = unserialize($data);
		if ( is_array($data) ) {
			update_site_option('wdp_un_text_admin_notice', $data['text_admin_notice']);
			update_site_option('wdp_un_text_page_head', $data['text_page_head']);
			update_site_option('wdp_un_last_response', $data);
			
			$remote_projects = $data['latest_versions'];
			
			$count = 0;
			
			foreach ( $local_projects as $local_project ) {
				foreach ( $remote_projects as $remote_project ) {
					update_site_option('wdp_un_updates_available', 'no');
					if ( $remote_project['id'] == $local_project['id'] ) {
						//match
						$local_version = $local_project['version'];
						$local_version = trim($local_version, '.0');
						$local_version = trim($local_version, '.0');
						$remote_version = $remote_project['version'];
						$remote_version = trim($remote_version, '.0');
						$remote_version = trim($remote_version, '.0');
						if ( $local_version != $remote_version ) {
							$count = $count + 1;
							update_site_option('wdp_un_updates_available', 'yes');
						}
					}
				}
			}
			update_site_option('wdp_un_updates_available_count', $count);
		}
	}
}

function update_notificiations_check() {
	if ( is_site_admin() ) {
		$last_run = get_site_option('wdp_un_last_run');
		$now = time();
		if ( empty( $last_run ) ) {
			//first run
			update_notificiations_process();
			
			update_site_option('wdp_un_last_run', $now);
		} else {
			$time_ago = $now - $last_run;
			if ( $time_ago > 43200 ) {
			//if ( $time_ago > 1 ) {
				update_notificiations_process();
				update_site_option('wdp_un_last_run', $now);
			}
		}
	}
}

function update_notificiations_notice_output() {
	global $update_notificiations_enable_admin_notices;

	if ( is_site_admin() ) {
		$count = get_site_option('wdp_un_updates_available_count');
		if ( empty( $count ) ) {
			$count = 0;
		}
		if ( $count > 0 && $_GET['page'] != 'wpmudev' && $update_notificiations_enable_admin_notices == 'yes'){
			echo '<div id="message" class="error"><p><strong>Site Admin Notice</strong>: <a href="wpmu-admin.php?page=wpmudev">' . stripslashes( get_site_option('wdp_un_text_admin_notice') ) . '</a></p></div>';
		}
	}
}

function update_notificiations_plug_pages() {
	global $wpdb, $wp_roles, $current_user;
	if ( is_site_admin() ) {
		$count = get_site_option('wdp_un_updates_available_count');
		if ( empty( $count ) ) {
			$count = 0;
		}
		if ( $count > 0 ) {
			$count_output = ' <span class="updates-menu"><span class="update-plugins"><span class="updates-count count-' . $count . '">' . $count . '</span></span></span>';
		} else {
			$count_output = ' <span class="updates-menu"></span>';
		}
		add_submenu_page('wpmu-admin.php', 'WPMU Dev' . $count_output, 'WPMU Dev' . $count_output, 10, 'wpmudev', 'update_notificiations_page_output');
	}
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function update_notificiations_page_output() {
	global $wpdb, $current_site, $update_notificiations_version, $update_notificiations_server_url;
	
	if(!current_user_can('edit_users')) {
		echo "<p>Nice Try...</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			$data = get_site_option('wdp_un_last_response');
			$last_run = get_site_option('wdp_un_last_run');
			?>
			<h2><?php _e('WPMU Dev') ?></h2>
            <p><?php echo stripslashes(get_site_option('wdp_un_text_page_head')); ?></p>
			<h3><?php _e('Recently Released Plugins') ?></h3>
            <?php
			echo "
			<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
			<thead><tr>
			<th scope='col'>Name</th>
			<th scope='col'>Description</th>
			</tr></thead>
			<tbody id='the-list'>
			";
			$latest_plugins = array();
			if ( is_array( $data ) ) {
				$latest_plugins = $data['latest_plugins'];
			}
			if (count($latest_plugins) > 0){
				$class = ('alternate' == $class) ? '' : 'alternate';
				foreach ($latest_plugins as $latest_plugin){
				//=========================================================//
				echo "<tr class='" . $class . "'>";
				echo "<td valign='top'><strong><a target='_blank' href='" . $latest_plugin['url'] . "'>" . stripslashes($latest_plugin['title']) . "</a></strong></td>";
				echo "<td valign='top'>" . stripslashes($latest_plugin['short_description']) . "</td>";
				echo "</tr>";
				$class = ('alternate' == $class) ? '' : 'alternate';
				//=========================================================//
				}
			}
			?>
			</tbody></table>
			<h3><?php _e('Recently Released Themes') ?></h3>
            <?php
			echo "
			<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
			<thead><tr>
			<th scope='col'>Name</th>
			<th scope='col'>Description</th>
			</tr></thead>
			<tbody id='the-list'>
			";
			$latest_themes = array();
			if ( is_array( $data ) ) {
				$latest_themes = $data['latest_themes'];
			}
			if (count($latest_themes) > 0){
				$class = ('alternate' == $class) ? '' : 'alternate';
				foreach ($latest_themes as $latest_theme){
				//=========================================================//
				echo "<tr class='" . $class . "'>";
				echo "<td valign='top'><strong><a target='_blank' href='" . $latest_theme['url'] . "'>" . stripslashes($latest_theme['title']) . "</a></strong></td>";
				echo "<td valign='top'>" . stripslashes($latest_theme['short_description']) . "</td>";
				echo "</tr>";
				$class = ('alternate' == $class) ? '' : 'alternate';
				//=========================================================//
				}
			}
			?>
			</tbody></table>
			<h3><?php _e('Installed Plugins/Themes') ?></h3>
            <?php
			echo "
			<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
			<thead><tr>
			<th scope='col'>Name</th>
			<th scope='col'>Installed Version</th>
			<th scope='col'>Latest Version</th>
			<th scope='col' width='75px'></th>
			</tr></thead>
			<tbody id='the-list'>
			";
			$projects = array();
			if ( is_array( $data ) ) {
				$remote_projects = $data['latest_versions'];
				$local_projects = get_site_option('wdp_un_local_projects');
				$local_project_ids = get_site_option('wdp_un_local_project_ids');
				if ( is_array( $local_projects ) ) {
					foreach ( $remote_projects as $remote_project ) {
						foreach ( $local_projects as $local_project ) {
							if ( $remote_project['id'] == $local_project['id'] ) {
								$projects[$remote_project['id']]['title'] = $remote_project['title'];
								$projects[$remote_project['id']]['url'] = $remote_project['url'];
								$projects[$remote_project['id']]['remote_version'] = $remote_project['version'];
								$projects[$remote_project['id']]['local_version'] = $local_project['version'];
							}
						}
					}
				}
			}
			if (count($projects) > 0){
				$class = ('alternate' == $class) ? '' : 'alternate';
				foreach ($projects as $project){
				$local_version = $project['local_version'];
				$local_version = trim($local_version, '.0');
				$local_version = trim($local_version, '.0');
				$remote_version = $project['remote_version'];
				$remote_version = trim($remote_version, '.0');
				$remote_version = trim($remote_version, '.0');
				
				$check = ($local_version == $remote_version) ? '' : "style='background-color:#FFEBE8;'";
				
				$upgrade_button_code = "<form style='display:inline;' method='get' action='" . $project['url'] . "'><input type='submit' value='Upgrade' class='button-secondary action' /></form> ";
				
				$upgrade_button = ($local_version == $remote_version) ? '' : $upgrade_button_code;
				
				//=========================================================//
				echo "<tr class='" . $class . "' " . $check . " >";
				echo "<td valign='top'><strong><a target='_blank' href='" . $project['url'] . "'>" . stripslashes($project['title']) . "</a></strong></td>";
				echo "<td valign='top'><strong>" . $local_version . "</strong></td>";
				echo "<td valign='top'><strong>" . $remote_version . "</strong></td>";
				echo "<td valign='top'>" . $upgrade_button . "</td>";
				echo "</tr>";
				$class = ('alternate' == $class) ? '' : 'alternate';
				//=========================================================//
				}
			}
			?>
			</tbody></table>
			<p><?php _e('Please note that all data is updated every twelve hours.') ?> <?php _e('Last updated:'); ?> <?php echo date(get_option('date_format') . ' ' . get_option('time_format'), $last_run); ?> GMT</p>
			<p><small>* <?php _e('Latest plugins, themes and installed plugins and themes above only refer to those provided to') ?> <a href="http://premium.wpmudev.org/join/"><?php _e('WPMU DEV members'); ?></a> <?php _e('by Incsub - free plugins and themes are not included here.'); ?></small></p>
            <?php
		break;
		//---------------------------------------------------//
		case "temp":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

?>
