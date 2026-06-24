{include file='globalheader.tpl'}
{cssfile src="css/zhl-dashboard.css"}

<div class="zhl-dash">

	<div class="zhl-dash-head">
		<div>
			<div class="zhl-uplabel">Medienausleihe ZHL</div>
			<h1 class="zhl-h1">Geräte einzeln buchen</h1>
			<p class="zhl-sub">Wähle eine Kategorie und einen Zeitraum — du siehst sofort, welche Geräte frei sind. <span class="zhl-muted">({$TotalVisible} Geräte für dich sichtbar)</span></p>
		</div>
		{if $IsAdmin}<div><a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-bundles-admin.php">⚙ Bundles verwalten</a></div>{/if}
	</div>

	<nav class="zhl-modenav">
		<a class="zhl-mode" href="{$Path}zhl-assistant.php">🎯 Bundles buchen</a>
		<a class="zhl-mode active" href="{$Path}zhl-dashboard.php">🎛 Geräte einzeln buchen</a>
		<a class="zhl-mode" href="{$Path}my-calendar.php">📅 Meine Buchungen</a>
		<a class="zhl-mode zhl-mode-logout" href="{$Path}logout.php">Abmelden</a>
	</nav>

	<form class="zhl-controls" method="get" action="{$Path}zhl-dashboard.php">
		<input type="hidden" name="schedule" value="{$ActiveSchedule}">
		<div class="zhl-field zhl-grow">
			<label>Suche</label>
			<input class="zhl-input" type="text" name="q" value="{$Search|escape}" placeholder="Gerät oder Typ suchen, z. B. Funkmikrofon…">
		</div>
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
		<button class="zhl-btn" type="submit">Anzeigen</button>
	</form>

	<div class="zhl-shell">
		<aside class="zhl-side">
			<div class="zhl-uplabel">Kategorien</div>
			<nav class="zhl-nav">
				<a class="zhl-navlink {if $ActiveSchedule == 0}active{/if}" href="{$Path}zhl-dashboard.php?schedule=0&amp;start={$StartInput}&amp;days={$Days}&amp;q={$Search|escape:'url'}">
					<span>Alle</span><span class="zhl-count">{$TotalVisible}</span>
				</a>
				{foreach from=$Categories item=cat}
					<a class="zhl-navlink {if $cat.id == $ActiveSchedule}active{/if}" href="{$Path}zhl-dashboard.php?schedule={$cat.id}&amp;start={$StartInput}&amp;days={$Days}&amp;q={$Search|escape:'url'}">
						<span>{$cat.name|escape}</span><span class="zhl-count">{$cat.count}</span>
					</a>
				{/foreach}
			</nav>
		</aside>

		<main class="zhl-main">
			<div class="zhl-results-head">
				<h2 class="zhl-h2">Geräte</h2>
				<span class="zhl-muted">{$RangeLabel}</span>
			</div>

			{if $Pools}
				<div class="zhl-pools">
					<span class="zhl-pools-label">Verfügbar je Typ:</span>
					{foreach from=$Pools item=pool}
						<span class="zhl-pool {if $pool.free == 0}empty{/if}">{$pool.type|escape} <strong>{$pool.free}/{$pool.total}</strong></span>
					{/foreach}
				</div>
			{/if}

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
								<span class="zhl-badge free">{$row->freeCount}/{$row->totalDays} Tage frei</span>
							{elseif $row->minNoticeDays > 0}
								<span class="zhl-badge vorlauf">Vorlauf</span>
							{else}
								<span class="zhl-badge full">ausgebucht</span>
							{/if}
						</div>

						<div class="zhl-days">
							{foreach from=$row->days item=day}
								<div class="zhl-day {if $day.state == 'free'}f{elseif $day.state == 'vorlauf'}v{else}b{/if}" title="{$day.weekday} {$day.label} — {if $day.state == 'free'}frei{elseif $day.state == 'vorlauf'}Vorlauf, noch nicht buchbar{else}belegt{/if}">
									<span class="zhl-day-wd">{$day.weekday}</span>
									<span class="zhl-day-dt">{$day.label}</span>
								</div>
							{/foreach}
						</div>

						{if $row->minNoticeDays > 0}
							<div class="zhl-vorlauf-note">⏳ Vorlauf {$row->minNoticeDays} Tage — frühester Start: <strong>{$row->earliestLabel}</strong></div>
						{/if}

						<div class="zhl-card-foot">
							{if $row->anyFree}
								<span class="zhl-muted zhl-small">Nächster freier Tag: {$row->nextFreeLabel}</span>
								<a class="zhl-btn zhl-btn-sm" href="{$Path}zhl-book.php?rid={$row->id}&amp;sid={$row->scheduleId}&amp;rd={$StartInput}">Buchen ▸</a>
							{elseif $row->minNoticeDays > 0}
								<span class="zhl-muted zhl-small">Erst ab {$row->earliestLabel} buchbar (Vorlauf {$row->minNoticeDays} Tage)</span>
							{else}
								<span class="zhl-muted zhl-small">Im Zeitraum komplett belegt</span>
							{/if}
						</div>
					</div>
				{foreachelse}
					<div class="zhl-empty">Keine Geräte für diese Auswahl. Versuch eine andere Kategorie, einen anderen Zeitraum oder Suchbegriff.</div>
				{/foreach}
			</div>

			<p class="zhl-note">Prototyp: Verfügbarkeit als <strong>Prognose</strong>; die verbindliche Prüfung erfolgt beim Buchen.</p>
		</main>
	</div>
</div>

{include file='globalfooter.tpl'}
