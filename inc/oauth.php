<?php

// Initiate OAuth with a request token
function wp_ff_verweise_oauth_start() {
	global $wp_ff_verweise;

	if ( wp_ff_verweise_twitter_keys_empty( 'consumer' ) )
		return false;
	
	if( !class_exists('TwitterOAuth') )
		require_once( dirname(__FILE__).'/oauth_lib/twitterOAuth.php' );

	$twitter = new TwitterOAuth( $wp_ff_verweise['consumer_key'], $wp_ff_verweise['consumer_secret'] );
	$request = $twitter->getRequestToken();

	$token = $request['oauth_token'];
	$_SESSION['verweise_req_token']  = $token;
	$_SESSION['verweise_req_secret'] = $request['oauth_token_secret'];
	$_SESSION['verweise_callback']   = $_GET['verweise_callback'];
	$_SESSION['verweise_callback_action'] = $_GET['verweise_callback_action'];
	
	if ( $_GET['type'] == 'authorize' ) {
		$url = $twitter->getAuthorizeURL($token);
	} else {
		$url = $twitter->getAuthenticateURL( $token );
	}

	wp_redirect( $url );
	exit;
}

// Confirm OAuth with an access token
function wp_ff_verweise_oauth_confirm() {
	global $wp_ff_verweise;

	if( wp_ff_verweise_twitter_keys_empty( 'consumer' ) )
		return false;

	if( !class_exists('TwitterOAuth') )
		require_once( dirname(__FILE__).'/oauth_lib/twitterOAuth.php' );
	
	$verweise_req_token = $_SESSION['verweise_req_token'] ? $_SESSION['verweise_req_token'] : $wp_ff_verweise['verweise_req_token'];
	$verweise_req_secret = $_SESSION['verweise_req_secret'] ? $_SESSION['verweise_req_secret'] : $wp_ff_verweise['verweise_req_secret'];
	
	if( !$verweise_req_token or !$verweise_req_secret )
		return false;
	
	$twitter = new TwitterOAuth( $wp_ff_verweise['consumer_key'], $wp_ff_verweise['consumer_secret'], $_SESSION['verweise_req_token'], $_SESSION['verweise_req_secret']);
	$access  = $twitter->getAccessToken();
	
	if( $access['oauth_token'] && $access['oauth_token_secret'] ) {
		$wp_ff_verweise['verweise_acc_token']  = $access['oauth_token'];
		$wp_ff_verweise['verweise_acc_secret'] = $access['oauth_token_secret'];
		update_option( 'ff_verweise', $wp_ff_verweise );
	}

	$twitter = new TwitterOAuth( $wp_ff_verweise['consumer_key'], $wp_ff_verweise['consumer_secret'], $access['oauth_token'], $access['oauth_token_secret'] );
	
	// Allow plugins to interrupt the callback
	if ( $_SESSION['verweise_callback_action'] ) {
		do_action( 'verweise_'.$_SESSION['verweise_callback_action'] );
		unset( $_SESSION['verweise_callback_action'] );
	}
	
	wp_redirect( $_SESSION['verweise_callback'] );
	exit;
}

// Send an OAuth request
function wp_ff_verweise_send_request( $url, $args = array(), $type = NULL ) {

	if( wp_ff_verweise_twitter_keys_empty() )
		return false;

	global $wp_ff_verweise;
	
	// Allow token override via parameter. Otherwise, get from options
	if( isset( $args['acc_token'] ) && $args['acc_token'] ) {
		$acc_token = $args['acc_token'];
		unset($args['acc_token']);
	} else {
		$acc_token = $wp_ff_verweise['verweise_acc_token'];
	}
	
	if( isset( $args['acc_secret'] ) && $args['acc_secret'] ) {
		$acc_secret = $args['acc_secret'];
		unset($args['acc_secret']);
	} else {
		$acc_secret = $wp_ff_verweise['verweise_acc_secret'];
	}
	
	$acc_token = $wp_ff_verweise['verweise_acc_token'];
	$acc_secret = $wp_ff_verweise['verweise_acc_secret'];
	
	if( empty($acc_token) or empty($acc_secret) )
		return false;
		
	if( !class_exists('TwitterOAuth') )
		require_once( dirname(__FILE__).'/oauth_lib/twitterOAuth.php' );

	$twitter = new TwitterOAuth( $wp_ff_verweise['consumer_key'], $wp_ff_verweise['consumer_secret'], $acc_token, $acc_secret );
	$json = $twitter->OAuthRequest( $url.'.json', $args, $type );
	
	return json_decode($json);
}

// Check connection with Twitter (check auth & check if site down). Return true or an error message.
function wp_ff_verweise_twitter_check() {
	if( wp_ff_verweise_twitter_keys_empty() )
		return 'Verbinde Deinen Blog jetzt mit Twitter:';

	$check = wp_ff_verweise_get_auth_infos( );
	
	if( $check == NULL ) {
		// Twitter probably down
		return 'Verbindung zu Twitter nich m&ouml;glich. Der Dienst ist momentan nicht verf&uuml;gbar, oder es liegt ein Problem mit Deinem Server vor.';
		
	} else {
		// error:
		if( isset( $check->error ) )
			return $check->error;
			
		// success:
		return true;
	}
}

