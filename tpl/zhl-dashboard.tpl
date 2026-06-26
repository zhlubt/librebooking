{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	<div class="zhl-dash-head">
		<div>
			<div class="zhl-uplabel">{translate key="ZhlDashUplabel"}</div>
			<h1 class="zhl-h1">{translate key="ZhlDashTitle"}</h1>
			<p class="zhl-sub">{translate key="ZhlDashIntro"} <span class="zhl-muted">({$TotalVisible} {translate key="ZhlDashVisibleSuffix"})</span></p>
		</div>
		{if $IsAdmin}<div class="d-flex gap-2"><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-bundles-admin.php">⚙ {translate key="ZhlAdminBundles"}</a><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-certificates-admin.php">🎓 {translate key="ZhlAdminCerts"}</a></div>{/if}
	</div>

	<nav class="zhl-modenav">
		<a class="zhl-mode" href="{$Path}zhl-assistant.php">🎯 {translate key="ZhlModeBundles"}</a>
		<a class="zhl-mode active" href="{$Path}zhl-dashboard.php">🎛 {translate key="ZhlModeSingle"}</a>
		<a class="zhl-mode" href="{$Path}zhl-bookings.php">📅 {translate key="ZhlMyBookings"}</a>
		<a class="zhl-mode zhl-mode-logout" href="{$Path}logout.php">{translate key="ZhlLogout"}</a>
	</nav>

	<form class="zhl-controls" method="get" action="{$Path}zhl-dashboard.php">
		<input type="hidden" name="cat" value="{$ActiveCat|escape}">
		<div class="zhl-field zhl-grow">
			<label>{translate key="ZhlSearchLabel"}</label>
			<input class="zhl-input" type="text" name="q" value="{$Search|escape}" placeholder="{translate key="ZhlSearchPlaceholder"}">
		</div>
		<div class="zhl-field">
			<label>{translate key="ZhlFromLabel"}</label>
			<input class="zhl-input" type="date" name="start" value="{$StartInput}">
		</div>
		<div class="zhl-field">
			<label>{translate key="ZhlPreviewLabel"}</label>
			<select class="zhl-input" name="days" title="{translate key="ZhlPreviewHint"}">
				<option value="7" {if $Days == 7}selected{/if}>7 {translate key="ZhlDays"}</option>
				<option value="14" {if $Days == 14}selected{/if}>14 {translate key="ZhlDays"}</option>
				<option value="21" {if $Days == 21}selected{/if}>21 {translate key="ZhlDays"}</option>
				<option value="30" {if $Days == 30}selected{/if}>30 {translate key="ZhlDays"}</option>
			</select>
		</div>
		<button class="zhl-btn" type="submit">{translate key="ZhlShow"}</button>
	</form>

	<div class="zhl-shell">
		<aside class="zhl-side">
			<div class="zhl-uplabel">{translate key="ZhlCategories"}</div>
			<nav class="zhl-nav">
				<a class="zhl-navlink {if $ActiveCat == ''}active{/if}" href="{$Path}zhl-dashboard.php?cat=&amp;start={$StartInput}&amp;days={$Days}&amp;q={$Search|escape:'url'}">
					<span>{translate key="ZhlAll"}</span><span class="zhl-count">{$TotalVisible}</span>
				</a>
				{foreach from=$Categories item=c}
					<a class="zhl-navlink {if $c.key == $ActiveCat}active{/if}" href="{$Path}zhl-dashboard.php?cat={$c.key|escape:'url'}&amp;start={$StartInput}&amp;days={$Days}&amp;q={$Search|escape:'url'}">
						<span class="zhl-navlabel">{$c.name|escape}{if $c.note} <span class="zhl-cat-tag" title="{$c.note|escape}">mehrere</span>{/if}</span><span class="zhl-count">{$c.count}</span>
					</a>
				{/foreach}
			</nav>
		</aside>

		<main class="zhl-main">
			<div class="zhl-results-head">
				<h2 class="zhl-h2">{translate key="ZhlDevices"}</h2>
				<span class="zhl-muted">{$RangeLabel}</span>
			</div>

			{if $ActiveNote}
				<div class="zhl-cat-note">ℹ️ {$ActiveNote|escape}</div>
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
								<span class="zhl-badge free">{$row->freeCount}/{$row->totalDays} {translate key="ZhlDaysFree"}</span>
							{elseif $row->minNoticeDays > 0}
								<span class="zhl-badge vorlauf">{translate key="ZhlLeadTime"}</span>
							{else}
								<span class="zhl-badge full">{translate key="ZhlFullyBooked"}</span>
							{/if}
						</div>

						<div class="zhl-days">
							{foreach from=$row->days item=day}
								<div class="zhl-day {if $day.state == 'free'}f{elseif $day.state == 'vorlauf'}v{else}b{/if}" title="{$day.weekday} {$day.label} — {if $day.state == 'free'}{translate key="ZhlStateFree"}{elseif $day.state == 'vorlauf'}{translate key="ZhlStateLeadTimeLong"}{else}{translate key="ZhlStateBusy"}{/if}">
									<span class="zhl-day-wd">{$day.weekday}</span>
									<span class="zhl-day-dt">{$day.label}</span>
								</div>
							{/foreach}
						</div>

						{if $row->minNoticeDays > 0}
							<div class="zhl-vorlauf-note">⏳ {translate key="ZhlLeadTime"} {$row->minNoticeDays} {translate key="ZhlDays"} — {translate key="ZhlEarliestStart"}: <strong>{$row->earliestLabel}</strong></div>
						{/if}

						<div class="zhl-card-foot">
							{if $row->anyFree}
								<span class="zhl-muted zhl-small">{translate key="ZhlNextFreeDay"}: {$row->nextFreeLabel}</span>
								<a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-book.php?rid={$row->id}&amp;sid={$row->scheduleId}&amp;rd={$StartInput}">{translate key="ZhlBook"} ▸</a>
							{elseif $row->minNoticeDays > 0}
								<span class="zhl-muted zhl-small">{translate key="ZhlBookableFrom"} {$row->earliestLabel} ({translate key="ZhlLeadTime"} {$row->minNoticeDays} {translate key="ZhlDays"})</span>
							{else}
								<span class="zhl-muted zhl-small">{translate key="ZhlFullyBookedRange"}</span>
							{/if}
						</div>
					</div>
				{foreachelse}
					<div class="zhl-empty">{translate key="ZhlNoDevices"}</div>
				{/foreach}
			</div>
			{if $Frosted}
				<div class="zhl-frost" role="button" tabindex="0" aria-label="Alle Geräte anzeigen">
					<div class="zhl-frost-card">
						<span class="zhl-frost-arrow" aria-hidden="true">←</span>
						<div class="zhl-frost-title">Bitte zuerst eine Kategorie wählen</div>
						<div class="zhl-frost-text">Wählen Sie links eine Kategorie, um gezielt zu filtern – oder klicken Sie hier, um alle Geräte anzuzeigen.</div>
					</div>
				</div>
			{/if}
			</div>

			<p class="zhl-note">{translate key="ZhlPrototypeNote"}</p>
		</main>
	</div>
</div>

{literal}
<script>
(function () {
	var KEY = 'zhlFrostSeen';
	function seen() { try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
	function markSeen() { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} }

	// Sobald der Nutzer eine Kategorie wählt oder sucht, gilt das Milchglas als „gesehen" →
	// es kommt beim Zurückwechseln auf „Alle" nicht wieder (nur einmal pro Browser-Tab).
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
	if (seen()) { reveal(); return; }   // schon gesehen → gar nicht erst zeigen
	f.addEventListener('click', reveal);
	f.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); reveal(); }
	});
})();
</script>
{/literal}

{include file='globalfooter.tpl'}
