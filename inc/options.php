<?php 

// Display notice prompting for settings
function wp_ff_verweise_admin_notice() {
	global $plugin_page;
	if( $plugin_page == 'ff_verweise' ) {
		$message = '<strong>verwei.se - Wordpress - Twitter</strong>: Einstellungen unvollständig';
	} else {
		$url = menu_page_url( 'ff_verweise', false );
		$message = 'Du mußt die <a href="'.$url.'">Einstellungen</a> für <strong>verwei.se - Wordpress - Twitter</strong> noch vornehmen.';
	}
	$notice = <<<NOTICE
	<div class="error"><p>$message</p></div>
NOTICE;
	echo apply_filters( 'ff_verweise_notice', $notice );
}

// Add page to menu
function wp_ff_verweise_add_page() {
	// Loading CSS & JS *only* where needed. Do it this way too, goddamnit.
	$page = add_options_page('YOURLS: WordPress to Twitter', 'verwei.se', 'manage_options', 'ff_verweise', 'wp_ff_verweise_do_page');
	add_action("load-$page", 'wp_ff_verweise_add_css_js_plugin');
	add_action("load-$page", 'wp_ff_verweise_handle_action_links');
	// Add the JS & CSS for the char counter. This is too early to check wp_ff_verweise_generate_on('post') or ('page')
	add_action('load-post.php', 'wp_ff_verweise_add_css_js_post');
	add_action('load-post-new.php', 'wp_ff_verweise_add_css_js_post');
	add_action('load-page.php', 'wp_ff_verweise_add_css_js_post');
	add_action('load-page-new.php', 'wp_ff_verweise_add_css_js_post');
}

// Add style & JS on the plugin page
function wp_ff_verweise_add_css_js_plugin() {
	add_thickbox();
	$plugin_url = WP_PLUGIN_URL.'/'.plugin_basename( dirname(dirname(__FILE__)) );
	wp_enqueue_script('verweise_js', $plugin_url.'/res/verweise.js');
	wp_enqueue_script('wp-ajax-response');
	wp_enqueue_style('verweise_css', $plugin_url.'/res/verweise.css');
}

// Add style & JS on the Post/Page Edit page
function wp_ff_verweise_add_css_js_post() {
	global $pagenow;
	$current = str_replace( array('-new.php', '.php'), '', $pagenow);
	if ( wp_ff_verweise_generate_on($current) ) {
		$plugin_url = WP_PLUGIN_URL.'/'.plugin_basename( dirname(dirname(__FILE__)) );
		wp_enqueue_script('verweise_js', $plugin_url.'/res/post.js');
		wp_enqueue_style('verweise_css', $plugin_url.'/res/post.css');
	}
}

// Sanitize & validate options that are submitted
function wp_ff_verweise_sanitize( $in ) {
	global $wp_ff_verweise;
	
	// all options: sanitized strings
	$in = array_map( 'esc_attr', $in);
	
	// 0 or 1 for generate_on_*, tweet_on_*, link_on_*
	foreach( $in as $key=>$value ) {
		if( preg_match( '/^(generate|tweet)_on_/', $key ) ) {
			$in[$key] = ( $value == 1 ? 1 : 0 );
		}
	}
	
	// Twitter keys
	$in['consumer_key'] = wp_ff_verweise_validate_key( $in['consumer_key'] );
	$in['consumer_secret'] = wp_ff_verweise_validate_key( $in['consumer_secret'] );
	
	// Keep options that are not set via form
	$in['verweise_acc_token']  = $wp_ff_verweise['verweise_acc_token'];
	$in['verweise_acc_secret'] = $wp_ff_verweise['verweise_acc_secret'];
	
	return $in;
}

// Validate Twitter keys
function wp_ff_verweise_validate_key( $key ) {
	$key = trim( $key );
	if( !preg_match('/^[A-Za-z0-9]+$/', $key) )
		  $key = '';
	return $key;
}

// Admin notice telling the Twitter keys were reset because invalid
function wp_ff_verweise_admin_notice_twitter_key() {
	echo <<<OOPS
	<div class="error"><p>Der Consumer or Secret Key ist fehlerhaft. Versuche es erneut.</p></div>
OOPS;
}

