{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
      <div class="ph-text">
        <h1>Persönliche Übergaben</h1>
        <div class="ph-sub">Abhol-, Rückgabe- und Einführungstermine mit persönlicher Übergabe koordinieren.</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="{$Path}zhl-medienmanager-ausleihen.php">Ausleihen</a>
        <a class="btn btn-light" href="{$Path}zhl-medienmanager.php">Rückgaben</a>
        <a class="btn btn-light" href="{$Path}zhl-dashboard.php">Dashboard</a>
      </div>
    </div>

    <form method="get" action="{$Path}zhl-handover-admin.php" class="card" style="margin-bottom:18px">
      <div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
        <div>
          <label style="font-size:12.5px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Status</label>
          <select name="status" style="padding:8px 10px;border:1px solid var(--line);border-radius:8px">
            <option value=""{if $Filters.status == ''} selected{/if}>Alle</option>
            <option value="requested"{if $Filters.status == 'requested'} selected{/if}>Angefragt</option>
            <option value="confirmed"{if $Filters.status == 'confirmed'} selected{/if}>Terminiert</option>
            <option value="done"{if $Filters.status == 'done'} selected{/if}>Erledigt</option>
            <option value="cancelled"{if $Filters.status == 'cancelled'} selected{/if}>Storniert</option>
          </select>
        </div>
        <div>
          <label style="font-size:12.5px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Typ</label>
          <select name="type" style="padding:8px 10px;border:1px solid var(--line);border-radius:8px">
            <option value=""{if $Filters.type == ''} selected{/if}>Alle</option>
            <option value="pickup"{if $Filters.type == 'pickup'} selected{/if}>Abholung</option>
            <option value="return"{if $Filters.type == 'return'} selected{/if}>Rückgabe</option>
            <option value="einf"{if $Filters.type == 'einf'} selected{/if}>Einführung</option>
          </select>
        </div>
        {if $StaffOptions|@count > 0}
        <div>
          <label style="font-size:12.5px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Zuständig</label>
          <select name="staff" style="padding:8px 10px;border:1px solid var(--line);border-radius:8px">
            <option value=""{if $Filters.staff == ''} selected{/if}>Alle</option>
            {foreach from=$StaffOptions item=s}
              <option value="{$s.id}"{if $Filters.staff == $s.id} selected{/if}>{$s.label|escape}</option>
            {/foreach}
          </select>
        </div>
        {/if}
        <div>
          <label style="font-size:12.5px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Referenznummer</label>
          <input type="text" name="ref" value="{$Filters.ref|escape}" placeholder="z. B. 6a5757e8…" style="padding:8px 10px;border:1px solid var(--line);border-radius:8px;width:200px">
        </div>
        <label style="display:flex;align-items:center;gap:6px;padding-bottom:9px">
          <input type="checkbox" name="upcoming" value="1"{if $Filters.upcoming} checked{/if}>
          <span style="font-size:14px">Nur offene (nicht erledigt)</span>
        </label>
        <button type="submit" class="btn btn-primary">Filtern</button>
        <a class="btn btn-light" href="{$Path}zhl-handover-admin.php">Zurücksetzen</a>
      </div>
    </form>

    <div class="seg" role="tablist" style="justify-content:flex-end">
      <span class="cnt" style="padding:6px 14px">{$Total} Eintrag{if $Total != 1}e{/if}</span>
    </div>

    {if $Total == 0}
      <div class="info-box">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
        <span>Keine Übergaben für diese Filter.</span>
      </div>
    {else}
      <div class="card"><div class="card-body" style="padding:0">
        <table class="ztable">
          <thead><tr>
            <th>Typ</th><th>Gerät</th><th>Vorgang</th><th>Termin</th><th>Ausleihende:r</th><th>Zuständig</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
          {foreach from=$Rows item=row}
            <tr>
              <td><span class="badge {if $row.type == 'return'}badge-info{elseif $row.type == 'einf'}badge-warn{else}badge-muted{/if}">{$row.typeLabel|escape}</span></td>
              <td>{$row.resourceName|escape}</td>
              <td style="font-size:13px">{$row.ref|escape}</td>
              <td style="font-size:13px">{$row.scheduledLocal|escape} Uhr</td>
              <td>{if $row.borrower}{$row.borrower|escape}{else}<span class="muted">unbekannt</span>{/if}</td>
              <td style="font-size:13px">{if $row.staffLabel}{$row.staffLabel|escape}{else}<span class="muted">—</span>{/if}</td>
              <td><span class="badge {$row.badgeClass|escape}">{$row.statusLabel|escape}</span></td>
              <td class="text-end">
                <a class="btn btn-light" href="{$Path}zhl-handover-check.php?ref={$row.ref|escape:'url'}&amp;token={$row.token|escape:'url'}&amp;type={$row.type|escape:'url'}&amp;resource={$row.resourceId}">Protokoll</a>
                {if $row.resourceId > 0}<a class="btn btn-light" href="{$Path}zhl-resource-qr.php?resource={$row.resourceId}">QR</a>{/if}
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div></div>
    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
