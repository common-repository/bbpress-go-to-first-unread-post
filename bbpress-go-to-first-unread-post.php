<?php
/**
 * Plugin Name: bbPress Go To First Unread Post
 * Description: Adds the ability to track which posts (Replies) have been viewed
 * by a user, and for them to visit the first unread Reply in a Topic.
 * Version: 1.1
 * Author: Matthew Rowland
 * License: GPL2
 */

/*  Copyright 2014  Matthew Rowland  (email : matt.roly@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

wp_enqueue_style( 'bbpress-go-to-first-unread-post', plugins_url( '/bbpress-go-to-first-unread-post.css', __FILE__ ) );

final class bbPressGoToFirstUnreadPost {
	public $last_reply_id = -1;
	private static $instance;

	public static function instance() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new bbPressGoToFirstUnreadPost;
		}
		return self::$instance;
	}

	private function __construct() { /* Do nothing here */ }

	public function set_last_reply_id( $reply_id ) {
		$this->last_reply_id = $reply_id;
	}
}


function gtfbbpress() {
	return bbPressGoToFirstUnreadPost::instance();
}


add_action( 'bbp_theme_after_reply_content', 'gtf_set_last_reply_id' );
function gtf_set_last_reply_id() {
	gtfbbpress()->last_reply_id = bbp_get_reply_id();
}


add_action( 'bbp_template_after_replies_loop', 'gtf_mark_replies_as_read' );
function gtf_mark_replies_as_read() {
	$user_id = get_current_user_id();
	$topic_id = bbp_get_topic_id();
	$old_last_id = get_user_meta( $user_id, 'gtf-last-read-' . $topic_id, true );
	
	if ( empty( $old_last_id ) ) {
		add_user_meta( $user_id, 'gtf-last-read-' . $topic_id, gtfbbpress()->last_reply_id, true );
	} else if ( $old_last_id < gtfbbpress()->last_reply_id ) {
		update_user_meta( $user_id, 'gtf-last-read-' . $topic_id, gtfbbpress()->last_reply_id );
	}
}

function gtf_topic_title( $topic_id = 0 ) {
	echo gtf_get_topic_title( $topic_id );
}
	function gtf_get_topic_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$topic_url = bbp_get_topic_permalink( bbp_get_reply_topic_id() );
		$topic_title = bbp_get_topic_title( bbp_get_reply_topic_id() );

		$last_read_id = get_user_meta( get_current_user_id(), 'gtf-last-read-' . $topic_id, true );
		$topic_last_post_id = bbp_get_topic_last_reply_id( $topic_id );

		if ( empty( $last_read_id ) || $last_read_id == $topic_last_post_id ) {
			$class = "bbp-topic-permalink";
		} else {
			$class = "bbp-topic-permalink gtf-unread";
		}

		$content = "
		<p class='$class' href='$topic_url'>$topic_title</p>";

		return apply_filters( 'gtf_get_topic_title', $content, $topic_id );
	}


add_action( 'bbp_theme_after_topic_title', 'gtf_unread_post_link' );

function gtf_unread_post_link( $topic_id = 0 ) {
	echo gtf_get_unread_post_link( $topic_id );
}
	function gtf_get_unread_post_link( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$last_read_id = get_user_meta( get_current_user_id(), 'gtf-last-read-' . $topic_id, true );
		// If the user has not read any posts in the topic, link directly to the topic.
		if ( empty( $last_read_id ) ) {
			$last_read_link = bbp_get_topic_permalink( $topic_id );
			$link = '<a class="gtf-new-post" title="Go To First Unread Post" href="' . $last_read_link . '"></a>';
			return apply_filters( 'gtf_get_unread_post_link', $link, $topic_id, $last_read_id );
		}
		// If the user has read the last post, don't display the link.
		$topic_last_post_id = bbp_get_topic_last_reply_id( $topic_id );
		if ( $last_read_id == $topic_last_post_id ) {
			return '';
		}

		global $wpdb;

		$unread_post = $wpdb->get_results(
			"SELECT post_id
			 FROM $wpdb->postmeta
			 WHERE post_id > $last_read_id AND meta_key = '_bbp_topic_id' AND meta_value=$topic_id
			 LIMIT 1"
		);

		if ( empty( $unread_post ) )
			return;
			
		$unread_post = $unread_post[0];

		$link = '<a class="gtf-new-post" href="' . bbp_get_reply_url( $unread_post->post_id ) . '"></a>';
		return apply_filters( 'gtf_get_unread_post_link', $link, $topic_id, $last_read_id );
	}