// Check if plugin seems configured. Param: 'overall' return one single bool, otherwise return details
function wp_ff_verweise_settings_are_ok( $check = 'overall' ) {
	global $wp_ff_verweise;
	
	$check_twitter   = ( wp_ff_verweise_twitter_keys_empty() ? false : true );
	$check_verweise    = ( isset( $wp_ff_verweise['service'] ) && !empty( $wp_ff_verweise['service'] ) ? true : false );
	$check_wordpress = ( isset( $wp_ff_verweise['twitter_message'] ) && !empty( $wp_ff_verweise['twitter_message'] ) ? true : false );
	
	if( $check == 'overall' ) {
		$overall = $check_twitter && $check_verweise && $check_wordpress ;
		return $overall;
	} else {
		return array( 'check_verweise' => $check_verweise, 'check_twitter' => $check_twitter, 'check_wordpress' => $check_wordpress );
	}
}

// Handle action links (reset or unlink)
function wp_ff_verweise_handle_action_links() {
	$actions = array( 'reset', 'unlink' );
	if( !isset( $_GET['action'] ) or !in_array( $_GET['action'], $actions ) )
		return;

	$action = $_GET['action'];
	$nonce  = $_GET['_wpnonce'];
	
	if ( !wp_verify_nonce( $nonce, $action.'-verweise') )
		wp_die( "Invalid link" );
	
	global $wp_ff_verweise;
		
	switch( $action ) {
	
		case 'unlink':
			wp_ff_verweise_session_destroy();
			$wp_ff_verweise['consumer_key'] =
				$wp_ff_verweise['consumer_secret'] =
				$wp_ff_verweise['verweise_acc_token'] = 
				$wp_ff_verweise['verweise_acc_secret'] = '';
			update_option( 'ff_verweise', $wp_ff_verweise );
			break;

		case 'reset':
			wp_ff_verweise_session_destroy();
			$wp_ff_verweise = array();
			delete_option( 'ff_verweise' );
			break;

	}
	
	wp_redirect( menu_page_url( 'ff_verweise', false ) );
}

// Destroy session
function wp_ff_verweise_session_destroy() {
	$_SESSION = array();
	if ( isset( $_COOKIE[session_name()] ) ) {
	   setcookie( session_name(), '', time()-42000, '/' );
	}
	session_destroy();
}

