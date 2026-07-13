{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	{if $Mode == 'success'}
		<div class="zhl-dash-head">
			<div>
				<div class="zhl-uplabel">Medienausleihe ZHL</div>
				<h1 class="zhl-h1"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Bundle gebucht</h1>
			</div>
		</div>
		<div class="zhl-book-card" style="max-width:620px;">
			<p class="zhl-sub">Deine Aufnahme-Reservierung ist angelegt.</p>
			{if $MainReference}<p>Buchungsnummer (Aufnahme): <strong>{$MainReference|escape}</strong></p>{/if}
			{if $AfterReference}<p>Buchungsnummer (Schnitt-/VR-PC): <strong>{$AfterReference|escape}</strong></p>{/if}
			{if $PickupInfo}<p class="zhl-muted zhl-small">{$PickupInfo|escape}</p>{/if}
			{if $AfterWarning}
				<div class="zhl-book-errors" style="margin-top:12px;"><strong>Hinweis:</strong> {$AfterWarning|escape}</div>
			{/if}
			{include file='zhl-mediainfo.tpl' MediaInfos=$MediaInfos}
			<div class="zhl-book-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
				<a class="zhl-btn" href="{$Path}zhl-bookings.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Meine Buchungen</a>
				<a class="zhl-btn zhl-btn-ghost" href="{$Path}zhl-assistant.php">Weiteres Bundle buchen</a>
			</div>
		</div>

	{else}
		<div class="zhl-dash-head">
			<div>
				<div class="zhl-uplabel">Medienausleihe ZHL</div>
				<h1 class="zhl-h1">{$BundleName|escape} buchen</h1>
				<p class="zhl-sub">Wähle den <strong>Aufnahme-Zeitraum</strong> und – falls nötig – eine <strong>Halterung</strong> und einen <strong>Schnitt-Termin danach</strong>. Alles wird <strong>in einer Reservierung</strong> gebucht.</p>
			</div>
		</div>

		<a class="zhl-back" href="{$Path}zhl-assistant.php?bundle={$BundleId}">← zurück zur Bundle-Übersicht</a>

		{if $Errors}
			<div class="zhl-book-errors">
				<strong>Buchung nicht möglich:</strong>
				<ul>{foreach from=$Errors item=e}<li>{$e|escape}</li>{/foreach}</ul>
				<div style="margin-top:10px;">
					<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-termin-anfrage.php?bundle={$BundleId}{if $ProjectTitle}&amp;pt={$ProjectTitle|escape:'url'}{/if}"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Passt der Zeitraum nicht? Wunschtermin anfragen</a>
				</div>
			</div>
		{/if}

		<form class="zhl-book-grid" method="post" action="{$Path}zhl-bundle-book.php" id="zhl-bundle-form">
			{csrf_token}
			<input type="hidden" name="bid" value="{$BundleId}">
			<input type="hidden" name="formNonce" value="{$FormNonce|escape}">

			{* Komposition / Packliste *}
			<div class="zhl-book-card">
				<div class="zhl-uplabel">Das ist im Bundle</div>
				{if $BundleHint}<p class="zhl-bundle-hint"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg> {$BundleHint|escape}</p>{/if}
				<input type="hidden" name="keep_ui" value="1">
				<ul class="zhl-bundle-items zhl-keep-list">
					{foreach from=$DisplayItems item=it}
						<li>
							<label class="zhl-keep">
								<input type="checkbox" name="keep_item[]" value="{$it.id}"{if $it.keep} checked{/if}>
								<span class="zhl-keep-label">{$it.quantity}× {$it.label|escape}{if !$it.required} <span class="zhl-opt">optional</span>{/if}{if $it.meta} <span class="zhl-it-note">{$it.meta|escape}</span>{/if}</span>
							</label>
						</li>
					{/foreach}
				</ul>
				<p class="zhl-muted zhl-small" style="margin-top:2px;">Häkchen entfernen, um eine Position wegzulassen (z. B. wenn du sie nicht brauchst).</p>

				{if $Packlist}
					<div class="zhl-uplabel" style="margin-top:14px;">Packliste (nicht buchbar)</div>
					<ul class="zhl-bundle-items">
						{foreach from=$Packlist item=p}
							<li><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 9.4 7.5 4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> {$p.label|escape}{if $p.meta} <span class="zhl-it-note">{$p.meta|escape}</span>{/if}</li>
						{/foreach}
					</ul>
				{/if}
			</div>

			{* Formular *}
			<div class="zhl-book-card">
				<div class="zhl-uplabel">Projekt</div>
				<div class="zhl-field" style="margin-bottom:16px;">
					<label>Titel des Projekts <span class="zhl-req">*</span></label>
					<input class="zhl-input" type="text" name="projectTitle" maxlength="120" required
						placeholder="z. B. Imagefilm Lehrstuhl XY" value="{$ProjectTitle|escape}">
				</div>

				<div class="zhl-uplabel">Aufnahme-Zeitraum</div>
				{if $Cal}
					<input type="hidden" name="dayStart" id="dayStart" value="{$SelStart|escape}">
					<input type="hidden" name="dayEnd" id="dayEnd" value="{$SelEnd|escape}">
					<div class="zhl-week-nav">
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-bundle-book.php?bid={$BundleId}&amp;rd={$Cal.prevMonth}">‹ Zurück</a>
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-bundle-book.php?bid={$BundleId}&amp;rd={$Cal.thisMonth}">Heute</a>
						<span class="zhl-week-label">{$Cal.monthLabel}</span>
						<a class="zhl-btn zhl-btn-ghost zhl-btn-sm" href="{$Path}zhl-bundle-book.php?bid={$BundleId}&amp;rd={$Cal.nextMonth}">Weiter ›</a>
					</div>
					<table class="zhl-monthgrid" data-max-days="{$MaxDays}">
						<thead><tr><th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th class="wknd">Sa</th><th class="wknd">So</th></tr></thead>
						<tbody>
							{foreach from=$Cal.weeks item=week}
								<tr>
									{foreach from=$week item=c}
										<td class="zhl-mg-cell s-{$c.state}{if !$c.inMonth} out{/if}{if $c.weekend} wknd{/if}" {if $c.state == 'free'}data-day="{$c.date}"{/if}>{$c.dom}</td>
									{/foreach}
								</tr>
							{/foreach}
						</tbody>
					</table>
					<div class="zhl-wg-legend zhl-muted zhl-small">
						<span><i class="lg lg-free"></i> frei (Leitgerät)</span>
						<span><i class="lg lg-busy"></i> belegt</span>
						<span><i class="lg lg-past"></i> nicht buchbar</span>
						<span><i class="lg lg-sel"></i> deine Auswahl</span>
						<strong id="zhl-day-label" style="margin-left:auto;">Klicke Start- und End-Tag an.</strong>
					</div>
					<p class="zhl-muted zhl-small" style="margin-top:6px;">Der Kalender zeigt grob die Verfügbarkeit des Leitgeräts. Die vollständige Prüfung aller Bundle-Geräte erfolgt beim Buchen.</p>
					{if $MaxDays > 0}<p class="zhl-muted zhl-small" style="margin-top:2px;"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Maximale Ausleihdauer: <strong>{$MaxDays} Tage</strong>. <em>Wichtig:</em> Die Geräte sind <strong>ab dem Abholtag</strong> für dich reserviert (nicht erst ab Einsatzbeginn) — die Dauer zählt also ab der Übergabe.</p>{/if}
				{else}
					<div class="zhl-book-errors">Für dieses Bundle ist kein buchbares Leitgerät hinterlegt — bitte beim ZHL-Team melden.</div>
				{/if}

				{* Alternativen (Stativ ODER Gimbal …) *}
				{if $AltGroups}
					<div class="zhl-uplabel" style="margin-top:16px;">Alternativen wählen</div>
					{foreach from=$AltGroups item=g}
						<div class="zhl-einf-pick"{if $g.mode == 'multi'} data-altmulti="1"{/if}>
							<div class="zhl-einf-head">{$g.group|capitalize|escape}{if $g.required} <span class="zhl-req">*</span>{/if}{if $g.mode == 'multi'} <span class="zhl-muted zhl-small">— mehrere möglich{if $g.required} (mind. eine){/if}</span>{/if}</div>
							{foreach from=$g.options item=opt name=optl}
								<label class="zhl-slot">
									{if $g.mode == 'multi'}
										<input type="checkbox" name="alt_{$g.group|escape}[]" value="{$opt.type|escape}"{if $opt.checked} checked{/if}>
									{else}
										<input type="radio" name="alt_{$g.group|escape}" value="{$opt.type|escape}" required{if $opt.checked} checked{/if}>
									{/if}
									<span>{$opt.type|escape}{if $opt.models} <span class="zhl-muted zhl-small">({foreach from=$opt.models item=m name=ml}{$m|escape}{if !$smarty.foreach.ml.last}, {/if}{/foreach})</span>{/if}</span>
								</label>
							{/foreach}
						</div>
					{/foreach}
				{/if}

				{* Übergabe / Abholung + Einführung — werden per AJAX zum GEWÄHLTEN Aufnahme-Start geladen
				   (zhl-bundle-book.php?ajax=slots), damit die Termine gegen dein Datum gefiltert werden und
				   kein Reload nötig ist (Titel/Auswahl bleiben erhalten). Datenattribute steuern das JS. *}
				{if $EinfCertified}<div class="zhl-ueb-item ok" style="margin-top:16px;"><strong>✓ Du bist bereits eingeführt</strong></div>{/if}
				{if $PickupActive || $EinfActive || $ReturnActive}
					<div id="zhl-slots"
						data-bid="{$BundleId}"
						data-pickup-active="{if $PickupActive}1{else}0{/if}"
						data-pickup-mandatory="{if $PickupMandatory}1{else}0{/if}"
						data-einf-active="{if $EinfActive}1{else}0{/if}"
						data-return-active="{if $ReturnActive}1{else}0{/if}"
						data-return-mandatory="{if $ReturnMandatory}1{else}0{/if}"
						data-sel-pickup="{$SelPickupSlot|escape}"
						data-sel-return="{$SelReturnSlot|escape}"
						data-sel-einf="{$SelEinfSlot|escape}">
						{if $CombineEligible}
<div class="zhl-handovermode" style="margin-bottom:8px;">
<div class="zhl-uplabel">Einführung und Abholung</div>
<label class="zhl-attr-check"><input type="radio" name="handover_mode" value="zusammen" class="zhl-hm-radio"{if $HandoverMode != 'getrennt'} checked{/if}><span><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Zusammen <span class="zhl-muted zhl-small">— ein gemeinsamer Termin für Einführung und Abholung</span></span></label>
<label class="zhl-attr-check"><input type="radio" name="handover_mode" value="getrennt" class="zhl-hm-radio"{if $HandoverMode == 'getrennt'} checked{/if}><span><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Getrennt <span class="zhl-muted zhl-small">— Einführung und Abholung an zwei verschiedenen Terminen</span></span></label>
</div>
{else}
<input type="hidden" name="handover_mode" value="getrennt">
{/if}
<p id="zhl-slot-hint" class="zhl-muted zhl-small" style="margin-top:16px;">⤴ Wähle oben den Aufnahme-Zeitraum — dann erscheinen hier die möglichen <strong>Abhol-</strong>{if $EinfActive}, <strong>Einführungs-</strong>{/if}{if $ReturnActive} und <strong>Rückgabe-</strong>{/if}termine.</p>
						<div id="zhl-pickup-wrap"></div>
						<div id="zhl-einf-wrap"></div>
						<div id="zhl-return-wrap"></div>
					</div>
				{/if}

				{* Folge-Phase: Schnitt-/VR-PC *}
				{if $HasAfter}
					<div class="zhl-seq" style="margin-top:16px;">
						<div class="zhl-seq-head"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg> Danach schneiden?</div>
						<label class="zhl-attr-check">
							<input type="checkbox" name="afterChosen" id="afterChosen" value="1" {if $AfterChosen}checked{/if}>
							<span>Schnitt-/VR-PC im Anschluss an die Aufnahme mitbuchen (getrennte Folge-Buchung).</span>
						</label>
						<div class="zhl-field" id="afterDaysWrap" style="margin-top:10px;{if !$AfterChosen}display:none;{/if}">
							<label>Dauer (Tage)</label>
							<select class="zhl-input" name="afterDays">
								{section name=dn start=3 loop=8}
									<option value="{$smarty.section.dn.index}" {if $AfterDays == $smarty.section.dn.index}selected{/if}>{$smarty.section.dn.index}</option>
								{/section}
							</select>
							<div class="zhl-muted zhl-small" style="margin-top:4px;">Zeitraum = direkt nach dem Aufnahme-Ende. Diese Folge-Buchung ist eigenständig — schlägt sie fehl, bleibt die Aufnahme trotzdem gebucht.</div>
						</div>
					</div>
				{/if}

				{* Pflicht-Angaben (z. B. Haftpflichtversicherung) — wie Einzelbuchung *}
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

				<div class="zhl-book-actions" style="margin-top:18px;">
					<button class="zhl-btn" type="submit" id="zhl-submit" {if !$Cal}disabled{/if}>Bundle verbindlich buchen ▸</button>
				</div>
				<p class="zhl-note">Verfügbarkeit, Vorlauf und Konflikte aller Geräte werden beim Buchen verbindlich geprüft. Es wird <strong>in einer Reservierung</strong> gebucht. <a href="{$Path}zhl-termin-anfrage.php?bundle={$BundleId}">Kein passender Zeitraum? Wunschtermin anfragen.</a></p>
			</div>
		</form>
	{/if}
</div>

<script>
(function () {
	var dStart = document.getElementById('dayStart'), dEnd = document.getElementById('dayEnd');
	var submit = document.getElementById('zhl-submit');
	var slotBox = document.getElementById('zhl-slots');
	var hint = document.getElementById('zhl-slot-hint');
	var pickupWrap = document.getElementById('zhl-pickup-wrap');
	var einfWrap = document.getElementById('zhl-einf-wrap');
	var returnWrap = document.getElementById('zhl-return-wrap');
	var hmRadios = document.querySelectorAll('.zhl-hm-radio');
	var lastVm = null;
	function curMode() { var v = 'zusammen'; hmRadios.forEach(function (r) { if (r.checked) { v = r.value; } }); return v; }
	function combinedActive() { return hmRadios && hmRadios.length > 0 && curMode() === 'zusammen' && slotBox && slotBox.getAttribute('data-einf-active') === '1' && slotBox.getAttribute('data-pickup-active') === '1'; }
	function applyMode() {
		var combined = combinedActive();
		var pBlock = false;
		if (combined) { if (pickupWrap) { pickupWrap.innerHTML = ''; } }
		else { pBlock = renderPickup(lastVm ? lastVm.pickup : null); }
		var eBlock = renderEinf(lastVm ? lastVm.einf : null, combined);
		var rBlock = renderReturn(lastVm ? lastVm.return : null);
		var needEinf = slotBox && slotBox.getAttribute('data-einf-active') === '1';
		var needPickup = !combined && slotBox && slotBox.getAttribute('data-pickup-mandatory') === '1';
		var needReturn = slotBox && slotBox.getAttribute('data-return-mandatory') === '1';
		if (eBlock && needEinf) { setSubmitBlocked(true, 'Kein Einführungstermin verfügbar — bitte anderen Zeitraum wählen.'); }
		else if (pBlock && needPickup) { setSubmitBlocked(true, 'Kein Abholtermin verfügbar — bitte anderen Zeitraum wählen.'); }
		else if (rBlock && needReturn) { setSubmitBlocked(true, 'Kein Rückgabetermin verfügbar — bitte anderen Zeitraum wählen.'); }
		else { setSubmitBlocked(false); }
	}
	// Modus-Wechsel (zusammen/getrennt): erst neu rendern; im Tagesmodus zusätzlich Slots neu laden,
	// da der Einführungs-Slot-Filter (Task B) serverseitig vom Modus abhängt.
	hmRadios.forEach(function (r) { r.addEventListener('change', function () { applyMode(); var ds = document.getElementById('dayStart'); if (ds && ds.value) { loadSlots(ds.value); } }); });
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }

	// --- Termine (Abholung/Einführung) per AJAX zum gewählten Start laden — ohne Reload. ---
	var fetchToken = 0;
	function setSubmitBlocked(blocked, reason) {
		if (!submit) { return; }
		submit.disabled = blocked;
		submit.title = blocked && reason ? reason : '';
	}
	function renderPickup(vm) {
		if (!pickupWrap) { return false; }
		if (!vm) { pickupWrap.innerHTML = ''; return false; }
		if (!vm.days || !vm.days.length) {
			pickupWrap.innerHTML = '<div class="zhl-ueb-item req" style="margin-top:16px;"><strong><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Kein Abholtermin verfügbar</strong> <span class="zhl-muted zhl-small">' +
				(vm.earliestLabel ? 'Frühester Termin: ' + esc(vm.earliestLabel) + '.' : 'Aktuell kein Abholtermin frei.') + '</span></div>';
			return vm.mandatory === true; // blockiert nur, wenn Abholung Pflicht ist
		}
		var sel = slotBox.getAttribute('data-sel-pickup') || '';
		var selDay = '';
		var req = vm.mandatory ? ' <span class="zhl-req">*</span>' : '';
		var h = '<div class="zhl-uplabel" style="margin-top:16px;">Abholung</div><div class="zhl-einf-pick zhl-pickup">' +
			'<div class="zhl-einf-head">Abholtermin wählen' + req + '</div><div class="zhl-pickup-daybar">';
		vm.days.forEach(function (d) { if ((d.slots || []).some(function (s) { return s.slot_id === sel; })) { selDay = d.date; } });
		vm.days.forEach(function (d, i) { var act = (selDay ? d.date === selDay : i === 0); h += '<button type="button" class="zhl-pickup-day' + (act ? ' active' : '') + '" data-day="' + esc(d.date) + '">' + esc(d.label) + '</button>'; });
		h += '</div>';
		vm.days.forEach(function (d, i) {
			var act = (selDay ? d.date === selDay : i === 0);
			h += '<div class="zhl-pickup-times" data-day="' + esc(d.date) + '"' + (act ? '' : ' style="display:none;"') + '>';
			(d.slots || []).forEach(function (s) {
				h += '<label class="zhl-pickup-pill"><input type="radio" name="pickup_slot" value="' + esc(s.slot_id) + '"' + (vm.mandatory ? ' required' : '') + (s.slot_id === sel ? ' checked' : '') + '><span>' + esc(s.timeLabel) + (s.memberName ? ' · ' + esc(s.memberName) : '') + '</span></label>';
			});
			h += '</div>';
		});
		h += '</div>';
		pickupWrap.innerHTML = h;
		return false;
	}
	function renderReturn(vm) {
		if (!returnWrap) { return false; }
		if (!vm) { returnWrap.innerHTML = ''; return false; }
		if (!vm.days || !vm.days.length) {
			returnWrap.innerHTML = '<div class="zhl-ueb-item req" style="margin-top:16px;"><strong><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Kein Rückgabetermin verfügbar</strong> <span class="zhl-muted zhl-small">' +
				(vm.earliestLabel ? 'Frühester Termin: ' + esc(vm.earliestLabel) + '.' : 'Aktuell kein Rückgabetermin frei.') + '</span></div>';
			return vm.mandatory === true; // blockiert nur, wenn Rückgabe Pflicht ist
		}
		var sel = slotBox.getAttribute('data-sel-return') || '';
		var selDay = '';
		var req = vm.mandatory ? ' <span class="zhl-req">*</span>' : '';
		var h = '<div class="zhl-uplabel" style="margin-top:16px;">Rückgabe</div><div class="zhl-einf-pick zhl-pickup">' +
			'<div class="zhl-einf-head">Rückgabetermin wählen' + req + ' <span class="zhl-muted zhl-small">— am/nach dem Nutzungsende</span></div><div class="zhl-pickup-daybar">';
		vm.days.forEach(function (d) { if ((d.slots || []).some(function (s) { return s.slot_id === sel; })) { selDay = d.date; } });
		vm.days.forEach(function (d, i) { var act = (selDay ? d.date === selDay : i === 0); h += '<button type="button" class="zhl-pickup-day' + (act ? ' active' : '') + '" data-day="' + esc(d.date) + '">' + esc(d.label) + '</button>'; });
		h += '</div>';
		vm.days.forEach(function (d, i) {
			var act = (selDay ? d.date === selDay : i === 0);
			h += '<div class="zhl-pickup-times" data-day="' + esc(d.date) + '"' + (act ? '' : ' style="display:none;"') + '>';
			(d.slots || []).forEach(function (s) {
				h += '<label class="zhl-pickup-pill"><input type="radio" name="return_slot" value="' + esc(s.slot_id) + '"' + (vm.mandatory ? ' required' : '') + (s.slot_id === sel ? ' checked' : '') + '><span>' + esc(s.timeLabel) + (s.memberName ? ' · ' + esc(s.memberName) : '') + '</span></label>';
			});
			h += '</div>';
		});
		h += '</div>';
		returnWrap.innerHTML = h;
		return false;
	}
	function renderEinf(vm, combined) {
		if (!einfWrap) { return false; }
		if (!vm || vm.certified) { einfWrap.innerHTML = ''; return false; }
		if (!vm.days || !vm.days.length) {
			einfWrap.innerHTML = '<div class="zhl-ueb-item req" style="margin-top:16px;"><strong><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Kein Einführungstermin verfügbar</strong> <span class="zhl-muted zhl-small">' +
				(vm.earliestLabel ? 'Frühester Termin: ' + esc(vm.earliestLabel) + '.' : 'Aktuell kein Termin frei.') + '</span></div>';
			return true; // Einführung ist Pflicht → ohne Termin keine Buchung
		}
		var sel = slotBox.getAttribute('data-sel-einf') || '';
		var selDay = '';
		vm.days.forEach(function (d) { if ((d.slots || []).some(function (s) { return s.slot_id === sel; })) { selDay = d.date; } });
		var note = combined ? '<div class="zhl-ueb-item ok" style="margin:8px 0;"><strong>✓ Ein Termin genügt</strong> <span class="zhl-muted zhl-small">Dieser Termin ist zugleich der Abholtermin — du bekommst die Geräte direkt im Anschluss.</span></div>' : '';
		// Task A: Mehrere einführungspflichtige Geräte-Typen → EIN gemeinsamer Termin deckt alle ab.
		if (vm.coveredDevices && vm.coveredDevices.length > 1) {
			var devList = vm.coveredDevices.map(function (n) { return esc(n); }).join(', ');
			note += '<div class="zhl-ueb-item ok" style="margin:8px 0;"><strong>✓ Eine Einführung für mehrere Geräte</strong> <span class="zhl-muted zhl-small">Dieser eine Termin führt dich in alle einführungspflichtigen Geräte dieses Bundles ein: ' + devList + '.</span></div>';
		}
		var h = '<div class="zhl-uplabel" style="margin-top:16px;">Einführung' + (combined ? ' &amp; Abholung' : '') + '</div>' + note + '<div class="zhl-einf-pick zhl-pickup">' +
			'<div class="zhl-einf-head">' + (combined ? 'Termin für Einführung + Abholung' : 'Einführungstermin wählen') + ' <span class="zhl-req">*</span></div><div class="zhl-pickup-daybar">';
		vm.days.forEach(function (d, i) { var act = (selDay ? d.date === selDay : i === 0); h += '<button type="button" class="zhl-pickup-day' + (act ? ' active' : '') + '" data-day="' + esc(d.date) + '">' + esc(d.label) + '</button>'; });
		h += '</div>';
		vm.days.forEach(function (d, i) {
			var act = (selDay ? d.date === selDay : i === 0);
			h += '<div class="zhl-pickup-times" data-day="' + esc(d.date) + '"' + (act ? '' : ' style="display:none;"') + '>';
			(d.slots || []).forEach(function (s) { h += '<label class="zhl-pickup-pill"><input type="radio" name="einf_slot" value="' + esc(s.slot_id) + '" required' + (s.slot_id === sel ? ' checked' : '') + '><span>' + esc(s.timeLabel) + (s.memberName ? ' · ' + esc(s.memberName) : '') + '</span></label>'; });
			h += '</div>';
		});
		h += '</div>';
		einfWrap.innerHTML = h;
		return false;
	}
	// B-remove: aktuelle Positions-Auswahl (Häkchen) als Query, damit die AJAX-Termine nur die
	// behaltenen Geräte berücksichtigen (abgewähltes einf-/abhol-pflichtiges Gerät → kein Pflicht-Slot).
	function keepQuery() {
		var boxes = document.querySelectorAll('.zhl-keep-list input[name="keep_item[]"]');
		if (!boxes.length) { return ''; }
		var qs = '&keep_ui=1';
		boxes.forEach(function (b) { if (b.checked) { qs += '&keep_item%5B%5D=' + encodeURIComponent(b.value); } });
		return qs;
	}
	// E1: gewählte Alternativ-Optionen (choice-Gruppen) mitgeben, damit der Server die Abhol-/Einführungs-
	// Slots nur gegen die TATSÄCHLICH gewählte Option filtert (sonst gilt ein Slot als frei, sobald
	// IRGENDEINE Option der Gruppe frei ist — auch die nicht gewählte). Auto-Gruppen haben keine Radios.
	function altQuery() {
		var qs = '';
		document.querySelectorAll('input[type="radio"][name^="alt_"]:checked').forEach(function (r) {
			qs += '&' + encodeURIComponent(r.name) + '=' + encodeURIComponent(r.value);
		});
		return qs;
	}
	function loadSlots(startYmd) {
		if (!slotBox || !startYmd) { return; }
		var bid = slotBox.getAttribute('data-bid');
		// Task B: Nutzungs-Ende mitgeben → der Server filtert Abhol-/Einführungs-Slots gegen die
		// Geräte-Verfügbarkeit über [Abholtag … Nutzungsende].
		var endYmd = (dEnd && dEnd.value) ? dEnd.value : startYmd;
		var needsSlot = (slotBox.getAttribute('data-einf-active') === '1') || (slotBox.getAttribute('data-pickup-mandatory') === '1') || (slotBox.getAttribute('data-return-mandatory') === '1');
		var token = ++fetchToken;
		// Während des Ladens: alte (zum alten Datum gehörende) Termine entfernen + bei Pflicht-Terminen Submit sperren.
		if (pickupWrap) { pickupWrap.innerHTML = ''; }
		if (einfWrap) { einfWrap.innerHTML = ''; }
		if (returnWrap) { returnWrap.innerHTML = ''; }
		if (needsSlot) { setSubmitBlocked(true, 'Termine werden geladen …'); }
		if (hint) { hint.innerHTML = '<svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Termine werden geladen …'; hint.style.display = ''; }
		fetch(window.location.pathname + '?ajax=slots&bid=' + encodeURIComponent(bid) + '&start=' + encodeURIComponent(startYmd) + '&end=' + encodeURIComponent(endYmd) + '&mode=' + encodeURIComponent(curMode()) + keepQuery() + altQuery(), { headers: { 'X-Requested-With': 'fetch' } })
			.then(function (r) { if (!r.ok) { throw new Error('http'); } return r.json(); })
			.then(function (j) {
				if (token !== fetchToken) { return; } // veraltete Antwort verwerfen
				if (!j || j.error) { throw new Error(j && j.error ? j.error : 'data'); }
				if (hint) { hint.style.display = 'none'; }
				lastVm = j;
				applyMode(); // rendert Abholung/Einführung je nach „zusammen/getrennt" + setzt Submit-Zustand
			})
			.catch(function () {
				if (token !== fetchToken) { return; }
				if (needsSlot) { setSubmitBlocked(true, 'Termine konnten nicht geladen werden.'); }
				if (hint) { hint.innerHTML = '<svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Termine konnten nicht geladen werden — bitte Zeitraum erneut wählen oder Seite neu laden.'; hint.style.display = ''; }
			});
	}

	// B-remove: Termine neu laden, wenn der Nutzer eine Bundle-Position ab-/zuwählt (Pflicht-Slots
	// können sich dadurch ändern). Nutzt den aktuell gewählten Aufnahme-Start (Hidden dayStart).
	document.querySelectorAll('.zhl-keep-list input[name="keep_item[]"]').forEach(function (b) {
		b.addEventListener('change', function () {
			var ds = document.getElementById('dayStart');
			if (ds && ds.value) { loadSlots(ds.value); }
		});
	});

	// E1: Wechselt der Nutzer eine Alternativ-Option (Stativ↔Gimbal o.ä.), die Slots neu gegen die
	// nun gewählte Option filtern (ein zuvor passender Slot kann mit der anderen Option belegt sein).
	document.querySelectorAll('input[type="radio"][name^="alt_"]').forEach(function (r) {
		r.addEventListener('change', function () {
			var ds = document.getElementById('dayStart');
			if (ds && ds.value) { loadSlots(ds.value); }
		});
	});

	// --- Monats-Kalender: zusammenhängende Spanne aus ganzen Tagen anklicken. ---
	var mgrid = document.querySelector('.zhl-monthgrid');
	if (mgrid) {
		var dLabel = document.getElementById('zhl-day-label');
		var maxDays = parseInt(mgrid.getAttribute('data-max-days') || '0', 10) || 0;
		var anchorDay = null;
		var freeCells = Array.prototype.slice.call(mgrid.querySelectorAll('td.s-free'));
		function clearDaySel() { mgrid.querySelectorAll('td.zhl-mg-cell.sel').forEach(function (c) { c.classList.remove('sel'); }); }
		function applyRange(a, b, fetchSlots) {
			var lo = (a <= b) ? a : b, hi = (a <= b) ? b : a;
			clearDaySel();
			var sel = freeCells.filter(function (c) { var d = c.getAttribute('data-day'); return d >= lo && d <= hi; });
			sel.forEach(function (c) { c.classList.add('sel'); });
			dStart.value = lo; dEnd.value = hi;
			if (dLabel) { dLabel.textContent = (lo === hi) ? ('Aufnahme: ' + lo) : ('Aufnahme: ' + lo + ' – ' + hi); }
			if (fetchSlots !== false) { loadSlots(lo); }
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
			if (maxDays > 0 && span > maxDays) {
				if (dLabel) { dLabel.textContent = 'Max. ' + maxDays + ' Tage (zählt ab Abholtag) — bitte kürzer wählen.'; }
				setOneDay(td); return;
			}
			applyRange(lo, hi);
			anchorDay = null;
		});
		// Re-Render nach POST-Fehler: vorgewählten Zeitraum wieder markieren + Termine laden.
		if (dStart.value) { applyRange(dStart.value, dEnd.value || dStart.value); }
	}

	// Abhol-/Einführungs-Picker (AJAX-gerendert): Tag-Chip wählt die sichtbare Zeitliste (Event-Delegation).
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

	// Folge-Phase: Dauer-Auswahl ein-/ausblenden.
	var afterChk = document.getElementById('afterChosen');
	var afterWrap = document.getElementById('afterDaysWrap');
	if (afterChk && afterWrap) {
		afterChk.addEventListener('change', function () { afterWrap.style.display = afterChk.checked ? '' : 'none'; });
	}

	// Doppel-Submit-Schutz (Client): Button nach Klick sperren.
	var form = document.getElementById('zhl-bundle-form');
	if (form && submit) {
		form.addEventListener('submit', function () {
			submit.disabled = true;
			submit.textContent = 'Wird gebucht …';
		});
	}
})();
</script>

{include file='globalfooter.tpl'}
