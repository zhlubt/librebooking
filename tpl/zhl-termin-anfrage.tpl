{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	{if $Mode == 'sent'}
		<div class="zhl-dash-head">
			<div>
				<div class="zhl-uplabel">Medienausleihe ZHL</div>
				<h1 class="zhl-h1"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Anfrage gesendet</h1>
			</div>
		</div>
		<div class="zhl-book-card" style="max-width:560px;">
			<p class="zhl-sub">Vielen Dank! Deine Wunschtermin-Anfrage ist beim ZHL-Medien-Team eingegangen.</p>
			<div class="zhl-ueb-item ok" style="margin:10px 0;display:block;">
				<strong>Wie geht es weiter?</strong>
				<span class="zhl-muted zhl-small">Das Team meldet sich mit einem passenden Termin oder einer Rückfrage. Eine Kopie der Anfrage ging an deine E-Mail-Adresse. Du kannst offene Anfragen unter „Meine Buchungen" einsehen und zurückziehen.</span>
			</div>
			<div class="zhl-book-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
				<a class="zhl-btn" href="{$Path}zhl-bookings.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Meine Buchungen</a>
				<a class="zhl-btn zhl-btn-ghost" href="{$Path}zhl-dashboard.php">Zur Geräteauswahl</a>
			</div>
		</div>

	{else}
		<div class="zhl-dash-head">
			<div>
				<div class="zhl-uplabel">Medienausleihe ZHL</div>
				<h1 class="zhl-h1">Wunschtermin anfragen</h1>
				<p class="zhl-sub">Kein passender Termin frei? Sag uns deinen <strong>Wunsch-Zeitraum</strong> — das ZHL-Medien-Team stimmt sich mit dir ab und trägt die Buchung ein.</p>
			</div>
		</div>

		<a class="zhl-back" href="{$Path}zhl-dashboard.php">← zurück zur Geräteauswahl</a>

		{if $Errors}
			<div class="zhl-book-errors">
				<strong>Bitte prüfen:</strong>
				<ul>{foreach from=$Errors item=e}<li>{$e|escape}</li>{/foreach}</ul>
			</div>
		{/if}

		<div class="zhl-book-grid">
			<div class="zhl-book-card">
				<div class="zhl-uplabel">{if $Kind == 'bundle'}Dein Bundle{else}Dein Gerät{/if}</div>
				<h2 class="zhl-h2">{$Label|escape}</h2>
				<div class="zhl-ueb-item" style="margin-top:12px;display:block;">
					<strong><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Hinweis</strong>
					<span class="zhl-muted zhl-small">Eine Anfrage <strong>reserviert das Gerät nicht</strong> automatisch — sie ist ein Wunsch, den das Team verbindlich mit dir abstimmt. Die normalen Mindest-Vorlaufzeiten gelten weiter; kurzfristige Termine kann nur das Team eintragen.</span>
				</div>
			</div>

			<form class="zhl-book-card" method="post" action="{$Path}zhl-termin-anfrage.php">
				{csrf_token}
				{if $Kind == 'bundle'}<input type="hidden" name="bundle" value="{$CtxId}">{else}<input type="hidden" name="rid" value="{$CtxId}">{/if}

				<div class="zhl-uplabel">Wunsch-Zeitraum</div>
				<div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
					<div class="zhl-field" style="flex:1; min-width:140px;">
						<label>Von <span class="zhl-req">*</span></label>
						<input class="zhl-input" type="date" name="wunschStart" value="{$WunschStart|escape}" required>
					</div>
					<div class="zhl-field" style="flex:1; min-width:140px;">
						<label>Bis <span class="zhl-req">*</span></label>
						<input class="zhl-input" type="date" name="wunschEnd" value="{$WunschEnd|escape}" required>
					</div>
				</div>

				<div class="zhl-uplabel">Projekt</div>
				<div class="zhl-field" style="margin-bottom:16px;">
					<label>Titel des Projekts</label>
					<input class="zhl-input" type="text" name="projectTitle" maxlength="120" placeholder="z. B. Podcast-Aufnahme Lehrstuhl XY" value="{$ProjectTitle|escape}">
				</div>

				<div class="zhl-uplabel">Nachricht ans Team</div>
				<div class="zhl-field" style="margin-bottom:16px;">
					<label>Wann hättest du gern? Besonderheiten?</label>
					<textarea class="zhl-input" name="message" rows="4" maxlength="2000" placeholder="z. B. „Möglichst in der Woche ab 14.7., vormittags. Brauche das Gerät für 2 Tage.“">{$Message|escape}</textarea>
				</div>

				<button type="submit" class="zhl-btn" style="width:100%;">Anfrage senden ▸</button>
				{if $Recipient != ''}<p class="zhl-muted zhl-small" style="margin-top:10px;">Geht an das ZHL-Medien-Team; du bekommst eine Kopie per E-Mail.</p>{/if}
			</form>
		</div>
	{/if}

</div>

{include file='globalfooter.tpl'}
