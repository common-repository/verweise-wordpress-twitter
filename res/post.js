// Stuff that happen on the post page

function verweise_update_count() {
	var len = 140 - jQuery('#titlewrap #title').val().length;
	jQuery('#verweise_count').html(len);
	jQuery('#verweise_count').removeClass();
	if (len < 60) {jQuery('#verweise_count').removeClass().addClass('len60');}
	if (len < 30) {jQuery('#verweise_count').removeClass().addClass('len30');}
	if (len < 15) {jQuery('#verweise_count').removeClass().addClass('len15');}
	if (len < 0) {jQuery('#verweise_count').removeClass().addClass('len0');}
}

(function($){
	var verweise = {
		// Send a tweet
		send: function() {
		
			var post = {};
			post['verweise_tweet'] = $('#verweise_tweet').val();
			post['verweise_post_id'] = $('#verweise_post_id').val();
			post['verweise_twitter_account'] = $('#verweise_twitter_account').val();
			post['action'] = 'verweise-promote';
			post['_ajax_nonce'] = $('#_ajax_verweise').val();

			$('#verweise-promote').html('<p>Warte...</p>');

			$.ajax({
				type : 'POST',
				url : ajaxurl,
				data : post,
				success : function(x) { verweise.success(x, 'verweise-promote'); },
				error : function(r) { verweise.error(r, 'verweise-promote'); }
			});
		},
		
		// Reset short URL
		reset: function() {
		
			var post = {};
			post['verweise_post_id'] = $('#verweise_post_id').val();
			post['verweise_shorturl'] = $('#verweise_shorturl').val();
			post['action'] = 'verweise-reset';
			post['_ajax_nonce'] = $('#_ajax_verweise').val();

			$('#verweise-shorturl').html('<p>Warte...</p>');

			$.ajax({
				type : 'POST',
				url : ajaxurl,
				data : post,
				success : function(x) { verweise.success(x, 'verweise-shorturl'); verweise.update(x); },
				error : function(r) { verweise.error(r, 'verweise-shorturl'); }
			});
		},
		
		// Update short URL in the tweet textarea
		update: function(x) {
			var r = wpAjax.parseAjaxResponse(x);
			r = r.responses[0];
			var oldurl = r.supplemental.old_shorturl;
			var newurl = r.supplemental.shorturl;
			var bg = jQuery('#verweise_tweet').css('backgroundColor');
			if (bg == 'transparent') {bg = '#fff';}

			$('#verweise_tweet')
				.val( $('#verweise_tweet').val().replace(oldurl, newurl) )
				.animate({'backgroundColor':'#ff8'}, 500, function(){
					jQuery('#verweise_tweet').animate({'backgroundColor':bg}, 500)
				});
		},
		
		// Ajax: success
		success : function(x, div) {
			if ( typeof(x) == 'string' ) {
				this.error({'responseText': x}, div);
				return;
			}

			var r = wpAjax.parseAjaxResponse(x);
			if ( r.errors )
				this.error({'responseText': wpAjax.broken}, div);

			r = r.responses[0];
			$('#'+div).html('<p>'+r.data+'</p>');
			
			console.log( r.supplemental.shorturl );
			
			//Update also built-in Shortlink button
			$('#shortlink').val( r.supplemental.shorturl );
		},

		// Ajax: failure
		error : function(r, div) {
			var er = r.statusText;
			if ( r.responseText )
				er = r.responseText.replace( /<.[^<>]*?>/g, '' );
			if ( er )
				$('#'+div).html('<p>Fehler beim AJaX-Request: '+er+'</p>');
		}
	};
	
	$(document).ready(function(){
		// Add the character count
		jQuery('#titlewrap #title').after('<div id="verweise_count" title="Number of chars remaining in a Twitter environment">000</div>').keyup(function(e){
			verweise_update_count();
		});
		verweise_update_count();

		$('#verweise_promote').click(function(e) {
			verweise.send();
			e.preventDefault();
		});
		$('#verweise_reset').click(function(e) {
			verweise.reset();
			e.preventDefault();
		});
		
	})

})(jQuery);