function gtf_mark_topic_read_link( $topic_id = 0 ) {
	echo gtf_get_mark_topic_read_link( $topic_id );
}
	function gtf_get_mark_topic_read_link( $topic_id = 0 ) {
		if ( !bbp_is_single_topic() ) {
			return;
		}
		
		$topic_id = bbp_get_topic_id( $topic_id );

		$url = admin_url('admin-ajax.php');

		$content = "
		<span id='gtf-mark-topic-read'>
			<span id='gtf-mark-topic-read-{$topic_id}'>
				<a href='#' id='gtf-mark-topic-read-link' data-topic='{$topic_id}' rel='nofollow'>Mark Topic Read</a>
			</span>
			&nbsp;|&nbsp;
		</span>

		<script type='text/javascript'>
			jQuery('#gtf-mark-topic-read-link').click( function(e) {
				e.preventDefault();
				var data = {
					action: 'mark_topic_read',
					topic_id: jQuery(this).data('topic')
				};
				jQuery.post('{$url}', data, function(response) {
					jQuery('#gtf-mark-topic-read-{$topic_id}').html(response);
				});
			});
		</script>
		";

		return apply_filters( 'gtf_get_mark_topic_read_link', $content, $topic_id );
	}

function gtf_mark_forum_read_link( $forum_id = 0 ) {
	echo gtf_get_mark_forum_read_link( $forum_id );
}
	function gtf_get_mark_forum_read_link( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		$url = admin_url('admin-ajax.php');
		
		if ( file_exists( get_stylesheet_directory() . "/images/ajax-loader.gif" ) ) {
			$img_url = get_stylesheet_directory_uri() . "/images/ajax-loader.gif";
		} else if ( file_exists( get_stylesheet_directory() . "/images/ajax-loader.png" ) ) {
			$img_url = get_stylesheet_directory_uri() . "/images/ajax-loader.png";
		} else if ( file_exists( get_template_directory() . "/images/ajax-loader.gif" ) ) {
			$img_url = get_template_directory_uri() . "/images/ajax-loader.gif";
		} else if ( file_exists( get_template_directory() . "/images/ajax-loader.png" ) ) {
			$img_url = get_template_directory_uri() . "/images/ajax-loader.png";
		} else if ( file_exists( plugin_dir_path( __FiLE__ ) . "images/ajax-loader.gif" ) ) {
			$img_url = plugins_url( '/images/ajax-loader.gif', __FILE__ );
		} else {
			throw new UnexpectedValueException("Ajax Loader image missing from %PLUGIN_DIR%/images/ajax-loader.gif");
		}

		$content = "
		<span id='gtf-mark-forum-read'>
			<span id='gtf-mark-forum-read-{$forum_id}'>
				<a href='#' id='gtf-mark-forum-read-link' data-forum='{$forum_id}' rel='nofollow'>Mark Forum Read</a>
				<img class='gtf-ajax-loader' src='{$img_url}' />
			</span>
		</span>

		<script type='text/javascript'>
			jQuery('#gtf-mark-forum-read-link').click( function(e) {
				e.preventDefault();
				jQuery('.gtf-ajax-loader').show();
				var data = {
					action: 'mark_forum_read',
					forum_id: jQuery(this).data('forum')
				};
				jQuery.post('{$url}', data, function(response) {
					location.reload();
				});
			});
		</script>
		";
		return apply_filters( 'gtf_get_mark_forum_read_link', $content, $forum_id );
	}

add_action( 'wp_ajax_mark_topic_read', 'gtf_mark_topic_read' );
function gtf_mark_topic_read() {
	$topic_id = intval($_POST['topic_id']);
	$user_id = get_current_user_id();
	
	$result = gtf_update_topic_last_read_meta( $user_id, $topic_id );

	if ($result === true)
		die("Marked as Read");
	else if ($result === false)
		die("Failed to Update");
	else 
		die($result);
}

add_action( 'wp_ajax_mark_forum_read', 'gtf_mark_forum_read' );
function gtf_mark_forum_read() {
	$forum_id = intval($_POST['forum_id']);
	$user_id = get_current_user_id();

	global $wpdb;

	$topics = $wpdb->get_results(
		"SELECT q1.post_id
		 FROM $wpdb->postmeta as q1
		 INNER JOIN $wpdb->postmeta as q2
		 ON q1.post_id = q2.post_id
		 WHERE q1.meta_key = '_bbp_forum_id' AND q1.meta_value=$forum_id AND q2.meta_key = '_bbp_reply_count'
		"
	);

	foreach ( $topics as $topic ) {
		$topic_id = $topic->post_id;
		gtf_update_topic_last_read_meta( $user_id, $topic_id );
	}

	die();
}

function gtf_update_topic_last_read_meta( $user_id, $topic_id ) {
	$last_reply_id = bbp_get_topic_last_reply_id( $topic_id );
	$last_read_id = get_user_meta( $user_id, 'gtf-last-read-' . $topic_id, true );
	if ( empty( $last_read_id ) ) {
		return add_user_meta( $user_id, 'gtf-last-read-' . $topic_id, $last_reply_id, true );
	} else if ( $last_read_id < $last_reply_id ) {
		return update_user_meta( $user_id, 'gtf-last-read-' . $topic_id, $last_reply_id );
	} else {
		return "Already Read";
	}
}
