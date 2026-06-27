{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	<div class="zhl-dash-head">
		<div>
			<div class="zhl-uplabel" data-en="ZHL Media Lending">Medienausleihe ZHL</div>
			<h1 class="zhl-h1" data-en="Book individual devices">Geräte einzeln buchen</h1>
			<p class="zhl-sub"><span data-en="Choose a category and a period — you will instantly see which devices are free.">Wähle eine Kategorie und einen Zeitraum — du siehst sofort, welche Geräte frei sind.</span> <span class="zhl-muted">({$TotalVisible} <span data-en="devices visible to you">Geräte für dich sichtbar</span>)</span></p>
		</div>
		<div class="d-flex gap-2 align-items-center">
			<button type="button" class="zhl-btn zhl-btn-sm" data-lang-btn onclick="zhlToggleLang()">EN</button>
			{if $IsAdmin}<a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-bundles-admin.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> <span data-en="Bundles">Bundles</span></a><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-certificates-admin.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10v6"/><path d="m2 10 10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> <span data-en="Certificates">Zertifikate</span></a><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-typeinfo-admin.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> <span data-en="Media info">Geräte-Infos</span></a><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-termin-anfrage-admin.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span data-en="Requests">Anfragen</span></a><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-mahnungen-admin.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> <span data-en="Reminders">Mahnungen</span></a>{/if}
		</div>
	</div>

	<nav class="zhl-modenav">
		<a class="zhl-mode" href="{$Path}zhl-assistant.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> <span data-en="Book bundles">Bundles buchen</span></a>
		<a class="zhl-mode active" href="{$Path}zhl-dashboard.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" y1="4" x2="14" y2="4"/><line x1="10" y1="4" x2="3" y2="4"/><line x1="21" y1="12" x2="12" y2="12"/><line x1="8" y1="12" x2="3" y2="12"/><line x1="21" y1="20" x2="16" y2="20"/><line x1="12" y1="20" x2="3" y2="20"/><line x1="14" y1="2" x2="14" y2="6"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="16" y1="18" x2="16" y2="22"/></svg> <span data-en="Book individual devices">Geräte einzeln buchen</span></a>
		<a class="zhl-mode" href="{$Path}zhl-bookings.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span data-en="My Bookings">Meine Buchungen</span></a>
		<a class="zhl-mode zhl-mode-logout" href="{$Path}logout.php" data-en="Sign out">Abmelden</a>
	</nav>

	<form class="zhl-controls" method="get" action="{$Path}zhl-dashboard.php">
		<input type="hidden" name="cat" value="{$ActiveCat|escape}">
		<div class="zhl-field zhl-grow">
			<label data-en="Search">Suche</label>
			<input class="zhl-input" type="text" name="q" value="{$Search|escape}" placeholder="Gerät oder Typ suchen, z. B. Funkmikrofon…" data-en-ph="Search device or type, e.g. wireless microphone…">
		</div>
		<div class="zhl-field">
			<label data-en="From">Ab</label>
			<input class="zhl-input" type="date" name="start" value="{$StartInput}">
		</div>
		<div class="zhl-field">
			<label data-en="Preview">Vorschau</label>
			<select class="zhl-input" name="days" title="Wie viele Tage Verfügbarkeit das Raster zeigt (die Ausleihdauer wählst du beim Buchen)." data-en-title="How many days of availability the grid shows (you choose the lending duration when booking).">
				<option value="7" {if $Days == 7}selected{/if} data-en="7 days">7 Tage</option>
				<option value="14" {if $Days == 14}selected{/if} data-en="14 days">14 Tage</option>
				<option value="21" {if $Days == 21}selected{/if} data-en="21 days">21 Tage</option>
				<option value="30" {if $Days == 30}selected{/if} data-en="30 days">30 Tage</option>
			</select>
		</div>
		<button class="zhl-btn" type="submit" data-en="Show">Anzeigen</button>
	</form>

	<div class="zhl-shell">
		<aside class="zhl-side">
			<div class="zhl-uplabel" data-en="Categories">Kategorien</div>
			<nav class="zhl-nav">
				<a class="zhl-navlink {if $ActiveCat == ''}active{/if}" href="{$Path}zhl-dashboard.php?cat=&amp;start={$StartInput}&amp;days={$Days}&amp;q={$Search|escape:'url'}">
					<span data-en="All">Alle</span><span class="zhl-count">{$TotalVisible}</span>
				</a>
				{foreach from=$Categories item=c}
					<a class="zhl-navlink {if $c.key == $ActiveCat}active{/if}" href="{$Path}zhl-dashboard.php?cat={$c.key|escape:'url'}&amp;start={$StartInput}&amp;days={$Days}&amp;q={$Search|escape:'url'}">
						<span class="zhl-navlabel"><span data-en="{$c.name_en|escape}">{$c.name|escape}</span>{if $c.note} <span class="zhl-cat-tag" title="{$c.note|escape}">mehrere</span>{/if}</span><span class="zhl-count">{$c.count}</span>
					</a>
				{/foreach}
			</nav>
		</aside>

		<main class="zhl-main">
			<div class="zhl-results-head">
				<h2 class="zhl-h2" data-en="Devices">Geräte</h2>
				<span class="zhl-muted">{$RangeLabel}</span>
			</div>

			{if $ActiveNote}
				<div class="zhl-cat-note"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>️ {$ActiveNote|escape}</div>
			{/if}

			<div class="zhl-gridwrap{if $Frosted} is-frosted{/if}">
			<div class="zhl-grid">
				{foreach from=$Rows item=row}
					<div class="zhl-card {if !$row->anyFree}is-full{/if}">
						<div class="zhl-card-top">
							<div class="zhl-card-titles">
								{if $row->type}
									<h3 class="zhl-card-title">{$row->type|escape}</h3>
									<div class="zhl-card-sub">{$row->name|escape}</div>
								{else}
									<h3 class="zhl-card-title">{$row->name|escape}</h3>
								{/if}
							</div>
							{if $row->anyFree}
								<span class="zhl-badge free">{$row->freeCount}/{$row->totalDays} <span data-en="days free">Tage frei</span></span>
							{elseif $row->minNoticeDays > 0}
								<span class="zhl-badge vorlauf" data-en="Lead time">Vorlauf</span>
							{else}
								<span class="zhl-badge full" data-en="fully booked">ausgebucht</span>
							{/if}
						</div>

						<div class="zhl-days">
							{foreach from=$row->days item=day}
								<div class="zhl-day {if $day.state == 'free'}f{elseif $day.state == 'vorlauf'}v{else}b{/if}" title="{$day.weekday} {$day.label} — {if $day.state == 'free'}frei{elseif $day.state == 'vorlauf'}Vorlauf, noch nicht buchbar{else}belegt{/if}" data-en-title="{$day.weekday} {$day.label} — {if $day.state == 'free'}free{elseif $day.state == 'vorlauf'}lead time, not yet bookable{else}busy{/if}">
									<span class="zhl-day-wd">{$day.weekday}</span>
									<span class="zhl-day-dt">{$day.label}</span>
								</div>
							{/foreach}
						</div>

						{if $row->minNoticeDays > 0}
							<div class="zhl-vorlauf-note"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <span data-en="Lead time">Vorlauf</span> {$row->minNoticeDays} <span data-en="days">Tage</span> — <span data-en="earliest start">frühester Start</span>: <strong>{$row->earliestLabel}</strong></div>
						{/if}

						<div class="zhl-card-foot">
							{if $row->anyFree}
								<span class="zhl-muted zhl-small"><span data-en="Next free day">Nächster freier Tag</span>: {$row->nextFreeLabel}</span>
								<a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-book.php?rid={$row->id}&amp;sid={$row->scheduleId}&amp;rd={$StartInput}"><span data-en="Book">Buchen</span> ▸</a>
							{elseif $row->minNoticeDays > 0}
								<span class="zhl-muted zhl-small"><span data-en="Bookable from">Buchbar ab</span> {$row->earliestLabel} (<span data-en="Lead time">Vorlauf</span> {$row->minNoticeDays} <span data-en="days">Tage</span>)</span>
							{else}
								<span class="zhl-muted zhl-small" data-en="Fully booked in this period">Im Zeitraum komplett belegt</span>
							{/if}
						</div>
					</div>
				{foreachelse}
					<div class="zhl-empty" data-en="No devices for this selection. Try another category, period or search term.">Keine Geräte für diese Auswahl. Versuch eine andere Kategorie, einen anderen Zeitraum oder Suchbegriff.</div>
				{/foreach}
			</div>
			{if $Frosted}
				<div class="zhl-frost" role="button" tabindex="0" aria-label="Alle Geräte anzeigen">
					<div class="zhl-frost-card">
						<span class="zhl-frost-arrow" aria-hidden="true">←</span>
						<div class="zhl-frost-title" data-en="Please choose a category first">Bitte zuerst eine Kategorie wählen</div>
						<div class="zhl-frost-text" data-en="Choose a category on the left to filter, or click here to show all devices.">Wählen Sie links eine Kategorie, um gezielt zu filtern – oder klicken Sie hier, um alle Geräte anzuzeigen.</div>
					</div>
				</div>
			{/if}
			</div>

			<p class="zhl-note" data-en="Prototype: availability shown as a <strong>forecast</strong>; the binding check happens when you book.">Prototyp: Verfügbarkeit als <strong>Prognose</strong>; die verbindliche Prüfung erfolgt beim Buchen.</p>
		</main>
	</div>
</div>

{literal}
<script>
(function () {
	var KEY = 'zhlLang';
	function apply(l) {
		document.querySelectorAll('[data-en]').forEach(function (el) {
			if (el.dataset.de === undefined) { el.dataset.de = el.innerHTML; }
			el.innerHTML = (l === 'en') ? el.getAttribute('data-en') : el.dataset.de;
		});
		document.querySelectorAll('[data-en-ph]').forEach(function (el) {
			if (el.dataset.dePh === undefined) { el.dataset.dePh = el.getAttribute('placeholder') || ''; }
			el.setAttribute('placeholder', (l === 'en') ? el.getAttribute('data-en-ph') : el.dataset.dePh);
		});
		document.querySelectorAll('[data-en-title]').forEach(function (el) {
			if (el.dataset.deTitle === undefined) { el.dataset.deTitle = el.getAttribute('title') || ''; }
			el.setAttribute('title', (l === 'en') ? el.getAttribute('data-en-title') : el.dataset.deTitle);
		});
		document.querySelectorAll('[data-lang-btn]').forEach(function (b) { b.textContent = (l === 'en') ? 'DE' : 'EN'; });
		try { localStorage.setItem(KEY, l); } catch (e) {}
		window.zhlLang = l;
	}
	window.zhlToggleLang = function () { apply(window.zhlLang === 'en' ? 'de' : 'en'); };
	// Initial: gemerkte Wahl, sonst der Sprache der Server-Chrome (globalheader) folgen.
	var initial;
	try { initial = localStorage.getItem(KEY); } catch (e) {}
	if (initial !== 'en' && initial !== 'de') {
		initial = (document.documentElement.lang || 'de').slice(0, 2).toLowerCase() === 'en' ? 'en' : 'de';
	}
	apply(initial);
})();
</script>
{/literal}

{literal}
<script>
(function () {
	var KEY = 'zhlFrostSeen';
	function seen() { try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
	function markSeen() { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} }
	document.querySelectorAll('.zhl-navlink').forEach(function (a) { a.addEventListener('click', markSeen); });
	var form = document.querySelector('.zhl-controls');
	if (form) { form.addEventListener('submit', markSeen); }
	var f = document.querySelector('.zhl-frost');
	if (!f) { return; }
	function reveal() {
		var wrap = f.parentNode;
		if (wrap) { wrap.classList.remove('is-frosted'); }
		f.remove();
		markSeen();
	}
	if (seen()) { reveal(); return; }
	f.addEventListener('click', reveal);
	f.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); reveal(); }
	});
})();
</script>
{/literal}

{include file='globalfooter.tpl'}
