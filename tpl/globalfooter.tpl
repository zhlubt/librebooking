	</div><!-- close main-->
	{if !isset($HideNavBar) || $HideNavBar == false}
		<div id="button-up" class="bg-primary rounded-circle text-white">
			<i class="bi bi-chevron-double-up" aria-hidden="true"></i>
		</div>
		<footer class="zhl-footer border-top text-center py-3" role="contentinfo">
			{if $CompanyName neq ''}
				<div class="mb-1"><a href="{$CompanyUrl}">{$CompanyName}</a></div>
			{/if}
			<div class="zhl-footer-muted">© 2026 · Zentrum für Hochschullehre · Universität Bayreuth</div>
			<div class="zhl-footer-muted"><a href="https://github.com/LibreBooking/librebooking">LibreBooking - GPLv3 -
					{$DisplayVersion}</a></div>
		</footer>
	{/if}

	<script type="text/javascript">
		// init() stammt aus phpscheduleit.js; die ZHL-Seiten laden dieses Bundle
		// nicht, daher defensiv aufrufen (sonst ReferenceError, der den Rest des
		// Blocks – z. B. den Sprachumschalter – abbricht).
		if (typeof init === 'function') { init(); }

		{if isset($LoggedIn) && $LoggedIn && count($AvailableLanguages) > 1}
			$(document).on('click', '[data-lang-code]', function(e) {
				e.preventDefault();
				var langCode = $(this).data('lang-code');
				var csrfToken = '{$CSRFToken|escape:'javascript'}';
				$.post('{$Path|escape:'javascript'}ajax/change_language.php', {
					'{FormKeys::LANGUAGE}': langCode,
					'{FormKeys::CSRF_TOKEN}': csrfToken
				}, 'json').done(function(data) {
					if (data && data.success) {
						window.location.reload();
					} else {
						console.error('Language change failed', data);
					}
				}).fail(function(jqXHR, textStatus, errorThrown) {
					console.error('Language change request failed', textStatus, errorThrown);
				});
			});
		{/if}
	</script>

	{if !empty($GoogleAnalyticsTrackingId)}
		<!-- Google tag (gtag.js) - Google Analytics -->
		<script async src="https://www.googletagmanager.com/gtag/js?id={$GoogleAnalyticsTrackingId}"></script>
		{literal}
			<script>
				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
			{/literal}
			gtag('config', '{$GoogleAnalyticsTrackingId}');
		</script>
	{/if}

	</body>

</html>
