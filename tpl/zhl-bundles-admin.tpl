{include file='globalheader.tpl'}

<div class="container my-4" style="max-width: 980px;">

	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h3 mb-0">Bundles verwalten</h1>
		<a class="btn btn-outline-primary btn-sm" href="{$Path}zhl-dashboard.php">← Zum Dashboard</a>
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
				<div class="col-12"><label class="form-label">Hinweis</label><input class="form-control" name="hint" placeholder="z. B. Einweisung zwingend / Folien-Tipp"></div>
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
					<div class="col-12 d-flex gap-2">
						<button class="btn btn-primary btn-sm">Speichern</button>
					</div>
				</form>

				{* Positionen *}
				<h6 class="text-muted">Positionen</h6>
				<table class="table table-sm align-middle">
					<thead><tr><th>Typ</th><th>Menge</th><th>Pflicht</th><th>Hinweis</th><th></th></tr></thead>
					<tbody>
						{foreach from=$bundle.items item=item}
							<tr>
								<td>{$item.type_label|escape}</td>
								<td>{$item.quantity}×</td>
								<td>{if $item.required}<span class="badge bg-primary">Pflicht</span>{else}<span class="badge bg-light text-dark">optional</span>{/if}</td>
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
							<tr><td colspan="5" class="text-muted">Noch keine Positionen.</td></tr>
						{/foreach}
					</tbody>
				</table>

				{* Position hinzufügen *}
				<form method="post" action="{$Path}zhl-bundles-admin.php" class="row g-2 align-items-end">
					{csrf_token}
					<input type="hidden" name="action" value="add_item">
					<input type="hidden" name="bundle_id" value="{$bundle.id}">
					<div class="col-md-4"><label class="form-label">Typ</label>
						<input class="form-control" name="type_label" list="zhl-types" placeholder="z. B. Funkmikrofon" required>
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
