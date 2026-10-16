(function($) {
	"use strict";

	// P-scrolling: only on the panels present on the page (an unguarded init threw on every ERP page).
	['.chat-scroll', '.Notification-scroll'].forEach(function (selector) {
		if (document.querySelector(selector)) {
			new PerfectScrollbar(selector, {
				useBothWheelAxes: true,
				suppressScrollX: true,
			});
		}
	});

})(jQuery);
