<?php
/*
Plugin Name: Simple Trackback Validation
Plugin URI: http://sw-guide.de/wordpress/plugins/simple-trackback-validation/
Description: Eliminates spam trackbacks by (1) checking if the IP address of the trackback sender is equal to the IP address of the webserver the trackback URL is referring to and (2) by retrieving the web page located at the URL used in the trackback and checking if the page contains a link to your blog.
Version: 2.1
Author: Michael Woehrer
Author URI: http://sw-guide.de
 	    ____________________________________________________
       |                                                    |
       |        Simple Trackback Validation Plugin          |
       |                © Michael Woehrer                   |
       |____________________________________________________|

	© Copyright 2006-2007 Michael Woehrer (michael dot woehrer at gmail dot com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    *
Change Log:
2010-10-11  Version 2.1. Props @Ulrich SOSSOU. WordPress 3 compatibility. PHP errors fixed.

	----------------------------------------------------------------------------
	INSTALLATION, USAGE:
	Visit the plugin's homepage.
	--------------------------------------------------------------------------*/


////////////////////////////////////////////////////////////////////////////////
// Plugin options etc.
////////////////////////////////////////////////////////////////////////////////
$stbv_val; // stores some values just at execution time, will not be saved.

$stbv_opt = get_option('plugin_simple_tb_validation2');
if ( !is_array($stbv_opt) ) {
	// Options do not exist or have not yet been loaded so we define standard options
	$stbv_opt = array(
		'stbv_action' => 'moderation',
		'stbv_accuracy' => 'strict',
		'stbv_blogurls' => get_bloginfo('url'),
		'stbv_validateURL' => '1',
		'stbv_validateIP' => '1',
		'stbv_enablelog' => '',
		'stbv_addblockinfo' => '1',
		'stbv_moderrors' => '1',

	);
}

////////////////////////////////////////////////////////////////////////////////
// Apply the plugin
////////////////////////////////////////////////////////////////////////////////

# 'preprocess_comment' filter is applied to the comment data prior to any other
# processing, when saving a new comment in the database. Function arguments:
# comment data array, with indices "comment_post_ID", "comment_author",
# "comment_author_email", "comment_author_url", "comment_content",
# "comment_type", and "user_ID".
add_filter('preprocess_comment', 'stbv_main', 1, 1);

# Apply the admin menu


########################################################################################################################
#					PART 1: Main Function(s)
########################################################################################################################

