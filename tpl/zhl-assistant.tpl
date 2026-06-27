{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	<div class="zhl-dash-head">
		<div>
			<div class="zhl-uplabel">Medienausleihe ZHL</div>
			<h1 class="zhl-h1">Bundles buchen</h1>
			<p class="zhl-sub">Sag uns, was du vorhast — wir schlagen dir das passende Set vor und zeigen, ob es im Zeitraum frei ist.</p>
		</div>
	</div>

	<nav class="zhl-modenav">
		<a class="zhl-mode active" href="{$Path}zhl-assistant.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> Bundles buchen</a>
		<a class="zhl-mode" href="{$Path}zhl-dashboard.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" y1="4" x2="14" y2="4"/><line x1="10" y1="4" x2="3" y2="4"/><line x1="21" y1="12" x2="12" y2="12"/><line x1="8" y1="12" x2="3" y2="12"/><line x1="21" y1="20" x2="16" y2="20"/><line x1="12" y1="20" x2="3" y2="20"/><line x1="14" y1="2" x2="14" y2="6"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="16" y1="18" x2="16" y2="22"/></svg> Geräte einzeln buchen</a>
		<a class="zhl-mode" href="{$Path}zhl-bookings.php"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Meine Buchungen</a>
		<a class="zhl-mode zhl-mode-logout" href="{$Path}logout.php">Abmelden</a>
	</nav>

	<form class="zhl-controls" method="get" action="{$Path}zhl-assistant.php">
		<input type="hidden" name="bundle" value="{if $Selected}{$Selected->id}{else}0{/if}">
		<div class="zhl-field">
			<label>Ab</label>
			<input class="zhl-input" type="date" name="start" value="{$StartInput}">
		</div>
		<div class="zhl-field">
			<label>Tage</label>
			<select class="zhl-input" name="days">
				<option value="1" {if $Days == 1}selected{/if}>1</option>
				<option value="3" {if $Days == 3}selected{/if}>3</option>
				<option value="7" {if $Days == 7}selected{/if}>7</option>
				<option value="14" {if $Days == 14}selected{/if}>14</option>
				<option value="21" {if $Days == 21}selected{/if}>21</option>
				<option value="30" {if $Days == 30}selected{/if}>30</option>
			</select>
		</div>
		<button class="zhl-btn" type="submit">Zeitraum übernehmen</button>
		<span class="zhl-muted zhl-small">{$RangeLabel}</span>
	</form>

	{if $Selected}
		{* Schritt 2: Bundle-Detail *}
		<div class="zhl-steps"><span class="zhl-step done">1 · Vorhaben</span><span class="zhl-step active">2 · Vorschlag</span></div>
		<a class="zhl-back" href="{$Path}zhl-assistant.php?start={$StartInput}&amp;days={$Days}">← anderes Vorhaben wählen</a>

		<div class="zhl-bundle-card zhl-bundle-detail {if !$Selected->available}is-unavail{/if}">
			<div class="zhl-bundle-top">
				<div class="zhl-card-titles">
					{if $Selected->useCase}<div class="zhl-eyebrow">{$Selected->useCase|escape}</div>{/if}
					<h2 class="zhl-h2">{$Selected->name|escape}</h2>
				</div>
				<span class="zhl-diff {$Selected->difficulty}">{if $Selected->difficulty == 'einfach'}Einfach{elseif $Selected->difficulty == 'fortgeschritten'}Fortgeschritten{else}Profi{/if}</span>
			</div>

			{if $Selected->hint}<p class="zhl-bundle-hint"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg> {$Selected->hint|escape}</p>{/if}

			{if $Selected->einweisungLevel == 'zwingend'}
				<div class="zhl-einw zwingend">
					<div class="zhl-einw-head"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Einweisung erforderlich</div>
					<p class="zhl-einw-text">{$Selected->einweisungText|escape}</p>
					<div class="zhl-einw-ways">
						<span class="zhl-einw-way"><strong>A) Monatsseminar</strong> „Studio-Einführung" – 1× pro Monat.</span>
						<span class="zhl-einw-way"><strong>B) Einzeltermin</strong> mit dem ZHL-Team vor Ort.</span>
					</div>
					{if $Selected->einweisungUrl}<a class="zhl-btn zhl-btn-sm" href="{$Selected->einweisungUrl|escape}" target="_blank" rel="noopener">Einweisungstermin buchen ▸</a>{/if}
					<p class="zhl-einw-fine">Hinweis: Diese Voraussetzung wird beim Buchen noch nicht automatisch geprüft – bitte vorab klären.</p>
				</div>
			{elseif $Selected->einweisungLevel == 'empfehlenswert'}
				<div class="zhl-einw empf">
					<div class="zhl-einw-head"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg> Einweisung empfehlenswert</div>
					<p class="zhl-einw-text">{$Selected->einweisungText|escape}</p>
					{if $Selected->einweisungUrl}<a class="zhl-btn zhl-btn-sm zhl-btn-ghost" href="{$Selected->einweisungUrl|escape}" target="_blank" rel="noopener">Termin ansehen ▸</a>{/if}
				</div>
			{elseif $Selected->einweisungLevel == 'beratung'}
				<div class="zhl-einw berat">
					<div class="zhl-einw-head"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> Experten-Beratung empfohlen</div>
					<p class="zhl-einw-text">{$Selected->einweisungText|escape}</p>
					{if $Selected->einweisungUrl}<a class="zhl-btn zhl-btn-sm zhl-btn-ghost" href="{$Selected->einweisungUrl|escape}" target="_blank" rel="noopener">Beratungstermin anfragen ▸</a>{/if}
				</div>
			{/if}

			<h3 class="zhl-h3" style="margin-top:6px;">Das gehört dazu</h3>
			<ul class="zhl-bundle-items">
				{foreach from=$Selected->items item=it}
					<li class="{if $it->required && !$it->ok}miss{/if}">
						<a class="zhl-it-link" href="{$Path}zhl-dashboard.php?q={$it->type|escape:'url'}&amp;start={$StartInput}&amp;days={$Days}">{$it->quantity}× {$it->type|escape}</a>
						{if $it->infoUrl != ''}<a class="zhl-it-info" href="{$it->infoUrl|escape}" target="_blank" rel="noopener" title="{if $it->infoText != ''}{$it->infoText|escape}{else}Info-Material{/if}" aria-label="Info-Material" data-en-title="Info material"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></a>{/if}
						{if $it->altGroup && $it->altOptions|@count > 1}<span class="zhl-it-note" data-en="auto choice, otherwise alternative">automatische Auswahl, sonst Alternative</span>{/if}
						{if !$it->required}<span class="zhl-opt">optional</span>{/if}
						{if $it->note}<span class="zhl-it-note">{$it->note|escape}</span>{/if}
						<span class="zhl-it-free {if $it->required && !$it->ok}bad{/if}">{$it->free} frei</span>
					</li>
				{/foreach}
			</ul>

			<div class="zhl-bundle-foot">
				{if $Selected->available}
					<span class="zhl-badge free">verfügbar im Zeitraum</span>
				{else}
					<span class="zhl-badge full">nicht komplett frei</span>
				{/if}
				<a class="zhl-btn" href="{$Path}zhl-bundle-book.php?bid={$Selected->id}" style="margin-left:auto;">Bundle in einer Reservierung buchen ▸</a>
			</div>
			<p class="zhl-note" style="margin-top:10px;">Buche das ganze Bundle in einer Reservierung — oder klick auf eine Position, um die konkreten Geräte einzeln zu buchen.</p>

			{if $Selected->offerSchnitt}
				<div class="zhl-seq">
					<div class="zhl-seq-head"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg> Danach schneiden?</div>
					<p class="zhl-seq-text">Nach der Aufnahme kannst du am <strong>Schnittplatz</strong> schneiden – eine <strong>getrennte Buchung</strong> für die Zeit <em>nach</em> deiner Drehphase, ganz optional.{if $SchnittPool !== null} <span class="zhl-muted">({$SchnittPool} Schnitt-/VR-PC frei)</span>{/if}</p>
					<a class="zhl-btn zhl-btn-sm zhl-btn-ghost" href="{$Path}zhl-dashboard.php?q={$SchnittType|escape:'url'}&amp;start={$SchnittStartInput}&amp;days=7">Schnittplatz ab {$SchnittStartInput} ansehen ▸</a>
				</div>
			{/if}
		</div>
	{else}
		{* Schritt 1: Vorhaben wählen *}
		<div class="zhl-steps"><span class="zhl-step active">1 · Vorhaben</span><span class="zhl-step">2 · Vorschlag</span></div>
		<h2 class="zhl-h2" style="margin:6px 0 14px;">Was möchtest du machen?</h2>

		<div class="zhl-bundle-grid">
			{foreach from=$Bundles item=b}
				<a class="zhl-goal-card {if !$b->available}is-unavail{/if}" href="{$Path}zhl-assistant.php?bundle={$b->id}&amp;start={$StartInput}&amp;days={$Days}">
					<div class="zhl-bundle-top">
						<div class="zhl-card-titles">
							{if $b->useCase}<div class="zhl-eyebrow">{$b->useCase|escape}</div>{/if}
							<h3 class="zhl-card-title">{$b->name|escape}</h3>
						</div>
						<span class="zhl-diff {$b->difficulty}">{if $b->difficulty == 'einfach'}Einfach{elseif $b->difficulty == 'fortgeschritten'}Fortgeschritten{else}Profi{/if}</span>
					</div>
					<div class="zhl-goal-foot">
						{if $b->available}<span class="zhl-badge free">verfügbar</span>{else}<span class="zhl-badge full">nicht komplett frei</span>{/if}
						{if $b->einweisungLevel == 'zwingend'}<span class="zhl-chip zwingend"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Einweisung</span>
						{elseif $b->einweisungLevel == 'empfehlenswert'}<span class="zhl-chip empf"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg> Einweisung</span>
						{elseif $b->einweisungLevel == 'beratung'}<span class="zhl-chip berat"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> Beratung</span>{/if}
						<span class="zhl-goal-go">wählen ▸</span>
					</div>
				</a>
			{foreachelse}
				<div class="zhl-empty">Noch keine Bundles angelegt.</div>
			{/foreach}
		</div>
	{/if}
</div>

{include file='globalfooter.tpl'}
