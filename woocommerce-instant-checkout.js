jQuery(document).ready(function(){
	if( jQuery( 'body' ).hasClass( 'instant_checkout_modal' ) ){
		var modal = jQuery('#instant_checkout_modal');
		var product = jQuery('form.cart');
		modal.find('.close').on('click', function(e){
			jQuery(this).parents('#instant_checkout_modal').fadeOut();
			product.find( ':submit' ).removeAttr('disabled');
		});
		product.on("submit", function(e){
			e.preventDefault();
			console.log( jQuery( this ).serialize() );
			jQuery.post( '', jQuery( this ).serialize(), function(){
				console.log('submitted')
			});
			// console.log(e);
			// jQuery(this).find('.button').removeAttr('disabled');
			
			// jQuery(this).find( ':submit' ).attr( 'disabled','disabled' );
			// alert('hi');
		}).find( ':submit' ).on( 'click', function(e){
			console.log(e);
			modal.css({
				// 'top': '30px',
				'left': ( jQuery(window).width() / 2 ) - ( modal.width() / 2 )
			}).fadeIn(); //.height( modal.find('.checkout').height() );
			jQuery(this).removeAttr('disabled');
		});
	}
});