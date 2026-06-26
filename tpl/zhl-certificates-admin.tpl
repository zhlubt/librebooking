{include file='globalheader.tpl'}

<div class="container my-4" style="max-width: 1000px;">

	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h3 mb-0">Einführungs-Zertifikate verwalten</h1>
		<div class="d-flex gap-2">
			<a class="btn btn-outline-primary btn-sm" href="{$Path}zhl-dashboard.php">← Zum Dashboard</a>
			<a class="btn btn-outline-secondary btn-sm" href="{$Path}logout.php">Abmelden</a>
		</div>
	</div>
	<p class="text-muted">Benannte Zertifikate (z. B. „Einführung ins Videostudio") decken eine pflegbare Geräte-Liste ab. Wer ein gültiges Zertifikat hat, bucht das jeweilige Material <strong>ohne Einführungstermin</strong> (Abholung bleibt nötig). Änderungen wirken sofort (Projektion auf das Buchungs-Gate).</p>

	{if isset($Message) && $Message neq ''}
		<div class="alert alert-success">{$Message|escape}</div>
	{/if}

	{* Neues Zertifikat *}
	<div class="card mb-4">
		<div class="card-header">Neues Zertifikat</div>
		<div class="card-body">
			<form method="post" action="{$Path}zhl-certificates-admin.php" class="row g-2 align-items-end">
				{csrf_token}
				<input type="hidden" name="action" value="create_type">
				<div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" placeholder="z. B. Einführung in Drohne" required></div>
				<div class="col-md-4"><label class="form-label">Bestätigungs-Mail (Einweiser)</label><input class="form-control" type="email" name="confirm_email" placeholder="optional — erhält den Bestätigungs-Link"></div>
				<div class="col-md-2"><label class="form-label">Reihenfolge</label><input class="form-control" type="number" name="sort_order" value="0"></div>
				<div class="col-12"><button class="btn btn-primary">Anlegen</button></div>
			</form>
		</div>
	</div>

	{foreach from=$Types item=t}
		<div class="card mb-3 {if !$t.active}border-secondary{/if}">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span>{$t.name|escape} {if !$t.active}<span class="badge bg-secondary">verborgen</span>{/if}</span>
				<span class="text-muted small">#{$t.id} · {$t.resources|@count} Geräte · {$t.grants|@count} Zuweisungen</span>
			</div>
			<div class="card-body">

				{* Name bearbeiten *}
				<form method="post" action="{$Path}zhl-certificates-admin.php" class="row g-2 align-items-end mb-3">
					{csrf_token}
					<input type="hidden" name="action" value="update_type">
					<input type="hidden" name="type_id" value="{$t.id}">
					<div class="col-md-5"><label class="form-label">Name</label><input class="form-control" name="name" value="{$t.name|escape}"></div>
					<div class="col-md-4"><label class="form-label">Bestätigungs-Mail (Einweiser)</label><input class="form-control" type="email" name="confirm_email" value="{$t.confirm_email|escape}" placeholder="optional"></div>
					<div class="col-md-1"><label class="form-label">Reihenf.</label><input class="form-control" type="number" name="sort_order" value="{$t.sort_order}"></div>
					<div class="col-md-2"><button class="btn btn-outline-primary w-100">Speichern</button></div>
				</form>

				<div class="row">
					{* Abgedeckte Geräte *}
					<div class="col-md-6">
						<h6 class="text-muted">Abgedeckte Geräte</h6>
						{if $t.resources}
							<ul class="list-group list-group-flush mb-2">
								{foreach from=$t.resources item=r}
									<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
										<span>{$r.name|escape}</span>
										<form method="post" action="{$Path}zhl-certificates-admin.php" class="d-inline">
											{csrf_token}
											<input type="hidden" name="action" value="remove_resource">
											<input type="hidden" name="type_id" value="{$t.id}">
											<input type="hidden" name="resource_id" value="{$r.resource_id}">
											<button class="btn btn-outline-danger btn-sm py-0">×</button>
										</form>
									</li>
								{/foreach}
							</ul>
						{else}
							<p class="text-muted small">Noch keine Geräte zugeordnet.</p>
						{/if}
						<form method="post" action="{$Path}zhl-certificates-admin.php" class="input-group input-group-sm">
							{csrf_token}
							<input type="hidden" name="action" value="add_resource">
							<input type="hidden" name="type_id" value="{$t.id}">
							<select class="form-select" name="resource_id" required>
								<option value="">Gerät hinzufügen…</option>
								{foreach from=$AllResources item=ar}<option value="{$ar.resource_id}">{$ar.label|escape}</option>{/foreach}
							</select>
							<button class="btn btn-outline-primary">+</button>
						</form>
					</div>

					{* Zuweisungen *}
					<div class="col-md-6">
						<h6 class="text-muted">Nutzer mit diesem Zertifikat</h6>
						{if $t.grants}
							<ul class="list-group list-group-flush mb-2">
								{foreach from=$t.grants item=g}
									<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
										<span>
											{$g.name|escape} <span class="text-muted small">{$g.email|escape}</span>
											{if $g.expires_at}<br><span class="small {if $g.expired}text-danger{else}text-muted{/if}">bis {$g.expires_at|escape}{if $g.expired} (abgelaufen){/if}</span>{else}<br><span class="small text-muted">unbegrenzt</span>{/if}
										</span>
										<form method="post" action="{$Path}zhl-certificates-admin.php" class="d-inline" onsubmit="return confirm('Zuweisung entfernen?');">
											{csrf_token}
											<input type="hidden" name="action" value="revoke">
											<input type="hidden" name="grant_id" value="{$g.id}">
											<button class="btn btn-outline-danger btn-sm py-0">×</button>
										</form>
									</li>
								{/foreach}
							</ul>
						{else}
							<p class="text-muted small">Noch niemandem zugewiesen.</p>
						{/if}
						<form method="post" action="{$Path}zhl-certificates-admin.php" class="row g-1">
							{csrf_token}
							<input type="hidden" name="action" value="grant">
							<input type="hidden" name="type_id" value="{$t.id}">
							<div class="col-9"><input class="form-control form-control-sm" name="user_ref" placeholder="E-Mail / Benutzername / ID" required></div>
														<div class="col-3"><button class="btn btn-primary btn-sm w-100" title="Zertifikat zuweisen (gilt 1 Jahr)">Zuweisen</button></div>
						</form>
					</div>
				</div>
			</div>
			<div class="card-footer d-flex gap-2 justify-content-end">
				<form method="post" action="{$Path}zhl-certificates-admin.php" class="d-inline">
					{csrf_token}<input type="hidden" name="action" value="toggle_type"><input type="hidden" name="type_id" value="{$t.id}">
					<button class="btn btn-outline-secondary btn-sm">{if $t.active}Verbergen{else}Sichtbar machen{/if}</button>
				</form>
				<form method="post" action="{$Path}zhl-certificates-admin.php" class="d-inline" onsubmit="return confirm('Zertifikat „{$t.name|escape}" wirklich löschen? Alle Zuweisungen gehen verloren.');">
					{csrf_token}<input type="hidden" name="action" value="delete_type"><input type="hidden" name="type_id" value="{$t.id}">
					<button class="btn btn-outline-danger btn-sm">Löschen</button>
				</form>
			</div>
		</div>
	{foreachelse}
		<div class="alert alert-info">Noch keine Zertifikate. Lege oben das erste an.</div>
	{/foreach}

</div>

{include file='globalfooter.tpl'}
