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
			{if $Warning}<div class="zhl-ueb-item req" style="margin:10px 0;"><strong>⚠ Bitte beachten</strong> <span class="zhl-muted zhl-small">{$Warning|escape}</span></div>{/if}
			{include file='zhl-mediainfo.tpl' MediaInfos=$MediaInfos}
			<div class="zhl-book-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
				<a class="zhl-btn" href="{$Path}zhl-bookings.php">📅 Meine Buchungen</a>
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

				<div class="zhl-uplabel">Projekt</div>
				<div class="zhl-field" style="margin-bottom:16px;">
					<label>Titel des Projekts <span class="zhl-req">*</span></label>
					<input class="zhl-input" type="text" name="projectTitle" maxlength="120" required
						placeholder="z. B. Podcast-Aufnahme Lehrstuhl XY" value="{$ProjectTitle|escape}">
					<div class="zhl-muted zhl-small" style="margin-top:4px;">Wofür leihst du das Gerät? Erscheint in deiner Buchung.</div>
				</div>

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
									<tr{if $d.weekend} class="zhl-wg-weekend"{/if}>
										<th class="zhl-wg-day">{$d.label}{if $d.weekend} <span class="zhl-wg-tp" title="Zugang nur mit freigeschaltetem Transponder">🔑</span>{/if}<br><span>{$d.dm}</span></th>
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
					{if $Picker.grid.hasWeekend}<div class="zhl-muted zhl-small" style="margin-top:6px;">🔑 Wochenende (Sa/So): Zugang nur mit für das Gebäude freigeschaltetem Transponder.</div>{/if}
				{else}
					{* Tagesmodus: Monats-Kalender — Start- und End-Tag anklicken (zusammenhängende Spanne). *}
					<input type="hidden" name="dayStart" id="dayStart" value="">
					<input type="hidden" name="dayEnd" id="dayEnd" value="">
					<div class="zhl-week-nav">
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-book.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$Picker.cal.prevMonth}">‹ Zurück</a>
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-book.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$Picker.cal.thisMonth}">Heute</a>
						<span class="zhl-week-label">{$Picker.cal.monthLabel}</span>
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-book.php?rid={$ResourceId}&amp;sid={$ScheduleId}&amp;rd={$Picker.cal.nextMonth}">Weiter ›</a>
					</div>
					<table class="zhl-monthgrid">
						<thead><tr><th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th class="wknd">Sa</th><th class="wknd">So</th></tr></thead>
						<tbody>
							{foreach from=$Picker.cal.weeks item=week}
								<tr>
									{foreach from=$week item=c}
										<td class="zhl-mg-cell s-{$c.state}{if !$c.inMonth} out{/if}{if $c.weekend} wknd{/if}" {if $c.state == 'free'}data-day="{$c.date}"{/if}>{$c.dom}</td>
									{/foreach}
								</tr>
							{/foreach}
						</tbody>
					</table>
					<div class="zhl-wg-legend zhl-muted zhl-small">
						<span><i class="lg lg-free"></i> frei</span>
						<span><i class="lg lg-busy"></i> belegt</span>
						<span><i class="lg lg-past"></i> nicht buchbar</span>
						<span><i class="lg lg-sel"></i> deine Auswahl</span>
						<strong id="zhl-day-label" style="margin-left:auto;">Klicke Start- und End-Tag an.</strong>
					</div>
					<div class="zhl-muted zhl-small" id="zhl-day-wknd" style="margin-top:6px;display:none;">🔑 Wochenende (Sa/So) im Zeitraum: Zugang nur mit für das Gebäude freigeschaltetem Transponder.</div>
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

					{* „Zusammen oder getrennt" — nur wenn eine Einführung ansteht UND eine Abholung greift.
					   Zusammen = ein Termin (der Einführungs-Slot) deckt Einführung + Abholung ab. *}
					{if $CombineEligible}
						<div class="zhl-handovermode" style="margin:14px 0;">
							<div class="zhl-uplabel">Einführung und Abholung</div>
							<label class="zhl-attr-check">
								<input type="radio" name="handover_mode" value="zusammen" class="zhl-hm-radio"{if $HandoverMode != 'getrennt'} checked{/if}>
								<span>🤝 Zusammen <span class="zhl-muted zhl-small">— ein gemeinsamer Termin für Einführung und Abholung</span></span>
							</label>
							<label class="zhl-attr-check">
								<input type="radio" name="handover_mode" value="getrennt" class="zhl-hm-radio"{if $HandoverMode == 'getrennt'} checked{/if}>
								<span>📅 Getrennt <span class="zhl-muted zhl-small">— Einführung und Abholung an zwei verschiedenen Terminen</span></span>
							</label>
							<div class="zhl-combined-note zhl-ueb-item ok" style="display:none; margin-top:8px;">
								<strong>✓ Ein Termin genügt</strong> <span class="zhl-muted zhl-small">Der unten gewählte Einführungstermin ist zugleich der Abholtermin — du bekommst das Gerät direkt im Anschluss.</span>
							</div>
						</div>
					{else}
						<input type="hidden" name="handover_mode" value="getrennt">
					{/if}

					{* Fulfillment-Wahl (C2): persönliche Abholung vs. Hauspost — nur wenn das Gerät Hauspost erlaubt. *}
					{if $Hauspost && $Hauspost.allowed}
						<div class="zhl-fulfillment" style="margin:14px 0;">
							<div class="zhl-uplabel">Wie möchtest du das Material erhalten?</div>
							<label class="zhl-attr-check">
								<input type="radio" name="fulfillment" value="pickup" class="zhl-ff-radio"{if $Fulfillment != 'hauspost'} checked{/if}>
								<span>{if $Abholung == 'ablageort'}📍 Am Ablageort abholen{elseif $Abholung == 'nicht_noetig'}📍 Selbst abholen (vor Ort){else}📦 Persönlich abholen{/if}</span>
							</label>
							<label class="zhl-attr-check">
								<input type="radio" name="fulfillment" value="hauspost" class="zhl-ff-radio"{if $Fulfillment == 'hauspost'} checked{/if}>
								<span>✉️ Per Hauspost verschicken</span>
							</label>
						</div>
					{else}
						<input type="hidden" name="fulfillment" value="pickup">
					{/if}

					{* --- Block A: persönliche Abholung (Slot-Picker C1) --- *}
					<div class="zhl-ff-pickup"{if $Hauspost && $Hauspost.allowed && $Fulfillment == 'hauspost'} style="display:none;"{/if}>
					{* Abhol-Slot-Picker (C1) — kompakt: Tag-Chips + scrollbare Zeit-Pills. Nur wenn Abholung nötig. *}
					{if $Pickup}
						<div id="zhl-pickup-wrap" data-mandatory="{if $Pickup.mandatory}1{else}0{/if}">
						{if $Pickup.days}
							<div class="zhl-einf-pick zhl-pickup">
								<div class="zhl-einf-head">Abholtermin wählen{if $Pickup.mandatory} <span class="zhl-req">*</span>{/if}</div>
								<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Nur Termine, deren Abholung <strong>vor</strong> deinem Ausleihstart abgeschlossen ist. <strong>Ab dem Abholtag ist das Gerät für dich reserviert</strong> (nicht erst ab Nutzungsbeginn). Die Termine aktualisieren sich automatisch, sobald du den Start änderst.</div>
								<div class="zhl-pickup-daybar">
									{foreach from=$Pickup.days item=d name=pd}
										<button type="button" class="zhl-pickup-day{if $smarty.foreach.pd.first} active{/if}" data-day="{$d.date}">{$d.label|escape}</button>
									{/foreach}
								</div>
								{foreach from=$Pickup.days item=d name=pd2}
									<div class="zhl-pickup-times" data-day="{$d.date}"{if !$smarty.foreach.pd2.first} style="display:none;"{/if}>
										{foreach from=$d.slots item=s}
											<label class="zhl-pickup-pill">
												<input type="radio" name="pickup_slot" value="{$s.slot_id|escape}" {if $Pickup.selected == $s.slot_id}checked{/if} {if $Pickup.mandatory}required{/if}>
												<span>{$s.timeLabel|escape}</span>
											</label>
										{/foreach}
									</div>
								{/foreach}
							</div>
						{elseif $Pickup.mandatory}
							<div class="zhl-ueb-item req">
								<strong>⛔ Kein Abholtermin vor deinem Ausleihstart frei</strong>
								<span class="zhl-muted zhl-small">{if $Pickup.earliestLabel}Frühester Termin: {$Pickup.earliestLabel}. Wähle einen späteren Ausleihstart.{else}Aktuell ist kein Abholtermin verfügbar — eine Buchung ist erst möglich, sobald Termine frei sind.{/if}</span>
							</div>
						{/if}
						</div>{* /#zhl-pickup-wrap *}
					{/if}
					</div>{* /.zhl-ff-pickup *}

					{* --- Block B: Hauspost-Zusatzformular (C2) — alle Felder Pflicht, wenn Hauspost gewählt --- *}
					{if $Hauspost && $Hauspost.allowed}
						<div class="zhl-ff-hauspost"{if $Fulfillment != 'hauspost'} style="display:none;"{/if}>
							<div class="zhl-einf-pick">
								<div class="zhl-einf-head">Angaben für den Hauspost-Versand</div>
								<div class="zhl-muted zhl-small" style="margin-bottom:10px;">Der Versand wird vom Medienmanager geprüft. Transport organisiert Stefan Bauernschmitt. Bitte plane mindestens {$Hauspost.leadDays} Werktage Vorlauf ein.</div>
								<div class="zhl-field"><label>Termin des Einsatzes <span class="zhl-req">*</span></label>
									<input class="zhl-input" type="text" name="einsatz_termin" maxlength="120" value="{$Hauspost.values.einsatz_termin|escape}" placeholder="z. B. 15.07.2026, 09–13 Uhr"></div>
								<div class="zhl-field"><label>Raum des Einsatzorts <span class="zhl-req">*</span></label>
									<input class="zhl-input" type="text" name="einsatz_raum" maxlength="190" value="{$Hauspost.values.einsatz_raum|escape}" placeholder="z. B. Gebäude X, Raum 1.23"></div>
								<div class="zhl-field"><label>Titel/Name des Einsatzes <span class="zhl-req">*</span></label>
									<input class="zhl-input zhl-hp-titel" type="text" name="einsatz_titel" maxlength="190" value="{$Hauspost.values.einsatz_titel|escape}"></div>
								<div class="zhl-field"><label>Wann kann angeliefert werden? <span class="zhl-req">*</span></label>
									<input class="zhl-input" type="text" name="liefer_fenster" maxlength="190" value="{$Hauspost.values.liefer_fenster|escape}" placeholder="z. B. ab 14.07. vormittags"></div>
								<div class="zhl-field"><label>Wann kann wieder abgeholt werden? <span class="zhl-req">*</span></label>
									<input class="zhl-input" type="text" name="rueckhol_fenster" maxlength="190" value="{$Hauspost.values.rueckhol_fenster|escape}" placeholder="z. B. 16.07. nachmittags"></div>
								<div class="zhl-field"><label>Eindeutige Bezeichnung des Anlieferungsorts <span class="zhl-req">*</span></label>
									<input class="zhl-input" type="text" name="anlieferort" maxlength="190" value="{$Hauspost.values.anlieferort|escape}" placeholder="z. B. Sekretariat Gebäude X, Raum 1.01"></div>
								<div class="zhl-field"><label>Eindeutige Bezeichnung des Abholungsorts <span class="zhl-req">*</span></label>
									<input class="zhl-input" type="text" name="abholort_hauspost" maxlength="190" value="{$Hauspost.values.abholort_hauspost|escape}" placeholder="z. B. Poststelle ZHL"></div>
							</div>
						</div>
					{/if}

					{if $Einfuehrung == 'notwendig'}
						<div class="zhl-ueb-item req"><strong>🎓 Einführung erforderlich</strong> <span class="zhl-muted zhl-small">{if $EinfuehrungTyp}Termintyp „{$EinfuehrungTyp|escape}" · {/if}vor der Nutzung persönlich nötig (auch zu früherem Termin).</span></div>
					{elseif $Einfuehrung == 'moeglich'}
						<div class="zhl-ueb-item"><strong>🎓 Einführung möglich</strong> <span class="zhl-muted zhl-small">{if $EinfuehrungTyp}Termintyp „{$EinfuehrungTyp|escape}"{/if}</span></div>
					{/if}

					{if $Einf.mode != 'keine'}
						<div id="zhl-einf-wrap" data-required="{if $Einfuehrung == 'notwendig'}1{else}0{/if}" data-active="{if !$Einf.certified}1{else}0{/if}">
						{if $Einf.certified}
							<div class="zhl-ueb-item ok"><strong>✓ Du bist bereits eingeführt</strong> <span class="zhl-muted zhl-small">Für dieses Gerät liegt ein Einführungs-Nachweis vor — kein Termin nötig.</span></div>
						{elseif $Einf.days}
							<div class="zhl-einf-pick zhl-pickup">
								<div class="zhl-einf-head">Einführungstermin wählen{if $Einfuehrung == 'notwendig'} <span class="zhl-req">*</span>{/if}</div>
								<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Erst Tag, dann Uhrzeit. Nur Termine <strong>vor</strong> deinem Ausleihstart. Die Termine aktualisieren sich automatisch, sobald du den Start änderst.</div>
								<div class="zhl-pickup-daybar">
									{foreach from=$Einf.days item=d name=ed}
										<button type="button" class="zhl-pickup-day{if $smarty.foreach.ed.first} active{/if}" data-day="{$d.date}">{$d.label|escape}</button>
									{/foreach}
								</div>
								{foreach from=$Einf.days item=d name=ed2}
									<div class="zhl-pickup-times" data-day="{$d.date}"{if !$smarty.foreach.ed2.first} style="display:none;"{/if}>
										{foreach from=$d.slots item=s}
											<label class="zhl-pickup-pill">
												<input type="radio" name="einf_slot" value="{$s.slot_id|escape}" {if $Einfuehrung == 'notwendig'}required{/if}>
												<span>{$s.timeLabel|escape}</span>
											</label>
										{/foreach}
									</div>
								{/foreach}
							</div>
						{else}
							<div class="zhl-ueb-item req">
								<strong>⛔ Kein Einführungstermin vor deinem Ausleihstart frei</strong>
								<span class="zhl-muted zhl-small">{if $Einf.earliestLabel}Frühester Termin: {$Einf.earliestLabel}. Wähle einen späteren Ausleihstart.{else}Aktuell ist kein Einführungstermin verfügbar — eine Buchung ist erst möglich, sobald Termine frei sind.{/if}</span>
							</div>
						{/if}
						</div>{* /#zhl-einf-wrap *}
					{/if}

					{* --- Block C: persönliche Rückgabe (Slot-Picker, SPEC-RUECKGABE) — spiegelbildlich zur Abholung --- *}
					{if $Return && $Return.applies}
						<div class="zhl-ff-return"{if $Hauspost && $Hauspost.allowed && $Fulfillment == 'hauspost'} style="display:none;"{/if}>
						<div id="zhl-return-wrap" data-mandatory="{if $Return.mandatory}1{else}0{/if}">
						{if $Return.days}
							<div class="zhl-einf-pick zhl-pickup">
								<div class="zhl-einf-head">Rückgabetermin wählen{if $Return.mandatory} <span class="zhl-req">*</span>{/if}</div>
								<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Nur Termine <strong>am oder nach</strong> deinem Ausleihende. <strong>Bis zum Rückgabetag bleibt das Gerät auf dich gebucht</strong> (nicht nur bis Nutzungsende).{if $Rueckgabeort} Rückgabeort: {$Rueckgabeort|escape}.{/if} Die Termine aktualisieren sich automatisch, sobald du den Zeitraum änderst.</div>
								<div class="zhl-pickup-daybar">
									{foreach from=$Return.days item=d name=rd}
										<button type="button" class="zhl-pickup-day{if $smarty.foreach.rd.first} active{/if}" data-day="{$d.date}">{$d.label|escape}</button>
									{/foreach}
								</div>
								{foreach from=$Return.days item=d name=rd2}
									<div class="zhl-pickup-times" data-day="{$d.date}"{if !$smarty.foreach.rd2.first} style="display:none;"{/if}>
										{foreach from=$d.slots item=s}
											<label class="zhl-pickup-pill">
												<input type="radio" name="return_slot" value="{$s.slot_id|escape}" {if $Return.selected == $s.slot_id}checked{/if} {if $Return.mandatory}required{/if}>
												<span>{$s.timeLabel|escape}</span>
											</label>
										{/foreach}
									</div>
								{/foreach}
							</div>
						{elseif $Return.mandatory}
							<div class="zhl-ueb-item req">
								<strong>⛔ Kein Rückgabetermin ab deinem Ausleihende frei</strong>
								<span class="zhl-muted zhl-small">{if $Return.earliestLabel}Frühester Termin: {$Return.earliestLabel}.{else}Aktuell ist kein Rückgabetermin verfügbar — eine Buchung ist erst möglich, sobald Termine frei sind.{/if}</span>
							</div>
						{/if}
						</div>{* /#zhl-return-wrap *}
						</div>{* /.zhl-ff-return *}
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
					<button class="zhl-btn" type="submit" id="zhl-submit" {if $Einf.blocked || ($Pickup && $Pickup.blocked && $Fulfillment != 'hauspost') || ($Return && $Return.blocked && $Fulfillment != 'hauspost')}disabled{/if}>Verbindlich buchen ▸</button>
					{if $Einf.blocked}<span class="zhl-muted zhl-small" style="margin-left:10px;">Buchung erst möglich, wenn ein Einführungstermin frei ist.</span>{/if}
					{if $Pickup && $Pickup.blocked && $Fulfillment != 'hauspost'}<span class="zhl-muted zhl-small" style="margin-left:10px;">Buchung erst möglich, wenn ein Abholtermin frei ist.</span>{/if}
					{if $Return && $Return.blocked && $Fulfillment != 'hauspost'}<span class="zhl-muted zhl-small" style="margin-left:10px;">Buchung erst möglich, wenn ein Rückgabetermin frei ist.</span>{/if}
				</div>
				{if $Einf.blocked || ($Pickup && $Pickup.blocked && $Fulfillment != 'hauspost') || ($Return && $Return.blocked && $Fulfillment != 'hauspost')}
					<div class="zhl-ueb-item req" style="margin-top:14px;display:block;">
						<strong>Kein passender Termin frei?</strong>
						<span class="zhl-muted zhl-small">Stell stattdessen eine Wunschtermin-Anfrage — das ZHL-Medien-Team meldet sich mit einem Termin.</span>
						<div style="margin-top:8px;"><a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-termin-anfrage.php?rid={$ResourceId}&amp;pt={$ProjectTitle|escape:'url'}">📅 Wunschtermin anfragen</a></div>
					</div>
				{/if}
				<p class="zhl-note">Verfügbarkeit, Vorlauf und Konflikte werden beim Buchen verbindlich geprüft. Ist eine Einführung nötig, wird der gewählte Termin direkt im Terminplaner gebucht; danach wird die Reservierung angelegt. <a href="{$Path}zhl-termin-anfrage.php?rid={$ResourceId}">Kein passender Zeitraum? Wunschtermin anfragen.</a></p>
			</form>
		</div>
	{/if}
</div>

<script>
(function () {
	var submit = document.getElementById('zhl-submit');
	var pickupWrap = document.getElementById('zhl-pickup-wrap');
	var einfWrap = document.getElementById('zhl-einf-wrap');
	var returnWrap = document.getElementById('zhl-return-wrap');
	var ridEl = document.querySelector('input[name=resourceId]');
	var fetchToken = 0;
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
	function curFulfillment() { var v = 'pickup'; document.querySelectorAll('.zhl-ff-radio').forEach(function (r) { if (r.checked) { v = r.value; } }); return v; }
	function setSubmitBlocked(blocked, reason) { if (!submit) { return; } submit.disabled = blocked; submit.title = blocked && reason ? reason : ''; }
	// Submit-Sperre als Zustand: Einführung blockiert immer (Pflicht), Abholung nur wenn NICHT Hauspost.
	var pickupBlocked = false, einfBlocked = false, returnBlocked = false, slotsLoading = false;
	function updateSubmitState() {
		if (slotsLoading) { setSubmitBlocked(true, 'Termine werden geladen …'); return; }
		if (einfBlocked) { setSubmitBlocked(true, 'Kein Einführungstermin verfügbar — bitte anderen Ausleihstart wählen.'); return; }
		if (pickupBlocked && curFulfillment() !== 'hauspost' && !combinedActive()) { setSubmitBlocked(true, 'Kein Abholtermin verfügbar — bitte anderen Ausleihstart wählen.'); return; }
		if (returnBlocked && curFulfillment() !== 'hauspost') { setSubmitBlocked(true, 'Kein Rückgabetermin verfügbar — bitte anderes Ausleihende wählen.'); return; }
		setSubmitBlocked(false);
	}
	function recomputeBlockedFromDom() {
		einfBlocked = !!(einfWrap && einfWrap.getAttribute('data-active') === '1' && einfWrap.getAttribute('data-required') === '1' && !einfWrap.querySelector('input[name=einf_slot]'));
		pickupBlocked = !!(pickupWrap && pickupWrap.getAttribute('data-mandatory') === '1' && !pickupWrap.querySelector('input[name=pickup_slot]'));
		returnBlocked = !!(returnWrap && returnWrap.getAttribute('data-mandatory') === '1' && !returnWrap.querySelector('input[name=return_slot]'));
	}

	// --- Abhol-/Einführungstermine client-seitig rendern (Struktur = Server-Render) ---
	function renderPickup(vm) {
		if (!pickupWrap) { return false; }
		var mandatory = pickupWrap.getAttribute('data-mandatory') === '1';
		if (!vm || !vm.days || !vm.days.length) {
			if (vm && mandatory) {
				pickupWrap.innerHTML = '<div class="zhl-ueb-item req"><strong>⛔ Kein Abholtermin vor deinem Ausleihstart frei</strong> <span class="zhl-muted zhl-small">' +
					(vm.earliestLabel ? 'Frühester Termin: ' + esc(vm.earliestLabel) + '. Wähle einen späteren Ausleihstart.' : 'Aktuell ist kein Abholtermin verfügbar.') + '</span></div>';
				return true;
			}
			pickupWrap.innerHTML = '';
			return false;
		}
		var h = '<div class="zhl-einf-pick zhl-pickup"><div class="zhl-einf-head">Abholtermin wählen' + (mandatory ? ' <span class="zhl-req">*</span>' : '') + '</div>' +
			'<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Nur Termine, deren Abholung <strong>vor</strong> deinem Ausleihstart abgeschlossen ist. <strong>Ab dem Abholtag ist das Gerät für dich reserviert</strong> (nicht erst ab Nutzungsbeginn).</div><div class="zhl-pickup-daybar">';
		vm.days.forEach(function (d, i) { h += '<button type="button" class="zhl-pickup-day' + (i === 0 ? ' active' : '') + '" data-day="' + esc(d.date) + '">' + esc(d.label) + '</button>'; });
		h += '</div>';
		vm.days.forEach(function (d, i) {
			h += '<div class="zhl-pickup-times" data-day="' + esc(d.date) + '"' + (i === 0 ? '' : ' style="display:none;"') + '>';
			(d.slots || []).forEach(function (s) { h += '<label class="zhl-pickup-pill"><input type="radio" name="pickup_slot" value="' + esc(s.slot_id) + '"' + (mandatory ? ' required' : '') + '><span>' + esc(s.timeLabel) + '</span></label>'; });
			h += '</div>';
		});
		h += '</div>';
		pickupWrap.innerHTML = h;
		return false;
	}
	function renderEinf(vm) {
		if (!einfWrap) { return false; }
		var active = einfWrap.getAttribute('data-active') === '1';
		var required = einfWrap.getAttribute('data-required') === '1';
		if (vm && vm.certified) {
			einfWrap.innerHTML = '<div class="zhl-ueb-item ok"><strong>✓ Du bist bereits eingeführt</strong> <span class="zhl-muted zhl-small">Für dieses Gerät liegt ein Einführungs-Nachweis vor — kein Termin nötig.</span></div>';
			return false;
		}
		if (!active || !vm) { return false; }
		if (!vm.days || !vm.days.length) {
			einfWrap.innerHTML = '<div class="zhl-ueb-item req"><strong>⛔ Kein Einführungstermin vor deinem Ausleihstart frei</strong> <span class="zhl-muted zhl-small">' +
				(vm.earliestLabel ? 'Frühester Termin: ' + esc(vm.earliestLabel) + '. Wähle einen späteren Ausleihstart.' : 'Aktuell ist kein Einführungstermin verfügbar.') + '</span></div>';
			return required;
		}
		var h = '<div class="zhl-einf-pick zhl-pickup"><div class="zhl-einf-head">Einführungstermin wählen' + (required ? ' <span class="zhl-req">*</span>' : '') + '</div>' +
			'<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Erst Tag, dann Uhrzeit. Nur Termine <strong>vor</strong> deinem Ausleihstart.</div><div class="zhl-pickup-daybar">';
		vm.days.forEach(function (d, i) { h += '<button type="button" class="zhl-pickup-day' + (i === 0 ? ' active' : '') + '" data-day="' + esc(d.date) + '">' + esc(d.label) + '</button>'; });
		h += '</div>';
		vm.days.forEach(function (d, i) {
			h += '<div class="zhl-pickup-times" data-day="' + esc(d.date) + '"' + (i === 0 ? '' : ' style="display:none;"') + '>';
			(d.slots || []).forEach(function (s) { h += '<label class="zhl-pickup-pill"><input type="radio" name="einf_slot" value="' + esc(s.slot_id) + '"' + (required ? ' required' : '') + '><span>' + esc(s.timeLabel) + '</span></label>'; });
			h += '</div>';
		});
		h += '</div>';
		einfWrap.innerHTML = h;
		return false;
	}
	// Rückgabe-Picker (SPEC-RUECKGABE) — Struktur = Abhol-Picker, name=return_slot. Gibt 'blocked' zurück.
	function renderReturn(vm) {
		if (!returnWrap) { return false; }
		var mandatory = returnWrap.getAttribute('data-mandatory') === '1';
		if (!vm || !vm.days || !vm.days.length) {
			if (vm && mandatory) {
				returnWrap.innerHTML = '<div class="zhl-ueb-item req"><strong>⛔ Kein Rückgabetermin ab deinem Ausleihende frei</strong> <span class="zhl-muted zhl-small">' +
					(vm.earliestLabel ? 'Frühester Termin: ' + esc(vm.earliestLabel) + '.' : 'Aktuell ist kein Rückgabetermin verfügbar.') + '</span></div>';
				return true;
			}
			returnWrap.innerHTML = '';
			return false;
		}
		var h = '<div class="zhl-einf-pick zhl-pickup"><div class="zhl-einf-head">Rückgabetermin wählen' + (mandatory ? ' <span class="zhl-req">*</span>' : '') + '</div>' +
			'<div class="zhl-muted zhl-small" style="margin-bottom:8px;">Nur Termine <strong>am oder nach</strong> deinem Ausleihende. <strong>Bis zum Rückgabetag bleibt das Gerät auf dich gebucht.</strong></div><div class="zhl-pickup-daybar">';
		vm.days.forEach(function (d, i) { h += '<button type="button" class="zhl-pickup-day' + (i === 0 ? ' active' : '') + '" data-day="' + esc(d.date) + '">' + esc(d.label) + '</button>'; });
		h += '</div>';
		vm.days.forEach(function (d, i) {
			h += '<div class="zhl-pickup-times" data-day="' + esc(d.date) + '"' + (i === 0 ? '' : ' style="display:none;"') + '>';
			(d.slots || []).forEach(function (s) { h += '<label class="zhl-pickup-pill"><input type="radio" name="return_slot" value="' + esc(s.slot_id) + '"' + (mandatory ? ' required' : '') + '><span>' + esc(s.timeLabel) + '</span></label>'; });
			h += '</div>';
		});
		h += '</div>';
		returnWrap.innerHTML = h;
		return false;
	}
	function loadSlots(start, time, end) {
		if (!start || !ridEl) { return; }
		var einfActive = einfWrap && einfWrap.getAttribute('data-active') === '1' && einfWrap.getAttribute('data-required') === '1';
		var pickupMand = pickupWrap && pickupWrap.getAttribute('data-mandatory') === '1';
		var returnMand = returnWrap && returnWrap.getAttribute('data-mandatory') === '1';
		var token = ++fetchToken;
		if (pickupWrap) { pickupWrap.innerHTML = '<div class="zhl-muted zhl-small">⏳ Termine werden geladen …</div>'; }
		if (returnWrap) { returnWrap.innerHTML = '<div class="zhl-muted zhl-small">⏳ Termine werden geladen …</div>'; }
		if (einfWrap && einfWrap.getAttribute('data-active') === '1') { einfWrap.innerHTML = ''; }
		slotsLoading = (einfActive || pickupMand || returnMand);
		updateSubmitState();
		var url = window.location.pathname + '?ajax=slots&rid=' + encodeURIComponent(ridEl.value) + '&start=' + encodeURIComponent(start) + (time ? '&time=' + encodeURIComponent(time) : '') + (end ? '&end=' + encodeURIComponent(end) : '');
		fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
			.then(function (r) { if (!r.ok) { throw new Error('http'); } return r.json(); })
			.then(function (j) {
				if (token !== fetchToken) { return; }
				if (!j || j.error) { throw new Error('data'); }
				pickupBlocked = renderPickup(j.pickup);
				einfBlocked = renderEinf(j.einf);
				returnBlocked = renderReturn(j.return);
				slotsLoading = false;
				applyFulfillment(); // ruft updateSubmitState() + deaktiviert pickup_slot/return_slot bei Hauspost
			})
			.catch(function () {
				if (token !== fetchToken) { return; }
				slotsLoading = false;
				if (einfActive) { einfBlocked = true; }
				if (pickupMand) { pickupBlocked = true; }
				if (returnMand) { returnBlocked = true; }
				updateSubmitState();
				if (pickupWrap && pickupWrap.innerHTML.indexOf('⏳') !== -1) { pickupWrap.innerHTML = '<div class="zhl-muted zhl-small">⚠ Termine konnten nicht geladen werden — bitte Start erneut wählen.</div>'; }
				if (returnWrap && returnWrap.innerHTML.indexOf('⏳') !== -1) { returnWrap.innerHTML = '<div class="zhl-muted zhl-small">⚠ Termine konnten nicht geladen werden — bitte Zeitraum erneut wählen.</div>'; }
			});
	}

	// --- Monats-Kalender (Tagesmodus): Spanne ganzer Tage anklicken → Termine zum START laden. ---
	var mgrid = document.querySelector('.zhl-monthgrid');
	if (mgrid) {
		var dStart = document.getElementById('dayStart'), dEnd = document.getElementById('dayEnd');
		var dLabel = document.getElementById('zhl-day-label'), dWknd = document.getElementById('zhl-day-wknd');
		var anchorDay = null;
		var freeCells = Array.prototype.slice.call(mgrid.querySelectorAll('td.s-free'));
		function clearDaySel() { mgrid.querySelectorAll('td.zhl-mg-cell.sel').forEach(function (c) { c.classList.remove('sel'); }); }
		function applyRange(a, b) {
			var lo = (a <= b) ? a : b, hi = (a <= b) ? b : a;
			clearDaySel();
			var sel = freeCells.filter(function (c) { var d = c.getAttribute('data-day'); return d >= lo && d <= hi; });
			sel.forEach(function (c) { c.classList.add('sel'); });
			dStart.value = lo; dEnd.value = hi;
			if (dLabel) { dLabel.textContent = (lo === hi) ? ('Ausleihe: ' + lo) : ('Ausleihe: ' + lo + ' – ' + hi); }
			if (dWknd) { dWknd.style.display = sel.some(function (c) { return c.classList.contains('wknd'); }) ? '' : 'none'; }
			loadSlots(lo, '', hi); // Rückgabe-Floor braucht den End-Tag (SPEC-RUECKGABE)
		}
		function setOneDay(td) { anchorDay = td.getAttribute('data-day'); applyRange(anchorDay, anchorDay); }
		mgrid.addEventListener('click', function (ev) {
			var td = ev.target.closest('td.s-free'); if (!td) { return; }
			var day = td.getAttribute('data-day');
			if (!anchorDay) { setOneDay(td); return; }
			var lo = (anchorDay <= day) ? anchorDay : day, hi = (anchorDay <= day) ? day : anchorDay;
			var contiguous = freeCells.filter(function (c) { var d = c.getAttribute('data-day'); return d >= lo && d <= hi; }).length;
			var span = Math.round((Date.parse(hi) - Date.parse(lo)) / 86400000) + 1;
			if (contiguous !== span) { setOneDay(td); return; }
			applyRange(lo, hi);
			anchorDay = null;
		});
	}

	// --- Fulfillment-Umschalter (C2) + „Zusammen/Getrennt"-Umschalter (Einführung+Abholung). ---
	var ffRadios = document.querySelectorAll('.zhl-ff-radio');
	var ffPickup = document.querySelector('.zhl-ff-pickup');
	var ffReturn = document.querySelector('.zhl-ff-return');
	var ffHauspost = document.querySelector('.zhl-ff-hauspost');
	var hpTitel = document.querySelector('.zhl-hp-titel');
	var projTitle = document.querySelector('input[name=projectTitle]');
	var hmRadios = document.querySelectorAll('.zhl-hm-radio');
	var combinedNote = document.querySelector('.zhl-combined-note');
	function curMode() { var v = 'getrennt'; hmRadios.forEach(function (r) { if (r.checked) { v = r.value; } }); return v; }
	// „Zusammen" aktiv? Dann steckt die Abholung im Einführungs-Slot → separater Abhol-Picker entfällt.
	function combinedActive() { return hmRadios && hmRadios.length > 0 && curMode() === 'zusammen' && curFulfillment() !== 'hauspost'; }
	function applyEinfRequired() {
		if (!einfWrap) { return; }
		var req = combinedActive() || einfWrap.getAttribute('data-required') === '1';
		einfWrap.querySelectorAll('input[name=einf_slot]').forEach(function (el) { el.required = req; });
	}
	function applyFulfillment() {
		var isHp = curFulfillment() === 'hauspost';
		var zusammen = combinedActive();
		var hidePickup = isHp || zusammen;
		var pMand = pickupWrap && pickupWrap.getAttribute('data-mandatory') === '1';
		if (ffPickup) {
			ffPickup.style.display = hidePickup ? 'none' : '';
			ffPickup.querySelectorAll('input[name=pickup_slot]').forEach(function (el) { el.disabled = hidePickup; el.required = (!hidePickup && pMand); });
		}
		// Rückgabe (SPEC-RUECKGABE): bei Hauspost ausblenden (Rückversand per Post), sonst zeigen.
		// Unabhängig von „Zusammen" (das betrifft nur Einführung+Abholung).
		if (ffReturn) {
			var rMand = returnWrap && returnWrap.getAttribute('data-mandatory') === '1';
			ffReturn.style.display = isHp ? 'none' : '';
			ffReturn.querySelectorAll('input[name=return_slot]').forEach(function (el) { el.disabled = isHp; el.required = (!isHp && rMand); });
		}
		if (ffHauspost) { ffHauspost.style.display = isHp ? '' : 'none'; ffHauspost.querySelectorAll('input').forEach(function (el) { el.required = isHp; }); }
		if (isHp && hpTitel && projTitle && hpTitel.value === '') { hpTitel.value = projTitle.value; }
		if (combinedNote) { combinedNote.style.display = zusammen ? '' : 'none'; }
		applyEinfRequired();
		updateSubmitState();
	}
	recomputeBlockedFromDom(); // Anfangszustand aus dem Server-Render übernehmen
	if (ffRadios.length) {
		ffRadios.forEach(function (r) { r.addEventListener('change', applyFulfillment); });
		if (projTitle) { projTitle.addEventListener('input', function () { if (hpTitel && hpTitel.value === '') { hpTitel.value = projTitle.value; } }); }
	}
	hmRadios.forEach(function (r) { r.addEventListener('change', applyFulfillment); });
	applyFulfillment(); // immer initial → Submit-Zustand + Modus-Sichtbarkeit setzen

	// --- Abhol-/Einführungs-Picker (AJAX-gerendert): Tag-Chip wählt Zeitliste (Event-Delegation). ---
	function bindDayChips(wrap) {
		if (!wrap) { return; }
		wrap.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.zhl-pickup-day'); if (!btn) { return; }
			var day = btn.getAttribute('data-day');
			wrap.querySelectorAll('.zhl-pickup-day').forEach(function (b) { b.classList.toggle('active', b === btn); });
			wrap.querySelectorAll('.zhl-pickup-times').forEach(function (tl) { tl.style.display = (tl.getAttribute('data-day') === day) ? '' : 'none'; });
		});
	}
	bindDayChips(pickupWrap);
	bindDayChips(einfWrap);
	bindDayChips(returnWrap);

	// --- Wochen-Raster (Slotmodus): freie Zeitspanne anklicken → Termine zum START laden. ---
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
			if (label) { label.textContent = sD.value + '  ' + sB.value + '–' + sE.value; }
			loadSlots(sD.value, sB.value, sD.value);
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
			if (label) { label.textContent = sD.value + '  ' + sB.value + '–' + sE.value; }
			loadSlots(sD.value, sB.value, sD.value);
			anchor = null;
		});
	}
})();
</script>

{include file='globalfooter.tpl'}
