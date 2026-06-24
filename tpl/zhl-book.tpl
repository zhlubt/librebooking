{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	<div class="zhl-dash-head">
		<div>
			<div class="zhl-uplabel">Medienausleihe ZHL</div>
			<h1 class="zhl-h1">Buchung</h1>
			<p class="zhl-sub">Prüfe dein Gerät und den Zeitraum und wähle, ob du <strong>abholst</strong> oder eine <strong>Einführung</strong> brauchst.</p>
		</div>
	</div>

	<a class="zhl-back" href="{$Path}zhl-dashboard.php">← zurück zur Geräteauswahl</a>

	<div class="zhl-book-grid">
		{* Gerät *}
		<div class="zhl-book-card">
			<div class="zhl-uplabel">Dein Gerät</div>
			{if $ResourceType}
				<h2 class="zhl-h2">{$ResourceType|escape}</h2>
				<div class="zhl-card-sub">{$ResourceName|escape}</div>
			{else}
				<h2 class="zhl-h2">{$ResourceName|escape}</h2>
			{/if}
			{if $ScheduleName}<div class="zhl-muted zhl-small" style="margin-top:6px;">Kategorie: {$ScheduleName|escape}</div>{/if}
			{if $MinNoticeDays > 0}
				<div class="zhl-vorlauf-note" style="margin-top:10px;">⏳ Vorlauf {$MinNoticeDays} Tage — frühester Start: <strong>{$EarliestLabel}</strong></div>
			{/if}
		</div>

		{* Buchungs-Formular *}
		<form class="zhl-book-card" method="post" action="{$Path}zhl-book.php" id="zhl-book-form">
			{csrf_token}
			<input type="hidden" name="resourceId" value="{$ResourceId}">
			<input type="hidden" name="scheduleId" value="{$ScheduleId}">

			<div class="zhl-uplabel">Zeitraum</div>
			<div class="zhl-book-row">
				<div class="zhl-field"><label>Von (Datum)</label><input class="zhl-input" type="date" name="beginDate" value="{$BeginDate}" min="{$EarliestLabel|default:''}"></div>
				<div class="zhl-field"><label>Uhrzeit</label><input class="zhl-input" type="time" name="beginPeriod" value="{$BeginTime}"></div>
			</div>
			<div class="zhl-book-row">
				<div class="zhl-field"><label>Bis (Datum)</label><input class="zhl-input" type="date" name="endDate" value="{$EndDate}"></div>
				<div class="zhl-field"><label>Uhrzeit</label><input class="zhl-input" type="time" name="endPeriod" value="{$EndTime}"></div>
			</div>

			<div class="zhl-uplabel" style="margin-top:16px;">Übergabe</div>
			<div class="zhl-choice">
				<label class="zhl-choice-opt">
					<input type="radio" name="handoverChoice" value="pickup" checked>
					<span><strong>📦 Abholung</strong><br><span class="zhl-muted zhl-small">Ich kenne das Gerät und hole es zum Termin ab.</span></span>
				</label>
				<label class="zhl-choice-opt">
					<input type="radio" name="handoverChoice" value="training">
					<span><strong>🎓 Einführung</strong><br><span class="zhl-muted zhl-small">Ich möchte vorab eine kurze Einführung in das Gerät.</span></span>
				</label>
			</div>

			<div class="zhl-book-actions">
				<a class="zhl-btn" id="zhl-book-next" href="{$Path}reservation.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$BeginDate}">Weiter zur Buchung ▸</a>
			</div>
			<p class="zhl-note">Hinweis: Der verbindliche Buchungs-Schritt mit Abholungs-/Einführungs-Termin wird gerade fertiggestellt — aktuell führt „Weiter" noch zur gewohnten Buchungsmaske.</p>
		</form>
	</div>
</div>

<script>
(function () {
	var d = document.querySelector('input[name=beginDate]');
	var a = document.getElementById('zhl-book-next');
	if (d && a) {
		var base = a.getAttribute('href').split('&rd=')[0];
		d.addEventListener('change', function () { a.setAttribute('href', base + '&rd=' + encodeURIComponent(d.value)); });
	}
})();
</script>

{include file='globalfooter.tpl'}
