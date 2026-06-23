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
		<a class="zhl-mode active" href="{$Path}zhl-assistant.php">🎯 Bundles buchen</a>
		<a class="zhl-mode" href="{$Path}zhl-dashboard.php">🎛 Geräte einzeln buchen</a>
		<a class="zhl-mode" href="{$Path}mycalendar.php">📅 Meine Buchungen</a>
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

			{if $Selected->hint}<p class="zhl-bundle-hint">💡 {$Selected->hint|escape}</p>{/if}

			<h3 class="zhl-h3" style="margin-top:6px;">Das gehört dazu</h3>
			<ul class="zhl-bundle-items">
				{foreach from=$Selected->items item=it}
					<li class="{if $it->required && !$it->ok}miss{/if}">
						<a class="zhl-it-link" href="{$Path}zhl-dashboard.php?q={$it->type|escape:'url'}&amp;start={$StartInput}&amp;days={$Days}">{$it->quantity}× {$it->type|escape}</a>
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
			</div>
			<p class="zhl-note" style="margin-top:10px;">Klick auf eine Position, um die konkreten Geräte zu sehen und einzeln zu buchen. (Sammel-Buchung folgt.)</p>
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
