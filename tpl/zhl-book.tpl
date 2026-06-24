{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	{if $Mode == 'success'}
		<div class="zhl-dash-head">
			<div>
				<div class="zhl-uplabel">Medienausleihe ZHL</div>
				<h1 class="zhl-h1">✅ Buchung bestätigt</h1>
			</div>
		</div>
		<div class="zhl-book-card" style="max-width:560px;">
			<p class="zhl-sub">Deine Reservierung ist angelegt.</p>
			{if $ReferenceNumber}<p>Buchungsnummer: <strong>{$ReferenceNumber|escape}</strong></p>{/if}
			<div class="zhl-book-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
				<a class="zhl-btn" href="{$Path}my-calendar.php">📅 Meine Buchungen</a>
				<a class="zhl-btn zhl-btn-ghost" href="{$Path}zhl-dashboard.php">Weiteres Gerät buchen</a>
			</div>
		</div>

	{else}
		<div class="zhl-dash-head">
			<div>
				<div class="zhl-uplabel">Medienausleihe ZHL</div>
				<h1 class="zhl-h1">Buchung</h1>
				<p class="zhl-sub">Prüfe dein Gerät und den Zeitraum und wähle, ob du <strong>abholst</strong> oder eine <strong>Einführung</strong> brauchst.</p>
			</div>
		</div>

		<a class="zhl-back" href="{$Path}zhl-dashboard.php">← zurück zur Geräteauswahl</a>

		{if $Errors}
			<div class="zhl-book-errors">
				<strong>Buchung nicht möglich:</strong>
				<ul>{foreach from=$Errors item=e}<li>{$e|escape}</li>{/foreach}</ul>
			</div>
		{/if}

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
			<form class="zhl-book-card" method="post" action="{$Path}zhl-book.php">
				{csrf_token}
				<input type="hidden" name="resourceId" value="{$ResourceId}">
				<input type="hidden" name="scheduleId" value="{$ScheduleId}">

				<div class="zhl-uplabel">Zeitraum</div>
				{if $Picker.mode == 'slot'}
					{* Wochen-Raster: freie Zeitspanne anklicken (Start- und End-Feld). Beliebige Länge. *}
					<input type="hidden" name="slotDay" id="slotDay" value="">
					<input type="hidden" name="slotBegin" id="slotBegin" value="">
					<input type="hidden" name="slotEnd" id="slotEnd" value="">
					<div class="zhl-week-nav">
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-book.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$Picker.grid.prevWeek}">‹ Zurück</a>
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-book.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$Picker.grid.thisWeek}">Diese Woche</a>
						<span class="zhl-week-label">{$Picker.grid.weekLabel}</span>
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-book.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$Picker.grid.nextWeek}">Weiter ›</a>
					</div>
					<div class="zhl-grid-scroll">
						<table class="zhl-weekgrid">
							<thead><tr><th></th>{foreach from=$Picker.grid.hours item=h}<th>{$h}</th>{/foreach}</tr></thead>
							<tbody>
								{foreach from=$Picker.grid.days item=d}
									<tr>
										<th class="zhl-wg-day">{$d.label}<br><span>{$d.dm}</span></th>
										{foreach from=$d.cells item=c}
											<td class="zhl-wg-cell s-{$c.state}" {if $c.state == 'free'}data-day="{$d.date}" data-b="{$c.h}" data-e="{$c.he}"{/if}></td>
										{/foreach}
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
					<div class="zhl-wg-legend zhl-muted zhl-small">
						<span><i class="lg lg-free"></i> frei</span>
						<span><i class="lg lg-busy"></i> belegt</span>
						<span><i class="lg lg-past"></i> nicht buchbar</span>
						<span><i class="lg lg-sel"></i> deine Auswahl</span>
						<strong id="zhl-sel-label" style="margin-left:auto;">Klicke Start- und End-Feld an einem Tag.</strong>
					</div>
				{else}
					{* Tagesmodus: freie Starttage als Chips + Dauer *}
					<div class="zhl-muted zhl-small" style="margin-bottom:6px;">Wähle einen freien Starttag:</div>
					<div class="zhl-daypick">
						{foreach from=$Picker.days item=dd}
							<label class="zhl-daychip"><input type="radio" name="beginDate" value="{$dd.date}" required><span>{$dd.label}</span></label>
						{foreachelse}
							<span class="zhl-muted zhl-small">Aktuell keine freien Tage im nächsten Zeitraum gefunden.</span>
						{/foreach}
					</div>
					<div class="zhl-field" style="max-width:170px;margin-top:12px;">
						<label>Dauer</label>
						<select class="zhl-input" name="durationDays">
							<option value="1">1 Tag</option>
							<option value="2">2 Tage</option>
							<option value="3">3 Tage</option>
							<option value="5">5 Tage</option>
							<option value="7">7 Tage</option>
							<option value="14">14 Tage</option>
						</select>
					</div>
				{/if}

				<div class="zhl-uplabel" style="margin-top:16px;">Übergabe & Einführung</div>
				<div class="zhl-ueb">
					{if $Abholung == 'nicht_noetig'}
						<div class="zhl-ueb-item"><strong>🔑 Keine Abholung nötig</strong> <span class="zhl-muted zhl-small">Zugang z. B. über Schlüsseltresor.</span></div>
					{elseif $Abholung == 'abholen_persoenlich'}
						<div class="zhl-ueb-item"><strong>📦 Abholung – persönlich</strong> <span class="zhl-muted zhl-small">{if $Abholort}Ort: {$Abholort|escape} · {/if}Dieses Gerät wird persönlich übergeben.</span></div>
					{else}
						<div class="zhl-ueb-item"><strong>📦 Abholung</strong>{if $Abholort} <span class="zhl-muted zhl-small">Ort: {$Abholort|escape}</span>{/if}</div>
					{/if}

					{if $Einfuehrung == 'notwendig'}
						<div class="zhl-ueb-item req"><strong>🎓 Einführung erforderlich</strong> <span class="zhl-muted zhl-small">{if $EinfuehrungTyp}Termintyp „{$EinfuehrungTyp|escape}" · {/if}vor der Nutzung persönlich nötig (auch zu früherem Termin).</span></div>
					{elseif $Einfuehrung == 'moeglich'}
						<div class="zhl-ueb-item"><strong>🎓 Einführung möglich</strong> <span class="zhl-muted zhl-small">{if $EinfuehrungTyp}Termintyp „{$EinfuehrungTyp|escape}"{/if}</span></div>
					{/if}

					{if $Einf.mode != 'keine'}
						{if $Einf.certified}
							<div class="zhl-ueb-item ok"><strong>✓ Du bist bereits eingeführt</strong> <span class="zhl-muted zhl-small">Für dieses Gerät liegt ein Einführungs-Nachweis vor — kein Termin nötig.</span></div>
						{elseif $Einf.slots}
							<div class="zhl-einf-pick">
								<div class="zhl-einf-head">Einführungstermin wählen{if $Einfuehrung == 'notwendig'} <span class="zhl-req">*</span>{/if}</div>
								<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Nur Termine <strong>vor</strong> deinem Ausleihstart. Änderst du den Start, lade die Termine neu.</div>
								<input type="hidden" name="einf_type_id" value="{$Einf.typeId}">
								<input type="hidden" name="einf_member_id" value="{$Einf.memberId}">
								{foreach from=$Einf.slots item=s}
									<label class="zhl-slot">
										<input type="radio" name="einf_slot" value="{$s.slot_id|escape}" {if $Einfuehrung == 'notwendig'}required{/if}>
										<span>{$s.label|escape}</span>
									</label>
								{/foreach}
								<button type="button" class="zhl-btn zhl-btn-ghost zhl-btn-sm zhl-reload-slots" style="margin-top:8px;">🔄 Termine zum gewählten Start aktualisieren</button>
							</div>
						{else}
							<div class="zhl-ueb-item req">
								<strong>⛔ Kein Einführungstermin vor deinem Ausleihstart frei</strong>
								<span class="zhl-muted zhl-small">{if $Einf.earliestLabel}Frühester Termin: {$Einf.earliestLabel}. Wähle einen späteren Ausleihstart und lade die Termine neu.{else}Aktuell ist kein Einführungstermin verfügbar — eine Buchung ist erst möglich, sobald Termine frei sind.{/if}</span>
								<button type="button" class="zhl-btn zhl-btn-ghost zhl-btn-sm zhl-reload-slots" style="margin-top:8px;">🔄 Termine zum gewählten Start aktualisieren</button>
							</div>
						{/if}
					{/if}
				</div>

				{if $Attributes}
					<div class="zhl-uplabel" style="margin-top:16px;">Angaben</div>
					<div class="zhl-attrs">
						{foreach from=$Attributes item=a}
							{if $a.type == 4}
								<label class="zhl-attr-check">
									<input type="checkbox" name="attr_{$a.id}" value="1" {if $a.value != ''}checked{/if} {if $a.required}required{/if}>
									<span>{$a.label|escape}{if $a.required} <span class="zhl-req">*</span>{/if}</span>
								</label>
							{elseif $a.type == 2}
								<div class="zhl-field"><label>{$a.label|escape}{if $a.required} <span class="zhl-req">*</span>{/if}</label><textarea class="zhl-input" name="attr_{$a.id}" rows="2" {if $a.required}required{/if}>{$a.value|escape}</textarea></div>
							{elseif $a.type == 3}
								<div class="zhl-field"><label>{$a.label|escape}{if $a.required} <span class="zhl-req">*</span>{/if}</label>
									<select class="zhl-input" name="attr_{$a.id}" {if $a.required}required{/if}>
										<option value="">– bitte wählen –</option>
										{foreach from=$a.options item=opt}<option value="{$opt|escape}" {if $a.value == $opt}selected{/if}>{$opt|escape}</option>{/foreach}
									</select>
								</div>
							{else}
								<div class="zhl-field"><label>{$a.label|escape}{if $a.required} <span class="zhl-req">*</span>{/if}</label><input class="zhl-input" type="text" name="attr_{$a.id}" value="{$a.value|escape}" {if $a.required}required{/if}></div>
							{/if}
						{/foreach}
					</div>
				{/if}

				<div class="zhl-book-actions">
					<button class="zhl-btn" type="submit" {if $Einf.blocked}disabled{/if}>Verbindlich buchen ▸</button>
					{if $Einf.blocked}<span class="zhl-muted zhl-small" style="margin-left:10px;">Buchung erst möglich, wenn ein Einführungstermin frei ist.</span>{/if}
				</div>
				<p class="zhl-note">Verfügbarkeit, Vorlauf und Konflikte werden beim Buchen verbindlich geprüft. Ist eine Einführung nötig, wird der gewählte Termin direkt im Terminplaner gebucht; danach wird die Reservierung angelegt.</p>
			</form>
		</div>
	{/if}