////////////////////////////////////////////////////////////////////////////////
// Main Function, called by 'preprocess_comment'
////////////////////////////////////////////////////////////////////////////////
function stbv_main($incomingTB) {

	global $stbv_opt, $stbv_val;

	####################################
	# We only deal with trackbacks
	####################################
	if ( $incomingTB['comment_type'] != 'trackback' ) return $incomingTB;

	####################################
	# Get trackback information
	####################################
 	$stbv_val['comment_author'] = $incomingTB['comment_author'];
 	$stbv_val['comment_author_url'] = $incomingTB['comment_author_url'];
	$stbv_val['comment_post_permalink'] = get_permalink($incomingTB['comment_post_ID']);
	$stbv_val['comment_post_permalink'] = preg_replace('/\/$/', '', $stbv_val['comment_post_permalink']); // Remove trailing slash
	$stbv_val['comment_post_ID'] = $incomingTB['comment_post_ID'];

	####################################
	# Get Plugin options
	####################################
	if ($stbv_opt['stbv_accuracy'] == 'open') {
		if ( is_string($stbv_opt['stbv_blogurls']) ) {
			if (strlen($stbv_opt['stbv_blogurls']) > 9) {
				$stbv_blogurlsArray = explode(' ', $stbv_opt['stbv_blogurls']);
			}
		}
	}

	####################################
	# 'Is Spam' flag is FALSE by default. Below we check several things
	# and this flag will become true as soon as we have any doubts.
	####################################
	$stbv_val['is_spam'] = false;

	####################################
	# If a Snoopy problem occurrs (Snoopy can't be loaded or a snoopy error
	# occurred), this variable will be set to TRUE
	####################################
	$stbv_val['snoopy_problem'] = false;

	####################################
	# If Author's URL is not correct, it will be considered as spam.
	####################################
	if (!$stbv_val['is_spam'] && substr($stbv_val['comment_author_url'], 0, 4) != 'http') {
		$stbv_val['log_info'][]['warning'] = 'Author\'s URL was found not to be correct';
		$stbv_val['is_spam'] = true;
	}

	####################################
	# Phase 1 (IP) -  Verify IP address
	####################################
	if (!$stbv_val['is_spam'] && ($stbv_opt['stbv_validateIP'] == '1') ) {
		$tmpSender_IP = preg_replace('/[^0-9.]/', '', $_SERVER['REMOTE_ADDR'] );

		$authDomainname = stbv_get_domainname_from_uri($stbv_val['comment_author_url']);
		$tmpURL_IP = preg_replace('/[^0-9.]/', '', gethostbyname($authDomainname) );

		if ( $tmpSender_IP != $tmpURL_IP) {
			$stbv_val['log_info'][]['info'] = 'Sender\'s IP address (' . $tmpSender_IP . ') not equal to IP address of host (' . $tmpURL_IP . ').';
			$stbv_val['is_spam'] = true;
		} else {
			$stbv_val['log_info'][]['info'] = 'IP address (' . $tmpSender_IP . ') was found to be valid.';
		}

	} elseif ( $stbv_opt['stbv_validateIP'] != '1' ) {
		$stbv_val['log_info'][]['info'] = 'IP address validation (Phase 1) skipped since it is not enabled in the plugin\'s options.';
	}

	####################################
	# Phase 2 (URL) -  Snoopy
	####################################
 	if ( $stbv_opt['stbv_validateURL'] == '1' ) {

		# Loading snoopy and create snoopy object. In case of
		# failure it is being considered as spam, just in case.
		if (!$stbv_val['is_spam'] && !stbv_loadSnoopy() ) {
			// Loading snoopy failed
			$stbv_val['log_info'][]['warning'] = 'Loading PHP Snoopy class failed. Phase 2 skipped.';
			$stbv_val['snoopy_problem'] = true;
		} else {
			// Create new Snoopy object
			$stbvSnoopy = new Snoopy;
		}

		# Fetch all URLs of the author's web page
		if (!$stbv_val['is_spam'] && !$stbv_val['snoopy_problem'] && ! @$stbvSnoopy->fetchlinks($stbv_val['comment_author_url']) ) {
				// Snoopy couldn't couldn't reach the target website, Snoopy error occurred, or something else...
				$stbv_val['log_info'][]['warning'] = 'Snoopy couldn\t find something on the source website or Snoopy error occurred. Phase 2 skipped.';
				$stbv_val['snoopy_problem'] = true;
		} else {
			$stbvAuthorUrlArray = $stbvSnoopy->results;
		}

		# Check if URL array contains link to website
		if (!$stbv_val['is_spam'] && !$stbv_val['snoopy_problem'] && is_array($stbvAuthorUrlArray) ) {
			$loopSuccess = false;

			foreach ($stbvAuthorUrlArray as $loopUrl) {

				// Remove trailing slash, "/trackback" and "/trackback/"
				$loopUrl = preg_replace('/(\/|\/trackback|\/trackback\/)$/', '', $loopUrl);


				if ( ($stbv_opt['stbv_accuracy'] == 'open') && (is_array($stbv_blogurlsArray)) ) {
					// We have more than one URL to be checked
					$loopInnerSuccess = false;

					foreach ($stbv_blogurlsArray as $loopOptionsURL) {
						// Check if the first chars of the URL of remote page contain URL of the options
						if (substr($loopUrl, 0, strlen($loopOptionsURL)) == $loopOptionsURL) {
							$loopInnerSuccess = true;
							break;
						}
					}
					if ( $loopInnerSuccess ) {
						$loopSuccess = true;
						break;
					}
				} else {
					// Strict mode or no URLs provided so we check strictly the permalink only!
					if ( $loopUrl == $stbv_val['comment_post_permalink'] ) {
						$loopSuccess = true;
						break;
					}
				}
			}
			if ( !$loopSuccess ) {
				$stbv_val['log_info'][]['info'] = 'The target URL was not found on the source website, therefore the trackback is considered to be spam.';
				$stbv_val['is_spam'] = true;
			} else {
				$stbv_val['log_info'][]['info'] = 'The trackback is considered to be valid: URL was found on the source website.';
			}
		}

	} else {	// if ( $stbv_opt['stbv_validateURL'] == '1' )
		$stbv_val['log_info'][]['info'] = 'URL validation (Phase 2) skipped since it is not enabled in the plugin\'s options.';
	}

	####################################
	# Now we know if we have a trackback spam or not.
	####################################
	if (($stbv_opt['stbv_moderrors'] == '1') && $stbv_val['snoopy_problem']) {
		if ($stbv_opt['stbv_enablelog'] == '1') stbv_log_addentry('Trackback placed into comment moderation due to an occurred problem while retrieving URLs from source website.');
		if ($stbv_opt['stbv_addblockinfo'] == '1')	$incomingTB['comment_author'] = '[BLOCKED BY STBV] ' . $incomingTB['comment_author'];
		add_filter('pre_comment_approved', create_function('$a', 'return \'0\';'));
		return $incomingTB;
	} elseif ( !$stbv_val['is_spam'] ) {
		# **** No Trackback Spam ***
		if ($stbv_opt['stbv_enablelog'] == '1') stbv_log_addentry('Trackback approved.');
		return $incomingTB;
	} else {
		# **** It is Trackback Spam ***
		# We put trackback in moderation queue, mark as spam or delete right away
		switch ($stbv_opt['stbv_action']) {
			case 'delete':
				if ($stbv_opt['stbv_enablelog'] == '1') stbv_log_addentry('Trackback discarded.');
				die('Your trackback has been rejected.');
				break;
			case 'spam':
				if ($stbv_opt['stbv_enablelog'] == '1') 	stbv_log_addentry('Trackback marked as spam.');
				if ($stbv_opt['stbv_addblockinfo'] == '1')	$incomingTB['comment_author'] = '[BLOCKED BY STBV] ' . $incomingTB['comment_author'];
				add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
				return $incomingTB;
				break;
			default:
				if ($stbv_opt['stbv_enablelog'] == '1') stbv_log_addentry('Trackback placed into comment moderation.');
				if ($stbv_opt['stbv_addblockinfo'] == '1')	$incomingTB['comment_author'] = '[BLOCKED BY STBV] ' . $incomingTB['comment_author'];
				add_filter('pre_comment_approved', create_function('$a', 'return \'0\';'));
				return $incomingTB;
		}

	}


} // function stbv_main()