// Display either connect button or Twitter infos
function wp_ff_verweise_twitter_button_or_infos() {
	$plugin_url = WP_PLUGIN_URL.'/'.plugin_basename( dirname(dirname(__FILE__)) );
	
	// wrong keys: wp_ff_verweise_get_twitter_screen_name() === false && 

	// Twitter down: wp_ff_verweise_get_twitter_screen_name() === false, wp_ff_verweise_send_request( 'http://twitter.com/account/verify_credentials' ) === NULL
	
	$check = wp_ff_verweise_twitter_check();
	
	if( wp_ff_verweise_twitter_keys_empty() || $check !== true ) {
		// Need "Connect button";
		$img = $plugin_url.'/res/Sign-in-with-Twitter-lighter.png';
		$connect_url = wp_ff_verweise_get_connect_link();
		echo "<p>$check</p>";
		echo "<p><a href='$connect_url'><img src='$img' alt='Verbinde mit Twitter' /></a></p>";

	} else if ( $check === true ) {
		// we're in!
		$screen_name = wp_ff_verweise_get_twitter_screen_name();
		$profile_pic = wp_ff_verweise_get_twitter_profile_pic();
		$f_count = wp_ff_verweise_get_twitter_follower_count();
		
		echo "<p>Bei Twitter angemeldet als <strong><a class='twitter_profile' href='http://twitter.com/$screen_name'><img src='$profile_pic' style='vertical-align:-32px'/>@$screen_name</a></strong> ($f_count followers)</p>";
		
		$unlink = add_query_arg( array('action' => 'unlink'), menu_page_url( 'ff_verweise', false ) );
		$unlink = wp_nonce_url( $unlink, 'unlink-verweise' );
	}
	
	echo "<p>Twitter-Info zur&uuml;cksetzen? <a href='$unlink' class='submitdelete' id='unlink-verweise'>Zur&uuml;cksetzen</a> von Twitter und Deinem Blog.</p>";

}

// Connect link
function wp_ff_verweise_get_connect_link( $action='', $type='authenticate', $image ='Sign-in-with-Twitter-darker' ) {
	$current_url = 	( isset($_SERVER["HTTPS"]) ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$current_url = add_query_arg( 'oauth_connected', 1 );
	$url = add_query_arg(
		array(
			'oauth_start' => 1,
			'verweise_callback' => $current_url,
			'type' => 'authorize', // authorize / authenticate
			'verweise_callback_action' => urlencode( $action )
		), trailingslashit( get_home_url() )
	);
	
	return $url ;
}

// Check authentication
function wp_ff_verweise_get_auth_infos( $refresh = false ) {
	if( isset( $_SESSION['verweise_credentials'] ) && $_SESSION['verweise_credentials'] && !$refresh )
		return $_SESSION['verweise_credentials']; 
	
	$_SESSION['verweise_credentials'] = wp_ff_verweise_send_request( 'http://twitter.com/account/verify_credentials' );
	
	return $_SESSION['verweise_credentials'];
}

// Get Twitter screen name
function wp_ff_verweise_get_twitter_screen_name() {
	$infos = wp_ff_verweise_get_auth_infos();
		
	if( $infos->screen_name )
			return $infos->screen_name;
			
	return false;
}

// Get Twitter profile pic
function wp_ff_verweise_get_twitter_profile_pic() {
	$infos = wp_ff_verweise_get_auth_infos();
		
	if( $infos->profile_image_url )
			return $infos->profile_image_url;
			
	return false;
}

// Get Twitter follower count
function wp_ff_verweise_get_twitter_follower_count() {
	$infos = wp_ff_verweise_get_auth_infos();
		
	if( $infos->followers_count )
			return $infos->followers_count;
			
	return false;
}

// Check if Twitter keys and tokens are empty. Check for consumer only if $what == 'consumer'
function wp_ff_verweise_twitter_keys_empty( $what = 'all' ) {
	global $wp_ff_verweise;
	
	$keys = array('consumer_key', 'consumer_secret' );
	if( $what != 'consumer' ) {
		$keys[] = 'verweise_acc_token';
		$keys[] = 'verweise_acc_secret';
	}
	
	foreach( $keys as $key ) {
		if( !isset( $wp_ff_verweise[$key] ) or empty( $wp_ff_verweise[$key] ) )
			return true;	
	}
	
	return false;
}

// Send a message to Twitter. Returns Twitter response.
function wp_ff_verweise_tweet_it( $message ) {
	global $wp_ff_verweise;
	$args = array();
	$args['status'] = $message;
	$args['acc_token'] = $wp_ff_verweise['verweise_acc_token'];
	$args['acc_secret'] = $wp_ff_verweise['verweise_acc_secret'];
	
	$resp = wp_ff_verweise_send_request( 'http://api.twitter.com/1/statuses/update', $args );
	
	return $resp;
}
