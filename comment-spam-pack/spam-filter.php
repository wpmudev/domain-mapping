<?php
/*
Plugin Name: TanTanNoodles Simple Spam Filter
Plugin URI: http://tantannoodles.com/toolkit/spam-filter/
Description: A plugin that does a simple sanity check to stop really obvious comment spam before it is processed.
Version: 0.2
Author: Joe Tan
Author URI: http://tantannoodles.com/
*
Change Log:
2010-10-11  Version 0.2. Props @Ulrich SOSSOU. WordPress 3 compatibility. PHP errors fixed.
*/



//define("TANTAN_COMMENT_KEY", "key-".md5(dirname(__FILE__)));
//define("TANTAN_COMMENT_CHECK", get_bloginfo('title'));

class TanTanSpamFilter {
    var $wordsToDieOn = array();
    function TanTanSpamFilter() {
        $this->wordsToDieOn = array(
        // profanity
//            'fuck',
//            'fucker',
        // common spam
            'cialis',
            'ebony',
            'nude',
            'porn',
            'porno',
            'pussy',
            'upskirt',
            'ringtones',
            'phentermine',
            'viagra',
			'<a',
			'rape',
            'casino',
            'poker',
            'cunt',
				'texas',
				'funcking',
				'fuck',

        );
        //add_action('comment_form', array(&$this, 'comment_form'));
        if ( !empty( $_REQUEST['comment'] ) ) {
            $this->comment_handler();
        //    add_action('init', array(&$this,'comment_handler'));
        }
        //add_action('init', array(&$this,'comment_handler'), -1);
        add_action('preprocess_comment', array(&$this,'comment_handler'), -100);

        add_action('admin_menu', array(&$this, 'init'));
    }
    function version_check() {
        global $TanTanVersionCheck;
        if (is_object($TanTanVersionCheck)) {
            $data = get_plugin_data(__FILE__);
            $TanTanVersionCheck->versionCheck(657, $data['Version']);
        }
    }

    function init() {
        add_comments_page( 'Spam Filter', 'Spam Filter', 'manage_options', __FILE__, array(&$this, 'spam_filter') );
        $this->version_check();
    }

    function spam_filter() {
       echo '<div class="wrap">';
       echo "<h1>Simple Spam Filter</h1>\n";

       echo "<p>So far, this simple spam filter has bounced <strong>".$this->getSpamCount()."</strong> spam comments.</p>";

       if ( !empty( $_GET['action'] ) && $_GET['action'] == 'delete' ) {
           $nuked = $this->spam_words_delete($_GET['word']);
           echo "<p>Deleted <strong>$nuked</strong> spams</p>";
       }
       global $wpdb;
	   $spams = $wpdb->get_results("SELECT * FROM $wpdb->comments WHERE comment_approved = 'spam'");
	   $words = array();
	   $uniqueWords = array();
	   foreach ($spams as $spam) {
	       $ws = $this->getWords($spam->comment_content, true);
	       foreach ($ws as $w) {
	           $words[$w]++;
	       }
	       foreach (array_unique($ws) as $w) {
	           $uniqueWords[$w]++;
	       }
	   }
	   $hams = $wpdb->get_results("SELECT * FROM $wpdb->comments WHERE comment_approved != 'spam'");
	   foreach ($hams as $ham) {
	       $ws = $this->getWords($ham->comment_content);
	       foreach ($ws as $w) {
	           unset($words[$w]);
	       }
	   }
	   arsort($words);
	   echo '';
	   echo '';
	   foreach ($words as $word => $count) {
	       echo "$word ($count) (<a href='edit-comments.php?page=tantan/spam-filter.php&action=delete&word=$word'>delete spams with this word</a>)<br />\n";
	   }
	   echo '</div>';
    }
    function spam_words_delete($word) {
        global $wpdb;
        $query = "DELETE FROM $wpdb->comments WHERE comment_approved = 'spam' AND comment_content LIKE '%$word%'";
        $nuked = $wpdb->query($query);
        return $nuked;
    }
    /*
    function comment_form() {
        echo '<input type="hidden" name="tantan_isComment" value="1" />';
        $input = '<input type="hidden" name="'.TANTAN_COMMENT_KEY.'" value="'.TANTAN_COMMENT_CHECK.'" />';
        echo "<script type=\"text/javascript\">document.write('$input');</script>";
    }*/

    function comment_handler($comment=false) {
        if ($comment['comment_content']) $text = $comment['comment_content'];
        else $text = $_REQUEST['comment'];
        $die = false;
        $message = "Sorry, your comment has been rejected.";

        if (!$text) {
            return $comment;
        } elseif ((($num = substr_count($text, 'http://')) >= 3)) { // too many links
            $die = true;
            $message = 'Sorry, your comment has been rejected because it contained several links starting with with http:// - this is a measure to protect users from comment spam, we apologies for the inconvenience. <br /><br />Please click back and delete the http:// elements of your comments.<br /><br />For example: "http://edublogs.org" should simply be "edublogs.org".';
        } elseif (eregi('(\[url=.*\])', $text, $matches)) {
            $die = true;
            $message = 'Sorry, your comment has been rejected because it contains the following: <strong>'.$matches[1].'</strong>.';
        } elseif ($spamWords = $this->hasSpamWords($text)) {
            $die = true;
            $message = 'Sorry, your comment has been rejected because it contains one or more of the following words: <strong>'. implode(', ', $spamWords).'</strong>.<br /><br />Please try posting your comment again, but without these words.';
        }
        if ($die) {
            $this->countSpam();
            wp_die($message);
        }
        /*elseif (!isset($_POST['tantan_isComment']) || ($_POST[TANTAN_COMMENT_KEY] != TANTAN_COMMENT_CHECK)) {
            wp_die( __('Sorry, you\'ll need JavaScript enabled in order to post a comment.') );
        }*/
        return $comment;
    }
    function hasSpamWords($text) {
        $words = $this->getWords($text);
        return array_intersect($this->wordsToDieOn, $words);

    }
    function getWords($text, $notUnique=false) {
        if ($notUnique) return preg_split("/[\W]+/", strtolower(strip_tags($text)));
        else return array_unique(preg_split("/[\W]+/", strtolower(strip_tags($text))));
    }
    function getSpamCount() {
        $count = get_option('tantan-spam-count');
        if (!$count) $count = 0;
        return $count;
    }
    function countSpam() {
        $count = $this->getSpamCount();
        update_option('tantan-spam-count', $count + 1);
        return $count;
    }

}
$TanTanSpamFilter = new TanTanSpamFilter();
?>
