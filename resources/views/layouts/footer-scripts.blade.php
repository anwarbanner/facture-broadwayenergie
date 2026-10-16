{{-- Removed 2026-09-28 (page speed): moment, rating, sparkline, eva-icons and the right-sidebar clock
     (sidebar-custom.js ticked every second on elements that do not exist). None is used by the ERP. --}}
<!-- Back-to-top -->
<a href="#top" id="back-to-top"><i class="las la-angle-double-up"></i></a>
<!-- JQuery min js -->
<script src="{{ \App\Support\Asset::url('assets/plugins/jquery/jquery.min.js')}}"></script>
<!-- Bootstrap Bundle js -->
<script src="{{ \App\Support\Asset::url('assets/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<!-- Ionicons js -->
<script src="{{ \App\Support\Asset::url('assets/plugins/ionicons/ionicons.js')}}"></script>


<!--Internal  Perfect-scrollbar js -->
<script src="{{ \App\Support\Asset::url('assets/plugins/perfect-scrollbar/perfect-scrollbar.min.js')}}"></script>
<script src="{{ \App\Support\Asset::url('assets/plugins/perfect-scrollbar/p-scroll.js')}}"></script>
<!-- Custom Scroll bar Js-->
<script src="{{ \App\Support\Asset::url('assets/plugins/mscrollbar/jquery.mCustomScrollbar.concat.min.js')}}"></script>
<!-- right-sidebar js -->
<script src="{{ \App\Support\Asset::url('assets/plugins/sidebar/sidebar-rtl.js')}}"></script>
<!-- Select2 js -->
<script src="{{ \App\Support\Asset::url('assets/plugins/select2/js/select2.min.js') }}"></script>
<script>
	(function () {
		function initSearchableSelects(scope) {
			if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
				return;
			}

			var $scope = scope ? window.jQuery(scope) : window.jQuery(document);
			$scope.find('select').each(function () {
				var $select = window.jQuery(this);
				if ($select.data('select2')) {
					return;
				}

				if ($select.is('[data-no-search], [data-contact-picker], .no-search, .select2-hidden-accessible')) {
					return;
				}

				var placeholder = $select.find('option[value=""]').first().text() || 'Tapez pour rechercher...';

				$select.select2({
					width: '100%',
					language: 'fr',
					placeholder: placeholder,
					allowClear: !$select.prop('required')
				});
			});
		}

		window.initSearchableSelects = initSearchableSelects;

		window.jQuery(function () {
			initSearchableSelects(document);
		});

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) {
						return;
					}

					if (node.tagName === 'SELECT') {
						initSearchableSelects(node.parentNode || document);
						return;
					}

					initSearchableSelects(node);
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	})();
</script>
@yield('js')
<!-- Sticky js -->
<script src="{{ \App\Support\Asset::url('assets/js/sticky.js')}}"></script>
<!-- custom js -->
<script src="{{ \App\Support\Asset::url('assets/js/custom.js')}}"></script><!-- Left-menu js-->
<script src="{{ \App\Support\Asset::url('assets/plugins/side-menu/sidemenu.js')}}"></script>



<!-- ERP UI: responsive list tables -->
<script src="{{ \App\Support\Asset::url('assets/js/erp-ui.js')}}"></script>
<script src="{{ \App\Support\Asset::url('assets/js/erp-documents.js')}}"></script>
