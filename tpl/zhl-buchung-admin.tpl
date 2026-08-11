{include file='globalheader.tpl'}
{cssfile src="css/zhl-landing.css"}

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
      <div class="ph-text">
        <h1>{if $Title != ''}{$Title|escape}{else}Buchung{/if}</h1>
        <div class="ph-sub">Buchungsakte — Referenz {$Ref|escape}</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="{$Path}zhl-medienmanager-ausleihen.php">Ausleihen</a>
        <a class="btn btn-light" href="{$Path}zhl-medienmanager.php">Rückgaben</a>
        <a class="btn btn-light" href="{$Path}zhl-dashboard.php">Dashboard</a>
      </div>
    </div>

    {if $EndedFlash}
      <div class="info-box" style="border-color:#bfe3d2">
        <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 10 10.5 15.5 8 13"/></svg></span>
        <span>Ausleihe beendet — die Geräte sind wieder buchbar.</span>
      </div>
    {/if}
    {if $EndedError}
      <div class="info-box" style="border-color:var(--err-bd)">
        <span>Die Ausleihe konnte nicht beendet werden. Bitte erneut versuchen.</span>
      </div>
    {/if}

    {if $Mode == 'cancelled'}
      <div class="info-box" style="border-color:var(--err-bd);margin-bottom:18px">
        <span>Diese Buchung wurde am {$CancelledLabel|escape} Uhr <strong>storniert</strong>. Angezeigt wird der gespeicherte Schnappschuss.</span>
      </div>
      <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>Stornierte Buchung</h2></div>
        <div class="card-body" style="padding:0">
          <table class="ztable">
            <tbody>
              <tr><td class="muted" style="width:220px">Ausleihende:r</td><td>{if $Borrower != ''}{$Borrower|escape}{if $Email != ''} <span class="muted">· <a href="mailto:{$Email|escape}" style="color:inherit">{$Email|escape}</a></span>{/if}{else}<span class="muted">unbekannt</span>{/if}</td></tr>
              <tr><td class="muted">Zeitraum</td><td>{if $StartLabel != ''}{$StartLabel|escape} – {$EndLabel|escape} Uhr{else}<span class="muted">—</span>{/if}</td></tr>
              <tr><td class="muted">Geräte</td><td>{if $DeviceNames != ''}{$DeviceNames|escape}{else}<span class="muted">—</span>{/if}</td></tr>
              <tr><td class="muted">Storniert am</td><td>{$CancelledLabel|escape} Uhr</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    {else}

      <div class="card" style="margin-bottom:18px">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
          <h2>Eckdaten</h2>
          <span class="ch-right"><span class="badge {$BadgeClass|escape}">{$StateLabel|escape}</span></span>
        </div>
        <div class="card-body" style="padding:0">
          <table class="ztable">
            <tbody>
              <tr><td class="muted" style="width:220px">Ausleihende:r</td><td>{$Borrower|escape}{if $Email != ''} <span class="muted">· <a href="mailto:{$Email|escape}" style="color:inherit">{$Email|escape}</a></span>{/if}</td></tr>
              {if $Phone != ''}<tr><td class="muted">Telefon</td><td>{$Phone|escape}</td></tr>{/if}
              {if $Organization != ''}<tr><td class="muted">Organisation</td><td>{$Organization|escape}</td></tr>{/if}
              <tr><td class="muted">Zeitraum</td><td>{$StartLabel|escape} – {$EndLabel|escape} Uhr</td></tr>
              {if $ReturnedLabel != ''}<tr><td class="muted">Zurückgegeben</td><td>{$ReturnedLabel|escape} Uhr</td></tr>{/if}
              <tr><td class="muted">Gebucht am</td><td>{$CreatedLabel|escape} Uhr</td></tr>
              {if $Beschreibung != ''}<tr><td class="muted">Beschreibung</td><td>{$Beschreibung|sanitize_rich_text}</td></tr>{/if}
              {if $StateLabel == 'aktiv'}
                <tr><td class="muted">Aktion</td><td>
                  <form method="post" onsubmit="return confirm('Diese Ausleihe jetzt als zurückgegeben markieren und beenden? Die Geräte werden sofort wieder buchbar.');">
                    {csrf_token}
                    <input type="hidden" name="ref" value="{$Ref|escape}">
                    <button type="submit" class="btn btn-outline btn-sm">Ausleihe beenden</button>
                  </form>
                </td></tr>
              {/if}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/></svg></span>
          <h2>Geräte</h2>
          <span class="ch-right muted">{$Devices|@count}</span>
        </div>
        <div class="card-body" style="padding:0">
          <table class="ztable">
            <thead><tr><th>Gerät</th><th>Einführung</th></tr></thead>
            <tbody>
            {foreach from=$Devices item=dev}
              <tr>
                <td><a href="{$Path}zhl-geraet-admin.php?rid={$dev.resourceId}" style="color:inherit;text-decoration:underline"><strong>{$dev.name|escape}</strong></a></td>
                <td><span class="badge {$dev.einfBadge|escape}">{$dev.einfLabel|escape}</span></td>
              </tr>
            {/foreach}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
          <h2>Übergabetermine</h2>
          <span class="ch-right muted">{$Termine|@count}</span>
        </div>
        <div class="card-body" style="padding:0">
          {if $Termine|@count > 0}
            <table class="ztable">
              <thead><tr><th>Art</th><th>Gerät</th><th>Termin</th><th>Status</th><th>Zuständig</th></tr></thead>
              <tbody>
              {foreach from=$Termine item=t}
                <tr>
                  <td>{$t.typeLabel|escape}</td>
                  <td style="font-size:13px">{if $t.resourceName != ''}{$t.resourceName|escape}{else}<span class="muted">ganze Buchung</span>{/if}</td>
                  <td style="font-size:13px">{if $t.terminLabel != ''}{$t.terminLabel|escape} Uhr{else}<span class="muted">—</span>{/if}</td>
                  <td><span class="badge {$t.statusBadge|escape}">{$t.statusLabel|escape}</span></td>
                  <td style="font-size:13px">{if $t.staff != ''}{$t.staff|escape}{else}<span class="muted">—</span>{/if}</td>
                </tr>
              {/foreach}
              </tbody>
            </table>
          {else}
            <div class="info-box" style="margin:14px">
              <span>Keine Übergabetermine hinterlegt.</span>
            </div>
          {/if}
        </div>
      </div>

    {/if}

  </div>
</main>

{include file='globalfooter.tpl'}
