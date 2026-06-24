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
				<div class="zhl-book-row">
					<div class="zhl-field"><label>Von (Datum)</label><input class="zhl-input" type="date" name="beginDate" value="{$BeginDate}" {if $EarliestYmd}min="{$EarliestYmd}"{/if} required></div>
					<div class="zhl-field"><label>Uhrzeit</label><input class="zhl-input" type="time" name="beginPeriod" value="{$BeginTime}" required></div>
				</div>
				<div class="zhl-book-row">
					<div class="zhl-field"><label>Bis (Datum)</label><input class="zhl-input" type="date" name="endDate" value="{$EndDate}" {if $EarliestYmd}min="{$EarliestYmd}"{/if} required></div>
					<div class="zhl-field"><label>Uhrzeit</label><input class="zhl-input" type="time" name="endPeriod" value="{$EndTime}" required></div>
				</div>

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
					<button class="zhl-btn" type="submit">Verbindlich buchen ▸</button>
				</div>
				<p class="zhl-note">Verfügbarkeit, Vorlauf und Konflikte werden beim Buchen verbindlich geprüft. Die Übergabe-Anforderung dieses Geräts wird vermerkt; die Termin-Auswahl direkt hier im Tool (statt Weiterleitung) folgt mit der Terminplaner-Anbindung.</p>
			</form>
		</div>
	{/if}
</div>

{include file='globalfooter.tpl'}
