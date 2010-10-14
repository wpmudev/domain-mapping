<?php
/*
Plugin Name: Peter's Custom Anti-Spam
Plugin URI: http://www.theblog.ca/?p=21
Description: Stop a lot of spambots from polluting your site by making visitors identify a random word displayed as an image before commenting. You can customize the pool of words to display. This was originally based on <a href="http://www.infor96.com/~nio/archives/369">Anti-Spam Image</a> by Krazy Nio. Core of the Version 2.0 rewrite by <a href="http://WillMurray.name/">Will Murray</a>.
Author: Peter Keung
Version: 2.83
Author URI: http://www.theblog.ca/
Change Log:
2010-10-11  Version 2.81. Props @Ulrich SOSSOU. WordPress 3 compatibility. PHP errors fixed.
2007-07-02  Version 2.82. Changed the default settings so that trackbacks and pingbacks are enabled by default and added the option to send one or both types to a moderation queue (Thanks to Donovan for feedback and MtDewVirus for unknowingly providing an example). With TTF fonts, the font size will automatically decrease if the word is slightly too tall for the image box (similar to the version 2.8 feature but for height).
2007-06-27  Version 2.81. Bug fix where the admin menu was producing a silent error (thanks to wirelessguru).
2007-06-17  Version 2.8. With TTF fonts, the font size will now automatically decrease (to a certain extent) if the word is slightly too long for the image box (thanks to LaTomate for introducing the imagettfbbox function to me). Also added a diagnosis page under Manage > Custom anti-spam which should be able to diagnose the server setup to identify most common problems with the script.
2007-03-24  Version 2.7. Added a workaround for users who get a "get_option function not defined" error, a workaround for users with an open_basedir restriction, and a new font.
2007-02-10  Version 2.61. Tweaked the logged in user verification in order to be compatible with the wp-polls plugins (thanks to Troy and... GaMerZ).
2006-12-07  Version 2.6. Added the ability for users to selectively allow either pingbacks or trackbacks or both, instead of having to either allow both or block both.
2006-11-17  Version 2.5. Changed the way comments are rejected for better compatibility with other plugins and for a slight performance increase. Also tweaked some stuff to be XHTML compliant (thanks to Ajay).
2006-10-21  Version 2.2. Added compatibility with the backslash directory separator (thanks to Ergin).
2006-10-14  Version 2.1. Increased default width of image and allowed customization of border color.
2006-09-15  Version 2.0. Core rewrite by Will Murray (http://WillMurray.name/):
	* Added ability to use multiple, random fonts.
	* Added random text color selection.
	* Added limited ability to pick background color (white or black) for better compatibility with light or dark themes.
	* Added custom (not yet dynamic) image sizes.
	* Added the option to output PNG (clearer text, but may break older browsers -- your choice)
	* Added additional input validation for improved security.
	* Improved speed and efficiency by adding conditionals and reducing redundant code.
	* Added "cas_" prefix to custom_anti_spam variables to avoid conflicts with other plugins.
	* Added self-checking of plugin location (placing the plugin inside a subfolder of wp-content/plugins is now suggested).
	* Added ability to forgo basic JavaScript positioning and insert the file yourself (Thanks to TerminalDigit)
View all previous change items at http://www.theblog.ca/?page_id=50
*/

// -----------------------------------------------------------------------------------
// Customize these parts if you want to change the defaults.

$cas_text = array(); // <-- This line should not be changed
// List as many words as you like, one per line
// If you want some words to be used more often, enter them multiple times.
// It is best to use words that are eight letters or less
$cas_text[] = "school";
$cas_text[] = "blogs";
$cas_text[] = "edu";
$cas_text[] = "gidday";
$cas_text[] = "nospam";
$cas_text[] = "chill";
$cas_text[] = "whoa";
$cas_text[] = "blocked";
$cas_text[] = "enjoy";
$cas_text[] = "feast";

// Set this to equal TRUE if you want to force registered users to enter the anti-spam word as well.
$cas_forcereg = false;

// Set this to equal TRUE if you want to allow trackbacks (but be vulnerable to trackback spam)
$cas_allowtrack = true;

// Set this to equal TRUE if you want to send all trackbacks to the moderation queue (only works if the above setting is TRUE)
$cas_modtrack = false;

// Set this to equal TRUE if you want to allow pingbacks (but be vulnerable to pingback spam)
$cas_allowping = true;

