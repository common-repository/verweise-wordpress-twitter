<?php
/*
Plugin Name: verwei.se - Wordpress - Twitter
Plugin URI: http://verwei.se/
Description: Kurz-URL Dienst auf Basis von <a href="http://verweise.org/" title="Your Own URL Shortener">YOURLS</a>. Erstellt automatisch eine Kurz-URL und teilt die Info in Deinem Twitter-Konto.
Author: naweko
Author URI: http://naweko.de
Version: 1.0.2
*/


/********************* DO NOT EDIT *********************/

global $wp_ff_verweise;
session_start();
require_once( dirname(__FILE__).'/inc/core.php' );


/******************** TEMPLATE TAGS ********************/

// Template tag: echo short URL for current post
function wp_ff_verweise_url() {
	global $id;
	$short = esc_url( apply_filters( 'ff_verweise_shorturl', wp_ff_verweise_geturl( $id ) ) );
	if ($short) {
		$rel    = esc_attr( apply_filters( 'ff_verweise_shorturl_rel', 'nofollow alternate shorturl shortlink' ) );
		$title  = esc_attr( apply_filters( 'ff_verweise_shorturl_title', 'Short URL' ) );
		$anchor = esc_html( apply_filters( 'ff_verweise_shorturl_anchor', $short ) );
		echo "<a href=../verweise-wordpress-twitter/\&quot;$short\&quot; rel=\"$rel\" title=\"$title\">$anchor</a>";
	}
}

// Template tag: echo short URL alternate link in <head> for current post. See http://revcanonical.appspot.com/ && http://shorturl.appjet.net/
function wp_ff_verweise_head_linkrel() {
	global $post;
	$id = $post->ID;
	$type = get_post_type( $id );
	if( wp_ff_verweise_generate_on( $type ) ) {	
		$short = apply_filters( 'ff_verweise_shorturl', wp_ff_verweise_geturl( $id ) );
		if ($short) {
			$rel    = apply_filters( 'ff_verweise_shorturl_linkrel', 'alternate shorturl shortlink' );
			echo "<link rel=\"$rel\" href=../verweise-wordpress-twitter/\&quot;$short\&quot; />\n";
		}
	}
}

// Template tag: return/echo short URL with no formatting
function wp_ff_verweise_raw_url( $echo = false ) {
	global $id;
	$short = apply_filters( 'ff_verweise_shorturl', wp_ff_verweise_geturl( $id ) );
	if ($short) {
		if ($echo)
			echo $short;
		return $short;
	}
}

// Get or create the short URL for a post. Input integer (post id), output string(url)
function wp_ff_verweise_geturl( $id ) {
	// Hardcode this const to always poll the shortening service. Debug tests only, obviously.
	if( defined('YOURLS_ALWAYS_FRESH') && YOURLS_ALWAYS_FRESH ) {
		$short = null;
	} else {
		$short = get_post_meta( $id, 'verweise_shorturl', true );
	}
	
	// short URL never was not created before? let's get it now!
	if ( !$short && !is_preview() && !get_post_custom_values( 'verweise_fetching', $id) ) {
		// Allow plugin to define custom keyword
		$keyword = apply_filters( 'ff_verweise_custom_keyword', '', $id );
		$short = wp_ff_verweise_get_new_short_url( get_permalink( $id ), $id, $keyword );
	}
	
	return $short;
}

/************************ HOOKS ************************/

// Check PHP 5 on activation and upgrade settings
register_activation_hook( __FILE__, 'wp_ff_verweise_activate_plugin' );
function wp_ff_verweise_activate_plugin() {
	if ( version_compare(PHP_VERSION, '5.0.0', '<') ) {
		deactivate_plugins( basename( __FILE__ ) );
		wp_die( 'Dieses Modul braucht PHP5.' );
	}
}

// Conditional actions
if (is_admin()) {
	require_once( dirname(__FILE__).'/inc/options.php' );
	// Add menu page, init options, add box on the Post/Edit interface
	add_action('admin_menu', 'wp_ff_verweise_add_page');
	add_action('admin_init', 'wp_ff_verweise_admin_init');
	add_action('admin_init', 'wp_ff_verweise_addbox', 10);
	// Handle AJAX requests
	add_action('wp_ajax_verweise-promote', 'wp_ff_verweise_promote' );
	add_action('wp_ajax_verweise-reset', 'wp_ff_verweise_reset_url' );
	add_action('wp_ajax_verweise-check', 'wp_ff_verweise_check_verweise' );
	// Custom icon & plugin action link
	add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'wp_ff_verweise_plugin_actions', -10);
	add_filter( 'ff_adminmenu_icon_ff_verweise', 'wp_ff_verweise_customicon' );
} else {
	add_action('init', 'wp_ff_verweise_init', 1 );
}

// Handle new stuff published
add_action('new_to_publish', 'wp_ff_verweise_newpost', 10, 1);
add_action('draft_to_publish', 'wp_ff_verweise_newpost', 10, 1);
add_action('pending_to_publish', 'wp_ff_verweise_newpost', 10, 1);
add_action('future_to_publish', 'wp_ff_verweise_newpost', 10, 1);

// Shortcut internal shortlink functions
add_filter( 'pre_get_shortlink', 'wp_ff_verweise_wp_get_shortlink', 10, 2 );

// naweko konfig.php
include_once('inc/konfig.php');