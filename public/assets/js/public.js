(function ($) {
	
	"use strict";

	$(function () {

		if(window.location.hash === '#tab-faqs'){

			$('a[href="'+window.location.hash+'"]').trigger('click');

		}

		$('.faq-question').click(function(event){

			event.stopPropagation();

			$(this).siblings('.faq-content').toggle();

		});

		if(typeof woocommerce_faqs_data.faq_highlight !== 'undefined'){

			$('.faq-'+woocommerce_faqs_data.faq_highlight+':not(.show) .faq-content').toggle();
			$('html, body').animate({
				scrollTop: $('.faq-'+woocommerce_faqs_data.faq_highlight).offset().top - 50
			}, 600);

		}

		$('#quick-approve-faq').submit(function(event){

			$(this).children('input[type="submit"]').replaceWith('<img src="'+woocommerce_faqs_data.spinner+'" />');

			event.preventDefault();

			var data = {

				action: 'approve_woo_faq',

				post_id: $('#qaf_post_id').val(),

				nonce: $('#qaf_nonce').val()

			};

			$.post(woocommerce_faqs_data.ajaxurl, data, function(response) {

				if(!response.success){

					alert( response.message );

					return false;

				}

				else{

					location.reload(true);

				}

			}, 'json');

		});

	});

}(jQuery));