// Set this to equal TRUE if you want to send all pingbacks to the moderation queue (only works if the above setting is TRUE)
$cas_modping = false;

// Set the path to the font(s).
// Empty string ("") defaults to the root of your blog.
// If you add a path, DO include the trailing slash.
$cas_fontpath = "wp-content/mu-plugins/custom-anti-spam/";

$cas_fontlist = array(); // <-- This line should not be changed
// List as many TrueType font(s) as you like, one per line. Drop your own font files into this plugin's directory.
// If you are using your own fonts, make sure all fonts used are about the same default size.
// If you want some fonts to be used more frequently, enter them multiple times.
// Default freeware fonts from font101.com
$cas_fontlist[] = "wesley.ttf";
$cas_fontlist[] = "beatty.ttf";
$cas_fontlist[] = "ateliersans.ttf";
$cas_fontlist[] = "worm.ttf";

// Set the anti-spam image width and height.
// You may need to increase these sizes for longer words and/or bigger fonts.
$cas_imgwidth = 160;
$cas_imgheight = 50;

// Set this to TRUE if you want to use random text colors.
// If random colors are not selected, blue text will appear on a white background, and white text will appear on black background (as decided in the next option)
$cas_randomcolors = true;

// Set the background color for the anti-spam image.
// Choose either "black" or "white"
$cas_bgcolorset = "white";

// Set the border color for the anti-spam image.
// Write either major colors (red, green, blue, etc.) or enter the HTML color code (such as #C0C0C0)
$cas_borderclr = "black";

// Set this to TRUE if you prefer PNG graphics (better quality text)
// Set this to FALSE if you prefer more compatable graphics (PNG crashes IE 4; JPEG does not)
$cas_UsePngNotJpeg = false;

// Set this to TRUE if you will be editing your comments file (add this php line wherever you want the anti-spam image inserted in the comments.php file: do_action('secure_image', $post->ID); )
// Set this to FALSE if you want to use the default Javascript positioning
$cas_manualinsert = false;

// Leave this as FALSE unless you are instructed by an error message to "The site administrator needs to manually configure his/her site address in the plugin configuration file!" In that case, enter your site address, which should be the same as the WordPress address from your Options page. Enter it in quotes like the commented line below, withOUT a trailing slash.
// $cas_siteurl = "http://yoursiteaddress/whatever";
$cas_siteurl = false;

// Leave this as FALSE unless you have an open_basedir restriction on your server and can't get it removed. In that case, enter the server path to your blog (different from the site address!) like in the example below WITH a trailing slash.
// $cas_abspath = "/home/yourusername/public_html/blog/";
$cas_abspath = false;

// -----------------------------------------------------------------------------------
// You should not need to (and probably shouldn't) edit anything from here to the end.

