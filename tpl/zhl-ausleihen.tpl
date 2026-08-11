{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/></svg></span>
      <div class="ph-text">
        <h1>Ausleihen</h1>
        <div class="ph-sub">Was geht demnächst raus, an wen, und wer ist zuständig.</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="{$Path}zhl-medienmanager.php">Rückgaben</a>
        <a class="btn btn-light" href="{$Path}zhl-handover-admin.php">Persönliche Übergaben</a>
        <a class="btn btn-light" href="{$Path}zhl-dashboard.php">Dashboard</a>
      </div>
    </div>

    <div class="seg" role="tablist">
      <a href="{$Path}zhl-medienmanager-ausleihen.php?filter=upcoming&days=7"{if $Filter == 'upcoming' && $Days == 7} class="active"{/if}>Kommend: 7d</a>
      <a href="{$Path}zhl-medienmanager-ausleihen.php?filter=upcoming&days=14"{if $Filter == 'upcoming' && $Days == 14} class="active"{/if}>Kommend: 14d</a>
      <a href="{$Path}zhl-medienmanager-ausleihen.php?filter=upcoming&days=30"{if $Filter == 'upcoming' && $Days == 30} class="active"{/if}>Kommend: 30d</a>
      <a href="{$Path}zhl-medienmanager-ausleihen.php?filter=this_week"{if $Filter == 'this_week'} class="active"{/if}>Diese Woche</a>
      <a href="{$Path}zhl-medienmanager-ausleihen.php?filter=active"{if $Filter == 'active'} class="active"{/if}>Derzeit ausgeliehen</a>
      <span class="cnt" style="margin-left:auto;padding:6px 14px">{$RangeLabel|escape} · {$Total} Ausleihe{if $Total != 1}n{/if}</span>
    </div>

    {if $EndedFlash}
      <div class="info-box" style="border-color:#bfe3d2">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 10 10.5 15.5 8 13"/></svg></span>
        <span>Ausleihe beendet — das Gerät ist wieder buchbar.</span>
      </div>
    {/if}
    {if $EndedError}
      <div class="info-box" style="border-color:var(--err-bd)">
        <span>Die Ausleihe konnte nicht beendet werden. Bitte erneut versuchen.</span>
      </div>
    {/if}

    {if $Total == 0}
      <div class="info-box">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 10 10.5 15.5 8 13"/></svg></span>
        <span>In den nächsten {$Days} Tagen stehen keine Abholungen an.</span>
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
            <table class="ztable">
              <thead><tr>
                <th>Zeit</th><th>Vorhaben</th><th>Ausleihende:r</th><th>Zuständig</th><th>Ort</th><th>Status</th>
              </tr></thead>
              <tbody>
              {foreach from=$day.items item=row}
                <tr>
                  <td>{$row.timeLabel|escape} Uhr</td>
                  <td>
                    <a href="{$Path}zhl-buchung-admin.php?ref={$row.ref|escape:'url'}" style="color:inherit"><strong>{$row.title|escape}</strong></a>
                    {if $row.kind == 'bundle'}
                      <span class="badge badge-info">Bundle · {$row.deviceCount} Geräte</span>
                    {/if}
                    <div class="muted" style="font-size:13px">{if isset($row.devicesFull)}{foreach from=$row.devicesFull item=dev name=devs}<a href="{$Path}zhl-geraet-admin.php?rid={$dev.id}" style="color:inherit;text-decoration:underline">{$dev.name|escape}</a>{if !$smarty.foreach.devs.last}, {/if}{/foreach}{else}{$row.devicesLabel|escape}{/if}</div>
                    {if $row.einfuehrungNoetig}<div style="font-size:13px"><span class="badge badge-warn">Einführung nötig</span></div>{/if}
                  </td>
                  <td>{if $row.borrower != ''}{$row.borrower|escape}{else}<span class="muted">unbekannt</span>{/if}</td>
                  <td style="font-size:13px">
                    {if !$row.needsPersonal}
                      <span class="muted">—</span>
                    {elseif $row.staffName}
                      {$row.staffName|escape}{if $row.staffRoleLabel} <span class="muted">({$row.staffRoleLabel|escape})</span>{/if}
                    {elseif $row.staffRoleLabel}
                      {$row.staffRoleLabel|escape}
                    {else}
                      <span class="muted">—</span>
                    {/if}
                  </td>
                  <td style="font-size:13px">{if $row.location != ''}{$row.location|escape}{else}<span class="muted">—</span>{/if}</td>
                  <td>
                    <span class="badge {$row.badgeClass|escape}">{$row.statusLabel|escape}</span>
                    {if $Filter == 'active'}
                      <form method="post" style="margin-top:8px" onsubmit="return confirm('Diese Ausleihe jetzt als zurückgegeben markieren und beenden? Es wird die gesamte Buchung beendet — bei Bundle-Buchungen werden alle enthaltenen Geräte sofort wieder buchbar.');">
                        {csrf_token}
                        <input type="hidden" name="ref" value="{$row.ref|escape}">
                        <button type="submit" class="btn btn-outline btn-sm">Ausleihe beenden</button>
                      </form>
                    {/if}
                  </td>
                </tr>
              {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      {/foreach}
    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