////////////////////////////////////////////////////////////////////////////////
// Load the Snoopy class.
// Returns TRUE if class is successfully loaded, FALSE otherwise.
////////////////////////////////////////////////////////////////////////////////
function stbv_loadSnoopy() {
	if ( !class_exists('Snoopy') ) {

		if (@include_once( ABSPATH . WPINC . '/class-snoopy.php' )) {
			return true;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

########################################################################################################################
#					PART 2: Administration
########################################################################################################################

////////////////////////////////////////////////////////////////////////////////
// Apply Options to Admin Menu
////////////////////////////////////////////////////////////////////////////////
function stbv_add_options_to_admin() {
    if (function_exists('add_options_page')) {
		add_options_page('Simple TB Validation', 'Simple TB Validation', 'manage_options', basename(__FILE__), 'stbv_adminOptions');
    }
}


////////////////////////////////////////////////////////////////////////////////
// This will add the new item, 'Simple TB Validation', to the Options menu.
////////////////////////////////////////////////////////////////////////////////
function stbv_adminOptions() {

	global $stbv_opt;

	add_option('plugin_simple_tb_validation2', $stbv_opt, 'Simple Trackback Validation Plugin Options');

	/* Check form submission and update options if no error occurred */
	if (isset($_POST['submit']) ) {

		// Options array
		$stbv_opt_update = array (
			'stbv_blogurls' => stbv_txtLineBreakToWhiteSpace($_POST['stbv_blogurls']),
			'stbv_action' => $_POST['stbv_action'],
			'stbv_accuracy' => $_POST['stbv_accuracy'],
			'stbv_validateURL' => $_POST['stbv_validateURL'],
			'stbv_validateIP' => $_POST['stbv_validateIP'],
			'stbv_enablelog' => $_POST['stbv_enablelog'],
			'stbv_addblockinfo' => $_POST['stbv_addblockinfo'],
			'stbv_moderrors' => $_POST['stbv_moderrors'],
		);
		update_option('plugin_simple_tb_validation2', $stbv_opt_update);

	}

	/* Get options */
	$stbv_opt = get_option('plugin_simple_tb_validation2');


	?>

	<style type="text/css">
		table#outer { width: 100%; border: 0 none; padding:0; margin:0; }
		table#outer td.left, table#outer td.right { vertical-align:top; }
		table#outer td.left {  padding: 0 10px 0 0; }
		table#outer td.right { width: 200px; padding: 0 0 0 10px; }
		.right a { background: no-repeat; padding-left: 20px; border: 0 none; }
		.right a.lhome { background-image:url(<?php echo stbv_get_resource_url('sw-guide.png'); ?>); }
		.right a.lpaypal { background-image:url(<?php echo stbv_get_resource_url('paypal.png'); ?>); }
		.right a.lamazon { background-image:url(<?php echo stbv_get_resource_url('amazon.png'); ?>); }
		.right a.lwp { background-image:url(<?php echo stbv_get_resource_url('wp.png'); ?>); }

		/* SIDEBAR */
		td.right dl { border: 1px solid #f4f4f4; margin:0 0 20px 0; padding: 1px; }  /* Box */
		td.right dt { background-color: #247fab; color: white; display:block; margin:0; padding:2px 5px; }  /* Title */
		td.right dd { display:block; margin:0; padding:5px 10px; }  /* Content */
		td.right dd ul, td.right dd ul li { list-style: none; margin:0; padding:0; background: 0 none; }
		td.right dd ul li { padding:3px 0;  }
		td.right dd p { margin: 0; padding:0; }
		td.right dd p.donate { font-size:90%; }

		.wrap h3 { color: black; background-color: #e5f3ff; padding: 4px 8px; }
		.wrap h3.log { background: 0 none; }
		div.additional { margin-left: 30px; }
		span.info { color: blue; }
		span.warning {color: red; }
		span.action { font-weight: bold; }
		table#logtable { width: 100%; border: 1px solid #dfdfdf; }
		table#logtable tr, table#logtable tr td { font-size: 95%; }
		table#logtable tr th { background-color: #dfdfdf; padding: 2px; height: 30px; }
		table#logtable td { background-color: #f1f1f1; padding: 2px; }
		table#logtable td.alt { background-color: #ffffff; }


	</style>


	<div class="wrap">

		<h2>Simple Trackback Validation</h2>

		<table id="outer"><tr><td class="left">
		<!-- *********************** BEGIN: Main Content ******************* -->

		<? if (stbv_isOldWordpress()) echo '<p style="color: red; font-weight: bold">You are using an outdated Wordpress version which is not supported by this plugin. Get the latest version at <a href="http://wordpress.org/download/">wordpress.org</a>.</p>'; ?>
		<form name="form1" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>&updated=true">
		<fieldset class="options">
			<h3>How to deal with spam trackbacks?</h3>
				<p style="float:right;" class="submit"><input type="submit" name="submit" value="<?php _e('Update Options &raquo;') ?>" /></p>
				<input id="radioa1" type="radio" name="stbv_action" value="moderation" <?php echo ($stbv_opt['stbv_action']=='moderation'?'checked="checked"':'') ?> />
				<label for="radioa1">Place into comment moderation queue</label> <span style="color: grey; font-size: 90%;"> (<a href="<?php bloginfo('url'); ?>/wp-admin/moderation.php">Awaiting Moderation &raquo;</a>)</span>
				<br />
				<input id="radioa2" type="radio" name="stbv_action" value="spam" <?php echo ($stbv_opt['stbv_action']=='spam'?'checked="checked"':'') ?> />
				<label for="radioa2">Mark as spam</label> <span style="color: grey; font-size: 90%;">(a plugin like <a href="http://akismet.com/">Akismet</a> will be needed to access to these trackbacks)</span>
				<br />
				<input id="radioa3" type="radio" name="stbv_action" value="delete" <?php echo ($stbv_opt['stbv_action']=='delete'?'checked="checked"':'') ?> />
				<label for="radioa3">Discard trackback <span style="color: grey; font-size: 90%;">(trackback will not be saved in the database)</span></label>

				<div class="additional">

					<p><strong>Additional options:</strong></p>

					<input name="stbv_addblockinfo" type="checkbox" id="stbv_addblockinfo" value="1" <?php checked('1', $stbv_opt['stbv_addblockinfo']); ?>"  />
					<label for="stbv_addblockinfo">Add prefix [BLOCKED BY STBV] to author's name if trackback is placed into moderation or marked as spam</label>
					<br /><span style="margin-left: 20px; color: grey; font-size: 90%;">This option is helpful if you want to see in the comment moderation or akismet spam list which trackbacks were blocked by Simple Trackback Validation.</span>

				</div> <!-- [additional] -->


			<h3>Validation Phase 1: IP Address</h3>

				<input name="stbv_validateIP" type="checkbox" id="stbv_validateIP" value="1" <?php checked('1', $stbv_opt['stbv_validateIP']); ?>"  />
				<label for="stbv_validateIP"><strong>Validate IP Address</strong></label>
				<br /><span style="margin-left: 20px; color: grey; font-size: 90%;">
				Checks if the IP address of the trackback sender is equal to the IP address of the webserver the trackback URL is referring to.
				This should reveal many spam trackbacks.</span>

			<h3>Validation Phase 2: URL</h3>

				<input name="stbv_validateURL" type="checkbox" id="stbv_validateURL" value="1" <?php checked('1', $stbv_opt['stbv_validateURL']); ?>"  />
				<label for="stbv_validateURL"><strong>Validate URL</strong></label>
				<br /><span style="margin-left: 20px; color: grey; font-size: 90%;">Retrieves the web page located at the URL included in the trackback to check if it contains a link to your blog</span>

				<div class="additional">

					<p><strong>Strictness:</strong></p>

					<input id="radiob1" type="radio" name="stbv_accuracy" value="strict" <?php echo ($stbv_opt['stbv_accuracy']!='open'?'checked="checked"':'') ?> />
					<label for="radiob1">Strict: A permalink needs to be used</label>
					<p style="margin-left: 35px; color: grey; font-size: 90%;">That means that e.g. a link to your blog's home (<?php bloginfo('url'); ?>) will not be accepted;
					If the permalink of your post is not available on the trackback's source page, the trackback is considered as spam.
					</p>
					<input id="radiob2" type="radio" name="stbv_accuracy" value="open" <?php echo ($stbv_opt['stbv_accuracy']=='open'?'checked="checked"':'') ?> />
					<label for="radiob2">Any link beginning with the following URLs is allowed:</label>
					<br />
					<textarea style="margin: 10px 0 0 35px" name="stbv_blogurls" id="stbv_blogurls" cols="100%" rows="2" ><?php echo stbv_txtWhiteSpaceToLineBreak($stbv_opt['stbv_blogurls']); ?></textarea>
					<p style="margin-left: 35px; color: grey; font-size: 90%;">
					Separate multiple URLs with new lines.<br />
					If you enter for example your blog's URL (<span style="color: #073991;"><?php bloginfo('url'); ?></span>), any link that begins with that URL,
					e.g. "<em><?php bloginfo('url'); ?>/about-me/</em>, will be accepted, even if it is completely different to the actual permalink of the post.
					</p>

				</div> <!-- [additional] -->

				<div class="additional">

					<p><strong>Additional options:</strong></p>

					<input name="stbv_moderrors" type="checkbox" id="stbv_moderrors" value="1" <?php checked('1', $stbv_opt['stbv_moderrors']); ?>"  />
					<label for="stbv_moderrors">Moderate in case of errors</label>
					<br /><span style="margin-left: 20px; color: grey; font-size: 90%;">If an error occurrs while fetching the links
					from the website (e.g. website currently not available), the trackback is considered to be spam. By enabling this option,
					the trackbacks are being placed into moderation in this case.</span>

				</div> <!-- [additional] -->


			<h3>Other Options</h3>
				<ul style="list-style: none; padding:0; margin:0;">

					<li>
						<input name="stbv_enablelog" type="checkbox" id="stbv_enablelog" value="1" <?php checked('1', $stbv_opt['stbv_enablelog']); ?>"  />
						<label for="stbv_enablelog">Enable Log (latest 50 trackbacks will appear below)</label>
						<br /><span style="margin-left: 20px; color: grey; font-size: 90%;">(Disable this option and click "<?php _e('Update Options &raquo;') ?>" to empty the log)</span>
					</li>


				</ul>
		</fieldset>

		<p class="submit"><input type="submit" name="submit" value="<?php _e('Update Options &raquo;') ?>" /></p>

		</form>

		<!-- The log -->
		<?php stbv_log_display(); ?>

		<!-- *********************** END: Main Content ********************* -->
		</td><td class="right">
		<!-- *********************** BEGIN: Sidebar ************************ -->

		<dl>
		<dt>Plugin</dt>
		<dd>
			<ul>
				<li><a class="lhome" href="http://sw-guide.de/wordpress/plugins/simple-trackback-validation/">Plugin's Homepage</a></li>
				<li><a class="lwp" href="http://wordpress.org/support/">WordPress Support</a></li>
			</ul>
		</dd>
		</dl>

		<dl>
		<dt>Donation</dt>
		<dd>
			<ul>
				<li><a class="lpaypal" href="http://sw-guide.de/donation/paypal/">Donate via PayPal</a></li>
				<li><a class="lamazon" href="http://sw-guide.de/donation/amazon/">My Amazon Wish List</a></li>
			</ul>
			<p class="donate">I spend a lot of time on the plugins I've written for WordPress.
			Any donation would by highly appreciated.</p>

		</dd>
		</dl>


		<dl>
		<dt>Miscellaneous</dt>
		<dd>
			<ul>
				<li><a class="lhome" href="http://sw-guide.de/wordpress/plugins/">WP Plugins I've Written</a></li>
			</ul>
		</dd>
		</dl>



		<!-- *********************** END: Sidebar ************************ -->
		</td></tr></table>



		<p style="margin-top: 30px; text-align: center; font-size: .85em;">&copy; Copyright 2006-2007&nbsp;&nbsp;<a href="http://sw-guide.de">Michael W&ouml;hrer</a></p>

	</div> <!-- [wrap] -->
	<?php


} // function mwli_options_subpanel



########################################################################################################################
#					PART 3: Miscellaneous Functions
########################################################################################################################


////////////////////////////////////////////////////////////////////////////////
// Converts textarea content (separated by line break) to space separated string
// since we want to store it like this in MySQL
////////////////////////////////////////////////////////////////////////////////
function stbv_txtLineBreakToWhiteSpace($input) {

	// Replace multiple whitespaces with only one space
	$input = preg_replace('/\s\s+/', ' ', $input);

	// Replace white space with line break
	$input = str_replace(' ', "\n", $input);

	// Replace linebreaks with white space, considering both \n and \r
	$input = preg_replace("/\r|\n/s", ' ', $input);

	// Create result. We create an array and loop thru it but do not consider empty values.
	$sourceArray = explode(' ', $input);
	$loopcount = 0;
	$result = '';
	foreach ($sourceArray as $loopval) {

		if ($loopval <> '') {

			// Clean URL (it's a Wordpress function)
			$loopval = clean_url($loopval);

			// Create separator
			$sep = '';
			if ($loopcount >= 1) $sep = ' ';

			// result
			$result .= $sep . $loopval;

			$loopcount++;
		}
	}
	return $result;

}


////////////////////////////////////////////////////////////////////////////////
// Replace white space with new line for displaying in text area
////////////////////////////////////////////////////////////////////////////////
function stbv_txtWhiteSpaceToLineBreak($input) {

	$output = str_replace(' ', "\n", $input);

	return $output;

}

////////////////////////////////////////////////////////////////////////////////
// Check if the current Wordpress installation is 1.x.
////////////////////////////////////////////////////////////////////////////////
function stbv_isOldWordpress() {
	global $wp_version;
	if (preg_match("/^1\./", $wp_version)) {
		return true;
	} else {
		return false;
	}
}


////////////////////////////////////////////////////////////////////////////////
// Add log entry
////////////////////////////////////////////////////////////////////////////////
function stbv_log_addentry($logmsg) {

	global $stbv_val;

	# log only the last x lines in the DB
	if ($log = get_option('plugin_simple_tb_validation2_log')) {
		if ( ! is_array($log) )  $log = array();
		if (count($log) >= 50)  array_splice($log, 0, -50);

		$logentry_arr = array(
			'time' => time(),
			'msg' => $logmsg,
			'log_info' => $stbv_val['log_info'],
			'comment_author_url' => $stbv_val['comment_author_url'],
			'comment_author' => $stbv_val['comment_author'],
			'comment_post_permalink' => $stbv_val['comment_post_permalink'],
			'comment_post_ID' => $stbv_val['comment_post_ID'],
		);
		$log[] = $logentry_arr;
		update_option('plugin_simple_tb_validation2_log', $log);
	} else {
		add_option('plugin_simple_tb_validation2_log', 'empty', 'Simple Trackback Validation Plugin: Log', 'no');
	}

}

////////////////////////////////////////////////////////////////////////////////
// Display log
////////////////////////////////////////////////////////////////////////////////
function stbv_log_display() {

	global $stbv_opt;

	if ($stbv_opt['stbv_enablelog'] == '1') {

		wp_cache_delete('plugin_simple_tb_validation2_log', 'options');

        $result = '';

		$log = get_option('plugin_simple_tb_validation2_log');
		if ( is_array($log) ) {

			$result .= '
			<table id="logtable">
			<tr>
				<th scope="col">When</th>
				<th scope="col">From/To</th>
				<th scope="col">Action(s)</th>
			</tr>';


			$log = array_reverse($log);
			$count = 0;
			foreach($log as $logline) {

				// Format input or get values
				$logline['time'] = date(get_settings('date_format'), floatval($logline['time'])) . ', ' . date(get_settings('time_format'), floatval($logline['time']));
				$logline['comment_post_title'] = get_the_title($logline['comment_post_ID']);

				// Generate output
				$tdstyle = ($count%2 != 0) ? '<td class="alt">' : '<td>';


				$result .= '
				  <tr>
				    ' . $tdstyle . $logline['time'] . '</td>
				    ' . $tdstyle . 'From: <a href="' . $logline['comment_author_url'] . '">' . $logline['comment_author'] . '</a><br />
				    	To: <a href="' . $logline['comment_post_permalink'] . '">' . $logline['comment_post_title'] . '</a>
					</td>
					';
				$tmpinf = '';
				if ( is_array($logline['log_info']) ) {
					foreach($logline['log_info'] as $lentry) {
					    foreach ($lentry as $ltype => $lmsg) {
							$tmpinf .= '<span class="' . $ltype . '">' . ucfirst($ltype) . '</span>: ' . $lmsg . '<br />' . "\n\t\t\t\t\t";
					    }
					}
				}

				$result .= $tdstyle . $tmpinf . '<span class="action">Action: </span>' . $logline['msg'] . '</td>
				  </tr>';

				$count++;
			}
			$result .= '</table>';
		} else {
			$result .= '<p>No trackbacks have been processed since you\'ve enabled the log.</p>';
		}

		echo '<h3 class="log">Latest 50 Trackbacks:</h3>' . "\n" . $result;

	} else {
		update_option('plugin_simple_tb_validation2_log', 'empty');
	}

}


////////////////////////////////////////////////////////////////////////////////
// Retrieves domain name from URI.
// Input:  URI, e.g. http://www.site.com/bla/bla.php
// Output: domain name, e.g. www.site.com
////////////////////////////////////////////////////////////////////////////////
function stbv_get_domainname_from_uri($uri) {
    $exp1 = '/^(http|https|ftp)?(:\/\/)?([^\/]+)/i';
	preg_match($exp1, $uri, $matches);
	if (isset($matches[3])) {
		return $matches[3];
    } else {
		return '';
	}
}


////////////////////////////////////////////////////////////////////////////////
// Icons
////////////////////////////////////////////////////////////////////////////////
if(isset($_GET['resource']) && !empty($_GET['resource'])) {
	# base64 encoding performed by base64img.php from http://php.holtsmark.no
	$resources = array(
		'paypal.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAFfKj/FAAAAB3RJTUUH1wYQEhELx'.
			'x+pjgAAAAlwSFlzAAALEgAACxIB0t1+/AAAAARnQU1BAACxjwv8YQUAAAAnUExURZ'.
			'wMDOfv787W3tbe55y1xgAxY/f39////73O1oSctXOUrZSlva29zmehiRYAAAABdFJ'.
			'OUwBA5thmAAAAdElEQVR42m1O0RLAIAgyG1Gr///eYbXrbjceFAkxM4GzwAyse5qg'.
			'qEcB5gyhB+kESwi8cYfgnu2DMEcfFDDNwCakR06T4uq5cK0n9xOQPXByE3JEpYG2h'.
			'KYgHdnxZgUeglxjCV1vihx4N1BluM6JC+8v//EAp9gC4zRZsZgAAAAASUVORK5CYI'.
			'I=',
		'amazon.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAFfKj/FAAAAB3RJTUUH1wYQESUI5'.
			'3q1mgAAAAlwSFlzAAALEgAACxIB0t1+/AAAAARnQU1BAACxjwv8YQUAAABgUExURe'.
			'rBhcOLOqB1OX1gOE5DNjc1NYKBgfGnPNqZO4hnOEM8NWZSN86SO1pKNnFZN7eDOuW'.
			'gPJRuOVBOTpuamo+NjURCQubm5v///9rZ2WloaKinp11bW3Z0dPPy8srKyrSzs09b'.
			'naIAAACiSURBVHjaTY3ZFoMgDAUDchuruFIN1qX//5eNYJc85EyG5EIBBNACEibsi'.
			'mi5UaUURJtI5wm+KwgSJflVkOFscBUTM1vgrmacThfomGVLO9MhIYFsF8wyx6Jnl8'.
			'8HUxEay+wYmlM6oNKcNYrIC58iHMcIyQlZRNmf/2LRQUX8bYwh3PCYWmOGrueargd'.
			'XGO5d6UGm5FSmBqzXEzK2cN9PcXsD9XsKTHawijcAAAAASUVORK5CYII=',
		'sw-guide.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAFfKj/FAAAAB3RJTUUH1wYQEhckO'.
			'pQzUQAAAAlwSFlzAAALEgAACxIB0t1+/AAAAARnQU1BAACxjwv8YQUAAABFUExURZ'.
			'wMDN7e3tbW1oSEhOfn54yMjDk5OTExMWtra7W1te/v72NjY0pKSs7OzpycnHNzc8b'.
			'Gxr29vff3962trVJSUqWlpUJCQkXEfukAAAABdFJOUwBA5thmAAAAlUlEQVR42k2O'.
			'WxLDIAwD5QfQEEKDob3/UevAtM1+LRoNFsDgCGbEAE7ZwBoe/maCndaRyylQTQK2S'.
			'XPpXjTvq2osRUCyAPEEaKvM6LWFKcFGnCI1Hc+WXVRFk07ROGVBoNpvVAJ3Pzjee5'.
			'7fdh9dfcUItO5UD8T6aVs69jheJlegFyFmPlj/wZZC3ssKSH+wB9/9C8IH45EIdeu'.
			'A/YIAAAAASUVORK5CYII=',
		'wp.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAFfKj/FAAAAB3RJTUUH1wYQEiwG0'.
			'0adjQAAAAlwSFlzAAALEgAACxIB0t1+/AAAAARnQU1BAACxjwv8YQUAAABOUExURZ'.
			'wMDN7n93ut1kKExjFjnHul1tbn75S93jFrnP///1qUxnOl1sbe71KMxjFrpWOUzjl'.
			'7tYy13q3G5+fv95y93muczu/39zl7vff3//f//9Se9dEAAAABdFJOUwBA5thmAAAA'.
			's0lEQVR42iWPUZLDIAxDRZFNTMCllJD0/hddktWPRp6x5QcQmyIA1qG1GuBUIArwj'.
			'SRITkiylXNxHjtweqfRFHJ86MIBrBuW0nIIo96+H/SSAb5Zm14KnZTm7cQVc1XSMT'.
			'jr7IdAVPm+G5GS6YZHaUv6M132RBF1PopTXiuPYplcmxzWk2C72CfZTNaU09GCM3T'.
			'Ww9porieUwZt9yP6tHm5K5L2Uun6xsuf/WoTXwo7yQPwBXo8H/8TEoKYAAAAASUVO'.
			'RK5CYII=',
	); // $resources = array

	if(array_key_exists($_GET['resource'],$resources)) {

		$content = base64_decode($resources[ $_GET['resource'] ]);

		$lastMod = filemtime(__FILE__);
		$client = ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false );
		// Checking if the client is validating his cache and if it is current.
		if (isset($client) && (strtotime($client) == $lastMod)) {
			// Client's cache IS current, so we just respond '304 Not Modified'.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
			exit;
		} else {
			// Image not cached or cache outdated, we respond '200 OK' and output the image.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
			header('Content-Length: '.strlen($content));
			header('Content-Type: image/' . substr(strrchr($_GET['resource'], '.'), 1) );
			echo $content;
			exit;
		}
	}
}

////////////////////////////////////////////////////////////////////////////////
// Display Icons
////////////////////////////////////////////////////////////////////////////////
function stbv_get_resource_url($resourceID) {
	return trailingslashit(get_bloginfo('siteurl')) . '?resource=' . $resourceID;
}



?>