// Draw the option page
function wp_ff_verweise_do_page() {
	$plugin_url = WP_PLUGIN_URL.'/'.plugin_basename( dirname(dirname(__FILE__)) );
	
	$ff_verweise = get_option('ff_verweise'); 
	
	extract( wp_ff_verweise_settings_are_ok( 'all' ) ); // $check_twitter, $check_verweise, $check_wordpress
	
	// If only one of the 3 $check_ is false, expand that section, otherwise expand first
	switch( intval( $check_twitter ) + intval( $check_verweise ) + intval( $check_wordpress ) ) {
		case 0:
		case 3:
			$script_expand = "jQuery('#h3_verweise').click();";
			break;
		case 1:
		case 2:
			if( !$check_verweise ) {
				$script_expand = "jQuery('#h3_verweise').click();";
			} elseif( !$check_twitter ) {
				$script_expand = "jQuery('#h3_twitter').click();";
			} else {
				$script_expand = "jQuery('#h3_wordpress').click();";
			}
			break;
	}

	
	?>
	<script>
	jQuery(document).ready(function(){
		toggle_ok_notok('#h3_check_verweise', '<?php echo $check_verweise ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_twitter', '<?php echo $check_twitter ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_wordpress', '<?php echo $check_wordpress ? 'ok' : 'notok' ; ?>' );
		<?php echo $script_expand; ?>
	});
	</script>	
	
	<div class="wrap">
	
	<?php /** ?>
	<pre><?php print_r(get_option('ff_verweise')); ?></pre>
	<?php /**/ ?>

	<div class="icon32" id="icon-plugins"><br/></div>
	<h2>verwei.se - Wordpress - Twitter</h2>
	
	<div id="y_logo">
		<div class="y_logo">
        	<a href="http://verwei.se/"><img src="<?php echo $plugin_url; ?>/res/verweise-logo.gif" width="256" height="51" alt="verwei.se" /></a><br /><br />
			<a href="http://verweise.org/"><img src="<?php echo $plugin_url; ?>/res/verweise-logo.png"></a>
		</div>
		<div class="y_text">
			<p><a href="http://verwei.se/">verwei.se</a> ist ein kostenloser Kurz-URL Dienst, der auf der freien Software <a href="http://verweise.org/">YOURLS</a> basiert und ständig weiter entwickelt wird. Das originale Plugin findet Ihr hier: <a href="http://planetozh.com/blog/yourls-wordpress-to-twitter-a-short-url-plugin/">http://planetozh.com/</a></p>
			<p>Dieses Modul verbindet Deinen Blog über <a href="http://verwei.se/">verwei.se</a> mit <a href="http://twitter.com/">Twitter</a>. Wenn Du einen neuen Artikel einstellst oder eine Seite veröffentlichst, übermittelt dieses Modul die von Wordpress generierte URL an <a href="http://verwei.se/">verwei.se</a> und veröffentlicht einen Eintrag in Deinem Twitter-Konto. Wenn Du möchtest, sogar voll automatisch.</p>
            <p>Als Hilfe zum Einrichten dieses Modeuls, haben wir hier die folgenden Grafiken und Erklärungen bereitgestellt:</p>
            <p><a href="http://verwei.se/wp-plugin/anleitung-wordpress.php">Anleitung und Dokumentation zu diesem Modul</a><br />
            <a href="http://verwei.se/wp-plugin/anleitung-twitter.php">Einrichten einer Twitter-Applikation</a> (<tt>Kommt weiter unten noch einmal</tt>)
		</div>
	</div>
	
	<form method="post" action="options.php">
	<?php settings_fields('wp_ff_verweise_options'); ?>

	<h3>Einstellungen für verwei.se <span class="h3_toggle expand" id="h3_verweise">+</span> <span id="h3_check_verweise" class="h3_check">*</span></h3>

	<div class="div_h3" id="div_h3_verweise">
	<table class="form-table">

	<tr valign="top">
	<th scope="row">Einstellungen URL-Dienst<span class="mandatory">*</span></th>
	<td>
        
    <label for="y_service">Du nutzt den kostenlosen Dienst von <a href="http://verwei.se/">verwei.se</a>.</label>
    <input type="hidden" name="ff_verweise[service]" id="y_service" value="verweise" class="y_toggle" />
	
	<div id="y_show_verweise" class="<?php echo $hidden; ?> y_service y_level2">
    
    <input type="hidden" name="ff_verweise[location]" id="y_location" value="remote" class="y_toggle">
		
	<div id="y_show_remote" class="<?php echo $hidden; ?> y_location y_level3">
	<label for="y_url">URL zur Schnittstelle bei verwei.se</label> <input type="text" id="y_url" class="y_longfield" name="ff_verweise[verweise_url]" value="<?=VERWEISE_API_PFAD?>"/> <span id="check_url" class="verweise_check button">Prüfen</span><br/>
	<em>Standard: <tt><?=VERWEISE_API_PFAD?> (Hier braucht i.d.R. keine Änderung zu erfolgen)</tt></em><br/><br />
    Öffentliche Zugangsdaten zur Schnittstelle<br /><br />
            
    <label for="y_verweise_login">Benutzername: <?=VERWEISE_BENUTZERNAME?></label> <input type="hidden" id="y_verweise_login" name="ff_verweise[verweise_login]" value="<?=VERWEISE_BENUTZERNAME?>"/><br/>
    <label for="y_verweise_passwd">Kennwort: <?=VERWEISE_KENNWORT?></label> <input type="hidden" id="y_verweise_passwd" name="ff_verweise[verweise_password]" value="<?=VERWEISE_KENNWORT?>"/><br/>
        
	<?php
    wp_nonce_field( 'verweise', '_ajax_verweise', false );
    ?>
        
	</div>

	</td>
	</tr>
	</table>
	</div><!-- div_h3_verweise -->
	
	<h3>Einstellungen für Twitter <span class="h3_toggle expand" id="h3_twitter">+</span> <span id="h3_check_twitter" class="h3_check">*</span></h3> 
	
	<?php
	$blogurl  = get_home_url();
	$blogname = urlencode( get_bloginfo( 'name' ) );
	$blogdesc = urlencode( trim( get_bloginfo( 'description' ), '.' ).'. Powered by YOURLS.' );
	$help_url = $plugin_url."/res/fake_twitter/frame.php?base=$plugin_url&amp;name=YOURLS+on+$blogname&amp;org=$blogname&amp;url=$blogurl&amp;desc=$blogdesc&tb_iframe=1&width=600&height=600";
	?>
	
	<div class="div_h3" id="div_h3_twitter">
	<p>Um eine Verbindung zwischen Deinem Blog und Twitter herzustellen, mußt Du zunächst eine <strong>Twitter Application</strong> erstellen,<br />um "<strong>Consumer Key</strong>" und "<strong>Consumer Secret</strong>" zu erhalten.</p>
	<p>Wenn Du bereits Applikationen bei Twitter eingerichtet hast, dann kannst Du sie in Deiner "<a href="http://twitter.com/apps">Twitter Applikations Liste</a>" aufrufen/bearbeiten.</p>
	<p>Falls Du noch keine Applikation eingerichtet hast, dann kannst Du es hier schnell und einfach tun: <a id="twitter_new_app" href="http://twitter.com/apps/new">Applikation bei Twitter einrichten</a> (<a target="_blank" href="<?=VERWEISE_ANLEITUNG_TWITTER?>">Anleitung</a>).</p>

	<table class="form-table">

	<tr valign="top">
	<th scope="row">Twitter Consumer Key<span class="mandatory">*</span></th>
	<td><input id="consumer_key" name="ff_verweise[consumer_key]" type="text" size="50" value="<?php echo $ff_verweise['consumer_key']; ?>"/></td>
	</tr>

	<tr valign="top">
	<th scope="row">Twitter Consumer Secret<span class="mandatory">*</span></th>
	<td><input id="consumer_secret" name="ff_verweise[consumer_secret]" type="text" size="50" value="<?php echo $ff_verweise['consumer_secret']; ?>"/> <span style="cursor: pointer;">[Prüfen]</span></td>
	</tr>
	
	<td colspan="2" id="verweise_twitter_infos">
	<span id="verweise_now_connect"></span>
	<?php
	if( !wp_ff_verweise_twitter_keys_empty( 'consumer' ) ) {
		wp_ff_verweise_twitter_button_or_infos(); // in oauth.php
	}
	?>
	</td>
	
	</table>
	
	
	</div> <!-- div_h3_twitter -->
	
	<h3>Einstellungen für WordPress <span class="h3_toggle expand" id="h3_wordpress">+</span> <span id="h3_check_wordpress" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_wordpress">

	<h4>Wenn eine Kurz-URL erzeugt und geteilt werden soll</h4> 
	
	<table class="form-table">

	<?php
	$types = get_post_types( array('publicly_queryable' => 1 ), 'objects' );
	foreach( $types as $type=>$object ) {
		$name = $object->labels->singular_name
		?>
		<tr valign="top">
		<th scope="row">Neue Inhalte: <strong><?php echo $name; ?></strong></th>
		<td>
		<input class="y_toggle" id="generate_on_<?php echo $type; ?>" name="ff_verweise[generate_on_<?php echo $type; ?>]" type="checkbox" value="1" <?php checked( '1', $ff_verweise['generate_on_'.$type] ); ?> /><label for="generate_on_<?php echo $type; ?>"> Erzeuge Kurz-URL</label><br/>
		<?php $hidden = ( $ff_verweise['generate_on_'.$type] == '1' ? '' : 'y_hidden' ) ; ?>
		<?php if( $type != 'attachment' ) { ?>
		<div id="y_show_generate_on_<?php echo $type; ?>" class="<?php echo $hidden; ?> generate_on_<?php echo $type; ?>">
			<input id="tweet_on_<?php echo $type; ?>" name="ff_verweise[tweet_on_<?php echo $type; ?>]" type="checkbox" value="1" <?php checked( '1', $ff_verweise['tweet_on_'.$type] ); ?> /><label for="tweet_on_<?php echo $type; ?>"> Artikel auf Twitter teilen</label>
		</div>
		<?php } ?>
		</td>
		</tr>
	<?php } ?>

	</table>

	<h4>Wie soll die Meldung auf Twitter erscheinen?</h4> 

	<table class="form-table">

	<tr valign="top">
	<th scope="row">Twitter-Meldung<span class="mandatory">*</span></th>
	<td><input id="tw_msg" name="ff_verweise[twitter_message]" type="text" size="50" value="<?php echo $ff_verweise['twitter_message']; ?>"/><br/>
	Das ist Deine Twitter-Vorlage. Das Modul ersetzt <tt>%T</tt> mit dem Titel und <tt>%U</tt> mit der erzeugten Kurz-URL.<br />
    Achte auf die Länge, weil der Inhalt automatisch auf 140 Zeichen gekürzt wird.<br/>
	Fertige Vorlagen (Zum Kopieren anklicken)<br/>
	<ul id="tw_msg_sample">
		<li><code class="tw_msg_sample">Neu auf <?php bloginfo();?>: %T %U</code></li>
		<li><code class="tw_msg_sample">Aktuelle Meldung: %T %U</code></li>
		<li><code class="tw_msg_sample">%T - %U</code></li>
	</ul>
	<em>Hinweis: Halte die Twitter-Meldung möglichst kurz!</em>
	<h4 id="toggle_advanced_template">Zusätzliche Zeichen für Deine Vorlage</h4>
	<div id="advanced_template">
		Du kannst die folgenden Zeichen für Deine Twitter-Meldung nutzen:
		<ul>
			<li><b><tt>%U</tt></b>: Kurz-URL</li>
			<li><b><tt>%T</tt></b>: Titel</li>
			<li><b><tt>%A</tt></b>: Name des Autors</li>
			<li><b><tt>%A{Teile}</tt></b>: Die 'Teile' des Autors, die in der Datenbank hinterlegt sind.<br />Beispiel: %A{first_name}. Mehr dazu hier: <a href="http://codex.wordpress.org/Function_Reference/get_userdata">get_userdata()</a>.</li>
			<li><b><tt>%F{Teile}</tt></b>: Die 'Teile' der Meldung. Siehe: <a href="http://codex.wordpress.org/Function_Reference/get_post_meta">get_post_meta()</a>.</li>
			<li><b><tt>%L</tt></b>: Teile als 'plaintext' und in Kleinbuchstaben (Mehrere duch Leerzeichen trennen)</li>
			<li><b><tt>%H</tt></b>: Teile als hashtags und Kleinbuchstaben (Mehrere duch Leerzeichen trennen)</li>
			<li><b><tt>%C</tt></b>: Kategorien als plaintext und Kleinbuchstaben (Mehrere duch Leerzeichen trennen)</li>
			<li><b><tt>%D</tt></b>: Kategorien als hashtags und Kleinbuchstaben (Mehrere duch Leerzeichen trennen)</li>
		</ul>
		Beachte, daß Du nur 140 Zeichen hast!
	</div>
	</td>
	</td>
	</tr>

	</table>
	
	</div> <!-- div_h3_wordpress -->
	
	<?php
	$reset = add_query_arg( array('action' => 'reset'), menu_page_url( 'ff_verweise', false ) );
	$reset = wp_nonce_url( $reset, 'reset-verweise' );
	?>

	<p class="submit">
	<input type="submit" class="button-primary y_submit" value="<?php _e('Speichern') ?>" /> oder <?php echo "alle Einstellungen <a href='$reset' id='reset-verweise' class='submitdelete'>zurücksetzen</a>"; ?>
	</p>
	
	<p><small><span class="mandatory">*</span> Pflichtfelder. Klick auf <img src="<?php echo $plugin_url; ?>/res/expand.png" /> um die jeweiligen Einstellungen vorzunehmen. </small></p>

	</form>

	</div> <!-- wrap -->

	
	<?php	
}

