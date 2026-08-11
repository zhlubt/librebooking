{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
      <div class="ph-text">
        <h1>Rückgaben</h1>
        <div class="ph-sub">Was kommt zurück, von wem, und was ist überfällig.</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="{$Path}zhl-medienmanager-ausleihen.php">Ausleihen</a>
        <a class="btn btn-light" href="{$Path}zhl-handover-admin.php">Persönliche Übergaben</a>
        <a class="btn btn-light" href="{$Path}zhl-dashboard.php">Dashboard</a>
      </div>
    </div>

    {if $OverdueTotal > 0}
      <div class="card" style="margin-bottom:18px;border-color:#f3c98b">
        <div class="card-head" style="background:#fff8ec">
          <span class="ch-icon" style="color:#b06a00"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 17h.01"/></svg></span>
          <h2>Überfällig</h2>
          <span class="ch-right muted">{$OverdueTotal}</span>
        </div>
        <div class="card-body" style="padding:0">
          <table class="ztable">
            <thead><tr>
              <th>Soll-Rückgabe</th><th>Vorhaben</th><th>Ausleihende:r</th><th>Zuständig</th><th>Ort</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            {foreach from=$Overdue item=row}
              <tr>
                <td style="font-size:13px">{$row.dateTimeLabel|escape} Uhr</td>
                <td>
                  <a href="{$Path}zhl-buchung-admin.php?ref={$row.ref|escape:'url'}" style="color:inherit"><strong>{$row.title|escape}</strong></a>
                  {if $row.kind == 'bundle'}<span class="badge badge-info">Bundle · {$row.deviceCount} Geräte</span>{/if}
                  <div class="muted" style="font-size:13px">{if isset($row.devicesFull)}{foreach from=$row.devicesFull item=dev name=devs}<a href="{$Path}zhl-geraet-admin.php?rid={$dev.id}" style="color:inherit;text-decoration:underline">{$dev.name|escape}</a>{if !$smarty.foreach.devs.last}, {/if}{/foreach}{else}{$row.devicesLabel|escape}{/if}</div>
                  {if $row.selfReturn}<div style="font-size:13px"><span class="badge badge-info">selbst abgelegt{if $row.selfReturnLocation != ''} · {$row.selfReturnLocation|escape}{/if}</span></div>{/if}
                </td>
                <td>{if $row.borrower != ''}{$row.borrower|escape}{else}<span class="muted">unbekannt</span>{/if}</td>
                <td style="font-size:13px">
                  {if $row.staffName}{$row.staffName|escape}{if $row.staffRoleLabel} <span class="muted">({$row.staffRoleLabel|escape})</span>{/if}{elseif $row.staffRoleLabel}{$row.staffRoleLabel|escape}{else}<span class="muted">—</span>{/if}
                </td>
                <td style="font-size:13px">{if $row.location != ''}{$row.location|escape}{else}<span class="muted">—</span>{/if}</td>
                <td><span class="badge badge-warn">Überfällig</span></td>
                <td class="text-end">
                  {if $row.hasSingleHandoverRow}
                    <a class="btn btn-light" href="{$Path}zhl-handover-check.php?ref={$row.ref|escape:'url'}&amp;token={$row.handoverToken|escape:'url'}&amp;type=return&amp;resource={$row.handoverResourceId}">Bestätigen</a>
                  {else}
                    <a class="btn btn-light" href="{$Path}zhl-handover-admin.php?ref={$row.ref|escape:'url'}">Details</a>
                  {/if}
                </td>
              </tr>
            {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    {/if}

    <div class="seg" role="tablist">
      <a href="{$Path}zhl-medienmanager.php?days=7"{if $Days == 7} class="active"{/if}>7 Tage</a>
      <a href="{$Path}zhl-medienmanager.php?days=14"{if $Days == 14} class="active"{/if}>14 Tage</a>
      <a href="{$Path}zhl-medienmanager.php?days=30"{if $Days == 30} class="active"{/if}>30 Tage</a>
      <span class="cnt" style="margin-left:auto;padding:6px 14px">{$RangeLabel|escape} · {$Total} Rückgabe{if $Total != 1}n{/if}</span>
    </div>

    {if $Total == 0}
      <div class="info-box">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 10 10.5 15.5 8 13"/></svg></span>
        <span>In den nächsten {$Days} Tagen stehen keine Rückgaben an.</span>
      </div>
    {else}
      {foreach from=$Groups item=day}
        <div class="card" style="margin-bottom:18px">
          <div class="card-head">
            <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <h2>{$day.weekday|escape}, {$day.dateLabel|escape}</h2>
            {if $day.tag}<span class="badge badge-ok">{$day.tag|escape}</span>{/if}
            <span class="ch-right muted">{$day.items|@count}</span>
          </div>
          <div class="card-body" style="padding:0">
            {foreach from=$day.byLocation item=loc}
              <div style="padding:10px 24px;background:#f7fafc;font-weight:600;font-size:13px;color:#4a5568">{$loc.label|escape} ({$loc.items|@count})</div>
              <table class="ztable">
                <thead><tr>
                  <th>Zeit</th><th>Vorhaben</th><th>Ausleihende:r</th><th>Zuständig</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                {foreach from=$loc.items item=row}
                  <tr>
                    <td>{$row.timeLabel|escape} Uhr</td>
                    <td>
                      <a href="{$Path}zhl-buchung-admin.php?ref={$row.ref|escape:'url'}" style="color:inherit"><strong>{$row.title|escape}</strong></a>
                      {if $row.kind == 'bundle'}<span class="badge badge-info">Bundle · {$row.deviceCount} Geräte</span>{/if}
                      <div class="muted" style="font-size:13px">{if isset($row.devicesFull)}{foreach from=$row.devicesFull item=dev name=devs}<a href="{$Path}zhl-geraet-admin.php?rid={$dev.id}" style="color:inherit;text-decoration:underline">{$dev.name|escape}</a>{if !$smarty.foreach.devs.last}, {/if}{/foreach}{else}{$row.devicesLabel|escape}{/if}</div>
                      {if $row.selfReturn}<div style="font-size:13px"><span class="badge badge-info">selbst abgelegt{if $row.selfReturnLocation != ''} · {$row.selfReturnLocation|escape}{/if}</span></div>{/if}
                    </td>
                    <td>{if $row.borrower != ''}{$row.borrower|escape}{else}<span class="muted">unbekannt</span>{/if}</td>
                    <td style="font-size:13px">
                      {if !$row.needsPersonal}<span class="muted">—</span>{elseif $row.staffName}{$row.staffName|escape}{if $row.staffRoleLabel} <span class="muted">({$row.staffRoleLabel|escape})</span>{/if}{elseif $row.staffRoleLabel}{$row.staffRoleLabel|escape}{else}<span class="muted">—</span>{/if}
                    </td>
                    <td><span class="badge {$row.badgeClass|escape}">{$row.statusLabel|escape}</span></td>
                    <td class="text-end">
                      {if $row.needsPersonal && $row.statusKey != 'done'}
                        {if $row.hasSingleHandoverRow}
                          <a class="btn btn-light" href="{$Path}zhl-handover-check.php?ref={$row.ref|escape:'url'}&amp;token={$row.handoverToken|escape:'url'}&amp;type=return&amp;resource={$row.handoverResourceId}">Bestätigen</a>
                        {else}
                          <a class="btn btn-light" href="{$Path}zhl-handover-admin.php?ref={$row.ref|escape:'url'}">Details</a>
                        {/if}
                      {/if}
                    </td>
                  </tr>
                {/foreach}
                </tbody>
              </table>
            {/foreach}
          </div>
        </div>
      {/foreach}
    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
