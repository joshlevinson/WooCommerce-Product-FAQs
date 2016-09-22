(function ($, woocommerce_faqs_data) {
	"use strict";
	$(function(){
		$('a.submitpublish').on('click', function (event) {
			event.preventDefault();
			$(this).replaceWith($('<div />', {
				'class': 'spinner is-active'
			}));

			var data = {
				action: 'approve_woo_faq',
				post_id: $(this).attr('data-id'),
				nonce: $(this).attr('data-nonce')
			};

			$.post(ajaxurl, data, function (response) {
				if (response.success) {
					//reload the window, but without $_GET so that the faq is no longer highlighted.
					window.location = response.data.redirect;
				}
				else if (response.indexOf('<') > -1) {
					//in case we get html back (like wp debug throwing errors)
					location.reload();
				}
				else {
					alert(response.data.message);
					return false;
				}
			}, 'json')
				.fail(function () {
					//in case we don't get a useable response
					location.reload();
				});
		});
		var highlight = function () {
			if (typeof woocommerce_faqs_data.highlight !== 'undefined') {
				$('#post-' + woocommerce_faqs_data.highlight).css('background-color', woocommerce_faqs_data.highlight_color);
			}
		};
		var init = function () {
			highlight();
		};
		$(window).load(init);
	});
})(jQuery, woocommerce_faqs_data);