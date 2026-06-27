{include file='globalheader.tpl'}

<div class="container my-4" style="max-width: 980px;">

	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h3 mb-0">Bundles verwalten</h1>
		<div class="d-flex gap-2">
			<a class="btn btn-outline-primary btn-sm" href="{$Path}zhl-dashboard.php">← Zum Dashboard</a>
			<a class="btn btn-outline-secondary btn-sm" href="{$Path}logout.php">Abmelden</a>
		</div>
	</div>
	<p class="text-muted">Bundles bündeln Geräte-Typen zu „Vorhaben". Typen kommen aus dem Attribut „Geräte-Typ" (im Geräte-Admin pflegbar).</p>

	{if isset($Message) && $Message neq ''}
		<div class="alert alert-success">{$Message|escape}</div>
	{/if}

	{* Neues Bundle *}
	<div class="card mb-4">
		<div class="card-header">Neues Bundle</div>
		<div class="card-body">
			<form method="post" action="{$Path}zhl-bundles-admin.php" class="row g-2 align-items-end">
				{csrf_token}
				<input type="hidden" name="action" value="create_bundle">
				<div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
				<div class="col-md-4"><label class="form-label">Wozu? (use_case)</label><input class="form-control" name="use_case"></div>
				<div class="col-md-2"><label class="form-label">Schwierigkeit</label>
					<select class="form-select" name="difficulty">
						{foreach from=$Difficulties item=d}<option value="{$d}">{$d}</option>{/foreach}
					</select>
				</div>
				<div class="col-md-2"><label class="form-label">Reihenfolge</label><input class="form-control" type="number" name="sort_order" value="0"></div>
				<div class="col-12"><label class="form-label">Hinweis</label><input class="form-control" name="hint" placeholder="z. B. Folien-Tipp"></div>
				<div class="col-md-3"><label class="form-label">Einweisung</label>
					<select class="form-select" name="einweisung_level">
						{foreach from=$EinweisungLevels item=l}<option value="{$l}">{$l}</option>{/foreach}
					</select>
				</div>
				<div class="col-md-9"><label class="form-label">Einweisungs-Link (optional)</label><input class="form-control" name="einweisung_url" placeholder="https://… Termin-/Seminar-Link"></div>
				<div class="col-12"><label class="form-label">Einweisungs-Text</label><input class="form-control" name="einweisung_text" placeholder="z. B. Vor dem Studio ist eine Einführung zwingend …"></div>
				<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="offer_schnitt" value="1" id="osnew"><label class="form-check-label" for="osnew">Schnitt-Folgebuchung anbieten (Schnitt-/VR-PC nach der Aufnahme)</label></div></div>
				<div class="col-12"><button class="btn btn-primary">Bundle anlegen</button></div>
			</form>
		</div>
	</div>

	{* Bestehende Bundles *}
	{foreach from=$Bundles item=bundle}
		<div class="card mb-3 {if !$bundle.active}border-secondary{/if}">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span>{$bundle.name|escape} {if !$bundle.active}<span class="badge bg-secondary">verborgen</span>{/if}</span>
				<span class="text-muted small">#{$bundle.id} · {$bundle.difficulty}</span>
			</div>
			<div class="card-body">
				{* Bundle bearbeiten *}
				<form method="post" action="{$Path}zhl-bundles-admin.php" class="row g-2 align-items-end mb-3">
					{csrf_token}
					<input type="hidden" name="action" value="update_bundle">
					<input type="hidden" name="bundle_id" value="{$bundle.id}">
					<div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" value="{$bundle.name|escape}"></div>
					<div class="col-md-4"><label class="form-label">Wozu?</label><input class="form-control" name="use_case" value="{$bundle.use_case|escape}"></div>
					<div class="col-md-2"><label class="form-label">Schwierigkeit</label>
						<select class="form-select" name="difficulty">
							{foreach from=$Difficulties item=d}<option value="{$d}" {if $bundle.difficulty == $d}selected{/if}>{$d}</option>{/foreach}
						</select>
					</div>
					<div class="col-md-2"><label class="form-label">Reihenfolge</label><input class="form-control" type="number" name="sort_order" value="{$bundle.sort_order}"></div>
					<div class="col-12"><label class="form-label">Hinweis</label><input class="form-control" name="hint" value="{$bundle.hint|escape}"></div>
					<div class="col-md-3"><label class="form-label">Einweisung</label>
						<select class="form-select" name="einweisung_level">
							{foreach from=$EinweisungLevels item=l}<option value="{$l}" {if $bundle.einweisung_level == $l}selected{/if}>{$l}</option>{/foreach}
						</select>
					</div>
					<div class="col-md-9"><label class="form-label">Einweisungs-Link (optional)</label><input class="form-control" name="einweisung_url" value="{$bundle.einweisung_url|escape}" placeholder="https://…"></div>
					<div class="col-12"><label class="form-label">Einweisungs-Text</label><input class="form-control" name="einweisung_text" value="{$bundle.einweisung_text|escape}"></div>
					<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="offer_schnitt" value="1" id="os{$bundle.id}" {if $bundle.offer_schnitt}checked{/if}><label class="form-check-label" for="os{$bundle.id}">Schnitt-Folgebuchung anbieten</label></div></div>
					<div class="col-12 d-flex gap-2">
						<button class="btn btn-primary btn-sm">Speichern</button>
					</div>
				</form>

				{* Positionen *}
				<h6 class="text-muted">Positionen</h6>
				<table class="table table-sm align-middle">
					<thead><tr><th>Typ (Nutzer-Begriff)</th><th>Menge</th><th>Pflicht</th><th>Verknüpfte Geräte (intern)</th><th>Hinweis</th><th></th></tr></thead>
					<tbody>
						{foreach from=$bundle.items item=item}
							<tr>
								<td>{$item.type_label|escape}</td>
								<td>{$item.quantity}×</td>
								<td>{if $item.required}<span class="badge bg-primary">Pflicht</span>{else}<span class="badge bg-light text-dark">optional</span>{/if}</td>
									<td class="small">{if $item.resolvedKind == 'specific'}<span class="badge bg-success">konkret</span> {$item.resolvedLabel|escape}{elseif $item.resolvedKind == 'packlist'}<span class="badge bg-light text-dark">Packliste</span> <span class="text-muted">kein buchbares Gerät</span>{elseif $item.resolvedKind == 'none'}<span class="badge bg-danger"><svg class="zhl-ic" style="width:1.05em;height:1.05em;vertical-align:-0.16em;flex:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> kein Gerät</span> <span class="text-muted">Typ deckt aktuell nichts ab</span>{else}<span class="text-muted">{$item.resolvedCount}×:</span> {$item.resolvedLabel|escape}{/if}</td>
								<td class="text-muted small">{$item.note|escape}</td>
								<td class="text-end">
									<form method="post" action="{$Path}zhl-bundles-admin.php" onsubmit="return confirm('Position entfernen?');" class="d-inline">
										{csrf_token}
										<input type="hidden" name="action" value="delete_item">
										<input type="hidden" name="item_id" value="{$item.id}">
										<button class="btn btn-outline-danger btn-sm">×</button>
									</form>
								</td>
							</tr>
						{foreachelse}
							<tr><td colspan="6" class="text-muted">Noch keine Positionen.</td></tr>
						{/foreach}
					</tbody>
				</table>

				{* Position hinzufügen *}
				<form method="post" action="{$Path}zhl-bundles-admin.php" class="row g-2 align-items-end">
					{csrf_token}
					<input type="hidden" name="action" value="add_item">
					<input type="hidden" name="bundle_id" value="{$bundle.id}">
					<div class="col-md-4"><label class="form-label">Typ</label>
						<select class="form-select" name="type_label" required><option value="">– Geräte-Typ wählen –</option>{foreach from=$KnownTypes item=t}<option value="{$t|escape}">{$t|escape}</option>{/foreach}</select>
					</div>
					<div class="col-md-2"><label class="form-label">Menge</label><input class="form-control" type="number" name="quantity" value="1" min="1"></div>
					<div class="col-md-2"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="required" value="1" id="req{$bundle.id}" checked><label class="form-check-label" for="req{$bundle.id}">Pflicht</label></div></div>
					<div class="col-md-4"><label class="form-label">Hinweis</label><input class="form-control" name="note"></div>
					<div class="col-12"><button class="btn btn-outline-primary btn-sm">Position hinzufügen</button></div>
				</form>
			</div>
			<div class="card-footer d-flex gap-2 justify-content-end">
				<form method="post" action="{$Path}zhl-bundles-admin.php" class="d-inline">
					{csrf_token}<input type="hidden" name="action" value="toggle_active"><input type="hidden" name="bundle_id" value="{$bundle.id}">
					<button class="btn btn-outline-secondary btn-sm">{if $bundle.active}Verbergen{else}Sichtbar machen{/if}</button>
				</form>
				<form method="post" action="{$Path}zhl-bundles-admin.php" onsubmit="return confirm('Bundle „{$bundle.name|escape}" wirklich löschen?');" class="d-inline">
					{csrf_token}<input type="hidden" name="action" value="delete_bundle"><input type="hidden" name="bundle_id" value="{$bundle.id}">
					<button class="btn btn-outline-danger btn-sm">Bundle löschen</button>
				</form>
			</div>
		</div>
	{foreachelse}
		<div class="alert alert-info">Noch keine Bundles. Lege oben das erste an.</div>
	{/foreach}

	<datalist id="zhl-types">
		{foreach from=$KnownTypes item=t}<option value="{$t|escape}">{/foreach}
	</datalist>

</div>

{include file='globalfooter.tpl'}