</div>

<script>
(function () {
	function reloadSlots() {
		var d = document.querySelector('input[name=beginDate]');
		var rid = document.querySelector('input[name=resourceId]');
		var sid = document.querySelector('input[name=scheduleId]');
		if (!d || !rid) { return; }
		window.location.href = window.location.pathname + '?rid=' + encodeURIComponent(rid.value) +
			'&sid=' + encodeURIComponent(sid ? sid.value : '') + '&rd=' + encodeURIComponent(d.value);
	}
	document.querySelectorAll('.zhl-reload-slots').forEach(function (b) { b.addEventListener('click', reloadSlots); });

	// Wochen-Raster: zusammenhängende freie Zeitspanne anklicken (Start- und End-Feld).
	var grid = document.querySelector('.zhl-weekgrid');
	if (grid) {
		var anchor = null;
		var sD = document.getElementById('slotDay'), sB = document.getElementById('slotBegin'), sE = document.getElementById('slotEnd');
		var label = document.getElementById('zhl-sel-label');
		function clearSel() { grid.querySelectorAll('.zhl-wg-cell.sel').forEach(function (c) { c.classList.remove('sel'); }); }
		function setOne(td) {
			anchor = { td: td, row: td.parentElement };
			clearSel(); td.classList.add('sel');
			sD.value = td.getAttribute('data-day'); sB.value = td.getAttribute('data-b'); sE.value = td.getAttribute('data-e');
			label.textContent = td.getAttribute('data-day') + '  ' + sB.value + '–' + sE.value;
		}
		grid.addEventListener('click', function (ev) {
			var td = ev.target.closest('td.s-free'); if (!td) { return; }
			if (!anchor || anchor.row !== td.parentElement) { setOne(td); return; }
			var cells = Array.prototype.slice.call(td.parentElement.querySelectorAll('td.zhl-wg-cell'));
			var lo = Math.min(cells.indexOf(anchor.td), cells.indexOf(td));
			var hi = Math.max(cells.indexOf(anchor.td), cells.indexOf(td));
			for (var i = lo; i <= hi; i++) { if (!cells[i].classList.contains('s-free')) { setOne(td); return; } }
			clearSel();
			for (var j = lo; j <= hi; j++) { cells[j].classList.add('sel'); }
			sD.value = cells[lo].getAttribute('data-day'); sB.value = cells[lo].getAttribute('data-b'); sE.value = cells[hi].getAttribute('data-e');
			label.textContent = sD.value + '  ' + sB.value + '–' + sE.value;
			anchor = null;
		});
	}
})();
</script>

{include file='globalfooter.tpl'}