// Determine the ABSPATH if it is not already defined (i.e., when called to generate the image)
if( ! defined( 'ABSPATH' ) && ! $cas_abspath )
{
	if (DIRECTORY_SEPARATOR=='/') {
		$abspath = dirname(__FILE__).'/';
	}
	else {
		$abspath = str_replace('\\', '/', dirname(__FILE__)).'/';
	}
	$absarray = explode( "/", $abspath );
	$abspath = "";
	foreach( $absarray as $value )
	{
		$abspath = $abspath . $value . "/";
		if( file_exists( $abspath . "wp-config.php" ) )
		{
			if( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', $abspath, true );
		}
	}
}
if( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', $cas_abspath );
$cas_fontpath = ABSPATH . $cas_fontpath;

// Determine how many words were entered
$cas_textcount = count( $cas_text );
// Copy the first element to a new last element
$cas_text[] = $cas_text[0];
// Set the first element to invalid
$cas_text[0] = "* * * INVALID * * *";

// Output the antispam image
if( isset( $_GET['antiselect'] ) )
{
	// Pick a random font to use
	$cas_font = $cas_fontpath . $cas_fontlist[ rand( 0, count( $cas_fontlist ) - 1 ) ];

	// Set the default colors for when random text colors are not selected
	if ( $cas_bgcolorset == "white") {
	$cas_textcolor = array( 0, 0, 255 ); // blue text
	$cas_bgcolor = array( 255, 255, 255); // white background
	}
	else {
	$cas_textcolor = array( 255, 255, 255 ); // white text
	$cas_bgcolor = array( 0, 0, 0); // black background
	}

	// If selected, pick a random color for the antispam word text
	if( $cas_randomcolors )
	{
		$cas_rand = rand( 0, 4 );
		switch( $cas_bgcolorset )
			{
				case "white":
					$cas_textcolorchoice[0] = array ( 0, 0, 255 ); // blue
					$cas_textcolorchoice[1] = array ( 0, 153, 0 ); // greenish
					$cas_textcolorchoice[2] = array ( 204, 0, 0 ); // reddish
					$cas_textcolorchoice[3] = array ( 203, 0, 154 ); // purplish
					$cas_textcolorchoice[4] = array ( 0, 0, 0 ); // black
					$cas_textcolor = $cas_textcolorchoice[$cas_rand];
					break;
				default:
					$cas_textcolorchoice[0] = array ( 255, 255, 0 ); // yellow
					$cas_textcolorchoice[1] = array ( 0, 255, 255 ); // blueish
					$cas_textcolorchoice[2] = array ( 255, 153, 204 ); // pinkish
					$cas_textcolorchoice[3] = array ( 102, 255, 102 ); // greenish
					$cas_textcolorchoice[4] = array ( 255, 255, 255 ); // white
					$cas_textcolor = $cas_textcolorchoice[$cas_rand];
					break;
			}
	}

	// Validate the input values
	$cas_antiselect = intval( $_GET['antiselect'] );
	if( $cas_antiselect < 1 || $cas_antiselect > $cas_textcount ) $cas_antiselect = 0;
	$cas_antispam = $cas_text[ $cas_antiselect ];

	// Start building the image
	$cas_image = @imagecreate( $cas_imgwidth, $cas_imgheight ) or wp_die("Cannot Initialize new GD image stream");
	$cas_bgcolor = imagecolorallocate( $cas_image, $cas_bgcolor[0], $cas_bgcolor[1], $cas_bgcolor[2] );
	$cas_fontcolor = imagecolorallocate( $cas_image, $cas_textcolor[0], $cas_textcolor[1], $cas_textcolor[2] );

	// Check for freetype lib, if not found default to ugly built in capability using imagechar (Lee's mod)
	// Also check that the chosen TrueType font is available
	if( function_exists( 'imagettftext' ) && file_exists( $cas_font ) )
	{
		$cas_angle = 4; // Degrees to tilt the text
		$cas_offset = 15; // Pixels to offset the text from the border
		$cas_fontsize = 28; // Default font size for the anti-spam image
		$cas_imagebox = imagettfbbox($cas_fontsize, $cas_angle, $cas_font, $cas_antispam);
		$cas_boxwidth = $cas_imagebox[2] - $cas_imagebox[0];
		$cas_boxheight = $cas_imagebox[1] - $cas_imagebox[7];

		// if the text width is too big for the image, decrease the font size to a certain extent (best practice is of course not to use really long words!
		while ($cas_boxwidth > $cas_imgwidth - $cas_offset && $cas_fontsize > 19) {
			$cas_fontsize = $cas_fontsize - 2;
			$cas_imagebox = imagettfbbox($cas_fontsize, $cas_angle, $cas_font, $cas_antispam);
			$cas_boxwidth = $cas_imagebox[2] - $cas_imagebox[0];
		}

		// if the text height too big for the image, decrease the font size to a certain extent (best practice is of course not to use really long words!
		while ($cas_boxheight > $cas_imgheight - $cas_offset && $cas_fontsize > 19) {
			$cas_fontsize = $cas_fontsize - 2;
			$cas_imagebox = imagettfbbox($cas_fontsize, $cas_angle, $cas_font, $cas_antispam);
			$cas_boxheight = $cas_imagebox[1] - $cas_imagebox[7];
		}

		// Use png is available, since it produces clearer text images
		if( function_exists( 'imagepng' ) && $cas_UsePngNotJpeg )
		{
			imagettftext( $cas_image, $cas_fontsize, $cas_angle, $cas_offset, $cas_imgheight - $cas_offset, $cas_fontcolor, $cas_font, $cas_antispam );
			header( "Content-type: image/png" );
			imagepng( $cas_image );
		} else {
			imagettftext( $cas_image, $cas_fontsize, $cas_angle, $cas_offset, $cas_imgheight - $cas_offset, $cas_fontcolor, $cas_font, $cas_antispam );
			header( "Content-type: image/jpeg" );
			imagejpeg( $cas_image );
		}
	} else {
		$cas_fontsize = 5; // 1, 2, 3, 4 or 5 (higher numbers correspond to larger font sizes)
		$tmp_len = strlen( $cas_antispam );
		for( $tmp_count = 0; $tmp_count < $tmp_len; $tmp_count++ )
		{
		   $tmp_xpos = $tmp_count * imagefontwidth( $cas_fontsize ) + 20;
		   $tmp_ypos = 10;
		   imagechar( $cas_image, $cas_fontsize, $tmp_xpos, $tmp_ypos, $cas_antispam, $cas_fontcolor );
		   $cas_antispam = substr( $cas_antispam, 1);
		}
		header("Content-Type: image/gif");
		imagegif( $cas_image );
	} // end if
	imagedestroy( $cas_image );

} else {

	// Determine the url to this script
	$tmpfile=str_replace('\\', '/', __FILE__);
	$tmp1 = strpos( $tmpfile, ABSPATH );
	$tmp2 = strlen( ABSPATH);
	$cas_message = "<small>To prove you're a person (not a spam script), type the security word shown in the picture.</small>";
	$cas_siteurl = site_url();
	if ( !$cas_siteurl ) {
		$cas_message = "<h1>The site administrator needs to manually configure his site address in the plugin configuration file!</h1>";
	}
	$cas_myurl = $cas_siteurl . "/" . substr( $tmpfile, $tmp1 + $tmp2 );

	class PeterAntiSpam
	{
		function PeterAntiSpam()
		{
			global $cas_manualinsert;
			add_action( 'secure_image', array( &$this, 'comment_form' ) );    // add image and input field to comment form
			if (! $cas_manualinsert ) {
				add_action( 'comment_form', array( &$this, 'comment_form' ) );    // add image and input field to comment form
			}
			add_filter( 'preprocess_comment', array( &$this, 'comment_post') );    // add post comment post security code check
		}

		function comment_form()
		{
			global $cas_forcereg, $post_ID, $user_ID, $cas_textcount, $cas_myurl, $cas_imgheight, $cas_imgwidth, $cas_limitcolor, $cas_manualinsert, $cas_borderclr, $cas_message;
			// If the user is logged in, don't prompt for code
			if( ! $cas_forcereg && intval( $user_ID ) > 0 )
			{
				return( $post_ID );
			}

			// Pick a random number
			$cas_antiselect = rand( 1, $cas_textcount ); // 0 is for invalid, so don't select it
			echo( "\t\t\t".'<div style="display:block;" id="secureimgdiv">'."\n\t\t\t\t" );
			echo( '<p><label for="securitycode">Anti-spam word: (Required)</label><span style="color:#FF0000;">*</span><br />'."\n\t\t\t\t" );
			echo( $cas_message . "<br />\n\t\t\t\t" );
			echo( '<input type="text" name="securitycode" id="securitycode" size="30" tabindex="4" />'."\n\t\t\t\t" );
			echo( '<input type="hidden" name="matchthis" value="' . $cas_antiselect . "\" />\n\t\t\t\t" );
			echo( '<img src="' . $cas_myurl . '?antiselect=' . $cas_antiselect . "\"\n\t\t\t\t" );
			echo( 'alt="Anti-Spam Image" ' );
			echo( 'style="border:1px solid ' . $cas_borderclr . ';vertical-align:top;' );
			echo( 'height:' . $cas_imgheight .';width:' . $cas_imgwidth . ";\" /></p>\n\t\t\t" );
			echo( "</div>\n\t\t\t" );
if ( ! $cas_manualinsert ) {
			echo"<script language='javascript' type='text/javascript'>
			<!--
				var urlinput = document.getElementById(\"url\");
				var submitp = urlinput.parentNode;
				var substitution2 = document.getElementById(\"secureimgdiv\");
				submitp.appendChild(substitution2, urlinput);
			// -->
			</script>\n";
}
			return( $post_ID );
		}

		function comment_post( $incoming_comment )
		{
			global $_POST, $cas_text, $cas_textcount, $cas_forcereg, $user_ID, $cas_allowtrack, $cas_allowping;
			// Validate the form input values
			if( isset( $_POST['securitycode'] ) )
			{
				$securitycode = substr( strval( $_POST['securitycode'] ), 0, 50 );
			} else {
				$securitycode = '';
			}
			if( isset( $_POST['matchthis'] ) )
			{
				$matchnum = intval( $_POST['matchthis'] );
			} else {
				$matchnum = 0;
			}
			if( $matchnum < 1 || $matchnum > $cas_textcount ) $matchnum = 0;
			$matchthis = $cas_text[ $matchnum ];

			// If the user is not logged in check the security code
			if( $cas_forcereg || empty( $user_ID ) )
			{
				$istrackping = $incoming_comment['comment_type'];
				if ( $istrackping == 'pingback' && $cas_allowping ) {

					// Send all pingbacks to a moderation queue?
					if ($cas_modping)
						add_filter('pre_comment_approved', create_function('$mod_ping', 'return \'0\';'));
				}
				elseif ( $istrackping == 'trackback' && $cas_allowtrack ) {

					// Send all trackbacks to a moderation queue?
					if ($cas_modtrack)
						add_filter('pre_comment_approved', create_function('$mod_track', 'return \'0\';'));
				}
				else
				{
					if ( $securitycode == '' )
					{
						wp_die( __('Error: Please enter the anti-spam word.') );
					}
					if ( strtolower( $matchthis ) != strtolower( $securitycode ) )
					{
						wp_die( __('Error: Please enter the correct anti-spam word. Press the back button and try again.') );
					} else {
						unset( $matchthis );
					}
				}
			}
			return( $incoming_comment );
		}
	}
	new PeterAntiSpam();

// Add some troubleshooting to the Manage tab

function cas_manage() {
global $cas_myurl;
print "<div class=\"wrap\">\n";
print "<h2>Peter's Custom Anti-Spam</h2>\n";
print "<p><img src=\"" . $cas_myurl . "?antiselect=1\" alt=\"Anti-spam image\" /></p>\n";
print "<p>This page will diagnose your server setup to see how it relates to the performance of this plugin. Remember that all actual settings for this plugin can be changed under the Plugins &gt; Plugin Editor menu. If you can see an image above, the plugin should be working correctly.</p>\n";
print "<hr />\n";
print "<p>Can't see the anti-spam image when you're viewing a comment form? Remember to log out of WordPress first -- by default, the image doesn't show to registered users (although you can edit that option in the plugin file).</p>\n";
print "<hr />\n";

print "<p><strong>GD library</strong><br />\n";
if (function_exists(imagejpeg)) {
print "Yay! The GD library is installed. This is the most important thing to be able to use this plugin. You might still need to do some tweaking to the settings of course.</p>\n";
}
else {
print "<font color=\"red\">The GD library is not installed. This plugin will not work without it. Ask your webhost to install this library (or install it if you manage your own server).</font></p>\n";
}

print "<p><strong>FreeType</strong><br />\n";
if (function_exists(imagettftext)) {
print "Yay! The FreeType library is installed. The anti-spam image should display using the uploaded fonts.</p>\n";
}
else {
print "<font color=\"red\">The FreeType library is not installed. The anti-spam image should still display, but with only a plain font. Ask your webhost to install this library (or install it if you manage your own server) if you want to be able to use the uploaded fonts.</font></p>\n";
}

print "<p><strong>get_option</strong><br />\n";
if (function_exists(get_option)) {
print "Yay! The script should be able to automatically figure out your blog address.</p>\n";
}
else {
print "<font color=\"red\">For some reason, the script cannot access your blog address. Make sure you edit the \$cas_siteurl value in the plugin file (something like <em>http://www.yoursiteaddress.com/whatever</em>).</font></p>\n";
}

print "<p><strong>open_basedir</strong><br />\n";
if (!ini_get('open_basedir')) {
print "Yay! open_basedir is off. The script should be able to automatically figure out the server path.</p>\n";
}
else {
print "<font color=\"red\">Your server has an open_basedir restriction on. Make sure you edit the \$cas_abspath value in the plugin file to set the absolute server path to your blog (something like <em>/home/peter/public_html/blog/</em>).</font></p>\n";
}

print "<hr />\n";
print "<p>Need more help? Post a comment on the <a href=\"http://www.theblog.ca\" title=\"Where all information is posted about the plugin\">Peter's Custom Anti-Spam page</a>.</p>\n";
print "</div>\n";
}

}
?>