// Add meta boxes to post & page edit
function wp_ff_verweise_addbox() {
	// What page are we on? (new Post, new Page, new custom post type?)
	$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post' ;
	add_meta_box( 'verweisediv', 'verwei.se', 'wp_ff_verweise_drawbox', $post_type, 'side', 'default' );
	
	// TODO: do something with links. Or wait till they're considered custom post types.
}


// Draw meta box
function wp_ff_verweise_drawbox( $post ) {
	$type = $post->post_type;
	$status = $post->post_status;
	$id = $post->ID;
	$title = $post->post_title;
	
	// Too early, young Padawan
	if ( $status != 'publish' ) {
		echo '<p>Die Meldung wird in Deinem Twitter-Konto veröffentlicht, sobald Du sie hier publizierst. Die Einstellungen kannst Du hier ändern: <a href="options-general.php?page=ff_verweise">Einstellungen</a></p>';
		return;
	}
	
	$shorturl = wp_ff_verweise_geturl( $id );
	// Bummer, could not generate a short URL
	if ( !$shorturl ) {
		echo '<p>Fehler. Der Kurz-URL Dienst ist nicht verfügbar. Bitte versuche es später noch einmal!</p>';
		return;
	}
	
	// YOURLS part:	
	wp_nonce_field( 'verweise', '_ajax_verweise', false );
	echo '
	<input type="hidden" id="verweise_post_id" value="'.$id.'" />
	<input type="hidden" id="verweise_shorturl" value="'.$shorturl.'" />
	<input type="hidden" id="verweise_twitter_account" value="'.$account.'" />';
	
	echo '<p><strong>Kurz-URL</strong></p>';
	echo '<div id="verweise-shorturl">';
	
	echo "<p>Kurz-Verweis für $type: <strong><a href='$shorturl'>$shorturl</a></strong></p>
	<p>Klick auf 'Kurz-URL neu laden', um den Kurz-Verweis neu zu erzeugen.</p>";
	echo '<p style="text-align:right"><input class="button" id="verweise_reset" type="submit" value="Kurz-URL neu laden" /></p>';
	echo '</div>';
	

	// Twitter part:
	if( wp_ff_verweise_twitter_keys_empty() or wp_ff_verweise_get_twitter_screen_name() === false )
		return;
	
	$action = 'Teilen';
	$promote = "Meldung";
	$tweeted = get_post_meta( $id, 'verweise_tweeted', true );
	$account = wp_ff_verweise_get_twitter_screen_name();
	
	?>
    <script type="application/javascript">
<!--
function ZeichenTesten()
{
maxZeichen=140;
var txt=document.getElementById('verweise_tweet').value;
if(txt.length>maxZeichen)
    {
      alert("Deine Twitter-Meldung darf maximal "+maxZeichen+" Zeichen enthalten!");
      document.getElementById('verweise_tweet').value=txt.substring(0,maxZeichen);
      document.getElementById('zaehler').value=0;
    }
else
    {
    document.getElementById('zaehler').value=maxZeichen-txt.length;
    }
}

ZeichenTesten();
-->
</script>

    <?php
	
	echo '<p><strong>'.$promote.' auf <a href="http://twitter.com/'.$account.'">@'.$account.'</a> teilen: </strong></p>
	<div id="verweise-promote">';
	if ($tweeted) {
		$action = 'Retweet';
		$promote = "Teile diese $type wieder";
		echo '<p><em>Hinweis:</em> Diese Meldung wurde in Deinem Twitter-Konto veröffentlicht.</p>';
	}
	echo '<p><textarea id="verweise_tweet" rows="4" style="width:100%" onkeyup="ZeichenTesten()">'.wp_ff_verweise_maketweet( $shorturl, $title, $id ).'</textarea><label>Zeichen übrig: <input type="text" id="zaehler" value="140" size="2" style="border: 0; background-color: #f5f5f5; font-size: 100%; color: #e10000; font-weight: bold;"></label></p>
	<p style="text-align:right"><input class="button" id="verweise_promote" type="submit" value="'.$action.'" /></p>
	</div>';
}

?>